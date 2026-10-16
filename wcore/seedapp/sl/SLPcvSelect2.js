/* Selector tool for choosing a pcv from sl_pcv

   Requires: select2.js

   Documentation: https://select2.org

   Usage:
   	   <span id='pcvLabel'>Optional place to show the cultivar name</span>
       <select id='pcvChooseOne' name='pcvChooseOne' style='width:40em'><option value='0'>Choose a cultivar</option></select>

       <script>let o = new SLPcvSelect2( { jSelect: $('#pcvChooseOne'),
                                           qUrl: '{$this->oP->oApp->UrlQ()}'
										   jLabel: $('#pcvLabel') } );
       </script>
*/

class SLPcvSelect2
{
    constructor( raConfig )
    {
        this.raConfig = raConfig;
        if( typeof(this.raConfig['eDatasets']) == 'undefined' )  this.raConfig['eDatasets'] = " ALL ";
        $(document).ready( this.init() );
    }
    
    init()
    {
        let saveThis = this;
        this.raConfig.jSelect.select2({ 
              ajax:{ url: this.raConfig.qUrl,
                     /* select2 has a default ajax data format; it provides this function to allow translation to our rosetta-search data format  
                      */
                     data: function (p) {
                               let ret = 
							       { sSrch: p.term,
                                     eDatasets: saveThis.raConfig['eDatasets'],
                                     qcmd: 'rosetta-cultivarsearch'
                                     //type: 'public'
                                   };
							   //console.log(p, saveThis.raConfig.qUrl, ret);
						       return(ret);
                           },
                     /* select2 provides this for translation of the successful ajax response to its expected response format
                      *    { results: [ {id:1, text:'one'}, {id:2, text:'two'} ] }
                      */
                     processResults: function (data) {
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
		
		// when a selection is made, copy the cultivar name to a designated label container if defined
		this.raConfig.jSelect.on('select2:select', function (e) {
		    if( "jLabel" in saveThis.raConfig ) {
				saveThis.raConfig.jLabel.html(e.params.data.text);
 	        }
		});
    }
}
