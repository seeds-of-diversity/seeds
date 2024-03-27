/* DocRepApp_Tree.js
 *
 * Copyright (c) 2021-2024 Seeds of Diversity Canada
 *
 * UI widget for managing DocRep folder/file tree.
 */

class DocRepTree
{
    constructor( oConfig )
    {
        this.fnHandleEvent = oConfig.fnHandleEvent;
        this.idTreeContainer = oConfig.idTreeContainer;
        this.mapDocs = oConfig.mapDocs;
        this.dirIcons = oConfig.dirIcons;
        this.speed = 0;
    }

    DrawTree( kRoot )
    /****************
        Draw the tree rooted at kRoot, insert it at #idTreeContainer, and set its state and listeners
     */
    {
        let s = this.DrawTree_Render(kRoot);
        $(this.idTreeContainer).html(s);     // put the tree in idTreeContainer
        this.DrawTree_Attach();                     // set state and listeners
    }

    DrawTree_Render( kRoot )
    /****************
        Draw the html tree content rooted at the given doc
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

    DrawTree_Attach()
    /**********
        After the tree is drawn into the dom, call this to set state and listeners
     */
    {
        let saveThis = this;

        $(this.idTreeContainer +' .DocRepTree_title').click( function () {
            $(saveThis.idTreeContainer +' .DocRepTree_title').removeClass('DocRepTree_titleSelected');
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
        $(this.idTreeContainer +' .DocRepTree_level').each( function () {
            let pDoc = saveThis.getDocAndJDoc( parseInt($(this).attr('data-under-kdoc')) ); //$(this).closest('.DocRepTree_doc'));
            saveThis.FolderOpenClose( pDoc, saveThis.LevelOpenGet(pDoc) ); 
        });
        
        // highlight the current doc
// GetCurrDoc doesn't exist in the base class. See how ctrlMode is stored in CtrlView.        
        let currDoc = saveThis.GetCurrDoc();

        if( currDoc ) {
            $(this.idTreeContainer +` .DocRepTree_title[data-kdoc=${currDoc}]`).addClass('DocRepTree_titleSelected');
        }

        // but always show the top-level forest (node 0 isn't real and can't be closed)
        saveThis.FolderOpenClose( 0, true );

        // after init, animate opening and closing
        this.speed = 200;
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
            oDoc.children.forEach( function (v,k,ra) { s += saveThis.DrawTree_Render(v); } );
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

        let label = "Untitled";
        if( oDoc.title ) {
            label = oDoc.title + (oDoc.name ? ("&nbsp;&nbsp;<span style='font-size:x-small;color:#777;font-weight:normal'>("+this.docBasename(oDoc)+")</span>") : "");
        } else if( oDoc.name ) {
            label = oDoc.name;
        }

        s = `<div class='DocRepTree_title' data-kdoc='${oDoc.k}'>
                 <div class='DocRepTree_titleFolderTriangle' style='width:10px;display:inline-block;margin:0 3px'>`
                +this.drawFolderTriangle( oDoc )
                +`</div>`
                +(oDoc.doctype=='folder' ? `<img src='${this.dirIcons}folder.png' width='20'>`
                                         : `<img src='${this.dirIcons}text.png' width='20'>`)
                +`&nbsp;${label}
             </div>`;

/*
<div class="DocRepTree_title "><a href="/~bob/seeds/seedapp/doc/app_docmanager.php?k=1">


<img src="../../wcore/img/icons/folder.png" width="20">&nbsp;<a href="?k=1"><nobr>folder1</nobr></a></div>
*/

        return( s );
    }

// put this in a Doc object 
    docBasename( oDoc )
    {
        let basename = "";
        
        if( oDoc.name ) {
            let i = oDoc.name.lastIndexOf('/');
            
            if( i == -1 ) {
                // name has no named parent (basename is full name)
                basename = oDoc.name;
            } else {
                basename = oDoc.name.substring(i+1);
            }
        }
        
        return( basename );
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
}
