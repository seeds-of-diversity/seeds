<?php

/* mailsetup.php
 *
 * Copyright 2010-2024 Seeds of Diversity Canada
 *
 * Prepare mail to be sent to members / donors / subscribers.
 * Use app_mailsend.php to send the mail.
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
    // these links are relative to the file that included this one
    'HEADER_LINKS' => [ [ 'href' => 'mbr_email.php',    'label' => "Email Lists",  'target' => '_blank' ],
                        [ 'href' => 'mailsend.php',     'label' => "Send 'READY'", 'target' => '_blank' ] ],
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
    private $oW;        // worker class for current ctrlview tab

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

    function TabSet_right_text_Init()           { $this->oW = new mailCtrlView_Text($this->oMailUI); $this->oW->Init(); }
    function TabSet_right_text_ControlDraw()    { return($this->oW->ControlDraw()); }
    function TabSet_right_text_ContentDraw()    { return($this->oW->ContentDraw()); }

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
        $raChildren = $oDocRepDB->GetSubtreeDescendants( $oDocScheduleFolder->GetKey(), 1 );
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

class mailCtrlView_Text implements Console02TabSet_Worker
/**********************
    Worker class for Text tab
 */
{
    private $oMailUI;
    private $oCtrlForm;

    function __construct( SEEDMailUI $oMailUI )
    {
        $this->oMailUI = $oMailUI;
    }

    function Init()
    {
        $this->oCtrlForm = new SEEDCoreForm('A');
        $this->oCtrlForm->Update();
    }

    function ControlDraw()
    {
        $s = "";

        $oContacts = new Mbr_Contacts($this->oMailUI->oApp);

        /* Get staged and unstaged recipients for member-expansion dropdown
         */
        $raMbrTo = ['-- To --' => 0];
        if( ($oMessage = $this->oMailUI->CurrMessageObj()) ) {
            foreach( array_merge($oMessage->GetRAStagedRecipients(), $oMessage->GetRAUnStagedRecipients()) as $email ) {    // $email can be kMbr or email
                if( ($raM = $oContacts->GetBasicValues($email)) ) {
                    $raMbrTo[$raM['email']] = $raM['_key'];
                }
            }
        }

        $s = "<form>"
            .$this->oCtrlForm->Select('mode', ['Normal'=>'Normal','Expanded'=>'Expanded','HTML'=>'HTML','Expanded HTML'=>'Expanded HTML'])
            .SEEDCore_NBSP('',5)
            .$this->oCtrlForm->Select('kMbrTo', $raMbrTo)
            ."<input type='submit' value='Show'/>"
            ."</form>";

        return( $s );
    }

    function ContentDraw()
    {
        $kMbrTo = $this->oCtrlForm->ValueInt('kMbrTo');
        $mode = $this->oCtrlForm->Value('mode');

        $s = ($oM = $this->oMailUI->CurrMessageObj()) ? $oM->GetMessageText([],false) : "";        // don't expand in GetMessageText because we choose whether or not to do that below

        if( in_array($mode, ['Expanded', 'Expanded HTML']) ) {
// factor this with SendOne()
            $raVars = []; // SEEDCore_ParmsURL2RA( $kfrStage->Value('sVars') );
            $raVars['kMbrTo'] = $kMbrTo;
            $raVars['lang'] = $this->oMailUI->oApp->lang;

            list($ok,$sBody,$sErr) = SEEDMailCore::ExpandMessage( $this->oMailUI->oApp, $s, ['raVars'=>$raVars] );
            $s = $ok ? $sBody : "Cannot expand message : $sErr";
        }

        if( in_array($mode, ['HTML', 'Expanded HTML']) ) {
            $s = "<pre>".SEEDCore_HSC($s)."</pre>";
        }

        return( $s );
    }
}


$oCTS = new MyConsole02TabSet( $oApp, $oMailUI );

$s = $oApp->oC->DrawConsole( $s, ['oTabSet'=>$oCTS] );


echo Console02Static::HTMLPage( SEEDCore_utf8_encode($s), "", 'EN', array( 'consoleSkin'=>'green') );   // sCharset defaults to utf8
