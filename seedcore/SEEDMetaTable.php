<?php

/* SEEDMetaTable
 *
 * Copyright (c) 2011-2018 Seeds of Diversity Canada
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
