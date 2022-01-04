<?php

/* MbrUsers
 *
 * Copyright 2021 Seeds of Diversity Canada
 *
 * Manage connection between mbr_contacts and SEEDSession_Users
 */

include( "MbrContacts.php" );


class MbrUsers
{
    private $oApp;

    function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;
        $this->oMbrDB = new Mbr_ContactsDB($oApp);
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
        if( ($kDup = $this->kfdb->Query1( "SELECT _key FROM {$this->dbname1}.SEEDSession_Users WHERE email='$sdbEmail' and _status='0'" )) ) {
            $bOk = false;
            $sErr = "Member $kMbr already has a login account ($kDup). If the report here says otherwise, it might be inactivated?<br/>";
            goto done;
        }


        // use mysql to generate an initial password
        $p = $this->kfdb->Query1( "SELECT left(md5('$sdbEmail'),6)" );
        // membership permissions if current member
        $gid1 = $this->IsCurrentFromExpires($kfrMbr->value('expires')) ? 2 : 0;
        $kMbr = $this->oSessUGP->CreateUser( $kfrMbr->value('email'), $p,
                                             array( 'k'=>$kMbr,
                                                    'realname'=> trim($kfrMbr->Expand("[[firstname]] [[lastname]] [[company]]")),
                                                    'eStatus'=>'ACTIVE',
                                                    'gid1'=> $gid1,
                                                    'lang'=> $kfrMbr->value('lang')
                                            ) );
        $bOk = ($kMbr != 0);

        //$bOk = $this->kfdb1->Execute(
        //        "INSERT INTO {$this->dbname1}.SEEDSession_Users (_key,_created,_created_by,_status,"
        //                                            ."email,password,realname,gid1,eStatus,dSentmsd)"
        //       ." VALUES ".SEEDStd_ArrayExpand($raMbr, "('[[_key]]',now(),0,0,'[[email]]',left(md5('[[email]]'),6),"
        //                                              ."trim('[[firstname]] [[lastname]] [[company]]'),2,'ACTIVE',0)") );
        if( !$bOk ) {
            $sErr = "Database error adding login account for member $kMbr : ".$this->kfdb->GetErrMsg();
        }

        done:
        return( array( $bOk, $sErr ) );
    }

    private function getMemberInfoAndValidate( $kMbr, $sTests = "" )
    /***************************************************************
        Return all fields from mbr_contacts, plus validation of the given tests
     */
    {
        $bOk = true;
        $sErr = "";

        if( !($kfrMbr = $this->oMbrDB->GetKFR($kMbr)) ) {
            $bOk = false;
            $sErr = "Member $kMbr is not in the contact database";
            goto done;
        }

        if( $sTests ) {
            foreach( explode(" ", $sTests) as $test ) {
                switch( $test ) {
                    case "UserExists":
                    case "UserNotExists":
                        $bExists = $this->kfdb->Query1( "SELECT _key FROM {$this->dbname1}.SEEDSession_Users WHERE _key='$kMbr'" );
                        $bOk = ($test=='UserExists' && $bExists) || ($test=='UserNotExists' && !$bExists);
                        if( !$bOk ) {
                            $sErr .= "Contact $kMbr ".($test=='UserExists' ? "does not have" : "already has")." a login account.";
                        }
                        break;

                    case "MembershipCurrent":
                        if( !$this->IsCurrentFromExpires($kfrMbr->value('expires')) ) {
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
        return( array($kfrMbr,$bOk,$sErr) );
    }

}
