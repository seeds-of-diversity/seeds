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
    Put a checkbox at the top of #docmanui_ctrlview called "Show source". If it's
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

$oApp = SEEDConfig_NewAppConsole( ['db'=>'drdev',
                              'sessPermsRequired' => $tabConfig['main']['perms'] ] );
                              //     'consoleConfig' => $consoleConfig] );


if( ($p = SEEDInput_Str('qcmd')) ) {
    $kDoc = SEEDInput_Int('kDoc');
    $rQ = ['bOk'=>false, 'raOut'=>[], 'sOut'=>"", 'sErr'=>''];

    switch( $p ) {
/***********************************
    Here's where you put the php code to serve requests from the js app.
    Actually don't put the code here. Define functions/classes somewhere else for each case and put the code there.
 */
        case 'dr-preview':
            $rQ['bOk'] = true;
// Make a class that will get the preview.
            $rQ['sOut'] = "<h4>Here's the preview coming to you via AJAX for doc $kDoc</h4>";
            $rQ['raOut']['doctype'] = 'HTML';   // or whatever - look it up, and the js app should do the right thing for different types
                                                // e.g. this could be an image type, or it could be pdf, or html
                                                // Note that if it isn't html, you don't want to send the doc in sOut. Instead it should be
                                                // a link to get or show the image, pdf, etc.

            $oDocRepDB = DocRepUtil::New_DocRepDB_WithMyPerms( $oApp );
            $oDoc = $oDocRepDB->GetDocRepDoc( $kDoc );
            $rQ['sOut'] = $oDoc->GetText('');
            break;
    }
    echo json_encode( $rQ );
    exit;
}



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
                     ."<div class='col-md-6'>
                           <div id='tabs'>
                               <div class='docmanui_button_tabchange tab active-tab' data-tabname='preview'>Preview</div>
                               <div class='docmanui_button_tabchange tab' data-tabname='edit'>Edit</div>
                               <div class='docmanui_button_tabchange tab' data-tabname='rename'>Rename</div>
                               <div class='docmanui_button_tabchange tab' data-tabname='versions'>Versions</div>
                           </div>
                           <div id='docmanui_ctrlview'></div>
                       </div>"
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
        } else {
            $p = 0;
            $n = '';
            $t = 'folder';
        }
        $c = implode(',', array_keys($raChildren));

        $s .= "[$kDoc, { k:$kDoc, name:'$n', doctype:'$t', kParent:$p, children: [$c] }],";

        foreach( $raChildren as $k => $ra ) {
            $s .= $this->outputTree( $oDocRepDB, $k, $ra['children'] );
        }

        done:
        return( $s );
    }
}


$s = "";

$kSelectedDoc = SEEDInput_Int('k');

$oDocTS = new DocManagerTabSet( $oApp, $kSelectedDoc );

$s = $oApp->oC->DrawConsole( "[[TabSet:main]]", ['oTabSet'=>$oDocTS] );

echo Console02Static::HTMLPage( SEEDCore_utf8_encode($s), "", 'EN', ['raScriptFiles' => [W_CORE_URL."js/SEEDCore.js", W_CORE_URL."js/DocRep/DocRepApp.js",W_CORE_URL."js/DocRep/docmanager.js"],
                                                                     'consoleSkin'=>'green'] );

?>

<style>
#docmanui_ctrlview { border: 1px solid #aaa; padding:15px; }
:root {
	--tab-color: lightgrey;
	--tab-rounding: 8px;
}
#tabs {
	display: inline-block;
	box-sizing: border-box;
	margin-top: 10px;
	width: 100%;
	min-height: 30px;
}
.tab {
	display: inline-block;
	min-width: 20%;
	padding: 5px 10px;
	height: 100%;
	background-color: var(--tab-color);
	vertical-align: middle;
	text-align: center;
	border: 1px solid var(--tab-color);
	border-bottom: none;
	border-radius: var(--tab-rounding) var(--tab-rounding) 0 0;
	box-sizing: border-box;
	cursor: default;
	user-select: none;
}
.tab.active-tab {
	background-color: white;
}
</style>
