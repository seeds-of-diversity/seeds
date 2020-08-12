<?php

/* GoogleSheets.php
 *
 * Copyright (c) 2020 Seeds of Diversity
 *
 * Author: Eric Wildfong
 *
 * Read/write google sheets data
 *
 * 1. Must enable the Google Sheets API and check the quota for your project at
 *    https://console.developers.google.com/apis/api/sheets
 * 2. Install the PHP client library with Composer. Check installation
 *    instructions at https://github.com/google/google-api-php-client.
 */

class SEEDGoogleSheets
{
    function __construct() {}

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
    function writeValues(String $spreadsheetId, String $range,array $values, $service)
    {
        foreach($values as $ra){
            if(!is_array($ra)){
                throw new Exception("Expected $values to be a 2D array, non-array value found",400);
            }
        }

        $requestBody = new Google_Service_Sheets_ValueRange();
        $requestBody->values = $values;

        $response = $service->spreadsheets_values->append($spreadsheetId, $range, $requestBody);

        //TODO Process response body and return neccesary information
    	return $response;
    }

    /**
     * Read values from the spreadsheet
     * Requires one of scopes: https://www.googleapis.com/auth/drive, https://www.googleapis.com/auth/drive.file, https://www.googleapis.com/auth/drive.readonly, https://www.googleapis.com/auth/spreadsheets, https://www.googleapis.com/auth/spreadsheets.readonly
     * Where the readonly scopes are the most restrictive scopes
     * @param String $spreadsheetId - Id of spreadsheet to read from
     * @param String - A1 notation containing the area in the spreadsheet to read the data from (ex. A1:B3)
     * @param unknown $service - Instance of the google service which has been authorized to read from the given spreadsheet
     * @return array of values retrieved from spreadsheet.
     * @throws Exception - If the underlying call to the google API throws an error
     */
    function getValues(String $spreadsheetId, String $range, $service)
    {
        $response = $service->spreadsheets_values->get($spreadsheetId, $range);

        return $response->values;
    }

    // May be useful in the future to convert an index in an array to the corresponding A1 notation column name
    function indexToColumnName(int $index)
    {
        $COLUMNS = array("A","B","C","D","E",'F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');
        if($index >= count($COLUMNS)){
            return indexToColumnName(intdiv($index,count($COLUMNS))-1) . indexToColumnName($index % count($COLUMNS));
        }
        return $COLUMNS[$index];
    }
}
