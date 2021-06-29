<?php

include_once( "batchopsTab_germtests.php" );
include_once( "batchopsTab_lotUpdates.php" );

class CollectionBatchOps
{
    private $oApp;
    private $oSVA;  // session vars for the UI tab (all batch ops modules)

    private $raSelectOps = ['Germination Tests'=>'germ',
                            'Batch Lot Update'=>'updatelots',
                            'Other Operation'=>'other'];
    private $currOp = "";

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;
        $this->currOp = $this->oSVA->SmartGPC( 'batchop', $this->raSelectOps );
    }

    function Init()
    {
        // Independent of any state of the worker because that only exists in ContentDraw
    }

    function ControlDraw()
    {
        // Independent of any state of the worker because that only exists in ContentDraw
        $oForm = new SEEDCoreForm( 'Plain' );
        $oForm->SetValue( 'batchop', $this->currOp );
        $s = "<form>".$oForm->Select( 'batchop', $this->raSelectOps, "", ['attrs'=>"onchange='submit()'"] )."</form>";

        return( $s );
    }

    function ContentDraw()
    {
        $s = "";

        switch( $this->currOp ) {
            case 'germ':        $s = (new CollectionBatchOps_GermTest( $this->oApp ))->Draw();                   break;
            case 'updatelots':  $s = (new CollectionBatchOps_UpdateLots( $this->oApp, $this->oSVA ))->Draw();    break;
        }

        return( $s );
    }
}
