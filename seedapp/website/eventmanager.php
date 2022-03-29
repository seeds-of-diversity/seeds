<?php

include_once( SEEDCORE."SEEDCoreFormSession.php" );
include_once( SEEDCORE."SEEDXLSX.php" );
include_once( SEEDLIB."google/GoogleSheets.php" );

class EventsSheet
/****************
 Connect events with a Google sheet
 */
{
    private $oApp;

    private $oSheets = null;
    private $p_idSheet;

    function __construct( SEEDAppDB $oApp )
    {
        $this->oApp = $oApp;
        $this->oForm = new SEEDCoreFormSession($oApp->sess, 'eventSheets');
        $this->oForm->Update();

        if( ($idSpread = $this->oForm->Value('idSpread')) ) {

            $this->oGoogleSheet = new SEEDGoogleSheets_NamedColumns(
                ['appName' => 'My PHP App',
                    'authConfigFname' => SEEDCONFIG_DIR."/sod-public-outreach-info-e36071bac3b1.json",
                    'idSpreadsheet' => $idSpread
                ] );
        }
    }

    function IsLoaded()  { return( $this->oSheets ); }

    function values()
    {
        $range = 'A1:Z1';
        list($values,$range) = $this->oSheets->GetValues( $range );
        return( $values );
    }

    function DrawForm()
    {
        $s = "<form method='post'>
              <div>{$this->oForm->Text('idSpread',  '', ['size'=>60])}&nbsp;Google sheet id</div>
              <div>{$this->oForm->Text('nameSheet', '', ['size'=>60])}&nbsp;sheet name</div>
              <div><input type='submit'/></div>
              </form>";
        return( $s );
    }

    function DrawTable()
    {
        $s = "";

        if( !$this->oGoogleSheet || !($nameSheet = $this->oForm->Value('nameSheet')) )  goto done;

        // 0-based index of columns or false if not found in spreadsheet (array_search returns false if not found)
        $raColNames = $this->oGoogleSheet->GetColumnNames($nameSheet);
        var_dump($raColNames);

        $raRows = $this->oGoogleSheet->GetRows($nameSheet);
        var_dump($raRows);

        done:
        return( $s );
    }

    /**
     * update or add event to spreadsheet
     * @param $parms array of parameters for an event
     */
    function AddEventToSpreadSheet( $parms )
    {

        $exist = false; // if event already exist on spreadsheet
        $spreadSheetRow = 0; // row in spreadsheet if event already exists in spreadsheet

        $raId = $this->oGoogleSheet->GetColumn("A"); // get all row's id

        if(isset($parms['id'])) { // if $parms is associative array with string index
            $id = $parms['id'];
        }
        else{ // if $parms is array with integer index
            $id = $parms[0];
        }

        foreach( $raId as $k=>$v ) { // check if id in spreadsheet matches with id in $parms

            if( $v == $id ) { //if id in spreadsheet matches with id in $parms
                $exist = true;
                $spreadSheetRow = $k+1; // row of current event
            }
        }

        if( !$exist ) { // if event does not exist in spreadsheet
            $spreadSheetRow = count($raId) + 1; // add to next available row
        }

        if(isset($parms['id'])){ // if $parms is associative array with string index
            $this->oGoogleSheet->SetRowWithAssociativeArray($spreadSheetRow, $parms);
        }
        else{// if $parms is array with integer index
            $this->oGoogleSheet->SetRow($spreadSheetRow, $parms);
        }

    }
    /**
     * return associative array of all events from sheets
     * @return array|NULL
     */
    function GetEventsFromSheets()
    {
        $nameSheet = $this->oForm->Value('nameSheet');
        $raColumns = $this->oGoogleSheet->GetColumnNames($nameSheet);
        $raEvents = $this->oGoogleSheet->GetRows($nameSheet);

        foreach($raEvents as $k=>$v){
            $raEvents[$k] = array_combine($raColumns, $v);
        }
        return $raEvents;
    }

    /**
     * get list of events using MEC
     * @return array of organized events with properties
     */
    function GetEventsFromDB()
    {
        include("../wp-content/plugins/modern-events-calendar-lite/modern-events-calendar-lite.php");
        $oMEC = new MEC_main();

        //trigger_error('there should be erro here ', E_USER_ERROR);

        $arr = $oMEC->get_upcoming_events(); // get list of events from MEC, will only return 12 events by default

        echo "<p></p>";

        $events = array(); // 2d array of events

        foreach($arr as $k=>$v){

            foreach($v as $k2=>$v2){
                //var_dump($k2);
                //var_dump($v2);
                //var_dump($v2->data->time);
                //var_dump($v2->data->tickets);

                $event = array(
                    'id'=>$v2->ID,
                    'title'=>$v2->data->title,
                    'date_start'=>$v2->date['start']['date'],
                    'date_end'=>$v2->date['end']['date'],
                    'time_start'=>$v2->data->time['start'],
                    'time_end'=>$v2->data->time['end'],
                    'location_name'=>reset($v2->data->locations)['name'],
                    'location_address'=>reset($v2->data->locations)['address'],
                    // address is in first line of content
                    'latitude'=>reset($v2->data->locations)['latitude'],
                    'longitude'=>reset($v2->data->locations)['longitude'],
                    'link_event'=>'last line of content',
                    'link_more_info'=>'last line of content',
                    'organizer_name'=>'convert id to name',
                    'organizer_id'=>$v2->data->meta['mec_organizer_id'],
                    'volunteer_id'=>'not in db?',
                    'materials_needed'=>'not in db',
                    'materials_sent'=>'not in db',
                    'attendance'=>'not in db?',

                );

                array_push($events, $event);

            }
        }

        //var_dump($events);

        return $events;

    }

}