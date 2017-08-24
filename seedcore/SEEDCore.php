<?php

/* SEEDCore
 *
 * Copyright (c) 2016-2017 Seeds of Diversity Canada
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

function SEEDInput_Get( $k )
/***************************
	Get the value of a GPC parm and return variants in an array
 */
{
    $raOut = array( 'plain'=>'', 'db'=>'', 'ent'=>'' );

    if( $k && ($v = @$_REQUEST[$k]) ) {
        $raOut['plain'] = $v;
        $raOut['db'] = addslashes($v);
        $raOut['ent'] = SEEDCore_Ent($v);
    }

    return( $raOut );
}

function SEEDInput_GetStrDB( $k )
/********************************
    If you aren't running some low version of php 5.x you can just use SEEDInput_Get($k)['db'] instead of this function.
 */
{
    $r = SEEDInput_Get( $k );
    return( $r['db'] );
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

function SEEDCore_ArrayExpandRows( $raRows, $sTemplate, $bEnt = true, $raParms = array() )
/*****************************************************************************************
    raRows is an array of arrays, each one to be expanded using the sTemplate

    raParms:
        sTemplateFirst : use this template on the first element
        sTemplateLast  : use this template on the last element
 */
{
    $s = "";

    $i = 0;
    $iLast = count($raRows) - 1;
    foreach( $raRows as $ra ) {
        if( $i == 0 && isset($raParms['sTemplateFirst']) ) {
            $s .= SEEDCore_ArrayExpand( $ra, $raParms['sTemplateFirst'], $bEnt );
        } else if( $i == $iLast && isset($raParms['sTemplateLast']) ) {
            $s .= SEEDCore_ArrayExpand( $ra, $raParms['sTemplateLast'], $bEnt );
        } else {
            $s .= SEEDCore_ArrayExpand( $ra, $sTemplate, $bEnt );
        }
    }

    return( $s );
}


function SEEDCore_ArraySmartVal( $raParms, $k, $raAllowed )
/**********************************************************
    $raParms[$k] must be one of the values in $raAllowed.
    If not, return $raAllowed[0]
 */
{
    return( isset($raParms[$k]) ? SEEDCore_SmartVal( $raParms[$k], $raAllowed )
                                : $raAllowed[0] );
}

function SEEDCore_ArraySmartVal1( $raParms, $k, $pDefault, $bEmptyAllowed = false )
/**********************************************************************************
    $raParms[$k] can have any value (except empty if !bEmptyAllowed).
    If it is not set, or it fails the bEmptyAllowed test, return $pDefault
 */
{
    if( !isset($raParms[$k]) )  return( $pDefault );

    return( (empty($raParms[$k]) && !$bEmptyAllowed ) ? $pDefault : $raParms[$k] );
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


function SEEDCore_EmailAddress( $s1, $s2, $label = "", $raMailtoParms = array(), $sAnchorAttrs = "" )
/****************************************************************************************************
    Write a spam-proof email address on a web page in the form:

    <a href='mailto:$s1@$s2'>$label</a>  or
    <a href='mailto:$s1@$s2'>$s1@$s2</a> if label is blank

    $sAnchorAttrs can contain additional attributes for the <a> tag - e.g. style='text-decoration:foo;color=bar'
 */
{
    $mparms = "";
    foreach( $raMailtoParms as $k => $v ) {
        $mparms .= ($mparms ? "?" : "&").$k."=".$v;  // I thought I should urlencode this, but Thunderbird doesn't decode it
    }

    $s = "<script language='javascript'>var a=\"$s1\";var b=\"$s2\";";
    if( empty($label) ) {
        $s .= "var l=a+\"@\"+b;";
    } else {
        $s .= "var l=\"$label\";";
    }
    $s .= "document.write(\"<a $sAnchorAttrs href='mailto:\"+a+\"@\"+b+\"$mparms'>\"+l+\"</a>\");</script>";
    return( $s );
}

?>
