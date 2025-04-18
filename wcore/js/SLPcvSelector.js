// requires SEEDCore.js
// requires SFUTextComplete.js

/* Selector tool for choosing a cultivar from sl_pcv

   Usage:
       let o = new SLPcvSelector( { urlQ        : 'http://.../q.php',
                                    idTxtSearch : 'myDummyTxt',
                                    idOutReport : 'myReport',
                                    idOutKey    : 'myPcvKey',
                                    fnResult    :  fn(array row from rosettaPCVSearch){}  } );

       <div style='position:relative'>
           <input type='text' id='myDummyTxt'/>       // you type here
           <span id='myReport'> </span>               // details of your choice go here
           <input type='hidden' id='myPcvKey'/>       // your selected pcv key goes here
       </div>
*/
class SLPcvSelector extends SFU_TextComplete
{
    constructor( raConfig )
    {
        super(raConfig.idTxtSearch);
        this.raConfig = raConfig;
        this.mbrData = [];          // store the mbr lookup data here for reporting when a result is chosen
    }

    GetMatches( sSearch )
    {
        let raRet = [];

        let jxData = { qcmd : 'rosettaPCVSearch',
                       lang : "EN",
                       srch : sSearch 
                     };
        let o = SEEDJXSync( this.raConfig['urlQ'], jxData );
        if( !o || !o['bOk'] || !o['raOut'] ) {
            //alert( "Sorry there is a server problem" );
        } else {
            this.mbrData = o['raOut'];   // save this so we can look it up in ResultChosen
            for( let i = 0; i < o['raOut'].length; ++i ) {
                let r = o['raOut'][i];
                raRet[i] = { val: r['P__key'], label: this.makelabel(r), rosetta: r };
            }
        }
        return( raRet );
    }

    ResultChosen( val )
    {
        for( let i = 0; i < this.mbrData.length; ++i ) {
            let r = this.mbrData[i];
            if( r['P__key'] == val ) {
                $("#"+this.raConfig['idOutReport']).html( "<input type='submit' value='Save'/> <span style='color:orange'>"+this.makelabel(r)+"</span>" );
                $("#"+this.raConfig['idOutKey']).val( r['P__key'] );
                
                if(typeof(this.raConfig['fnResult']) != 'undefined' )  this.raConfig['fnResult'](r);     // send r to the callback
                break;
            }
        }
    }

    makelabel( r )
    {
        return( r['S_psp']+" : "+r['P_name']+" ("+r['P__key']+")");
    }
}
