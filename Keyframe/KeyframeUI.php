<?php

/* KeyframeUI
 *
 * UI classes for Keyframe
 *
 * Copyright (c) 2009-2020 Seeds of Diversity Canada
 */

include_once( SEEDCORE."SEEDUI.php" );
include_once( "KeyframeForm.php" );

class KeyframeUIComponent extends SEEDUIComponent
{
    private $kfrel;
    private $raViewParms = array();

    function __construct( SEEDUI $o, Keyframe_Relation $kfrel, $cid = "A", $raCompConfig = array() )
    {
         $this->kfrel = $kfrel;     // set this before the parent::construct because that uses the factory_SEEDForm
         parent::__construct( $o, $cid, $raCompConfig );
    }

    protected function factory_SEEDForm( $cid, $raSFParms )
    {
        // Any widget can find this KeyframeForm at $this->oComp->oForm
        return( new KeyframeForm( $this->kfrel, $cid, $raSFParms ) );
    }

    function Start()
    {
        parent::Start();

        /* Now the Component is all set up with its uiparms and widgets, but the oForm is not initialized to
         * the current key (unless it got loaded during Update).
         */

        if( $this->Get_kCurr() && ($kfr = $this->kfrel->GetRecordFromDBKey($this->Get_kCurr())) ) {
            $this->oForm->SetKFR( $kfr );
        }
    }

    function FetchViewSlice( $iViewSliceOffset, $nViewSliceSize )
    /************************************************************
        If a widget needs a slice of a view it can call here to get it.

        Returns the rows in the view slice and the total number of rows in the view (used for navigation by the caller)
     */
    {
        list($oView,$raWindowRows) = $this->GetViewWindow($iViewSliceOffset,$nViewSliceSize);

        return( [$raWindowRows, $iViewSliceOffset, $oView->GetNumRows()] );
    }

    function GetViewWindow( $iWindowOffset = 0, $nWindowSize = 0 )
    {
        // this is almost always called with default arguments
        $iWindowOffset = $iWindowOffset ?: $this->Get_iWindowOffset();
        $nWindowSize   = $nWindowSize   ?: $this->Get_nWindowSize();

        $raViewParms = ['sSortCol'  => $this->GetUIParm('sSortCol'),
                        'bSortDown' => $this->GetUIParm('bSortDown'),
                        'sGroupCol' => $this->GetUIParm('sGroupCol'),
                        'iStatus'   => $this->GetUIParm('iStatus')];

        $oView = new KeyframeRelationView( $this->kfrel, $this->sSqlCond, $raViewParms );
        $raWindowRows = $oView->GetDataWindowRA( $iWindowOffset, $nWindowSize );

        return( [$oView, $raWindowRows] );
    }
}

class KeyframeUIWidget_List extends SEEDUIWidget_List
{
    function __construct( KeyframeUIComponent $oComp, $raConfig = array() )
    {
        parent::__construct( $oComp, $raConfig );
    }
}

class KeyframeUIWidget_Form extends SEEDUIWidget_Form
{
    function __construct( KeyframeUIComponent $oComp, $raConfig = array() )
    {
        parent::__construct( $oComp, $raConfig );
    }

    function Draw()
    {
        $s = "";

        if( $this->oComp->oForm->GetKey() ) {
            $s = parent::Draw();
        } else {
            $s = parent::Draw();
        }
        return( $s );
    }
}
