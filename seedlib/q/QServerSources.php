<?php

/* QServerSources
 *
 * Copyright 2015-2021 Seeds of Diversity Canada
 *
 * Serve queries about sources of cultivars
 * (queries involving sl_sources, sl_cv_sources, sl_cv_sources_archive)
 */

include_once( "Q.php" );
include_once( SEEDLIB."sl/sldb.php" );

class QServerSourceCV extends SEEDQ
{
    private $oSLDBSrc;
    private $oSLDBRosetta;

    function __construct( SEEDAppSessionAccount $oApp, $raConfig = [] )
    {
        parent::__construct( $oApp, $raConfig );
        $this->oSLDBSrc = new SLDBSources( $oApp );
        $this->oSLDBRosetta = new SLDBRosetta( $oApp );
    }

    function Cmd( $cmd, $parms )
    {
        $rQ = $this->GetEmptyRQ();

        if( $cmd == 'srcHelp' ) {
            $rQ['bHandled'] = true;
            $rQ['bOk'] = true;
            $rQ['sOut'] = $this->sHelp;
        }

        /* Cultivars X Sources offered by seed companies and/or seed banks (one row per SrcCv)
         */
        if( $cmd == 'srcSrcCv' ) {
            $rQ['bHandled'] = true;
            $raParms = $this->normalizeParms( $parms );

            $rQ['sLog'] = SEEDCore_ImplodeKeyValue( $raParms, "=", "," );

            if( ($ra = $this->getSrcCV( $raParms )) ) {
                $rQ['bOk'] = true;
                $rQ['raOut'] = $ra;
            }
        }

        /* Cultivars from sl_cv_sources that match criteria (one row per cultivar)
         */
        if( $cmd == 'srcSrcCvCultivarList' ) {
            $rQ['bHandled'] = true;
            $raParms = $this->normalizeParms( $parms );

            $rQ['sLog'] = SEEDCore_ImplodeKeyValue( $raParms, "=", "," );

            if( ($ra = $this->getSrcCVCultivarList( $raParms )) ) {
                $rQ['bOk'] = true;
                $rQ['raOut'] = $ra;
            }
        }

        /* Species offered by seed companies (one row per species)
         * Filter by species criteria and seed company criteria.
         */
        if( $cmd == 'srcSpecies' ) {
            $rQ['bHandled'] = true;
            $raParms = $this->normalizeParms( $parms );

            $rQ['sLog'] = SEEDCore_ImplodeKeyValue( $raParms, "=", "," );

            if( ($ra = $this->getSpecies( $raParms )) ) {
                $rQ['bOk'] = true;
                $rQ['raOut'] = $ra;
            }
        }

        /* Download ESF/CSCI statistics based on the log files
         */
        if( $cmd == 'srcESFStats' ) {
            $rQ['bHandled'] = true;
            $v = intval(@$parms['v']);   // type of report
            $y = intval(@$parms['y']);   // limit to a given year

            $rQ['sLog'] = "v=$v, y=$y";

            if( ($ra = $this->getSrcESFStats( $v, $y )) ) {
                $rQ['bOk'] = true;
                $rQ['raOut'] = $ra;
            }
        }

        done:
        return( $rQ );
    }


    private function getSrcCV( $raParms )
    /************************************
    */
    {
        $raOut = array();

        $sCond = $this->condSrcCVCursor( $raParms );
//$this->oApp->kfdb->SetDebug(2);
        if( ($kfrc = $this->oSLDBSrc->GetKFRC( "SRCCVxSRC", $sCond, $raParms['kfrcParms'] )) ) {
            $oCursor = new SEEDQCursor( $kfrc, [$this,"GetSrcCVRow"], $raParms );
            while( ($ra = $oCursor->GetNextRow()) ) {
                $raOut[] = $ra;
            }
        }

        return( $raOut );
    }

    private function getSrcCVCultivarList( $raParms )
    /************************************************
    */
    {
//TODO: sort $raOut by spname,cvname
        $raOut = [];

        if( $raParms['sMode'] == 'TopChoices' ) {
            $raOut = $this->getTopChoices();
            goto done;
        }

        $sCond = $this->condSrcCVCursor( $raParms );
//$this->oApp->kfdb->SetDebug(2);

        /* Fetch the PxS info for matching cultivars that are indexed in sl_pcv.
         * All conditions in sCond apply only to SRCCVxSRC.
         * All QCursor fetched fields are only from PxS.
         */
        $raParms['kfrcParms']['sGroupAliases'] = "P__key,P_name,S_name_en,S_name_fr";
        $raParms['kfrcParms']['sSortCol'] = "S.name_en asc,P.name";
        if( ($kfrc = $this->oSLDBSrc->GetKFRC( "SRCCVxSRCxPxS", $sCond." AND fk_sl_pcv<>'0'", $raParms['kfrcParms'] )) ) {
            $oCursor = new SEEDQCursor( $kfrc, [$this,"GetSrcCVCultivarListRow"], $raParms );
            while( ($ra = $oCursor->GetNextRow()) ) {
                $raOut[] = $ra;
            }
        }

        /* Fetch the PxS info for matching cultivars that are not indexed in sl_pcv
         * All conditions in sCond apply only to SRCCVxSRC.
         * All QCursor fetched fields are only from SRCCVxSRC.
         *
         * This query is only applied to rows where fk_sl_pcv=0; this value is obtained by sl_cv_sources._key+10,000,000
         * ANY_VALUE of kSrccv will do (using MIN because MariaDB doesn't support ANY_VALUE).
         *
         * N.B. Since KF only reads a defined set of cols it is not possible to get a VERBATIM from kfr (though you can make the db read it).
         *      So the ANY_VALUE is placed in fk_sl_pcv because we know it isn't used.
         */
        $raParms['kfrcParms']['sGroupAliases'] = "fk_sl_species,ocv";
        $raParms['kfrcParms']['raFieldsOverride'] = ['fk_sl_species'=>'fk_sl_species','ocv'=>'ocv',
                                                     'VERBATIM-k'=>"MIN(SRCCV._key) as fk_sl_pcv"];   // put ANY_VALUE in fk_sl_pcv; this is horrible
        $raParms['kfrcParms']['sSortCol'] = "ocv";
        if( ($kfrc = $this->oSLDBSrc->GetKFRC( "SRCCVxSRC", $sCond." AND fk_sl_pcv='0'", $raParms['kfrcParms'] )) ) {
            $oCursor = new SEEDQCursor( $kfrc, [$this,"GetSrcCVCultivarListRowKluge"], $raParms );
            while( ($ra = $oCursor->GetNextRow()) ) {
                $raOut[] = $ra;
            }
        }

        done:
        return( $raOut );
    }

    private function condSrcCVCursor( $raParms )
    /*******************************************
        Convert normalized parms into sql condition for SRCCVxSRC or SRCCVxSRCx*
     */
    {
        $raCond = [];

        // species
        if( $raParms['rngSp'] )   $raCond[] = SEEDCore_RangeStrToDB( $raParms['rngSp'],  'SRCCV.fk_sl_species' );

        // cultivars
        $r = $raParms['rngPcv'];
        $d = addslashes(@$raParms['sSrchPKluge']);
        if( $r || $d ) {    // avoid short circuiting the assignment of $d
            // kluge: sSrchP has already been used to populate rngPcv, but only for cultivars indexed in sl_pcv.
            //        Use sSrchPKluge to search ocv.
            $raCond[] = "("
                       .($d ? "SRCCV.ocv like '%$d%'" : "")
                       .($d && $r ? " OR " : "")
                       .($r ? SEEDCore_RangeStrToDB($r, 'SRCCV.fk_sl_pcv') : "")
                       .")";
        }

        // sources
        $sSrc = SEEDCore_RangeStrToDB( $raParms['rngSrc'], 'SRCCV.fk_sl_sources' );;
        if( !$sSrc || @$raParms['bAllComp'] ) {
            // if no Src parms are defined, default to bAllComp
            $sSrc = "(SRCCV.fk_sl_sources >= '3'".($sSrc ? " OR ($sSrc)" : "").")";
        }
        $raCond[] = $sSrc;

        // other criteria
        if( $raParms['bOrganic'] )  $raCond[] = "SRCCV.bOrganic";
        if( $raParms['bBulk'] )     $raCond[] = "SRCCV.bulk<>''";

        if( $raParms['raProvinces'] ) {
            $raCond[] = "SRC.prov in ('".implode( "','", $raParms['raProvinces'] )."')";
        }

if( ($k = intval(@$raParms['kPcvKluge'])) ) {
// kluge: some kPcv are fakes, actually SRCCV._key+10,000,000 representing the ocv at that row
    if( ($ra = $this->oApp->kfdb->QueryRA("SELECT fk_sl_species,ocv FROM {$this->oApp->GetDBName('seeds1')}.sl_cv_sources WHERE _key='".($k-10000000)."'")) ) {
        $raCond[] = "SRCCV.fk_sl_species='".addslashes($ra['fk_sl_species'])."' AND SRCCV.ocv='".addslashes($ra['ocv'])."'";
    }
}


        return( implode( " AND ", $raCond ) );
    }

    private function getTopChoices()
    /*******************************
        Forget about all other parameters and just return the 30 most common varieties from all companies.
        Just get the indexed varieties.
     */
    {
        $kfrcparms = ['raFieldsOverride' => ['S_name_en'=>"S.name_en", 'S_name_fr'=>"S.name_fr", 'S__key'=>"S._key",
                                             'P_name'=>"P.name", 'P__key'=>"P._key", 'c'=>"count(*)"],
                      'sGroupAliases'    => "S_name_en,S_name_fr,S__key,P_name,P__key",
                      'sSortCol'         => "c desc,S.name_en asc,P.name",
                      'iLimit'           => 30
                     ];

        $sCond = "fk_sl_sources >= 3";
        if( ($kfrc = $this->oSLDBSrc->GetKFRC( "SRCCVxSRCxPxS", $sCond, $kfrcparms )) ) {
            $oCursor = new SEEDQCursor( $kfrc, [$this,"GetSrcCVCultivarListRow"], $dummyQCursorParms = [] );
            while( ($ra = $oCursor->GetNextRow()) ) {
                // use sortable dummy keys and sort at the bottom
                $k = "${ra['S_name_en']} ${ra['P_name']}";
                $raOut[$k] = $ra;
            }
            ksort($raOut);
        }

        return( $raOut );
    }

    private function getSpecies( $raParms )
    /**************************************
        Return sorted list of species available from given sources

            raParms:
                (see normalizeParms)
                bSoDSL   : default=false    include species in the SoD seed library
                bSoDMSD  : default=false    include species in the SoD member seed directory

                outFmt   : NameKey : return array( name => _key )
                           KeyName : return array( _key => name )
                           Name    : return array( name )
                           Key     : return array( _key )

                opt_spMap    : the namespace of sl_species_map for which map.appnames and map.keys are returned (default: sl_species names and keys)
     */
    {
        $raOut = [];

        $condDB = "";   // default is to read all sl_cv_sources

        if( @$raParms['bAll'] ) {       // not implemented in normalizeParms?
            $bReadSLCV = true;
            $raParms['bSoDSL'] = true;
            $raParms['bSoDMSD'] = true;
        } else {
            $raParms['bSoDSL']  = intval(@$raParms['bSoDSL']);
            $raParms['bSoDMSD'] = intval(@$raParms['bSoDMSD']);

            $condDB = $this->condSrcCVCursor($raParms);

            $bReadSLCV = $raParms['bAllComp'] || $raParms['rngSrc'];
        }

        if( $bReadSLCV ) {
//$this->oSLDBSrc->kfdb->SetDebug(2);
            if( ($kfr = $this->oSLDBSrc->GetKFRC( "SRCCVxS", $condDB,
                                                  ['sGroupAliases'=>'S__key,S_name_en,S_name_fr,S_iname_en,S_iname_fr'] )) )
                                                  //['raFieldsOverride'=>['S__key'=>"S._key",'S_name_en'=>"S.name_en",'S_name_fr'=>"S.name_fr"],
                                                  // 'sGroupCol'=>'S._key,S.name_en,S.name_fr'] )) )
            {
                while( $kfr->CursorFetch() ) {
                    if( $raParms['outFmt'] != 'Key' ) {
                        $sp = '';
                        if( $this->oApp->lang == 'FR' ) {
                            $sp = (@$raParms['opt_bIndex'] && ($sp = $kfr->Value('S_iname_fr'))) ? $sp : $kfr->Value('S_name_fr');
                        }
                        if( !$sp ) {
                            $sp = (@$raParms['opt_bIndex'] && ($sp = $kfr->Value('S_iname_en'))) ? $sp : $kfr->Value('S_name_en');
                        }
                        $sp = $this->QCharsetFromLatin($sp);
                    }
                    switch( $raParms['outFmt'] ) {
                        case "Key":     $raOut[] = $kfr->Value('S__key');      break;
                        case "Name":    $raOut[] = $sp;                        break;
                        case "KeyName": $raOut[$kfr->Value('S__key')] = $sp;   break;
                        case "NameKey": $raOut[$sp] = $kfr->Value('S__key');   break;
                    }
                }
            }

            /* If a species map is specified, use it to map sl_species._key/name to map._key/name
             * (when there are multiple map rows with the same fk_sl_species, any map._key of those
             *  rows is equivalently valid to identify the map relation)
             */
            if( @$raParms['opt_spMap'] ) {
                // Get the map rows, keyed by fk_sl_species.
                // If multiple rows have the same fk_sl_species they will overwrite each other so one
                // random row will remain (any map._key is equivalent)
                $raMap = array();
                $raR = $this->oApp->kfdb->QueryRowsRA( "SELECT _key,fk_sl_species,appname_en,appname_fr "
                                                      ."FROM {$this->oApp->GetDBName('seeds1')}.sl_species_map WHERE ns='".addslashes($raParms['opt_spMap'])."'" );
                foreach( $raR as $ra ) {
                    $raMap[$ra['fk_sl_species']] = $ra;
                }

                // overwrite any fk_sl_species matches with the map key/name
                $raOld = $raOut;
                $raOut = array();
                if( $raParms['outFmt'] == 'KeyName' ) {
                    foreach( $raOld as $kSp => $sSpName ) {
                        if( @$raMap[$kSp] ) {
                            // found a mapped species
                            $kOut = 'spapp'.$raMap[$kSp]['_key'];   // sl_species_map._key
                            $nameOut = $this->QCharsetFromLatin($this->oApp->lang == 'FR' ? $raMap['appname_fr'] : $raMap['appname_en']);
                            $raOut[$kOut] = $nameOut;
                        } else {
                            // non-mapped species
                            $raOut['spk'.$kSp] = $sSpName;
                        }
                    }
                }

                if( $raParms['outFmt'] == 'NameKey' ) {
                    foreach( $raOld as $sSpName => $kSp ) {
                        if( @$raMap[$kSp] ) {
                            // found a mapped species
                            $kOut = 'spapp'.$raMap[$kSp]['_key'];   // sl_species_map._key
                            $nameOut = $this->QCharsetFromLatin($this->oApp->lang == 'FR' ? $raMap['appname_fr'] : $raMap['appname_en']);
                            $raOut[$nameOut] = $kOut;
                        } else {
                            // non-mapped species
                            $raOut[$sSpName] = 'spk'.$kSp;
                        }
                    }
                }
            }

            /* Sort by name (there could be a parm to disable this but why)
             */
            switch( $raParms['outFmt'] ) {
                case "Key":                       break;
                case "Name":    sort($raOut);     break;
                case "KeyName": asort($raOut);    break;
                case "NameKey": ksort($raOut);    break;
            }
        }
        return( $raOut );
    }


    function GetSrcCVRow( SEEDQCursor $oCursor, $raParms )
    /*****************************************************
        Return rows of SRCCVxSRC fetched from a SEEDQCursor
     */
    {
        $ra = array();

        // By default only return the common fields.
        // This is a config because an ajax user should not be able to access arbitrary fields of the SRCCVxSRC (e.g. internal notes)
        $bSanitize = SEEDCore_ArraySmartVal( $this->raConfig, 'config_bSanitize', [true,false] );

        $kfrc = $oCursor->kfrc;

        if( $raParms['bCSCICols'] ) {
            $ra = [ 'k' => $kfrc->Value('_key'),
                    'company'           => $this->QCharsetFromLatin( $kfrc->Value('SRC_name_en') ),
                    'species'           => $this->QCharsetFromLatin( $kfrc->Value('osp') ),
                    'cultivar'          => $this->QCharsetFromLatin( $kfrc->Value('ocv') ),
                    'organic'           => $kfrc->Value('bOrganic'),
                    'bulk'              => $kfrc->Value('bulk'),
                    'notes'             => $kfrc->Value('notes')
            ];
        } else if( $bSanitize ) {
            $ra = [ 'SRCCV__key'          => $kfrc->Value('_key'),
                    'SRCCV_fk_sl_species' => $kfrc->Value('fk_sl_species'),
                    'SRCCV_fk_sl_pcv'     => $kfrc->Value('fk_sl_pcv'),
                    'SRCCV_fk_sl_sources' => $kfrc->Value('fk_sl_sources'),
                    'SRCCV_osp'           => $this->QCharsetFromLatin( $kfrc->Value('osp') ),
                    'SRCCV_ocv'           => $this->QCharsetFromLatin( $kfrc->Value('ocv') ),
                    'SRCCV_bOrganic'      => $kfrc->Value('bOrganic'),
            ];
        } else {
            $ra = $this->QCharsetFromLatin( $kfrc->ValuesRA() );
        }
        return( $ra );
    }

    function GetSrcCVCultivarListRow( SEEDQCursor $oCursor, $raParms )
    /*****************************************************************
        Return PxS info for rows of SRCCVxSRCxPxS, grouped by fk_sl_pcv, fetched from a SEEDQCursor
     */
    {
        $ra = $this->QCharsetFromLatin(
                ['S_name_en' => $oCursor->kfrc->Value('S_name_en'),
                 'S_name_fr' => $oCursor->kfrc->Value('S_name_fr'),
                 'P_name'    => $oCursor->kfrc->Value('P_name'),
                 'P__key'    => $oCursor->kfrc->Value('P__key'),
                ]);
        return( $ra );
    }

    function GetSrcCVCultivarListRowKluge( SEEDQCursor $oCursor, $raParms )
    /**********************************************************************
        Return PxS info for rows of SRCCVxSRC, grouped by (fk_sl_species,ocv), fetched from a SEEDQCursor
        This means you have to look up sl_species for each row, so we use a cache.

        This query is only applied to rows where fk_sl_pcv=0; this value is obtained by sl_cv_sources._key+10,000,000
        ANY_VALUE of kSrccv will do (using MIN because MariaDB doesn't support ANY_VALUE).
     */
    {
        $spCache = [];

        // N.B. Since KF only reads a defined set of cols it is not possible to get a VERBATIM from kfr (though you can make the db read it).
        //      So the ANY_VALUE is placed in fk_sl_pcv because we know it isn't used.
        $kPcvKluge = $oCursor->kfrc->Value('fk_sl_pcv') + 10000000;

        $spEn = $spFr = "";
        if( ($kSp = $oCursor->kfrc->Value('fk_sl_species')) ) {
            if( !@$spCache["en$kSp"] ) {
                $kfrS = $this->oSLDBRosetta->GetKFR("S", $kSp);
                $spCache["en$kSp"] = $kfrS->Value('name_en');
                $spCache["fr$kSp"] = $kfrS->Value('name_fr');
            }
            $spEn = $spCache["en$kSp"];
            $spFr = $spCache["fr$kSp"];
        }

        $ra = $this->QCharsetFromLatin(
                ['S_name_en' => $spEn,
                 'S_name_fr' => $spFr,
                 'P_name'    => $oCursor->kfrc->Value('ocv'),
                 'P__key'    => $kPcvKluge,
                ]);

        return( $ra );
    }

    private function getSrcESFStats( $v, $year )
    {
        $raOut = [];

        switch( $v ) {
            case 1: $raOut = $this->getSrcESFStats1( $year );  break;
            case 2: $raOut = $this->getSrcESFStats2( $year );  break;
        }

        return( $raOut );
    }

    private function getSrcESFStats1( $year )
    // Report on the contents of the CSCI log (species selected) and ESF log (species searched)
    {
        $raOut = array();
        $raTmp = array();   // collect stats here, then sort and copy them to raOut in Q format

        if( file_exists( ($fname = ($this->oApp->logdir."csci_sp.log")) ) &&
            ($f = fopen( $fname, "r" )) )
        {
            $spCache = [];

            while( ($line = fgets($f)) !== false ) {
                $ra = array();
                // date  time  ip  |  kSp  spNameIfKeyZero
                preg_match( "/^([^\s]+) ([^\s]+) ([^\s]+) \| (.*)$/", $line, $ra );

                // only collect data for the given year, 0 = all years
                if( $year && substr(@$ra[0],0,4) != $year )  continue;

                if( ($kSp = intval($ra[4])) ) {
                    if( !($sp = @$spCache[$kSp]) ) {
                        $sp = $this->oApp->kfdb->Query1( "SELECT name_en FROM {$this->oApp->GetDBName('seeds1')}.sl_species WHERE _key='$kSp'" );
                        $spCache[$kSp] = $sp;
                    }
                } else {
                    $sp = substr( $ra[4], 2 );
                }

                $sp = str_replace( "+", " ", $sp );                 // for some reason some names have + instead of spaces
                $sp = str_replace( "Broccooli", "Broccoli", $sp );  // typo in earlier logs
                $sp = str_replace( "Oriental", "Asian", $sp );      // don't call it that

                $raTmp[$sp] = intval(@$raTmp[$sp]) + 1;
            }
            fclose( $f );
        }

        if( file_exists( ($fname = ($this->oApp->logdir."q.log")) ) &&
            ($f = fopen( $fname, "r" )) )
        {
            while( ($line = fgets($f)) !== false ) {
            }
        }

        /* Species hits have been counted as array( sSp => n )
         * Sort by sSp and convert to array( 'sp'=>charset(sSp), 'n'=>n )
         */
        ksort($raTmp);
        foreach( $raTmp as $sp => $n ) {
            $raOut[] = array( 'sp'=>$this->QCharsetFromLatin($sp), 'n'=>$n );
        }

        return( $raOut );
    }

    private function getSrcESFStats2( $year )
    // Report on the contents of the ESF log
    {
        $raOut = array();
        $raTmp = array();

        if( file_exists( ($fname = ($this->oApp->logdir."q.log")) ) &&
            ($f = fopen( $fname, "r" )) )
        {
            while( ($line = fgets($f)) !== false ) {
                $ra = array();
                // date  time  ip  bOk  qcmd  parms
                preg_match( "/^([^\s]+)\s+([^\s]+)\s+([^\s]+)\s+([^\s]+)\s+([^\s]+)\s*(.*)$/", $line, $ra );

                // only collect data for the given year, 0 = all years
                if( $year && substr(@$ra[0],0,4) != $year )  continue;

                $cmd = @$ra[5];
                if( $cmd == 'srcSources' &&
                    (substr( ($r = @$ra[6]), 0, 5 ) == 'kPcv=') &&
                    ($kPCV = intval(substr($r,5))) )
                {
                    if( $kPCV >= 10000000 ) {
                        list($kSp,$sCV) = $this->oApp->kfdb->QueryRA( "SELECT fk_sl_species,ocv FROM {$this->oApp->GetDBName('seeds1')}.sl_cv_sources WHERE _key='".($kPCV-10000000)."'" );
                    } else {
                        list($kSp,$sCV) = $this->oApp->kfdb->QueryRA( "SELECT fk_sl_species,name FROM {$this->oApp->GetDBName('seeds1')}.sl_pcv WHERE _key='$kPCV'" );
                    }
                    if( $kSp && $sCV ) {
                        $psp = $this->oApp->kfdb->Query1( "SELECT psp FROM {$this->oApp->GetDBName('seeds1')}.sl_species WHERE _key='$kSp'" );
                        $raTmp[$psp."|".$sCV] = intval(@$raTmp[$psp."|".$sCV]) + 1;
                    }
                }
            }
        }

        /* CV source hits have been counted as array( psp|pname => n )
         * Sort by sp,cv and convert to array( 'sp'=>charset(sSp), 'cv'=>charset(pname), 'n'=>n )
         */
        ksort($raTmp);
        foreach( $raTmp as $k => $n ) {
            list($psp,$pname) = explode( '|', $k );
            $raOut[] = $this->QCharsetFromLatin( ['sp'=>$psp, 'cv'=>$pname, 'n'=>$n] );
        }

        return( $raOut );
    }


    private function normalizeParms( $parms )
    /****************************************
        Lots of input parms are allowed. Consolidate them into normalized parms.

        Input:
            kSrc, raSrc, rngSrc     one or more sl_sources._key
            kSp,  raSp,  rngSp      one or more sl_species._key
            kPcv, raPcv, rngPcv     one or more sl_pcv._key
            sSrchS                  match sp name or sp_syn (implemented by adding to raSp)
            sSrchP                  match pcv name or pcv_syn (implemented by adding to raPcv, but must also search ocv where SRCCV.fk_sl_pcv==0)
            bPGRC                   include src 1
            bNPGS                   include src 2
            bAllComp                include src >=3

            bOrganic                true: fetch only organic (there is no way to fetch only non-organic)
            bBulk                   true: fetch only rows where bulk<>'' (there is no way to fetch only non-bulk)
            sProvinces              (e.g. 'QC SK NB') : filter to companies located in the given province(s)
            sRegions                (e.g. 'QC AT') : filter to companies located in the regions BC, PR=prairies, ON, QC, AT=Atlantic Canada
            kfrcParms               array of parms for kfrc
            outFmt                  "Key","Name","KeyName","NameKey"
            sMode                   arbitrary mode for command

            opt_*                   anything with this prefix is copied to the output

        Normalized:
            rngSrc                  a SEEDRange of sl_sources._key (including special sources 1 and/or 2)
            rngSp                   a SEEDRange of sl_species._key
            rngPcv                  a SEEDRange of sl_pcv._key
            bAllComp                include src >=3 and exclude any of those numbers from rngSrc

            bOrganic                true: fetch only organic (there is no way to fetch only non-organic)
            bBulk                   true: fetch only rows where bulk<>'' (there is no way to fetch only non-bulk)
            raProvinces             filter to companies located in the given province(s)
            kfrcParms               array of parms for kfrc
            bCSCICols               output the csci spreadsheet columns

            kPcvKluge               a way of referring to a non-indexed cultivar (kPcv>10,000,000)
            sSrchPKluge             since not all ocv are in sl_pcv, rngPcv cannot represent all sSrcP matches

            outFmt                  "Key","Name","KeyName","NameKey"
            sMode                   arbitrary mode for command

            opt_*                   anything with this prefix is copied to the output
     */
    {
//var_dump($parms);
        $raParms = array();

        // mode possibly used by command
        $raParms['sMode'] = @$parms['sMode'];

        // some commands specify different output formats
        $raParms['outFmt'] = SEEDCore_SmartVal( @$parms['outFmt'], ["Key","Name","KeyName","NameKey"] );

        // all parms with opt_* prefix are copied to the output
        foreach( $parms as $k => $v ) {
            if( SEEDCore_StartsWith( $k, 'opt_' ) )  $raParms[$k] = $v;
        }

        // Species
        $ra = @$parms['raSp'] ?: array();
        if( ($k = intval(@$parms['kSp'])) ) $ra[] = $k;
        if( ($r = @$parms['rngSp']) ) {
            list($ra1,$sRdummy) = SEEDCore_ParseRangeStr( $r );
            $ra = array_merge( $ra, $ra1 );
        }
        if( ($d = addslashes(@$parms['sSrchS'])) ) {
            // add all kSp that match the substring in sl_species and sl_species_syn
            $ra = array_merge( $ra,
                               $this->oSLDBRosetta->Get1List('S', '_key', "name_en like '%$d%' OR name_fr like '%$d%' OR "
                                                                         ."iname_en like '%$d%' OR iname_fr like '%$d%' OR name_bot like '%$d%'"),
                               $this->oSLDBRosetta->Get1List('SY', 'fk_sl_species', "name like '%$d%'") );
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
        if( ($d = addslashes(@$parms['sSrchP'])) ) {
            // add all kPcv that match the substring in sl_pcv and sl_pcv_syn
            $ra = array_merge( $ra,
                               $this->oSLDBRosetta->Get1List('P', '_key', "name like '%$d%'"),
                               $this->oSLDBRosetta->Get1List('PY', 'fk_sl_pcv', "name like '%$d%'") );
        }
        $raParms['rngPcv'] = SEEDCore_MakeRangeStr( $ra );

        // pass along the sSrchP so it can be used to search for matching sl_cv_sources.ocv
        $raParms['sSrchPKluge'] = @$parms['sSrchP'];


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

        $raParms['rngSrc'] = "";
        if( !($raParms['bAllComp'] = intval(@$parms['bAllComp'])) ) {
            if( count($raSrc) ) {
                // load the normalized range with the seedbanks and companies collected above
                $raParms['rngSrc'] = SEEDCore_MakeRangeStr( $raSrc );
            } else {
                // no seed banks or companies specified, so default to bAllComp
                $raParms['bAllComp'] = true;
            }
        }

        // other filters
        $raParms['bOrganic'] = intval(@$parms['bOrganic']);
        $raParms['bBulk'] = intval(@$parms['bBulk']);

        // provinces and regions
        $raParms['raProvinces'] = ($p = @$parms['sProvinces']) ? explode(' ', strtoupper($p)) : [];
        if( @$parms['sRegions'] ) {
            foreach( explode(' ',$parms['sRegions']) as $r ) {
                switch( strtolower($r) ) {
                    case 'bc':  $raParms['raProvinces'][] = 'BC';  break;

                    case 'pr':  $raParms['raProvinces'][] = 'AB';
                                $raParms['raProvinces'][] = 'SK';
                                $raParms['raProvinces'][] = 'MB';  break;

                    case 'on':  $raParms['raProvinces'][] = 'ON';  break;

                    case 'qc':  $raParms['raProvinces'][] = 'QC';  break;

                    case 'at':  $raParms['raProvinces'][] = 'NB';
                                $raParms['raProvinces'][] = 'NS';
                                $raParms['raProvinces'][] = 'PE';
                                $raParms['raProvinces'][] = 'NF';  break;
                }
            }
        }

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
        <li>bBulk (boolean) : limit results to supplies available in bulk quantity</li>
        <li>sProvinces (string e.g. 'QC SK NB') : return companies located in the given province(s)</li>
        <li>sRegions (string e.g. 'QC AT') : return companies located in the given regions BC, PR=prairies, ON, QC, AT=Atlantic Canada</li>
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
        <li>rngSrc (a range string) : include species from a range of seed companies</li>
        <li>bAll : override other parameters, include species from every possible source</li>
        <li>bPGRC : include species in the PGRC collection</li>
        <li>bNPGS : include species in the NPGC collection</li>
        <li>bSoDSL : include species in the SoD seed library (not implemented)</li>
        <li>bSoDMSD : include species in the SoD member seed directory (not implemented)</li>
        <li>bOrganic (boolean) : limit results to certified organic seeds</li>
        <li>bBulk (boolean) : limit results to supplies available in bulk quantity</li>
        <li>sProvinces (string e.g. 'QC SK NB') : return companies located in the given province(s)</li>
        <li>sRegions (string e.g. 'QC AT') : return companies located in the given regions BC, PR=prairies, ON, QC, AT=Atlantic Canada</li>
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
        <li>bBulk (boolean) : limit results to supplies available in bulk quantity</li>
        <li>sProvinces (string e.g. 'QC SK NB') : return companies located in the given province(s)</li>
        <li>sRegions (string e.g. 'QC AT') : return companies located in the given regions BC, PR=prairies, ON, QC, AT=Atlantic Canada</li>
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
        <li>bBulk (boolean) : limit results to supplies available in bulk quantity</li>
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
