<?php

include_once( SEEDCORE."console/console02ui.php");

class Rosetta_TS_Admin
{
    private $oApp;
    private $oSVA;  // session vars for the UI tab (all batch ops modules)
    private $oOpPicker;

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;

        $raOps = ['Upload/Download Cultivar Synonyms'=>'cvsyn_updown',
                  'Other Operation'=>'other'];
        $this->oOpPicker = new Console02UI_OperationPicker('batchop', $oSVA, $raOps);
    }

    function Init()
    {
        // Independent of any state of the worker because that only exists in ContentDraw
    }

    function ControlDraw()  { return( $this->oOpPicker->DrawDropdown() ); }

    function ContentDraw()
    {
        $s = "";

        switch( $this->oOpPicker->Value() ) {
            case 'cvsyn_updown':        $s = (new Rosetta_TS_Admin_CVSynUploadDownload($this->oApp,$this->oSVA))->Draw();                   break;
        }

        return( $s );
    }
}

include_once(SEEDLIB."google/GoogleSheets.php");

class Rosetta_TS_Admin_CVSynUploadDownload
{
    private $oApp;
    private $oSVA;
    private $oSLDB;

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;

        $this->oSLDB = new SLDBRosetta( $oApp );
    }

    function Draw()
    {
        $sNotificationUpload = $sNotificationDownload = "";

        $s = "<h3>Download/Upload Cultivar Synonyms</h3>";

        // oSVA should be the TabSet-SVA for the current tab
        $oForm = new SEEDCoreFormSVA($this->oSVA, 'A');
        $oForm->Update();

        /* Do the Download/Upload
         */
        switch(SEEDInput_Str('p_cmd')) {
            case "Download to google sheet":  $sNotificationDownload = $this->downloadToSheet($oForm);  break;
            case "Upload to Rosetta":         $sNotificationUpload   = $this->uploadToDb($oForm);       break;
        }

        /* Draw google sheet controls, and buttons for Download and Upload
         */
        $s .= "<form method='post'>
               <div style='display:inline-block;vertical-align:top;padding-left:1em'border:1px solid #aaa;border-radius:5px'><form>
               {$oForm->Text('idSpreadsheet', "", ['size'=>"100", 'placeholder'=>"google spreadsheet address -- https://docs.google.com/spreadsheets/d/..."])}<br/>
               {$oForm->Text('nameSheet',     "", ['size'=>30, 'placeholder'=>"sheet tab name"])}<br/><br/>
               The spreadsheet must be shared to \"Anyone with the link\" as Editor.
               </div>

               <div style='margin:2em 1em;padding:1em;border:1px solid #aaa'>
                   $sNotificationDownload
                   <h4>Download cultivar synonyms from Rosetta to google sheet</h4>
                   <p>This will overwrite everything on the named sheet tab.</p>
                   <form method='post'><input name='p_cmd' type='submit' value='Download to google sheet'/></form>
               </div>

               <div style='margin:1em;padding:1em;border:1px solid #aaa'>
                   $sNotificationUpload
                   <h4>Upload cultivar synonyms from google sheet to Rosetta</h4>
                   <p>This will overwrite everything in the Rosetta <em>Cultivar Synonyms</em> database.</p>
                   <form method='post'><input name='p_cmd' type='submit' value='Upload to Rosetta'/></form>
               </div>
               </form>";

        return($s);
    }

    private function downloadToSheet(SEEDCoreFormSVA $oForm)
    {
        $s = "";

        list($oGoogleSheet,$s) = $this->getGoogleSheet($oForm);
        if( !$oGoogleSheet ) goto done;
        $nameSheet = $oForm->Value('nameSheet');

// ensure sheet is blank

        $raG[] = $this->raSheetHeaders;

        $raPY = $this->oSLDB->GetList('PYxPxS',"");
        $nBottom = count($raPY)+1;
        foreach($raPY as $ra) {
            $raG[] = [$ra['_key'],/*$ra['fk_sl_pcv'],*/$ra['S_psp']??"",$ra['P_name']??"",$ra['name']??"",$ra['notes']??""];
        }
        $raG = SEEDCore_utf8_encode($raG);
        $oGoogleSheet->WriteValues($nameSheet."!A1:F{$nBottom}", $raG);

        $s = "<div class='alert alert-success'>Downloaded ".($nBottom-1)." rows to the google sheet</div>";

        done:
        return($s);
    }

    private $raSheetHeaders = ['kSyn',/*'kPcv',*/'species','primary name','synonym','notes'];

    private function uploadToDb(SEEDCoreFormSVA $oForm)
    {
        $s = "";

        list($oGoogleSheet,$s) = $this->getGoogleSheet($oForm);
        if( !$oGoogleSheet ) goto done;

        $nameSheet = $oForm->Value('nameSheet');
        $raG = $oGoogleSheet->GetRowsWithNamedColumns($nameSheet);
        $raG = SEEDCore_utf8_decode($raG);

        /* Validate the spreadsheet has the expected headers
         */
        foreach($this->raSheetHeaders as $h) {
            if( !array_key_exists($h, $raG[0]) ) {
                $s = "<div class='alert alert-danger'>Google sheet must have column headers: ".implode(", ",$this->raSheetHeaders)."</div>";
                goto done;
            }
        }

        /* Validate the spreadsheet contains allowed changes
         */
        $raNew = $raChange = [];
        $raPY = $this->oSLDB->GetListKeyed('PYxPxS','_key',"");
        $nRow = 1;  // count header row
        foreach($raG as $ra) {
            ++$nRow;
            // check all cols are non-blank


            if( ($kPY_g = intval($ra['kSyn'])) ) {
                /* Existing row
                 */
                // check species, primary name have not changed

//var_dump($ra,$raPY[$kPY_g]);
                if( $ra['synonym'] != $raPY[$kPY_g]['name'] || $ra['notes'] != $raPY[$kPY_g]['notes'] ) {
                    $raChange[$kPY_g] = ['sname'=>$ra['synonym'], 'notes'=>$ra['notes']];
                }
            } else {
                /* New row
                 */
                // check species, primary name make sense
                if( !($kPcv = $this->oSLDB->GetRecordVal1Cond("PxS", "name='".addslashes($ra['primary name'])."' AND S_psp='".addslashes($ra['species'])."'")) ) {
                    $s = "<div class='alert alert-danger'>Unknown species or primary name at row $nRow of Google sheet</div>";
                    goto done;
                }

                $raNew[] = ['kPcv'=>$kPcv,'sname'=>$ra['synonym'], 'notes'=>$ra['notes']];
            }
        }

        /* Checks have passed, and raNew/raChange contain the expected changes
         */
        $s = "<div class='alert alert-success'>Uploaded $nRow rows from Google sheet.<br/>"
            .(($n = count($raNew)) ? "$n new synonyms added.<br/>" : "")
            .(($n = count($raChange)) ? "$n synonyms/notes changed.<br/>" : "")
            .((count($raNew)+count($raChange)==0) ? "No changes made" : "")
            ."</div>";

        foreach($raNew as $ra) {
            $kfr = $this->oSLDB->KFRel('PY')->CreateRecord();
            $kfr->SetValue('fk_sl_pcv', $ra['kPcv']);
            $kfr->SetValue('name', $ra['sname']);
            $kfr->SetValue('notes', $ra['notes']);
            $kfr->PutDBRow();
            $s .= "<p>Added synonym {$ra['sname']} [{$ra['notes']}]</p>";
        }
        foreach($raChange as $kPY => $ra) {
            $kfr = $this->oSLDB->GetKFR('PY', $kPY);
            $kfr->SetValue('name', $ra['sname']);
            $kfr->SetValue('notes', $ra['notes']);
            $kfr->PutDBRow();
            $s .= "<p>Changed synonym {$raPY[$kPY]['name']} = {$ra['sname']} [{$ra['notes']}]</p>";
        }

        done:
        return($s);
    }

    private function getGoogleSheet(SEEDCoreFormSVA $oForm)
    {
        $oGoogleSheet = null;
        $sErr = "";

        $idSpreadsheet = $oForm->Value('idSpreadsheet');
        $nameSheet = $oForm->Value('nameSheet');
        if( $idSpreadsheet && $nameSheet ) {
            $oGoogleSheet = new SEEDGoogleSheets_NamedColumns(
                        ['appName' => 'My PHP App',
                         'authConfigFname' => SEEDCONFIG_DIR."sod-public-outreach-info-e36071bac3b1.json",
                         'idSpreadsheet' => $idSpreadsheet] );
        } else {
            $sErr = "<div class='alert alert-warning'>Specify the google spreadsheet address and sheet name above</div>";
        }

        return([$oGoogleSheet,$sErr]);
    }
}
