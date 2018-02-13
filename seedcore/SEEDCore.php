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
    // SEEDCore_SmartVal uses strict in_array so the type of its key has to match the type in the array
    if( is_integer($raAllowed[0]) ) {
        $p = SEEDInput_Int($k);
    } else {
        $p = SEEDInput_Str($k);
    }

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


/******
 * SEEDCore_ArrayExpand(*)
 *
 * ArrayExpand          takes one row of named values, expands into a template of "A [[foo]] B [[bar]] C" once.
 * ArrayExpandRows      takes an array of rows of named values, expands into a template as above. First and last rows can have their own templates.
 * ArrayExpandSeries    takes an array of scalars, expands into a template "A [[]] B" repeated once for each element. First and last can have their own templates.
 */

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
 * Replace "[[]]" with $ra[0], repeat for $ra[1], etc.
 */
function SEEDCore_ArrayExpandSeries( $ra, $sTemplate, $bEnt = true, $raParms = array() )
/***************************************************************************************
    raParms: sTemplateFirst : use this template on the first element
             sTemplateLast  : use this template on the last element
 */
{
    $s = "";

    $i = 0;
    $iLast = count($ra) - 1;
    foreach( $ra as $v ) {
        $tmpl = ( $i == 0 && isset($raParms['sTemplateFirst']) )    ? $raParms['sTemplateFirst'] :
                (($i == $iLast && isset($raParms['sTemplateLast'])) ? $raParms['sTemplateLast']
                                                                    : $sTemplate );
        $s .= str_replace( "[[]]", ($bEnt ? SEEDCore_HSC($v) : $v), $tmpl );
    }

    return( $s );
}

/**
 * Replace "[[foo]]" in template with $ra[0]['foo'], repeat for $ra[1], etc
 */
function SEEDCore_ArrayExpandRows( $raRows, $sTemplate, $bEnt = true, $raParms = array() )
/*****************************************************************************************
    raParms: sTemplateFirst : use this template on the first element
             sTemplateLast  : use this template on the last element
 */
{
    $s = "";

    $i = 0;
    $iLast = count($raRows) - 1;
    foreach( $raRows as $ra ) {
// use this like above
// $tmpl = ( $i == 0 && isset($raParms['sTemplateFirst']) )    ? $raParms['sTemplateFirst'] :
//         (($i == $iLast && isset($raParms['sTemplateLast'])) ? $raParms['sTemplateLast']
//                                                             : $sTemplate );
// $s .= SEEDCore_ArrayExpand( $ra, $tmpl, $bEnt );

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

function SEEDCore_NBSP( $s, $n = 0 )
/***********************************
    Replace spaces in $s with "&nbsp;", then append $n x "&nbsp;"

    e.g.    ("foo bar")     returns "foo&nbsp;bar"
            ("foo bar",5)   returns "foo&nbsp;bar&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"
            ("",5)          returns "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"
 */
{
    $sOut = "";
    if( $s )  $sOut .= str_replace( " ", "&nbsp;", $s );
    while( $n-- > 0 ) $sOut .= "&nbsp;";
    return( $sOut );
}

function SEEDCore_Dollar( $fAmount, $lang = "EN" )      // also see SEEDLocal::Dollar()
{
    if( $lang == "EN" ) {
        $s = "$".sprintf("%.2f",$fAmount);
    } else {
        $d1 = intval($fAmount);
        $d2 = intval(($fAmount - $d1)*100);
        $s = "$d1,".sprintf("%02d",$d2)." $";
    }
    return( $s );
}


function SEEDCore_ParseRangeStr( $sRange )
/*****************************************
    Parse a string containing a potentially complicated range of numbers
    e.g. 5-6,8,9,12,3,1-5,13-15,9-9
    The only constraint is ranges with hyphens cannot be decreasing (note that 9-9 is allowed; it is just 9)

    Return a normalized string for that range e.g. 1-6,8-9,12-15 which is guaranteed to be reparsable by this function,
    and an array containing all the numbers
 */
{
    $raRange = array();
    $sRangeNormal = "";

    /* First explode the range into the array of numbers
     */
    $ra = explode( ',', $sRange );
    foreach( $ra as $sN ) {
        $sN = trim($sN);
        if( !$sN )  continue;    // otherwise a blank term becomes a 0
        if( strpos($sN,'-') === false ) {
            // just one value
            $n = intval($sN);
            if( !in_array( $n, $raRange ) )  $raRange[] = $n;
        } else {
            // a range separated by '-'
            list($n1,$n2) = explode('-',$sN);
            $n1 = intval($n1);
            $n2 = intval($n2);
            if( $n1 && $n1 <= $n2 ) {
                for( $n = $n1; $n <= $n2; ++$n ) {
                    if( !in_array( $n, $raRange ) )  $raRange[] = $n;
                }
            }
        }
    }
    sort($raRange);

    /* Now process the array of numbers into a normalized range string
     */
    $sRangeNormal = SEEDCore_MakeRangeStr( $raRange, true );

    return( array( $raRange, $sRangeNormal ) );
}

function SEEDCore_MakeRangeStr( $raNumbers, $bSorted = false )
/*************************************************************
    Make a range string e.g. 1-3,5,7-9 from the given set of numbers, which is guaranteed
    to be parseable by SEEDCore_ParseRangeStr to get the same set of numbers.

    $bSorted tells us if the array is already sorted, so we don't have to sort it again
 */
{
    if( !count($raNumbers) ) return( "" );    // otherwise the code below will write out "0"

    $s = "";

    $raNCopy = $raNumbers;     // necessary?  does php sort the original array or a copy?
    if( !$bSorted ) {
        sort($raNCopy);
    }


    $n1 = 0;
    $n2 = 0;
    foreach( $raNCopy as $n ) {
        if( !$n1 ) {
            $n1 = $n2 = $n;
        } else if( $n == $n2 + 1 ) {
            $n2 = $n;
        } else {
            // found an n outside of the currently-stored range, so write out the stored term
            $s .= $s ? ',' : '';
            $s .= $n1 == $n2 ? $n1 : "$n1-$n2";

            $n1 = $n2 = $n;
        }
    }
    // write out the last stored term
    $s .= $s ? ',' : '';
    $s .= $n1 == $n2 ? $n1 : "$n1-$n2";

    return( $s );
}

function SEEDCore_MakeRangeStrDB( $raNumbers, $fld, $bSorted = false )
/*********************************************************************
    Same as SEEDCore_MakeRangeStr but the output is for sql queries
        e.g. Input: 1-3,5,7-9,11
             Output: (fld in (5,11) or fld between 1 and 3 or fld between 7 and 9)
 */
{
    if( !count($raNumbers) ) return( "" );    // otherwise the code below will write out "0"

    $s = "";

    /* Turn the numbers into a normalized range string, then translate that into sql
     */
    $sRange = SEEDCore_MakeRangeStr( $raNumbers, $bSorted );

    return( SEEDCore_RangeStrToDB( $sRange, $fld ) );
}

function SEEDCore_RangeStrToDB( $sRange, $fld )
/**********************************************
    Turn a normal range string into an sql condition

    See SEEDCore_MakeRangeStrDB for output format.
 */
{
    if( !$sRange ) return( "" );

    $s = "";

    $raSingles = array();    // record the numbers for in()
    $raRanges = array();     // record the 'between' terms

    $ra = explode( ',', $sRange );
    foreach( $ra as $sN ) {
        if( strpos($sN,'-') === false ) {
            // just one value
            $n = intval($sN);
            $raSingles[] = intval($sN);
        } else {
            // a range separated by '-'
            list($n1,$n2) = explode('-',$sN);
            $raRanges[] = "$fld between $n1 and $n2";
        }
    }

    if( count($raSingles) ) {
        $s .= "$fld in(".implode(',',$raSingles).")";
    }
    if( count($raRanges) ) {
        if( count($raSingles) ) $s .= " or ";
        $s .= implode(" or ", $raRanges );
    }

    if( $s ) $s = "($s)";

    return( $s );
}

?>
