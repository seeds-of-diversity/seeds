<?php

/* SEEDFormParms.php
 *
 * Copyright (c) 2008-2018 Seeds of Diversity Canada
 *
 * Standardize the names of form elements so that they can be easily isolated and marshalled, and provide basic parameter management.
 *
 * The design model allows multiple ui components (each with one data relation), and each component can have multiple records.
 * Components are identified by a single-letter cid.
 * Records are identified by a number {R}=(0..N) that has no relationship to the record's key.
 *
 * Record data and UI control codes are handled in two formats:
 *     1-D array of name-val pairs that serialize values and controls of all fields, records, and components. This is useful for serializing UI data into http parms.
 *     2-D array of records/fields.  A separate array for each component. This is useful for general manipulation of records.
 *
 * The 2-D array for a single component:
 *    array( 'rows' => array( 0 => array( 'k' => key of this record,    // typically used in KFRecord implementation
 *                                        'op' => 'd' | 'h' | 'r'  (if delete, hide, reset (undelete/unhide) - only one should be specified at a time)
 *                                        'values' => array( field => value, ... )
 *                                        'control' => array( name => value, ... ) ),
 *                            1 => array( ... ), ... )
 *           'control'=> array( name=>value, ... )    // global control parms for the component
 *
 *    R is an arbitrary number that helps the deserializer. It has no relationship to the record's key.
 *    Any number of rows can have k=0, which indicates a new row that has not yet been inserted into the db.
 *
 * The 1-D array:
 *
 *  sf{cid}k{R} = the key of row {R}, or 0 if this is a new row, or row is not in a database, or has no key
 *  sf{cid}d{R} = 1 : row {R} is to be deleted
 *  sf{cid}h{R} = 1 : row {R} is to be hidden
 *  sf{cid}r{R} = 1 : row {R} is to be reset to _status=0
 *  sf{cid}p{R}_{field} = {value} : the value of the given field in row {R}
 *
 *  also
 *  sf{cid}u{R}_{code} = {value} : a UI control code for row {R}
 *  sf{cid}x_{code}    = {value} : a UI control code for the whole component
 *
 *  If {R} is blank, it is assumed to be the row number 0.
 *  Note: It is common and convenient to leave {R} blank when only a single row is processed.
 *
 * --------
 *
 *  Summary of SEEDFormParms (for component A), used for form names and http parms:
 *
 *  Cid A = component id is a single letter identifying the form
 *  Row R = parms for an arbitrary form row R, where R is a positive number unrelated to the row's key.  This is used in forms that contain multiple rows.
 *  Row 0 = shorthand typically used for a single-row form.
 *
 *  Row 0       Row R
 *
 *  sfAk       sfAkR     : the row's key, 0 if the row is new, not in database, has no key
 *  sfAp_foo   sfApR_foo : the value of the base field 'foo' in the given row
 *  sfAd = 1   sfAdR = 1 : action command to delete the given row
 *  sfAh = 1   sfAhR = 1 : action command to hide the given row
 *  sfAr = 1   sfArR = 1 : action command to reset the given row (e.g. undelete/unhide via _status=0 in KFRecord)
 *  sfAx_blart           : global ui control parm 'blart' (e.g. for sorting, list control, etc)
 *  sfAu_bar   sfAuR_bar : ui control parm 'bar' for the given row (e.g. tree view expanded/collapsed at this row)
 */


class SEEDFormParms {
    private $cid = 'A';

    function __construct( $cid = null )
    /**********************************
        cid {blank} uses default 'A'
        cid "Plain" sets non-sfParm Vanilla mode
        cid other uses that prefix
     */
    {
        $this->SetCid( $cid );
    }

    function GetCid()       { return( $this->cid ); }
    function SetCid( $cid )
    {
        if( $cid == 'Plain' ) { $this->cid = ''; } // Vanilla mode
        else if( $cid )       { $this->cid = $cid; }
        else                  { $this->cid = 'A'; }
    }

    function sfParmField( $fld, $iR = 0 )
    {
        if( !$this->cid )  return( $fld );    // Vanilla mode

        if( $iR == 0 ) $iR = '';  return( "sf{$this->cid}p{$iR}_{$fld}" );
    }

    // these are not meaningful in Vanilla mode
    function sfParmKey( $iR = 0 )             { if( $iR == 0 ) $iR = '';  return( "sf{$this->cid}k{$iR}" ); }
    function sfParmOp( $op, $iR = 0 )         { if( $iR == 0 ) $iR = '';  return( "sf{$this->cid}{$op}{$iR}" ); }
    function sfParmControlRow( $fld, $iR = 0) { if( $iR == 0 ) $iR = '';  return( "sf{$this->cid}u{$iR}_{$fld}" ); }
    function sfParmControlGlobal( $fld )      {                           return( "sf{$this->cid}x_{$fld}" ); }

    function sfParmName( $fld, $iR = 0, $sType = '' )
    /************************************************
        This is useful for an API that wants to parameterize the parm-namespace of a form function
        e.g. Select() can set values into different parm-namespaces by passing sType from a calling function. One calling function
             may use Select for regular data values, another can use Select for ctrl navigation values.
     */
    {
        if( !$this->cid )  return( $fld );    // Vanilla mode

        $sType = SEEDCore_SmartVal( $sType, array('p','k','op','ctrl_row','ctrl_global') );
        switch( $sType ) {
            case 'p':           return( $this->sfParmField($fld,$iR) );
            case 'k':           return( $this->sfParmKey($iR) );
            case 'op':          return( $this->sfParmOp($fld,$iR) );
            case 'ctrl_row':    return( $this->sfParmControlRow($fld,$iR) );
            case 'ctrl_global': return( $this->sfParmControlGlobal($fld) );
        }
    }

    function Serialize( $ra2D )
    /**************************
        Given a 2-D parms array, return it in the 1-D format
     */
    {
        $raOut = array();

        foreach( $ra2D['rows'] as $iR => $raRow ) {
            if( @$raRow['k'] ) {
                $raOut[$this->sfParmKey( $iR )] = $raRow['k'];
            }
            if( @$raRow['op'] && in_array( $raRow['op'], array('d','h','r') ) ) {
                $raOut[$this->sfParmOp( $raRow['op'], $iR )] = 1;
            }
            if( @$raRow['values'] ) {
                foreach( $raRow['values'] as $k => $v ) {
                    $raOut[$this->sfParmField( $k, $iR )] = $v;
                }
            }
            if( @$raRow['control'] ) {
                foreach( $raRow['control'] as $k => $v ) {
                    $raOut[$this->sfParmControlRow( $k, $iR )] = $v;
                }
            }
        }
        foreach( $ra2D['control'] as $k => $v ) {
            $raOut[$this->sfParmControlGlobal( $k )] = $v;
        }
        return( $raOut );
    }

    function Deserialize( $ra1D, $bGPCDummy = false )   // remove bGPCDummy when no one sets that argument - magic quotes was removed in PHP5.4
    /************************************************
        Given a 1-D parms array, return the parms of the given cid in the 2-D format
     */
    {
        $raOut = array();
        $raOut['rows'] = array();
        $raOut['control'] = array();

        if( !$this->cid ) {
            // In Vanilla mode we can't distinguish which parms belong to our form and there can only be one record,
            // so just put $_REQUEST in the first $raOut row.  The code at the bottom fills in default portions of the row definition.
            $raOut['rows'][0]['values'] = $ra1D;
            goto done;
        }

        foreach( $ra1D as $k => $v ) {
            if( !SEEDCore_StartsWith( $k, 'sf'.$this->cid ) )  continue;
            $c = substr( $k, strlen('sf'.$this->cid), 1 );
            $r = intval( substr( $k, strlen('sf'.$this->cid)+1 ) );

            switch( $c ) {
                case 'k':
                    $raOut['rows'][$r]['k'] = intval($v);
                    break;
                case 'p':
                case 'u':
                case 'x':
                    if( ($i = strpos($k,'_')) !== false ) {
                        $fld = substr($k,$i+1);
                        if( !empty($fld) ) {
                                 if( $c == 'p' )  $raOut['rows'][$r]['values'][$fld] = $v;      // data value
                            else if( $c == 'u' )  $raOut['rows'][$r]['control'][$fld] = $v;     // per-row control code
                            else if( $c == 'x' )  $raOut['control'][$fld] = $v;                 // global control code
                        }
                    }
                    break;
                case 'd':
                case 'h':
                case 'r':
                    if( $v == 1 ) {
                        $raOut['rows'][$r]['op'] = $c;
                    }
                    break;
            }
        }

        done:

        /* Make sure every row has a key (default 0), and values, control arrays, to ease error checking (do this for Vanilla too)
         */
        foreach( $raOut['rows'] as $k => $v ) {
            if( !isset($raOut['rows'][$k]['k']) )       $raOut['rows'][$k]['k'] = 0;
            if( !isset($raOut['rows'][$k]['op']) )      $raOut['rows'][$k]['op'] = '';
            if( !isset($raOut['rows'][$k]['values']) )  $raOut['rows'][$k]['values'] = array();
            if( !isset($raOut['rows'][$k]['control']) ) $raOut['rows'][$k]['control'] = array();
        }

        return( $raOut );
    }
}

?>