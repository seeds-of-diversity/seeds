<?php

/* SEEDSessionAccount
 *
 * Copyright 2015-2023 Seeds of Diversity Canada
 *
 * Implement a user account.
 *
 * SEEDSessionAccount determines the existence of a current login session, and/or makes one if login http parms are present, and tests permissions.
 * After construction, you can test SEEDSessionAccount to see if there is a current user, and what they are allowed to do.
 */

include_once( SEEDCORE."SEEDSession.php" );
include_once( SEEDCORE."SEEDSessionAccountDB.php" );


define( 'SEEDSESSION_ERR_NOERR',               0 );
define( 'SEEDSESSION_ERR_GENERAL',             1 );
define( 'SEEDSESSION_ERR_NOSESSION',           2 );
define( 'SEEDSESSION_ERR_EXPIRED',             3 );
define( 'SEEDSESSION_ERR_UID_UNKNOWN',         4 );
define( 'SEEDSESSION_ERR_USERSTATUS_PENDING',  5 );
define( 'SEEDSESSION_ERR_USERSTATUS_INACTIVE', 6 );
define( 'SEEDSESSION_ERR_WRONG_PASSWORD',      7 );
define( 'SEEDSESSION_ERR_PERM_NOT_FOUND',      8 );
define( 'SEEDSESSION_ERR_MAGIC_NOT_FOUND',     9 );
define( 'SEEDSESSION_ERR_MAGIC_INVALID',      10 );
define( 'SEEDSESSION_ERR_MAGIC_EXPIRED',      11 );

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

    /**
     * Database object for accessing user info
     * @var SEEDSessionAccountDBRead2
     */
    private $oDB = null;

    private $bLogin = false;
    private $eLoginState = self::SESSION_NONE;

    /**
     * The real user id of the currently logged in user.
     * @var int
     */
    private $rUID = 0;
    /**
     * The effective user id of the "logged in" user.
     * Used to determine what permissions the user has.
     * In most cases this will be the same as {@link SEEDSessionAccount::$rUID}, unless the user has logged in as a different user.
     * @see SEEDSessionAccount::LoginAsUser($uid)
     * @var int
     */
    private $eUID = 0;

    protected $kfdb;
    protected $httpNameUID = "seedsession_uid";     // the http parm that identifies the user login userid  (change if an override wants to use a different parm name)
    protected $httpNamePWD = "seedsession_pwd";     // the http parm that identifies the user login password
    protected $httpNameML  = "seedsession_ml";      // the http parm that identifies a magic login id
    protected $kRealUID = "seedsession_ruid";       // $_SESSION[$this->kRealUID] is the current session's real user id used by findSession
    protected $kEffectiveUID = "seedsession_euid";  // $_SESSION[$this->kEffectiveUID] is the current session's effective user id used by findSession

    public $bDebug = false;
    public $sDebug = "";

    function __construct( KeyframeDatabase $kfdb, array $raPerms, array $raParms = array() )
    /***************************************************************************
        raParms: uid        = different name for uid http parm
                 pwd        = different name for pwd http parm
                 ml         = different name for magic http parm
     */
    {
        parent::__construct();

        $this->kfdb = $kfdb;

        $this->oDB = new SEEDSessionAccountDBRead2( $kfdb, 0 ); // uid 0 because we don't know who we are yet and this is readonly anyway

        /* Get seedsession parms from http arrays. Then remove them so other code that copies and reissues $_REQUEST won't tell the password.
         * Using POST because it is stored in a cookie, which overrides the POST parm in _REQUEST.
         */
        $sUid = @$raParms['uid'] ?: SEEDInput_Str( $this->httpNameUID );
        $sPwd = @$raParms['pwd'] ?: SEEDInput_Str( $this->httpNamePWD );
        $sMagicLink = @$raParms['ml'] ?: SEEDInput_Str( $this->httpNameML );

        /* It is imperative that these be removed from the _REQUEST array, because several applications copy
         * and reissue GPC parms to subsequent pages.  This would reveal the password in client application links.
         */
        unset($_POST[$this->httpNameUID]);
        unset($_POST[$this->httpNamePWD]);
        unset($_POST[$this->httpNameML]);
        unset($_GET[$this->httpNameUID]);
        unset($_GET[$this->httpNamePWD]);
        unset($_GET[$this->httpNameML]);
        unset($_REQUEST[$this->httpNameUID]);
        unset($_REQUEST[$this->httpNamePWD]);
        unset($_REQUEST[$this->httpNameML]);

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
        } else if( $sMagicLink ) {
            // The user sent a magic login link. That means we destroy any current user session and start over.
            $this->LogoutSession();

            if( $this->makeMagicSession($sMagicLink) ) {
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
        // Assume bLogin during _testPerms because it will fail otherwise. It is called by public TestPerms() so it must check.
        $this->bLogin = true;
        if( !$this->_testPerms($raPerms) ) {
            $this->eLoginState = self::SESSION_PERMS_FAILED;
            $this->bLogin = false;
        }

    done:
    }

    /**
     * Return whether there's a current session or not
     * @return bool
     */
    function IsLogin(): bool       { return( $this->bLogin ); }

    /**
     * Get the current login state
     * @return int
     */
    function GetLoginState(): int { return( $this->eLoginState ); }      // use this to get the reason for bLogin

    /**
     * Return the user id for the logged in user.
     * Returns the users effective user id by default.
     * @param bool $useRUID - whether to return the real user id instead.
     * @return int
     */
    function GetUID(bool $useRUID = false): int {
        if ($this->bLogin) {
            if ($useRUID) {
                return $this->rUID;
            }
            return $this->eUID;
        }
        return 0;
    }

    /**
     * Return the real name of the logged in user.
     * Returns the name of effective user by default.
     * @param bool $useRUID - whether to return the name of the real user instead.
     * @return string
     */
    function GetRealname(bool $useRUID = false): string {
        if ($this->bLogin) {
            if ($useRUID) {
                $user = $this->oDB->GetUserInfo($this->rUID, false, true)[1];
                return $user['realname'];
            }
            $user = $this->oDB->GetUserInfo($this->eUID, false, true)[1];
            return $user['realname'];
        }
        return '';
    }

    /**
     * Return the email of the logged in user.
     * Returns the email of the effective user by default.
     * @param bool $useRUID - whether to return the email of the real user instead.
     * @return string
     */
    function GetEmail(bool $useRUID = false): string {
        if ($this->bLogin) {
            if ($useRUID) {
                $user = $this->oDB->GetEmail($this->rUID);
                return $user['email'];
            }
            $user = $this->oDB->GetEmail($this->eUID);
            return $user['email'];
        }
        return '';
    }

    /**
     * Compute and return the name of the logged in user.
     * Falls back to email if the user does not have a real name set.
     * Falls back to uid if the user does not have a real name or email set.
     * Returns the name of the effective user by default.
     * @param bool $useRUID - whether to return the name of the real user instead.
     * @return string
     */
    function GetName(bool $useRUID = false): string {
        if($this->IsLogin()) {
            $name = $this->GetRealname($useRUID);
            if(!$name) {
                if(!($name = $this->GetEmail($useRUID))) {
                    $name = "#".$this->GetUID($useRUID);
                }
            }
            return $name;
        }
        return '';
    }

    /**
     * Check if the user has logged in as another user.
     * A user is considered to be logged in as another user if their current real and effective id's don't match
     * @return bool - true if the user's effective id doesn't match their real id
     */
    function IsLoggedInAsAnotherUser(): bool {
        return $this->rUID !== $this->eUID;
    }

    /**
     * Get the name of the username HTTP parameter
     * @return string
     */
    function GetHTTPNameUID(): string { return( $this->httpNameUID ); }

    /**
     * Get the name of the password HTTP parameter
     * @return string
     */
    function GetHTTPNamePWD(): string { return( $this->httpNamePWD ); }

    /**
     * Return true if the current user has permissions that match the given array.
     * Checks against effective permissions by default.
     * Recursively processes nested arrays.
     * 'PUBLIC' always succeeds regardless of IsLogin(), and must appear first in a '|' list to allow anonymous login (because of short-circuit logic)
     * @param string[] | string[][] $raPerms - array of permission strings to test
     * @param bool $useRUID - whether to check against the real users permissions instead
     * @return true if the user has matching permissions, false otherwise
     * @example TestPermRA([]) is equivalent to IsLogin()
     * @example TestPermRA(['PUBLIC']) - always succeeds
     * @example TestPermRA(['R a']) - tests that the effective user has the "a" read permission
     * @example TestPermRA(['&', 'R a', 'W b']) - tests that the effective user has both "a" read permission, AND "b" write permission.
     * Equivalent to TestPermRA(['R a', '&', 'W b'])
     * @example TestPermRA(['|', 'R a', 'W b']) - tests that the effective user has either "a" read permission, OR "b" write permission.
     * Equivalent to TestPermRA(['R a', '|', 'W b'])
     * @example TestPermRA(['R a', 'W b']) - tests that the effective user has both "a" read permission, AND "b" write permission.
     * Equivalent to TestPermRA(['R a', '&', 'W b'])
     * @example TestPermRA(['R a', '&', ['W b', '|', 'A c']]) - tests that effective the user has both "a" read permission, AND either "b" write permission OR "c" admin permission
     * @example TestPermRA('R a'], true) - tests that the real user has "a" read permission
     */
    function TestPermRA( array $raPerms, bool $useRUID = false ): bool {
        return( $this->_testPerms($raPerms, $useRUID) );
    }

    /**
     * Return true if the current user has permissions that match the given array.
     * Checks against effective permissions by default.
     * Recursively processes nested arrays.
     * Called internally by {@link SEEDSessionAccount::TestPermRA()}
     * 'PUBLIC' always succeeds regardless of IsLogin(), and must appear first in a '|' list to allow anonymous login (because of short-circuit logic)
     * @param string[] | string[][] $raPerms - array of permission strings to test
     * @param bool $useRUID - whether to check against the real users permissions instead
     * @return true if the user has matching permissions, false otherwise
     * @see SEEDSessionAccount::TestPermRA($raPerms)
     * @example _testPerms([]) is equivalent to IsLogin()
     * @example _testPerms(['PUBLIC']) - always succeeds
     * @example _testPerms(['R a']) - tests that the effective user has the "a" read permission
     * @example _testPerms(['&', 'R a', 'W b']) - tests that the effective user has both "a" read permission, AND "b" write permission.
     * Equivalent to _testPerms(['R a', '&', 'W b'])
     * @example _testPerms(['|', 'R a', 'W b']) - tests that the effective user has either "a" read permission, OR "b" write permission.
     * Equivalent to _testPerms(['R a', '|', 'W b'])
     * @example _testPerms(['R a', 'W b']) - tests that the effective user has both "a" read permission, AND "b" write permission.
     * Equivalent to _testPerms(['R a', '&', 'W b'])
     * @example _testPerms(['R a', '&', ['W b', '|', 'A c']]) - tests that effective the user has both "a" read permission, AND either "b" write permission OR "c" admin permission
     * @example _testPerms('R a'], true) - tests that the real user has "a" read permission
     */
    private function _testPerms( array $raPerms, bool $useRUID = false ): bool {
        $ok = false;

        // An empty array always succeeds if logged in. Since this is called in the constructor before IsLogin is set, check eLoginState.
        if( (!$raPerms || count($raPerms)==0) ) {
            $ok = $this->IsLogin();
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
                $x = $this->_testPerms( $v, $useRUID );
            } else if( $v == 'PUBLIC' ) {
                // This is a special perm that always succeeds regardless of IsLogin()
                $x = true;
            } else {
                // Regular perm string. Only returns true if IsLogin()
                // "mode perm" e.g. "RW foobar", "A foobar", "WA foobar"
                list($mode,$perm) = explode( ' ', $v, 2 );
                $x = $this->TestPerm( $perm, $mode, $useRUID );
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

    /**
     * Get if the current user has the expected permission.
     * Checks against the effective users permissions by default.
     * Called internally by {@link SEEDSessionAccount::_testPerms()}, {@link SEEDSessionAccount::CanRead()},
     * {@link SEEDSessionAccount::CanWrite()}, and {@link SEEDSessionAccount::CanAdmin()}
     * @param string $perm - the permission to test for
     * @param string $mode - the level of permission required. "R" for read permission, "W" for write permission, and "A" for admin permission
     * @param bool $useRUID - whether to check against the real users permissions instead
     * @return bool - true if the user has the permission, false otherwise
     * @see SEEDSessionAccount::_testPerms($raPerms)
     * @see SEEDSessionAccount::CanRead($perm)
     * @see SEEDSessionAccount::CanWrite($perm)
     * @see SEEDSessionAccount::CanAdmin($perm)
     * @example TestPerm('test', 'R') - tests if the effective user has the test read permission
     * @example TestPerm('test', 'W') - tests if the effective user has the test write permission
     * @example TestPerm('test', 'A') - tests if the effective user has the test admin permission
     * @example TestPerm('test', 'R', true) - tests if the real user has the test read permission
     * @example TestPerm('test', 'W', true) - tests if the real user has the test write permission
     * @example TestPerm('test', 'A', true) - tests if the real user has the test admin permission
     */
    function TestPerm( string $perm, string $mode, bool $useRUID = false ): bool
    /********************************
        Return true if the given perm is available in the column 'perms$mode'. i.e. $mode = R, W, A
     */
    {
        $ok = false;

        if( $this->IsLogin() && $this->GetUID($useRUID))
        {
            $raPerms = $this->oDB->GetPermsFromUser($this->GetUID($useRUID))['mode2perms'][$mode] ?? [];

            $ok = in_array($perm, $raPerms);
        }
        return( $ok );
    }

    /**
     * Get if the user has the given read permission.
     * Checks against the effective users permissions by default.
     * @param string $perm - permission to check
     * @param bool $useRUID - whether to check against the real users permissions instead
     * @return bool true if the user has the read permission, false otherwise
     */
    function CanRead( string $perm, bool $useRUID = false ): bool   { return( $this->TestPerm( $perm, "R", $useRUID ) ); }

    /**
     * Get if the user has the given write permission
     * Checks against the effective users permissions by default.
     * @param string $perm - permission to check
     * @param bool $useRUID - whether to check against the real users permissions instead
     * @return bool true if the user has the write permission, false otherwise
     */
    function CanWrite( string $perm, bool $useRUID = false ): bool  { return( $this->TestPerm( $perm, "W", $useRUID ) ); }

    /**
     * Get if the user has the given admin permission.
     * Checks against the effective users permissions by default.
     * @param string $perm - permission to check
     * @param bool $useRUID - whether to check against the real users permissions instead
     * @return bool true if the user has the admin permission, false otherwise
     */
    function CanAdmin( string $perm, bool $useRUID = false ): bool  { return( $this->TestPerm( $perm, "A", $useRUID ) ); }

    /**
     * Get whether the users permissions would allow for the given permission-formatted string to be used.
     * Checks against the effective users permissions by default.
     * @param string $p - the permission-formatted string to check
     * @param bool $useRUID - whether to check against the real users permissions instead
     * @return array{bool,string,string} Whether the user is allowed to perform the action,
     * followed by the part of the string following the hyphens (if any), followed by the reason the action is not allowed (if any)
     * @example IsAllowed('foo-bar') - bar allowed if the effective user has "foo" read permission
     * @example IsAllowed('foo--bar') - bar allowed if the effective user has "foo" write permission
     * @example IsAllowed('foo---bar') - bar allowed if the effective user has "foo" admin permission
     * @example IsAllowed('bar') - bar always allowed
     * @example IsAllowed('foo-bar', true) - bar allowed if the real user has "foo" read permission
     * @example IsAllowed('foo--bar', true) - bar allowed if the real user has "foo" write permission
     * @example IsAllowed('foo---bar', true) - bar allowed if the real user has "foo" admin permission
     * @see SEEDSessionAccount::CheckPerms($cmd, $ePerm, $sPermLabel)
     */
    function IsAllowed( string $p, bool $useRUID = false ): array {
        $bOk = false;
        $suff = "";
        $sErr = "";

        $p = $p ?? "";

        if( strpos( $p, "---" ) !== false ) {
            list($perm,$suff) = explode( "---", $p, 2 );
            if( !$perm || !$suff || !$this->CanAdmin( $perm, $useRUID ) ) {
                $sErr = "Requires admin permission";
                goto done;
            }
        } else
        if( strpos( $p, "--" ) !== false ) {
            list($perm,$suff) = explode( "--", $p, 2 );
            if( !$perm || !$suff || !$this->CanWrite( $perm, $useRUID ) ) {
                $sErr = "Requires write permission";
                goto done;
            }
        } else
        if( strpos( $p, "-" ) !== false ) {
            list($perm,$suff) = explode( "-", $p, 2 );
            if( !$perm || !$suff || !$this->CanRead( $perm, $useRUID ) ) {
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

    /**
     * Get whether the user has the required permissions to perform the given command.
     * Similar to {@link SEEDSessionAccount::IsAllowed} but more flexible since the permission doesn't need to be encoded in the command.
     * Checks against the effective users permissions by default.
     * NOTE: Any command might check further permissions to allow or deny access.
     * @param string $cmd - the command to check permissions for
     * @param string $ePerm - the permission that should be tested for. The level of the permission required is determined by the command
     * @param string $sPermLabel - label for the permission to use in the error message
     * @param bool $useRUID - whether to check against the real users permissions instead
     * @return array{bool,string} Whether the user is allowed to perform the command,
     * followed by the reason the action is not allowed (if any)
     * @see SEEDSessionAccount::IsAllowed($p)
     * @example CheckPerms('foo-bar', 'test', 'test') - allowed if the effective user has the "test" read permission
     * @example CheckPerms('foo--bar', 'test', 'test') - allowed if the effective user has the "test" write permission
     * @example CheckPerms('foo---bar', 'test', 'test') - allowed if the effective user has the "test" admin permission
     * @example CheckPerms('foo-bar', 'test', 'test', true) - allowed if the real user has the "test" read permission
     * @example CheckPerms('foo--bar', 'test', 'test', true) - allowed if the real user has the "test" write permission
     * @example CheckPerms('foo---bar', 'test', 'test', true) - allowed if the real user has the "test" admin permission
     */
    function CheckPerms( string $cmd, string $ePerm, string $sPermLabel, bool $useRUID = false ): array {
        $bAccess = false;
        $sErr = "";

        $cmd = $cmd ?? "";

        if( strpos( $cmd, "---" ) !== false ) {
            if( !($bAccess = $this->TestPerm( $ePerm, 'A', $useRUID )) ) {
                $sErr = "Command requires $sPermLabel admin permission";
            }
        } else
        if( strpos( $cmd, "--" ) !== false ) {
            if( !($bAccess = $this->TestPerm( $ePerm, 'W', $useRUID )) ) {
                $sErr = "Command requires $sPermLabel write permission";
            }
        } else
        if( strpos( $cmd, "-" ) !== false ) {
            if( !($bAccess = $this->TestPerm( $ePerm, 'R', $useRUID )) ) {
                $sErr = "Command requires $sPermLabel read permission";
            }
        }

        return( [$bAccess, $sErr] );
    }


    /**
     * Log a user out of a session.
     * By default, if a user has logged in as another user using {@link SEEDSessionAccount::LoginAsUser} this will return them to their actual user
     * instead of logging them out.
     * @param bool $fullLogout - Whether to log the user out completely instead
     * @see SEEDSessionAccount::LoginAsUser($uid)
     */
    function LogoutSession( bool $fullLogout = false ) {

        if( $this->IsLogin()) {
            if( !$fullLogout && $this->rUID !== $this->eUID ) {
                // The user has switched to another user. Return them to their user instead of logging them out.
                $this->eUID = $this->rUID;
                $this->VarSet($this->kEffectiveUID, $this->rUID);
            } else {
                /* Prevent session hijacking (by fixation) by not leaving the same session open for another user at the logout screen on a public computer.
                 * Although the measures below might not leave that session useful anyway.
                 */
                setcookie(session_name(),'',0,'/');
                session_regenerate_id(true);

                /* Destroy session variables here. Otherwise someone else logging in on the same session will get the first user's variables.
                 * This logout happens when a login is done in the middle of a session, e.g. for a page inaccessible by the first login.
                 */
                session_unset();
                session_destroy();
                session_write_close();
            }
        }
        $this->bLogin = false;
        $this->eLoginState = self::SESSION_NONE;
    }

    /**
     * Temporarily log in as another user.
     * This sets the effective user id to the given id as long as there's a logged in session.
     * The existing session is NOT destroyed.
     * @param int $uid - id of the user to temporarily log in as
     * @return bool - true if the login was successful, false otherwise
     */
    function LoginAsUser(int $uid): bool {
        if ($this->IsLogin() && $this->rUID) {
            // Could add a perms check to prevent unauthorized users from changing their permissions
            if ($this->oDB->GetEmail($uid)) {
                // Set the effective user id to the provided id.
                // N.B. This could be used to log into inactive or pending user
                $this->eUID = $uid;
                $this->VarSet($this->kEffectiveUID, $uid);
                return true;
            }
        }
        // Optionally could support logging into freshly created accounts (eg. accounts that were created within the last 5 mins)
        // This could allow for seemless account creation & login for new users if desired, while limiting annonymous access to any account
        return false;
    }

    /**
     * Get all http params that are not part of the session control.
     * i.e. these can be propagated safely without revealing or messing up the session.
     * @return unknown[]
     */
    function GetNonSessionHttpParms(): array {
        $ra = array();
        foreach( $_GET as $k => $v ) {
            if( !in_array($k, [$this->httpNameUID,$this->httpNamePWD,$this->httpNameML]) ) {
                $ra[$k] = $v;
            }
        }
        foreach( $_POST as $k => $v ) {
            if( !in_array($k, [$this->httpNameUID,$this->httpNamePWD,$this->httpNameML]) ) {
                $ra[$k] = $v;
            }
        }
        return( $ra );
    }

    /**
     * Attempt to load the real and effective user id's from the PHP session.
     * The effective user id will default to the real user id if it's not separately set
     * @return boolean true if at least the real user id was loaded from the session, false otherwise
     */
    private function findSession(): bool {
        $this->rUID = $this->VarGetInt($this->kRealUID);
        $this->eUID = $this->VarIsSet($this->kEffectiveUID) ? $this->VarGetInt($this->kEffectiveUID) : $this->VarGetInt($this->kRealUID);
        return boolval($this->rUID);
    }

    /**
     * Attempt to login using a user's id or email, as well as their password.
     * This will handle upgrading the password if necessary on successful login.
     * @param string|int $userIdOrEmail - id or email of the user to login as
     * @param boolean $sPwd - the password to compare against the stored password for the user
     * @return boolean - true if the login was successful, false otherwise
     */
    private function makeSession( string|int $userIdOrEmail, string $sPwd ): bool {
        $ok = false;

        list($kUser,$raUser,$raMetadata) = $this->oDB->GetUserInfo( $userIdOrEmail );

        if ($kUser && @$raUser['eStatus'] == 'ACTIVE') {
            if (@$raUser['password'] && password_verify($sPwd, $raUser['password'])) {
                $ok = true;
                // Hashed passwords match
                // Initialize the session
                $this->makeSessionRecord($raUser['_key']);
            } else if(@$raUser['password'] && !password_get_info($raUser['password'])['algo'] && $raUser['password'] == $sPwd) {
                $ok = true;
                // Password doesn't appear to be hashed, fallback to old check and upgrade
                // Initialize the session
                $this->makeSessionRecord($raUser['_key']);
            }
            if( $ok ) {
                if (password_needs_rehash($raUser['password'], PASSWORD_BCRYPT)) {
                    // We successfully logged in using a password, and it needs to be rehashed/upgraded
                    $oDB = new SEEDSessionAccountDB2($this->kfdb, $raUser['_key']);
                    // Change the user's password to upgrade it
                    $oDB->ChangeUserPassword($raUser['_key'], $sPwd);
                }
            }
        }

        return( $ok );
    }

    /**
     * Initialize the session for a given user.
     * Sets both the real user id and the effective user id
     * @param int $kUser - id to initialize the session for
     */
    private function makeSessionRecord( int $kUser ) {
        $this->rUID = $kUser;
        $this->eUID = $kUser;

        $this->VarSet($this->kRealUID, $kUser);
        $this->VarSet($this->kEffectiveUID, $kUser);
    }

    private function makeMagicSession( $sMagicLink )
    /***********************************************
        This class was constructed with a seedsession_ml parm in the http.
        That means someone clicked on a link that was coded for Magic Login.
        The page is currently being processed, any current session was destroyed before calling here,
        and if we can authenticate this magic link, the user will be logged in according to the magic login record.

        Magic logins can specify perms: blank means to provide all perms for the given user.
                                 tsExpiry: only allow login until that time
                                 nLimit: only allow login this many times (-1 means unlimited)

        Magic Links can look like:
            A{kMagicLogin}A{magic_str}      Look up the magic record. If magic_str matches, login the uid in the record.
            B{kMagicLogin}B{uid}B{hash}     Look up the magic record. If hash matches the hash of uid+magic_str, login the uid in the link.
     */
    {
        $ok = false;

        list($bOk,$kUid,$sPerms,$errcode) = SEEDSessionAccount_MagicLogin::ValidateMagicLoginLink($this->oDB2, $sMagicLink);

        if( !$bOk ) {
// set err $errcode
            goto done;
        }

        // The magic login is good; make sure the user is ACTIVE
        list($kUser,$raUser,$raMetadata) = $this->oDB->GetUserInfo($kUid);
        if( !$kUser || @$raUser['eStatus'] != 'ACTIVE' ) {
// set err SEEDSESSION_ERR_USERSTATUS_INACTIVE
            goto done;
        }

        /* Magic Login has passed all tests. Login kUser with given perms (if blank, makeSessionRecord looks up the user's default perms)
         */
        $ok = $this->makeSessionRecord( $kUser, $raUser['realname'], $raUser['email'], $sPerms);

        done:
        return( $ok );
    }
}

class SEEDSessionAccount_MagicLogin
/**********************************
    MagicLogins are links that refer to SEEDSession_MagicLogin records that can allow a user to login just by issuing the link as an http parm (e.g. by clicking in an email).
    These links should obviously not be shared.
    They can be set to expire at a certain datetime, or only work a certain number of times.

    Type A: uid specified in record, magic_str in link for verification
            link : {kMagic}M{magic_str}
    Type B: uid specified in link, magic_str & uid hashed in link
            link : {kMagic}M{uid}B{hash}
 */
{
    static function CreateMagicLoginLink( SEEDSessionAccountDBRead2 $oDB, string $name, int $uid = 0 )
    /*************************************************************************************************
        Create a MagicLogin link for the given named record
     */
    {
        $s = "";

        if( ($kfrML = $oDB->GetKFRCond('ML',"name='".addslashes($name)."'")) ) {
            switch($kfrML->Value('type')) {
                case 'A':
                    break;
                case 'B':
                    if( $uid ) {
                        $s = $kfrML->Key()."M".$uid."B".md5($uid.$kfrML->Value('magic_str'));   // hash of uid+magic_str makes the link unique wrt the plaintext uid
                    }
                    break;
                default:
                    break;
            }
        }
        return( $s );
    }

    static function ValidateMagicLoginLink( SEEDSessionAccountDBRead2 $oDB, string $sLink )
    /**************************************************************************************
        Parse and validate a MagicLogin link.
        Return uid and kfrML if valid, err code otherwise
     */
    {
        $bOk = false;
        $uid = 0;
        $sPerms = "";
        $errCode = SEEDSESSION_ERR_MAGIC_NOT_FOUND;

        /* get the MagicLogin record
         */
        list($kMagic,$sLink) = explode('M',$sLink,2);
        if( !($kfrML = $oDB->GetKFR('ML',intval($kMagic))) ) { goto done; }

        /* Verify that ts_expiry, nLimit are okay
         */
        if( $kfrML->Value('ts_expiry') && time() > $kfrML->Value('ts_expiry') ) {
            $errCode = SEEDSESSION_ERR_MAGIC_EXPIRED;
            goto done;
        }
        if( ($nLimit = $kfrML->Value('nLimit')) == 0 ) {
            $errCode = SEEDSESSION_ERR_MAGIC_EXPIRED;
            goto done;
        } else if( $nLimit > 0 ) {
            $kfrML->SetValue('nLimit', $nLimit - 1);
            $kfrML->PutDBRow();
        }

        /* parse the second part of the link according to type
         */
        switch($kfrML->Value('type')) {
            case 'A':
                goto done;
            case 'B':
                // sLink is {uid}B{hash} : get uid and make sure hash is good
                list($kUid,$sHash) = explode('B', $sLink, 2);
                $kUid = intval($kUid);
                if( $sHash != md5($kUid.$kfrML->Value('magic_str')) ) {
                    $errCode = SEEDSESSION_ERR_MAGIC_INVALID;
                    goto done;
                }
                break;
            default:
                goto done;
        }

        /* $kUid is authenticated for login using $kfrML->[perms]  (caller must still check that the user is ACTIVE)
         */
        $bOk = true;
        $uid = $kUid;
        $sPerms = $kfrML->Value('perms');

        done:
        return([$bOk, $uid, $sPerms, $errCode]);
    }
}
