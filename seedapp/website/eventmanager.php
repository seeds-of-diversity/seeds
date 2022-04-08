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
    private $oForm;     // SVASession to specify the google spreadsheet
    private $oMEC;      // MEC_main class - mec should already be included

    private $oSheets = null;
    private $nameSheet = '';

    function __construct()
    {
        $this->oApp = SEEDConfig_NewAppConsole_LoginNotRequired( ['db'=>'wordpress'] );
        $this->oForm = new SEEDCoreFormSession($this->oApp->sess, 'eventSheets');
        $this->oForm->Update();

        $this->oMEC = new MEC_main();   // MEC plugin should already be included by our WP plugin

        if( ($idSpread = $this->oForm->Value('idSpread')) ) {
            $this->nameSheet = $this->oForm->Value('nameSheet');

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

    function DrawEventControlPanel()
    {
        $s = "<form method='post'>
              <div>{$this->oForm->Text('idSpread',  '', ['size'=>60])}&nbsp;Google sheet id</div>
              <div>{$this->oForm->Text('nameSheet', '', ['size'=>60])}&nbsp;sheet name</div>
              <div><input type='submit' value='Show events'/></div>
              </form>";

        $s .= "<p>Export events to spreadsheet</p>
              <form method='get' action='?page=eventctrl'>
              <input type='hidden' name='page' value='eventctrl'/>
              <input type='submit' name='export' value='export'/>
              </form>";

        $s .=  "<p>Import events from spreadsheet</p>
              <form method='get' action='?page=eventctrl'>
              <input type='hidden' name='page' value='eventctrl'/>
              <input type='submit' name='import' value='import'/>
              </form>";


        if( SEEDInput_Str('export') ) {
            $raEvents = $this->GetEventsFromDB(); // return organized list of events from db
            foreach($raEvents as $k=>$v){
                $this->AddEventToSpreadSheet($v); // add each event to spreadsheet
            }
        }

        if( SEEDInput_Str('import') ) {
            $events = $this->GetEventsFromSheets();    // return list of events from spreadsheet
            foreach($events as $k=>$v){
                $this->AddEventToDB($v);               // add each event to db
            }
        }

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

        // Get all ids from the id column starting at row 2. $raId[0] is the id value of row 2
        $raId = $this->oGoogleSheet->GetColumnByName( $this->nameSheet, 'id' );

        if(isset($parms['id'])) { // if $parms is associative array with string index
            $id = $parms['id'];
        }
        else{ // if $parms is array with integer index
            $id = $parms[0];
        }

        foreach( $raId as $k=>$v ) { // check if id in spreadsheet matches with id in $parms

            if( $v == $id ) { //if id in spreadsheet matches with id in $parms
                $exist = true;
                $spreadSheetRow = $k+2; // $raId[0] is the id value of row 2 so the indexes are different by 2
            }
        }

        if( !$exist ) { // if event does not exist in spreadsheet
            $spreadSheetRow = count($raId) + 2; // add to next available row
        }

        if(isset($parms['id'])){ // if $parms is associative array with string index
            //var_dump("Adding {$parms['id']} at $spreadSheetRow");
            $this->oGoogleSheet->SetRowWithAssociativeArray($this->nameSheet, $spreadSheetRow, $parms);
        }
        else{// if $parms is array with integer index
            $this->oGoogleSheet->SetRow($this->nameSheet, $spreadSheetRow, $parms);
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
        $oMEC = new MEC_main();

        //trigger_error('there should be erro here ', E_USER_ERROR);

        $arr = $oMEC->get_upcoming_events(); // get list of events from MEC, will only return 12 events by default

        echo "<p></p>";

        $events = array(); // 2d array of events

        foreach($arr as $k=>$v){

            foreach($v as $k2=>$v2){
                //var_dump($k2);
                //var_dump($v2);
                //var_dump($v2->data->locations);
                //var_dump($v2->data->tickets);

                $id = $v2->ID;

                //NOTE: sometimes the location object will not exist, reset($v2->data->locations) will give warning
                $loc = @$v2->data->locations && ($l = @reset($v2->data->locations)) ? $l : null;
                //get_location_data($location_id)

                $seedMeta = $this->GetSEEDEventMeta($id);


                $event = array(
                    'id'=>isset($v2->ID) ? $v2->ID : 'id not found',
                    'title'=>isset($v2->data->title) ? $v2->data->title : 'title not found',
                    'date_start'=>isset($v2->date['start']['date']) ? $v2->date['start']['date'] : 'date_start not found',
                    'date_end'=>isset($v2->date['end']['date']) ? $v2->date['end']['date'] : 'date_end not found',
                    'time_start'=>isset($v2->data->time['start']) ? $v2->data->time['start'] : 'time_start not found',
                    'time_end'=>isset($v2->data->time['end']) ? $v2->data->time['end'] : 'time_end not found',
                    'location_name'=> @$loc['name'] ?: 'location_name not found',
                    'location_address'=> @$loc['address'] ?: 'location_address not found',
                    // address is in first line of content
                    'latitude'=>@$loc['latitude'] ?: 'latitude not found',
                    'longitude'=>@$loc['longitude'] ?: 'longitude not found',
                    'link_event'=>isset($v2->data->meta['mec_read_more']) ? $v2->data->meta['mec_read_more'] : 'link_event not found',
                    'link_more_info'=>isset($v2->data->meta['mec_more_info']) ? $v2->data->meta['mec_more_info'] : 'link_more_info not found',
                    'organizer_name'=>'convert id to name',
                    'organizer_id'=>isset($v2->data->meta['mec_organizer_id']) ? $v2->data->meta['mec_organizer_id'] : 'organizer_id not found',
                    'volunteer_id'=>'not in db?',
                    'materials_needed'=>'not in db',
                    'materials_sent'=>'not in db',
                    'attendance'=>'not in db?',
                    'volunteer_id'=>isset($seedMeta['volunteer_id']) ? $seedMeta['volunteer_id'] : 'volunteer_id not found',
                    'materials_needed'=>isset($seedMeta['materials_needed']) ? $seedMeta['materials_needed'] : 'materials_needed not found',
                    'materials_sent'=>isset($seedMeta['materials_sent']) ? $seedMeta['materials_sent'] : 'materials_sent not found',
                    'attendance'=>isset($seedMeta['attendance']) ? $seedMeta['attendance'] : 'attendance not found',

                    // convert organizer id into name
                    // convert location id into name

                );



                array_push($events, $event);
            }
        }
        return $events;
    }

    /**
     * return metadata of a event from SEED_eventmeta
     */
    function GetSEEDEventMeta( $id )
    {
        $dbName = $this->oApp->GetDBName('wordpress');
        $ra = $this->oApp->kfdb->QueryRA("SELECT volunteer_id, materials_needed, materials_sent, attendance from $dbName.SEED_eventmeta where id=$id");
        //var_dump($ra);
        return $ra;
    }

    /**
     * set SEED_eventmeta table based on array
     */
    private function setEventMeta( $parms, $id )
    {
        $dbName = $this->oApp->DBName('wordpress');

        $kVolunteer= intval(@$parms['volunteer_id']);
        $dbMaterialsNeeded = addslashes(@$parms['materials_needed']);
        $dbMaterialsSent   = addslashes(@$parms['materials_sent']);
        $nAttendance = intval(@$parms['attendance']);

        $exist = $this->oApp->kfdb->Query1("SELECT id FROM $dbName.SEED_eventmeta where id=$id");
        if( $exist ) { // if there is already a database entry
            var_dump("exist");
            $this->oApp->kfdb->Execute("UPDATE $dbName.SEED_eventmeta
                                        SET volunteer_id=$kVolunteer, materials_needed='$dbMaterialsNeeded', materials_sent='$dbMaterialsSent', attendance=$nAttendance
                                        WHERE id=$id");
        }
        else { // create new row
            $this->oApp->kfdb->Execute("INSERT INTO $dbName.SEED_eventmeta (id, volunteer_id, materials_needed, materials_sent, attendance) VALUES
                                        ($id, $kVolunteer, '$dbMaterialsNeeded', '$dbMaterialsSent', $nAttendance)");
        }
    }

    /**
     * add event to database table
     * takes array of params from spreadsheet
     * format array and use MEC to save event
     */
    function AddEventToDB( $parms ) {

//        include("../wp-content/plugins/modern-events-calendar-lite/modern-events-calendar-lite.php");
        $oMEC = new MEC_main();


        $raEvent = []; // array to give to MEC

        $id = isset($parms['id']) ? $parms['id'] : null;

        $raEvent['title'] = isset($parms['title']) ? $parms['title'] : 'title not found';
        //$raEvent['mec_location_id'] = isset($parms['location_name']) ? $parms['location_name'] : 'location not found';
        $raEvent['mec_organizer_id'] = isset($parms['organizer_id']) ? $parms['organizer_id'] : 'organizer_id not found';

        if(isset($parms['time_start'])){
            $time_start = new DateTime($parms['time_start']);
            $raEvent['start_time_hour'] = $time_start->format('h');
            $raEvent['start_time_minutes'] = $time_start->format('i');
            $raEvent['start_time_ampm'] = $time_start->format('a');
        }
        if(isset($parms['time_end'])){
            $time_start = new DateTime($parms['time_end']);
            $raEvent['end_time_hour'] = $time_start->format('h');
            $raEvent['end_time_minutes'] = $time_start->format('i');
            $raEvent['end_time_ampm'] = $time_start->format('a');
        }

        $raEvent['start'] = isset($parms['date_start']) ? $parms['date_start'] : 'date_start not found';
        $raEvent['end'] = isset($parms['date_end']) ? $parms['date_end'] : 'date_end not found';
        $raEvent['repeat_status'] = isset($parms['repeat_status']) ? $parms['repeat_status'] : 'repeat not found';
        $raEvent['repeat_type'] = isset($parms['repeat_type']) ? $parms['repeat_type'] : 'repeat not found';
        $raEvent['interval'] = isset($parms['interval']) ? $parms['interval'] : 'interval not found';

        $raEvent['meta'] = array();
        $raEvent['meta']['mec_read_more'] = isset($parms['link_event']) ? $parms['link_event'] : 'mec_read_more not found';
        $raEvent['meta']['mec_more_info'] = isset($parms['link_more_info']) ? $parms['link_more_info'] : 'mec_more_info not found';
        $raEvent['meta']['mec_organizer_id'] = isset($parms['organizer_id']) ? $parms['organizer_id'] : 'mec_organizer_id not found';

        // location is stored separately from events
        // This looks up the location name, or adds it, or returns false if something fails
        if( ($loc_id = $oMEC->save_location(['name'=>@$parms['location_name']])) !== false ) {
            $raEvent['location_id'] = $loc_id;
        }

        $id = $oMEC->save_event($raEvent, $id);

        $this->setEventMeta($parms, $id);

        // update spreadsheet id here

    }

    const SqlCreate_EventMeta = "
        CREATE TABLE SEED_eventmeta (
            id                  INTEGER NOT NULL DEFAULT 0,     # MEC event id
            volunteer_id        INTEGER NOT NULL DEFAULT 0,     # SoD's contact id
            materials_needed    TEXT,
            materials_sent      TEXT,                           # date when materials were sent  YYYY-MM-DD
            attendance          INTEGER                         # follow-up record of how many people attended this event
        );
        ";
}
