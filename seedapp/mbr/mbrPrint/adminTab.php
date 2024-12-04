<?php


class MbrDonationsTab_Admin
{
    private $oApp;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
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
        include_once( SEEDLIB."mbr/MbrIntegrity.php" );

        $s = "<h3>Donation Integrity Tests</h3>"
            .(new MbrIntegrity($this->oApp))->ReportDonations();

        return( $s );
    }
}
