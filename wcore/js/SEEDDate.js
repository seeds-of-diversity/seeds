function SEEDDateString( date, bShowDate, bShowTime )
/****************************************************
 */
{
    let s = "";
    if( bShowDate ) {
        let raDays = new Array("Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday");
        let raMonths = new Array("January","February","March","April","May","June","July","August","September","October","November","December");
        let year = date.getYear();

        year += (year > 99 && year < 200) ? 1900 : ( year < 100 ? 2000 : 0 );

        s += raDays[date.getDay()]+"&nbsp;"+raMonths[date.getMonth()]+"&nbsp;"+date.getDate()+"&nbsp;"+year;
    }
    if( bShowTime ) {
        if( bShowDate ) { s += "&nbsp;&nbsp;"; }
        let min = date.getMinutes();
        let sec = date.getSeconds();
        s += date.getHours()+":" + (min < 10 ? "0" : "") + min + ":" + (sec < 10 ? "0" : "") + sec;
    }
    return( s );
}


function SEEDDateStringToday()
/*****************************
 */
{
    return( SEEDDateString( (new Date()), 1, 0 ) );
}


function SEEDDateStringNow()
/***************************
 */
{
    return( SEEDDateString( (new Date()), 1, 1 ) );
}


function SEEDDateStringNowTime()
/*******************************
 */
{
    return( SEEDDateString( (new Date()), 0, 1 ) );
}
