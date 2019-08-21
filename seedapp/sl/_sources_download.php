<?php

class SLSourcesAppDownload
{
    private $oApp;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
    }

    function Draw()
    {
        $s = "";

        $sMenu = $this->drawMenu();
        $sBody = "BBB";

        $s = "<div class='container-fluid'><div class='row'><div class='col-md-2'>$sMenu</div><div class='col-md-10'>$sBody</div></div></div>";


        return( $s );
    }

    private function drawMenu()
    {
        $s = "";

        $raPills = array( 'companies' => array( "Seed Companies"),
                          'pgrc'      => array( "Canada: Plant Gene Resources (PGRC)" ),
                          'npgs'      => array( "USA: National Plant Germplasm System (NPGS)" ),
                          'sound'     => array( "Sound Tests" ),
                          'one-off-csci' => array( "One-off CSCI loading" ),
        );

        $oSVA = new SEEDSessionVarAccessor( $this->oApp->sess, 'SLSourcesAppDownload' );    // use the tab SVA instead
        $oUIPills = new SEEDUIWidgets_Pills( $raPills, 'pMode', array( 'oSVA' => $oSVA, 'ns' => '' ) );
        $s = $oUIPills->DrawPillsVertical();

        return( $s );
    }
}