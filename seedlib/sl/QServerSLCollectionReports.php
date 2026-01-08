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
 * Copyright 2017-2025 Seeds of Diversity Canada
 *
 * Serve reports about sl_collection, sl_accession, sl_adoption, etc
 */

include_once( SEEDLIB."sl/sl_util.php" );
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
            //obsolete - only used by old spreadsheet dump (remove when we no longer need that spreadsheet)
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


            // list of info for all cultivars - use collreport-cultivarinfo for one kPcv
            case 'collreport-cultivarlist_active_lots_combined':
            case 'collreport-cultivarlist_active_lots_combined_unioncsci':
                if( !$this->normalizeParms( $parms, "kCollection", $rQ['sErr'] ) ) {
                    goto done;
                }
                list($rQ['bOk'],$rQ['raOut']) = $this->cultivarList_activeLotsCombined( $parms['kCollection'],
                                                                                        strtolower($cmd)=='collreport-cultivarlist_active_lots_combined_unioncsci',
                                                                                        $bCanReadInternal,
// NormalizeParms modes=>S?
                                                                         $parms['modes'] ?? "");
                $rQ['raMeta']['title'] = "Summary of All Varieties";
                $rQ['raMeta']['name'] = $cmd;
                break;

            case 'collreport-cultivar_adopt_priorities':
                if( !$this->normalizeParms( $parms, "kCollection", $rQ['sErr'] ) ) {
                    goto done;
                }
                list($rQ['bOk'],$rQ['raOut']) = $this->cultivarAdoptPriorities( $parms['kCollection'], $bCanReadInternal );
                $rQ['raMeta']['title'] = "Adoption Priorities";
                $rQ['raMeta']['name'] = $cmd;
                break;

            case 'collreport-cultivar_growout_priorities':
                if( !$this->normalizeParms( $parms, "kCollection", $rQ['sErr'] ) ) {
                    goto done;
                }
                list($rQ['bOk'],$rQ['raOut']) = $this->cultivarGrowoutPriorities( $parms['kCollection'], $bCanReadInternal );
                $rQ['raMeta']['title'] = "Growout Priorities";
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
// deprecate
$rQ['raOut'] = $this->getInvDetailsForPCV($parms['kPcv'], $parms['kCollection'], true, $bCanReadInternal, true );
                if( ($raPxS = $this->oSLDB->GetRecordVals('PxS', $parms['kPcv'])) ) {
                    $rQ['raOut'] = array_merge($rQ['raOut'],$this->getDetails_PCV( $raPxS, $parms['kCollection'], true /*adoption*/, true /*$bGetIxG*/, $bCanReadInternal ), );
                }

                /* format an html table showing the IxA status
this is for sure not the best place to put this
                 */
                $rQ['raOut']['sTable_IxA'] = "<table><tr><th>&nbsp;</th><th>&nbsp;</th><th style='text-align:center'>germ</th><th style='text-align:center'>est viable pops</th></tr>";
                foreach( (@$rQ['raOut']['raIxA'] ?? []) as $kEncodesYear => $raI ) {
                    $sCol1 = "<nobr>{$raI['location']} {$raI['inv_number']}: {$raI['g_weight']} g</nobr>";
                    $sCol2 = ($y = intval($kEncodesYear)) ?: "";
                    $sCol3 = $raI['latest_germtest_date'] ? "<nobr>{$raI['latest_germtest_result']}% on {$raI['latest_germtest_date']}</nobr>" : "";
                    $sCol4 = $raI['pops_estimate'];

                    $rQ['raOut']['sTable_IxA'] .= "<tr><td style='padding:0 1em;border:1px solid #bbb'>$sCol1</td>
                               <td style='padding:0 1em;border:1px solid #bbb'>$sCol2</td>
                               <td style='padding:0 1em;border:1px solid #bbb'>$sCol3</td>
                               <td style='padding:0 1em;border:1px solid #bbb'>$sCol4</td>
                           </tr>";
                }
                $rQ['raOut']['sTable_IxA'] .= "</table>";

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

//put in SEEDQ::NormalizeParms() with raRequired[[parmname=>type]]  where I is required int, I? is optional int, I+ required >0, I+? optional >0
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
                   ."FROM {$this->oApp->DBName('seeds1')}.sl_cv_sources SrcCV LEFT JOIN {$this->oApp->DBName('seeds1')}.sl_species S ON (SrcCV.fk_sl_species=S._key) "
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
                    $nCSCI = $this->oQ->kfdb->Query1( "SELECT count(*) FROM seeds_1.sl_cv_sources "
                                                     ."WHERE _status='0' AND fk_sl_sources>='3' "
                                                     ."AND (fk_sl_species='".intval($ra['S__key'])."' OR osp='".addslashes($ra['S_psp'])."') "
                                                     ."AND ocv='".addslashes($ra['P_name'])."'" );
*/
                    $raOut[] = [
                        'cv'                     => 0,
                        'species'                => $ra['S_psp'],
                        'cultivar'               => $this->QCharSetFromLatin($ra['P_name']),
                        'csci_count'             => $ra['nCSCI'],
                        'adoption'               => '',
                        'year_newest'            => '',
                        'total_grams'            => '',
                        'notes'                  => '',
                        'newest_lot_year'        => '',
                        'newest_lot_grams'       => '',
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
        $nCSCI = $this->oApp->kfdb->Query1( "SELECT count(*) FROM {$this->oApp->DBName('seeds1')}.sl_cv_sources WHERE _status='0' AND fk_sl_pcv='{$raPCV['P__key']}' AND fk_sl_sources>='3'" );

        $raOut = ['cv'          => $raPCV['P__key'],
                  'species'     => $this->QCharSetFromLatin( $raPCV['S_psp'] ),
                  'cultivar'    => $this->QCharSetFromLatin( $raPCV['P_name'] ),
                  'csci_count'  => $nCSCI,
                  'adoption'    => $bComputeAdoption ? $fAdoption : $raPCV['amount'],    // could be pre-computed or not
                  'year_newest' => $yNewest,
                  'total_grams' => $nWeightTotal,
                  'notes'       => $sNotes,           // already QCharset in the method that aggregates it
                  'newest_lot_year'        => $yNewest,
                  'newest_lot_grams'       => $ra['newest_lot_grams'],
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
                   'PxS' => [],
                   'newest_lot_grams'=>0.0,
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
        $kLotNewest = 0;
        $raNotes = array();
        $kfrcI = $this->oSLDB->GetKFRC( "IxA", "A.fk_sl_pcv='$kPCV' AND I.fk_sl_collection='$kCollection' AND NOT I.bDeAcc" );
        while( $kfrcI && $kfrcI->CursorFetch() ) {
            // don't report zero-weight lots publicly
            if( !$bCanReadInternal && $kfrcI->Value('g_weight')==0 ) continue;

            $g = intval($kfrcI->Value('g_weight')*100.0)/100.0;     // round to 0.01
            $raOut['nWeightTotal'] += $g;

            // sometimes these fields contain a date and sometimes just the year. Mysql doesn't allow dates to just be years, so these are plain strings.
            $yHarvested = intval(substr($kfrcI->Value('A_x_d_harvest'),0,4));
            $yReceived  = intval(substr($kfrcI->Value('A_x_d_received'),0,4));
            $y = $yHarvested or $y = $yReceived;
            if( $y > $raOut['yNewest'] ) {
                $kLotNewest = $kfrcI->Key();
                $raOut['yNewest'] = $y;
                $raOut['newest_lot_grams'] = $g;
            }

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
            $raOut['fAdoption'] = $this->oApp->kfdb->Query1( "SELECT SUM(amount) FROM {$this->oApp->DBName('seeds1')}.sl_adoption WHERE fk_sl_pcv='$kPCV' AND _status='0'" );
        }

        if( $kLotNewest ) {
            // Find most recent germ test results for the newest lot
            if( ($kfrG = $this->oSLDB->GetKFRCond( "G", "fk_sl_inventory='$kLotNewest'", ['sSortCol'=>'dStart','bSortDown'=>true] )) &&
                ($nSown = $kfrG->Value('nSown')) )
            {
                $raOut['newest_lot_germ_result'] = $kfrG->Value('nGerm');
                $raOut['newest_lot_germ_year'] = substr($kfrG->Value('dStart'), 0, 4);
            }
        }

        /* This method returns everything with QCharset, so avoid double-converting in client code.
         */
        $raOut['sNotes'] = $this->QCharSetFromLatin( $raOut['sNotes'] );

        return( $raOut );
    }

    private function cultivarList_activeLotsCombined( $kCollection, $bUnionCSCI, $bCanReadInternal, string $modes )
    /**************************************************************************************************************
        Get a summary of information on all cultivars in the given Seed Library Collection.
        If bUnionCSCI get all varieties in the csci and left-join that with the collection information.
     */
    {
        $bOk = false;
        $raOut = array();
        $raRows = [];

        $bGetIxG = strpos($modes, ' raIxG ') !== false;

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
                   ."FROM {$this->oApp->DBName('seeds1')}.sl_cv_sources SrcCV LEFT JOIN {$this->oApp->DBName('seeds1')}.sl_species S ON (SrcCV.fk_sl_species=S._key) "
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
                    $raOut[] = $this->getDetails_PCV( $ra, $kCollection, true /*adoption*/, $bGetIxG, $bCanReadInternal );
                } else {
                    // this row came from the csci where fk_sl_pcv=0
/*
                    $nCSCI = $this->oQ->kfdb->Query1( "SELECT count(*) FROM seeds_1.sl_cv_sources "
                                                     ."WHERE _status='0' AND fk_sl_sources>='3' "
                                                     ."AND (fk_sl_species='".intval($ra['S__key'])."' OR osp='".addslashes($ra['S_psp'])."') "
                                                     ."AND ocv='".addslashes($ra['P_name'])."'" );
*/

                    $raOut[] = [
                        'kPcv'                   => 0,
                        'species'                => $ra['S_psp'],
                        'cultivar'               => $this->QCharSetFromLatin($ra['P_name']),
                        'csci_count'             => $ra['nCSCI'],
                        'adoption'               => '',
                        //'newest_lot_year'        => '',
                        //'newest_lot_grams'       => '',
                        //'newest_lot_germ_result' => '',
                        //'newest_lot_germ_year'   => '',
                        'est_total_viable_grams' => 0,
                        'est_total_viable_seeds' => 0,
                        'est_total_viable_pops'  => 0,
                        'total_grams'            => '',
                        'notes'                  => '',
                        'raIxA'                  => [],
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
                $raOut[] = $this->getDetails_PCV( $ra, $kCollection, true /*adoption*/, $bGetIxG, $bCanReadInternal );
            }
        }

        $bOk = true;

        done:
        return( [$bOk, $raOut] );
    }

    private function getDetails_PCV( $raPCV, $kCollection, $bComputeAdoption, $bGetIxG, $bCanReadInternal )
    {
        $raOut = [];

        // Get the most recent harvest date and total weight of each pcv
        $ra = $this->getLotDetails_PCV( $raPCV['P__key'], $kCollection, $raPCV['S_psp'], $bComputeAdoption, $bGetIxG, $bCanReadInternal );
        $yNewest = $ra['yNewest'];
        $nWeightTotal = $ra['nWeightTotal'];
        $sNotes = $bCanReadInternal ? $ra['sNotes'] : "";
        $fAdoption = $ra['fAdoption'];  // ok to show the amount publicly

        // Get the number of csci companies that have the given pcv
        $nCSCI = $this->oApp->kfdb->Query1( "SELECT count(*) FROM {$this->oApp->DBName('seeds1')}.sl_cv_sources WHERE _status='0' AND fk_sl_pcv='{$raPCV['P__key']}' AND fk_sl_sources>='3'" );

        $raOut = ['kPcv'                   => $raPCV['P__key'],
                  'species'                => $this->QCharSetFromLatin( $raPCV['S_psp'] ),
                  'cultivar'               => $this->QCharSetFromLatin( $raPCV['P_name'] ),
                  'csci_count'             => $nCSCI,
                  'adoption'               => $bComputeAdoption ? $fAdoption : $raPCV['amount'],    // could be pre-computed or not
//                  'newest_lot_year'        => $yNewest,
//                  'newest_lot_grams'       => $ra['newest_lot_grams'],
//                  'newest_lot_germ_result' => $ra['newest_lot_germ_result'],
//                  'newest_lot_germ_year'   => $ra['newest_lot_germ_year'],
                  'total_grams'            => $nWeightTotal,
                  'est_total_viable_grams' => round($ra['total_viable_grams'], 2),
                  'est_total_viable_seeds' => $ra['total_viable_seeds'],
                  'est_total_viable_pops'  => round($ra['total_viable_pops'], 1),
                  'notes'                  => $sNotes,           // already QCharset in the method that aggregates it
                  'raIxA'                  => @$ra['raIxA'] ?? [],
                 ];

        return( $raOut );
    }

    private function getLotDetails_PCV( $kPCV, $kCollection, string $psp, $bAdoption, bool $bGetIxG, $bCanReadInternal, $bFullDetails = false )
    /*****************************************************************************************************************************
        Report details about the IxA inventory for the given (pcv,collection), plus some aggregate information e.g. adoption status

        Return:
            yNewest      = year of the newest non-bDeAcc accession
            newest_lot_grams      = grams of the newest non-bDeAcc accession
            nWeightTotal = sum of lot weights for this pcv
            fAdoption    = sum of adoption amounts for this pcv
            sNotes       = summary of lot notes (if $bCanReadInternal)

            $bFullDetails:
            raIxA        = list of non-bDeAcc IxA for this pcv
            PxS          = the PxS record for this pcv
     */
    {
        $raOut = [ 'yNewest' => 0,
                   'newest_lot_grams' => 0,
                   'newest_lot_germ_result' => '',
                   'newest_lot_germ_year' => '',
                   'nWeightTotal' => 0.0,
                   'fAdoption' => 0.0,
                   'sNotes' => "",
                   'raIxA' => [],
                   'PxS' => [],
                   'total_viable_grams' => 0.0,
                   'total_viable_seeds' => 0,
                   'total_viable_pops'  => 0.0,
                 ];

        /* Get PxS record if reporting full details (other clients either don't use it or they got here from a list of P,S anyway.
         * Note that everything returned by this method has QCharset, so avoid double-converting by clients.
         */
        if( $bFullDetails ) {
// the caller has raPCV which might have all of this already
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
        $kLotNewest = 0;
        $raNotes = array();
        $kfrcI = $this->oSLDB->GetKFRC( "IxA", "A.fk_sl_pcv='$kPCV' AND I.fk_sl_collection='$kCollection' AND NOT I.bDeAcc AND I.g_weight > 0.0" );
        while( $kfrcI && $kfrcI->CursorFetch() ) {
            // don't report zero-weight lots publicly
            if( !$bCanReadInternal && $kfrcI->Value('g_weight')==0 ) continue;

            $g = intval($kfrcI->Value('g_weight')*1000.0)/1000.0;     // round to 0.001
            $raOut['nWeightTotal'] += $g;

            // sometimes these fields contain a date and sometimes just the year. Mysql doesn't allow dates to just be years, so these are plain strings.
            $yHarvested = intval(substr($kfrcI->Value('A_x_d_harvest'),0,4));
            $yReceived  = intval(substr($kfrcI->Value('A_x_d_received'),0,4));
            $y = $yHarvested or $y = $yReceived;
            if( $y > $raOut['yNewest'] ) {
                $kLotNewest = $kfrcI->Key();
                $raOut['yNewest'] = $y;
                $raOut['newest_lot_grams'] = $g;
            }

            // Most recent germ test for this lot
            $dGermLatest = "";
            $nGermLatest = 0;
            $nGermNow = 0.0;
            if( $bGetIxG ) {
                /* Compute the modeled current germ rate, for comparison or use.
                 * See https://docs.google.com/document/d/1xnm7Ylo97jYB8e8SCu_CRjrIGB2UHi9JDOywYm-ZLnw
                 */
                $a = 6;                                                                 // shape parm (6 to 8 seems good)
                $t0 = SEEDCore_StartsWith($kfrcI->Value('location'), 'P') ? 15 : 8;     // age when seeds likely reach zero germ
                $yOrigin = $y;                                                          // year the seeds were harvested
                $ageNow = date('Y')-$y;                                                 // age of seeds now
                $nGermModel = intval((1 - (1 / (1 + exp($a*(0.5 - $ageNow/$t0))))) * 100);

                /* Find the most recent germ test, and recompute current germ rate using that test to determine the viability curve.
                 */
                if( ($kfrG = $this->oSLDB->GetKFRCond('G', "fk_sl_inventory='{$kfrcI->Key()}' AND nSown>0", ['sSortCol'=>'dStart','bSortDown'=>true])) ) {
                    $dGermLatest = $kfrG->Value('dStart');
                    $nGermLatest = $kfrG->Value('nGerm');

                    /* Compute nGermNow using the latest germ test to define the viability curve.
                     * $y is taken to be the year the seeds were harvested
                     * year($dGermLatest) is year of test
                     * Compute t0 in germ model and use that to compute germ rate in year(now)
                     */
                    if( $nGermLatest && $yOrigin && ($yTest = substr($dGermLatest,0,4)) && $ageNow )
                    {
                        $ageTest = $yTest-$yOrigin;
                        if($ageTest <= 0) $ageTest = 0.5;   // prevent t0 from being forced to 0

                        /* The formula for t0 contains three problems:
                         *     fGerm==1.0 causes divide-by-zero inside log()
                         *     2log(...)==a causes divide-by-zero
                         *     fGerm ~ 1.0 the denominator can be negative which makes t0 invalid
                         *
                         * Solution 1 is to require a > 2log(g/(g-1))           we do this below
                         * Solution 2 is to require g < exp(a/2)/(1+exp(a/2))
                         */
                        $fGerm = $nGermLatest/100.0;
                        if( $fGerm > 0.95 ) $a = 7;
                        if( $fGerm > 0.97 ) $a = 8;
                        if( $fGerm > 0.99 ) $fGerm = 0.99;  // and a==8 from above

                        $t0 = 2 * $a * $ageTest / ($a - 2 * log($fGerm / (1.0-$fGerm)));
//if($kfrcI->Value('inv_number')==5043) var_dump($nGermLatest,$yOrigin, $dGermLatest,$ageNow,$t0,$ageTest,$fGerm);
                        $nGermNow = intval((1 - (1 / (1 + exp($a*(0.5 - floatval($ageNow)/$t0))))) * 100);
                    }
                } else {
                    // No germ tests for this lot. Just use the model.
                    $nGermNow = $nGermModel;
                }

                $raOut['total_viable_grams'] += ($g * $nGermNow) / 100.0;
// look up g_100 for lot, another lot of the same pcv, rosetta, etc
                $raOut['total_viable_seeds'] = SLUtil::SeedsFromGrams($raOut['total_viable_grams'], ['g_100'=>0, 'psp'=>$psp]);
                $raOut['total_viable_pops'] = SLUtil::PopsFromSeeds($raOut['total_viable_seeds'], ['psp'=>$psp]);
            }

            if( $bGetIxG || $bFullDetails ) {
                /* Note that everything returned by this method has QCharset, so avoid double-converting by clients.
                 */
                $fGramsViableEstimate = $bGetIxG ? (intval($g * $nGermNow) / 100.0) : 0;
                $nSeedsViableEstimate = SLUtil::SeedsFromGrams($fGramsViableEstimate, ['g_100'=>0, 'psp'=>$psp]);
                $fPopsViableEstimate  = SLUtil::PopsFromSeeds($nSeedsViableEstimate, ['psp'=>$psp]);

                $raOut['raIxA']["0$y $i"] = $this->QCharsetFromLatin(
                            ['inv_number' => $kfrcI->Value('inv_number'),
                             'g_weight'   => $g,
                             'location'   => SEEDCore_StartsWith($kfrcI->Value('location'), 'P') ? 'P' : 'T',
                             'year_harvested' => $yHarvested,
                             'year_received'  => $yReceived,
                             'latest_germtest_date' => $dGermLatest,
                             'latest_germtest_result' => $nGermLatest,
                             'current_germ_estimate' => $nGermNow,
                             'current_germ_model' => $nGermModel,
                             'g_weight_viable_estimate' => $fGramsViableEstimate,
                             'pops_estimate' => $fPopsViableEstimate,
                             'notes' => (($bFullDetails && $bCanReadInternal) ? trim($kfr->Expand("[[notes]] [[A_notes]]")) : ""),
                            ]);
            }

            // don't show notes publicly
            if( $bCanReadInternal ) $raNotes["0$y $i"] = $g;    // this will ksort by year, and you can get the year with intval() even if $y is 0
            $i++;
        }
        krsort($raNotes);
        krsort($raOut['raIxA']);
        foreach( $raNotes as $y => $g ) {
            $y = intval($y);
            $raOut['sNotes'] .= ($raOut['sNotes'] ? " | " : "")."$g g from ".($y ? $y : "unknown year");
        }

        // It's okay to show the adoption amount publicly. Adoption info only makes sense for kCollection==1
        if( $bAdoption && $kCollection == 1 ) {
            $raOut['fAdoption'] = $this->oApp->kfdb->Query1( "SELECT SUM(amount) FROM {$this->oApp->DBName('seeds1')}.sl_adoption WHERE fk_sl_pcv='$kPCV' AND _status='0'" );
        }

        if( $kLotNewest ) {
            // Find most recent germ test results for the newest lot
            if( ($kfrG = $this->oSLDB->GetKFRCond( "G", "fk_sl_inventory='$kLotNewest'", ['sSortCol'=>'dStart','bSortDown'=>true] )) &&
                ($nSown = $kfrG->Value('nSown')) )
            {
                $raOut['newest_lot_germ_result'] = $kfrG->Value('nGerm');
                $raOut['newest_lot_germ_year'] = substr($kfrG->Value('dStart'), 0, 4);
            }
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
                                             ."FROM {$this->oApp->DBName('seeds1')}.sl_adoption D,{$this->oApp->DBName('seeds1')}.sl_pcv P,{$this->oApp->DBName('seeds1')}.sl_species S "
                                             ."WHERE D.amount AND D.fk_sl_pcv=P._key AND P.fk_sl_species=S._key "
                                             ."AND D._status='0' AND P._status='0' AND S._status='0' "
                                             ."GROUP BY P._key ORDER BY S.psp,P.name" );

        foreach( $raDRows as $ra ) {
            $raOut[] = $this->getDetailsForPCV( $ra, $kCollection, false /*adoption*/, $bCanReadInternal );
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

    private function cultivarAdoptPriorities( int $kCollection, bool $bCanReadInternal )
    {
        $bOk = false;
        $raOut = $raOutPartialAdopt = $raNonAdopt = [];

        /* Get overview of all active lots by cultivar, then filter and sort adoption priorities.
         * Remove fully adopted, sort by (partial vs non-adopted, pops/csci_count).
         * This creates two sections (partial, non) each sorted for good pops + rarity at top, poor pops + commonness at bottom.
         */
        list($bOk,$raCV) = $this->cultivarList_activeLotsCombined( $kCollection, false /*bUnionCSCI*/, $bCanReadInternal, " raIxG ");

        foreach($raCV as $ra) {
            if( $ra['adoption'] >= 250 ) continue;

            // array keys are pops/csci_count so krsort makes good pops with low availability come first;  kPcv is for uniqueness; csci+1 prevents div0
            $k = round(floatval($ra['est_total_viable_pops'])/floatval($ra['csci_count']+1), 3)."|".$ra['kPcv'];

            if( $ra['adoption'] > 0 ) {
                // a partial adoption is a priority for filling
                $raOutPartialAdopt[$k] = $ra;
            } else if( $ra['csci_count'] > 0 && $ra['csci_count'] <= 3 && $ra['est_total_viable_pops'] > 3 ) {
                // a variety with a workable pop and low non-zero csci_count is a good priority.
                $raOutNonAdopt[$k] = $ra;
            }
        }

        krsort($raOutPartialAdopt);     // sorting high-low pops/csci_count
        krsort($raOutNonAdopt);
        $raOut = array_merge($raOutPartialAdopt, $raOutNonAdopt);   // partial adoptions first, then non-adopted; since keys are unique the arrays should simply concatenate

        return( [$bOk, $raOut] );
    }

    private function cultivarGrowoutPriorities( int $kCollection, bool $bCanReadInternal )
    {
        $bOk = false;
        $raOut = $raOutAdopted = $raNonAdopted = [];

        /* Get overview of all active lots by cultivar, then filter and sort growout priorities.
         * Remove csci_count>0 and pop>3, sort by (adopted vs non-adopted, population ascending).
         * This creates two sections (adopted, not) each sorted with lowest viable population first.
         */
        list($bOk,$raCV) = $this->cultivarList_activeLotsCombined( $kCollection, false /*bUnionCSCI*/, $bCanReadInternal, " raIxG ");

        foreach($raCV as $ra) {
            if( $ra['csci_count'] > 0 || $ra['est_total_viable_pops'] > 3.0 ) continue;

            // array keys are pops|kPcv to be sortable and unique
            $k = $ra['est_total_viable_pops']."|".$ra['kPcv'];

            if( $ra['adoption'] > 0 ) {
                $raOutAdopted[$k] = $ra;
            } else {
                $raOutNonAdopted[$k] = $ra;
            }
        }

        ksort($raOutAdopted);       // sorting low-high pops
        ksort($raOutNonAdopted);
        $raOut = array_merge($raOutAdopted, $raOutNonAdopted);   // adopted first, then non-adopted; since keys are unique the arrays should simply concatenate

        return( [$bOk, $raOut] );
    }
}
