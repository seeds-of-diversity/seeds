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

function SEEDCore_ArrayExpandIfNotEmpty( $ra, $k, $sTemplate, $bEnt = true )
/***************************************************************************
    Return template string with all [[]] replaced by $ra[$k] if that value is not empty
 */
{
    $v = @$ra[$k];
    return( $v ? str_replace( "[[]]", ($bEnt ? SEEDStd_HSC($v) : $v), $sTemplate ) : "" );
}

/**
 * Replace "[[]]" with $ra[0], repeat for $ra[1], etc.
 *          [[v]] is the same as [[]]
 *          [[k]] substitutes the key instead of the value
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
    foreach( $ra as $k => $v ) {
        $tmpl = ( $i == 0 && isset($raParms['sTemplateFirst']) )    ? $raParms['sTemplateFirst'] :
                (($i == $iLast && isset($raParms['sTemplateLast'])) ? $raParms['sTemplateLast']
                                                                    : $sTemplate );
        $t0 = str_replace( "[[k]]", ($bEnt ? SEEDCore_HSC($k) : $k), $tmpl );
        $t0 = str_replace( "[[v]]", ($bEnt ? SEEDCore_HSC($v) : $v), $t0 );
        $s .= str_replace( "[[]]", ($bEnt ? SEEDCore_HSC($v) : $v), $t0 );
        ++$i;
    }

    return( $s );
}

/**
 * Replace "[[k]]" with key of first array element and [[v]] with value, repeat for each row
 */

// deprecated - use the regular function with [[k]] where you want the key to go
function SEEDCore_ArrayExpandSeriesWithKey( $ra, $sTemplate, $bEnt = true, $raParms = array() )
/**********************************************************************************************
    raParms: sTemplateFirst : use this template on the first element
             sTemplateLast  : use this template on the last element
 */
{
    $s = "";

    $i = 0;
    $iLast = count($ra) - 1;
    foreach( $ra as $k=> $v ) {
        $tmpl = ( $i == 0 && isset($raParms['sTemplateFirst']) )    ? $raParms['sTemplateFirst'] :
                (($i == $iLast && isset($raParms['sTemplateLast'])) ? $raParms['sTemplateLast']
                                                                    : $sTemplate );
        $t0 = str_replace( "[[k]]", ($bEnt ? SEEDCore_HSC($k) : $k), $tmpl );
        $s .= str_replace( "[[v]]", ($bEnt ? SEEDCore_HSC($v) : $v), $t0 );
        ++$i;
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
        ++$i;
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

function SEEDCore_UniqueId( $iTruncateLen = 0 )
/**********************************************
    An unguessable string. From PHP docs for uniqid.

    Optional truncation for a less unique, less unguessable, but shorter string
 */
{
    $s = md5(uniqid(rand(), true));

    return( $iTruncateLen ? substr( $s, 0, $iTruncateLen ) : $s );
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

    // </a> : drupal 8 converts this to "" right in the js string (don't know why), so the link never ends
    // </ a> : drupal 8 doesn't rewrite, but seems to convert to <!-- a --> even on non-drupal pages, so the link also never ends
    // <\/a> : always seems to do the right thing
    $s .= "document.write('<a $sAnchorAttrs href=\"mailto:'+a+'@'+b+'$mparms\">'+l+'<\/a>');</script>";
    return( $s );
}

function SEEDCore_EmailAddress2( $s1, $s2, $label = "", $raMailtoParms = array(), $sAnchorAttrs = "" )
/*****************************************************************************************************
    Write a spam-proof email address on a web page in the form:

    <a href='mailto:$s1@$s2'>$label</a>  or
    <a href='mailto:$s1@$s2'>$s1@$s2</a> if label is blank

    $sAnchorAttrs can contain additional attributes for the <a> tag - e.g. style='text-decoration:foo;color=bar'
 */
{
    $mparms = SEEDCore_ParmsRA2URL( $raMailtoParms, false );

    // jquery should act on .SEEDCore_mailto to create href='mailto:[a]@[b]?c'> and replace html() with [a]@[b]

    $s = "<a class='SEEDCore_mailto' a='$s1' b='$s2' c='".SEEDCore_HSC($mparms)."' d='".SEEDCore_HSC($label)."' $sAnchorAttrs>"
            ."$s1 [at] $s2"       // default label if jquery doesn't do the right thing
        ."</a>";

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

function SEEDCore_Bound( $i, $floor = null, $ceiling = null )
/************************************************************
    Constrain $i within the range of floor and ceiling
 */
{
    if( $floor   !== null && $i < $floor )   $i = $floor;
    if( $ceiling !== null && $i > $ceiling ) $i = $ceiling;

    return( $i );
}

function SEEDCore_StartsWith( $haystack, $needle )
/*************************************************
 */
{
     return( substr( $haystack, 0, strlen($needle) ) === $needle );
}

function SEEDCore_EndsWith( $haystack, $needle )
/***********************************************
 */
{
    $length = strlen($needle);
    return( substr( $haystack, -$length, $length ) === $needle );   // third parameter is for the boundary condition where $needle==''
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
    $sRangeNormal = "";

    // First explode the range into the array of numbers
    $raRange = SEEDCore_ParseRangeStrToRA( $sRange );

    // Now process the array of numbers into a normalized range string
    $sRangeNormal = SEEDCore_MakeRangeStr( $raRange, true );

    return( array( $raRange, $sRangeNormal ) );
}

function SEEDCore_ParseRangeStrToRA( $sRange )
/*********************************************
    Parse a string containing a potentially complicated range of numbers.
 */
{
    $raRange = array();

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
            if( /*$n1 &&*/ $n1 <= $n2 ) {       // no reason to restrict to non-zero
                for( $n = $n1; $n <= $n2; ++$n ) {
                    if( !in_array( $n, $raRange ) )  $raRange[] = $n;
                }
            }
        }
    }
    sort($raRange);

    return( $raRange );
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

    // Does this affect the caller's array?
    // No. PHP makes a late copy of arrays passed to functions. Passed by reference for efficiency, but a value-by-value copy is made if the array is changed.
    if( !$bSorted ) {
        sort($raNumbers);
    }

    $n1 = 0;
    $n2 = 0;
    foreach( $raNumbers as $n ) {
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
    Turn a normal range string into an sql condition.
    N.B. It has to be a normalized range string - use SEEDCore_ParseRangeStr() to make that if it isn't.

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


function SEEDCore_ParmsRA2URL( $raParms, $bEncode = true )
/*********************************************************
    Return an urlencoded string containing the parms in the given array
 */
{
    $s = "";
    foreach( $raParms as $k => $v ) {
        if( !empty($s) )  $s .= "&";
        if( $bEncode ) $v = urlencode($v);
        $s .= $k."=".$v;
    }
    return( $s );
}

function SEEDCore_ParmsURL2RA( $sUrlParms )
/******************************************
    Return an array containing the parms in the given urlencoded string
 */
{
    $raOut = array();
    if( !empty($sUrlParms) ) {   // the code below works properly with an empty string, but with display_errors turned on it throws a notice at the second explode
        $ra = explode( "&", $sUrlParms );
        foreach( $ra as $m ) {
            @list($k,$v) = explode( '=', $m, 2 );  // list() needs a @ because an empty string or a string
                                                   // with no '=' throws a notice that the second index doesn't exist
            if( $k )  $raOut[$k] = urldecode($v);
        }
    }
    return( $raOut );
}

function SEEDCore_ParmsURLGet( $sUrlParms, $k )
/**********************************************
    Return the named parm from the string
 */
{
    $ra = SEEDCore_ParmsURL2RA( $sUrlParms );
    return( @$ra[$k] );
}

function SEEDCore_ParmsURLAdd( $sUrlParms, $k, $v )
/**************************************************
    Return an array with a parm added or changed
 */
{
    $ra = SEEDCore_ParmsURL2RA( $sUrlParms );
    $ra[$k] = $v;
    return( SEEDCore_ParmsRA2URL( $ra ) );
}

function SEEDCore_ParmsURLRemove( $sUrlParms, $k )
/*************************************************
    Return an array with a parm removed
 */
{
    $ra = SEEDCore_ParmsURL2RA( $sUrlParms );
    if( isset($ra[$k]) )  unset($ra[$k]);
    return( SEEDCore_ParmsRA2URL( $ra ) );
}


function SEEDPRG()
/*****************
   Implement the Post, Redirect, Get paradigm for submitting forms.
   The purpose of this is to prevent the possibility of a user re-posting a form by reloading a page after a submit.

   Usage: Call this near the top of your script.
          If it returns true, process the contents of $_POST.
          Or you can ignore the return value and just check whether $_POST isn't empty.
 */
{
    $doPost = false;

    if( @$_SERVER['REQUEST_METHOD'] == 'POST' ) {
        // A form was submitted. Defer processing until the page is reloaded via 303, which causes the browser to do a GET on the given location.
        $uniqid = uniqid();
        $_SESSION['seedprg'][$uniqid] = $_POST;
        setcookie( 'seedprg', $uniqid );
        header( "Location: {$_SERVER['PHP_SELF']}", true, 303 );
        //header( "Location: {$_SERVER['PHP_SELF']}?seedprgid=$uniqid", true, 303 );
        exit;
    } else
    if( ($uniqid = @$_COOKIE['seedprg']) && isset($_SESSION['seedprg'][$uniqid]) ) {
        // A 303 was issued (by the code above) so the browser did a GET on the page.
        // Restore the deferred form parms and return true to tell the calling code to process $_POST now.
        $_POST = $_SESSION['seedprg'][$uniqid];
        $_REQUEST += $_POST;
        setcookie( 'seedprg', '', time()-3600 ); // expiration date in the past deletes the cookie from the browser (blank value works too)
        unset( $_SESSION['seedprg'] );           // might as well get rid of the whole array because there shouldn't be multiple elements

        $doPost = true;
    }

    return( $doPost );
}


function SEEDCore_HumanFilesize( $filesize, $decimals = 1 )
/**********************************************************
    Convert an integer number to a human measure in K, M, G, etc.
    From the comments in php.net manual for filesize()
 */
{
    $sz = 'BKMGTP';
    $factor = floor((strlen(strval($filesize)) - 1) / 3);
    return( sprintf("%.{$decimals}f", floatval($filesize) / pow(1024, $factor)) . @$sz[$factor] );
}

?>