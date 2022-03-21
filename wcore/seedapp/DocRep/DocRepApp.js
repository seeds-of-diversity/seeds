/* DocRepApp.js
 *
 * Copyright (c) 2021-2022 Seeds of Diversity Canada
 *
 * UI widgets for managing DocRep documents.
 *
 * DocRepCache    - storage for docrep doc info, accessible by widgets 
 * DocRepTree     - a base document-tree widget that needs a subclass to be able to get doc info and store its state
 * DocRepCtrlView - a base control-view widget that needs a subclass to implement its contents (e.g. tabs, forms, controls )   
 */

class DocRepCache
{
    constructor( oConfig )
    {
        this.mapDocs = oConfig.mapDocs;     // a Map() of docrep docs
    }
    
    GetDocInfo( kDoc, bInternalRecurse = false )
    {
        let oDoc = null;

        if( this.mapDocs.has(kDoc) ) {
            oDoc = this.mapDocs.get(kDoc);
        } else if( !bInternalRecurse ) {
            // if not found on first time through, try to fetch it
            this.FetchDoc( kDoc );
            oDoc = this.GetDocInfo( kDoc, true );
        } else {
            // not found after fetching
            console.log(kDoc+" not found");
        }
        return( oDoc );
    }
    
    UpdateDocInfo( p )
    {
        let kDoc = parseInt(p.kDoc);    // map requires this to be integer type (uses strict key matching)
        let ok = false;
        
        if( kDoc && this.mapDocs.has(kDoc) ) {
            let oDoc = this.mapDocs.get(kDoc);
            oDoc.name = p.name;
            oDoc.title = p.title;
            oDoc.permclass = p.permclass;
            this.mapDocs.set(kDoc, oDoc);
            ok = true;
        }
        return( ok );
    }
    
    FetchDoc( kDoc )
    {
        // override to add doc(s) to mapDocs
    }
}


class DocRepTree
{
    constructor( oConfig )
    {
        this.fnHandleEvent = oConfig.fnHandleEvent;
        this.mapDocs = oConfig.mapDocs;
        this.dirIcons = oConfig.dirIcons;
        this.speed = 0;
    }

    DrawTree( kRoot )
    /****************
        Draw the given root doc and its descendants
     */
    {
        let s = "";

        let oDoc = this.getDocInfo(kRoot);
        if( oDoc ) {
            s += `<div class='DocRepTree_doc' data-kdoc='${kRoot}'>`
                +(kRoot ? this.drawDoc(oDoc) : "")      // kRoot==0 is a non-doc that contains the root forest
                +this.DrawChildren( oDoc )
                +"</div>";
        }
        return( s );
    }

    DrawForestChildren( kRoot )
    /**************************
        Draw the given root doc's children and their descendants
     */
    {
        let s = "";

        let oDoc = this.getDocInfo(kRoot);
        if( oDoc ) {
            s += this.DrawChildren( oDoc );
        }
        return( s );
    }

    DrawChildren( oDoc )
    {
        let s = "";

        if( oDoc.children.length > 0 ) {
            s += `<div class='DocRepTree_level' data-under-kdoc='${oDoc.k}'>`;
            let saveThis = this;
            oDoc.children.forEach( function (v,k,ra) { s += saveThis.DrawTree(v); } );
            s += "</div>";
        }

        return( s );
    }

    getDocInfo( kDoc, bRecurse = false )
    {
        // get the document info from the app (it probably has a DocRepCache)
        return( this.fnHandleEvent('getDocInfo', kDoc) );
    }

    drawDoc( oDoc )
    {
        let s = "";

        s = `<div class='DocRepTree_title' data-kdoc='${oDoc.k}'>
                 <div class='DocRepTree_titleFolderTriangle' style='width:10px;display:inline-block;margin:0 3px'>`
                +this.drawFolderTriangle( oDoc )
                +`</div>`
                +(oDoc.doctype=='folder' ? `<img src='${this.dirIcons}folder.png' width='20'>`
                                         : `<img src='${this.dirIcons}text.png' width='20'>`)
                +`&nbsp;${oDoc.name}
             </div>`;

/*
<div class="DocRepTree_title "><a href="/~bob/seeds/seedapp/doc/app_docmanager.php?k=1">


<img src="../../wcore/img/icons/folder.png" width="20">&nbsp;<a href="?k=1"><nobr>folder1</nobr></a></div>
*/

        return( s );
    }

    drawFolderTriangle( oDoc )
    {

        let triangleLeft =
            `<svg width='10' height='10' viewBox='0 0 20 20'>
                 <polygon points='4,0 16,10 4,20' style='fill:blue;stroke:blue;stroke-width:1'></polygon>
                 Sorry, your browser does not support inline SVG.
                 </svg>`;
        let triangleDown =
            `<svg width='10' height='10' viewBox='0 0 20 20'>
                 <polygon points='10,16 0,4 20,4' style='fill:blue;stroke:blue;stroke-width:1'></polygon>
                 Sorry, your browser does not support inline SVG.
                 </svg>`;
        let triangleNone = "&nbsp;";

        let t = triangleNone;
        if( oDoc.doctype == 'folder' ) {
            t = ( sessionStorage.getItem( 'DocRepTree_'+oDoc.k ) == 1 ) ? triangleDown : triangleLeft;
        }
        return( t );
    }

    HandleRequest( eRequest, p )
    {
        // override to respond to notifications
    }
    LevelOpenGet( pDoc )
    {
        // override to see whether a level is open (shown) or closed (hidden)
        return( true );
    }
    LevelOpenSet( pDoc, bOpen )
    {
        // override to store whether a level is open (shown) or closed (hidden)
    }

    FolderOpenClose( pDoc, bOpen )
    {
        let oDRDoc = this.getDocAndJDoc(pDoc);
        
        if( bOpen ) {
            oDRDoc.jDoc.children('.DocRepTree_level').show(this.speed);
            this.LevelOpenSet( oDRDoc, 1 );
        } else {
            oDRDoc.jDoc.children('.DocRepTree_level').hide(this.speed);
            this.LevelOpenSet( oDRDoc, 0 );
        }
        
        if( oDRDoc.oDoc ) {
            let t = this.drawFolderTriangle( oDRDoc.oDoc );
            $(`.DocRepTree_title[data-kdoc=${oDRDoc.kDoc}] .DocRepTree_titleFolderTriangle`).html(t);
        }
        
    }    
    levelShow( pDoc )
    {
        this.FolderOpenClose( pDoc, true );
    }
    levelHide( pDoc )
    {
        this.FolderOpenClose( pDoc, false );
    }

    getDocAndJDoc( p )
    /*****************
        Given a kDoc, a jDoc (jquery object for .DocRepTree_doc), or an oDRDoc
        return an oDRDoc
     */
    {
        let oDRDoc = { kDoc: 0, oDoc: null, jDoc: null };
        
        if( typeof p === 'object' && 'kDoc' in p ) {
            // assume p is already a complete oDRDoc
            oDRDoc = p;
        } else if ( typeof p === 'object' ) {
            // assume it is a $(.DocRepTree_doc)
            oDRDoc.kDoc = parseInt(p.attr('data-kdoc'));
            oDRDoc.oDoc = this.getDocInfo(oDRDoc.kDoc);
            oDRDoc.jDoc = p;
        } else {
            // assume it is a kDoc
            oDRDoc.kDoc = p;
            oDRDoc.oDoc = this.getDocInfo(oDRDoc.kDoc);
            oDRDoc.jDoc = $(`.DocRepTree_doc[data-kdoc=${p}]`);
        }
        
        return( oDRDoc );
    }

    InitUI()
    {
        let saveThis = this;
        
        $('.DocRepTree_title').click( function () {
            $('.DocRepTree_title').removeClass('DocRepTree_titleSelected');
            $(this).addClass('DocRepTree_titleSelected');

// This uses the derived class's event handler to store the new kDoc.
// That's a weird way to it, because no other classes call their own event handlers.
// Also GetCurrDoc() is in the derived class but not the base class, but it's used below.
            saveThis.HandleRequest( 'docSelected', $(this).attr('data-kdoc') );

            // show/hide any children by toggling the Level contained within the same Doc (there should be zero or one Level sibling)
            $(this).siblings('.DocRepTree_level').each( function () { 
                    let jDoc = $(this).closest('.DocRepTree_doc');
                    saveThis.LevelOpenGet(jDoc) ? saveThis.levelHide(jDoc) : saveThis.levelShow(jDoc); 
            });
        });
        
        // open/close each level based on stored status 
        $('.DocRepTree_level').each( function () {
            let pDoc = saveThis.getDocAndJDoc( parseInt($(this).attr('data-under-kdoc')) ); //$(this).closest('.DocRepTree_doc'));
            saveThis.FolderOpenClose( pDoc, saveThis.LevelOpenGet(pDoc) ); 
        });
        
        // highlight the current doc
// GetCurrDoc doesn't exist in the base class. See how ctrlMode is stored in CtrlView.        
        let currDoc = saveThis.GetCurrDoc();
        if( currDoc ) {
            $(`.DocRepTree_title[data-kdoc=${currDoc}]`).addClass('DocRepTree_titleSelected');
        }
        
        // but always show the top-level forest (node 0 isn't real and can't be closed)
        saveThis.FolderOpenClose( 0, true );
        
        // after init, animate opening and closing
        this.speed = 200;
    }
}


/* TODO: this is a generic tabbed CtrlView widget if you rename the ids. Put it in a generic Console class or derive this from one.
 */

class DocRepCtrlView
{
    constructor( oConfig )
    {
        this.fnHandleEvent = oConfig.fnHandleEvent;     // use this to communicate with widgets/app
        this.oConfig = { ui: oConfig.ui };
        
        // initialize ctrlMode then use derived method (or base method if no subclass)
        this.ctrlMode = "";
        this.ctrlMode = this.GetCtrlMode();

        /* Draw the tabs.
         * defTabs is {tabname:tablabel, ...}
         * If ctrlMode is not one of the tabnames, it is set to the first one
         */
        let sTabs = "";
        let bFoundTabname = false;
        for( const tabname in oConfig.defTabs ) {
            sTabs += `<div class='tab' data-tabname='${tabname}'>${oConfig.defTabs[tabname]}</div>`;
            if( tabname == this.ctrlMode ) bFoundTabname = true;
        }
                                       
        $('#docrepctrlview').html( `<div id='docrepctrlview_tabs'>${sTabs}</div>
                                    <div id='docrepctrlview_body'></div>` );

        // do this after setting the html because it also highlights the current tab
        if( !bFoundTabname ) {
            // get the first key in the object. ECMAScript doesn't guarantee this but all major browsers do it.
            this.SetCtrlMode( Object.keys(oConfig.defTabs)[0] ); 
        } else {
            this.SetCtrlMode(this.ctrlMode);
        }

        /* Bind the tabs to a function that changes the ctrlMode and redraws the CtrlView 
         */
        let saveThis = this;
        let id = 'docrepctrlview_body';
        $('#docrepctrlview_tabs .tab').click( function() {
            // store the new tab and redraw tabs
            saveThis.SetCtrlMode( $(this).attr('data-tabname') );
            // draw the form for the new tab            
            saveThis.DrawCtrlView();
        });
    }

    // override these to implement persistent state storage
    GetCtrlMode()    { return( this.ctrlMode ); }
    SetCtrlMode( m )
    {
        this.ctrlMode = m;
        
        // highlight the current tab
        $("#docrepctrlview_tabs .tab").removeClass("active-tab"); 
        $(`#docrepctrlview_tabs .tab[data-tabname=${m}]`).addClass("active-tab");
    }

    DrawCtrlView()
    /*************
        Draw the ctrlview_body for the current tab & doc, and attach event listeners to controls as needed
     */
    {
        let kCurrDoc = this.fnHandleEvent('getKDocCurr'); 
        let parentID = 'docrepctrlview_body';

        if( kCurrDoc ) {
			$(`#${parentID}`).empty();
            this.DrawCtrlView_Render(parentID, kCurrDoc );
            this.DrawCtrlView_Attach();
        }
    }

    DrawCtrlView_Render( kCurrDoc, parentID )
    /******************************
        Returns a jquery object containing the content of the ctrlview_body
     */
    {
        // override to draw the ctrlview_body for the current tab
        return( null );
    }
    
    DrawCtrlView_Attach()
    {
        // override to attach event listeners to the html from DrawCtrlView_Render, which is now in the DOM
    }

    HandleRequest( eRequest, p )
    {
        // override to respond to notifications/requests
    }
}
