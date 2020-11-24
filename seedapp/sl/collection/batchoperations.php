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
             ."<tr><th>Lot</th><th>Cultivar</th><th>Start Date</th><th>End Date</th><th>Number Sown</th><th>Number Germ</th><th>Notes</th></tr>";
        for( $i = 0; $i < 5; ++$i ) {
            $s .= $oFE->ExpandForm( "<tr>[[hiddenkey:]]
                                         <td style='width:10%'>[[XLotNum     | width:100% | class='gtLotNum']]</td>
                                         <td style='width:15%'>[[XCvName     | width:100% | class='gtCVName' disabled]]</td>
                                         <td style='width:15%'>[[Date:dStart | width:100%]]</td>
                                         <td style='width:15%'>[[Date:dEnd   | width:100%]]</td>
                                         <td style='width:10%'>[[nSown       | width:100%]]</td>
                                         <td style='width:10%'>[[nGerm       | width:100%]]</td>
                                         <td style='width:40%'>[[notes       | width:100%]]</td></tr>" );
            $oForm->IncRowNum();
        }

        //$s .= "<div id='collection-batch-germ-container'></div>";

        $s .= "<tr><td colspan='7'>&nbsp;</td></tr>"
             ."<tr><td colspan='7'>&nbsp;</td></tr>";

        if( ($raKfrG = $this->oSLDB->KFRel('GxIxAxPxS')->GetRecordSet( 'dEnd is null', ['sSortCol'=>'dStart','bSortDown'=>true] )) ) {
            foreach( $raKfrG as $kfr ) {
                $oForm->SetKFR($kfr);

                $oForm->SetValue( 'XLotNum', 'I_inv_number_already_set' );
                $oForm->SetValue( 'XCvName', $oForm->Value('S_name_en').' '.$oForm->Value('P_name') );
            $s .= $oFE->ExpandForm( "<tr>[[hiddenkey:]]
                                         <td style='width:10%'>[[I_inv_number| width:100% | disabled]]</td>
                                         <td style='width:15%'>[[XCvName     | width:100% | disabled]]</td>
                                         <td style='width:15%'>[[Date:dStart | width:100%]]</td>
                                         <td style='width:15%'>[[Date:dEnd   | width:100%]]</td>
                                         <td style='width:10%'>[[nSown       | width:100%]]</td>
                                         <td style='width:10%'>[[nGerm       | width:100%]]</td>
                                         <td style='width:40%'>[[notes       | width:100%]]</td></tr>" );
            $oForm->IncRowNum();

            }
        }

        $s .= "</table></form>";

        $s .= "<p style='margin-top:30px'>Required: Lot #, Number seeds sown.</p><p>Start date defaults to today. Records shown until End Date set.</p>";

        return( $s );
    }

    function PreStoreGerm( KeyFrame_DataStore $oDS )
    {
        if( $oDS->Key() ) {
            // The I_inv_num is there because the server filled it in on an existing germ record
            $iLot = ($ra = $this->oSLDB->GetRecordVals( 'GxIxAxPxS', $oDS->Key() )) ? $ra['I_inv_number'] : 0;      // really only need GxI

        } else {
            // The I_inv_num/XLotNum was typed into a new germ record.
            // Validate that it's an integer, then look up the Lot and set fk_sl_inventory

            if( !($iLot = $oDS->ValueInt('XLotNum')) ) {
                // somebody typed a non-integer?
                $this->oApp->oC->AddUserMsg( "Not processing lot # {$oDS->Value('XLotNum')}<br/>" );
                return( false );
            }
            if( !($kfrLot = $this->oSLDB->GetKFRCond('I', "inv_number='$iLot' AND fk_sl_collection='1'")) ) {
                $this->oApp->oC->AddErrMsg( "Lot # $iLot not found<br/>" );
                return( false );
            }

            $oDS->SetValue( 'fk_sl_inventory', $kfrLot->Key() );
        }

        $oDS->CastInt('nSown');
        $oDS->CastInt('nGerm');

        if( !$oDS->Value('nSown') ) {
            $this->oApp->oC->AddErrMsg( "Not recording record for lot # $iLot : 0 seeds sown<br/>" );
            return( false );
        }

        if( !$oDS->Value('dStart') ) {
            $this->oApp->oC->AddUserMsg( "Defaulting Lot # $iLot test to today's date<br/>" );
            $oDS->SetValue( 'dStart', date('Y-m-d') );
        }
        if( !$oDS->Value('dEnd') ) {
            // blank date has to be represented as NULL
            $oDS->SetNull('dEnd');
        }

        //$this->oApp->oC->AddUserMsg( "Saving Lot # $iLot, date {$oDS->Value('dStart')}, nSown {$oDS->ValueInt('nSown')}, nGerm {$oDS->ValueInt('nGerm_count')}, {$oDS->Value('notes')}<br/>");

        return( true );
    }
}
