<?php

include_once( SEEDLIB."sl/sources/sl_sources_db.php" );

class SLSourcesAppDownload
{
    private $oApp;
    private $oSVA;  // where to store state variables for this app
    private $oUIPills;

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;

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
            case 'companies':
                break;
            case 'companies-test':
                $s = $this->companiesTest();
                break;
        }

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
                $l = $_SERVER['PHP_SELF']."?c02ts_main=archive&k1={$ra['A__key']}&k2={$ra['B__key']}";

                $s .= SLSourcesLib::DrawSRCCVRow( $ra, 'A_', ['subst_key' => "<a href='$l'>{$ra['A__key']}</a>"] );
                $s .= SLSourcesLib::DrawSRCCVRow( $ra, 'B_', ['subst_key' => "<a href='$l'>{$ra['B__key']}</a>"] );
                $s .= "<tr><td colspan='5'><hr/></td></tr>";
            }
            $s .= "</table>";
        }

        return( $s );
    }

}