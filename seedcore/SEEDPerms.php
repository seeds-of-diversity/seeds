<?php

/* SEEDPerms
 *
 * Copyright (c) 2007-2018 Seeds of Diversity Canada
 *
 * A flexible permissions manager.
 *
 * Each application has one or more permission classes, defined by integers.
 * Each permission class (permclass) has any number of single-character permission modes (e.g. R, W) defined for any
 * number of users and/or user groups.
 *
 * You can therefore relate integer permclasses to any element in an application, and use this module to manage access.
 * An element can be an operation, a table row, or anything that can be related to an integer set.
 *
 * Users and User Groups are not meaningful to this code: they are just application defined integers.
 * Permission modes are not meaningful to this code: they can be any characters that are meaningful to your application.
 *
 * Anonymous access:
 *     Some applications allow anonymous access to certain permclasses (e.g. public web pages).
 *     Generally, without a login there is no user_id or user_group so a special user_id/user_group must be
 *     reserved to indicate anonymous access.
 *
 *     Nothing in this module treats any user_id/group specially, so your calling code has to give special status to one or
 *     more id/groups if it allows anonymous users.
 *
 *     Example:  user_id=-1 could be a nice id for the anonymous user. Your authentication code might define -1 as an actual
 *               'anonymous' user, or not, but that doesn't affect SEEDPerms. All that matters here is that user_id -1 would be
 *               given certain permclasses and modes, and the calling code would have to somehow instantiate SEEDPerms with the
 *               anonymous user_id -1 when no user is logged in.
 *               In this example, user_id=-1, mode='R', permclass=N  would provide anonymous read access to permclass N
 *
 *     Why not use user_id=0 for anonymous users?
 *     Never do this. Zero is a handy default, but it is also the default placeholder in the SEEDPerms table.
 *     Say you define perms to group 2: user_group=2,user_id=0
 *     The placeholder looks like access to user 0! You should therefore never specify 0 in the SEEDPerms raUserid or raUserGroup
 *     (and the constructor dies if you do).
 */


class SEEDPermsRead extends Keyframe_NamedRelations
{
    protected $sDB = "";

    function __construct( SEEDAppDB $oApp, $raConfig = array() )
    /***********************************************************
        raConfig:   dbname      optional database name
                    raUserid    array( uid1, uid2, ... )    the user(s) for the SEEDPerms lookup
                    raUserGroup array( gid1, gid2, ... )    the group(s) for the SEEDPerms lookup
                    uidOwner    credited with _created_by and/or _updated_by
                                (can be zero or omitted if only reading -- enforced by constructor of SEEDPerms when writing)
     */
    {
        if( ($sDB = @$raConfig['dbname']) ) {    // allows UGP management of other databases, can be empty for current db
            // Prepended to every database table reference
            $this->sDB = $sDB.".";
        }
        // set $this->sDB before constructing the named relations because it's used in initKfrel()
        parent::__construct( $oApp->kfdb, intval(@$raConfig['uidOwner']), $oApp->logdir );
    }

    function GetRAClassesOpts( $app = "", $bDetail = false )
    /*******************************************************
        Return an array of Class names and keys formatted for Select(), using optional app namespace
     */
    {
        $raPermOpts = array();

        $sCondApp = $app ? ("application='".addslashes($app)."' AND") : "";

        $q = $this->KFDB()->QueryRowsRA( "SELECT * FROM SEEDPerms_Classes WHERE $sCondApp _status='0' ORDER BY _key" );
        foreach( $q as $ra ) {
            if( $bDetail ) {
                $raPermOpts["{$ra['_key']} : {$ra['application']} : {$ra['name']}"] = $ra['_key'];
            } else {
                $raPermOpts[$ra['name']] = $ra['_key'];
            }
        }
        return( $raPermOpts );
    }

    function GetUsersFromPermClass( $permclass, $mode )
    /**************************************************
        Get all the userids/usergroups that have the given mode of access to the given permclass
     */
    {
        $raUid = array();
        $raUG = array();

        if( ($kfr = $this->KFRel('P')->CreateRecordCursor( "fk_SEEDPerms_Classes='$permclass' AND modes LIKE '%$mode%'" )) ) {
            while( $kfr->CursorFetch() ) {
                if( $kfr->Value('user_id') )     $raUid[] = $kfr->Value('user_id');
                if( $kfr->Value('user_group') )  $raUG[]  = $kfr->Value('user_group');
            }
        }

        return( array($raUid, $raUG) );
    }

    protected function initKfrel( KeyframeDatabase $kfdb, $uid, $logdir )
    {
        $fld_C = array( array("col"=>"application",          "type"=>"S"),
                        array("col"=>"name",                 "type"=>"S") );

        $fld_P = array( array("col"=>"fk_SEEDPerms_Classes", "type"=>"K"),
                        array("col"=>"user_id",              "type"=>"I"),
                        array("col"=>"user_group",           "type"=>"I"),
                        array("col"=>"modes",                "type"=>"S") );

        $def_C = array( "Tables" => array(
                "C" => array( "Table" => "{$this->sDB}SEEDPerms_Classes",
                              "Type"  => 'Base',
                              "Fields" => $fld_C ) ) );

        $def_P = array( "Tables" => array(
                "P" => array( "Table" => "{$this->sDB}SEEDPerms",
                              "Type"  => 'Base',
                              "Fields" => $fld_P ) ) );

        $def_PxC = array( "Tables" => array(
                "P" => array( "Table" => "{$this->sDB}SEEDPerms",
                              "Type"  => 'Base',
                              "Fields" => $fld_P ),
                "C" => array( "Table" => "{$this->sDB}SEEDPerms_Classes",
                              "Type"  => 'Parent',
                              "Fields" => $fld_C ) ) );

        // Normally you always want to use PxC, but this left-join is very useful in admin UI because if
        // a P.fk_SEEDPerms_Classes is set to an invalid value, the PxC will not return that P row, and then
        // you have a possibly operational permission that you don't know about.
        $def_P_C = array( "Tables" => array(
                "P" => array( "Table" => "{$this->sDB}SEEDPerms",
                              "Type"  => 'Base',
                              "Fields" => $fld_P ),
                "C" => array( "Table" => "{$this->sDB}SEEDPerms_Classes",
                              "Type"  => "LeftJoin",
                              "JoinOn" => "P.fk_SEEDPerms_Classes=C._key",
                              "Fields" => $fld_C ) ) );

        $raKfrel = array();
        $parms = $logdir ? array('logfile'=>$logdir."seedperms.log") : array();
        $raKfrel['C']   = new Keyframe_Relation( $kfdb, $def_C,   $uid, $parms );
        $raKfrel['P']   = new Keyframe_Relation( $kfdb, $def_P,   $uid, $parms );
        $raKfrel['PxC'] = new Keyframe_Relation( $kfdb, $def_PxC, $uid, $parms );
        $raKfrel['P_C'] = new Keyframe_Relation( $kfdb, $def_P_C, $uid, $parms );

        return( $raKfrel );

    }
}

class SEEDPermsTest extends SEEDPermsRead
{
    private $appname = "";
    private $raClassesInfo  = array(); // array( permclass1 => array('modes'=>M, 'classname'=>C), permclass2 => ...)
    private $raModesClasses = array(); // array( modeChar1 => array( permclass1, permclass2, ... ), modeChar2 => ... )

    /* Given an appname and a list of users and groups, look up the permclasses and modes that are allowed.
     */
    function __construct( SEEDAppDB $oApp, $appname, $raUserid, $raUserGroup, $raConfig = array() )
    {
        $this->appname = $appname;
        parent::__construct( $oApp, $raConfig );

        /* Load the seedperms for the given app and users
         */
        $sCond = $this->getUserDBCond( $raUserid, $raUserGroup );
        if( $sCond )  $sCond .= " AND ";
        $sCond .= "C.application='$appname'";

        if( ($kfr = $this->KFRel('PxC')->CreateRecordCursor( $sCond, array( 'sSortCol'=>'C.name' ) )) ) {
            while( $kfr->CursorFetch() ) {
                $permclass = $kfr->value('C__key');
                $modes = $kfr->value('modes');

                $this->raClassesInfo[$permclass]['classname'] = $kfr->value('C_name');
                for( $i = 0; $i < strlen($modes); ++$i ) {
                    $m = $modes[$i];
                    // Store the relation permclass->mode
                    if( strpos( @$this->raClassesInfo[$permclass]['modes'], $m ) === false ) {
                        @$this->raClassesInfo[$permclass]['modes'] .= $m;
                    }
                    // Store the relation mode->permclass
                    if( !isset($this->raModesClasses[$m]) || !in_array($permclass, $this->raModesClasses[$m]) ) {
                        $this->raModesClasses[$m][] = $permclass;
                    }
                }
            }
        }
    }

    private function getUserDBCond( $raUserid, $raUserGroup )
    /*******************************************************
       Return the sql condition that constrains permclasses to the applicable user(s)
     */
    {
        $sCond = "";

        // Ensure that no one is trying to use user_id=0 or user_group=0. (e.g. for anonymous user permissions)
        // Those are placeholders in the SEEDPerms table so their use is wrong and dangerous.
        if( array_search(0, $raUserid) !== false )     die( "Cannot use uid=0 in SEEDPerms" );
        if( array_search(0, $raUserGroup) !== false )  die( "Cannot use gid=0 in SEEDPerms" );

        $bU = (count($raUserid) > 0);
        $bG = (count($raUserGroup) > 0);
        if( $bU || $bG ) {
            if( $bU )  $sCond .= "P.user_id IN (".implode(",",$raUserid).")";
            if( $bU && $bG )  $sCond .= " OR ";
            if( $bG )  $sCond .= "P.user_group IN (".implode(",",$raUserGroup).")";
            $sCond = "(".$sCond.")";
        }

        return( $sCond );
    }

    function GetPermClassInfo( $permclass )
    /**************************************
       For the given permclass return array('modes'=>M, 'classname'=>C, 'app'=>A)
     */
    {
        return( array_merge( @$this->raClassInfo[$permclass], array( 'app' => $this->appname ) ) );
    }

    function GetClassesAllowedRA()
    /*****************************
        Output: $ra['All'] = GetClassesAllowed( all modes , bAllInfo )
                $ra[X] = GetClassesAllowed( mode X , !bAllInfo ) where X is each and every mode available
     */
    {
        // The ra[X] parts of the result are just $this->raModesClasses.
        // The ra['All'] part is $this->raClassesInfo.

        return( array_merge( $this->raModesClasses, array('All' => $this->raClassesInfo) ) );
    }

    function GetClassesAllowed( $modes, $bAllInfo = false )
    /******************************************************
        Return a list of the permclasses that are allowed for the given modes.

        This is not quite like Unix "rwx" style directory lists, because a user will never even know about an item
        unless they at least have read permission on it.

        bAllInfo = false : return array( permclass1, permclass2, permclass3,... )
        bAllInfo = true  : return array( permclass1 => array( name, modes ), permclass2 => array( name, modes ),... )

        Typically, the former is useful for imploding to a list of integers for SQL conditions
                   the latter is useful for UI
     */
    {
        $raRet = array();

/* There are two optimizations:
 *     1) (easy) if strlen($modes)==1 && !$bAllInfo, just return the correct array from raModesClasses
 *     2) (harder) if $modes contains all modes && $bAllInfo, just return raClassesInfo
 */

        if( empty($modes) ) return( $raRet );

        // Optimization 1
        if( strlen($modes) == 1 && !$bAllInfo && isset($this->raModesClasses[$modes]) )  return( $this->raModesClasses[$modes] );


        // build up an array of permclasses that have the given mode(s)
        for( $i=0; $i < strlen($modes); $i++ ) {
            // Why the isset: Rarely, an installation will have no perms for a given mode. If that mode is requested, the array will not exist
            // in raModesClasses and array_merge will fail completely, returning null no matter what the first argument is.
            if( isset($this->raModesClasses[$modes[$i]]) ) {
                $raRet = array_merge( $raRet, $this->raModesClasses[$modes[$i]] );
            }
        }
        $raRet = array_unique( $raRet );

        if( $bAllInfo ) {
            // return a more complete form of the resulting array
            $ra1 = $raRet;
            $raRet = array();
            foreach( $ra1 as $permclass ) {
                $raRet[$permclass] = $this->raClassesInfo[$permclass];
            }
        }

        return( $raRet );
    }

    function EnumClassNames( $modes = "" )
    /*************************************
        Retrieve all class names accessible by the current user, with the given modes

        $mode is zero or more characters. If empty, this constraint is ignored.

        Returns array( permclass1 => name1, permclass2 => name2, ... ) ordered by name
     */
    {
        $raRet = array();

        foreach( $this->raClassesInfo as $permclass => $ra ) {
            if( empty($modes) ) {
                $bOk = true;
            } else {
                $bOk = false;
                for( $i=0; $i < strlen($modes); $i++ ) {
                    if( strpos($ra['modes'], $modes[$i]) !== false ) {
                        $bOk = true;
                        break;
                    }
                }
            }
            if( $bOk ) {
                $raRet[$permclass] = $ra['classname'];
            }
        }

        natsort($raRet);  // sorts by array value, retaining assoc with keys (i.e. it reorders the keys)
        return( $raRet );
    }

    function GetClassName( $permclass )
    /**********************************
     */
    {
        return( @$this->raClassesInfo[$permclass]['classname'] );
    }

    function IsClassModeAllowed( $permclass, $mode )
    /***********************************************
        Return boolean: can the user access the given permclass in the given mode
     */
    {
        if( !isset($this->raModesClasses[$mode]) )  return( false );    // can't give a null to arg 2 of in_array
        return( in_array( $permclass, $this->raModesClasses[$mode] ) );
    }

    function EnumAppNames( $raUserid, $raUserGroup )
    /***********************************************
        Retrieve all application names accessible by the given current user
    */
    {
        $raRet = array();

        $sCond = $this->getUserDBCond( $raUserid, $raUserGroup );

        $kfdb = $this->KFDB();

//TODO: if kfrel::CreateRecordCursor could take a raSelFields parm (use in makeSelect) then this would be self::KfrelSEEDPerms_PxC

//TODO: if you want to add $modes = "" as a parm, add condition (modes LIKE '%X%' {OR modes LIKE '%Y%' ...}) for each char of $modes
        if( ($dbc = $kfdb->CursorOpen( "SELECT C.application "
                                      ."FROM SEEDPerms P,SEEDPerms_Classes C "
                                      ."WHERE P.fk_SEEDPerms_Classes=C._key AND C._status=0 AND P._status=0 "
                                      .($sCond ? "AND $sCond " : "")
                                      ."ORDER BY 1 GROUP BY 1")) ) {
            while( $ra = $kfdb->CursorFetch( $dbc ) ) {
                $raRet[] = $ra[0];
            }
            $kfdb->CursorClose( $dbc );
        }
        return( $raRet );
    }
}


class SEEDPermsWrite extends SEEDPermsRead
{
//Maybe this should use SEEDAppSessionAccount so it can GetUID for the namedrelation instead of passing uidOwner?
//This change has to be deferred until the client code of this class is able to make SEEDAppSessionAccount - right now it often
//creates SEEDAppDB in the middle of some old code where it wouldn't know the login permission requirements
    function __construct( SEEDAppDB $oApp, $uidOwner, $raConfig = array() )
    /**********************************************************************
        raConfig:   dbname      optional database name

        $uidOwner is the uid to be credited with _created_by and/or _updated_by
     */
    {
        parent::__construct( $oApp, array_merge( $raConfig, array('uidOwner' => $uidOwner) ) );
    }

    function CreatePermClass( $appname, $name )
    {
        $ok = false;

        if( ($kfr = $this->KFRel('C')->CreateRecord()) ) {
            $kfr->SetValue( 'application', $appname );
            $kfr->SetValue( 'name', $name );
            $ok = $kfr->PutDBRow();
        }
        return( $ok ? $kfr->Key() : 0 );
    }

    function AddPermForUser( $user_id, $permclass, $mode )
    /*****************************************************
        Check if the user has the given perm. If not, add it.

        This simplistically adds a new record - it could be fancier by adding another mode to an existing record.
     */
    {
        $kfrel = $this->KFRel('P');
        if( !($kfr = $kfrel->GetRecordFromDB( "user_id='$user_id' AND fk_SEEDPerms_Classes='$permclass' AND modes LIKE '%$mode%'")) ) {
            $kfr = $kfrel->CreateRecord();
            $kfr->SetValue( 'fk_SEEDPerms_Classes', $permclass );
            $kfr->SetValue( 'user_id', $user_id );
            $kfr->SetValue( 'user_group', 0 );
            $kfr->SetValue( 'modes', $mode );
            $kfr->PutDBRow();
        }
    }

    function RemovePermForUser( $user_id, $permclass, $mode )
    /********************************************************
        Remove the given perm for the given user.

        There is no way to do this if the perm is provided in a group. Too bad for you.
     */
    {
        $kfrel = $this->KFRel('P');
        if( ($kfr = $kfrel->CreateRecordCursor( "user_id='$user_id' AND fk_SEEDPerms_Classes='$permclass' AND modes LIKE '%$mode%'")) ) {
            while( $kfr->CursorFetch() ) {
                if( $kfr->Value( 'modes' ) == $mode ) {
                    $kfr->StatusSet( KFRECORD_STATUS_DELETED );
                } else {
                    $kfr->SetValue( 'modes', str_replace( $mode, '', $kfr->Value('modes') ) );
                }
                $kfr->PutDBRow();
            }
        }
    }
}

function SEEDPerms_Setup( $oSetup, &$sReport, $bCreate = false )
/***************************************************************
    Test whether the tables exist.
    bCreate: create the tables and insert initial data if they don't exist.

    Return true if exists (or create is successful if bCreate); return a text report in sReport

    N.B. $oSetup is a SEEDSetup.  This file doesn't include SEEDSetup.php because the setup code is very rarely used.
         Instead, the code that calls this function knows about SEEDSetup.
 */
{
    $sReport = "";
    $bRet = $oSetup->SetupTable( "SEEDPerms",         SEEDPERMS_DB_TABLE_SEEDPERMS,         $bCreate, $sReport ) &&
            $oSetup->SetupTable( "SEEDPerms_Classes", SEEDPERMS_DB_TABLE_SEEDPERMS_CLASSES, $bCreate, $sReport );

    /* Initialize users (dev, friend, guest) with typical permclasses
     */
    if( $bRet && !$oSetup->kfdb->Query1("SELECT count(*) FROM SEEDPerms_Classes") ) {
        foreach( array( 1 => array('DocRep', 'Public web site'),
                        2 => array('DocRep', 'Internal web site'),
                        3 => array('DocRep', 'Secret documents') )  as $k => $ra )
        {
            $bRet = $oSetup->kfdb->Execute( "INSERT INTO SEEDPerms_Classes (_key,_created,_updated,application,name) "
                                           ."VALUES ($k, NOW(), NOW(), '{$ra[0]}', '{$ra[1]}')");
            $sReport .= ($bRet ? "Inserted" : "Failed to insert") ." SEEDPerms Class '{$ra[0]}/{$ra[1]}'.<BR/>";
        }
    }
    if( $bRet && !$oSetup->kfdb->Query1("SELECT count(*) FROM SEEDPerms") ) {
                          //  class  modes     uid       gid
        foreach( array( array( 1,    'RWAP',  'NULL',       2 ),  // Friend group has full access on public web site
                        array( 1,    'R',     -1,      'NULL' ),  // Special (-1) anonymous user has read access on public web site
                        array( 2,    'WP',    'NULL',       2 ),  // Friend group has WP in Internal site
                        array( 2,    'A',     'NULL',       1 ),  // Dev group has admin on Internal site
                        array( 2,    'R',     'NULL',       3 ),  // Guest group has read access to Internal site (dev and friend are members)
                        array( 3,    'RWAP',       1,  'NULL' ),  // dev user has full access to Secret documents
                      ) as $ra )
        {
            $bRet = $oSetup->kfdb->Execute( "INSERT INTO SEEDPerms (_key,_created,_updated,fk_SEEDPerms_Classes,modes,user_id,user_group) "
                                           ."VALUES (NULL, NOW(), NOW(), {$ra[0]}, '{$ra[1]}', {$ra[2]}, {$ra[3]})");
            $sReport .= ($bRet ? "Inserted" : "Failed to insert") ." SEEDPerm {$ra[0]} {$ra[1]} => {$ra[2]} {$ra[3]}.<BR/>";
        }
    }

    return( $bRet );
}


define("SEEDPERMS_DB_TABLE_SEEDPERMS_CLASSES",
"
CREATE TABLE SEEDPerms_Classes (
    # Every element of your application has a permclass, either named or identified by _key
    # name is the name of the permission class
    # application is like a namespace. It groups names for each application that uses this table.

        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    application     VARCHAR(200) NOT NULL DEFAULT '',
    name            VARCHAR(200) NOT NULL DEFAULT '',

    INDEX (application(20))
);
");

define("SEEDPERMS_DB_TABLE_SEEDPERMS",
"
CREATE TABLE SEEDPerms (
    # Map users, user groups, modes, to permclasses

        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    fk_SEEDPerms_Classes INTEGER NOT NULL,
    user_id              INTEGER NULL,
    user_group           INTEGER NULL,
    modes                VARCHAR(20),

    INDEX (fk_SEEDPerms_Classes),
    INDEX (user_id),
    INDEX (user_group)
);
");

?>
