<?php

class CollectionBatchOps
{
    private $oApp;
    private $oSVA;  // session vars for the UI tab containing this tool

    private $raSelectOps = ['Germination Tests'=>'germ', 'Other Operation'=>'other'];
    private $currOp = "";

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;
        $this->currOp = $this->oSVA->SmartGPC( 'batchop', $this->raSelectOps );
    }

    function Init()
    {
    }

    function ControlDraw()
    {
        $oForm = new SEEDCoreForm( 'Plain' );
        $oForm->SetValue( 'batchop', $this->currOp );
        $s = "<form>".$oForm->Select( 'batchop', $this->raSelectOps, "", ['attrs'=>"onchange='submit()'"] )."</form>";

        return( $s );
    }

    function ContentDraw()
    {
        $s = "";

        $s = $this->currOp;

        return( $s );
    }
}
