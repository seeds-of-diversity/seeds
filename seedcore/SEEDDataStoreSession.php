<?php

/* SEEDDataStoreSession
 *
 * Copyright 2010-2019 Seeds of Diversity Canada
 *
 * Implement a SEEDDataStore using a Session Namespace
 */

//include_once( "SEEDSession.php" ); do this for completeness
include_once( "SEEDDataStore.php" );

class SEEDDataStoreSession extends SEEDDataStoreSVA
/*************************
    Implement a SEEDDataStore using a Session Namespace
 */
{
    function __construct( SEEDSession $sess, $ns = "", $raConfig = array() )
    {
        $oSVA = new SEEDSessionVarAccessor( $sess, $ns );
        parent::__construct( $oSVA, $raConfig );
    }
}

class SEEDDataStoreSVA extends SEEDDataStore
/*********************
    Implement a SEEDDataStore using a SEEDSessionVarAccessor
 */
{
    private $oSVA;

    function __construct( SEEDSessionVarAccessor $oSVA, $raConfig = array() )
    {
        $this->oSVA = $oSVA;
        parent::__construct( $raConfig + ['bNoClearOnConstruct'=>true] );
    }

    /* Override the Data-side methods.
     * The Application-side methods are normally not overridden.
     */
    function DSClear()            { $this->oSVA->VarUnSetAll(); }
    function DSLoad( $k, $r )     { return( true ); }
    function DSValue( $k )        { return( $this->oSVA->VarGet( $k ) ); }
    function DSSetValue( $k, $v ) { $this->oSVA->VarSet( $k, $v ); }
    function DSOp( $op )          {}
    function DSPreStore()         { return( true ); }
    function DSStore()            { return( $this->oSVA ); }
    function DSGetDataObj()       { return( $this->oSVA ); }

    function DSValuesRA()
    /********************
        Return a simple array containing all values in the data store.
     */
    {
        $raOut = array();

        $raData = $this->oSVA->VarGetAllRA();
        foreach( $raData as $k => $v ) {
            $raOut[$k] = $this->Value( $k );    // to get all the urlparm goodness
        }
        return( $raOut );
    }
}
