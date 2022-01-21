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

class myDocRepCache extends DocRepCache
{
    constructor(oConfig)
    {
        super(oConfig);
    }

    FetchDoc( kDoc )
    {
        if( kDoc == 9 ) {
            this.mapDocs.set( 9, { k:9, name:'folder3/pageF',   doctype: 'page',   kParent: 3,  children: [] } );
        }
    }
}


class myDocRepTree extends DocRepTree
{
    constructor(oConfig)
    {
        super(oConfig);
    }

    InitUI()
    {
        super.InitUI();
    }

    HandleRequest( eNotify, p )
    /**************************
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
        oConfig.defTabs = { preview:"Preview", add:"Add", edit:"Edit", rename:"Rename", versions:"Versions" };

        super(oConfig);
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
        let rQ = [];

/****************************
   Here is where you add code to implement the controls.
   Preferably create a new method, or even a new class, for each of the cases. Don't just put all the code in the switch.
 */

        switch( this.GetCtrlMode() ) {
            case 'preview':
                rQ = SEEDJXSync( "", {qcmd: 'dr-preview', kDoc: kCurrDoc} );
                s = rQ.bOk ? rQ.sOut : `Cannot get preview for document ${kCurrDoc}`;
                break;

            case 'add':
                s = this.drawFormAdd(kCurrDoc);
                break;

            case 'edit':
                rQ = SEEDJXSync( "", {qcmd: 'dr-preview', kDoc: kCurrDoc} );
                if( rQ.bOk ) {
                    s = `<div id='drEdit_notice'></div>
                         <form onsubmit='myDocRepEditSubmit(event)'>
                         <textarea id='drEdit_text' style='width:100%'>${rQ.sOut}</textarea>
                         <br/>
                         <input type='hidden' id='drEdit_kDoc' value='${kCurrDoc}'/>
                         <input type='submit' value='Save'/>
                         </form>`;
                } else {
                    s = `Cannot get preview for document ${kCurrDoc}`;
                }
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

	drawFormAdd(kCurrDoc) {

		// file no need for option 

		let s = `<form onsubmit='myDocRepAddSubmit(event)'>
					<br>	
					<div>Type: </div>
					<div class='row'> 
						<div [label]>File</div>
						<div [ctrl]>
							<input type='radio' id='add-file'  name='file-or-folder' value='file' checked/>
						</div>
					</div>
					<div class='row'> 
						<div [label]>Folder</div>
						<div [ctrl]>
							<input type='radio' id='add-folder'  name='file-or-folder' value='folder' />
						</div>
					</div>
					<br>
					`
		let current = $(`.DocRepTree_title[data-kDoc=${kCurrDoc}]`)[0];
		if (current.dataset.doctype == 'folder') { // if current is a folder, add option to place new doc as child or sibling 
			s += `	<div class='row'> 
						<div [label]>Add under folder</div>
						<div [ctrl]>
							<input type='radio' id='add-as-child'  name='child-or-sibling' value='child' checked/>
						</div>
					</div>
					<div class='row'> 
						<div [label]>Add beside folder</div>
						<div [ctrl]>
							<input type='radio' id='add-as-sibling'  name='child-or-sibling' value='sibling' />
						</div>
					</div>
					<br>`
		}
		s += `		<div class='row'> 
						<div [label]>Name</div>
						<div [ctrl]>
							<input type='text' id='add-name'  value='' style='width:100%'/>
						</div>
					</div>
					<div class='row'> 
						<div [label]>Title</div>
						<div [ctrl]>
							<input type='text' id='add-title'  value='' style='width:100%'/>
						</div>
					</div>
					<div class='row'> 
						<div [label]>Permissions</div>
						<div [ctrl]>
							<input type='text' id='add-permissions'  value='' style='width:100%'/>
						</div>
					</div>										
					<input type='hidden' id='drAdd_kDoc' value='${kCurrDoc}'/>
				    <input type='submit' value='Add'/>
				 <form>`

		s = s.replaceAll("[label]", "class='col-md-3'");
		s = s.replaceAll("[ctrl]", "class='col-md-6'");


		return s;
	}
	
    
    drawFormRename( kCurrDoc )
    {
        let sName = "", sTitle = "", sPerms = "";
        let oDoc = this.fnHandleEvent('getDocInfo', kCurrDoc);
        if( oDoc ) {
            sName = oDoc['name'];
//            sTitle = oDoc['title'];
//            sPerms = oDoc['perms'];
        }
        
        let s = `<form onsubmit='myDocRepRenameSubmit(event)'>
        		 <div class='row'> <div [label]>Name</div>        <div [ctrl]><input type='text' id='formRename_name'  value='${sName}' style='width:100%'/></div></div>
                 <div class='row'> <div [label]>Title</div>       <div [ctrl]><input type='text' id='formRename_title' value='${sTitle}' style='width:100%'/></div></div>
                 <div class='row'> <div [label]>Permissions</div> <div [ctrl]><input type='text' id='formRename_perms' value='${sPerms}' style='width:100%'/></div></div>
                 <input type='hidden' id='drRename_kDoc' value='${kCurrDoc}'/>
                 <input type='submit' value='Change'/>
                 </form>`;
        s = s.replaceAll("[label]", "class='col-md-3'");
        s = s.replaceAll("[ctrl]",  "class='col-md-6'");
        
s += "<p>Put the current values in. Make the button send the new values to the server, and update the tree with new name/title.";
        return( s );
    }
}

function myDocRepAddSubmit( e ) 
{

	e.preventDefault();
	var rQ;
	let kDoc = $('#drAdd_kDoc').val();
	let position = $('input[name=child-or-sibling]:checked').val()
	let type = $('input[name=file-or-folder]:checked').val()
	let name = $('#add-name').val();
	let title = $('#add-title').val();
	let permissions = $('#add-permissions').val();


	if( !name ) {
		return;
	}
	else if( !permissions ) {
		return; 
	}
	else if( !kDoc ) {
		return;
	}

	if ( !position ) { // if no position is selected, or position does not exist, default sibling 
		position == "sibling";
	}

	if ( position == "child" ) {
		rQ = SEEDJXSync("", { qcmd: 'dr--add', kDoc: kDoc, dr_posUnder: kDoc, type: type, dr_name: name, dr_class: title, dr_permclass: permissions });
	}
	else {
		rQ = SEEDJXSync("", { qcmd: 'dr--add', kDoc: kDoc, dr_posAfter: kDoc, type: type, dr_name: name, dr_class: title, dr_permclass: permissions });
	}

	if (!rQ.bOk) {
		console.log("error add");
	}
	else {
		console.log("add success");
		// update tree with new folder/file

		myDocRepAddUpdateTree();
	}
}

function myDocRepEditSubmit( e )
{
    e.preventDefault();
    editor.updateSourceElement();
    let kDoc = $('#drEdit_kDoc').val();
    if( kDoc ) {
        let rQ = SEEDJXSync( "", {qcmd: 'dr--update', kDoc: kDoc, src: 'TEXT', p_text: $('#drEdit_text').val() } );
    console.log(rQ);
        $('#drEdit_notice').html( rQ.bOk ? "Update successful" : "Update failed" );
    }
    console.log(kDoc + "kdoc");
}

/*
rename submit 
*/
function myDocRepRenameSubmit( e ) 
{
	e.preventDefault();;
	let kDoc = $('#drRename_kDoc').val();
	let name = $('#formRename_name').val();
	let title = $('#formRename_title').val();
	let permissions = $('#formRename_perms').val();

	let rQ = SEEDJXSync( "",{ qcmd: 'dr--rename', kDoc: kDoc, name: name, class: title, permclass: permissions });

	console.log("pressed submit");
	console.log(rQ);

	if ( !rQ.bOk ) {
		console.log("error rename");
	}
	else {
		myDocRepRenameUpdateTree(kDoc, name);
	}
}

/*
update tree after rename 
*/
function myDocRepRenameUpdateTree( kDoc, name ) 
{
	let doc = $(`.DocRepTree_title[data-kDoc=${kDoc}]`)[0];
	let child = doc.children[1].nextSibling;
	child.nodeValue = '\u00A0' + name; // \u00a0 is same as &nbsp; in html
}
/*
update tree after adding new doc 
for now, just reload page 
TODO: add doc on front end 
*/
function myDocRepAddUpdateTree() 
{
	location.reload();
	
	// use myDocRepTree to update tree 
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

        this.oCache = new myDocRepCache( 
                        { mapDocs: mymapDocs,
                          fnHandleEvent: this.HandleRequest.bind(this) } );    // tell the object how to send events here
// these parms should be in oConfig
        this.oTree = new myDocRepTree(
                        { mapDocs: mymapDocs,
                          dirIcons: '../../wcore/img/icons/',
                          fnHandleEvent: this.HandleRequest.bind(this) } );    // tell the object how to send events here
        this.oCtrlView = new myDocRepCtrlView(
                        { fnHandleEvent: this.HandleRequest.bind(this) } );    // tell the object how to send events here
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

    HandleRequest( eRequest, p = 0 )
    /*******************************
        Components call here with notifications/requests
     */
    {
        switch( eRequest ) {
            case 'docSelected':
                break;

// is this the best way for widgets to get this?
            case 'getKDocCurr':
                return( this.oTree.GetCurrDoc() );
                
            case 'getDocInfo':
                return( this.oCache.GetDocInfo(p) );
                
            case 'getDocInfoCurr':
                let kDocCurr = this.oTree.GetCurrDoc();
                return( kDocCurr ? this.oCache.GetDocInfo(kDocCurr) : null );
        }
        this.fnHandleEvent( eRequest, p );
    }
}


class DocRepApp02
/****************
    Manage the rendering of DocRepUI components. The components know how to update each others' states.
 */
{
    constructor( oConfig )
    {
        this.oDocRepUI = new DocRepUI02( { fnHandleEvent: this.HandleRequest.bind(this) } );    // tell DocRepUI how to send events here
    }

    InitUI()
    {
        // draw the components before initializing them because InitUI sets js bindings in the dom
        $('#docmanui_tree').html( this.oDocRepUI.DrawTree() );
        $('#docrepctrlview_body').html( this.oDocRepUI.DrawCtrlView() );
        this.oDocRepUI.InitUI();
    }

    HandleRequest( eRequest, p )
    {
        switch( eRequest ) {
            case 'docSelected':
                $('#docrepctrlview_body').html( this.oDocRepUI.DrawCtrlView() );
                break;
        }
    }
}


$(document).ready( function () {
    (new DocRepApp02( { } )).InitUI();
});
