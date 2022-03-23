<?php

/* app_eventmanager.php
 *
 * Copyright 2010-2022 Seeds of Diversity Canada
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

include_once( SEEDCORE."SEEDCoreFormSession.php" );
include_once( SEEDCORE."SEEDXLSX.php" );
include_once( SEEDLIB."google/GoogleSheets.php" );
include_once( SEEDAPP."website/eventmanager.php");

$oApp = SEEDConfig_NewAppConsole_LoginNotRequired( ['db'=>'wordpress'] ); //, 'sessPermsRequired'=>["W events"]] );

SEEDPRG();


$oES = new EventsSheet($oApp);

if( $oES->IsLoaded() ) {
}

$s = $oES->DrawForm()
    .$oES->DrawTable();


echo Console02Static::HTMLPage( utf8_encode($s), "", 'EN', ['consoleSkin'=>'green'] );

// testing code

$testEvent = array(
    'id' => 8,
    'title' => 'Oakville Seedy Saturday12345',
    'city' => 'Oakville12345',
    'address' => '123 Some street12345',
    'date' => '2022-03-26',
    'time_start' => '10:00',
    'time_end' => '15:00',
    'contact' => 'somebody@someemail.ca12345'
);

$testEvent2 = array(
    5,
    'Oakville Seedy Saturday',
    'Oakville',
    '123 Some street',
    '2022-03-26',
    '10:00',
    '15:00',
    'somebody@someemail.ca, new email'
);

/*
$raRows = $oES->GetRows();
$raCol = $oES->GetColumnNames();
foreach( $raRows as $k=>$v ){
    $events = array_combine($raCol, $raRows[$k]);
    var_dump($events);
    $oES->AddEventFromSpreadsheet($events);
}
*/

$oES->AddEventToSpreadSheet($testEvent);




