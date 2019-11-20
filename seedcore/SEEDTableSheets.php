<?php

/*
 * SEEDTableSheets
 *
 * Copyright 2014-2019 Seeds of Diversity Canada
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
        charset         : charset of the in-memory data, default Windows-1252
 */
{
    private $raConfig;
    private $raSheets = array();

    function __construct( $raConfig = array() )
    {
        $this->raConfig = $raConfig;
//        if( !isset($this->raParms['charset']) ) $this->raParms['charset'] = "Windows-1252";
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

            If headers_required/headers_optional defined, only those are loaded; else all cols are loaded
     */
    {
        $this->raSheets[$sheet] = array();

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
            $this->raSheets[$sheet][] = $ra;
        }

        return( $raSheetsOut );
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
                if( !in_array( $head, $raRows[0] ) ) {
                    $sErrMsg = "The first row must have the labels <span style='font-weight:bold'>"
                              .implode( ", ", $raParms['headers-required'] )
                              ."</span> (in any order). Like this:<br/>".self::SampleHead( $raParms );
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
            fmt          = xls (default) | csv
            charset-file = utf-8 (default) | cp1252                                  ; not used for xls because it has to be utf-8
            sheets       = array of the sheets to load (by name or 1-origin number)  ; default all
     */
    {
        $ok = false;
        $sErr = "";

        $raParms = $this->normalizeParms($raParms);

        $oSheets = new SEEDTableSheets();

        if( @$raParms['fmt'] == 'csv' ) {
            list($ok,$sErr) = $this->loadFromCSV( $oSheets, $filename, $raParms );
        } else {
            list($ok,$sErr) = $this->loadFromXLSX( $oSheets, $filename, $raParms );
        }
var_dump("A");
        if( !$ok )  $oSheets = null;

        return( array($oSheets,$sErr) );
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
        $raParms['fmt'] = SEEDCore_ArraySmartVal( $raParms, 'fmt', ['xls','csv'] );
        switch( $raParms['fmt'] ) {
            case 'xls':
                $raParms['charset-file'] = 'utf-8';
                break;
            case 'csv':
                $raParms['charset-file'] = SEEDCore_ArraySmartVal( $raParms, 'charset', ['utf-8','cp1252'] );
                $raParms['sheets'] = @$raParms['sheets'] ? array($raParms['sheets'][0]) : array(1);
                break;
        }

        return( $raParms );
    }

    function loadFromCSV( $oSheets, $filename, $raParms )
    {
        $ok = false;
        $sErr = "";


        return( array($ok,$sErr) );
    }

    function writeToCSV( $oSheets, $filename, $raParms )
    {
        $ok = false;
        $sErr = "";


        return( array($ok,$sErr) );
    }

    function loadFromXLSX( $oSheets, $filename, $raParms )
    {
        $ok = false;
        $sErr = "";

        $oXLS = new SEEDXLSRead();
        if( !$oXLS->LoadFile( $filename ) ) {
            $sErr = "Could not load file $filename";
            goto done;
        }



/*
            include_once( W_ROOT."os/PHPExcel1.8/Classes/PHPExcel.php" );
            include_once( W_ROOT."os/PHPExcel1.8/Classes/PHPExcel/IOFactory.php" );

            if( ($objPHPExcel = PHPExcel_IOFactory::load( $sFilename )) ) {
                $raSheets = $objPHPExcel->getAllSheets();
                $iSheet = 1;
                foreach( $raSheets as $sheet ) {
                    $highestRow = $sheet->getHighestDataRow();
                    $highestColumn = $sheet->getHighestDataColumn();

                    $raRows = array();
                    for( $row = 1; $row <= $highestRow; $row++ ) {
                        $ra = $sheet->rangeToArray( 'A'.$row.':'.$highestColumn.$row,
                                                    NULL, TRUE, FALSE );
                        if( $this->raParms['charset'] != 'utf-8' ) {
                            for( $i = 0; $i < count($ra[0]); ++$i ) {
                                if( is_string($ra[0][$i]) ) {
                                    $ra[0][$i] = iconv( 'utf-8', $this->raParms['charset'], $ra[0][$i] );
                                }
                            }
                        }
                        $raRows[] = $ra[0];     // $ra is an array of rows, with only one row
                    }
                    if( !($sheetName = $sheet->getTitle()) ) {
                        $sheetName = "Sheet".$iSheet;
                    }
                    $this->raSheets[$sheetName] = $raRows;
                    ++$iSheet;
                }
            }
*/

        done:
        return( array($ok,$sErr) );
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
    Read a spreadsheet file, return an array of rows

    raSEEDTableSheetsFileParms     = the parms for SEEDTableSheetsFile()
    raSEEDTableSheetsLoadParms = the parms for SEEDTableSheetsFile::LoadFromFile()
 */
{
    $bOk = false;
    $sErr = "";
    $raRows = array();

    $oFile = new SEEDTableSheetsFile( @$raParms['raSEEDTableSheetsFileParms'] );

    list($oSheets,$sErr) = $oFile->LoadFromFile( $filename, @$raParms['raSEEDTableSheetsLoadParms'] );

    return( [ $oSheets, $sErr ] );
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
            $sErr .= "No error was recorded.  Please tell Bob.";
        } else {
            $sErr .= "Please tell Bob that error # ${f['error']} was reported.";
        }
    }
    return( [$oSheets,$sErr] );
}

