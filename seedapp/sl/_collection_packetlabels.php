<?php
include_once( SEEDLIB."fpdf/PDF_Label.php" );
include_once( SEEDCORE."SEEDCoreForm.php" );
include_once( SEEDLIB."sl/sldb.php" );

//TODO REMOVE
define( "SITEROOT", "https://seeds.ca/" );

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
            $this->drawPDF_Lables($oForm);
            exit; // Actually drawPDF_Lables exits, but it's nice to have a reminder of that here
        }
        
        $oSLDB = new SLDBCollection( $this->oApp );
        
        if( (!$oForm->Value('cvName') || !$oForm->Value('desc')) && $this->kInventory ) {
            if( ($kfrLot = $oSLDB->GetKFRCond( 'IxAxPxS', "fk_sl_collection='1' AND I._key='".$this->kInventory."'" )) ) {
                if( !$oForm->Value('cvName') ) $oForm->SetValue( 'cvName', $kfrLot->Value('P_name').' '.strtolower($kfrLot->Value('S_name_en')) );
                
                if( !$oForm->Value('desc') ) $oForm->SetValue( 'desc', $kfrLot->Value('P_packetLabel') );
            }
        }
        $oForm->SetValue('kLot', $kfrLot->Value('inv_number'));
        
        if( !$oForm->Value('nLabels') )  $oForm->SetValue( 'nLabels', 30 );
        
        $oFE = new SEEDFormExpand( $oForm );
        $s = "<h3>Seed Labels</h3>"
            ."<form method='post' target='_blank'>"
                ."<div class='container'>"
                    .$oFE->ExpandForm(
                        "|||BOOTSTRAP_TABLE(class='col-md-1' | class='col-md-3' | class='col-md-1' | class='col-md-3')"
                        ."||| Lot # || [[kLot | | readonly]] || Cultivar name || [[cvName]]"
                        ."||| Description || [[TextArea:desc]] || || "
                        ."||| # labels || [[nLabels]] || Skip first || [[offset]]"
                        ."||| <input type='submit' name='cmd' value='Update'/> || <input type='submit' name='cmd' value='Make Labels'/>"
                        
                        )
                        ."</form></div>";
        return $s;
    }

    private function drawPDF_Lables(SEEDCoreForm $oForm){
        $pdf = new MyPDF_Label( '5160' );
        $pdf->Set_Font_Size(8);  // default is 8pt which is pretty small; though this might be too big for long addresses
        $pdf->AddPage();
        
        // Skip the offset number
        if( ($n = intval($oForm->Value('offset'))) ) {
            for( $i = 0; $i < $n; ++$i ) {
                $pdf->AddLabel1();
            }
        }
        
        // Print the labels
        for( $i = 0; $i < intval($oForm->Value('nLabels')); ++$i ) {
            $cvName = $oForm->Value('cvName').(($kLot = $oForm->Value('kLot')) ? " ($kLot)" : "");
            $desc = $oForm->Value('desc');
            $xMarginText = 18;   // x position of cvname and description (beside logo)
            $yMarginText = 2;    // y position of cvname and description (beside logo)
            $yMarginWWW = 18;    // y position of www text (below logo)
            $fontsizeText = 8;
            $fontsizeWWW = 7;
            
            // move to the next label
            $pdf->AddLabel1();
            
            // set position to the top-left and draw the logo
            $pdf->AddLabel2( 0, 0 );
            $pdf->Image( SITEROOT."i/img/logo/logoA_v-en-300.jpg", $pdf->GetX(), $pdf->GetY(), 17.14, 17.14 );  // image is 300x300
            
            // set position to the bottom-left and write the web site in bold
            $pdf->AddLabel2( 0, $yMarginWWW );
            $pdf->SetFont( '', 'B', $fontsizeWWW );
            $pdf->AddLabel3( "www.seeds.ca", 0 );
            
            // set position to the top with left padding for the logo, and write the cvname in bold
            $pdf->AddLabel2( $xMarginText, $yMarginText );
            $pdf->SetFont( '', 'B', $fontsizeText );
            $pdf->AddLabel3( $cvName, $xMarginText );
            
            // set position to the top-left with additional left padding for the logo and one line of top padding for the cvname,
            // and write the description
            $pdf->SetFont( '', '', $fontsizeText );
            $pdf->AddLabel2( $xMarginText, $yMarginText );
            $pdf->AddLabel3( "\n".$desc, $xMarginText );
        }
        
        $pdf->Output();
        exit; // FPDF doesn't exit
    }
    
}

class MyPDF_Label extends PDF_Label
{
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
    
    function AddLabel3( $text, $xMargin = 0 )
    {
        // This is the third part of Add_Label, which writes text to the current x/y.
        // xMargin is used to make the available text width narrower since _Width is the whole width of the label.
        $this->MultiCell($this->_Width - $this->_Padding - $xMargin, $this->_Line_Height, $text, 0, 'L');
    }
}