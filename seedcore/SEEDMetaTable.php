<?php

/* SEEDMetaTable
 *
 * Copyright (c) 2011-2020 Seeds of Diversity Canada
 *
 * Emulate a set of database tables, storing their data in a single table structure.
 * This is a handy way to store stuff in a table without really creating a new database table.
 *
 * Only simple lookups are possible, not joins or other advanced database features.
 *
 * There are 3 implementations here:
 *     1) SEEDMetaTable_Tables : a system of virtual tables, columns, rows, and fields
 *            fairly complex, powerful enough to support an application that uses tabular data.
 *            Designed with ordered cols and rows like a spreadsheet.
 *
 *     2) SEEDMetaTable_TablesLite : virtual tables that group urlparm tuples into sets of rows indexable by arbitrary string keys
 *            A simpler implementation than SEEDMetaTables_Tables, but appropriate for many applications.
 *
 *     3) SEEDMetaTable_StringBucket : a place to store strings, keyed by (namespace,key)
 *            sometimes you just want a place to throw random stuff
 */


/* StringBucket
 *
 * Store a ns,k,v tuple in a table
 *
 * PutStr( ns, k, v )  puts a namespaced key/value in a table.  Only one such ns,k,v can exist at a time.
 * GetStr( ns, k )     gets the value back
 * DeleteStr( ns, k )  deletes the tuple from the table
 */
class SEEDMetaTable_StringBucket
{
    private $kfdb;
    private $uid;

    function __construct( KeyframeDatabase $kfdb, $uid = 0 )
    {
        $this->kfdb = $kfdb;
        $this->uid = $uid;
    }

    function GetStr( $ns, $k )
    {
        $ns = addslashes($ns);
        $k = addslashes($k);
        $v = $this->kfdb->Query1("SELECT v FROM SEEDMetaTable_StringBucket WHERE ns='$ns' AND k='$k'");
        return( $v );
    }

    function PutStr( $ns, $k, $v )
    {
        $ns = addslashes($ns);
        $k = addslashes($k);
        $v = addslashes($v);
        $kBucket = $this->kfdb->Query1("SELECT _key FROM SEEDMetaTable_StringBucket WHERE ns='$ns' AND k='$k'");
        if( $kBucket ) {
            $this->kfdb->Execute("UPDATE SEEDMetaTable_StringBucket SET v='$v',_updated=NOW(),_updated_by='{$this->uid}' WHERE ns='$ns' AND k='$k'");
        } else {
            $this->kfdb->Execute("INSERT INTO SEEDMetaTable_StringBucket (_created,_updated,_created_by,_updated_by,ns,k,v) "
                                ."VALUES (NOW(),NOW(),{$this->uid},{$this->uid},'$ns','$k','$v')" );
        }
    }

    function DeleteStr( $ns, $k )
    {
        $ns = addslashes($ns);
        $k = addslashes($k);
        $this->kfdb->Execute("DELETE FROM SEEDMetaTable_StringBucket WHERE ns='$ns' AND k='$k'");
    }

    const SqlCreate = "
        CREATE TABLE SEEDMetaTable_StringBucket (
            # This is a place to throw random stuff, keyed by (ns,k)

                _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
                _created    DATETIME,
                _created_by INTEGER,
                _updated    DATETIME,
                _updated_by INTEGER,
                _status     INTEGER DEFAULT 0,

            ns                      VARCHAR(200) NOT NULL,    -- namespace
            k                       VARCHAR(200) NOT NULL,    -- key
            v                       TEXT NOT NULL,            -- value

            INDEX (ns(20),k(20))
        );
        ";
}


/* TablesLite
 *
 * Stores a list of virtual tables in SEEDMetaTables_TablesLite
 * Stores a list of rows in SEEDMetaTables_TablesLite_Rows in the form of (table,[keys],values-urlencoded)
 *
 * PutRow( table, k, lookup-keys, values )  stores a set of values and lookup-keys in the given table with primary key k
 * GetRow{various}( table, ... )            fetches one or more rows by primary or lookup-keys
 * DeleteRow( k )                           deletes a row
 *
 *  This object is stateless, so it can manage any number of open tables simultaneously.
 */
class SEEDMetaTable_TablesLite
{
    private $oApp;
    private $uid;

    function __construct( SEEDAppDB $oApp, $uid )
    {
        $this->oApp = $oApp;
        $this->uid = $uid;
    }

    function OpenTable( string $tablename )
    /**************************************
        Open or create the named virtual table
     */
    {
        $tablename = addslashes($tablename);
        if( !($kTable = $this->oApp->kfdb->Query1( "SELECT _key FROM SEEDMetaTable_TablesLite WHERE _status='0' AND table_name='$tablename'" )) ) {
            $kTable = $this->kfdb->InsertAutoInc(
                        "INSERT INTO SEEDMetaTable_TablesLite (_key,_created,_created_by,_updated,_updated_by,table_name) "
                       ."VALUES (NULL,NOW(),'{$this->uid}',NOW(),'{$this->uid}','$tablename')" );
        }
        return( $kTable );
    }

    function GetRows( int $kTable, array $raParms )
    /**********************************************
        Return all rows that match the conjunction (AND) of the lookup keys, and optionally map the keys into the returned values

            [ TablesLiteRow._key => [ values..., mapped keys ]

        Parms: k1, k2, k3          = filter rows on the (AND) of these specified key values

        By default the lookup-key values are returned as k1=>{value['k1']}, k2=>{value['k2']}
        Optionally map k1, k2, k3 to meaningful names.
        e.g. raParms['k1map'=>'foo','k2map'=>'bar'] returns the lookup-key values as foo=>{value['k1']} and bar=>{value['k2']}
     */
    {
        $raCond = ["fk_SEEDMetaTable_TablesLite='$kTable'"];
        if( isset($raParms['k1']) )  $raCond[] = "k1='".addslashes($raParms['k1'])."'";
        if( isset($raParms['k2']) )  $raCond[] = "k2='".addslashes($raParms['k2'])."'";
        if( isset($raParms['k3']) )  $raCond[] = "k3='".addslashes($raParms['k3'])."'";
        $sCond = implode( " AND ", $raCond );

        $raRet = array();
        if( ($dbc = $this->oApp->kfdb->CursorOpen( "SELECT * FROM SEEDMetaTable_TablesLite_Rows WHERE _status='0' AND $sCond" ) )) {
            while( ($ra = $this->oApp->kfdb->CursorFetch($dbc)) ) {
                $raRet[$ra['_key']] = $this->unpackRow($ra,$raParms);
            }
        }
        return( $raRet );
    }

    function GetRowByKey( int $kRow, array $raParms = [] )
    /*****************************************************
        Get the row with _key kRow, and return just the values.

        By default the lookup-key values are returned as k1=>{value['k1']}, k2=>{value['k2']}
        Optionally map k1, k2, k3 to meaningful names.
        e.g. raParms['k1map'=>'foo','k2map'=>'bar'] returns the lookup-key values as foo=>{value['k1']} and bar=>{value['k2']}
     */
    {
        $ra = $this->oApp->kfdb->QueryRA( "SELECT * FROM SEEDMetaTable_TablesLite_Rows WHERE _key='$kRow'");

        return( @$ra['_key'] ? $this->unpackRow($ra,$raParms) : NULL );
    }

    function EnumKeys( int $kTable, string $keyname )
    /************************************************
        Return a list of the distinct values for a given lookup-key
     */
    {
        $ra = [];

        if( in_array($keyname, ['k1','k2','k3']) ) {
            $ra = $this->oApp->kfdb->QueryRowsRA1( "SELECT distinct $keyname FROM SEEDMetaTable_TablesLite_Rows "
                                                  ."WHERE _status='0' AND fk_SEEDMetaTable_TablesLite='$kTable'" );
        }
        return( $ra );
    }

    private function unpackRow( $ra, $raParms )
    /******************************************
        Unpack all values into a plain array, optionally mapping k1,k2,k3 to meaningful names
     */
    {
        $ra1 = SEEDCore_ParmsURL2RA( $ra['vals'] );
        if( ($k = @$raParms['k1map']) ) { $ra1[$k] = $ra['k1']; } else { $ra1['k1'] = $ra['k1']; }
        if( ($k = @$raParms['k2map']) ) { $ra1[$k] = $ra['k2']; } else { $ra1['k2'] = $ra['k2']; }
        if( ($k = @$raParms['k3map']) ) { $ra1[$k] = $ra['k3']; } else { $ra1['k3'] = $ra['k3']; }

        return( $ra1 );
    }


    function PutRow( int $kTable, int $kRow, array $raVals, $k1 = NULL, $k2 = NULL, $k3 = NULL )
    /*******************************************************************************************
        Put a row in the table. If kRow is 0 make a new row else overwrite the existing row.
        Return the resulting kRow if successful.
     */
    {
        $dbVals = addslashes(SEEDCore_ParmsRA2URL( $raVals ));
        $dbK1 = ($k1 === NULL ? "NULL" : ("'".addslashes($k1)."'") );
        $dbK2 = ($k2 === NULL ? "NULL" : ("'".addslashes($k2)."'") );
        $dbK3 = ($k3 === NULL ? "NULL" : ("'".addslashes($k3)."'") );
        if( $kRow ) {
            if( !$this->oApp->kfdb->Execute( "UPDATE SEEDMetaTable_TablesLite_Rows "
                                            ."SET _updated=NOW(),_updated_by='{$this->uid}',k1=$dbK1,k2=$dbK2,k3=$dbK3,vals='$dbVals' "
                                            ."WHERE _key='$kRow'" ) ) {
                $kRow = 0;
            }
        } else {
            $kRow = $this->oApp->kfdb->InsertAutoInc(
                        "INSERT INTO SEEDMetaTable_TablesLite_Rows "
                       ."(_key,_created,_created_by,_updated,_updated_by,fk_SEEDMetaTable_TablesLite,k1,k2,k3,vals) "
                       ."VALUES (NULL,NOW(),'{$this->uid}',NOW(),'{$this->uid}',$kTable,$dbK1,$dbK2,$dbK3,'$dbVals')" );
        }
        return( $kRow );
    }

    function DeleteRow( $kRow )
    {
        $this->oApp->kfdb->Execute( "DELETE FROM SEEDMetaTable_TablesLite_Rows WHERE _key='$kRow'" );
    }

    const SqlCreate = "
        CREATE TABLE SEEDMetaTable_TablesLite (
            # These are the virtual tables in the TablesLite system

                _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
                _created    DATETIME,
                _created_by INTEGER,
                _updated    DATETIME,
                _updated_by INTEGER,
                _status     INTEGER DEFAULT 0,

            table_name              VARCHAR(200) NOT NULL,

        # maybe useful?
        #   permclass               INTEGER NOT NULL,

            INDEX (table_name(20))
        );

        CREATE TABLE SEEDMetaTable_TablesLite_Rows (
            # These are the rows of the virtual tables in the TablesLite system

                _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
                _created    DATETIME,
                _created_by INTEGER,
                _updated    DATETIME,
                _updated_by INTEGER,
                _status     INTEGER DEFAULT 0,

            fk_SEEDMetaTable_TablesLite  INTEGER NOT NULL,
            k1                      VARCHAR(200) NULL,        # arbitrary lookup-key
            k2                      VARCHAR(200) NULL,        # arbitrary lookup-key
            k3                      VARCHAR(200) NULL,        # arbitrary lookup-key
            vals                    TEXT NOT NULL,            # urlparm of non-indexed values  e.g. field1=val1&field2=val2...

            INDEX (fk_SEEDMetaTable_TablesLite),
            INDEX (fk_SEEDMetaTable_TablesLite, k1),
            INDEX (fk_SEEDMetaTable_TablesLite, k2),
            INDEX (fk_SEEDMetaTable_TablesLite, k3)
        );
    ";
}
