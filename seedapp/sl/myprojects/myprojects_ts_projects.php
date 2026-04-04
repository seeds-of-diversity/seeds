<?php

/* MyProjects app
 *
 * Copyright (c) 2024-2026 Seeds of Diversity Canada
 */

class ProjectsTabProjects
{
    private $oP, $oPUI;
    private $oSLDB;

    private $oUIProfile, $oUIRecord;

    private $kfrCurrVI = null;
    private $bNew = false;      // a new record is requested

    function __construct( ProjectsCommon $oP, ProjectsCommonUI $oPUI )
    {
        $this->oP = $oP;
        $this->oPUI = $oPUI;
        $this->oSLDB = new SLDBProfile($this->oP->oApp);  // use oP->oProfilesDB when it extends from SLDBProfile

        // ui components of this tab view
        $this->oUIProfile = new ProjectsTabProjects_UI_Profile($this, $oP);
        $this->oUIRecord = new ProjectsTabProjects_UI_Record($this, $oP);
    }

    /* Profile and Record UI components need to reference the current kfr, but each can potentially update it.
     * Use this as the one true copy, allowing Updates to change it.
     */
    function KFRCurrVI()  { return($this->kfrCurrVI); }
    function KCurrVI()    { return($this->kfrCurrVI ? $this->kfrCurrVI->Key() : 0); }

    function Init()
    {
        /* vi == -1 indicates Add New Project mode
         * vi > 0   when user clicks in project list. forms must propagate this because it is not persistent
         */
        if( SEEDInput_Str('action') == 'Add New Project' ) {
            $this->bNew = true;
        } else if( ($kVI = SEEDInput_Int('vi')) > 0 ) {
            $this->kfrCurrVI = $this->oP->oProfilesDB->oSLDB->GetKFR('VI', $kVI);
        }

        /* kfrCurrVI and kCurrMbr must be correct before initializing ui components
         */
        $this->kfrCurrVI = $this->oUIRecord->Init($this->oP->KCurrMbr());     // returns kfrCurrVI because Update could have created a new record (if bNew)

        $this->oUIProfile->Init();
    }

    function StyleDraw()
    {
        return(
            "<style>
                 .projlist-item-workflow { display:inline-block; color:#777; border:1px solid #777; border-radius:3px; padding:0 2px }
                 .projlist-item-workflow-0  {}
                 .projlist-item-workflow-1  {}
                 .projlist-item-workflow-2  {}
                 .projlist-item-workflow-3  {color:orange; background-color:#ffa}
                 .projlist-item-workflow-4  {color:blue; background-color:#ddf}
                 .projlist-item-workflow-5  {color:blue; background-color:#ddf}
                 .projlist-item-workflow-6  {color:red; background-color:#fdd}
                 .projlist-item-workflow-20 {color:green; background-color:#dfd}
                 .projlist-item-workflow--1 {color:black; background-color:#fdd}
                 .projlist-item-workflow--2 {color:black; background-color:#fdd}
                 .projlist-item-workflow--3 {color:black; background-color:#fdd}
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
        if($this->oP->KCurrMbr()) {
            $parms = $this->oP->oL->GetLang()=='EN'
                        ? ['sExtra_Current' => "<br/>We're glad to help at <a href='mailto:growers@seeds.ca'>growers@seeds.ca</a>.",
                           'sExtra_Expired' => "Then refresh this page and join our projects.<br/><br/>"]
                        : ['sExtra_Current' => "<br/>Nous sommes heureux de vous aider &agrave; <a href='mailto:growers@seeds.ca'>growers@seeds.ca</a>.",
                           'sExtra_Expired' => "Rafra&icirc;chissez ensuite cette page et rejoignez nos projets.<br/><br/>"];
            $parms['lang'] = $this->oP->oL->GetLang();

            $sL = (new MbrContactsDraw($this->oP->oApp))->DrawExpiryNotice($this->oP->KCurrMbr(), $parms );
// also show email
            $sR = "<div style='border:1px solid #aaa;padding:1em;'>{$this->oP->oMbr->DrawAddressBlock($this->oP->KCurrMbr())}</div>";
            $s .= "<div class='container-fluid'><div class='row'>
                       <div class='col-md-6'>$sL</div>
                       <div class='col-md-3'>&nbsp;</div>
                       <div class='col-md-3'>$sR</div>
                   </div>";
        }

        $s .= "<hr/>";

        /* Show projects
         */
        $sLeft = $sOfficePanel = $sProfile = "";

        // Add New Project button
        if( $this->oP->CanWriteOtherUsers() ) {
            $sLeft .= "<form method='post'><input type='submit' name='action' value='Add New Project'/></form>";
        }

        if( ($u = $this->oP->KCurrMbr()) ) {
            $raY = [];
            for( $year = $this->oP->CurrentYear(); $year >= 2024; --$year ) {
                foreach( $this->oP->oProfilesDB->GetVarInstNames($u, $year) as $ra ) {
                    if(!isset($raY[$year])) {
                        $sLeft .= "<h4>$year projects for {$this->oP->oMbr->GetContactName($u)}</h4>";
                        $raY[$year] = 1;
                    }

                    $namelink = "<a href='?vi={$ra['kVI']}'>{$ra['sp']} : {$ra['cv']}</a>";
                    $iWorkflow = $ra['raVI']['workflow'];

                    if( $this->oP->CanReadOtherUsers() ) {
                        $sItem = "<div class='col-md-1'><div class='projlist-item-workflow projlist-item-workflow-{$iWorkflow}'>{$iWorkflow}</div></div>
                                  <div class='col-md-11'>$namelink {$ra['raVI']['projcode']}</div>";
                    } else {
                        $sItem = "<div class='col-md-12'>$namelink</div>";
                    }
                    $sLeft .= "<div class='row projlist-item'>$sItem</div>";
                }
            }
            if( !$raY ) {
                $sLeft .= "<h4 style='color:#777'>You haven't signed up for any projects (yet!)</h3>";
            }
        }

        /* Show profile if project selected in the list or new project created
         */
        if( $this->kfrCurrVI ) {
            $sProfile .= $this->oUIProfile->DrawProfile();
        }

        /* Show record form if project selected in the list or new project requested (Add New Project)
         */
        if( ($this->bNew || $this->kfrCurrVI) && $this->oP->CanWriteOtherUsers() ) {
            $sOfficePanel .= ("<h3>".($this->bNew ? "New " : "")."Project Record</h3>")
                            .$this->oUIRecord->DrawRecord( $this->oP->KCurrMbr() /*kluge: remove when kCurrMbr is known in Init()*/);
        }

        if( $this->oP->CanWriteOtherUsers() ) {
            $s .= "<div class='container-fluid'><div class='row'>
                   <div class='col-md-3'>$sLeft</div>
                   <div class='col-md-5'>$sProfile</div>
                   <div class='col-md-4'>$sOfficePanel</div>
                   </div></div>";
        } else {
            $s .= "<div class='container-fluid'><div class='row'>
                   <div class='col-md-3'>$sLeft</div>
                   <div class='col-md-9'>$sProfile</div>
                   </div></div>";

        }

        return( $s );
    }
}

class ProjectsTabProjects_UI_Profile
{
    private $oP;
    private $oPTP;
    private $oProfilesReport;
    private $oForm;

    function __construct( ProjectsTabProjects $oPTP, ProjectsCommon $oP )
    {
        $this->oP = $oP;
        $this->oPTP = $oPTP;
        $this->oProfilesReport = new SLProfilesReport($this->oP->oProfilesDB, new SLProfilesDefs($this->oP->oProfilesDB), $this->oP->oApp );
    }

    function Init()
    {
        /* Process form submission before drawing any other components' content.
         * kfrCurrVI is correct at this point
         */
        $this->oForm = new SLProfilesForm( $this->oP->oProfilesDB, $this->oPTP->KCurrVI() );
        $this->oForm->Update();  // record the sl_desc_obs returned from the form

        /* The code above doesn't update the VI record. If it ever does, this method should return a new kfr.
         */
    }

    function DrawProfile()
    /*********************
        Draw the profile for the selected VI
        Switch between profile summary and profile form
     */
    {
        $s = "";

        $kfrVI = $this->oPTP->KFRCurrVI();
        if( !$kfrVI || !$kfrVI->Key() )  goto done;

        list($psp,$sSp,$sCv) = $this->oP->oProfilesDB->ComputeVarInstName($kfrVI);

        $s .= "<h2>$sSp : $sCv</h2>";

//what is this for
        $oUI = new SEEDUI();
        $oComp = new SEEDUIComponent($oUI);
        $oComp->Update();
        $oComp->Set_kCurr($kfrVI->Key());   // initialize the list to the right row e.g. if we just created a new row

// require $this->kCurrMbr==$this->oApp->sess->GetUID() || $this->oP->CanWriteOtherUsers()
        if( SEEDInput_Int('doForm') ) {
            // Show the form
            $oChooseForm = new SEEDCoreForm('Plain');
            $oChooseForm->Update();
            if( !$oChooseForm->Value('chooseForm') )  $oChooseForm->SetValue('chooseForm', 'cgo');

            $s .= "<div style='float:right'><form method='post'>
                   <p><b>Choose Your Form</b></p>"
                  .$oChooseForm->Select('chooseForm',
                                  ["Trial performance form for Community Grow-outs" => 'cgo',
                                   "Shortened descriptive form"                     => 'short',
                                   "Full taxonomic form"                            => 'long'],
                                  "",
                                  ['attrs'=>"onchange='submit()'"] )
                  ."<input type='hidden' name='doForm' value='1'/>
                    <input type='hidden' name='vi' value='{$kfrVI->Key()}'/>
                    </form></div>

                    <h3>Edit Record</h3>" //  for $sSp : $sCv</h3>" // (#$kVI)</h3>
                   .$this->oProfilesReport->DrawVIForm( $kfrVI, $oComp, $oChooseForm->Value('chooseForm') );
        } else {
            // Show the summary
            $s .= "<div style='border-left:1px solid #ddd;border-bottom:1px solid #ddd'>
                       <div style='float:left;margin-right:20px;'>
                           <form method='post'>
                               <input type='hidden' name='doForm' value='1'/>
                               <input type='hidden' name='vi' value='{$kfrVI->Key()}'/>
                               <input type='submit' value='Edit'/>
                      ".        //.$oComp->HiddenFormUIParms( array('kCurr', 'sortup', 'sortdown') )
                                     //.$oComp->HiddenKCurr()
                      "    </form>
                       </div>"
                       //."<h3>Record #$kVI</h3>"
                      .$this->oProfilesReport->DrawVIRecord( $kfrVI, true )
                 ."</div>";
        }

        done:
        return( $s );
    }
}

class ProjectsTabProjects_UI_Record
{
    private $oP, $oPTP;
    private $kVI = 0;
    private $kMbr = 0;
    private $oForm;

    function __construct( ProjectsTabProjects $oPTP, ProjectsCommon $oP )
    {
        $this->oP = $oP;
        $this->oPTP = $oPTP;
    }

    function Init( int $kMbr )
    {
        /* Process form submission before drawing any other components' content.
         * kCurrVI is correct at this point
         */
        $this->kVI = $this->oPTP->KCurrVI();
        $this->kMbr = $kMbr;

        $this->oForm = new KeyframeForm($this->oP->oProfilesDB->oSLDB->Kfrel('VI'), 'R', ['DSParms'=>['fn_DSPreStore'=>[$this,'DSPreStore_UIRecord']]]);
        $this->oForm->Update();

        /* If a record was submitted, return the form's new kfr to the caller to become the shared kfr for all ui components.
         * Otherwise use the parent's kfr to draw the form (return that kfr redundantly to keep the code simple)
         */
        if( $this->oForm->GetKey() ) {
            // A record was submitted via the form.
            if( $this->kVI && $this->kVI != $this->oForm->GetKey() ) {
                // some bad hacky thing is going on
                $this->oP->oApp->oC->AddErrMsg("mismatched varinst keys : {$this->kVI} and {$this->oForm->GetKey()}");
            }
            $kfrVI = $this->oForm->GetKFR();
        } else {
            // Form was not submitted, so use the pre-loaded kfr to draw the form
            if( ($kfrVI = $this->oPTP->KFRCurrVI()) ) {
                $this->oForm->SetKFR($kfrVI);
            } else {
                // blank form for new record; set default values
                if( !$this->oForm->Value('year') ) $this->oForm->SetValue('year', date('Y'));
            }
        }
        return($kfrVI);
    }

    function DSPreStore_UIRecord( Keyframe_DataStore $oDS )
    {
        /* Lot #: we store fk_sl_inventory but use inv_number in the form.
         */
        $kI = 0;
        if( ($iLot = $oDS->ValueInt('iLot')) ) {
            $kI = $this->oP->oProfilesDB->oSLDB->GetRecordVal1Cond('I', "inv_number=$iLot", '_key');
        }
        $oDS->SetValue('fk_sl_inventory', $kI);     // even if 0 because iLot could have changed to 0 or blank

        return(true);
    }


// move to ProjectsCommon
    private $projcodes =
                ["Core"              => 'core',
                 "CGO ground cherry" => 'cgo_gc',
                 "CGO tomato"        => 'cgo_tomato',
                 "CGO bean"          => 'cgo_bean',
                ];

    function DrawRecord( $kMbrKluge )
    /********************
        Show the details of the varinst record
        Switch between record summary and form
     */
    {
        /*
          alter table sl_varinst add projcode varchar(100) not null default '';
          alter table sl_varinst add workflow int not null default 0;
          alter table sl_varinst add notes_office text not null;
         */
        $s = "";

$this->kMbr = $kMbrKluge;   // remove this when kMbr is confirmed in Init()

        $kfrInv = null;
        if( ($kI = $this->oForm->Value('fk_sl_inventory')) && ($kfrInv = $this->oP->oProfilesDB->oSLDB->GetKFR('I', $kI)) ) {
            $this->oForm->SetValue('iLot', $kfrInv->Value('inv_number'));
        }


        $oExpand = new SEEDFormExpand($this->oForm);

        $s .= "<div><form method='post'>"
             .$oExpand->ExpandForm(
                  "|||BOOTSTRAP_TABLE(class='col-md-4'|class='col-md-8')
                   ||| <input type='submit' value='Save Project Record'/>
                   ||| *Project #*               || [[Key: | readonly]]
                   ||| *Project group*           || ".$this->oForm->Select('projcode', array_merge(['-- Choose --'=>''], $this->projcodes))."
                   ||| *Year*                    || [[Text:year]]
                   ||| *Workflow*                || ".$this->oForm->Select('workflow', array_merge(['-- Choose --'=>''], $this->oP::workflowcodes))."
                   ||| *SoD Lot #*               || [[Text:iLot]]  ".($kfrInv ? $kfrInv->Value('location') : "")." &nbsp;&nbsp;(kInv [[fk_sl_inventory | readonly]])
                   ||| &nbsp;
                   ||| *Species* psp             || [[Text:psp | width:100%]]
                   ||| *Species* osp             || [[Text:osp | width:100%]]
                   ||| *Variety* pname           || [[Text:pname | width:100%]]
                   ||| *Variety* oname           || [[Text:oname | width:100%]]
                   ||| metadata                  || [[Text:metadata | width:100%]]
                   ||| fk_sl_species             || [[Text:fk_sl_species]]
                   ||| fk_sl_pcv                 || [[Text:fk_sl_pcv]]
                   ||| &nbsp;                    || \n
                   ||| {replaceWith class='col-md-12'} <label>Office notes</label><br/>[[TextArea: notes_office | width:100% rows:10]]
                   |||ENDTABLE
                   [[Hidden: action | value=saveProj]]
                   [[Hidden: fk_mbr_contacts | value={$this->kMbr}]]
                 ")
             ."<input type='hidden' name='vi' value='{$this->kVI}'/>"       // -1 will cause UI to do this function, which will create new record via Update(); HiddenKey will be 0
             ."</form></div>";

        done:
        return($s);
    }
}
