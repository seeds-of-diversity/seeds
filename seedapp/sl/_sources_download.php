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
        $s = "<div class='container-fluid'><div class='row'>"
                ."<div class='col-md-2'>".$this->drawMenu()."</div>"
                ."<div class='col-md-10'>".$this->drawBody()."</div>"
            ."</div></div>";

        return( $s );
    }

    private function drawMenu()
    {
        $s = "";

        $raPills = array( 'companies'      => array( "Seed Companies"),
                          'companies-test' => array( "Seed Companies Test"),
                          'pgrc'           => array( "Canada: Plant Gene Resources (PGRC)" ),
                          'npgs'           => array( "USA: National Plant Germplasm System (NPGS)" ),
                          'sound'          => array( "Sound Tests" ),
                          'one-off-csci'   => array( "One-off CSCI loading" ),
        );

        $oSVA = new SEEDSessionVarAccessor( $this->oApp->sess, 'SLSourcesAppDownload' );    // use the tab SVA instead
        $oUIPills = new SEEDUIWidgets_Pills( $raPills, 'pMode', array( 'oSVA' => $oSVA, 'ns' => '' ) );
        $s = $oUIPills->DrawPillsVertical();

        return( $s );
    }

    private function drawBody()
    {
        $s = "";

        switch( SEEDInput_Str('pMode') ) {
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

        // Test for sl_cv_src rows that contain identical (fk_sl_sources,osp,ocv)
        $raRows = $this->oApp->kfdb->QueryRowsRA(
                "SELECT A._key as kA,B._key as kB,S.name_en as srcName,A.osp as osp,A.ocv as ocv "
               ."FROM sl_cv_sources A, sl_cv_sources B LEFT JOIN sl_sources S ON (S._key=B.fk_sl_sources) "
               ."WHERE A._key>B._key AND A.fk_sl_sources=B.fk_sl_sources AND A.ocv=B.ocv "
                     ."AND ((A.fk_sl_species=B.fk_sl_species AND A.fk_sl_species<>'0') OR A.osp=B.osp) "
               ."AND A.fk_sl_sources >=3 "
               ."ORDER BY srcName,osp,ocv");
        if( count($raRows) ) {
            $s .= "<h4 style='margin-top:30px'>SrcCV rows have duplicate (src,sp,cv)</h4>"
                 ."<table>";
            foreach( $raRows as $ra ) {
                $s .= SEEDCore_ArrayExpand( $ra, "<tr><td><em>[[srcName]]</em></td><td><strong>[[osp]] : [[ocv]]</strong></td><td>keys [[kA]] [[kB]]</td></tr>" );
            }
            $s .= "</table>";
        }

        return( $s );
    }

}