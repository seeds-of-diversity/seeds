<?php

if( !defined( "SEEDROOT" ) ) {
    define( "SEEDROOT", "../../" );
    define( "SEED_APP_BOOT_REQUIRED", true );
    include_once( SEEDROOT."seedConfig.php" );
}

include_once( SEEDAPP."mbr/mbrApp.php" );
include_once( SEEDLIB."mbr/MbrContacts.php" );
include_once( "tab_mbrcontacts_contacts.php" );
include_once( "tab_mbrcontacts_addcontacts.php" );

$consoleConfig = [
    'CONSOLE_NAME' => "mbrContacts",
    'HEADER' => "Contacts",
    //'HEADER_LINKS' => array( array( 'href' => 'mbr_email.php',    'label' => "Email Lists",  'target' => '_blank' ),
    //    array( 'href' => 'mbr_mailsend.php', 'label' => "Send 'READY'", 'target' => '_blank' ) ),
    'TABSETS' => ['main'=> ['tabs' => [ 'contacts'    => ['label'=>'Contacts'],
                                        'addcontacts' => ['label'=>'Add Contacts'],
                                        'logins'      => ['label'=>'Logins'],
                                        'ebulletin'   => ['label'=>'eBulletin'],
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

    function TabSet_main_contacts_Init()          { $this->oW = new MbrContactsTabContacts( $this->oApp, $this->oContacts ); $this->oW->Init(); }
    function TabSet_main_contacts_ControlDraw()   { return( $this->oW->ControlDraw() ); }
    function TabSet_main_contacts_ContentDraw()   { return( $this->oW->ContentDraw() ); }

    function TabSet_main_addcontacts_Init()       { $this->oW = new MbrContactsTabAddContacts( $this->oApp ); $this->oW->Init(); }
    function TabSet_main_addcontacts_ControlDraw(){ return( $this->oW->ControlDraw() ); }
    function TabSet_main_addcontacts_ContentDraw(){ return( $this->oW->ContentDraw() ); }
}

$oCTS = new MyConsole02TabSet( $oApp );

$sBody = $oApp->oC->DrawConsole( "[[TabSet:main]]", ['oTabSet'=>$oCTS] );

echo Console02Static::HTMLPage( utf8_encode($sBody), "", 'EN', ['consoleSkin'=>'green'] );
