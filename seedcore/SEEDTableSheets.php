<?php

/*
 * SEEDTableSheets
 *
 * Copyright 2014-2020 Seeds of Diversity Canada
 *
 * Manage 3-d tabular data as sheets, rows, columns
 *
 * SEEDTableSheets      - data class that manages a 3-d table
 * SEEDTableSheetsFile  - reads/writes XLSX and CSV files
 */

class SEEDTableSheets
/********************
    Manage a set of tables, such as a spreadsheet with multiple sheets

    SheetA
        headA    headB    headC
        valA1    valB1    valC1
        valA2    valB2    valC2
    SheetB
        headA    headB    headC
        valA1    valB1    valC1
        valA2    valB2    valC2

    Data is stored like this:
        SheetA => [ [headA=>valA1, headB=>valB1, headC=>valC1]
                    [headA=>valA2, headB=>valB2, headC=>valC2]
                  ],
        SheetB => [ [headA=>valA1, headB=>valB1, headC=>valC1]
                    [headA=>valA2, headB=>valB2, headC=>valC2]
                  ]

    Parms:
        header-required : header labels required in top row
        header-optional : header labels optional in top row
        charset         : charset of the in-memory data, default cp1252
 */
{
    private $raConfig;
    private $raSheets = array();

    function __construct( $raConfig = array() )
    {
        $this->raConfig = $raConfig;
        if( !isset($this->raConfig['charset']) ) $this->raConfig['charset'] = "cp1252";
    }

    function GetSheetList()     { return( array_keys($this->raSheets) ); }
    function GetNRows( $sheet ) { return( count($this->GetSheet( $sheet )) ); }
    function GetNCols( $sheet ) { return( count($this->GetRow( $sheet, 0 )) ); }  // implementation must make this true

    /* Sheets are named.
     * Rows and cols are numbered origin-0
     */
    function GetSheet( $sheet )            { return( @$this->raSheets[$sheet] ?: array() ); }
    function GetRow( $sheet, $row )        { return( @$this->raSheets[$sheet][$row] ?: array() ); }
    function GetCell( $sheet, $row, $col ) { return( @$this->raSheets[$sheet][$row][$col] ?: null ); }

    function GetCharset()                  { return( $this->raConfig['charset'] ); }

    function LoadSheet( $sheet, $raTable, $raParms = array() )
    /*********************************************************
        Given a 2-d table with a header row, store the data in raSheets format

            headA   headB   headC
            valA1   valB1   valC1
            valA2   valB2   valC2

            to

            [ [headA=>valA1, headB->valB1, headC->valC1],
              [headA=>valA2, headB->valB2, headC->valC2]
            ]

            raParms: headers_required = array of header col names that must exist (order does not matter)
                     headers_optional = array of header col names to load if they exist
                     charset-input    = (optional) charset of the input data. If different than sheet's charset, iconv

            If headers_required/headers_optional defined, only those are loaded; else all cols are loaded
     */
    {
        $this->raSheets[$sheet] = array();

        // iconv strings if both the input and the sheet have a charset, and they're different
        $bIconv = ($ci = @$raParms['charset-input']) && $this->GetCharset() && $ci != $this->GetCharset();

        $raHead = $raTable[0];
        // only load columns for defined header names; if both undefined or empty load all columns
        $raHeadersToLoad = array_merge( @$raParms['headers_required'] ?: array(),
                                        @$raParms['headers_optional'] ?: array() );

        for( $r = 1; $r < count($raTable); ++$r ) {
            $ra = array();
            for( $j = 0; $j < count($raHead); ++$j ) {
                if( !$raHead[$j] )                                                        continue; // skip columns with blank headers
                if( count($raHeadersToLoad) && !in_array($raHead[$j], $raHeadersToLoad) ) continue; // skip columns not in headers list

                $ra[$raHead[$j]] = $raTable[$r][$j];
            }

            if( $bIconv ) $ra = SEEDCore_CharsetConvert( $ra, $raParms['charset-input'], $this->GetCharset() );

            $this->raSheets[$sheet][] = $ra;
        }

        return( $this->raSheets[$sheet] );
    }


    static function ValidateBeforeLoad( $raTable, $raParms )
    /*******************************************************
        With arguments identical to LoadSheet(), verify that the load makes sense
     */
    {
        $bOk = false;
        $sErrMsg = "";

        if( isset($raParms['headers-required']) ) {
            foreach( $raParms['headers-required'] as $head ) {
                // strict mode is necessary so (string)"colname" does not match (int)0, which it does otherwise
                if( !in_array( $head, $raTable[0], true ) ) {
                    $sErrMsg = "The first row must have these names (in any order):<br/>".self::SampleHead( $raParms );
                    goto done;
                }
            }
        }
        $bOk = true;

        done:
        return( array($bOk,$sErrMsg) );
    }

    static function SampleHead( $raParms )
    /*************************************
        Given header_required and/or header_optional, show what a valid header would look like
     */
    {
        $s = "<table class='table' border='1'><tr>";
        if( isset($raParms['headers-required']) ) {
            $s .= SEEDCore_ArrayExpandSeries( $raParms['headers-required'], "<th>[[]]</th>" );
        }
        if( isset($raParms['headers-optional']) ) {
            $s .= SEEDCore_ArrayExpandSeries( $raParms['headers-optional'], "<th>[[]] <span style='font-size:8pt'>(optional)</span></th>" );
        }
        $s .= "</tr></table>";

        return( $s );
    }
}

include_once( "SEEDXLSX.php" );

class SEEDTableSheetsFile
/************************
    Read/write SEEDTableSheets from/to files
 */
{
    private $raConfig;

    function __construct( $raConfig = array() )
    {
        $this->raConfig = $raConfig;
    }

    function LoadFromFile( $filename, $raParms = array() )
    /*****************************************************
        Read a file and return a SEEDTableSheets

        $raParms:
            fmt           = xls (default) | csv
            charset-file  = utf-8 (default) | cp1252                                  ; not used for xls because it has to be utf-8
            charset-sheet = charset of the output SEEDTableSheets
            sheets        = array of the sheets to load (by name or 1-origin number)  ; default all
            bBigFile      = use SEEDXlsReadBigFile instead of SEEDXlsRead
     */
    {
        $ok = false;
        $sErr = "";

        $raParms = $this->normalizeParms($raParms);

        /* Charset conversion is implemented:
         *     csv - the sheet knows its charset, the file charset is passed to LoadSheet, so the sheet converts its input
         *     xls - the sheet knows its charset, the file is always utf-8 which is passed to LoadSheet, and the sheet converts its input
         */
        $oSheets = new SEEDTableSheets( ['charset'=>$raParms['charset-sheet']] );

        if( @$raParms['fmt'] == 'csv' ) {
            list($ok,$sErr) = $this->loadFromCSV( $oSheets, $filename, $raParms );
        } else {
            list($ok,$sErr) = $this->loadFromXLSX( $oSheets, $filename, $raParms );
        }

        if( !$ok )  $oSheets = null;

        return( [$oSheets,$sErr] );
    }

    function WriteToFile( $oSheets, $filename, $raParms = array() )
    /**************************************************************
        Write the given SEEDTableSheets to a file

        $raParms:
            fmt          = xls (default) | csv
            charset-file = utf-8 (default) | cp1252                                   ; not used for xls because it has to be utf-8
            sheets       = array of the sheets to write (by name or 1-origin number)  ; default all for xls, 1 for csv
     */
    {
        $ok = false;
        $sErr = "";

        $raParms = $this->normalizeParms($raParms);

        if( @$raParms['fmt'] == 'csv' ) {
            list($ok,$sErr) = $this->writeToCSV( $oSheets, $filename, $raParms );
        } else {
            list($ok,$sErr) = $this->writeToXLSX( $oSheets, $filename, $raParms );
        }

        return( array($ok,$sErr) );
    }

    function normalizeParms( $raParms )
    {
        // to facilitate testing (input_charset == output_charset) reduce the synonyms
        $charsets = ['utf8'   => ['utf8','utf-8'],
                     'cp1252' => ['cp1252','cp-1252','windows1252','windows-1252']
                    ];
        foreach( $charsets as $normal => $raSyn ) {
            foreach( ['charset-file', 'charset-sheet']  as $p ) {
                if( isset($raParms[$p]) && in_array( strtolower($raParms[$p]), $raSyn ) )  $raParms[$p] = $normal;
            }
        }

        $raParms['fmt'] = SEEDCore_ArraySmartVal( $raParms, 'fmt', ['xls','csv'] );
        switch( $raParms['fmt'] ) {
            case 'xls':
                $raParms['charset-file'] = 'utf8';
                break;
            case 'csv':
                $raParms['charset-file'] = SEEDCore_ArraySmartVal( $raParms, 'charset-file', ['utf8','cp1252'] );
                $raParms['sheets'] = @$raParms['sheets'] ? [$raParms['sheets'][0]] : ['Sheet1'];    // just the first sheet name
                break;
        }
        if( !isset($raParms['charset-sheet']) )  $raParms['charset-sheet'] = 'utf8';

        return( $raParms );
    }

    function loadFromCSV( SEEDTableSheets $oSheets, $filename, $raParms )
    /********************************************************************
        Read csv or tab-delimited file into the first sheet of $oSheets.
        The first row is a header row, whose labels are converted into the keys of SEEDTableSheets data format.

        Skip blank lines (don't store them).

        $raParms:
            bTab (default:false)    = same as (sDelimiter="\t", sEnclosure='', sEscape='\')
            sDelimiter              = single char separating fields
            sEnclosure              = single char before and after fields (not required if not necessary)
            sEscape                 = single char to escape delimiter and enclosure chars
            charset-file            = charset of the input file
     */
    {
        $ok = false;
        $sErr = "";

        $nCols = 0;
        $raRows = [];

        if( !($f = @fopen( $filename, "r" )) ) {
            $sErr = "Cannot open $filename<br/>";
            goto done;
        }

        /* if bTab is not set use the first row to try to determine the format
         */
        if( isset($raParms['bTab']) ) {
            $bTab = $raParms['bTab'];
        } else {
            $line = fgets($f);
            $bTab = ( strpos( $line, "\t" ) !== false );
            rewind( $f );
        }


        if( !$bTab ) {
            $sDelimiter = @$raParms['sDelimiter'] ?: ",";
            $sEnclosure = @$raParms['sEnclosure'] ?: "\"";
        } else {
            // not used below
            //$sDelimiter = @$raParms['sDelimiter'] ?: "\t";
            //$sEnclosure = @$raParms['sEnclosure'] ?: "";
        }
        $sEscape = @$raParms['sEscape'] ?: "\\";

        while( !feof( $f ) ) {
            if( $bTab ) {
                // fgetcsv doesn't seem to like a blank sEnclosure, so here it's implemented our way.
                $s = fgets( $f );
                $s = rtrim( $s, " \r\n" );    // fgets retains the linefeed
                if( !strlen( $s ) ) continue;
                $raFields = explode( "\t", $s );
            } else {
// escape parm is available since PHP 5.3 -- try it out
                $raFields = fgetcsv( $f, 0, $sDelimiter, $sEnclosure ); //, $sEscape );
                if($raFields == null)   break;     // eof or error
                if($raFields[0]===null) continue;  // blank line
            }
//var_dump($raFields);

            $nCols = max( $nCols, count($raFields) );

            $raRows[] = $raFields;
        }

        fclose( $f );


        /* Fill unset values in the 2D array.
         * If there were missing column values at the ends of rows, especially with longer rows later, some cells will be !isset
         */
        for( $i = 0; $i < count($raRows); ++$i ) {
            while( count($raRows[$i]) < $nCols ) {
                $raRows[$i][] = '';
            }
        }
//var_dump($raRows);
        list($ok,$sErr) = SEEDTableSheets::ValidateBeforeLoad( $raRows, $raParms );
        if( $ok ) {
            // the sheet will convert its input if its own charset is different than 'charset-input'
            $oSheets->LoadSheet( $raParms['sheets'][0], $raRows, $raParms + ['charset-input'=>$raParms['charset-file']] );
        }

        done:
        return( [$ok,$sErr] );
    }

    function writeToCSV( $oSheets, $filename, $raParms )
    {
        $ok = false;
        $sErr = "";

// use SEEDXlsx_WriteFileCSV() to write the first sheet

        return( array($ok,$sErr) );
    }

    function loadFromXLSX( SEEDTableSheets $oSheets, $filename, $raParms )
    {
        $ok = false;
        $sErr = "";

        // SEEDXLSX could convert the returned data into the sheet's preferred charset but the sheet can
        // also do that. For symmetry with CSV, we get the data in the spreadsheet's native utf-8 and tell
        // that to LoadSheet(). The oSheet was created with the preferred output charset so it will do the right thing.
        //$raParms['sCharsetOutput'] = $oSheets->GetCharset();
        $raParms['sCharsetOutput'] = 'utf-8';   // reuse $raParms because it can also contain other SEEDXLSX parms.

        if( @$raParms['bBigFile'] ) {
            // the caller thinks this is going to be a big file so get it in chunks and return the data of the first sheet
            $oXLS = new SEEDXlsReadBigFile();
            if( !($data = $oXLS->LoadFile( $filename, $raParms )) ) {
                $sErr = "Could not load file $filename";
                goto done;
            }
            $sSheetName = "Sheet1";

            list($ok,$sErr) = SEEDTableSheets::ValidateBeforeLoad( $data, $raParms );
            if( $ok ) {
                $oSheets->LoadSheet( $sSheetName, $data, $raParms + ['charset-input'=>'utf-8'] );
            }
        } else {
            $oXLS = new SEEDXlsRead();
            if( !$oXLS->LoadFile( $filename ) ) {
                $sErr = "Could not load file $filename";
                goto done;
            }

            for( $i = 0; $i < $oXLS->GetSheetCount(); ++$i ) {
                $data = $oXLS->GetSheetData( $i, $raParms );
                $sSheetName = $oXLS->GetSheetName( $i );
                if( $i == 0 ) {
                    // validation is only defined in raParms for the first sheet -- extend this
                    list($ok,$sErr) = SEEDTableSheets::ValidateBeforeLoad( $data, $raParms );
                    if( !$ok ) goto done;
                }

                $oSheets->LoadSheet( $sSheetName, $data, $raParms + ['charset-input'=>'utf-8'] );
            }

            $ok = true;
        }

        done:
        return( [$ok,$sErr] );
    }

    function writeToXLSX( $oSheets, $filename, $raParms )
    {
        $ok = false;
        $sErr = "";


        return( array($ok,$sErr) );
    }
}


function SEEDTableSheets_LoadFromFile( $filename, $raParms = array() )
/*********************************************************************
    Read a spreadsheet file, return a SEEDTableSheets object

    raSEEDTableSheetsFileParms = the parms for SEEDTableSheetsFile()
    raSEEDTableSheetsLoadParms = the parms for SEEDTableSheetsFile::LoadFromFile()
 */
{
    $bOk = false;
    $sErr = "";
    $raRows = array();

    $oFile = new SEEDTableSheetsFile( @$raParms['raSEEDTableSheetsFileParms'] );

    list($oSheets,$sErr) = $oFile->LoadFromFile( $filename, @$raParms['raSEEDTableSheetsLoadParms'] );

    return( [$oSheets, $sErr] );
}


function SEEDTableSheets_LoadFromUploadedFile( $fileIndex, $raParms )
/********************************************************************
    Read a spreadsheet file that was uploaded as $FILE[$fileIndex]
 */
{
    $oSheets = null;
    $sErr = "";

    $f = @$_FILES[$fileIndex];
    if( $f && !@$f['error'] ) {
        list($oSheets,$sErr) = SEEDTableSheets_LoadFromFile( $f['tmp_name'], $raParms );
    } else {
        $sErr = "The upload was not successful. ";
        if( $f['size'] == 0 ) {
            $sErr .= "No file was uploaded.  Please try again.";
        } else if( !isset($f['error']) ) {
            $sErr .= "Something went wrong but no error was recorded.  Please tell Bob.";
        } else {
            $sErr .= "Please tell Bob that error # ${f['error']} was reported.";
        }
    }
    return( [$oSheets,$sErr] );
}

