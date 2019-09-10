<?php

include_once( SEEDCORE."SEEDDataStoreSession.php" );

class SEEDCoreFormSession extends SEEDCoreForm
/************************
    Same as SEEDCoreForm but store the data in a session variable namespace
 */
{
    // var $oDS;  defined in SEEDForm - this derivation must create a SEEDDataStoreSession before calling SEEDForm

    private $oSVACtrlGlobal;

    function __construct( SEEDSession $sess, $ns, $cid = NULL, $raParms = array() )
    {
    //  this needs to get the urlparms from the fields array
        $this->oDS = new SEEDDataStoreSession( $sess, $ns, isset($raParms['DSParms']) ? $raParms['DSParms'] : array() );
        $this->oSVACtrlGlobal = new SEEDSessionVarAccessor( $sess, $ns."_ctrlGlobal" );
        parent::__construct( $cid, $raParms );
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
