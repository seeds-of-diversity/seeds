<?php

include_once( SEEDLIB."SEEDTemplate/masterTemplate.php" );

include_once( SITEROOT."drupalmod/lib/d8_seedbreeze.php" );


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

    // add our js files to the <head>
    add_action( 'wp_enqueue_scripts', 'seedsWPPlugin_EnqueueScripts' );
}

function seedsWPPlugin_EnqueueScripts()
{
    wp_register_script( 'SEEDUI', "/wcore/js/SEEDUI.js", ['jquery'],'1.0', true );  // use root path of production server to avoid SEEDW weirdness
    wp_enqueue_script( 'SEEDUI' );
}



function seedsWPPlugin_Filter( $content )
{
    $oApp = SEEDConfig_NewAppConsole_LoginNotRequired( [] );

    $oTmpl = new Drupal8Template( $oApp, [] );

    $oMT = new SoDMasterTemplate( $oApp, [] );
    //$content = $oMT->GetTmpl()->ExpandStr( $content );
    $content = $oTmpl->ExpandStr( $content, [] );

//    $content = SEEDROOT." ".SEEDW." ".SEEDW_URL
//              ."<br/><br/>".$content;

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
