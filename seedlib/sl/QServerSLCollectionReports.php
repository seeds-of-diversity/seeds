<?php

/* Ultimately this factors with QServerRosetta::cultivarOverview except sometimes you want narrow information on many cultivars
 * and sometimes you want all information on one cultivar.
 *
 * Although this appears to be a reporting tool just for the Collection, its only use-case is a general overview that should include Srccv and MSD,
 * and at sufficient depth and narrowness, also SrccvArchive and MSDArchive.
 *
 * So this should be remodelled to go to varying depths of detail, expanded to include Srccv(Archive) and MSD(Archive) and implemented for
 * individual cultivar reporting, and list reporting for certain lists (adopted varieties, given species, etc).
 */


/* QServerSLCollectionReports
 *
 * Copyright 2017-2020 Seeds of Diversity Canada
 *
 * Serve reports about sl_collection, sl_accession, sl_adoption, etc
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

        $cmd = strtolower($cmd);

        /* Permissions:
         *
         * collreport-- and above require W or A permission.
         *
         * collreport- are publicly accessible because this is used to show public information about our Seed Library.
         *             But some internal information is only returned if the user explicitly has R permission.
         */

// also check per-collection RWA permission

        if( SEEDCore_StartsWith( $cmd, 'collreport--' ) ) {
            list($bAccess,$rQ['sErr']) = $this->oApp->sess->CheckPerms( $cmd, 'SLCollectionReport', "Seed Collection Report" );
            if( !$bAccess ) goto done;
            $bCanReadInternal = true;
            $rQ['bHandled'] = true;
        } else
        if( SEEDCore_StartsWith( $cmd, 'collreport-' ) ) {
            $bCanReadInternal = $this->oApp->sess->TestPerm( 'SLCollectionReport', 'R' );
            $rQ['bHandled'] = true;
        } else {
            goto done;
        }

        switch( $cmd ) {
            // list of info for all cultivars - use collreport-cultivarinfo for one kPcv
            case 'collreport-cultivarsummary':
            case 'collreport-cultivarsummaryunioncsci':
                if( !$this->normalizeParms( $parms, "kCollection", $rQ['sErr'] ) ) {
                    goto done;
                }
                list($rQ['bOk'],$rQ['raOut']) = $this->cultivarSummary( $parms['kCollection'],
                                                                        strtolower($cmd)=='collreport-cultivarsummaryunioncsci',
                                                                        $bCanReadInternal );
                $rQ['raMeta']['title'] = "Summary of All Varieties";
                $rQ['raMeta']['name'] = $cmd;
                break;

            case 'collreport-adoptedsummary':
                if( !$this->normalizeParms( $parms, "kCollection", $rQ['sErr'] ) ) {
                    goto done;
                }
                list($rQ['bOk'],$rQ['raOut']) = $this->adoptedSummary( $parms['kCollection'], $bCanReadInternal );
                $rQ['raMeta']['title'] = "Summary of Adopted Varieties";
                $rQ['raMeta']['name'] = $cmd;
                break;

            case 'collreport-germsummary':
                if( !$this->normalizeParms( $parms, "kCollection", $rQ['sErr'] ) ) {
                    goto done;
                }
                list($rQ['bOk'],$rQ['raOut']) = $this->germSummary( $parms['kCollection'], $bCanReadInternal );
                $rQ['raMeta']['title'] = "Germination Tests";
                $rQ['raMeta']['name'] = $cmd;
                break;

            // info for one cultivar
            case 'collreport-cultivarinfo':
                if( !$this->normalizeParms($parms, "kCollection kPcv", $rQ['sErr']) ) {
                    goto done;
                }
                $rQ['raOut'] = $this->getInvDetailsForPCV($parms['kPcv'], $parms['kCollection'], true, $bCanReadInternal, true );
                $rQ['bOk'] = true;
                $rQ['raMeta']['title'] = "Cultivar Information";
                $rQ['raMeta']['name'] = $cmd;
                break;

            default:
                break;
        }

        done:
        return( $rQ );
    }

    private function normalizeParms( &$parms, $sRequired, &$sErr )
    {
        $ok = true;

        foreach( explode(' ', $sRequired) as $p ) {
            switch( $p ) {
                case 'kCollection':
                    if( !($parms['kCollection'] = intval(@$parms['kCollection'])) ) {
                        $sErr = "No collection specified";
                        $ok = false;
                        goto done;
                    }
                    break;
                case 'kPcv':
                    if( !($parms['kPcv'] = intval(@$parms['kPcv'])) ) {
                        $sErr = "No kPcv specified";
                        $ok = false;
                        goto done;
                    }
                    break;
            }
        }

        done:
        return( $ok );
    }

    private function cultivarSummary( $kCollection, $bUnionCSCI, $bCanReadInternal )
    /*******************************************************************************
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
                    $raOut[] = $this->getDetailsForPCV( $ra, $kCollection, true, $bCanReadInternal );
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
            // Get the pcv of every variety in the specified collection by grouping IxAxPxS by P,S, then compute details for each pcv
            $raRows = $this->oSLDB->GetList(
                            "IxAxPxS",
                            "I.fk_sl_collection='$kCollection' AND NOT I.bDeAcc",
                            ['sGroupAliases' => 'P__key,P_name,S_name_en,S_name_fr,S_psp,S__key',
                             'sSortCol' => 'S.psp,P.name'] );

            foreach( $raRows as $ra ) {
                $raOut[] = $this->getDetailsForPCV( $ra, $kCollection, true, $bCanReadInternal );
            }
        }

        $bOk = true;

        done:
        return( [$bOk, $raOut] );
    }

    private function getDetailsForPCV( $raPCV, $kCollection, $bComputeAdoption, $bCanReadInternal )
    {
        $raOut = [];

        // Get the most recent harvest date and total weight of each pcv
        $ra = $this->getInvDetailsForPCV( $raPCV['P__key'], $kCollection, $bComputeAdoption, $bCanReadInternal );
        $yNewest = $ra['yNewest'];
        $nWeightTotal = $ra['nWeightTotal'];
        $sNotes = $bCanReadInternal ? $ra['sNotes'] : "";
        $fAdoption = $ra['fAdoption'];  // ok to show the amount publicly

        // Get the number of csci companies that have the given pcv
        $nCSCI = $this->oApp->kfdb->Query1( "SELECT count(*) FROM seeds.sl_cv_sources WHERE _status='0' AND fk_sl_pcv='{$raPCV['P__key']}' AND fk_sl_sources>='3'" );

        $raOut = ['cv'          => $raPCV['P__key'],
                  'species'     => $this->QCharSetFromLatin( $raPCV['S_psp'] ),
                  'cultivar'    => $this->QCharSetFromLatin( $raPCV['P_name'] ),
                  'csci_count'  => $nCSCI,
                  'adoption'    => $bComputeAdoption ? $fAdoption : $raPCV['amount'],    // could be pre-computed or not
                  'year_newest' => $yNewest,
                  'total_grams' => $nWeightTotal,
                  'notes'       => $sNotes,           // already QCharset in the method that aggregates it
                 ];

        return( $raOut );
    }

    private function getInvDetailsForPCV( $kPCV, $kCollection, $bAdoption, $bCanReadInternal, $bFullDetails = false )
    /****************************************************************************************************************
        Report details about the IxA inventory for the given (pcv,collection), plus some aggregate information e.g. adoption status

        Return:
            yNewest      = year of the newest non-bDeAcc accession
            nWeightTotal = sum of lot weights for this pcv
            fAdoption    = sum of adoption amounts for this pcv
            sNotes       = summary of lot notes (if $bCanReadInternal)

            $bFullDetails:
            raIxA        = list of non-bDeAcc IxA for this pcv
            PxS          = the PxS record for this pcv
     */
    {
        $raOut = [ 'yNewest' => 0,
                   'nWeightTotal' => 0.0,
                   'fAdoption' => 0.0,
                   'sNotes' => "",
                   'raIxA' => [],
                   'PxS' => []
                 ];

        /* Get PxS record if reporting full details (other clients either don't use it or they got here from a list of P,S anyway.
         * Note that everything returned by this method has QCharset, so avoid double-converting by clients.
         */
        if( $bFullDetails ) {
            $o = new SLDBRosetta($this->oApp);
            if( ($kfr = $o->GetKFR('PxS', $kPCV)) ) {
                $raOut['PxS'] = $this->QCharsetFromLatin( ['kPcv'          => $kPCV,
                                                           'P_name'        => $kfr->Value('name'),
                                                           'P_packetLabel' => $kfr->Value('packetLabel'),
                                                           'P_notes'       => ($bCanReadInternal ? $kfr->Value('notes') : ""),
                                                           'kSp'           => $kfr->Value('fk_sl_species'),
                                                           'S_psp'         => $kfr->Value('S_psp'),
                                                           'S_name_en'     => $kfr->Value('S_name_en'),
                                                           'S_name_fr'     => $kfr->Value('S_name_fr'),
                                                           'S_name_bot'    => $kfr->Value('S_name_bot'),
                                                          ] );
            }
        }

        /* Get all IxA for this cultivar.
         * Count the total weight and find the newest lot.
         * Also record the weight and year of the lots in reverse chronological order. This is hard because the year can come from
         * two different places, so store array( '0year i'=>weight, ... ) where i is a unique number, then sort, then unpack.
         */
        $i = 0;
        $raNotes = array();
        $kfrcI = $this->oSLDB->GetKFRC( "IxA", "A.fk_sl_pcv='$kPCV' AND I.fk_sl_collection='$kCollection' AND NOT I.bDeAcc" );
        while( $kfrcI && $kfrcI->CursorFetch() ) {
            // don't report zero-weight lots publicly
            if( !$bCanReadInternal && $kfrcI->Value('g_weight')==0 ) continue;

            // sometimes these fields contain a date and sometimes just the year. Mysql doesn't allow dates to just be years, so these are plain strings.
            $yHarvested = intval(substr($kfrcI->Value('A_x_d_harvest'),0,4));
            $yReceived  = intval(substr($kfrcI->Value('A_x_d_received'),0,4));
            $y = $yHarvested or $y = $yReceived;
            $raOut['yNewest'] = max( $raOut['yNewest'], $y );

            $g = intval($kfrcI->Value('g_weight')*100.0)/100.0;     // round to 0.01
            $raOut['nWeightTotal'] += $g;

            if( $bFullDetails ) {
                /* Note that everything returned by this method has QCharset, so avoid double-converting by clients.
                 */
                $raOut['raIxA'][] = $this->QCharsetFromLatin(
                            ['inv_number' => $kfrcI->Value('inv_number'),
                             'g_weight'   => $g,
                             'year_harvested' => $yHarvested,
                             'year_received'  => $yReceived,
                             'notes' => ($bCanReadInternal ? trim($kfr->Expand("[[notes]] [[A_notes]]")) : ""),
                            ]);
            }

            // don't show notes publicly
            if( $bCanReadInternal ) $raNotes["0$y $i"] = $g;    // this will ksort by year, and you can get the year with intval() even if $y is 0
            $i++;
        }
        krsort($raNotes);
        foreach( $raNotes as $y => $g ) {
            $y = intval($y);
            $raOut['sNotes'] .= ($raOut['sNotes'] ? " | " : "")."$g g from ".($y ? $y : "unknown year");
        }

        // It's okay to show the adoption amount publicly. Adoption info only makes sense for kCollection==1
        if( $bAdoption && $kCollection == 1 ) {
            $raOut['fAdoption'] = $this->oApp->kfdb->Query1( "SELECT SUM(amount) FROM seeds.sl_adoption WHERE fk_sl_pcv='$kPCV' AND _status='0'" );
        }

        /* This method returns everything with QCharset, so avoid double-converting in client code.
         */
        $raOut['sNotes'] = $this->QCharSetFromLatin( $raOut['sNotes'] );

        return( $raOut );
    }


    private function adoptedSummary( $kCollection, $bCanReadInternal )
    {
        $bOk = false;
        $raOut = array();

        if( !$bCanReadInternal ) {
            /* It's okay to show the sum(D.amount) publicly.
             * Any information about donors could be confidential though (or they might have given permission).
             */
        }

        // Get the pcv of every adopted variety
        $raDRows = $this->oApp->kfdb->QueryRowsRA( "SELECT P._key as P__key,P.name as P_name,S.psp as S_psp,SUM(D.amount) as amount "
                                             ."FROM seeds.sl_adoption D,seeds.sl_pcv P,seeds.sl_species S "
                                             ."WHERE D.amount AND D.fk_sl_pcv=P._key AND P.fk_sl_species=S._key "
                                             ."AND D._status='0' AND P._status='0' AND S._status='0' "
                                             ."GROUP BY P._key ORDER BY S.psp,P.name" );

        foreach( $raDRows as $ra ) {
            $raOut[] = $this->getDetailsForPCV( $ra, $kCollection, false, $bCanReadInternal );
        }

        $bOk = true;

        done:
        return( array($bOk, $raOut) );
    }

    private function germSummary( $kCollection, $bCanReadInternal )
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
