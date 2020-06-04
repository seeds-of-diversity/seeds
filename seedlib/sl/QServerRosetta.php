<?php

/* QServerRosetta
 *
 * Copyright 2020 Seeds of Diversity Canada
 *
 * Search and manage plant species and cultivars names
 */

include_once( SEEDLIB."sl/QServerSLCollectionReports.php" );

include_once( SEEDLIB."sl/sldb.php" );

class QServerRosetta extends SEEDQ
{
    private $oSLDB;

    function __construct( SEEDAppSessionAccount $oApp, $raConfig = [] )
    {
        parent::__construct( $oApp, $raConfig );
        $this->oSLDB = new SLDBRosetta( $oApp );
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

        if( ($kfr = $this->oSLDB->GetKFRC('PxS', "P.name LIKE '%$dbSrch%'")) ) {
            while( $kfr->CursorFetch() ) {
                $raOut[$kfr->Expand("[[S_psp]]|[[name]]")] = $this->QCharsetFromLatin(
                    ['kPcv'            => $kfr->Value('_key'),
                     'sSpecies'        => $kfr->Value('S_name_en'),
                      'about_cultivar' => $kfr->Value('packetLabel')
                    ] );
            }
        }

        ksort($raOut);
        $bOk = true;

        done:
        return( [$bOk,$raOut,$sErr] );
    }

    private function cultivarOverview( $parms )
    /******************************************
        Get a summary of all (public/office) information that we know about a cultivar.
     */
    {

        $bOk = false;
        $raOut = [];
        $sErr = "";

        if( !($kPcv = intval(@$parms['kPcv'])) ) {
            $sErr = "No kPcv";
            goto done;
        }

        $bKlugeSrccv = ($kPcv > 10000000);  // kPcv-10000000 is the key of sl_cv_sources containing the (fk_sl_species,ocv) to report on
        $bKlugeMSD = ($kPcv < 0);           // -kPcv is the key of SEEDBasket_Products containing (species,cultivar) to report on
        $bOffice = $this->oApp->sess->TestPermRA( ["W SLRosetta", "A SL", "|"] );

        if( $bKlugeSrccv ) {
            /* Referring to a non-indexed cultivar in sl_cv_sources, all we can do is find the companies that list it there and try to find
             * the same name in MSD.
             */
            $raOut = $this->cultivarOverviewKlugeSrccv( $kPcv - 10000000 );
        } else if( $bKlugeMSD ) {
            /* Referring to a non-indexed cultivar in MSD, all we can do is find the information there and try to find the same name in sl_cv_sources.
             */
            $raOut = $this->cultivarOverviewKlugeMSD( -$kPcv );
        } else {
            if( !($kfr = $this->oSLDB->GetKFR('PxS', $kPcv )) ) {
                $sErr = "Unknown kPcv";
                goto done;
            }

            // Get IxAxPxS information for this cultivar, including adoption status
            $o = new QServerSLCollectionReports( $this->oApp );
            $rQ = $o->Cmd('collreport-cultivarinfo', ['kPcv'=>$kPcv, 'kCollection'=>1] );
            if( $rQ['bOk'] ) {
                $raOut['PxS'] = $rQ['raOut']['PxS'];
                $raOut['raIxA'] = $rQ['raOut']['raIxA'];
                $raOut['fAdoption'] = $rQ['raOut']['fAdoption'];
            }

            // Get synonyms
            $raOut['PY'] = [];
            foreach( $this->oSLDB->GetList('PY', "fk_sl_pcv='$kPcv'", ['sSortCol'=>'name']) as $ra ) {
                $raOut['PY'] = ['name' => $ra['name']];
            }



            $ra['nAcc']    = $this->oApp->kfdb->Query1( "SELECT count(*) FROM seeds.sl_accession WHERE _status='0' AND fk_sl_pcv='$kPcv'" );
            $ra['nAdopt']  = $this->oApp->kfdb->Query1( "SELECT count(*) FROM seeds.sl_adoption WHERE _status='0' AND fk_sl_pcv='$kPcv'" );
            $ra['nDesc']   = $this->oApp->kfdb->Query1( "SELECT count(*) FROM seeds.sl_varinst WHERE _status='0' AND fk_sl_pcv='$kPcv'" );

            $ra['nSrcCv1'] = $this->oApp->kfdb->Query1( "SELECT count(*) FROM seeds.sl_cv_sources WHERE _status='0' AND fk_sl_pcv='$kPcv' AND fk_sl_sources='1'" );
            $ra['nSrcCv2'] = $this->oApp->kfdb->Query1( "SELECT count(*) FROM seeds.sl_cv_sources WHERE _status='0' AND fk_sl_pcv='$kPcv' AND fk_sl_sources='2'" );
            $ra['nSrcCv3'] = $this->oApp->kfdb->Query1( "SELECT count(*) FROM seeds.sl_cv_sources WHERE _status='0' AND fk_sl_pcv='$kPcv' AND fk_sl_sources>='3'" );

            $ra['nTotal'] = $ra['nAcc'] + $ra['nAdopt'] + $ra['nDesc'] +
                            $ra['nSrcCv1'] + $ra['nSrcCv2'] + $ra['nSrcCv3'];
        }

        $bOk = true;

        done:
        return( [$bOk,$raOut,$sErr] );
    }


}
