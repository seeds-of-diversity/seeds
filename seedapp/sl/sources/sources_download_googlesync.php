<?php

/* Seed Sources Sync sl_cv_sources with a Google sheet
 *
 * Copyright (c) 2026 Seeds of Diversity Canada
 *
 */

include_once( SEEDLIB."google/GoogleSheets.php" );


class SLSourcesDownload_GoogleSheetSync
{
    private $oApp;
    private $oGoogleSheet;
    private $nameSheet;

    private const raSheetColnames = ['k'=>'I','company'=>'S','species'=>'S','cultivar'=>'S','organic'=>'I','bulk'=>'I','hybrid'=>'S','synonym'=>'S','notes'=>'S'];

    function __construct( SEEDAppConsole $oApp,  array $raConfig )
    {
//        $idSpreadsheet = $raConfig['idSpreadsheet'];
        $this->oApp = $oApp;
        $this->nameSheet = SEEDCore_ArraySmartVal1($raConfig, 'nameSheet', "Worksheet");


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

        if( true || SEEDInput_Int('doGoogleSheetSync') ) {
            $this->nameSheet = "Worksheet";
// only needed for steps 1 and 3
            $this->oGoogleSheet = new SEEDGoogleSheets_NamedColumns(
                            ['appName' => 'My PHP App',
                             'authConfigFname' => SEEDCONFIG_DIR."sod-public-outreach-info-e36071bac3b1.json",
                             'idSpreadsheet' => $idSpreadsheet] );

            switch($iStep) {
                default:
                case 0: $ok = true;                                      $btnLabel = "Start Sync";       break;
                case 1: list($ok, $s) = $this->step1_FetchAndProcess();  $btnLabel = "Commit to Db";     break;
                case 2: list($ok, $s) = $this->step2_WriteDB();          $btnLabel = "Write to Sheet";   break;
                case 3: list($ok, $s) = $this->step3_WriteSheet();       $btnLabel = "";                 break;
            }
        }

        if( $ok ) {
            $s .= "<h3>Step $iStep</h3>";

            $s .= "<form method='post'>
                       {$oForm->Text('idSpreadsheet', "", ['size'=>50, 'placeholder'=>"spreadsheet id"])}<br/>
                       {$oForm->Text('nameSheet',     "", ['size'=>30, 'placeholder'=>"sheet name"])}<br/>
                       <input type='hidden' name='iStep' value='".($iStep+1)."'/>
                       <input type='hidden' name='doGoogleSheetSync' value='1'/>
                       <button>$btnLabel</button>
                   </form>";
        }

        return($s);
    }




    private function step1_FetchAndProcess()
    {
        $s = "";
        $ok = false;

        $this->initDb();

        $raProperties = $this->oGoogleSheet->GetProperties($this->nameSheet);
        $s .= "<p>Spreadsheet has {$raProperties['rowsUsed']} rows, {$raProperties['colsUsed']} columns";

        /* Verify spreadsheet has the required column headers
         */
        $raColnames = $this->oGoogleSheet->GetColumnNames($this->nameSheet, ['bFetchAllRows'=>true]);
        foreach(self::raSheetColnames as $col => $type) {
            if(!in_array($col,$raColnames)) {
                $s .= "<div class='alert alert-danger'>Required columns: ".SEEDCore_ArrayExpandSeries(self::raSheetColnames,"[[k]],")."<br/>
                       but found".SEEDCore_ArrayExpandSeries($raColnames,"[[]],")."</div>";
                goto done;
            }
        }

        /* Fetch data in column-keyed array
         */
        $raRows = $this->oGoogleSheet->GetRowsWithNamedColumns($this->nameSheet);

        $sql = "INSERT INTO gsheettest VALUES ";
        for( $i = 0; $i < 1000; $i++ ) {
            $raSql = [];
            foreach(self::raSheetColnames as $col => $type) {
                $raSql[] = $type=='I' ? intval(@$raRows[$i][$col]) : ("'".addslashes(@$raRows[$i][$col]??"")."'");
            }
            $raSql[] = $i;  // the row number

            // build ,(v1,v2,'v3',...)
            $sql .= ($i ? "," : "")
                   ."(".implode(',', $raSql).")";
        }
        $this->oApp->kfdb->Execute($sql);

        $this->oApp->kfdb->Execute("UPDATE gsheettest G, sl_species S, sl_pcv P, sl_pcv_syn PS SET G.synonym=P.name
                                    WHERE G.species=S.psp AND S._key=P.fk_sl_species AND P._key=PS.fk_sl_pcv AND PS.name=G.cultivar");

        $ok = true;

        done:
        return([$ok,$s]);
    }

    private function step2_WriteDB()
    {
        // copy from tmp table to sl_cv_sources
        $s = "";
        $ok = false;

        $s .= "This will commit to sl_cv_sources";
        $ok = true;

        return([$ok,$s]);
    }

    private function step3_WriteSheet()
    {
        // copy from tmp table to sl_cv_sources
        $s = "";
        $ok = false;

        if( ($dbc = $this->oApp->kfdb->CursorOpen("SELECT synonym,i FROM gsheettest")) ) {
            $nRows = $this->oApp->kfdb->CursorGetNumRows($dbc);
            $raPcv = array_fill(0,$nRows,[""]);
            while( ($ra = $this->oApp->kfdb->CursorFetch($dbc)) ) {
                $raPcv[$ra[1]][0] = $ra[0];
            }
            //var_dump($raPcv);
            $nBottom = $nRows+1;    // A1 row number of the last row
$this->oGoogleSheet->WriteValues($this->nameSheet."!I2:I{$nBottom}", $raPcv);
            $s .= "<p>Wrote $nRows cells to column I</p>";
        }

        return([$ok,$s]);
    }

    private function initDb()
    {
        $table = "{$this->oApp->DBName('seeds1')}.gsheettest";
        $this->oApp->kfdb->Execute(
            "CREATE TABLE IF NOT EXISTS $table (
                 k        integer,
                 company  text,
                 species  text,
                 cultivar text,
                 organic  int,
                 bulk     int,
                 hybrid   text,
                 synonym  text,
                 notes    text,
                 i        int )
            ");
        $this->oApp->kfdb->Execute("DELETE FROM $table");
    }
}
