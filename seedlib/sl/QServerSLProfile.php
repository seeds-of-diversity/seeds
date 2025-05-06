<?php

/* QServerSLProfile
 *
 * Copyright 2025 Seeds of Diversity Canada
 *
 * Serve queries about Crop Profiles
 */

include_once(SEEDLIB."q/Q.php");
include_once(SEEDLIB."sl/sldb.php");
include_once( SEEDLIB."sl/profiles/sl_profiles_db.php" );
include_once( SEEDLIB."sl/profiles/sl_profiles_defs.php" );
include_once( SEEDLIB."sl/profiles/sl_profiles_report.php" );


class QServerSLProfile extends SEEDQ
{
    private $oProfilesDB, $oProfilesDefs, $oProfilesReport;
    private $oSLDB, $oSLDBSrc, $oSLDBRosetta;

    function __construct( SEEDAppSessionAccount $oApp, $raConfig = [] )
    {
        parent::__construct( $oApp, $raConfig );

        $this->oSLDB = new SLDBProfile($this->oApp);
        $this->oProfilesDB = new SLProfilesDB($this->oApp);
        $this->oProfilesDefs = new SLProfilesDefs($this->oProfilesDB);
        $this->oProfilesReport = new SLProfilesReport($this->oProfilesDB, $this->oProfilesDefs, $this->oApp);

        $this->oSLDBSrc = new SLDBSources( $oApp );
        $this->oSLDBRosetta = new SLDBRosetta( $oApp );     // this inherits from SLDBSources so it's redundant
    }

    function Cmd( $cmd, $parms )
    {
        $rQ = $this->GetEmptyRQ();

        if( $cmd == 'slprofile-help' ) {
            $rQ['bHandled'] = true;
            //$rQ['bOk'] = true;
            //$rQ['sOut'] = $this->sHelp;
        }

        if( $cmd == 'slprofile-minireport' ) {
            $rQ['bHandled'] = true;

            $rQ['sLog'] = SEEDCore_ImplodeKeyValue( $parms, "=", "," );

            if( ($ra = $this->getReportMini($parms)) ) {
                $rQ['bOk'] = true;
//                $rQ['raOut'] = $ra;
                $rQ['sOut'] = $ra;
            }
        }

        return( $rQ );
    }

    private function getReportMini( array $raParms )
    {
        $raOut = [];
        $s = "";

        if( ($kPcv = intval($raParms['kPcv'])) ) {

//            if( ($kfr = $this->oProfilesDB->GetKFRCond( "VISite", "osp='".addslashes($sp)."' AND oname='".addslashes($cv)."'" )) ) {
            if( ($kfr = $this->oProfilesDB->GetKFRCond( "VISite", "fk_sl_pcv=$kPcv" )) ) {
// or Lot.fk_sl_pcv = $kPcv
// wait this is not doing the right thing - it's only showing the first matching record
                $s = $this->oProfilesReport->DrawVIRecord( $kfr, false );
            }
        }

        return($s);
    }
}

