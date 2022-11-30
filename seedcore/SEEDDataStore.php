<?php

/* SEEDDataStore
 *
 * Copyright 2010-2017 Seeds of Diversity Canada
 *
 * The base class for a virtual data store.  A data store is any structure that can store a set of name-value pairs
 * e.g. a session array, a database record.
 *
 *
 * Usage and Derivation
 * --------------------
 * The data store is an interface with two sides: application and data.
 * The application side sees a name/value space, possibly structured as keyed records.  The data side sees real data storage.
 * Some transformation can happen between the two sides, such as url-parm mapping.
 *
 * Application uses:
 *      Load()     - get a record (if the data store supports records e.g. database)
 *      Value()    - get a named value from current record
 *      SetValue() - set a named value in the current record
 *      Op()       - perform an operation on the current record
 *      PreStore() - validate the current record
 *      PostStore()- called after successful Store
 *      Store()    - save the current record
 *
 * Data side uses:
 *      DSLoad(), DSValue(), DSSetValue(), DSOp(), DSPreStore(), DSPostStore(), DSStore(), DSGetDataObj()
 *      These are supplied by derived classes, called from the above Application-side methods.
 *
 *  Url-encoded multiplexed data storage fields
 *  -------------------------------------------
 *  Extension fields are supported, in which any number of name=value pairs are stored in a single field in url-parm format.
 *  On the data store side, the field is the full url-encoded string.
 *  On the form side, the field is a single name=value pair.
 *  This class handles the translation using a map:
 *      $raParms['urlparms'] = array( 'form_field1' => 'data_A',
 *                                    'form_field2' => 'data_A',
 *                                    'form_field3' => 'data_B',
 *                                    'form_field4' => 'data_B' )
 *      So the data store will contain two fields data_A = form_field1=val1&form_field2=val2
 *                                                data_B = form_field3=val3&form_field4=val4
 */

include_once( "SEEDCore.php" );     // SEEDCore_HSC

class SEEDDataStore
{
    /* raParms:
     *     urlparms      = array( url-encoded multiplexed data storage fields )
     *     fn_DSPreStore = function to call in place of DSPreStore - sometimes it's easier to specify a function rather than make a derived class
     *     fn_DSPostStore = function to call in place of DSPostStore
     */
    private   $raBaseData = array();    // the base data source is an array; derived classes must have their own storage
    protected $raParms = array();       // derived classes can access parms

    function __construct( $raConfig = [] )
    {
        $this->raParms = $raConfig;
        if( !@$raConfig['bNoClearOnConstruct'] ) {  // persistent datastores should not clear their data here
            $this->Clear();
        }
    }

    /*** Application-side methods ***/

    function Load( $k, $r = 0 )
    /**************************
        Load up the given row/key from the data store.  e.g. if k==0 a db would prepare a new row (but probably not commit it yet)

        Return false if there's some problem, to cancel the update for this row.
     */
    {
        return( $this->DSLoad( $k, $r ) );
    }

    function Value( $k )
    /*******************
        Get a named value from the current data store record.
        Implement urlparms transparently, so the application-side code only knows about name-values, the data-side code
        only knows about urlencoded multiplexed parms.
     */
    {
        if( isset($this->raParms['urlparms'][$k]) ) {
            // the parm is stored in an urlencoded field of the data store
            $fld = $this->raParms['urlparms'][$k];
            $s = $this->DSValue( $fld );
            $v = SEEDCore_ParmsURLGet( $s, $k );
        } else {
            $v = $this->DSValue( $k );
        }
        return( $v );
    }

    function SetValue( $k, $v )
    /**************************
        Set a named value in the current data store record.
        Implement urlparms transparently, so the application-side code only knows about form parms, and the data-side code
        only knows about urlencoded multiplexed parms.
     */
    {
        if( isset($this->raParms['urlparms'][$k]) ) {
            // the parm is stored in an urlencoded field of the data store
            $fld = $this->raParms['urlparms'][$k];
            $s = $this->DSValue( $fld );
            $s = SEEDCore_ParmsURLAdd( $s, $k, $v );
            $this->DSSetValue( $fld, $s );
        } else {
            $this->DSSetValue( $k, $v );
        }
    }

    function GetValuesRA()
    /*********************
        Return a simple array containing all values in the data store.
        This is only implemented for the base implementation so far.
     */
    {
        $raOut = array();

        foreach( $this->raBaseData as $k => $v ) {
            $raOut[$k] = $this->Value( $k );    // to get all the urlparm goodness
        }
        return( $raOut );
    }

    function SetValuesRA( $ra )
    /**************************
        Set a bunch of values from an array.

        This is also used to merge an array into the existing datastore. If you want to replace the whole thing, use Clear() first.
     */
    {
        foreach( $ra as $k => $v ) {
            $this->SetValue( $k, $v );
        }
    }

    function Clear()
    /***************
        Remove all values from the datastore.
     */
    {
        $this->raBaseData = array();
    }

    function Op( $op )
    /*****************
        Perform an operation on the current data store record.
     */
    {
        if( isset($this->raParms['fn_DSPreOp']) ) {
            $ok = call_user_func($this->raParms['fn_DSPreOp'], $this, $op );
        } else {
            $ok = $this->DSPreOp( $op );
        }
        return( $ok ? $this->DSOp($op) : false );
    }

    function PreStore()
    /******************
        This is called immediately before Store, allowing an application derived class to validate and modify row
        data before it is committed, without having to know the details of Store.

        Modify the row data here as desired (e.g. reorganize, trim, correct spelling, etc).
        Return true to commit the row using Store; return false to cancel the commit.
     */
    {
        if( isset($this->raParms['fn_DSPreStore']) ) {
            return( call_user_func($this->raParms['fn_DSPreStore'], $this ) );
        } else {
            return( $this->DSPreStore() );
        }
    }

    function Store()
    /***************
        Commit the current row to the data store, and return the data store object.
     */
    {
        return( $this->DSStore() );
    }

    function PostStore( $o, $o2 = null )
    /***********************************
        This is called after Store, for whatever purpose the user has.
        $o is whatever Store returned (an array of values, a session var array, a kfr, etc)
        $o2 is optionally another obj of use. SEEDForm uses this to send $oForm (itself) to the app.
     */
    {
        if( isset($this->raParms['fn_DSPostStore']) ) {
            return( call_user_func($this->raParms['fn_DSPostStore'], $o, $o2 ) );
        } else {
            return( $this->DSPostStore() );
        }
    }

    function Key()
    /*************
       Return the key of the current row in the data store (0 if new row, NULL if n/a)
     */
    {
        return( $this->DSKey() );
    }

    function SetKey( $k )
    /********************
        Change the key of the current row in the data store (0 is not allowed)
     */
    {
        $this->DSSetKey($k);
    }

    function GetDataObj()
    /********************
        Return the active data object for the instantiated derivation of SEEDDataStore
     */
    {
        return( $this->DSGetDataObj() );
    }

    function ValueInt( $k )     { return( intval($this->Value($k)) ); }
    function ValueEnt( $k )     { return( SEEDCore_HSC($this->Value($k)) ); }
    function ValueDB( $k )      { return( addslashes($this->Value($k)) ); }
    function IsEmpty( $k )      { $v = $this->Value($k); return( empty($v) ); } // because empty doesn't work on methods

    function CastInt( $k )      { $this->SetValue( $k, $this->ValueInt($k) ); return( $this->Value($k) ); }

    function SetValuePrepend( $k, $v ) { $this->SetValue( $k, ($v . $this->Value($k)) ); }
    function SetValueAppend( $k, $v )  { $this->SetValue( $k, ($this->Value($k) . $v) ); }

    function SmartValue( $k, $raValues, $v = null )
    /**********************************************
        Ensure that the named value is in the given array. Set it to the first one if not.
     */
    {
        if( $v !== null ) $this->SetValue( $k, $v );
        if( !in_array( $this->Value($k), $raValues ) )  $this->SetValue( $k, $raValues[0] );
    }

    function Expand( $sTemplate, $bEnt = true )
    /******************************************
        Return template string with all [[value]] replaced
     */
    {
        for(;;) {
            $s1 = strpos( $sTemplate, "[[" );
            $s2 = strpos( $sTemplate, "]]" );
            if( $s1 === false || $s2 === false )  break;
            $k = substr( $sTemplate, $s1 + 2, $s2 - $s1 - 2 );
            if( empty($k) ) break;

            $sTemplate = substr( $sTemplate, 0, $s1 )
                        .($bEnt ? $this->ValueEnt($k) : $this->Value($k))
                        .substr( $sTemplate, $s2+2 );
        }
        return( $sTemplate );
    }

    function ExpandIfNotEmpty( $fld, $sTemplate, $bEnt = true )
    /**********************************************************
        Return template string with [[]] replaced by the value of the field, if it is not empty.
        This lets you do this:  ( !$kfr->IsEmpty('foo') ? ($kfr->value('foo')." items<BR/>") : "" )
                    with this:  ExpandIfNotEmpty( 'foo', "[[]] items<BR/>" )
     */
    {
        if( !$this->IsEmpty($fld) )  return( str_replace( "[[]]", ($bEnt ? $this->ValueEnt($fld) : $this->Value($fld)), $sTemplate ) );
    }


    /*** Data-side methods ***/

    // Base class uses raBaseData to hold the current row.
    // Derived classes probably use their own data store (which is the point of this class).
    function DSLoad( $k, $r )
    {
        $this->raBaseData = array();
        return( true );
    }

    function DSValue( $k )        { return( @$this->raBaseData[$k] ); }
    function DSSetValue( $k, $v ) { $this->raBaseData[$k] = $v; }
    function DSPreOp( $op )       { return( true ); }               // base implementation doesn't have to do anything
    function DSOp( $op )          { }                               // no operations are defined in the base implementation
    function DSPreStore()         { return( true ); }               // base implementation doesn't have to do anything
    function DSPostStore()        { return( true ); }               // base implementation doesn't have to do anything
    function DSStore()            { return( $this->raBaseData ); }  // base implementation doesn't have to do anything
    function DSKey()              { return( null ); }               // base implementation has no key
    function DSSetKey( $k )       { }                               // base implementation has no key
    function DSGetDataObj()       { return( $this->raBaseData ); }  // base implementation uses this array to store data
}

?>