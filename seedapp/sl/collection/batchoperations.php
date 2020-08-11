<?php

class CollectionBatchOps
{
    private $oApp;
    private $oSVA;  // session vars for the UI tab containing this tool

    private $raSelectOps = ['Germination Tests'=>'germ', 'Other Operation'=>'other'];
    private $currOp = "";

    private $oSLDB;

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;
        $this->currOp = $this->oSVA->SmartGPC( 'batchop', $this->raSelectOps );

        $this->oSLDB = new SLDBCollection($this->oApp);
    }

    function Init()
    {
    }

    function ControlDraw()
    {
        $oForm = new SEEDCoreForm( 'Plain' );
        $oForm->SetValue( 'batchop', $this->currOp );
        $s = "<form>".$oForm->Select( 'batchop', $this->raSelectOps, "", ['attrs'=>"onchange='submit()'"] )."</form>";

        return( $s );
    }

    function ContentDraw()
    {
        $s = "";

        switch( $this->currOp ) {
            case 'germ':
                $s = $this->germinationTests();
                break;
        }

        return( $s );
    }

    private function germinationTests()
    {
        $s = "";

        $oForm = new KeyFrameForm( $this->oSLDB->KFRel('G'), 'A', ['DSParms'=>['fn_DSPreStore'=>[$this,'PreStoreGerm']] ] );
        $oForm->Update();

        $oForm->SetKFR( $this->oSLDB->KFRel('G')->CreateRecord() );

        $oFE = new SEEDFormExpand( $oForm );
        $s .= "<form method='post'><input type='submit'/>"
             ."<table>"
             ."<tr><th>Lot</th><th>Cultivar</th><th>Date</th><th>Number Sown</th><th>Number Germ</th><th>Notes</th></tr>";
        for( $i = 0; $i < 10; ++$i ) {
            $s .= $oFE->ExpandForm( "<tr><td style='width:10%'>[[inv_number  | width:100%]]</td>
                                         <td style='width:15%'>[[cv_name     | width:100% | readonly]]</td>
                                         <td style='width:15%'>[[Date:dStart | width:100%]]</td>
                                         <td style='width:10%'>[[nSown       | width:100%]]</td>
                                         <td style='width:10%'>[[nGerm_count | width:100%]]</td>
                                         <td style='width:40%'>[[notes       | width:100%]]</td></tr>" );
            $oForm->IncRowNum();
        }
        $s .= "</table></form>";

        return( $s );
    }

    function PreStoreGerm( KeyFrame_DataStore $oDS )
    {
        if( !($iLot = $oDS->ValueInt('inv_number')) ) {
            $this->oApp->oC->AddUserMsg( "Not processing lot # {$oDS->Value('inv_number')}<br/>" );
            return( false );
        }
        if( !($kfrLot = $this->oSLDB->GetKFRCond('I', "inv_number='$iLot' AND fk_sl_collection='1'")) ) {
            $this->oApp->oC->AddErrMsg( "Lot # $iLot not found<br/>" );
            return( false );
        }

        $oDS->CastInt('nSown');
        $oDS->CastInt('nGerm');

        if( !$oDS->Value('nSown') ) {
            $this->oApp->oC->AddErrMsg( "Not processing lot # $iLot : 0 seeds sown<br/>" );
            return( false );
        }

        $oDS->SetValue( 'fk_sl_inventory', $kfrLot->Key() );

        if( !$oDS->Value('dStart') ) {
            $this->oApp->oC->AddUserMsg( "Defaulting Lot # $iLot test to today's date<br/>" );
            $oDS->SetValue( 'dStart', date('Y-m-d') );
        }
        if( !$oDS->Value('dEnd') ) {
            // blank date has to be represented as NULL
            $oDS->SetNull('dEnd');
        }

        $this->oApp->oC->AddUserMsg( "Saving Lot # $iLot, date {$oDS->Value('dStart')}, nSown {$oDS->ValueInt('nSown')}, nGerm {$oDS->ValueInt('nGerm_count')}, {$oDS->Value('notes')}<br/>");

        return( true );
    }
}
