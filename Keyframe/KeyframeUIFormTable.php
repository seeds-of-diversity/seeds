<?php

/* KFUIFormTable.php
 *
 * Copyright (c) 2016-2019 Seeds of Diversity Canada
 *
 * Automate development of tables of form rows.
 * Given a KeyfameForm and a formdef, provide the UI pieces needed to make a table updater application.
 *
 * To update only a particular set of columns, just include those in the formdef. The rest will be unaltered.
 */

include_once( "KeyframeForm.php" );
include_once( SEEDCORE."SEEDFormTable.php" );

class KeyframeUIFormTable extends SEEDFormTable
{
    private $raFormDef;

    function __construct( KeyframeForm $oForm )
    {
        parent::__construct( $oForm );
    }

    /* Use these methods of SEEDFormTable

        function Start( $raFormDef );
        function Header();
        function End();
    */
    function RowKFR( KeyframeRecord $kfr )
    /*************************************
        Draw a row, just like Row(), but use the given kfr in the oForm
     */
    {
        $this->oForm->SetKFR( $kfr );    // method available in KFUIForm, not SEEDForm
        return( $this->Row() );
    }

    function DrawTable( $raFormDef, $sCond, $raKFRCParms )
    /*****************************************************
        Draw a table of form rows using $sCond to filter the rows shown, and $raFormDef to limit the columns
     */
    {
        $s = "";

        $s .= $this->Start( $raFormDef )
             .$this->Header();
        if( ($kfrc = $this->oForm->kfrel->CreateRecordCursor( $sCond, $raKFRCParms )) ) {
            while( $kfrc->CursorFetch() ) {
                $s .= $this->RowKFR( $kfrc );
            }
        }
        if( false /* new row */ ) {
            $kfr = $this->oForm->Kfrel()->CreateRecord();      // add a blank record at the end for inserting new rows
            $s .= $this->RowKFR( $kfr );
        }
        $s .= $this->End();

        return( $s );
    }
}
