<?php

/* _sources_edit.php
 *
 * Copyright 2016-2019 Seeds of Diversity Canada
 *
 * Implement the user interface for directly editing sl_cv_sources and sl_cv_sources_archive (for seed companies)
 */

include_once( SEEDCORE."SEEDCoreFormSession.php" );

class SLSourcesAppEdit
{
    private $oApp;
    private $oSVA;  // where to store state variables for this app
    private $oUIPills;

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;

        $raPills = [ 'srccv-edit-company' => array( "SrcCV Edit Company"),
                     'srccv-edit-archive' => array( "SrcCV Edit Archive"),
        ];

        $this->oUIPills = new SEEDUIWidgets_Pills( $raPills, 'pMode', array( 'oSVA' => $this->oSVA, 'ns' => '' ) );
    }

    function Draw()
    {
        $sMenu = $this->oUIPills->DrawPillsVertical();

        $sBody = "";
        switch( $this->oUIPills->GetCurrPill() ) {
            case 'srccv-edit-company':
                $o = new SLSourcesAppEditCompany( $this->oApp, $this->oSVA );
                $sBody = $o->Main();
                break;
            case 'srccv-edit-archive':
                $o = new SLSourcesAppEditArchive( $this->oApp, $this->oSVA );
                $sBody = $o->Main();
                break;
        }

        $s = "<div class='container-fluid'><div class='row'>"
                ."<div class='col-md-2'>$sMenu</div>"
                ."<div class='col-md-10'>$sBody</div>"
            ."</div></div>";

        return( $s );
    }
}

class SLSourcesAppEditCompany
{
    private $oApp;
    private $oSVA;  // where to store state variables for this app
    private $oSrcLib;

    private $kCompany = 0;  // current company selected
    private $sCompanyName = "";

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;
        $this->oSrcLib = new SLSourcesLib( $this->oApp );
    }

    function Main()
    {
        $s = "<h3>SrcCV Edit Company</h3>";

        // Company Selector sets $this->kCompany and $this->sCompanyName
        $s .= $this->drawCompanySelector();
        if( !$this->kCompany ) goto done;

        // Download/Upload
        $s .= $this->drawDownloadUploadBox();


        done:
        return( $s );
    }

    private function drawCompanySelector()
    {
        $oForm = new SEEDCoreFormSession( $this->oApp->sess, 'SLSourcesEdit', 'A' );
        $oForm->Update();
        $this->kCompany = $oForm->Value('kCompany');
        $this->sCompanyName = "";

        $raSrc = $this->oSrcLib->oSrcDB->GetList( 'SRC', '_key>=3', ['sSortCol'=>'name_en'] );
        $raOpts = [ " -- Choose a Company -- " => 0 ];
        foreach( $raSrc as $ra ) {
            if( $this->kCompany && $this->kCompany == $ra['_key'] ) {
                $this->sCompanyName = $ra['name_en'];
            }
            $raOpts[$ra['name_en']] = $ra['_key'];
        }

        $s = "<div style='padding:1em'><form method='post' action=''>"
            .$oForm->Select( 'kCompany', $raOpts )
            ."<input type='submit' value='Choose'/>"
            ."</form></div>";

        return( $s );
    }

    private function drawDownloadUploadBox()
    {
        $s = "";

        $sUpload = "";

        // only allow upload of one company at a time
        if( $this->kCompany ) {
            $sUpload = "<p>Upload a spreadsheet of {$this->sCompanyName}</p>"
                      ."<form style='width:100%;text-align:left' action='{$_SERVER['PHP_SELF']}' method='post' enctype='multipart/form-data'>"
                      ."<div style='margin:0px auto;width:60%'>"
                      ."<input type='hidden' name='MAX_FILE_SIZE' value='10000000' />"
                      ."<input type='hidden' name='cmd' value='upload' />"
                      ."<input type='file' name='uploadfile'/><br/>"
                      ."<input type='submit' value='Upload'/>"
                      ."</div></form>";
        }

        $sXLS = "qcmd=srcCSCI"
               .($this->kCompany ? "&kSrc={$this->kCompany}" : "")
               ."&qname=".urlencode($this->kCompany ? $this->sCompanyName : "All Companies")
               ."&qfmt=xls";
        $s .= "<div style='display:inline-block;float:right;border:1px solid #999;border-radius:10px;"
                         ."margin-left:10px;padding:10px;background-color:#f0f0f0;text-align:center'>"
                ."<a href='".Q_URL."q.php?$sXLS' target='_blank' style='text-decoration:none'>"
                ."<img src='".W_CORE_URL."std/img/dr/xls.png' height='25'/>"
                .SEEDCore_NBSP("",5)."Download a spreadsheet of ".($this->kCompany ? $this->sCompanyName : "all companies")
                ."</a>"
                ."<hr style='width:85%;border:1px solid #aaa'/>"
                .$sUpload
            ."</div>";

        return( $s );
    }

}


class SLSourcesAppEditArchive
{
    private $oApp;
    private $oSVA;  // where to store state variables for this app
    private $oSrcLib;

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;
        $this->oSrcLib = new SLSourcesLib( $this->oApp );
    }

    function Main()
    {
        $s = "<h3>SrcCV Edit Archive</h3>";

        if( !$this->oApp->sess->TestPermRA( ['W SLSrcArchive', 'A SLSources', 'A SL', '|'] ) ) {
            $s .= "<p>Editing the archive is not enabled</p>";
            goto done;
        }

        $k1 = $this->oSVA->SmartGPC('srccv-edit-archive-k1');
        $k2 = $this->oSVA->SmartGPC('srccv-edit-archive-k2');

        $oForm = new SEEDCoreForm( 'Plain' );

        $s .= "<div><form>"
             .$oForm->Text( 'k1', 'Key 1 ', ['value'=>$k1] )
             .SEEDCore_NBSP( '     ')
             .$oForm->Text( 'k2', 'Key 2 ', ['value'=>$k2] )
             .SEEDCore_NBSP( '     ')
             ."<input type='submit'/>"
             ."</form></div>";

        foreach( [$k1,$k2] as $k ) {
            if( !$k ) continue;

            // using SRCCVxSRC instead of SRCCV_SRC because existence of SRC should be enforced in SRCTMP and integrity tests should validate SRCCVxSRC
            $ra = $this->oSrcLib->oSrcDB->GetRecordVals( 'SRCCVxSRC', $k );
            $s .= "<h4 style='margin-top:30px'>SrcCV for key $k</h4>"
                 ."<table>"
                 .SLSourcesLib::DrawSRCCVRow( $ra, '', ['no_key'=>true] )
                 ."</table>";

            $raRows = $this->oSrcLib->oSrcDB->GetList( 'SRCCVAxSRC', "SRCCVA.sl_cv_sources_key='$k'", ['sSortCol'=>'SRC_name_en,year,osp,ocv'] );
            if( count($raRows) ) {
                $s .= "<h4 style='margin-top:30px'>SrcCVArchive for key $k</h4>"
                     ."<table>";
                foreach( $raRows as $ra ) {
                    $s .= SLSourcesLib::DrawSRCCVRow( $ra, '', ['no_key'=>true] );
                }
                $s .= "</table>";
            }
            $s .= "<hr style='border:1px solid #aaa'/>";
        }

        done:
        return( $s );
    }
}
