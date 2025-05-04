/* Selector tool for choosing a contact from MbrContacts

   Requires: select2.js

   Documentation: https://select2.org

   Usage:
       <select id='mbrChooseOne' style='width:40em'><option value='0'>Choose a member</option></select>

       <script>let o = new SLPcvSelect2( { jSelect: $('#mbrChooseOne'),
                                           qUrl: '{$this->oP->oApp->UrlQ()}' } );
       </script>
*/

class SLPcvSelect2
{
    constructor( raConfig )
    {
        this.raConfig = raConfig;
        $(document).ready( this.init() );
    }
    
    init()
    {
        this.raConfig.jSelect.select2({ 
              ajax:{ url: this.raConfig.qUrl,
                     /* select2 has a default ajax data format; it provides this function to allow translation to our mbr-search data format  
                      */
                     data: function (p) {
                               //console.log(p);
                               return { sSrch: p.term,
                                        qcmd: 'rosetta-cultivarsearch'
                                        //type: 'public'
                                      };
                           },
                     /* select2 provides this for translation of the successful ajax response to its expected response format
                      *    { results: [ {id:1, text:'one'}, {id:2, text:'two'} ] }
                      */
                     processResults: function (data) {console.log(data);
                                         data= window.JSON.parse(data);
                                         //console.log(data);

                                         let raResult = [];
                                         if( data.bOk ) {
                                             data.raOut.forEach( function(v, k, ra) { raResult.push({id:v.kPcv, text:`${v.sSpecies} : ${v.sCultivar} (${v.kPcv})`}); } );
                                         }
                                         return { results: raResult };
                                     },
                     /* prevent unnecessary queries by waiting until the user has stopped typing for awhile
                      */
                     delay: 250,
                     /* don't query search terms shorter than 3 chars
                      */
                     minimumInputLength: 3,
                   } 
        });
    }
}
