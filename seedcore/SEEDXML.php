<?php

/* SEEDXML
 *
 * Copyright 2015-2018 Seeds of Diversity Canada
 *
 * Transform and parse XML files
 */


class SEEDXML
{
    private $oDom = null;   // a DOMDocument containing the current xml content
    private $fname = "";    // used for error reporting in the parser

    function __construct() {}

    function GetXML()  { return( $this->oDom ? $this->oDom->saveXML() : "" ); }

    function LoadFile( $fname, $raParms = array() )
    /**********************************************
        Load an xml file

        bPreserveWS      = preserve whitespace in the xml (default true)  -- this makes line numbers in the parser errors meaningful
        bProcessIncludes = do <xi:include> (default true)
     */
    {
        $bPreserveWS      = SEEDCore_ArraySmartVal( $raParms, 'bPreserveWS',      array(true,false) );
        $bProcessIncludes = SEEDCore_ArraySmartVal( $raParms, 'bProcessIncludes', array(true,false) );

        $this->fname = $fname;  // only used for error reporting in the parser

        if( !$this->oDom ) {
            $this->oDom = new DOMDocument;
        }
        $this->oDom->preserveWhiteSpace = $bPreserveWS;
        $this->oDom->load( $fname );
        if( $bProcessIncludes ) {
            $this->oDom->xinclude();       // this does <xi:include>
        }
    }

    function GetByXPath( $sXpath )
    {
        $oXP = new DOMXPath( $this->oDom );

        $ra = $oXP->query( $sXpath );   // the pieces of the dom that match the xpath

        return( $ra );
    }

    function LoadStr( $sXML, $raParms = array() )
    /********************************************
        Load/append an xml string

        bPreserveWS = preserve whitespace in the xml
     */
    {
        $bPreserveWS      = SEEDCore_ArraySmartVal( $raParms, 'bPreserveWS',      array(true,false) );
        $bProcessIncludes = SEEDCore_ArraySmartVal( $raParms, 'bProcessIncludes', array(true,false) );
        
        if( !$this->oDom ) {
            $this->oDom = new DOMDocument;
        }
        $this->oDom->preserveWhiteSpace = $bPreserveWS;
        $this->oDom->loadxml( $sXML );
        if( $bProcessIncludes ) {
            $this->oDom->xinclude();       // this does <xi:include>
        }
    }

    function TransformXSLFile( $fnameXSL )
    /*************************************
        Transform the loaded xml using the given xsl file
     */
    {
        $oDomXSL = new DOMDocument;
        $oDomXSL->load( $fnameXSL );
        $oXSL = new XSLTProcessor;
        $oXSL->importStyleSheet( $oDomXSL );
        $s = $oXSL->transformToXML( $this->oDom );

        return( $s );
    }

    function TransformXSLStr( $sXSL )
    /********************************
        Transform the loaded xml using the given xsl string
     */
    {

    }

    function Parse( $raParms )
    /*************************
        Parse the xml in the current dom.
     */
    {
        $this->ParseStr( $this->GetXML(), $raParms );
    }

    function ParseStr( $sXML, $raParms )
    /***********************************
        Run the given xml through a parser, calling the given handlers

        fnElementStart => function to call at the start of an element
        fnElementEnd   => function to call at the end of an element
        fnElementCData => function to call when character data is encountered
     */
    {
        $oParser = xml_parser_create();
        xml_set_element_handler( $oParser, $raParms['fnElementStart'], $raParms['fnElementEnd'] );
        xml_set_character_data_handler( $oParser, $raParms['fnElementCData'] );
        if( !xml_parse( $oParser, $sXML, true ) ) {
            die( sprintf( "XML error: %s at line %d of %s",
                          xml_error_string( xml_get_error_code( $oParser ) ),
                          xml_get_current_line_number( $oParser ),
                          $this->fname ) );
        }
        xml_parser_free( $oParser );
    }
}

?>
