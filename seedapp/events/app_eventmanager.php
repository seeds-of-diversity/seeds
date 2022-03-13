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


$oApp = SEEDConfig_NewAppConsole_LoginNotRequired( ['db'=>'seeds2'] ); //, 'sessPermsRequired'=>["W events"]] );

SEEDPRG();


$oES = new EventsSheet($oApp);

if( $oES->IsLoaded() ) {
}

$s = $oES->DrawForm()
    .$oES->DrawTable();


echo Console02Static::HTMLPage( utf8_encode($s), "", 'EN', ['consoleSkin'=>'green'] );


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
}
