/* DocRepApp.js
 *
 * Copyright (c) 2021 Seeds of Diversity Canada
 *
 * UI for managing DocRep documents.
 */

class DocRepTree
{
    constructor( raConfig )
    {
        this.mapDocs = raConfig.mapDocs;
        this.dirIcons = raConfig.dirIcons;
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
            s += "<div class='DocRepTree_doc'>"
                +this.drawDoc(oDoc)
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

        let triangleLeft =
            `<div style='position:relative;display:inline-block;margin:0 3px'>
                 <svg width='10' height='10' viewBox='0 0 20 20'>
                 <polygon points='4,0 16,10 4,20' style='fill:blue;stroke:blue;stroke-width:1'></polygon>
                 Sorry, your browser does not support inline SVG.
                 </svg>
             </div>`;
        let triangleDown =
            `<div style='position:relative;display:inline-block;margin:0 3px'>
                 <svg width='10' height='10' viewBox='0 0 20 20'>
                 <polygon points='10,16 0,4 20,4' style='fill:blue;stroke:blue;stroke-width:1'></polygon>
                 Sorry, your browser does not support inline SVG.
                 </svg>
             </div>`;
        let triangleNone = "<div style='width:16px;height:10px;display:inline-block'>&nbsp;</div>";

        let t = triangleNone;
        if( oDoc.doctype == 'folder' ) {
            t = ( sessionStorage.getItem( 'DocRepTree_'+oDoc.k ) == 1 ) ? triangleDown : triangleLeft;
        }

        s = `<div class='DocRepTree_title' data-kdoc='${oDoc.k}'>`
           +t //+(oDoc.doctype=='folder' ? triangleLeft : triangleNone)
           +(oDoc.doctype=='folder' ? `<img src='${this.dirIcons}folder.png' width='20'>`
                                    : `<img src='${this.dirIcons}text.png' width='20'>`)
           +`&nbsp;${oDoc.name}</div>`;

/*
<div class="DocRepTree_title "><a href="/~bob/seeds/seedapp/doc/app_docmanager.php?k=1">


<img src="../../wcore/img/icons/folder.png" width="20">&nbsp;<a href="?k=1"><nobr>folder1</nobr></a></div>
*/

        return( s );
    }

    LevelOpenGet( jLevel )
    {
        // override to see whether a level is open (shown) or closed (hidden)
        return( true );
    }
    LevelOpenSet( jLevel, bOpen )
    {
        // override to store whether a level is open (shown) or closed (hidden)
    }
    
    levelShow( jLevel )
    {
        jLevel.show(this.speed);
        this.LevelOpenSet( jLevel, 1 );
    }
    levelHide( jLevel )
    {
        jLevel.hide(this.speed);
        this.LevelOpenSet( jLevel, 0 );
    }

    InitUI()
    {
        let saveThis = this;
        
        $('.DocRepTree_title').click( function () {
            $('.DocRepTree_title').removeClass('DocRepTree_titleSelected');
            $(this).addClass('DocRepTree_titleSelected');

            // show/hide any children by toggling the Level contained within the same Doc
            $(this).closest('.DocRepTree_doc').find('.DocRepTree_level').each( 
                    function () { saveThis.LevelOpenGet($(this)) ? saveThis.levelHide($(this)) : saveThis.levelShow($(this)); });
        });
        
        // open/close each level based on stored status 
        $('.DocRepTree_level').each( function () { 
            saveThis.LevelOpenGet($(this)) ? saveThis.levelShow($(this)) : saveThis.levelHide($(this)); 
        });
        
        // but always open the top-level forest
        $('.DocRepTree_level[data-under-kdoc=0]').each( function() { saveThis.levelShow($(this)); } );
        
        // after init, animate opening and closing
        this.speed = 200;
    }
}
