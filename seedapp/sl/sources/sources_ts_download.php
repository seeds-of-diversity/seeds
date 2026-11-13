<?php

include_once( SEEDCORE."SEEDCoreFormSession.php" );
include_once( SEEDCORE."SEEDTableSheets.php" );
include_once( SEEDCORE."console/console02ui.php" );
include_once( SEEDLIB."sl/sources/sl_sources_db.php" );
include_once( SEEDLIB."sl/sources/sl_sources_cv_upload.php" );
include_once( "sources_ts_download_csciUpload.php" );
include_once( "sources_download_xlsx_old.php" );

class SLSourcesAppDownload
{
    private $oApp;
    private $oSVA;  // where to store state variables for this app
    private $oUIPills;

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;

        $raPills = ['csci-sync-gsheet'   => ["Seed Finder Update - Sync from Google Sheet"],
                    'csci-sync-xlsx'     => ["Seed Finder Update - Upload XLSX Spreadsheet"],
                    'csci-sync-xlsx-old' => ["Old Seed Finder Update - Upload XLSX Spreadsheet"],
                    'csci-xlsx-download' => ["Download Seed Finder to XLSX Spreadsheet"],
                    'csci-archive'       => ["Archive Seed Finder"],
                    'companies-test' => array( "Seed Companies Test"),
                    'pgrc'           => array( "Canada: Plant Gene Resources (PGRC)" ),
                    'npgs'           => array( "USA: National Plant Germplasm System (NPGS)" ),
                    'sound'          => array( "Sound Tests" ),
                    'one-off-csci'   => array( "One-off CSCI loading" ),
        ];

        $this->oUIPills = new SEEDUIWidgets_Pills( $raPills, 'pMode', array( 'oSVA' => $this->oSVA, 'ns' => '' ) );
    }

    function Draw()
    {
        $sMenu = $this->oUIPills->DrawPillsVertical();
        $sBody = "";
        switch( $this->oUIPills->GetCurrPill() ) {
            case 'csci-sync-gsheet':    $sBody = (new SLSourcesAppDownload_CSCIUpload($this->oApp,$this->oSVA))->DoUI();                        break;
            case 'csci-sync-xlsx':      $sBody = (new SLSourcesAppDownload_CSCIUpload($this->oApp,$this->oSVA,['bUIModeXLSX'=>true]))->DoUI();  break;
            case 'csci-sync-xlsx-old':  $sBody = (new SLSourcesAppDownload_XSLX_Old($this->oApp,$this->oSVA))->DoUI();              break;
            case 'csci-xlsx-download':  $sBody = "";                                                          break;
            case 'csci-archive':        $sBody = (new SLSourcesAppDownload_CSCIArchive($this->oApp))->DoUI(); break;
            case 'companies-test':      $sBody = $this->companiesTest();    break;
        }

        $s = "<div class='container-fluid'><div class='row'>"
                ."<div class='col-md-2'>$sMenu</div>"
                ."<div class='col-md-10'>$sBody</div>"
            ."</div></div>";

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
