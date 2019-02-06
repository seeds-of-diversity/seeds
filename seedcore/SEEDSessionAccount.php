<?php

/* SEEDSessionAccount
 *
 * Copyright 2015-2018 Seeds of Diversity Canada
 *
 * Implement a user account.
 *
 * SEEDSessionAccount determines the existence of a current login session, and/or makes one if login http parms are present, and tests permissions.
 * After construction, you can test SEEDSessionAccount to see if there is a current user, and what they are allowed to do.
 */

include_once( SEEDCORE."SEEDSession.php" );
include_once( SEEDCORE."SEEDSessionAccountDB.php" );


define( "SEEDSESSION_ERR_NOERR",               "0" );
define( "SEEDSESSION_ERR_GENERAL",             "1" );
define( "SEEDSESSION_ERR_NOSESSION",           "2" );
define( "SEEDSESSION_ERR_EXPIRED",             "3" );
define( "SEEDSESSION_ERR_UID_UNKNOWN",         "4" );
define( "SEEDSESSION_ERR_USERSTATUS_PENDING",  "5" );
define( "SEEDSESSION_ERR_USERSTATUS_INACTIVE", "6" );
define( "SEEDSESSION_ERR_WRONG_PASSWORD",      "7" );
define( "SEEDSESSION_ERR_PERM_NOT_FOUND",      "8" );
define( "SEEDSESSION_ERR_MAGIC_NOT_FOUND",     "9" );

class SEEDSessionAccount extends SEEDSession
/*******************************************
    Provides a session with persistent variable store.
    If login credentials given for a SEEDSessionAccount (optionally with permissions), open an active account session.
    If account session is already active, use it.

    $raPerms:
        - nested arrays of "modes perm"
        - if an array contains more than one element, an additional element '|' or '&' signifies the operator for them
        - if operator is not defined, '&' is assumed
        - the operator element may occur in any location in an array (first element, between, at the end)
        - an empty array always succeeds

        e.g. this permission expression:
        ( ('R A' and 'R B') or ('R C' and 'R D') )
          and
        ( ['W E' and 'R A'] or 'A F' )

        is coded like this:
        array(
          [ ['R A','R B'],          // if op not defined assume &
            '|',
            ['R C', '&', 'R D']
          ],
          '&',
          [ '|',                          // position of op relative to operands doesn't matter
            ['W E','R A'],
            ['A F']
          ]
        )

    N.B. The arrays only have values (not keys) and their elements can be re-ordered without changing the meaning of the expressions.
         This is a feature that allows sub-arrays to be marked with meaningful keys.

         e.g. A page containing three tabs can use this array to determine whether the page is accessible:
                [ 'tab1' => ['R foo'], 'tab2'=> ['W foo'], 'tab3' => ['R foo','|','R bar'], '|' ]
              and each of tab1, tab2, tab3 to determine whether each of those tabs are accessible separately.

    $raParms:
        'uid' => email or kUser
        'pwd' => password in plain text
 */
{
    const SESSION_NONE = 0;             // bLogin false : no user session active, no credentials found
    const SESSION_FOUND = 1;            // bLogin true  : a user session is already active
    const SESSION_CREATED = 2;          // bLogin true  : no user session, but valid credentials found (with the right perms)
    const SESSION_LOGIN_FAILED = 3;     // bLogin false : no user session, found credentials but they were wrong
    const SESSION_PERMS_FAILED = 4;     // bLogin false : no user session, credentials were good, but perms were wrong

    const TS_LOGOUT = 1;                // this timestamp is very far in the past, so the session is seen as expired

    public $oDB = null;
    public $oAuthDB = null; // old code refers to this, deprecate

    private $bLogin = false;
    private $eLoginState = self::SESSION_NONE;

    private $raUser = array();
    private $raMetadata = array();

    protected $kfdb;
    protected $httpNameUID = "seedsession_uid";     // the http parm that identifies the user login userid  (change if an override wants to use a different parm name)
    protected $httpNamePWD = "seedsession_pwd";     // the http parm that identifies the user login password
    protected $kSessionIdStr = "seedsession_key"; // $_SESSION[$this->kSessionIdStr] is the current session's id string used by findSession
    protected $nExpiryDefault = 7200;

    private $kfrelSess = null;
    private $kfrSession = null;

    private $logfile = "";

    public $bDebug = false; public $sDebug = "";

    function __construct( KeyframeDatabase $kfdb, $raPerms, $raParms = array() )
    /***************************************************************************
        raParms: logfile    = record UGP changes here
                 logdir     = record UGP changes in logdir/seedsessionaccount.log
                 uid        = different name for uid http parm
                 pwd        = different name for pwd http parm
     */
    {
        parent::__construct();

        $this->kfdb = $kfdb;
        $this->initKfrel(0);    // uid 0 because we don't know who we are yet and this is readonly anyway
        $this->oDB = new SEEDSessionAccountDBRead( $kfdb );
        $this->oAuth = $this->oDB;

        if( ($logfile = @$raParms['logfile']) ) {
            $this->logfile = $logfile;
        } else if( ($logdir = @$raParms['logdir']) ) {
            $this->logfile = $logdir."seedsessionaccount.log";
        }

        /* Get seedsession parms from http arrays. Then remove them so other code that copies and reissues $_REQUEST won't tell the password.
         * Using POST because it is stored in a cookie, which overrides the POST parm in _REQUEST.
         */
        $sUid = @$raParms['uid'] ?: SEEDInput_Str( $this->httpNameUID );
        $sPwd = @$raParms['pwd'] ?: SEEDInput_Str( $this->httpNamePWD );

        /* It is imperative that these be removed from the _REQUEST array, because several applications copy
         * and reissue GPC parms to subsequent pages.  This would reveal the password in client application links.
         */
        unset($_POST[$this->httpNameUID]);
        unset($_POST[$this->httpNamePWD]);
        unset($_GET[$this->httpNameUID]);
        unset($_GET[$this->httpNamePWD]);
        unset($_REQUEST[$this->httpNameUID]);
        unset($_REQUEST[$this->httpNamePWD]);

        /* First see if the user is trying to login, because they can login to override a current session (especially on a page
         * where their current user session doesn't have the required perms).
         * If no login is being attempted, look for an existing user session.
         * Otherwise, there is no active session.
         *
         * Permissions must be tested for any created or found user session.
         */
        if( $sUid && $sPwd ) {
            // The user sent login credentials. That means we destroy any current user session and start over.
            $this->LogoutSession();

            if( $this->makeSession( $sUid, $sPwd ) ) {
                $this->eLoginState = self::SESSION_CREATED;
            } else {
                $this->eLoginState = self::SESSION_LOGIN_FAILED;
                goto done;
            }
        } else if( $this->findSession() ) {
            // No login credentials, but there's an active session user session.
            $this->eLoginState = self::SESSION_FOUND;
        } else {
            goto done;
        }

        // Test permissions for any user session that was created or found.
        if( $this->_testPerms($raPerms) ) {
            $this->bLogin = true;
        } else {
            $this->eLoginState = self::SESSION_PERMS_FAILED;
        }

    done:
        if( $this->IsLogin() ) {
            // This could have been done in makeSession (which does this lookup!) or findSession, but we prefer to do it after the perms are checked above
            list($kUser,$this->raUser,$this->raMetadata) = $this->oDB->GetUserInfo( $this->kfrSession->Value('uid') );
        }
    }

    function IsLogin()       { return( $this->bLogin ); }           // true if there is an active user session
    function GetLoginState() { return( $this->eLoginState ); }      // use this to get the reason for bLogin

    function GetSessIDStr()  { return( $this->bLogin && $this->kfrSession ? $this->kfrSession->Value('sess_idstr') : "" ); }
    function GetUID()        { return( $this->bLogin && $this->kfrSession ? $this->kfrSession->Value('uid') : 0 ); }
    function GetRealname()   { return( $this->bLogin && $this->kfrSession ? $this->kfrSession->Value('realname') : "" ); }
    function GetEmail()      { return( $this->bLogin && $this->kfrSession ? $this->kfrSession->Value('email') : "" ); }
    function GetName()
    {
        $name = "";

        if( !$this->IsLogin() ) goto done;

        if( !($name = $this->GetRealname()) ) {
            if( !($name = $this->GetEmail()) ) {
                $name = "#".$this->GetUID();
            }
        }
        done:
        return( $name );
    }

    function GetHTTPNameUID() { return( $this->httpNameUID ); }
    function GetHTTPNamePWD() { return( $this->httpNamePWD ); }

    function TestPermRA( $raPerms )
    /******************************
        Return true if the current user has permissions that match the given array.

        'PUBLIC' always succeeds regardless of IsLogin(), and must appear first in a '|' list to allow anonymous login (because of short-circuit logic)
        [] is equivalent to IsLogin()
     */
    {
        return( $this->_testPerms($raPerms) );
    }

    private function _testPerms( $raPerms )
    /**************************************
        $raPerms is an array of permission operands.
        ['PUBLIC'] always succeeds and allows !IsLogin
        [] always succeeds if IsLogin
        ['R a'] is a simple test that 'a' has read permission
        ['&', 'R a', 'W b', ...] tests that all of the operands in the array pass permission test -- & can occur anywhere in the array
        ['|', 'R a', 'W b', ...] tests that any one of the operands in the array pass permission test -- | can occur anywhere in the array
        ['R a', 'W b', ...] same as if '&' were in the array
        ['&', 'R a', array(...) ] uses recursion with this method to assess the result of the array
     */
    {
        $ok = false;

        if( !is_array($raPerms) ) { var_dump($raPerms); die( "Perms must be array" ); }

        // An empty array always succeeds if logged in. Since this is called in the constructor before IsLogin is set, check eLoginState.
        if( (!$raPerms || count($raPerms)==0) ) {
            $ok = $this->IsLogin() || in_array($this->eLoginState, [self::SESSION_CREATED, self::SESSION_FOUND]);
            goto done;
        }

        // find the operator in this array (use & if not defined)
        $bAnd = !in_array( '|', $raPerms );
        $ok = $bAnd ? true : false;         // keep checking until this value changes (then short-circuit)

        // evaluate each array member and apply the operator
        foreach( $raPerms as $v ) {
            if( $v == '&' || $v == '|' ) continue;  // skip the operator

            if( is_array($v) ) {
                // This is a nested array. Evaluate with this method recursively.
                $x = $this->_testPerms( $v );
            } else if( $v == 'PUBLIC' ) {
                // This is a special perm that always succeeds regardless of IsLogin()
                $x = true;
            } else {
                // Regular perm string. Only returns true if IsLogin()
                // "mode perm" e.g. "RW foobar", "A foobar", "WA foobar"
                list($mode,$perm) = explode( ' ', $v, 2 );
                $x = $this->TestPerm( $perm, $mode );
            }
            if( $bAnd ) {
                if( !($ok = $ok && $x) )  goto done;
            } else {
                if( ($ok = $ok || $x) )  goto done;
            }
        }
        //var_dump($raPerms,$ok);

        done:
        return( $ok );
    }

    function TestPerm( $perm, $mode )
    /********************************
        Return true if the given perm is available in the column 'perms$mode'. i.e. $mode = R, W, A
     */
    {
        $ok = false;

        if( !$this->IsLogin() &&   // this is probably redundant with $this->kfrSession==null, but it's critical to be clear here
            $this->kfrSession )
        {
            $ok = (strpos($this->kfrSession->value("perms$mode"), " $perm ") !== false);  // NB !== because 0 means first position
        }

        return( $ok );
    }

    function CanRead( $perm )   { return( $this->TestPerm( $perm, "R" ) ); }
    function CanWrite( $perm )  { return( $this->TestPerm( $perm, "W" ) ); }
    function CanAdmin( $perm )  { return( $this->TestPerm( $perm, "A" ) ); }

    function IsAllowed( $p )
    /***********************
        $p is a screen name, command name, operation name, or other permission-formatted string as below

        Permission is defined by the format of the name

        foo-bar      : if Read  permission on "foo" perm, allow bar
        foo--bar     : if Write permission on "foo" perm, allow bar
        foo---bar    : if Admin permission on "foo" perm, allow bar

        Commands with no hyphens are available to everyone.

        return:
        bOk  = true if the current user is allowed to use the screen/command
        suff = the suffix of screen/command name after the hyphens (if any)
        sErr = the reason why bOk is false
     */
    {
        $bOk = false;
        $suff = "";
        $sErr = "";

        if( strpos( $p, "---" ) !== false ) {
            list($perm,$suff) = explode( "---", $p, 2 );
            if( !$perm || !$suff || !$this->CanAdmin( $perm ) ) {
                $sErr = "Requires admin permission";
                goto done;
            }
        } else
        if( strpos( $p, "--" ) ) {
            list($perm,$suff) = explode( "--", $p, 2 );
            if( !$perm || !$suff || !$this->CanWrite( $perm ) ) {
                $sErr = "Requires write permission";
                goto done;
            }
        } else
        if( strpos( $p, "-" ) !== false ) {
            list($perm,$suff) = explode( "-", $p, 2 );
            if( !$perm || !$suff || !$this->CanRead( $perm ) ) {
                $sErr = "Requires read permission";
                goto done;
            }
        } else {
            // anyone can use this command
            $suff = $p;
        }

        $bOk = true;

        done:
        return( array($bOk, $suff, $sErr) );
    }



    function LogoutSession()
    /***********************
        This class can create sessions; it can also destroy them.
     */
    {
        $ok = false;

        if( $this->IsLogin() && $this->kfrSession ) {
            $this->kfrSession->SetValue( 'ts_expiry', self::TS_LOGOUT );      // this is very far in the past so the session is seen as expired
            $ok = $this->kfrSession->PutDBRow();
            $this->kfrSession = null;
        }
        $this->bLogin = false;
        $this->eLoginState = self::SESSION_NONE;

        return( $ok );
    }

    function LoginAsUser( $uid )
    /***************************
        In rare instances, you want to login as a particular user. e.g. after creating a new account.
        Only use this if you've already ensured authentication.
     */
    {
        $this->LogoutSession();

        if( $this->makeSession( $uid, "", true ) ) {
            $this->eLoginState = self::SESSION_CREATED;
            $this->bLogin = true;
            list($kUser,$this->raUser,$this->raMetadata) = $this->oDB->GetUserInfo( $this->kfrSession->Value('uid') );
        } else {
            $this->eLoginState = self::SESSION_LOGIN_FAILED;
        }
        return( $this->bLogin );
    }

    function GetNonSessionHttpParms()
    /********************************
        Get all http parms that are not part of the session control.
        i.e. these can be propagated safely without revealing or messing up the session.
     */
    {
        $ra = array();
        foreach( $_GET as $k => $v ) {
            if( $k != $this->httpNameUID && $k != $this->httpNamePWD ) {
                $ra[$k] = $v;
            }
        }
        foreach( $_POST as $k => $v ) {
            if( $k != $this->httpNameUID && $k != $this->httpNamePWD ) {
                $ra[$k] = $v;
            }
        }
        return( $ra );
    }

    private function findSession()
    {
        $sid = 0;    // this could be an arg so someone could make us find a specific session
        $uid = 0;    // this could be an arg so someone could make us find a session for a specific user
                     //   (bad idea because you can login multiple times as the same user, at least from different machines)

        $sess_idstr = $this->VarGet($this->kSessionIdStr);      // makeSession put this in $_SESSION on the last successful login

        $ok = false;

        if( $sid ) {
            $this->kfrSession = $this->kfrelSess->GetRecordFromDBKey( $sid );

        } else if( $uid ) {
            $this->kfrSession = $this->kfrelSess->GetRecordFromDB( "uid='".intval($uid)."'" );

        } else if( $sess_idstr ) {
            $this->kfrSession = $this->kfrelSess->GetRecordFromDB( "sess_idstr='".addslashes($sess_idstr)."'" );
        }

        if( !$this->kfrSession ) {
            if( $this->bDebug ) { $this->sDebug = "NOSESSION $sess_idstr<br/>"; }

        /* Has the session expired?
         */
        } else if( ($ts = intval($this->kfrSession->Value('ts_expiry'))) == self::TS_LOGOUT ) {
            // The last session was logged out.
            if( $this->bDebug ) { $this->sDebug = "LOGGED OUT"; }
            $this->kfrSession = NULL;

        } else if( $ts < time() ) {
            if( $this->bDebug ) { $this->sDebug = "EXPIRED<br/>$ts<br/>".time()."<br/>"; }
            $this->kfrSession = NULL;

/*
            if( time() - $ts < 3600 ) {
                // if the session expired less than an hour ago, note that it expired
                $this->error = SEEDSESSION_ERR_EXPIRED;
            } else {
                // otherwise, don't bother the user with an expiry message because it could be a long time ago (days or weeks) and that would seem weird
                $this->error = SEEDSESSION_ERR_NOSESSION;
            }
*/
        }

        $ok = $this->kfrSession != NULL;

        return( $ok );
    }

    private function makeSession( $sUid, $sPwd, $bNoPassword = false )
    {
        $ok = false;

        // sUid can be a user key or email
        list($kUser,$raUser,$raMetadata) = $this->oDB->GetUserInfo( $sUid );
        if( $kUser &&
            (@$raUser['password'] == $sPwd || $bNoPassword) &&
            @$raUser['eStatus'] == 'ACTIVE' )
        {
            // Create a session record
            $ok = $this->makeSessionRecord( $raUser['_key'], $raUser['realname'], $raUser['email'] );
        }
        if( $ok ) {
            // save the session id string in $_SESSION so findSession() can find this user session again
            $this->VarSet( $this->kSessionIdStr, $this->kfrSession->Value('sess_idstr') );
        }

        return( $ok );
    }

    private function makeSessionRecord( $kUser, $realname, $email )
    {
        $raSessParms = array(); // this could be an arg allowing MagicLogin to push perms and ts_expiry into this session

        $sess_idstr = SEEDCore_UniqueId();

        $this->kfrSession = $this->kfrelSess->CreateRecord();
        $this->kfrSession->SetValue( "sess_idstr", $sess_idstr );
        $this->kfrSession->SetValue( "uid",        $kUser );
        $this->kfrSession->SetValue( "realname",   $realname );
        $this->kfrSession->SetValue( "email",      $email );

        if( empty($raSessParms["permsR"]) && empty($raSessParms["permsW"]) && empty($raSessParms["permsA"]) ) {
//TODO: use SEEDSessionAuthDB if the same format is useful elsewhere like the UGP admin
            $permsR = $permsW = $permsA = " ";

            $dbcPerms = $this->kfdb->CursorOpen(
                                // Get perms explicitly set for this uid
                                "SELECT perm,modes FROM SEEDSession_Perms WHERE _status='0' AND uid='$kUser' "
                               ."UNION "
                                // Get perms associated with the user's primary group
                               ."SELECT P.perm AS perm, P.modes as modes "
                                   ."FROM SEEDSession_Perms P, SEEDSession_Users U "
                                   ."WHERE P._status='0' AND U._status='0' AND "
                                   ."U._key='$kUser' AND U.gid1 >=1 AND P.gid=U.gid1 "
                               ."UNION "
                                // Get perms from groups
                               ."SELECT P.perm AS perm, P.modes as modes "
                                   ."FROM SEEDSession_Perms P, SEEDSession_UsersXGroups GU "
                                   ."WHERE P._status=0 AND GU._status=0 AND "
                                   ."GU.uid='$kUser' AND GU.gid >=1 AND GU.gid=P.gid" );
            while( $ra = $this->kfdb->CursorFetch( $dbcPerms ) ) {
                if( strchr($ra['modes'],'R') && !strstr($permsR, " ".$ra['perm']." ") )  $permsR .= $ra['perm']." ";
                if( strchr($ra['modes'],'W') && !strstr($permsW, " ".$ra['perm']." ") )  $permsW .= $ra['perm']." ";
                if( strchr($ra['modes'],'A') && !strstr($permsA, " ".$ra['perm']." ") )  $permsA .= $ra['perm']." ";
            }
            $this->kfdb->CursorClose( $dbcPerms );

            $this->kfrSession->SetValue( "permsR", $permsR );
            $this->kfrSession->SetValue( "permsW", $permsW );
            $this->kfrSession->SetValue( "permsA", $permsA );

        } else {
            $this->kfrSession->SetValue( "permsR", @$raSessParms["permsR"] );
            $this->kfrSession->SetValue( "permsW", @$raSessParms["permsW"] );
            $this->kfrSession->SetValue( "permsA", @$raSessParms["permsA"] );
        }

        $this->kfrSession->SetValue( "ts_expiry", time() + (!empty($raSessParms["ts_expiry"]) ? $raSessParms["ts_expiry"]
                                                                                              : $this->nExpiryDefault) );

        return( $this->kfrSession->PutDBRow() );
    }

    private function initKfrel( $uid )
    {
        $def = array( "Tables" => array(
                "S" => array( "Table" => 'SEEDSession',
                              "Fields" => array( array("col"=>"sess_idstr", "type"=>"S"),
                                                 array("col"=>"uid",        "type"=>"I"),
                                                 array("col"=>"realname",   "type"=>"S"),
                                                 array("col"=>"email",      "type"=>"S"),
                                                 array("col"=>"permsR",     "type"=>"S"),
                                                 array("col"=>"permsW",     "type"=>"S"),
                                                 array("col"=>"permsA",     "type"=>"S"),
                                                 array("col"=>"ts_expiry",  "type"=>"I")
                ) ) ) );

        // this logfile is not very interesting because it just records the SEEDSession entries
        $this->kfrelSess = new KeyFrame_Relation( $this->kfdb, $def, $uid,
                                                  $this->logfile ? array( 'logfile' => $this->logfile ) : array() );
    }
}

?>