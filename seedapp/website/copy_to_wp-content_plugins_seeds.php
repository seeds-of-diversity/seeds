<?php

/***
 * Plugin Name:   Seeds
 *
 * Copyright 2021-2022 Seeds of Diversity Canada
 *
 * Customization for the Seeds of Diversity web sites.
 *
 * 1. Copy this file into wp-content/plugins
 * 2. Activate the Seeds plugin in wp admin interface
 * 3. Don't put any code here. Put it in SEEDAPP/website/seedsWPPlugin.php so you don't have to keep copying this file.
 */

define("SEED_display_errors",0);    // the evolve theme throws so many warnings so turn off error reporting on local server

if( !defined( "SEEDROOT" ) ) {
    define( "SEEDROOT", "../seeds/");   // expecting seeds/ to be a sibling folder with wordpress/
    define( "SEED_APP_BOOT_REQUIRED", true );
    include_once( SEEDROOT."seedConfig.php" );
}
include_once( SEEDAPP."website/seedsWPPlugin.php" );

if( function_exists('seedsWPStart') ) {
    seedsWPStart();
} else {
    die( "seedsWPStart not found" );
}
