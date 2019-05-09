<?php

/* KeyframeUI
 *
 * UI classes for Keyframe
 *
 * Copyright (c) 2009-2019 Seeds of Diversity Canada
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

    function GetViewWindow()
    {
        $raViewParms = array();

        $raViewParms['sSortCol']  = $this->GetUIParm('sSortCol');
        $raViewParms['bSortDown'] = $this->GetUIParm('bSortDown');
        $raViewParms['sGroupCol'] = $this->GetUIParm('sGroupCol');
        $raViewParms['iStatus']   = $this->GetUIParm('iStatus');

        $oView = new KeyframeRelationView( $this->kfrel, $this->sSqlCond, $raViewParms );
        $raWindowRows = $oView->GetDataWindowRA( $this->Get_iWindowOffset(), $this->Get_nWindowSize() );
        return( array( $oView, $raWindowRows ) );
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
            $o = new SEEDFormExpand( $this->oComp->oForm );
            $s = $o->ExpandForm( $this->raConfig['sTemplate'] );
        }
        return( $s );
    }
}
