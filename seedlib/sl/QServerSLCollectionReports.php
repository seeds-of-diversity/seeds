<?php

/* QServerSLCollectionReports
 *
 * Copyright 2017-2020 Seeds of Diversity Canada
 *
 * Serve reports about sl_collection, sl_accession, sl_adoption, etc
 * This is intended mainly for internal use, so permissions are restricted to SoD personnel.
 */

include_once( SEEDLIB."sl/sldb.php" );

class QServerSLCollectionReports extends SEEDQ
{
    private $oSLDB;

    function __construct( SEEDAppSessionAccount $oApp, $raConfig = [] )
    {
        parent::__construct( $oApp, $raConfig );
        $this->oSLDB = new SLDBCollection( $oApp );
    }

    function Cmd( $cmd, $parms )
    {
        $raParms = array();

        $rQ = $this->GetEmptyRQ();

        /* These commands are intended mainly for internal use, so permissions are restricted to SoD personnel.
         */
        list($bAccess,$rQ['sErr']) = $this->oApp->sess->CheckPerms( $cmd, 'SLCollectionReport', "Seed Collection Report" );
// also check per-collection RWA permission
        if( !$bAccess ) goto done;

        switch( strtolower($cmd) ) {
            case 'collreport-cultivarsummary':
            case 'collreport-cultivarsummaryunioncsci':
                if( !($kCollection = intval(@$parms['kCollection'])) ) {
                    $rQ['sErr'] = "No collection specified";
                    goto done;
                }
                list($rQ['bOk'],$rQ['raOut']) = $this->cultivarSummary( $kCollection, strtolower($cmd)=='collreport-cultivarsummaryunioncsci' );
                $rQ['raMeta']['title'] = "Summary of All Varieties";
                $rQ['raMeta']['name'] = "collreport-cultivar-summary";
                break;

            case 'collreport-adoptedsummary':
                if( !($kCollection = intval(@$parms['kCollection'])) ) {
                    $rQ['sErr'] = "No collection specified";
                    goto done;
                }
                list($rQ['bOk'],$rQ['raOut']) = $this->adoptedSummary( $kCollection );
                $rQ['raMeta']['title'] = "Summary of Adopted Varieties";
                $rQ['raMeta']['name'] = "collreport-adopted-summary";
                break;

            case 'collreport-germsummary':
                if( !($kCollection = intval(@$parms['kCollection'])) ) {
                    $rQ['sErr'] = "No collection specified";
                    goto done;
                }
                list($rQ['bOk'],$rQ['raOut']) = $this->germSummary( $kCollection );
                $rQ['raMeta']['title'] = "Germination Tests";
                $rQ['raMeta']['name'] = "collreport-germ-summary";
                break;

            default:
                break;
        }

        done:
        return( $rQ );
    }

    private function cultivarSummary( $kCollection, $bUnionCSCI )
    /************************************************************
        Get a summary of information on all cultivars in the given Seed Library Collection.
        If bUnionCSCI get all varieties in the csci and left-join that with the collection information.
     */
    {
        $bOk = false;
        $raOut = array();
        $raRows = [];

        if( $bUnionCSCI ) {
            // Every sl_cv_sources that is in sl_pcv or sl_pcv_syn should have an fk_sl_pcv.
            // Every sl_cv_sources should have an fk_sl_species.
            // So get every P._key,P.name,S.name_en,S.name_fr,S.psp,S._key from sl_pcv UNION (those equivalents from sl_cv_sources where fk_sl_pcv=0)
            if( ($kfrc = $this->oSLDB->GetKFRC('PxS')) ) {
                while( $kfrc->CursorFetch() ) {
                    $raRows[$kfrc->Value('S_psp').'|'.$kfrc->Value('name')] = [
                        'P__key' => $kfrc->Value('_key'),
                        'P_name' => $kfrc->Value('name'),
                        'S_name_en' => $kfrc->Value('S_name_en'),
                        'S_name_fr' => $kfrc->Value('S_name_fr'),
                        'S_psp' => $kfrc->Value('S_psp'),
                        'S__key' => $kfrc->Value('S__key'),
                    ];
                }
            }

            // Now get every psp/cv from sl_cv_sources where fk_sl_pcv=0 and add those to the list
            if( ($dbc = $this->oApp->kfdb->CursorOpen(
                    "SELECT osp,ocv,S.name_en as S_name_en,S.name_fr as S_name_fr,S.psp as S_psp,S._key as S__key,count(*) as c "
                   ."FROM seeds.sl_cv_sources SrcCV LEFT JOIN seeds.sl_species S ON (SrcCV.fk_sl_species=S._key) "
                   ."WHERE SrcCV.fk_sl_sources>='3' AND SrcCV._status='0' AND SrcCV.fk_sl_pcv='0' "
                   ."GROUP BY osp,ocv,S.name_en,S.name_fr,S.psp,S._key")) )
            {
                while( $ra = $this->oApp->kfdb->CursorFetch($dbc) ) {
                    $sp = @$ra['S_psp'] ?: $ra['osp'];
                    $raRows[$sp.'|'.$ra['ocv']] = [
                        'P__key' => 0,
                        'P_name' => $ra['ocv'],
                        'S_name_en' => $ra['S_name_en'],
                        'S_name_fr' => $ra['S_name_fr'],
                        'S_psp' => $sp,
                        'S__key' => $ra['S__key'],
                        'nCSCI' => $ra['c']
                    ];
                }
            }

            ksort($raRows);

            // Process the list
            foreach( $raRows as $ra ) {
                if( $ra['P__key'] ) {
                    // this row came from the Seed Library Collection
                    $raOut[] = $this->getDetailsForPCV( $ra, $kCollection, true );
                } else {
                    // this row came from the csci where fk_sl_pcv=0
/*
                    $nCSCI = $this->oQ->kfdb->Query1( "SELECT count(*) FROM seeds.sl_cv_sources "
                                                     ."WHERE _status='0' AND fk_sl_sources>='3' "
                                                     ."AND (fk_sl_species='".intval($ra['S__key'])."' OR osp='".addslashes($ra['S_psp'])."') "
                                                     ."AND ocv='".addslashes($ra['P_name'])."'" );
*/

                    $raOut[] = [
                        'cv'          => 0,
                        'species'     => $ra['S_psp'],
                        'cultivar'    => $this->QCharSetFromLatin($ra['P_name']),
                        'csci_count'  => $ra['nCSCI'],
                        'adoption'    => '',
                        'year_newest' => '',
                        'total_grams' => '',
                        'notes'       => ''
                    ];
                }
            }

        } else {
            // Get the pcv of every variety in the specified collection
            $raRows = $this->oSLDB->GetList(
                            "IxAxPxS",
                            "I.fk_sl_collection='$kCollection' AND NOT I.bDeAcc",
                            ['sGroupAliases' => 'P__key,P_name,S_name_en,S_name_fr,S_psp,S__key',
                             'sSortCol' => 'S.psp,P.name'] );

            foreach( $raRows as $ra ) {
                $raOut[] = $this->getDetailsForPCV( $ra, $kCollection, true );
            }
        }

        $bOk = true;

        done:
        return( [$bOk, $raOut] );
    }

    private function getDetailsForPCV( $raPCV, $kCollection, $bComputeAdoption )
    {
        $raOut = [];

        // Get the most recent harvest date and total weight of each pcv
        list($yNewest,$nWeightTotal,$sNotes,$fAdoption) = $this->getInvDetailsForPCV( $raPCV['P__key'], $kCollection, $bComputeAdoption );

        // Get the number of csci companies that have the given pcv
        $nCSCI = $this->oApp->kfdb->Query1( "SELECT count(*) FROM seeds.sl_cv_sources WHERE _status='0' AND fk_sl_pcv='{$raPCV['P__key']}' AND fk_sl_sources>='3'" );

        $raOut = [
                'cv'          => $raPCV['P__key'],
                'species'     => $raPCV['S_psp'],
                'cultivar'    => $this->QCharSetFromLatin($raPCV['P_name']),
                'csci_count'  => $nCSCI,
                'adoption'    => $bComputeAdoption ? $fAdoption : $raPCV['amount'],    // could be pre-computed or not
                'year_newest' => $yNewest,
                'total_grams' => $nWeightTotal,
                'notes'       => $sNotes,
        ];

        return( $raOut );
    }

//TODO: make this a cmd so Rosetta can get this info
    private function getInvDetailsForPCV( $kPCV, $kCollection, $bAdoption )
    /**********************************************************************
        Get some reporting details about the SL inventory for the given pcv
     */
    {
        $kfrcI = $this->oSLDB->GetKFRC( "IxA", "A.fk_sl_pcv='$kPCV' AND I.fk_sl_collection='$kCollection' AND NOT I.bDeAcc" );

        $yNewest = 0;
        $nWeightTotal = 0.0;
        $sNotes = "";
        $raNotes = array();
        $fAdoption = 0.0;

        /* Count the total weight and find the newest lot.
         * Also record the weight and year of the lots in reverse chronological order. This is hard because the year can come from
         * two different places, so store array( '0year i'=>weight, ... ) where i is a unique number, then sort, then unpack.
         */
        $i = 0;
        while( $kfrcI->CursorFetch() ) {
            // sometimes these fields contain a date and sometimes just the year. Mysql doesn't allow dates to just be years, so these are plain strings.
            $y = intval(substr($kfrcI->Value('A_x_d_harvest'),0,4)) or $y = intval(substr($kfrcI->Value('A_x_d_received'),0,4));
            if( $y > $yNewest )  $yNewest = $y;

            $g = intval($kfrcI->Value('g_weight')*100.0)/100.0;
            $nWeightTotal += $g;

            $raNotes["0$y $i"] = $g;    // this will ksort by year, and you can get the year with intval() even if $y is 0
            $i++;
        }
        krsort($raNotes);
        foreach( $raNotes as $y => $g ) {
            $y = intval($y);
            $sNotes .= ($sNotes ? " | " : "")."$g g from ".($y ? $y : "unknown year");
        }

        if( $bAdoption ) {
            // this is independent of kCollection, and really should only be used if kCollection==1
            $fAdoption = $this->oApp->kfdb->Query1( "SELECT SUM(amount) FROM seeds.sl_adoption WHERE fk_sl_pcv='$kPCV' AND _status='0'" );
        }

        return( [$yNewest,$nWeightTotal,$sNotes, $fAdoption] );
    }


    private function adoptedSummary( $kCollection )
    {
        $bOk = false;
        $raOut = array();

        // Get the pcv of every adopted variety
        $raDRows = $this->oApp->kfdb->QueryRowsRA( "SELECT P._key as P__key,P.name as P_name,S.psp as S_psp,SUM(D.amount) as amount "
                                             ."FROM seeds.sl_adoption D,seeds.sl_pcv P,seeds.sl_species S "
                                             ."WHERE D.amount AND D.fk_sl_pcv=P._key AND P.fk_sl_species=S._key "
                                             ."AND D._status='0' AND P._status='0' AND S._status='0' "
                                             ."GROUP BY P._key ORDER BY S.psp,P.name" );

        foreach( $raDRows as $ra ) {
            $raOut[] = $this->getDetailsForPCV( $ra, $kCollection, false );
        }

        $bOk = true;

        done:
        return( array($bOk, $raOut) );
    }

    private function germSummary( $kCollection )
    {
        $bOk = false;
        $raOut = [];

        // Get a record for every lot in the collection that has had a germ test
        $raIRows = $this->oSLDB->GetList(
                        "IxGxAxPxS",
                        "I.fk_sl_collection='$kCollection' AND NOT I.bDeAcc",
                        [ 'sGroupAliases' => "S_name_en, S_name_fr, S_psp, S__key, P_name, P__key, _key, inv_number, g_weight",
                          'sSortCol' => 'S.psp,P.name,I._key' ] );

        // Get the germ test information for each lot
        $c = 0;
        foreach( $raIRows as $raI ) {
            $sNotes = "";
            $raGRows = $this->oSLDB->GetList( "G", "G.fk_sl_inventory='{$raI['_key']}'", ['sSortCol'=>"dStart",'bSortDown'=>true] );
            foreach( $raGRows as $raG ) {
                $sNotes .= ($sNotes ? " | " : "")."{$raG['nGerm']} % from {$raG['nSown']} seeds tested {$raG['dStart']} to {$raG['dEnd']}";
            }

            $raOut[] = array(
                    'cv'          => $raI['P__key'],
                    'species'     => $raI['S_psp'],
                    'cultivar'    => $this->QCharSetFromLatin($raI['P_name']),
                    'lot'         => $raI['inv_number'],
                    'g_weight'    => $raI['g_weight'],
                    'tests'       => $sNotes
            );
        }

        $bOk = true;

        done:
        return( [$bOk, $raOut] );
    }
}
