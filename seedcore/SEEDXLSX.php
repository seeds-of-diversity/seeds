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
        $this->oXls = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);     // static load() method
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
        return( $oSheet->getHighestDataRow() );
    }

    function GetColCount( $iSheet )
    /******************************
        Return the max number of columns in the sheet
     */
    {
        $oSheet = $this->oXls->getSheet( $iSheet );
        return( $oSheet->getHighestDataColumn() );
    }

    function GetRow( $iSheet, $iRow, $bCalculateFormulae = false )
    /*************************************************************
        Return an array of the row's values

        Sheets are origin-0
        Rows are origin-1
        bCalculateFormulae = false : return formulae verbatim
                           = true  : return the calculated result of the formulae
     */
    {

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
