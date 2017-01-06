<?php

/* SEEDCore
 *
 * Copyright (c) 2016 Seeds of Diversity Canada
 *
 * Basic functions useful in most applications
 */

function SEEDInput_Str( $k, $sDefault = '' )
/*******************************************
 */
{
    return( isset($_REQUEST[$k]) ? $_REQUEST[$k] : $sDefault );
}

function SEEDInput_Int( $k, $iDefault = 0 )
/******************************************
 */
{
    return( isset($_REQUEST[$k]) ? intval($_REQUEST[$k]) : $iDefault );
}

function SEEDInput_Smart( $k, $raAllowed )
/*****************************************
    $k = the name of a GPC parm
    $raAllowed = allowable values ([0]=default)

    if count($raAllowed) == 1:    any non-empty values are accepted, but empty input forces the default value
    if count($raAllowed) > 1:     values are constrained to those in the array, empty input defaults to the first value
 */
{
    $p = SEEDInput_Str($k);

    if( !$p )  return( $raAllowed[0] );

    return( count($raAllowed) == 1 ? $p : SEEDCore_SmartVal( $p, $raAllowed ) );
}


function SEEDCore_Ent( $s )
/**************************
    Since the default charset used by htmlentities depends on the php version, standardize the charset by using this instead
 */
{
    return( htmlentities( $s, ENT_QUOTES, 'cp1252') );  // assuming php will not soon use unicode natively
}

function SEEDCore_HSC( $s )
/**************************
    Since the default charset used by htmlspecialchars depends on the php version, standardize the charset by using this instead
 */
{
    return( htmlspecialchars( $s, ENT_QUOTES, 'cp1252') );  // assuming php will not soon use unicode natively
}


/**
 * Replace "[[foo]]" in template with $ra['foo']
 */
function SEEDCore_ArrayExpand( $ra, $sTemplate, $bEnt = true )
/*************************************************************
 */
{
    foreach( $ra as $k => $v ) {
        $sTemplate = str_replace( "[[$k]]", ($bEnt ? SEEDCore_HSC($v) : $v), $sTemplate );
    }
    return( $sTemplate );
}

/**
 *  if $raAllowed contains 1 value, then $raParms[$k] is unconstrained (except for empty or !isset) and $raAllowed[0] is the default:
 *      { Return $raParms[$k] if isset() and not empty, or isset() and empty and $bEmptyAllowed : else return $raAllowed[0] }
 *
 *  if $raAllowed contains >1 values, then $raParms[$k] is constrained to that set and $raAllowed[0] is the default ($bEmptyAllowed is not used):
 *      { Return $raParms[$k] if it is in $raAllowed; return $raAllowed[0] if $raParms[$k] is not in that list or not set }
 */
function SEEDCore_ArraySmartVal( $raParms, $k, $raAllowed, $bEmptyAllowed = true )
/*********************************************************************************
    raParms is a set of values provided from some input e.g. function argument parms, http parms, user input, etc
    raAllowed is the set of all allowed values (or a single default value)

    if $raAllowed contains 1 value:
        if $bEmptyAllowed
            $raParms[$k] is allowed to be any value, including empty
            if $raParms[$k] is not set, return $raAllowed[0]
        else
            $raParms[$k] is allowed to be any value, except empty
            if $raParms[$k] is empty or not set, return $raAllowed[0]


    if $raAllowed contains >1 values:
        $raParms[$k] is constrained to that set of values
        if $raParms[$k] is not set, return $raAllowed[0]
        if $raParms[$k] is allowed, return it
        if $raParms[$k] is not allowed, return $raAllowed[0]
 */
{
    if( !isset($raParms[$k]) )  return( $raAllowed[0] );

    if( count($raAllowed) == 1 ) {
        return( (empty($raParms[$k]) && !$bEmptyAllowed ) ? $raAllowed[0] : $raParms[$k] );
    } else {
        return( SEEDCore_SmartVal( $raParms[$k], $raAllowed ) );
    }
}

/**
 * $v is constrained to the set of $raAllowed. Return $v if it is in the array or $raAllowed[0] if not
 */
function SEEDCore_SmartVal( $v, $raAllowed )
/*******************************************
    Return $v if it is in $raAllowed.  Else return $raAllowed[0]
 */
{
    return( in_array( $v, $raAllowed, true ) ? $v : $raAllowed[0] );
}

?>
