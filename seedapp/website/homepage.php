<?php

/* homepage.php
 *
 * Copyright 2020 Seeds of Diversity Canada
 *
 * System for managing home page content
 */

include_once( SEEDCORE."SEEDMetaTable.php" );

class SoDWebsite_HomePage
{
    private $oApp;
    private $oBucket;

    function __construct( SEEDAppSession $oApp )
    {
        $this->oApp = $oApp;
        $this->oBucket = new SEEDMetaTable_StringBucket( $oApp->kfdb, $oApp->sess->GetUID() );
    }

    function GetBlocks( $bActiveOnly = true )
    {
        $raBlocks = [];

        /* Fetch the blocks, filter them by bActiveOnly, and sort by iOrder
         */
        $oTable = new SEEDMetaTable_TablesLite( $this->oApp, $oApp->sess->GetUID() );
        $kTable = $oTable->OpenTable( "SEEDWebsite_HomePage Blocks" );
        $raRows = $oTable->GetRows( $kTable );
        foreach( $raRows as $kRow => $ra ) {
            if( $bActiveOnly && !$ra['bActive'] )  continue;
            $raBlocks['block'.$ra['iOrder']] = $ra + ['kRow'=>$kRow];
        }
        ksort($raBlocks);

    }

}
