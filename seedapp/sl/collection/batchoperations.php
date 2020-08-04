<?php

class CollectionBatchOps
{
    private $oApp;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
    }

    function Init()
    {
        //parent::Init();
    }

    function ControlDraw()
    {
        return( "" );
    }

    function ContentDraw()
    {
        return( "Put Germination Test entry here" );
    }
}
