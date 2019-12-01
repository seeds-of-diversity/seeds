<?php

include_once( SEEDCORE."SEEDTableSheets.php" );
include_once( SEEDCORE."console/console02ui.php" );
include_once( SEEDLIB."sl/sources/sl_sources_db.php" );
include_once( SEEDLIB."sl/sources/sl_sources_cv_upload.php" );

class SLSourcesAppDownload
{
    private $oApp;
    private $oSVA;  // where to store state variables for this app
    private $oSrcLib;
    private $oUIPills;

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;
        $this->oSrcLib = new SLSourcesLib( $this->oApp );

        $raPills = array( 'companies'      => array( "Seed Companies"),
                          'companies-test' => array( "Seed Companies Test"),
                          'pgrc'           => array( "Canada: Plant Gene Resources (PGRC)" ),
                          'npgs'           => array( "USA: National Plant Germplasm System (NPGS)" ),
                          'sound'          => array( "Sound Tests" ),
                          'one-off-csci'   => array( "One-off CSCI loading" ),
        );

        $this->oUIPills = new SEEDUIWidgets_Pills( $raPills, 'pMode', array( 'oSVA' => $this->oSVA, 'ns' => '' ) );
    }

    function Draw()
    {
        $sMenu = $this->oUIPills->DrawPillsVertical();
        $sBody = "";
        switch( $this->oUIPills->GetCurrPill() ) {
            case 'companies':
                $sBody = $this->companies();
                break;
            case 'companies-test':
                $sBody = $this->companiesTest();
                break;
        }

        $s = "<div class='container-fluid'><div class='row'>"
                ."<div class='col-md-2'>$sMenu</div>"
                ."<div class='col-md-10'>$sBody</div>"
            ."</div></div>";

        return( $s );
    }

    private $companyTableDef = array( 'headers-required' => array('k','company','species','cultivar','organic','notes'),
                                      'headers-optional' => array() );

    private function companies()
    {
        $s = "";
        $sLater = "";

        $oUpload = new SLSourcesCVUpload( $this->oApp, SLSourcesCVUpload::ReplaceWholeCSCI, 0 );
        switch( SEEDInput_Str('cmd') ) {
            case 'cmpupload_cleartmp':
                $oUpload->ClearTmpTable();
                break;
            case 'cmpupload_rebuildtmp':
                list($bOk,$sOk,$sErr,$sWarn) = $oUpload->ValidateTmpTable();
                $s .= $sOk;
                if( $sErr )  $sLater .= "<div class='alert alert-danger'>$sErr</div>";
                if( $sWarn ) $sLater .= "<div class='alert alert-warning'>$sWarn</div>";
                break;
            case 'company_upload':
                $s .= $this->companies_uploadfile( $oUpload );
                break;
        }


        if( !$oUpload->IsTmpTableEmpty() ) {
            $s .= "<p><a href='?cmd=cmpupload_rebuildtmp'>Validate/Build/Rebuild Upload Table</a></p>";
            $s .= "<p><a href='?cmd=cmpupload_cleartmp'>Clear Upload Table</a></p>";
        }

        /* Report on upload status
         */
        $raReport = $oUpload->ReportTmpTable();
        if( $raReport['nRows'] ) {
            $s .= $this->companies_drawTmpReport( $raReport );
        } else {
            $s .= $this->companies_drawUploadForm();
        }

        $s .= $sLater;

        return( $s );
    }

    private function companies_drawTmpReport( $raReport )
    {
        $sErrorUnknownCompanies = $sErrorUnknownSpecies = $sErrorUnknownCultivars = "";
        if( ($n = count($raReport['raUnknownCompanies'])) ) {
            $sErrorUnknownCompanies = "<span style='color:red'> + $n unindexed</span>";
        }
        if( ($n = count($raReport['raUnknownSpecies'])) ) {
            $sErrorUnknownSpecies = "<span style='color:red'> + $n unindexed</span>";
        }
        if( ($n = count($raReport['raUnknownCultivars'])) ) {
            $sErrorUnknownCultivars = "<span style='color:red'> + $n unindexed</span>";
        }

        $s = "<style>"
               .".companyUploadResultsTable    { border-collapse-collapse; text-align:center }"
               .".companyUploadResultsTable th { text-align:center }"
               .".companyUploadResultsTable td { border:1px solid #aaa; padding:3px; text-align:center }"
               ."</style>";

        $s .= "<table class='companyUploadResultsTable'><tr><th>Existing</th><th width='50%'>Upload<br/>({$raReport['nRows']} rows)</th></tr>"
               ."<tr><td>&nbsp;</td><td>{$raReport['nDistinctCompanies']} companies indexed $sErrorUnknownCompanies</td></tr>"
               ."<tr><td>&nbsp;</td><td>{$raReport['nDistinctSpKeys']} distinct species indexed $sErrorUnknownSpecies</td></tr>"
               ."<tr><td>&nbsp;</td><td>{$raReport['nDistinctCvKeys']} distinct cultivars indexed $sErrorUnknownCultivars</td></tr>"
               ."<tr><td colspan='2'>{$raReport['nRowsSame']} rows are identical including the year</td></tr>"
               ."<tr><td colspan='2'>{$raReport['nRowsY']} rows are exactly the same except for the year (will be archived)</td></tr>"
               ."<tr><td colspan='2'>{$raReport['nRowsU']} rows have changed from previous year (will be archived)</td></tr>"
               ."<tr><td colspan='2'>{$raReport['nRowsV']} rows have corrections for current-year (won't be archived)</td></tr>"
               ."<tr><td>&nbsp;</td><td>{$raReport['nRowsN']} rows are new</td></tr>"
               ."<tr><td>&nbsp;</td><td>{$raReport['nRowsD1']} rows are marked in the spreadsheet for deletion</td></tr>"
               ."<tr><td>{$raReport['nRowsD2']} rows will be deleted because they are missing in the upload</td><td>&nbsp;</td></tr>"
               ."<tr><td>&nbsp;</td><td><span style='color:red'>{$raReport['nRowsUncomputed']} rows are not computed</span></td></tr>"
               ."</table><br/>";

        /* Warn about unindexed companies
         */
        if( count($raReport['raUnknownCompanies']) ) {
            $s .= "<div class='alert alert-danger'><p>These companies are not indexed. Please add to Sources list and try again.</p>"
                    ."<ul>".SEEDCore_ArrayExpandRows( $raReport['raUnknownCompanies'], "<li>[[company]]</li>")."</ul></div>";
        }

        /* Warn about unindexed species and cultivars, unless company is blank (action C-delete).
         */
        if( count($raReport['raUnknownSpecies']) ) {
            $s .= "<div class='alert alert-warning'><p>These species are not indexed. Please add to Species list or Species Synonyms and try again.</p>"
                 ."<ul style='background-color:#f8f8f8;max-height:200px;overflow-y:scroll'>"
                 .SEEDCore_ArrayExpandRows( $raReport['raUnknownSpecies'], "<li>[[osp]]</li>")."</ul></div>";
        }

        /* Warn about unindexed cultivars that are not indexed, unless company is blank (action C-delete).
         */
        if( count($raReport['raUnknownCultivars']) ) {
            $s .= "<div class='alert alert-warning'><p>These cultivars are not indexed. They will be matched by name as much as possible, but you should add them to the Cultivars list.</p>"
                 ."<ul style='background-color:#f8f8f8;max-height:200px;overflow-y:scroll'>"
                 .SEEDCore_ArrayExpandRows( $raReport['raUnknownCultivars'], "<li>[[osp]] : [[ocv]]</li>")."</ul></div>";
        }

        return( $s );
    }

    private function companies_drawUploadForm()
    {
        $oForm = new SEEDCoreFormSVA( $this->oSVA->CreateChild('-companies'), 'A' );
        $oForm->Update();

        list($sSelect,$sCompanyName) = $this->oSrcLib->DrawCompanySelector( $oForm, 'kSrc' );
        $sDownloadAction = "https://seeds.ca/app/q/index.php";     // use old Q until the new Q does this
        $sDownloadCtrl = $oForm->Hidden( 'qcmd', ['value'=>'srcCSCI'] )
                        //.$oForm->Hidden( 'qname', "" )
                        .$oForm->Hidden( 'qfmt', ['value'=>'xls'] )
                        .$sSelect;

        $raUDParms = [ 'label'=>"Seed Company listings",
                       'download_disable' => true,
                       'downloadaction'=>$_SERVER['PHP_SELF'],
                       'downloadctrl'=>$sDownloadCtrl,
                       'uploadaction'=>$_SERVER['PHP_SELF'],
                       'uploadctrl'=>
                                  "<input type='hidden' name='cmd' value='company_upload' />"
                                 ."<select name='eReplace' style='margin:0px 0px 10px 20px'>"
                                 ."<option value='".SLSourcesCVUpload::ReplaceVerbatimRows."'>Just copy the rows in the spreadsheet</option>"
                                 ."<option value='".SLSourcesCVUpload::ReplaceWholeCompanies."'>Replace entire companies mentioned in the spreadsheet</option>"
                                 ."<option value='".SLSourcesCVUpload::ReplaceWholeCSCI."'>Replace entire CSCI</option>"
                                 ."</select>",
                       'seedTableDef'=>$this->companyTableDef,
                     ];
        return( Console02UI::DownloadUpload( $this->oApp, $raUDParms ) );
    }

    private function companies_uploadfile( SLSourcesCVUpload $oUpload )
    /******************************************************************
        Get rows from the spreadsheet file, put them in a temporary table, validate and compute diff-ops
     */
    {
        $ok = false;
        $s = "";

        // Determine how to handle rows in sl_cv_sources that aren't mentioned in the spreadsheet
        $eReplace = SEEDInput_Smart( 'eReplace', [ SLSourcesCVUpload::ReplaceVerbatimRows,
                                                   SLSourcesCVUpload::ReplaceWholeCompanies,
                                                   SLSourcesCVUpload::ReplaceWholeCSCI ] );

        /* Load the uploaded spreadsheet into an array
         */
        $raSEEDTableLoadParms = ['sCharsetOutput'=>'cp1252', 'bBigFile'=>true];
        switch( SEEDInput_Str('upfile-format') ) {
            case 'xls':
            default:
                $raSEEDTableLoadParms['fmt'] = 'xlsx';
                $raSEEDTableLoadParms['charset-file'] = "utf-8";    // not actually used because xls is always utf-8
                break;
            case 'csv-utf8':
                $raSEEDTableLoadParms['fmt'] = 'csv';
                $raSEEDTableLoadParms['charset-file'] = "utf-8";
                break;
            case 'csv-win1252':
                $raSEEDTableLoadParms['fmt'] = 'csv';
                $raSEEDTableLoadParms['charset-file'] = "Windows-1252";
                break;
        }

        list($oSheets,$sErrMsg) = SEEDTableSheets_LoadFromUploadedFile( 'upfile',
                                        [ 'raSEEDTableSheetsFileParms'=> ['tabledef' => $this->companyTableDef],
                                          'raSEEDTableSheetsLoadParms' => $raSEEDTableLoadParms ] );
        if( !$oSheets ) {
            $this->oApp->oC->AddErrMsg( $sErrMsg );
            goto done;
        }

        $s .= "<p>File uploaded successfully.</p>";

        $raK = $oSheets->GetSheetList();
        if( count($raK) ) {
            /* Copy the spreadsheet rows into a temporary table.
             * Remove blank rows (company or species blank).
             * Rows are grouped in the table by a number kUpload, which will be propagated to a confirm command so it can copy those rows to sl_cv_sources.
             */
//$oUpload->Set( 'eReplace', $eReplace );
            $raRows = $oSheets->GetSheet( $raK[0] );
            list($ok,$sOk,$sWarn,$sErr) = $oUpload->LoadToTmpTable( $raRows );

//        $s .= $this->output( $ok, $sOk, $sWarn, $sErr );
            if( !$ok ) goto done;

//        if( !$oUpload->kUpload ) goto done;    // shouldn't happen, but bad if it does

        }

        $ok = true;

        done:
        return( $s );
    }


    private function companiesTest()
    {
        $s = "<h3>Seed Companies Test</h3>";

        // Test for sl_cv_sources rows that contain identical (fk_sl_sources,osp,ocv)
        $o = new SLSourcesDBTest( $this->oApp );
        $raRows = $o->TestForDuplicateSRCCV( 'sl_cv_sources' );
        if( count($raRows) ) {
            $s .= "<h4 style='margin-top:30px'>SrcCV rows have duplicate (src,sp,cv)</h4>"
                 ."<table style='margin-left:30px'>";
            foreach( $raRows as $ra ) {
                // Each row contains matches as A_*, B_*.  Draw a 2-row table showing each.

                // subst the _keys with links to see more details
                $l = "?c02ts_main=edit&pMode=srccv-edit-archive&k1={$ra['A__key']}&k2={$ra['B__key']}";

                $s .= SLSourcesLib::DrawSRCCVRow( $ra, 'A_', ['subst_key' => "<a href='$l'>{$ra['A__key']}</a>"] );
                $s .= SLSourcesLib::DrawSRCCVRow( $ra, 'B_', ['subst_key' => "<a href='$l'>{$ra['B__key']}</a>"] );
                $s .= "<tr><td colspan='5'><hr/></td></tr>";
            }
            $s .= "</table>";
        }

        return( $s );
    }

}