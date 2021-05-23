<?php

/* app_docmanager.php
 *
 * Copyright 2006-2021 Seeds of Diversity Canada
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

$tabConfig = [ 'main'=> ['tabs' => [ 'documents' => ['label'=>'Documents'],
                                     'documentsx'=> ['label'=>'Documents Old'],
                                     'versions'  => ['label'=>'Versions'],
                                     'files'     => ['label'=>'Files'],
                                     'ghost'     => ['label'=>'Ghost']
                                   ],
                         // this doubles as sessPermsRequired and console::TabSetPermissions
                         'perms' =>[ 'documents' => ['W DocRepMgr'],
                                     'documentsx'=> ['A DocRepMgr'],
                                     'versions'  => ['W DocRepMgr'],
                                     'files'     => ['W DocRepMgr'],
                                     'ghost'     => ['A notyou'],
                                                    '|'  // allows screen-login even if some tabs are ghosted
                                   ],
             ] ];

$oApp = SEEDConfig_NewAppConsole( ['db'=>'seeds1',
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

    function TabSet_main_documentsx_Init()         { $this->oW = new DocManagerTabDocumentsOld( $this->oApp, $this->kSelectedDoc ); $this->oW->Init(); }
    function TabSet_main_documentsx_ControlDraw()  { return( $this->oW->ControlDraw() ); }
    function TabSet_main_documentsx_ContentDraw()  { return( $this->oW->ContentDraw() ); }
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
                           <div>
                               <button class='docmanui_button_tabchange' data-tabname='preview'>Preview</button>
                               <button class='docmanui_button_tabchange' data-tabname='edit'>Edit</button>
                               <button class='docmanui_button_tabchange' data-tabname='rename'>Rename</button>
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


class DocManagerTabDocumentsOld
{
    private $kSelectedDoc;

    function __construct( SEEDAppConsole $oApp, $kSelectedDoc )
    {
        $this->oApp = $oApp;
        $this->kSelectedDoc = $kSelectedDoc;
    }

    function Init()
    {
    }

    function ControlDraw() { return( "<br/>" ); }
    function ContentDraw()
    {
        $s = "";

        $o = new DocManagerUI( $this->oApp, $this->kSelectedDoc );

        $s .= $o->Style();

        $s .= "<div class='docman_doctree'>"
             ."<div class='container-fluid'>"
                 ."<div class='row'>"
                     ."<div class='col-md-6'>".$o->oDocMan->DrawDocTree( 0 )."</div>"
                     ."<div class='col-md-6'>"
                         .($o->oDocMan->GetSelectedDocKey() ? ("<div class='docman_doctreetabs'>".$o->DrawTreeTabs()."</div>") : "")
                         ."<div class='docman_docform'>".$o->oDocMan->TreeForms()."</div>"
                     ."</div>"
                 ."</div>"
            ."</div></div>";

        $s = str_replace( "[[DocRepApp_TreeForm_View_Text]]", $o->oDocMan->GetDocHTML(), $s );

        return( $s );
    }
}


class DocManApp extends DocRepApp1
{
    private $oApp;
    private $oDocRepUI;

    function __construct( SEEDAppSessionAccount $oApp, $kSelectedDoc, DocRepDB2 $oDB, DocManDocRepUI $oUI )
    {
        parent::__construct( $oUI, $oDB, $kSelectedDoc );
        $this->oApp = $oApp;
    }

    function Edit0()
    {
        $s = "";

        $s = "PUT THE EDIT FORM HERE";

        return( $s );
    }

    function Rename0()
    {
        $s = "";

        if( ($k = $this->GetSelectedDocKey()) ) {
            $oForm = new SEEDCoreForm( 'Plain' );
            $s .= "<form method='post'>"
                 .$oForm->Hidden( 'k', array( 'value' => $k ) )                // so the UI knows the current doc in the tree
                 .$oForm->Hidden( 'action', array( 'value' => 'rename2' ) )
                 .$oForm->Text( 'doc_name', '' )           // the new document name
                 ."<input type='submit' value='Rename'/>"
                 ."</form>";
        }

        return( $s );
    }

}

class DocManDocRepUI extends DocRepUI
{
    function __construct( DocRepDB2 $oDocRepDB, $pathToSelf )
    {
        parent::__construct( $oDocRepDB, $pathToSelf );
    }

    function DrawTree_title_NOTUSED( DocRepDoc2 $oDoc, $raTitleParms )
    /*********************************************************
        This is called from DocRepUI::DrawTree for every item in the tree. It writes the content of <div class='DocRepTree_title'>.

        raTitleParms:
            bSelectedDoc:   true if this document is selected in the tree and should be highlighted
            sExpandCmd:     the command to be issued if the user clicks on a tree-expand-collapse control associated with this item
     */
    {
        $kDoc = $oDoc->GetKey();

        $s = "<a href='${_SERVER['PHP_SELF']}?k=$kDoc'><nobr>"
        .( $raTitleParms['bSelectedDoc'] ? "<span class='docman_doctree_titleSelected'>" : "" )
        .($oDoc->GetTitle('') ?: ($oDoc->GetName() ?: "Untitled"))
        .( $raTitleParms['bSelectedDoc'] ? "</span>" : "" )
        ."</nobr></a>";

        return( $s );
    }
}


class DocManagerUI
{
    private $oApp;
    public  $oDocMan;
    public  $oDocRepDB;

    function __construct( SEEDAppSessionAccount $oApp, $kSelectedDoc )
    {
        $this->oApp = $oApp;
        $oDocRepDB = DocRepUtil::New_DocRepDB_WithMyPerms( $oApp );
        $this->oDocRepDB = $oDocRepDB;
        $oDocRepUI = new DocManDocRepUI( $oDocRepDB, $oApp->PathToSelf() );

        $this->oDocMan = new DocManApp( $oApp, $kSelectedDoc, $oDocRepDB, $oDocRepUI );
    }

    function DrawTreeTabs()
    {
        $s = $this->oDocMan->TreeTabs();
        return( $s );
        $s = "<div class='row'>"
            .$this->tab( "View",   "view0" )
            .$this->tab( "Edit",   "edit0" )
            .$this->tab( "Rename", "rename0" )
            //.$this->tab( "Tell a Joke", "joke0" )
            ."</div>";
        return( $s );
    }

    private function tab( $label, $tabcmd )
    {
        return( "<div class='col-md-2'>"
               ."<a href='{$this->oApp->PathToSelf()}?tab=$tabcmd&k=".$this->oDocMan->GetSelectedDocKey()."'>$label</a>"
               ."</div>" );

/*
            ."<form method='post'>"
               ."<input type='hidden' name='k' value='".$this->oDocMan->GetSelectedDocKey()."'/>"
               ."<input type='hidden' name='action' value='$action'/>"
               ."<input type='submit' value='$label'/>"
               ."</form></div>" );
*/
    }

    function Style()
    {
        return( DocRepApp1::Style()."
<style>
.docman_doctree {
        border-radius:10px;
        margin:20px;
}

.docman_doctreetabs {
        margin-bottom:10px;
}

.docman_docpreview_folder {
}
.docman_docform {
        background-color:#eee;
        border:1px solid #777;
        border-radius: 10px;
        padding:20px;
}

</style>
" );
    }
}


$s = "";

$kSelectedDoc = SEEDInput_Int('k');

$oDocTS = new DocManagerTabSet( $oApp, $kSelectedDoc );

$s = $oApp->oC->DrawConsole( "[[TabSet:main]]", ['oTabSet'=>$oDocTS] );

echo Console02Static::HTMLPage( SEEDCore_utf8_encode($s), "", 'EN', ['raScriptFiles' => [W_CORE_URL."js/SEEDCore.js", W_CORE_URL."js/DocRep/DocRepApp.js"],
                                                                     'consoleSkin'=>'green'] );

?>

<style>
#docmanui_ctrlview { border: 1px solid #aaa; padding:15px; }
</style>

<script>
var mymapDocsX = new Map( [
    [0, { k:0, name:'',                doctype: 'folder', kParent: -1, children: [1,2] }],
    [1, { k:1, name:'folder1',         doctype: 'folder', kParent: 0,  children: [4,5,3] }],
    [2, { k:2, name:'folder2',         doctype: 'folder', kParent: 0,  children: [6,7] }],
    [3, { k:3, name:'folder1/folder3', doctype: 'folder', kParent: 1,  children: [8,9] }],
    [4, { k:4, name:'folder1/pageA',   doctype: 'page',   kParent: 1,  children: [] }],
    [5, { k:5, name:'folder1/pageB',   doctype: 'page',   kParent: 1,  children: [] }],
    [6, { k:6, name:'folder2/pageC',   doctype: 'page',   kParent: 2,  children: [] }],
    [7, { k:7, name:'folder2/pageD',   doctype: 'page',   kParent: 2,  children: [] }],
    [8, { k:8, name:'folder3/pageE',   doctype: 'page',   kParent: 3,  children: [] }],
    //[9, { k:9, name:'folder3/pageF',   doctype: 'page',   kParent: 3,  children: [] }],
]);



class myDocRepTree extends DocRepTree
{
    constructor(oConfig)
    {
        super(oConfig);
        this.fnHandleEvent = oConfig.fnHandleEvent;
    }

    InitUI()
    {
        super.InitUI();
    }

    HandleEvent( eNotify, p )
    /************************
        DocRepTree calls here when something is clicked
     */
    {
        switch( eNotify ) {
            case 'docSelected':
                sessionStorage.setItem( 'DocRepTree_Curr', p );    // SetCurrDoc() not defined but it would be this
                break;
        }

        // pass the event up the chain
        this.fnHandleEvent( eNotify, p );
    }

    GetCurrDoc()
    {
        return( parseInt(sessionStorage.getItem( 'DocRepTree_Curr' )) || 0 );
    }

    FetchDoc( kDoc )
    {
        if( kDoc == 9 ) {
            this.mapDocs.set( 9, { k:9, name:'folder3/pageF',   doctype: 'page',   kParent: 3,  children: [] } );
        }
    }

    LevelOpenGet( pDoc )
    {
        let oDRDoc = this.getDocAndJDoc(pDoc);
        return( sessionStorage.getItem( 'DocRepTree_Open_'+oDRDoc.kDoc ) == 1 );    // compare to int because '0' === true
    }
    LevelOpenSet( pDoc, bOpen )
    {
        let oDRDoc = this.getDocAndJDoc(pDoc);
        sessionStorage.setItem( 'DocRepTree_Open_'+oDRDoc.kDoc, bOpen );
    }
}


class DocRepUI02
/***************
    Manages UI components but agnostic to rendering
    Contains a DocRepTree, a DocRepCtrlView, and DocRepTree_Curr
 */
{
    constructor( oConfig )
    {
        this.fnHandleEvent = oConfig.fnHandleEvent;                          // tell this object how to send events up the chain

// these parms should be in oConfig
        this.oTree = new myDocRepTree(
                        { mapDocs: mymapDocs,
                          dirIcons: '../../wcore/img/icons/',
                          fnHandleEvent: this.HandleEvent.bind(this) } );    // tell DocRepTree how to send events here
        this.ctrlMode = 'preview';
    }

    DrawTree()
    {
        return( this.oTree.DrawTree( 0 ) );
    }

    DrawCtrlView()
    {
        let s = "";

        s = this.ctrlMode + " " + this.oTree.GetCurrDoc();

        return( s );
    }

    InitUI()
    {
        this.oTree.InitUI();
    }

    HandleEvent( eNotify, p )
    /************************
        Components call here with notifications
     */
    {
        switch( eNotify ) {
            case 'docSelected':
                break;
        }
        this.fnHandleEvent( eNotify, p+1 );
    }
}


class DocRepApp02
/****************
    Manage the rendering of DocRepUI components. The components know how to update each others' states.
 */
{
    constructor( oConfig )
    {
        this.oDocRepUI = new DocRepUI02( { fnHandleEvent: this.HandleEvent.bind(this) } );    // tell DocRepUI how to send events here
    }

    InitUI()
    {
        let saveThis = this;
        $('.docmanui_button_tabchange').click( function() {
            saveThis.oDocRepUI.ctrlMode = $(this).attr('data-tabname');
            $('#docmanui_ctrlview').html( saveThis.oDocRepUI.DrawCtrlView() );
        });

        // draw the components before initializing them because InitUI sets js bindings in the dom
        $('#docmanui_tree').html( this.oDocRepUI.DrawTree() );
        $('#docmanui_ctrlview').html( this.oDocRepUI.DrawCtrlView() );
        this.oDocRepUI.InitUI();
    }

    HandleEvent( eEvent, p )
    {
        switch( eEvent ) {
            case 'docSelected':
                $('#docmanui_ctrlview').html( this.oDocRepUI.DrawCtrlView() );
                break;
        }
    }
}


$(document).ready( function () {
    (new DocRepApp02( { } )).InitUI();
});

</script>
