<?php

/* SEEDSessionAccountUI
 *
 * Copyright 2015-2019 Seeds of Diversity Canada
 *
 * Extensible and templatable UI for user accounts and login
 */

include_once( "SEEDSessionAccount.php" );
include_once( "SEEDTemplateMaker.php" );

class SEEDSessionAccountUI
{
    private $oApp;
    private $bLoginRequired = true;
    private $oTmpl;

    private $bTmpActivate = false;      // remove this when we always activate by default

    private $httpCmdParm = 'sessioncmd';  // the http parm that identifies the mode

    function __construct( SEEDAppSessionAccount $oApp, $raConfig = array() )
    {
        $this->oApp = $oApp;    // Already established the user/login state

        $this->bLoginRequired = (@$raConfig['bLoginNotRequired'] != true);  // notice this logic is inverted so login required by default
        $this->bTmpActivate = (@$raConfig['bTmpActivate'] == true);         // remove this when we activate by default

        $this->oTmpl = @$raConfig['oTmpl'] ?: $this->makeTemplate( $raConfig );
    }

    function DoUI()
    /**************
        Handle any sessioncmd, draw result and exit.
        Else draw login screen if required and exit.
        Else return.

        This function will not return if !IsLogin && bLoginRequired
     */
    {
        $bHandled = false;
        $s = "";

if( !$this->bTmpActivate ) return;  // set in config to use DoUI. Eventually it will be activated by default so remove this then.

        // regardless of IsLogin, if there's a sessioncmd try to handle it
        if( ($cmd = SEEDInput_Str( $this->httpCmdParm )) ) {
            list($bHandled,$bExit,$s) = $this->Command( $cmd );
            if( $bHandled ) {
                goto drawUI;
            }
        }

        // if (no sessioncmd, or sessioncmd not handled) and (IsLogin or login not required), proceed to the calling app
        if( $this->oApp->sess->IsLogin() || !$this->bLoginRequired ) {
            return;
        }

        // otherwise show a login screen and don't return
        list($bHandled,$bExit,$s) = $this->Command( 'acctLogin' );

        drawUI:
        header( "Content-Type:text/html; charset=ISO-8859-1" );
        echo $s;
        exit;
    }

    private function makeTemplate( $raConfig )
    {
        return( SEEDTemplateMaker2( array(
                    /* Templates in files:
                     *    named templates are defined in a file;
                     *    raConfig['fTemplates'] is an array of files whose named templates override the named templates in the base file
                     */
                    'fTemplates' => array_merge( [SEEDCORE."templates/SEEDSession.html"], @$raConfig['fTemplates'] ?: array() ),

                    /* Tag Resolution:
                     *     SEEDForm tags in Vanilla mode (require Form prefix)
                     *     SEEDLocal tags (require Local prefix)
                     *     Basic tags (appended to the list by EnableBasicResolver - default)
                     */
                    'sFormCid' => 'Plain',
                    'bFormRequirePrefix' => true,
                    //'oLocal' => $this->oLocal,        // it's better to put localized strings right in the template
                    'bLocalRequirePrefix' => true,

                    /* Global variables for templates:
                     *     e.g. site config, links to url root
                     *     When each template is expanded, the method allows template-specific variables; these apply to all templates (and can be overridden)
                     */
                    'raVars' => (@$raConfig['raTmplVars'] ?: array())
                                + ['lang' => $this->oApp->lang,
                                   'WCORE'=>W_CORE,
                                   'WCORE_JQUERY'=>W_CORE_JQUERY,
                                    'acctCreateURL' => $this->MakeURL('acctCreateURL'),
                                   'acctLoginURL' => $this->MakeURL('acctLoginURL') ]
        ) ) );
    }


    function Command( $cmd = "", $raTmplVars = array() )
    {
        $bHandled = true;
        $bExit = true;  // if bHandled, exit after writing $sOut
        $sOut = "";

        if( !$cmd ) $cmd = SEEDSafeGPC_GetStr( $this->httpCmdParm );

        $this->oTmpl->SetVars( $raTmplVars );

        switch( strtolower($cmd) ) {
            // Show the login screen
            case 'acctlogin':
            case 'acctlogin0':
                $sOut = $this->accountLogin0();
                break;

            // Show the user's profile
            case 'acctprofile':
                $sOut = $this->accountProfile();
                break;

            // Show the Account Create form, then the other UI steps during account creation
            case 'acctcreate':
            case 'acctcreate-0':
                $sOut = $this->accountCreate0();
                break;
            case 'acctcreate-1a': // Account Create form was submitted; validate and send the email
                $sOut = $this->accountCreate1a();
                break;
            case 'acctcreate-1b': // someone clicked on the email link; validate and show the Account Update form in acctCreate mode
                $sOut = $this->accountCreate1b();
                break;

            // Show the Account Update form (also used for acctCreate after the user clicks the magic link)
            case 'acctupdate':
            case 'acctupdate-0':
                $sOut = $this->accountUpdate0();
                break;
            case 'acctupdate-1':  // Account Update form was submitted; validate and store (also used for acctCreate if !bLogin and valid hash given)
                $sOut = $this->accountUpdate1();
                break;

            case 'logout':
                $sOut = $this->logout();
                break;

            case 'changepwd':  $sOut = $this->changePwd();  break;

            /* Send Password
                   0 = form to type your email address
                   1 = result page (we sent you an email; never heard of that email address)
             */
            case 'sendpwd':
            case 'sendpwd-0':   $sOut = $this->sendPwd0();  break;
            case 'sendpwd-1':   $sOut = $this->sendPwd1();  break;

            case 'jxupdateaccount':
                // update account metadata within an authenticated SEEDSessionAccount
                break;
            case 'jxretrievepassword':
                break;
            case 'jxauthenticate':
                break;
            case 'jxchangepassword':
                break;

            case 'littlelogin':
                $sOut = $this->oTmpl->ExpandTmpl( "LittleLogin" );

            default:
                $bHandled = false;
                break;
        }
        return( array($bHandled, $bExit, $sOut) );
    }

    private function accountProfile()
    /********************************
     */
    {
        return( $this->oTmpl->ExpandTmpl( "AccountProfile" ) );
    }

    private function accountLogin0()
    /*******************************
     */
    {
        $bCopyGP = true;

        $sHidden = "";

        if( $bCopyGP ) {
            // Propagate all current parms so a re-login happens seamlessly (except for the session control parms)
            $ra = $this->oApp->sess->GetNonSessionHttpParms();
            unset($ra[$this->httpCmdParm]);
            foreach( $ra as $k => $v ) {
                $sHidden .= "<input type='hidden' name='$k' value='".SEEDCore_HSC($v)."'>";
            }
        }

// use $sess->GetLoginState() to find out whether there was a problem logging in (e.g. unknown user, bad password, wrong perms) and
// say so in an errmsg
        return( $this->oTmpl->ExpandTmpl( "AccountLogin", array( 'sHidden'=>$sHidden,
                                                                 'bEnableCreateAccount' => intval(@$this->raConfig['bEnableCreateAccount']
                                                               ) ) ) );
    }

    private function accountCreate0()
    /********************************
     */
    {
        return( $this->oTmpl->ExpandTmpl( "AccountCreate-0", array( 'errmsg'=>'' ) ) );
    }

    private function accountCreate1a()
    {
        $s = "";

        // validate the email that the new user typed
        $email = trim(SEEDSafeGPC_GetStrPlain( "acctCreate_uid" ));
        if( !$email /* || !it_looks_like_an_email_address($email) */ ) {
            $s = $this->oTmpl->ExpandTmpl( "AccountCreate-0", array('errmsg'=>"Please enter a valid email address") );
            goto done;
        }

        // check if it's a duplicate address
        list($k,$raUser,$raMetadata) = $this->oAuthDB->GetUserInfo( $email );
        if( $k ) {
            $s = $this->oTmpl->ExpandTmpl( "AccountCreate-0", array('errmsg'=>"The email address <b>$email</b> is already registered. If you've forgotten your password you can reset it.") );
            goto done;
        }

        // make a hash so we can validate the email later too
        $hashSeed = $this->oAuthDB->GetSessionHashSeed();
        $md5 = md5($email.$hashSeed);

        $urlLink = $this->MakeURL( 'acctCreate-1aEmailLinkURL' );
        $sEmail = $this->oTmpl->ExpandTmpl( "AccountCreate-1aEmail",
                                            array('acctCreate-1aEmailLinkURL'=>$urlLink,'email'=>$email,'emailUE'=>urlencode($email),'hash'=>$md5) );
//var_dump($sEmail);
        $this->SendMail( $email, "Please confirm your Seeds of Diversity web account", $sEmail );

        $s = $this->oTmpl->ExpandTmpl( "AccountCreate-1a" );

        done:
        return( $s );
    }

    private function accountCreate1b()
    /*********************************
        The new user entered their address in the form in step 0, and it was validated in step 1a and given a magic hash.
        Now the user has clicked on the verification link containing those two things. Re-validate and show the form to enter password and metadata.
     */
    {
        $s = "";

        $email = SEEDSafeGPC_GetStrPlain('email');
        $hash = SEEDSafeGPC_GetStrPlain('hash');

        list($bOk,$sOut) = $this->acctCreateValidate( $email, $hash );
        if( $bOk ) {
            $s = $this->accountUpdate0( $email, $hash );
        } else {
            $s = $sOut;
        }

        return( $s );
    }

    private function acctCreateValidate( $email, $hash )
    {
        /* Two parameters are propagated through the AccountCreate process: the email that the user typed in the initial form, and a hash that
         * validates it through the various steps. Make sure both are secure.
         */
        $bOk = false;
        $sOut = "";

        // Test that the hash matches - this verifies that the email is the same one that was entered (and to which the confirmation was sent)
        if( md5($email.$this->oAuthDB->GetSessionHashSeed()) != $hash ) {
            $sOut = $this->oTmpl->ExpandTmpl( "AccountCreate-1aErr", array('errmsg'=>"The link you clicked in your confirmation email doesn't match our records. "
                                                                                    ."Please try registering again, or contact <a href='mailto:office@seeds.ca'>office@seeds.ca</a>" ));
            goto done;
        }

        // The account didn't exist when we sent the confirmation email, but maybe it exists now. (e.g. if the user clicked the magic link twice).
        // This could send the user to the Update screen if the account already exists, but we don't really want anyone to be able to alter user info and
        // password just by finding an old confirmation email. Instead, force them to login with their password.
        list($k,$raUser,$raMetadata) = $this->oAuthDB->GetUserInfo( $email );
        if( $k ) {
            $sOut = $this->oTmpl->ExpandTmpl( "AccountCreate-1aErr", array('errmsg'=>"The email address <b>$email</b> is already registered. "
                                                                                    ."If you've forgotten your password you can reset it.") );
            goto done;
        }

        $bOk = true;

        done:
        return( array($bOk,$sOut) );
    }

    private function accountUpdate0( $email = "", $hash = "", $raVars = array() )
    /****************************************************************************
        Show the AccountUpdate screen - if email/hash given, this is actually used for Account Create when the user clicks the magic link in their email

        Update: must be logged in, all fields are shown normally
        Create: show email readonly, propagate email and hash to accountUpdate1 to do the actual creation

        $raVars can contain userdata propagated from forms that didn't validate (e.g. passwords didn't match). It will override userdata from db.
                It can also contain errmsg from that kind of validation.
     */
    {
        $raVars['IsAccountCreate'] = ($hash != "");

        // mode Create:  email and hash are given
        // mode Update:  email and hash are not given, must be logged in
        if( $raVars['IsAccountCreate'] ) {
            if( !$email || !$hash )  return( "" );
            $raVars['email'] = $email;
            $raVars['hash'] = $hash;
            $raVars['acctUpdateURL'] = $this->MakeURL( 'acctCreateURL' );
        } else {
            if( $email || $hash || !$this->oApp->sess->IsLogin() )  return( "" );
            $raVars = $this->fetchUserDataDb();
            $raVars['acctUpdateURL'] = $this->MakeURL( 'acctUpdateURL' );
        }

        $s = $this->oTmpl->ExpandTmpl( "AccountUpdate", $raVars );

        return( $s );
    }

    private function accountUpdate1()
    /********************************
        If IsAccountCreate
            Check that the hash matches the email and passwords match; CreateUser and show profile.
            When showing profile, send login credentials so AccountProfile shows the new user. Must logout if already logged in as another user.
        Else
            Must be logged in. Just update the current user's information.
     */
    {
        $s = "";

        $email = SEEDSafeGPC_GetStrPlain('email');
        $raVars = $this->getUserDataHttp();

        if( ($bIsAccountCreate = SEEDSafeGPC_GetInt('IsAccountCreate')) ) {
            /* Create mode
             */
            $hash = SEEDSafeGPC_GetStrPlain('hash');
            $pwd1 = SEEDSafeGPC_GetStrPlain('user_pass1');
            $pwd2 = SEEDSafeGPC_GetStrPlain('user_pass2');
            list($bOk,$s) = $this->acctCreateValidate( $email, $hash );
            if( !$bOk ) {
                goto done;
            }

            if( !$pwd1 || !$pwd2 ) {
                $raVars['errmsg'] = "Please enter a password, and retype it to make sure.";
                $s = $this->accountUpdate0( $email, $hash, $raVars );
                goto done;
            }
            if( $pwd1 != $pwd2 ) {
                $raVars['errmsg'] = "The passwords you typed did not match. Please try again.";
                $s = $this->accountUpdate0( $email, $hash, $raVars );
                goto done;
            }

            $raP = array( 'realname' => "",  // this should be username
                          'eStatus' => "ACTIVE",
                          'lang' => "E",
                          'gid1' => intval(@$this->raConfig['iActivationInitialGid1'])
                        );
            if( !($kUser = $this->oAuthDB->CreateUser( $email, $pwd1, $raP )) ||    // create the new user
                !$this->putUserDataDb( $kUser, $raVars ) ||                         // store the metadata
                !$this->oApp->sess->LoginAsUser( $kUser ) )                         // then login as the new user so Profile does the right thing
            {
                $s = $this->oTmpl->ExpandTmpl( "AccountCreate-1aErr",
                            array('errmsg'=>"An error occurred creating your account. Please try again, or contact <a href='mailto:office@seeds.ca'>office@seeds.ca</a>" ) );
                goto done;
            }

        } else {
            /* Update mode - store the metadata for the current user
             */
            if( !$this->oApp->sess->IsLogin() )  goto done;

            $this->putUserDataDb( $this->oApp->sess->GetUID(), $raVars );
        }

        $s = $this->GotoURL( $this->MakeURL( 'acctProfileURL' ) );

        done:
        return( $s );
    }

    private function logout()
    {
        $sUsername = $this->oApp->sess->GetUID() ? $this->oApp->sess->GetName() : "";   // if not logged in, say "Goodbye" instead of "Goodbye #0"

        $this->oApp->sess->LogoutSession();

        return( $this->oTmpl->ExpandTmpl( "AccountLogout", array( 'sUsername' => $sUsername ) ) );
    }

    private function changePwd()
    /***************************
        if parms are given, attempt to change the password and tell the results
        if no parms exist, draw the Change Password form
     */
    {
        $bSuccess = false;
        $raVars = array();

        $pwd1 = SEEDSafeGPC_GetStrPlain('user_pass1');
        $pwd2 = SEEDSafeGPC_GetStrPlain('user_pass2');

        if( !$pwd1 || !$pwd2 ) {
            $raVars['errmsg'] = "Please enter a password, and retype it to make sure.";
            goto done;
        }
        if( $pwd1 != $pwd2 ) {
            $raVars['errmsg'] = "The passwords you typed did not match. Please try again.";
            goto done;
        }

        $bSuccess = $this->oAuthDB->ChangeUserPassword( $this->oApp->sess->GetUID(), $pwd1 );

        done:
        return( $this->oTmpl->ExpandTmpl( $bSuccess ? "AccountChangePassword-1" : "AccountChangePassword-0", $raVars ) );
    }

    private function sendPwd0()
    /**************************
        Draw the Send my Password form
     */
    {
        return( $this->oTmpl->ExpandTmpl( "AccountSendPassword-0", array() ) );
    }

    private function sendPwd1()
    /**************************
        Respond to the Send my Password form by checking for the given user and a) sending the password by email, or b) saying why not
     */
    {
// Sorry, this could be sendPwd0 and it would all work just as well
        $s = "";
        $sErrMsg = "";
        $bOk = false;

        // Get the uid from $this->httpNameUID.'1' so the uid can be propagated to the Send Password form's initial value without confusion
        if( ($sUid = SEEDSafeGPC_GetStrPlain( 'sendPwd_uid', $_POST )) ) {
            list($k,$raUser,$raMetadata) = $this->oAuthDB->GetUserInfo( $sUid );

            if( !$k ) {
                $sErrMsg = $this->oLocal->S( "SendPassword_user_not_registered", array($sUid) );
            } else if( $raUser['eStatus'] != 'ACTIVE' ) {
                $sErrMsg = $raUser['eStatus'] == 'PENDING'  ? $this->oLocal->S( "login_err_userstatus_pending", array($sUid) )
                                                            : $this->oLocal->S( "login_err_userstatus_inactive", array($sUid) );
            } else {
                assert( !empty($this->raConfig['urlSendPasswordSite']) );
                $sMail = $this->oLocal->S( "SendPassword_email_body", array( $this->raConfig['urlSendPasswordSite'], $raUser['email'], $raUser['password'] ) );

                $bOk = $this->SendMail( $raUser['email'], $this->oLocal->S('SendPassword_email_subject'), $sMail );
                       $this->SendMail( "bob@seeds.ca",   $this->oLocal->S('SendPassword_email_subject'), $sMail );
            }
        }

        if( $bOk ) {
            $s .= $this->oTmpl->ExpandTmpl( "AccountSendPassword-1", array() );
        } else {
            $s .= $this->oTmpl->ExpandTmpl( "AccountSendPassword-0", array( 'sErrMsg'=>$sErrMsg ) );
        }

        return( $s );
    }


    private $userDataKeys = array(
        'user_firstname',
        'user_lastname',
        'user_address',
        'user_city',
        'user_province',
        'user_postcode',
        'user_country',
        'user_phone',
        'user_profile_desc',
        'user_ip'
    );


    private function getUserDataHttp()
    {
        $raUserData = array();

        foreach( $this->userDataKeys as $kMD ) {
            $raUserData[$kMD] = SEEDSafeGPC_GetStrPlain($kMD);
        }
        return( $raUserData );
    }

    private function fetchUserDataDb()
    {
        $raUserData = array();
        $raMD = $this->oAuthDB->GetUserMetadata( $this->oApp->sess->GetUID(), false );

        $sLivUserid = intval(@$raMD['sliv_userid']) or die( "UserMetadata:sliv_userid is not set" );
        $sLivAccid  = intval(@$raMD['sliv_accid']) or die( "UserMetadata:sliv_accid is not set" );
        $raUserData['kSLivUserid'] = $sLivUserid;
        $raUserData['kSLivAccid'] = $sLivAccid;

        foreach( $this->userDataKeys as $kMD ) {
            $raUserData[$kMD] = @$raMD[$kMD];
        }
    }
    private function putUserDataDb( $kUser, $raUserData )
    {
        $ok = true;

        foreach( $this->userDataKeys as $kMD ) {
            $ok = $ok && $this->oAuthDB->SetUserMetaData( $kUser, $kMD, @$raUserData[$kMD] );
        }
// kluge to make SeedLiving happy for now
// this is only a good thing for new accounts, where $kUser is larger than any existing user key (otherwise, risk linking to someone else's SeedLiving account)
        if( !@$raUserData['sliv_userid'] )  $ok = $ok && $this->oAuthDB->SetUserMetaData( $kUser, 'sliv_userid', $kUser );
        if( !@$raUserData['sliv_accid'] )   $ok = $ok && $this->oAuthDB->SetUserMetaData( $kUser, 'sliv_accid', $kUser );

        return( $ok );
    }

    protected function GotoURL( $url )
    {
        header( "Location: $url" );
    }

    protected function MakeURL( $urlType, $raParms = array() )
    {
        $s = "";

        // The trailing / really matters if this is an action='' in a <form>.
        // The http(s) is necessary because some of these links are placed in emails for confirmations
        if( defined("STD_isLocal") ) {
            $sUrlLogin = (STD_isLocal ? 'http' : 'https')."://".$_SERVER['SERVER_NAME'].SITEROOT_URL."login/";
        } else {
            $sUrlLogin = "/";
        }

        switch( $urlType ) {
            case 'acctProfile':                  $s = $sUrlLogin."profile";    break;
            case 'acctCreateURL':                $s = $sUrlLogin;    break;
            case 'acctCreate-1aEmailLinkURL':    $s = $sUrlLogin;    break;
            case 'acctUpdateURL':                $s = $sUrlLogin;    break;
            case 'acctLoginURL':                 $s = $sUrlLogin;    break;
        }
        return( $s );
    }

    protected function SendMail( $mailto, $subject, $body )
    {
        if( @$this->raConfig['fnSendMail'] ) {
            return( call_user_func( $this->raConfig['fnSendMail'], $mailto, $subject, $body ) );
        }

        die( "Override SEEDSessionAccount_UI::SendMail" );
    }
}

class SEEDSessionAuthUI_Local2
{
    function __construct() {}

    function GetLocalStrings()
    {
        $localStrings = array(
        // Cr�ez un compte
        // Votre compte
        // Ouvrez  une session
        // Retour au pr�c�dent
        // Changez Le E-mail
        // Vous avez d�j� un compte?
        //   Veuillez entrer votre adresse de courriel
        //   Veuillez entrer votre mot de passe
        // Vous avez oubli� votre mot de passe? Cliquez ici.
        // Vous n'avez pas de compte?
        //   Pr�nom:
        //   Nom:
        //   Veuillez entrer votre adresse de courriel:
        // Modifier les informations de votre compte
        // Vos param�tres
        // Ajouter - to add
        // Modifier - to modify
        // Supprimer - to remove

        "Your email address" => array(
                "EN" => "Your email address",
                "FR" => "Votre adresse de courriel" ),

        "Your password" => array(
                "EN" => "Your password",
                "FR" => "Votre mot de passe" ),

        "Login" => array(
            "EN" => "Sign in",
            "FR" => "Ouvrez une session" ),

        "Logout" => array(
            "EN" => "Sign out",
/* ! */     "FR" => "Fermez la session" ),

        "Back to Login" => array(
            "EN" => "Back to Login",
/* ! */     "FR" => "Back to Login" ),

        "Don't have an account?" => array(
                "EN" => "Don't have an account?",
                "FR" => "Vous n'avez pas un compte?"),

        "Create an account" => array(
                "EN" => "Create an account",
                "FR" => "Cr&eacute;ez un compte" ),

        "Forgot your password?" => array(
                "EN" => "Forgot your password?",
                "FR" => "Oubliez votre mot de passe?" ),

        "Send me my password" => array(
                "EN" => "Send me my password",
                "FR" => "Envoyez-moi mon mot de passe" ),

        "SendPassword_success" => array(
            "EN" => "<h2>Your password has been sent to you by email</h2>"
                   ."<p>You should receive an email shortly containing login instructions.</p>",
/* ! */     "FR" => "<h2>Your password has been sent to you by email</h2>"
                   ."<p>You should receive an email shortly containing login instructions.</p>" ),

        "SendPassword_fail" => array(
            "EN" => "<h3>User not known</h3>"
                    ."<p>User '%1%' is not registered on our web site. Do you have another email that you might have registered here instead? "
                    ."Please try again using a different email address, or contact our office if you need help, at office@seeds.ca or 1-226-600-7782</p>",
/* ! */     "FR" => "<h3>User not known</h3>"
                    ."<p>User '%1%' is not registered on our web site. Do you have another email that you might have registered here instead? "
                    ."Please try again using a different email address, or contact our office if you need help, at office@seeds.ca or 1-226-600-7782</p>" ),

        "SendPassword_email_subject" => array(
            "EN" => "Seeds of Diversity web site - Password reminder",
/* ! */     "FR" => "Seeds of Diversity web site - Password reminder" ),


        "SendPassword_email_body" => array(
            "EN" => "You have requested a password reminder from Seeds of Diversity. Please use the following "
                   ."to login to our web site.\n\nWeb site: %1%\nUser:     %2%\nPassword: %3%\n\n"
                   ."If you have any questions, please contact office@seeds.ca or 1-226-600-7782",
/* ! */     "FR" => "You have requested a password reminder from Seeds of Diversity. Please use the following "
                   ."to login to our web site.\n\nWeb site: %1%\nUser:     %2%\nPassword: %3%\n\n"
                   ."If you have any questions, please contact courriel@semences.ca or 1-226-600-7782" ),

        /* ChangePassword
         */
        "ChangePassword_button" => array(
            "EN" => "Change Password",
            "FR" => "Changez le mot de passe" ),

        "ChangePassword_Your new password" => array(
            "EN" => "Type your new password",
            "FR" => "Tapez votre nouveau mot de passe" ),

        "ChangePassword_Your new password again" => array(
            "EN" => "Please re-type your new password",
            "FR" => "SVP retapez" ),

        "ChangePassword_success" => array(
            "EN" => "Your password is changed",
/* ! */     "FR" => "Your password is changed" ),

        );
        return( $localStrings );
    }
}

?>
