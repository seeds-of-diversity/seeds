<?php

include_once( "eventsDB.php" );

class EventsLib
{
    private $oApp;
    public  $oDB;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oDB = new EventsDB( $oApp );
    }


}