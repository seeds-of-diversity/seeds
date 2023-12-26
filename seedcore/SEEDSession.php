<?php

/* SEEDSession
 *
 * Copyright 2006-2023 Seeds of Diversity Canada
 *
 * A wrapper for PHP sessions, with variable access
 */


class SEEDSessionVarAccessor
/***************************
    Get/Set variables into an existing session, using a namespace to isolate groups of variables.
 */
{
    private $sess;
    private $ns = "";   // namespace to simplify isolation of session vars

    function __construct( SEEDSession $sess, $ns = "" )
    {
        $this->sess = $sess;
        $this->SetNamespace($ns);
    }

    function SetNamespace($ns) { $this->ns = $ns; }

    function VarGet($k)        { return( (empty($this->ns) ? @$_SESSION[$k] : @$_SESSION[$this->ns][$k]) ?? "" ); }
    function VarGetEnt($k)     { return( SEEDStd_HSC($this->VarGet($k)) ); }
    function VarGetInt($k)     { return( intval($this->VarGet($k)) ); }
    function VarGetBool($k)    { return( !$this->VarEmpty($k) ); }  // false if 0, "", NULL, false, or !isset
    function VarEmpty($k)      { return( empty($this->ns) ? empty($_SESSION[$k]) : empty($_SESSION[$this->ns][$k]) ); }
    function VarIsSet($k)      { return( empty($this->ns) ? isset($_SESSION[$k]) : isset($_SESSION[$this->ns][$k]) ); }
    function VarSet($k,$v)     { if(empty($this->ns) ) { $_SESSION[$k] = $v; } else { $_SESSION[$this->ns][$k] = $v; } }
    function VarUnSet( $k )    { if(empty($this->ns) ) { unset($_SESSION[$k]); } else { unset($_SESSION[$this->ns][$k]); } }
    function VarUnSetAll()     { if(empty($this->ns) ) { unset($_SESSION); } else { unset($_SESSION[$this->ns]); } }

    function VarGetAllRA( $prefix = "" )  // if you want this one for the default namespace you'll have to implement something that checks for !is_array
    {
        if( $prefix ) {
            $ra = array();
            if( ($raSVA = @$_SESSION[$this->ns]) ) {
                foreach( $raSVA as $k => $v ) {
                    if( substr($k,0,strlen($prefix)) == $prefix ) {
                        $ra[$k] = $v;
                    }
                }
            }
            return( $ra );
        } else {
            return( @$_SESSION[$this->ns] ?: [] );
        }
    }

    function SmartGPC( $k, $raVal = array() )
    /****************************************
        $k = the name of a GPC parm
        $raVal = allowable values ([0]=default)

        Store a GPC var as a session var, and manage changes as necessary.
        When a valid GPC value is present, override the session var to that value.
        If session var is empty, and no valid GPC present, initialize session var to $raVal[0].

        $raVal:
            array()            - No restriction is placed on the value. Just update the session var when the value changes. Default ""
            array( X )         - No restriction on the value, but empty values or absent initial values default to X.
            array( X, Y, Z )   - Values restricted to the given set, default to X.

        N.B.: to simplify the detection of a change to "empty" value, such values as 0 and "" must be at $raVal[0]

        N.B. #2: don't assume $raVal is indexed using numeric keys - there might not be $raVal[0] if it's ['a=>"A",'b'=>"B"]
     */
    {
        $bDefault = false;

        $p = isset($_REQUEST[$k]) ? SEEDInput_Str($k) : $this->VarGet($k);
        if( empty($p) || (count($raVal)>1 && !in_array($p,$raVal)) ) {
            $p = count($raVal) ? reset($raVal) : "";    // reset returns the value of the first element
        }
        $this->VarSet($k,$p);
        return( $p );
    }

    function CreateChild( $sNameChild )
    /**********************************
        Create a SEEDSessionVarAccessor whose namespace is this one + sNameChild.
        e.g. if this is ns='myNS' and sNameChild='foobar' make a new SVA with ns='myNSfoobar'
     */
    {
        return( new SEEDSessionVarAccessor( $this->sess, $this->ns.$sNameChild ) );
    }
}


class SEEDSession {
/****************
 */
    private $oSVA; // the SEEDSessionVarAccessor for the default namespace

    function __construct()
    /*********************
     */
    {
        // This tells the session garbage collector to remove the session SEEDSESSION_EXPIRY_DEFAULT seconds after its last modification.
        // The default is 24 minutes (1440 seconds).  If other PHP scripts store sessions in the same directory, their garbage collectors
        // will probably use the default lifetime, and delete our sessions.  If this is a problem, the solution is to use
        // ini_set('session.save_path', $dir) to store our sessions in a different place.
        //   ini_set('session.gc_maxlifetime', SEEDSESSION_EXPIRY_DEFAULT);
        if( !session_id() ) {
            session_start();
        }

        $this->oSVA = new SEEDSessionVarAccessor( $this, "" );

        if( @$_REQUEST['SEEDSESSION_CLEAR'] == 1 ) {
            foreach( $_SESSION as $k => $v )  unset($_SESSION[$k]);
        }
    }

    // session vars stored in the top-level _SESSION namespace
    function VarGet($k)     { return( $this->oSVA->VarGet($k) ); }
    function VarGetEnt($k)  { return( $this->oSVA->VarGetEnt($k) ); }
    function VarGetInt($k)  { return( $this->oSVA->VarGetInt($k) ); }
    function VarGetBool($k) { return( $this->oSVA->VarGetBool($k) ); }
    function VarEmpty($k)   { return( $this->oSVA->VarEmpty($k) ); }
    function VarIsSet($k)   { return( $this->oSVA->VarIsSet($k) ); }
    function VarSet($k,$v)  { $this->oSVA->VarSet($k,$v); }
    function VarUnSet($k)   { $this->oSVA->VarUnSet($k); }
    function SmartGPC( $k, $raVal = array() )  { return( $this->oSVA->SmartGPC( $k, $raVal ) ); }

    function FormHidden()
    /********************
        Propagate the session_id in a <FORM> if it is not being passed by cookies.
        This works for Get and Post methods.
     */
    {
        return( (!isset($_COOKIE[session_name()]))
                    ? "<input type='hidden' name='".session_name()."' value='".session_id()."'>"
                    : "" );
    }
}

?>