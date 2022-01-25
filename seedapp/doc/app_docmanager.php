<?php

/* app_docmanager.php
 *
 * Copyright 2006-2021 Seeds of Diversity Canada
 *
 * Manage docrep documents.
 */



/* Todo:

Preview tab:
    Remove the instruction text. "If it's html put it here..." etc
    Put a checkbox at the top of #docrepctrlview called "Show source". If it's
    unchecked show the preview normally, if checked escape htmlchars and make it
    monospace font so source html is shown

Edit tab:
    Find an html editor and put it here with the doc's html. I've used CKEditor and MCE but
    there are others that might be better now. I'm okay with Save doing a page refresh but if
    it's easy to hook the save to an ajax command then make a command called dr--textsave

Rename tab:
    Put a form here showing the document name, title, and permission class. Get title using GetTitle('').
    Make an ajax command called dr--metadatasave that updates these three.
    The hard part might be changing the tree to show the new name... hmm.

*/


if( !defined( "SEEDROOT" ) ) {
    define( "SEEDROOT", "../../" );
    define( "SEED_APP_BOOT_REQUIRED", true );
    include_once( SEEDROOT."seedConfig.php" );
}

include_once( SEEDROOT."DocRep/DocRep.php" );
include_once( SEEDROOT."DocRep/DocRepUI.php" );
include_once( SEEDROOT."DocRep/QServerDocRep.php" );

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
    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
    }

    function Init()
    {
    }

    function ControlDraw() { return( "<br/>" ); }
    function ContentDraw()
    {
        $s = "";

//        $o = new DocManagerUI( $this->oApp, $this->kSelectedDoc );

//        $s .= $o->Style();
        $s .= DocRepApp1::Style();

        $s .= "<div class='docman_doctree'>"
             ."<div class='container-fluid'>"
                 ."<div class='row'>"
                     ."<div class='col-md-6'> <div id='docmanui_tree'></div> </div>"
                     ."<div class='col-md-6'> <div id='docrepctrlview'></div> </div>"
                 ."</div>"
            ."</div></div>";

//        $s = str_replace( "[[DocRepApp_TreeForm_View_Text]]", $o->oDocMan->GetDocHTML(), $s );

        $oDocRepDB = DocRepUtil::New_DocRepDB_WithMyPerms( $this->oApp );
        $raTree = $oDocRepDB->GetSubTree( 0, -1 );
        $s .= "<script>var mymapDocs = new Map( [".$this->outputTree( $oDocRepDB, 0, $raTree )." ] );</script>";

        return( $s );
    }

    private function outputTree( $oDocRepDB, $kDoc, $raChildren )
    {
        $s = "";

        if( $kDoc ) {
            if( !($oDoc = $oDocRepDB->GetDocRepDoc( $kDoc )) )  goto done;

            $n = $oDoc->GetName();
            $t = $oDoc->GetType() == 'FOLDER' ? 'folder' : 'page';
            $p = $oDoc->GetParent();
            $schedule = !empty($oDoc->GetDocMetadataValue('schedule')) ? $oDoc->GetDocMetadataValue('schedule') : '';
            $perms = $oDoc->GetPermclass();
        } else {
            $p = 0;
            $n = '';
            $t = 'folder';
            $schedule = '';
            $perms = '';
        }
        $c = implode(',', array_keys($raChildren));

        $s .= "[$kDoc, { k:$kDoc, name:'$n', doctype:'$t', kParent:$p, children: [$c], schedule:'$schedule', perms:'$perms' }],";

        foreach( $raChildren as $k => $ra ) {
            $s .= $this->outputTree( $oDocRepDB, $k, $ra['children'] );
        }

        done:
        return( $s );
    }
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

echo Console02Static::HTMLPage( SEEDCore_utf8_encode($s), "<script src='https://cdn.ckeditor.com/ckeditor5/29.0.0/classic/ckeditor.js'></script>", 'EN',
                                ['raScriptFiles' => [W_CORE_URL."js/SEEDCore.js",
                                                     W_CORE_URL."seedapp/DocRep/DocRepApp.js",W_CORE_URL."seedapp/DocRep/docmanager.js"],
                                 'raCSSFiles' => [W_CORE_URL."seedapp/DocRep/DocRepApp.css"],
                                 'consoleSkin'=>'green'] );
