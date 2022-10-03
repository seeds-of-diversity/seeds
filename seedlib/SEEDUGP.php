<?php

include_once( SEEDROOT."Keyframe/KeyframeUI.php" );
include_once( SEEDCORE."SEEDPerms.php" );

class SEEDUGP_KFUIListForm_Config extends KeyFrameUI_ListFormUI_Config
/********************************
    Get the configuration for a KeyframeUI_ListFormUI on the UGP tables
        $c = users | groups | perms
 */
{
    private $oApp;
    private $c;
    private $oAcctDB;

    function __construct( SEEDAppSession $oApp, $c )
    {
        $this->oApp = $oApp;
        $this->c = $c;
        $this->oAcctDB = new SEEDSessionAccountDB2( $this->oApp->kfdb, $this->oApp->sess->GetUID(), ['logdir'=>$this->oApp->logdir] );
        parent::__construct();  // sets the default raConfig

        switch($c) {
            case 'users':
                $this->raConfig['sessNamespace'] = 'UGPUsers';
                $this->raConfig['cid']           = 'U';
                $this->raConfig['kfrel']         = $this->oAcctDB->GetKfrel('U');
                $this->raConfig['raListConfig']['cols'] = [
                        ['label'=>'User #', 'col'=>'_key' ],
                        ['label'=>'Name',   'col'=>'realname' ],
                        ['label'=>'Email',  'col'=>'email' ],
                        ['label'=>'Status', 'col'=>'eStatus' ],
                        ['label'=>'Group1', 'col'=>'G_groupname' ] ];
                // Not the same format as list cols because _key is ambiguous
                $this->raConfig['raSrchConfig']['filters'] = [
                        ['label'=>'User #', 'col'=>'U._key' ],
                        ['label'=>'Name',   'col'=>'U.realname' ],
                        ['label'=>'Email',  'col'=>'U.email' ],
                        ['label'=>'Status', 'col'=>'U.eStatus' ],
                        ['label'=>'Group1', 'col'=>'G.groupname' ] ];
                break;

            case 'groups':
                $this->raConfig['sessNamespace'] = 'UGPGroups';
                $this->raConfig['cid']           = 'G';
                $this->raConfig['kfrel']         = $this->oAcctDB->GetKfrel('G');
                $this->raConfig['raListConfig']['cols'] = [
                        ['label'=>'k',          'col'=>'_key' ],
                        ['label'=>'Group Name', 'col'=>'groupname' ],
                        ['label'=>'Inherited',  'col'=>'gid_inherited' ] ];
                // conveniently, we can use the same format for search filters as for the cols (because filters can be cols or aliases)
                $this->raConfig['raSrchConfig']['filters'] = $this->raConfig['raListConfig']['cols'];
                break;

            case 'perms':
                $this->raConfig['sessNamespace'] = 'UGPPerms';
                $this->raConfig['cid']           = 'P';
                $this->raConfig['kfrel']         = $this->oAcctDB->GetKfrel('P');
                $this->raConfig['raListConfig']['cols'] = [
                        ['label'=>'Permission', 'col'=>'perm' ],
                        ['label'=>'Modes',      'col'=>'modes' ],
                        ['label'=>'User',       'col'=>'U_realname' ],
                        ['label'=>'Group',      'col'=>'G_groupname' ] ];
                // conveniently, we can use the same format for search filters as for the cols (because filters can be cols or aliases)
                $this->raConfig['raSrchConfig']['filters'] = $this->raConfig['raListConfig']['cols'];
                break;
        }
    }

    function ListRowTranslate( $raRow )
    {
        switch($this->c) {
            case 'users':
                // show the groupname with (gid) appended for convenience
                if( $raRow['gid1'] && $raRow['G_groupname'] ) {
                    $raRow['G_groupname'] .= " ({$raRow['gid1']})";
                }
            break;

            case 'groups':
                // show the inherited groupname instead of the key
                if( $raRow['gid_inherited'] == 0 ) {
                    $raRow['gid_inherited'] = '';
                } else {
                    $g = intval($raRow['gid_inherited']);   // protect against sql injection
                    $raRow['gid_inherited'] = $this->oApp->kfdb->Query1("SELECT groupname FROM SEEDSession_Groups WHERE _key='$g'" )
                                             ." ($g)";
                }
                break;

            case 'perms':
                break;
        }

        return( $raRow );
    }

    function FormTemplate()
    {
        switch($this->c) {
            case 'users':
                $s = "|||BOOTSTRAP_TABLE(class='col-md-6'|class='col-md-6')\n"
                    ."||| User #|| [[Key: | readonly]]\n"
                    ."||| Name  || [[Text:realname]]\n"
                    ."||| Email || [[Text:email]]\n"
                    ."||| Password || [[if:[[value:password]]|-- cannot change here --|[[Text:password]] ]]\n"
                    ."||| Status|| ".$this->getSelectTemplateFromArray( 'sfUp_eStatus', 'eStatus',
                                            ['ACTIVE'=>'ACTIVE','INACTIVE'=>'INACTIVE','PENDING'=>'PENDING'] )."\n"
                    ."||| Group || ".$this->makeGroupSelect( 'sfUp_gid1', 'gid1' )
                    ."||| <input type='submit'>";
                break;

            case 'groups':
                $s = "|||BOOTSTRAP_TABLE(class='col-md-6'|class='col-md-6')\n"
                    ."||| Name            || [[Text:groupname]]\n"
                    ."||| Inherited Group || ".$this->makeGroupSelect('sfGp_gid_inherited', 'gid_inherited' )
                    ."||| <input type='submit'> [[HiddenKey:]]";
                break;

            case 'perms':
                $s = "|||BOOTSTRAP_TABLE(class='col-md-6'|class='col-md-6')\n"
                    ."||| Name  || [[Text:perm]]\n"
                    ."||| Mode  || [[Text:modes]]\n"
                    ."||| User  || ".$this->makeUserSelect( 'sfPp_uid', 'uid' )
                    ."||| Group || ".$this->makeGroupSelect( 'sfPp_gid', 'gid' )
                    ."||| <input type='submit'>  [[HiddenKey:]]";
                break;
        }

        return( $s );
    }

    function PreStore( Keyframe_DataStore $oDS )
    {
        $bOk = false;

        switch($this->c) {
            case 'users':
                if( !$oDS->Value('lang') ) $oDS->SetValue('lang','E');
                // help make sure a blank value is cast as an integer
                if( !$oDS->Value('gid1') ) $oDS->SetValue('gid1','0');
                $bOk = true;
                break;

            case 'groups':
                // help make sure a blank value is cast as an integer
                if( !$oDS->Value('gid_inherited') ) $oDS->SetValue('gid_inherited','0');
                // don't allow a group to inherit itself (we don't check for loops but at least we can check for this)  not sure what happens if you loop
                if( $oDS->Key() && $oDS->value('gid_inherited')==$oDS->Key() ) {
                    $oDS->SetValue( 'gid_inherited', 0 );
                }
                $bOk = true;
                break;

            case 'perms':
                $bOk = true;
                break;
        }

        return( $bOk );
    }


    private function makeUserSelect( $name_sf, $name )
    {
        $raOpts = $this->getOptsFromTableCol( $this->oApp->kfdb, 'SEEDSession_Users', 'realname', '-- No User --' );
        return( $this->getSelectTemplateFromArray( $name_sf, $name, $raOpts ) );
    }
    private function makeGroupSelect( $name_sf, $name )
    {
        $raOpts = $this->getOptsFromTableCol( $this->oApp->kfdb, 'SEEDSession_Groups', 'groupname', '-- No Group --' );
        return( $this->getSelectTemplateFromArray( $name_sf, $name, $raOpts ) );
    }

    private function getSelectTemplateFromArray( $name_sf, $name, $raOpts )
    /**********************************************************************
        Make a <select> template from a given array of options
     */
    {
        $s = "<select name='$name_sf'>";
        foreach( $raOpts as $label => $val ) {
            $s .= "<option value='$val' [[ifeq:[[value:$name]]|$val|selected| ]]>$label</option>";
        }
        $s .= "</select>";

        return( $s );
    }
// this could be a general method in SEEDForm or KeyframeForm
    private function getOptsFromTableCol( KeyframeDatabase $kfdb, $table, $tableCol, $emptyLabel = false )
    /*****************************************************************************************************
        Make an array of <options> from the contents of a table column and its _key.

        emptyLabel is the label of a '' option : omitted if false
     */
    {
        $raOpts = array();
        if( $emptyLabel !== false ) {
            $raOpts[$emptyLabel] = "";
        }
        $raVals = $kfdb->QueryRowsRA( "SELECT _key as val,$tableCol as label FROM $table" );
        foreach( $raVals as $ra ) {
            $raOpts[$ra['label']] = $ra['val'];
        }

        return( $raOpts );
    }
}


class SEEDPerm_KFUIListForm_Config extends KeyFrameUI_ListFormUI_Config
/*********************************
    Get the configuration for a KeyframeUI_ListFormUI on the SEEDPerms tables
        $c = seedpermsclasses | seedperms
 */
{
    private $oApp;
    private $c;
    private $oSEEDPerms;

    function __construct( SEEDAppDB $oApp, $c )
    {
        $this->oApp = $oApp;
        $this->c = $c;
        $this->oSEEDPerms = new SEEDPermsRead( $this->oApp, ['dbname'=>$this->oApp->kfdb->GetDB()] );       // uses the same db as the kfdb

        parent::__construct();  // sets the default raConfig
        $this->raConfig['sessNamespace'] = ($c=='seedpermsclasses' ? 'SEEDPermsClasses' : 'SEEDPerms');
        $this->raConfig['cid']           = ($c=='seedpermsclasses' ? 'SC' : 'SP');
        $this->raConfig['kfrel']         = $this->oSEEDPerms->GetKfrel($c == 'seedpermsclasses' ? 'C' : 'PxC');
        $this->raConfig['raListConfig']['cols'] =
                ($c=='seedpermsclasses'
                    // SEEDPermsClasses list cols
                    ? [ ['label'=>'k',          'col'=>'_key'],
                        ['label'=>'App',        'col'=>'application'],
                        ['label'=>'Class name', 'col'=>'name'] ]
                    // SEEDPerms list cols
                    : [ [ 'label'=>'App',        'col'=>'C_application' ],
                        [ 'label'=>'Class Name', 'col'=>'C_name' ],
                        [ 'label'=>'User',       'col'=>'user_id' ],
                        [ 'label'=>'Group',      'col'=>'user_group' ],
                        [ 'label'=>'Modes',      'col'=>'modes' ] ]);
        // conveniently, we can use the same format for search filters as for the cols (because filters can be cols or aliases)
        $this->raConfig['raSrchConfig']['filters'] = $this->raConfig['raListConfig']['cols'];
    }

    /* These are not called directly, but referenced in raConfig
     */
    function FormTemplate()
    {
        if( $this->c == 'seedpermsclasses' ) {
            $s = "|||BOOTSTRAP_TABLE(class='col-md-6'|class='col-md-6')\n"
                ."||| App  || [[Text:application]]\n"
                ."||| Class name  || [[Text:name]]\n"
                ."||| <input type='submit'>  [[HiddenKey:]]";
        } else {
            $s = "|||BOOTSTRAP_TABLE(class='col-md-6'|class='col-md-6')\n"
                ."||| Class name  || [[Text:C_name]] should be a select\n"
                ."||| Mode  || [[Text:modes]]\n"
                ."||| User  || ".$this->makeUserSelect( 'sfSPp_user_id', 'user_id' )
                ."||| Group || ".$this->makeGroupSelect( 'sfSPp_user_group', 'user_group' )
                ."||| <input type='submit'>  [[HiddenKey:]]";
        }
        return( $s );
    }



    private function makeUserSelect( $name_sf, $name )
    {
        $raOpts = $this->getOptsFromTableCol( $this->oApp->kfdb, 'SEEDSession_Users', 'realname', '-- No User --' );
        return( $this->getSelectTemplateFromArray( $name_sf, $name, $raOpts ) );
    }
    private function makeGroupSelect( $name_sf, $name )
    {
        $raOpts = $this->getOptsFromTableCol( $this->oApp->kfdb, 'SEEDSession_Groups', 'groupname', '-- No Group --' );
        return( $this->getSelectTemplateFromArray( $name_sf, $name, $raOpts ) );
    }

    private function getSelectTemplateFromArray( $name_sf, $name, $raOpts )
    /**********************************************************************
        Make a <select> template from a given array of options
     */
    {
        $s = "<select name='$name_sf'>";
        foreach( $raOpts as $label => $val ) {
            $s .= "<option value='$val' [[ifeq:[[value:$name]]|$val|selected| ]]>$label</option>";
        }
        $s .= "</select>";

        return( $s );
    }
// this could be a general method in SEEDForm or KeyframeForm
    private function getOptsFromTableCol( KeyframeDatabase $kfdb, $table, $tableCol, $emptyLabel = false )
    /*****************************************************************************************************
        Make an array of <options> from the contents of a table column and its _key.

        emptyLabel is the label of a '' option : omitted if false
     */
    {
        $raOpts = array();
        if( $emptyLabel !== false ) {
            $raOpts[$emptyLabel] = "";
        }
        $raVals = $kfdb->QueryRowsRA( "SELECT _key as val,$tableCol as label FROM $table" );
        foreach( $raVals as $ra ) {
            $raOpts[$ra['label']] = $ra['val'];
        }

        return( $raOpts );
    }
}


class UsersGroupsPermsUI
{
    private $oApp;
    private $oAcctDB;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oAcctDB = new SEEDSessionAccountDB2( $this->oApp->kfdb, $this->oApp->sess->GetUID(), ['logdir'=>$this->oApp->logdir] );
    }


    function GetConfig( $sMode )
    {
        if( in_array($sMode, ['seedpermsclasses' ,'seedperms']) ) {
            $oConf = new SEEDPerm_KFUIListForm_Config($this->oApp, $sMode);
        } else {
            if( !(in_array($sMode, ['users','groups','perms'])) ) {
                $sMode = 'users';
            }
            $oConf = new SEEDUGP_KFUIListForm_Config($this->oApp, $sMode);
        }
        return( $raConfig = $oConf->GetConfig() );
    }


    function drawUsersInfo( KeyframeUIComponent $oComp )
    {
        $s = "";
        $s .= $this->ugpStyle();

        if( !($kUser = $oComp->Get_kCurr()) )  goto done;

        $raGroups = $this->oAcctDB->GetGroupsFromUser( $kUser, array('bNames'=>true) );
        $raPerms = $this->oAcctDB->GetPermsFromUser( $kUser );
        $raMetadata = $this->oAcctDB->GetUserMetadata( $kUser );

        /* Groups list
         */
        $sG = "<p><b>Groups</b></p>"
             ."<div class='ugpBox'>"
             .SEEDCore_ArrayExpandSeries( $raGroups, "[[v]] &nbsp;<span style='float:right'>([[k]])</span><br/>" )
             ."</div>";

        // group add/remove
        $oFormB = new SEEDCoreForm( "B" );
        $sG .= "<div>"
              ."<form action='".$this->oApp->PathToSelf()."' method='post'>"
              //.$this->oComp->EncodeHiddenFormParms()
              .$oFormB->Hidden( 'uid', ['value'=>$kUser] )
              //.$oFormB->Text( 'gid', '' )
              .$this->makeGroupSelect( 'sfBp_gid', 'gid' )
              ."<input type='hidden' name='ugpFunction' value='UsersXGroups'/>"     //
              ."<input type='submit' name='cmd' value='Add'/><INPUT type='submit' name='cmd' value='Remove'/>"
              ."</form></div>";


        /* Perms list
         */
        $sP = "<p><b>Permissions</b></p>"
             ."<div class='ugpBox'>";
        ksort($raPerms['perm2modes']);
        foreach( $raPerms['perm2modes'] as $k => $v ) {
            $sP .= "$k &nbsp;<span style='float:right'>( $v )</span><br/>";
        }
        $sP .= "</div>";


        /* Metadata list
         */
        $sM = "<p><b>Metadata</b></p>"
             ."<div class='ugpBox'>";
        foreach( $raMetadata as $k => $v ) {
            $sM .= "$k &nbsp;<span style='float:right'>( $v )</span><br/>";
        }
        $sM .= "</div>";
/*
            // Metadata Add/Remove
            $s .= "<BR/>"
                 ."<FORM action='".$this->oApp->PathToSelf()."' method='post'>"
                 .$this->oComp->EncodeHiddenFormParms()
                 .SEEDForm_Hidden( 'uid', $kUser )
                 .SEEDForm_Hidden( 'form', "UsersMetadata" )
                 ."k ".SEEDForm_Text( 'meta_k', '' )
                 ."<br/>"
                 ."v ".SEEDForm_Text( 'meta_v', '' )
                 ."<INPUT type='submit' name='cmd' value='Set'/><INPUT type='submit' name='cmd' value='Remove'/>"
                 ."</FORM></TD>";
*/

        $s .= "<div class='row'>"
                 ."<div class='col-md-4'>$sG</div>"
                 ."<div class='col-md-4'>$sP</div>"
                 ."<div class='col-md-4'>$sM</div>"
             ."</div>";

        done:
        return( $s );
    }

    function drawGroupsInfo( KeyframeUIComponent $oComp )
    {
        $s = "";

        return( $s );
    }

    function drawPermsInfo( KeyframeUIComponent $oComp )
    {
        $s = "";

        return( $s );

    }

    // should be somewhere else
    function drawSeedPermsClassesInfo( KeyframeUIComponent $oComp )
    {
        $s = "";

        return( $s );

    }
    function drawSeedPermsInfo( KeyframeUIComponent $oComp )
    {
        $s = "";

        return( $s );

    }

    private function ugpStyle()
    {
        $s = "<style>"
             .".ugpForm { font-size:14px; }"
             .".ugpBox { height:200px; border:1px solid gray; padding:3px; font-family:sans serif; font-size:11pt; overflow-y:scroll }"
            ."</style>";
        return( $s );
    }


    private function makeUserSelect( $name_sf, $name )
    {
        $raOpts = $this->getOptsFromTableCol( $this->oApp->kfdb, 'SEEDSession_Users', 'realname', '-- No User --' );
        return( $this->getSelectTemplateFromArray( $name_sf, $name, $raOpts ) );
    }
    private function makeGroupSelect( $name_sf, $name )
    {
        $raOpts = $this->getOptsFromTableCol( $this->oApp->kfdb, 'SEEDSession_Groups', 'groupname', '-- No Group --' );
        return( $this->getSelectTemplateFromArray( $name_sf, $name, $raOpts ) );
    }

    private function getSelectTemplateFromArray( $name_sf, $name, $raOpts )
    /**********************************************************************
        Make a <select> template from a given array of options
     */
    {
        $s = "<select name='$name_sf'>";
        foreach( $raOpts as $label => $val ) {
            $s .= "<option value='$val' [[ifeq:[[value:$name]]|$val|selected| ]]>$label</option>";
        }
        $s .= "</select>";

        return( $s );
    }

    private function getSelectTemplateFromTableCol( KeyframeDatabase $kfdb, $name_sf, $name, $table, $tableCol, $emptyLabel = false )
    /********************************************************************************************************************************
        Make a <select> template from the contents of a table column and its _key.

        emptyLabel is the label of a '' option : omitted if false
     */
    {
        $raOpts = $this->getOptsFromTableCol( $kfdb, $table, $tableCol, $emptyLabel );

        return( $this->getSelectTemplateFromArray( $name_sf, $name, $raOpts ) );
    }

// this could be a general method in SEEDForm or KeyframeForm
    private function getOptsFromTableCol( KeyframeDatabase $kfdb, $table, $tableCol, $emptyLabel = false )
    /*****************************************************************************************************
        Make an array of <options> from the contents of a table column and its _key.

        emptyLabel is the label of a '' option : omitted if false
     */
    {
        $raOpts = array();
        if( $emptyLabel !== false ) {
            $raOpts[$emptyLabel] = "";
        }
        $raVals = $kfdb->QueryRowsRA( "SELECT _key as val,$tableCol as label FROM $table" );
        foreach( $raVals as $ra ) {
            $raOpts[$ra['label']] = $ra['val'];
        }

        return( $raOpts );
    }



    private function getSelectTemplate($table, $col, $name, $bEmpty = FALSE)
    /****************************************************
     * Generate a template of a <select> that defines a select element
     *
     * table - The database table to get the options from
     * col - The database column that the options are associated with.
     * name - The database column that contains the user understandable name for the option
     * bEmpty - If a None option with value of NULL should be included in the select
     *
     * eg. table = SEEDSession_Groups, col = gid, name = groupname
     * will result a select element with the groups as options with the gid of kfrel as the selected option
     */
    {
        $options = $this->oApp->kfdb->QueryRowsRA("SELECT * FROM ".$table);
        $s = "<select name='$col'>";
        if($bEmpty){
            $s .= "<option value='NULL'>None</option>";
        }
        foreach($options as $option){
            $s .= "<option [[ifeq:[[value:".$col."]]|".$option["_key"]."|selected| ]] value='".$option["_key"]."'>".$option[$name]."</option>";
        }
        $s .= "</select>";
        return $s;
    }

    private function getUserStatusSelectionFormTemplate(){
        global $config_KFDB;
        $db = $config_KFDB['cats']['kfdbDatabase'];
        $options = $this->oApp->kfdb->Query1("SELECT SUBSTRING(COLUMN_TYPE,5) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='".$db."' AND TABLE_NAME='SEEDSession_Users' AND COLUMN_NAME='eStatus'");
        $options = substr($options, 1,strlen($options)-2);
        $options_array = str_getcsv($options, ',', "'");
        $s = "";
        foreach($options_array as $option){
            $s .= "<option [[ifeq:[[value:eStatus]]|".$option."|selected| ]]>".$option."</option>";
        }
        return $s;
    }




    private function doCmd( $mode )
    /******************************
        Process the custom commands on each page e.g. add user to group, remove user from group, add/remove metadata
     */
    {
        $oForm = new SEEDCoreForm( "B" );
        $oForm->Load();
        switch( SEEDInput_Str('ugpFunction') ) {
            case 'UsersXGroups':
                // Initiated from Users or Groups page
                $uid = intval($oForm->Value('uid'));
                $gid = intval($oForm->Value('gid'));
                if( $uid && $gid ) {
                    switch( SEEDInput_Str('cmd') ) {
                        case 'Add':    $this->oAcctDB->AddUserToGroup($uid, $gid);        break;
                        case 'Remove': $this->oAcctDB->RemoveUserFromGroup($uid, $gid);   break;
                    }
                }
                break;
            case 'UsersMetadata':
                break;
            case 'GroupsMetadata':
                break;
        }
    }
}

/*
class UGP_SEEDUI extends SEEDUI
{
    private $oSVA;

    function __construct( SEEDAppSession $oApp, $sApplication )
    {
        parent::__construct();
        $this->oSVA = new SEEDSessionVarAccessor( $oApp->sess, $sApplication );
    }

    function GetUIParm( $cid, $name )      { return( $this->oSVA->VarGet( "$cid|$name" ) ); }
    function SetUIParm( $cid, $name, $v )  { $this->oSVA->VarSet( "$cid|$name", $v ); }
    function ExistsUIParm( $cid, $name )   { return( $this->oSVA->VarIsSet( "$cid|$name" ) ); }
}
*/