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

if( ($cmd = SEEDInput_Str('cmd')) ) {
    if( $cmd == 'putKMbrInSheet' ) {
        $cell = SEEDInput_Str('cell');
        $kMbr = SEEDInput_Int('kMbr');

        $ret = $oUI->PutKMbrInSheet($kMbr, $cell);

        echo json_encode($ret);
    }
    exit;
}



$s .= $oUI->DrawForm();

if( $oUI->IsAccessible() ) {
    $s .= $oUI->DrawTable();
}


echo Console02Static::HTMLPage( utf8_encode($s), "", 'EN', ['consoleSkin'=>'green', 'raScriptFiles' => [SEEDW_URL."js/SEEDCore.js"]] );


class MbrContactsSheetUI
/***********************
 */
{
    private $oApp;
    private $oMCS = null;
    private $oForm;
    private $nameSheet = '';    // sheet name required

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oForm = new SEEDCoreFormSession($oApp->sess, 'mbrGoogleSheets');
        $this->oForm->Update();

        $this->nameSheet = $this->oForm->Value('nameSheet');

        if( ($id = $this->oForm->Value('idSpread')) && $this->nameSheet ) {     // only allow read if nameSheet is defined
            $this->oMCS = new MbrContactsSheet($oApp, $id);
        }
    }

    function IsAccessible()  { return( $this->oMCS != null ); }

    function DrawForm()
    {
        $s = "<form method='post'>
              <div>{$this->oForm->Text('idSpread',  '', ['size'=>60])}&nbsp;Google sheet id</div>
              <div>{$this->oForm->Text('nameSheet', '', ['size'=>60])}&nbsp;sheet name</div>
              <div>{$this->oForm->Text('colEmail', '', ['size'=>60])}&nbsp;email column name (will read only)</div>
              <div>{$this->oForm->Text('colPcode', '', ['size'=>60])}&nbsp;postcode column name (will read only)</div>
              <div>{$this->oForm->Text('colKMbr', '', ['size'=>60])}&nbsp;kMbr column name (will read only)</div>
              <div><input type='submit'/></div>
              </form>";
        return( $s );
    }

    function DrawTable()
    {
        $s = "";

        if( !$this->oMCS || !($this->nameSheet) )  goto done;

        $raKMbr = [];

        // 0-based index of columns or false if not found in spreadsheet (array_search returns false if not found)
        $raColNames = $this->oMCS->GetColNames($this->nameSheet);
        $kEmail = ($col = $this->oForm->Value('colEmail')) ? array_search($col, $raColNames) : false;
        $kKMbr  = ($col = $this->oForm->Value('colKMbr')) ? array_search($col, $raColNames) : false;
        $kPcode = ($col = $this->oForm->Value('colPcode')) ? array_search($col, $raColNames) : false;

        if( $kEmail === false ) {
            $s = "Choose Email column";
            $s .= "<br/>".SEEDCore_ArrayExpandSeries($this->oMCS->GetColNames($this->nameSheet), " [[]]," );
            goto done;
        }

        $oMbr = new Mbr_Contacts($this->oApp);

        $ra = $this->oMCS->GetProperties($this->nameSheet, ['bGetGridProperties'=>true]);
        $s .= "<div style='margin:10px;padding:10px;border:1px solid #bbb'>"
             ."Grid is {$ra['rowsGrid']} rows and {$ra['colsGrid']} columns.<br/>"
             ."Using {$ra['rowsUsed']} rows and {$ra['colsUsed']} columns.<br/>"

             ."Email is in column ".SEEDXls::Index2ColumnName($kEmail)
             ."</div>";

        $oGrid = new SEEDGrid( ['type'=>'bootstrap',
                                'classCols' => ['col-md-3', 'col-md-1', 'col-md-1', 'col-md-5', 'col-md-1', 'col-md-1'],
                                'styleRow' => 'border-bottom:1px solid #aaa'
        ]);

        $s .= "<div class='container-fluid'>"
             .$oGrid->Row( ["email in sheet", "# in sheet", "found # from email", "found addr from email", "postcode in sheet"],
                           ['styleRow'=>'font-weight:bold;border-bottom:2px solid #aaa'] );
        $iRow = 1;
        foreach( $this->oMCS->GetRows($this->nameSheet) as $raRow ) {
            ++$iRow;    // first row is 2

            // kEmail is required to get this far
            $gEmail = $raRow[$kEmail];
            $raMbr = $oMbr->GetBasicValues($gEmail);
            $kMbrFromDb = @$raMbr['_key'];
            $addrblock = $raMbr ? $oMbr->DrawAddressBlockFromRA($raMbr) : "";
            $mbrPcode = @$raMbr['postcode'] ?: "";

            // these are optional
            $kMbrFromSheet = $kKMbr !== false ? @$raRow[$kKMbr] : 0;
            $gPcode = $kPcode !== false ? $raRow[$kPcode] : "";

            $sMbrFromSheet = '';
            if( $kKMbr !== false ) {
                $sCellKMbrFromSheet = SEEDXls::Index2ColumnName($kKMbr).$iRow;
                $sMbrFromSheet = (!$kMbrFromSheet && $kMbrFromDb) ? "<button data-kMbr='$kMbrFromDb' data-cell='$sCellKMbrFromSheet'>set $kMbrFromDb</button>"
                                                                  : $kMbrFromSheet;
            }

            $bPostcodeMatch = Mbr_Contacts::PostcodesEqual($gPcode, $mbrPcode);
            $cssEmail = !$kMbrFromSheet ? 'color:red'
                            : (!$bPostcodeMatch ? 'color:orange' : 'color:green');

            $cssPcode = $bPostcodeMatch ? "" : "color:red";
            $s .= $oGrid->Row( [$gEmail, $sMbrFromSheet, $kMbrFromDb, $addrblock, $gPcode], ['styleCol0'=>$cssEmail,'styleCol4'=>$cssPcode] );

            // add to textarea for copying
            $raKMbr[] = $kMbrFromDb;
        }
        $s .= "</div>";


        $s .= "<div style='margin:30px 15px'><textarea cols='10' rows='30'>".implode("\n",$raKMbr)."</textarea></div>";



$s .= <<<JSCRIPT
<script>
$(document).ready( function() {
    $('button').click( function() {
        let jx = { cmd:'putKMbrInSheet',
                   kMbr: $(this).data('kmbr'),
                   cell: $(this).data('cell')
                 };

        let rQ = SEEDJXSync('', jx);
        console.log(rQ);
    });
});
</script>
JSCRIPT;

        done:
        return( $s );
    }

    function PutKMbrInSheet( int $kMbr, string $cell )
    {
        if( $this->IsAccessible() && $kMbr && SEEDXls::IsValidCellname($cell) ) {
            $ret = $this->oMCS->PutValue( $this->nameSheet, $cell, $kMbr );
        } else {
            $ret = "sheet not ready, or invalid kMbr=$kMbr or invalid cell=$cell";
        }
        return( $ret );
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
        $this->oGoogleSheet = new SEEDGoogleSheets_NamedColumns(
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

    function PutValue( string $nameSheet, string $cell, $v )
    {
        $response = $this->oGoogleSheet->WriteValues( $nameSheet.'!'.$cell, [[$v]] );   // value passed as 2D array
        return( $response );
    }

    private function values( $nameSheet, $range )
    {
        return( $this->oGoogleSheet->GetValues( $nameSheet."!".$range ) );
    }

}
