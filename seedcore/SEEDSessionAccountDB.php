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
        $k = 0; $raUser = []; $raMetadata = [];

        if( !$userid ) goto done;

        if( is_numeric($userid) ) {
            $cond = "_key='$userid'".($bIncludeDeletedAndHidden ? "" : " AND _status='0'");
        } else {
            // don't look for deleted/hidden user rows by email because there can be duplicates
            $cond = "email='".addslashes($userid)."' AND _status='0'";
        }

        $raUser = $this->kfdb->QueryRA( "SELECT * FROM {$this->sDB}SEEDSession_Users WHERE $cond" );

        // $k is an unambiguous return value for testing success
        $k = intval(@$raUser['_key']);
        if( $k && $bGetMetadata ) {
            $raMetadata = $this->GetUserMetadata( $k );
        }
        done:
        return( [$k, $raUser, $raMetadata] );
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

/**
 * DB access layer for reading Users, UsersMetadata, Groups, GroupsMetadata, UsersXGroups, Perms
 */
class SEEDSessionAccountDBRead2 extends Keyframe_NamedRelations {
    
    /**
     * Optional db prefix
     * @var string
     */
    protected $sDB = "";

    /**
     * @param KeyframeDatabase $kfdb db connection to use for managing Users, Metadata, Groups, and Perms
     * @param int $uidOwnerOrAdmin - uid of the person altering the UGP (either the owner of the account or an admin altering someone's account).
     * It is only used for Keyframe._created/update_by so it can be 0 of only reading
     * @param string $raConfig['dbname'] - Name of the db containing the UGP tables
     * @param string $raConfig['logdir'] - Location to log db changes. Only used for Keyframe writes so can be empty if only reading.
     */
    function __construct( KeyframeDatabase $kfdb, int $uidOwnerOrAdmin = 0, array $raConfig = array() )
    {
        $logdir = @$raConfig['logdir'];          // only used for Keyframe writes so it can be empty if only reading
        if( ($sDB = @$raConfig['dbname']) ) {    // allows UGP management of other databases, can be empty for current db
            // Prepended to every database table reference
            $this->sDB = $sDB.".";
        }
        parent::__construct( $kfdb, $uidOwnerOrAdmin, $logdir );
    }

    /**
     * Get the email for a user give their id
     * @param string|int $kUser - id of the user who's email to retrieve
     * @return string|NULL
     */
    function GetEmail(string|int $kUser) {
        $email = $this->GetKFDB()->Query1_prepared("SELECT email FROM {$this->sDB}SEEDSession_Users WHERE _key = ?;", [$kUser]);
        return $email;
    }

    /**
     * Get emails for all user id's in the array
     * @param array $raKUser - id's to get the emails for
     * @param bool $bDetail - whether to return associative array or not. Default: true
     * @return array - Associative array between id and email if $bDetail is true, otherwise only the emails are returned
     */
    function GetEmailRA(array $raKUser, bool $bDetail = true): array {
        $raEmails = $this->GetKFDB()->QueryRowsRA_prepared("SELECT email, _key FROM {$this->sDB}SEEDSession_Users WHERE _key IN (".implode(',', array_fill(0, count($raGroups), '?')).");", $raGroups);
        $raOut = [];
        foreach ($raEmails as $ra) {
            if ($bDetail) {
                $raOut[$ra['_key']] = $ra['email'];
            } else {
                $raOut[] = $ra['email'];
            }
        }
        return $raOut;
    }

    /**
     * Get a users id from their email
     * @param string $email - email to get the id for
     * @return int - id of the user, or 0 if one doesn't exist
     */
    function GetKUserFromEmail(string $email): int {
        $kUser = $this->GetKFDB()->Query1_prepared("SELECT _key FROM {$this->sDB}SEEDSession_Users WHERE _status = 0 AND email = ?", [$email]);
        return intval($kUser);
    }

    /**
     * Get id's for all user emails in the array
     * @param array $raKUser - emails to get the id's for
     * @param bool $bDetail - whether to return associative array or not. Default: true
     * @return array - Associative array between email and id if $bDetail is true, otherwise only the id's are returned
     */
    function GetKUserFromEmailRA(array $raEmail, bool $bDetail = true): array {
        $raIds = $this->GetKFDB()->QueryRowsRA_prepared("SELECT email, _key FROM {$this->sDB}SEEDSession_Users WHERE email IN (".implode(',', array_fill(0, count($raEmail), '?')).");", $raEmail);
        $raOut = [];
        foreach ($raIds as $ra) {
            if ($bDetail) {
                $raOut[$ra['email']] = $ra['_key'];
            } else {
                $raOut[] = $ra['_key'];
            }
        }
        return $raOut;
    }

    /**
     * Get a user's info and metadata
     * @param string|int $userIdOrEmail - id or email of the user
     * @param bool $bGetMetadata - whether or not to include the users metadata. Default: true
     * @param bool $bIncludeDeletedAndHidden - whether to look in all users, or just "normal" ones.
     * Only supported when using user id to prevent conflicts with old records. Default: false
     * @return array[] - array of 3 elements containing user data. First element is the users id,
     * second element is an array of user information, third element is an array of user metadata if requested
     */
    function GetUserInfo(string|int $userIdOrEmail, bool $bGetMetadata = true, bool $bIncludeDeletedAndHidden = false): array {
        $cond = '';

        if (is_numeric($userIdOrEmail)) {
            $cond = "_key = ?".($bIncludeDeletedAndHidden ? "" : " AND _status = 0");
        } else {
            // don't look for deleted/hidden user rows by email because there can be duplicates
            $cond = "email = ? AND _status = 0";
        }

        // Password excluded for security reasons
        $sql = "SELECT _key, _created, _created_by, _updated, _updated_by, _status, realname, email, lang, gid1, eStatus, sExtra FROM {$this->sDB}SEEDSession_Users WHERE {$cond};";
        $raUser = $this->GetKFDB()->QueryRA_prepared($sql, [$userIdOrEmail]);
        $raMetadata = array();

        $k = 0;
        if ($raUser) {
            $k = intval($raUser['_key']);
        }

        if ($k && $bGetMetadata) {
            $raMetadata = $this->GetUserMetadata($k);
        }
        return array($k, $raUser, $raMetadata);
    }

    /**
     * Get all users that match the given condition
     * @param string $cond - condition to test against
     * @param boolean $raParms['bDetail'] - whether to include user details in the result.
     * Defaults to true if not specified
     * @param int $raParms['_status'] - _status to filter by. Use -1 to disable.
     * Defaults to 0 (normal) if not specified
     * @param string $raParms['eStatus'] - comma separated string of user statuses to filter by.
     * Statuses should be one of 'ACTIVE', 'PENDING', or 'INACTIVE' and be wrapped in quotes.
     * Defaults to "'ACTIVE'" if not specified.
     * @return unknown[]|unknown[][]
     */
    function GetAllUsers( string $cond, array $raParms = [] ): array {
        return( $this->getUsers( "SELECT [[cols]] FROM {$this->sDB}SEEDSession_Users U WHERE [[statusCond]]".($cond ? " AND ($cond)" : ""), $raParms ) );
    }

    /**
     * Get the list of users belonging to the given group.
     * Also includes users who belong to groups that inherit from the given group by default.
     * @param string|int $kGroup - id of the group to get users of
     * @param boolean $raParms['bDetail'] - whether to include user details in the result.
     * Defaults to true if not specified
     * @param int $raParms['_status'] - _status to filter by. Use -1 to disable.
     * Defaults to 0 (normal) if not specified
     * @param string $raParms['eStatus'] - comma separated string of user statuses to filter by.
     * Statuses should be one of 'ACTIVE', 'PENDING', or 'INACTIVE' and be wrapped in quotes.
     * Defaults to "'ACTIVE'" if not specified.
     * @param boolean $raParms['bDoNotIncludeDescendantGroups'] - whether to skip including users belonging to groups that inherit from the given group.
     * Defauts to false if not provided.
     * @return unknown[]|unknown[][]
     */
    function GetUsersFromGroup( string|int $kGroup, array $raParms = array() ): array {
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

    /**
     * Get users who's metadata for a given key matches  the given condition.
     * @param string $sK - metadata key to use to retrieve users by
     * @param string $sVCond - contdition the users metadata needs to match for the user to be returned.
     * Metadata fields need to be prefixed with "UM." (eg. "UM.v" for the metadata value)
     * @param boolean $raParms['bDetail'] - whether to include user details in the result.
     * Defaults to true if not specified
     * @param int $raParms['_status'] - _status to filter by. Use -1 to disable.
     * Defaults to 0 (normal) if not specified
     * @param string $raParms['eStatus'] - comma separated string of user statuses to filter by.
     * Statuses should be one of 'ACTIVE', 'PENDING', or 'INACTIVE' and be wrapped in quotes.
     * Defaults to "'ACTIVE'" if not specified.
     * @return unknown[]|unknown[][]
     * @example sK=foo, sVCond="UM.v='bar'": returns users who have metadata foo=bar
     * @example sK=foo, sVCond="UM.v<>'bar'": returns users who don't have metadata foo=bar including where foo is undefined
     * @example sK=foo, sVCond="UM.v is null": returns users where foo is undefined or foo=NULL
     * @example sK=foo, sVCond="UM.uid is null": returns users where foo is undefined
     */
    function GetUsersFromMetadata( $sK, $sVCond, array $raParms = array() ): array {
        $sql = "SELECT [[cols]] FROM {$this->sDB}SEEDSession_Users U LEFT JOIN {$this->sDB}SEEDSession_UsersMetadata UM "
                ."ON (U._key=UM.uid AND UM.k='$sK') "
                ."WHERE [[statusCond]] AND ($sVCond)";

        return( $this->getUsers( $sql, $raParms ) );
    }

    /**
     * Get Users matching an SQL Query.
     * SQL must contain [[cols]] which will be replaced by the column list.
     * SQL must contain [[statusCond]] which will be replaced by the status condition
     * @param string $sql - sql query to use
     * @param boolean $raParms['bDetail'] - whether to include user details in the result.
     * Defaults to true if not specified
     * @param int $raParms['_status'] - _status to filter by. Use -1 to disable.
     * Defaults to 0 (normal) if not specified
     * @param string $raParms['eStatus'] - comma separated string of user statuses to filter by.
     * Statuses should be one of 'ACTIVE', 'PENDING', or 'INACTIVE' and be wrapped in quotes.
     * Defaults to "'ACTIVE'" if not specified.
     * @return unknown[]|unknown[][]
     */
    private function getUsers( string $sql, array $raParms = array() ): array {
        $raRet = array();
        
        $bDetail = SEEDCore_ArraySmartVal($raParms, 'bDetail', array(true, false));

        // sql must contain [[cols]] which is replaced by the column list
        $sql = str_replace(
            "[[cols]]",
            $bDetail ? "U._key AS _key, U.email AS email, U.realname AS realname, U.eStatus AS eStatus" : "U._key AS _key",
            $sql
        );

        // sql must contain [[statusCond]] which is replaced by the status conditions
        $st  = $raParms['_status'] ?: 0;
        $est = $raParms['eStatus'] ?: "'ACTIVE'";
        $sCondStatus = "U.eStatus IN ($est)"
                      .($st == -1 ? "" : " AND U._status='$st'");
        $sql = str_replace( "[[statusCond]]", $sCondStatus, $sql );

        if( ($dbc = $this->GetKFDB()->CursorOpen( $sql )) ) {
            while( $ra = $this->GetKFDB()->CursorFetch( $dbc ) ) {
                if( $bDetail ) {
                    $raRet[$ra['_key']] = array('email' => $ra['email'], 'realname' => $ra['realname'], 'eStatus' => $ra['eStatus']);
                } else {
                    $raRet[] = $ra['_key'];
                }
            }
            $this->GetKFDB()->CursorClose( $dbc );
        }
        return( $raRet );
    }

    /**
     * Get all groups matching the given condition
     * @param string $cond - condition to get groups for
     * @param int $raParams['_status'] - status of the groups to get, default 0, -1 for all.
     * @param boolean $raParams['bNames'] - whether to retrieve group names, default false.
     * @return array
     */
    function GetAllGroups( string $cond, array $raParams = [] ): array {
        $status = @$raParams['_status'] ?: 0;
        $queryParams = [];
        $query = "SELECT _key AS gid, groupname FROM {$this->sDB}SEEDSession_Groups[[COND]];";
        if($status === -1) {
            if ($cond) {
                $query = str_replace('[[COND]]', " WHERE $cond", $query);
            }
            $query = str_replace('[[COND]]', "", $query);
        } else {
            if ($cond) {
                $query = str_replace('[[COND]]', " WHERE _status = ? AND $cond", $query);
            }
            $query = str_replace('[[COND]]', ' WHERE _status = ?', $query);
            array_push($raParams, $status);
        }
        $raGroups = $this->KFDB()->QueryRowsRA_prepared($query, $queryParams, KEYFRAMEDB_RESULT_ASSOC);
        $raOut = [];
        foreach($raGroups as $ra) {
            if(isset($raParams['bNames']) && boolval($raParams['bNames'])) {
                $ra[$ra['gid']] = $ra['groupname'];
            } else {
                $raOut[] = $ra['gid'];
            }
        }
        asort($raOut);
        return $raOut;
    }

    /**
     * Get the groups the user is a member of.
     * Includes direct group membership as well as all groups they inherit from
     * @param string|int $kUser - id of the user whos groups to get
     * @param int $raParams['_status'] - status of the groups to get, default 0, -1 for all.
     * @param boolean $raParams['bNames'] - whether to retrieve group names, default false.
     * @return array
     */
    function GetGroupsFromUser( string|int $kUser, array $raParams = [] ): array {
        $query = <<<SQL
WITH RECURSIVE groups AS (
    SELECT
        _key AS gid,
        _key AS id,
        _status,
        gid_inherited AS parent_id,
        CAST(_key AS CHAR(200)) AS path -- track visited ids to detect cycles
    FROM [[DB]]SEEDSession_Groups
    UNION ALL
    SELECT
    	gid,
    	parent._key as id,
        parent._status,
    	parent.gid_inherited AS parent_id,
    	CONCAT(path, ',', parent._key) AS path
    FROM groups
    INNER JOIN [[DB]]SEEDSession_Groups AS parent ON parent._key = parent_id
    WHERE parent_id IS NOT NULL AND parent_id > 0
    	AND FIND_IN_SET(parent._key, path) = 0
),
usersxgroups AS (
    SELECT
    	id as gid,
        groups._status,
    	users._key AS uid
    FROM [[DB]]SEEDSession_Users AS users
    INNER JOIN groups ON gid = users.gid1
    WHERE users._status = 0
    UNION ALL
    SELECT
    	id as gid,
        groups._status,
    	SEEDSession_UsersXGroups.uid AS uid
    FROM [[DB]]SEEDSession_UsersXGroups
    INNER JOIN groups ON SEEDSession_UsersXGroups.gid = groups.gid
    WHERE SEEDSession_UsersXGroups._status = 0
)
SELECT DISTINCT gid, groupname
FROM usersxgroups
INNER JOIN [[DB]]SEEDSession_Groups ON gid = _key
WHERE uid = ?[[STATUS_CLAUSE]];
SQL;
        $query = str_replace('[[DB]]', $this->sDB, $query);
        $status = @$raParams['_status'] ?: 0;
        $queryParams = [$kUser];
        if($status === -1) {
            $query = str_replace('[[STATUS_CLAUSE]]', '', $query);
        } else {
            $query = str_replace('[[STATUS_CLAUSE]]', 'AND usersxgroups._status = ?', $query);
            array_push($queryParams, $status);
        }
        $raGroups = $this->KFDB()->QueryRowsRA_prepared($query, $queryParams, KEYFRAMEDB_RESULT_ASSOC);
        $raOut = [];
        foreach($raGroups as $ra) {
            if(isset($raParams['bNames']) && boolval($raParams['bNames'])) {
                $ra[$ra['gid']] = $ra['groupname'];
            } else {
                $raOut[] = $ra['gid'];
            }
        }
        asort($raOut);
        return $raOut;
    }

    /**
     * Get the metadata for a given user.
     * User metadata always overrides group metadata if included
     * @param string|int $kUser - id of the user who's metadata to get
     * @param boolean $bAndGroupMetadata - whether the metadata of the users groups should also be included. Default false
     */
    function GetUserMetadata( string|int $kUser, bool $bAndGroupMetadata = false ): array {
        $raMetadata = array();

        // Get group metadata first so user metadata keys overwrite it
        if( $bAndGroupMetadata ) {
            $raMetadata = $this->GetGroupMetadataByUser( $kUser );
        }

// todo: use named relation
        $ra = $this->GetKFDB()->QueryRowsRA_prepared( "SELECT k, v FROM {$this->sDB}SEEDSession_UsersMetadata WHERE _status = 0 AND uid = ?", [$kUser] );
        // ra is array( array( k=>keyname1, v=>value1 ), array( k=>keyname2, v=>value2 ) )
        foreach( $ra as $ra2 ) {
            $raMetadata[$ra2['k']] = $ra2['v'];
        }
        return( $raMetadata );
    }
    
    /**
     * Get metadata for the given group.
     * Includes inherited metadata by default
     * @param string|int $kGroup - id of the group who's metadata to retrieve.
     * @param boolean $raParms['bDoNotIncludeAncestorGroups'] - whether to skip fetching inherited metadata
     * @return array
     */
    function GetGroupMetadata( string|int $kGroup, array $raParms = array() ): array {
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

    /**
     * Get a users group metadata.
     * Includes metadata for all groups the user directly belongs to.
     * @param string|int $kUser - id of the user who's group metadata to retrieve
     * @return array
     */
    function GetGroupMetadataByUser( string|int $kUser ): array {
        $raMetadata = array();
        $ra = $this->GetKFDB()->QueryRowsRA_prepared(
            "SELECT G.k as k, G.v as v FROM {$this->sDB}SEEDSession_GroupsMetadata G,{$this->sDB}SEEDSession_UsersXGroups X "
                ."WHERE G._status = '0' AND X._status = '0' "
                ."AND G.gid = X.gid AND X.uid = ? "
           ."UNION "
           ."SELECT G.k as k, G.v as v FROM {$this->sDB}SEEDSession_GroupsMetadata G,{$this->sDB}SEEDSession_Users U "
                ."WHERE G._status = '0' AND U._status = '0' "
                ."AND G.gid = U.gid1 AND U._key = ?;", [$kUser, $kUser] );
        // ra is array( array( k=>keyname1, v=>value1 ), array( k=>keyname2, v=>value2 ) )
        foreach( $ra as $ra2 ) {
            $raMetadata[$ra2['k']] = $ra2['v'];
        }
        return( $raMetadata );
    }
    
    /**
     * Get all the permissions for the given user.
     * Includes permissions directly assigned to the user, permissions directly assigned to the users groups,
     * and permissions inherrited from other groups.
     * @param string|int $kUser - id of the user who's permissions to get
     * @return array[]|array[][]
     */
    function GetPermsFromUser( string|int $kUser ) {
        $query = <<<SQL
WITH RECURSIVE perms AS (
    SELECT gid, uid, 'R' AS mode, perm FROM [[DB]]SEEDSession_Perms WHERE modes LIKE '%R%' AND _status = 0
    UNION
    SELECT gid, uid, 'W' AS mode, perm FROM [[DB]]SEEDSession_Perms WHERE modes LIKE '%W%' AND _status = 0
    UNION
    SELECT gid, uid, 'A' AS mode, perm FROM [[DB]]SEEDSession_Perms WHERE modes LIKE '%A%' AND _status = 0
),
groups AS (
    SELECT
        _key AS gid,
        _key AS id,
        gid_inherited AS parent_id,
        CAST(_key AS CHAR(200)) AS path -- track visited ids to detect cycles
    FROM [[DB]]SEEDSession_Groups
	WHERE _status = 0
    UNION ALL
    SELECT
    	gid,
    	parent._key as id,
    	parent.gid_inherited AS parent_id,
    	CONCAT(path, ',', parent._key) AS path
    FROM groups
    INNER JOIN [[DB]]SEEDSession_Groups AS parent ON parent._key = parent_id
    WHERE parent_id IS NOT NULL AND parent_id > 0
    	AND FIND_IN_SET(parent._key, path) = 0
		AND parent._status = 0
),
usersxgroups AS (
    SELECT
    	id as gid,
    	users._key AS uid
    FROM [[DB]]SEEDSession_Users AS users
    INNER JOIN groups ON gid = users.gid1
    WHERE users._status = 0
    UNION ALL
    SELECT 
    	id as gid,
    	SEEDSession_UsersXGroups.uid AS uid
    FROM [[DB]]SEEDSession_UsersXGroups
    INNER JOIN groups ON SEEDSession_UsersXGroups.gid = groups.gid
    WHERE SEEDSession_UsersXGroups._status = 0
)
SELECT DISTINCT mode, perm
FROM [[DB]]SEEDSession_Users AS users
INNER JOIN perms ON uid = users._key OR gid IN (SELECT gid FROM usersxgroups WHERE uid = users._key)
WHERE users._key = ?;
SQL;
        $query = str_replace('[[DB]]', $this->sDB, $query);
        $raPerms = $this->GetKFDB()->QueryRowsRA_prepared($query, [$kUser], KEYFRAMEDB_RESULT_ASSOC);
        $raOut = array( 'perm2modes' => array(), 'mode2perms' => array( 'R'=>array(), 'W'=>array(), 'A'=>array() ) );
        foreach($raPerms as $ra) {
            if (!isset($raOut['perm2modes'][$ra['perm']])) {
                $raOut['perm2modes'][$ra['perm']] = '';
            }
            $raOut['perm2modes'][$ra['perm']] .= $ra['mode'];
            $raOut['mode2perms'][$ra['mode']][] = $ra['perm'];
        }
        return $raOut;
    }

    /**
     * Get the permissions for the given group.
     * Includes permissions directly assigned to the group and permissions inherrited from other groups.
     * @param string|int $kGroup - id of the group who's permissions to get
     * @return array[]|array[][]
     */
    function GetPermsFromGroup( string|int $kGroup ) {
        $query = <<<SQL
WITH RECURSIVE perms AS (
    SELECT gid, uid, 'R' AS mode, perm FROM [[DB]]SEEDSession_Perms WHERE modes LIKE '%R%' AND _status = 0
    UNION
    SELECT gid, uid, 'W' AS mode, perm FROM [[DB]]SEEDSession_Perms WHERE modes LIKE '%W%' AND _status = 0
    UNION
    SELECT gid, uid, 'A' AS mode, perm FROM [[DB]]SEEDSession_Perms WHERE modes LIKE '%A%' AND _status = 0
),
groups AS (
    SELECT
        _key AS gid,
        _key AS id,
        gid_inherited AS parent_id,
        CAST(_key AS CHAR(200)) AS path -- track visited ids to detect cycles
    FROM [[DB]]SEEDSession_Groups
	WHERE _status = 0
    UNION ALL
    SELECT
    	gid,
    	parent._key as id,
    	parent.gid_inherited AS parent_id,
    	CONCAT(path, ',', parent._key) AS path
    FROM groups
    INNER JOIN [[DB]]SEEDSession_Groups AS parent ON parent._key = parent_id
    WHERE parent_id IS NOT NULL AND parent_id > 0
    	AND FIND_IN_SET(parent._key, path) = 0
		AND parent._status = 0
)
SELECT DISTINCT mode, perm
FROM [[DB]]SEEDSession_Groups
INNER JOIN perms ON gid IN (SELECT id FROM groups WHERE gid = SEEDSession_Groups._key)
WHERE SEEDSession_Groups._key = ?;
SQL;
        $query = str_replace('[[DB]]', $this->sDB, $query);
        $raPerms = $this->GetKFDB()->QueryRowsRA_prepared($query, [$kGroup], KEYFRAMEDB_RESULT_ASSOC);
        $raOut = array( 'perm2modes' => array(), 'mode2perms' => array( 'R'=>array(), 'W'=>array(), 'A'=>array() ) );
        foreach($raPerms as $ra) {
            if (!isset($raOut['perm2modes'][$ra['perm']])) {
                $raOut['perm2modes'][$ra['perm']] = '';
            }
            $raOut['perm2modes'][$ra['perm']] .= $ra['mode'];
            $raOut['mode2perms'][$ra['mode']][] = $ra['perm'];
        }
        return $raOut;
    }

    /**
     * Return the groups that the given groups inherit from.
     * Includes the groups themselves and all groups from which they inherit from.
     * @param array $raGroups - ids of groups to get the ancestors of
     * @return array - ids of the groups which the given groups inherit from
     */
    private function getGroupAncestors( array $raGroups ): array {
        $query = <<<SQL
WITH RECURSIVE groups AS (
    SELECT
        _key AS gid,
        _key AS id,
        gid_inherited AS parent_id,
        CAST(_key AS CHAR(200)) AS path -- track visited ids to detect cycles
    FROM [[DB]]SEEDSession_Groups
    WHERE _status = 0
    UNION ALL
    SELECT
    	gid,
    	parent._key as id,
    	parent.gid_inherited AS parent_id,
    	CONCAT(path, ',', parent._key) AS path
    FROM groups
    JOIN [[DB]]SEEDSession_Groups AS parent ON parent._key = parent_id
    WHERE parent_id IS NOT NULL AND parent_id > 0
    	AND FIND_IN_SET(parent._key, path) = 0
        AND parent._status = 0
)
SELECT DISTINCT
    id
FROM groups
WHERE gid IN ([[GROUPS]]);
SQL;
        $query = str_replace(['[[GROUPS]]', '[[DB]]'], [implode(',', array_fill(0, count($raGroups), '?')), $this->sDB], $query);
        $raOut = $this->GetKFDB()->QueryRowsRA1_prepared($query, $raGroups);

        return $raOut;
    }

    /**
     * Return the groups that inherit from the given groups.
     * Includes the groups themselves and all groups that inherit from them.
     * @param array $raGroups - ids of groups to get the descendents of
     * @return array - ids of the groups which inherit from the given groups
     */
    private function getGroupDescendants( array $raGroups ): array {
        $query = <<<SQL
WITH RECURSIVE groups AS (
    SELECT
        _key AS gid,
        _key AS id,
        gid_inherited AS parent_id,
        CAST(_key AS CHAR(200)) AS path -- track visited ids to detect cycles
    FROM [[DB]]SEEDSession_Groups
    WHERE _status = 0
    UNION ALL
    SELECT
    	gid,
    	parent._key as id,
    	parent.gid_inherited AS parent_id,
    	CONCAT(path, ',', parent._key) AS path
    FROM groups
    JOIN [[DB]]SEEDSession_Groups AS parent ON parent._key = parent_id
    WHERE parent_id IS NOT NULL AND parent_id > 0
    	AND FIND_IN_SET(parent._key, path) = 0
        AND parent._status = 0
)
SELECT DISTINCT
    gid
FROM groups
WHERE id IN ([[GROUPS]]);
SQL;
        $query = str_replace(['[[GROUPS]]', '[[DB]]'], [implode(',', array_fill(0, count($raGroups), '?')), $this->sDB], $query);
        $raOut = $this->GetKFDB()->QueryRowsRA1_prepared($query, $raGroups);
    }

    protected function initKfrel( KeyframeDatabase $kfdb, $uid, $logdir )
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
                                      ['col'=>"type",       'type'=>'S'],
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

/**
 * DB access layer for managing Users, UsersMetadata, Groups, GroupsMetadata, UsersXGroups, Perms
 */
class SEEDSessionAccountDB2 extends SEEDSessionAccountDBRead2 {

    /**
     * Id of the user making changes
     * @var int
     */
    private $uidOwnerOrAdmin;

    /**
     * @param KeyframeDatabase $kfdb db connection to use for managing Users, Metadata, Groups, and Perms
     * @param int $uidOwnerOrAdmin - uid of the person altering the UGP (either the owner of the account or an admin altering someone's account).
     * It is only used for Keyframe._created/update_by so it can be 0 of only reading
     * @param string $raConfig['dbname'] - Name of the db containing the UGP tables
     * @param string $raConfig['logdir'] - Location to log db changes. Only used for Keyframe writes so can be empty if only reading.
     */
    function __construct( KeyframeDatabase $kfdb, int $uidOwnerOrAdmin, array $raConfig = [] )
    {
        parent::__construct( $kfdb, $uidOwnerOrAdmin, $raConfig );
        $this->uidOwnerOrAdmin = $uidOwnerOrAdmin;
    }

    /**
     * Create a new user.
     * If a user with the given username/email already exists, return that id instead of creating a user.
     * @param string $sEmail - username or email to use for the user. Must be unique
     * @param string $sPwd - password for the user
     * @param int $raParms['k'] - optional id to use for the user. Ommiting or specifying 0 will cause the next auto-increment value to be used.
     * @param string $raParms['realname'] - Users real name
     * @param string $raParms['sExtra'] - Extra information to store with the user
     * @param string $raParms['eStatus'] - Initial status of the user. Must be one of "ACTIVE", "PENDING", or "INACTIVE".
     * Defaults to "PENDING" if not specified
     * @param string $raParms['lang'] - Language of the user. Must be one of "E" for English, "F" for French, or "B" for both.
     * Defautls to "E" if not specified
     * @param int $raParms['gid1'] - group the user is in
     * @return int - id of the newly created user, or the id of the existing user for the given username/email if one already exists.
     */
    function CreateUser( string $sEmail, string $sPwd, array $raParms = array() ): string|int {
        $kUser       = intval(@$raParms['k']);      // 0 means use the next auto-increment
        // TODO: Hash & Salt the password (password_hash)
        $sdbPwd      = $sPwd;
        $sdbRealname = @$raParms['realname'] ?? "";
        $sdbExtra    = @$raParms['sExtra'] ?? "";
        $eStatus     = SEEDCore_ArraySmartVal( $raParms, 'eStatus', array('PENDING','ACTIVE','INACTIVE') );
        $eLang       = SEEDCore_ArraySmartVal( $raParms, 'lang', array('E','F','B') );
        $gid1        = intval(@$raParms['gid1']);

// todo: use named relation
        $k = $this->GetKFDB()->Query1_prepared("SELECT _key FROM {$this->sDB}SEEDSession_Users WHERE email = ? AND _status = 0;", [$sEmail]);
        if( !$k ) {
            // return value is what you expect, whether k is 0 or non-zero
            $k = $this->GetKFDB()->InsertAutoInc(
                    "INSERT INTO {$this->sDB}SEEDSession_Users "
                    ."(_key,_created,_created_by,_updated,_updated_by,_status,email,password,realname,eStatus,sExtra,lang,gid1) "
                   ."VALUES (?,NOW(),?,NOW(),?,0,?,?,?,?,?,?,?);",
            [$kUser, $this->uidOwnerOrAdmin, $this->uidOwnerOrAdmin, $sEmail, $sdbPwd, $sdbRealname, $eStatus, $sdbExtra, $eLang, $gid1] );
        }
        return( $k );
    }

    /**
     * Activate an existing user allowing them to log in.
     * Handles users that have a "deleted" or "hidden" status, as well as users who are "PENDING" or "INACTIVE"
     * @param string|int $kUser - id of the user to activate
     * @return boolean - true if the user was activated, false otherwise
     */
    function ActivateUser( string|int $kUser ): bool {
        $bOk = false;

        if( ($kfr = $this->GetKfrel('U')->GetRecordFromDBKey( $kUser )) ) {
            $kfr->StatusSet( KeyframeRecord::STATUS_NORMAL );    // if the account has been deleted or hidden, undelete it
            $kfr->SetValue( 'eStatus', 'ACTIVE' );        // if the account is INACTIVE, activate it

            $bOk = $kfr->PutDBRow();
        }

        return( $bOk );
    }

    /**
     * Change a users password
     * @param string|int $kUser - id of the user who's password to change
     * @param string $sPwd - the new password for the user
     * @return boolean- true if the password was changed, false otherwise
     */
    function ChangeUserPassword( string|int $kUser, string $sPwd ): bool {
        $kUser = intval($kUser);
        // TODO: Hash & Salt the password (password_hash)
        $sdbPwd = $sPwd;

        return( $kUser ? $this->GetKFDB()->Execute_prepared( "UPDATE {$this->sDB}.SEEDSession_Users SET password = ? WHERE _key = ?;", [$sdbPwd, $kUser] ) : false );
    }

    /**
     * Set a users metadata
     * @param string|int $kUser - id of the user who's metadata to set
     * @param string $k - metadata key to set
     * @param string $v - value for the metadata to set
     * @return boolean - true if the metadata was set, false otherwise
     */
    function SetUserMetadata( string|int $kUser, string $k, string $v ): bool {
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

    /**
     * Set a groups metadata
     * @param string|int $kGroup - id of the group who's metadata to set
     * @param string $k - metadata key to set
     * @param string $v - value for the metadata to set
     * @return boolean - true if the metadata was set, false otherwise
     */
    function SetGroupMetadata( string|int $kGroup, string $k, string $v ): bool {
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

    /**
     * Delete a user & their metadata.
     * Marks the user as "deleted" in the db alongside all their metadata but does not actually delete the user.
     * Does not remove the user from their groups, though they will no longer be included in the list of group members by default
     * @param string|int $kUser - id of the user to delete
     * @return boolean - true if the user was deleted, false otherwise
     */
    function DeleteUser( string|int $kUser ): bool {
        $bOk = false;

        if( ($kfr = $this->GetKfrel('U')->GetRecordFromDBKey($kUser)) ) {
            $kfr->StatusSet( KeyframeRecord::STATUS_DELETED );    // mark the account as deleted
            $bOk = $kfr->PutDBRow();
        }

        // Fetch iStatus==-1 so any hidden records are found, and replaced with the DELETED status
        if( ($kfr = $this->GetKfrel('UM')->CreateRecordCursor("uid='$kUser'", ['iStatus'=>-1] )) ) {
            while( $kfr->CursorFetch() ) {
                $kfr->StatusSet( KeyframeRecord::STATUS_DELETED );
                $bOk = $kfr->PutDBRow() && $bOk;
            }
        }

        return( $bOk );
    }

    /**
     * Delete a users metadata.
     * Marks the metadata as "deleted" in the db but does not actually delete it.
     * @param string|int $kUser - id of user who's metadata to delete
     * @param string $k - metadata key to delete
     * @return boolean - true if the metadata was deleted, false otherwise
     */
    function DeleteUserMetadata( string|int $kUser, string $k ): bool {
        $ok = false;

        $kfrelUM = $this->GetKfrel('UM');

        // Fetch iStatus==-1 so any hidden records are found, and replaced with the DELETED status
        if( ($kfr = $kfrelUM->GetRecordFromDB( "uid='$kUser' AND k='".addslashes($k)."'", array('iStatus'=>-1) )) ) {
            $kfr->StatusSet( KeyframeRecord::STATUS_DELETED );
            $ok = $kfr->PutDBRow();
        }
        return( $ok );
    }

    /**
     * Delete a groups metadata.
     * Marks the metadata as "deleted" in the db but does not actually delete it.
     * @param string|int $kGroup - id of the group who's metadata to delete
     * @param string $k - metadata key to delete
     * @return boolean - true if metadata was deleted, false otherwise
     */
    function DeleteGroupMetadata( string|int $kGroup, string $k ): bool {
        $ok = false;

        $kfrelGM = $this->GetKfrel('GM');

        // Fetch iStatus==-1 so any hidden records are found, and replaced with the DELETED status
        if( ($kfr = $kfrelGM->GetRecordFromDB( "gid='$kGroup' AND k='".addslashes($k)."'", array('iStatus'=>-1) )) ) {
            $kfr->StatusSet( KeyframeRecord::STATUS_DELETED );
            $ok = $kfr->PutDBRow();
        }
        return( $ok );
    }

    /**
     * Add a user to the given group.
     * If the user is already in the given group, return true (no-op).
     * If the user's gid1 (first group) is 0, set it to the given group.
     * Else, add a row to UserXGroups
     * @param string|int $kUser - id of the user to add to the group
     * @param string|int $kGroup - id of group to add the user to
     * @return boolean - true if the user was added to the group, false otherwise
     */
    function AddUserToGroup( string|int $kUser, string|int $kGroup ): bool {
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

    /**
     * Remove a user from the given group.
     * If the user is not in the given group, return true (no-op).
     * If the user's gid1 (first group) is the given group, set it to 0.
     * If the given group exists in UsersXGroups for the user, delete that row.
     * UserXGroups rows are completely removed from the db, instead of being soft deleted like Users.
     * @param string|int $kUser - id of the user to remove from the group
     * @param string|int $kGroup - id of the group to remove the user from
     * @return boolean - true if the user was removed from the group, false otherwise
     */
    function RemoveUserFromGroup( string|int $kUser, string|int $kGroup ): bool {
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
        $this->KFDB()->Execute_prepared( "DELETE FROM {$this->sDB}SEEDSession_UsersXGroups WHERE uid=? AND gid=?", [$kUser, $kGroup] );

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
    type        VARCHAR(200) NOT NULL,              # A=uid specified in record; B=uid specified in link
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
