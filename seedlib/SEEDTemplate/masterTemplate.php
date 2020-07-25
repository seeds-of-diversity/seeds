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

class MasterTemplate
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
                // move msd handler from siteTemplate
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
