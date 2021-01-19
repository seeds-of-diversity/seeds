<?php

/* mailsetup.php
 *
 * Copyright 2010-2021 Seeds of Diversity Canada
 *
 * Prepare mail to be sent to members / donors / subscribers.
 * Use mbr_mailsend to send the mail.
 */

if( !defined( "SEEDROOT" ) ) {
    define( "SEEDROOT", "../../" );
    define( "SEED_APP_BOOT_REQUIRED", true );
    include_once( SEEDROOT."seedConfig.php" );
}

include_once( SEEDCORE."console/console02.php" );
include_once( "SEEDMailUI.php" );
include_once( SEEDLIB."mail/SEEDMailer.php" );

$consoleConfig = [
    'CONSOLE_NAME' => "mailsetup",
    'HEADER' => "Bulk Mailer",
    'HEADER_LINKS' => array( array( 'href' => 'mbr_email.php',    'label' => "Email Lists",  'target' => '_blank' ),
                             array( 'href' => 'mbr_mailsend.php', 'label' => "Send 'READY'", 'target' => '_blank' ) ),
    'TABSETS' => ['main'=> ['tabs' => [ 'pending' => ['label'=>'Pending'],
                                        'sent'    => ['label'=>'Sent'],
                                        'ghost'   => ['label'=>'Ghost']
                                      ],
                            // this doubles as sessPermsRequired and console::TabSetPermissions
                            'perms' =>[ 'pending' => ['W MBRMAIL'],
                                        'sent'    => ['W MBRMAIL'],
                                        'ghost'   => ['A notyou'],
                                        '|'  // allows screen-login even if some tabs are ghosted
                                      ],
                           ],
                  'right'=>['tabs' => [ 'mailitem' => ['label'=>'Mail Item'],
                                        'text'     => ['label'=>'Text'],
                                        'controls' => ['label'=>'Controls'],
                                        'delete'   => ['label'=>'Delete'],
                                        'ghost'   =>  ['label'=>'Ghost']
                                      ],
                            // this doubles as sessPermsRequired and console::TabSetPermissions
                            'perms' =>[ 'mailitem' => [],
                                        'text'     => ['PUBLIC'],
                                        'controls' => [],
                                        'delete'   => ['A MBRMAIL'],
                                        'ghost'    => ['A notyou'],
                                      ]
                           ]
    ],
    'urlLogin'=>'../login/',

    'consoleSkin' => 'green',
];


$oApp = SEEDConfig_NewAppConsole( ['db'=>'seeds2',
                                   'sessPermsRequired' => $consoleConfig['TABSETS']['main']['perms'],
                                   'consoleConfig' => $consoleConfig] );

$oMail = new SEEDMailUI( $oApp );

$sMailTable = "";
$sPreview = "";
$sControls = "[[TabSet:right]]"; // $oConsole->TabSetDraw( "right" )


$s = "<table cellspacing='0' cellpadding='10' style='width:100%;border:1px solid #888'><tr>"
    ."<td valign='top'>"
        ."<form method='post' action='${_SERVER['PHP_SELF']}'>"
        //.SEEDForm_Hidden( "p_kMail", $oMS->kMail )
        .$sMailTable
        ."</form>"
        .$sPreview
    ."</td>"
    ."<td valign='top' style='border-left:solid grey 1px;padding-left:2em;width:50%'>"
        .$sControls
    ."</td>"
    ."</tr></table>";

class MyConsole02TabSet extends Console02TabSet
{
    function __construct( SEEDAppConsole $oApp )
    {
        global $consoleConfig;
        parent::__construct( $oApp->oC, $consoleConfig['TABSETS'] );
    }

    function TabSet_right_mailitem_ControlDraw()
    {
        return( "<div style='padding:20px'>Foo</div>" );
    }

    function TabSet_right_mailitem_ContentDraw()
    {
        return( "<div style='padding:20px'>Bar</div>" );
    }

    function TabSet_right_text_ControlDraw()
    {
        return( "<div style='padding:20px'>AAA</div>" );
    }

    function TabSet_right_text_ContentDraw()
    {
        return( "<div style='padding:20px'>BBB</div>" );
    }
}


$oCTS = new MyConsole02TabSet( $oApp );

$s = $oApp->oC->DrawConsole( $s, ['oTabSet'=>$oCTS] );


echo Console02Static::HTMLPage( SEEDCore_utf8_encode($s), "", 'EN', array( 'consoleSkin'=>'green') );   // sCharset defaults to utf8
