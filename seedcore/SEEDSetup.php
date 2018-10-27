<?php

/* SEEDSetup
 *
 * Copyright (c) 2009-2018 Seeds of Diversity Canada
 *
 * Help install sites and databases on servers
 */

class SEEDSetup2
{
    private $kfdb;  // the database where tables will be created
    private $sReport = "";

    function __construct( KeyframeDatabase $kfdb )
    {
        $this->kfdb = $kfdb;
    }

    function GetReport() { return( $this->sReport ); }

    function SetupTable( $table, $sqlCreateTable, $bCreate )
    /*******************************************************
        $bCreate == false : test for existence of table
        $bCreate == true  : if not exist, execute sqlCreateTable to create a database table

        Return bool success
     */
    {
        if( !($bRet = $this->kfdb->TableExists( $table )) ) {
            if( $bCreate ) {
                $bRet = $this->kfdb->Execute( $sqlCreateTable );
                $this->sReport .= ($bRet ? "Created table $table<br/>" : ("Failed to create $table. ".$this->kfdb->GetErrMsg()."<br/>"));
            } else {
                $this->sReport .= "Table $table does not exist<br/>";
            }
        }
        return( $bRet );
    }

    function SetupDBTables( $raDef, $bCreate )
    /*****************************************
        $raDef['tables'] = array( tablename => sqlCreateTable, ... )
        $raDef['inserts'] = array( tablename => array(sqlInsert, ...), ... )
     */
    {
        $bOk = true;
        foreach( $raDef['tables'] as $tablename => $sqlCreateTable ) {
            $bOk = $this->SetupTable( $tablename, $sqlCreateTable, $bCreate ) && $bOk;
        }
        if( !$bOk ) goto done;

        foreach( $raDef['inserts'] as $tablename => $raInserts ) {
            if( !$this->kfdb->Query1( "SELECT count(*) FROM $tablename" ) ) {
                // table is empty so do the inserts
                foreach( $raInserts as $sql ) {
                    $this->sReport .= $this->kfdb->Execute( $sql )
                                            ? "Inserted row to $tablename<br/>" : "Failed to insert row to $tablename<br/>";
                }
            }
        }

        done:
        return( $bOk );
    }
}

?>
