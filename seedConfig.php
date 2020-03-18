<?php

/* seedConfig
 *
 * Copyright (c) 2019 Seeds of Diversity Canada
 *
 * Definitions for locations of seeds components.
 */

// You have to define SEEDROOT. We'll make good guesses about everything else.
if( !defined("SEEDROOT") )  die( "SEEDROOT must be defined" );

// Good to know. substr is necessary because SERVER_NAME can have a port number.
// Note: direct php execution (e.g. cron) doesn't set SERVER_NAME so it will always appear to be non-local
define("SEED_isLocal", ((substr(@$_SERVER["SERVER_NAME"],0,9) == "localhost") ? true : false));

// These are always under SEEDROOT
if( !defined("SEEDAPP") )   define( "SEEDAPP", SEEDROOT."seedapp/" );
if( !defined("SEEDLIB") )   define( "SEEDLIB", SEEDROOT."seedlib/" );
if( !defined("SEEDCORE") )  define( "SEEDCORE", SEEDROOT."seedcore/" );

// Filesystem path to seedw (wcore).
// This has to be visible to the browser (under the web docroot) so override if not
if( !defined("SEEDW") )     define( "SEEDW", SEEDROOT."wcore/" );
if( !defined("W_CORE") )    define( "W_CORE", SEEDW );

// URL path to seedw (wcore).
// If W_CORE uses a relative url then this will work. Otherwise you have to override with an absolute url.
if( !defined("SEEDW_URL"))  define( "SEEDW_URL", SEEDW );
if( !defined("W_CORE_URL")) define( "W_CORE_URL", SEEDW_URL );

// URL path to q directory.
// If SEEDAPP is reachable by the browser you don't have to do anything.
// Otherwise, set Q_URL somewhere convenient and for each file in SEEDAPP/q/*.php make a file with the same name that includes SEEDAPP."q/*.php"
if( !defined("SEEDQ_URL") ) define( "SEEDQ_URL", SEEDAPP."q/" );
if( !defined("Q_URL") )     define( "Q_URL", SEEDQ_URL );

// Locations of components that need to be visible to the web browser
define("W_CORE_JQUERY_3_3_1", W_CORE_URL."os/jquery/jquery-3-3-1.min.js");  // use this if you need this specific version
define("W_CORE_JQUERY",       W_CORE_JQUERY_3_3_1 );                        // use this if you want the latest version (it will change)


// include everything that SEEDROOT gets via composer
require_once SEEDROOT."vendor/autoload.php";


function SEEDConfig_NewAppConsole( $raConfig = array() )
/*******************************************************
    SEEDApp should encapsulate all the system context that is external to SEEDROOT.
    This function standardizes the global parameters that define the system context, and that must be defined
    outside of SEEDROOT ( i.e. by code that uses seeds/ )

    Must be defined externally:
        $config_KFDB[]  : array of kfdb connection defs
        SEED_LOG_DIR    : directory where log files should be written (if logdir not specified)

    Defaults defined in this file, overridable in various ways:
        W_URL           : url of the wcore/ directory
        Q_URL           : url of the q/ directory
 */
{
    global $config_KFDB;

    $db = @$raConfig['db'] ?: 'seeds1';

    $raP = [
        'lang'              => @$raConfig['lang'] ?: 'EN',
        'logdir'            => @$raConfig['logdir'] ?: SEED_LOG_DIR,
        'urlW'              => @$raConfig['urlW'] ?: W_CORE_URL,
        'urlQ'              => @$raConfig['urlQ'] ?: Q_URL,
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
