/* Console02.js
 *
 * Copyright (c) 2018 Seeds of Diversity Canada
 * 
 * UI support for Console
 */

class ConsolePage
{
    constructor( config )
    {
        this.cpConfig = config;
        this.cpVars = {};
        this.cpPage = 'Start';

        // Run fnPre for initial page
        this.cpConfig.pages[this.cpPage]['fnPre']();

        // Show the initial page
        this.ShowPage( this.cpPage );
    }
    
    GetVar( k )      { return( this.cpVars[k] ); }
    SetVar( k, v )   { this.cpVars[k] = v; }

    GetVarInt( k )   { return( parseInt(this.GetVar(k)) || 0 ); }
    GetVarFloat( k ) { return( parseFloat(this.GetVar(k)) || 0.0 ); }
    
    FormValInt( k )   { return( parseInt(this.FormVal(k)) || 0 ); }
    FormValFloat( k ) { return( parseFloat(this.FormVal(k)) || 0.0 ); }
    
    FormVal( k )
    /***********
        Get the current value of the input k. 
        Use this in fnPost instead of GetVar because values in forms have not been stored in cpVars yet.
     */
    {
        return( this._formVal( this.cpPage, k ) );
    }
    
    _formVal( p, k )
    /**************
     */
    {
        return( $('#consolePage'+p+' .cpvar_'+k).val() );
    }

    _formValSet( p, k, v )
    /*********************
     */
    {
        let e = $('#consolePage'+p+' .cpvar_'+k);
        if( e.length ) {
            if( $.inArray( e.prop('tagName'), ['INPUT','SELECT','TEXTAREA'] ) != -1 ) {
                e.val( v );
            } else {
                e.html( v );
            }
        }
    }

    FormValSet( k, v )
    /*****************
        Set a value in a named form element. 
        Use this in fnPre - LoadVars happens before fnPre.
     */
    {
        this._formValSet( this.cpPage, k, v );
    }
    
    LoadVars( p )
    /************
        Populate variable values in all .cpvar_* elements in the given page
     */
    {
        for( let k in this.cpVars ) {
            this._formValSet( p, k, this.GetVar(k) );
        }
    }
    
    StoreVars( p )
    /*************
        Find all cpvar_* input values in the given page and copy them to cpVars
     */
    {
        let oCP = this;    // because 'this' means something else within the closure
        let page = $('#consolePage'+p);
        page.find('select, textarea, input').each( function() {
            let clist = this.className.split(' ');
            for( let i in clist ) {
                let c = clist[i];
                if( c.substring(0,6) == 'cpvar_' ) {
                    c = c.substring(6);
                    oCP.SetVar( c, $(this).val() );
                }
            }
        });
    }
    
    ShowPage( p )
    /************
        Show page p and hide all the others
     */
    {
        for( let i in this.cpConfig.pages ) {
            if( i == p ) {
                $('#consolePage'+i).show();
            } else {
                $('#consolePage'+i).hide();
            }
        }
    }

    PageSubmit()
    /***********
        When a submit button is clicked on a page, capture form data, validate it, and decide which page should become current.
     */
    {
        // Run fnPost for the submitted page. The return value is the page that should become current.
        let nextPage = this.cpConfig.pages[this.cpPage]['fnPost']();

        if( nextPage == '' ) {
            // Stay on the same page and don't load vars
        } else {
            // switch to the given next page, after possibly storing the vars of the submitted page
            if( this.cpConfig.pages[this.cpPage]['model'] == 'LoadStore' ) {
                this.StoreVars(this.cpPage);
            }

            this.cpPage = nextPage;

            if( this.cpConfig.pages[this.cpPage]['model'] == 'LoadStore' ) {
                this.LoadVars(this.cpPage);
            }
            // Run fnPre for the new current page
            this.cpConfig.pages[this.cpPage]['fnPre']();
            
            // Show the current page
            this.ShowPage( this.cpPage );
        }
    }
    
    Ready()
    {
        let oCP = this;    // because 'this' means something else within the closure 
        $(document).ready( function () { $('.consolePage form').submit( function (e) { e.preventDefault(); oCP.PageSubmit(); } ); });;
    }
}

