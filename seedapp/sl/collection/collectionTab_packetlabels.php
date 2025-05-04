<?php
include_once( SEEDCORE."SEEDCoreForm.php" );
include_once( SEEDLIB."sl/sldb.php" );
include_once( SEEDLIB."sl/sl_packetLabels.php" );

class CollectionTab_PacketLabels
{
    private $oApp;
    private $kInventory;

    function __construct( SEEDAppConsole $oApp, $kInventory )
    {
        $this->oApp = $oApp;
        $this->kInventory = $kInventory;
    }

    function Init()
    {

    }

    function ControlDraw()
    {
        return( "" );
    }

    function ContentDraw()
    {
        $oForm = new SEEDCoreForm( 'A' );
        $oForm->Update();

        if( SEEDInput_Str('cmd') == 'Make Labels' ){
            // Do PDF Creation
            SLPacketLabels::DrawPDFLabels(
                ['raLabels'     => [['label_head'   => $oForm->Value('label_head'),
                                     'label_desc'   => $oForm->Value('label_desc'),
                                     'label_n'      => $oForm->ValueInt('label_n')]],
                 'label_offset' => $oForm->ValueInt('label_offset'),
                 'bFrench'      => $oForm->ValueInt('bFrench'),
                 ]);

            exit; // Actually drawPDF_Labels exits, but it's nice to have a reminder of that here
        }

        $s = "";

        $oSLDB = new SLDBCollection( $this->oApp );

        if( !$this->kInventory || !($kfrLot = $oSLDB->GetKFR( 'IxAxPxS', $this->kInventory )) ) {
            goto done;
        }

        // the label header is: Cultivar Species (Lot_number)
        //                      Description
        $oForm->SetValue( 'label_head', $kfrLot->Value('P_name')." ".strtolower($kfrLot->Value('S_name_en'))." (".$kfrLot->Value('inv_number').")" );
        $oForm->SetValue( 'label_desc', $kfrLot->Value('P_packetLabel') );
        $oForm->SetValue( 'label_n',    30 );

        if( !$oForm->Value('nLabels') )  $oForm->SetValue( 'nLabels', 30 );

        $oFE = new SEEDFormExpand( $oForm );
        $s = "<h4>Format seed packet labels for Avery #5160/8160</h4>"
            ."<form method='post' target='_blank'>"
                ."<div style='float:left;margin:10px;'>"
                    .$oFE->ExpandForm( "[[label_head | width:220px ]]<br/>[[TextArea:label_desc | width:220px]]" )
                ."</div>"
                ."<div style='float:left;margin:10px'>"
                    //."<p style='font-size:small'>If this wraps more than the first line, put a period + line break at the start of the description to make room.</p>"
                    //."<p style='font-size:small'>You can change any information here before printing labels.</p>"
                    .$oFE->ExpandForm( "|||TABLE()"
                                      ."||| # labels   &nbsp; || [[label_n      | width:50px]]"
                                      ."||| Skip first &nbsp; || [[label_offset | width:50px]]"
                                      ."||| French &nbsp; || [[checkbox:bFrench ]]" )
                ."</div>"
                ."<div style='clear:both;margin:10px'><input type='submit' name='cmd' value='Make Labels'/></div>"
            ."</form>";

        done:
        return $s;
    }
}
