<?php

include_once( SEEDCORE."console/console02ui.php");
include_once( "batchopsTab_germtests.php" );
include_once( "batchopsTab_lotUpdates.php" );

class CollectionBatchOps
{
    private $oApp;
    private $oSVA;  // session vars for the UI tab (all batch ops modules)
    private $oOpPicker;

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;

        $raOps = ['Germination Tests'=>'germ',
                  'Batch Lot Update'=>'updatelots',
                  'Other Operation'=>'other'];
        $this->oOpPicker = new Console02UI_OperationPicker('batchop', $oSVA, $raOps);
    }

    function Init()
    {
        // Independent of any state of the worker because that only exists in ContentDraw
    }

    function ControlDraw()  { return( $this->oOpPicker->DrawDropdown() ); }

    function ContentDraw()
    {
        $s = "";

        switch( $this->oOpPicker->Value() ) {
            case 'germ':        $s = (new CollectionBatchOps_GermTest( $this->oApp ))->Draw();                   break;
            case 'updatelots':  $s = (new CollectionBatchOps_UpdateLots( $this->oApp, $this->oSVA ))->Draw();    break;
        }

        return( $s );
    }
}
