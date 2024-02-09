<?php

/* MbrUsers
 *
 * Copyright 2021-2024 Seeds of Diversity Canada
 *
 * Manage connection between mbr_contacts and SEEDSession_Users
 */

include_once( "MbrContacts.php" );


class MbrUsers
{
    private $oApp;
    private $oMbr;

    function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;
        $this->oMbr = new Mbr_Contacts($oApp);
    }

    function UserExists( int $kMbr )
    {
        list($kfrMbr,$bOk,$sErr) = $this->GetMemberInfoAndValidate( $kMbr, "UserExists" );
        return( [$bOk, $sErr, $kfrMbr] );
    }

    function CreateLoginFromContact( $kMbr )
    /***************************************
        For an existing contact, create an active login.

        If they're a current member, give them membership permissions.
     */
    {
        $bOk = true;
        $sErr = "";

        // The contact must exist and have an email address
        list($kfrMbr,$bOk,$sErr) = $this->GetMemberInfoAndValidate( $kMbr, "EmailNotBlank UserNotExists" );
        if( !$bOk ) {
            goto done;
        }

        $sdbEmail = $kfrMbr->ValueDB('email');

        // Another login may not have the same email address (CreateUser checks this, but doesn't report an error - it should)
        if( ($kDup = $this->oApp->kfdb->Query1( "SELECT _key FROM {$this->oApp->DBName('seeds1')}.SEEDSession_Users WHERE email='$sdbEmail' and _status='0'" )) ) {
            $bOk = false;
            $sErr = "Member $kMbr already has a login account ($kDup). If the report here says otherwise, it might be inactivated?<br/>";
            goto done;
        }


        // use mysql to generate an initial password
        $p = $this->oApp->kfdb->Query1( "SELECT left(md5('$sdbEmail'),6)" );
        // membership permissions if current member
        $gid1 = $this->oMbr->IsCurrentFromExpires($kfrMbr->value('expires')) ? 2 : 0;

        $oSessDB = new SEEDSessionAccountDB2( $this->oApp->kfdb, $this->oApp->sess->GetUID(), ['dbname'=>$this->oApp->DBName('seeds1')] );
        $kUser = $oSessDB->CreateUser( $kfrMbr->Value('email'), $p,
                              ['k'=>$kMbr,
                               'realname'=>trim($kfrMbr->Expand("[[firstname]] [[lastname]] [[company]]")),
                               'eStatus'=>'ACTIVE',
                               'lang'=>$kfrMbr->value('lang'),
                               'gid1'=> $gid1,
                              ] );

        $bOk = ($kUser != 0);

        if( !$bOk ) {
            $sErr = "Database error adding login account for member $kMbr : ".$this->oApp->kfdb->GetErrMsg();
        }

        done:
        return( [$bOk, $sErr] );
    }

    function GetMemberInfoAndValidate( $kMbr, $sTests = "" )
    /*******************************************************
        Return all fields from mbr_contacts, plus validation of the given tests
     */
    {
        $bOk = true;
        $sErr = "";

        if( !($kfrMbr = $this->oMbr->oDB->GetKFR('M',$kMbr)) || $kfrMbr->Value('_status')!=0 ) {
            $bOk = false;
            $sErr = "Member $kMbr is not in the contact database";
            goto done;
        }

        if( $sTests ) {
            foreach( explode(" ", $sTests) as $test ) {
                switch( $test ) {
                    case "UserExists":
                    case "UserNotExists":
                        $bExists = $this->oApp->kfdb->Query1( "SELECT _key FROM {$this->oApp->DBName('seeds1')}.SEEDSession_Users WHERE _key='$kMbr' AND _status=0" );
                        $bOk = ($test=='UserExists' && $bExists) || ($test=='UserNotExists' && !$bExists);
                        if( !$bOk ) {
                            $sErr .= "Contact $kMbr ".($test=='UserExists' ? "does not have" : "already has")." a login account.";
                        }
                        break;

                    case "MembershipCurrent":
                        if( !$this->oContacts->IsCurrentFromExpires($kfrMbr->value('expires')) ) {
                            $bOk = false;
                            $sErr .= "Contact # $kMbr is not a current member";
                        }
                        break;

                    case "EmailNotBlank":
                        if( $kfrMbr->IsEmpty('email') ) {
                            $bOk = false;
                            $sErr .= "Contact # $kMbr does not have an email address in the contact database";
                        }
                        break;

                    default:
                        die( "Unknown validation code $test" );
                }
            }
        }

        done:
        return( [$kfrMbr,$bOk,$sErr] );
    }

}
