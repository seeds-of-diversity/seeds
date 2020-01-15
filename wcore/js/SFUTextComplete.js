/* SFU_TextComplete

   Make a text input that creates a <select> containing search matches.
   There can be multiple TextComplete controls because they are defined per id.

   Use SFU_TextCompleteVars to define the TextComplete controls and their handlers.
   { 'id_of_SFU_TextComplete' : { // Called when someone types in the text control. Return the <option>s that match the search text.
                                  'fnFillSelect' : function (sSearch) { return [ [val, label], [val, label] ] },
                                  // Called when someone clicks on an <option>
                                  'fnSelectChoose' : function (val) { } 
   }
 */
var SFU_TextCompleteVars = {};

$(document).ready( function() {
    SFU_TextComplete_Init();
});

function SFU_TextComplete_Init()
/*******************************
    Called by document.ready but you have to call this again if you create a node containing a .SFU_TextComplete
 */
{
    // Don't set up the TextComplete if there isn't one. offset().left below causes a js error that kills other ready() functions
    if( typeof $('.SFU_TextComplete').offset() == 'undefined' ) { console.log("No .SFU_TextComplete"); return; }

    $('.SFU_TextComplete').click( function(e) { 
        let oTC = $(this);   // preserve $this in the closure below

        // Get the <select> that was created on a previous click or create it if it doesn't exist
        let select = $(this).siblings('.SFUACSelect');
        if( !select.length ) {
            select = $("<select class='SFUACSelect'><option>--- Type more ---</option></select>").insertAfter($(this));

            let xSearch = $(this).offset().left;
            let ySearch = $(this).offset().top;
            let hSearch = $(this).outerHeight();
            let xAnchor = $(this).parent().offset().left;
            let yAnchor = $(this).parent().offset().top;
            select.css({ position:'absolute', left:xSearch-xAnchor, top:ySearch-yAnchor+hSearch+1, 'z-index':2 });
            
            // When someone clicks on an <option> send its value to fnSelectChoose and remove the <select> 
            select.click( function(e) {
                e.preventDefault(); 
                let v = SFU_TextCompleteVars[oTC.attr('id')];
                if( typeof v != 'undefined' ) {
                    oTC.val( (v['fnSelectChoose'])( $(this).val() ) ); 
                    $(this).remove(); 
                }
            });
        }
    });
    
    // In someone has typed at least 3 chars in the text input, use the callback to fetch a set of <options> that match
    $('.SFU_TextComplete').keyup( function(e) { 
        let srchVal = $(this).val();
        if( srchVal.length < 3 )  return;
        
        let select = $(this).siblings('.SFUACSelect');
        if( !select.length ) return;

        // remove all <option>s from the <select>
        select.find('option').each(function() { $(this).remove(); });
        
        // call the defined function to get new options
        let nOpts = 0;
        let v = SFU_TextCompleteVars[$(this).attr('id')];
        if( typeof v != 'undefined' ) {
            let options = (v['fnFillSelect'])(srchVal);
            // limit the number of options to 20 because you can keep typing to get a better match
            nOpts = options.length;
            if( nOpts > 20 ) nOpts = 20;
            for( let i = 0; i < nOpts; ++i ) {
                let r = options[i];
                select.append($('<option>', { value: r['val'], text: r['label'] }));
            }
        }

        // make the select control tall enough to contain all options
        select.attr({ size: nOpts }); 
    });
}
