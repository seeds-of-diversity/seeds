<?php

/* KeyframeDB
 *
 * Copyright (c) 2006-2025 Seeds of Diversity Canada
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
    
    /**
     * Open a connection to the db
     * @param string $dbname Name of the database to connect to
     * @return bool - True if a connection was successfully established. False otherwise
     */
    abstract function _connect( string $dbname ): bool;
    /**
     * Execute a query against the db.
     * Intended to be used for queries that do not return a result set (INSERT, UPDATE, DELETE, etc.)
     * @param string $sql - SQL to execute
     * @param array $params - Optional: Parameters for the query
     * @return bool - True if the query was successfully executed. False otherwise
     * @see KeyframeDB_Connection::_cursorOpen()
     */
    abstract function _execute( string $sql, ?array $params = null ): bool;
    /**
     * Query the db and return a cursor to the result set.
     * @param string $sql - SQL to execute
     * @param array $params - Optional: Parameters for the query.
     * @return mixed - True if the query didn't return a result set.
     * An object containing the result set usable by _cursorFetch/_cursorGetNumRows/_cursorClose if the query returns a result set.
     * False otherwise
     * @see KeyframeDB_Connection::_cursorFetch()
     * @see KeyframeDB_Connection::_cursorGetNumRows()
     * @see KeyframeDB_Connection::_cursorClose()
     */
    abstract function _cursorOpen( string $sql, ?array $params = null );
    /**
     * Fetch the results from the cursor
     * @param unknown $dbc - Cursor object to get results from
     * @param unknown $result_type
     * @return array
     * @see KeyframeDB_Connection::_cursorOpen()
     */
    abstract function _cursorFetch( $dbc, $result_type );
    /**
     * Get the number of rows from the cursor
     * @param unknown $dbc - Cursor object to get the number of rows from
     * @return string|int - Number of rows returned by the cursor
     * @see KeyframeDB_Connection::_cursorOpen()
     */
    abstract function _cursorGetNumRows( $dbc ): string|int;
    /**
     * Close a cursor
     * @param unknown $dbc - Cursor object to close
     * @see KeyframeDB_Connection::_cursorOpen()
     */
    abstract function _cursorClose( $dbc ): void;
    /**
     * Execute a query and return the value for the auto increment column.
     * Most commonly used for inserting new rows
     * @param string $sql - query to execute
     * @param array $params - Optional: Parameters for the query.
     * @return string|int - Value of the auto increment column from the query
     */
    abstract function _insertAutoInc( string $sql, ?array $params ): string|int;
    abstract function _getErrNo();
    abstract function _getErrMsg();
    abstract function _getConnectErrMsg();
    abstract function _escapeString( $s );
}


class KeyframeDB_Connection_MySQLI extends KeyFrameDB_Connection
{
    function __construct( $raParms )
    {
        parent::__construct( $raParms );
    }

    function _connect( string $dbname ): bool
    {
        $this->_conn = mysqli_connect( $this->raParms['host'], $this->raParms['userid'], $this->raParms['password'], $dbname );

        // login parms not needed anymore so clear them to prevent exposing in something like var_dump($kfdb);
        $this->raParms['userid'] = $this->raParms['password'] = $this->raParms['host'] = 'erased';

        return( $this->_conn != null );
    }
    
    /**
     * Helper function for executing queries.
     * Handles falling back to mysqli_query if mysqli_execute_query is not supported
     */
    private function _executeQuery ( string $sql, ?array $params = null) {
        // mysqli_query returns a dbc (SELECT) or true (UPDATE,DELETE,etc) on success, false on error
        if (function_exists('mysqli_execute_query')) {
            return( mysqli_execute_query( $this->_conn, $sql, $params ) );
        }
        if($params && count($params) > 0) {
            // Received what is presumed to be a prepared statement, but we don't have access to execute_query
            trigger_error("Received parameters for a prepared statement, but mysqli_execute_query is not supported", E_USER_WARNING);
            $i = 0;
            // Replace the placeholders in the SQL with the parameter values
            // This defeats the purpose of using prepared statements but is backwards compatible with PHP versions < 8.2
            $sql = preg_replace_callback('/\?/', function() use (&$i, $params) {
                return isset($params[$i]) ? $this->_escapeString($params[$i++]) : '?';
            }, $str);
        }
        return mysqli_query( $this->_conn, $sql );
    }
    
    function _execute( string $sql, ?array $params = null ): bool { return( $this->_executeQuery($sql, $params) != 0 ); }   // mysqli_query returns a dbc (SELECT) or true (UPDATE,DELETE,etc) on success, false on error
    function getAffectedRows(): string|int { return( mysqli_affected_rows( $this->_conn ) ); }      // rows SELECTED, INSERTED, UPDATED, or DELETED by preceding command
    function _cursorOpen( string $sql, ?array $params = null ) { return( $this->_executeQuery($sql, $params) ); }
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
    function _cursorGetNumRows( $dbc ): string|int { return( $dbc->num_rows ); }
    function _cursorClose( $dbc ): void { mysqli_free_result( $dbc ); }

    // Return of the correct autoinc depends on this _conn not being used by another process that inserts simultaneously.
    // i.e. there is no explicit transaction linking this mysql_query and mysql_insert_id
    // Normally this will be okay since a new _conn is created for each instance of this class.
    function _insertAutoInc( string $sql, ?array $params = null ): string|int { return( $this->_execute($sql, $params) ? mysqli_insert_id( $this->_conn ) : 0 ); }
    function _getConnectErrMsg()       { return( mysqli_connect_error() ); }
    function _getErrMsg()              { return( mysqli_error( $this->_conn ) ); }
    function _getErrNo()               { return( mysqli_errno( $this->_conn ) ); }
    function _escapeString( $s )       { return( mysqli_real_escape_string( $this->_conn, $s ) ); }
}


class KeyframeDatabase {
    private $oConn;
    private $errmsg = "";
    private $lastQuery = "";
    private $bDebug = 0;           // 0=none, 1=echo errors, 2=echo queries
    private $dbname = "";

    // host is optional because it's almost always localhost
    function __construct( $userid, $password, $host = 'localhost' ) {
        $this->oConn = new KeyFrameDB_Connection_MySqlI( array( 'host'=>$host, 'userid'=>$userid, 'password'=>$password ) );
    }

    function Connect( $dbname ) {
        if( !($bOk = $this->oConn->_connect( $dbname )) ) {
            $this->errmsg = "Cannot connect to database $dbname : ".$this->oConn->_getConnectErrMsg();
        }
        $this->dbname = $dbname;
        return( $bOk );
    }

    function GetDB()  { return( $this->dbname ); }

    /**
     * Execute an SQL query
     * @param string $sql - Query to execute
     * @return boolean - True if the query executed successfully, false otherwise
     */
    function Execute( $sql ) {
        $this->debugStart($sql);
        $bOk = $this->oConn->_execute( $sql );
        $this->debugEnd( !$bOk );
        return( $bOk );
    }
    
    /**
     * Execute an SQL query using prepared statements
     * @param string $sql - SQL to execute
     * @param array $params - Optional: Parameters for the query
     * @return boolean - True if the query executed successfully, false otherwise
     */
    function Execute_prepared( string $sql, ?array $params = null): bool {
        $this->debugStart($sql, $params);
        $bOk = $this->oConn->_execute( $sql, $params );
        $this->debugEnd( !$bOk );
        return( $bOk );
    }

    function GetAffectedRows() {
        return( $this->oConn->getAffectedRows() );
    }

    /**
     * Query the db and return a cursor to the result set.
     * @param string $query - SQL to execute
     * @param array $params - Optional: Parameters for the query
     * @return mysqli_result|boolean
     */
    function CursorOpen( $query, ?array $params = null ) {
        $this->debugStart( $query, $params );
        $dbc = $this->oConn->_cursorOpen( $query, $params );
        $this->debugEnd( $dbc == NULL );
        return( $dbc );
    }

    /**
     * Fetch the results from a db cursor
     * @param mysqli_result $dbc - Cursor to retrieve the result set from
     * @param string $result_type - Whether to return results as an associative array, a numeric array, or both
     * @return NULL|array|boolean
     * @see KeyframeDatabase::CursorOpen()
     */
    function CursorFetch( $dbc, $result_type = KEYFRAMEDB_RESULT_BOTH )
    {
        return( ($dbc && ($ra = $this->oConn->_cursorFetch($dbc, $result_type))) ? $ra  : NULL );
    }

    /**
     * Get the number of rows from a db cursor
     * @param mysqli_result $dbc - Cursor to get the number of rows from
     * @return number|string - Number of rows returned by the cursor
     * @see KeyframeDatabase::CursorOpen()
     */
    function CursorGetNumRows( $dbc )  { return( $dbc ? $this->oConn->_cursorGetNumRows($dbc) : 0 ); }
    
    /**
     * Close a db cursor
     * @param mysqli_result $dbc - Cursor to close
     * @see KeyframeDatabase::CursorOpen()
     */
    function CursorClose( $dbc )       { if($dbc) $this->oConn->_cursorClose($dbc); }

    /**
     * INSERT a row into a table that contains an AUTO_INCREMENT column, and return the value of that column.
     * @param string $sql - query to execute. "INSERT INTO foo (id, bar) VALUES (NULL, x)"
     * @param array $params - Optional: Parameters for the query.
     * @return string|int - Value of the AUTO_INCREMENT column
     */
    function InsertAutoInc( string $sql, ?array $params = null ) {
        $this->debugStart( $sql, $params );
        $kNew = $this->oConn->_insertAutoInc($sql, $params);
        $this->debugEnd( $kNew == 0 );
        return( $kNew );
    }

    /**
     * Execute a query, returning the first row of the result set.
     * @param string $query - Query to execute
     * @param string $result_type - Whether to return results as an associative array, a numeric array, or both
     * @return NULL|array|false
     */
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
    
    /**
     * Execute a query using prepared statements, returning the first row of the result set
     * @param string $query - Query to execute
     * @param array $params - Optional: Parameters for the query
     * @param string $result_type - Whether to return results as an associative array, a numeric array, or both
     * @return NULL|array|boolean
     */
    function QueryRA_prepared ( string $query, ?array $params = null, string $result_type = KEYFRAMEDB_RESULT_BOTH) {
        /* Return the array of values from the first row of a SELECT query
         */
        $ra = NULL;
        if( ($dbc = $this->CursorOpen( $query, $params )) ) {
            $ra = $this->CursorFetch( $dbc, $result_type );
            $this->CursorClose( $dbc );
        }
        return( $ra );
    }

    /**
     * Execute a query, returning a single value from the first row.
     * Eg: Query1('SELECT p1 FROM table;'); would return the first value of p1
     * @param string $query - Query to execute
     * @return unknown|NULL
     * @see KeyframeDatabase::QueryRowsRA1()
     */
    function Query1( $query ) {
        /* Return a single value from the first row of a SELECT p1 FROM... query
         */
        $ra = $this->QueryRA( $query );
        return( $ra ? $ra[0] : NULL );
    }
    
    /**
     * Execute a query using prepared statements, returning a single value from the first row
     * Eg: Query1('SELECT p1 FROM table;'); would return the first value of p1
     * @param string $query - Query to execute
     * @param array $params - Optional: Parameters for the query
     * @return unknown|NULL
     */
    function Query1_prepared( string $query, ?array $params = null) {
        /* Return a single value from the first row of a SELECT p1 FROM... query
         */
        $ra = $this->QueryRA_prepared( $query, $params );
        return( $ra ? $ra[0] : NULL );
    }

    /**
     * Execute a query, returning all the rows in the result set
     * @param string $query - Query to execute
     * @param string $result_type - Whether to return results as an associative array, a numeric array, or both
     * @return NULL|array|boolean
     */
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
    
    /**
     * Execute a query using prepared statements, returning all the rows in the result set
     * @param string $query - Query to execute
     * @param string $result_type - Whether to return results as an associative array, a numeric array, or both
     * @return NULL|array|boolean
     */
    function QueryRowsRA_prepared( string $query, ?array $params, string $result_type = KEYFRAMEDB_RESULT_BOTH ) {
        /* Return an array of rows:  array( array( fld1 => val1, fld2 => val2, ...)
         *                                  array( fld1 => val1, fld2 => val2, ...) ... )
         */
        $ra = array();
        if( ($dbc = $this->CursorOpen( $query, $params )) ) {
            while( ($raRow = $this->CursorFetch( $dbc, $result_type )) ) {
                $ra[] = $raRow;
            }
            $this->CursorClose( $dbc );
        }
        return( $ra );
    }

    /**
     * Execute a query, returning a single column of the result set.
     * Eg: 'SELECT k FROM tbl;' for a table with 3 rows, would return array( k_of_row1, k_of_row2, k_of_row3 )
     * @param string $query - Query to execute
     * @return array - one dimensional array of the results
     * @see KeyframeDatabase::Query1()
     */
    function QueryRowsRA1( $query )
    /******************************
       Fetch an array of rows where each row contains one value, and collapse the rows into a single-dimensional array.

       e.g. SELECT k FROM tbl; for a table with 3 rows, would return array( k_of_row1, k_of_row2, k_of_row3 )
     */
    {
        $ra = array();
        if( ($dbc = $this->CursorOpen( $query )) ) {
            while( ($raRow = $this->CursorFetch( $dbc, KEYFRAMEDB_RESULT_NUM )) ) {
                $ra[] = $raRow[0];
            }
            $this->CursorClose( $dbc );
        }
        return( $ra );
    }
    
    /**
     * Execute a query using prepared statements, returning a single column of the result set.
     * Eg: 'SELECT k FROM tbl;' for a table with 3 rows, would return array( k_of_row1, k_of_row2, k_of_row3 )
     * @param string $query - Query to execute
     * @return array - one dimensional array of the results
     * @see KeyframeDatabase::Query1()
     */
    function QueryRowsRA1_prepared( string $query, ?array $params) {
        $ra = array();
        if( ($dbc = $this->CursorOpen( $query, $params )) ) {
            while( ($raRow = $this->CursorFetch( $dbc, KEYFRAMEDB_RESULT_NUM )) ) {
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

    /**
     * Set the debug level.
     * Possible values are: 
     * 0 = No debug messages,
     * 1 = Errors only,
     * 2 = Queries and Errors
     * @param int $bDebug - Debug level to set
     */
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

        if( ($dbc = $this->CursorOpen("SHOW FIELDS FROM ".$this->EscapeString($table))) ) {
            while( ($ra = $this->CursorFetch( $dbc )) ) {
                $raOut[$ra['Field']]['type'] = $ra['Type'];
                $raOut[$ra['Field']]['null'] = ($ra['Null'] == "YES");
                $raOut[$ra['Field']]['default'] = $ra['Default'];

                // set the kf_type and kf_default
                if( substr($ra['Type'],0,3) == 'int' ||
                    substr($ra['Type'],0,7) == 'tinyint' ||
                    substr($ra['Type'],0,8) == 'smallint' ||
                    substr($ra['Type'],0,6) == 'bigint' )
                {
                    $t = 'I';
                    $d = 0;
                } elseif( SEEDCore_StartsWith($ra['Type'], 'decimal') ) {
                    $t = 'F';
                    $d = 0.0;
                } else {
                    $t = 'S';
                    $d = "";
                }
                $raOut[$ra['Field']]['kf_type'] = $t;
                // When MySQL 5 shows 'NULL' as Default on the command line client, it returns an empty string in the cursor.
                $raOut[$ra['Field']]['kf_default'] =
                    (!$ra['Default'] || $ra['Default'] == 'NULL') ? $d : $ra['Default'];
            }
            $this->CursorClose( $dbc );
        }

        return( $raOut );
    }

    /**
     * Check if a table exists
     * @param string $table - Table to check for
     * @return boolean - True if the table exists, false otherwise
     */
    function TableExists( $table )
    /*****************************
     */
    {
        $bExists = false;

        //in more recent versions of PHP/mysqli this gives a fatal error if the table is not found
        //$raFld = $this->QueryRA("SHOW FIELDS FROM $table");    // gets the first row returned
        //return( $raFld['Field'] != "" );

        if( strpos( $table, '.' ) !== false ) {
            // the method below can not recognize a table with a db prefix, so confirm that it is the default db and remove the prefix
            list($dbname,$table) = explode( '.', $table );
            if( $dbname && $dbname != $this->GetDB() )  return( false );    // can't use below to test table in a different db
        }

        if( ($dbc = $this->CursorOpen( "SHOW TABLES LIKE '".$this->EscapeString($table)."'" )) ) {
            $bExists = $this->CursorGetNumRows($dbc) == 1;
            $this->CursorClose($dbc);
        }
        return( $bExists );
    }

    /**
     * Escape a string for use in SQL statements
     * @param string $s - String to escape
     * @return string - Escaped string
     */
    function EscapeString( $s )
    /**************************
        Escape the given string for use in SQL statements
     */
    {
        return( $this->oConn->_escapeString( $s ) );
    }

    private function debugStart( $sql, ?array $params = null )
    {
        if ($params) {
            $this->lastQuery = $sql." Parameters: [".implode(',', $params)."]";
        } else {
            $this->lastQuery = $sql;
        }
        if( $this->bDebug >= 2 && $params) {
            echo "<p style='font-size:9pt;font-family:courier,monospace;color:gray;'>".nl2br($sql)."<br />Parameters: [".implode(',', $params)."]</p>";
        }
        else if( $this->bDebug >= 2) {
            echo "<p style='font-size:9pt;font-family:courier,monospace;color:gray;'>".nl2br($sql)."</p>";
        }
    }

    private function debugEnd( $bError )
    {
        if( $bError && $this->bDebug ) { echo "<p style='font-size:9pt;font-family:courier,monospace;color:gray;'>".$this->GetErrMsg()."</p>"; }
    }
}
