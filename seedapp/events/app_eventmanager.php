<?php

/* app_eventmanager.php
 *
 * Copyright 2010-2021 Seeds of Diversity Canada
 *
 * Manage event lists
 */

if( !defined( "SEEDROOT" ) ) {
    if( php_sapi_name() == 'cli' ) {    // available as SEED_isCLI after seedConfig.php
        // script has been run from the command line
        define( "SEEDROOT", pathinfo($_SERVER['PWD'].'/'.$_SERVER['SCRIPT_NAME'],PATHINFO_DIRNAME)."/../../" );
    } else {
        define( "SEEDROOT", "../../" );
    }

    define( "SEED_APP_BOOT_REQUIRED", true );
    include_once( SEEDROOT."seedConfig.php" );
}


include("../../seedlib/google/GoogleSheets.php" );

$oApp = SEEDConfig_NewAppConsole( ['db'=>'seeds2', 'sessPermsRequired'=>["W events"]] );



$sheets = new SEEDGoogleSheets( ['appName' => 'My PHP App',
                                 'authConfigFname' => SEEDCONFIG_DIR."/sod-public-outreach-info-e36071bac3b1.json",
                                 'idSheet' => "1npC38lFv84iG1YhfS9oBpiLXYjbrjiXqHQTaibonl5I" //"1NkwvvyO71XcRlsGK9vEi2T2uB_b0fJ5-LdwFcCAeDQk"
                                ] );

$spreadsheetId = "1l1WuajTKtVqLTTR7vWCoDan-vD9WUS9li7ErA5BXThI";
$spreadsheetId = "";
$range = 'A2:H';
list($values,$range) = $sheets->GetValues( $range );

$sheets->WriteValues( 'Sheet1!A23:B23', [['1','2']] );

var_dump($values);








