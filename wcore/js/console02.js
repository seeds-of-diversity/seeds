/* Console02.js
 *
 * Copyright (c) 2018 Seeds of Diversity Canada
 * 
 * UI support for Console
 */

var consolePageObj = {};

function ConsolePageStart( config )
{
    consolePageObj['nMaxPage'] = config.nMaxPage;
    consolePageObj['iPage'] = 0;
    consolePageObj['vars'] = config.vars;
    consolePageObj['fns'] = config.fns;

    // Run PrePage() for first page
    consolePageObj['fns'][consolePageObj['iPage']]['pre']();

    consolePageShow( consolePageObj['iPage'] );
}

function consolePageSubmit()
{
    // Run PostPage for the submitted page
    consolePageObj['iPage'] = consolePageObj['fns'][consolePageObj['iPage']]['post']();

    // Run PrePage for the new or current page
    consolePageObj['fns'][consolePageObj['iPage']]['pre']();

    // Show the current page
    consolePageShow( consolePageObj['iPage'] );
}

function consolePageShow( p )
{
    for( var i = 0; i <= consolePageObj['nMaxPage']; i++ ) {
        if( i == p ) {
            $('#consolePage'+i).show();
        } else {
            $('#consolePage'+i).hide();
        }
    }
}
