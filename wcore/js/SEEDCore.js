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

function SEEDJXAsync( jxUrl, jxData, fnSuccess, fnError )
/********************************************************
    Post an ajax request to the given url, set handler functions for success or error and return immediately
 */
{
    $.ajax({
        type: "POST",
        async: true,
        url: jxUrl,
        data: jxData,
        //dataType: "json",
        success: fnSuccess,
        error:   fnError,
    });
}

function SEEDJXAsync2( jxUrl, jxData, fnSuccess, fnError = null )
/****************************************************************
    Post an ajax request to the given url, set handler functions for success or error and return immediately
 */
{
    if( !fnError ) {
        fnError = function(jqXHR, textStatus, errorThrown) {
                      if( SEEDJX_bDebug ) {
                          console.log(errorThrown);
                          //alert(jqXHR);
                          //alert(textStatus);
                      }
                  }
    }

    if( SEEDJX_bDebug ) {console.log("cmd="+jxUrl+":"); console.log(jxData); }

    $.ajax({
        type: "POST",
        async: true,
        url: jxUrl,
        data: jxData,
        //dataType: "json",
        
        // This gets response data from .ajax, parses it and passes a Q object to the fnSuccess. Write your fnSuccess to receive a Q object.
        // To debug the server, put die("whatever") in the server code and set SEEDJX_bDebug so "whatever" will appear in the console
        success: function(data) {
            if( SEEDJX_bDebug ) console.log("outData="+data);
            o = SEEDJX_ParseJSON(data);
            fnSuccess(o);
        },
        error: fnError
    });
}

function SEEDJXSync( jxUrl, jxData )
/*********************************
    Post an ajax request to the given url, wait for the server, and return the response
 */
{
    var bSuccess = false;
    var oRet = null;
    $.ajax({
        type: "POST",
        async: false,
        url: jxUrl,
        data: jxData,
        //dataType: "json",
        success: function(data) {
            // To debug the server, put die("whatever") in the server code and set SEEDJX_bDebug so "whatever" will appear in the console
            if( SEEDJX_bDebug ) console.log("data="+data);

            bSuccess = true;
            oRet = SEEDJX_ParseJSON(data);
        },
        error: function(jqXHR, textStatus, errorThrown) {
            if( SEEDJX_bDebug ) {
                console.log(errorThrown);
                //alert(jqXHR);
                //alert(textStatus);
            }
        }
    });
    return( bSuccess ? oRet : null );
}

function SEEDJX_ParseJSON( data )
{
    if( !data ) return( null );
    return( window.JSON && window.JSON.parse && data ? window.JSON.parse(data) : eval(data) ); 
}

function SEEDJX_Form1( jxUrl, btnSubmit )
/****************************************
    Someone clicked on a button that invoked a Form1.
    
    jxUrl is an ajax url to call
    btnSubmit is the jQuery object of the button that was clicked.
    
    Get all the <input> elements in the form containing that button, send them to jxUrl with jxCmd = the value of attr seedjx_cmd,
    and put the return html in seedjx_out and seedjx_err
    
    <div class='seedjx-form1' seedjx-cmd='mycommand'>
        <div class='seedjx-err'></div>
        <div class='seedjx-out'>
            <input .../>
            <input .../>
            <input class='seedjx-submit' id='foo'/>   <!-- doesn't have to be a type=submit button, doesn't have to be in a <form> -->
        </div>
    </div>
    
    Invoke with $('#foo').onclick( SEEDJX_Form1( myJXUrl, $(this) );
 */
{
    // Everything for the Form1 should be contained in a .seedjx
    var d = btnSubmit.closest( ".seedjx" );
    
    // The cmd should be defined in an attr either in the clicked object, or in the container element
    var cmd = btnSubmit.attr('seedjx-cmd');
    if( typeof cmd == 'undefined' ) {
        cmd = d.attr('seedjx-cmd');
    }
    
    // Get parameters from all form elements within the container
    var inputData = d.find("select, textarea, input").serialize();
    
    inputData = "cmd="+cmd+"&"+inputData;
    if( SEEDJX_bDebug ) alert(inputData);

    SEEDJXAsync(jxUrl, 
                inputData,
                // Success
                // This calls anonymous function with arg d -> div, then constructs function receiving 
                // responseData and referencing a copy of div in the local scope. This is necessary because d will be
                // gone by the time the success function is called.
                function( div ) {
                    return function( responseData ) { 
                        // To debug the server, put die("whatever") in the server code and uncomment below
                        if( SEEDJX_bDebug ) alert("responseData="+responseData);

                        var oRet = SEEDJX_ParseJSON( responseData );
                        // oRet is { bOk, sOut, sErr }
                        div.find(".seedjx-out").html( oRet['sOut'] );
                        if( !oRet['bOk'] ) {
                            div.find(".seedjx-err").html( oRet['sErr'] );
                            div.find(".seedjx-err").show();
                        }
                    };
                }(d),
                // Error
                function (jqXHR, textStatus, errorThrown) {
                    if( SEEDJX_bDebug ) {
                        alert(errorThrown);
                        //alert(jqXHR);
                        //alert(textStatus);
                    }
                }
    );
}
