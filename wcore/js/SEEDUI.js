/* SEEDUI
 *
 * Copyright 2020 Seeds of Diversity Canada
 */

function SEEDUI_BoxExpandInit( lang, urlWCore )
/**********************************************
    Initialize seedui_boxexpand ui
    
    <div class='seedui_boxexpand class_to_style_the_box'>
        <div class='seedui_boxexpand_head'>stuff you want in the header</div>
        <div class='seedui_boxexpand_body'>the body of your box</div>
    </div>
    
    The header will get an expand button and body will be initially hidden.
    When you click on the button the body will slide visible and hidden.
 */
{
    $(document).ready(function() {
        /* Put the expand button and instruction on the left side of the header
         */
        $('.seedui_boxexpand_head').prepend(
            "<div class='seedui_boxexpand_expand-button'><img src='"+urlWCore+"img/ctrl/expand_button.gif'/></div>"
           +"<div class='seedui_boxexpand_expand-instruction'>"+(lang=='FR' ? "Cliquez" : "Click to show")+"</div>");
        
        /* Clicking on the header makes the body slide down/up
         */
        $('.seedui_boxexpand_head').click( function(e) {
            let oHead = $(this);
            let oBody = $(this).closest('.seedui_boxexpand').find('.seedui_boxexpand_body');
            // the position of the expand_button image tells us the open/close state
            if( $(this).find('img').css('left') == '0px') {
                // slide down and hide the instruction
                $(this).find('img').css('left', '-14px');
                oBody.slideDown(500);
                $(this).find('.seedui_boxexpand_expand-instruction').html('');
            } else {
                // slide up
                $(this).find('img').css('left', '0px');
                oBody.slideUp(500);
            }
        });
    });
}