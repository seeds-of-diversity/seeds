<?php

/* Seed Sources Sync sl_cv_sources with a Google sheet
 *
 * Copyright (c) 2026 Seeds of Diversity Canada
 *
 */

include_once(SEEDLIB."google/GoogleSheets.php");
include_once(SEEDLIB."sl/sources/sl_sources_rosetta.php");
include_once(SEEDLIB."sl/sources/sl_sources_cv_upload.php");

class SLSourcesDownload_GoogleSheetSync
{
    private $oApp;
    private $oGoogleSheet;
    private $nameSheet;
    private $tmpTable;
    private $oUpload;

    private const raSheetColnames = [
        'k'         => ['sheetColName'=>'k',        'type'=>'I', 'bReadFromSheet'=>true],
        'company'   => ['sheetColName'=>'company',  'type'=>'S', 'bReadFromSheet'=>true],
        'species'   => ['sheetColName'=>'species',  'type'=>'S', 'bReadFromSheet'=>true],
        'cultivar'  => ['sheetColName'=>'cultivar', 'type'=>'S', 'bReadFromSheet'=>true],
        'organic'   => ['sheetColName'=>'organic',  'type'=>'I', 'bReadFromSheet'=>true],
        'bulk'      => ['sheetColName'=>'bulk',     'type'=>'I', 'bReadFromSheet'=>true],
        'hybrid'    => ['sheetColName'=>'hybrid',   'type'=>'S', 'bReadFromSheet'=>true],
        'notes'     => ['sheetColName'=>'notes',    'type'=>'S', 'bReadFromSheet'=>true],

        'pcv'       => ['sheetColName'=>'primary',  'type'=>'S', 'bReadFromSheet'=>false],    // written but not read
    ];

    function __construct( SEEDAppConsole $oApp,  array $raConfig )
    {
//        $idSpreadsheet = $raConfig['idSpreadsheet'];
        $this->oApp = $oApp;
        $this->nameSheet = SEEDCore_ArraySmartVal1($raConfig, 'nameSheet', "Worksheet");
        $this->tmpTable = "{$this->oApp->DBName('seeds1')}.sl_tmp_cv_sources";
        $this->oUpload = new SLSourcesCVUpload($this->oApp, SLSourcesCVUpload::ReplaceWholeCSCI, 0, $this->tmpTable);
    }

    function DoSync()
    {
        $s = "";
        $ok = false;

        $oForm = new SEEDCoreFormSession($this->oApp->sess, 'SLSources_GoogleSheetSync');
        $oForm->Update();
        $idSpreadsheet = $oForm->Value('idSpreadsheet');
        $this->nameSheet = $oForm->Value('nameSheet');
        $iStep = SEEDInput_Int('iStep');  // don't make this sticky $oForm->ValueInt('iStep');

// only needed for steps 1 and 3
            $this->oGoogleSheet = new SEEDGoogleSheets_NamedColumns(
                            ['appName' => 'My PHP App',
                             'authConfigFname' => SEEDCONFIG_DIR."sod-public-outreach-info-e36071bac3b1.json",
                             'idSpreadsheet' => $idSpreadsheet] );



        switch( SEEDInput_Str('actionStep') ) {
            case '':                                $iStep = 0;                           break;      // no form submission - iStep should be zero anyway
            case 'Start over':                      $iStep = 0;                           break;      // override iStep to force restart
            case 'Redo this step':                                                        break;      // use iStep again
            case 'Commit to Seed Finder Database':  if($iStep==3) $this->CommitToDb();    break;
            case 'Write to Google Sheet':           if($iStep==3) $this->WriteToSheet();  break;
            default:                                ++$iStep;                             break;      // button label described step 1, 2, 3
        }
        $iStep = min($iStep, 3);

        $raStep = [0 => ['name'=>"Start Sync"],
                   1 => ['name'=>"Load from sheet"],
                   2 => ['name'=>"Validate"],
                   3 => ['name'=>"Prepare update"],


            4 => ['name'=>"Commit to Seed Finder Database"],
                   5 => ['name'=>"Write to sheet"],
            ];


        $sCtrls = $sResults = "";
        switch($iStep) {
            default:
            case 0:
                /* Starting form: get config for spreadsheet to load
                 */
                $ok = true;
                $sCtrls = "{$oForm->Text('idSpreadsheet', "", ['size'=>50, 'placeholder'=>"spreadsheet id"])}<br/>
                           {$oForm->Text('nameSheet',     "", ['size'=>30, 'placeholder'=>"sheet name"])}<br/>";
                break;
            case 1:
                /* Fetch from google sheet, insert to tmp table
                 */
                list($ok, $sResults) = $this->step1_Fetch();
                break;
            case 2:
                /* Validate tmp table
                 */
                list($ok, $sResults) = $this->step2_Validate();
                break;
            case 3:
                /* Commit tmp table to sl_cv_sources
                 */
                list($ok, $sResults) = $this->step3_PrepareUpdate();
                break;
        }

        $btnNext = "";
        if($iStep < 3) {
            // the first three steps have a Next button
            $btnNext = "<input type='submit' name='actionStep' value='Next: ".$raStep[$iStep+1]['name']."'/>";
        } else {
            // the final step has various operations that lead to the same state
            $raReport = $this->oUpload->CalculateUploadReport();
            $sDisableCommit = !($ok && $this->oUpload->IsCommitAllowed($raReport)) ? " disabled" : "";
            $sDisableSheetWrite = !($ok && true /* google sheet mode */) ? " disabled" : "";
            $btnNext .= "<input type='submit' name='actionStep' value='Commit to Seed Finder Database' $sDisableCommit/>&nbsp;&nbsp;&nbsp;";
            $btnNext .= "<input type='submit' name='actionStep' value='Write to Google Sheet'          $sDisableSheetWrite/>";
        }

        $s .= "<h3>Step $iStep : {$raStep[$iStep]['name']}</h3>";

        $s .= "<form method='post'>
                   $sCtrls
                   <input type='hidden' name='iStep' value='{$iStep}'/>"
                 // last step has no Next button
                 .$btnNext
                 // first step has no Redo or Start over
                 .($iStep ? "<input style='float:right' type='submit' name='actionStep' value='Redo this step'/>
                             <input style='float:right' type='submit' name='actionStep' value='Start over'/>" : "")
             ."</form>
               <div style='margin:2em'>$sResults</div>";

        return($s);
    }

    private function step1_Fetch()
    {
        $s = "";
        $ok = false;

        $this->oUpload->InitTmpTable();

        $raProperties = $this->oGoogleSheet->GetProperties($this->nameSheet);
        $s .= "<p>Spreadsheet has {$raProperties['rowsUsed']} rows, {$raProperties['colsUsed']} columns";

        /* Verify spreadsheet has the required column headers
         */
        $raColnames = $this->oGoogleSheet->GetColumnNames($this->nameSheet, ['bFetchAllRows'=>true]);
        foreach(self::raSheetColnames as $col => $raCol) {
            if(!in_array($raCol['sheetColName'],$raColnames)) {
                $s .= "<div class='alert alert-danger'>Required columns: ".SEEDCore_ArrayExpandRows(self::raSheetColnames,"[[sheetColName]],")."<br/>
                       but found".SEEDCore_ArrayExpandSeries($raColnames,"[[]],")."</div>";
                goto done;
            }
        }

        /* Fetch data in column-keyed array (0-origin keys correspond to 2-origin spreadsheet rows)
         */
        $raRows = $this->oGoogleSheet->GetRowsWithNamedColumns($this->nameSheet);

        $sql = "INSERT INTO {$this->tmpTable} (k,company,osp,ocv,organic,bulk,hybrid,notes,i) VALUES ";
        for( $i = 0; $i < count($raRows); $i++ ) {
            // skip rows with blank required columns
            if( !$raRows[$i][self::raSheetColnames['company']['sheetColName']] ||
                !$raRows[$i][self::raSheetColnames['species']['sheetColName']] ||
                !$raRows[$i][self::raSheetColnames['cultivar']['sheetColName']] ) continue;

            $raSql = [];
            foreach(self::raSheetColnames as $col => $raCol) {
                if( !$raCol['bReadFromSheet'] ) continue;     // skip non-read columns
                $v = @$raRows[$i][self::raSheetColnames[$col]['sheetColName']] ?? "";
                $raSql[] = $raCol['type']=='I' ? intval($v) : ("'".addslashes($v)."'");
            }
            $raSql[] = $i;  // the row number - add 2 to this to get the A1 sheet row number

            // build ,(v1,v2,'v3',...)
            $sql .= ($i ? "," : "")
                   ."(".implode(',', $raSql).")";
        }
// convert company and cultivar names to latin1 so the db can match them
$sql = SEEDCore_utf8_decode($sql);
        $this->oApp->kfdb->Execute($sql);

        $nRows = $this->oApp->kfdb->Query1("SELECT count(*) FROM {$this->tmpTable}");
        $s .= "<p>Loaded {$nRows} rows into temporary database table</p>";

        $ok = true;

        done:
        return([$ok,$s]);
    }

    private function step2_Validate()
    {
        $s = "";
        $ok = false;

        /* Build fk_sl_sources, fk_sl_species, fk_sl_pcv and report success
         */
        $s .= SLSourceCV_Build::BuildAll($this->oApp, $this->tmpTable);
        $raStatus = SLSourceCV_Build::GetTableStatus($this->oApp, $this->tmpTable);
        $s .= $raStatus['sReport'];

        /* Update pcv column if ocv <> pcv (pcv was found via sl_pcv_syn)
         */
        $this->oApp->kfdb->Execute("UPDATE {$this->tmpTable} G, sl_pcv P
                                    SET G.pcv=P.name
                                    WHERE G.fk_sl_pcv<>0 AND G.fk_sl_pcv=P._key AND G.ocv<>P.name");

        $ok = true;

        done:
        return([$ok,$s]);
    }

    private function step3_PrepareUpdate()
    {
        // prepare and explain update operations in tmp table to be applied to sl_cv_sources
        $s = "";
        $ok = false;

        /* Compute the differences between tmptable and sl_cv_sources
         */
        $o = new SLSourcesCVUpload($this->oApp, SLSourcesCVUpload::ReplaceWholeCSCI, 0, $this->tmpTable);
        $o->ComputeDiff();
        $raReport = $o->CalculateUploadReport();
        if( $raReport['nRowsSameDiffKeys'] ) {
            $s .= $o->FixMatchingRowKeys();
        }
        $s .= $o->DrawUploadReport( $raReport );

        $ok = true;

        return([$ok,$s]);
    }

    private function CommitToDb()
    {
        // copy from tmp table to sl_cv_sources
        $s = "";
        $ok = false;

        list($ok,$s,$sErr) = $this->oUpload->Commit();
        if($sErr) $s .= "<div class='alert alert-warning'>$sErr</div>";

        return([$ok,$s]);
    }

    private function WriteToSheet()
    {
        // copy from tmp table to sl_cv_sources
        $s = "";
        $ok = false;
var_dump("WRITING TO COLUMN I, MUST FIND COLUMN FROM NAME");
        // the size of the re-written sheet area is the max 0-origin row number +1; this goes from sheet row 2 to $nSheetRows+1; i.e. 2 to max(i)+2
        $nSheetRows = $this->oApp->kfdb->Query1("SELECT MAX(i) FROM {$this->tmpTable}") + 1;
        $raPcv = array_fill(0,$nSheetRows,[""]);
        if( ($dbc = $this->oApp->kfdb->CursorOpen("SELECT pcv,i FROM {$this->tmpTable} WHERE pcv<>''")) ) {
        //    $nRows = $this->oApp->kfdb->CursorGetNumRows($dbc);
            var_dump($this->oApp->kfdb->CursorGetNumRows($dbc));
            while( ($ra = $this->oApp->kfdb->CursorFetch($dbc)) ) {
                $raPcv[$ra[1]][0] = SEEDCore_utf8_encode($ra[0]);
            }
            //var_dump($raPcv);
            $nBottom = $nSheetRows+1;    // A1 row number of the last row
$this->oGoogleSheet->WriteValues($this->nameSheet."!I2:I{$nBottom}", $raPcv);
            $s .= "<p>Wrote $nSheetRows cells to column I</p>";
        }

        return([$ok,$s]);
    }
}
