/* Implements a custom DocManager
 *
 * Copyright (c) 2021-2022 Seeds of Diversity Canada
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
        oConfig.defTabs = oConfig.ui.eUILevel == 3 ? { preview:"Preview", add:"Add", rename:"Rename", versions:"Versions", vars:"Variables", schedule:"Schedule", xml:"XML" }
                                                   : { preview:"Preview", add:"Add", rename:"Rename", schedule:"Schedule" };

        super(oConfig);

        this.oConfigEnv = oConfig.env;      // save the application environment config
        this.oConfigUI = oConfig.ui;        // save the ui config
        myDocRepCtrlView_Preview.Reset();   // so the Preview tab starts in Preview mode
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
    
    // TODO: on first time load, no doc is selected 
    // show default message or select a doc by default

    DrawCtrlView_Render( parentID, kCurrDoc )
    /******************************
        draws contents of ctrl_view body under parentID
     */
    {
        switch( this.GetCtrlMode() ) {
            case 'preview':
                // use static class to implement the Preview pane
                myDocRepCtrlView_Preview.Init(this, kCurrDoc);
                myDocRepCtrlView_Preview.DrawTabBody(parentID);
                break;

            case 'add':
            	myDocRepCtrlView_Add.Init(this, kCurrDoc);
                myDocRepCtrlView_Add.DrawTabBody(parentID);
                break;

            case 'rename':
                myDocRepCtrlView_Rename.Init(this, kCurrDoc);
                myDocRepCtrlView_Rename.DrawTabBody(parentID);
                break;
                
            case 'versions':
            	myDocRepCtrlView_Versions.Init(this, kCurrDoc);
                myDocRepCtrlView_Versions.DrawVersions(parentID);
                myDocRepCtrlView_Versions.DrawTabBody(parentID);
                break;
                
            case 'vars':
                myDocRepCtrlView_Vars.Init(this, kCurrDoc);
                myDocRepCtrlView_Vars.DrawTabBody(parentID);
                break;
            
            case 'schedule':
            	myDocRepCtrlView_Schedule.Init(this, kCurrDoc);
                myDocRepCtrlView_Schedule.DrawTabBody(parentID);
            	break;
            	
            case 'xml':
            	myDocRepCtrlView_XML.Init(this, kCurrDoc);
                myDocRepCtrlView_XML.DrawTabBody(parentID);
            	break;

            default:
                $(`#${parentID}`).html(`<div>unknown control mode</div>`);
        }
    }

    DrawCtrlView_Attach()
    {
    }
}

class myDocRepCtrlView_Preview
/*****************************
    Implement the Preview pane of the Ctrlview
 */
{
    static oCtrlView = null;    // the myDocRepCtrlView using this class
    static kCurrDoc = 0;        // the current doc (you could also get this via oCtrlView)
    
    static Init( oCtrlView, kCurrDoc )
    {
        this.oCtrlView = oCtrlView;
        this.kCurrDoc = kCurrDoc;
    }
    
    static Reset()
    {
        this.#setMode('');  // force to default
    }
    
    static #getMode()
    {
        // default is preview
        return( this.#normalizeMode( sessionStorage.getItem('DocRepCtrlView_preview_mode') ) );
    }

    static #setMode( m )
    {
        m = this.#normalizeMode(m);    // mode can be preview, source, or edit
        
        sessionStorage.setItem( 'DocRepCtrlView_preview_mode', m );
    }
    
    // modes can be preview (default), source, or edit
    static #normalizeMode( m ) { return( (m == 'source' || m == 'edit') ? m : 'preview'); }
    
    static DrawTabBody( parentID )
    {
        let s = "";
        let rQ = null;
        let m = this.#getMode();
        
        let oDoc = this.oCtrlView.fnHandleEvent('getDocInfo', this.kCurrDoc);
        if( !oDoc || oDoc.doctype != 'page' ) {
			$(`#${parentID}`).empty(); 
			$(`#${parentID}`).html(`Click on a page to see its content`); 
			return;
		}

		$(`#${parentID}`).empty(); 
        $(`#${parentID}`).html(`
        	<div>
             	<select id='drCtrlview-preview-state-select' onchange='myDocRepCtrlView_Preview.Change(this.value)'>
                    <option value='preview'` +(m=='preview' ? ' selected' :'')+ `>Preview</option>`
                  +(this.oCtrlView.oConfigUI.eUILevel==3 ? (`<option value='source'`  +(m=='source'  ? ' selected' :'')+ `>Source</option>`) : '')
                  +`<option value='edit'`    +(m=='edit'    ? ' selected' :'')+ `>Edit</option>
             	</select>
            	<div id='drCtrlview-preview-body' style='border:1px solid #aaa;padding:20px;margin-top:10px'></div>
            </div>`);
           
        
        switch( m ) {
            case 'preview':
                if( (rQ = SEEDJXSync( this.oCtrlView.oConfigEnv.q_url, {qcmd: 'dr-preview', kDoc: this.kCurrDoc, bExpand: 1} )) ) {
                    s = rQ.bOk ? rQ.sOut : `Cannot get preview for document ${this.kCurrDoc}`;
                    $(`#drCtrlview-preview-body`).append(s);
                }
                break;
            case 'source':
                rQ = SEEDJXSync( this.oCtrlView.oConfigEnv.q_url, {qcmd: 'dr-preview', kDoc: this.kCurrDoc} );
                if( rQ.bOk ) {
                    s = "<div style='font-family:monospace'>" 
                      + rQ.sOut.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') 
                      + "</div>";
                    $(`#drCtrlview-preview-body`).append(s);
                } else {
                    s = `Cannot get preview for document ${this.kCurrDoc}`;
                    $(`#drCtrlview-preview-body`).append(s);
                }
                break;
            case 'edit':
                rQ = SEEDJXSync( this.oCtrlView.oConfigEnv.q_url, {qcmd: 'dr-preview', kDoc: this.kCurrDoc} );
                if( rQ.bOk ) {
                    s = myDocRepCtrlView_Edit.DrawEditor(this.kCurrDoc, rQ.sOut); // create textarea 
                    $(`#drCtrlview-preview-body`).append(s);
                    myDocRepCtrlView_Edit.InitEditor(this.oCtrlView); // attarch CKEditor to textarea 
                }
                break;
        }
    }
    
    
    static Change( mode )
    /********************
        Called when the <select> changes
     */
    {
        this.#setMode(mode);
        this.oCtrlView.DrawCtrlView();
    }
}

class myDocRepCtrlView_Edit
/**************************
    Implement the Edit control of the Ctrlview.
    This is used within the Preview pane, and also in a full-screen mode.
 */
{
    static CKEditorInstance = null;
    static oCtrlView = null;            // the ctrlview using this object

    static InitEditor( oCtrlView )
    /******************
        Attach the CKEditor to the <textarea>
     */
    {
        this.oCtrlView = oCtrlView;
        
    	CKEDITOR.replace( 'drEdit_text', {
			//customConfig: '/seeds/wcore/seedapp/DocRep/ckeditor_config.js'
		} );
		
		this.CKEditorInstance = CKEDITOR.instances.drEdit_text;
	}

    static DrawEditor( kCurrDoc, sContent )
    {
        let s = `<div id='drEdit_notice'></div>
                 <form onsubmit='myDocRepCtrlView_Edit.SaveHandler(event)'>
                 <textarea id='drEdit_text' style='width:100%'>${sContent}</textarea>
                 <br/>
                 <input type='hidden' id='drEdit_kDoc' value='${kCurrDoc}'/>
                 <input type='submit' value='Save'/> <input type='checkbox' id='dr_Edit_newversion' value='1'/> Save New Version
                 </form>`;
        return( s );
    }    
    
    static SaveHandler( e )
    /**********************
        Event handler for editor Save
     */
    {
        e.preventDefault();
        //this.CKEditorInstance.updateSourceElement();
        let text = this.CKEditorInstance.getData();

        let kDoc = $('#drEdit_kDoc').val();
        if( kDoc ) {
            let rQ = SEEDJXSync( this.oCtrlView.oConfigEnv.q_url,
                                 {qcmd:'dr--update', kDoc:kDoc, p_src:'TEXT', p_text:text, 
                                                     p_bNewVersion:$('#dr_Edit_newversion').is(':checked') ? 1 : 0
                                 } );
            // console.log(rQ);
            $('#drEdit_notice').html( rQ.bOk ? "Update successful" : "Update failed" );
        }
        // console.log(kDoc + "kdoc");
    }
}

class myDocRepCtrlView_Add
{
	
	static oCtrlView = null;    // the myDocRepCtrlView using this class
    static kCurrDoc = 0;        // the current doc (you could also get this via oCtrlView)
    
    static Init( oCtrlView, kCurrDoc )
    {
        this.oCtrlView = oCtrlView;
        this.kCurrDoc = kCurrDoc;
    }
    
    static DrawTabBody( parentID )
    /*****************************
        Draw form for add 
     */
    {
		let oDoc = this.oCtrlView.fnHandleEvent('getDocInfo', this.kCurrDoc);   
		let sType = '';
        if( oDoc ) {
            sType = oDoc['doctype'];
        }      
        
        const label = 'col-md-3';
        const ctrl = 'col-md-6';
        
        $(`#${parentID}`).empty();
        $(`#${parentID}`).html(`
        		<form id='drAdd_form' onsubmit='myDocRepCtrlView_Add.Submit(event, "${this.oCtrlView.oConfigEnv.q_url}")'>
					<div class='row'> 
						<div class='col-md-12'>
						    <select id='select-page-or-folder'>
						    <option value='page' selected>Add a new Page</option>
						    <option value='folder'>Add a new Folder</option> 
						    </select>
						</div>
					</div>
				</form>`);

		if( sType == 'folder' ) { // if current is a folder, add option to place new doc as child or sibling 
			$(`#drAdd_form`).append(`
					<div class='row'> 
						<div class='col-md-12'>
                            <select id='select-child-or-sibling'>
                            <option value='child' selected>Inside the current folder</option>
                            <option value='sibling'>After the current folder</option> 
                            </select>
						</div>
					</div>`);
		}
		$(`#drAdd_form`).append(`
					<br/>
					<div class='row'> 
						<div class=${label}>Name</div>
						<div class=${ctrl}>
							<input type='text' id='add-name'  value='' style='width:100%'/>
						</div>
					</div>
					<div class='row'> 
						<div class=${label}>Title</div>
						<div class=${ctrl}>
							<input type='text' id='add-title'  value='' style='width:100%'/>
						</div>
					</div>`
                  +(this.oCtrlView.oConfigUI.eUILevel>=2 ?
                    `<div class='row'> 
						<div class=${label}>Permissions</div>
						<div class=${ctrl}>
							<input type='text' id='add-permissions'  value='1' style='width:100%'/>
						</div>
					</div>` : '')
                  +`<br/><input type='hidden' id='drAdd_kDoc' value='${this.kCurrDoc}'/>
				    <input type='submit' value='Add'/>
				</form>
				<br>
				<br>`);
				
        if( this.oCtrlView.oConfigUI.eUILevel>=2 ) {
            $(`#drAdd_form`).append(`
                <form id='drAdd_duplicate' onsubmit='myDocRepCtrlView_Add.Duplicate(event, "${this.oCtrlView.oConfigEnv.q_url}")'>
                <input type='submit' value='Duplicate Folder/File' />
                </form>`);
        }
    }

    static Submit( e, q_url ) 
    {
        e.preventDefault();

        let oDocCurr = this.oCtrlView.fnHandleEvent('getDocInfoCurr');
        let kDoc = oDocCurr.k; // $('#drAdd_kDoc').val();
        
        let position = $('#select-child-or-sibling').val();
        let type = $('#select-page-or-folder').val();
        let name = $('#add-name').val();
        let title = $('#add-title').val();
        let permclass = $('#add-permissions').val();

        if( !kDoc )  return(false);
        if( position != 'child' ) position = 'sibling';      // default sibling
        if( !permclass ) {
            // not defined probably because the control is not exposed in this user mode. Use same permclass as parent.
            let oDoc = this.oCtrlView.fnHandleEvent('getDocInfo', kDoc);
            if( !oDoc ) {
                return( false );
            }

            if( position == 'child' ) {
                // kDoc is the parent so use the same permclass
                permclass = oDoc.perms;
            } else {
                // kDoc is the sibling so use the parent's permclass
                if( oDoc.kParent ) {
                    oDoc = this.oCtrlView.fnHandleEvent('getDocInfo', oDoc.kParent);
                    permclass = oDoc.perms;
                } else {
                    alert( "Cannot add at document tree root without specifying permission class" );
                    return( false );
                }
            }
            console.log("Permission not specified: using permclass "+permclass+" from parent");
        }

        let q = { qcmd: 'dr--add', kDoc: kDoc,
                                   type: type=='folder' ? 'folder' : 'text',
                                   dr_name: name, dr_title: title, dr_permclass: permclass };

        if( position == 'child' ) {
            q.dr_posUnder = kDoc;
        } else {
            q.dr_posAfter = kDoc;
        }
        console.log(q);
        
        let rQ = SEEDJXSync(q_url, q);
        if( !rQ.bOk ) {
            console.log( "Error adding doc: "+rQ.sErr, q );
        } else {
            // update tree with new folder/file
//	this.UpdateTree();
        }
        return( false );
    }

	static Duplicate( e, q_url )
	/**
	make a copy of currently selected folder beside 
	use export and import xml 
	 */
	{
		e.preventDefault();
		let kDoc = this.kCurrDoc;	
		let rQ = SEEDJXSync(q_url, { qcmd: 'dr-XMLExport', kDoc: kDoc }); // export xml of selected folder/file
		
		if( !rQ.bOk ) {
			console.log("error duplicating");
		}
		else {
			$(`#drXML_export`).html(rQ.sOut);
		}
		
		function decode(s){ // decode html entities, eg. &lt; becomes <
			var t = document.createElement('textarea');
			t.innerHTML = s;
			return t.value;
		}
		
		let xml = decode(rQ.sOut);
		
		rQ = SEEDJXSync(q_url, { qcmd: 'dr--XMLImport', kDoc: kDoc, xml:xml }); // import xml of selected folder/file

		if( !rQ.bOk ) {
			console.log("error duplicating");
		}
		else {
			location.reload();
		}
	}
		
	/*
	update tree after adding new doc 
	for now, just reload page 
	*/
	static UpdateTree() 
	{
		location.reload();
		// TODO: 
		// call ajax to update map 
		// redraw tree with updated map 
	}
}

class myDocRepCtrlView_Rename
{
	static oCtrlView = null;
    static kCurrDoc = 0;
    
    static Init( oCtrlView, kCurrDoc )
    {
        this.oCtrlView = oCtrlView;
        this.kCurrDoc = kCurrDoc;
    }
    
    static DrawTabBody( parentID )
    /**
    draw rename form
     */
    {
        let sName = "", sTitle = "", sPerms = "";
        let oDoc = this.oCtrlView.fnHandleEvent('getDocInfo', this.kCurrDoc);

        if( oDoc ) {
            sName = oDoc['name'];
            sTitle = oDoc['title'];
            sPerms = oDoc['perms'];
        }
		const label = 'col-md-3';
        const ctrl = 'col-md-6';
        
        $(`#${parentID}`).empty();
        
        $(`#${parentID}`).html(`
        	<form onsubmit='myDocRepCtrlView_Rename.Submit(event, "${this.oCtrlView.oConfigEnv.q_url}")'>
        		<div class='row'> 
        		 	<div class=${label}>Name</div>        
        		 	<div class=${ctrl}>
        		 		<input type='text' id='formRename_name'  value='${sName}' style='width:100%'/>
        		 	</div>
        		</div>
                <div class='row'> 
                 	<div class=${label}>Title</div>       
                 	<div class=${ctrl}>
                 		<input type='text' id='formRename_title' value='${sTitle}' style='width:100%'/>
                 	</div>
                </div>`
              +(this.oCtrlView.oConfigUI.eUILevel>=2 ?
                    `<div class='row'> 
                        <div class=${label}>Permissions</div>
                        <div class=${ctrl}>
                            <input type='text' id='formRename_perms' value='${sPerms}' style='width:100%'/>
                        </div>
                    </div>` : '')
              +`<br/><input type='hidden' id='drRename_kDoc' value='${this.kCurrDoc}'/>
                <input type='submit' value='Change'/>
			</form>`);
    }
    
   static Submit( e, q_url ) 
   /**
   change name 
    */
	{
		e.preventDefault();;
		let kDoc = parseInt($('#drRename_kDoc').val());
		let name = $('#formRename_name').val();
		let title = $('#formRename_title').val();
		let permclass = $('#formRename_perms').val();
	
		let rQ = SEEDJXSync( q_url, { qcmd: 'dr--rename', kDoc: kDoc, name: name, title: title, permclass: permclass });
		if ( !rQ.bOk ) {
			console.log("error rename");
		}
		else {
			this.UpdateTree(kDoc, name, title, permclass);
		}
	}
	
	/*
	update tree after rename 
	*/
	static UpdateTree( kDoc, name, title, permclass ) 
	{
        // change the name in the tree
		let doc = $(`.DocRepTree_title[data-kDoc=${kDoc}]`)[0];
		let child = doc.children[1].nextSibling;
		child.nodeValue = '\u00A0' + name; // \u00a0 is same as &nbsp; in html
		
		// change the values in the cache
		let parms = {kDoc:kDoc, permclass:permclass};
		parms.name = name;    // adding them this way means not having to escape quotes
		parms.title = title;
		this.oCtrlView.fnHandleEvent('updateDocInfo', parms);
	}
}

class myDocRepCtrlView_Versions
{
	static oCtrlView = null;
    static kCurrDoc = 0;
    
    static Init( oCtrlView, kCurrDoc )
    {
        this.oCtrlView = oCtrlView;
        this.kCurrDoc = kCurrDoc;
    }
    
	static DrawVersions( parentID )
    /**
    display a list of versions of a doc 
     */
    {
		let oDoc = this.oCtrlView.fnHandleEvent('getDocInfo', this.kCurrDoc);
        if( !oDoc || oDoc.doctype != 'page' ) {
			$(`#${parentID}`).empty(); 
			$(`#${parentID}`).html(`No versions info available`); 
			return;
		}
	
		$(`#${parentID}`).empty();
		$(`#${parentID}`).html('Version information not available');
		let rQ = SEEDJXSync( this.oCtrlView.oConfigEnv.q_url, {qcmd: 'dr-versions', kDoc: this.kCurrDoc} );
		
		if(!rQ.bOk){
			return s;
		}
		else{
			$(`#${parentID}`).html('<div>versions: </div>');
			let versions = rQ.sOut;
			let index = Object.keys(versions).reverse();
			
			for( let i of index ){	
				
				$(`#${parentID}`).append(`
					<div class='versions-file'onclick='
						myDocRepCtrlView_Versions.UpdatePreview(${this.kCurrDoc}, ${i}, "${this.oCtrlView.oConfigEnv.q_url}"); 
						myDocRepCtrlView_Versions.UpdateDiff(event, ${this.kCurrDoc}, ${i}, "${this.oCtrlView.oConfigEnv.q_url}"); 
						myDocRepCtrlView_Versions.UpdateModify(${this.kCurrDoc}, ${i}, "${this.oCtrlView.oConfigEnv.q_url}")'> 
						
						<span class='versions-number'>${versions[i].ver}</span>
						<span class='versions-title'>${versions[i].title}</span>
					</div>`);
			}
		}
		$(`#${parentID}`).append(`<br>`);		
	}
	
    static DrawTabBody( parentID, versionNumber )
    /**
    form for previewing and modifying versions
     */
    {
		let oDoc = this.oCtrlView.fnHandleEvent('getDocInfo', this.kCurrDoc);
        if( !oDoc || oDoc.doctype != 'page' ) {
			return;
		}
	
		if( versionNumber ){ // if version is selected 
		}
			
		$(`#${parentID}`).append(`
		<div>
			<span >Preview: </span>
			<div id='versions-preview'>Select a version to preview</div>
		</div>
		<div>
			<span>Difference between current and previous version: </span>
			<div id='versions-diff'>Difference not available</div>
		</div>
		<div>
			<span>Delete / Restore: </span>
			<div id='versions-modify'>Select a version to delete</div>
			
		</div>
		<div>
			<span>Flags: </span>
			<div id='versions-flags' style='height:50px; border:1px solid;'>Select a version to see flags</div>
		</div>
		<div>
		Note: delete, restore, flags not finished yet
		</div>`);

	}
	
	static UpdatePreview( kCurrDoc, versionNumber, q_url )
	/**
	update preview based on version selected 
	 */
	{
		let rQ = SEEDJXSync( q_url, {qcmd: 'dr-versions', kDoc: kCurrDoc, version: versionNumber} );
		
		if(!rQ.bOk){
			return;
		}
		else{
			$('#versions-preview').html(rQ.sOut['data_text']);
		}
	}
	
	static UpdateDiff( e, kCurrDoc, versionNumber, q_url )
	/**
	show difference between current selected and previous version
	 */
	{
		let target = e.target;
		if(target.className == 'versions-number' || target.className == 'versions-title'){
			target = target.parentNode;
		}

		if(target.nextElementSibling.className == 'versions-file'){ // find next available version
			let versionNumber2 = target.nextElementSibling.firstElementChild.innerHTML;

			let rQ = SEEDJXSync( q_url, {qcmd: 'dr-versionsDiff', kDoc1: kCurrDoc, kDoc2: kCurrDoc, ver1: versionNumber, ver2:versionNumber2} );
			
			if(!rQ.bOk){
				return;
			}
			else{
				// update diff view 
				let diffString = rQ.sOut;
				$('#versions-diff').html(diffString);
			}
		}
		else{
			$('#versions-diff').html('Difference not available');
		}	
	}
	
	static UpdateModify( kCurrDoc, versionNumber, q_url )
	/**
	add delete and restore button when a version is clicked
	 */
	{
		$('#versions-modify').html(`
			<button id='versions-delete' type='button' onclick='myDocRepCtrlView_Versions.SubmitDelete(${kCurrDoc}, ${versionNumber}, "${q_url}")'>delete</button>
			<button id='versions-restore' type='button' onclick='myDocRepCtrlView_Versions.SubmitRestore(${kCurrDoc}, ${versionNumber}, "${q_url}")'>restore</button>`);
	}
	static SubmitDelete( kCurrDoc, versionNumber, q_url )
	/**
	delete current version 
	once version is deleted, it will not be shown, so there's not way to restore it
	 */
	{
		let rQ = SEEDJXSync( q_url, {qcmd: 'dr--versionsDelete', kDoc: kCurrDoc, version: versionNumber} );
		if(!rQ.bOk){
			console.log('error delete version');
		}
		else{
			$(`.versions-number:contains(${versionNumber})`).parent().remove();
			$('#versions-preview').html('Select a version to preview');
			$('#versions-diff').html('Difference not available');
			$('#versions-modify').html('Select a version to delete');
			$('#versions-flags').html('Select a version to see flags');
		}
	}
	
	static SubmitRestore( kCurrDoc, versionNumber, q_url )
	/**
	restore current version 
	not finished yet 
	 */
	{
		console.log("clicked on restore");
		let rQ = SEEDJXSync( q_url, {qcmd: 'dr--versionsRestore', kDoc: kCurrDoc, version: versionNumber} );
		console.log('restore not implemented in database yet');
		if(!rQ.bOk){
			return;
		}
		else{
			
		}
	}
}

class myDocRepCtrlView_Vars
/**************************
    Implement the Variables pane of the Ctrlview
 */
{
    static oCtrlView = null;    // the myDocRepCtrlView using this class
    static kCurrDoc = 0;        // the current doc (you could also get this via oCtrlView)
    
    static Init( oCtrlView, kCurrDoc )
    {
        this.oCtrlView = oCtrlView;
        this.kCurrDoc = kCurrDoc;
    }
    
    static DrawTabBody( parentID )
    {
	
        let jForm = null;
        let rQ = null;
        
        let oDoc = this.oCtrlView.fnHandleEvent('getDocInfo', this.kCurrDoc);
        /*
        if( !oDoc || oDoc.doctype != 'page' ) {
			$(`#${parentID}`).empty(); 
			$(`#${parentID}`).html(`No variables info available`); 
			return;
		}
        */
        jForm = $(`<form id='drVars_form' onsubmit='myDocRepCtrlView_Vars.Submit(event)'>
                   <div class='row' id='drVars_rownew'>
                       <div class='col-4'><input type='text' id='var_knew' style='width:100%'/></div>
                       <div class='col-8'><input type='text' id='var_vnew' style='width:100%'/></div>
                   </div>
                   <input type='submit' value='Save'>
                   </form>`);
        let i = 0;
        for( const k in oDoc.docMetadata ) {
            // Create a pair of input elements for the key and value of this variable, and append them to the form.
            // N.B. It must be allowed for k={blank} to be stored, so a blank var can overwrite an ancestor's value.
            //      k=={blank} must be allowed to delete a variable from the set.  
            let jInput = $(`<div class='row'>
                            <div class='col-4'><input type='text' id='var_k${i}' style='width:100%'/></div>
                            <div class='col-8'><input type='text' id='var_v${i}' style='width:100%'/></div>
                            </div>`);
            jInput.find('input#var_k'+i).val(k);
            jInput.find('input#var_v'+i).val(oDoc.docMetadata[k]);
            jInput.insertBefore(jForm.find('#drVars_rownew'));
            ++i;
        }
        // append an empty pair of inputs so a new value can be entered, and a Save button
        jForm.append( $(``) );

        $(`#${parentID}`).html(jForm);
        
    }
    
    static Submit(e)
    {
        e.preventDefault();
        
        let oDoc = this.oCtrlView.fnHandleEvent('getDocInfo', this.kCurrDoc);
        if( !oDoc || oDoc.doctype != 'page' ) return( "" );

        let newVars = {};
        // The form contains the same number of input pairs as the docMetedata        
        for( let i = 0; i < Object.keys(oDoc.docMetadata).length; ++i ) {
            let k = $("#drVars_form input#var_k"+i).val();
            let v = $("#drVars_form input#var_v"+i).val();
         
            // if k is blank, the var is deleted
            if( k ) {
                newVars[k] = v;
            }
        }
        let k = $("#drVars_form input#var_knew").val();
        let v = $("#drVars_form input#var_vnew").val();
        if( k ) {
            newVars[k] = v;
        }
        
        // save the new var set on the server
        let rQ = SEEDJXSync( this.oCtrlView.oConfigEnv.q_url,
                             {qcmd:'dr--docMetadataStoreAll', kDoc:this.kCurrDoc, p_docMetadata:JSON.stringify(newVars) } );
        // save the new var set on the client
        oDoc.docMetadata = newVars;
        
        this.oCtrlView.fnHandleEvent('ctrlviewRedraw');
    }
}

class myDocRepCtrlView_Schedule
{
	static oCtrlView = null;
    static kCurrDoc = 0;
    
    static Init( oCtrlView, kCurrDoc )
    {
        this.oCtrlView = oCtrlView;
        this.kCurrDoc = kCurrDoc;
    }
    
    static DrawTabBody( parentID )
    /**
    if CurrDoc is a file, list schedule of the file 
    if CurrDoc is a folder, list schedule of all files under the folder (assume only 1 depth)
     */
    {
		let sName = '', sType = '', sSchedule = '', raChildren = '', kDocParent = '';
		let sNameEmail = '', sTypeEmail = '', sScheduleEmail = '';
        let oDoc = this.oCtrlView.fnHandleEvent('getDocInfo', this.kCurrDoc);
        
        if( oDoc ) {
            sName = oDoc['name'];
            sType = oDoc['doctype'];
            sSchedule = oDoc['schedule'];
            raChildren = oDoc['children'];
            kDocParent = oDoc['kParent'];
        }
        
        $(`#${parentID}`).empty();
        $(`#${parentID}`).html('Schedule not available');	
		
		if( sType == 'page' ){ // if selected is a file 
		
			let oDocParent = this.oCtrlView.fnHandleEvent('getDocInfo', kDocParent);
			if(oDocParent['name'].toLowerCase().includes('schedule')){
				
				$(`#${parentID}`).html(`
					<form onsubmit='myDocRepCtrlView_Schedule.Submit(event, "${this.oCtrlView.oConfigEnv.q_url}")'>
						<div class='row'> 
							<div class='col-md-9'>${sName}</div>
							<div class='col-md-3'>
								<input type='text' class='schedule-date'  value='${sSchedule}' style='width:100%'/>
							</div>
						</div>	
						
						<input type='hidden' class='drSchedule_kDoc' value='${this.kCurrDoc}'/>
					    <input type='submit' value='update schedule'/>
					</form>`)
			}
		}
		else if( sType == 'folder' && sName.toLowerCase().includes('schedule') ) { // if slected is a folder and contains schedule in name 
			
			if( this.folderContainsEmail( this.kCurrDoc ) ){
				$(`#${parentID}`).html(`<form id='drSchedule_form' onsubmit='myDocRepCtrlView_Schedule.Submit(event, "${this.oCtrlView.oConfigEnv.q_url}")'></form>`);
			}
			else{
				$(`#${parentID}`).html(`No emails found under folder`);
			}		
			for( let kDocEmail of raChildren ){ // loop through all children 
				let oDocEmail = this.oCtrlView.fnHandleEvent('getDocInfo', kDocEmail);
				
				if( oDocEmail ) {
		            sNameEmail = oDocEmail['name'];
		            sTypeEmail = oDocEmail['doctype'];
		            sScheduleEmail = oDocEmail['schedule'];
        		}
				if( sTypeEmail == 'page' ){ // if child is a file 
			
					$(`#drSchedule_form`).append(`
						<div class='row'> 
							<div class='col-md-9'>${sNameEmail}</div>
							<div class='col-md-3'>
								<input type='text' class='schedule-date'  value='${sScheduleEmail}' style='width:100%'/>
							</div>
						</div>	

						<input type='hidden' class='drSchedule_kDoc' value='${kDocEmail}'/>`);
				}
			}
			if( this.folderContainsEmail( this.kCurrDoc ) ){
				$(`#drSchedule_form`).append(`<input type='submit' value='update schedule'/>`);
			}		
		}
	}
	
	static folderContainsEmail( kDoc )
	/**
	check to see if a given folder contains emails
	 */
	{
		let oDoc = this.oCtrlView.fnHandleEvent('getDocInfo', kDoc);
		
		if( oDoc['doctype'] == 'folder' && oDoc['name'].toLowerCase().includes('schedule') ){
			for( let kDocEmail of oDoc['children'] ){
				let oDocEmail = this.oCtrlView.fnHandleEvent('getDocInfo', kDocEmail);			
				if ( oDocEmail['doctype'] == 'page' ){
					return true;
				}
			}
		}
		return false;
	}
	
	static Submit( e, q_url )
	/**
	update schedule with date information 
	 */
	{
		e.preventDefault();
		let allKDoc = $('.drSchedule_kDoc');
		let allSchedule = $('.schedule-date');

		for(let i = 0; i < allKDoc.length; i++){
			
			let kDoc = allKDoc[i].value;
			let schedule = allSchedule[i].value;

			let rQ = SEEDJXSync( q_url, { qcmd: 'dr--schedule', kDoc: kDoc, schedule: schedule });

			if ( !rQ.bOk ) {
				console.log("error schedule");
			}
			else {
				 console.log("ok schedule")
			}
		}
	}
}

class myDocRepCtrlView_XML
{
	static oCtrlView = null;
    static kCurrDoc = 0;
    
    static Init( oCtrlView, kCurrDoc )
    {
        this.oCtrlView = oCtrlView;
        this.kCurrDoc = kCurrDoc;
    }
    
    static DrawTabBody( parentID )
    {
		$(`#${parentID}`).append(`<div><button type='button' onclick='myDocRepCtrlView_XML.export()'>export xml</button></div>`);
		$(`#${parentID}`).append(`<div><button type='button' onclick='myDocRepCtrlView_XML.import()'>import xml</button></div>`);
		$(`#${parentID}`).append(`<div id='drXML_export'></div>`);
		$(`#${parentID}`).append(`<textarea id='drXML_import'></textarea>`);
	}
	
	static export()
    {
		let kDoc = this.kCurrDoc;
			
		let rQ = SEEDJXSync(this.oCtrlView.oConfigEnv.q_url, { qcmd: 'dr-XMLExport', kDoc: kDoc });
		if( !rQ.bOk ) {
			console.log("error exporting xml");
		}
		else {
			$(`#drXML_export`).html(rQ.sOut);
		}
		
		console.log(rQ.sOut);
		
	}
	
	static import()
	{
		console.log("start xml import");
		
		let kDoc = this.kCurrDoc;
		let xml = $(`#drXML_import`).val();
		
		//check to make sure field is inputted 
		if( !xml || !kDoc ) {
			console.log("xml input or kDoc is missing")
			return;
		}

		let rQ = SEEDJXSync(this.oCtrlView.oConfigEnv.q_url, { qcmd: 'dr--XMLImport', kDoc: kDoc, xml:xml });

		if( !rQ.bOk ) {
			console.log("error importing xml");
		}
		else {
			location.reload();
		}
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

        this.oCache = new myDocRepCache( 
                        { mapDocs: oConfig.docsPreloaded,
                          fnHandleEvent: this.HandleRequest.bind(this) } );    // tell the object how to send events here

        this.oTree = new myDocRepTree(
                        { mapDocs: oConfig.docsPreloaded,
                          dirIcons: oConfig.env.seedw_url+'img/icons/',
                          fnHandleEvent: this.HandleRequest.bind(this) } );    // tell the object how to send events here

        this.oCtrlView = new myDocRepCtrlView(
                        { fnHandleEvent: this.HandleRequest.bind(this),        // tell the object how to send events here
                          env: oConfig.env,                                     // tell the ctrlview how to interact with the application environment
                          ui:  oConfig.ui 
                        } );

        this.kCurrDoc = 0;
        
        console.log("DocRepUI at level "+oConfig.ui.eUILevel);
    }

    DrawTree()
    {
        return( this.oTree.DrawTree( 0 ) );
    }

    DrawCtrlView()
    {
        this.oCtrlView.DrawCtrlView();
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
                
            case 'updateDocInfo':
                return( this.oCache.UpdateDocInfo(p) );

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
        oConfig.fnHandleEvent = this.HandleRequest.bind(this);      // tell DocRepUI how to send events here
        this.oDocRepUI = new DocRepUI02( oConfig );
    }

    InitUI()
    {
        // draw the components before initializing them because InitUI sets js bindings in the dom
        $('#docmanui_tree').html( this.oDocRepUI.DrawTree() );
        this.oDocRepUI.DrawCtrlView();
        this.oDocRepUI.InitUI();
    }

    HandleRequest( eRequest, p )
    {
        switch( eRequest ) {
            case 'docSelected':     // when a doc/folder is clicked in the tree, this request is issued to redraw the CtrlView
            case 'ctrlviewRedraw':  // the CtrlView can request itself to be redrawn when its state changes
                this.oDocRepUI.DrawCtrlView();
                break;
        }
    }
}

// override these config values before document.ready
var oDocRepApp02_Config = {
    // configuration of the application's environment
    env: { 
        seedw_url:    '../../wcore/',         // url to seeds wcore directory
        q_url:        ''                      // url to server that handles QServerDocRep commands
    },
    docsPreloaded: new Map([]),               // replace this with Map() of docs pre-loaded for DocRepTree
    ui: {
        eUILevel:     1                       // 1=basic UI, 2=more advanced, 3=full UI
    }
};

$(document).ready( function () {
    (new DocRepApp02( oDocRepApp02_Config )).InitUI();
});
