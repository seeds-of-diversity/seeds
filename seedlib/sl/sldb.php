<?php

/* Seed Library database access
 *
 * Copyright (c) 2010-2024 Seeds of Diversity Canada
 *
 * SLDBBase                                 All individual tables accessible by named relations. e.g. 'A', 'P', 'SY'
 * SLDBRosetta      extends SLDBBase        Joins of variety naming tables e.g. 'PxS', 'SYxS', 'PYxPxS'
 * SLDBCollection   extends SLDBRosetta     Joins of Seed Library tables e.g. 'IxAxC', 'IxAxPxS', also Rosetta joins for convenience
 * SLDBSources      extends SLDBRosetta     Joins of cv-source tables e.g. 'SrcCVxSrc', 'SrcCVxPxS', also Rosetta joins for convenience
 * SLDBProfile      extends SLDBCollection  Joins of variety-instance-observations tables - cannot join to Collection/Rosetta because pcv is specified multiple ways, but those are available
 */

class _sldb_defs
/***************
    Static class to contain defs. Not intended to be used outside of this file.
 */
{
    static function fldSLCollection()
    {
        return( array( array( "col"=>"name",                "type"=>"S" ),
                       array( "col"=>"uid_owner",           "type"=>"I" ),
                       array( "col"=>"inv_prefix",          "type"=>"S" ),
                       array( "col"=>"inv_counter",         "type"=>"I" ),
                       array( "col"=>"permclass",           "type"=>"I" ),
                       array( "col"=>"eReadAccess",         "type"=>"S" ),
        ));
    }

    static function fldSLAccession()
    {
        return( array( array( "col"=>"fk_sl_pcv",           "type"=>"K" ),
                       array( "col"=>"spec",                "type"=>"S" ),   // e.g. tomato colour, bush/pole bean

                       array( "col"=>"batch_id",            "type"=>"S" ),
                       array( "col"=>"location",            "type"=>"S" ),
                       array( "col"=>"parent_src",          "type"=>"S" ),
                       array( "col"=>"parent_acc",          "type"=>"I" ),

                       array( "col"=>"g_original",          "type"=>"F" ),
                       array( "col"=>"g_have",              "type"=>"F" ),
                       array( "col"=>"g_pgrc",              "type"=>"F" ),
                       array( "col"=>"bDeAcc",              "type"=>"I" ),

                       array( "col"=>"notes",               "type"=>"S" ),

                       array( "col"=>"oname",               "type"=>"S" ),
                       array( "col"=>"x_member",            "type"=>"S" ),   // source of seeds - should just be a string?
                       array( "col"=>"x_d_harvest",         "type"=>"S" ),   // should be a date, except some are ranges and guesses
                       array( "col"=>"x_d_received",        "type"=>"S" ),   // should be a date, except some are ranges and guesses

                       array( "col"=>"psp_obsolete",        "type"=>"S" ) ) );
    }

    static function fldSLInventory()
    {
        return( array( array( "col"=>"fk_sl_collection",    "type"=>"K" ),
                       array( "col"=>"fk_sl_accession",     "type"=>"K" ),
                       array( "col"=>"inv_number",          "type"=>"I" ),
                       array( "col"=>"g_weight",            "type"=>"S" ),
                       array( "col"=>"location",            "type"=>"S" ),
                       array( "col"=>"parent_kInv",         "type"=>"K" ),
                       array( "col"=>"dCreation",           "type"=>"S" ),
                       array( "col"=>"bDeAcc",              "type"=>"I" ),
        ));
    }

    static function fldSLAdoption()
    {
        return( array( array( "col"=>"kDonation",           "type"=>"K" ),  // fk_mbr_donations with an outer join
                       array( "col"=>"fk_mbr_contacts",     "type"=>"K" ),
                       array( "col"=>"donor_name",          "type"=>"S" ),
                       array( "col"=>"public_name",         "type"=>"S" ),
                       array( "col"=>"amount",              "type"=>"F" ),
                       array( "col"=>"sPCV_request",        "type"=>"S" ),
                       array( "col"=>"d_donation",          "type"=>"S" ),
       array( "col"=>"x_d_donation",        "type"=>"S" ),   // remove when migrated to date

                       array( "col"=>"fk_sl_pcv",           "type"=>"K" ),
                       array( "col"=>"notes",               "type"=>"S" ),

                       array( "col"=>"bDoneCV",             "type"=>"I" ),
                       array( "col"=>"bDoneHaveSeed",       "type"=>"I" ),
                       array( "col"=>"bDoneBulkStored",     "type"=>"I" ),
                       array( "col"=>"bDoneAvail",          "type"=>"I" ),
                       array( "col"=>"bDoneBackup",         "type"=>"I" ),

                       array( "col"=>"bAckDonation",        "type"=>"I" ),
                       array( "col"=>"bAckCV",              "type"=>"I" ),
                       array( "col"=>"bAckHaveSeed",        "type"=>"I" ),
                       array( "col"=>"bAckBulkStored",      "type"=>"I" ),
                       array( "col"=>"bAckAvail",           "type"=>"I" ),
                       array( "col"=>"bAckBackup",          "type"=>"I" ) )
              );
    }

    static function fldSLGerm()
    {
        return( array( array( "col"=>"fk_sl_inventory",     "type"=>"S" ),
                       array( "col"=>"dStart",              "type"=>"S" ),
                       array( "col"=>"dEnd",                "type"=>"S" ),
                       array( "col"=>"nSown",               "type"=>"I" ),
                       array( "col"=>"nGerm",               "type"=>"I" ),      // percent
                       array( "col"=>"nGerm_count",         "type"=>"I" ),      // temporary until nGerm contains count instead of %
                       array( "col"=>"notes",               "type"=>"S" ) )
              );
    }


    /**********************
        Rosetta
     */
    static function fldSLSpecies()
    {
        return( array( array( "col"=>"psp",                 "type"=>"S" ),
                       array( "col"=>"name_en",             "type"=>"S" ),
                       array( "col"=>"name_fr",             "type"=>"S" ),
                       array( "col"=>"name_bot",            "type"=>"S" ),
                       array( "col"=>"iname_en",            "type"=>"S" ),
                       array( "col"=>"iname_fr",            "type"=>"S" ),
                       array( "col"=>"family_en",           "type"=>"S" ),
                       array( "col"=>"family_fr",           "type"=>"S" ),
                       array( "col"=>"category",            "type"=>"S" ),
                       array( "col"=>"notes",               "type"=>"S" ) )
              );
    }

    static function fldSLPCV()
    {
        return( array( array( "col"=>"fk_sl_species",       "type"=>"K" ),
                       array( "col"=>"psp",                 "type"=>"S" ),
                       array( "col"=>"name",                "type"=>"S" ),
                       array( "col"=>"t",                   "type"=>"I" ),
                       array( "col"=>"packetLabel",         "type"=>"S" ),
                       array( "col"=>"originHistory",       "type"=>"S" ),
                       array( "col"=>"notes",               "type"=>"S" ) )
                // sound_* are not here because they're only used during rebuild-index and associated manual steps
              );
    }

    static function fldSLSpeciesSyn()
    {
        return( array( array( "col"=>"fk_sl_species",       "type"=>"K" ),
                       array( "col"=>"name",                "type"=>"S" ),
                       array( "col"=>"t",                   "type"=>"I" ),
                       array( "col"=>"notes",               "type"=>"S" ) )
        );
    }

    static function fldSLPCVSyn()
    {
        return( array( array( "col"=>"fk_sl_pcv",           "type"=>"K" ),
                       array( "col"=>"name",                "type"=>"S" ),
                       array( "col"=>"t",                   "type"=>"I" ),
                       array( "col"=>"notes",               "type"=>"S" ) )
        );
    }


    /**********************
        Sources
     */
    static function fldSLSources()
    {
        return( array( array("col"=>"sourcetype",    "type"=>"S"),
                       array("col"=>"name_en",       "type"=>"S"),
                       array("col"=>"name_fr",       "type"=>"S"),
                       array("col"=>"addr_en",       "type"=>"S"),
                       array("col"=>"addr_fr",       "type"=>"S"),
                       array("col"=>"city",          "type"=>"S"),
                       array("col"=>"prov",          "type"=>"S"),
                       array("col"=>"country",       "type"=>"S", "default"=>"Canada"),
                       array("col"=>"postcode",      "type"=>"S"),
                       array("col"=>"phone",         "type"=>"S"),
                       //array("col"=>"fax",           "type"=>"S"),  not our job to keep track of this - look it up on their web site
                       array("col"=>"web",           "type"=>"S"),
                       array("col"=>"web_alt",       "type"=>"S"),
                       array("col"=>"email",         "type"=>"S"),
                       array("col"=>"email_alt",     "type"=>"S"),
                       array("col"=>"desc_en",       "type"=>"S"),
                       array("col"=>"desc_fr",       "type"=>"S"),
                       array("col"=>"year_est",      "type"=>"I"),
                       array("col"=>"comments",      "type"=>"S"),
                       array("col"=>"bShowCompany",  "type"=>"I"),
                       array("col"=>"bSupporter",    "type"=>"I"),
                       array("col"=>"tsVerified",    "type"=>"S"),
                       array("col"=>"bNeedVerify",   "type"=>"I"),
                       array("col"=>"bNeedProof",    "type"=>"I"),
                       array("col"=>"bNeedXlat",     "type"=>"I") )
              );
    }

    static function fldSLSourcesCV()
    {
        return( array( array("col"=>"fk_sl_sources", "type"=>"K"),
                       array("col"=>"fk_sl_species", "type"=>"K"),  // not canonical but useful for now
                       array("col"=>"fk_sl_pcv",     "type"=>"K"),
                       array("col"=>"osp",           "type"=>"S"),
                       array("col"=>"ocv",           "type"=>"S"),
                       array("col"=>"bOrganic",      "type"=>"I"),
                       array("col"=>"bulk",          "type"=>"S"),
                       array("col"=>"notes",         "type"=>"S"),
                       array("col"=>"year",          "type"=>"I"),
        )
            // fk_sl_species and sound* are not here because they're only used during rebuild-index and its associated manual steps
        );
    }

    static function fldSLSourcesCVArchive()
    {
        return( array( array("col"=>"sl_cv_sources_key", "type"=>"K"),
                       array("col"=>"fk_sl_sources",     "type"=>"K"),
                       array("col"=>"fk_sl_species",     "type"=>"K"),  // not canonical but useful for now
                       array("col"=>"fk_sl_pcv",         "type"=>"K"),
                       array("col"=>"osp",               "type"=>"S"),
                       array("col"=>"ocv",               "type"=>"S"),
                       array("col"=>"bOrganic",          "type"=>"I"),
                       array("col"=>"bulk",              "type"=>"S"),
                       array("col"=>"year",              "type"=>"S"),
                       array("col"=>"notes",             "type"=>"S"),
                       array("col"=>"op",                "type"=>"S"),
            )
        );
    }

    static function fldMbrSites()
    {
        return( [["col"=>"uid",                 "type"=>"K"],       // obsolete : use sl_varinst.fk_mbr_contacts instead
                 ["col"=>"nSite",               "type"=>"I"],
                 ["col"=>"sitename",            "type"=>"S"],
                 ["col"=>"address",             "type"=>"S"],
                 ["col"=>"city",                "type"=>"S"],
                 ["col"=>"province",            "type"=>"S"],
                 ["col"=>"postcode",            "type"=>"S"],
                 ["col"=>"country",             "type"=>"S"],
                 ["col"=>"latitude",            "type"=>"S"],
                 ["col"=>"longitude",           "type"=>"S"],
                 ["col"=>"metadata",            "type"=>"S"]
        ] );
    }

    static function fldSLVarinst()
    {
        return( [["col"=>"fk_mbr_contacts",     "type"=>"K"],   // required
                 ["col"=>"fk_mbr_sites",        "type"=>"K"],   // should exist but left-joined in case it doesn't
                 ["col"=>"fk_sl_pcv",           "type"=>"K"],   // preferred one way
                 ["col"=>"fk_sl_inventory",     "type"=>"K"],   // preferred another way
                 ["col"=>"fk_sl_accession",     "type"=>"K"],   // obsolete - convert to sl_inventory
                 ["col"=>"fk_sl_species",       "type"=>"K"],   // allowed but not preferred
                 ["col"=>"pname",               "type"=>"S"],   // "
                 ["col"=>"psp",                 "type"=>"S"],   // obsolete - use fk_sl_species instead
                 ["col"=>"osp",                 "type"=>"S"],   // to record user's original names
                 ["col"=>"oname",               "type"=>"S"],   // to record user's original names
                 ["col"=>"year",                "type"=>"I"],
                 ["col"=>"metadata",            "type"=>"S"],
        ] );
    }

    static function fldSLDescObs()
    {
        return( [["col"=>"fk_sl_varinst",       "type"=>"K"],
                 ["col"=>"k",                   "type"=>"S"],
                 ["col"=>"v",                   "type"=>"S"],
        ] );
    }
}


class SLDBBase extends Keyframe_NamedRelations
/*************
    Implement base kfrels and fetches (extend this class to add joined relations)
 */
{
    protected $tDef = array();      // table defs for building kfreldefs. Derived classes just add more.
    protected $dbname;

    function __construct( SEEDAppSessionAccount $oApp, $raConfig = array() )
    {
        $this->dbname = @$raConfig['dbname'] ?: $oApp->GetDBName('seeds1');
        $logdir = @$raConfig['logdir'] ?: $oApp->logdir;
        parent::__construct( $oApp->kfdb, $oApp->sess->GetUID(), $logdir );
    }

    protected function initKfrel( KeyframeDatabase $kfdb, $uid, $logdir )
    {
        $raKfrel = array();
        $this->tDef['C']  = array( "Table" => "{$this->dbname}.sl_collection", "Fields" => _sldb_defs::fldSLCollection() );
        $this->tDef['I']  = array( "Table" => "{$this->dbname}.sl_inventory",  "Fields" => _sldb_defs::fldSLInventory() );
        $this->tDef['A']  = array( "Table" => "{$this->dbname}.sl_accession",  "Fields" => _sldb_defs::fldSLAccession() );
        $this->tDef['D']  = array( "Table" => "{$this->dbname}.sl_adoption",   "Fields" => _sldb_defs::fldSLAdoption() );
        $this->tDef['G']  = array( "Table" => "{$this->dbname}.sl_germ",       "Fields" => _sldb_defs::fldSLGerm() );

        $this->tDef['P']  = array( "Table" => "{$this->dbname}.sl_pcv",        "Fields" => _sldb_defs::fldSLPCV() );
        $this->tDef['S']  = array( "Table" => "{$this->dbname}.sl_species",    "Fields" => _sldb_defs::fldSLSpecies() );
        $this->tDef['PY'] = array( "Table" => "{$this->dbname}.sl_pcv_syn",    "Fields" => _sldb_defs::fldSLPCVSyn() );
        $this->tDef['SY'] = array( "Table" => "{$this->dbname}.sl_species_syn","Fields" => _sldb_defs::fldSLSpeciesSyn() );

        $this->tDef['SITE'] = ['Table' => "{$this->dbname}.mbr_sites",  'Fields' => _sldb_defs::fldMbrSites()];
        $this->tDef['VI']   = ['Table' => "{$this->dbname}.sl_varinst", 'Fields' => _sldb_defs::fldSLVarinst()];
        $this->tDef['VO']   = ['Table' => "{$this->dbname}.sl_desc_obs",'Fields' => _sldb_defs::fldSLDescObs()];

        $sLogfile = $logdir ? "{$logdir}slcollection.log" : "";
        $raKfrel['C'] = $this->newKfrel( $kfdb, $uid, array( "C" => $this->tDef['C'] ), $sLogfile );
        $raKfrel['I'] = $this->newKfrel( $kfdb, $uid, array( "I" => $this->tDef['I'] ), $sLogfile );
        $raKfrel['A'] = $this->newKfrel( $kfdb, $uid, array( "A" => $this->tDef['A'] ), $sLogfile );
        $raKfrel['D'] = $this->newKfrel( $kfdb, $uid, array( "D" => $this->tDef['D'] ), $sLogfile );
        $raKfrel['G'] = $this->newKfrel( $kfdb, $uid, array( "G" => $this->tDef['G'] ), $sLogfile );

        $sLogfile = $logdir ? "{$logdir}slrosetta.log" : "";
        $raKfrel['P'] = $this->newKfrel( $kfdb, $uid, array( "P" => $this->tDef['P'] ),  $sLogfile );
        $raKfrel['S'] = $this->newKfrel( $kfdb, $uid, array( "S" => $this->tDef['S'] ),  $sLogfile );
        $raKfrel['PY']= $this->newKfrel( $kfdb, $uid, array( "PY"=> $this->tDef['PY'] ), $sLogfile );
        $raKfrel['SY']= $this->newKfrel( $kfdb, $uid, array( "SY"=> $this->tDef['SY'] ), $sLogfile );

        $sLogfile = $logdir ? "{$logdir}slprofile.log" : "";
        $raKfrel['SITE'] = $this->newKfrel( $kfdb, $uid, ['SITE' => $this->tDef['SITE']],  $sLogfile );
        $raKfrel['VI']   = $this->newKfrel( $kfdb, $uid, ['VI'   => $this->tDef['VI']],    $sLogfile );
        $raKfrel['VO']   = $this->newKfrel( $kfdb, $uid, ['VO'   => $this->tDef['VO']],    $sLogfile );

        return( $raKfrel );
    }

    protected function newKfrel( $kfdb, $uid, $raTableDefs, $sLogfile, array $raDefParms = [] )
    /******************************************************************************************
        $raTableDefs is an array('Alias'=>array('Table'=>...), ... )
        $raDefParms are kfrelDef parms merged with $raTableDefs e.g. ['bFetchFullBaseAliases'=>true]
     */
    {
        $parms = $sLogfile ? array('logfile'=>$sLogfile) : array();
        return( new KeyFrame_Relation( $kfdb, array_merge(['Tables'=>$raTableDefs], $raDefParms), $uid, $parms ) );
    }

    protected function newKfrel2( $kfdb, $uid, $raTDefs, $sLogfile, array $raDefParms = [] )
    /***************************************************************************************
        $raTDefs is an array of keys to $this->tDef that will compose a natural join (or a single table)
     */
    {
        $raTableDefs = array();
        foreach( $raTDefs as $k ) {
            $raTableDefs[$k] = $this->tDef[$k];
        }
        return( $this->newKfrel( $kfdb, $uid, $raTableDefs, $sLogfile, $raDefParms ) );
    }
}

class SLDBRosetta extends SLDBBase
/****************
 * Implement joins of variety name tables
 */
{
    function __construct( SEEDAppSessionAccount $oApp, $raConfig = array() )
    {
        parent::__construct( $oApp, $raConfig );
    }

    protected function initKfrel( KeyframeDatabase $kfdb, $uid, $logdir )
    {
        // do this first because it sets $this->tDef
        $raKfrel = parent::initKfrel( $kfdb, $uid, $logdir );

        $sLogfile = $logdir ? "{$logdir}slrosetta.log" : "";
        $raKfrel['PxS']    = $this->newKfrel2( $kfdb, $uid, array('P','S'), $sLogfile, ['bFetchFullBaseAliases'=>true] );  // code wants to refer to P__key because conditioning on _key is ambiguous
        $raKfrel['PYxPxS'] = $this->newKfrel2( $kfdb, $uid, array('PY','P','S'), $sLogfile );
        $raKfrel['SYxS']   = $this->newKfrel2( $kfdb, $uid, array('SY','S'), $sLogfile );

        return( $raKfrel );
    }
}

class SLDBCollection extends SLDBRosetta
/*******************
 * Implement joins of Seed Library tables and Rosetta
 */
{
    function __construct( SEEDAppSessionAccount $oApp, $raConfig = array() )
    {
        parent::__construct( $oApp, $raConfig );
    }

    protected function initKfrel( KeyframeDatabase $kfdb, $uid, $logdir )
    {
        // do this first because it sets $this->tDef
        $raKfrel = parent::initKfrel( $kfdb, $uid, $logdir );

        $sLogfile = $logdir ? "{$logdir}slcollection.log" : "";

        $raKfrel['IxA']       = $this->newKfrel2( $kfdb, $uid, array('I','A'), $sLogfile );
        $raKfrel['AxPxS']     = $this->newKfrel2( $kfdb, $uid, array('A','P','S'), $sLogfile );
        $raKfrel['IxAxP']     = $this->newKfrel2( $kfdb, $uid, array('I','A','P'), $sLogfile );
        $raKfrel['IxAxPxS']   = $this->newKfrel2( $kfdb, $uid, array('I','A','P','S'), $sLogfile );
        $raKfrel['IxGxAxPxS'] = $this->newKfrel2( $kfdb, $uid, array('I','G','A','P','S'), $sLogfile );
        $raKfrel['GxIxAxPxS'] = $this->newKfrel2( $kfdb, $uid, array('G','I','A','P','S'), $sLogfile );

        /* Adoptions usually have pcv but not if they're fresh
         */
        $raKfrel['DxP'] = $this->newKfrel2( $kfdb, $uid, array('D','P'), $sLogfile );


        $raKfrel['A_P'] = $this->newKfrel( $kfdb, $uid,
                array( 'A' => $this->tDef['A'],
                       'P' => array( "Table" => "{$this->dbname}.sl_pcv",
                                     "Type"  => "LeftJoin",
                                     "JoinOn" => "A.fk_sl_pcv=P._key",
                                     "Fields" => _sldb_defs::fldSLPCV() ) ),
                $sLogfile );

        $raKfrel['IxA_P'] = $this->newKfrel( $kfdb, $uid,
                array( 'I' => array( "Table" => "{$this->dbname}.sl_inventory",
                                     "Fields" => _sldb_defs::fldSLInventory() ),
                       'A' => array( "Table" => "{$this->dbname}.sl_accession",
                                     "Fields" => _sldb_defs::fldSLAccession() ),
                       'P' => array( "Table" => "{$this->dbname}.sl_pcv",
                                     "Type"  => "LeftJoin",
                                     "JoinOn" => "A.fk_sl_pcv=P._key",
                                     "Fields" => _sldb_defs::fldSLPCV() ) ),
                $sLogfile );


        return( $raKfrel );
    }
}

class SLDBSources extends SLDBRosetta
/****************
 * Implement joins of cv-source tables and variety name tables
 */
{
    function __construct( SEEDAppSessionAccount $oApp, $raConfig = array() )
    {
        parent::__construct( $oApp, $raConfig );
    }

    protected function initKfrel( KeyframeDatabase $kfdb, $uid, $logdir )
    {
        // do this first because it sets $this->tDef
        $raKfrel = parent::initKfrel( $kfdb, $uid, $logdir );

        // add these table definitions to the tDef list
        $this->tDef['SRC']    = array( "Table" => "{$this->dbname}.sl_sources",            "Fields" => _sldb_defs::fldSLSources() );
        $this->tDef['SRCCV']  = array( "Table" => "{$this->dbname}.sl_cv_sources",         "Fields" => _sldb_defs::fldSLSourcesCV() );
        $this->tDef['SRCCVA'] = array( "Table" => "{$this->dbname}.sl_cv_sources_archive", "Fields" => _sldb_defs::fldSLSourcesCVArchive() );


        $sLogfile = $logdir ? "{$logdir}slsources.log" : "";

        // Letters are out of order in the arrays to solve forward-dependency in the sql (is this still necessary?)
        $raKfrel['SRC']           = $this->newKfrel2( $kfdb, $uid, array('SRC'), $sLogfile );
        $raKfrel['SRCCV']         = $this->newKfrel2( $kfdb, $uid, array('SRCCV'), $sLogfile );
        $raKfrel['SRCCVA']        = $this->newKfrel2( $kfdb, $uid, array('SRCCVA'), $sLogfile );
        $raKfrel['SRCCVxSRC']     = $this->newKfrel2( $kfdb, $uid, array('SRCCV','SRC'), $sLogfile );
        $raKfrel['SRCCVxPxS']     = $this->newKfrel2( $kfdb, $uid, array('SRCCV','P','S'), $sLogfile );
        $raKfrel['SRCCVxSRCxPxS'] = $this->newKfrel2( $kfdb, $uid, array('SRCCV','SRC','P','S'), $sLogfile );

        $raKfrel['SRCCVAxSRC']    = $this->newKfrel2( $kfdb, $uid, array('SRCCVA','SRC'), $sLogfile );

        // SRC is required to exist during upload procedure, but this is useful for edge cases during uploading and integrity testing
        $raKfrel['SRCCV_SRC'] = $this->newKfrel( $kfdb, $uid,
                [ 'SRCCV' => [ "Table" => "{$this->dbname}.sl_cv_sources",
                               "Fields" => _sldb_defs::fldSLSourcesCV() ],
                  'SRC' =>   [ "Table" => "{$this->dbname}.sl_sources",
                               "Fields" => _sldb_defs::fldSLSources(),
                               "Type"  => "LeftJoin",
                               "LeftJoinOn" => "SRCCV.fk_sl_sources=SRC._key" ] ],
                $sLogfile );

        // every SrcCV must have a Src, but it might not have a PCV
        $raKfrel['SRCCVxSRC_P'] = $this->newKfrel( $kfdb, $uid,
                array( 'SRCCV' => array( "Table" => "{$this->dbname}.sl_cv_sources",
                                         "Fields" => _sldb_defs::fldSLSourcesCV() ),
                       'SRC' =>   array( "Table" => "{$this->dbname}.sl_sources",
                                         "Fields" => _sldb_defs::fldSLSources() ),
                       'P' =>     array( "Table" => "{$this->dbname}.sl_pcv",
                                         "Type"  => "LeftJoin",
                                         "LeftJoinOn" => "SRCCV.fk_sl_pcv=P._key",
                                         "Fields" => _sldb_defs::fldSLPCV() ) ),
            $sLogfile );

//kluge: Since fk_sl_pcv is often 0, SRCCVxPxS cannot be used to get a list of species from SRCCV.
//       SRCCV.fk_sl_species is non-canonical so replace this with SRCCVxPxS when fk_sl_pcv is done right.
$raKfrel['SRCCVxS'] = $this->newKfrel( $kfdb, $uid,
    array( 'SRCCV' => array( "Table" => "{$this->dbname}.sl_cv_sources",
                             "Fields" => _sldb_defs::fldSLSourcesCV() ),
           'S' =>     array( "Table" => "{$this->dbname}.sl_species",
                             "Type"  => "Join",
                             "JoinOn" => "SRCCV.fk_sl_species=S._key",
                             "Fields" => _sldb_defs::fldSLSpecies() ) ),
    $sLogfile );

//kluge: This should be obtained by SRCCVAxSRCxPxS because SRCCVA would not normally have fk_sl_species, but for now this is how we do it
//       Also make sure you use SRC._status=-1 (ignore _status) because some archived srccv records will have "deleted" companies
$raKfrel['SRCCVAxS'] = $this->newKfrel( $kfdb, $uid,
    array( 'SRCCVA' => array( "Table" => "{$this->dbname}.sl_cv_sources_archive",
                              "Fields" => _sldb_defs::fldSLSourcesCVArchive() ),
           'S' =>      array( "Table" => "{$this->dbname}.sl_species",
                             "Fields" => _sldb_defs::fldSLSpecies() ) ),
    $sLogfile );
$raKfrel['SRCCVAxSRC_S'] = $this->newKfrel( $kfdb, $uid,
    array( 'SRCCVA' => array( "Table" => "{$this->dbname}.sl_cv_sources_archive",
                              "Fields" => _sldb_defs::fldSLSourcesCVArchive() ),
           'SRC' =>    array( "Table" => "{$this->dbname}.sl_sources",
                              "Type"  => "Join",
                              "Fields" => _sldb_defs::fldSLSources() ),
           'S' =>      array( "Table" => "{$this->dbname}.sl_species",
                             "Type"  => "LeftJoin",
                             "LeftJoinOn" => "SRCCVA.fk_sl_species=S._key",
                             "Fields" => _sldb_defs::fldSLSpecies() ) ),
    $sLogfile );

        return( $raKfrel );
    }
}


class SLDBProfile extends SLDBCollection
/****************
 * Implement joins of variety-instance-observations
 */
{
    function __construct( SEEDAppSessionAccount $oApp, $raConfig = array() )
    {
        parent::__construct( $oApp, $raConfig );
    }

    protected function initKfrel( KeyframeDatabase $kfdb, $uid, $logdir )
    {
        // do this first because it sets $this->tDef
        $raKfrel = parent::initKfrel( $kfdb, $uid, $logdir );

        $sLogfile = $logdir ? "{$logdir}slprofile.log" : "";

        $raKfrel['VOxVI'] = $this->newKfrel2( $kfdb, $uid, ['VO','VI'], $sLogfile );

        $raKfrel['VI_SITE'] = $this->newKfrel( $kfdb, $uid,
                    ['VI'   => $this->tDef['VI'],
                     'SITE' => ['Table' => "{$this->dbname}.mbr_sites",
                                'Type'  => "LeftJoin",
                                'JoinOn' => "VI.fk_mbr_sites=SITE._key",
                                'Fields' => _sldb_defs::fldMbrSites()] ],
                    $sLogfile );

        $raKfrel['VOxVI_SITE'] = $this->newKfrel( $kfdb, $uid,
                    ['VO' => $this->tDef['VO'],
                     'VI' => $this->tDef['VI'],
                     'SITE' => ['Table' => "{$this->dbname}.mbr_sites",
                                'Type'  => "LeftJoin",
                                'JoinOn' => "VI.fk_mbr_sites=SITE._key",
                                'Fields' => _sldb_defs::fldMbrSites()] ],
                    $sLogfile );

        return( $raKfrel );
    }
}


class SLDB_Create
{
/****************************************
    CV_SOURCES  (main, archive, tmp)
 */

const SEEDS_DB_TABLE_SL_CV_SOURCES =
"
CREATE TABLE sl_cv_sources (

        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

-- cv source data
    fk_sl_sources   INTEGER NOT NULL DEFAULT 0,
    fk_sl_pcv       INTEGER NOT NULL DEFAULT 0,
    fk_sl_species   INTEGER NOT NULL DEFAULT 0,     -- used in rebuild-index to find fk_sl_pcv
    osp             VARCHAR(200) NOT NULL DEFAULT '',
    ocv             VARCHAR(200) NOT NULL DEFAULT '',
    bOrganic        INTEGER NOT NULL DEFAULT 0,
    bulk            VARCHAR(200) NOT NULL DEFAULT '',
    year            INTEGER NOT NULL DEFAULT 0,
    notes           VARCHAR(1000) NOT NULL DEFAULT '',  -- cycles back to the people who edit the data

-- workflow
    tsVerified  VARCHAR(200) NULL,   -- was DATETIME but mysql won't allow '' as a default    YEAR(tsVerified) goes in csci_seeds_archive.year
    bNeedVerify     INTEGER DEFAULT 1,
    bNeedProof      INTEGER DEFAULT 1,

-- sound matching
    sound_soundex   VARCHAR(100) NOT NULL DEFAULT '',
    sound_metaphone VARCHAR(100) NOT NULL DEFAULT '',


    index (fk_sl_sources),
    index (fk_sl_pcv),
    index (fk_sl_species), -- used in updates to find fk_sl_pcv
    index (osp),           -- these two indexes are very important for the update process that tries to match names in sl_pcv
    index (ocv),
    index (sound_soundex),
    index (sound_metaphone)
);
";

const SEEDS_DB_TABLE_SL_CV_SOURCES_ARCHIVE =
"
CREATE TABLE sl_cv_sources_archive (

        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    sl_cv_sources_key INTEGER NOT NULL,              -- (sl_cv_sources_key, year) groups records for the same entry over multiple years

    fk_sl_sources   INTEGER NOT NULL DEFAULT 0,
    fk_sl_pcv       INTEGER NOT NULL DEFAULT 0,
    fk_sl_species   INTEGER NOT NULL DEFAULT 0,      -- don't have to keep this when all names are in sl_pcv but useful until then
    osp             VARCHAR(200) NOT NULL DEFAULT '',
    ocv             VARCHAR(200) NOT NULL DEFAULT '',
    bOrganic        INTEGER NOT NULL DEFAULT 0,
    bulk            VARCHAR(200) NOT NULL DEFAULT '',
    year            INTEGER NOT NULL DEFAULT 0,
    notes           TEXT,
    op              CHAR NOT NULL,                  -- record the op that triggered this archive (update / year / delete)

    index (fk_sl_sources),
    index (fk_sl_pcv),
    index (fk_sl_species),
    index (osp),
    index (ocv),
    index (year)
);
";

const SEEDS_DB_TABLE_SL_TMP_CV_SOURCES = "
CREATE TABLE sl_tmp_cv_sources (
    -- These columns are required in the spreadsheet
    -- osp and ocv are named this way to enable compatible code with SLSourceRosetta
    k             integer not null default 0,            -- sl_cv_sources._key, preserved here for re-integration
    company       varchar(200) not null default '',      -- must match sl_sources.name_en
    osp           varchar(200) not null default '',      -- copy of sl_cv_sources.osp
    ocv           varchar(200) not null default '',      -- copy of sl_cv_sources.ocv
    organic       tinyint not null default 0,            -- copy of sl_cv_sources.bOrganic
    bulk          varchar(200) not null default '',
    year          integer not null default 0,
    notes         text,

    -- These columns are generated when the spreadsheet is uploaded
    kUpload       integer not null,                      -- each upload has a unique number for grouping rows of that upload
    _key          INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,  -- necessary when joining table to itself looking for duplicates
    _created      datetime,                              -- time when this row was uploaded - for garbage collection of orphaned uploads
    _status       integer not null default 0,            -- mainly so we can apply queries written for sl_cv_sources

    -- Computed after loading
    fk_sl_sources integer default 0,                     -- validates integrity of (company)
    fk_sl_species integer default 0,                     -- attempts to match (species) with a species identifier, but allows 0 so Rosetta can work on it
    fk_sl_pcv     integer default 0,                     -- attempts to match (fk_sl_species,cultivar), but allows 0 so Rosetta can work on it
    op            CHAR not null default '',              -- '' = not computed yet, 'N' = new, 'U' = update, 'D' = delete1, 'X' = delete2, 'Y' = year updated, '-' = no change
    op_data       integer default 0,                     -- op=='.' needs an extra piece of data

    -- These are obsolete, probably
    -- sp_old        varchar(200) not null default '',
    -- var_old       varchar(200) not null default '',

    -- Indexes
    index (k),
    index (osp(20)),
    index (ocv(20)),
    -- index (sp_old(20)),
    -- index (var_old(20)),
    index (fk_sl_sources),
    index (fk_sl_species),
    index (fk_sl_pcv),
    index (kUpload)
) CHARSET latin1;
";



const SEEDS_DB_TABLE_MBR_SITES = "
CREATE TABLE mbr_sites (
    # Register a site where something is grown or observed - this is shared by Seed Library and PollinatorWatch
    # Each user has 0+ sites.
    #
    # The site defines location, and permanent characteristics about the site, e.g. soil type.
    # No time-dependent information is stored here.  i.e. the information is valid for all years

        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

uid             INTEGER NOT NULL,       # obsolete      SEEDSession_Users._key
    sitename        VARCHAR(200),
    address         VARCHAR(200),           # not necessarily the same as the mbr mailing address
    city            VARCHAR(200),           # not necessarily the same as the mbr mailing address
    province        VARCHAR(10),
    postcode        VARCHAR(200),
    country         VARCHAR(200),
    latitude        VARCHAR(200),
    longitude       VARCHAR(200),
    metadata        TEXT,                   # stored as an urlencoded string

    INDEX (uid),
    INDEX (province(2)),
    INDEX (postcode(3))
);
";

const SEEDS_DB_TABLE_SL_VARINST = "
CREATE TABLE sl_varinst (
    # Register a Variety Instance for a mbr_site.  This is shared by various components of the Seed Library e.g. Multiplication, Descriptors
    # Each site has has 0+ variety instances.
    #
    # A Variety Instance is a tuple of (grower, variety/accession, year) with some metadata.
    # Metadata can be e.g. fertilized, mulched, etc, but note that sl_desc_obs can contain any such metadata because it's related to the same tuple.

        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    fk_mbr_contacts INTEGER NOT NULL DEFAULT 0,
    fk_mbr_sites    INTEGER NOT NULL DEFAULT 0,
    fk_sl_inventory INTEGER NOT NULL DEFAULT 0,  # preferred one way
    fk_sl_pcv       INTEGER NOT NULL DEFAULT 0,  # or preferred another way

    fk_sl_accession INTEGER NOT NULL DEFAULT 0,  # obsolete - replace with sl_inventory

    fk_sl_species   INTEGER NOT NULL DEFAULT 0,  # allowed with pname
    pname           VARCHAR(200) NOT NULL,
    psp             VARCHAR(200) NOT NULL,       # obsolete - use sl_species
    osp             VARCHAR(200) NOT NULL,       # the name the user gave us (keep this for reference, but override above)
    oname           VARCHAR(200) NOT NULL,
    year            INTEGER NOT NULL,
    metadata        TEXT,                   # stored as an urlencoded string

    INDEX (fk_mbr_sites),
    INDEX (fk_sl_pcv),
    INDEX (osp(10),oname(10)),
    INDEX (fk_sl_accession)
);
";

const SEEDS_DB_TABLE_SL_DESC_OBS = "
CREATE TABLE sl_desc_obs (
    # Store observations for each variety instance
    # Each variety instance has 0+ observations
    #
    # The variety instance is a container for observations of a variety during one season.
    # It defines the variety being observed, the year, and metadata relating to the (variety, year).
    # Metadata can be e.g. fertilized, mulched, etc, but note that sl_desc_obs can contain any such metadata because it's related to the same tuple.

    # Note: This table is a normalization for the (site,variety,year) tuple that could be stored repetitively in sl_desc_obs instead.
    #       It is useful however, for centralizing metadata, and alternate linkages such as fk_sl_acc

        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    fk_sl_varinst   INTEGER NOT NULL,
    k               VARCHAR(200) NOT NULL,
    v               VARCHAR(200) NOT NULL,

    INDEX (fk_sl_varinst),
    INDEX (k(20))
);
";


const SEEDS_DB_TABLE_SL_DESC_CFG_TAGS = "
CREATE TABLE sl_desc_cfg_tags (
        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    tag        VARCHAR(200) NOT NULL,
    label_en   VARCHAR(200) NOT NULL DEFAULT '',
    label_fr   VARCHAR(200) NOT NULL DEFAULT '',
    q_en       TEXT,
    q_fr       TEXT,

    INDEX (tag(20))
) DEFAULT CHARSET=latin1;
";

const SEEDS_DB_TABLE_SL_DESC_CFG_M = "
CREATE TABLE sl_desc_cfg_m (
        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    tag        VARCHAR(200) NOT NULL,
    v          VARCHAR(200) NOT NULL DEFAULT '',
    l_en       VARCHAR(200) NOT NULL DEFAULT '',
    l_fr       VARCHAR(200) NOT NULL DEFAULT '',

    INDEX (tag(20))
) DEFAULT CHARSET=latin1;
";

}
