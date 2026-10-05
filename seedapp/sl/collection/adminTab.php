<?php

include_once(SEEDCORE."SEEDCoreFormSession.php");   // SEEDCoreFormStringBucket

class CollectionAdmin
{
    private $oApp;
    private $oSVA;  // session vars for the UI tab (all batch ops modules)
    private $oOpPicker;
    private $oSLDB;
    private $oQCollReports;

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;
        $this->oSLDB = new SLDBCollection($oApp);
    }

    function Init()
    {
        // Independent of any state of the worker because that only exists in ContentDraw
    }

    function ControlDraw()
    {
        $s = "";

        return( $s );
    }

    function ContentDraw()
    {
        $s = "";

        $oForm = new SEEDCoreFormStringBucket($this->oApp, "slcoll_admin", 'A');
        $oForm->Update();

        /* google sheet controls
         */
        $s = "<div style='display:inline-block;vertical-align:top;padding:1em 1em;border:1px solid #aaa;border-radius:5px'><form method='post'>
              <h4>Copy Seed Library data to Management Master spreadsheet</h4>
              <table>
              <tr><td>Google spreadsheet&nbsp;&nbsp;</td><td>{$oForm->Text('idSpreadsheet', "", ['size'=>50, 'placeholder'=>"spreadsheet id"])}</td></tr>
              <tr><td>Sheet name</td><td>{$oForm->Text('nameSheet', "", ['size'=>30, 'placeholder'=>"sheet name"])}</td></tr></table><br/>
              <input type='submit' name='cmd_g' value='Write to sheet'/>
              </form></div>";

        if( ($cmd_g = SEEDInput_Str('cmd_g')) ) {
            include_once(SEEDLIB."google/GoogleSheets.php");

            $idSpreadsheet = $oForm->Value('idSpreadsheet');
            $nameSheet = $oForm->Value('nameSheet');
            $oGoogleSheet = new SEEDGoogleSheets_NamedColumns(
                        ['appName' => 'My PHP App',
                         'authConfigFname' => SEEDCONFIG_DIR."sod-public-outreach-info-e36071bac3b1.json",
                         'idSpreadsheet' => $idSpreadsheet] );
            $raG = [];
            switch($cmd_g) {
                case 'Write to sheet':
                    $raQCmdParms = ['kCollection'=>1, 'modes'=>" raIxG ", 'config_bUTF8'=>true];
                    $rQ = (new QServerSLCollectionReports($this->oApp))->Cmd('collreport-cultivarlist_active_lots_combined', $raQCmdParms);
//var_dump($rQ['raOut'][0]);
                    if( $rQ['bOk'] ) {
                        $raG[] = ['cv','species','cultivar','csci_count','adoption',
                                  'newest_lot_year','total g','newest_lot_grams','newest_lot_germ_result','newest_lot_germ_year',
                                  'est total viable g','est total viable pops','notes'];
                        foreach($rQ['raOut'] as $ra) {
                            $raG[] = [$ra['kPcv'], $ra['species'], $ra['cultivar'], $ra['csci_count'], $ra['adoption']??'',
                                      $ra['newest_lot_year'],         // deprecate
                                      $ra['total_grams']??0,
                                      $ra['newest_lot_grams'],        // deprecate
                                      $ra['newest_lot_germ_result'],  // deprecate
                                      $ra['newest_lot_germ_year'],    // deprecate
                                      $ra['est_total_viable_grams']??0, $ra['est_total_viable_pops']??0, $ra['notes']??""];
                        }
                        //$raG = SEEDCore_utf8_encode($raG);    using config_bUTF8=true above
//var_dump($raG);
                        $nBottom = count($raG)+1;
                        $oGoogleSheet->WriteValues($nameSheet."!A1:M{$nBottom}", $raG);
                        $this->oApp->oC->AddUserMsg("Wrote table to google sheet");
                    }
                    break;
            }
        }

        return( $s );
    }
}
