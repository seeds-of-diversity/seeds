<?php

/* SEEDSessionAccountDB
 *
 * Copyright 2006-2023 Seeds of Diversity Canada
 *
 * DB layer for SEEDSession Users, Groups, Perms, Metadata
 */

//include_once( "SEEDMetaTable.php" );    // StringBucket
include_once( SEEDROOT."Keyframe/KeyframeRelation.php" );

/****************************
 *
 * Don't make any changes to these classes without also changing the v2 classes.
 *
 ****************************/


class SEEDSessionAccountDBRead
/*****************************
    DB access layer for Users, UsersMetadata, Groups, GroupsMetadata, UsersXGroups, Perms
 */
{
    protected $kfdb;
    protected $sDB = "";

    function __construct( KeyframeDatabase $kfdb, $sDB = "" )
    {
        $this->kfdb = $kfdb;

        // Prepended to every database table so you can refer to a seedsession database other than the default for the kfdb.
        // Normally, $this->sDB == "" so the default database is used.
        if( $sDB ) $this->sDB = $sDB.".";
    }

    function GetEmail( $kUser )
    /**************************
        Often you know someone's kUser and you just want to show their email id
     */
    {
        $email = $this->kfdb->Query1( "SELECT email FROM {$this->sDB}SEEDSession_Users WHERE _key='".addslashes($kUser)."'" );
        return( $email );
    }

    function GetEmailRA( $raKUser, $bDetail = true )
    /***********************************************
        Like GetEmail() but for an array.
        Given an array of kUser, return one of the following:
            $bDetail:  array of kUser=>email
           !$bDetail:  array of email
     */
    {
        $cond = SEEDCore_MakeRangeStrDB( $raKUser, "_key" );
    }

    function GetKUserFromEmail( $email )
    /***********************************
        Reverse of GetEmail(), because this is often used
     */
    {
        $kUser = $this->kfdb->Query1( "SELECT _key FROM {$this->sDB}SEEDSession_Users WHERE _status='0' AND email='".addslashes($email)."'" );
        return( intval($kUser) );
    }

    function GetKUserFromEmailRA( $raEmail, $bDetail = true )
    /********************************************************
        Like GetKUserFromEmail() but for an array.
        Given an array of emails, return one of the following:
            $bDetail:  array of kUser=>email
           !$bDetail:  array of email
     */
    {
        $raRet = array();

        foreach( $raEmail as $email ) {
            if( ($k = $this->GetKUserFromEmail( $email )) ) {
                if( $bDetail ) {
                    $raRet[$k] = $email;
                } else {
                    $raRet[] = $k;
                }
            }
        }
        return( $raRet );
    }


    function GetUserInfo( $userid, $bGetMetadata = true, $bIncludeDeletedAndHidden = false )
    /***************************************************************************************
       Retrieve a SEEDSession_Users row and its metadata

       userid can be kUser or email (email only works for _status='0' to prevent conflicts with old records)
     */
    {
        if( is_numeric($userid) ) {
            $cond = "_key='$userid'".($bIncludeDeletedAndHidden ? "" : " AND _status='0'");
        } else {
            // don't look for deleted/hidden user rows by email because there can be duplicates
            $cond = "email='".addslashes($userid)."' AND _status='0'";
        }

        $raUser = $this->kfdb->QueryRA( "SELECT * FROM {$this->sDB}SEEDSession_Users WHERE $cond" );
        $raMetadata = array();

        // $k is an unambiguous return value for testing success
        $k = intval(@$raUser['_key']);
        if( $k && $bGetMetadata ) {
            $raMetadata = $this->GetUserMetadata( $k );
        }
        return( array($k, $raUser, $raMetadata) );
    }

    function GetUsersFromGroup( $kGroup, $raParms = array() )
    /********************************************************
        Return the list of users that belong to kGroup: gid1 + UsersXGroups

        raParms:
            _status = kf status (-1 means all)
            eStatus = {list of eStatus codes to include}  e.g. 'INACTIVE','PENDING'
                      default: eStatus IN ('ACTIVE')
            bDetail = return array of uid=>array( user data ) -- default for historical reasons
           !bDetail = return array of uids
     */
    {
        $sql = "SELECT [[cols]] FROM {$this->sDB}SEEDSession_Users U WHERE gid1='$kGroup' AND [[statusCond]] "
                ."UNION "
              ."SELECT [[cols]] FROM {$this->sDB}SEEDSession_UsersXGroups UG,{$this->sDB}SEEDSession_Users U "
                ."WHERE UG.gid='$kGroup' AND UG.uid=U._key AND [[statusCond]]";

        return( $this->getUsers( $sql, $raParms ) );
    }

    function GetUsersFromMetadata( $sK, $sVCond, $raParms = array() )
    /****************************************************************
        Return an array of users whose metadata for key $sK is given by condition sVCond
        Metadata fields in sVCond must be prefaced by "UM."
            e.g. sK=foo, sVCond="UM.v='bar'"     returns users who have metadata foo=bar
                 sK=foo, sVCond="UM.v<>'bar'"    returns users who don't have metadata foo=bar including where foo is undefined
                 sk=foo, sVCond="UM.v is null"   means foo equals null or foo is undefined
                 sk=foo, sVCond="UM.uid is null" unambiguously means foo is undefined

        raParms: same as GetUsersFromGroup
     */
    {
        $sql = "SELECT [[cols]] FROM {$this->sDB}SEEDSession_Users U LEFT JOIN {$this->sDB}SEEDSession_UsersMetadata UM "
                ."ON (U._key=UM.uid AND UM.k='$sK') "
                ."WHERE ($sVCond) AND [[statusCond]]";

        return( $this->getUsers( $sql, $raParms ) );
    }

    private function getUsers( $sql, $raParms = array() )
    {
        $raRet = array();

        // sql must contain [[cols]] which is replaced by the column list
        $sql = str_replace( "[[cols]]", "U._key as _key,U.email as email,U.realname as realname,U.eStatus as eStatus", $sql );

        // sql must contain [[statusCond]] which is replaced by the status conditions
        $st  = SEEDCore_ArraySmartVal1( $raParms, '_status', 0 );           // empty is not a valid value
        $est = SEEDCore_ArraySmartVal1( $raParms, 'eStatus', "'ACTIVE'" );
        $sCondStatus = "U.eStatus IN ($est)"
                      .($st == -1 ? "" : " AND U._status='$st'");
        $sql = str_replace( "[[statusCond]]", $sCondStatus, $sql );

        $bDetail = SEEDCore_ArraySmartVal( $raParms, 'bDetail', array(true,false) );

        if( ($dbc = $this->kfdb->CursorOpen( $sql )) ) {
            while( $ra = $this->kfdb->CursorFetch( $dbc ) ) {
                if( $bDetail ) {
                    $raRet[$ra['_key']] = array('email'=>$ra['email'],'realname'=>$ra['realname'],'eStatus'=>$ra['eStatus']);
                } else {
                    $raRet[] = $ra['_key'];
                }
            }
            $this->kfdb->CursorClose( $dbc );
        }
        return( $raRet );
    }

    function GetGroupsFromUser( $kUser, $raParms = array() )
    /*******************************************************
        Return the list of groups in which kUser is a member: gid1 + UsersXGroups

        raParms:
            _status = kf status (-1 means all)
            bNames  = Return array of kGroup=>groupname
           !bNames  = Return array of kGroup  (default)
     */
    {
        $raRet = array();

        $st  = SEEDCore_ArraySmartVal1( $raParms, '_status', 0 );           // empty is not a valid value
        $sCondStatus = ($st == -1 ? "" : " AND _status='$st'");

        $bNames = intval(@$raParms['bNames']);

        if( ($dbc = $this->kfdb->CursorOpen(
                "SELECT gid1 FROM {$this->sDB}SEEDSession_Users WHERE _key='$kUser' $sCondStatus "
               ."UNION "
               ."SELECT gid FROM {$this->sDB}SEEDSession_UsersXGroups WHERE uid='$kUser' $sCondStatus" )) ) {
            while( $ra = $this->kfdb->CursorFetch( $dbc ) ) {
                if( $ra[0] ) {
                    if( $bNames ) {
                        $raRet[$ra[0]] = $this->kfdb->Query1("SELECT groupname FROM {$this->sDB}SEEDSession_Groups WHERE _key='{$ra[0]}'");
                    } else {
                        $raRet[] = $ra[0];
                    }
                }
            }
            $this->kfdb->CursorClose($dbc);
        }
        asort( $raRet );  // sort by array value, maintaining key association if bNames

        return( $raRet );
    }

    function GetUserMetadata( $kUser, $bAndGroupMetadata = false )
    /*************************************************************
        bAndGroupMetadata == false: just get the metadata associated with the user (good for UI and R/W applications)
                          == true:  combine with the metadata for the user's groups (good for R/O applications)
     */
    {
        $raMetadata = array();

        // Get group metadata first so user metadata keys overwrite it
        if( $bAndGroupMetadata ) {
            $raMetadata = $this->GetGroupMetadataByUser( $kUser );
        }

        $ra = $this->kfdb->QueryRowsRA( "SELECT k,v FROM {$this->sDB}SEEDSession_UsersMetadata WHERE _status='0' AND uid='$kUser'" );
        // ra is array( array( k=>keyname1, v=>value1 ), array( k=>keyname2, v=>value2 ) )
        foreach( $ra as $ra2 ) {
            $raMetadata[$ra2['k']] = $ra2['v'];
        }
        return( $raMetadata );
    }

    function GetGroupMetadata( $kGroup )
    /***********************************
     */
    {
        $raMetadata = array();
        $ra = $this->kfdb->QueryRowsRA( "SELECT k,v FROM {$this->sDB}SEEDSession_GroupsMetadata WHERE _status='0' AND gid='$kGroup'" );
        // ra is array( array( k=>keyname1, v=>value1 ), array( k=>keyname2, v=>value2 ) )

        foreach( $ra as $ra2 ) {
            $raMetadata[$ra2['k']] = $ra2['v'];
        }
        return( $raMetadata );
    }

    function GetGroupMetadataByUser( $kUser )
    /****************************************
     */
    {
        $raMetadata = array();
        $ra = $this->kfdb->QueryRowsRA(
            "SELECT G.k as k,G.v as v FROM {$this->sDB}SEEDSession_GroupsMetadata G,{$this->sDB}SEEDSession_UsersXGroups X "
                ."WHERE G._status='0' AND X._status='0' "
                ."AND G.gid=X.gid AND X.uid='$kUser' "
           ."UNION "
           ."SELECT G.k as k,G.v as v FROM {$this->sDB}SEEDSession_GroupsMetadata G,{$this->sDB}SEEDSession_Users U "
                ."WHERE G._status='0' AND U._status='0' "
                ."AND G.gid=U.gid1 AND U._key='$kUser'" );
        // ra is array( array( k=>keyname1, v=>value1 ), array( k=>keyname2, v=>value2 ) )
        foreach( $ra as $ra2 ) {
            $raMetadata[$ra2['k']] = $ra2['v'];
        }
        return( $raMetadata );
    }


    function GetPermsFromUser( $kUser )
    /**********************************
        Get the given user's permissions from perms and group-perms.

            e.g. for perms  'app1','R'
                            'app2','W'  & group 'app2','R'
                            'app3','RW' & group 'app3'=>'RWA'

                 'perm2modes' => array( 'app1'=>'R', 'app2'=>'RW', 'app3'=>'RWA' ),
                 'mode2perms' => array( 'R'=>array('app1','app2','app3'), 'W'=>array('app2','app3'), 'A'=>array('app3') )
     */
    {
        return( $this->getPermsList(
                // Get perms explicitly set for this uid
                "SELECT perm,modes FROM SEEDSession_Perms WHERE _status=0 AND uid='$kUser' "
               ."UNION "
                // Get perms associated with the user's primary group
               ."SELECT P.perm AS perm, P.modes as modes "
               ."FROM SEEDSession_Perms P, SEEDSession_Users U "
               ."WHERE P._status=0 AND U._status=0 AND "
               ."U._key='$kUser' AND U.gid1 >=1 AND P.gid=U.gid1 "
               ."UNION "
                // Get perms from groups
               ."SELECT P.perm AS perm, P.modes as modes "
               ."FROM SEEDSession_Perms P, SEEDSession_UsersXGroups GU "
               ."WHERE P._status=0 AND GU._status=0 AND "
               ."GU.uid='$kUser' AND GU.gid >=1 AND GU.gid=P.gid" ) );
    }

    function GetPermsFromGroup( $kGroup )
    /************************************
        Get the given group's permissions in the same format as GetPermsFromUserKey
     */
    {
        return( $this->getPermsList( "SELECT P.perm AS perm, P.modes as modes FROM SEEDSession_Perms P "
                                    ."WHERE P._status=0 AND P.gid='$kGroup'" ) );
    }

    private function getPermsList( $sql )
    {
        $raRet = array( 'perm2modes' => array(), 'mode2perms' => array( 'R'=>array(), 'W'=>array(), 'A'=>array() ) );
        if( ($dbc = $this->kfdb->CursorOpen( $sql )) ) {
            while( $ra = $this->kfdb->CursorFetch( $dbc ) ) {
                if( strchr($ra['modes'],'R') && !in_array($ra['perm'], $raRet['mode2perms']['R']) ) { $raRet['mode2perms']['R'][] = $ra['perm']; }
                if( strchr($ra['modes'],'W') && !in_array($ra['perm'], $raRet['mode2perms']['W']) ) { $raRet['mode2perms']['W'][] = $ra['perm']; }
                if( strchr($ra['modes'],'A') && !in_array($ra['perm'], $raRet['mode2perms']['A']) ) { $raRet['mode2perms']['A'][] = $ra['perm']; }
            }
            $this->kfdb->CursorClose( $dbc );
        }
        foreach( $raRet['mode2perms']['R'] as $p ) { $raRet['perm2modes'][$p]  = "R"; }
        foreach( $raRet['mode2perms']['W'] as $p ) { @$raRet['perm2modes'][$p] .= "W"; } // the @ prevents warning if R is not set so index not found for concatenation
        foreach( $raRet['mode2perms']['A'] as $p ) { @$raRet['perm2modes'][$p] .= "A"; }

        return( $raRet );
    }


    function GetSessionHashSeed()
    /****************************
        For security operations that involve a publicly transmitted hash based on some public data + some non-public data, this is the non-public data.
        It is generated per-server and stored in the StringBucket so even though you're reading this code right now, you still can't hack it.
     */
    {
/*
        $oSB = new SEEDMetaTable_StringBucket( $this->kfdb );
        if( !($hashSeed = $oSB->GetStr( "SEEDSession", "hashSeed" )) ) {    // first time this is called on this server; create the hashSeed and return it forever
            $hashSeed = rand();
            $oSB->PutStr( "SEEDSession", "hashSeed", $hashSeed );
        }
        return( $hashSeed );
*/
    }
}

class SEEDSessionAccountDB extends SEEDSessionAccountDBRead
/*************************
    DB write layer for UGP
 */
{
    private $uidOwnerOrAdmin;   // the user who is making changes to the UGP

//Kind of confusing that these are named the same as their private accessor methods
//And they should be in SEEDSessionAuthDBRead, and that should use them.
    private $kfrelUsers = NULL;
    private $kfrelGroups = NULL;
    private $kfrelUsersXGroups = NULL;
    private $kfrelPerms = NULL;
    private $kfrelUsersMetadata = NULL;
    private $kfrelGroupsMetadata = NULL;

    function __construct( KeyframeDatabase $kfdb, $uidOwnerOrAdmin, $sDB = "" )
    {
        parent::__construct( $kfdb, $sDB );
        $this->uidOwnerOrAdmin = $uidOwnerOrAdmin;
    }

    function CreateUser( $sEmail, $sPwd, $raParms = array() )
    /********************************************************
        raParms: k, eStatus, realname, sExtra, lang, gid1
     */
    {
        $kUser       = intval(@$raParms['k']);      // 0 means use the next auto-increment
        $sdbEmail    = addslashes($sEmail);
        $sdbPwd      = addslashes($sPwd);
        $sdbRealname = addslashes(@$raParms['realname']);
        $sdbExtra    = addslashes(@$raParms['sExtra']);
        $eStatus     = SEEDCore_ArraySmartVal( $raParms, 'eStatus', array('PENDING','ACTIVE','INACTIVE') );
        $eLang       = SEEDCore_ArraySmartVal( $raParms, 'lang', array('E','F','B') );
        $gid1        = intval(@$raParms['gid1']);

        $k = $this->kfdb->Query1("SELECT _key FROM {$this->sDB}SEEDSession_Users WHERE email='$sdbEmail' and _status='0'");
        if( !$k ) {
            // return value is what you expect, whether k is 0 or non-zero
            $k = $this->kfdb->InsertAutoInc(
                    "INSERT INTO {$this->sDB}SEEDSession_Users "
                    ."(_key,_created,_created_by,_updated,_updated_by,_status,email,password,realname,eStatus,sExtra,lang,gid1) "
                   ."VALUES ($kUser,NOW(),{$this->uidOwnerOrAdmin},NOW(),{$this->uidOwnerOrAdmin},0,"
                   ."'$sdbEmail','$sdbPwd','$sdbRealname','$eStatus','$sdbExtra','$eLang','$gid1')" );
        }
        return( $k );
    }

    function ActivateUser( $kUser )
    /******************************
        Activate an existing login that is INACTIVE or PENDING, or deleted or hidden
     */
    {
        $bOk = false;

        if( ($kfr = $this->kfrelUsers()->GetRecordFromDBKey( $kUser )) ) {
            $kfr->StatusSet( KeyframeRecord::STATUS_NORMAL );    // if the account has been deleted or hidden, undelete it
            $kfr->SetValue( 'eStatus', 'ACTIVE' );        // if the account is INACTIVE, activate it

            $bOk = $kfr->PutDBRow();
        }

        return( $bOk );
    }


    function ChangeUserPassword( $kUser, $sPwd )
    {
        $kUser = intval($kUser);
        $sdbPwd = addslashes($sPwd);

        return( $kUser ? $this->kfdb->Execute( "UPDATE {$this->sDB}.SEEDSession_Users SET password='$sdbPwd' WHERE _key='$kUser'" ) : false );
    }

    function SetUserMetadata( $kUser, $k, $v )
    /*****************************************
     */
    {
        $ok = false;

        $kfrelUM = $this->kfrelUsersMetadata();

        // Fetch iStatus==-1 so any deleted or hidden records are found, and replaced with the new metadata
        if( !($kfr = $kfrelUM->GetRecordFromDB( "uid='$kUser' AND k='".addslashes($k)."'", array('iStatus'=>-1) )) ) {
            $kfr = $kfrelUM->CreateRecord();
        }
        if( $kfr ) {
            $kfr->SetValue( 'uid', $kUser );
            $kfr->SetValue( 'k', $k );
            $kfr->SetValue( 'v', $v );
            $kfr->StatusSet( KeyframeRecord::STATUS_NORMAL );  // because maybe there's an old value that has been deleted or hidden
            $ok = $kfr->PutDBRow();
        }
        return( $ok );
    }

    function SetGroupMetadata( $kGroup, $k, $v )
    /*******************************************
     */
    {
        $ok = false;

        $kfrelGM = $this->kfrelGroupsMetadata();

        // Fetch iStatus==-1 so any deleted or hidden records are found, and replaced with the new metadata
        if( !($kfr = $kfrelGM->GetRecordFromDB( "gid='$kGroup' AND k='".addslashes($k)."'", array('iStatus'=>-1) )) ) {
            $kfr = $kfrelGM->CreateRecord();
        }
        if( $kfr ) {
            $kfr->SetValue( 'gid', $kGroup );
            $kfr->SetValue( 'k', $k );
            $kfr->SetValue( 'v', $v );
            $kfr->StatusSet( KeyframeRecord::STATUS_NORMAL );  // because maybe there's an old value that has been deleted or hidden
            $ok = $kfr->PutDBRow();
        }
        return( $ok );
    }

    function DeleteUserMetadata( $kUser, $k )
    /****************************************
     */
    {
        $ok = false;

        $kfrelUM = $this->kfrelUsersMetadata();

        // Fetch iStatus==-1 so any hidden records are found, and replaced with the DELETED status
        if( ($kfr = $kfrelUM->GetRecordFromDB( "uid='$kUser' AND k='".addslashes($k)."'", array('iStatus'=>-1) )) ) {
            $kfr->StatusSet( KeyframeRecord::STATUS_DELETED );
            $ok = $kfr->PutDBRow();
        }
        return( $ok );
    }

    function DeleteGroupMetadata( $kGroup, $k )
    /******************************************
     */
    {
        $ok = false;

        $kfrelGM = $this->kfrelGroupsMetadata();

        // Fetch iStatus==-1 so any hidden records are found, and replaced with the DELETED status
        if( ($kfr = $kfrelGM->GetRecordFromDB( "gid='$kGroup' AND k='".addslashes($k)."'", array('iStatus'=>-1) )) ) {
            $kfr->StatusSet( KeyframeRecord::STATUS_DELETED );
            $ok = $kfr->PutDBRow();
        }
        return( $ok );
    }

    function AddUserToGroup( $kUser, $kGroup )
    /*****************************************
        If the user is in the group, return true
        If gid1 is 0, set it to kGroup
        else add a row to UsersXGroups
     */
    {
        $ok = false;

        $kfrU = $this->kfrelUsers()->GetRecordFromDBKey( $kUser );
        $kfrG = $this->kfrelGroups()->GetRecordFromDBKey( $kGroup );
        if( !$kfrU || !$kfrG ) return( false );

        $raGroups = $this->GetGroupsFromUser( $kUser );
        if( in_array( $kGroup, $raGroups ) ) {
            return( true );
        }

        if( !$kfrU->Value('gid1') ) {
            $kfrU->SetValue( 'gid1', $kGroup );
            $ok = $kfrU->PutDBRow();
        } else {
            $kfr = $this->kfrelUsersXGroups()->CreateRecord();
            $kfr->SetValue( 'uid', $kUser );
            $kfr->SetValue( 'gid', $kGroup );
            $ok = $kfr->PutDBRow();
        }

        return( $ok );
    }

    private function kfrelUsers()
    /****************************
        This relation returns a single row because the left join only matches one row, populated or not
     */
    {
        if( !$this->kfrelUsers ) {
            $kfreldef = array( "Tables" => array(
                    "U" => array( "Table" => "{$this->sDB}SEEDSession_Users",
                                  "Type"  => 'Base',
                                  "Fields" => array( array("col"=>"realname",    "type"=>"S"),
                                                     array("col"=>"email",       "type"=>"S"),
                                                     array("col"=>"password",    "type"=>"S"),
                                                     array("col"=>"lang",        "type"=>"S"),
                                                     array("col"=>"gid1",        "type"=>"I"),
                                                     array("col"=>"eStatus",     "type"=>"S"),
                                                     array("col"=>"sExtra",      "type"=>"S"),
                                                     ) ),
                    "G" => array( "Table" => "{$this->sDB}SEEDSession_Groups",
                                  "Type"  => "LeftJoin",
                                  "JoinOn" => "U.gid1=G._key",
                                  "Fields" => array( array("col"=>"groupname",   "type"=>"S"),
                    ) ) ) );
            $this->kfrelUsers = $this->newKfrel( $kfreldef );
        }
        return( $this->kfrelUsers );
    }

    private function kfrelGroups()
    {
        if( !$this->kfrelGroups ) {
            $kfreldef = array( "Tables" => array(
                    "G" => array( "Table" => "{$this->sDB}SEEDSession_Groups",
                                  "Type"  => 'Base',
                                  "Fields" => array( array("col"=>"groupname",   "type"=>"S" )
                    ) ) ) );
            $this->kfrelGroups = $this->newKfrel( $kfreldef );
        }
        return( $this->kfrelGroups );
    }

    private function kfrelUsersXGroups()
    {
        if( !$this->kfrelUsersXGroups ) {
            $kfreldef = array( "Tables" => array(
                    "UG" => array( "Table" => "{$this->sDB}SEEDSession_UsersXGroups",
                    "Type"  => 'Base',
                    "Fields" => array( array("col"=>"uid", "type"=>"I"),
                                       array("col"=>"gid", "type"=>"I")
                    ) ) ) );
            $this->kfrelUsersXGroups = $this->newKfrel( $kfreldef );
        }
        return( $this->kfrelUsersXGroups );
    }

    private function kfrelPerms()
    /****************************
        This relation returns one row per Perms record, with single-row info left joined from other tables
     */
    {
        if( !$this->kfrelPerms ) {
            $kfreldef = array( "Tables" => array(
                    "P" => array( "Table" => "{$this->sDB}SEEDSession_Perms",
                                  "Type"  => 'Base',
                                  "Fields" => array( array("col"=>"perm",   "type"=>"S"),
                                                     array("col"=>"modes",  "type"=>"S"),
                                                     array("col"=>"uid",    "type"=>"I"),
                                                     array("col"=>"gid",    "type"=>"I"),
                                  ) ),
                    "U" => array( "Table" => "{$this->sDB}SEEDSession_Users",
                                  "Type"  => "LeftJoin",
                                  "JoinOn" => "P.uid=U._key",
                                  "Fields" => array( array("col"=>"realname",   "type"=>"S"),
                                                     array("col"=>"email",      "type"=>"S"),
                                  ) ),
                    "G" => array( "Table" => "{$this->sDB}SEEDSession_Groups",
                                  "Type"  => "LeftJoin",
                                  "JoinOn" => "P.gid=G._key",
                                  "Fields" => array( array("col"=>"groupname",   "type"=>"S"),
                    ) ) ) );
            $this->kfrelPerms = $this->newKfrel( $kfreldef );
        }
        return( $this->kfrelPerms );
    }

    private function kfrelUsersMetadata()
    {
        if( !$this->kfrelUsersMetadata ) {
            $kfreldef = array( "Tables" => array(
                    "UM" => array( "Table" => "{$this->sDB}SEEDSession_UsersMetadata",
                                   "Type"  => 'Base',
                                   "Fields" => array( array("col"=>"uid", "type"=>"K" ),
                                                      array("col"=>"k",   "type"=>"S" ),
                                                      array("col"=>"v",   "type"=>"S" ),
                    ) ) ) );
            $this->kfrelUsersMetadata = $this->newKfrel( $kfreldef );
        }
        return( $this->kfrelUsersMetadata );
    }

    private function kfrelGroupsMetadata()
    {
        if( !$this->kfrelGroupsMetadata ) {
            $kfreldef = array( "Tables" => array(
                    "GM" => array( "Table" => "{$this->sDB}SEEDSession_GroupsMetadata",
                                   "Type"  => 'Base',
                                   "Fields" => array( array("col"=>"gid", "type"=>"K" ),
                                                      array("col"=>"k",   "type"=>"S" ),
                                                      array("col"=>"v",   "type"=>"S" ),
                    ) ) ) );
            $this->kfrelGroupsMetadata = $this->newKfrel( $kfreldef );
        }
        return( $this->kfrelGroupsMetadata );
    }

    private function newKfrel( $kfreldef )
    {
        return( new Keyframe_Relation( $this->kfdb, $kfreldef, $this->uidOwnerOrAdmin, array('logfile'=>SITE_LOG_ROOT."seedsessionauth.log") ) );
    }
}


class SEEDSessionAccountDBRead2 extends Keyframe_NamedRelations
/*****************************
    DB access layer for Users, UsersMetadata, Groups, GroupsMetadata, UsersXGroups, Perms
 */
{
    protected $sDB = "";

    function __construct( KeyframeDatabase $kfdb, int $uidOwnerOrAdmin = 0, array $raConfig = array() )
    /**************************************************************************************************
        uidOwnerOrAdmin is the uid of the person altering UGP (either the owner of the account or an admin altering someone's account)
            It is only used for Keyframe._created/updated_by so it can be 0 if only reading
     */
    {
        $logdir = @$raConfig['logdir'];          // only used for Keyframe writes so it can be empty if only reading
        if( ($sDB = @$raConfig['dbname']) ) {    // allows UGP management of other databases, can be empty for current db
            // Prepended to every database table reference
            $this->sDB = $sDB.".";
        }
        parent::__construct( $kfdb, $uidOwnerOrAdmin, $logdir );
    }

    function GetEmail( $kUser )
    /**************************
        Often you know someone's kUser and you just want to show their email id
     */
    {
// todo: use named relation
        $email = $this->GetKFDB()->Query1( "SELECT email FROM {$this->sDB}SEEDSession_Users WHERE _key='".addslashes($kUser)."'" );
        return( $email );
    }

    function GetEmailRA( $raKUser, $bDetail = true )
    /***********************************************
        Like GetEmail() but for an array.
        Given an array of kUser, return one of the following:
            $bDetail:  array of kUser=>email
           !$bDetail:  array of email
     */
    {
// todo: use named relation
        $cond = SEEDCore_MakeRangeStrDB( $raKUser, "_key" );
        // the rest is not implemented?
    }

    function GetKUserFromEmail( $email )
    /***********************************
        Reverse of GetEmail(), because this is often used
     */
    {
// todo: use named relation
        $kUser = $this->GetKFDB()->Query1( "SELECT _key FROM {$this->sDB}SEEDSession_Users WHERE _status='0' AND email='".addslashes($email)."'" );
        return( intval($kUser) );
    }

    function GetKUserFromEmailRA( $raEmail, $bDetail = true )
    /********************************************************
        Like GetKUserFromEmail() but for an array.
        Given an array of emails, return one of the following:
            $bDetail:  array of kUser=>email
           !$bDetail:  array of email
     */
    {
        $raRet = array();

        foreach( $raEmail as $email ) {
            if( ($k = $this->GetKUserFromEmail( $email )) ) {
                if( $bDetail ) {
                    $raRet[$k] = $email;
                } else {
                    $raRet[] = $k;
                }
            }
        }
        return( $raRet );
    }


    function GetUserInfo( $userid, $bGetMetadata = true, $bIncludeDeletedAndHidden = false )
    /***************************************************************************************
       Retrieve a SEEDSession_Users row and its metadata

       userid can be kUser or email (email only works for _status='0' to prevent conflicts with old records)
     */
    {
        if( is_numeric($userid) ) {
            $cond = "_key='$userid'".($bIncludeDeletedAndHidden ? "" : " AND _status='0'");
        } else {
            // don't look for deleted/hidden user rows by email because there can be duplicates
            $cond = "email='".addslashes($userid)."' AND _status='0'";
        }

// todo: use named relation
        $raUser = $this->GetKFDB()->QueryRA( "SELECT * FROM {$this->sDB}SEEDSession_Users WHERE $cond" );
        $raMetadata = array();

        // $k is an unambiguous return value for testing success
        $k = intval(@$raUser['_key']);
        if( $k && $bGetMetadata ) {
            $raMetadata = $this->GetUserMetadata( $k );
        }
        return( array($k, $raUser, $raMetadata) );
    }

    function GetAllUsers( $cond, $raParms = [] )
    /*******************************************
     */
    {
        return( $this->getUsers( "SELECT [[cols]] FROM {$this->sDB}SEEDSession_Users U WHERE [[statusCond]]".($cond ? " AND ($cond)" : ""), $raParms ) );
    }

    function GetUsersFromGroup( $kGroup, $raParms = array() )
    /********************************************************
        Return the list of users that belong to kGroup: gid1 + UsersXGroups ; also users that belong to descendant groups of kGroup

        raParms:
            _status = kf status (-1 means all)
            eStatus = {list of eStatus codes to include}  e.g. 'INACTIVE','PENDING'
                      default: eStatus IN ('ACTIVE')
            bDetail = return array of uid=>array( user data ) -- default for historical reasons
           !bDetail = return array of uids

            bDoNotIncludeDescendantGroups = (default false) skip the group inheritance and just use gid1 + UsersXGroups to find users
     */
    {
        // Get all descendants of the given group, unless we're told not to
        $raGroups = array( $kGroup );
        if( !@$raParms['bDoNotIncludeDescendantGroups'] ) {
            $raGroups = $this->getGroupDescendants( $raGroups );
        }

        // For each of those groups, get the users associated with gid1 and UsersXGroups
        $sql = "SELECT [[cols]] FROM {$this->sDB}SEEDSession_Users U "
                ."WHERE ".SEEDCore_MakeRangeStrDB( $raGroups, "U.gid1" )." AND [[statusCond]] "
              ."UNION "
              ."SELECT [[cols]] FROM {$this->sDB}SEEDSession_UsersXGroups UG,{$this->sDB}SEEDSession_Users U "
                ."WHERE ".SEEDCore_MakeRangeStrDB( $raGroups, "UG.gid" )." AND UG.uid=U._key AND [[statusCond]]";

        return( $this->getUsers( $sql, $raParms ) );
    }

    function GetUsersFromMetadata( $sK, $sVCond, $raParms = array() )
    /****************************************************************
        Return an array of users whose metadata for key $sK is given by condition sVCond
        Metadata fields in sVCond must be prefaced by "UM."
            e.g. sK=foo, sVCond="UM.v='bar'"     returns users who have metadata foo=bar
                 sK=foo, sVCond="UM.v<>'bar'"    returns users who don't have metadata foo=bar including where foo is undefined
                 sk=foo, sVCond="UM.v is null"   means foo equals null or foo is undefined
                 sk=foo, sVCond="UM.uid is null" unambiguously means foo is undefined

        raParms: same as GetUsersFromGroup
     */
    {
        $sql = "SELECT [[cols]] FROM {$this->sDB}SEEDSession_Users U LEFT JOIN {$this->sDB}SEEDSession_UsersMetadata UM "
                ."ON (U._key=UM.uid AND UM.k='$sK') "
                ."WHERE ($sVCond) AND [[statusCond]]";

        return( $this->getUsers( $sql, $raParms ) );
    }

    private function getUsers( $sql, $raParms = array() )
    {
        $raRet = array();

        // sql must contain [[cols]] which is replaced by the column list
        $sql = str_replace( "[[cols]]", "U._key as _key,U.email as email,U.realname as realname,U.eStatus as eStatus", $sql );

        // sql must contain [[statusCond]] which is replaced by the status conditions
        $st  = SEEDCore_ArraySmartVal1( $raParms, '_status', 0 );           // empty is not a valid value
        $est = SEEDCore_ArraySmartVal1( $raParms, 'eStatus', "'ACTIVE'" );
        $sCondStatus = "U.eStatus IN ($est)"
                      .($st == -1 ? "" : " AND U._status='$st'");
        $sql = str_replace( "[[statusCond]]", $sCondStatus, $sql );

        $bDetail = SEEDCore_ArraySmartVal( $raParms, 'bDetail', array(true,false) );

        if( ($dbc = $this->GetKFDB()->CursorOpen( $sql )) ) {
            while( $ra = $this->GetKFDB()->CursorFetch( $dbc ) ) {
                if( $bDetail ) {
                    $raRet[$ra['_key']] = array('email'=>$ra['email'],'realname'=>$ra['realname'],'eStatus'=>$ra['eStatus']);
                } else {
                    $raRet[] = $ra['_key'];
                }
            }
            $this->GetKFDB()->CursorClose( $dbc );
        }
        return( $raRet );
    }

    function GetAllGroups( $cond, $raParms = [] )
    /********************************************
        Return the list of all groups that meet the conditions
     */
    {
        return( $this->getGroups( $cond, $raParms, "ALL" ) );
    }

    function GetGroupsFromUser( $kUser, $raParms = [] )
    /**************************************************
        Return the list of groups in which kUser is a member: gid1 + UsersXGroups + their inherited groups
     */
    {
        return( $this->getGroups( "", $raParms, $kUser ) );
    }

    private function getGroups( $cond, $raParms, $userOrAll )
    /********************************************************
        raParms:
            _status = default 0 (-1 means all)
            bNames  = Return array of kGroup=>groupname
           !bNames  = Return array of kGroup  (default)
     */
    {
        $raRet = [];

        $st = SEEDCore_ArraySmartVal1( $raParms, '_status', 0 );           // default 0, empty is not a valid value
        $cond = "(".($cond ?: "1=1").")"
               .($st != -1 ? " AND _status='$st'" : "");

        $bNames = intval(@$raParms['bNames']);

        if( $userOrAll == "ALL" ) {
            if( $bNames ) {
                $raGroups = $this->KFDB()->QueryRowsRA( "SELECT _key,groupname FROM {$this->sDB}SEEDSession_Groups WHERE $cond" );
                foreach( $raGroups as $ra ) {
                    $raRet[$ra['_key']] = $ra['groupname'];
                }
            } else {
                $raRet = $this->KFDB()->QueryRowsRA1( "SELECT _key FROM {$this->sDB}SEEDSession_Groups WHERE $cond" );
            }
        } else if( ($kUser = intval($userOrAll)) ) {
            $raGroups = $this->KFDB()->QueryRowsRA1(
            // Get the user's primary group
                   "SELECT gid1 FROM {$this->sDB}SEEDSession_Users WHERE _key='$kUser' AND gid1<>0 AND $cond "
                   ."UNION "
            // And the groups mapped to the user
                   ."SELECT gid FROM {$this->sDB}SEEDSession_UsersXGroups WHERE uid='$kUser' AND $cond" );
            // And their inherited groups
            $raGroups = $this->getGroupAncestors( $raGroups );

            if( $bNames ) {
                foreach( $raGroups as $gid ) {
                    $raRet[$gid] = $this->KFDB()->Query1( "SELECT groupname FROM {$this->sDB}SEEDSession_Groups WHERE _key='$gid'" );
                }
            } else {
                $raRet = $raGroups;
            }
        }

        asort( $raRet );  // sort by array value, maintaining key association if bNames
        return( $raRet );
    }

    function GetUserMetadata( $kUser, $bAndGroupMetadata = false )
    /*************************************************************
        bAndGroupMetadata == false: just get the metadata associated with the user (good for UI and R/W applications)
                          == true:  combine with the metadata for the user's groups (good for R/O applications)
     */
    {
        $raMetadata = array();

        // Get group metadata first so user metadata keys overwrite it
        if( $bAndGroupMetadata ) {
            $raMetadata = $this->GetGroupMetadataByUser( $kUser );
        }

// todo: use named relation
        $ra = $this->GetKFDB()->QueryRowsRA( "SELECT k,v FROM {$this->sDB}SEEDSession_UsersMetadata WHERE _status='0' AND uid='$kUser'" );
        // ra is array( array( k=>keyname1, v=>value1 ), array( k=>keyname2, v=>value2 ) )
        foreach( $ra as $ra2 ) {
            $raMetadata[$ra2['k']] = $ra2['v'];
        }
        return( $raMetadata );
    }

    function GetGroupMetadata( $kGroup, $raParms = array() )
    /*******************************************************
        raParms:
            bDoNotIncludeAncestorGroups = (default false) skip the group inheritance
     */
    {
        // Get all ancestors of the given group, unless we're told not to
        $raGroups = array( $kGroup );
        if( !@$raParms['bDoNotIncludeAncestorGroups'] ) {
            $raGroups = $this->getGroupAncestors( $raGroups );
        }

        $raMetadata = array();

// todo: use named relation
        $ra = $this->GetKFDB()->QueryRowsRA(
                "SELECT k,v FROM {$this->sDB}SEEDSession_GroupsMetadata "
               ."WHERE _status='0' AND ".SEEDCore_MakeRangeStrDB( $raGroups, "gid" ) );

        // ra is array( array( k=>keyname1, v=>value1 ), array( k=>keyname2, v=>value2 ) )
        foreach( $ra as $ra2 ) {
            $raMetadata[$ra2['k']] = $ra2['v'];
        }
        return( $raMetadata );
    }

    function GetGroupMetadataByUser( $kUser )
    /****************************************
     */
    {
        $raGroups = $this->GetGroupsFromUser( $kUser );

        $raMetadata = array();
        $ra = $this->GetKFDB()->QueryRowsRA(
            "SELECT G.k as k,G.v as v FROM {$this->sDB}SEEDSession_GroupsMetadata G,{$this->sDB}SEEDSession_UsersXGroups X "
                ."WHERE G._status='0' AND X._status='0' "
                ."AND G.gid=X.gid AND X.uid='$kUser' "
           ."UNION "
           ."SELECT G.k as k,G.v as v FROM {$this->sDB}SEEDSession_GroupsMetadata G,{$this->sDB}SEEDSession_Users U "
                ."WHERE G._status='0' AND U._status='0' "
                ."AND G.gid=U.gid1 AND U._key='$kUser'" );
        // ra is array( array( k=>keyname1, v=>value1 ), array( k=>keyname2, v=>value2 ) )
        foreach( $ra as $ra2 ) {
            $raMetadata[$ra2['k']] = $ra2['v'];
        }
        return( $raMetadata );
    }


    function GetPermsFromUser( $kUser )
    /**********************************
        Get the given user's permissions from perms and group-perms.

            e.g. for perms  'app1','R'
                            'app2','W'  & group 'app2','R'
                            'app3','RW' & group 'app3'=>'RWA'

                 'perm2modes' => array( 'app1'=>'R', 'app2'=>'RW', 'app3'=>'RWA' ),
                 'mode2perms' => array( 'R'=>array('app1','app2','app3'), 'W'=>array('app2','app3'), 'A'=>array('app3') )
     */
    {
        $raGroups = $this->GetGroupsFromUser( $kUser );

        // Get perms explicitly for this uid
        $sql = "SELECT perm,modes FROM SEEDSession_Perms WHERE _status='0' AND uid='$kUser'";
        if( count($raGroups) ) {
            // And perms for its groups and inherited groups
            $sql .= " UNION "
                   ."SELECT P.perm AS perm, P.modes as modes "
                   ."FROM SEEDSession_Perms P WHERE P._status='0' AND ".SEEDCore_MakeRangeStrDB( $raGroups, "P.gid" );
        }

        return( $this->getPermsList( $sql ) );
    }

    function GetPermsFromGroup( $kGroup )
    /************************************
        Get the given group's permissions in the same format as GetPermsFromUserKey
     */
    {
        // list all groups inherited by kGroup, including kGroup, and find the perm/modes of those groups.
        $raGroups = $this->getGroupAncestors( array($kGroup) );
        $sqlGroupRange = SEEDCore_MakeRangeStrDB( $raGroups, "P.gid" );
        return( $this->getPermsList( "SELECT P.perm AS perm, P.modes as modes FROM SEEDSession_Perms P "
                                    ."WHERE P._status='0' AND $sqlGroupRange" ) );
    }

    private function getPermsList( $sql )
    {
        $raRet = array( 'perm2modes' => array(),
                        'mode2perms' => array( 'R'=>array(), 'W'=>array(), 'A'=>array() ) );

        if( ($dbc = $this->GetKFDB()->CursorOpen( $sql )) ) {
            while( $ra = $this->GetKFDB()->CursorFetch( $dbc ) ) {
                if( strchr($ra['modes'],'R') && !in_array($ra['perm'], $raRet['mode2perms']['R']) ) { $raRet['mode2perms']['R'][] = $ra['perm']; }
                if( strchr($ra['modes'],'W') && !in_array($ra['perm'], $raRet['mode2perms']['W']) ) { $raRet['mode2perms']['W'][] = $ra['perm']; }
                if( strchr($ra['modes'],'A') && !in_array($ra['perm'], $raRet['mode2perms']['A']) ) { $raRet['mode2perms']['A'][] = $ra['perm']; }
            }
            $this->GetKFDB()->CursorClose( $dbc );
        }
        foreach( $raRet['mode2perms']['R'] as $p ) { $raRet['perm2modes'][$p]  = "R"; }
        foreach( $raRet['mode2perms']['W'] as $p ) { @$raRet['perm2modes'][$p] .= "W"; } // the @ prevents concatenation warning if R is not set
        foreach( $raRet['mode2perms']['A'] as $p ) { @$raRet['perm2modes'][$p] .= "A"; }

        return( $raRet );
    }

    private function getGroupAncestors( $raGroups )
    /**********************************************
        Return the set of groups inherited by the given groups. Include the given groups in the result.

        Array( gid1, gid2, ... ) is input and output.
     */
    {
        $raOut = array();

        // Check each group for a gid_inherited. If set, add it to the list and check it for an ancestor too.
        // Use array_shift instead of foreach so we can add to the raGroups list (foreach acts on its own copy of the array).
        // Prevent circular lookups by testing whether raOut already contains the group being processed.
        // Use isset with keys to make this test efficient.
        while( ($gid = array_shift($raGroups)) ) {
            if( isset($raOut[$gid]) )  continue;
            $raOut[$gid] = 1;

            if( ($kfr = $this->GetKFR( "G", $gid )) ) {
                if( $kfr->Value('gid_inherited') ) {
                    $raGroups[] = $kfr->Value('gid_inherited');
                }
            }
        }

        return( array_keys( $raOut ) );
    }

    private function getGroupDescendants( $raGroups )
    /************************************************
        Return the set of groups that inherit the given groups. Include the given groups in the result.

        Array( gid1, gid2, ... ) is input and output.
     */
    {
        $raOut = array();

        // For each group, fetch any groups that have that group as their gid_inherited. Repeat for those groups.
        // See getGroupAncestors() for comments on this implementation
        while( ($gid = array_shift($raGroups)) ) {
            if( isset($raOut[$gid]) )  continue;
            $raOut[$gid] = 1;

            $raDesc = $this->GetList( "G", "gid_inherited='$gid'" );
            foreach( $raDesc as $desc ) {
                $raGroups[] = $desc['_key'];
            }
        }

        return( array_keys( $raOut ) );
    }

    function GetSessionHashSeed()
    /****************************
        For security operations that involve a publicly transmitted hash based on some public data + some non-public data, this is the non-public data.
        It is generated per-server and stored in the StringBucket so even though you're reading this code right now, you still can't hack it.
     */
    {
/*
        $oSB = new SEEDMetaTable_StringBucket( $this->kfdb );
        if( !($hashSeed = $oSB->GetStr( "SEEDSession", "hashSeed" )) ) {    // first time this is called on this server; create the hashSeed and return it forever
            $hashSeed = rand();
            $oSB->PutStr( "SEEDSession", "hashSeed", $hashSeed );
        }
        return( $hashSeed );
*/
    }


    function initKfrel( KeyframeDatabase $kfdb, $uid, $logdir )
    /**********************************************************
        Override Keyframe_NamedRelation's method for generating the kfrels for this class
     */
    {
        $fld_G = array( array("col"=>"groupname",     "type"=>"S"),
                        array("col"=>"gid_inherited", "type"=>"I")
        );

        $kfreldef_U = array( "Tables" => array(
                "U" => array( "Table" => "{$this->sDB}SEEDSession_Users",
                              "Type"  => 'Base',
                              "Fields" => array( array("col"=>"realname",    "type"=>"S"),
                                                 array("col"=>"email",       "type"=>"S"),
                                                 array("col"=>"password",    "type"=>"S"),
                                                 array("col"=>"lang",        "type"=>"S"),
                                                 array("col"=>"gid1",        "type"=>"I"),
                                                 array("col"=>"eStatus",     "type"=>"S"),
                                                 array("col"=>"sExtra",      "type"=>"S"),
                                               ) ),
                "G" => array( "Table" => "{$this->sDB}SEEDSession_Groups",
                              "Type"  => "LeftJoin",
                              "JoinOn" => "U.gid1=G._key",
                              "Fields" => $fld_G
                ) ) );

        $kfreldef_G = array( "Tables" => array(
                "G" => array( "Table" => "{$this->sDB}SEEDSession_Groups",
                              "Type"  => 'Base',
                              "Fields" => $fld_G
                ) ) );

        $kfreldef_UxG = array( "Tables" => array(
                "UxG" => array( "Table" => "{$this->sDB}SEEDSession_UsersXGroups",
                                "Type"  => 'Base',
                                "Fields" => array( array("col"=>"uid", "type"=>"I"),
                                                   array("col"=>"gid", "type"=>"I")
                ) ) ) );

        $kfreldef_P = array( "Tables" => array(
                "P" => array( "Table" => "{$this->sDB}SEEDSession_Perms",
                              "Type"  => 'Base',
                              "Fields" => array( array("col"=>"perm",   "type"=>"S"),
                                                 array("col"=>"modes",  "type"=>"S"),
                                                 array("col"=>"uid",    "type"=>"I"),
                                                 array("col"=>"gid",    "type"=>"I"),
                              ) ),
                "U" => array( "Table" => "{$this->sDB}SEEDSession_Users",
                              "Type"  => "LeftJoin",
                              "JoinOn" => "P.uid=U._key",
                              "Fields" => array( array("col"=>"realname",   "type"=>"S"),
                                                 array("col"=>"email",      "type"=>"S"),
                              ) ),
                "G" => array( "Table" => "{$this->sDB}SEEDSession_Groups",
                              "Type"  => "LeftJoin",
                              "JoinOn" => "P.gid=G._key",
                              "Fields" => $fld_G
                ) ) );

        $kfreldef_UM = array( "Tables" => array(
                "UM" => array( "Table" => "{$this->sDB}SEEDSession_UsersMetadata",
                               "Type"  => 'Base',
                               "Fields" => array( array("col"=>"uid", "type"=>"K" ),
                                                  array("col"=>"k",   "type"=>"S" ),
                                                  array("col"=>"v",   "type"=>"S" ),
                ) ) ) );

        $kfreldef_GM = array( "Tables" => array(
                "GM" => array( "Table" => "{$this->sDB}SEEDSession_GroupsMetadata",
                               "Type"  => 'Base',
                               "Fields" => array( array("col"=>"gid", "type"=>"K" ),
                                                  array("col"=>"k",   "type"=>"S" ),
                                                  array("col"=>"v",   "type"=>"S" ),
                ) ) ) );

        $def_ML = ['Tables' => [
                'ML' => ['Table' => "{$this->sDB}SEEDSession_MagicLogin",
                         'Fields' => [['col'=>"name",       'type'=>'S'],
                                      ['col'=>"magic_str",  'type'=>'S'],
                                      ["col"=>"uid",        'type'=>'I'],
                                      ["col"=>"perms",      'type'=>'S'],
                                      ["col"=>"notes",      'type'=>'S'],
                                      ["col"=>"sess_parms", 'type'=>'S'],
                                      ["col"=>"ts_expiry",  'type'=>'S'],
                                      ["col"=>"nLimit",     'type'=>'I'],
            ]]]];

        $raKfrel = array();
        $parms = $logdir ? array('logfile'=>$logdir."seedsessionaccount.log") : array();
        // This relation returns a single row because the left join only matches one row, populated or not
        $raKfrel['U']   = new Keyframe_Relation( $kfdb, $kfreldef_U,   $uid, $parms );
        $raKfrel['G']   = new Keyframe_Relation( $kfdb, $kfreldef_G,   $uid, $parms );
        $raKfrel['UxG'] = new Keyframe_Relation( $kfdb, $kfreldef_UxG, $uid, $parms );
        $raKfrel['UM']  = new Keyframe_Relation( $kfdb, $kfreldef_UM,  $uid, $parms );
        $raKfrel['GM']  = new Keyframe_Relation( $kfdb, $kfreldef_GM,  $uid, $parms );
        // This relation returns one row per Perms record, with single-row info left joined from other tables
        $raKfrel['P']   = new Keyframe_Relation( $kfdb, $kfreldef_P,   $uid, $parms );

        $raKfrel['ML']  = new Keyframe_Relation( $kfdb, $def_ML,  $uid, $parms );

        return( $raKfrel );
    }
}

class SEEDSessionAccountDB2 extends SEEDSessionAccountDBRead2
/*************************
    DB write layer for UGP
 */
{
    private $uidOwnerOrAdmin;   // the user who is making changes to the UGP

    function __construct( KeyframeDatabase $kfdb, int $uidOwnerOrAdmin, array $raConfig = [] )
    {
        parent::__construct( $kfdb, $uidOwnerOrAdmin, $raConfig );
        $this->uidOwnerOrAdmin = $uidOwnerOrAdmin;
    }

    function CreateUser( $sEmail, $sPwd, $raParms = array() )
    /********************************************************
        raParms: k, eStatus, realname, sExtra, lang, gid1
     */
    {
        $kUser       = intval(@$raParms['k']);      // 0 means use the next auto-increment
        $sdbEmail    = addslashes($sEmail);
        $sdbPwd      = addslashes($sPwd);
        $sdbRealname = addslashes(@$raParms['realname']);
        $sdbExtra    = addslashes(@$raParms['sExtra']);
        $eStatus     = SEEDCore_ArraySmartVal( $raParms, 'eStatus', array('PENDING','ACTIVE','INACTIVE') );
        $eLang       = SEEDCore_ArraySmartVal( $raParms, 'lang', array('E','F','B') );
        $gid1        = intval(@$raParms['gid1']);

// todo: use named relation
        $k = $this->GetKFDB()->Query1("SELECT _key FROM {$this->sDB}SEEDSession_Users WHERE email='$sdbEmail' and _status='0'");
        if( !$k ) {
            // return value is what you expect, whether k is 0 or non-zero
            $k = $this->GetKFDB()->InsertAutoInc(
                    "INSERT INTO {$this->sDB}SEEDSession_Users "
                    ."(_key,_created,_created_by,_updated,_updated_by,_status,email,password,realname,eStatus,sExtra,lang,gid1) "
                   ."VALUES ($kUser,NOW(),{$this->uidOwnerOrAdmin},NOW(),{$this->uidOwnerOrAdmin},0,"
                   ."'$sdbEmail','$sdbPwd','$sdbRealname','$eStatus','$sdbExtra','$eLang','$gid1')" );
        }
        return( $k );
    }

    function ActivateUser( $kUser )
    /******************************
        Activate an existing login that is INACTIVE or PENDING, or deleted or hidden
     */
    {
        $bOk = false;

        if( ($kfr = $this->GetKfrel('U')->GetRecordFromDBKey( $kUser )) ) {
            $kfr->StatusSet( KeyframeRecord::STATUS_NORMAL );    // if the account has been deleted or hidden, undelete it
            $kfr->SetValue( 'eStatus', 'ACTIVE' );        // if the account is INACTIVE, activate it

            $bOk = $kfr->PutDBRow();
        }

        return( $bOk );
    }


    function ChangeUserPassword( $kUser, $sPwd )
    {
        $kUser = intval($kUser);
        $sdbPwd = addslashes($sPwd);

        return( $kUser ? $this->GetKFDB()->Execute( "UPDATE {$this->sDB}.SEEDSession_Users SET password='$sdbPwd' WHERE _key='$kUser'" ) : false );
    }

    function SetUserMetadata( $kUser, $k, $v )
    /*****************************************
     */
    {
        $ok = false;

        $kfrelUM = $this->GetKfrel('UM');

        // Fetch iStatus==-1 so any deleted or hidden records are found, and replaced with the new metadata
        if( !($kfr = $kfrelUM->GetRecordFromDB( "uid='$kUser' AND k='".addslashes($k)."'", array('iStatus'=>-1) )) ) {
            $kfr = $kfrelUM->CreateRecord();
        }
        if( $kfr ) {
            $kfr->SetValue( 'uid', $kUser );
            $kfr->SetValue( 'k', $k );
            $kfr->SetValue( 'v', $v );
            $kfr->StatusSet( KeyframeRecord::STATUS_NORMAL );  // because maybe there's an old value that has been deleted or hidden
            $ok = $kfr->PutDBRow();
        }
        return( $ok );
    }

    function SetGroupMetadata( $kGroup, $k, $v )
    /*******************************************
     */
    {
        $ok = false;

        $kfrelGM = $this->GetKfrel('GM');

        // Fetch iStatus==-1 so any deleted or hidden records are found, and replaced with the new metadata
        if( !($kfr = $kfrelGM->GetRecordFromDB( "gid='$kGroup' AND k='".addslashes($k)."'", array('iStatus'=>-1) )) ) {
            $kfr = $kfrelGM->CreateRecord();
        }
        if( $kfr ) {
            $kfr->SetValue( 'gid', $kGroup );
            $kfr->SetValue( 'k', $k );
            $kfr->SetValue( 'v', $v );
            $kfr->StatusSet( KeyframeRecord::STATUS_NORMAL );  // because maybe there's an old value that has been deleted or hidden
            $ok = $kfr->PutDBRow();
        }
        return( $ok );
    }

    function DeleteUserMetadata( $kUser, $k )
    /****************************************
     */
    {
        $ok = false;

        $kfrelUM = $this->GetKfrel('UM');

        // Fetch iStatus==-1 so any hidden records are found, and replaced with the DELETED status
        if( ($kfr = $kfrelUM->GetRecordFromDB( "uid='$kUser' AND k='".addslashes($k)."'", array('iStatus'=>-1) )) ) {
            $kfr->StatusSet( KeyframeRecord::STATUS_DELETED );
            $ok = $kfr->PutDBRow();
        }
        return( $ok );
    }

    function DeleteGroupMetadata( $kGroup, $k )
    /******************************************
     */
    {
        $ok = false;

        $kfrelGM = $this->GetKfrel('GM');

        // Fetch iStatus==-1 so any hidden records are found, and replaced with the DELETED status
        if( ($kfr = $kfrelGM->GetRecordFromDB( "gid='$kGroup' AND k='".addslashes($k)."'", array('iStatus'=>-1) )) ) {
            $kfr->StatusSet( KeyframeRecord::STATUS_DELETED );
            $ok = $kfr->PutDBRow();
        }
        return( $ok );
    }

    function AddUserToGroup( $kUser, $kGroup )
    /*****************************************
        If the user is in the group, return true
        If gid1 is 0, set it to kGroup
        else add a row to UsersXGroups
     */
    {
        $ok = false;

        $kfrU = $this->GetKfrel('U')->GetRecordFromDBKey( $kUser );
        $kfrG = $this->GetKfrel('G')->GetRecordFromDBKey( $kGroup );
        if( !$kfrU || !$kfrG )  goto done;;

        $raGroups = $this->GetGroupsFromUser( $kUser );
        if( in_array( $kGroup, $raGroups ) ) {
            return( true );
        }

        if( !$kfrU->Value('gid1') ) {
            $kfrU->SetValue( 'gid1', $kGroup );
            $ok = $kfrU->PutDBRow();
        } else {
            $kfr = $this->GetKfrel('UxG')->CreateRecord();
            $kfr->SetValue( 'uid', $kUser );
            $kfr->SetValue( 'gid', $kGroup );
            $ok = $kfr->PutDBRow();
        }

        done:
        return( $ok );
    }

    function RemoveUserFromGroup( $kUser, $kGroup )
    /**********************************************
        If the user is not in the group, return true
        If gid1 is kGroup, set it to zero
        If kGroup is in one of the user's UsersXGroups rows, delete that row

        UsersXGroups rows are deleted permanently, not via _status, because otherwise
        they're weird to reinstate and there's not much reason to keep them
     */
    {
        $ok = false;

        $kfrU = $this->GetKfrel('U')->GetRecordFromDBKey( $kUser );
        $kfrG = $this->GetKfrel('G')->GetRecordFromDBKey( $kGroup );
        if( !$kfrU || !$kfrG )  goto done;;

        $raGroups = $this->GetGroupsFromUser( $kUser );
        if( !in_array( $kGroup, $raGroups ) ) {
            return( true );
        }

        if( $kfrU->Value('gid1') == $kGroup ) {
            $kfrU->SetValue( 'gid1', 0 );
            $ok = $kfrU->PutDBRow();
        }

        // do this even if gid1 matched because the group might be duplicated
        $this->KFDB()->Execute( "DELETE FROM {$this->sDB}SEEDSession_UsersXGroups WHERE uid='$kUser' AND gid='$kGroup'" );

        done:
        return( $ok );
    }
}


function SEEDSessionAccountDBCreateTables( $kfdb, $sDB )
{
    $kfdb->Execute( "use $sDB" );
    $kfdb->Execute( SEEDSESSION_DB_TABLE_SEEDSESSION );
    $kfdb->Execute( SEEDSESSION_DB_TABLE_SEEDSESSION_USERS );
    $kfdb->Execute( SEEDSESSION_DB_TABLE_SEEDSESSION_GROUPS );
    $kfdb->Execute( SEEDSESSION_DB_TABLE_SEEDSESSION_USERSXGROUPS );
    $kfdb->Execute( SEEDSESSION_DB_TABLE_SEEDSESSION_PERMS );
    $kfdb->Execute( SEEDSESSION_DB_TABLE_SEEDSESSION_USERS_METADATA );
    $kfdb->Execute( SEEDSESSION_DB_TABLE_SEEDSESSION_GROUPS_METADATA );
}

define("SEEDSESSION_DB_TABLE_SEEDSESSION",
"
CREATE TABLE IF NOT EXISTS SEEDSession (
    # Security zone: Sess
    #
    # Stores authenticated sessions.
    # A session is valid if sess_idstr appears here and time() < ts_expiry.
    # A uid is authenticated if uid appears here and time() < ts_expiry.
    # perm strings contain permission codes separated by spaces, and with spaces at beginning and end.

        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    sess_idstr  VARCHAR(200) NULL,      # such as the PHPSESSID
    uid         INTEGER,                # SEEDSession_Users._key
    realname    VARCHAR(200),
    email       VARCHAR(200),
    permsR      TEXT,
    permsW      TEXT,
    permsA      TEXT,
    ts_expiry   INTEGER,                # Unix time() + duration till expiry

    INDEX (sess_idstr),
    INDEX (uid)
);
"
);

define("SEEDSESSION_DB_TABLE_SEEDSESSION_USERS",
"
CREATE TABLE IF NOT EXISTS SEEDSession_Users (
    # Security zone: Auth
    #
    # List all users in the system.

        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    realname    VARCHAR(200),
    email       VARCHAR(200),
    password    VARCHAR(200),
    lang        ENUM ('E','F','B') NOT NULL DEFAULT 'E',
    gid1        INTEGER NOT NULL DEFAULT '0',         # the basic group for this user (since every user will be in at least one group)
    eStatus     ENUM ('PENDING','ACTIVE','INACTIVE') NOT NULL DEFAULT 'PENDING',
    sExtra      TEXT,
  # bEBull      INTEGER NOT NULL DEFAULT '0'

    INDEX (email)
);
"
);

define("SEEDSESSION_DB_TABLE_SEEDSESSION_GROUPS",
"
CREATE TABLE IF NOT EXISTS SEEDSession_Groups (
    # Security zone: Auth
    #
    # List all groups. Used only for group administration.

        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    groupname     VARCHAR(200),
    gid_inherited INTEGER NOT NULL DEFAULT '0'      # users in the group are also considered members of this group
);
"
);

define("SEEDSESSION_DB_TABLE_SEEDSESSION_USERSXGROUPS",
"
CREATE TABLE IF NOT EXISTS SEEDSession_UsersXGroups (
    # Security zone: Auth
    #
    # Which users are in which groups. Many-to-many intersection.

        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    uid     INTEGER,
    gid     INTEGER,

    INDEX (uid),
    INDEX (gid)
);
"
);

define("SEEDSESSION_DB_TABLE_SEEDSESSION_PERMS",
"
CREATE TABLE IF NOT EXISTS SEEDSession_Perms (
    # Security zone: Auth
    #
    # Which user and/or group has a given permission. uid and gid are independent, overlapping for convenience

        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    perm    VARCHAR(200),
    modes   VARCHAR(10),
    uid     INTEGER NULL,       # this uid has this permission
    gid     INTEGER NULL,       # this group has this permission

    INDEX (uid),
    INDEX (gid)
);
"
);

define("SEEDSESSION_DB_TABLE_SEEDSESSION_USERS_METADATA",
"
CREATE TABLE IF NOT EXISTS SEEDSession_UsersMetadata (
        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    uid    INTEGER NOT NULL,
    k      VARCHAR(200) NOT NULL DEFAULT '',
    v      TEXT NOT NULL,

    INDEX (uid),
    INDEX (k)
);
"
);

define("SEEDSESSION_DB_TABLE_SEEDSESSION_GROUPS_METADATA",
"
CREATE TABLE IF NOT EXISTS SEEDSession_GroupsMetadata (
        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    gid    INTEGER NOT NULL,
    k      VARCHAR(200) NOT NULL DEFAULT '',
    v      TEXT NOT NULL,

    INDEX (gid),
    INDEX (k)
);
"
);

define('SEEDSESSION_DB_TABLE_SEEDSESSION_MAGICLOGIN',
"
CREATE TABLE IF NOT EXISTS SEEDSession_MagicLogin (
        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    name        VARCHAR(200) NOT NULL,              # records can be named (optional at app level for looking up the _key)
    magic_str   VARCHAR(200) NOT NULL,              # arbitrary string for passcode/hashing/authentication of the magic link
    uid         INTEGER,                            # login as this user, unless uid specified in magic link
    perms       VARCHAR(200) NOT NULL DEFAULT '',   # with these perms (if blank, look up normal perms for the user)
    ts_expiry   INTEGER,                            # Unix time when this magic login stops working (0=no expiry)
    nLimit      INTEGER,                            # number of times this magic login will work (count down). -1 means unlimited
    sess_parms  TEXT,                               # optional parms to control the session
    notes       VARCHAR(200)                        # optional description of purpose
);
"
);
