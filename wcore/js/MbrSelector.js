// requires SEEDCore.js
// requires SFUTextComplete.js

/* Selector tool for choosing a contact from MbrContacts

   Usage:
       let o = new MbrSelector( { urlQ        : 'http://.../q.php',
                                  idTxtSearch : 'myDummyTxt',
                                  idOutReport : 'myReport',
                                  idOutKey    : 'myMbrKey' } );

       <div style='position:relative'>
           <input type='text' id='myDummyTxt'/>       // you type here
           <span id='myReport'> </span>               // details of your choice go here
           <input type='hidden' id='myMbrKey'/>       // your selected mbr key goes here
       </div>
*/
class MbrSelector extends SFU_TextComplete
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

        let jxData = { qcmd    : 'mbr-search',
                       lang    : "EN",
                       sSearch : sSearch
                     };
        let o = SEEDJXSync( this.raConfig['urlQ'], jxData );
        if( !o || !o['bOk'] || !o['raOut'] ) {
            alert( "Sorry there is a server problem" );
        } else {
            this.mbrData = o['raOut'];   // save this so we can look it up in ResultChosen
            for( let i = 0; i < o['raOut'].length; ++i ) {
                let r = o['raOut'][i];
                raRet[i] = { val: r['_key'], label: this.makelabel(r) };
            }
        }
        return( raRet );
    }

    ResultChosen( val )
    {
        for( let i = 0; i < this.mbrData.length; ++i ) {
            let r = this.mbrData[i];
            if( r['_key'] == val ) {
                $("#"+this.raConfig['idOutReport']).html( this.makelabel(r) );
                $("#"+this.raConfig['idOutKey']).val( r['_key'] );
                break;
            }
        }
    }

    makelabel( r )
    {
        let l = r['firstname']+" "+r['lastname']+" ("+r['_key']+")";
        if( r['city'] )  l += " in "+r['city'];
        return( l );
    }
}
