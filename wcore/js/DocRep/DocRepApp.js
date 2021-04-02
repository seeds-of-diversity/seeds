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
    }

    DrawTree( kRoot )
    /****************
        Draw the given root doc and its descendants
     */
    {
        let s = "";

        let oDoc = this.getDocObj(kRoot);
        if( oDoc ) {
            s += this.drawDoc(oDoc)
                +this.DrawChildren( oDoc );
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
            s += "<div style='margin-left:20px'>";
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

        let triangle =
            `<div style='position:relative;display:inline-block;margin:0 3px'>
                 <svg width='10' height='10' viewBox='0 0 20 20'>
                 <polygon points='4,0 16,10 4,20' style='fill:blue;stroke:blue;stroke-width:1'></polygon>
                 Sorry, your browser does not support inline SVG.
                 </svg>
             </div>`;
        let noTriangle = "<div style='width:16px;height:10px;display:inline-block'>&nbsp;</div>";

        s = `<div class='DocRepTree_title' data-kDoc='${oDoc.k}'>`
           +(oDoc.doctype=='folder' ? triangle : noTriangle)
           +(oDoc.doctype=='folder' ? `<img src='${this.dirIcons}folder.png' width='20'>`
                                    : `<img src='${this.dirIcons}text.png' width='20'>`)
           +`&nbsp;${oDoc.name}</div>`;

/*
<div class="DocRepTree_title "><a href="/~bob/seeds/seedapp/doc/app_docmanager.php?k=1">


<img src="../../wcore/img/icons/folder.png" width="20">&nbsp;<a href="?k=1"><nobr>folder1</nobr></a></div>
*/

        return( s );
    }

    InitUI ()
    {
        $('.DocRepTree_title').click( function () {
            $('.DocRepTree_title').removeClass('DocRepTree_titleSelected');   //$('.DocRepTree_title').css('font-weight','normal');
            $(this).addClass('DocRepTree_titleSelected');                     //$(this).css('font-weight','bold');
        });
    }
}
