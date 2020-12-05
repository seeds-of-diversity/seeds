$(document).ready( function() {
    $(".gtLotNum").blur( function () { collectionBatch.getCVName( $(this) ); } );
    $("#collection-batch-germ-container").html( collectionBatchGermForm() );	
});

function collectionBatchGermForm()
{
    let s = "";

    s = "Hello world!";
    
    return( s );
}


class collectionBatch {
    constructor() {}
    
    static qUrl = "https://www.seeds.ca/app/q/index.php";
    
    static getCVName( jLotInput )
    {
        //alert( jLotInput.val() );
        // jLotInput.val() );

        let jxData = { qcmd  : 'collection-getlot',
                       kColl : 1,
                       nInv  : jLotInput.val()
                     };

        SEEDJXAsync2( this.qUrl, jxData, 
                      function(o) {
                          console.log(o);
                          if( o['bOk'] ) {
                              jLotInput.closest('tr').find('.gtCVName').val( o.raOut['S_name_en']+' '+o.raOut['P_name'] );
                          }
                      });

    }
}