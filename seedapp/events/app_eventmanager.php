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

class EventsSheet
/****************
    Connect events with a Google sheet
 */
{
    private $oApp;

    private $oSheets = null;
    private $p_idSheet;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oForm = new SEEDCoreFormSession($oApp->sess, 'eventSheets');
        $this->oForm->Update();

        if( ($idSpread = $this->oForm->Value('idSpread')) ) {

            $this->oGoogleSheet = new SEEDGoogleSheets_NamedColumns(
                                        ['appName' => 'My PHP App',
                                         'authConfigFname' => SEEDCONFIG_DIR."/sod-public-outreach-info-e36071bac3b1.json",
                                         'idSpreadsheet' => $idSpread
                                        ] );
        }
    }

    function IsLoaded()  { return( $this->oSheets ); }

    function values()
    {
        $range = 'A1:Z1';
        list($values,$range) = $this->oSheets->GetValues( $range );
        return( $values );
    }

    function DrawForm()
    {
        $s = "<form method='post'>
              <div>{$this->oForm->Text('idSpread',  '', ['size'=>60])}&nbsp;Google sheet id</div>
              <div>{$this->oForm->Text('nameSheet', '', ['size'=>60])}&nbsp;sheet name</div>
              <div><input type='submit'/></div>
              </form>";
        return( $s );
    }

    function DrawTable()
    {
        $s = "";

        if( !$this->oGoogleSheet || !($nameSheet = $this->oForm->Value('nameSheet')) )  goto done;

                // 0-based index of columns or false if not found in spreadsheet (array_search returns false if not found)
        $raColNames = $this->oGoogleSheet->GetColumnNames($nameSheet);
        var_dump($raColNames);

        $raRows = $this->oGoogleSheet->GetRows($nameSheet);
        var_dump($raRows);

        done:
        return( $s );
    }

    /**
     * update or add event to spreadsheet
     * @param $parms array of parameters for an event
     */
    function AddEventToSpreadSheet( $parms )
    {
        $exist = false; // if event already exist on spreadsheet
        $spreadSheetRow = 0; // row in spreadsheet if event already exists in spreadsheet

        $raId = $this->oGoogleSheet->GetColumn("A"); // get all row's id

        foreach( $raId as $k=>$v ) { // check if id in spreadsheet matches with id in $parms

            if(isset($parms['id'])) { // if $parms is associative array with string index
                $id = $parms['id'];
            }
            else{ // if $parms is array with integer index
                $id = $parms[0];
            }

            if( $v == $id ) { //if id in spreadsheet matches with id in $parms
                $exist = true;
                $spreadSheetRow = $k+1; // row of current event
            }
        }

        if( !$exist ) { // if event does not exist in spreadsheet
            $spreadSheetRow = count($raId) + 1; // add to next available row
        }

        if(isset($parms['id'])){ // if $parms is associative array with string index
            $this->oGoogleSheet->SetRowWithAssociativeArray($spreadSheetRow, $parms);
        }
        else{// if $parms is array with integer index
            $this->oGoogleSheet->SetRow($spreadSheetRow, $parms);
        }

    }
}


