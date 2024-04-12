/* DocRepApp.js
 *
 * Copyright (c) 2021-2023 Seeds of Diversity Canada
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
        kDoc = Number(kDoc);
        
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
    
    GetDocObj( kDoc )
    {
        let doc = this.GetDocInfo(kDoc);
        let oDoc = new DocRepDoc(kDoc);
// DocRepDoc should have a method for fetching metadata when it instantiates
        oDoc.SetMetadata( {name: doc.name, title: doc.title, permclass: doc.permclass} );
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

class DocRepDoc
{
    constructor( kDoc )
    {
        this.kDoc = kDoc;
        
// must have a method that fetches or initializes the metadata
    }    

    Key()       { return(this.kDoc); }
    Name()      { return(this.name); }
    Title()     { return(this.title); }
    Permclass() { return(this.permclass); }
    
    /**
        The name not including a leading path
     */
    BaseName()
    {
        let name = this.Name();
        let basename = "";
        
        if( name ) {
            let i = name.lastIndexOf('/');
            
            basename = (i == -1) ? name         // name has no named parent (basename is full name)
                                 : name.substring(i+1);
        }
        
        return( basename );
    }

    SetMetadata( oM )
    {
        this.name = oM.name;
        this.title = oM.title;
        this.permclass = oM.permclass;    
    }
    
}
