<?php
/*
 * MasterTemplate
 *
 * Copyright 2016-2020 Seeds of Diversity Canada
 *
 * Handle our advanced template functions.
 *
 * As much as possible, try to include code only when necessary and create objects only when necessary.
 */

include_once( SEEDCORE."SEEDTemplateMaker.php" );

class SoDMasterTemplate
{
    private $oApp;
    private $oTmpl;

    private $oDesc = null;

    function __construct( SEEDAppSessionAccount $oApp, $raParms )
    {
        $this->oApp = $oApp;

        /* OLD COMMENT FROM seedsx
         *
         *
         *
         *  $raParms['raSEEDTemplateMakerParms'] defines any resolvers etc that should precede the usual resolvers, as well as any
         * special parms for the SEEDTemplateMaker.
         *
         * EnableDocRep :
         *      site = 'public' | 'office'
         *      flag = the DocRep flag
         *      oDocRepDB = a DocRepDB object - the default uses the given kfdb and uid and is readonly, so you mostly won't need this
         *
         * EnableSEEDLocal :
         *      oLocal = a SEEDLocal object (if not defined, we create a minimal SEEDLocal so [[Lang:]] works)
         *      lang   = 'EN' | 'FR' (only needed if oLocal not defined)
         *
         * EnableSEEDForm  : define this in raSEEDTemplateMakerParms for now
         *
         * EnableSEEDSession : give info about the current user, or another user
         *
         * The BasicResolver is enabled by default.
         */
        $raTmplParms = SEEDCore_ArraySmartVal1( $raParms, 'raSEEDTemplateMakerParms', array() );    // empty array is the default value

        $raTmplParms['fTemplates'][] = SEEDAPP."templates/seeds_sessionaccount.html";

        $raTmplParms['raResolvers'][] = ['fn' => [$this,'ResolveTag'],
                                         'raParms' => SEEDCore_ArraySmartVal1( $raParms, 'raResolverParms', array() )   // empty array is the default value
        ];

        // Add DocRepTagHandler

        // Add SEEDSessionAccountTag handler

        /* Basic resolver is enabled by default.
         * EnableBasicResolver=>'DISABLE' disables it.
         * EnableBasicResolver=>[parms] overrides default parms
         */
        if( @$raParms['EnableBasicResolver'] != "DISABLE" ) {
            $raTmplParms['bEnableBasicResolver'] = true;
            $raTmplParms['raBasicResolverParms'] = ['LinkBase'=>"https://seeds.ca/", "ImgBase"=>"https://seeds.ca/d?n="]
                                                    + (@$raParms['EnableBasicResolver'] ?: array());        // these overwrite the first array
        }
        $this->oTmpl = SEEDTemplateMaker2( $raTmplParms );
    }

    function GetTmpl()  { return( $this->oTmpl ); }

    function ResolveTag( $raTag, SEEDTagParser $oTagDummy_same_as_this_oTmpl_oSeedTag, $raParms = array() )
    /******************************************************************************************************
     */
    {
        $s = "";
        $bHandled = true;

        switch( strtolower($raTag['tag']) ) {
            case 'events':
                // move events handler from siteTemplate
                break;

            case 'msd':
                /* [[msd:seedlist|kMbr]]
                 *     Show all seeds offered by kMbr, including skipped and deleted.
                 *     MSDQ is configured to override read access on seeds so the bulk emailer can show each grower their skipped and deleted seeds.
                 */
                include_once( SEEDLIB."msd/msdq.php" );
                if( $raTag['target'] == 'seedlist' ) {
                    if( !($kMbr = intval($raTag['raParms'][1])) ) {
                        if( SEED_isLocal ) $s = "<div class='alert alert-danger'>**msd:seedlist** kMbr not defined</div>";
                        goto done;
                    }

                    if( !($sSeedListStyle = $this->oTmpl->GetVar('sSeedListStyle')) ) {
                        $sSeedListStyle="font-family:verdana,arial,helvetica,sans serif;margin-bottom:15px";
                    }
                    $oApp = SEEDConfig_NewAppConsole_LoginNotRequired( [] );   // seeds1 and no perms required
                    $o = new MSDQ( $oApp, ['config_bUTF8'=>false, 'config_bAllowCanSeedRead'=>true] );
                    $rQ = $o->Cmd( 'msdSeedList-Draw', ['kUidSeller'=>$kMbr, 'eStatus'=>'ALL'] );
                    $s =
                    "<style>.sed_seed_skip {background-color:#ccc} .sed_seed {margin:10px}</style>"
                    .$rQ['sOut'];
                    $bHandled = true;
                }
                break;

            case 'cd':
                // move crop profiles handler from siteTemplate
                break;

            default:
                $bHandled = false;
        }

        done:
        return( [$bHandled,$s] );
    }
}
