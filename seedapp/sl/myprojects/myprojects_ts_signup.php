<?php

/* MyProjects app
 *
 * Copyright (c) 2024-2026 Seeds of Diversity Canada
 */

class ProjectsTabSignup
{
    private $oP, $oPUI;
    private $oSLDB;

    function __construct( ProjectsCommon $oP, ProjectsCommonUI $oPUI )
    {
        $this->oP = $oP;
        $this->oPUI = $oPUI;
        $this->oSLDB = new SLDBProfile($this->oP->oApp);  // use oP->oProfilesDB when it extends from SLDBProfile
    }

    function Init()
    {
        /* kCurrMbr is GetUID unless office mode allows member selection
         */
    }

    function StyleDraw()
    {
        return(
            "<style>
             </style>");
    }

    function ControlDraw()
    {
        return($this->oPUI->Participant_ControlDraw());
    }

    function ContentDraw()
    {
        $s = "";

        /* Membership status and renewal
         */
        if( $this->oP->KCurrMbr() ) {
            $parms = $this->oP->oL->GetLang()=='EN'
                        ? ['sExtra_Current' => "<br/>We're glad to help at <a href='mailto:growers@seeds.ca'>growers@seeds.ca</a>.",
                           'sExtra_Expired' => "Then refresh this page and join our projects.<br/><br/>"]
                        : ['sExtra_Current' => "<br/>Nous sommes heureux de vous aider &agrave; <a href='mailto:growers@seeds.ca'>growers@seeds.ca</a>.",
                           'sExtra_Expired' => "Rafra&icirc;chissez ensuite cette page et rejoignez nos projets.<br/><br/>"];
            $parms['lang'] = $this->oP->oL->GetLang();

            $sL = (new MbrContactsDraw($this->oP->oApp))->DrawExpiryNotice($this->oP->KCurrMbr(), $parms );
            $sR = "<div style='border:1px solid #aaa;padding:1em;'>{$this->oP->oMbr->DrawAddressBlock($this->oP->KCurrMbr())}</div>";
            $s .= "<div class='container-fluid'><div class='row'>
                       <div class='col-md-6'>$sL</div>
                       <div class='col-md-3'>&nbsp;</div>
                       <div class='col-md-3'>$sR</div>
                   </div>";
        }

        $s .= "<hr/>";

// TODO: Each project configurable by control panel in Office tab
// TODO: Add a lettuce project as an entry point for future core growers
        $s .= $this->cgoSignup();


        /* CGO bean selection
         */
/*
        if( ($kfrBean = $this->oP->oProfilesDB->GetKFRCond('VI', "fk_mbr_contacts={$this->kCurrMbr} AND metadata LIKE '%project=cgo2025bean%'")) ) {
            if( !$kfrBean->Value('fk_sl_inventory') ) {
                include_once("cgo_signup.php");

                $s .= (new CGOSignup_Bean($this->oP))->Draw2();

                // For Office mode, tell cgosignup the uid to sign up
                $s .= "<script>var CGOSignup_Uid=".($this->oP->CanReadOtherUsers() ? $this->kCurrMbr : 0).";</script>";
            }
        }
*/

        return( $s );
    }

    private function cgoSignup()
    {
        $s = "";

        if(!$this->oP->KCurrMbr())  goto done;

        include_once("cgo_signup.php");

// oProfilesDB is obsolete as a named relation object - use oProfilesDB->oSLDB
// actually, update client code to use SLDBProfile relations, then make oProfileDB extend from that
        $bRegisteredGC     = $this->oP->oProfilesDB->GetCount('VI', "fk_mbr_contacts={$this->oP->KCurrMbr()} AND year={$this->oP->CurrentYear()} AND projcode='cgo_gc'");
        $bRegisteredTomato = $this->oP->oProfilesDB->GetCount('VI', "fk_mbr_contacts={$this->oP->KCurrMbr()} AND year={$this->oP->CurrentYear()} AND projcode='cgo_tomato'");
        $bRegisteredBean   = $this->oP->oProfilesDB->GetCount('VI', "fk_mbr_contacts={$this->oP->KCurrMbr()} AND year={$this->oP->CurrentYear()} AND projcode='cgo_bean'");

        //$s .= "<h4 class='alert alert-success' style='color:green'>We have lots of seeds left so we've extended the deadline!</h4>";

        $s .= "<h3>{$this->oP->oL->S('Join Our Community Seed Growouts')}</h3>";
        $s .= (new CGOSignup_GC($this->oP))->Draw($bRegisteredGC)
             ."<br/><br/>"
             .(new CGOSignup_Tomato($this->oP))->Draw($bRegisteredTomato)
             ."<br/><br/>"
             .(new CGOSignup_Bean($this->oP))->Draw($bRegisteredBean);

        /* For Office mode, tell cgosignup the uid to sign up
         */
        $s .= "<script>var CGOSignup_Uid=".($this->oP->CanReadOtherUsers() ? $this->oP->KCurrMbr() : 0).";</script>";

        done:
        return($s);
    }
}
