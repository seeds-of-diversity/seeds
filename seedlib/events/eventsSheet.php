<?php

/* eventsSheet.php
 *
 * Copyright (c) 2022 Seeds of Diversity Canada
 *
 * Store and retrieve events data in a Google sheet
 *
 * You have to provide or define
 *      GOOGLE_AUTH_CONFIG_FILENAME     the json file that Google gives you to authorize your use of its services
 *      EVENTS_GOOGLE_SPREADSHEET_ID    the id of the spreadsheet where you store events data
 */

include_once( SEEDLIB."google/GoogleSheets.php" );

class EventsSheet
{
    private $oApp;
    private $oGoogleSheet;

    function __construct( SEEDAppConsole $oApp, $raParms = [] )
    {
        $this->oApp = $oApp;


        if( !($idSpreadsheet = @$raParms['googleAuthConfigFilename']) ) {
            if( defined('GOOGLE_AUTH_CONFIG_FILENAME') ) {
                $fnameGoogleAuthConfig = GOOGLE_AUTH_CONFIG_FILENAME;
            } else {
                die( "Google Auth Config json file not defined" );
            }
        }

        if( !($idSpreadsheet = @$raParms['idSpreadsheet']) ) {
            if( defined('EVENTS_GOOGLE_SPREADSHEET_ID') ) {
                $idSpreadsheet = EVENTS_GOOGLE_SPREADSHEET_ID;
            } else {
                die( "Events Google spreadsheet not defined" );
            }
        }

        $this->oGoogleSheet = new SEEDGoogleSheets_NamedColumns(
            ['appName' => 'My PHP App',
                'authConfigFname' => SEEDCONFIG_DIR."/".$fnameGoogleAuthConfig,
                'idSpreadsheet' => $idSpreadsheet
            ] );
    }

    function GetEventsFromSheet( $nameSheet = 'Current' )
    /****************************************************
        Read event data from Google sheet
     */
    {
        $raColumns = $this->oGoogleSheet->GetColumnNames($nameSheet);
        $raEvents = $this->oGoogleSheet->GetRows($nameSheet);

        foreach( $raEvents as $k=>$v ) {
            // pad the end of $v (if right-hand cells are blank the sheet will not return values for them)
            while( count($raColumns) > count($v) ) { $v[] = ''; }
            $raEvents[$k] = array_combine( $raColumns, $v );
        }
        return( $raEvents );
    }

}
