<?php

/* SEEDSessionAccountDB
 *
 * Copyright 2006-2018 Seeds of Diversity Canada
 *
 * DB layer for SEEDSession Users, Groups, Perms, Metadata
 */

//include_once( "SEEDMetaTable.php" );    // StringBucket
include_once( SEEDROOT."Keyframe/KeyframeRelation.php" );

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
            $kfr->StatusSet( KEYFRAMERECORD_STATUS_NORMAL );    // if the account has been deleted or hidden, undelete it
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
            $kfr->StatusSet( KEYFRAMERECORD_STATUS_NORMAL );  // because maybe there's an old value that has been deleted or hidden
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
            $kfr->StatusSet( KEYFRAMERECORD_STATUS_NORMAL );  // because maybe there's an old value that has been deleted or hidden
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
            $kfr->StatusSet( KEYFRAMERECORD_STATUS_DELETED );
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
            $kfr->StatusSet( KEYFRAMERECORD_STATUS_DELETED );
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

    groupname   VARCHAR(200)
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

?>
