<?php

/* SEEDCoreFormSession
 *
 * Copyright 2019 Seeds of Diversity Canada
 *
 * Implement a SEEDCoreForm using a session namespace
 */

include_once( SEEDCORE."SEEDDataStoreSession.php" );

class _seedCoreFormSessionBase extends SEEDCoreForm
/*****************************
    SEEDCoreForm derivations that store values in a session variable namespace
 */
{
    // protected $oDS;  defined in SEEDCoreForm - derivations must create a SEEDDataStore* before calling SEEDCoreForm::__construct

    protected $oSVACtrlGlobal;  // derivations must create a child oSVA to hold global control parms

    public function __construct( $cid = NULL, $raConfig = array() )    // although php >= 7.2 allows this to be protected 7.0 wants it to match SEEDCoreForm::__construct, which is wrong
    {
    //  this needs to get the urlparms from the fields array
        parent::__construct( $cid, $raConfig );
    }

    function CtrlGlobal( $k )
    /************************
        Override SEEDCoreForm::CtrlGlobal to store control parms persistently
     */
    {
        return( $this->oSVACtrlGlobal->VarGet($k) );
    }

    function CtrlGlobalIsSet( $k )
    /*****************************
     */
    {
        return( $this->oSVACtrlGlobal->VarIsSet($k) );
    }

    function CtrlGlobalSet( $k, $v )
    /*******************************]
        Override SEEDForm::CtrlGlobalSet to store control parms persistently
     */
    {
        $this->oSVACtrlGlobal->VarSet($k,$v);
    }
}

class SEEDCoreFormSession extends _seedCoreFormSessionBase
/************************
    Same as SEEDCoreForm but store the data in a session variable namespace
 */
{
    function __construct( SEEDSession $sess, $ns, $cid = NULL, $raConfig = array() )
    {
    //  this needs to get the urlparms from the fields array
        $this->oDS = new SEEDDataStoreSession( $sess, $ns, @$raConfig['DSParms'] ?: array() );
        $this->oSVACtrlGlobal = new SEEDSessionVarAccessor( $sess, $ns."_ctrlGlobal" );
        parent::__construct( $cid, $raConfig );
    }
}

class SEEDCoreFormSVA extends SEEDCoreForm
/********************
    Same as SEEDCoreForm but store the data in a given SEEDsessionVarAccessor
 */
{
    function __construct( SEEDSessionVarAccessor $oSVA, $cid = NULL, $raConfig = array() )
    {
        $this->oDS = new SEEDDataStoreSVA( $oSVA, @$raConfig['DSParms'] ?: array() );
        $this->oSVACtrlGlobal = $oSVA->CreateChild( "_ctrlGlobal" );
        parent::__construct( $cid, $raConfig );
    }
}
