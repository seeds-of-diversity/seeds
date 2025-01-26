<?php
include_once( SEEDLIB."fpdf/PDF_Label.php" );
include_once( SEEDCORE."SEEDCoreForm.php" );
include_once( SEEDLIB."sl/sldb.php" );

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
            $this->drawPDF_Labels($oForm);
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

    private function drawPDF_Labels(SEEDCoreForm $oForm)
    {
        $sHead = $oForm->Value('label_head');
        $sDesc = $oForm->Value('label_desc');
        $nLabels = $oForm->ValueInt('label_n');
        $iOffset = $oForm->ValueInt('label_offset');
        $bFrench = $oForm->ValueInt('bFrench');

        $pdf = new MyPDF_Label( '5160' );
        $pdf->Set_Font_Size(8);  // default is 8pt which is pretty small; though this might be too big for long addresses
        $pdf->AddPage();

        // Skip the offset number
        for( $i = 0; $i < $iOffset; ++$i ) {
             $pdf->AddLabel1();
        }

        // Print the labels
        for( $i = 0; $i < $nLabels; ++$i ) {
            $xMarginText = 18;   // x position of cvname and description (beside logo)
            $yMarginText = 2;    // y position of cvname and description (beside logo)
            $yMarginWWW = 18;    // y position of www text (below logo)
            $fontsizeText = 8;
            $fontsizeWWW = 7;

            // move to the next label
            $pdf->AddLabel1();

            // set position to the top-left and draw the logo
            $pdf->AddLabel2( 0, 0 );
            $pdf->Image( "https://seeds.ca/i/img/logo/logoA_v-".($bFrench ? 'fr-300x.png':'en-300.jpg'), $pdf->GetX(), $pdf->GetY(), 17.14, 17.14 );  // image is 300x300

            // set position to the bottom-left and write the web site in bold
            $pdf->AddLabel2( 0, $yMarginWWW );
            $pdf->SetFont( '', 'B', $fontsizeWWW );
            $pdf->AddLabel3( $bFrench ? "semences.ca" : "www.seeds.ca", 0 );

            // set position to the top with left padding for the logo, and write the cvname in bold
            $pdf->AddLabel2( $xMarginText, $yMarginText );
            $pdf->SetFont( '', 'B', $fontsizeText );
            $pdf->AddLabel3( SEEDCore_utf8_decode($sHead), $xMarginText );  // the pdf is iso8859

            // set position to the top-left with additional left padding for the logo and one line of top padding for the cvname,
            // and write the description
            $pdf->SetFont( '', '', $fontsizeText );
            $pdf->AddLabel2a( $xMarginText );
            $pdf->AddLabel3( SEEDCore_utf8_decode($sDesc), $xMarginText );  // the pdf is iso8859
            //$pdf->AddLabel3( "\n".$sDesc, $xMarginText );      old method reset Y to top and inserted \n here
        }

        $pdf->Output();
        exit; // FPDF doesn't exit
    }

}

class MyPDF_Label extends PDF_Label
{
    /* Encoding dilemma:
     *      FPDF fonts only support ISO-8859-1
     *      The form that drives this labelling is drawn using utf8_encode() so cultivar name and description is utf8.
     *      That means you have use utf8_decode() when you draw those below.
     */

    function __construct( $format )
    {
        parent::__construct( $format );
    }

    function AddLabel1()
    {
        // This is the first part of Add_Label, which moves the label counter forward.
        $this->_COUNTX++;
        if ($this->_COUNTX == $this->_X_Number) {
            // Row full, we start a new one
            $this->_COUNTX=0;
            $this->_COUNTY++;
            if ($this->_COUNTY == $this->_Y_Number) {
                // End of page reached, we start a new one
                $this->_COUNTY=0;
                $this->AddPage();
            }
        }
    }

    function AddLabel2( $xPadding = 0, $yPadding = 0 )
    {
        // This is the second part of Add_Label, which positions the fpdf x/y at the top-left of the current label
        $_PosX = $this->_Margin_Left + $this->_COUNTX*($this->_Width+$this->_X_Space) + $this->_Padding + $xPadding;
        $_PosY = $this->_Margin_Top + $this->_COUNTY*($this->_Height+$this->_Y_Space) + $this->_Padding + $yPadding;
        $this->SetXY($_PosX, $_PosY);
    }

    function AddLabel2a( $xPadding = 0 )
    {
        // Just set the X position, using the current Y position after AddLabel3
        $_PosX = $this->_Margin_Left + $this->_COUNTX*($this->_Width+$this->_X_Space) + $this->_Padding + $xPadding;
        $this->SetX($_PosX);
    }

    function AddLabel3( $text, $xMargin = 0 )
    {
        // This is the third part of Add_Label, which writes text to the current x/y.
        // xMargin is used to make the available text width narrower since _Width is the whole width of the label.
        $this->MultiCell($this->_Width - $this->_Padding - $xMargin, $this->_Line_Height, $text, 0, 'L');
    }
}