<?php

/*
 * Seeds plugin for Wordpress
 *
 * Copyright 2021-2022 Seeds of Diversity Canada
 *
 * Customization for the Seeds of Diversity web sites.
 *
 * 1. Copy the seeds.php file to into wp-content/plugins
 * 2. Configure the includes in seeds.php so it can find this file.
 * 3. Don't put any code in seeds.php. Put it here so you don't have to keep copying seeds.php
 */


include_once( SEEDLIB."SEEDTemplate/masterTemplate.php" );

if( defined("SITEROOT") ) {
    include_once( SITEROOT."drupalmod/lib/d8_seedbreeze.php" );
}


function seedsWPStart()
{
    // replace SEEDTemplate tags in content
    add_filter( 'the_content', 'seedsWPPlugin_Filter' );

    // stop WP default behaviour that e.g. turns '--' into &mdash; which breaks some image names in SEEDTags
    remove_filter( 'the_content', 'wptexturize' );

    // WP is stateless so it doesn't use sessions.
    // We start_session() when a SEEDSession is created, but that doesn't seem to happen early enough so we also do it here.
    add_action( 'init',      'seedsWPPlugin_SessionStart', 1 );
    add_action( 'wp_logout', 'seedsWPPlugin_SessionEnd');    // only necessary if the user is logging in/out of WP and needs session wiped
    add_action( 'wp_login',  'seedsWPPlugin_SessionEnd');    // only necessary if the user is logging in/out of WP and needs session wiped

    // add our css and js files to the <head>
    add_action( 'wp_enqueue_scripts', 'seedsWPPlugin_EnqueueStylesAndScripts' );
}


function seedsWPPlugin_EnqueueStylesAndScripts()
{
    // Use root path of production server to avoid SEEDW weirdness
    wp_register_style( 'SEEDUI-css', "//seeds.ca/wcore/css/SEEDUI.css", [], '1.0' /*, 'screen'*/ );    // optional final parm: not media-specific
    wp_enqueue_style( 'SEEDUI-css' );

    wp_register_script( 'SEEDUI-js', "//seeds.ca/wcore/js/SEEDUI.js", ['jquery'], '1.0', false );    // put the script at the top of the file because it's (sometimes) called in the middle
    wp_enqueue_script( 'SEEDUI-js' );
}



function seedsWPPlugin_Filter( $content )
{
    if( !function_exists("Drupal8Template") )  goto done;   // old code might not be installed, just ignore

    $oApp = SEEDConfig_NewAppConsole_LoginNotRequired( [] );

    $oTmpl = new Drupal8Template( $oApp, [] );

    $oMT = new SoDMasterTemplate( $oApp, [] );
    //$content = $oMT->GetTmpl()->ExpandStr( $content );
    $content = $oTmpl->ExpandStr( $content, [] );

//    $content = SEEDROOT." ".SEEDW." ".SEEDW_URL
//              ."<br/><br/>".$content;

    done:
    return( $content );
}

function seedsWPPlugin_SessionStart() {
    if( !session_id() ) {
        session_start();
    }
}

function seedsWPPlugin_SessionEnd() {
    session_destroy ();
}
