<?php

/* SEEDTemplateMaker
 *
 * Copyright 2015-2019 Seeds of Diversity Canada
 *
 * Simplify the creation of SEEDTemplates by standardizing typical parameters to SEEDTemplate_Generator
 */
include_once( "SEEDTemplate.php" );
include_once( "SEEDCoreForm.php" );


function SEEDTemplateMaker2( $raConfig )
/**************************************
    raConfig:
        sTemplates = string or array of strings each containing one or more named templates
        fTemplates = filename or array of filenames each containing one or more named templates
        raTemplates = array of named templates

        raResolvers = array of tag resolvers, to which the standard resolvers are appended

        oForm    = use this SEEDCoreForm's ResolveTag()
        sFormCid = instantiate a SEEDCoreForm using this cid and use its ResolveTag()
        bFormRequirePrefix = the resolver requires 'FormText' instead of 'Text', etc

        oLocal = use this SEEDLocal's ResolveTag()
        bLocalRequirePrefix = the resolver requires 'LocalLang' instead of 'Lang', etc

        bEnableBasicResolver = enable the basic resolver (default=true)
        raBasicResolverParms = parms for basic resolver

        vars = array of variables globally available to all templates (and unaffected by local overrides and SetVar because of scoping)
 */
{
    $raGen = ['charset' => (@$raConfig['charset'] ?: 'utf-8')];

    /* Templates can be defined in strings containing %% tmpl names, files with the same format as strings, or arrays of named templates.
     * All of these can be given as single items or arrays of items.
     * e.g. sTemplates can be a string, or an array of strings
     *      fTemplates can be a filename, or an array of filenames
     *      raTemplates can be an array of templates, or an array of
     */
    if( @$raConfig['sTemplates'] )  $raGen['sTemplates'] = $raConfig['sTemplates'];
    if( @$raConfig['fTemplates'] )  $raGen['fTemplates'] = $raConfig['fTemplates'];
    if( @$raConfig['raTemplates'] )  $raGen['raTemplates'] = $raConfig['raTemplates'];

    /* Tag resolvers
     */
    $tagParms = array();
    $tagParms['raResolvers'] = isset($raConfig['raResolvers']) ? $raConfig['raResolvers'] : array();

    $oForm = null;
    if( @$raConfig['oForm'] ) {
        $oForm = $raConfig['oForm'];
    } else if( @$raConfig['sFormCid'] ) {
        $oForm = new SEEDCoreForm( $raConfig['sFormCid'] );
    }
    if( $oForm ) {
        $oFormExpand = new SEEDFormExpand( $oForm );
        $tagParms['raResolvers'][] = array( 'fn'=>array($oFormExpand,'ResolveTag'),
                                            'raConfig'=>array('bRequireFormPrefix'=>(@$raConfig['bFormRequirePrefix']?true:false)) );
    }

//    if( @$raConfig['oLocal'] ) {
//        $tagParms['raResolvers'][] = array( 'fn'=>array($raConfig['oLocal'],'ResolveTag'),
//                                            'raConfig'=>array('bRequireLocalPrefix'=>(@$raConfig['bLocalRequirePrefix']?true:false)) );
//    }

    if( !isset($raConfig['bEnableBasicResolver']) || $raConfig['bEnableBasicResolver'] ) {
        $tagParms['EnableBasicResolver'] = isset($raConfig['raBasicResolverParms']) ? $raConfig['raBasicResolverParms'] : array();
    }

    $raGen['SEEDTagParms'] = $tagParms;
    $raGen['vars'] = isset($raConfig['raVars']) ? $raConfig['raVars'] : array();
    $o = new SEEDTemplate_Generator2( $raGen );
    $oTmpl = $o->MakeSEEDTemplate();

    return( $oTmpl );
}
