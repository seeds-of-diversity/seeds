/* myDocRepCtrlView_Rename.js
 *
 * Copyright (c) 2021-2024 Seeds of Diversity Canada
 *
 * Implements Rename CtrlView.
 */

class myDocRepCtrlView_Rename
{
    constructor( oCtrlView )
    {
        this.oCtrlView = oCtrlView;
    }
    
    DrawTabBody( oParms )
    {
        let sName = "", sTitle = "", sPermclass = "";
        let oDoc = oParms.oDoc;
        
        if( oDoc ) {
            sName = oDoc.BaseName();
            sTitle = oDoc.Title();
            sPermclass = oDoc.Permclass();
        }
        const colLabel = 'col-md-3';
        const colCtrl = 'col-md-6';

        oParms.jCtrlViewBody.html(`
            <form id='drRename_form'> `// onsubmit='myDocRepCtrlView_Rename.Submit(event, "")'>
            +`  <div class='row'> 
                    <div class='${colLabel}'>Name</div>        
                    <div class='${colCtrl}'>
                        <input type='text' id='formRename_name'  value='${sName}' style='width:100%'/>
                    </div>
                </div>
                <div class='row'> 
                    <div class=${colLabel}>Title</div>       
                    <div class=${colCtrl}>
                        <input type='text' id='formRename_title' value='${sTitle}' style='width:100%'/>
                    </div>
                </div>`
              +(this.oCtrlView.oConfigUI.eUILevel>=2 ?
                    `<div class='row'> 
                        <div class=${colLabel}>Permissions</div>
                        <div class=${colCtrl}>
                            <input type='text' id='formRename_perms' value='${sPermclass}' style='width:100%'/>
                        </div>
                    </div>` : '')
              +`<br/>
                <input type='submit' value='Change'/>
            </form>`);

        let saveThis = this;
        $('#drRename_form').submit( function(e) {  
            e.preventDefault();
            saveThis.Submit(oParms)
        });

    }
   
   Submit(oParms) 
    {
        if( !oParms.oDoc.Key() ) {
            console.log("error rename has no kDoc");
            return;
        }

        let name = $('#formRename_name').val();
        let title = $('#formRename_title').val();
        let permclass = $('#formRename_perms').val();

        let rQ = SEEDJXSync( this.oCtrlView.oConfigEnv.q_url, 
                             { qcmd: 'dr--rename', 
                               kDoc: oParms.oDoc.Key(), 
                               name: name, 
                               title: title, 
                               permclass: permclass } );
        if ( rQ.bOk ) {
            this.oCtrlView.HandleRequest('docTreeChange', oParms.oDoc.Key());
        } else {
            console.log("error rename");
        }
    }
}
