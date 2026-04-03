<?php

/* MyProjects app
 *
 * Copyright (c) 2024-2026 Seeds of Diversity Canada
 */

class ProjectsTabSignup
{
    private $oCTS;
    private $oP;
    private $oMbr;
    private $oSLDB;

    private $kCurrMbr = 0;

    function __construct( ProjectsCommon $oP, MyConsole02TabSet $oCTS )
    {
        $this->oCTS = $oCTS;
        $this->oP = $oP;
        $this->oMbr = new Mbr_Contacts($this->oP->oApp);
        $this->oSLDB = new SLDBProfile($this->oP->oApp);

        // default to current login - change to the SVA selected kMbr if allowed to do that below
        $this->kCurrMbr = $this->oP->oApp->sess->GetUID();
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
        $s = "";

// put all this in a class and get kCurrMbr in Init()
        if( $this->oP->CanReadOtherUsers() ) {
// make a checkbox Show What Members See to turn off CanReadOtherUsers() -- except for that checkbox
            $y = 2024;

            $oForm = new SEEDCoreFormSVA($this->oCTS->TabSetGetSVACurrentTab('main'), 'Plain');

            // the SVA is active so you can get old values to compare
            $iWorkflowOld = $oForm->Value('workflow');

            $oForm->Update();

            $iWorkflow = $oForm->ValueInt('workflow');
            $bWorkflowChanged = $iWorkflow != $iWorkflowOld;

            $kMbrSearch = SEEDInput_Int('kMbrSearch');

            if( $kMbrSearch ) {
                // reset workflow filter when a member is selected via search
                $bWorkflowChanged = $iWorkflow != 0;
                $iWorkflow = 0;
                $oForm->SetValue('workflow', 0);
            }


            /* Get list of project members, filtered by workflow state
             */
            $raOpts = [];
            $condWorkflow = $iWorkflow ? " AND workflow=$iWorkflow" : "";
            foreach( $this->oSLDB->Get1List('VI', 'fk_mbr_contacts', "VI.year>=$y {$condWorkflow}") as $kMbr ) {
                $raOpts[$this->oMbr->GetContactName($kMbr)." ($kMbr)"] = $kMbr;                     // uniquifies the list
            }
            ksort($raOpts);

            /* If member selected via search
             */
            if( $kMbrSearch ) {
                $name = $this->oMbr->GetContactName($kMbrSearch);
                $raOpts["$name ($kMbrSearch)"] = $kMbrSearch;  // add to the dropdown (idempotent if it is already there)
                $this->kCurrMbr = $kMbrSearch;                 // current in ui
            } else
            /* If member chosen from dropdown or recalled from oSVA.
             * If workflow changed, it's best to forget the kMbr state so the default reset() behaviour should happen instead.
             * Adding to dropdown for rare cases where kMbr already selected but no projects yet. e.g. search for member without project, click Add Project : won't be loaded into dropdown
             */
            if( !$bWorkflowChanged && ($kMbr = $oForm->ValueInt('kMbr')) ) {
                $name = $this->oMbr->GetContactName($kMbr);
                $raOpts["$name ($kMbr)"] = $kMbr;           // add to the dropdown (idempotent if it is already there)
                $this->kCurrMbr = $kMbr;                    // current in ui
            } else {
                $this->kCurrMbr = reset($raOpts);
            }
            $oForm->SetValue('kMbr', $this->kCurrMbr);      // make this member persistent in oFormSVA

            $s .= "<div style='display:inline-block'>
                       <form method='post'>".$oForm->Select('kMbr', $raOpts, "", ['selected'=>$this->kCurrMbr, 'attrs'=>"onChange='submit();'"])
                     ."<br/><br/>"
                     .$oForm->Select('workflow', array_merge(['-- Filter by workflow --'=>'0'],$this->oP::workflowcodes), "", ['selected'=>$iWorkflow, 'attrs'=>"onChange='submit();'"])
                     ."</form>
                   </div>
                   &nbsp;&nbsp;
                   <div style='display:inline-block;vertical-align:top'>
                       <form method='post'><select id='kMbrSearch' name='kMbrSearch' style='width:40em' onChange='submit();'><option value='0'>Search for a member</option></select></form>
                   </div>
                   <script>
                       new MbrContactsSelect2( { jSelect: $('#kMbrSearch'),
                                                 qUrl: '{$this->oP->oApp->UrlQ()}' } );
                   </script>";
        }

        // show the kCurrMbr's name on the right
        $s .= "<div style='text-align:right'><h3 style='padding:0;margin:0;color:white'>{$this->oMbr->GetContactName($this->kCurrMbr)}</h3></div>";

        return( $s );
    }

    function ContentDraw()
    {
        $s = "";

        /* Membership status and renewal
         */
        if( $this->kCurrMbr ) {
            $parms = $this->oP->oL->GetLang()=='EN'
                        ? ['sExtra_Current' => "<br/>We're glad to help at <a href='mailto:growers@seeds.ca'>growers@seeds.ca</a>.",
                           'sExtra_Expired' => "Then refresh this page and join our projects.<br/><br/>"]
                        : ['sExtra_Current' => "<br/>Nous sommes heureux de vous aider &agrave; <a href='mailto:growers@seeds.ca'>growers@seeds.ca</a>.",
                           'sExtra_Expired' => "Rafra&icirc;chissez ensuite cette page et rejoignez nos projets.<br/><br/>"];
            $parms['lang'] = $this->oP->oL->GetLang();

            $sL = (new MbrContactsDraw($this->oP->oApp))->DrawExpiryNotice($this->kCurrMbr, $parms );
            $sR = "<div style='border:1px solid #aaa;padding:1em;'>{$this->oMbr->DrawAddressBlock($this->kCurrMbr)}</div>";
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

        if(!$this->kCurrMbr)  goto done;

        include_once("cgo_signup.php");

// oProfilesDB is obsolete as a named relation object - use oProfilesDB->oSLDB
        $bRegisteredGC     = $this->oP->oProfilesDB->GetCount('VI', "fk_mbr_contacts={$this->kCurrMbr} AND year={$this->oP->CurrentYear()} AND projcode='cgo_gc'");
        $bRegisteredTomato = $this->oP->oProfilesDB->GetCount('VI', "fk_mbr_contacts={$this->kCurrMbr} AND year={$this->oP->CurrentYear()} AND projcode='cgo_tomato'");
        $bRegisteredBean   = $this->oP->oProfilesDB->GetCount('VI', "fk_mbr_contacts={$this->kCurrMbr} AND year={$this->oP->CurrentYear()} AND projcode='cgo_bean'");

        //$s .= "<h4 class='alert alert-success' style='color:green'>We have lots of seeds left so we've extended the deadline!</h4>";

        $s .= "<h3>{$this->oP->oL->S('Join Our Community Seed Growouts')}</h3>";
        $s .= (new CGOSignup_GC($this->oP))->Draw($bRegisteredGC)
             ."<br/><br/>"
             .(new CGOSignup_Tomato($this->oP))->Draw($bRegisteredTomato)
             ."<br/><br/>"
             .(new CGOSignup_Bean($this->oP))->Draw($bRegisteredBean);

        /* For Office mode, tell cgosignup the uid to sign up
         */
        $s .= "<script>var CGOSignup_Uid=".($this->oP->CanReadOtherUsers() ? $this->kCurrMbr : 0).";</script>";

        done:
        return($s);
    }
}
