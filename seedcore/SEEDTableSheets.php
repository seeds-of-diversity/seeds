<?php

/*
 * SEEDTableSheets
 *
 * Copyright 2014-2019 Seeds of Diversity Canada
 *
 * Manage 3-d tabular data as sheets, rows, columns
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
