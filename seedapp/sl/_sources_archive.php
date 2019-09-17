<?php

class SLSourcesAppArchive
{
    private $oApp;
    private $oSVA;  // where to store state variables for this app
    private $oUIPills;
    private $oSrcLib;

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;

        $raPills = array( 'srccv-archive-edit'      => array( "SrcCV Archive Edit"),
        );

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
            case 'srccv-archive-edit':
                $s = $this->srccvArchiveEdit();
                break;
        }

        return( $s );
    }

    private function srccvArchiveEdit()
    {
        $s = "<h3>SrcCV Archive Edit</h3>";

        $k1 = SEEDInput_Int('k1') ?: $this->oSVA->VarGet('srccv-archive-edit-k1');
        $k2 = SEEDInput_Int('k2') ?: $this->oSVA->VarGet('srccv-archive-edit-k2');
        $this->oSVA->VarSet('srccv-archive-edit-k1', $k1);
        $this->oSVA->VarSet('srccv-archive-edit-k2', $k2);

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

        return( $s );
    }
}
