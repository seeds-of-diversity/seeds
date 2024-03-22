<?php

/* SEED UGP application entry point
 *
 * Copyright (c) 2014-2020 Seeds of Diversity Canada
 *
 * Show permissions available by Users, Groups, Perms
 */

if( !defined("APP_SEEDUGP_DB") ) define("APP_SEEDUGP_DB", 'seeds1');

include_once( SEEDCORE."console/console02.php" );
include_once( SEEDLIB."SEEDUGP.php" );

$consoleConfig = [
    'CONSOLE_NAME' => "SEEDSessionUGP",
    'HEADER' => "Users, Groups, Permissions on ".APP_SEEDUGP_DB,
//    'HEADER_LINKS' => array( array( 'href' => 'mbr_email.php',    'label' => "Email Lists",  'target' => '_blank' ),
//                             array( 'href' => 'mbr_mailsend.php', 'label' => "Send 'READY'", 'target' => '_blank' ) ),
    'TABSETS' => ['main'=> ['tabs' => [ 'users'            => ['label'=>'Users'],
                                        'groups'           => ['label'=>'Groups'],
                                        'permissions'      => ['label'=>'Permissions'],
                                        'seedpermsclasses' => ['label'=>'SeedPermsClasses'],
                                        'seedperms'        => ['label'=>'SeedPerms'],
                                      ],
                            'perms' =>[ 'users'            => [ "A SEEDSessionUGP" ],
                                        'groups'           => [ "A SEEDSessionUGP" ],
                                        'permissions'      => [ "A SEEDSessionUGP" ],
                                        'seedpermsclasses' => [ "A SEEDSessionUGP" ],
                                        'seedperms'        => [ "A SEEDSessionUGP" ],
                                        '|'  // allows screen-login even if some tabs are ghosted
                                      ],
                           ],
                 ],
    'pathToSite' => '../../',

    'consoleSkin' => 'green',
];

$oApp = SEEDConfig_NewAppConsole( ['db' => APP_SEEDUGP_DB,
                                   'sessPermsRequired' => $consoleConfig['TABSETS']['main']['perms'],
                                   'consoleConfig' => $consoleConfig] );
$oApp->kfdb->SetDebug(1);

SEEDPRG();

$s = "";


class MyConsole02TabSet extends Console02TabSet
{
    private $oApp;
    private $oW;

    function __construct( SEEDAppConsole $oApp )
    {
        global $consoleConfig;
        parent::__construct( $oApp->oC, $consoleConfig['TABSETS'] );

        $this->oApp = $oApp;
    }

    function TabSet_main_users_Init()          { $this->oW = new UGPListForm( $this->oApp, 'users' ); $this->oW->Init(); }
    function TabSet_main_users_StyleDraw()     { return( $this->oW->StyleDraw() ); }
    function TabSet_main_users_ControlDraw()   { return( $this->oW->ControlDraw() ); }
    function TabSet_main_users_ContentDraw()   { return( $this->oW->ContentDraw() ); }

    function TabSet_main_groups_Init()         { $this->oW = new UGPListForm( $this->oApp, 'groups' ); $this->oW->Init(); }
    function TabSet_main_groups_StyleDraw()    { return( $this->oW->StyleDraw() ); }
    function TabSet_main_groups_ControlDraw()  { return( $this->oW->ControlDraw() ); }
    function TabSet_main_groups_ContentDraw()  { return( $this->oW->ContentDraw() ); }

    function TabSet_main_permissions_Init()          { $this->oW = new UGPListForm( $this->oApp, 'perms' ); $this->oW->Init(); }
    function TabSet_main_permissions_StyleDraw()     { return( $this->oW->StyleDraw() ); }
    function TabSet_main_permissions_ControlDraw()   { return( $this->oW->ControlDraw() ); }
    function TabSet_main_permissions_ContentDraw()   { return( $this->oW->ContentDraw() ); }

    function TabSet_main_seedpermsclasses_Init()          { $this->oW = new UGPListForm( $this->oApp, 'seedpermsclasses' ); $this->oW->Init(); }
    function TabSet_main_seedpermsClasses_StyleDraw()     { return( $this->oW->StyleDraw() ); }
    function TabSet_main_seedpermsclasses_ControlDraw()   { return( $this->oW->ControlDraw() ); }
    function TabSet_main_seedpermsclasses_ContentDraw()   { return( $this->oW->ContentDraw() ); }

    function TabSet_main_seedperms_Init()                 { $this->oW = new UGPListForm( $this->oApp, 'seedperms' ); $this->oW->Init(); }
    function TabSet_main_seedperms_StyleDraw()            { return( $this->oW->StyleDraw() ); }
    function TabSet_main_seedperms_ControlDraw()          { return( $this->oW->ControlDraw() ); }
    function TabSet_main_seedperms_ContentDraw()          { return( $this->oW->ContentDraw() ); }
}


class UGPListForm extends KeyframeUI_ListFormUI
{
    private $mode;
    private $oUGPUI;

    function __construct( SEEDAppConsole $oApp, $mode )
    {
        $this->mode = $mode;
        $this->oUGPUI = new UsersGroupsPermsUI( $oApp );

        parent::__construct( $oApp, $this->oUGPUI->GetConfig($mode) );
    }

    function Init()  { parent::Init(); }

    function StyleDraw()    { return( $this->DrawStyle() ); }
    function ControlDraw()  { return( $this->DrawSearch() ); }

    function ContentDraw()
    {
        $sInfo = "";
        if( $this->oComp->oForm->GetKey() ) {     // only show extra info for existing items, not when the New form is open
            switch( $this->mode ) {
                case 'users':            $sInfo = $this->oUGPUI->drawUsersInfo( $this->oComp );   break;
                case 'groups':           $sInfo = $this->oUGPUI->drawGroupsInfo( $this->oComp );  break;
                case 'perms':            $sInfo = $this->oUGPUI->drawPermsInfo( $this->oComp );   break;
                case 'seedpermsclasses': $sInfo = $this->oUGPUI->drawSeedPermsClassesInfo( $this->oComp );   break;
                case 'seedperms':        $sInfo = $this->oUGPUI->drawSeedPermsInfo( $this->oComp );   break;
            }
        }

        $cid = $this->oComp->Cid();

        $s = $this->ContentDraw_Horz_NewDelete()
            ."<div>$sInfo</div>";

        return( $s );
    }
}

$oCTS = new MyConsole02TabSet( $oApp );

$s .= $oApp->oC->DrawConsole( "[[TabSet:main]]", ['oTabSet'=>$oCTS] );

echo Console02Static::HTMLPage( SEEDCore_utf8_encode($s), "", 'EN',
                                ['consoleSkin'=>'green',
                                 'raScriptFiles' => [$oApp->UrlW()."js/SEEDCore.js"] ] );
