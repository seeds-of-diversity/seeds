<?php

/* SEEDDataStoreStringBucket
 *
 * Copyright 2026 Seeds of Diversity Canada
 *
 * Implement a SEEDDataStore using a SEEDMetaTable_StringBucket
 */

include_once( "SEEDDataStore.php" );
include_once( "SEEDMetaTable.php" );

class SEEDDataStoreStringBucket extends SEEDDataStore
/******************************
    Implement a SEEDDataStore using a SEEDMetaTable_StringBucket
 */
{
    private $oBucket;
    private $ns;

    function __construct( SEEDAppDB $oApp, string $namespace, $raConfig = [] )
    {
        $this->oBucket = new SEEDMetaTable_StringBucket($oApp->kfdb);
        $this->ns = $namespace;
        parent::__construct( $raConfig + ['bNoClearOnConstruct'=>true] );
    }

    /* Override the Data-side methods.
     * The Application-side methods are normally not overridden.
     */
    function DSClear()            {}  // not implemented - need a StringBucket method to clear all values of namespace
    function DSLoad( $k, $r )     { return( true ); }
    function DSValue( $k )        { return( $this->oBucket->GetStr($this->ns, $k) ); }
    function DSSetValue( $k, $v ) { $this->oBucket->PutStr($this->ns, $k, $v); }
    function DSOp( $op )          {}
    function DSPreStore()         { return( true ); }
    function DSStore()            { return( $this->oBucket ); }
    function DSGetDataObj()       { return( $this->oBucket); }

    function DSValuesRA()
    /********************
        Return a simple array containing all values in the data store.
     */
    {
        $raOut = [];

        // not implemented - need a StringBucket method to get all values of namespace

        return( $raOut );
    }
}
