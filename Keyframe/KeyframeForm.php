<?php

/* KeyframeForm.php
 *
 * Copyright (c) 2008-2018 Seeds of Diversity Canada
 *
 * Implement a SEEDForm that uses a KeyframeRecord as a data source.
 */

include_once( "KeyframeDataStore.php" );
include_once( SEEDCORE."SEEDCoreForm.php" );


class KeyframeForm extends SEEDCoreForm
/*****************
 */
{
    // $oDS is defined in SEEDForm - this derivation must create a KeyFrameDataStore before calling SEEDCoreForm::__construct

    private $kfrel;  // though this is also stored in the oDS, it makes more sense for clients to reference it here via Kfrel()

    function __construct( KeyFrame_Relation $kfrel, $cid = NULL, $raParms = array() )
    {
        $this->kfrel = $kfrel;
        $this->oDS = new Keyframe_DataStore( $kfrel, isset($raParms['DSParms']) ? $raParms['DSParms'] : array() );
        // This prevents rows with no data and a zero key from being inserted.  Probably, they are unfilled rows in a table form. Override by explicitly setting to false.
        if( !isset($raParms['bSkipBlankRows']) )  $raParms['bSkipBlankRows'] = true;
        parent::__construct( $cid, $raParms );
    }

    function Kfrel()  { return( $this->kfrel ); }

    function GetKey()  { return( ($kfr = $this->oDS->GetDataObj()) ? $kfr->Key() : 0 ); }

    function GetKFR()                       { return( $this->oDS->GetKFR() ); }
    function SetKFR( KeyframeRecord $kfr )  { $this->oDS->SetKFR( $kfr ); }

    function LoadKFR( int $k )
    /*************************
        Load the given kfr from db and put it in the form
     */
    {
        if( !$k || !($kfr = $this->kfrel->GetRecordFromDBKey($k)) ) {
            $kfr = $this->kfrel->CreateRecord();
        }
        $this->SetKFR($kfr);
    }

    // Additional Form Elements that are KeyFrame specific (these should use the same format as SEEDForm Elements)

    function HiddenKey()
    /*******************
        Write the current row's key into a hidden form parameter.
     */
    {
        return( $this->HiddenKeyParm( $this->GetKey() ) );    // use SEEDForm::HiddenKeyParm to encode the key as an sfParmKey
    }

    function DeleteKFRecord()    { return( $this->oDS->Op('d') ); }
    function HideKFRecord()      { return( $this->oDS->Op('h') ); }
    function RestoreKFRecord()   { return( $this->oDS->Op('r') ); }
}
