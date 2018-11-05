<?php

/*
 * SEEDDataStoreSession
 *
 * Copyright 2010-2017 Seeds of Diversity Canada
 *
 * Implement a SEEDDataStore using a Session Namespace
 */

//include_once( "SEEDSession.php" ); do this for completeness
include_once( "SEEDDataStore.php" );

class SEEDDataStoreSession extends SEEDDataStore
/*************************
    Implement a SEEDDataStore using a Session Namespace
 */
{
    private $oSVA;

    function __construct( SEEDSession $sess, $ns = "", $raParms = array() )
    {
        $this->oSVA = new SEEDSessionVarAccessor( $sess, $ns );
        parent::__construct( $raParms );
    }

    function GetValuesRA() { die( "GetValuesRA not implemented yet" ); }     // only implemented for the base implementation


    /* Override the Data-side methods.
     * The Application-side methods are normally not overridden.
     */
    function DSLoad( $k, $r )     { return( true ); }
    function DSValue( $k )        { return( $this->oSVA->VarGet( $k ) ); }
    function DSSetValue( $k, $v ) { $this->oSVA->VarSet( $k, $v ); }
    function DSOp( $op )          {}
    function DSPreStore()         { return( true ); }
    function DSStore()            { return( $this->oSVA ); }
    function DSGetDataObj()       { return( $this->oSVA ); }
}

?>