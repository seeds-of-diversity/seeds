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
    define( "SEED_APP_BOOT_REQUIRED", true );

    /* if seeds/ is a sibling folder with wordpress/
     * then the regular wordpress site will find seeds one level up
     * and the wp-admin site will find seeds two levels up.
     * Otherwise you have to change this so SEEDROOT points to seeds/
     */
    if( !file_exists( ($d = "../seeds/")."seedConfig.php" ) &&
        !file_exists( ($d = "../../seeds/")."seedConfig.php" ) )
    {
        die("wp-content/plugins/seeds.php : cannot find seedConfig.php");
    }
    define( "SEEDROOT", $d );
    include_once( SEEDROOT."seedConfig.php" );
}

include_once( SEEDAPP."website/seedsWPPlugin.php" );

if( function_exists('seedsWPStart') ) {
    seedsWPStart();
} else {
    die( "seedsWPStart not found" );
}
