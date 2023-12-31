/* SEEDPopover.js
 *
 * Copyright (c) 2015-2023 Seeds of Diversity Canada
 *
 * Simplify presentation of a set of Bootstrap popovers
 */

var SEEDPopover_Def = {};   // replace this with the definitions for popovers
var SEEDPopoverShow = "";   // replace this with the name of a default popover to show on initialization

function SEEDPopover()
/*********************
   Call this on ready() to initialize and optionally show the popover(s).
 */
{
    /* Initialize the popovers that exist in the current document
     */
    for( let pop in SEEDPopover_Def ) {
        if( $('.SPop_'+pop).length ) {    // number of elements that match the class
            $('.SPop_'+pop).popover( SEEDPopover_Def[pop] );
        }
    }

    /* If a popover has been chosen to be shown by default, show it.
     */
    if( SEEDPopoverShow && typeof SEEDPopover_Def[SEEDPopoverShow] != 'undefined' ) {
        $('.SPop_'+SEEDPopoverShow).popover('show');
    }
}
