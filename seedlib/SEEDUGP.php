<?php

include_once( SEEDROOT."Keyframe/KeyframeUI.php" );
include_once( SEEDCORE."SEEDPerms.php" );

class SEEDUGP_KFUIListForm_Config
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
    }

    // this is based on KeyframeUI_ListFormUI->GetConfigTemplate() and should probably use it.
    // the advantage of putting all of this within KeyframeUI_ListFormUI is that the callback methods like FormTemplate can access oComp
    function GetConfig()
    {
        switch($this->c) {
            case 'users':
                $raConfig = ['sessNamespace' => 'UGPUsers',
                             'cid'           => 'U',
                             'kfrel'         => $this->oAcctDB->GetKfrel('U'),
                             'raListConfig'  => ['cols' => [['label'=>'User #', 'col'=>'_key' ],
                                                            ['label'=>'Name',   'col'=>'realname' ],
                                                            ['label'=>'Email',  'col'=>'email' ],
                                                            ['label'=>'Status', 'col'=>'eStatus' ],
                                                            ['label'=>'Group1', 'col'=>'G_groupname' ]]],
                             // Not the same format as list cols because _key is ambiguous
                             'raSrchConfig'  => ['filters' => [['label'=>'User #', 'col'=>'U._key' ],
                                                               ['label'=>'Name',   'col'=>'U.realname' ],
                                                               ['label'=>'Email',  'col'=>'U.email' ],
                                                               ['label'=>'Status', 'col'=>'U.eStatus' ],
                                                               ['label'=>'Group1', 'col'=>'G.groupname' ]]]
                ];
                break;

            case 'groups':
                $raConfig = ['sessNamespace' => 'UGPGroups',
                             'cid'           => 'G',
                             'kfrel'         => $this->oAcctDB->GetKfrel('G'),
                             'raListConfig'  => ['cols' => [['label'=>'k',          'col'=>'_key' ],
                                                            ['label'=>'Group Name', 'col'=>'groupname' ],
                                                            ['label'=>'Inherited',  'col'=>'gid_inherited' ]]]
                ];
                // conveniently, we can use the same format for search filters as for the cols (because filters can be cols or aliases)
                $raConfig['raSrchConfig']['filters'] = $raConfig['raListConfig']['cols'];
                break;

            case 'perms':
                $raConfig = ['sessNamespace' => 'UGPPerms',
                             'cid'           => 'P',
                             'kfrel'         => $this->oAcctDB->GetKfrel('P'),
                             'raListConfig'  => ['cols' => [['label'=>'Permission', 'col'=>'perm' ],
                                                            ['label'=>'Modes',      'col'=>'modes' ],
                                                            ['label'=>'User',       'col'=>'U_realname' ],
                                                            ['label'=>'Group',      'col'=>'G_groupname' ]]]
                ];
                // conveniently, we can use the same format for search filters as for the cols (because filters can be cols or aliases)
                $raConfig['raSrchConfig']['filters'] = $raConfig['raListConfig']['cols'];
                break;
        }
        $raConfig['raListConfig']['fnRowTranslate'] = [$this,'ListRowTranslate'];
        $raConfig['raListConfig']['bUse_key']       = true;     // probably makes sense for KeyFrameUI to do this by default
        $raConfig['raFormConfig'] = ['fnExpandTemplate'=>[$this,'FormTemplate']];
        $raConfig['KFCompParms']  = ['raSEEDFormParms'=>['DSParms'=>['fn_DSPreStore'=> [$this,'PreStore'],
                                                                     'fn_DSPreOp'   => [$this,'PreOp']]]];

        return( $raConfig );
    }

    /* These are not called directly, but referenced in raConfig
     */
    function PreOp( Keyframe_DataStore $oDS, string $op )
    {
        return( true );
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

    function FormTemplate( SEEDCoreForm $dummy )
    {
        switch($this->c) {
            case 'users':
                $s = "|||BOOTSTRAP_TABLE(class='col-md-3'|class='col-md-9')\n"
                    ."||| User #|| [[Key: | readonly]]\n"               // generates HiddenKey in readonly mode
                    ."||| Name  || [[Text:realname | width:100%]]\n"
                    ."||| Email || [[Text:email | width:100%]]\n"
                    ."||| Password || [[if:[[value:password]]|-- cannot change here --|[[Text:password | width:100%]] ]]\n"
                    ."||| Status|| ".SEEDFormBasic::DrawSelectCtrlFromOptsArray( 'sfUp_eStatus', 'eStatus',
                                            ['ACTIVE'=>'ACTIVE','INACTIVE'=>'INACTIVE','PENDING'=>'PENDING'])."\n"
                    ."||| Group1 || ".SEEDSessionAccount_AdminUI::MakeGroupSelectCtrl($this->oAcctDB, 'sfUp_gid1', 'gid1')

                    ."||| <input type='submit' value='Save'>";
                break;

            case 'groups':
                $s = "|||BOOTSTRAP_TABLE(class='col-md-3'|class='col-md-9')\n"
                    ."||| Name            || [[Text:groupname | width:100%]]\n"
                    ."||| Inherited Group || ".SEEDSessionAccount_AdminUI::MakeGroupSelectCtrl($this->oAcctDB, 'sfGp_gid_inherited', 'gid_inherited' )
                    ."||| <input type='submit' value='Save'> [[HiddenKey:]]";
                break;

            case 'perms':
                $s = "|||BOOTSTRAP_TABLE(class='col-md-3'|class='col-md-9')\n"
                    ."||| Name  || [[Text:perm | width:100% ]]\n"
                    ."||| Mode  || [[Text:modes | width:100% ]]\n"
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


class SEEDPerm_KFUIListForm_Config
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
    }

    // this is based on KeyframeUI_ListFormUI->GetConfigTemplate() and should probably use it.
    // the advantage of putting all of this within KeyframeUI_ListFormUI is that the callback methods like FormTemplate can access oComp
    function GetConfig()
    {
        $raConfig = ['sessNamespace' => ($this->c=='seedpermsclasses' ? 'SEEDPermsClasses' : 'SEEDPerms'),
                     'cid'           => ($this->c=='seedpermsclasses' ? 'SC' : 'SP'),
                     'kfrel'         => $this->oSEEDPerms->GetKfrel($this->c == 'seedpermsclasses' ? 'C' : 'PxC'),
                     'raListConfig'  => ['cols' => ($this->c=='seedpermsclasses'
                                            // SEEDPermsClasses list cols
                                            ? [ ['label'=>'k',          'col'=>'_key'],
                                                ['label'=>'App',        'col'=>'application'],
                                                ['label'=>'Class name', 'col'=>'name'] ]
                                            // SEEDPerms list cols
                                            : [ [ 'label'=>'App',        'col'=>'C_application' ],
                                                [ 'label'=>'Class Name', 'col'=>'C_name' ],
                                                [ 'label'=>'User',       'col'=>'user_id' ],
                                                [ 'label'=>'Group',      'col'=>'user_group' ],
                                                [ 'label'=>'Modes',      'col'=>'modes' ] ]),
                                         'fnRowTranslate' => [$this,'ListRowTranslate'],
                                         'bUse_key'       => true,     // probably makes sense for KeyFrameUI to do this by default
                                        ],
                     'raFormConfig' => ['fnExpandTemplate'=>[$this,'FormTemplate']],

                     // derived class may optionally override these methods
                    'KFCompParms' => ['raSEEDFormParms'=>['DSParms'=>['fn_DSPreStore'=> [$this,'PreStore'],
                                                                      'fn_DSPreOp'   => [$this,'PreOp']]]],
        ];
        // conveniently, we can use the same format for search filters as for the cols (because filters can be cols or aliases)
        $raConfig['raSrchConfig']['filters'] = $raConfig['raListConfig']['cols'];

        return( $raConfig );
    }

    /* These are not called directly, but referenced in raConfig
     */
    function ListRowTranslate( $raRow )                    { return( $raRow ); }    // override to alter list values (only affects display)
    function PreStore( Keyframe_DataStore $oDS )           { return( true ); }      // override to validate/alter values before save; return false to cancel save
    function PreOp( Keyframe_DataStore $oDS, string $op )
    {
        return( true );
    }

    function FormTemplate( SEEDCoreForm $dummy )
    {
        if( $this->c == 'seedpermsclasses' ) {
            $s = "|||BOOTSTRAP_TABLE(class='col-md-3'|class='col-md-9')\n"
                ."||| App         || [[Text:application | width:100%]]\n"
                ."||| Class name  || [[Text:name | width:100%]]\n"
                ."||| <input type='submit' value='Save'>  [[HiddenKey:]]";
        } else {
            $raClassesOpts = $this->oSEEDPerms->GetRAClassesOpts('',true);  // get detailed opts label as key
            $s = "|||BOOTSTRAP_TABLE(class='col-md-3'|class='col-md-9')\n"
                ."||| Class name  || ".SEEDFormBasic::DrawSelectCtrlFromOptsArray( 'sfSPp_fk_SEEDPerms_Classes', 'fk_SEEDPerms_Classes', $raClassesOpts )
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
