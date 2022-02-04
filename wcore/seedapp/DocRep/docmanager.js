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
        oConfig.defTabs = { preview:"Preview", add:"Add", 
                            //edit:"Edit", 
                            rename:"Rename", versions:"Versions", schedule:"Schedule" };

        super(oConfig);

        this.oConfigEnv = oConfig.env;      // save the application environment config
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

    DrawCtrlView_Render( kCurrDoc )
    {
        let s = "";
        let rQ = [];

/****************************
   Here is where you add code to implement the controls.
   Preferably create a new method, or even a new class, for each of the cases. Don't just put all the code in the switch.
 */

        switch( this.GetCtrlMode() ) {
            case 'preview':
                // use static class to implement the Preview pane
                myDocRepCtrlView_Preview.Init(this, kCurrDoc);
                s = myDocRepCtrlView_Preview.DrawTabBody();
                break;

            case 'add':
                s = this.drawFormAdd(kCurrDoc);
                break;

            case 'rename':
                s = this.drawFormRename( kCurrDoc );
                break;
                
            case 'versions':
                s = "<p>Todo:<br/>"
                   +`For doc ${kCurrDoc}<br/>`
                   +"Show versions of this document, allow preview, diff view, restore, and delete.<br/>";
                s += this.drawVersions(kCurrDoc);
                s += this.drawFormVersions(kCurrDoc);
                break;
                
            case 'schedule':
            	s = this.drawFormSchedule( kCurrDoc );
            	break;

            default:
                s = "Unknown control mode";
        }

        return( s );
    }

    DrawCtrlView_Attach()
    {
// move this into myDocRepCtrlView_Preview       
        if( this.GetCtrlMode() == 'preview' && sessionStorage.getItem('DocRepCtrlView_preview_mode') == 'edit' ) {
                // Now that there is a <textarea> for the editor, initialize CKEditor and attach it there
            myDocRepCtrlView_Edit.InitEditor(this);
        }
    }

	drawFormAdd( kCurrDoc ) {

		let oDoc = this.fnHandleEvent('getDocInfo', kCurrDoc);        
		let sType = '';
        if( oDoc ) {
            sType = oDoc['doctype'];
        }

        let s = `<form onsubmit='myDocRepCtrlView.addSubmit(event, "${this.oConfigEnv.q_url}")'>
					<br>	
					<div>Type: </div>
					<div class='row'> 
						<div [label]>File</div>
						<div [ctrl]>
							<input type='radio' id='add-file'  name='text-or-folder' value='text' checked/>
						</div>
					</div>
					<div class='row'> 
						<div [label]>Folder</div>
						<div [ctrl]>
							<input type='radio' id='add-folder'  name='text-or-folder' value='folder' />
						</div>
					</div>
					<br>
					`
		if (sType == 'folder') { // if current is a folder, add option to place new doc as child or sibling 
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
							<input type='text' id='add-permissions'  value='1' style='width:100%'/>
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
            sTitle = oDoc['title'];
            sPerms = oDoc['perms'];
        }
        
        let s = `<form onsubmit='myDocRepCtrlView.renameSubmit(event, "${this.oConfigEnv.q_url}")'>
        		 <div class='row'> <div [label]>Name</div>        <div [ctrl]><input type='text' id='formRename_name'  value='${sName}' style='width:100%'/></div></div>
                 <div class='row'> <div [label]>Title</div>       <div [ctrl]><input type='text' id='formRename_title' value='${sTitle}' style='width:100%'/></div></div>
                 <div class='row'> <div [label]>Permissions</div> <div [ctrl]><input type='text' id='formRename_perms' value='${sPerms}' style='width:100%'/></div></div>
                 <input type='hidden' id='drRename_kDoc' value='${kCurrDoc}'/>
                 <input type='submit' value='Change'/>
                 </form>`;
        s = s.replaceAll("[label]", "class='col-md-3'");
        s = s.replaceAll("[ctrl]",  "class='col-md-6'");
        
        return( s );
    }
    
    drawVersions( kCurrDoc )
    /**
    display a list of versions of a doc 
     */
    {
		let s = 'Version information not available';
		let rQ = SEEDJXSync( this.oConfigEnv.q_url, {qcmd: 'dr-versions', kDoc: kCurrDoc} );
		
		if(!rQ.bOk){
			return s;
		}
		else{
			s = '<div>versions: </div>'
			let versions = rQ.sOut;
			let index = Object.keys(versions).reverse();
			
			for( let i of index ){	
				//console.log(versions[i]);
				s += `
					<div class='versions-file'onclick='
						myDocRepCtrlView.updateVersionsPreview(${kCurrDoc}, ${i}, "${this.oConfigEnv.q_url}"); 
						myDocRepCtrlView.updateVersionsDiff(event, ${kCurrDoc}, ${i}, "${this.oConfigEnv.q_url}"); 
						myDocRepCtrlView.updateVersionsModify(${kCurrDoc}, ${i}, "${this.oConfigEnv.q_url}")'> 
						
						<span class='versions-number'>${versions[i].ver}</span>
						<span class='versions-title'>${versions[i].title}</span>
					</div>
					`
			}
		}
		
		s += `<br>`;
		
		return s;
		
	}
    
    drawFormVersions( kCurrDoc, versionNumber )
    /**
    form for previewing and modifying versions
     */
    {
		let s = '';
		if( versionNumber ){ // if version is selected 
		}
			
		s += `
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
		</div>
		
		`
		return s;
	}
	
	static updateVersionsPreview( kCurrDoc, versionNumber, q_url )
	/**
	update preview based on version selected 
	 */
	{
// q_url is from oCtrlView.oConfigEnv. If this method is moved to a static class the oCtrlView can be stored there the same as with Preview        
		let rQ = SEEDJXSync( q_url, {qcmd: 'dr-versions', kDoc: kCurrDoc, version: versionNumber} );
		
		if(!rQ.bOk){
			return;
		}
		else{
			$('#versions-preview').html(rQ.sOut['data_text']);
		}
	}
	
	static updateVersionsDiff( e, kCurrDoc, versionNumber, q_url )
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
// q_url is from oCtrlView.oConfigEnv. If this method is moved to a static class the oCtrlView can be stored there the same as with Preview
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
	
	static updateVersionsModify( kCurrDoc, versionNumber, q_url )
	/**
	add delete and restore button when a version is clicked
	 */
	{
		$('#versions-modify').html(`
			<button id='versions-delete' type='button' onclick='myDocRepCtrlView.versionsDeleteSubmit(${kCurrDoc}, ${versionNumber}, "${q_url}")'>delete</button>
			<button id='versions-restore' type='button' onclick='myDocRepCtrlView.versionsRestoreSubmit(${kCurrDoc}, ${versionNumber}, "${q_url}")'>restore</button>`);
	}
	static versionsDeleteSubmit( kCurrDoc, versionNumber, q_url )
	/**
	delete current version 
	 */
	{
// q_url is from oCtrlView.oConfigEnv. If this method is moved to a static class the oCtrlView can be stored there the same as with Preview
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
	
	static versionsRestoreSubmit( kCurrDoc, versionNumber, q_url )
	/**
	restore current version 
	 */
	{
		console.log("clicked on restore");
// q_url is from oCtrlView.oConfigEnv. If this method is moved to a static class the oCtrlView can be stored there the same as with Preview
		let rQ = SEEDJXSync( q_url, {qcmd: 'dr--versionsRestore', kDoc: kCurrDoc, version: versionNumber} );
		console.log('restore not implemented in database yet');
		if(!rQ.bOk){
			return;
		}
		else{
			
		}
	}
    
    drawFormSchedule( kCurrDoc )
    {
		let s = 'Schedule not available';	
		let sName = '', sType = '', sSchedule = '', raChildren = '', kDocParent = '';
		let sNameEmail = '', sTypeEmail = '', sScheduleEmail = '';
        let oDoc = this.fnHandleEvent('getDocInfo', kCurrDoc);
        
        if( oDoc ) {
            sName = oDoc['name'];
            sType = oDoc['doctype'];
            sSchedule = oDoc['schedule'];
            raChildren = oDoc['children'];
            kDocParent = oDoc['kParent'];
        }
		
		if( sType == 'page' ){ // if selected is a file 
		
			let oDocParent = this.fnHandleEvent('getDocInfo', kDocParent);
			if(oDocParent['name'].toLowerCase().includes('schedule')){
				
				s = `<form onsubmit='myDocRepCtrlView.scheduleSubmit(event, "${this.oConfigEnv.q_url}")'>
						<div class='row'> 
							<div class='col-md-3'>${sName}</div>
							<div class='col-md-6'>
								<input type='text' class='schedule-date'  value='${sSchedule}' style='width:100%'/>
							</div>
						</div>	
						
						<input type='hidden' class='drSchedule_kDoc' value='${kCurrDoc}'/>
					    <input type='submit' value='update schedule'/>
					</form>`
			}
		}
		else if( sType == 'folder' && sName.toLowerCase().includes('schedule') ) { // if slected is a folder and contains schedule in name 
			
			if( this.folderContainsEmail( kCurrDoc ) ){
				s = `<form onsubmit='myDocRepCtrlView.scheduleSubmit(event, "${this.oConfigEnv.q_url}")'>`;
			}
			else{
				s = `No emails found under folder`;
			}		
			for( let kDocEmail of raChildren ){ // loop through all children 
				let oDocEmail = this.fnHandleEvent('getDocInfo', kDocEmail);
				
				if( oDocEmail ) {
		            sNameEmail = oDocEmail['name'];
		            sTypeEmail = oDocEmail['doctype'];
		            sScheduleEmail = oDocEmail['schedule'];
        		}
				if( sTypeEmail == 'page' ){ // if child is a file 
			
					s +=   `<div class='row'> 
								<div class='col-md-3'>${sNameEmail}</div>
								<div class='col-md-6'>
									<input type='text' class='schedule-date'  value='${sScheduleEmail}' style='width:100%'/>
								</div>
							</div>	
							
							<input type='hidden' class='drSchedule_kDoc' value='${kDocEmail}'/>`
				}
			}
			if( this.folderContainsEmail( kCurrDoc ) ){
				s += `<input type='submit' value='update schedule'/></form>`;
			}		
		}
		s = s.replaceAll("[label]", "class='col-md-3'");
        s = s.replaceAll("[ctrl]",  "class='col-md-6'");
		
		return s;
		
	}
	/**
	check to see if a given folder contains emails
	 */
	folderContainsEmail( kDoc )
	{
		let oDoc = this.fnHandleEvent('getDocInfo', kDoc);
		
		if( oDoc['doctype'] == 'folder' && oDoc['name'].toLowerCase().includes('schedule') ){
			for( let kDocEmail of oDoc['children'] ){
				let oDocEmail = this.fnHandleEvent('getDocInfo', kDocEmail);			
				if ( oDocEmail['doctype'] == 'page' ){
					return true;
				}
			}
		}
		return false;
	}
	
	static addSubmit( e, q_url ) 
	{
		e.preventDefault();
		var rQ;
		let kDoc = $('#drAdd_kDoc').val();
		let position = $('input[name=child-or-sibling]:checked').val()
		let type = $('input[name=text-or-folder]:checked').val()
		let name = $('#add-name').val();
		let title = $('#add-title').val();
		let permissions = $('#add-permissions').val();

		if( !name || !permissions || !kDoc) {
			return;
		}
	
		if ( !position ) { // if no position is selected, or position does not exist, default sibling 
			position == "sibling";
		}

// q_url is from oCtrlView.oConfigEnv. If this method is moved to a static class the oCtrlView can be stored there the same as with Preview
		if ( position == "child" ) {
			rQ = SEEDJXSync(q_url, { qcmd: 'dr--add', kDoc: kDoc, dr_posUnder: kDoc, type: type, dr_name: name, dr_title: title, dr_permclass: permissions });
		}
		else {
			rQ = SEEDJXSync(q_url, { qcmd: 'dr--add', kDoc: kDoc, dr_posAfter: kDoc, type: type, dr_name: name, dr_title: title, dr_permclass: permissions });
		}
	
		if (!rQ.bOk) {
			console.log("error add");
		}
		else {
			// update tree with new folder/file
			this.addUpdateTree();
		}
	}
	
	static renameSubmit( e, q_url ) 
	{
		e.preventDefault();;
		let kDoc = $('#drRename_kDoc').val();
		let name = $('#formRename_name').val();
		let title = $('#formRename_title').val();
		let permissions = $('#formRename_perms').val();
	
// q_url is from oCtrlView.oConfigEnv. If this method is moved to a static class the oCtrlView can be stored there the same as with Preview
		let rQ = SEEDJXSync( q_url, { qcmd: 'dr--rename', kDoc: kDoc, name: name, title: title, permclass: permissions });
	
		if ( !rQ.bOk ) {
			console.log("error rename");
		}
		else {
			this.renameUpdateTree(kDoc, name);
		}
	}
	
	static scheduleSubmit( e, q_url )
	{
		e.preventDefault();
		let allKDoc = $('.drSchedule_kDoc');
		let allSchedule = $('.schedule-date');

		for(let i = 0; i < allKDoc.length; i++){
			
			let kDoc = allKDoc[i].value;
			let schedule = allSchedule[i].value;
			
// q_url is from oCtrlView.oConfigEnv. If this method is moved to a static class the oCtrlView can be stored there the same as with Preview
			let rQ = SEEDJXSync( q_url, { qcmd: 'dr--schedule', kDoc: kDoc, schedule: schedule });
		console.log(rQ);
			if ( !rQ.bOk ) {
				console.log("error schedule");
			}
			else {
				// console.log("ok schedule")
			}
			
		}
	}
	
	/*
	update tree after rename 
	*/
	static renameUpdateTree( kDoc, name ) 
	{
		let doc = $(`.DocRepTree_title[data-kDoc=${kDoc}]`)[0];
		let child = doc.children[1].nextSibling;
		child.nodeValue = '\u00A0' + name; // \u00a0 is same as &nbsp; in html
		
		// TODO: redraw tree with update map instead 
	}
	
	/*
	update tree after adding new doc 
	for now, just reload page 
	*/
	static addUpdateTree() 
	{
		location.reload();
		// TODO: 
		// call ajax to update map 
		// redraw tree with updated map 
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
    
    static DrawTabBody()
    {
        let s = "";
        let rQ = null;
        let m = this.#getMode();
        
        let oDoc = this.oCtrlView.fnHandleEvent('getDocInfo', this.kCurrDoc);
        if( !oDoc || oDoc.doctype != 'page' ) return( "" );
        
        switch( m ) {
            case 'preview':
                if( (rQ = SEEDJXSync( this.oCtrlView.oConfigEnv.q_url, {qcmd: 'dr-preview', kDoc: this.kCurrDoc} )) ) {
                    s = rQ.bOk ? rQ.sOut : `Cannot get preview for document ${this.kCurrDoc}`;
                }
                break;
            case 'source':
                rQ = SEEDJXSync( this.oCtrlView.oConfigEnv.q_url, {qcmd: 'dr-preview', kDoc: this.kCurrDoc} );
                if( rQ.bOk ) {
                    s = "<div style='font-family:monospace'>" 
                      + rQ.sOut.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') 
                      + "</div>";
                } else {
                    s = `Cannot get preview for document ${this.kCurrDoc}`;
                }
                break;
            case 'edit':
                rQ = SEEDJXSync( this.oCtrlView.oConfigEnv.q_url, {qcmd: 'dr-preview', kDoc: this.kCurrDoc} );
                if( rQ.bOk ) {
                    s = myDocRepCtrlView_Edit.DrawEditor(this.kCurrDoc, rQ.sOut);
                        
                }
                break;
        }
        
        s = `<div>
             <select id='drCtrlview-preview-state-select' onchange='myDocRepCtrlView_Preview.Change(this.value)'>
                 <option value='preview'` +(m=='preview' ? ' selected' :'')+ `>Preview</option>
                 <option value='source'`  +(m=='source'  ? ' selected' :'')+ `>Source</option>
                 <option value='edit'`    +(m=='edit'    ? ' selected' :'')+ `>Edit</option>
             </select>
             <div style='border:1px solid #aaa;padding:20px;margin-top:10px'>${s}</div>
             </div>`;
        
        return( s );
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
                                 {qcmd:'dr--update', kDoc:kDoc, src:'TEXT', 
                                                         p_text:text, 
                                                         p_bNewVersion:$('#dr_Edit_newversion').is(':checked') } );
            // console.log(rQ);
            $('#drEdit_notice').html( rQ.bOk ? "Update successful" : "Update failed" );
        }
        // console.log(kDoc + "kdoc");
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
                        { mapDocs: mymapDocs,
                          fnHandleEvent: this.HandleRequest.bind(this) } );    // tell the object how to send events here

        this.oTree = new myDocRepTree(
                        { mapDocs: mymapDocs,
                          dirIcons: oConfig.env.seedw_url+'img/icons/',
                          fnHandleEvent: this.HandleRequest.bind(this) } );    // tell the object how to send events here

        this.oCtrlView = new myDocRepCtrlView(
                        { fnHandleEvent: this.HandleRequest.bind(this),        // tell the object how to send events here
                          env: oConfig.env                                     // tell the ctrlview how to interact with the application environment 
                        } );

        this.kCurrDoc = 0;
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
        q_url:        'jx.php'                // url to server that handles QServerDocRep commands
    },
    docsPreloaded: null                       // array of docs pre-loaded for DocRepTree
};

$(document).ready( function () {
    (new DocRepApp02( oDocRepApp02_Config )).InitUI();
});
