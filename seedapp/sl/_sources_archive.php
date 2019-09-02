<?php

class SLSourcesAppArchive
{
    private $oApp;
    private $oSVA;  // where to store state variables for this app
    private $oUIPills;

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;

        $raPills = array( 'srccv-archive'      => array( "SrcCV Archive"),
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
            case 'srccv-archive':
                $s = $this->srccvArchive();
                break;
        }

        return( $s );
    }

    private function srccvArchive()
    {
        $s = "<h3>SrcCV Archive</h3>";

        $k1 = SEEDInput_Int('k1') ?: $this->oSVA->VarGet('srccv-archive-k1');
        $k2 = SEEDInput_Int('k2') ?: $this->oSVA->VarGet('srccv-archive-k2');
        $this->oSVA->VarSet('srccv-archive-k1', $k1);
        $this->oSVA->VarSet('srccv-archive-k2', $k2);

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

            $ra = $this->oApp->kfdb->QueryRA(
                    "SELECT S.name_en as srcName,A.osp as osp,A.ocv as ocv,A.year as year,A.notes as notes,A.bOrganic as bOrganic " // A.bulk as bulk
                   ."FROM sl_cv_sources A LEFT JOIN sl_sources S ON (S._key=A.fk_sl_sources) "
                   ."WHERE A._key=$k" );
            $s .= "<h4 style='margin-top:30px'>SrcCV for key $k</h4>"
                 ."<table>"
                 .$this->drawSrcCVRow( $ra )
                 ."</table>";


            $raRows = $this->oApp->kfdb->QueryRowsRA(
                    "SELECT S.name_en as srcName,A.osp as osp,A.ocv as ocv,A.year as year,A.notes as notes,A.bOrganic as bOrganic " // A.bulk as bulk
                   ."FROM sl_cv_sources_archive A LEFT JOIN sl_sources S ON (S._key=A.fk_sl_sources) "
                   ."WHERE A.sl_cv_sources_key=$k "
                   ."ORDER BY srcName,year,osp,ocv");
            if( count($raRows) ) {
                $s .= "<h4 style='margin-top:30px'>SrcCV archive for key $k</h4>"
                     ."<table>";
                foreach( $raRows as $ra ) {
                    $s .= $this->drawSrcCVRow( $ra );
                }
                $s .= "</table>";
            }
            $s .= "<hr style='border:1px solid #aaa'/>";
        }

        return( $s );
    }

    private function drawSrcCVRow( $ra )
    {
        $s = SEEDCore_ArrayExpand( $ra,
                "<tr><td style='padding-right:5px'><em>[[srcName]]</em></td>"
                   ."<td style='padding-right:5px'>[[year]]</td>"
                   ."<td style='padding-right:5px'><strong>[[osp]] : [[ocv]]</strong></td>"
                   ."<td style='padding-right:5px'>".($ra['bOrganic']?"<span style='color:green'>organic</span>":"")."</td>"
                   ."<td style='padding-right:5px'>[[notes]]</td>"
               ."</tr>" );
        return( $s );
    }
}
