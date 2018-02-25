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
/**********************************
 */
{
    // $oDS is defined in SEEDForm - this derivation must create a KeyFrameDataStore before calling SEEDForm

    private $kfrel;  // though this is also stored in the oDS, it makes more sense for clients to reference it here

    function __construct( KeyFrame_Relation $kfrel, $cid = NULL, $raParms = array() )
    {
        $this->kfrel = $kfrel;
        $this->oDS = new Keyframe_DataStore( $kfrel, isset($raParms['DSParms']) ? $raParms['DSParms'] : array() );
        // This prevents rows with no data and a zero key from being inserted.  Probably, they are unfilled rows in a table form. Override by explicitly setting to false.
        if( !isset($raParms['bSkipBlankRows']) )  $raParms['bSkipBlankRows'] = true;
        parent::__construct( $cid, $raParms );
    }

    function SetKFR( KeyframeRecord $kfr )  { $this->oDS->SetKFR( $kfr ); }

    function GetKey()  { return( ($kfr = $this->oDS->GetDataObj()) ? $kfr->Key() : 0 ); }

    // Additional Form Elements that are KeyFrame specific (these should use the same format as SEEDForm Elements)

    function HiddenKey()
    /*******************
        Write the current row's key into a hidden form parameter.
     */
    {
        return( $this->HiddenKeyParm( $this->GetKey() ) );    // use SEEDForm::HiddenKeyParm to encode the key as an sfParmKey
    }
}

?>
