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