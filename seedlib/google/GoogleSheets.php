<?php

/* GoogleSheets.php
 *
 * Copyright (c) 2020-2022 Seeds of Diversity
 *
 * Author: Eric Wildfong
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
    private $idSpreadsheet;
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
     * @param String $spreadsheetId - Id of spreadsheet to write to
     * @param String $range - A1 notation containing the area in the spreadsheet to write the data to (ex. A1:B3)
     * @param array $values - 2D array of values to write to the spreadsheet. Null values are ignored use empty string to clear a cell.
     * @param unknown $service - Instance of the google service which has been authorized to write to the given spreadsheet
     * @return unknown Response Object.
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
            $this->raValuesCache = $response->values;
            $this->raRangeCache = $response->range;
            // what else is in the response?
        }

        return( $this->raValuesCache ); // [$response->values, $response->range] );
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

    }

    /**
     * Get 2D array of the rows (not including the column names) starting at row 2
     * @param String $nameSheet - name of the sheet to read
     * @return array of rows
     */
    function GetRows( String $nameSheet ) : array
    {

    }
}