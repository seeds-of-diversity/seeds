<?php

include_once( SEEDCORE."console/console02ui.php");

class MbrDonationsTab_Admin
{
    private $oApp;
//    private $oSVA;  // session vars for the Console tab
    private $oOpPicker;

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
//        $this->oSVA = $oSVA;
        $raOps = ['-- Choose --'=>'',
                  'Integrity test'                 =>'integrity',
                  'Upload Canada Helps spreadsheet'=>'uploadCH'];
        $this->oOpPicker = new Console02UI_OperationPicker('currOp', $oSVA, $raOps);
    }

    function Init()
    {
    }

    function ControlDraw()  { return( $this->oOpPicker->DrawDropdown() ); }

    function ContentDraw()
    {
        $s = "";

        switch( $this->oOpPicker->Value() ) {
            case 'integrity':
                include_once( SEEDLIB."mbr/MbrIntegrity.php" );
                $s = "<h3>Donation Integrity Tests</h3>"
                    .(new MbrIntegrity($this->oApp))->ReportDonations();
                break;

            case 'uploadCH':
                $s = "upload here";
                break;
        }

        return( $s );
    }
}
