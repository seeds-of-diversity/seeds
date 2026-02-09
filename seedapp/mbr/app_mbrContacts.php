<?php

/* app_MbrContact
 *
 * Copyright 2021-2024 Seeds of Diversity Canada
 *
 * Application for managing member contact list
 */

if( !defined( "SEEDROOT" ) ) {
    define( "SEEDROOT", "../../" );
    define( "SEED_APP_BOOT_REQUIRED", true );
    include_once( SEEDROOT."seedConfig.php" );
}

include_once( SEEDAPP."mbr/mbrApp.php" );
include_once( SEEDLIB."mbr/MbrContacts.php" );
include_once( "tab_mbrcontacts_contacts.php" );
include_once( "tab_mbrcontacts_addcontacts.php" );
include_once( "tab_mbrcontacts_ebulletin.php" );
include_once( "tab_mbrcontacts_manage.php" );
include_once( SEEDCORE."console/console02ui.php");


$consoleConfig = [
    'CONSOLE_NAME' => "mbrContacts",
    'HEADER' => "Contacts",
    //'HEADER_LINKS' => array( array( 'href' => 'mbr_email.php',    'label' => "Email Lists",  'target' => '_blank' ),
    //    array( 'href' => 'mbr_mailsend.php', 'label' => "Send 'READY'", 'target' => '_blank' ) ),
    'TABSETS' => ['main'=> ['tabs' => [ 'contacts'    => ['label'=>'Contacts'],
                                        'addcontacts' => ['label'=>'Add Contacts'],
                                        'logins'      => ['label'=>'Logins'],
                                        'ebulletin'   => ['label'=>'eBulletin'],
                                        'manage'      => ['label'=>'Manage'],
                                      ],
                            // this doubles as sessPermsRequired and console::TabSetPermissions
                            'perms' => MbrApp::$raAppPerms['mbrContacts'],
                           ],
                 ],
    'urlLogin'=>'../login/',

    'consoleSkin' => 'green',
];

$oApp = SEEDConfig_NewAppConsole( ['db'=>'seeds2',
    'sessPermsRequired' => $consoleConfig['TABSETS']['main']['perms'],
    'consoleConfig' => $consoleConfig] );

SEEDPRG();


class MyConsole02TabSet extends Console02TabSet
{
    private $oApp;
    private $oW = null;
    private $oContacts;

    function __construct( SEEDAppConsole $oApp )
    {
        global $consoleConfig;
        parent::__construct( $oApp->oC, $consoleConfig['TABSETS'] );

        $this->oApp = $oApp;
        $this->oContacts = new Mbr_Contacts( $oApp );
    }

    function TabSet_main_contacts_Init()          { $this->oW = new MbrContactsTabContacts($this->oApp, $this->oContacts);  $this->oW->Init(); }
    function TabSet_main_addcontacts_Init()       { $this->oW = new MbrContactsTabAddContacts($this->oApp);                 $this->oW->Init(); }
    function TabSet_main_logins_Init()            { $this->oW = new MbrContactsTabLogins($this->oApp, $this->oContacts);    $this->oW->Init(); }
    function TabSet_main_ebulletin_Init(Console02TabSet_TabInfo $oT) { $this->oW = new MbrContactsTabEBulletin($this->oApp, $this->oContacts, $oT->oSVA); $this->oW->Init(); }
    function TabSet_main_manage_Init()            { $this->oW = new MbrContactsTabManage($this->oApp, $this->oContacts);    $this->oW->Init(); }


    function TabSetControlDraw($tsid, $tabname)   { return( $this->oW->ControlDraw() ); }
    function TabSetContentDraw($tsid, $tabname)   { return( $this->oW->ContentDraw() ); }
}

$oCTS = new MyConsole02TabSet( $oApp );

$sBody = $oApp->oC->DrawConsole( "[[TabSet:main]]", ['oTabSet'=>$oCTS] );

echo Console02Static::HTMLPage( SEEDCore_utf8_encode($sBody), "", 'EN', ['consoleSkin'=>'green'] );


class MbrContactsTabLogins
{
    private $oApp;
    private $oContacts;

    function __construct( SEEDAppConsole $oApp, Mbr_Contacts $oContacts )
    {
        $this->oApp = $oApp;
        $this->oContacts = $oContacts;
    }

    function Init()
    {

    }

    function ControlDraw()
    {
        $s = "";

        return($s);
    }

    function ContentDraw()
    {
        $s = "";

        return($s);
    }
}
