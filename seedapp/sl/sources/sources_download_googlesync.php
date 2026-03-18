<?php

/* Seed Sources Sync sl_cv_sources with a Google sheet
 *
 * Copyright (c) 2026 Seeds of Diversity Canada
 *
 */

include_once(SEEDLIB."google/GoogleSheets.php");
include_once(SEEDLIB."sl/sources/sl_sources_rosetta.php");

class SLSourcesDownload_GoogleSheetSync
{
    private $oApp;
    private $oGoogleSheet;
    private $nameSheet;
    private $tmpTable;

    private const raSheetColnames = [
        'k'         => ['sheetColName'=>'k',        'type'=>'I', 'bReadFromSheet'=>true],
        'company'   => ['sheetColName'=>'company',  'type'=>'S', 'bReadFromSheet'=>true],
        'species'   => ['sheetColName'=>'species',  'type'=>'S', 'bReadFromSheet'=>true],
        'cultivar'  => ['sheetColName'=>'cultivar', 'type'=>'S', 'bReadFromSheet'=>true],
        'organic'   => ['sheetColName'=>'organic',  'type'=>'I', 'bReadFromSheet'=>true],
        'bulk'      => ['sheetColName'=>'bulk',     'type'=>'I', 'bReadFromSheet'=>true],
        'hybrid'    => ['sheetColName'=>'hybrid',   'type'=>'S', 'bReadFromSheet'=>true],
        'notes'     => ['sheetColName'=>'notes',    'type'=>'S', 'bReadFromSheet'=>true],

        'synonym'   => ['sheetColName'=>'synonym',  'type'=>'S', 'bReadFromSheet'=>false],    // written but not read
    ];

    function __construct( SEEDAppConsole $oApp,  array $raConfig )
    {
//        $idSpreadsheet = $raConfig['idSpreadsheet'];
        $this->oApp = $oApp;
        $this->nameSheet = SEEDCore_ArraySmartVal1($raConfig, 'nameSheet', "Worksheet");
        $this->tmpTable = "{$this->oApp->DBName('seeds1')}.sl_tmp_cv_sources2";

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

        switch( SEEDInput_Str('actionStep') ) {
            case '':                $iStep = 0;  break;      // no form submission - iStep should be zero anyway
            case 'Start over':      $iStep = 0;  break;      // override iStep to force restart
            case 'Redo this step':               break;      // use iStep again
            default:                ++$iStep;    break;      // button label described the next step
        }



// only needed for steps 1 and 3
            $this->oGoogleSheet = new SEEDGoogleSheets_NamedColumns(
                            ['appName' => 'My PHP App',
                             'authConfigFname' => SEEDCONFIG_DIR."sod-public-outreach-info-e36071bac3b1.json",
                             'idSpreadsheet' => $idSpreadsheet] );

        $raStep = [0 => ['name'=>"Start Sync"],
                   1 => ['name'=>"Fetch from sheet"],
                   2 => ['name'=>"Validate"],
                   3 => ['name'=>"Commit to Db"],
                   4 => ['name'=>"Write to sheet"],
            ];


        $sCtrls = $sResults = "";
        switch($iStep) {
            default:
            case 0:
                /* Starting form: get config for spreadsheet to load
                 */
                $ok = true;
                $btnLabelNext = "Start Sync";
                $sCtrls = "{$oForm->Text('idSpreadsheet', "", ['size'=>50, 'placeholder'=>"spreadsheet id"])}<br/>
                           {$oForm->Text('nameSheet',     "", ['size'=>30, 'placeholder'=>"sheet name"])}<br/>";
                break;
            case 1:
                /* Fetch from google sheet, insert to tmp table
                 */
                list($ok, $sResults) = $this->step1_Fetch();
                $btnLabelNext = "Validate";
                break;
            case 2:
                /* Validate tmp table
                 */
                list($ok, $sResults) = $this->step2_Validate();
                $btnLabelNext = "Commit to Db";
                break;
            case 3:
                /* Commit tmp table to sl_cv_sources
                 */
                list($ok, $sResults) = $this->step3_WriteDB();
                $btnLabelNext = "Write to Sheet";
                break;
            case 4:
                /* Copy computed columns to google sheet
                 */
                list($ok, $sResults) = $this->step4_WriteSheet();
                $btnLabelNext = "";
                break;
        }

        $s .= "<h3>Step $iStep : {$raStep[$iStep]['name']}</h3>";

        $s .= "<form method='post'>
                   $sCtrls
                   <input type='hidden' name='iStep' value='{$iStep}'/>"
                 // last step has no Next button
                 .($iStep < 4 ? "<input type='submit' name='actionStep' value='Next: ".$raStep[$iStep+1]['name']."'/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" : "")
                 // first step has no Redo or Start over
                 .($iStep ? "<input type='submit' name='actionStep' value='Redo this step'/>
                             <input type='submit' name='actionStep' value='Start over'/>" : "")
             ."</form>
               <div style='margin:2em'>$sResults</div>";

        return($s);
    }

    private function step1_Fetch()
    {
        $s = "";
        $ok = false;

        $this->initDb();

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
        $s .= $raStatus['sReport']
             ."<h4>Next Step</h4><p>If you commit this data, records for {$raStatus['nSpMatched']} from ".count($raStatus['raSrcMatched'])." companies will be copied</p>";

        /* Update synonym column if cv <> pcv (pcv was found via sl_pcv_syn)
         */
        $this->oApp->kfdb->Execute("UPDATE {$this->tmpTable} G, sl_pcv P
                                    SET G.synonym=P.name
                                    WHERE G.fk_sl_pcv<>0 AND G.fk_sl_pcv=P._key AND G.ocv<>P.name");

        $ok = true;

        done:
        return([$ok,$s]);
    }

    private function step3_WriteDB()
    {
        // copy from tmp table to sl_cv_sources
        $s = "";
        $ok = false;

        $s .= "This will commit to sl_cv_sources";
        $ok = true;

        return([$ok,$s]);
    }

    private function step4_WriteSheet()
    {
        // copy from tmp table to sl_cv_sources
        $s = "";
        $ok = false;

        // the size of the re-written sheet area is the max 0-origin row number +1; this goes from sheet row 2 to $nSheetRows+1; i.e. 2 to max(i)+2
        $nSheetRows = $this->oApp->kfdb->Query1("SELECT MAX(i) FROM {$this->tmpTable}") + 1;
        $raPcv = array_fill(0,$nSheetRows,[""]);
        if( ($dbc = $this->oApp->kfdb->CursorOpen("SELECT synonym,i FROM {$this->tmpTable} WHERE synonym<>''")) ) {
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

    private function initDb()
    {
        // cols have to match SLSourceCV_Build tmp table
        $this->oApp->kfdb->Execute(
            "CREATE TABLE IF NOT EXISTS {$this->tmpTable} (
                 k        integer,
                 company  text,
                 osp      text,
                 ocv      text,
                 organic  int default 0,
                 bulk     int default 0,
                 hybrid   text,
                 notes    text,

                 synonym  text,
                 i        int,                      # 0-origin row number - add 2 to this to get the A1 sheet row number
                 op       char,
                 fk_sl_sources int default 0,
                 fk_sl_species int default 0,
                 fk_sl_pcv     int default 0,

                 _status       int default 0
               )
            ");
        $this->oApp->kfdb->Execute("DELETE FROM {$this->tmpTable}");
    }
}
