<?php

/* xlsupload
 *
 * Copyright 2014-2020 Seeds of Diversity Canada
 *
 * Upload and download the contents of table seeds2.xlsupload to/from a spreadsheet.
 * This is useful for transferring data conveniently from database relations to files.
 *
 * e.g. 1) On the database create table xlsupload and copy data there; use this script to download it to a spreadsheet.
 *      2) Put data in a spreadsheet; upload to xlsupload table (created automatically) and then manipulate in the database.
 */

$oApp = SEEDConfig_NewAppConsole( ['db'=>'seeds2', 'sessPermsRequired'=>''] );  // login required on seeds2, but no particular perms

$s = "";

switch( ($cmd = SEEDInput_Str( 'cmd' )) ) {
    case 'upload':    // use PHPExcel to upload any spreadsheet file to db table
    case 'upload':    // use native code to upload a csv file to db table (because large files blow up PHPExcel)
        $fileFmt = SEEDInput_Str( 'fileFmt' );
        $charset = SEEDInput_Str( 'charset' );

        $s .= uploadFile( $oApp, $charset, ($fileFmt == 'csv') );
        break;

    case 'download':
        $fileFmt = SEEDInput_Str( 'fileFmt' );
        $charset = SEEDInput_Str( 'charset' );

        if( $fileFmt == 'csv' ) {
            // downloadCSV( $oApp, $charset );
            $oApp->oC->AddErrMsg( "CSV download not implemented" );
            goto showPage;
        } else {
            downloadXLS( $oApp, $charset );
        }
        break;    // function either outputs xlsx file and exits, or sets console error message and returns

    default:
        showPage:
        $s .= "<style>"
             .".ctrl {border:1px solid #aaa;margin-bottom:20px;padding:10px;}"
             ."</style>";

        $s .= "<div class='ctrl'>"
            ."Upload any spreadsheet to 'xlsupload' using PHPSpreadsheet - the top row will be used as keys<br/><br/>"
            ."<form action='".$oApp->PathToSelf()."' method='post' enctype='multipart/form-data'>"
            ."<input type='hidden' name='MAX_FILE_SIZE' value='10000000' />"
            ."<input type='file' name='uploadfile'/>"
            ."<select name='charset'>"
                ."<option value='fileUtf8-dbWin'>Windows-1252</option>"
                ."<option value='fileUtf8-dbUtf8'>UTF-8</option>"
                ."</select> Charset in database table<br/>"
            ."<input type='hidden' name='fileFmt' value='xlsx' />"
            ."<input type='hidden' name='cmd' value='upload' />"
            ."<input type='submit' value='Upload'/>"
            ."</form>"
            ."</div>";

        $s .= "<div class='ctrl'>"
            ."Upload a csv file to 'xlsupload' using native code (e.g. in case PHPSpreadsheet doesn't work) - the top row will be used as keys<br/><br/>"
            ."<form action='".$oApp->PathToSelf()."' method='post' enctype='multipart/form-data'>"
            ."<input type='hidden' name='MAX_FILE_SIZE' value='10000000' />"
            ."<input type='file' name='uploadfile'/>"
            ."<select name='charset'>"
                ."<option value='fileWin-dbWin'>file Windows-1252 -&gt; db Windows-1252</option>"
                ."<option value='fileWin-dbUtf8'>file Windows-1252 -&gt; db UTF-8</option>"
                ."<option value='fileUtf8-dbWin'>file UTF-8 -&gt; b Windows-1252</option>"
                ."<option value='fileUtf8-dbUtf8'>file UTF-8 -&gt; db UTF-8</option>"
                ."</select> Charset in file/database<br/>"
            ."<input type='hidden' name='fileFmt' value='csv' />"
            ."<input type='hidden' name='cmd' value='upload' />"
            ."<input type='submit' value='Upload'/>"
            ."</form>"
            ."</div>";

        $s .= "<div class='ctrl'>"
            ."Download 'xlsupload' as an xlsx file using PHPSpreadsheet<br/><br/>"
            ."<form action='".$oApp->PathToSelf()."' method='post'>"
            ."<select name='charset'>"
                ."<option value='dbWin-fileUtf8'>Windows-1252</option>"
                ."<option value='dbUtf8-fileUtf8'>UTF-8</option>"
                ."</select> Charset in database table<br/>"
            ."<input type='hidden' name='fileFmt' value='xlsx' />"
            ."<input type='hidden' name='cmd' value='download' />"
            ."<input type='submit' value='Download'/>"
            ."</form>"
            ."</div>";

        $s .= "<div class='ctrl'>"
            ."Download 'xlsupload' as a csv file using native code<br/><br/>"
            ."<form action='".$oApp->PathToSelf()."' method='post'>"
            ."<select name='charset'>"
                ."<option value='dbWin-fileWin'>db table Windows-1252 -&gt; file Windows-1252</option>"
                ."<option value='dbWin-fileUtf8'>db table Windows-1252 -&gt; file UTF-8</option>"
                ."<option value='dbUtf8-fileWin'>db table UTF-8 -&gt; file Windows-1252</option>"
                ."<option value='dbUtf8-fileUtf8'>db table UTF-8 -&gt; file UTF-8</option>"
                ."</select> Charset in database/file<br/>"
            ."<input type='hidden' name='fileFmt' value='csv' />"
            ."<input type='hidden' name='cmd' value='download' />"
            ."<input type='submit' value='Download'/>"
            ."</form>"
            ."</div>";

        break;
}

echo Console02Static::HTMLPage( $oApp->oC->DrawConsole($s), "", "EN", [] );



function uploadFile( SEEDAppConsole $oApp, $charset, $bCSV )
{
    include_once( SEEDCORE."SEEDTableSheets.php" );


    $s = "";

    // charset is one of fileWin-dbWin, fileWin-dbUtf8, fileUtf8-dbWin, fileUtf8-dbUtf8
    $sCharsetFile = SEEDCore_StartsWith( $charset, 'fileWin' ) ? "Windows-1252" : "utf-8";
    $sCharsetTable = SEEDCore_EndsWith( $charset, 'dbWin' ) ? "Windows-1252" : "utf-8";

    $parms = [ // parms for SEEDTableSheetsFile constructor
               'raSEEDTableSheetsFileParms' => [],
               // parms for LoadFromFile()
               'raSEEDTableSheetsLoadParms' => ['fmt' => ($bCSV ? 'csv' : 'xls'),
                                                'charset-file' => $sCharsetFile,
                                                'charset-sheet' => $sCharsetTable,
                                                //'sheets' => [1]                 // just the first sheet
                                               ]
             ];

    list($oSheets,$sErr) = SEEDTableSheets_LoadFromUploadedFile( 'uploadfile', $parms );
    if( !$oSheets ) die( "Error: $sErr" );

    $sheetname = $oSheets->GetSheetList()[0];
    $raRows = $oSheets->GetSheet($sheetname);
    if( !count($raRows) )  die( "Empty spreadsheet" );

    $keys = array_keys($raRows[0]);
    //var_dump($keys,$raData);

    $oApp->kfdb->SetDebug(2);

    /* Drop the db-table if it was left over from last time
     */
    $oApp->kfdb->Execute( "DROP TABLE IF EXISTS xlsupload" );

    /* Create a db-table with the required fields
     */
    $raK = array();
    foreach( $keys as $k ) {
        // the purpose of addslashes is to prevent sql insertion attacks; the sql will still fail if a field name contains quotes or other weird characters
        $raK[] = addslashes($k)." text";
    }
    $oApp->kfdb->Execute( "CREATE TABLE xlsupload (".implode(",",$raK).");" );

    // the insert statement is only interesting if something goes wrong
    $oApp->kfdb->SetDebug(1);


    /* Generate an INSERT statement to fill the db-table.
     * A single INSERT is efficent:  INSERT INTO xlsupload (keys) VALUES (values-row-1),(values-row-2),(values-row-3)...
     */
    $raK = array();
    $raR = array();
    foreach( $keys as $k ) {
        $raK[] = addslashes($k);
    }
    foreach( $raRows as $ra ) {
        $raV = array();
        foreach( $keys as $k ) {
            $raV[] = "'".addslashes($ra[$k])."'";
        }
        $raR[] = "(".implode(',',$raV).")";
    }
    $oApp->kfdb->Execute( "INSERT INTO xlsupload (".implode(',',$raK).") VALUES ".implode(',',$raR) );

    $s .= "xlsupload is ready with ".$oApp->kfdb->Query1( "SELECT count(*) FROM xlsupload" )." rows!";

    return( $s );
}

function downloadXLS( SEEDAppConsole $oApp, $charset )
{
    include_once( SEEDCORE."SEEDXLSX.php" );

    $raCols = array();
    $raColsRA = $oApp->kfdb->QueryRowsRA( "SHOW COLUMNS FROM xlsupload" );
    foreach( $raColsRA as $ra ) {
        $raCols[] = $ra[0];
    }
    if( !count($raCols) ) {
        $oApp->oC->AddErrMsg( "Table xlsupload not found or has no columns" );
        return;
    }

    // charset is one of dbWin-fileWin, dbWin-fileUtf8, dbUtf8-fileWin, dbUtf8-fileUtf8
    $sCharsetTable = SEEDCore_StartsWith( $charset, 'dbWin' ) ? "Windows-1252" : "utf-8";
    $sCharsetFile = SEEDCore_EndsWith( $charset, 'fileWin' ) ? "Windows-1252" : "utf-8";

    $raRows = $oApp->kfdb->QueryRowsRA( "SELECT ".implode( ',', $raCols )." FROM xlsupload", KEYFRAMEDB_RESULT_ASSOC );

    $oXLSX = new SEEDXlsWrite();

    // row 0 is the column names
    $oXLSX->WriteRow( 0, 1, $raCols );

    $iRow = 2;
    foreach( $raRows as $ra ) {
        if( $sCharsetTable != $sCharsetFile ) {
            $ra = SEEDCore_CharsetConvert( $ra, $sCharsetTable, $sCharsetFile );    // convert array of strings
        }
        $oXLSX->WriteRow( 0, $iRow++, $ra );
    }

    $oXLSX->OutputSpreadsheet();
    exit;
}
