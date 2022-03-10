<?php

/* app_mbrGoogleSheet.php
 *
 * Copyright 2022 Seeds of Diversity Canada
 *
 * Connect MbrContacts and arbitrary google sheets
 */

if( !defined( "SEEDROOT" ) ) {
    define( "SEEDROOT", "../../" );
    define( "SEED_APP_BOOT_REQUIRED", true );
    include_once( SEEDROOT."seedConfig.php" );
}

include_once( SEEDCORE."SEEDGrid.php" );
include_once( SEEDCORE."SEEDCoreFormSession.php" );
include_once( SEEDCORE."SEEDXLSX.php" );
include_once( SEEDLIB."google/GoogleSheets.php" );
include_once( "mbrApp.php" );
include_once( SEEDLIB."mbr/MbrContacts.php" );

$consoleConfig = [
    'CONSOLE_NAME' => "mbrGoogleSheets",
    'HEADER' => "Contacts in Google Sheets",
    'urlLogin'=>'../login/',
    'consoleSkin' => 'green',
];

$oApp = SEEDConfig_NewAppConsole( ['db'=>'seeds2',
    'sessPermsRequired' => MbrApp::$raAppPerms[$consoleConfig['CONSOLE_NAME']],
    'consoleConfig' => $consoleConfig] );

SEEDPRG();

$s = "";
$oUI = new MbrContactsSheetUI($oApp);

$s .= $oUI->DrawForm();

if( $oUI->IsLoaded() ) {
    $s .= $oUI->DrawTable();
}


echo Console02Static::HTMLPage( utf8_encode($s), "", 'EN', ['consoleSkin'=>'green'] );


class MbrContactsSheetUI
/***********************
 */
{
    private $oApp;
    private $oMCS = null;
    private $oForm;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oForm = new SEEDCoreFormSession($oApp->sess, 'mbrGoogleSheets');
        $this->oForm->Update();

        if( ($id = $this->oForm->Value('idSpread')) ) {
            $this->oMCS = new MbrContactsSheet($oApp, $id);
        }
    }

    function IsLoaded()  { return( $this->oMCS != null ); }

    function DrawForm()
    {
        $s = "<form method='post'>
              <div>{$this->oForm->Text('idSpread',  '', ['size'=>60])}&nbsp;Google sheet id</div>
              <div>{$this->oForm->Text('nameSheet', '', ['size'=>60])}&nbsp;sheet name</div>
              <div>{$this->oForm->Text('colEmail', '', ['size'=>60])}&nbsp;email column name</div>
              <div>{$this->oForm->Text('colPcode', '', ['size'=>60])}&nbsp;postcode column name</div>
              <div><input type='submit'/></div>
              </form>";
        return( $s );
    }

    function DrawTable()
    {
        $s = "";

        if( !$this->oMCS || !($nameSheet = $this->oForm->Value('nameSheet')) )  goto done;

        $raKMbr = [];

        // 0-based index of columns or false if not found in spreadsheet (array_search returns false if not found)
        $raColNames = $this->oMCS->GetColNames($nameSheet);
        $kEmail = ($col = $this->oForm->Value('colEmail')) ? array_search($col, $raColNames) : false;
        $kPcode = ($col = $this->oForm->Value('colPcode')) ? array_search($col, $raColNames) : false;

        if( $kEmail === false ) {
            $s = "Choose Email column";
            $s .= "<br/>".SEEDCore_ArrayExpandSeries($raCols, " [[]]," );
            goto done;
        }

        $oMbr = new Mbr_Contacts($this->oApp);

        $ra = $this->oMCS->GetProperties($nameSheet, ['bGetGridProperties'=>true]);
        $s .= "<div style='margin:10px;padding:10px;border:1px solid #bbb'>"
             ."Grid is {$ra['rowsGrid']} rows and {$ra['colsGrid']} columns.<br/>"
             ."Using {$ra['rowsUsed']} rows and {$ra['colsUsed']} columns.<br/>"

             ."Email is in column ".SEEDXls::Index2ColumnName($kEmail)
             ."</div>";

        $oGrid = new SEEDGrid( ['type'=>'bootstrap',
                                'classCols' => ['col-md-3', 'col-md-1', 'col-md-5', 'col-md-1', 'col-md-1'],
                                'styleRow' => 'border-bottom:1px solid #aaa'
        ]);

        $s .= "<div class='container-fluid'>"
             .$oGrid->Row( ["email in sheet", "found #", "member addr", "postcode in sheet"], ['styleRow'=>'font-weight:bold;border-bottom:2px solid #aaa'] );
        foreach( $this->oMCS->GetRows('nameSheet') as $raRow ) {
            $gEmail = $raRow[$kEmail];
            $raMbr = $oMbr->GetBasicValues($gEmail);
            $kMbr = @$raMbr['_key'];

            $raKMbr[] = $kMbr;

            $addrblock = $raMbr ? $oMbr->DrawAddressBlockFromRA($raMbr) : "";

            $gPcode = $kPcode !== false ? $raRow[$kPcode] : "";
            $mbrPcode = @$raMbr['postcode'] ?: "";
            $cssPcode = Mbr_Contacts::PostcodesEqual( $gPcode, $mbrPcode ) ? "" : "color:red";
            $s .= $oGrid->Row( [$gEmail, $kMbr, $addrblock, $gPcode], ['styleCol3'=>$cssPcode] );
        }
        $s .= "</div>";


        $s .= "<div style='margin:30px 15px'><textarea cols='10' rows='30'>".implode("\n",$raKMbr)."</textarea></div>";

        done:
        return( $s );
    }


}

class MbrContactsSheet
/*********************
    Connect mbr_contacts with a Google sheet
 */
{
    private $oApp;
    private $oGoogleSheet = null;

    function __construct( SEEDAppConsole $oApp, $idSpreadsheet )
    {
        $this->oApp = $oApp;
        $this->oGoogleSheet = new SEEDGoogleSheets(
                                    ['appName' => 'My PHP App',
                                     'authConfigFname' => SEEDCONFIG_DIR."/sod-public-outreach-info-e36071bac3b1.json",
                                     'idSpreadsheet' => $idSpreadsheet
                                    ] );
    }

    function GetProperties( $nameSheet ) { return( $this->oGoogleSheet->GetProperties($nameSheet) ); }

    function GetColNames( $nameSheet )
    {
        $ra = $this->oGoogleSheet->GetValues($nameSheet);   // returns a 2D array of all rows in the sheet
        return( @$ra[0][0] ? $ra[0] : [] );
    }

    function GetRows( $nameSheet )
    /*****************************

     */
    {
        $ra = $this->oGoogleSheet->GetValues($nameSheet);   // returns a 2D array of all rows in the sheet
        array_shift($ra);                                   // removes the column names row (top row)
        return( $ra );
    }

    private function values( $nameSheet, $range )
    {
        return( $this->oGoogleSheet->GetValues( $nameSheet."!".$range ) );
    }

}
