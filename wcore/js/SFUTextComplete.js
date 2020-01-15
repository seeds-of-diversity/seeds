/* SFU_TextComplete

   Make a text input that creates a <select> containing search matches.
   There can be multiple TextComplete controls because they are defined per id.

   Usage:
       <div style='position:relative'>        // this is necessary for positioning the <select>
       <input type='text' id='myTC'/>
       </div>
       
       class myTextComplete extends SFU_TextComplete { 
           constructor( idTextElement )  { super(idTextElement); }
           GetMatches( sSearch )         { return array of opts for the <select> [ [val, label], [val, label] ] }
           ResultChosen( v )             { do what you want with the chosen value }
       }
       let o = new myTextComplete('myTC');
   }
 */

class SFU_TextComplete
{
    constructor( idTextElement )
    {
        this.idTextElement = idTextElement;
        this.Init();
    }

    Init()
    {
        let saveThis = this;

        // When someone clicks the text element, create a <select> element (if not already existing) and position it beneath
        $('#'+this.idTextElement).click( function(e) { 
            let select = $(this).siblings('.SFUTCSelect');
            if( !select.length ) {
                // Create the <select> and position it
                select = $("<select class='SFUTCSelect'><option>--- Type more ---</option></select>").insertAfter($(this));

                let xSearch = $(this).offset().left;
                let ySearch = $(this).offset().top;
                let hSearch = $(this).outerHeight();
                let xAnchor = $(this).parent().offset().left;
                let yAnchor = $(this).parent().offset().top;
                select.css({ position:'absolute', left:xSearch-xAnchor, top:ySearch-yAnchor+hSearch+1, 'z-index':2 });
                
                // When someone clicks on an <option> send its value to this.ResultChosen() and remove the <select> 
                let thisText = $(this);
                select.click( function(e) {
                    e.preventDefault(); 
                    saveThis.ResultChosen( $(this).val() );
                    $(this).remove(); 
                    thisText.val('');
                });
            }
        });

        // If someone has typed at least 3 chars in the text input, fetch a set of <options> that match
        $('#'+this.idTextElement).keyup( function(e) { 
            let sSearch = $(this).val();
            if( sSearch.length < 3 )  return;

            let select = $(this).siblings('.SFUTCSelect');
            if( !select.length ) return;

            // remove all <option>s from the <select>
            select.find('option').each(function() { $(this).remove(); });

            // get new options
	        let nOpts = 0;
	            let options = saveThis.GetMatches( sSearch );
	            // limit the number of options to 20 because you can keep typing to get a better match
	            nOpts = options.length;
	            if( nOpts > 20 ) nOpts = 20;
	            for( let i = 0; i < nOpts; ++i ) {
	                let r = options[i];
	                select.append($('<option>', { value: r['val'], text: r['label'] }));
	            }
	        
	
	        // make the select control tall enough to contain all options
	        select.attr({ size: nOpts }); 
	    });
	}
    
    GetMatches( sSearch )
    {
        // OVERRIDE THIS to return [[val,label],[val,label],...] for the <option>s that match the search
    }
    
    ResultChosen( val )
    {
        // OVERRIDE THIS to do what you want when the user clicks on the given <option>
    }
}
