<?php

/* QServerSources
 *
 * Copyright 2015-2019 Seeds of Diversity Canada
 *
 * Serve queries about sources of cultivars
 * (queries involving sl_sources, sl_cv_sources, sl_cv_sources_archive)
 */

include_once( "Q.php" );
include_once( SEEDLIB."sl/sldb.php" );

class QServerSourceCV
{
    private $oQ;
    private $oSLDBSrc;

    function __construct( Q $oQ, $raConfig = array() )
    {
        $this->oQ = $oQ;
        $this->oSLDBSrc = new SLDBSources( $oQ->oApp );
    }

    function Cmd( $cmd, $parms )
    {
        $rQ = Q::GetEmptyRQ();

        if( $cmd == 'srcHelp' ) {
            $rQ['bOk'] = true;
            $rQ['sOut'] = $this->sHelp;
        }

        /* Cultivars X Sources offered by seed companies and/or seed banks (one row per SrcCv)
         */
        if( $cmd == 'srcSrcCv' ) {
            $raParms = $this->normalizeParms( $parms );

            // Currently default is true. This should possibly not be a public user parm. Or maybe it's just not advertised or encouraged.
            //$raParms['bSanitize'] = intval(@$parms['bSanitize']);

            // you can make app/q work really hard if you try to read too much
// maybe not needed anymore if normalizeParms is forcing bAllComp when src=""?
//            if( (@$raParms['bNPGS'] || @$raParms['bPGRC']) && !(@$raParms['kSp'] || @$raParms['kPcv']) )  goto done;

            $rQ['sLog'] = SEEDCore_ImplodeKeyValue( $raParms, "=", "," );

            if( ($ra = $this->getSrcCV( $raParms )) ) {
                $rQ['bOk'] = true;
                $rQ['raOut'] = $ra;
            }
        }

        done:
        return( $rQ );
    }


    private function getSrcCV( $raParms = array() )
    /**********************************************
    */
    {
        $raOut = array();

        if( ($oCursor = $this->getSrcCVCursor( $raParms )) ) {
            while( ($ra = $oCursor->GetNextRow()) ) {
                $raOut[] = $ra;
            }
        }

        return( $raOut );
    }

    private function getSrcCVCursor( $raParms = array() )
    /****************************************************
        Get sl_cv_sources information for all entries that fit the criteria

        $raParms is the output from $this->normalizeParms
     */
    {
        $raCond = array();
        if( $raParms['rngSp'] )   $raCond[] = SEEDCore_RangeStrToDB( $raParms['rngSp'],  'SRCCV.fk_sl_species' );
        if( $raParms['rngPcv'] )  $raCond[] = SEEDCore_RangeStrToDB( $raParms['rngPcv'], 'SRCCV.fk_sl_pcv' );

        $sSrc = SEEDCore_RangeStrToDB( $raParms['rngSrc'], 'SRCCV.fk_sl_sources' );;
        if( !$sSrc || @$raParms['bAllComp'] ) {
            // if no Src parms are defined, default to bAllComp
            $sSrc = "(SRCCV.fk_sl_sources >= '3'".($sSrc ? " OR ($sSrc)" : "").")";
        }
        $raCond[] = $sSrc;

        if( $raParms['bOrganic'] )  $raCond[] = "SRCCV.bOrganic";

if( ($k = intval(@$raParms['kPcvKluge'])) ) {
// kluge: some kPcv are fakes, actually SRCCV._key+10,000,000 representing the ocv at that row
    if( ($ra = $this->oQ->oApp->kfdb->QueryRA("SELECT osp,ocv FROM seeds.sl_cv_sources WHERE _key='".($k-10000000)."'")) ) {
        $raCond[] = "SRCCV.osp='".addslashes($ra['osp'])."' AND SRCCV.ocv='".addslashes($ra['ocv'])."'";
    }
}

        $sCondDB = implode( " AND ", $raCond );
//$this->oQ->oApp->kfdb->SetDebug(2);

        $oCursor = null;
        if( ($kfrc = $this->oSLDBSrc->GetKFRC( "SRCCVxSRC", $sCondDB, $raParms['kfrcParms'] )) ) {
            $oCursor = new QCursor( $kfrc, array($this,"GetSrcCVRow"), $raParms );
        }

        return( $oCursor );
    }

    function GetSrcCVRow( QCursor $oCursor, $raParms )
    {
        $ra = array();

        // This is not a normalized parm because if it is set to false it should be done so on purpose by the function
        // that handles the command. Maybe that can be done via an input parm, but it's probably best to assume that
        // extra or "hidden" information cannot be requested by a standardized parm.
        $bSanitize = SEEDCore_ArraySmartVal( $raParms, 'bSanitize', array(true,false) );     // by default only return the common fields

        $kfrc = $oCursor->KFRC();

        if( $raParms['bCSCICols'] ) {
            $ra = [ 'k' => $kfrc->Value('_key'),
                    'company'           => $this->oQ->QCharset( $kfrc->Value('SRC_name_en') ),
                    'species'           => $this->oQ->QCharset( $kfrc->Value('osp') ),
                    'cultivar'          => $this->oQ->QCharset( $kfrc->Value('ocv') ),
                    'organic'           => $kfrc->Value('bOrganic'),
                    'bulk'              => $kfrc->Value('bulk'),
                    'notes'             => $kfrc->Value('notes')
            ];


        } else if( $bSanitize ) {
            $ra = [ 'SRCCV__key'          => $kfrc->Value('_key'),
                    'SRCCV_fk_sl_species' => $kfrc->Value('fk_sl_species'),
                    'SRCCV_fk_sl_pcv'     => $kfrc->Value('fk_sl_pcv'),
                    'SRCCV_fk_sl_sources' => $kfrc->Value('fk_sl_sources'),
                    'SRCCV_osp'           => $this->oQ->QCharset( $kfrc->Value('osp') ),
                    'SRCCV_ocv'           => $this->oQ->QCharset( $kfrc->Value('ocv') ),
                    'SRCCV_bOrganic'      => $kfrc->Value('bOrganic'),
            ];
        } else {
            // does not support $this->bUTF8
            $ra = $kfrc->ValuesRA();
        }
        return( $ra );
    }


    private function normalizeParms( $parms )
    /****************************************
        Lots of input parms are allowed. Consolidate them into normalized parms.

        Input:
            kSrc, raSrc, rngSrc     one or more sl_sources._key
            kSp,  raSp,  rngSp      one or more sl_species._key
            kPcv, raPcv, rngPcv     one or more sl_pcv._key
            bPGRC                   include src 1
            bNPGS                   include src 2
            bAllComp                include src >=3

            bOrganic                true: fetch only organic (there is no way to fetch only non-organic)
            kfrcParms               array of parms for kfrc

        Normalized:
            rngSrc                  a SEEDRange of sl_sources._key (including special sources 1 and/or 2)
            rngSp                   a SEEDRange of sl_species._key
            rngPcv                  a SEEDRange of sl_pcv._key
            bAllComp                include src >=3 and exclude any of those numbers from rngSrc

            bOrganic                true: fetch only organic (there is no way to fetch only non-organic)
            kfrcParms               array of parms for kfrc
            bCSCICols               output the csci spreadsheet columns
     */
    {
//var_dump($parms);
        $raParms = array();

        // Species
        $ra = @$parms['raSp'] ?: array();
        if( ($k = intval(@$parms['kSp'])) ) $ra[] = $k;
        if( ($r = @$parms['rngSp']) ) {
            list($raR,$sRdummy) = SEEDCore_ParseRangeStr( $r );
            $ra = array_merge( $ra, $raR );
        }
        $raParms['rngSp'] = SEEDCore_MakeRangeStr( $ra );

        // Pcv
// raPcv and rngPcv only supported for non-kluged kPcv < 10000000
        $ra = @$parms['raPcv'] ?: array();
        if( ($k = intval(@$parms['kPcv'])) && $k < 10000000 ) $ra[] = $k;
        if( ($r = @$parms['rngPcv']) ) {
            list($raR,$sRdummy) = SEEDCore_ParseRangeStr( $r );
            $ra = array_merge( $ra, $raR );
        }
        $raParms['rngPcv'] = SEEDCore_MakeRangeStr( $ra );

// kluge: special handler for kPcv that are really sl_cv_sources._key+10000000
if( ($k = intval(@$parms['kPcv'])) && $k > 10000000 ) $raParms['kPcvKluge'] = $k;

        // Src
        $raSrc = @$parms['raSrc'] ?: array();
        if( ($k = intval(@$parms['kSrc'])) ) $raSrc[] = $k;
        if( ($r = @$parms['rngSrc']) ) {
            list($raR,$sRdummy) = SEEDCore_ParseRangeStr( $r );
            $raSrc = array_merge( $raSrc, $raR );
        }
        /* bAllComp overrides all kSrc >=3
         *
         *      bAllComp            -> bAllComp
         *      bAllComp + srcX>=3  -> bAllComp + ()
         *      bAllComp + bPGRC    -> bAllComp + (1)
         *      srcX>=3             -> (X)
         *      bPGRC               -> (1)
         *      srcX>=3 + bPGRC     -> (1,X)
         *      src=''              -> bAllComp         no src input (and no seed banks) implies all src>=3
         */
        if( ($bPGRC = @$parms['bPGRC']) )  $raSrc[] = 1;
        if( ($bNPGS = @$parms['bNPGS']) )  $raSrc[] = 2;
        $raParms['bAllComp'] = intval(@$parms['bAllComp']);

        if( !$raParms['bAllComp'] ) {
            if( count($raSrc) ) {
                // load the normalized range with the seedbanks and companies collected above
                $raParms['rngSrc'] = SEEDCore_MakeRangeStr( $raSrc );
            } else {
                // no seed banks or companies specified, so default to bAllComp
                $raParms['rngSrc'] = "";
                $raParms['bAllComp'] = true;
            }
        }

        $raParms['bOrganic'] = intval(@$parms['bOrganic']);

        $raParms['kfrcParms'] = @$parms['kfrcParms'] ?: array();

        $raParms['bCSCICols'] = intval(@$parms['bCSCICols']);

        return( $raParms );
    }


    private $sHelp = "
    <h2>Data about seed sources</h2>
    <h4>Seed companies</h4>
      <p><i>Use this to get metadata about seed companies, filtered by company, location, or what they offer (species/cultivars/organic).</i></p>
      <p style='margin-left:30px'>cmd=srcSources&[parameters...]<p>
      <ul style='margin-left:30px'>
        <li>kSp (integer key of a seed species) : return companies that sell this species</li>
        <li>kPcv (integer key of a seed cultivar) : return companies that sell this cultivar</li>
        <li>bOrganic (boolean) : limit results to certified organic seeds of the above species/varieties</li>
        <li>sProvinces (string e.g. 'QC SK NB') : return companies located in the given province(s)</li>
        <li>sRegions (string e.g. 'QC AC') : return companies located in the given regions BC, PR=prairies, ON, QC, AC=Atlantic Canada</li>
      </ul>
      <p style='margin-left:30px'>Return (one result per company)</p>
      <ul style='margin-left:30px'>
        <li>SRC__key : integer key of seed company</li>
        <li>SRC_name : name of seed company</li>
        <li>SRC_address, SRC_city, SRC_prov, SRC_postcode : address of seed company</li>
        <li>SRC_email : email address of seed company</li>
        <li>SRC_web : web site of seed company</li>
      </ul>

    <h4>Species available from seed companies</h4>
      <p><i>Use this to get the species offered by a subset of seed companies, filtered by company, location, etc.</i></p>
      <p style='margin-left:30px'>cmd=srcSpecies&[parameters...]<p>
      <ul style='margin-left:30px'>
        <li>bAllComp : override other parameters, include species from every seed company</li>
        <li>rngComp (a range string) : include species from a range of seed companies (not implemented)</li>
        <li>bAll : override other parameters, include species from every possible source (not implemented)</li>
        <li>bPGRC : include species in the PGRC collection (not implemented)</li>
        <li>bNPGS : include species in the NPGC collection (not implemented)</li>
        <li>bSoDSL : include species in the SoD seed library (not implemented)</li>
        <li>bSoDMSD : include species in the SoD member seed directory (not implemented)</li>
        <li>bOrganic (boolean) : limit results to certified organic seeds (not implemented)</li>
        <li>sProvinces (string e.g. 'QC SK NB') : return companies located in the given province(s) (not implemented)</li>
        <li>sRegions (string e.g. 'QC AC') : return companies located in the given regions BC, PR=prairies, ON, QC, AC=Atlantic Canada (not implemented)</li>
        <li>outFmt : NameKey = return array(name=>kSp), KeyName = return array(kSp=>name), Name = return array(name), Key => return array(kSp)</li>
      </ul>
      <p style='margin-left:30px'>Return (one result per species)</p>
      <ul style='margin-left:30px'>
        <li>see outFmt above</li>
      </ul>

    <h4>Cultivars available from seed companies</h4>
      <p><i>Use this to search for cultivars available from a subset of seed companies, filtered by company, location, etc.</i></p>
      <p style='margin-left:30px'>cmd=srcCultivars&[parameters...]<p>
      <ul style='margin-left:30px'>
        <li>sSrch : search string that matches species and cultivar names, limited by other parameters</li>
        <li>kSp (integer key of a seed species) : limit to cultivars of this species</li>
        <li>bOrganic (boolean) : limit to cultivars available as certified organic</li>
        <li>sProvinces (string e.g. 'QC SK NB') : return companies located in the given province(s) (not implemented)</li>
        <li>sRegions (string e.g. 'QC AC') : return companies located in the given regions BC, PR=prairies, ON, QC, AC=Atlantic Canada</li>
        <li>sMode='TopChoices' : overrides all other parameters and returns the most popular cultivars - can be a nice default if search is blank</li>
      </ul>
      <p style='margin-left:30px'>Return (one result per cultivar)</p>
      <ul style='margin-left:30px'>
        <li>P__key : integer key for cultivar</li>
        <li>P_name : cultivar name</li>
        <li>S_name_en : English name of the cultivar's species</li>
      </ul>


    <h4>Seeds available from seed companies</h4>
      <p><i>Use this to look up specific relations between seed cultivars and sources</i></p>
      <p style='margin-left:30px'>cmd=srcSrcCv&[parameters...]<p>
      <ul style='margin-left:30px'>
        <li>kSrc (integer key of a seed company) : return seed varieties sold by this company</li>
        <li>kSp (integer key of a seed species) : return seed varieties/companies for this species</li>
        <li>kPcv (integer key of a seed cultivar) : return companies that sell this cultivar</li>
        <li>bOrganic (boolean) : limit results to certified organic seeds of the above species/varieties</li>
        <li>bAllComp (boolean) : search all companies (kSrc==0) does not imply this)</li>
      </ul>
      <p style='margin-left:30px'>Return (one result per company x cultivar)</p>
      <ul style='margin-left:30px'>
        <li>SRCCV__key : internal key for this (company,cultivar)</li>
        <li>SRCCV_fk_sl_species : integer key for species</li>
        <li>SRCCV_fk_sl_pcv : integer key for cultivar</li>
        <li>SRCCV_osp : species name</li>
        <li>SRCCV_ocv : cultivar name</li>
        <li>SRCCV_bOrganic (boolean) : seed cultivar is certified organic from this company</li>
      </ul>
    ";
}
