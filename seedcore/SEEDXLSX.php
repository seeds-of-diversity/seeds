<?php

/* SEEDXLSX
 *
 * Copyright (c) 2014-2022 Seeds of Diversity Canada
 *
 * Read and write spreadsheet files.
 *
 * This is a thin wrapper for third-party XLS libraries.
 * For more comprehensive handling of 3D data, use SEEDTableSheetsFile (which uses this).
 */

require_once SEEDROOT.'/vendor/autoload.php';   // PhpOffice/PhpSpreadsheet

class SEEDXls
{
    /**
     * Convert a 0-based column index to an alphabetical column name
     * @param int - 0-based index
     * @return String - alphabetic column name
     */
    static function Index2ColumnName( int $index ) : String
    {
        $cols = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];
        if($index >= count($cols)){
            return self::IndexToColumnName(intdiv($index,count($cols))-1) . self::IndexToColumnName($index % count($cols));
        }
        return $cols[$index];
    }

    /**
     * Convert an alphabetical column name to a 0-based column index
     * @param String - alphabetic column name
     * @return int - 0-based index
     */
    static function ColumnName2Index( String $colname ) : int
    {
        $i = 0;
        $colname = strtoupper($colname);

        if( strlen($colname) == 2 ) {
            $i = (ord($colname[0]) - ord('A') + 1) * 26;
            $colname = $colname[1];
        }
        $i += ord($colname[0]) - ord('A');

        return( $i );
    }

    /**
     * Validate whether a string contains a valid cell code e.g. A1, N20, CF120
     * @param String - alphabetic column name
     * @return int - 0-based index
     */
    static function IsValidCellname( string $cellname )
    {
        return( preg_match( "/^[A-Z]+[0-9]+$/", $cellname) );
    }
}


class SEEDXlsRead
{
    private $oXls;

    function __construct( $raConfig = array() )
    {
    }

    function LoadFile( $filename )
    {
        $this->oXls = \PhpOffice\PhpSpreadsheet\IOFactory::load($filename);     // static load() method

        return( $this->oXls != null );
    }

    function GetSheetCount()  { return( $this->oXls ? $this->oXls->getSheetCount() : 0 ); }
    function GetSheetNames()  { return( $this->oXls ? $this->oXls->getSheetNames() : [] ); }

    function GetSheetName( $iSheet )
    {
        return( ($ra = $this->GetSheetNames()) ? @$ra[$iSheet] : "" );
    }

    function GetRowCount( $iSheet )
    /******************************
        Return the number of rows in the sheet

        Sheets are origin-0
     */
    {
        $oSheet = $this->oXls->getSheet( $iSheet );
        return( $oSheet ? $oSheet->getHighestDataRow() : 0 );
    }

    function GetColCount( $iSheet )
    /******************************
        Return the max number of columns in the sheet
     */
    {
        $n = 0;

        if( ($oSheet = $this->oXls->getSheet( $iSheet )) ) {
            $max = $oSheet->getHighestDataColumn();     // returns an upper case letter ( cols > 26 not implemented here` )
            $n = ord($max) - ord('A') + 1;
        }
        return( $n );
    }

    function GetAllSheets()  { return( $this->oXls->getAllSheets() ); }

    function GetSheetData( $iSheet, $raParms = array() )
    /***************************************************
        Get a 2D array containing the sheet data. Rows and columns are keyed numerically origin-0.

        Sheets are origin-0
        bCalculateFormulae = false : return formulae verbatim
                           = true  : return the calculated result of the formulae
        sCharsetOutput : data in xls is always utf-8; convert if this is not utf-8 or empty
     */
    {
        return( _seedxlsread_GetData::GetData( $this->oXls, $iSheet, 0, 0, $raParms ) );
    }

    function GetRow( $iSheet, $iRow, $raParms = array() )
    /****************************************************
        Return an array of the row's values

        Sheets are origin-0
        Rows are origin-1
        bCalculateFormulae = false : return formulae verbatim
                           = true  : return the calculated result of the formulae
        sCharsetOutput : data is utf-8; convert if this is not utf-8 or empty
     */
    {
        return( _seedxlsread_GetData::GetData( $this->oXls, $iSheet, $iRow, $iRow, $raParms ) );
    }
}

class _seedxlsread_GetData
{
    static function GetData( $oXls, $iSheet, $iRowTop, $iRowBottom, $raParms )
    /*************************************************************************
        Get 2D data for the given sheet between the given rows.

        Sheets are origin-0
        Rows are origin-1

        $iRowTop == 0    : defaults to row 1
        $iRowBottom == 0 : defaults to highest row containing data

        bCalculateFormulae = false : return formulae verbatim
                           = true  : return the calculated result of the formulae
        sCharsetOutput : data is utf-8; convert if this is not utf-8 or empty
     */
    {
        $ra = array();

        if( !($oSheet = $oXls->getSheet( $iSheet )) )  goto done;

        if( !$iRowTop )    $iRowTop = 1;
        if( !$iRowBottom ) $iRowBottom = $oSheet->getHighestDataRow();

        $sRange = "A$iRowTop:".$oSheet->getHighestDataColumn().$iRowBottom;

        $defaultIfCellNotExist = null;
        $bCalculateFormulae = SEEDCore_ArraySmartVal1( $raParms, 'bCalculateFormulae', true );
        $bFormatCells = false;
        $bUseCellnamesForKeys = false;

        $ra = $oSheet->rangeToArray( $sRange, $defaultIfCellNotExist, $bCalculateFormulae, $bFormatCells, $bUseCellnamesForKeys );
        $ra = self::charsetConvert( $ra, $raParms );

        done:
        return( $ra );
    }

    static private function charsetConvert( $ra, $raParms )
    /******************************************************
        Note that this is very expensive for a large file, so you probably don't want to do it during the load here
        (don't define sCharsetOutput with bBigFile - unless it's a modest file that doesn't need bBigFile anyway)
     */
    {
        if( ($sCharset = @$raParms['sCharsetOutput']) && !in_array($sCharset, ['utf8','utf-8']) ) {
            for( $i = 0; $i < count($ra); ++$i ) {
                $ra[$i] = SEEDCore_CharsetConvert( $ra[$i], 'utf-8', $sCharset );   // $ra[$i] is an array; this converts each element individually
            }
        }
        return( $ra );
    }
}


class SEEDXlsWrite
{
    private $oXls;
    private $filename;

    function __construct( $raConfig = array() )
    {
        $nSheets = @$raConfig['nSheets'] ?: 1;

        // Initialize the spreadsheet with the right number of sheets (one is created by default)
        $this->oXls = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        for( $i = 0; $i < $nSheets-1; ++$i ) {
            $oXls->createSheet();
        }

        // Set document properties
        $this->oXls->getProperties()
            ->setCreator(       @$raConfig['creator']     ?: "")
            ->setLastModifiedBy(@$raConfig['author']      ?: "")
            ->setTitle(         @$raConfig['title']       ?: "")
            ->setSubject(       @$raConfig['subject']     ?: "")
            ->setDescription(   @$raConfig['description'] ?: "")
            ->setKeywords(      @$raConfig['keywords']    ?: "")
            ->setCategory(      @$raConfig['category']    ?: "");

        $this->filename = @$raConfig['filename'] ?: "spreadsheet.xlsx";
    }

    function OutputSpreadsheet()
    {

        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $this->oXls->setActiveSheetIndex(0);

        // Redirect output to a client’s web browser (Xlsx)
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"{$this->filename}\"");
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');

        // If you're serving to IE over SSL, then the following may be needed
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($this->oXls, 'Xlsx');
        $writer->save('php://output');
    }

    private function storeSheet( $oXls, $iSheet, $sSheetName, $raRows, $raCols )
    {
//        $c = 'A';
 //       foreach( $raCols as $ra ) {
  //          var_dump($c);
   //     }exit;

        $oSheet = $oXls->setActiveSheetIndex( $iSheet );
        $oSheet->setTitle( $sSheetName );

        // Set the headers in row 1
        $c = 'A';
        foreach( $raCols as $dbfield => $label ) {
            $oSheet->setCellValue($c.'1', $label );
//see SEEDTable for formula to calculate double-char column letters
            $c = chr(ord($c)+1);    // Change A to B, B to C, etc
        }

        // Put the data starting at row 2
        $row = 2;
        foreach( $raRows as $ra ) {
            $col = 'A';
            foreach( $raCols as $dbfield => $label ) {
                $oSheet->setCellValue($col.$row, $ra[$dbfield] );
                $col = chr(ord($col)+1);    // Change A to B, B to C, etc
            }
            ++$row;
        }
    }

    function WriteHeader( $iSheet, $raCols )
    /***************************************
        Sheet numbers are origin-0, rows are origin-1
     */
    {
        $this->WriteRow($iSheet, 1, $raCols);
    }

    function WriteRow( $iSheet, $iRow, $raCols )
    /*******************************************
        Sheet numbers are origin-0, rows are origin-1
     */
    {
        $oSheet = $this->oXls->setActiveSheetIndex( $iSheet );

        $col = 'A';
        foreach( $raCols as $v ) {
            $oSheet->setCellValue($col.$iRow, $v );
            $col = chr(ord($col)+1);    // Change A to B, B to C, etc
        }
    }

    function SetCellStyle( $iSheet, $iRow, $sCol, $raStyle )
    /*******************************************************
        Set a style for the given cell
     */
    {
        $oSheet = $this->oXls->setActiveSheetIndex( $iSheet );

        $oSheet->getStyle("$sCol$iRow")->applyFromArray($raStyle);
    }
}


class _seedxlsbigfile_ChunkReadFilter implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter
{
    private $startRow = 0;
    private $endRow   = 0;

    //  Set the list of rows that we want to read
    public function setRows($startRow, $chunkSize) {
        $this->startRow = $startRow;
        $this->endRow   = $startRow + $chunkSize;
    }

    public function readCell($column, $row, $worksheetName = '') {
        if( $row >= $this->startRow && $row < $this->endRow ) {
            return true;
        }
        return false;
    }
}

class SEEDXlsReadBigFile
/***********************
    Similar to SEEDXlsRead but loads the file in chunks to conserve memory.
    This limits how the data can be retrieved, because only a chunk is loaded at any time, so all this does is return
    the first sheet's data in an array.
 */
{
    private $oXls;

    function __construct( $raConfig = array() )
    {
    }

    function LoadFile( $filename, $raParms = array() )
    {
        $raData = array();

        // Create a new Reader of the type defined by the file extension.
        // Attach a ChunkReadFilter to load the file in chunks that won't exhaust memory
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filename);
        $reader->setReadDataOnly(true);
        $chunkFilter = new _seedxlsbigfile_ChunkReadFilter();
        $reader->setReadFilter($chunkFilter);

        $chunkSize = 4096;
        for( $row = 1; $row <= 65536; $row += $chunkSize ) {
            $chunkFilter->setRows( $row, $chunkSize );

            // Load the next chunk and add the data in the array.
            // When a chunk loads with no data, it returns [0 => [0 => null]]
            $this->oXls = $reader->load($filename);
            $d = _seedxlsread_GetData::GetData( $this->oXls, 0, $row, 0, $raParms );    // toprow=$row, bottomrow=getHighestDataRow()

            if( !$d || !is_array($d) || count($d)==0 || (count($d)==1 && is_array($d[0]) && count($d[0])==1 && $d[0][0] === null) ) {
                // this chunk returned no data
                break;
            }
            $raData = array_merge( $raData, $d );
        }

        //$this->oXls = \PhpOffice\PhpSpreadsheet\IOFactory::load($filename);     // static load() method

        return( $raData );
    }
}


function SEEDXlsx_WriteFileXlsx( $raCols, $raRows, $raParms = [] )
/*****************************************************************
    Write a XLSX file from raRows where raCols are the keys in column order and the column headings.

    raParms : sCharsetRows = charset of the raRows data
 */
{
    $sCharsetRows = @$raParms['sCharsetRows'] ?: 'utf-8';

    $oXLSX = new SEEDXlsWrite();

    // row 0 is the column names
    $oXLSX->WriteRow( 0, 1, $raCols );

    $iRow = 2;
    foreach( $raRows as $ra ) {
        if( $sCharsetRows != 'utf-8' ) {
            $ra = SEEDCore_CharsetConvert( $ra, $sCharsetRows, 'utf-8' );    // convert array of strings
        }
        $oXLSX->WriteRow( 0, $iRow++, $ra );
    }

    $oXLSX->OutputSpreadsheet();
    exit;
}
