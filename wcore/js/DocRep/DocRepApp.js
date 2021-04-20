/* DocRepApp.js
 *
 * Copyright (c) 2021 Seeds of Diversity Canada
 *
 * UI for managing DocRep documents.
 */

class DocRepTree
{
    constructor( oConfig )
    {
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

        let oDoc = this.getDocObj(kRoot);
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

        let oDoc = this.getDocObj(kRoot);
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

    FetchDoc( kDoc )
    {
        // override to add doc(s) to mapDocs
    }

    getDocObj( kDoc, bRecurse = false )
    {
        let oDoc = null;

        if( this.mapDocs.has(kDoc) ) {
            oDoc = this.mapDocs.get(kDoc);
        } else if( !bRecurse ) {
            // if not found on first time through, try to fetch it
            this.FetchDoc( kDoc );
            oDoc = this.getDocObj( kDoc, true );
        } else {
            // not found after fetching
            console.log(kDoc+" not found");
        }
        return( oDoc );
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

    HandleEvent( eEvent, p )
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
            oDRDoc.oDoc = this.getDocObj(oDRDoc.kDoc);
            oDRDoc.jDoc = p;
        } else {
            // assume it is a kDoc
            oDRDoc.kDoc = p;
            oDRDoc.oDoc = this.getDocObj(oDRDoc.kDoc);
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
            saveThis.HandleEvent( 'docSelected', $(this).attr('data-kdoc') );    // tell the derived class that a title was clicked

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
