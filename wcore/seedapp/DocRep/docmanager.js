/* Implements a custom DocManager
 *
 * Copyright (c) 2021 Seeds of Diversity Canada
 *
 * usage: DocRepApp02::InitUI() makes it all start up
 *
 * DocRepApp02      - creates a Tree and a CtrlView, lets them communicate with each other, and handles rendering
 * DocRepUI02       - creates Tree and CtrlView for DocRepApp02, manages inter-widget communication, but agnostic to rendering
 * myDocRepTree     - a DocRepTree that knows how to store local state (via sessionstorage) and how to talk to DocRepUI02 
 * myDocRepCtrlView - a DocRepCtrlView that knows how to store local state, how to talk to DocRepUI02, and implements custom controls 
 */

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


class myDocRepCtrlView extends DocRepCtrlView
/*********************
    Implement the CtrlView widget with custom tabs and forms
 */
{
    constructor( oConfig )
    {
        oConfig.defTabs = { preview:"Preview", edit:"Edit", rename:"Rename", versions:"Versions" };

        super(oConfig);
        this.fnHandleEvent = oConfig.fnHandleEvent;
    }

    GetCtrlMode()
    {
        return( sessionStorage.getItem( 'DocRepCtrlView_Mode' ) );
    }

    SetCtrlMode( m )
    {
        sessionStorage.setItem( 'DocRepCtrlView_Mode', m );
        super.SetCtrlMode( m );
    }
    
    DrawCtrlView( kCurrDoc )
    {
        let s = "";

/****************************
   Here is where you add code to implement the controls.
   Preferably create a new method, or even a new class, for each of the cases. Don't just put all the code in the switch.
 */

        switch( this.GetCtrlMode() ) {
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
                s = this.drawFormRename( kCurrDoc );
                break;
            case 'versions':
                s = "<p>Todo:<br/>"
                   +`For doc ${kCurrDoc}<br/>`
                   +"Show versions of this document, allow preview, diff view, restore, and delete.<br/>";
                   break;

            default:
                s = "Unknown control mode";
        }

        return( s );
    }
    
    drawFormRename( kCurrDoc )
    {
        let s = `<div class='row'> <div [label]>Name</div>        <div [ctrl]><input type='text' id='formRename_name' style='width:100%'/></div></div>
                 <div class='row'> <div [label]>Title</div>       <div [ctrl]><input type='text' id='formRename_title' style='width:100%'/></div></div>
                 <div class='row'> <div [label]>Permissions</div> <div [ctrl]><input type='text' id='formRename_perms' style='width:100%'/></div></div>
                 <p><button>Change</button></p>`;
        s = s.replaceAll("[label]", "class='col-md-3'");
        s = s.replaceAll("[ctrl]",  "class='col-md-6'");
        
s += "<p>Put the current values in. Make the button send the new values to the server, and update the tree with new name/title.";
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
        // draw the components before initializing them because InitUI sets js bindings in the dom
        $('#docmanui_tree').html( this.oDocRepUI.DrawTree() );
        $('#docrepctrlview_body').html( this.oDocRepUI.DrawCtrlView() );
        this.oDocRepUI.InitUI();
    }

    HandleEvent( eEvent, p )
    {
        switch( eEvent ) {
            case 'docSelected':
                $('#docrepctrlview_body').html( this.oDocRepUI.DrawCtrlView() );
                break;
        }
    }
}


$(document).ready( function () {
    (new DocRepApp02( { } )).InitUI();
});