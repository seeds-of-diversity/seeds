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

if( SEED_isLocal ) {
    define( 'WP_DEBUG', true );
    define( 'WP_DEBUG_LOG', true );
    define( 'WP_DEBUG_DISPLAY', true );
    @ini_set( 'display_errors', 1 );

    error_reporting(E_ALL | E_STRICT);
    ini_set('display_errors', 1);
    ini_set('html_errors', 1);
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}

/*******************
 *
 *
 *
 *
 * NOTE TO SELF
 *
 *
 * https://wordpress.stackexchange.com/questions/365737/ftp-nlist-and-ftp-pwd-warnings
 *
 * define('FS_METHOD', 'direct');
 * is added to wp-config.php
 *
 *
 *
 *
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

    // add Event Control item to the wp-admin menu.
    seedsWPPlugin_EventControl::init();
}

class seedsWPPlugin_EventControl
{
    private static $oES;
//    private static $oMEC;


    /* Add Event Control item to the wp-admin menu.
     * WP wants an admin_menu action to trigger an add_menu_page(), which specifies a function to draw the menu page
     */
    static function init()
    {
        add_action( 'admin_menu', ['seedsWPPlugin_EventControl', 'addMenu'] );

        $oApp = SEEDConfig_NewAppConsole_LoginNotRequired( ['db'=>'wordpress'] ); //, 'sessPermsRequired'=>["W events"]] )

        include_once( SEEDAPP."website/eventmanager.php" );
        self::$oES = new EventsSheet($oApp);

        $f = "../wp-content/plugins/modern-events-calendar-lite/modern-events-calendar-lite.php";
        if( !file_exists($f) ) {
            echo "Can't include $f"; return;
        }
        include($f);
        if( !class_exists( 'MEC_main' ) ) {
            echo "<p>There is no MEC_main class</p>"; return;
        }
//        self::$oMEC = new MEC_main();


    }
    static function addMenu()
    {
        add_menu_page( "Event Control", "Event Control", 'manage_options', 'eventctrl', ['seedsWPPlugin_EventControl', 'drawMenu'], '', null );
    }
    static function drawMenu()
    {

        $s = self::$oES->DrawForm();
        echo $s;

        echo "<p>Click this button to test save_events()
              <form method='get' action='?page=eventctrl'>
              <input type='hidden' name='page' value='eventctrl'/>
              <input type='submit' name='test' value='Test'/>
              </form></p>";

        echo "<p>Export events to spreadsheet</p>
              <form method='get' action='?page=eventctrl'>
              <input type='hidden' name='page' value='eventctrl'/>
              <input type='submit' name='export' value='export'/>
              </form>";

        echo "<p>Import events from spreadsheet</p>
              <form method='get' action='?page=eventctrl'>
              <input type='hidden' name='page' value='eventctrl'/>
              <input type='submit' name='import' value='import'/>
              </form>";

        if( SEEDInput_Str('test') ) {
            /* find the MEC code, include its initialization, and make sure we can access class MEC_main
             */
            $f = "../wp-content/plugins/modern-events-calendar-lite/modern-events-calendar-lite.php";
            if( !file_exists($f) ) {
                echo "Can't include $f"; return;
            }
            include($f);
            if( !class_exists( 'MEC_main' ) ) {
                echo "<p>There is no MEC_main class</p>"; return;
            }

            /* Save an event
             */
            //$o = new MEC_main();
            //echo "Saving event ".$o->save_event([]);    // add test parameters here!
        }

        if( SEEDInput_Str('export') ) {
            var_dump("export button clicked");

            $raEvents = self::$oES->GetEventsFromDB(); // return organized list of events

            foreach($raEvents as $k=>$v){
                self::$oES->AddEventToSpreadSheet($v); // add each event to spreadsheet
            }

        }
        if( SEEDInput_Str('import') ) {
            var_dump("import button clicked");

            $events = self::$oES->GetEventsFromSheets();

            foreach($events as $k=>$v){
                self::$oES->AddEventToDB($v);
            }
        }
    }
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
