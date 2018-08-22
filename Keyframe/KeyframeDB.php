<?php

/* KeyframeDB
 *
 * Copyright (c) 2006-2018 Seeds of Diversity Canada
 *
 * Keyframe basic database access
 */

define( "KEYFRAMEDB_RESULT_ASSOC", "ASSOC" );
define( "KEYFRAMEDB_RESULT_NUM",   "NUM" );
define( "KEYFRAMEDB_RESULT_BOTH",  "BOTH" );


abstract class KeyframeDB_Connection
{
    protected $_conn = NULL;  // a resource that indicates the db connection
    protected $raParms = NULL;

    function __construct( $raParms ) { $this->raParms = $raParms; }

//    function IsConnected() { return( $this->_conn != NULL ); }

    // implement per platform
    abstract function _connect( $dbname ); // return boolean
    abstract function _execute( $sql );    // return boolean
    abstract function _cursorOpen( $sql ); // return resource usable by CursorFetch/CursorGetNumRows/CursorClose
    abstract function _cursorFetch( $dbc, $result_type );
    abstract function _cursorGetNumRows( $dbc );
    abstract function _cursorClose( $dbc );
    abstract function _insertAutoInc( $sql );
    abstract function _getErrNo();
    abstract function _getErrMsg();
    abstract function _getConnectErrMsg();
}


class KeyframeDB_Connection_MySQLI extends KeyFrameDB_Connection
{
    function __construct( $raParms )
    {
        parent::__construct( $raParms );
    }

    function _connect( $dbname )
    {
        $this->_conn = mysqli_connect( $this->raParms['host'], $this->raParms['userid'], $this->raParms['password'], $dbname );
        return( $this->_conn != null );
    }
    function _execute( $sql )          { return( mysqli_query( $this->_conn, $sql ) != 0 ); }   // mysqli_query returns a dbc (SELECT) or true (UPDATE,DELETE,etc) on success, false on error
    function getAffectedRows()         { return( mysqli_affected_rows( $this->_conn ) ); }      // rows SELECTED, INSERTED, UPDATED, or DELETED by preceding command
    function _cursorOpen( $sql )       { return( mysqli_query( $this->_conn, $sql ) ); }
    function _cursorFetch( $dbc, $result_type )
    {
        switch( $result_type ) {
            case KEYFRAMEDB_RESULT_ASSOC: $result_type = MYSQLI_ASSOC; break;
            case KEYFRAMEDB_RESULT_NUM:   $result_type = MYSQLI_NUM;   break;
            case KEYFRAMEDB_RESULT_BOTH:
            default:                      $result_type = MYSQLI_BOTH;
        }
        return( mysqli_fetch_array( $dbc, $result_type ) );
    }
    function _cursorGetNumRows( $dbc ) { return( $dbc->num_rows ); }
    function _cursorClose( $dbc )      { mysqli_free_result( $dbc ); }

    // Return of the correct autoinc depends on this _conn not being used by another process that inserts simultaneously.
    // i.e. there is no explicit transaction linking this mysql_query and mysql_insert_id
    // Normally this will be okay since a new _conn is created for each instance of this class.
    function _insertAutoInc( $sql )    { return( $this->_execute($sql) ? mysqli_insert_id( $this->_conn ) : 0 ); }
    function _getConnectErrMsg()       { return( mysqli_connect_error() ); }
    function _getErrMsg()              { return( mysqli_error( $this->_conn ) ); }
    function _getErrNo()               { return( mysqli_errno( $this->_conn ) ); }
}


class KeyframeDatabase {
    private $oConn;
    private $errmsg = "";
    private $lastQuery = "";
    private $bDebug = 0;           // 0=none, 1=echo errors, 2=echo queries

    // host is optional because it's almost always localhost
    function __construct( $userid, $password, $host = 'localhost' ) {
        $this->oConn = new KeyFrameDB_Connection_MySqlI( array( 'host'=>$host, 'userid'=>$userid, 'password'=>$password ) );
    }

    function Connect( $dbname ) {
        if( !($bOk = $this->oConn->_connect( $dbname )) ) {
            $this->errmsg = "Cannot connect to database $dbname : ".$this->oConn->_getConnectErrMsg();
        }
        return( $bOk );
    }

    function Execute( $sql ) {
        $this->debugStart($sql);
        $bOk = $this->oConn->_execute( $sql );
        $this->debugEnd( !$bOk );
        return( $bOk );
    }

    function GetAffectedRows() {
        return( $this->oConn->getAffectedRows() );
    }

    function CursorOpen( $query ) {
        $this->debugStart( $query );
        $dbc = $this->oConn->_cursorOpen( $query );
        $this->debugEnd( $dbc == NULL );
        return( $dbc );
    }
    function CursorFetch( $dbc, $result_type = KEYFRAMEDB_RESULT_BOTH )
    {
        return( ($dbc && ($ra = $this->oConn->_cursorFetch($dbc, $result_type))) ? $ra  : NULL );
    }
    function CursorGetNumRows( $dbc )  { return( $dbc ? $this->oConn->_cursorGetNumRows($dbc) : 0 ); }
    function CursorClose( $dbc )       { if($dbc) $this->oConn->_cursorClose($dbc); }

    /* INSERT a row into a table that contains an AUTO_INCREMENT column, and return the value of that column.
     * $sql should be of the form "INSERT INTO foo (id, bar) VALUES (NULL, x)"
     */
    function InsertAutoInc( $sql ) {
        $this->debugStart( $sql );
        $kNew = $this->oConn->_insertAutoInc($sql);
        $this->debugEnd( $kNew == 0 );
        return( $kNew );
    }

    function QueryRA( $query, $result_type = KEYFRAMEDB_RESULT_BOTH ) {
        /* Return the array of values from the first row of a SELECT query
         */
        $ra = NULL;
        if( ($dbc = $this->CursorOpen( $query )) ) {
            $ra = $this->CursorFetch( $dbc, $result_type );
            $this->CursorClose( $dbc );
        }
        return( $ra );
    }

    function Query1( $query ) {
        /* Return a single value from the first row of a SELECT p1 FROM... query
         */
        $ra = $this->QueryRA( $query );
        return( $ra ? $ra[0] : NULL );
    }

    function QueryRowsRA( $query, $result_type = KEYFRAMEDB_RESULT_BOTH ) {
        /* Return an array of rows:  array( array( fld1 => val1, fld2 => val2, ...)
         *                                  array( fld1 => val1, fld2 => val2, ...) ... )
         */
        $ra = array();
        if( ($dbc = $this->CursorOpen( $query )) ) {
            while( ($raRow = $this->CursorFetch( $dbc, $result_type )) ) {
                $ra[] = $raRow;
            }
            $this->CursorClose( $dbc );
        }
        return( $ra );
    }

    function QueryRowsRA1( $query, $result_type = KEYFRAMEDB_RESULT_BOTH ) {
        /* Fetch an array of rows where each row contains one value, and collapse the rows into a single-dimensional array.
         *
         * e.g. SELECT k FROM tbl; for a table with 3 rows, would return array( k_of_row1, k_of_row2, k_of_row3 )
         */
        $ra = array();
        if( ($dbc = $this->CursorOpen( $query )) ) {
            while( ($raRow = $this->CursorFetch( $dbc, $result_type )) ) {
                $ra[] = $raRow[0];
            }
            $this->CursorClose( $dbc );
        }
        return( $ra );
    }

    function GetErrMsg() {
        if( $this->errmsg )  return( $this->errmsg );
        return( "A database error occurred: ".$this->oConn->_getErrMsg()." : ".$this->oConn->_getErrNo()." : ".$this->lastQuery );
    }

    function SetDebug( $bDebug )    { $this->bDebug = $bDebug; }      // 0=none, 1=show errors, 2=show queries and errors

    function GetFields( $table ) {
        /* Get an array of the fields in the given table.  This is mostly useful by KeyFrameRelation.
         *      array( field1 => array( 'type' => {db field type},
         *                              'null' => {boolean},
         *                              'default' => {db field default},
         *                              'kf_type' => {simplified type for KF: I or S},  (should implement float too)
         *                              'kf_default' => {normalized default for KF}
         */
        /* MySQL also provides:
         *     $result = mysql_query( "SELECT * FROM table" )
         *     for( $i=0; $i<mysql_num_fields($result); $i++ )  $fields[] = mysql_field_name($result, $i);
         * but this doesn't tell us about null and default
         */
        $raOut = array();

        if( ($dbc = $this->CursorOpen("SHOW FIELDS FROM $table")) ) {
            while( ($ra = $this->CursorFetch( $dbc )) ) {
                $raOut[$ra['Field']]['type'] = $ra['Type'];
                $raOut[$ra['Field']]['null'] = ($ra['Null'] == "YES");
                $raOut[$ra['Field']]['default'] = $ra['Default'];

                $raOut[$ra['Field']]['kf_type'] =
                    (substr($ra['Type'],0,3) == 'int' ||
                     substr($ra['Type'],0,7) == 'tinyint' ||
                     substr($ra['Type'],0,8) == 'smallint' ||
                     substr($ra['Type'],0,6) == 'bigint')
                      ? "I" : "S";
                // When MySQL 5 shows 'NULL' as Default on the command line client, it returns an empty string in the cursor.
                $raOut[$ra['Field']]['kf_default'] = (($ra['Default'] == 'NULL' || empty($ra['Default'])) ? ($raOut[$ra['Field']]['kf_type']=='I' ? 0 : "") : $ra['Default']);
            }
            $this->CursorClose( $dbc );
        }

        return( $raOut );
    }

    function TableExists( $table )
    /*****************************
     */
    {
        $raFld = $this->QueryRA("SHOW FIELDS FROM $table");    // gets the first row returned
        return( $raFld['Field'] != "" );
    }

    private function debugStart( $sql )
    {
        $this->lastQuery = $sql;
        if( $this->bDebug >= 2 ) echo "<p style='font-size:9pt;font-family:courier,monospace;color:gray;'>".nl2br($sql)."</p>";
    }

    private function debugEnd( $bError )
    {
        if( $bError && $this->bDebug ) { echo "<p style='font-size:9pt;font-family:courier,monospace;color:gray;'>".$this->GetErrMsg()."</p>"; }
    }
}

?>
