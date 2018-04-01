<?php

/* KeyframeRelation
 *
 * Copyright (c) 2006-2018 Seeds of Diversity Canada


KeyframeRelation allows specification and management of complex multi-table data relationships.

A Relation is a logical tuple of columns from one or more tables.
A View is an ordered set of data rows for a Relation, after (optional) filter and sort.
A Window is a contiguous span of ordered rows from a View, numbering from 1 to count(View) rows.

Every Relation has a base table, and optionally foreign tables that are related by keys.
Rows of a Relation can be retrieved individually or by cursor.
Only the base table can be altered (UPDATE, INSERT, DELETE) through a Relation.

The system is divided into three levels:
1) KeyframeDatabase: direct access to the database engine
2) KeyframeRelation: uses KeyFrameDatabase to read/write db per the constraints of the defined Relation
3) KeyframeRecord: created by KeyframeRelation to contain/update data of a single record of the Relation
   KeyframeRecordCursor: an extension of KyeframeRecord, created by KeyframeRelation to read/update a set of records

Usage:
1) Create a KeyframeDatabase
2) Create a KeyframeRelation, using the KFDB and a Relation definition.
3) Use KeyframeRelation to create cursors on the relation or select single rows into record classes
4) Use cursors and records to read, write, insert individual rows


KeyframeRelation::CreateRecord()        - make an empty KFRecord for the relation
KeyframeRelation::CreateRecordFromRA()  - make a new KFRecord from the data in a given array (does not consider magic_quotes)
KeyframeRelation::CreateRecordFromGPC() - make a new KFRecord from a GPC global array (handles magic_quotes)
KeyframeRelation::CreateRecordCursor()  - get a KFRecordCursor on the relation
KeyframeRelation::GetRecordFromDB()     - fetch a KFRecord from the database
KeyframeRelation::GetRecordFromDBKey()  - fetch a KFRecord from the database by base _key
KeyframeRecordCursor::CursorFetch()     - load the KFRecord with the next record from the cursor
KeyframeRecord::GetValue()/SetValue()   - get and set values of the record
KeyframeRecord::PutDBRow()              - write any changed base table values to the database (use this for INSERT and UPDATE)


Database tables that use KeyFrameRelation/KFRecord must have the following columns.
The _key column must be the first AUTO_INCREMENT column in the table.

        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,


Sample KeyframeRelation definition:
$kfreldef =
    array( "Tables" => array( 'alias1' => array( "Table" => 'baseTable',
                                                 "Fields" => array( array("col"=>"title",        "type"=>"S"),
                                                                    array("col"=>"quantity",     "type"=>"I", "default"=> 1) ) ),
                              'alias2' => array( "Table" => "foreignTable1",
                                                 "Fields" => array( array("col"=>"fk_baseTable", "type"=>"K"),
                                                                    array("col"=>"name",         "type"=>"S"),
                                                                    array("col"=>"year",         "type"=>"I", "alias"=>"t2year") ) ) ) );

    This creates a join between baseTable and foreignTable, automatically generating the constraint alias2.fk_baseTable=alias1._key
*/


include_once( "KeyframeDB.php" );


/* _status codes
 */
define("KEYFRAMERECORD_STATUS_NORMAL",  "0");
define("KEYFRAMERECORD_STATUS_DELETED", "1");
define("KEYFRAMERECORD_STATUS_HIDDEN",  "2");


class KeyFrame_Relation
/*********************
 */
{
    private $kfdb;
    private $kfrdef;
    private $uid;

    // internal constants
    private $baseTable = null;  // ref to the base table definition in $this->kfrdef
    private $baseTableAlias;
    private $raTableN2A;        // store all table names and aliases for reference ( array of tableName => tableAlias )
    private $raColAlias;        // store all field names for reference ( array of colAlias => tableAlias.col )

    private $qSelect = null;            // cache the constant part of the SELECT query (with the fields clause substitutable)
    private $qSelectFieldsClause = "";  // cache the default fields clause (caller can override)

    // variables
    private $logFile = null;

    function SetLogFile( $filename )    { $this->logFile = $filename; }
    function KFDB()                     { return( $this->kfdb ); }      // just nice to be able to get this for random stuff sometimes
    function UID()                      { return( $this->uid ); }       // just nice to be able to get this for random stuff sometimes
    function BaseTableName()            { return( $this->baseTable['Table'] ); }
    function BaseTableFields()          { return( $this->baseTable['Fields'] ); }
    function TablesDef()                { return( $this->kfrdef['Tables'] ); }

    function __construct( KeyframeDatabase $kfdb, $kfrdef, $uid, $raKfrelParms = array() )
    /*************************************************************************************
     */
    {
        $this->kfdb   = $kfdb;
        $this->kfrdef = $kfrdef;
        $this->uid    = $uid;

        if( @$raKfrelParms['logfile'] ) { $this->SetLogFile( $raKfrelParms['logfile'] ); }

        $bFirst = true;
        foreach( $this->kfrdef['Tables'] as $a => &$t ) {
            /* Make a lookup table of tablename->alias.
             * The kfrdef can always be used as a lookup of alias->tablename.
             */
            $this->raTableN2A[$t['Table']] = $a;

            /* If Type is not specified, the first one is Base
             */
            if( empty($t['Type']) ) {
                $t['Type'] = $bFirst ? "Base" : "Join";
            }
            $bFirst = false;
            if( $t['Type'] == 'Base' ) {
                $this->baseTableAlias = $a;
            }

            /* Auto-fields are designated by the string "Auto" as the Fields value (instead of an array of fields definitions).
             * The database is queried for the fields in the table.
             * The basic KF fields are ignored here, but appended below.
             */
            if( $t['Fields'] == "Auto" ) {
                $t['Fields'] = array();

                $ra = $kfdb->GetFields( $t['Table'] );
                foreach( $ra as $fld => $raFld ) {
                    if( in_array( $fld, array("_key","_created","_created_by","_updated","_updated_by","_status") ) )  continue;

                    $t['Fields'][] = array( 'type'=>$raFld['kf_type'], 'col'=>$fld, 'default'=>$raFld['kf_default'] );
                }
            }

            /* Add KF fields, unless KFCompat=="no".
             * Non-KF tables defined here are joined, but not constrained. Conditions (e.g. WHERE A.foo=B.bar) should be
             * specified at CreateRecordCursor etc.
             */
            if( @$t['KFCompat'] != "no" ) {
                $t['Fields'][] = array( 'type'=>'I', 'col'=>'_key',        'default'=>0 );
                $t['Fields'][] = array( 'type'=>'S', 'col'=>'_updated',    'default'=>0 );
                $t['Fields'][] = array( 'type'=>'I', 'col'=>'_updated_by', 'default'=>$this->uid );
                $t['Fields'][] = array( 'type'=>'S', 'col'=>'_created',    'default'=>0 );
                $t['Fields'][] = array( 'type'=>'I', 'col'=>'_created_by', 'default'=>$this->uid );
                $t['Fields'][] = array( 'type'=>'I', 'col'=>'_status',     'default'=>0 );
            }

            /* Make a lookup table of colalias->colname.
             * Set a default alias for every column that doesn't have one.
             * Default col aliases for base table are the column names.  Default col alias for other tables is tableAlias_col
             */
            foreach( $t['Fields'] as &$f ) {
                if( empty($f['alias']) ) {
                    $f['alias'] = ($t['Type'] == 'Base' ? $f['col'] : ($a."_".$f['col']));
                }
                $this->raColAlias[$f['alias']] = $a.".".$f['col'];
            }
            unset($f);
        }
        unset($t);  // always do this after foreach with reference, especially if you use $t again

        /* Refer to the base table definition, so we can look at it later.
         * Do this here, like this, instead of saving the reference within the foreach, because of weird behaviour with copying reference variables
         */
        $this->baseTable = $this->kfrdef['Tables'][$this->baseTableAlias];

        /* Calculate the non-varying portion of the SELECT statement for this Relation.
         */
        $this->qSelect = $this->makeQSelect();
    }

    function IsBaseField( $q )
    /*************************
        return true if $q is a field name of the base table
     */
    {
        $ret = false;
        foreach( $this->baseTable['Fields'] as $f ) {
            if( $f['alias'] == $q ) {
                $ret = true;
                break;
            }
        }
        return( $ret );
    }

    function GetListColAliases( $bBaseOnly = true )
    /**********************************************
     */
    {
        $ra = array();
        if( $bBaseOnly ) {
            foreach( $this->baseTable['Fields'] as $f ) {
                $ra[] = $f['alias'];
            }
        } else {
            foreach( $this->raColAlias as $a => $col ) {
                $ra[] = $a;
            }
        }
        return( $ra );
    }

    function GetRealColName( $alias )
    /********************************
        Given a column alias, return the column name that is used in a SELECT query.

        e.g. Alias=Users_realname, return Users.realname
     */
    {
        return( $this->raColAlias[$alias] );
    }

    function GetDBColName( $table, $col )
    /************************************
        Return the name that is used for the given column in SELECT queries.  (e.g. T2.foo)
        This can be used to generate condition expressions with tables that don't have user-defined alias names (e.g. T2.foo)
     */
    {
        return( $this->raTableN2A[$table].".$col" );  // the value of the array is the tableAlias
    }

    function GetDBTableAlias( $table )
    /*********************************
        Return the alias that is used for the given table.

        Note that this can be used (for example, by a base db access class) to determine whether a particular table exists in the relation.
        e.g. null means the table is not in the definition
     */
    {
        return( isset($this->raTableN2A[$table]) ? $this->raTableN2A[$table] : null );
    }

    function GetDBColAlias( $table, $col )
    /*************************************
        Return the alias that is used for the given column in SELECT queries.  (e.g. T2_foo)
        This can be used to retrieve fields from tables that don't have user-defined alias names
     */
    {
        $alias = "";

        if( @$this->kfrdef['ver'] == 2 ) {
            $t = $this->getTableDef( $table );
            foreach( $t['Fields'] as $f ) {
                if( $f['col'] == $col ) {
                    $alias = $f['alias'];
                    break;
                }
            }
        } else {
            foreach( $this->kfrdef['Tables'] as $t ) {
                if( $t['Table'] == $table ) {
                    foreach( $t['Fields'] as $f ) {
                        if( $f['col'] == $col ) {
                            $alias = $f['alias'];
                            break;
                        }
                    }
                }
            }
        }
        return( $alias );
    }

    private function getTableDef( $tableName )
    {
        $a = $this->raTableN2A( $tableName );
        return( $a ? $this->kfrdef['Tables'][$a] : null );
    }

    /*******************************************************************************************************************
     * Create KeyframeRecords
     */

    function CreateRecord()
    /**********************
        Return an empty KeyframeRecord
     */
    {
//TODO: add kfrel parm "fnPrePopulateNewRecord" = "{fn}" - this is invoked in CreateRecord, after fkDefaults.
        // with no sSelect this just creates an empty record and should not be able to fail
        return( $this->factory_KeyframeRecord() );
    }

    function CreateRecordCursor( $cond = "", $parms = array() )
    /**********************************************************
        Return a KeyframeRecordCursor to retrieve a record set

        parms: sSortCol  => name of column to sort (can be multiple columns comma-separated)
               bSortDown => true/false
               sGroupCol => GROUP BY sGroupCol
               iOffset   => offset of rows to return
               iLimit    => max rows to return (might help to optimize query on the server end)
               iStatus   => _status=iStatus  default 0

               raFieldsOverride => array of colalias=>fld to override the fields clause
     */
    {
        $sSelect = $this->makeSelect( $cond, $parms );
        $kfrc = $this->factory_KeyframeRecordCursor( $sSelect );
        return( $kfrc && $kfrc->IsOpen() ? $kfrc : null );
    }

    function GetRecordFromDB( $cond = "", $parms = array() )
    /*******************************************************
        Return a KeyframeRecord from the database
     */
    {
        $ok = false;

        $sSelect = $this->makeSelect( $cond, $parms );
        if( ($kfr = $this->factory_KeyframeRecord()) &&
            ($ra = $this->kfdb->QueryRA( $sSelect )) )
        {
            $kfr->LoadValuesFromRA( $ra );
            $ok = true;
        }
        return( $ok ? $kfr : null );
    }

    function GetRecordFromDBKey( $key )
    /**********************************
        Return a KeyframeRecord from the database where the base row's _key is $key
     */
    {
        if( !$key ) return( null );
        return( $this->GetRecordFromDB( "{$this->baseTableAlias}._key='$key'", array("iStatus"=>-1) ) );
    }

    function GetRecordSet( $cond, $parms = array() )
    /***********************************************
        Return an array of KeyframeRecord for the given record set
     */
    {
        $ra = array();
        if( ($kfrc = $this->CreateRecordCursor( $cond, $parms ))) {
            while( $kfrc->CursorFetch() ) {
                $kfr = $kfrc->Copy();
                $ra[] = $kfr;
            }
        }
        return( $ra );
    }

    function GetRecordSetRA( $cond, $parms = array() )
    /*************************************************
        Return an array of array() for the given record set
     */
    {
        $ra = array();
        if( ($kfrc = $this->CreateRecordCursor( $cond, $parms )) ) {
            while( $kfrc->CursorFetch() ) {
                $ra[] = $kfrc->ValuesRA();
            }
        }
        return( $ra );
    }

    // Override these to create custom KFRecord objects
    function factory_KeyframeRecord()                 { return( new KeyframeRecord($this)); }
    function factory_KeyframeRecordCursor( $sSelect ) { return( new KeyframeRecordCursor($this,$sSelect)); }

    function _Log( $s, $ok )
    /***********************
     */
    {
        if( !empty($this->logFile) ) {
            if( $fp = fopen( $this->logFile, "a" ) ) {
                if( !$ok )  $s .= " {{".$this->kfdb->GetErrMsg()."}}";

                fwrite( $fp, sprintf( "-- %d %s %s\n", time(), date("Y-m-d H:i:s"), $s ) );
                fclose( $fp );
            }
        }
    }


    /*******************************************************************************************************************
     * Private
     */
    private function makeQSelect()
    /*****************************
        Make the constant part of the SELECT statement. This is based only on kfrdef. Variable portions (filtering, sorting)
        are done in makeSelect

        The SELECT statement is composed of the following pieces:

          SELECT
             [fieldsClause]
         FROM
             [tablesClause]
         WHERE
             [condClause]     the fixed part defined by kfreldef
             AND
             [cond]           the variable part defined by the caller
         [other clauses]      like ORDER BY, GROUP BY, etc

         The statement up to and including [condClause] is defined by kfreldef, and is cached for multiple uses.
         The part of the statement after [condClause] can vary per query.

         [fieldsClause]   contains all column names. Columns of base tables are returned with no prefix, columns of
                          other tables are returned as tableAlias_columnName.

         [tablesClause]   is table1 AS tableAlias1 [jointype] {ON ([joincond])} table2 AS tableAlias2 ...
                          in the order that the tables are declared

                          When Type=="LeftJoin", [jointype] is LEFT JOIN, and [joincond] is JoinOn
                          When Type=="Join", [jointype] is the natural join keyword JOIN
                              If JoinOn is defined, [joincond] is JoinOn.
                              Else if an fk_ relationship exists, [joincond] uses fk_
                              Else the join has no ON clause.
                          This means the fk_ feature is only enabled if neither JoinOn nor LeftJoinOn are defined.
                          That's because the fk_ feature is normally useful for inner joins, and the JoinOn parameter
                          exists to specify non-fk_ relationships.

         [condClause]     contains all conditions defined by the kfreldef


         Table types:
             Base     : should be the first table, default if not specified
             Join     : natural join, default for non-first table
             LeftJoin : left join with the previous table
     */
    {
        $raFieldsClause = array();
        $sTablesClause = "";
        $raCondClause = array();


        $raTables = array();

        /* Pre-process each table by recording the alias, type, and whether there is a fk_ field that matches
         * another table name (modulo db prefix)
         */
        foreach( $this->kfrdef['Tables'] as $a => &$t ) {
            if( !@$t['JoinOn'] ) $t['JoinOn'] = "";
            $t['fk'] = array();
        }
        unset($t);  // always do this after foreach with reference, especially if you use $t again

        /* Find any fk_ fields that match another table in the kfrdef
         *
         * The join is constructed left to right in the order of tables specified. That means it's awkward for the first table
         * to have a fk_ to a later table, because the ON clause has to come after the second table. Much easier if the first table has no fk_
         *
         *      A JOIN B ON (A.fk_B=B._key)                             not too hard to implement if you store the dependency from A
         *      A JOIN B ON (A.fk_B=B._key) JOIN C ON (C.fk_B=B._key)   a lot harder to implement because B and C have to do different things
         *      A JOIN B ON (B.fk_A=A._key) JOIN C ON (C.fk_A=A._key)   easier to implement because each fk_ is in an ON clause with its own table
         *      A JOIN B ON (B.fk_A=A._key) JOIN C ON (C.fk_A=B._key)   this is also not a problem
         */
        foreach( $this->kfrdef['Tables'] as $a => $t ) {
            foreach( $t['Fields'] as $f ) {
                if( substr($f['col'],0,3) != "fk_" || !($foreignTable = substr($f['col'],3)) )  continue;

                /* This is a foreign key to another table. See if that table is in the kfrdef.
                 * Table names can have the db name appended e.g. db1.table, so do the compare after any '.'
                 */
                foreach( $this->raTableN2A as $t2 => $a2 ) {
                    if( ($i = strpos( $t2, '.' )) !== false ) {
                        $t2 = substr( $t2, $i + 1 );
                    }
                    if( $t2 == $foreignTable ) {
                        // $a.$f is a fk_ to $a2._key
                        $this->kfrdef['Tables'][$a]['fk'][] = array( $f['col'], $a2 );
                    }
                }
            }
        }

        // To make the JoinOn for an fk_, the dependent table cannot be the first in the list -- in "A JOIN B" we can't put ON immediately after A
        // If this happens, put a condition in the ConditionClause instead.

        // Also, you can't do A JOIN B ON (B.fk_C=C._key) JOIN C  -- forward reference to C
        // You have to do     A JOIN C JOIN B ON (B.fk_C=C._key)  -- the order of table definition matters
        $bFirst = true;
        foreach( $this->kfrdef['Tables'] as $a => &$t ) {
            if( count($t['fk']) && !$t['JoinOn'] ) {
                $joinOn = "";
                foreach( $t['fk'] as $ra ) {
                    $fkFld = $ra[0];
                    $aTarget = $ra[1];
                    if( $joinOn ) $joinOn .= " AND ";
                    $joinOn .= "$a.$fkFld=$aTarget._key";
                }
                if( !$bFirst ) {
                    $t['JoinOn'] = $joinOn;
                } else {
                    $raCondClause[] = $joinOn;
                }
            }
            $bFirst = false;
        }
        unset($t);

        /* Step through the tables and build the Fields, Table, and Condition clauses
         */
        $raFields = array();
        foreach( $this->kfrdef['Tables'] as $a => $t ) {
            /* Make the Fields clause
             */
            foreach( $t['Fields'] as $f ) {
                $raFieldsClause[] = "$a.{$f['col']} as {$f['alias']}";
            }

            /* Make the Tables clause
             */
            $sTA = "{$t['Table']} AS $a";
            if( !$sTablesClause ) {
                // the first table doesn't have a join
                $sTablesClause .= $sTA;
            } else if( $t['Type'] == "LeftJoin" ) {
                $sTablesClause .= " LEFT JOIN $sTA ON ({$t['JoinOn']})";
            } else {
                $sTablesClause .= " JOIN $sTA".($t['JoinOn'] ? " ON ({$t['JoinOn']})" : "");
            }

            /* Make the Condition clause
             */
            if( isset($t['sCond']) )  $raCondClause[] = $t['sCond'];
        }

        // cache the fields clause, to be substituted later if the caller doesn't override
        $this->qSelectFieldsClause = implode( ',', $raFieldsClause );

        // the kfreldef can define a condition that filters the results or constrains a join
        if( isset($this->kfrdef['Condition']) )  $raCondClause[] = $this->kfrdef['Condition'];

        $q = "SELECT [fields clause]\n"
                        ."FROM $sTablesClause\n"
                        ."WHERE ".(count($raCondClause) ? implode(' AND ', $raCondClause)
                                                         : "1=1");  // do this so additional conds can be added below
        return( $q );
    }


    private function makeSelect( $cond = "", $parms = array() )
    /**********************************************************
     */
    {
        $sGroupCol = @$parms['sGroupCol'];
        $sSortCol  = @$parms['sSortCol'];
        $bSortDown = intval(@$parms['bSortDown']);
        $iOffset   = intval(@$parms['iOffset']);
        $iLimit    = intval(@$parms['iLimit']);
        $iStatus   = intval(@$parms['iStatus']);

        /* $this->qSelect is completely defined by kfrel.
         * Now customize the query by appending conditions and call-specific clauses e.g. ORDER, GROUP
         */
        $q = $this->qSelect;

        /* Make the SELECT field clause.
         *
         * raFieldsOverride takes precedence over all computed select fields.
         *     array( alias=>fld, ... ) generates {fld as alias},...
         *
         * If raGroup is defined, use it to create the select fields.
         *     array( alias=>fld, ... ) uses {fld} as grouping cols and {fld as alias} as select fields
         *
         *     raGroupAlso => array( alias=>fld, .... ) makes {ANY_VALUE(fld) as alias} to retrieve cols not dependent on the group columns
         *
         * Otherwise use the default select fields computed from the kfrel.
         */
        $sFieldsClause = "";
        if( isset($parms['raFieldsOverride']) ) {
            foreach( $parms['raFieldsOverride'] as $alias=>$fld ) {
                $sFieldsClause .= ($sFieldsClause ? "," : "")
                                 ."$fld as $alias";
            }
        } else if( isset($parms['raGroup']) ) {
            foreach( $parms['raGroup'] as $alias=>$fld ) {
                $sFieldsClause .= ($sFieldsClause ? "," : "")
                                 ."$fld as $alias";
                $sGroupCol     .= ($sGroupCol ? "," : "").$fld;
            }
// MariaDB doesn't have ANY_VALUE()
            if( isset($parms['raGroupAnyValue']) ) {
                foreach( $parms['raGroupAnyValue'] as $alias=>$fld ) {
                    $sFieldsClause .= ($sFieldsClause ? "," : "")
                                     ."ANY_VALUE($fld) as $alias";
                }
            }
        } else {
            $sFieldsClause = $this->qSelectFieldsClause;
        }

        $q = str_replace( '[fields clause]', $sFieldsClause, $q );


        if( $cond ) $q .= " AND ($cond)";

        foreach( $this->kfrdef['Tables'] as $a => $t ) {
            if( $iStatus != -1 )  $q .= " AND ($a._status='$iStatus')";
        }

        if( $sGroupCol )  $q .= " GROUP BY $sGroupCol";
        if( $sSortCol )   $q .= " ORDER BY $sSortCol". ($bSortDown ? " DESC" : " ASC");

        if( $iLimit > 0 || $iOffset > 0 ) {
            /* The correct syntax is LIMIT [offset,] limit
             * For compatibility with PostgreSQL, "LIMIT limit OFFSET offset" is supported but "OFFSET offset" is illegal (when LIMIT is infinite).
             * So the only way to make LIMIT infinite in MySQL is to set it to a very big number.
             * The first row is OFFSET 0
             */
            if( $iLimit < 1 )  $iLimit = "4294967295";  // 2^32-1 - make this a string so php doesn't do something weird converting to signed int or something
            $q .= " LIMIT ".($iOffset>0 ? "$iOffset," : "").$iLimit;
        }

        return( $q );
    }
}

class KeyframeRecord
/*******************
    Contains the data of a single record, driven by KeyFrameRelation.
    This is created by KeyFrameRelation, and should not normally be constructed independently by user code.
 */
{
    protected $kfrel;          // the KeyframeRelation that governs this record; protected so KeyframeRecordCursor can access it easily

    // The Record
    // Note that values and dbValSnap use colalias as their keys
    private $key;
    private $values;
    private $dbValSnap;        // a snapshot of the _values most recently retrieved from the db.  For change detection.

    private $keyForce = 0;

    function KFRel()  { return( $this->kfrel ); }

    function __construct( Keyframe_Relation $kfrel )
    /***********************************************
     */
    {
        $this->kfrel = $kfrel;
        $this->Clear();
    }

    function Clear()
    /***************
        Clear the values and set defaults
     */
    {
        $this->key = 0;
        $this->values = array();
        $this->dbValSnap = array();
        $this->keyForce = 0;

        foreach( $this->kfrel->BaseTableFields() as $k ) {
            $this->setDefault($k);
        }
    }

    function Copy()
    /**************
        Return a KeyframeRecord that contains the same data as this one
     */
     {
         $kfr = $this->kfrel->factory_KeyframeRecord();
         $kfr->kfrel = $this->kfrel;
         $kfr->key = $this->key;
         $kfr->values = $this->values;
         $kfr->dbValSnap = $this->dbValSnap;
         $kfr->keyForce = 0;  // don't copy this
         return( $kfr );
     }

    function Value( $k )
    /*******************
        $k is the alias name of a column
     */
    {
        return( array_key_exists( $k, $this->values ) ? $this->values[$k] : null );
    }

    function GetKfrel()         { return( $this->kfrel ); }        // just nice to be able to get this for random stuff sometimes

    function ValueEnt( $k )     { return( SEEDStd_HSC($this->Value($k)) ); }
    function ValueXlat( $k )    { return( $this->Value( $k ) ); }
    function ValueXlatEnt( $k ) { return( $this->ValueEnt( $k ) ); }
    function ValueDB( $k )      { return( addslashes($this->Value($k)) ); }
    function ValuesRA()         { return( $this->values ); }

    function Key()              { return( $this->key ); }
    function IsEmpty( $k )      { $v = $this->Value($k); return( empty($v) ); } // because empty doesn't work on methods
    function SetKey( $i )       { $this->key = $i; $this->SetValue( 'key', $i ); }
    function SetValue( $k, $v )
    {
/*
        $bFound = false;
        foreach( $this->kfrel->baseTable['Fields'] as $f ) {
            if( $f['alias'] == $k ) {
                if( $f['type'] == "S+" ) {
                    $this->_valPrepend[$k] = $v;
                    $bFound = true;
                }
                break;
            }
        }
        if( !$bFound ) { $this->_values[$k] = $v; }
*/
        $this->values[$k] = $v;
    }
    // simulate the function of an S+ type
    function SetValuePrepend( $k, $v ) { $this->SetValue( $k, $v . $this->Value($k) ); }
    function SetValueAppend( $k, $v )  { $this->SetValue( $k, $this->Value($k) . $v ); }

    function SmartValue( $k, $raValues, $v = null )
    /**********************************************
       Ensure that the given value is in the given array. Set it to the first element if not.

       $v !== null : set v, then reject it if it isn't valid
       $v === null : just check that the current value is valid
     */
    {
        if( $v !== null ) $this->SetValue( $k, $v );
        if( !in_array( $this->Value($k), $raValues ) )  $this->SetValue( $k, $raValues[0] );
    }

    /* functions to manage lists of urlparms
     */
    function UrlParmGet( $fld, $k )
    /******************************
        Get the value from an urlparm
     */
    {
        $ra = $this->UrlParmGetRA( $fld );
        return( @$ra[$k] );
    }

    function UrlParmSet( $fld, $k, $v )
    /**********************************
        Set the given value into an urlparm
     */
    {
        $ra = $this->UrlParmGetRA( $fld );
        $ra[$k] = $v;
        $this->UrlParmSetRA( $fld, $ra );
    }

    function UrlParmRemove( $fld, $k )
    /*********************************
        Remove the given parm from an urlparm
     */
    {
        $ra = $this->UrlParmGetRA( $fld );
        if( isset($ra[$k]) )  unset($ra[$k]);
        $this->UrlParmSetRA( $fld, $ra );
    }

    function UrlParmGetRA( $fld )
    /****************************
        Return an array containing all values in an urlparm
     */
    {
// not implemented yet        return( SEEDCore_ParmsURL2RA( $this->value($fld) ) );
    }

    function UrlParmSetRA( $fld, $raParms )
    /**************************************
        Store the given array as an urlparm
     */
     {
// not implemented yet         $s = SEEDCore_ParmsRA2URL( $raParms );
         $this->SetValue( $fld, $s );
     }

    function Expand( $sTemplate, $bEnt = true )
    /******************************************
        Return template string with all [[value]] replaced
     */
    {
// probably easier to loop through ValuesRA and use str_replace on each "[[k]]"
        for(;;) {
            $s1 = strpos( $sTemplate, "[[" );
            $s2 = strpos( $sTemplate, "]]" );
            if( $s1 === false || $s2 === false )  break;
            $k = substr( $sTemplate, $s1 + 2, $s2 - $s1 - 2 );
            if( empty($k) ) break;

            $sTemplate = substr( $sTemplate, 0, $s1 )
                        .($bEnt ? $this->ValueEnt($k) : $this->Value($k))
                        .substr( $sTemplate, $s2+2 );
        }
        return( $sTemplate );
    }

    function ExpandIfNotEmpty( $fld, $sTemplate, $bEnt = true )
    /**********************************************************
        Return template string with [[]] replaced by the value of the field, if it is not empty.
        This lets you do this:  ( !$kfr->IsEmpty('foo') ? ($kfr->value('foo')." items<br/>") : "" )
                    with this:  ExpandTemplateIfNotEmpty( 'foo', "[[]] items<br/>" )
     */
    {
        if( !$this->IsEmpty($fld) )  return( str_replace( "[[]]", ($bEnt ? $this->ValueEnt($fld) : $this->Value($fld)), $sTemplate ) );
    }

/*
    // find values in the given array that match base field names - alters only those that match - GPC handles slashes
    function UpdateBaseValuesFromRA( $p_ra )    { $this->prot_getBaseValuesFromRA( $p_ra, false, KFRECORD_DATASOURCE_RA_NONGPC ); }
    function UpdateBaseValuesFromGPC( $p_ra )   { $this->prot_getBaseValuesFromRA( $p_ra, false, KFRECORD_DATASOURCE_RA_GPC ); }
    function ForceAllBaseValuesFromRA( $p_ra )  { $this->prot_getBaseValuesFromRA( $p_ra, true,  KFRECORD_DATASOURCE_RA_NONGPC ); }
    function ForceAllBaseValuesFromGPC( $p_ra ) { $this->prot_getBaseValuesFromRA( $p_ra, true,  KFRECORD_DATASOURCE_RA_GPC ); }
*/

    function KeyForce( $kForce )
    /***************************
        Force the _key to a particular (different) value, only if that _key is not already being used. The change is made on PutDBRow().
     */
    {
        $this->keyForce = 0;

        // if forcing to current value do nothing but return success
        if( $kForce == $this->key ) return( true );

        if( $kForce && !$this->kfrel->KFDB()->Query1( "SELECT _key FROM ".$this->kfrel->BaseTableName()." WHERE _key='$kForce'" ) ) {
            $this->keyForce = $kForce;
        }

        return( $this->keyForce != 0 );
    }


    function PutDBRow( $bUpdateTS = false )
    /**************************************
        Insert/Update the row as needed.  The choice is based on $this->key==0.

        This does NOT automatically update $this->_values('_created') and ('_updated'), since that requires an extra fetch.
        $bUpdateTS==true causes this fetch
     */
    {
        $ok = false;

        /* Handle prepend types (S+)
Why is this done via _valPrepend? Can't we just prepend to _values using a method? This way it makes a sync problem: SetValue(B); GetValue() != B.
         */
/*
        foreach( $this->kfrel->baseTable['Fields'] as $f ) {
            if( $f['type'] == 'S+' ) {
                if( empty($this->_valPrepend[$f['alias']]) ) continue;

                if( empty($this->_values[$f['alias']]) ) {
                    $this->_values[$f['alias']] = $this->_valPrepend[$f['alias']];
                } else {
                    $this->_values[$f['alias']] = $this->_valPrepend[$f['alias']]."\n".$this->_values[$f['alias']];
                }
                $this->_valPrepend[$f['alias']] = "";
            }
        }
*/
        $kfBaseTableName = $this->kfrel->BaseTableName();
        $kfUid = $this->kfrel->UID();

        if( $this->key ) {
            /* UPDATE all user fields, plus _status, _updated and _updated_by.
             * _key doesn't change unless $this->keyForce
             * _created* never change
             */
            $bDo = false;
            $bSnap = isset($this->dbValSnap['_key']) && $this->dbValSnap['_key'] == $this->key;
            $bKeyForce = $this->keyForce && $this->keyForce != $this->key;

            $s = "UPDATE $kfBaseTableName SET _updated=NOW(),_updated_by='$kfUid'";
            $sClause = "";
            if( $bKeyForce ) {
                $sClause .= ",_key='{$this->keyForce}'";
                $bDo = true;
            }
            foreach( $this->kfrel->BaseTableFields() as $f ) {
                if( !in_array( $f['col'], array("_key", "_created", "_created_by") ) ) {
                    /* Use the dbVal snapshot to inhibit update of unchanged fields. Though the db engine would do this
                     * anyway, this makes kfr log files much more readable.
                     */
                    $val = $this->values[$f['alias']];
                    if( $bSnap && isset($this->dbValSnap[$f['alias']]) && $this->dbValSnap[$f['alias']] == $val ) {
                        continue;
                    }
                    $sClause .= ",{$f['col']}=".$this->putFmtVal( $val, $f['type'] );
                    $bDo = true;
                }
            }
            if( $bDo ) {
                $s .= $sClause." WHERE _key='{$this->key}'";
                $ok = $this->kfrel->KFDB()->Execute( $s );

                // Log U table _key uid: update clause {{err}}
                // Do this before SetKey(keyForce) so it shows the old key
                $this->kfrel->_Log( "U $kfBaseTableName {$this->key} $kfUid: $sClause", $ok );

                if( $ok && $bKeyForce ) {
                    $this->SetKey( $this->keyForce );
                }
            } else {
                $ok = true;
            }
        } else {
            /* INSERT all client fields, plus kfr fields.  Set _created=_updated=NOW().  Set _key to a new autoincrement.
             * Other fields default to the correct initial values.
             */
            $sk = "";
            $sv = "";
            foreach( $this->kfrel->BaseTableFields() as $f ) {
                if( !in_array( $f['col'], array("_key", "_created", "_created_by","_updated","_updated_by") ) ) {
                    $sk .= ",".$f['col'];
                    $sv .= ",".$this->putFmtVal( $this->values[$f['alias']], $f['type'] );
                }
            }

            $sKey = $this->keyForce ? "'{$this->keyForce}'" : "NULL";

            $s = "INSERT INTO $kfBaseTableName (_key,_created,_updated,_created_by,_updated_by $sk) "
                ."VALUES ($sKey,NOW(),NOW(),$kfUid,$kfUid $sv)";

            /* In MySQL, this depends on _key being the first AUTOINCREMENT column.
             */
            if( ($kNew = $this->kfrel->KFDB()->InsertAutoInc( $s )) ) {
                $this->SetKey( $kNew );
                $ok = true;
            }
            // Log I table _key uid: insert clauses {{err}}
            $this->kfrel->_Log( "I $kfBaseTableName {$sKey}->{$kNew} $kfUid: ($sk) ($sv)", $ok );
        }
        if( $ok ) {
            if( $bUpdateTS ) {
                if( ($ra = $this->kfrel->KFDB()->QueryRA( "SELECT _created,_updated FROM $kfBaseTableName WHERE _key='{$this->key}'" )) ) {
                    $this->values['_created'] = $ra['_created'];
                    $this->values['_updated'] = $ra['_updated'];
                }
            }
            $this->snapValues();   // reset the "clean" record state, since the db now matches the KeyframeRecord
        }
        return( $ok );
    }

    function StatusSet( $status )
    /****************************
        Allowed values of $status:
            KEYFRAMERECORD_STATUS_NORMAL
            KEYFRAMERECORD_STATUS_DELETED
            KEYFRAMERECORD_STATUS_HIDDEN
            "Normal"
            "Deleted"
            "Hidden"
            any other integer that means something to you
     */
    {
        switch( $status ) {
            case "Normal":  $status = KEYFRAMERECORD_STATUS_NORMAL;  break;
            case "Deleted": $status = KEYFRAMERECORD_STATUS_DELETED; break;
            case "Hidden":  $status = KEYFRAMERECORD_STATUS_HIDDEN;  break;
        }
        $this->SetValue( "_status", $status );
    }

    function StatusGet()
    {
        return( $this->Value('_status') );
    }

    function DeleteRow()
    /*******************
        Not the same as StatusChange.  This actually deletes the current row permanently.
     */
    {
        $ok = false;

        $kfBaseTableName = $this->kfrel->BaseTableName();

        if( $this->key ) {
            $s = "DELETE FROM $kfBaseTableName WHERE _key='{$this->key}'";

            $ok = $this->kfrel->KFDB()->Execute( $s );
            // Log D table _key uid: {{err}}
            $this->kfrel->_Log( "D $kfBaseTableName {$this->key} ".$this->kfrel->UID().": ", $ok );
        }
        return( $ok );
    }

    /*******************************************************************************************************************
     * Protected methods used by KeyFrameRelation
     */
/* Should be called from within KeyframeRecord

    function prot_setFKDefaults( $raFK = array() )
    [*********************************************
        With no args, this is the same as Clear()
        Args of "table"=>"fk key" cause those foreign keys to be set in the relation, and foreign data to be
        retrieved for non-base tables.  This is especially useful for creating an "empty" row in a form that
        displays read-only data from a parent row.
     *]
    {
        $this->Clear();

        // Leave _dbValSnap cleared because it's only used in updates to the base row, which is not set by this method.


        // N.B. only implemented for one level of indirection from the base table.
        //      Traversal or really smart joins required to fetch data for a second-level row e.g. grandparent
        //      We are assuming that the fk_* column name is not aliased (i.e. Field['col']==Field['alias']=='fk_'.$tableName
        foreach( $raFK as $tableName => $fkKey ) {
            $this->values['fk_'.$tableName] = $fkKey;

            if( ($a = $this->kfrel->raTableN2A[$tableName]) && ($t = $this->kfrdef['Tables'][$a]) ) {
                $raSelFields = array();
                foreach( $t['Fields'] as $f ) {
                    $raSelFields[] = "$a.{$f['col']} as {$f['alias']}";
                }
                $ra = $this->kfrel->KFDB()->QueryRA( "SELECT ".implode(",",$raSelFields)." FROM {$t['Table']} $a"
                                                  ." WHERE $a._key='$fkKey'" );
                // array_merge is easier, but KFDB returns duplicate entries in $ra[0],$ra[1],...
                foreach( $t['Fields'] as $f ) {
                    $this->values[$f['alias']] = $ra[$f['alias']];
                }
            }
        }
    }
*/

    function LoadValuesFromRA( $ra )
    /*******************************
        Copy the values in the given array into the Record
     */
    {
        $this->getBaseValuesFromRA( $ra, true );    // get base values, set defaults(why?), not gpc
        $this->getFKValuesFromArray( $ra );        // get all fk values
        $this->snapValues();
    }


    /*******************************************************************************************************************
     * Private
     */

    private function getFKValuesFromArray( $ra, $bForceDefaults = true )
    /*******************************************************************
     */
    {
        foreach( $this->kfrel->TablesDef() as $a => $t ) {
            if( $t['Type'] == 'Base' )  continue;

            foreach( $t['Fields'] as $f ) {
                $this->getValFromRA( $f, $ra, $bForceDefaults );
            }
        }
    }

    private function getBaseValuesFromRA( $ra, $bForceDefaults )
    /***********************************************************
        Load base field values found in $ra.
        $bForceDefaults should be false when the record already contains values and $ra is a subset
     */
    {
        if( isset($ra['_key']) ) {          // _key won't necessarily be in ra if these are values posted from a form
            $this->key = intval($ra['_key']);
        } else if( $bForceDefaults ) {
            $this->key = 0;
        }
        foreach( $this->kfrel->BaseTableFields() as $f ) {
            $this->getValFromRA( $f, $ra, $bForceDefaults );
        }
    }

    private function getValFromRA( $f, $ra, $bForceDefaults )
    /********************************************************
     */
    {
        if( isset( $ra[$f['alias']] ) ) {
            switch( $f['type'] ) {
                case 'S':   $this->values[$f['alias']] = $ra[$f['alias']];             break;
                case 'F':   $this->values[$f['alias']] = floatval($ra[$f['alias']]);   break;
                case 'K':
                case 'I':
                default:    $this->values[$f['alias']] = intval($ra[$f['alias']]);     break;
            }

        } else if( $bForceDefaults ) {
            $this->setDefault($f);
        }
    }

    private function setDefault( $f )
    /********************************
        $f is one element (an array itself) of a table's Fields array
     */
    {
        if( isset( $f['default'] ) ) {
            $this->values[$f['alias']] = $f['default'];
        } else {
            switch( $f['type'] ) {
                case 'S':   $this->values[$f['alias']] = "";               break;
                case 'F':   $this->values[$f['alias']] = floatval(0.0);    break;
                case 'K':
                case 'I':
                default:    $this->values[$f['alias']] = intval(0);        break;
            }
        }
    }

    private function putFmtVal( $val, $type )
    /****************************************
        Return the correct Put format of the value
     */
    {
        switch( $type ) {
            case 'S':   $s = "'".addslashes($val)."'";      break;
            case 'F':   $s = "'".floatval($val)."'";        break;
            case 'I':
            case 'K':   $s = intval($val);                  break;      // protect against an empty value
            default:    $s = $val;                          break;
        }
        return( $s );
    }

    private function snapValues()
    /****************************
        After reading a DB row, set the record to a "clean" state to prevent unnecessary UPDATE in PutDBRow
     */
    {
        $this->dbValSnap = $this->values;
    }
}


class KeyframeRecordCursor extends KeyframeRecord
/*************************
    A special KeyframeRecord that can cursor over a set of records.
    UPDATE operations can be done between cursor fetches.
 */
{
    private $dbc = null;

    function __construct( Keyframe_Relation $kfrel, $sSelect )
    /*********************************************************
     */
    {
        parent::__construct( $kfrel );

        $this->dbc = $this->kfrel->KFDB()->CursorOpen( $sSelect );    // test IsOpen to see if this worked
    }

    function IsOpen()  { return( $this->dbc != null ); }

    function CursorFetch()
    /*********************
     */
    {
        $ok = false;
        if( $this->IsOpen() && ($ra = $this->kfrel->KFDB()->CursorFetch( $this->dbc )) ) {
            $this->LoadValuesFromRA( $ra );
            $ok = true;
        }
        return( $ok );
    }

    function CursorNumRows()
    /***********************
     */
    {
        return( $this->IsOpen() ? $this->kfrel->KFDB()->CursorGetNumRows($this->dbc) : 0 );
    }

    function CursorClose()
    /*********************
     */
    {
        if( $this->dbc ) {
            $this->kfrel->KFDB()->CursorClose($this->dbc);
            $this->dbc = null;
        }
    }
}


class KeyframeRelationView
/*************************
    A View is the set of rows from a Relation, after a filter, group, and sort.
    A Window is a range of contiguous rows from the View.

    View parms are defined at construction, and cannot change, so GetDataWindow can be repeated at different offsets with consistent results.
 */
{
    private $kfrel;
    private $p_sCond = "";
    private $raViewParms = array();
    private $numRowsCache = 0;

    function __construct( KeyFrame_Relation $kfrel, $sCond = "", $raParms = array() )
    /********************************************************************************
     */
    {
        $this->kfrel = $kfrel;
        $this->SetViewParms( $sCond, $raParms );
    }

    function SetViewParms( $sCond = "", $raParms = array() )
    /********************************************************
        raViewParms:
            sSortCol  - column to ORDER BY
            bSortDown - true:ASC, false:DESC
            sGroupCol - column to GROUP BY
            iStatus
     */
    {
        $this->p_sCond                  = $sCond;
        $this->raViewParms['sSortCol']  = (!empty($raParms['sSortCol']) ? $raParms['sSortCol']  : "_key");
        $this->raViewParms['bSortDown'] = (isset($raParms['bSortDown']) ? $raParms['bSortDown'] : true );
        $this->raViewParms['sGroupCol'] = @$raParms['sGroupCol'];
        $this->raViewParms['iStatus']   = intval(@$raParms['iStatus']);
    }

    function GetDataWindow( $iOffset = 0, $nLimit = -1 )
    /***************************************************
        Return array(KFRecord) for the given span of rows.

        Examples (offset,limit):
            (0,10)   - the first ten rows
            (0,-1)   - all rows
            (10,10)  - rows 10 through 19
            (-1,1)   - the single last row
            (-10,10) - the last ten rows
            (-10,-1) - the last ten rows
     */
    {
        return( $this->kfrel->GetRecordSet( $this->p_sCond, $this->makeWindowParms( $iOffset, $nLimit ) ) );
    }

    function GetDataWindowRA( $iOffset = 0, $nLimit = -1 )
    /*****************************************************
        Like GetDataWindow but return array( array(values) ).
     */
    {
        return( $this->kfrel->GetRecordSetRA( $this->p_sCond, $this->makeWindowParms( $iOffset, $nLimit ) ) );
    }

    function GetDataRow( $iOffset )
    /******************************
        Get one row from the view
     */
    {
        $raKFR = $this->GetDataWindow( $iOffset, 1 );
        return( $raKFR && isset($raKFR[0]) ? $raKFR[0] : null );
    }

    private function makeWindowParms( $iOffset, $nLimit )
    {
        $raWindowParms = $this->raViewParms;
        if( $iOffset >= 0 ) {
            /* Offset from the top
             */
            $raWindowParms['iOffset'] = $iOffset;
            $raWindowParms['iLimit'] = $nLimit;
        } else {
            /* Offset from the bottom
             */
            $i = $this->GetNumRows() + $iOffset;        // the real-number offset
            if( $i < 0 ) $i = 0;
            $raWindowParms['iOffset'] = $i;
            $raWindowParms['iLimit'] = $nLimit;         // no problem if $i+$nLimit > numRows
        }
        return( $raWindowParms );
    }

    function FindOffsetByKey( $k )
    /*****************************
        Return the view offset of the row with key $k
        -1 == not found
     */
    {
        if( !$k )  return( -1 );

        // linear search is the only way I know
        $n = -1;
        $i = 0;
// this could be optimized by adding an option to CreateRecordCursor to retrieve only the keys
// That is called raFieldsOverride
        if( ($kfrc = $this->kfrel->CreateRecordCursor( $this->p_sCond, $this->raViewParms )) ) {
            while( $kfrc->CursorFetch() ) {
                if( $kfrc->Key() == $k ) {
                    $n = $i;
                    break;
                }
                ++$i;
            }
        }
        return( $n );
    }

    function GetNumRows()
    /********************
        Return the size of the view
     */
    {
        if( !$this->numRowsCache ) {
            if( ($kfrc = $this->kfrel->CreateRecordCursor( $this->p_sCond, $this->raViewParms )) ) {
                $this->numRowsCache = $kfrc->CursorNumRows();
                $kfrc->CursorClose();
            }
        }
        return( $this->numRowsCache );
    }
}


class Keyframe_NamedRelations
/****************************
    Simplify access to a set of relations by giving each a name like A, B, or C.
    Implements a set of standard accessors to those relations.
 */
{
    private $raKfrel = array();
    private $kfdb;              // not used here but GetKFDB() is useful to people sometimes

    function __construct( KeyframeDatabase $kfdb, $uid )
    {
        $this->kfdb = $kfdb;
        $this->raKfrel = $this->initKfrel( $kfdb, $uid );  // override this protected function to create an array('A'=>kfrelA, 'B'=>kfrelB)
    }

    function GetKfrel( $sRel ) { return( @$this->raKfrel[$sRel] ); }
    function GetKFDB()         { return( $this->kfdb ); }

    function GetKFR( $sRel, $k )
    /***************************
        Return a kfr with one result pre-loaded
     */
    {
        return( ($kfrel = $this->GetKfrel($sRel)) ? $kfrel->GetRecordFromDBKey( $k ) : null );
    }

    function GetKFRCond( $sRel, $sCond, $raKFParms = array() )
    /*********************************************************
        Return a kfr with one result pre-loaded
     */
    {
        return( ($kfrel = $this->GetKfrel($sRel)) ? $kfrel->GetRecordFromDB( $sCond, $raKFParms ) : null );
    }

    function GetKFRC( $sRel, $sCond = "", $raKFParms = array() )
    /***********************************************************
        Return a kfrc that needs CursorFetch to load the first result
     */
    {
        return( ($kfrel = $this->GetKfrel($sRel)) ? $kfrel->CreateRecordCursor( $sCond, $raKFParms ) : null );
    }

    function GetList( $sRel, $sCond, $raKFParms = array() )
    /******************************************************
        Return an array of results
     */
    {
        return( ($kfrel = $this->GetKfrel($sRel)) ? $kfrel->GetRecordSetRA( $sCond, $raKFParms ) : array() );
    }


    protected function initKfrel( KeyFrameDatabase $kfdb, $uid )
    {
        die( "OVERRIDE with function to create kfrel array" );
    }
}

?>
