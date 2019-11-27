<?php

/* SEEDXLSX
 *
 * Copyright (c) 2014-2019 Seeds of Diversity Canada
 *
 * Read and write spreadsheet files.
 *
 * This is a thin wrapper for third-party XLS libraries.
 * For more comprehensive handling of 3D data, use SEEDTableSheetsFile (which uses this).
 */

require_once SEEDROOT.'/vendor/autoload.php';   // PhpOffice/PhpSpreadsheet

class SEEDXlsRead
{
    private $oXls;

    function __construct( $raConfig = array() )
    {
    }

// TODO: use \PhpOffice\PhpSpreadsheet\Reader\IReadFilter to read a file in chunks to conserve memory

    function LoadFile( $filename )
    {
        $this->oXls = \PhpOffice\PhpSpreadsheet\IOFactory::load($filename);     // static load() method

        return( $this->oXls != null );
    }

    function GetSheetCount()  { return( $this->oXls ? $this->oXls->getSheetCount() : 0 ); }
    function GetSheetNames()  { return( $this->oXls ? $this->oXls->getSheetNames() : [] ); }

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
        return( $this->getData( $iSheet, null, $raParms ) );
    }

    function GetRow( $iSheet, $iRow, $raParms = array() )
    /****************************************************
        Return an array of the row's values

        Sheets are origin-0
        Rows are origin-1
        bCalculateFormulae = false : return formulae verbatim
                           = true  : return the calculated result of the formulae
        sCharset : data is utf-8; convert if this is not utf-8 or empty
     */
    {
        return( $this->getData( $iSheet, $iRow, $raParms ) );
    }

    private function getData( $iSheet, $iRow, $raParms )
    /***************************************************
        Get 2D data for the given sheet.

        $iRow == 0 : data for the whole sheet
        $iRow > 0  : just one row
     */
    {
        $ra = array();

        if( !($oSheet = $this->oXls->getSheet( $iSheet )) )  goto done;

        if( $iRow ) {
            $sRange = "A$iRow:".$oSheet->getHighestDataColumn().$iRow;
        } else {
            $sRange = "A1:".$oSheet->getHighestDataColumn().$oSheet->getHighestDataRow();
        }

        $defaultIfCellNotExist = null;
        $bCalculateFormulae = SEEDCore_ArraySmartVal1( $raParms, 'bCalculateFormulae', true );
        $bFormatCells = false;
        $bUseCellnamesForKeys = false;

        $ra = $oSheet->rangeToArray( $sRange, $defaultIfCellNotExist, $bCalculateFormulae, $bFormatCells, $bUseCellnamesForKeys );
        $ra = $this->charsetConvert( $ra, $raParms );

        done:
        return( $ra );
    }

    private function charsetConvert( $ra, $raParms )
    {
        if( ($sCharset = @$raParms['sCharsetOutput']) && $sCharset != 'utf-8' ) {
            for( $i = 0; $i < count($ra[0]); ++$i ) {
                if( is_string($ra[0][$i]) ) {
                    $ra[0][$i] = iconv( 'utf-8', $sCharset, $ra[0][$i] );
                }
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
        $this->oXls->getProperties()->setCreator(@$raConfig['creator'])
            ->setLastModifiedBy(@$raConfig['author'])
            ->setTitle(@$raConfig['title'])
            ->setSubject(@$raConfig['subject'])
            ->setDescription(@$raConfig['description'])
            ->setKeywords(@$raConfig['keywords'])
            ->setCategory(@$raConfig['category']);

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
// replace this all with WriteRow($iSheet, '1', $raCols)
        $oSheet = $this->oXls->setActiveSheetIndex( $iSheet );

        // Set the headers in row 1
        $c = 'A';
        foreach( $raCols as $dbfield => $label ) {
            $oSheet->setCellValue($c.'1', $label );
            $c = chr(ord($c)+1);    // Change A to B, B to C, etc
        }
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
