<?php

/* Seed collection manager - batch operations - print seed packet labels for arbitrary sets of lots
 *
 * Copyright 2025 Seeds of Diversity Canada
 */

include_once( SEEDCORE."SEEDCoreFormSession.php" );

class CollectionBatchOps_PacketLabels
{
    private $oApp;
    private $oSVA;  // where to keep state info for this tool
    private $oSLDB;

    private $oForm;

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;
        $this->oSLDB = new SLDBCollection($this->oApp);

        $this->oForm = new SEEDCoreFormSession($oApp->sess, 'LotLabels', 'A',  ['fields'=>['bFrench'=>['control'=>'checkbox']]]);
        $this->oForm->Update();

        if( SEEDInput_Str('cmd') == 'clear' ) {
            $this->oForm->SetValue( 'rLots', "" );
            $this->oForm->SetValue( 'nSkip', "" );
            $this->oForm->SetValue( 'bFrench', "" );
        }
    }

    function Draw()
    {
        $raLots = $this->getLotBlocks('HTML');

        if( SEEDInput_Str('cmd') == 'pdf' ){
            // Do PDF Creation
            $raLabels = [];
            foreach($raLots as $ra) {
                $raLabels[] = ['label_head'=>$ra['label_head'], 'label_desc'=>$ra['label_desc'], 'label_n'=>1];
            }

            SLPacketLabels::DrawPDFLabels(
                ['raLabels' => $raLabels,
                 'label_offset' => $this->oForm->ValueInt('nSkip'),
                 'bFrench'      => $this->oForm->ValueInt('bFrench'),
                ]);

            exit; // Actually drawPDF_Labels exits, but it's nice to have a reminder of that here
        }


        /* Draw the form
         */
        $sInputs =
            "<div><h4>Lot numbers</h4>
               {$this->oForm->TextArea('rLots', ['width'=>'100%'])}
             </div>
             <div style='margin:5px; text-align:left'>
               {$this->oForm->Text('nSkip', "", ['size'=>2])} Skip first <br/>
               {$this->oForm->Checkbox('bFrench', "")} French
             </div>
             <div style='text-align:left'><input type='submit' value='Update'/></div>";

        /* Draw preview table showing the basic label info
         */
        $sPreviewTable = "";
        $skip = $this->oForm->ValueInt('nSkip');
        for( $r = 0; $r < 10; ++$r ) {
            $sPreviewTable .= "<tr>";
            for( $c = 0; $c < 3; ++$c ) {
                $sPreviewTable .= "<td style='width:300px;height:60px;font-size:9pt;padding:0px 3px'>";
                if( $skip > 0 ) {
                    --$skip;
                } else if( ($raL = current($raLots)) !== false ) {
                    $sPreviewTable .= SEEDCore_ArrayExpand($raL, "<strong>[[label_head]]</strong><br/>[[label_desc]]");
                    next($raLots);
                }
                $sPreviewTable .= "</td>";
            }
            $sPreviewTable .= "</tr>";
        }
        $sPreviewTable = "<table border='1' style='width:100%'>$sPreviewTable</table>";

        /* Buttons
         */
        $clearButton =
            "<div style='margin-top:10px'>"
           ."<form method='post'>"
           ."<input type='hidden' name='cmd' value='clear'/>"
           ."<input type='submit' value='Clear'/>"
           ."</form>"
           ."</div>";
        $printButton =
            "<div style='margin-top:10px'>"
           ."<form method='post' target='_blank'>"
           ."<input type='hidden' name='cmd' value='pdf'/>"
           ."<input type='submit' value='Print Labels'/>"
           ."</form>"
           ."</div>";

        /* Put it together
         */
        $s = "<div class='container-fluid'>"
            ."<div class='row'>"
                ."<div class='col-sm-6'>$clearButton</div>"
                ."<div class='col-sm-6'>$printButton</div>"
            ."</div>"
            ."<div class='row'>"
                ."<form method='post'>"
                    ."<div class='col-sm-6'>$sInputs</div>"
                    ."<div class='col-sm-6'>$sPreviewTable</div>"
                ."</form>"
            ."</div>"
            ."</div>";

        return( $s );
    }

    private function getLotBlocks( $format = "HTML" )
    /************************************************
        Return array of formatted address blocks for the union of Orders and Contacts
     */
    {
        $raOut = [];
        foreach( preg_split('/\s+/', $this->oForm->Value('rLots')) as $v ) {
            $v = intval($v);
            if( ($kfrLot = $this->oSLDB->GetKFRCond('IxAxPxS', "fk_sl_collection=1 AND inv_number=$v")) ) {
                $ra = [];
                $ra['label_head'] = $kfrLot->Value('P_name')." ".strtolower($kfrLot->Value('S_name_en'))." (".$kfrLot->Value('inv_number').")";
                $ra['label_desc'] = $kfrLot->Value('P_packetLabel');
                $raOut[] = $ra;
            }
        }

        return( $raOut );
    }
}
