$(document).ready( function() {
    $(".gtLotNum").blur( function () { collectionBatch.getCVName( $(this) ); } );
});


class collectionBatch {
    constructor() {}
    
    static qUrl = "https://seeds.ca/app/q/index.php";    // replace this with SEEDQ_URL
    
    static getCVName( jLotInput )
    {
        //alert( jLotInput.val() );
        // jLotInput.val() );
// move this command to seedlib/sl/QServerCollection.php
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