<?php

include_once( SEEDCORE."SEEDCoreFormSession.php" );
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

        $yCurr = date("Y");
        $oUpload = new SLSourcesCVUpload( $this->oApp, SLSourcesCVUpload::ReplaceWholeCSCI, 0 );
        $oArchive = new SLSourcesCVArchive( $this->oApp );

//$this->oApp->kfdb->SetDebug(2);
        switch( SEEDInput_Str('cmd') ) {
            case 'cmpupload_cleartmp':
                $oUpload->ClearTmpTable();
                break;
            case 'cmpupload_rebuildtmp':
                $oUpload->ValidateTmpTable();
                break;
            case 'company_upload':
                $s .= $this->companies_uploadfile( $oUpload );
                break;
            case 'cmpupload_archivecurr':
                // only admin can do this, but it doesn't depend on upload state
                if( in_array($this->oApp->sess->GetUID(), [1,1499]) ) {
                    list($ok,$sOk,$sErr) = $oArchive->CopySrcCv2Archive();
                    $s .= $sOk;
                    $this->oApp->oC->AddErrMsg($sErr);
                }
                break;
            case 'cmpupload_commit':
                list($bOk,$sOk,$sErr) = $oUpload->Commit();
                $s .= $sOk;
                $this->oApp->oC->AddErrMsg($sErr);
                break;
            case 'cmpupload_fixmatches':
                $s .= $oUpload->FixMatchingRowKeys();
                break;
        }


        $raReport = $oUpload->CalculateUploadReport();

        if( $raReport['nRows'] ) {
            $s .= "<p><a href='?cmd=cmpupload_rebuildtmp'>Validate/Build/Rebuild Upload Table</a></p>";
            $s .= "<p><a href='?cmd=cmpupload_cleartmp'>Clear Upload Table</a></p>";

            if( ($y = $oArchive->IsSrcCv2ArchiveSimple()) && in_array($this->oApp->sess->GetUID(), [1,1499]) ) {
                $s .= "<p><a href='?cmd=cmpupload_archivecurr'>Copy Current SrcCv to Archive (SrcCv records are all "
                     ."of year=$y; all records of that year will be deleted from Archive first)</a></p>";
            }

            if( $raReport['nRowsSameDiffKeys'] ) {
                $s .= "<p><a style='color:red' href='?cmd=cmpupload_fixmatches'>"
                     ."Copy keys from SrcCv to Upload table where data matches but keys are different</a><br/>"
                     ."There are {$raReport['nRowsSameDiffKeys']} rows in upload table with the same (src,sp,cv) as SrcCv but different keys.<br/>"
                     ."This happens when new (k==0) rows are committed, which is fine, just fix it by clicking this link.<br/>"
                     ."N.B. You have to rebuild the indexes after clicking this link.</p>";
            }

            if( $oUpload->IsCommitAllowed($raReport) ) {
                $s .= "<p><a href='?cmd=cmpupload_commit'>Commit the Uploaded CSCI to the Web Site</a></p>";
            }

            $s .= $oUpload->DrawUploadReport( $raReport );
        } else {
            $s .= $this->companies_drawUploadForm();
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
