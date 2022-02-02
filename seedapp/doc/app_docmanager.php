<?php

/* app_docmanager.php
 *
 * Copyright 2006-2022 Seeds of Diversity Canada
 *
 * Manage docrep documents.
 */

if( !defined( "SEEDROOT" ) ) {
    define( "SEEDROOT", "../../" );
    define( "SEED_APP_BOOT_REQUIRED", true );
    include_once( SEEDROOT."seedConfig.php" );
}

include_once( SEEDROOT."DocRep/DocRep.php" );
include_once( SEEDROOT."DocRep/DocRepUI.php" );
include_once( SEEDROOT."DocRep/QServerDocRep.php" );
include_once( "docmanagerui.php" );

$tabConfig = [ 'main'=> ['tabs' => [ 'documents' => ['label'=>'Documents'],
                                     'files'     => ['label'=>'Files'],
                                     'ghost'     => ['label'=>'Ghost']
                                   ],
                         // this doubles as sessPermsRequired and console::TabSetPermissions
                         'perms' =>[ 'documents' => ['W DocRepMgr'],
                                     'files'     => ['W DocRepMgr'],
                                     'ghost'     => ['A notyou'],
                                                    '|'  // allows screen-login even if some tabs are ghosted
                                   ],
             ] ];

$oApp = SEEDConfig_NewAppConsole( ['db'=>'cats',
                              'sessPermsRequired' => $tabConfig['main']['perms'] ] );
                              //     'consoleConfig' => $consoleConfig] );


class DocManagerTabSet extends Console02TabSet
{
    private $oApp;
    private $kSelectedDoc;
    private $oW = null;

    function __construct( SEEDAppConsole $oApp, $kSelectedDoc )
    {
        $this->oApp = $oApp;
        $this->kSelectedDoc = $kSelectedDoc;

        global $tabConfig;
        parent::__construct( $oApp->oC, $tabConfig );
    }

    function TabSet_main_documents_Init()          { $this->oW = new DocManagerTabDocuments( $this->oApp ); $this->oW->Init(); }
    function TabSet_main_documents_ControlDraw()   { return( $this->oW->ControlDraw() ); }
    function TabSet_main_documents_ContentDraw()   { return( $this->oW->ContentDraw() ); }
}



class DocManagerTabDocuments
{
    private $oApp;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oDocManUI = new DocManagerUI_Documents( $oApp );
    }

    function Init()        {}
    function ControlDraw() { return( "<br/>" ); }
    function ContentDraw() { return( DocRepApp1::Style() . $this->oDocManUI->DrawDocumentsUI() ); }
}


/* Serve ajax commands
 */
if( ($p = SEEDInput_Str('qcmd')) ) {
    echo json_encode( (new QServerDocRep($oApp))->Cmd($p, $_REQUEST) );
    exit;
}


/* Document Manager app
 */

$s = "";

$kSelectedDoc = SEEDInput_Int('k');

$oDocTS = new DocManagerTabSet( $oApp, $kSelectedDoc );

$s = $oApp->oC->DrawConsole( "[[TabSet:main]]", ['oTabSet'=>$oDocTS] );

echo Console02Static::HTMLPage( SEEDCore_utf8_encode($s), "", 'EN',
                                ['raScriptFiles' => array_merge([W_CORE_URL."js/SEEDCore.js"], DocManagerUI_Documents::ScriptFiles()),
                                 'raCSSFiles' => DocManagerUI_Documents::StyleFiles(),
                                 'consoleSkin'=>'green'] );
