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
        this.cpPage = 0;

        // Run fnPre for initial page
        this.cpConfig.pages[0]['fnPre']();

        // Show the initial page
        this.ShowPage( 0 );
    }
    
    GetVar( k )  	{ return( this.cpVars[k] ); }
    SetVar( k, v )  { this.cpVars[k] = v; }

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
        this.cpPage = this.cpConfig.pages[this.cpPage]['fnPost']();

        // Run fnPre for the new current page
        this.cpConfig.pages[this.cpPage]['fnPre']();

        // Show the current page
        this.ShowPage( this.cpPage );
    }
}

