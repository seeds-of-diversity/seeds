<?php

/* Crop Profiles database layer
 *
 * Copyright (c) 2009-2018 Seeds of Diversity Canada
 */

class SLProfilesDB extends Keyframe_NamedRelations
{
    function __construct( KeyframeDatabase $kfdb, $uid )
    {
        parent::__construct( $kfdb, $uid );
    }

    function GetVarInst( $kVI )
    /**************************
        Get info about one variety instance
     */
    {
        $ra = array();

        if( ($kfr = $this->GetKFR( 'VI', $kVI )) ) {
            $ra = $kfr->ValuesRA();
            list($ra['csp'],$ra['ccv']) = $this->ComputeVarInstName( $ra );
        }
        return( $ra );
    }

    function ComputeVarInstName( $raIn, $prefix = "" )
    /*************************************************
        Given a varinst record, fill in the blanks.  Prefix is "" for base varinst, typically VI_ for non-base

        Output:
            csp = computed sp based on multiplexed records
            ccv = computed cv based on multiplexed records
     */
    {
        $sp = $cv = "";

        // If the accession is recorded but not the kPCV, get that for the next test
        if( ($kAcc = $raIn[$prefix.'fk_sl_accession']) && !$raIn[$prefix.'fk_sl_pcv'] ) {
            $raIn[$prefix.'fk_sl_pcv'] = $this->GetKFDB()->Query1( "SELECT fk_sl_pcv FROM seeds.sl_accession WHERE _key='$kAcc''" );
        }
        // If the kPCV is known, get the sp and cv from that
        if( ($kPCV = $raIn[$prefix.'fk_sl_pcv']) &&
            ($ra = $this->GetKFDB()->QueryRA( "SELECT psp,name FROM seeds.sl_pcv WHERE _key='$kPCV'" )) )
        {
            $sp = @$ra['psp'];
            $cv = @$ra['name'];
        }

        // If that didn't work, get the psp/pname from the varinst
        if( !$sp || !$cv ) {
            $sp = @$raIn[$prefix.'psp'];
            $cv = @$raIn[$prefix.'pname'];
        }

        // If that didn't work, get the osp/oname from the varinst
        if( !@$raOut[$prefix.'csp'] || !@$raOut[$prefix.'ccv'] ) {
            $sp = @$raIn[$prefix.'osp'];
            $cv = @$raIn[$prefix.'oname'];
        }

        return( array($sp,$cv) );
    }


    protected function initKfrel( KeyFrameDatabase $kfdb, $uid )
    {
        /* Profile records tables
         */
        $kdefSite = array( "Tables" =>
            array( "Site" => array( "Table" => "seeds.mbr_sites",       "Fields" => "Auto" ) ) );
        $kdefVI   = array( "Tables" =>
            array( "VI"   => array( "Table" => "seeds.sl_varinst",      "Fields" => "Auto" ) ) );
        $kdefObs  = array( "Tables" =>
            array( "Obs"  => array( "Table" => "seeds.sl_desc_obs",     "Fields" => "Auto" ) ) );

        $kdefVISite = array( "Tables" =>
            array( "VI"   => array( "Table" => "seeds.sl_varinst",      "Fields" => "Auto" ),
                   "Site" => array( "Table" => "seeds.mbr_sites",       "Fields" => "Auto" ) ) );

        $kdefObsVISite = array( "Tables" =>
            array( "Obs"  => array( "Table" => "seeds.sl_desc_obs",     "Fields" => "Auto" ),
                   "VI"   => array( "Table" => "seeds.sl_varinst",      "Fields" => "Auto" ),
                   "Site" => array( "Table" => "seeds.mbr_sites",       "Fields" => "Auto" ) ) );

        /* Descriptor config tables
         */
        $kdefCfgTags = array( "Tables" =>
            array( "CfgTags" => array( "Table" => "seeds.sl_desc_cfg_tags", "Fields" => "Auto" ) ) );
        $kdefCfgM = array( "Tables" =>
            array( "CfgM"    => array( "Table" => "seeds.sl_desc_cfg_m",    "Fields" => "Auto" ) ) );


        $raParms = array( 'logfile' => SITE_LOG_ROOT."slprofiles.log" );
        $raKfrel = array();
        $raKfrel['Site']             = new KeyFrame_Relation( $kfdb, $kdefSite,      $uid, $raParms );
        $raKfrel['VI']               = new KeyFrame_Relation( $kfdb, $kdefVI,        $uid, $raParms );
        $raKfrel['Obs']              = new KeyFrame_Relation( $kfdb, $kdefObs,       $uid, $raParms );
        $raKfrel['VISite']           = new KeyFrame_Relation( $kfdb, $kdefVISite,    $uid, $raParms );
        $raKfrel['ObsVISite']        = new KeyFrame_Relation( $kfdb, $kdefObsVISite, $uid, $raParms );

        $raKfrel['CfgTags']          = new KeyFrame_Relation( $kfdb, $kdefCfgTags,   $uid, $raParms );
        $raKfrel['CfgM']             = new KeyFrame_Relation( $kfdb, $kdefCfgM,      $uid, $raParms );

        return( $raKfrel );
    }
}


define("MBR_DB_TABLE_SITE",
"
CREATE TABLE IF NOT EXISTS mbr_sites (
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

    uid             INTEGER NOT NULL,       # SEEDSession_Users._key
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
"
);


// Promote this to SL level and share it (e.g. for seed multiplication)
define("SL_DB_TABLE_VAR_INST",
"
CREATE TABLE IF NOT EXISTS sl_varinst (
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

    fk_mbr_sites    INTEGER NOT NULL,
    fk_sl_accession INTEGER NULL,                # if the variety is an accession, indicate here.  Name should be denormalized out for convenience.
    fk_sl_pcv       INTEGER NOT NULL DEFAULT 0,  # or record the variety name this way
    psp             VARCHAR(200) NOT NULL,       # or record the species/variety here
    pcv             VARCHAR(200) NOT NULL,
    osp             VARCHAR(200) NOT NULL,       # last default is the name the user gave us (keep this for reference, but override above)
    oname           VARCHAR(200) NOT NULL,
    year            INTEGER NOT NULL,
    metadata        TEXT,                   # stored as an urlencoded string

    INDEX (fk_mbr_sites),
    INDEX (fk_sl_pcv),
    INDEX (osp(10),oname(10)),
    INDEX (fk_sl_accession)
);
"
);

define("SL_DB_TABLE_DESC_OBS",
"
CREATE TABLE IF NOT EXISTS sl_desc_obs (
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
"
);

define("SL_DB_TABLE_DESC_CFG_TAGS",
"
CREATE TABLE IF NOT EXISTS sl_desc_cfg_tags (
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
"
);

define("SL_DB_TABLE_DESC_CFG_M",
"
CREATE TABLE IF NOT EXISTS sl_desc_cfg_m (
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
"
);

?>
