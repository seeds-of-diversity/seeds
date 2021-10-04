/* SEEDBasket fulfilment
 *
 * Copyright 2021 Seeds of Diversity Canada
 */

$(document).ready(function() {
    /* 
     */
//     $('.mbrOrderShowTicket').click( function (event) {
//         event.preventDefault();
//         initClickShowTicket( $(this) );
//     });
});

var urlQ_sbfulfil = "";     // defaults to the current app



class SEEDBasketFulfilUI
{
    constructor()
    {
    }

    static replaceBasketContentsWithEditor( kB )
    {
        let jxData = { jx   : 'sbfulfil--drawBasketEditor',
                       kB   : kB,
                       lang : "EN"
                     };
    
        SEEDJXAsync2( urlQ_sbfulfil, jxData, function(o) {
            if( o['bOk'] ) {
                $('.sbfulfil_basket[data-kBasket='+kB+']').html(o['sOut']);
            }
            else console.log(o);
        });
    }

    static replaceBasketEditorWithContents( kB )
    {
        let jxData = { jx   : 'sbfulfil--drawBasketContents',
                       kB   : kB,
                       lang : "EN"
                     };
    
        SEEDJXAsync2( urlQ_sbfulfil, jxData, function(o) {
            if( o['bOk'] ) {
                $('.sbfulfil_basket[data-kBasket='+kB+']').html(o['sOut']);
            }
            else console.log(o);
        });
    }

}