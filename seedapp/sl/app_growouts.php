<?php

/* Growouts app
 *
 * Copyright (c) 2023 Seeds of Diversity Canada
 *
 */

include_once( SEEDCORE."console/console02.php" );
include_once( SEEDLIB."google/GoogleSheets.php" );
include_once( SEEDLIB."mbr/MbrContacts.php" );


$consoleConfig = [
    'CONSOLE_NAME' => "growouts",
    'HEADER' => "Seed Growouts",
//    'HEADER_LINKS' => array( array( 'href' => 'mbr_email.php',    'label' => "Email Lists",  'target' => '_blank' ),
//                             array( 'href' => 'mbr_mailsend.php', 'label' => "Send 'READY'", 'target' => '_blank' ) ),
    'TABSETS' => ['main'=> ['tabs' => [ 'growers'      => ['label'=>'Growers'],
                                        'cultivars'    => ['label'=>'Cultivars'],
                                        'settings'     => ['label'=>'Settings']
                                      ],
                            'perms' =>[ 'growers'      => ['W SL'],
                                        'cultivars'    => ['W SL'],
                                        'settings'     => ['W SL'],
                                        '|'  // allows screen-login even if some tabs are ghosted
                                      ],
                           ],
                 ],
    'pathToSite' => '../../',

    'consoleSkin' => 'green',
];


$oApp = SEEDConfig_NewAppConsole( ['sessPermsRequired' => $consoleConfig['TABSETS']['main']['perms'],
                                   'consoleConfig' => $consoleConfig] );
$oApp->kfdb->SetDebug(1);

SEEDPRG();

//var_dump($_REQUEST);

class MyConsole02TabSet extends Console02TabSet
{
    private $oApp;

    private $oGrowouts;
    private $oW;

    function __construct( SEEDAppConsole $oApp )
    {
        global $consoleConfig;
        parent::__construct( $oApp->oC, $consoleConfig['TABSETS'] );

        $this->oApp = $oApp;
        $this->oGO = new GrowoutsCommon($this->oApp);
    }

    function TabSet_main_growers_Init()         { $this->oW = new GrowoutsTabGrowers($this->oGO); $this->oW->Init(); }
    function TabSet_main_growers_ControlDraw()  { return( $this->oW->ControlDraw() ); }
    function TabSet_main_growers_ContentDraw()  { return( $this->oW->ContentDraw() ); }

    function TabSet_main_cultivars_Init()         { $this->oW = new GrowoutsTabCultivars($this->oGO); $this->oW->Init(); }
    function TabSet_main_cultivars_ControlDraw()  { return( $this->oW->ControlDraw() ); }
    function TabSet_main_cultivars_ContentDraw()  { return( $this->oW->ContentDraw() ); }

    function TabSet_main_settings_Init()         { $this->oW = new GrowoutsTabSettings($this->oGO); $this->oW->Init(); }
    function TabSet_main_settings_ControlDraw()  { return( $this->oW->ControlDraw() ); }
    function TabSet_main_settings_ContentDraw()  { return( $this->oW->ContentDraw() ); }

    function TabSet_main_cultivar_ControlDraw()
    {
        return( "<div style='padding:20px'>AAA</div>" );
    }

    function TabSet_main_cultivar_ContentDraw()
    {
        return( "<div style='padding:20px'>BBB</div>" );
    }

    function TabSet_main_cultivarsyn_Init( Console02TabSet_TabInfo $oT ) {
                                                      $this->oW = new RosettaCultivarSynonyms( $this->oApp, $oT->oSVA );
                                                      $this->oW->Init(); }
    function TabSet_main_cultivarsyn_ControlDraw()  { return( $this->oW->ControlDraw() ); }
    function TabSet_main_cultivarsyn_ContentDraw()  { return( $this->oW->ContentDraw() ); }
}

class GrowoutsCommon
{
    const  BUCKET_NS = 'AppGrowouts';
    public $oApp;

    private $oGoogleSheet = null;     // SEEDGoogleSheets_NamedColumns of the Community Growouts sheet

    private $cacheRows = null;        // rows from the Google sheet with values keyed by colname (keep this intermediate step to help debug GoogleSheet access)
    private $cacheGrowers = null;     // rows that are non-blank keyed by [sheet row number][column name]

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oBucket = new SEEDMetaTable_StringBucket($this->oApp->kfdb);
    }

    function GetBucketValue( $k )        { return( $this->oBucket->GetStr(self::BUCKET_NS, $k) ); }
    function StoreBucketValue( $k, $v )  { $this->oBucket->PutStr(self::BUCKET_NS, $k, $v); }

    function SheetOpen()
    {
        if( $this->oGoogleSheet ) goto done;                                    // already open
        if( !($idSpread  = $this->GetBucketValue('idSpread')) )   goto done;    // need these parms
        if( !($nameSheet = $this->GetBucketValue('nameSheet')) )  goto done;

        // open the Google sheet
        if( $this->oGoogleSheet = new SEEDGoogleSheets_NamedColumns(
                            ['appName' => 'My PHP App',
                             'authConfigFname' => SEEDCONFIG_DIR."/sod-public-outreach-info-e36071bac3b1.json",
                             'idSpreadsheet' => $idSpread
                            ] ) )
        {
            // Get the named sheet
            try {
                $this->cacheRows = $this->oGoogleSheet->GetRowsWithNamedColumns($nameSheet);
            } catch(Exception $e) {
                $this->cacheRows = null;
                var_dump($e->getMessage());
            }
            if( !$this->cacheRows ) {
                // failed to find the named sheet (could be many reasons)
                $this->oGoogleSheet = null;
                goto done;
            }
        }
        done:
        return( $this->oGoogleSheet ? true : false );
    }

    function EmailColNameLoaded()
    /****************************
        Return the key of the email column, only if that column exists and has been loaded
     */
    {
        return( $this->SheetOpen() && ($colEmail = $this->GetBucketValue('colEmail')) && isset($this->cacheRows[0][$colEmail])  // checks that the emailColName is actually a header value
                    ? $colEmail : "" );
    }

    function GetGrowerRows()
    /***********************
        Return array of grower rows that are non-blank, only if email column found.
        Add col iRow == origin-1 index of row in GoogleSheet
     */
    {
        if( $this->cacheGrowers ) goto done;            // already loaded

        if( ($colEmail = $this->EmailColNameLoaded()) ) {
            $i = 2;
            foreach( $this->cacheRows as $ra ) {
                if( @$ra[$colEmail] ) {
                    $this->cacheGrowers[] = array_merge($ra, ['iRow'=>$i]);
                }
                ++$i;
            }
        }

        done:
        return( $this->cacheGrowers ?: [] );
    }
}

class GrowoutsTabGrowers
{
    private $oGO;

    function __construct( GrowoutsCommon $oGO )
    {
        $this->oGO = $oGO;
    }

    function Init()
    {
    }

    function ControlDraw()
    {
    }

    function ContentDraw()
    {
        $s = "";

        if( !$this->oGO->SheetOpen() || !($colEmail = $this->oGO->EmailColNameLoaded()) )  goto done;

        $oMbr = new Mbr_Contacts($this->oGO->oApp);

        $sTable = "";
        $colPCode = $this->oGO->GetBucketValue('colPCode') ?: "Unknown postcode col";
        $colKMbr  = $this->oGO->GetBucketValue('colKMbr') ?: "";

        $nGroundCherry = $nTomSlicer = $nTomCherry = 0;

        foreach( $this->oGO->GetGrowerRows() as $ra ) {
            $sWarn = "";
            $sGroundCherry = $sTomSlicer = $sTomCherry = "";

            if( @$ra['Ground Cherry Participation'] == 'Yes' ) {
                $sGroundCherry = "1";
                ++$nGroundCherry;
            }
            if( ($n = intval(@$ra['Tomato Varieties'])) ) {
                switch(@$ra['Slicer or Cherry?']) {
                    case 'Slicer':
                        if( $n != 1 && $n != 2 )  $sWarn = "Tomatoes don't add up";
                        $sTomSlicer = $n;
                        $nTomSlicer += $n;
                        break;
                    case 'Cherry':
                        if( $n != 1 && $n != 2 )  $sWarn = "Tomatoes don't add up";
                        $sTomCherry = $n;
                        $nTomCherry += $n;
                        break;
                    case 'Slicer, Cherry':
                        if( $n != 2 )  $sWarn = "Tomatoes don't add up";
                        $sTomSlicer = 1;    $sTomCherry = 1;
                        $nTomSlicer += 1;   $nTomCherry += 1;
                        break;
                    case null:
                        if( $n != 0 )  $sWarn = "Tomatoes don't add up";
                        break;
                    default:
                        $sWarn = "Tomato varieties don't make sense";
                        break;
                }
            }

            /* Validate the member id, postcode
             */
            $sEmail = $ra[$colEmail];
            $sKMbr = "";
            $sPCode = @$ra[$colPCode] ?: "";
            if( !($raMbr = $oMbr->GetBasicValues($sEmail)) ) {
                $sEmail = "<span style='color:red'>$sEmail</span>";
            } else {
                if( ($kMbr = intval(@$ra[$colKMbr])) ) {
                    $sKMbr = ($kMbr==$raMbr['_key']) ? $kMbr : "<span style='color:red'>$kMbr</span>";
                }
                if( $sPCode && !Mbr_Contacts::PostcodesEqual($sPCode, $raMbr['postcode']) ) {
                    $sPCode = "<span style='color:red'>$sPCode</span>";
                }
            }


            $sTable .= SEEDCore_ArrayExpand($ra, "<tr><td>[[iRow]]</td> <td>$sEmail</td> <td>[[First Name]] [[Last Name]]</td> <td>$sPCode</td> <td>$sKMbr</td>
                                                      <td>$sGroundCherry</td><td>$sTomSlicer</td><td>$sTomCherry</td>
                                                      <td style='color:red'>$sWarn</td>
                                                  </tr>");
        }

        $sSummary = "<p>$nGroundCherry ground cherries<br/>
                        $nTomSlicer slicer tomatoes<br/>
                        $nTomCherry cherry tomatoes
                     </p>";

        $sDups = ""; $raE = [];
        foreach( $this->oGO->GetGrowerRows() as $ra ) { $raE[] = $ra[$colEmail]; }
        foreach(array_count_values($raE) as $e => $n) { if( $n > 1 )  $sDups.= "<br/>$e"; }
        if( $sDups ) $sSummary .= "<p><b>Duplicate emails:</b>$sDups</p>";


        $sInstructions = "<p style='font-size:small'><span style='color:red'>Red emails</span> mean they aren't found in our member database (we'll add them).</p>
                          <p style='font-size:small'><span style='color:red'>Red postal codes</span> mean the address differs from our records (we'll fix that).</p>";

        $s = "<div class='container-fluid'><div class='row'><div class='col-md-6'>$sSummary</div><div class='col-md-6'>$sInstructions</div></div></div>
              <table class='table table-striped'>
                  <tr><th>Row</th><th>$colEmail</th><th>Name</th><th>$colPCode</th><th>Member #</th>
                      <th>Ground<br/>cherry</th><th>Tomato<br>Slicer</th><th>Tomato<br/>Cherry</th>
                      <th>&nbsp;</th> <!-- warnings -->
                  </tr>
                  $sTable
              </table>";

        done:
        return( $s );
    }
}

class GrowoutsTabCultivars
{
    private $oGO;

    function __construct( GrowoutsCommon $oGO )
    {
        $this->oGO = $oGO;
    }

    function Init()
    {
    }

    function ControlDraw()
    {
    }

    function ContentDraw()
    {
        $s = "";

        $s = "Cultivars";

        return( $s );
    }
}

class GrowoutsTabSettings
{
    private $oGO;
    private $oForm;

    function __construct( GrowoutsCommon $oGO )
    {
        $this->oGO = $oGO;
        $this->oForm = new SEEDCoreForm('Plain');
    }

    function Init()
    {
        $this->oForm->Update();

        // store form values in StringBucket; if blank get them from there (plain SEEDCoreForm is always blank unless just submitted)
        foreach( ['idSpread','nameSheet','colEmail','colPcode','colKMbr'] as $k ) {
            if( ($v = $this->oForm->Value($k)) ) {
                $this->oGO->StoreBucketValue($k, $v);
            } else {
                $this->oForm->SetValue($k, $this->oGO->GetBucketValue($k));
            }
        }
    }

    function ControlDraw()
    {
    }

    function ContentDraw()
    {
        $sTestResult = "";

        if( $this->oForm->Value('Test')=='Test' ) {
            if( !$this->oGO->SheetOpen() ) {
                $sTestResult = "<span style='color:red'>Could not connect to spreadsheet</span>";
            } else {
                $sTestResult = "<span style='color:green'>Found the spreadsheet</span><br/>"
                              .($this->oGO->EmailColNameLoaded() ? ("<span style='color:green'>There are ".count($this->oGO->GetGrowerRows())." growers</span>")
                                                                 : "<span style='color:red'>Could not find column for email address</span>");
            }
        }

        $s = "<form method='post'>
              <div>{$this->oForm->Text('idSpread',  '', ['size'=>60])}&nbsp;Google sheet id ( <span style='font-size:small'>https://docs.google.com/spreadsheets/d/ <span style='color:red'>this part</span> /edit</span> )</div>
              <div>{$this->oForm->Text('nameSheet', '', ['size'=>60])}&nbsp;tab name in spreadsheet</div>
              <div>{$this->oForm->Text('colEmail', '', ['size'=>60])}&nbsp;column heading for email addresses</div>
              <div>{$this->oForm->Text('colPcode', '', ['size'=>60])}&nbsp;column headiung for postal codes (optional)</div>
              <div>{$this->oForm->Text('colKMbr', '', ['size'=>60])}&nbsp;column name for member numbers (optional)</div>
              <div><input type='submit' value='Save'/></div>
              </form>

              <hr style='border-color:var(--ts-bgcolor)'/>
              <form method='post'>
              <input type='submit' name='Test' value='Test'/> ".($sTestResult ?: "Test the connection to the spreadsheet")."
              </form>";

        return( $s );
    }
}

$s = "[[TabSet:main]]";

$oCTS = new MyConsole02TabSet( $oApp );

$s = $oApp->oC->DrawConsole( $s, ['oTabSet'=>$oCTS] );


echo Console02Static::HTMLPage( SEEDCore_utf8_encode($s), "", 'EN',
                                ['consoleSkin'=>'green',
                                'raScriptFiles' => [$oApp->UrlW()."js/SEEDCore.js"] ] );

?>
<script>SEEDCore_CleanBrowserAddress();</script>
