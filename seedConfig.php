<?php

/* seedConfig
 *
 * Copyright (c) 2019-2020 Seeds of Diversity Canada
 *
 * Definitions for locations of seeds components.
 */

// You have to define SEEDROOT. We'll make good guesses about everything else.
if( !defined("SEEDROOT") )  die( "SEEDROOT must be defined" );

// Good to know. substr is necessary because SERVER_NAME can have a port number.
// Note: direct php execution (e.g. cron) doesn't set SERVER_NAME so it will always appear to be non-local
define("SEED_isLocal", ((substr(@$_SERVER["SERVER_NAME"],0,9) == "localhost") ? true : false));


/* Activate full error reporting in development environments, not in production
 * You can define SEED_display_errors = true to turn on error reporting when you have weird production problems
 */ 
if( SEED_isLocal || defined("SEED_display_errors") ) {
    error_reporting(E_ALL | E_STRICT);
    ini_set('display_errors', 1);
    ini_set('html_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('html_errors', 0);
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
 */
if( !defined("SEEDQ_URL") ) define( "SEEDQ_URL", SEEDAPP."q/" );

// Locations of components that need to be visible to the web browser
define("W_CORE_JQUERY_3_3_1", W_CORE_URL."os/jquery/jquery-3-3-1.min.js");  // use this if you need this specific version
define("W_CORE_JQUERY",       W_CORE_JQUERY_3_3_1 );                        // use this if you want the latest version (it will change)


// include everything that SEEDROOT gets via composer
require_once SEEDROOT."vendor/autoload.php";

function SEEDConfig_NewAppConsole_LoginNotRequired( $raConfig )
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


function SEEDConfig_NewAppConsole( $raConfig = array() )
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

    $db = @$raConfig['db'] ?: 'seeds1';

    $raP = [
        'lang'              => @$raConfig['lang'] ?: 'EN',
        'logdir'            => @$raConfig['logdir'] ?: SEED_LOG_DIR,
        'urlW'              => @$raConfig['urlW'] ?: SEEDW_URL,
        'urlQ'              => @$raConfig['urlQ'] ?: SEEDQ_URL,
        'sessPermsRequired' => @$raConfig['sessPermsRequired'] ?: [],
        'sessUIConfig'      => @$raConfig['sessUIConfig']
                                // default sessUI requires login, uses the old method temporarily
                                ?: ['bTmpActivate'=>true,
                                    'bLoginNotRequired'=>false,
                                    'fTemplates'=>[SEEDAPP.'templates/seeds_sessionaccount.html'] ],

        'consoleConfig'     => @$raConfig['consoleConfig'] ?: [],
    ];

    $oApp = new SEEDAppConsole( $config_KFDB[$db] + $raP );

    return( $oApp );
}
