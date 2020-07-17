<?php


class CollectionTab_GerminationTests
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
        return( "<p>Here is where you put the code that shows germination tests for the selected lot and lets you record another one.</p>
                 <p>By the way, the current sl_inventory._key is {$this->kInventory} (not the same as the inv_number)</p>" );
    }

}
