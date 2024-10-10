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

include_once( "docmanagerui.php" );
include_once( SEEDROOT."DocRep/DocRepUI.php" );

$tabConfig = [ 'main'=> ['tabs' => [ 'documents' => ['label'=>'Documents'],
                                     'files'     => ['label'=>'Files'],
                                     'perms'     => ['label'=>'Permissions'],
                                     //'ghost'     => ['label'=>'Ghost']
                                   ],
                         // this doubles as sessPermsRequired and console::TabSetPermissions
                         'perms' =>[ 'documents' => ['W DocRepMgr'],
                                     'files'     => ['W DocRepMgr'],
                                     'perms'     => ['A DocRepMgr'],
                                     //'ghost'     => ['A notyou'],
                                                    '|'  // allows screen-login even if some tabs are ghosted
                                   ],
             ] ];

$oApp = SEEDConfig_NewAppConsole( ['db'=>'',    // default is SEED_DB_DEFAULT or 'seeds1'
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

    function TabSet_main_files_ContentDraw()   { return("File management here"); }//return( $this->oW->ContentDraw() ); }
    function TabSet_main_perms_ContentDraw()   { return("SEEDPerms for ns=DocRep"); }   //return( $this->oW->ContentDraw() ); }
}



class DocManagerTabDocuments
{
    private $oApp;
    private $oDocManUI;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oDocManUI = new DocManagerUI_Documents( $oApp );
    }

    function Init()        {}
    function ControlDraw() { return( "<br/>" ); }
    function ContentDraw() { return( DocRepApp1::Style() . $this->oDocManUI->DrawDocumentsUI([]) ); }
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

$oApp->oC->SetConfig( ['HEADER'=>"Documents on {$oApp->kfdb->GetDB()}" ] );

$s = $oApp->oC->DrawConsole( "[[TabSet:main]]", ['oTabSet'=>$oDocTS] );

// What charset is the document text?
echo Console02Static::HTMLPage( SEEDCore_utf8_encode($s), "", 'EN',
                                ['raScriptFiles' => array_merge([W_CORE_URL."js/SEEDCore.js"], DocManagerUI_Documents::ScriptFiles()),
                                 'raCSSFiles' => DocManagerUI_Documents::StyleFiles(),
                                 'consoleSkin'=>'green'] );
