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


/*******************
 *
 * To prevent WP from trying to force ftp install of plugins and themes, we had to add
 *
 * define('FS_METHOD','direct');
 *
 * to the wp-config.php file (in the custom values section at the bottom).
 *
 * https://wordpress.stackexchange.com/questions/365737/ftp-nlist-and-ftp-pwd-warnings
 */


// Use this to show errors in plugins. It will also show lots of warnings from the evolve theme, which should be ignored.
if( false && SEED_isLocal ) {
    define( 'WP_DEBUG', true );
    define( 'WP_DEBUG_LOG', true );
    define( 'WP_DEBUG_DISPLAY', true );
    @ini_set( 'display_errors', 1 );

    error_reporting(E_ALL | E_STRICT);
    ini_set('display_errors', 1);
    ini_set('html_errors', 1);
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}


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

    // add Seeds item to the wp-admin menu.
    seedsWPPlugin_AdminPage::init();

    // add Event Control item to the wp-admin menu.
    seedsWPPlugin_EventControl::init();
}

class seedsWPPlugin_AdminPage
{
    /* Add Seeds page to the wp-admin menu.
     * WP wants an admin_menu action to trigger an add_menu_page(), which specifies a function to draw the menu page
     */
    static function init()
    {
        add_action( 'admin_menu', ['seedsWPPlugin_AdminPage', 'addMenu'] );
    }
    static function addMenu()
    {
        add_menu_page( "Seeds Config", "Seeds Config", 'manage_options', 'seedsadmin', ['seedsWPPlugin_AdminPage', 'drawPanel'], '', null );
    }
    static function drawPanel()
    {
        $homeTop = ['imgA'=>"", 'captionA'=>"", 'linkA'=>"",
                    'imgB'=>"", 'captionB'=>"", 'linkB'=>"",
                    'imgC'=>"", 'captionC'=>"", 'linkC'=>"",
                    'imgD'=>"", 'captionD'=>"", 'linkD'=>"",
                    'imgE'=>"", 'captionE'=>"", 'linkE'=>"" ];

        $oApp = SEEDConfig_NewAppConsole_LoginNotRequired([]);
        $oSB = new SEEDMetaTable_StringBucket( $oApp->kfdb, 0 );
        foreach( array_keys($homeTop) as $k ) {
            $homeTop[$k] = $oSB->GetStr( 'SeedsWPHomeTop', $k );
        }

        if( SEEDInput_Str('homeTop') ) {
            foreach( $homeTop as $k => $v ) {
                $homeTop[$k] = SEEDInput_Str($k);
                $oSB->PutStr( 'SeedsWPHomeTop', $k, $homeTop[$k] );
            }
        }


        $s = "<style>
              #homeTop .tdA { width:400px;height:400px; padding:10px }
              #homeTop .tdB { width:200px;height:200px }
              #homeTop .tdA input, #homeTop .tdA textarea, #homeTop .tdB input, #homeTop .tdB textarea { width:100% }
              </style>

              <h3>Home Page Top Links</h3>
              <form>
              <table border='1' id='homeTop'>
              <tr>
                <td colspan='2' rowspan='2' class='tdA'>
                    <div style='margin:auto'>
                    Image<br/> <input name='imgA' value='{$homeTop['imgA']}'/><br/>
                    Caption<br/><textarea name='captionA'>{$homeTop['captionA']}</textarea><br/>
                    Link<br/> <input name='linkA' value='{$homeTop['linkA']}'/><br/></div></td>
                <td class='tdB'>
                    Image<br/> <input name='imgB' value='{$homeTop['imgB']}'/><br/>
                    Caption<br/><textarea name='captionB'>{$homeTop['captionB']}</textarea><br/>
                    Link<br/> <input name='linkB' value='{$homeTop['linkB']}'/><br/></div></td>
                <td class='tdB'>
                    Image<br/> <input name='imgC' value='{$homeTop['imgC']}'/><br/>
                    Caption<br/><textarea name='captionC'>{$homeTop['captionC']}</textarea><br/>
                    Link<br/> <input name='linkC' value='{$homeTop['linkC']}'/><br/></div></td>
              </tr>
              <tr>
                <td class='tdB'>
                    Image<br/> <input name='imgD' value='{$homeTop['imgD']}'/><br/>
                    Caption<br/><textarea name='captionD'>{$homeTop['captionD']}</textarea><br/>
                    Link<br/> <input name='linkD' value='{$homeTop['linkD']}'/><br/></div></td>
                <td class='tdB'>
                    Image<br/> <input name='imgE' value='{$homeTop['imgE']}'/><br/>
                    Caption<br/><textarea name='captionE'>{$homeTop['captionE']}</textarea><br/>
                    Link<br/> <input name='linkE' value='{$homeTop['linkE']}'/><br/></div></td>
              </tr>
              </table>
              <input type='submit' name='homeTop' value='Save'/>
              <input type='hidden' name='page' value='seedsadmin'/>
              </form>";

        echo $s;
    }
}

class seedsWPPlugin_EventControl
{
    /* Add Event Control item to the wp-admin menu.
     * WP wants an admin_menu action to trigger an add_menu_page(), which specifies a function to draw the menu page
     */
    static function init()
    {
        // MEC plugin might not be installed, so fail gracefully if not
        $f = WP_CONTENT_DIR."/plugins/modern-events-calendar-lite/modern-events-calendar-lite.php";
        if( !file_exists($f) ) {
            SEEDConfig_NewAppConsole_LoginNotRequired([])->Log( 'seedsWPPlugin', "Can't include $f" );
            return;
        }
        include_once($f);
        if( !class_exists('MEC_main') ) {
            SEEDConfig_NewAppConsole_LoginNotRequired([])->Log( 'seedsWPPlugin', "There is no MEC_main class" );
            return;
        }

        add_action( 'admin_menu', ['seedsWPPlugin_EventControl', 'addMenu'] );
    }
    static function addMenu()
    {
        add_menu_page( "Event Control", "Event Control", 'manage_options', 'eventctrl', ['seedsWPPlugin_EventControl', 'drawPanel'], '', null );
    }
    static function drawPanel()
    {
        include_once( SEEDAPP."website/eventmanager.php" );
        echo (new EventsSheet())->DrawEventControlPanel();
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
    if( !class_exists("Drupal8Template") )  goto done;   // old code might not be installed, just ignore

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
