<?php

class SLSourcesAppEdit
{
    private $oApp;
    private $oSVA;  // where to store state variables for this app
    private $oUIPills;
    private $oSrcLib;

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;

        $raPills = [ 'srccv-edit-company' => array( "SrcCV Edit Company"),
                     'srccv-edit-archive' => array( "SrcCV Edit Archive"),
        ];

        $this->oUIPills = new SEEDUIWidgets_Pills( $raPills, 'pMode', array( 'oSVA' => $this->oSVA, 'ns' => '' ) );
        $this->oSrcLib = new SLSourcesLib( $this->oApp );
    }

    function Draw()
    {
        $s = "<div class='container-fluid'><div class='row'>"
                ."<div class='col-md-2'>".$this->drawMenu()."</div>"
                ."<div class='col-md-10'>".$this->drawBody()."</div>"
            ."</div></div>";

        return( $s );
    }

    private function drawMenu()
    {
        return( $this->oUIPills->DrawPillsVertical() );
    }

    private function drawBody()
    {
        $s = "";

        switch( $this->oUIPills->GetCurrPill() ) {
            case 'srccv-edit-company':  $s = $this->srccvEditCompany(); break;
            case 'srccv-edit-archive':  $s = $this->srccvEditArchive(); break;
        }

        return( $s );
    }

    private function srccvEditCompany()
    {
        $s = "<h3>SrcCV Edit Company</h3>";

        return( $s );
    }

    private function srccvEditArchive()
    {
        $s = "<h3>SrcCV Edit Archive</h3>";

        if( !$this->oApp->sess->TestPermRA( ['W SLSrcArchive', 'A SLSources', 'A SL', '|'] ) ) {
            $s .= "<p>Editing the archive is not enabled</p>";
            goto done;
        }

        $k1 = SEEDInput_Int('k1') ?: $this->oSVA->VarGet('srccv-edit-archive-k1');
        $k2 = SEEDInput_Int('k2') ?: $this->oSVA->VarGet('srccv-edit-archive-k2');
        $this->oSVA->VarSet('srccv-edit-archive-k1', $k1);
        $this->oSVA->VarSet('srccv-edit-archive-k2', $k2);

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

            $raRows = $this->oSrcLib->oSrcDB->GetList( 'SRCCVAxSRC', "SRCCVA.sl_cv_sources_key='$k'", ['sSort'=>'SRC_name_en,year,osp,ocv'] );
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
