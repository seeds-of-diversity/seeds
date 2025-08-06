<?php

/* QServerRosetta
 *
 * Copyright 2020 Seeds of Diversity Canada
 *
 * Search and manage plant species and cultivar names
 */

include_once( SEEDLIB."sl/QServerSLCollectionReports.php" );
include_once( SEEDLIB."sl/sldb.php" );
include_once( SEEDLIB."msd/msdq.php" );

class QServerRosetta extends SEEDQ
{
    private $oSLDB;
    private $oSLDBSrc;
    private $oSLDBColl;

    const emptyRAOut = ['PxS'=>[], 'raIxA'=>[], 'fAdoption'=>0, 'raSrc'=>[], 'raProfile'=>[], 'raMSE'=>[] ];

    function __construct( SEEDAppSessionAccount $oApp, $raConfig = [] )
    {
        parent::__construct( $oApp, $raConfig );
        $this->oSLDB = new SLDBRosetta( $oApp );
        $this->oSLDBSrc = new SLDBSources( $oApp );
        $this->oSLDBColl = new SLDBCollection( $oApp );
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
            // get species name by lang, index, etc
            case 'rosetta-spname':          list($rQ['bOk'],$rQ['sOut'],$rQ['sErr']) = $this->spName($parms);     break;

            /* mode=name,  return PxS
             * mode=all,   return PxS, IxA, raSyn, fAdoption, etc
             */
            case 'rosetta-cultivarinfo':    list($rQ['bOk'],$rQ['raOut'],$rQ['sErr']) = $this->cultivarInfo($parms);     break;

            case 'rosetta-cultivarsearch':
                list($rQ['bOk'],$rQ['raOut'],$rQ['sErr']) = $this->cultivarSearch( $parms );
                break;
            // this is more general than Rosetta but so far there is no better place to put it
            case 'rosetta-speciesoverview':
                list($rQ['bOk'],$rQ['raOut'],$rQ['sErr']) = $this->speciesOverview( $parms );
                break;
            case 'rosetta-cultivaroverview':  // deprecate and use rosetta-cultivarinfo, mode=all
                $parms['mode'] = 'all';
                list($rQ['bOk'],$rQ['raOut'],$rQ['sErr']) = $this->cultivarInfo($parms);
                break;
        }

        done:
        return( $rQ );
    }

    private function spName( array $raParms )
    {
        $kSpecies = intval(@$raParms['kSp']);
        $lang = @$raParms['lang'] ?: $this->oApp->lang;

        $s = $sErr = "species $kSpecies not found"; $bOk = false;

        if( $kSpecies && ($kfr = $this->oSLDB->GetKFR('S', $kSpecies)) ) {
            $s = $lang=='EN' ? ($kfr->value('name_en') ?: $kfr->value('name_fr'))
                             : ($kfr->value('name_fr') ?: $kfr->value('name_en'));
            $bOk = true; $sErr = "";
        }
        return( [$bOk,$s,$sErr] );
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

        if( !($dbSrch = addslashes(@$parms['sSrch']??"")) ) {
            $sErr = "No search parameter";
            goto done;
        }

        /* Find sl_pcv whose names match dbSrch
         */
        if( ($kfr = $this->oSLDB->GetKFRC('PxS', "P.name LIKE '%$dbSrch%'")) ) {
            while( $kfr->CursorFetch() ) {
                $raOut[$kfr->Expand("[[S_psp]]|[[name]]")] = $this->QCharsetFromLatin(
                    ['kPcv'            => $kfr->Value('_key'),
                     'psp'             => $kfr->Value('S_psp'),
                     'sSpecies'        => $kfr->Value('S_name_en'),
                     'sCultivar'       => $kfr->Value('name'),
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
                     'psp'             => $kfr->Value('S_psp'),
                     'sSpecies'        => $kfr->Value('S_name_en'),
                     'sCultivar'       => $kfr->Value('P_name'),
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
                     'psp'             => $kfr->Value('S_psp'),
                     'sSpecies'        => $kfr->Value('S_name_en'),
                     'sCultivar'       => $kfr->Value('ocv'),
                     'about_cultivar' => "",//$kfr->Value('P_packetLabel')
                    ] );
            }
        }

        /* Add MSE cultivars whose names match dbSrch (and fk_sl_pcv=0). Return (name=cultivar, kPcv=SEEDBasket_Product._key * -1)
         */
        $raMSE = $this->oApp->kfdb->QueryRowsRA( "SELECT PEspecies.v as sp, PEvariety.v as cv, P._key as k
                                                  FROM SEEDBasket_Products P, SEEDBasket_ProdExtra PEspecies, SEEDBasket_ProdExtra PEvariety
                                                  WHERE P._key=PEspecies.fk_SEEDBasket_Products AND P._key=PEvariety.fk_SEEDBasket_Products AND
                                                        PEspecies.k='species' AND PEvariety.k='variety' and P._status=0 AND
                                                        PEvariety.v like '%$dbSrch%'" );
        foreach($raMSE as $ra) {
            $dbSp = addslashes($ra['sp']);
            if( ($kfr = $this->oSLDB->GetKFRCond('S',"psp='$dbSp' OR name_en='$dbSp'")) ) {  // or other synonyms or language variants
                $raOut["{$kfr->Value('psp')}|{$ra['cv']}"] = $this->QCharset(
                    ['kPcv'            => $ra['k'] * -1,
                     'psp'             => $kfr->Value('psp'),
                     'sSpecies'        => $kfr->Value('name_en'),
                     'sCultivar'       => $ra['cv'],
                     'about_cultivar' => "",//$kfr->Value('P_packetLabel')
                    ] );
            }
        }

        // keys are only for sorting in this function; return an unkeyed array so json_encode(raOut) gives a normal js array
        ksort($raOut);
        $raOut = array_values($raOut);

        $bOk = true;

        done:
        return( [$bOk,$raOut,$sErr] );
    }

    private function speciesOverview( $parms )
    /*****************************************
        Get a summary of all (public/office) information that we know about a species.

        parms:
            kSp = key of sl_species

        output:
            S         = portions of the S relation
            raSY      = array of SY synonyms
            nP        = number of sl_pcv of this sp
            nI        = number of sl_inventory lots of this sp (in all collections)
            nAdopt    = number of adoption records of this sp
            nSrcCv    = number of SrcCv records for this sp
            nSrcCv1   = " for PGRC
            nSrcCv2   = " for NPGS
            nSrcCv3   = " for seed companies
            nProfile  = number of profile records for this sp
            nMSE      = number of MSE listings of this sp

            todo:
            nSrcCv_archive = old seed company sources
            nMSE_archive = old MSE listings
     */
    {
        $bOk = false;
        $raOut = self::emptyRAOut;
        $sErr = "";

        if( !($kSp = intval(@$parms['kSp'])) ) {
            $sErr = "No kSp";
            goto done;
        }

        // Get more information if the user is allowed to see it
        $bCanReadInternal = $this->oApp->sess->TestPermRA( ["W SLRosetta", "A SL", "|"] );

        /* Species: get sl_species data
         */
        if( !($kfrS = $this->oSLDB->GetKFR('S', $kSp)) ) {
            $sErr = "Unknown kPcv";
            goto done;
        }
        $raOut['S'] = $this->QCharsetFromLatin( ['kSp'          => $kSp,
                                                 'name_en'      => $kfrS->Value('name_en'),
                                                 'name_fr'      => $kfrS->Value('name_fr'),
                                                 'iname_en'     => $kfrS->Value('iname_en'),
                                                 'iname_fr'     => $kfrS->Value('iname_fr'),
                                                 'name_bot'     => $kfrS->Value('name_bot'),
                                                 'family_en'    => $kfrS->Value('family_en'),
                                                 'family_fr'    => $kfrS->Value('family_fr'),
                                                 'psp'          => $kfrS->Value('psp'),
                                                 'category'     => $kfrS->Value('category'),
                                                 'notes'        => ($bCanReadInternal ? $kfrS->Value('notes') : ""),
                                                ] );

        /* Synonyms: get sl_species_syn matches
         */
        $raOut['raSY'] = [];
        foreach( $this->oSLDB->GetList('SY', "fk_sl_species='$kSp'", ['sSortCol'=>'name']) as $ra ) {
            $raOut['raSY'][] = ['name' => $ra['name']];
        }

        /* Statistics:
         * If these are used to check for pre-delete referential integrity, it's okay to delete a species if any of these are _status<>0 because the species will
         * also be preserved as _status<>0, retaining referential integrity in Trash.
         */
        $raOut['nP'] = $this->oSLDB->GetCount('P', "fk_sl_species=$kSp");                                   // number of pcv of this species
        $raOut['nI'] = $this->oSLDBColl->GetCount('IxAxP', "P.fk_sl_species=$kSp");                         // number of lots of this species (in all collections)
        $raOut['nAdopt'] = $this->oSLDBColl->GetCount('DxP', "P.fk_sl_species=$kSp");                       // number of adoption records of this species
        $raOut['nSrcCv1'] = $this->oSLDBSrc->GetCount('SRCCV', "fk_sl_species=$kSp AND fk_sl_sources=1");   // for PGRC
        $raOut['nSrcCv2'] = $this->oSLDBSrc->GetCount('SRCCV', "fk_sl_species=$kSp AND fk_sl_sources=2");   // for NPGS
        $raOut['nSrcCv3'] = $this->oSLDBSrc->GetCount('SRCCV', "fk_sl_species=$kSp AND fk_sl_sources>=3");  // for seed companies
        $raOut['nSrcCv']  = $raOut['nSrcCv1'] + $raOut['nSrcCv2'] + $raOut['nSrcCv3'];

        /* MSE: get current matches in Member Seed Exchange
         */
        $raOut['nMSE'] = 0;
        $o = new MSDQ( $this->oApp, $this->raConfig );        // use the same config_bUTF8 parm
// not implemented
        $rQ = $o->Cmd('msdSeedList-CountSpecies', ['psp'=>$kfrS->Value('psp')]);
        if( $rQ['bOk'] )  $raOut['nMSE'] = $rQ['iOut'];

        // todo
        $raOut['nProfile'] = 0;                     // number of profile records for this sp
        $raOut['nMSE_archive'] = 0;                 // number of archived MSE listings of this sp
        $raOut['nSrcCv_archive'] = 0;               // number of archived SrcCv records for this sp

        $raOut['nTotal'] = count($raOut['raSY']) + $raOut['nP'] + $raOut['nI'] + $raOut['nAdopt'] + $raOut['nSrcCv'] + $raOut['nMSE'];
                          // + $raOut['nProfile'] + $raOut['nMSE_archive'] + $raOut['nSrcCv_archive'];

        $bOk = true;

        done:
        return( [$bOk,$raOut,$sErr] );
    }

    private function cultivarInfo( $parms )
    /**************************************
        Get a summary of all (public/office) information that we know about a cultivar.

        parms:
            kPcv = key of sl_pcv, or kluge sl_cv_sources, or kluge SEEDBasket_Products
            mode = name: get PxS
                   all:  get everything

        output:
            PxS       = portions of the PxS relation (if cultivar indexed in sl_pcv)
            raPY      = array of PY synonyms
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
        $raOut = self::emptyRAOut;
        $sErr = "";

        if( !($kPcv = intval(@$parms['kPcv'])) ) {
            $sErr = "No kPcv";
            goto done;
        }

        $eMode = SEEDCore_ArraySmartVal($parms, 'mode', ['all', 'name']);

        // Get more information if the user is allowed to see it
        $bOffice = $this->oApp->sess->TestPermRA( ["W SLRosetta", "A SL", "|"] );

        $bKlugeSrccv = ($kPcv > 10000000);  // kPcv-10000000 is the key of sl_cv_sources containing the (fk_sl_species,ocv) to report on
        $bKlugeMSE = ($kPcv < 0);           // -kPcv is the key of SEEDBasket_Products containing (species,cultivar) to report on

        if( $bKlugeSrccv ) {
            /* Referring to a non-indexed cultivar in sl_cv_sources, all we can do is find the companies that list it there and try to find
             * the same name in MSE.
             */
            $raOut = $this->cultivarInfoKlugeSrccv( $kPcv - 10000000, $bOffice, $raOut );

        } else if( $bKlugeMSE ) {
            /* Referring to a non-indexed cultivar in MSE, all we can do is find the information there and try to find the same name in sl_cv_sources.
             */
            $raOut = $this->cultivarInfoKlugeMSE( -$kPcv );

        } else {
            if( !($kfrPxS = $this->oSLDB->GetKFR('PxS', $kPcv )) ) {
                $sErr = "Unknown kPcv";
                goto done;
            }

            if( $eMode == 'name' ) {
                // just return PxS
                $raOut['PxS'] = $this->QCharsetFromLatin(
                       ['P__key' => $kfrPxS->Value('_key'),
                        'P_name' => $kfrPxS->Value('name'),
                        'S_name_en' => $kfrPxS->Value('S_name_en'),
                        'S_name_fr' => $kfrPxS->Value('S_name_fr'),
                        'S_psp' => $kfrPxS->Value('S_psp'),
                        'S__key' => $kfrPxS->Value('S__key') ]);

                $bOk = true;
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
            $raOut['raPY'] = [];
            foreach( $this->oSLDB->GetList('PY', "fk_sl_pcv='$kPcv'", ['sSortCol'=>'name']) as $ra ) {
                $raOut['raPY'][] = ['name' => $ra['name']];
            }

            /* Sources: get seed company sources
             */
            $raOut['raSrc'] = $this->cultivarInfoGetSources( "fk_sl_pcv='$kPcv'" );

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


    private function cultivarInfoKlugeSrccv( $kSrccv, $bOffice, &$raOut )
    /********************************************************************
        Referring to a cultivar identified by a sl_cv_sources key.

        All we can do is report the sources, and try to look up the name in the MSE.
     */
    {
        $raOut = self::emptyRAOut;

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
        $raOut['raSrc'] = $this->cultivarInfoGetSources( "fk_sl_species='$kSp' AND ocv='$dbOcv'" );

        $raOut['raMSE'] = [];   // TODO: look for the name in MSE

        $ok = true;

        done:
        return( $raOut );
    }

    private function cultivarInfoKlugeMSE( int $kProduct )
    /*****************************************************
        Show the overview of a variety that was found in MSE with no fk_sl_pcv. The key is kluged as one random kProduct of this variety.

        Look up the variety in MSE, and also look for the name in sl_cv_sources
     */
    {
        $raOut = self::emptyRAOut;

        // Lazy way: look up the species and variety by kProduct, then use a deprecated MSDQ cmd to search MSE

        $sp = $this->oApp->kfdb->Query1("SELECT v FROM SEEDBasket_ProdExtra WHERE fk_SEEDBasket_Products='$kProduct' AND k='species'");
        $cv = $this->oApp->kfdb->Query1("SELECT v FROM SEEDBasket_ProdExtra WHERE fk_SEEDBasket_Products='$kProduct' AND k='variety'");
        if( $sp && $cv ) {
            // this should work because they were found when kProduct was determined
            $dbSp = addslashes($sp);
            if( ($kfrS = $this->oSLDB->GetKFRCond('S',"psp='$dbSp' OR name_en='$dbSp'")) ) {
                $raOut['PxS'] = $this->QCharsetFromLatin(
                                ['kPcv'          => 0,
                                 'P_name'        => $cv,
                                 'P_packetLabel' => "",
                                 'P_notes'       => "",
                                 'kSp'           => $kfrS->Value('_key'),
                                 'S_psp'         => $kfrS->Value('psp'),
                                 'S_name_en'     => $kfrS->Value('name_en'),
                                 'S_name_fr'     => $kfrS->Value('name_fr'),
                                 'S_name_bot'    => $kfrS->Value('name_bot')
                                ]);
            }

            $o = new MSDQ( $this->oApp, $this->raConfig );        // use the same config_bUTF8 parm
            $rQ = $o->Cmd('msdSeedList-FindByName', ['species'=>$sp, 'cultivar'=>$cv]);
            if( $rQ['bOk'] )  $raOut['raMSE'] = $rQ['raOut'];
        }

        $raOut['raSrc'] = [];  // TODO: look for the name in sl_cv_sources

        return( $raOut );
    }

    private function cultivarInfoGetSources( $sCond )
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
