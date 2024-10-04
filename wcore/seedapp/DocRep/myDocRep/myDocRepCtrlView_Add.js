/* myDocRepCtrlView_Add.js
 *
 * Copyright (c) 2021-2024 Seeds of Diversity Canada
 *
 * Implements Add CtrlView.
 */

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
                permclass = oDoc.permclass;
            } else {
                // kDoc is the sibling so use the parent's permclass
                if( oDoc.kParent ) {
                    oDoc = this.oCtrlView.fnHandleEvent('getDocInfo', oDoc.kParent);
                    permclass = oDoc.permclass;
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
//  this.UpdateTree();
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
