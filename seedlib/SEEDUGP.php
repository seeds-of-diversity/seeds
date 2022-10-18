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
                // show the uid with the user because sometimes the realname is blank, which would hide their presence in the list
                if( $raRow['uid'] )  $raRow['U_realname'] .= " ({$raRow['uid']})";
                break;
        }

        return( $raRow );
    }

    function FormTemplate()
    {
        switch($this->c) {
            case 'users':
                $s = "|||BOOTSTRAP_TABLE(class='col-md-6'|class='col-md-6')\n"
                    ."||| User #|| [[Key: | readonly]]\n"               // generates HiddenKey in readonly mode
                    ."||| Name  || [[Text:realname]]\n"
                    ."||| Email || [[Text:email]]\n"
                    ."||| Password || [[if:[[value:password]]|-- cannot change here --|[[Text:password]] ]]\n"
                    ."||| Status|| ".SEEDFormBasic::DrawSelectCtrlFromOptsArray( 'sfUp_eStatus', 'eStatus',
                                            ['ACTIVE'=>'ACTIVE','INACTIVE'=>'INACTIVE','PENDING'=>'PENDING'] )."\n"
                    ."||| Group1 || ".SEEDSessionAccount_AdminUI::MakeGroupSelectCtrl($this->oAcctDB, 'sfUp_gid1', 'gid1')

                    ."||| <input type='submit' value='Save'>";
                break;

            case 'groups':
                $s = "|||BOOTSTRAP_TABLE(class='col-md-6'|class='col-md-6')\n"
                    ."||| Name            || [[Text:groupname]]\n"
                    ."||| Inherited Group || ".SEEDSessionAccount_AdminUI::MakeGroupSelectCtrl($this->oAcctDB, 'sfGp_gid_inherited', 'gid_inherited' )
                    ."||| <input type='submit' value='Save'> [[HiddenKey:]]";
                break;

            case 'perms':
                $s = "|||BOOTSTRAP_TABLE(class='col-md-6'|class='col-md-6')\n"
                    ."||| Name  || [[Text:perm]]\n"
                    ."||| Mode  || [[Text:modes]]\n"
                    ."||| User  || ".SEEDSessionAccount_AdminUI::MakeUserSelectCtrl($this->oAcctDB, 'sfPp_uid', 'uid')
                    ."||| Group || ".SEEDSessionAccount_AdminUI::MakeGroupSelectCtrl($this->oAcctDB, 'sfPp_gid', 'gid')
                    ."||| <input type='submit' value='Save'>  [[HiddenKey:]]";
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
    private $oAcctDB;

    function __construct( SEEDAppDB $oApp, $c )
    {
        $this->oApp = $oApp;
        $this->c = $c;
        $this->oSEEDPerms = new SEEDPermsRead( $this->oApp, ['dbname'=>$this->oApp->kfdb->GetDB()] );       // uses the same db as the kfdb
        $this->oAcctDB = new SEEDSessionAccountDB2( $this->oApp->kfdb, $this->oApp->sess->GetUID(), ['logdir'=>$this->oApp->logdir] );

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
                ."||| <input type='submit' value='Save'>  [[HiddenKey:]]";
        } else {
            $s = "|||BOOTSTRAP_TABLE(class='col-md-6'|class='col-md-6')\n"
                ."||| Class name  || [[Text:C_name]] should be a select\n"
                ."||| Mode  || [[Text:modes]]\n"
                ."||| User  || ".SEEDSessionAccount_AdminUI::MakeUserSelectCtrl($this->oAcctDB, 'sfSPp_user_id', 'user_id')
                ."||| Group || ".SEEDSessionAccount_AdminUI::MakeGroupSelectCtrl($this->oAcctDB, 'sfSPp_user_group', 'user_group')
                ."||| <input type='submit' value='Save'>  [[HiddenKey:]]";
        }
        return( $s );
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
              .SEEDSessionAccount_AdminUI::MakeGroupSelectCtrl($this->oAcctDB, 'sfBp_gid', 'gid')
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
