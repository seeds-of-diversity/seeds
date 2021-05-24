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


if( ($p = SEEDInput_Str('qcmd')) && ($kDoc = SEEDInput_Int('kDoc')) ) {
    $rQ = ['bOk'=>false, 'raOut'=>[], 'sOut'=>"", 'sErr'=>''];

    switch( $p ) {
/***********************************
    Here's where you put the php code to serve requests from the js app.
    Actually don't put the code here. Define functions/classes somewhere else for each case and put the code there.
 */
        case 'dr-preview':
            $rQ['bOk'] = true;
// Make a class that will get the preview.
            $rQ['sOut'] = "<h4>Here's the preview coming to you via AJAX</h4>";
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
                           <div>
                               <button class='docmanui_button_tabchange' data-tabname='preview'>Preview</button>
                               <button class='docmanui_button_tabchange' data-tabname='edit'>Edit</button>
                               <button class='docmanui_button_tabchange' data-tabname='rename'>Rename</button>
                               <button class='docmanui_button_tabchange' data-tabname='versions'>Versions</button>
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

echo Console02Static::HTMLPage( SEEDCore_utf8_encode($s), "", 'EN', ['raScriptFiles' => [W_CORE_URL."js/SEEDCore.js", W_CORE_URL."js/DocRep/DocRepApp.js"],
                                                                     'consoleSkin'=>'green'] );

?>

<style>
#docmanui_ctrlview { border: 1px solid #aaa; padding:15px; }
</style>

<script>
/*
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
*/


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


class DocRepCtrlView  // put base class in wcore/js/DocRep/DocRepApp.js
{
    constructor( oConfig )
    {
        this.ctrlMode = "";
    }

    HandleEvent( eEvent, p )
    {
        // override to respond to notifications
    }
}
class myDocRepCtrlView extends DocRepCtrlView
{
    constructor( oConfig )
    {
        super(oConfig);
        this.ctrlMode = 'preview';
        this.fnHandleEvent = oConfig.fnHandleEvent;
    }

    DrawCtrlView( kCurrDoc )
    {
        let s = "";

/****************************
   Here is where you add code to implement the controls.
   Preferably create a new method, or even a new class, for each of the cases. Don't just put all the code in the switch.
 */

        switch( this.ctrlMode ) {
            case 'preview':
                s = "<p>Todo:<br/>"
                   +"If it's html, put it here.<br/>"
                   +"If it's an image, put an &lt;img> tag here to show it.<br/>"
                   +"Otherwise put a link here to download/view it (e.g. docx,pdf)</p>";

                let rQ = SEEDJXSync( "", {qcmd: 'dr-preview', kDoc: kCurrDoc} );
                if( rQ.bOk ) s += rQ.sOut;
                break;

            case 'edit':
                s = "<p>Todo:<br/>"
                   +`Fetch metadata/data for doc ${kCurrDoc}<br/>`
                   +"If it's html, put an html editor here. CKEditor?<br/>";
                   break;
            case 'rename':
                s = "<p>Todo:<br/>"
                   +`For doc ${kCurrDoc}<br/>`
                   +"Put a form here to change name, title, permissions, other metadata.<br/>";
                   break;
            case 'versions':
                s = "<p>Todo:<br/>"
                   +`For doc ${kCurrDoc}<br/>`
                   +"Show versions of this document, allow preview, diff view, restore, and delete.<br/>";
                   break;

            default:
                s = this.oCtrlView.ctrlMode + " " + this.oTree.GetCurrDoc();
        }

        return( s );
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
        this.oCtrlView = new myDocRepCtrlView(
                        { fnHandleEvent: this.HandleEvent.bind(this) } );    // tell DocRepTree how to send events here
        this.kCurrDoc = 0;
    }

    DrawTree()
    {
        return( this.oTree.DrawTree( 0 ) );
    }

    DrawCtrlView()
    {
// The ctrlMode is awkwardly set to this obj, then this is called to draw it. Should be a cleaner way to change mode and draw.
// Also oCtrlView should either know which doc is current, or it should be able to ask DocRepUI02 through a callback (eliminate the fn parm).
        return( this.oCtrlView.DrawCtrlView( this.oTree.GetCurrDoc() ) );
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
            saveThis.oDocRepUI.oCtrlView.ctrlMode = $(this).attr('data-tabname');
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
