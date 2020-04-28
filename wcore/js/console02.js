/* Console02.js
 *
 * Copyright (c) 2018-2019 Seeds of Diversity Canada
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


class ConsoleEditList
/********************
    Show a list of items and allow them to expand to forms one at a time.

    Usage: Make a list like below. Use seededit-item-{type} to differentiate different types of items.
           Make a derived class for each {type} that supplies the text, form, submit action, and additional js functionality.
           Create one instance of each derived class. It hooks up listeners to its items.

        <div class='seededit-list'>
            <div class='seededit-item seededit-item-{type}'>
                <div class='seededit-form-msg'></div>           -- put this anywhere you want messages to be visible
                <div class='seededit-text'> The item text goes here </div>
                <div class='seededit-form'></div>               -- leave empty, seededit will fill it as needed
            </div>
            <div class='seededit-item seededit-item-{type}'>
            .
            .
            </div>
        </div>
 */
{
    constructor( raConfig )
    {
        this.raConfig = raConfig;

        this.jItemCurr = null;          // the current seededit-item

        this.bFormIsOpen = false;
        this.bFormIsNew = false;        // if an open form is new, Cancel will .remove() it

        let saveThis = this;
        // any controls with this class will open a New form when clicked
        $(".seededit-ctrlnew").click( function(e) { console.log("A");saveThis.ItemNew(); });
    }

    IsFormOpen()  { return( this.bFormIsOpen ); }
    IsFormNew()   { return( this.bFormIsNew ); }


    ItemNew()
    /********
        Create a new item, and open its form for input
     */
    {
        // Cannot create and select a new item if another item is open
        if( this.IsFormOpen() ) return( null );

        // insert a new item in a nice place
        let jItem = $(this.raConfig['itemhtml']);
        if( this.jItemCurr ) {
            jItem.insertAfter( this.jItemCurr );
        } else {
            $(".seededit-list").prepend( jItem );
        }

        this.Item_Init( jItem );

        // make it the current item, open the form in the container, mark it as a New form so Cancel will remove() it
        this.bFormIsNew = true;
        this.FormOpen( jItem );
    }

    FormOpen( jItem )
    /****************
        Open the edit form for a clicked item.
        Input is the jquery object of the seededit_item.
     */
    {
        // Make jItem the current item and open the form, unless the item is unopenable or a form is already open
        let kItem = this.SelectItem( jItem, true );
        if( kItem == -1 )  return;

        let jFormDiv = this.jItemCurr.find(".seededit-form");

        // Create a form and put it inside seededit-item, after seededit-text. It is initially non-displayed, but fadeIn shows it.
        jFormDiv.html( this.MakeFormHTML( { formhtml: this.raConfig['formhtml'] } ) );

        // set listeners for the Save and Cancel buttons. Use saveThis because "this" is not defined in the closures.
        let saveThis = this;
        jFormDiv.find("form").submit( function(e) { e.preventDefault(); saveThis.FormSave( kItem ); } );
        jFormDiv.find(".seededit-form-button-cancel").click( function(e) { e.preventDefault(); saveThis.FormCancel(); } );

        // connect event listeners in the new form, etc.
        this.FormOpen_InitForm( jFormDiv, kItem );

        jFormDiv.fadeIn(500);
    }

    FormSave( kItem )
// kind of silly to pass kItem from FormOpen but check to see if there's a current item and open form. Why not just get the item id here.
    {
        if( this.jItemCurr == null || !this.IsFormOpen() ) return;

        return( this.FormSave_Action( kItem ) );
    }


    FormCancel()
    {
        this.FormClose( false );
    }

    FormClose( ok )
    {
        if( this.jItemCurr == null || !this.IsFormOpen() ) return;

        let jFormDiv = this.jItemCurr.find('.seededit-form');

        this.FormClose_PreClose( jFormDiv );

        let saveThis = this;    // "this" is not defined in the closure
        jFormDiv.fadeOut(500, function() {
                jFormDiv.html("");      // clear the form after fadeOut

                if( ok ) {
                    // do this after fadeOut because it looks better afterward
                    saveThis.jItemCurr.find(".seededit-form-msg").html( "<div class='alert alert-success'>Saved</div>" );
                }

                if( saveThis.IsFormNew() ) {
                    if( ok ) {
                        // Closing after successful submit of New form; leave the item intact
                    } else {
                        // Closing after Cancel on New form; remove the form
                        saveThis.jItemCurr.remove();
                        saveThis.jItemCurr = null;
// here the current item is undefined so if you click new again it draws at the top of the page (unscrolled). Better to try to remember the previous current item and make that current here.
                    }
                }
                // allow another block to be clicked (keep jItemCurr so a New container can be inserted after it)
                saveThis.bFormIsOpen = false;
                saveThis.bFormIsNew = false;

//                if( saveThis.jItemCurr ) {
                    // N.B. if a New form is Cancelled, jItemCurr will be null here
                    saveThis.FormClose_PostClose( saveThis.jItemCurr );
//                }
            } );
    }

    GetItemId( jItem )
    {
        let k = parseInt(jItem.attr("data-kitem")) || 0;     // apparently this is zero if parseInt returns NaN
        return( k );
    }

    SetItemId( jItem, kItem )
    {
        jItem.attr( 'data-kitem', kItem );
    }


    SelectItem( jItem, bOpenForm )
    /*****************************
        Make jItem the current item and set the status of the form.
        Check first that a form is not already open, because you can't change selection when a form is open.
        Return the kItem if successful (0 means success with a New form)
               or -1 if not (a form is already open)
     */
    {
        if( this.IsFormOpen() ) {
            console.log("Cannot open multiple forms");
            return( -1 );
        }

        let kItem = this.GetItemId( jItem );

        // only check this if opening a form because even unopenable forms can be selected (e.g. deleted MSD items can be selected so they can be Undeleted, but no form open)
        if( bOpenForm ) {
            if( !this.FormOpen_IsOpenable( jItem, kItem ) )  return( -1 );
        }

        this.jItemCurr = jItem;
        this.bFormIsOpen = bOpenForm;

        // clear previous edit indicators
        $(".seededit-item").css({border:"1px solid #e3e3e3"});
        $(".seededit-form-msg").html("");

        // show the current container is selected
        this.jItemCurr.css({border:"1px solid blue"});

        return( kItem );
    }

    MakeFormHTML( raConfig )
    /***********************
        Build the form by defining substitutions in the form below, or override to define the whole form.
     */
    {
        let f = "<form>"
                   +"[formhtml]"
                   +"<input type='submit' value='Save'/> "
                   +"<button class='seededit-form-button-cancel' type='button'>Cancel</button>"
               +"</form>";


        if( typeof raConfig['formhtml'] !== 'undefined' )  { f = f.replace( "[formhtml]", raConfig['formhtml'] ); }

        return( f );
    }


    /* Functions meant to provide derived behaviours
     */
    Item_Init( jItem )
    /*****************
        Derived class must call this to hook clicks to FormOpen
     */
    {
        let saveThis = this;
        // clicking on the seededit-text causes the form to open
        jItem.find(".seededit-text").click( function(e) { saveThis.FormOpen( jItem ); });
        // clicking on anything with class seededit_ctrledit causes the form to open (this could be a button, a link, an image...)
        jItem.find(".seededit-ctrledit").click( function(e) { saveThis.FormOpen( jItem ); });
    }

    FormOpen_IsOpenable( jItem, kItem )
    /**********************************
        Override to say whether the selected item is allowed to open a form
     */
    {
        return( true );
    }

    FormOpen_InitForm( jFormDiv, kItem )
    /***********************************
        Override to initialize the given form
     */
    {
        // disable all control buttons for all items, while the form is open
        $(".seededit-ctrlnew").attr("disabled","disabled");
        $(".seededit-ctrledit").attr("disabled","disabled");
    }

    FormClose_PreClose( jFormDiv )
    /*****************************
        Override for actions when a form is closed, before it fades out
     */
    {
    }

    FormClose_PostClose( jItem )
    /***************************
        Override for actions when a form is closed, after it fades out.
        This is called after the form is removed from the dom. The item should still be valid though.
        
        N.B. If a New form is Cancelled, jItem will be null at this point.
     */
    {
        // re-enable all control buttons for all items
        $(".seededit-ctrlnew").removeAttr("disabled");
        $(".seededit-ctrledit").removeAttr("disabled");
    }

    FormSave_Action( kItem )
    /***********************
        Override for the action when a form is saved
     */
    {
        return( true );
    }
}
