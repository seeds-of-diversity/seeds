<?php

/* seedConfig
 *
 * Copyright (c) 2019 Seeds of Diversity Canada
 *
 * Definitions for locations of seeds components.
 */

// You have to define SEEDROOT. We'll make good guesses about everything else.
if( !defined("SEEDROOT") )  die( "SEEDROOT must be defined" );

// These are always under SEEDROOT
if( !defined("SEEDAPP") )   define( "SEEDAPP", SEEDROOT."seedapp/" );
if( !defined("SEEDLIB") )   define( "SEEDLIB", SEEDROOT."seedlib/" );
if( !defined("SEEDCORE") )  define( "SEEDCORE", SEEDROOT."seedcore/" );

// Filesystem path to wcore.
// This has to be visible to the browser (under the web docroot) so override if not
if( !defined("W_CORE") )    define( "W_CORE", SEEDROOT."wcore" );

// URL path to wcore.
// If W_CORE uses a relative url then this will work. Otherwise you have to override with an absolute url.
if( !defined("W_CORE_URL")) define( "W_CORE_URL", W_CORE );

// Locations of components that need to be visible to the web browser
define("W_CORE_JQUERY_3_3_1", W_CORE_URL."os/jquery/jquery-3-3-1.min.js");  // use this if you need this specific version
define("W_CORE_JQUERY",       W_CORE_JQUERY_3_3_1 );                        // use this if you want the latest version (it will change)


// include everything that SEEDROOT gets via composer
require_once SEEDROOT."vendor/autoload.php";
