<?php

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
        return( "<p>Here is where you put the code that makes a form that creates packet labels for the given inv_number.</p>
                 <p>By the way, the current sl_inventory._key is {$this->kInventory} (not the same as the inv_number)</p>" );
    }

}
