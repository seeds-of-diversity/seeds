/* SEEDUI
 *
 * Copyright 2020-2024 Seeds of Diversity Canada
 */

function SEEDUI_BoxExpandInit( lang, urlWCore )
/**********************************************
    Initialize seedui-boxexpand ui
    
    <div class='seedui-boxexpand class_to_style_the_box'>
        <div class='seedui-box-head'>stuff you want in the header</div>
        <div class='seedui-box-body'>the body of your box</div>
    </div>
    
    The header will get an expand button and body will be initially hidden.
    When you click on the button the body will slide visible and hidden.
 */
{
    jQuery(document).ready(function($) {
        /* Put the expand button and instruction on the left side of the header
         */
        $('.seedui-boxexpand .seedui-box-head').prepend(
            "<div class='seedui-boxexpand-expand-button'><img src='"+urlWCore+"img/ctrl/expand_button.gif'/></div>"
           +"<div class='seedui-boxexpand-expand-instruction'>"+(lang=='FR' ? "Cliquez" : "Click to show")+"</div>");
        
        /* Clicking on the header makes the body slide down/up
         */
        $('.seedui-boxexpand .seedui-box-head').click( function(e) {
            let oHead = $(this);
            let oBody = $(this).closest('.seedui-boxexpand').find('.seedui-box-body');
            // the position of the expand_button image tells us the open/close state
            if( $(this).find('img').css('left') == '0px') {
                // slide down and hide the instruction
                $(this).find('img').css('left', '-14px');
                oBody.slideDown(500);
                $(this).find('.seedui-boxexpand-expand-instruction').html('');
            } else {
                // slide up
                $(this).find('img').css('left', '0px');
                oBody.slideUp(500);
            }
        });
    });
}


/* js for SEEDUIWidget::SearchControl reset button

   Clear the search box and reset the select controls. 
   This is necessary because the values are hard-coded into the search control, so Reset's action is to put them there.
 */
jQuery(document).ready( function($) {
    $('.seedui_srchctrl_btn_formreset').click( function(e) {
        e.preventDefault();
        let jForm = $(this).closest("form"); 
        jForm.find(".seedui_srchctrl_fld").val("");     // the value of "Any""
        jForm.find(".seedui_srchctrl_op").val("like");  // the value of "contains"
        jForm.find(".seedui_srchctrl_text").val("");
        jForm.submit();
    });
});


/* Hack to replace video gallery figcaption>h3 with our font and our green background
 */
jQuery(document).ready( function($) {
    let j = $('figcaption');
    j.css('background-color', '#4e7722');

    j = $('figcaption h3');
    //j.css('font-family', 'Roboto');
    j.each(function () { this.style.setProperty( 'font-family', 'Roboto', 'important' ); });  // this allows priority to be set
});
