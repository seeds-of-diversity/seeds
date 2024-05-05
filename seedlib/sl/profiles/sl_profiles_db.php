<?php

/* Crop Profiles database layer
 *
 * Copyright (c) 2009-2024 Seeds of Diversity Canada
 */
include_once(SEEDLIB."sl/sldb.php");

class SLProfilesDB extends Keyframe_NamedRelations
{
    private $oApp;
    private $oSLDB;

    function __construct( SEEDAppSession $oApp )
    {
        $this->oApp = $oApp;
        parent::__construct( $oApp->kfdb, $oApp->sess->GetUID(), $oApp->logdir );
        $this->oSLDB = new SLDBProfile($oApp);
    }

/*
    function GetVarInst( $kVI )
    [**************************
        Get info about one variety instance
     *]
    {
        $ra = array();

        if( ($kfr = $this->GetKFR( 'VI', $kVI )) ) {
            list($ra['csp'],$spname,$ra['ccv']) = $this->ComputeVarInstName($kfr);
        }
        return( $ra );
    }
*/

    function ComputeVarInstName( KeyframeRecord $kfrVI, $prefix = "" )
    /*****************************************************************
        Given a varinst record, fill in the blanks.  Prefix is "" for base varinst, typically VI_ for non-base

        Output:
            csp = computed sp based on multiplexed records
            ccv = computed cv based on multiplexed records
     */
    {
        $sp = $cv = $psp = "";

        // Use sl_inventory.sl.accession.sl_pcv if defined
        if( ($kI = $kfrVI->ValueInt($prefix.'fk_sl_inventory')) && ($kfr = $this->oSLDB->GetKFRCond('IxAxPxS', "I.inv_number=$kI AND I.fk_sl_collection=1")) ) {
            $sp  = $kfr->Value('S_name_en');
            $psp = $kfr->Value('S_psp');
            $cv  = $kfr->Value('P_name');
        } else
        // Use sl_pcv if defined
        if( ($kP = $kfrVI->ValueInt($prefix.'fk_sl_pcv')) && ($kfr = $this->oSLDB->GetKFR('PxS', $kP)) ) {
            $sp  = $kfr->Value('S_name_en');
            $psp = $kfr->Value('S_psp');
            $cv  = $kfr->Value('name');
        } else

    // obsolete: sl_accession.sl_pcv
    if( ($kA = $kfrVI->ValueInt($prefix.'fk_sl_accession')) && ($kfr = $this->oSLDB->GetKFR('AxPxS', $kA)) ) {
        $sp  = $kfr->Value('S_name_en');
        $psp = $kfr->Value('S_psp');
        $cv  = $kfr->Value('P_name');
    } else

        // Use sl_species/pname|oname if defined
        if( ($kS = $kfrVI->ValueInt($prefix.'fk_sl_species')) && ($kfr = $this->oSLDB->GetKFR('S', $kS)) ) {
            $sp  = $kfr->Value('name_en');
            $psp = $kfr->Value('psp');
            $cv = $kfrVI->Value($prefix.'pname') ?: $kfrVI->Value($prefix.'oname');
        } else

    // obsolete: psp/pname
    if( ($sp = $kfrVI->Value($prefix.'psp')) ) {
        $psp = $sp;
        $cv = $kfrVI->Value($prefix.'pname') ?: $kfrVI->Value($prefix.'oname');
    } else

        // Use osp/oname as a last resort
        if( ($sp = $kfrVI->Value($prefix.'osp')) ) {
            $psp = $sp;
            $cv = $kfrVI->Value($prefix.'oname');
        }

        return( [$psp,$sp,$cv] );
    }


    protected function initKfrel( KeyFrameDatabase $kfdb, $uid, $logdir )
    {
        /* Profile records tables
         */
        $dbname = $this->oApp->DBName('seeds1');


/***** This uses the obsolete fk_mbr_sites heirarchy - replace with the relations in SLDBProfiles
*/

        $kdefSite = array( "Tables" =>
            array( "Site" => array( "Table" => "{$dbname}.mbr_sites",       "Fields" => "Auto" ) ) );
        $kdefVI   = array( "Tables" =>
            array( "VI"   => array( "Table" => "{$dbname}.sl_varinst",      "Fields" => "Auto" ) ) );
        $kdefObs  = array( "Tables" =>
            array( "Obs"  => array( "Table" => "{$dbname}.sl_desc_obs",     "Fields" => "Auto" ) ) );

        $kdefVISite = array( "Tables" =>
            array( "VI"   => array( "Table" => "{$dbname}.sl_varinst",      "Fields" => "Auto" ),
                   "Site" => array( "Table" => "{$dbname}.mbr_sites",       "Fields" => "Auto" ) ) );

        $kdefObsVISite = array( "Tables" =>
            array( "Obs"  => array( "Table" => "{$dbname}.sl_desc_obs",     "Fields" => "Auto" ),
                   "VI"   => array( "Table" => "{$dbname}.sl_varinst",      "Fields" => "Auto" ),
                   "Site" => array( "Table" => "{$dbname}.mbr_sites",       "Fields" => "Auto" ) ) );

        /* Descriptor config tables
         */
        $kdefCfgTags = array( "Tables" =>
            array( "CfgTags" => array( "Table" => "{$dbname}.sl_desc_cfg_tags", "Fields" => "Auto" ) ) );
        $kdefCfgM = array( "Tables" =>
            array( "CfgM"    => array( "Table" => "{$dbname}.sl_desc_cfg_m",    "Fields" => "Auto" ) ) );


        $raParms = array( 'logfile' => $logdir."slprofiles.log" );
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
