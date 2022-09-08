<?php

/* mailsetup.php
 *
 * Copyright 2010-2022 Seeds of Diversity Canada
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
include_once( SEEDLIB."mail/SEEDMail.php" );

$consoleConfig = [
    'CONSOLE_NAME' => "mailsetup",
    'HEADER' => "Bulk Mailer",
    'HEADER_LINKS' => [ [ 'href' => 'mbr_email.php',    'label' => "Email Lists",  'target' => '_blank' ],
                        [ 'href' => 'app_mailsend.php', 'label' => "Send 'READY'", 'target' => '_blank' ] ],
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
                                        'staged'   => ['label'=>'Staged'],
                                        'delete'   => ['label'=>'Delete'],
                                        'ghost'    => ['label'=>'Ghost']
                                      ],
                            // this doubles as sessPermsRequired and console::TabSetPermissions
                            'perms' =>[ 'mailitem' => [],
                                        'text'     => ['PUBLIC'],
                                        'controls' => [],
                                        'staged'   => [],
                                        'delete'   => ['A MBRMAIL'],
                                        'ghost'    => ['A notyou'],
                                      ]
                           ]
    ],
    'urlLogin'=>'../login/',

    'consoleSkin' => 'green',
];

// not needed if SEEDS_DB_DEFAULT is set to this value
$db = 'seeds2';

$oApp = SEEDConfig_NewAppConsole( ['db'=>$db,
                                   'sessPermsRequired' => $consoleConfig['TABSETS']['main']['perms'],
                                   'consoleConfig' => $consoleConfig] );
SEEDPRG();

$oMailUI = new SEEDMailUI( $oApp, ['db'=>$db] );
$oMailUI->Init();

$sMailTable = $oMailUI->GetMessageList( '' );
$sPreview = "";
$sControls = "[[TabSet:right]]"; // $oConsole->TabSetDraw( "right" )


$s = "<table cellspacing='0' cellpadding='10' style='width:100%;'><tr>"
    ."<td valign='top'>"
        ."<form method='post' action='{$oApp->PathToSelf()}'>"
        ."<input type='hidden' name='cmd' value='CreateMail'/>"
        ."<input type='submit' value='Create New Message'/>"
        ."</form>"
        //.SEEDForm_Hidden( "p_kMail", $oMS->kMail )
        .$sMailTable
        .$sPreview
    ."</td>"
    ."<td valign='top' style='padding-left:2em;width:50%'>"
        .$sControls
    ."</td>"
    ."</tr></table>";


class MyConsole02TabSet extends Console02TabSet
{
    private $oMailUI;

    function __construct( SEEDAppConsole $oApp, SEEDMailUI $oMailUI )
    {
        global $consoleConfig;
        parent::__construct( $oApp->oC, $consoleConfig['TABSETS'] );
        $this->oMailUI = $oMailUI;
    }

    function TabSet_right_mailitem_ControlDraw()
    {
        return( "<div style='padding:20px'>Foo</div>" );
    }

    function TabSet_right_mailitem_ContentDraw()
    {
        return( "<div style='padding:20px'>{$this->oMailUI->MailItemForm()}</div>" );
    }

    function TabSet_right_text_ControlDraw()
    {
        return( "<div style='padding:20px'>AAA</div>" );
    }

    function TabSet_right_text_ContentDraw()
    {
        $sMessageText = ($oM = $this->oMailUI->CurrMessageObj()) ? $oM->GetMessageText() : "";

        return( "<div style='padding:20px'>$sMessageText</div>" );
    }

    function TabSet_right_staged_ContentDraw()
    {
        $s = "";


        $s .= "<p>Show the staged mails here and allow READY mails to be edited</p>";

        $s .= "<p>Implement email scheduling</p>";

/*
        $oDocRepDB = DocRepUtil::New_DocRepDB_WithMyPerms( $this->oMailUI->oApp );

        // this is how you get the information about a named folder
        $oDocScheduleFolder = $oDocRepDB->GetDocRepDoc( 'Schedule2' );

        // this is how you find all the documents in the folder, and their details
        $raChildren = $oDocRepDB->GetSubtree( $oDocScheduleFolder->GetKey(), 1 );
        foreach( $raChildren as $kChild => $ra ) {
            if( $ra['visible'] ) {
                $oChild = $oDocRepDB->GetDocRepDoc( $kChild );
                $s .= $oChild->GetName()."<br/>";
            }
        }
*/

        return( $s );
    }
}


$oCTS = new MyConsole02TabSet( $oApp, $oMailUI );

$s = $oApp->oC->DrawConsole( $s, ['oTabSet'=>$oCTS] );


echo Console02Static::HTMLPage( SEEDCore_utf8_encode($s), "", 'EN', array( 'consoleSkin'=>'green') );   // sCharset defaults to utf8
