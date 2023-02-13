<?php

/* SEEDSessionAccountTag
 *
 * Copyright 2017-2023 Seeds of Diversity Canada
 *
 * Handle SEEDTag tags for SEEDSessionAccount information
 */

include_once( "SEEDSessionAccount.php" );

class SEEDSessionAccountTagHandler
/*********************************
    Show information about the current (or other) user
 */
{
    private $oApp;
    private $raConfig;
    private $oDB;

    function __construct( SEEDAppSessionAccount $oApp, $raConfig )
    /*************************************************************
        raConfig: db         = specify which SEEDSession_* tables to report on
                  bAllowKMbr = permits kMbr argument e.g. [[SEEDSessionAccount_Name: 1499]] to reveal info about other users
                  bAllowPwd  = permits password to be revealed
     */
    {
        $this->oApp = $oApp;
        $this->raConfig = $raConfig;
        $dbname = @$this->raConfig['db'] ? $oApp->GetDBName($this->raConfig['db']) : "";
        $this->oDB = new SEEDSessionAccountDBRead( $oApp->kfdb, $dbname );
        $this->oDB2 = new SEEDSessionAccountDBRead2( $oApp->kfdb, 0, ['dbname'=>$dbname] );     // uid==0 because only reading and tags always contain uid
    }

    function ResolveTagSessionAccount( $raTag, SEEDTagParser $oTagParser, $raParms = [] )
    /************************************************************************************
        $user must be given as the first arg.  if !$bAllowKMbr it is forced to the current login regardless

        [[SEEDSessionAccount_Key: $user]]       resolve to kUser (e.g. $user could be an email)
        [[SEEDSessionAccount_Email: $user]]     email address of given user (e.g. $user could be a kUser)
        [[SEEDSessionAccount_Realname: $user]]  real name
        [[SEEDSessionAccount_Name: $user]]      real name but defaults to email address if blank
        [[SEEDSessionAccount_Password: $user]]  if $bAllowPwd show password (should only be used in account initialization)

        [[SEEDSessionAccount_TrustTest: $user]] set privacy controls such as $bAllowPwd based on admin state and given user

        [[SEEDSessionAccount_MagicLoginLink: $user | namedMagicLink]] create a magic login for the given user
     */
    {
        $s = "";
        $bHandled = true;

        $tag = strtolower($raTag['tag']);

        if( !SEEDCore_StartsWith($tag, 'seedsessionaccount_') ) {
            $bHandled = false;
            goto done;
        }

        $bAllowKMbr = intval(@$this->raConfig['bAllowKMbr']);     // allow arbitrary account e.g. [[SEEDSessionAccount_Name: 1499]]
        $bAllowPwd  = intval(@$this->raConfig['bAllowPwd']);      // allow the password to be shown

        // if showing other users, we are logged into an admin account so require kMbr and inhibit revealing info about self
// if target is an email, look it up
        $uid = $bAllowKMbr ? intval($raTag['target'])
                           : $this->oApp->sess->GetUID();
        if( !$uid ) {
            $bHandled = false;
            goto done;
        }

        list($kUser,$raUser) = $this->oDB->GetUserInfo( $uid, false );
        if( !$kUser ) {
            $bHandled = false;
            goto done;
        }

        switch( $tag ) {
            case 'seedsessionaccount_key':      $s = $uid;                                        break;
            case 'seedsessionaccount_email':    $s = @$raUser['email'];                           break;
            case 'seedsessionaccount_realname': $s = @$raUser['realname'];                        break;
            case 'seedsessionaccount_name':     $s = @$raUser['realname'] ?: @$raUser['email'];   break;
            case 'seedsessionaccount_password': $s = $bAllowPwd ? @$raUser['password'] : "";      break;

            case 'seedsessionaccount_trusttest':
                /* Cause seedtag variables to be set that will allow the template to make choices about what it can say.
                 *     [[if: bSEEDSessionAllowArbitraryUser | I can tell you all about yourself | Nope]]
                 *     [[if: bSEEDSessionAllowPassword      | I can tell you your password      | Nope]]
                 *     [[if: bSEEDSessionPasswordAutoGen    | You have a new password           | You changed it so I won't show it here]]
                 */
                $oTagParser->SetVar( 'bSEEDSessionAllowArbitraryUser', $bAllowKMbr );
                $oTagParser->SetVar( 'bSEEDSessionAllowPassword', $bAllowPwd );
                if( $bAllowPwd ) {
                    // Tell the template whether the user still has the auto-generated password, because a template might
                    // legitimately show that but really shouldn't reveal someone's chosen password
                    if( strlen($raUser['password']) == 5 ) {
                        $oTagParser->SetVar( 'bSEEDSessionPasswordAutoGen', 1 );
                    }
                }
                break;

            case 'seedsessionaccount_magicloginlink':
                /* Make a SEEDSession_MagicLogin link for the given user and named MagicLogin record
                 * Note: type A links don't use the userid but the format of these tags requires uid as first arg.
                 *
                 * Type A: [[SEEDSessionAccount_MagicLoginLink: 0 | namedMagicLogin]]     ->  {kML}M{magic_str}
                 * Type B: [[SEEDSessionAccount_MagicLoginLink: uid | namedMagicLogin]]   ->  {kML}M{uid}B{hash}
                 */
                if( $uid && ($sMLLink = @$raTag['raParms'][1]) ) {
                    $s = SEEDSessionAccount_MagicLogin::CreateMagicLoginLink($this->oDB2, $sMLLink, $uid);
                }
                break;

            default:
                $bHandled = false;
        }

        done:
        return( [$bHandled,$s] );
    }
}
