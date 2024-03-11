<?php

/* QServerRosetta
 *
 * Copyright 2020 Seeds of Diversity Canada
 *
 * Search and manage plant species and cultivar names
 */

include_once( SEEDLIB."sl/QServerSLCollectionReports.php" );

include_once( SEEDLIB."sl/sldb.php" );

class QServerRosetta extends SEEDQ
{
    private $oSLDB;
    private $oSLDBSrc;

    function __construct( SEEDAppSessionAccount $oApp, $raConfig = [] )
    {
        parent::__construct( $oApp, $raConfig );
        $this->oSLDB = new SLDBRosetta( $oApp );
        $this->oSLDBSrc = new SLDBSources( $oApp );
    }

    function Cmd( $cmd, $parms )
    {
        $raParms = array();

        $rQ = $this->GetEmptyRQ();

        // Read cmds are open source data. Check perms for Write and Admin cmds.
        if( SEEDCore_StartsWith( $cmd, 'rosetta--' ) ) {
            list($bAccess,$rQ['sErr']) = $this->oApp->sess->CheckPerms( $cmd, 'SLRosetta', "Seed Library Rosetta" );
            if( !$bAccess ) goto done;
        }

        if( SEEDCore_StartsWith( $cmd, 'rosetta-' ) ) {
            $rQ['bHandled'] = true;
        } else {
            goto done;
        }

        switch( strtolower($cmd) ) {
            case 'rosetta-cultivarsearch':
                list($rQ['bOk'],$rQ['raOut'],$rQ['sErr']) = $this->cultivarSearch( $parms );
                break;
            // this is more general than Rosetta but so far there is no better place to put it
            case 'rosetta-cultivaroverview':
                list($rQ['bOk'],$rQ['raOut'],$rQ['sErr']) = $this->cultivarOverview( $parms );
                break;
        }

        done:
        return( $rQ );
    }

    private function cultivarSearch( $parms )
    /****************************************
        Find all cultivar names that are LIKE '%{$parms['sSrch']%'

        Return [ 'psp|cvname' => [ sl_pcv._key, sl_species.name_en, about_cultivar ], ... ]
        sorted by psp|cvname.

        First add all matching sl_pcv and sl_pcv_syn, with about_cultivar = the seed label description
        Then add all matching sl_cv_sources.ocv that are not in sl_pcv or sl_pcv_syn (short cut: where sl_cv_sources.fk_sl_pcv==0)
        Then add all matching cultivar names in seed exchange, with about_cultivar = a description for this cv taken at random
            - if already in the list but about_cultivar is blank, put a description there
     */
    {
        $bOk = false;
        $raOut = [];
        $sErr = "";

        if( !($dbSrch = addslashes(@$parms['sSrch'])) ) {
            $sErr = "No search parameter";
            goto done;
        }

        /* Find sl_pcv whose names match dbSrch
         */
        if( ($kfr = $this->oSLDB->GetKFRC('PxS', "P.name LIKE '%$dbSrch%'")) ) {
            while( $kfr->CursorFetch() ) {
// QCharSetFromLatin the keys too
                $raOut[$kfr->Expand("[[S_psp]]|[[name]]")] = $this->QCharsetFromLatin(
                    ['kPcv'            => $kfr->Value('_key'),
                     'sSpecies'        => $kfr->Value('S_name_en'),
                     'about_cultivar' => $kfr->Value('packetLabel')
                    ] );
            }
        }

        /* Add sl_pcv_syn whose names match dbSrch. Return (name=syn.name, kPcv=fk_sl_pcv)
         */
        if( ($kfr = $this->oSLDB->GetKFRC('PYxPxS', "PY.name LIKE '%$dbSrch%'")) ) {
            while( $kfr->CursorFetch() ) {
                $raOut[$kfr->Expand("[[S_psp]]|[[name]]")] = $this->QCharsetFromLatin(
                    ['kPcv'            => $kfr->Value('P__key'),
                     'sSpecies'        => $kfr->Value('S_name_en'),
                     'about_cultivar' => $kfr->Value('P_packetLabel')
                    ] );
            }
        }

        /* Add sl_cv_sources whose ocv match dbSrch and fk_sl_pcv=0. Return (name=ocv, kPcv=sl_cv_sources._key + 10000000)
         */
        if( ($kfr = $this->oSLDBSrc->GetKFRC('SRCCVxS', "SRCCV.ocv LIKE '%$dbSrch%' AND SRCCV.fk_sl_pcv='0' AND SRCCV.fk_sl_sources>='3'")) ) {
            while( $kfr->CursorFetch() ) {
                $raOut[$kfr->Expand("[[S_psp]]|[[ocv]]")] = $this->QCharsetFromLatin(
                    ['kPcv'            => $kfr->Value('_key') + 10000000,
                     'sSpecies'        => $kfr->Value('S_name_en'),
                     'about_cultivar' => "",//$kfr->Value('P_packetLabel')
                    ] );
            }
        }

        /* Add MSD cultivars whose names match dbSrch (and fk_sl_pcv=0). Return (name=cultivar, kPcv=SEEDBasket_Product._key * -1)
         */


        ksort($raOut);
        $bOk = true;

        done:
        return( [$bOk,$raOut,$sErr] );
    }

    private function cultivarOverview( $parms )
    /******************************************
        Get a summary of all (public/office) information that we know about a cultivar.

        parms:
            kPcv = key of sl_pcv, or kluge sl_cv_sources, or kluge SEEDBasket_Products

        output:
            PxS       = portions of the PxS relation (if cultivar indexed in sl_pcv)
            raIxA     = array of IxA relations for Lots in collection (if cultivar in collection #1)
            fAdoption = adoption amount
            raSrc     = list of seed company sources
            raProfile = crop profile info
            raMSE     = current MSE listings

            todo:
            raSrc_archive = old seed company sources
            raMSE_archive = old MSE listings
     */
    {
        $bOk = false;
        $raOut = ['PxS'=>[], 'raIxA'=>[], 'fAdoption'=>0, 'raSrc'=>[], 'raProfile'=>[], 'raMSE'=>[] ];
        $sErr = "";

        if( !($kPcv = intval(@$parms['kPcv'])) ) {
            $sErr = "No kPcv";
            goto done;
        }

        // Get more information if the user is allowed to see it
        $bOffice = $this->oApp->sess->TestPermRA( ["W SLRosetta", "A SL", "|"] );

        $bKlugeSrccv = ($kPcv > 10000000);  // kPcv-10000000 is the key of sl_cv_sources containing the (fk_sl_species,ocv) to report on
        $bKlugeMSD = ($kPcv < 0);           // -kPcv is the key of SEEDBasket_Products containing (species,cultivar) to report on

        if( $bKlugeSrccv ) {
            /* Referring to a non-indexed cultivar in sl_cv_sources, all we can do is find the companies that list it there and try to find
             * the same name in MSD.
             */
            if( !$this->cultivarOverviewKlugeSrccv( $kPcv - 10000000, $bOffice, $raOut ) ) {
                goto done;
            }
        } else if( $bKlugeMSD ) {
            /* Referring to a non-indexed cultivar in MSD, all we can do is find the information there and try to find the same name in sl_cv_sources.
             */
            $raOut = $this->cultivarOverviewKlugeMSD( -$kPcv );
        } else {
            if( !($kfrPxS = $this->oSLDB->GetKFR('PxS', $kPcv )) ) {
                $sErr = "Unknown kPcv";
                goto done;
            }

            /* Seed Library Collection: get IxAxPxS information for this cultivar, including adoption status
             */
            $o = new QServerSLCollectionReports( $this->oApp, $this->raConfig );        // use the same config_bUTF8 parm
            $rQ = $o->Cmd('collreport-cultivarinfo', ['kPcv'=>$kPcv, 'kCollection'=>1] );
            if( $rQ['bOk'] ) {
                $raOut['PxS'] = $rQ['raOut']['PxS'];                // already QCharset
                $raOut['raIxA'] = $rQ['raOut']['raIxA'];
                $raOut['fAdoption'] = $rQ['raOut']['fAdoption'];
            }

            /* Synonyms: get sl_pcv_syn matches
             */
            $raOut['PY'] = [];
            foreach( $this->oSLDB->GetList('PY', "fk_sl_pcv='$kPcv'", ['sSortCol'=>'name']) as $ra ) {
                $raOut['PY'] = ['name' => $ra['name']];
            }

            /* Sources: get seed company sources
             */
            $raOut['raSrc'] = $this->cultivarOverviewGetSources( "fk_sl_pcv='$kPcv'" );

            /* MSE: get current matches in Member Seed Exchange
             */
            $o = new MSDQ( $this->oApp, $this->raConfig );        // use the same config_bUTF8 parm
            $rQ = $o->Cmd('msdSeedList-FindByName', ['species'=>$kfrPxS->Value('S_name_en'), 'cultivar'=>$kfrPxS->Value('name')]);
            if( $rQ['bOk'] )  $raOut['raMSE'] = $rQ['raOut'];

            /* Statistics:
             * If these are used to check for pre-delete referential integrity, it's okay to delete a cultivar if any of these are _status<>0 because the cultivar will
             * also be preserved as _status<>0, retaining referential integrity in Trash.
             */
            $dbname = $this->oApp->DBName('seeds1');
            $raOut['nAcc']    = $this->oApp->kfdb->Query1( "SELECT count(*) FROM $dbname.sl_accession WHERE _status='0' AND fk_sl_pcv='$kPcv'" );
            $raOut['nAdopt']  = $this->oApp->kfdb->Query1( "SELECT count(*) FROM $dbname.sl_adoption WHERE _status='0' AND fk_sl_pcv='$kPcv'" );
            $raOut['nDesc']   = $this->oApp->kfdb->Query1( "SELECT count(*) FROM $dbname.sl_varinst WHERE _status='0' AND fk_sl_pcv='$kPcv'" );

            $raOut['nSrcCv1'] = $this->oApp->kfdb->Query1( "SELECT count(*) FROM $dbname.sl_cv_sources WHERE _status='0' AND fk_sl_pcv='$kPcv' AND fk_sl_sources='1'" );
            $raOut['nSrcCv2'] = $this->oApp->kfdb->Query1( "SELECT count(*) FROM $dbname.sl_cv_sources WHERE _status='0' AND fk_sl_pcv='$kPcv' AND fk_sl_sources='2'" );
            $raOut['nSrcCv3'] = $this->oApp->kfdb->Query1( "SELECT count(*) FROM $dbname.sl_cv_sources WHERE _status='0' AND fk_sl_pcv='$kPcv' AND fk_sl_sources>='3'" );

            $raOut['nTotal'] = $raOut['nAcc'] + $raOut['nAdopt'] + $raOut['nDesc'] +
                               $raOut['nSrcCv1'] + $raOut['nSrcCv2'] + $raOut['nSrcCv3'];
        }

        $bOk = true;

        done:
        return( [$bOk,$raOut,$sErr] );
    }


    private function cultivarOverviewKlugeSrccv( $kSrccv, $bOffice, &$raOut )
    /************************************************************************
        Referring to a cultivar identified by a sl_cv_sources key.

        All we can do is report the sources, and try to look up the name in the MSD.
     */
    {
        $ok = false;

        if( !($kfr = $this->oSLDBSrc->GetKFR( 'SRCCVxS', $kSrccv )) ) {
            goto done;
        }

        $raOut['PxS'] = $this->QCharsetFromLatin(
                        ['kPcv'          => 0,
                         'P_name'        => $kfr->Value('ocv'),
                         'P_packetLabel' => "",
                         'P_notes'       => ($bOffice ? $kfr->Value('notes') : ""),     // these are the sl_cv_sources notes actually
                         'kSp'           => $kfr->Value('S__key'),
                         'S_psp'         => $kfr->Value('S_psp'),
                         'S_name_en'     => $kfr->Value('S_name_en'),
                         'S_name_fr'     => $kfr->Value('S_name_fr'),
                         'S_name_bot'    => $kfr->Value('S_name_bot')
                        ]);

        /* Get a list of sources for this cultivar
         */
        $kSp = $kfr->Value('S__key');
        $dbOcv = addslashes($kfr->Value('ocv'));
        $raOut['raSrc'] = $this->cultivarOverviewGetSources( "fk_sl_species='$kSp' AND ocv='$dbOcv'" );

        $ok = true;

        done:
        return( $raOut );
    }

    private function cultivarOverviewGetSources( $sCond )
    {
        $raSrc = [];

        if( ($kfrcS = $this->oSLDBSrc->GetKFRC('SRCCVxSRC', "$sCond AND fk_sl_sources>='3'", ['sSortCol'=>'SRC.name_en']) ) ) {
            while( $kfrcS->CursorFetch() ) {
                $raSrc[] = $this->QCharsetFromLatin(
                            ['SRC_name_en'=>$kfrcS->Value('SRC_name_en'),
                             'SRC_prov'=>$kfrcS->Value('SRC_prov'),
                             'SRC_web'=>$kfrcS->Value('SRC_web'),
                            ]);
            }
        }
        return( $raSrc );
    }
}
