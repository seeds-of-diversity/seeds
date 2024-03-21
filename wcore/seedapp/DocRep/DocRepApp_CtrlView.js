/* DocRepApp_CtrlView.js
 *
 * Copyright (c) 2021-2024 Seeds of Diversity Canada
 *
 * UI widget for managing DocRep tabbed control view.
 */


/* TODO: this is a generic tabbed CtrlView widget if you rename the ids. Put it in a generic Console class or derive this from one.
 */

class DocRepCtrlView
{
    constructor( oConfig )
    {
        this.oConfig = oConfig;
        
        this.fnHandleEvent = oConfig.fnHandleEvent;     // use this to communicate with widgets/app
        
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
