<?php

/* Seed Sources Sync sl_cv_sources with a Google sheet
 *
 * Copyright (c) 2026 Seeds of Diversity Canada
 *
 */

include_once(SEEDLIB."google/GoogleSheets.php");
include_once(SEEDLIB."sl/sources/sl_sources_rosetta.php");
include_once(SEEDLIB."sl/sources/sl_sources_cv_upload.php");

class SLSourcesAppDownload_CSCIUpload_GoogleSheet
{
    private $oApp;
    private $oUploadLib;
    private $oForm;
    private $nameSheet;
    private $oGoogleSheet;

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

    function __construct( SEEDAppConsole $oApp, SLSourcesCVUpload $oUploadLib, array $raConfig )
    {
//        $idSpreadsheet = $raConfig['idSpreadsheet'];
        $this->oApp = $oApp;
        $this->oUploadLib = $oUploadLib;

// shared namespace?
        /* FormSession captures and stores the sheet's id and tabname
         */
        $this->oForm = new SEEDCoreFormSession($this->oApp->sess, 'SLSourcesAppDownload_CSCIUpload');
        $this->oForm->Update();
        $idSpreadsheet = $this->oForm->Value('idSpreadsheet');
        $this->nameSheet = $this->oForm->Value('nameSheet');
        $this->oGoogleSheet = new SEEDGoogleSheets_NamedColumns(
                            ['appName' => 'My PHP App',
                             'authConfigFname' => SEEDCONFIG_DIR."sod-public-outreach-info-e36071bac3b1.json",
                             'idSpreadsheet' => $idSpreadsheet] );
    }

    function GetFormControls()
    {
        return( "{$this->oForm->Text('idSpreadsheet', "", ['size'=>50, 'placeholder'=>"spreadsheet id"])}<br/>
                 {$this->oForm->Text('nameSheet',     "", ['size'=>30, 'placeholder'=>"sheet name"])}<br/>" );
    }

    function FetchSheetToTmpTable()
    {
        $s = "";
        $ok = false;

        $this->oUploadLib->InitTmpTable();

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

        $sql = "INSERT INTO {$this->oUploadLib->TmpTableName()} (k,company,osp,ocv,organic,bulk,hybrid,notes,i) VALUES ";
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

        $nRows = $this->oApp->kfdb->Query1("SELECT count(*) FROM {$this->oUploadLib->TmpTableName()}");
        $s .= "<p>Loaded {$nRows} rows into temporary database table</p>";

        $ok = true;

        done:
        return([$ok,$s]);
    }

    function WriteToSheet()
    {
        // copy from tmp table to sl_cv_sources
        $s = "";
        $ok = false;

        // the size of the re-written sheet area is the max 0-origin row number +1; this goes from sheet row 2 to $nSheetRows+1; i.e. 2 to max(i)+2
        $nSheetRows = $this->oApp->kfdb->Query1("SELECT MAX(i) FROM {$this->oUploadLib->TmpTableName()}") + 1;
        $raPcv = array_fill(0,$nSheetRows,[""]);
        if( ($dbc = $this->oApp->kfdb->CursorOpen("SELECT pcv,i FROM {$this->oUploadLib->TmpTableName()} WHERE pcv<>''")) ) {
        //    $nRows = $this->oApp->kfdb->CursorGetNumRows($dbc);
            var_dump($this->oApp->kfdb->CursorGetNumRows($dbc));
            while( ($ra = $this->oApp->kfdb->CursorFetch($dbc)) ) {
                $raPcv[$ra[1]][0] = SEEDCore_utf8_encode($ra[0]);
            }
            //var_dump($raPcv);
            $nBottom = $nSheetRows+1;    // A1 row number of the last row
var_dump("WRITING TO COLUMN I, MUST FIND COLUMN FROM NAME");
$this->oGoogleSheet->WriteValues($this->nameSheet."!I2:I{$nBottom}", $raPcv);
            $s .= "<p>Wrote $nSheetRows cells to column I</p>";
        }

        return([$ok,$s]);
    }
}
