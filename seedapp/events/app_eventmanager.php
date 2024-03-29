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


$consoleConfig = [
    'CONSOLE_NAME' => "eventManager",
    'HEADER' => "Events",
    //'HEADER_LINKS' => array( array( 'href' => 'mbr_email.php',    'label' => "Email Lists",  'target' => '_blank' ),
    //    array( 'href' => 'mbr_mailsend.php', 'label' => "Send 'READY'", 'target' => '_blank' ) ),
    'TABSETS' => ['main'=> ['tabs' => ['events'     => ['label'=>'Events'],
                                       'volunteers' => ['label'=>'Volunteers'],
                                      ],
                            // this doubles as sessPermsRequired below
                            'perms' => ['events'    =>  [],     // require login but no particular perms; access is controlled per user/event
                                        'volunteers' => ['W Events'],
                                        '|'
                                       ],
                           ],
                 ],
    'urlLogin'=>'../login/',

    'consoleSkin' => 'green',
];

$oApp = SEEDConfig_NewAppConsole( ['db'=>'seeds1',
    'sessPermsRequired' => $consoleConfig['TABSETS']['main']['perms'],
    'consoleConfig' => $consoleConfig] );

SEEDPRG();

include_once(SEEDLIB.'events/events.php');
include_once('tab_eventmanager_events.php');

class MyConsole02TabSet extends Console02TabSet
{
    private $oApp;
    private $oW = null;
    private $oEvLib;

    function __construct( SEEDAppConsole $oApp )
    {
        global $consoleConfig;
        parent::__construct( $oApp->oC, $consoleConfig['TABSETS'] );

        $this->oApp = $oApp;
        $this->oEvLib = new EventsLib($oApp);
    }

    function TabSet_main_events_Init()          { $this->oW = new EventManTabEvents($this->oApp, $this->oEvLib); $this->oW->Init(); }
    function TabSet_main_events_ControlDraw()   { return( $this->oW->ControlDraw() ); }
    function TabSet_main_events_ContentDraw()   { return( $this->oW->ContentDraw() ); }

    function TabSet_main_volunteers_Init()        { $this->oW = new EventManTabVolunteers($this->oApp, $this->oEvLib); $this->oW->Init(); }
    function TabSet_main_volunteers_ControlDraw() { return( $this->oW->ControlDraw() ); }
    function TabSet_main_volunteers_ContentDraw() { return( $this->oW->ContentDraw() ); }
}

$oCTS = new MyConsole02TabSet( $oApp );

$sBody = $oApp->oC->DrawConsole( "[[TabSet:main]]", ['oTabSet'=>$oCTS] );

// newer ev_events data is utf8 -- old data is cp1252 but we don't care if it shows a few funny diacritics
echo Console02Static::HTMLPage( $sBody, "", 'EN', ['consoleSkin'=>'green'] );





exit;

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




