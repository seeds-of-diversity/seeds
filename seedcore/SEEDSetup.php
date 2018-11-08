<?php

/* SEEDSetup
 *
 * Copyright (c) 2009-2018 Seeds of Diversity Canada
 *
 * Help install sites and databases on servers
 */

class SEEDSetup2
{
    const ACTION_TESTEXIST = 0;
    const ACTION_CREATETABLES = 1;
    const ACTION_INSERT = 2;
    const ACTION_CREATETABLES_INSERT = 3;

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
        if( !($bOk = $this->kfdb->TableExists( $table )) ) {
            if( $bCreate ) {
                $bOk = $this->kfdb->Execute( $sqlCreateTable );
                $this->sReport .= ($bOk ? "Created table $table<br/>" : ("Failed to create $table. ".$this->kfdb->GetErrMsg()."<br/>"));
            } else {
                $this->sReport .= "Table $table does not exist<br/>";
            }
        }
        return( $bOk );
    }

    function SetupDBTables( $raDef, $eAction )
    /*****************************************
        $raDef['tables'] = array( tablename => array( 'create'=>sqlCreateTable, 'insert'=>array( insert statements ...
     */
    {
        $bRet = true;
        foreach( $raDef['tables'] as $tablename => $raTable ) {
            $bExists = $this->SetupTable( $tablename, "", self::ACTION_TESTEXIST );

            if( $eAction == self::ACTION_TESTEXIST ) {
                // Just test existence of all the tables in raDef
                $bRet = $bRet && $bExists;
            } else {
                $ok = true;

                if( in_array( $eAction, array(self::ACTION_CREATETABLES, self::ACTION_CREATETABLES_INSERT) ) ) {
                    // Create table if not exist
                    if( !$bExists ) {
                        $ok = isset($raTable['create']) ? $this->SetupTable( $tablename, $raTable['create'], true )
                                                        : true;
                    }
                }

                // $bExists refers to existence before the create-table step
                if( $ok && isset($raTable['insert']) &&
                    (($eAction == self::ACTION_INSERT              && $bExists) ||   // insert only if the table exists
                     ($eAction == self::ACTION_CREATETABLES_INSERT && !$bExists)) )  // insert only if the table didn't exist but was created
                {
                    foreach( $raTable['insert'] as $sql ) {
                        if( ($ok = $this->kfdb->Execute( $sql )) ) {
                            $this->sReport .= "Inserted row to $tablename<br/>";
                        } else {
                            $this->sReport .= "Failed to insert row to $tablename<br/>";
                            break;
                        }
                    }
                }
                $bRet = $bRet && $ok;
            }
        }

        return( $bRet );
    }
}

?>