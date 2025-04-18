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
// should prepend nameSheet! to the range
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
     * @param int $row - row number order-1
     * @param array $values - 1D array of values to set
     * @return response
     */
    function SetRow( String $nameSheet, int $row, $values )
    {
        // docs say that null values leave the original data intact, but other examples on SO show otherwise, and null values actually cause a json error in the library we're using
        foreach( $values as &$v ) {
            if( $v === null ) $v = '';
        }
        unset($v);

        $requestBody = new Google_Service_Sheets_ValueRange();
        $requestBody->values = [$values];   // values are always provided as a 2D array

        $colEnd = self::NumberToColumnLetter(count($values)); // find last column index
        $response = $this->oService->spreadsheets_values->update($this->idSpreadsheet, "{$nameSheet}!A{$row}:{$colEnd}{$row}", $requestBody, ['valueInputOption' => 'USER_ENTERED']);

        return( $response );
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
    static function NumberToColumnLetter( int $columnNumber )
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
    {var_dump($nameSheet);
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

    /**
     * Read all rows of a sheet, returning an array whose keys match the column header names.
     * @param String $nameSheet - name of the sheet to read
     * @return array - 2D array of values where keys match column names
     */
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
     * Write a row of values to the sheet, from an array whose keys match the column header names, in any order.
     * @param String $nameSheet - name of the sheet
     * @param int $row row number
     * @param array $values 1D array of values where keys match column names
     */
    function SetRowWithNamedColumns( string $nameSheet, int $row, array $values )
    {
        // Place values in $ra in the same order as the columns in the sheet.
        $ra = [];
        foreach( $this->GetColumnNames($nameSheet) as $col ) {
            $ra[] = isset($values[$col]) ? $values[$col] : '';
        }

        $this->SetRow($nameSheet, $row, $ra);
    }

    /**
     * Write values to specific named cells in a given row.
     *
     * @param String $nameSheet - name of the sheet to read
     * @param int    $row - row number to write
     * @param array  [colname => value, colname => value, ...]
     */
    function WriteCellsWithNamedColumns( string $nameSheet, int $row, array $raCells )
    {
        $raColNames = $this->GetColumnNames($nameSheet);
        foreach( $raCells as $colname => $v ) {
            // Get the index number of the named col, convert to A1 notation
//TODO: a smarter implementation could combine adjacent cells
            if( ($i = array_search($colname, $raColNames)) === false ) continue;
            $range = self::NumberToColumnLetter($i+1).$row;
            $this->WriteValues( $nameSheet.'!'.$range, [[$v]] );   // value in 2D array
//var_dump("Writing $v to $range");
        }
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
            $range = self::NumberToColumnLetter( $iCol + 1 );
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
        //var_dump($mapCols);

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
                $this->oGoogleSheet->WriteCellsWithNamedColumns( $this->nameSheet, $iRow, ['sync_note' => "not found in db"] );
                goto do_next;
            }
var_dump($raRow['sync_ts'],$raRow['Timestamp'], strtotime($raRow['Timestamp']));
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
        $writeCells = ['sync_key'=>"", 'sync_ts'=>"", 'sync_note'=>""];  // these are written to the sheet row

        // If there's a validation function, use that to test whether the row should be copied. If not, store a note.
        if( ($fn = @$this->raConfig['fnValidateSheetRow']) ) {
            list($ok,$raRow,$note) = call_user_func($fn, $raRow);
            $writeCells['sync_note'] = $note;
            if( !$ok ) {
                goto done;
            }
        }

        foreach( $this->mapCols as $raMap ) {
            if( $raMap['dbCol'] ) { // some map rows are used in the validation process and don't have dbCols
                $kfr->SetValue( $raMap['dbCol'], @$raRow[$raMap['sheetCol']] );
            }
        }
        $kfr->SetValue( 'tsSync', time() );
        if( $kfr->PutDBRow() ) {
            $writeCells['sync_key'] = $kfr->Key();
            $writeCells['sync_ts'] = $kfr->Value('tsSync');
            $writeCells['sync_note'] = "synced".$writeCells['sync_note'];
            $ok = true;
        }

        done:
        if( $ok || $writeCells['sync_note'] ) {
            $this->oGoogleSheet->WriteCellsWithNamedColumns( $this->nameSheet, $iRow, $writeCells );
        }
    }

/* To make a Google Sheet push changes to a sync process:

   Create an http-activated process using this class (example url below).
   Put the AppScript below in your google sheet.
   Bind the script to an installable trigger on an Edit action. Don't use onEdit because as a simple trigger
   it doesn't have permission to use UrlFetchApp.

function MyOnEdit(e)    // or just use onEdit if you don't need to use UrlFetchApp to push the change
{
  // When a cell is changed, reset the sync_ts column to indicate a dirty row.

  var sheet = e.range.getSheet();
  var row = e.range.getRowIndex();  // the (first) changed row
  var col = e.range.getColumn();    // the (first) changed col

  // find the sync_key,sync_ts columns
  var colKey = 0;
  var colTS = 0;
  for( i=1; i<sheet.getLastColumn(); i++ ) {
    if( sheet.getRange(1,i).getValue() == "sync_key") { colKey = i; }
    if( sheet.getRange(1,i).getValue() == "sync_ts")  { colTS = i; }
  }

  // Only trigger dirty for rows and cols of primary data (below header row and left of sync_key)
  if( colKey && (row > 1 && col < colKey) ) {
    // mark the row by blanking the sync_ts
    sheet.getRange(row,colTS).setValue('');
//      var response = UrlFetchApp.fetch("https://seeds.ca/app/q2/?qcmd=ev-syncSheet");
  }
}

*/

}
