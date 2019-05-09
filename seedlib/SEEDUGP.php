<?php

include_once( SEEDROOT."Keyframe/KeyframeUI.php" );


class UsersGroupsPermsUI
{
    private $oApp;
    private $oAcctDB;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oAcctDB = new SEEDSessionAccountDBRead2( $this->oApp->kfdb, $this->oApp->sess->GetUID(), array('logdir'=>$this->oApp->logdir) );
    }

    function DrawUI()
    {
        $s = "";

        $mode = $this->oApp->oC->oSVA->SmartGPC( 'adminUsersMode', array('Users','Groups','Permissions') );

        $raListParms = array( 'bUse_key' => true );

        switch( $mode ) {
            case "Users":
                $cid = "U";
                $kfrel = $this->oAcctDB->GetKfrel('U');
                $raListParms['cols'] = array(
                    array( 'label'=>'User #',  'col'=>'_key' ),
                    array( 'label'=>'Name',    'col'=>'realname' ),
                    array( 'label'=>'Email',   'col'=>'email'  ),
                    array( 'label'=>'Status',  'col'=>'eStatus'  ),
                    array( 'label'=>'Group1',  'col'=>'G_groupname'  ),
                );
                $raListParms['fnRowTranslate'] = array($this,"usersListRowTranslate");
                // Not the same format as listcols because these actually need the column names not aliases.
                // For groups and perms it happens to work but when _key is included in the WHERE it is ambiguous
                $raSrchParms['filters'] = array(
                    array( 'label'=>'User #',  'col'=>'U._key' ),
                    array( 'label'=>'Name',    'col'=>'U.realname' ),
                    array( 'label'=>'Email',   'col'=>'U.email'  ),
                    array( 'label'=>'Status',  'col'=>'U.eStatus'  ),
                    array( 'label'=>'Group1',  'col'=>'G.groupname'  ),
                );
                $formTemplate = $this->getUsersFormTemplate();
                break;
            case "Groups":
                $cid = "G";
                $kfrel = $this->oAcctDB->GetKfrel('G');
                $raListParms['cols'] = array(
                    array( 'label'=>'k',          'col'=>'_key' ),
                    array( 'label'=>'Group Name', 'col'=>'groupname'  ),
                    array( 'label'=>'Inherited',  'col'=>'gid_inherited'  ),
                );
                $raSrchParms['filters'] = $raListParms['cols'];     // conveniently the same format
                $formTemplate = $this->getGroupsFormTemplate();
                break;
            case "Permissions":
                $cid = "P";
                $kfrel = $this->oAcctDB->GetKfrel('P');
                $raListParms['cols'] = array(
                    array( 'label'=>'Permission', 'col'=>'perm'  ),
                    array( 'label'=>'Modes',      'col'=>'modes'  ),
                    array( 'label'=>'User',       'col'=>'U_realname'  ),
                    array( 'label'=>'Group',      'col'=>'G_groupname'  ),
                );
                $raSrchParms['filters'] = $raListParms['cols'];     // conveniently the same format
                $formTemplate = $this->getPermsFormTemplate();
                break;
        }

        $oUI = new UGP_SEEDUI( $this->oApp, "Stegosaurus" );
        $oComp = new KeyframeUIComponent( $oUI, $kfrel, $cid );
        $oComp->Update();

//$this->oApp->kfdb->SetDebug(2);
        $oList = new KeyframeUIWidget_List( $oComp );
        $oSrch = new SEEDUIWidget_SearchControl( $oComp, $raSrchParms );
        $oForm = new KeyframeUIWidget_Form( $oComp, array('sTemplate'=>$formTemplate) );

        $oComp->Start();    // call this after the widgets are registered

        list($oView,$raWindowRows) = $oComp->GetViewWindow();
        $sList = $oList->ListDrawInteractive( $raWindowRows, $raListParms );

        $sSrch = $oSrch->Draw();
        $sForm = $oForm->Draw();

        // Have to do this after Start() because it can change things like kCurr
        switch( $mode ) {
            case 'Users':       $sInfo = $this->drawUsersInfo( $oComp );    break;
            case 'Groups':      $sInfo = $this->drawGroupsInfo( $oComp );   break;
            case 'Permissions': $sInfo = $this->drawPermsInfo( $oComp );    break;
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
             ."<div class='ugpBox'>";
        foreach( $raGroups as $kGroup => $sGroupname ) {
            $sG .= "$sGroupname &nbsp;<span style='float:right'>($kGroup)</span><br/>";
        }
        $sG .= "</div>";

        // group add/remove
        $oFormB = new SEEDCoreForm( "B" );
        $sG .= "<div>"
              ."<form action='{$_SERVER['PHP_SELF']}' method='post'>"
              //.$this->oComp->EncodeHiddenFormParms()
              //.SEEDForm_Hidden( 'uid', $kUser )
              //.SEEDForm_Hidden( 'form', "UsersXGroups" )
              .$oFormB->Text( 'gid', '' )
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
                 ."<FORM action='{$_SERVER['PHP_SELF']}' method='post'>"
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
        $s = "|||BOOTSTRAP_TABLE(class='col-md-6',class='col-md-6')\n"
            ."||| User #|| [[Text:_key | readonly]]\n"
            ."||| Name  || [[Text:realname]]\n"
            ."||| Email || [[Text:email]]\n"
            ."||| Status|| <select name='eStatus'>".$this->getUserStatusSelectionFormTemplate()."</select>\n"
            ."||| Group || ".$this->getSelectTemplate("SEEDSession_Groups", "gid1", "groupname")."\n"
            ."||| <input type='submit'>"
                ;

        return( $s );
    }

    private function getGroupsFormTemplate()
    {
        $s = "|||BOOTSTRAP_TABLE(class='col-md-6',class='col-md-6')\n"
            ."||| Name            || [[Text:groupname]]\n"
                ."||| Inherited Group || ".$this->getSelectTemplate("SEEDSession_Groups", "gid_inherited", "groupname", TRUE)."\n"
                    ."||| <input type='submit'>"
                ;

        return( $s );
    }

    private function getPermsFormTemplate()
    {
        $s = "|||BOOTSTRAP_TABLE(class='col-md-6',class='col-md-6')\n"
            ."||| Name  || [[Text:perm]]\n"
            ."||| Mode  || [[Text:modes]]\n"
            ."||| User  || ".$this->getSelectTemplate("SEEDSession_Users", "uid", "realname", TRUE)."\n"
            ."||| Group || ".$this->getSelectTemplate("SEEDSession_Groups", "gid", "groupname", TRUE)."\n"
            ."||| <input type='submit'>"
                ;

        return( $s );
    }

    private function getSelectTemplate($table, $col, $name, $bEmpty = FALSE)
    /****************************************************
     * Generate a template of that defines a select element
     *
     * table - The database table to get the options from
     * col - The database collum that the options are associated with.
     * name - The database collum that contains the user understandable name for the option
     * bEmpty - If a None option with value of NULL should be included in the select
     *
     * eg. table = SEEDSession_Groups, col = gid, name = groupname
     * will result a select element with the groups as options with the gid of kfrel as the selected option
     */
    {
        $options = $this->oApp->kfdb->QueryRowsRA("SELECT * FROM ".$table);
        $s = "<select name='".$col."'>";
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

    function usersListRowTranslate( $raRow )
    {
        if( $raRow['gid1'] && $raRow['G_groupname'] ) {
            // When displaying the group name it's helpful to show the gid too
            $raRow['G_groupname'] .= " (".$raRow['gid1'].")";
        }

        return( $raRow );
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
