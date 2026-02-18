<?php

/* seedConfig
 *
 * Copyright (c) 2019-2025 Seeds of Diversity Canada
 *
 * Definitions for locations of seeds components.
 *
 * There are two ways to initialize a seed app:
 *
 * 1. Execute seedapp/foo directly. When SEEDROOT is found to be undefined, SEED_APP_BOOT_REQUIRED is set which causes _myseedconfig.php
 *    to be executed below. That's where you put your config parameters.
 *
 * 2. Execute a boot script that sets the config parameters then includes seedapp/foo. If it defines SEEDROOT, SEED_APP_BOOT_REQUIRED is not
 *    set and _myseedconfig.php is not used.
 */


// You have to define SEEDROOT. We'll make good guesses about everything else.
if( !defined("SEEDROOT") )  die( "SEEDROOT must be defined" );


// Auto-configure applications executed directly from SEEDAPP.
// _myseedconfig.php is only needed for SEED_APP_BOOT_REQUIRED, and probably unnecessary if SEEDCONFIG_DIR already defined
if( !defined("SEEDCONFIG_DIR") ) {
    if( !file_exists(($dMyConfig = SEEDROOT."../")."_myseedconfig.php") &&
        !file_exists(($dMyConfig = SEEDROOT."../../")."_myseedconfig.php") &&
        !file_exists(($dMyConfig = SEEDROOT."../../_config/")."_myseedconfig.php") )
    {
        die( "SEEDCONFIG_DIR not defined and _myseedconfig.php not found" );
    } else {
        define( "SEEDCONFIG_DIR", $dMyConfig );
    }
}
if( defined("SEED_APP_BOOT_REQUIRED") ) {
    /* A seed app was executed directly so use the _myseedconfig.php boot-up script.
     *
     * Required:
     * $config_KFDB = ['dbname' => ['kfdbUserid'   => 'seeds', 'kfdbPassword' => 'seeds', 'kfdbDatabase' => 'seeds'], ... ]
     *
     * Optional:
     * SEEDW
     * SEEDW_URL
     * SEEDQ_URL
     * SEED_LOG_DIR
     */
    require_once SEEDCONFIG_DIR."_myseedconfig.php";
}

// Was this script run from the command line or from a web server
define("SEED_isCLI", php_sapi_name() == 'cli' );

// Good to know. substr is necessary because SERVER_NAME can have a port number.
// Note: direct php execution (e.g. cron) doesn't set SERVER_NAME so it will always appear to be non-local
define("SEED_isLocal", ((substr(@$_SERVER["SERVER_NAME"]??"",0,9) == "localhost") ? true : false));


/* Activate full error reporting in development environments, not in production  (SEED_isLocal)
 * Use SEED_display_errors=true  when you have weird problems on production server
 * Use SEED_display_errors=false when on local server and third-party code generates annoying warnings (this turns off all error reporting for all code)
 *
 * Do not define SEED_display_errors here so the default condition can be evaluated in SEEDConfig_NewAppConsole()
 */

define('SEED_DEBUG', (defined('SEED_display_errors') && SEED_display_errors) ||
                     (!defined('SEED_display_errors') && SEED_isLocal) );
if( SEED_DEBUG ) {
    error_reporting(E_ALL | E_STRICT);
    ini_set('display_errors', 1);
    ini_set('html_errors', 1);
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('html_errors', 0);
    mysqli_report(MYSQLI_REPORT_OFF);
}


// These are always under SEEDROOT
if( !defined("SEEDAPP") )   define( "SEEDAPP", SEEDROOT."seedapp/" );
if( !defined("SEEDLIB") )   define( "SEEDLIB", SEEDROOT."seedlib/" );
if( !defined("SEEDCORE") )  define( "SEEDCORE", SEEDROOT."seedcore/" );

/* Filesystem path to seedw (wcore).
 * This has to be visible to the browser (under the web docroot) so override if not.
 */
if( !defined("SEEDW") )     define( "SEEDW", SEEDROOT."wcore/" );
if( !defined("W_CORE") )    define( "W_CORE", SEEDW );    //deprecated

/* URL path to seedw (wcore).
 * If SEEDW uses a relative url then this will work. Otherwise you have to override with an absolute url.
 */
if( !defined("SEEDW_URL") )  define( "SEEDW_URL", SEEDW );
if( !defined("W_CORE_URL") ) define( "W_CORE_URL", SEEDW_URL );    //deprecated

/* URL path to q directory.
 * If seedapp/q/ is reachable by the browser you don't have to do anything.
 * Otherwise, set SEEDQ_URL somewhere in the docroot and for each file seedapp/q/* make a file with the same name that includes SEEDAPP."q/*"
 *
 * N.B. on production machines where the location is "/app/q/", that is the best way to set this value because the browser will use
 *      the same domain (e.g. seeds.ca or www.seeds.ca) and mode (http or https) as the application. This is crucial because Q creates
 *      a SEEDSessionAccount that must find the same PHPSession as the application that's using the qcmd.
 */
if( !defined("SEEDQ_URL") ) define( "SEEDQ_URL", SEEDAPP."q/" );

if( !defined("SEED_LOG_DIR") )  define( "SEED_LOG_DIR", SEEDROOT."../logs/" );


// Locations of components that need to be visible to the web browser
define("W_CORE_JQUERY_3_3_1", W_CORE_URL."os/jquery/jquery-3-3-1.min.js");  // use this if you need this specific version
define("W_CORE_JQUERY",       W_CORE_JQUERY_3_3_1 );                        // use this if you want the latest version (it will change)


// include everything that SEEDROOT gets via composer
if (!defined("SEED_EXTERNAL_COMPOSER")) {
    require_once SEEDROOT."vendor/autoload.php";
}

include_once( SEEDCORE."SEEDApp.php" );

function SEED_define_lang( $lang = "" )
/**************************************
    Defines SEED_LANG = ("EN" | "FR")

    Order of overrides:
    1) SEED_LANG already defined (so you can hard-code the language of an app when you include it in a language-defined entry point)
    2) GPC contains lang parm (allows manual override, mostly for testing)
    3) Parm (must be EN or FR else defaults to EN)
    4) SERVER_NAME identifies English or French site
    5) Default is EN
 */
{
    if( defined("SITE_LANG") )  return( SITE_LANG );

    $lang = strtoupper($lang);

    if( ($l = strtoupper(SEEDInput_Str('lang'))) && in_array($l, ['EN','FR']) ) {
        $lang = $l;
    } else if( $lang && in_array($lang, ['EN','FR']) ) {
        // use $lang
    } else if( strpos($_SERVER['SERVER_NAME'], "semences.ca") !== false ||
               strpos($_SERVER['SERVER_NAME'], "pollinisation") !== false ||
               strpos($_SERVER['SERVER_NAME'], "pollinisateur") !== false ) {
        $lang = "FR";
    }
    define("SEED_LANG", ($lang=='FR' ? $lang : 'EN') );
    return( SEED_LANG );
}


function SEEDConfig_NewAppConsole_LoginNotRequired( $raConfig ) : SEEDAppConsole
/**************************************************************
    Create a new SEEDAppConsole that doesn't require a login.
    sess->IsLogin() will be true if the user is logged in, and sess->CanRead/Write can be checked.

    The only difference is that the function will return if the user is not logged in, without diverting to the login screen.
 */
{
    return( SEEDConfig_NewAppConsole( $raConfig +
                        ['sessUIConfig' => ['bTmpActivate'=>true, 'bLoginNotRequired'=>true],
                         // this is probably irrelevant because login is not required
                         'sessPermsRequired' => @$raConfig['sessPermsRequired'] ?: ['PUBLIC']
                        ] ) );
}


function SEEDConfig_NewAppConsole( $raConfig = array() ) : SEEDAppConsole
/*******************************************************
    SEEDApp should encapsulate all the system context that is external to SEEDROOT.
    This function standardizes the global parameters that define the system context, and that must be defined
    outside of SEEDROOT ( i.e. by code that uses seeds/ )

    Must be defined externally:
        $config_KFDB[]  : array of kfdb connection defs
        SEED_LOG_DIR    : directory where log files should be written (if logdir not specified)

    Defaults defined in this file, overridable in various ways:
        SEEDW_URL       : url of the wcore/ directory
        SEEDQ_URL       : url of the q/ directory
 */
{
    global $config_KFDB;
    global $SEEDSessionAuthUI_Config;

    $db = @$raConfig['db'] ?: (defined('SEED_DB_DEFAULT') ? SEED_DB_DEFAULT : 'seeds1');

    $raP = [
        // call SEED_define_lang() to set SEED_LANG
        'lang'              => @$raConfig['lang'] ?: (defined('SEED_LANG') ? SEED_LANG : SEEDInput_Smart('lang', ['EN','FR'])),
        'logdir'            => @$raConfig['logdir'] ?: SEED_LOG_DIR,
        'urlW'              => @$raConfig['urlW'] ?: SEEDW_URL,
        'urlQ'              => @$raConfig['urlQ'] ?: SEEDQ_URL,
        'sessPermsRequired' => @$raConfig['sessPermsRequired'] ?: [],
        'sessUIConfig'      => @$raConfig['sessUIConfig'] ?:
                                [
                                 // default sessUI requires login, uses the old method temporarily
                                 'bTmpActivate'=>true,
                                 'bLoginNotRequired'=>false,
                                 // later templates substitute earlier, so alternate seedsession templates can be appended
                                 'fTemplates'=>array_merge(
                                     [SEEDAPP.'templates/seeds_sessionaccount.html'],
                                     @$raConfig['sessUIConfig_fTemplates'] ?? []
                                 ),
// $SEEDSessionAuthUI_Config should be parameterized better - comes from site.php and site2.php
                                 'urlSendPasswordSite' => @$SEEDSessionAuthUI_Config['urlSendPasswordSite'] ?? "",
// this should be parameterized externally
                                 'fnSendMail' => 'klugeMailFromHere',
                                ],
        'consoleConfig'     => @$raConfig['consoleConfig'] ?: [],
    ];

    $oApp = new SEEDAppConsole( $config_KFDB[$db] + $raP );

    /* Set error reporting on production servers for admin users only - this is only possible after $oApp created.
     * See above for general error reporting.
     */
    if( !defined('SEED_display_errors') && in_array($oApp->sess->GetUID(), [1,1499]) ) {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        ini_set('html_errors', 1);
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        // this is only possible after $oApp created
        $oApp->kfdb->SetDebug(1);
    }

    return( $oApp );
}

function klugeMailFromHere( $mailto, $subject, $body )
{
    return( SEEDEmailSend( 'Seeds of Diversity <office@seeds.ca>', $mailto, $subject, $body ) );
}
