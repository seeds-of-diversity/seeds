/* SEEDCore.js
 *
 * Common helper functions
 * 
 * Copyright (c) 2015-2018 Seeds of Diversity Canada
 */

var SEEDJX_bDebug = false;   // set this true where you call SEEDJX to get private debug information


function SEEDCore_CleanBrowserAddress()
/**************************************
    Clean the GET parameters off of the url in the browser address bar.
    
    When a page sends parameters using GET, those parameters stay in the browser address bar. 
    Then if the next page sends something by POST, some browsers re-send the whole url in the address bar which still contains the GET parms.
    That's not right.
    Even if a browser doesn't do that, the address bar looks cluttered and this makes it look nicer.
    
    N.B. The browser should process the GET parms long before this function is called. Use it in the html body.
 */
{
    var clean_uri = location.protocol + "//" + location.host + location.pathname;
    window.history.replaceState({}, document.title, clean_uri);
}