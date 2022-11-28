<?php

/* GoogleSheets.php
 *
 * Copyright (c) 2020-2022 Seeds of Diversity
 *
 * Author: Eric Wildfong, Bob Wildfong
 *
 * Read/write google sheets data
 *
 * 1. Enable the Google Sheets API in your Google account and check the quota for your project
 *    at https://console.developers.google.com/apis/api/sheets
 * 2. Create a Service Account in your Google account, and download a key in a json file. This is authConfigFname below.
 *    Use of spreadsheet will be billed to this account.
 * 3. Install the PHP client library with Composer. See https://github.com/google/google-api-php-client.
 * 4. Access spreadsheets by their id found in their url. https://docs.google.com/spreadsheets/d/{ ***this part*** }/edit
 *    You can access any spreadsheet that has share-by-link activated, because the id is the link.
 *    You can also access any spreadsheet that has the service account's email added to its shared people list.
 */

class SEEDGoogleSheets
{
    public  $oService;
    protected $idSpreadsheet;
    private $raValuesCache = null;      // it's also not a bad assumption that Google_Service_Sheets class caches this, so maybe don't bother
    private $raRangeCache = null;

    function __construct( $raConfig )
    /********************************
        appName         = application name (appears in some REST http headers but not used in service access)
        authConfigFname = filename of the secret auth file that google gives you when you create a service account
        idSpreadsheet   = the id of the Google Sheet to open https://docs.google.com/spreadsheets/d/{ ***this part*** }/edit
     */
    {
        $oClient = new \Google_Client();
        $oClient->setApplicationName($raConfig['appName']);
        $oClient->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
        $oClient->setAccessType('offline');     // only relevant in OAuth, not used in service access
        $oClient->setAuthConfig($raConfig['authConfigFname']);
        //$oClient->setSubject( getenv( 'GOOGLE_SERVICE_ACCOUNT_NAME' ) );
        $this->oService = new Google_Service_Sheets( $oClient );
        $this->idSpreadsheet = $raConfig['idSpreadsheet'];
    }

    /**
     * Write values to the spreadsheet
     * Requires one of scopes: https://www.googleapis.com/auth/drive (least restrictive),https://www.googleapis.com/auth/drive.file (more restrictive),https://www.googleapis.com/auth/spreadsheets (most restrictive)
     * @param String $range - A1 notation containing the area in the spreadsheet to write the data to (ex. A1:B3)
     * @param array $values - 2D array of values to write to the spreadsheet. Null values are ignored use empty string to clear a cell.
     * @throws Exception - If $values is not a 2D array. (will cause google to return 400)
     * @throws Exception - If the underlying call to the google API throws an error
     */
    function WriteValues( String $range, array $values )
    {
        foreach($values as $ra){
            if(!is_array($ra)){
                throw new Exception("Expected $values to be a 2D array, non-array value found",400);
            }
        }

        $requestBody = new Google_Service_Sheets_ValueRange();
        $requestBody->values = $values;

        //$response = $service->spreadsheets_values->append($spreadsheetId, $range, $requestBody);
        $response = $this->oService->spreadsheets_values->update($this->idSpreadsheet, $range, $requestBody, ['valueInputOption' => 'USER_ENTERED']);

    //  $response = $this->googleSheets->updateSheet( $values, $range,$spreadsheetId );

        // we are not smart enough to update our cache
        $this->raValuesCache = $this->raRangeCache = null;

        //TODO Process response body and return neccesary information
        return $response;
    }

    /**
     * Read values from the spreadsheet
     * Requires one of scopes: https://www.googleapis.com/auth/drive, https://www.googleapis.com/auth/drive.file, https://www.googleapis.com/auth/drive.readonly, https://www.googleapis.com/auth/spreadsheets, https://www.googleapis.com/auth/spreadsheets.readonly
     * Where the readonly scopes are the most restrictive scopes
     * @param String - A1 notation containing the area in the spreadsheet to read the data from (ex. A1:B3)
     * @return array of values retrieved from spreadsheet.
     * @throws Exception - If the underlying call to the google API throws an error
     */
    function GetValues( String $range ) : array
    {
        if( !$this->raValuesCache ) {
            $response = $this->oService->spreadsheets_values->get($this->idSpreadsheet, $range, ['majorDimension' => 'ROWS']);
            // If the range is empty in the spreadsheet, google returns null for ->values. We want that to be an empty array.
            $this->raValuesCache = $response->values ?: [];
            $this->raRangeCache = $response->range;
            // what else is in the response?
        }

        return( $this->raValuesCache ); // [$response->values, $response->range] );
    }

    /**
     * @param $range - row number
     * @return 1D array of row
     */
    function GetRow( $range ) : array
    {
        if( is_int($range) || ctype_digit($range) ) {
            $response = $this->oService->spreadsheets_values->get($this->idSpreadsheet, "$range:$range");
            $values = $response->getValues()[0];
            return $values;
        }
        return [];
    }

    /**
     *
     * @param $range - column letter
     * @return 1D array of column
     */
    function GetColumn( $range ) : array
    {
        if( ctype_alpha($range) ) {
            $response = $this->oService->spreadsheets_values->get($this->idSpreadsheet, "$range:$range");
            $values = $response->getValues();
            for($i = 0; $i < count($values); $i++){
                $values[$i] = $values[$i][0];
            }
            return $values;
        }
        return [];
    }
    /**
     * set row in spreadsheet
     * @param String $nameSheet - name of the sheet to read
     * @param row number
     * @param values array of values to set
     * @return response
     */
    function SetRow( String $nameSheet, $range, $values )
    {
        $end = $this->NumberToColumnLetter(count($values)); // find last column index
        $values = array($values);
        $requestBody = new Google_Service_Sheets_ValueRange();
        $requestBody->values = $values;
        $response = $this->oService->spreadsheets_values->update($this->idSpreadsheet, "{$nameSheet}!A$range:$end$range", $requestBody, ['valueInputOption' => 'USER_ENTERED']);

        return $response;
    }

    /**
     * Get properties about a range
     * @param String - A1 notation containing the area in the spreadsheet to read the properties from (ex. A1:B3, A:A, Sheetname!A1:Z100, Sheetname)
     * @return array of properties retrieved from spreadsheet.
     * @throws Exception - If the underlying call to the google API throws an error
     */
    function GetProperties( String $range, array $raParms = [] ) : array
    {
        $raOut = ['rowsGrid' => 0,  // current size of spreadsheet incl unused cells
                  'colsGrid' => 0,
                  'rowsUsed' => 0,  // current size of spreadsheet not incl unused cells
                  'colsUsed' => 0
                 ];

        /* These might be cached in Google_Service_Sheets but not sure, so make them optional
         *
         * Could also parse these values out of the $this->raRangeCache string
         */
        if( @$raParms['bGetGridProperties'] ) {
            $response = $this->oService->spreadsheets->get($this->idSpreadsheet, ["ranges" => [$range], "fields" => "sheets(properties(gridProperties(columnCount,rowCount)))"]);
            $gridProperties = $response[0]->getProperties()->getGridProperties();
            $raOut['rowsGrid'] = $gridProperties->getRowCount();
            $raOut['colsGrid'] = $gridProperties -> getColumnCount();
        }

        /* Get the values in the range and count their extent
         */
        $raRows = $this->GetValues($range);
        $raOut['rowsUsed'] = count($raRows);
        foreach( $raRows as $raCols ) {
            $raOut['colsUsed'] = max($raOut['colsUsed'], count($raCols));
        }

        return( $raOut );
    }

    /**
     * converts integer to column letter
     * eg. 1 = A, 2 = B, 27 = AA
     * @param int $columnNumber
     * @return string column letter
     */
    function NumberToColumnLetter( int $columnNumber )
    {
        $columnName = "";

        while ($columnNumber > 0) {
            $modulo = ($columnNumber - 1) % 26; // get current letter
            $columnName = (chr(65 + $modulo)) . $columnName; // convert current letter to ascii
            $columnNumber = intdiv(($columnNumber - $modulo), 26);
        }
        return $columnName;
    }
}


/* Manage Google Sheets that have named columns
 */
class SEEDGoogleSheets_NamedColumns extends SEEDGoogleSheets
{
    function __construct( $raConfig )
    {
        parent::__construct($raConfig);
    }

    /**
     * Get array of the first row, which should be names of the columns
     * @param String $nameSheet - name of the sheet to read
     * @return array of column names
     */
    function GetColumnNames( String $nameSheet ) : array
    {
        $ra = $this->GetValues($nameSheet);   // returns a 2D array of all rows in the sheet
        return( @$ra[0][0] ? $ra[0] : [] );
    }

    /**
     * Get 2D array of the rows (not including the column names) starting at row 2
     * @param String $nameSheet - name of the sheet to read
     * @return array of rows
     */
    function GetRows( String $nameSheet ) : array
    {
        $ra = $this->GetValues($nameSheet);   // returns a 2D array of all rows in the sheet
        array_shift($ra);
        return( $ra );
    }

    function GetRowsWithNamedColumns( string $nameSheet ) : array
    {
        $raOut = [];

        $raColumns = $this->GetColumnNames($nameSheet);

        foreach( $this->GetRows($nameSheet) as $ra ) {
            $row = [];
            for( $i = 0; $i < count($raColumns); ++$i ) {
                $row[$raColumns[$i]] = @$ra[$i];
            }
            $raOut[] = $row;
        }

        return( $raOut );
    }

    /**
     * Get array of the values in the named column starting at row 2.
     *     i.e. $ret[0] is spreadsheet row 2 of the column that has $colname in row 1
     *
     * @param String $nameSheet - name of the sheet to read
     * @return array of column values or null if colname not found in top row
     */
    function GetColumnByName( String $nameSheet, $colname ) : ?array
    {
        $ret = null;

        $raColnames = $this->GetColumnNames( $nameSheet );
        if( ($iCol = array_search($colname, $raColnames, false)) !== false ) {    // $i is the index of colname in the column names
            $range = $this->NumberToColumnLetter( $iCol + 1 );
            // Get one column. Returns 2D array so reduce to 1D array and remove the top row.
            $response = $this->oService->spreadsheets_values->get($this->idSpreadsheet, "$nameSheet!$range:$range");
            $values = $response->getValues();

            $ret = [];
            for($i = 1; $i < count($values); $i++) {
                $ret[$i-1] = $values[$i][0];
            }
        }
        return $ret;
    }

    /**
     * takes in an associative array of values
     * match value with column
     * this function makes sure if values is not ordered the same way as spreadsheet columns, it will still work
     * @param String $nameSheet - name of the sheet to read/write
     * @param $row row number
     * @param $values associative array where the key matches column names
     */
    function SetRowWithAssociativeArray( String $nameSheet, $row, $values )
    {

        $columns = $this->GetColumnNames($nameSheet);
        $ra = [];

        foreach( $values as $k=>$v ) {
            foreach( $columns as $k2=>$v2 ) { // compare each column to $values key

                if( $k == $v2 ) {
                    $ra[$v2] = $v; //fill $ra with correctly ordered values
                }
            }
        }

        $ra = array_values($ra); // convert associative array into normal array
        $this->SetRow($nameSheet, $row, $ra); // set rows
    }
}

class SEEDGoogleSheets_SyncSheetAndDb
/************************************
 */
{
    private $oApp;
    private $raConfig;
    private $sheetColKey  = 'sync_key';     // sheet col name for the row key
    private $sheetColTS   = 'sync_ts';      // sheet col name for the sync timestamp
    private $sheetColNote = 'sync_note';    // sheet col name for the sync note

    private $oGoogleSheet;                  // google sheet to sync
    private $nameSheet;                     // name of sheet to sync
    private $kfrel;                         // db table to sync
    private $mapCols;                       // columns to sync between sheet and db table

    function __construct( SEEDAppDB $oApp, array $raConfig )
    {
        $this->oApp;
        $this->raConfig = $raConfig;
    }

    function DoSync( SEEDGoogleSheets_NamedColumns $oGoogleSheet, string $nameSheet, Keyframe_Relation $kfrel, array $mapCols, array $raParms = [] )
    /***********************************************************************************************************************************************
        Synchronize a GoogleSheet and a database table.
        ** If changes are made to the same row on both, the results of the sync are indeterminate.

        nameSheet   = the name of the sheet in the oGoogleSheet
        kfrel       = the Keyframe_Relation for the db table
        mapCols     = [ ['sheetcol'=>'foo', 'dbcol'=>'foo'], ['sheetcol'=>'bar', 'dbcol'=>'bar'] ]  syncs columns foo and bar across the two data sets
        raParms:
            fnValidateSheetRow = function that returns true if a sheet row contains valid data

        The sheet must have two required columns, which can be hidden to manual users.
            sync_ts  = a timestamp written by this script. This is never in mapCols.
            sync_key = the db key written by this script. It can be defined in mapCols as an alias of _key: otherwise it is called key.

        The sheet may have an optional column
            sync_notes = the synchronization process will put notes here regarding the sync status of each row. This is write-only and human-readable.

        The db table must have one required column
            tsSync  = copy of the timestamp in the script

        1) Sheet row with no key.                       A new sheet row: add to db, write key and ts to sheet.
        2) Sheet row with key but no ts.                An edited sheet row: copy to db, write ts to sheet.     (AppScript blanks the tsSync when a cell is edited)
        3) Sheet row with no corresponding db row.      Was deleted in db: delete in sheet.
        4) Db row with no sheet row, db.tsSync==0.      A new db row: add to sheet, with key and ts.
        4) Db row with no sheet row, db.tsSync!=0       Was deleted in sheet: delete in db.
        5) Db row with db.tsSync > sheet.tsSync         An edited db row: copy to sheet, with ts.
     */
    {
        $this->kfrel = $kfrel;
        $this->mapCols = $mapCols;
        $this->oGoogleSheet = $oGoogleSheet;
        $this->nameSheet = $nameSheet;

        $raColumns = $this->oGoogleSheet->GetColumnNames($nameSheet);
        $raEvents = $this->oGoogleSheet->GetRowsWithNamedColumns($nameSheet);
        //var_dump($raColumns,$raEvents);

        $iRow = 2;
        foreach( $raEvents as $raRow ) {
            $kSync = intval(@$raRow['sync_key']);

            // 1) Sheet row with no sync_key
            if( !$kSync ) {
                $kfr = $this->kfrel->CreateRecord();
                $this->copySheetRowToDb( $raRow, $kfr, $iRow );
                goto do_next;
            }

            if( !($kfr = $this->kfrel->GetRecordFromDBKey($kSync)) ) {
                $raRow['sync_note'] = "not found in db";
                $this->oGoogleSheet->SetRowWithAssociativeArray( $this->nameSheet, $iRow, $raRow );
                goto do_next;
            }

            // 2) Sheet row has key but not sync_ts. This normally means a change has been made on the sheet so copy to db.
            if( !$raRow['sync_ts'] ) {
                $this->copySheetRowToDb( $raRow, $kfr, $iRow );
                goto do_next;
            }


            do_next:
            ++$iRow;
        }

    }

    private function copySheetRowToDb( array $raRow, KeyframeRecord $kfr, int $iRow )
    /*********************************************************************************
        Copy the given sheet row data to the db table, update sync_ts and sync_key as necessary, store sync_note to describe what happened
     */
    {
        $ok = false;
        $note = "";

        // If there's a validation function, use that to test whether the row should be copied. If not, store a note.
        if( ($fn = @$this->raConfig['fnValidateSheetRow']) ) {
            list($ok,$n1) = call_user_func($fn, $raRow);
            if( !$ok ) {
                $note = $n1;
                goto done;
            }
        }

        foreach( $this->mapCols as $raMap ) {
            $kfr->SetValue( $raMap['dbCol'], @$raRow[$raMap['sheetCol']] );
        }
        $kfr->SetValue( 'tsSync', time() );
        if( $kfr->PutDBRow() ) {
            $raRow['sync_key'] = $kfr->Key();
            $raRow['sync_ts'] = $kfr->Value('tsSync');
            $note = "synced";
            $ok = true;
        }

        done:
        if( $note || $ok ) {
            $raRow['sync_note'] = $note;
            $this->oGoogleSheet->SetRowWithAssociativeArray( $this->nameSheet, $iRow, $raRow );
        }
    }
}
