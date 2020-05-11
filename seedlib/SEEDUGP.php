<?php

include_once( SEEDROOT."Keyframe/KeyframeUI.php" );


class UsersGroupsPermsUI
{
    private $oApp;
    private $oAcctDB;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oAcctDB = new SEEDSessionAccountDB2( $this->oApp->kfdb, $this->oApp->sess->GetUID(), ['logdir'=>$this->oApp->logdir] );
    }

    function DrawUI()
    {
        $s = "";

        $mode = $this->oApp->oC->oSVA->SmartGPC( 'adminUsersMode', array('Users','Groups','Permissions') );

        $this->doCmd( $mode );

        $raListConfig = [ 'bUse_key' => true ]; // constants for the __construct that can be used in Start()
        $raListParms = [];                      // variables that can be computed or altered during Start()

        switch( $mode ) {
            case "Users":
                $cid = "U";
                $kfrel = $this->oAcctDB->GetKfrel($cid);
                $raListConfig['cols'] = [
                    [ 'label'=>'User #',  'col'=>'_key' ],
                    [ 'label'=>'Name',    'col'=>'realname' ],
                    [ 'label'=>'Email',   'col'=>'email' ],
                    [ 'label'=>'Status',  'col'=>'eStatus' ],
                    [ 'label'=>'Group1',  'col'=>'G_groupname' ],
                ];
                $raListConfig['fnRowTranslate'] = array($this,"usersListRowTranslate");
                // Not the same format as listcols because these actually need the column names not aliases.
                // For groups and perms it happens to work but when _key is included in the WHERE it is ambiguous
                $raSrchParms['filters'] = [
                    [ 'label'=>'User #',  'col'=>'U._key' ],
                    [ 'label'=>'Name',    'col'=>'U.realname' ],
                    [ 'label'=>'Email',   'col'=>'U.email' ],
                    [ 'label'=>'Status',  'col'=>'U.eStatus' ],
                    [ 'label'=>'Group1',  'col'=>'G.groupname' ],
                ];
                $formTemplate = $this->getUsersFormTemplate();
                $raSEEDFormParms = ['DSParms'=>['fn_DSPreStore'=> [$this,'usersPreStore']]];
                break;
            case "Groups":
                $cid = "G";
                $kfrel = $this->oAcctDB->GetKfrel($cid);
                $raListConfig['cols'] = [
                    [ 'label'=>'k',          'col'=>'_key' ],
                    [ 'label'=>'Group Name', 'col'=>'groupname' ],
                    [ 'label'=>'Inherited',  'col'=>'gid_inherited' ],
                ];
                $raListConfig['fnRowTranslate'] = array($this,"groupsListRowTranslate");
                $raSrchParms['filters'] = $raListConfig['cols'];     // conveniently the same format
                $formTemplate = $this->getGroupsFormTemplate();
                $raSEEDFormParms = ['DSParms'=>['fn_DSPreStore'=> [$this,'groupsPreStore']]];
                break;
            case "Permissions":
                $cid = "P";
                $kfrel = $this->oAcctDB->GetKfrel($cid);
                $raListConfig['cols'] = [
                    [ 'label'=>'Permission', 'col'=>'perm' ],
                    [ 'label'=>'Modes',      'col'=>'modes' ],
                    [ 'label'=>'User',       'col'=>'U_realname' ],
                    [ 'label'=>'Group',      'col'=>'G_groupname' ],
                ];
                $raListConfig['fnRowTranslate'] = array($this,"permsListRowTranslate");
                $raSrchParms['filters'] = $raListConfig['cols'];     // conveniently the same format
                $formTemplate = $this->getPermsFormTemplate();
                $raSEEDFormParms = ['DSParms'=>['fn_DSPreStore'=> [$this,'permsPreStore']]];
                break;
        }


        $oUI = new UGP_SEEDUI( $this->oApp, "UGP" );
        $oComp = new KeyframeUIComponent( $oUI, $kfrel, $cid, ['raSEEDFormParms'=>$raSEEDFormParms] );
        $oComp->Update();

//$this->oApp->kfdb->SetDebug(2);
        $oList = new KeyframeUIWidget_List( $oComp, $raListConfig );
        $oSrch = new SEEDUIWidget_SearchControl( $oComp, $raSrchParms );
        $oForm = new KeyframeUIWidget_Form( $oComp, ['sExpandTemplate'=>$formTemplate] );

        $oComp->Start();    // call this after the widgets are registered

        list($oView,$raWindowRows) = $oComp->GetViewWindow($oComp->Get_iWindowOffset(), $oComp->Get_nWindowSize());
        $oViewWindow = new SEEDUIComponent_ViewWindow( $oComp, ['bEnableKeys'=>true] );
        $oViewWindow->SetViewSlice( $raWindowRows, ['iViewSliceOffset' => $oComp->Get_iWindowOffset(),
                                                    'nViewSize' => $oView->GetNumRows()] );
        $sList = $oList->ListDrawInteractive( $oViewWindow, $raListParms );

        $sSrch = $oSrch->Draw();
        $sForm = $oForm->Draw();

        // Do this after Start() because it can change things like kCurr
        $sInfo = "";
// need a clearer way to tell when the New form is open
        if( $oComp->oForm->GetKey() ) {     // only show extra info for existing items, not when the New form is open
            switch( $mode ) {
                case 'Users':       $sInfo = $this->drawUsersInfo( $oComp );    break;
                case 'Groups':      $sInfo = $this->drawGroupsInfo( $oComp );   break;
                case 'Permissions': $sInfo = $this->drawPermsInfo( $oComp );    break;
            }
        }

        $s = $oList->Style()
            ."<form method='post'>"
                ."<input type='submit' name='adminUsersMode' value='Users'/>&nbsp;&nbsp;"
                ."<input type='submit' name='adminUsersMode' value='Groups'/>&nbsp;&nbsp;"
                ."<input type='submit' name='adminUsersMode' value='Permissions'/>"
            ."</form>"
            ."<h2>$mode</h2>"
            ."<div class='container-fluid'>"
                ."<div class='row'>"
                    ."<div class='col-md-6'>"
                        ."<div>".$sSrch."</div>"
                        ."<div>".$sList."</div>"
                    ."</div>"
                    ."<div class='col-md-6'>"
                        ."<div style='margin-bottom:5px'><a href='?sf{$cid}ui_k=0'><button>New</button></a>&nbsp;&nbsp;&nbsp;<button>Delete</button></div>"
                        ."<div style='width:90%;padding:20px;border:2px solid #999'>".$sForm."</div>"
                    ."</div>"
                ."</div>"
                .$sInfo
            ."</div>";


//         $s .= "<div class='seedjx' seedjx-cmd='test'>"
//                  ."<div class='seedjx-err alert alert-danger' style='display:none'></div>"
//                  ."<div class='seedjx-out'>"
//                      ."<input name='a'/>"
//                      ."<select name='test'/><option value='good'>Good</option><option value='bad'>Bad</option></select>"
//                      ."<button class='seedjx-submit'>Go</button>"
//                  ."</div>"
//              ."</div>";

        return( $s );
    }

    private function drawUsersInfo( KeyframeUIComponent $oComp )
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

    private function drawGroupsInfo( KeyframeUIComponent $oComp )
    {
        $s = "";

        return( $s );
    }

    private function drawPermsInfo( KeyframeUIComponent $oComp )
    {
        $s = "";

        return( $s );

    }

    function ugpStyle()
    {
        $s = "<style>"
             .".ugpForm { font-size:14px; }"
             .".ugpBox { height:200px; border:1px solid gray; padding:3px; font-family:sans serif; font-size:11pt; overflow-y:scroll }"
            ."</style>";
        return( $s );
    }

    private function getUsersFormTemplate()
    {
        $s = "|||BOOTSTRAP_TABLE(class='col-md-6'|class='col-md-6')\n"
            ."||| User #|| [[Key: | readonly]]\n"
            ."||| Name  || [[Text:realname]]\n"
            ."||| Email || [[Text:email]]\n"
            ."||| Password || [[if:[[value:password]]|-- cannot change here --|[[Text:password]] ]]\n"
            ."||| Status|| ".$this->getSelectTemplateFromArray( 'sfUp_eStatus', 'eStatus',
                                    ['ACTIVE'=>'ACTIVE','INACTIVE'=>'INACTIVE','PENDING'=>'PENDING'] )."\n"
            ."||| Group || ".$this->makeGroupSelect( 'sfUp_gid1', 'gid1' )
            ."||| <input type='submit'>";
        return( $s );
    }

    private function getGroupsFormTemplate()
    {
        $s = "|||BOOTSTRAP_TABLE(class='col-md-6'|class='col-md-6')\n"
            ."||| Name            || [[Text:groupname]]\n"
            ."||| Inherited Group || ".$this->makeGroupSelect('sfGp_gid_inherited', 'gid_inherited' )
            ."||| <input type='submit'> [[HiddenKey:]]";

        return( $s );
    }

    private function getPermsFormTemplate()
    {
        $s = "|||BOOTSTRAP_TABLE(class='col-md-6'|class='col-md-6')\n"
            ."||| Name  || [[Text:perm]]\n"
            ."||| Mode  || [[Text:modes]]\n"
            ."||| User  || ".$this->makeUserSelect( 'sfPp_uid', 'uid' )
            ."||| Group || ".$this->makeGroupSelect( 'sfPp_gid', 'gid' )
            ."||| <input type='submit'>  [[HiddenKey:]]";

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


    /* Callbacks to amend the contents of lists for each table
     */
    function usersListRowTranslate( $raRow )
    {
        // show the groupname with (gid) appended for convenience
        if( $raRow['gid1'] && $raRow['G_groupname'] ) {
            $raRow['G_groupname'] .= " (".$raRow['gid1'].")";
        }
        return( $raRow );
    }
    function groupsListRowTranslate( $raRow )
    {
        // show the inherited groupname instead of the key
        if( $raRow['gid_inherited'] == 0 ) {
            $raRow['gid_inherited'] = '';
        } else {
            $g = intval($raRow['gid_inherited']);   // protect against sql injection
            $raRow['gid_inherited'] = $this->oApp->kfdb->Query1("SELECT groupname FROM SEEDSession_Groups WHERE _key='$g'" )
                                     ." ($g)";
        }
        return( $raRow );
    }
    function permsListRowTranslate( $raRow )
    {
        return( $raRow );
    }


    /* Callbacks to process DSPreStore for each table
     */
    function usersPreStore( $kfr )
    {
        if( !$kfr->value('lang') ) $kfr->SetValue('lang','E');
        // help make sure a blank value is cast as an integer
        if( !$kfr->value('gid1') ) $kfr->SetValue('gid1','0');
        return( true );
    }
    function groupsPreStore( $kfr )
    {
        // help make sure a blank value is cast as an integer
        if( !$kfr->value('gid_inherited') ) $kfr->SetValue('gid_inherited','0');
        // don't allow a group to inherit itself (we don't check for loops but at least we can check for this)  not sure what happens if you loop
        if( $kfr->Key() && $kfr->value('gid_inherited')==$kfr->Key() ) {
            $kfr->SetValue( 'gid_inherited', 0 );
        }
        return( true );
    }
    function permsPreStore( $kfr )
    {
        return( true );
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
