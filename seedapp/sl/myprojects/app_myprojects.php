<?php

/* MyProjects app
 *
 * Copyright (c) 2024-2026 Seeds of Diversity Canada
 */

/*

alter table sl_varinst add fk_mbr_contacts integer not null default 0;
alter table sl_varinst add fk_sl_inventory integer not null default 0;
alter table sl_varinst add fk_sl_species   integer not null default 0;

insert into sl_varinst (fk_mbr_contacts,fk_sl_inventory,year) values (1499,9200,2024);
insert into sl_varinst (fk_mbr_contacts,fk_sl_pcv,year) values (1499,188,2024);




add sl_varinst.fk_mbr_contacts to test for deleting mbr_contacts
 */
// padding under images for phone width
// re-enable select control after canceling tomato, or refresh page

include_once( SEEDCORE."console/console02.php" );
//include_once( SEEDLIB."google/GoogleSheets.php" );
include_once( SEEDLIB."mbr/MbrContacts.php" );
include_once( SEEDLIB."sl/sldb.php" );
include_once( SEEDLIB."sl/profiles/sl_profiles_db.php" );
include_once( SEEDLIB."sl/profiles/sl_profiles_defs.php" );
include_once( SEEDLIB."sl/profiles/sl_profiles_report.php" );
include_once( SEEDLIB."sl/profiles/sl_profiles_form.php" );
include_once( SEEDROOT."Keyframe/KeyframeForm.php" );
include_once( SEEDROOT."Keyframe/KeyframeUI.php" );
include_once( SEEDCORE."SEEDLocal.php" );
include_once( SEEDCORE."SEEDUI.php" );
include_once( SEEDCORE."SEEDCoreFormSession.php" );

$consoleConfig = [
    'CONSOLE_NAME' => "myprojects",
    'HEADER' => "My Projects",
//    'HEADER_LINKS' => array( array( 'href' => 'mbr_email.php',    'label' => "Email Lists",  'target' => '_blank' ),
//                             array( 'href' => 'mbr_mailsend.php', 'label' => "Send 'READY'", 'target' => '_blank' ) ),
    'TABSETS' => ['main'=> ['tabs' => [ 'projects' => ['label'=>'My Projects'],
                                        'sites'    => ['label'=>'My Sites'],
                                        'office'   => ['label'=>'Office'],
                                      //'settings'     => ['label'=>'Settings']
                                      ],
                            'perms' =>['PUBLIC'],    // used only to permit access to the app; tabs use separate Permissions
                           ],
                 ],
    'pathToSite' => '../../',

    'consoleSkin' => 'green',
];

SEED_define_lang();     // sets SEED_LANG which tells SEEDAppConsole to set language based on various cues including server name
$oApp = SEEDConfig_NewAppConsole( ['sessPermsRequired' => $consoleConfig['TABSETS']['main']['perms'],
                                   'consoleConfig' => $consoleConfig] );
//$oApp->kfdb->SetDebug(1);

if( ($qcmd = SEEDInput_Str('qcmd')) ) {
    $oP = new ProjectsCommon($oApp);

    $rQ = SEEDQ::GetEmptyRQ();

    /* Add a project for the current/given user.
     * Project managers can add projects for any user.
     * N.B. This doesn't check if seeds are available - only the front end does that.
     */
    if( $qcmd == 'myprojects--add' ) {
        $kLot = 0;
        $psp = $oname = "";

        if( !($uid = $oP->CanWriteOtherUsers() ? SEEDInput_Int('uid') : $oApp->sess->GetUID()) )  goto skip;

        switch( ($sProjname = SEEDInput_Str('projectName')) ) {
            case 'cgo2026gc':
                /* record project, psp, oname
                 */
                $psp = 'ground-cherry';
                $oname = "Tall-bearing selection from ".(date('Y')-1)."";
                break;
            case 'cgo2026tomato':
            case 'cgo2026bean':
                /* record project, kLot, and psp just for good measure
                 */
                $psp = substr($sProjname,7); // 'tomato' or 'bean'
                if( ($iLot = SEEDInput_Int('iLot')) ) {
                    $kLot = (new SLDBCollection($oApp))->GetRecordVal1Cond('I', "fk_sl_collection='1' AND inv_number='$iLot'", '_key');
                }
                if(!$kLot)  goto skip;
                break;
            default:
                $rQ['sErr'] = "no project";
                goto skip;
        }

// oProfilesDB is obsolete as a named relation object - use oProfilesDB->oSLDB
        if( ($kfr = $oP->oProfilesDB->Kfrel('VI')->CreateRecord()) ) {
            $kfr->SetValue('fk_mbr_contacts', $uid);
            $kfr->SetValue('psp', $psp);
            $kfr->SetValue('oname', $oname);
            $kfr->SetValue('fk_sl_inventory', $kLot);
            $kfr->UrlParmSet('metadata', 'project', $sProjname);
            $kfr->SetValue('year', 2026);

            if( $kfr->PutDBRow() ) {
                $rQ['bOk'] = true;
            }
        }
    }

    /* Remove a project for the current/given user.
     * Project managers can remove projects for any user.
     */
    if( $qcmd == 'myprojects--remove' ) {
        if( !($uid = $oP->CanWriteOtherUsers() ? SEEDInput_Int('uid') : $oApp->sess->GetUID()) )  goto skip;

        if( ($sProjname = SEEDInput_Str('projectName')) &&
// oProfilesDB is obsolete as a named relation object - use oProfilesDB->oSLDB
            ($kfr = $oP->oProfilesDB->GetKFRCond('VI', "fk_mbr_contacts={$uid} && metadata LIKE '%project=".addslashes($sProjname)."%'")) )
        {
            $kfr->StatusSet(KeyFrameRecord::STATUS_DELETED);
            if( $kfr->PutDBRow() ) {
                $rQ['bOk'] = true;
            }
        }
    }

    if( $qcmd == 'myprojects--choosebean' ) {
        if( !($uid = $oP->CanWriteOtherUsers() ? SEEDInput_Int('uid') : $oApp->sess->GetUID()) )  goto skip;

        if( ($iLot = SEEDInput_Int('iLot')) &&
            ($kfrLot = $oP->oProfilesDB->oSLDB->GetKFRCond('I', "fk_sl_collection=1 AND inv_number=$iLot") ) &&
// oProfilesDB is obsolete as a named relation object - use oProfilesDB->oSLDB
            ($kfrVI = $oP->oProfilesDB->GetKFRCond('VI', "fk_mbr_contacts={$uid} && metadata LIKE '%project=cgo2025bean%'")) )
        {
            $kfrVI->SetValue('pname', '');
            $kfrVI->SetValue('oname', '');
            $kfrVI->SetValue('fk_sl_inventory', $kfrLot->Key());
            if( $kfrVI->PutDBRow() ) {
                $rQ['bOk'] = true;
            }
        }
    }



//    include(SEEDLIB."q/Q.php");
//    DoQCmd($oApp, $qcmd);
    skip:
    echo json_encode($rQ);
    exit;
}


SEEDPRG();

//var_dump($_REQUEST);

class MyConsole02TabSet extends Console02TabSet
{
    private $oApp;

    private $oProjects;
    private $oW;

    function __construct( SEEDAppConsole $oApp )
    {
        global $consoleConfig;
        parent::__construct( $oApp->oC, $consoleConfig['TABSETS'] );

        $this->oApp = $oApp;
        $this->oProjects = new ProjectsCommon($this->oApp);
    }

    function TabSetPermission( $tsid, $tabname )
    {
        switch($tabname) {
            case 'projects':
            case 'sites':
                return( Console02TabSet::PERM_SHOW );
            case 'office':
            case 'settings':
                return( $this->oProjects->CanReadOtherUsers() ? Console02TabSet::PERM_SHOW : Console02TabSet::PERM_HIDE );
        }
        return( Console02TabSet::PERM_HIDE );
    }


    function TabSet_main_projects_Init()         { $this->oW = new ProjectsTabProjects($this->oProjects, $this); $this->oW->Init(); }
    function TabSet_main_projects_StyleDraw()    { return( $this->oW->StyleDraw() ); }
    function TabSet_main_projects_ControlDraw()  { return( $this->oW->ControlDraw() ); }
    function TabSet_main_projects_ContentDraw()  { return( $this->oW->ContentDraw() ); }

    function TabSet_main_sites_Init()         { $this->oW = new ProjectsTabSites($this->oProjects); $this->oW->Init(); }
    function TabSet_main_sites_ControlDraw()  { return( $this->oW->ControlDraw() ); }
    function TabSet_main_sites_ContentDraw()  { return( $this->oW->ContentDraw() ); }

    function TabSet_main_office_Init()          { $this->oW = new ProjectsTabOffice($this->oProjects, $this); $this->oW->Init(); }
    function TabSet_main_office_ControlDraw()   { return( $this->oW->ControlDraw() ); }
    function TabSet_main_office_ContentDraw()   { return( $this->oW->ContentDraw() ); }

//    function TabSet_main_settings_Init()         { $this->oW = new GrowoutsTabSettings($this->oGO); $this->oW->Init(); }
//    function TabSet_main_settings_ControlDraw()  { return( $this->oW->ControlDraw() ); }
//    function TabSet_main_settings_ContentDraw()  { return( $this->oW->ContentDraw() ); }
}

class ProjectsCommon
{
    const  BUCKET_NS = 'AppMyProjects';
    public $oApp;
    public $oProfilesDB;
    public $oL;

    public const workflowcodes =
                ["1 - Interest"                     => 1,
                 "2 - Choosing seeds"               => 2,
                 "3 - Seeds chosen"                 => 3,
                 "4 - Seeds mailed"                 => 4,
                 "5 - Growing"                      => 5,
                 "6 - Needs support"                => 6,
                 "20 - Seeds returned successfully" => 20,
                 "-1 - Did not start"               => -1,
                 "-2 - Problem during season"       => -2,
                 "-3 - Did not return seeds"        => -3,
                ];

    function __construct( SEEDAppConsole $oApp, array $raParms = [] )
    {
        $this->oApp = $oApp;
        $this->oProfilesDB = new SLProfilesDB($oApp);
        $this->oL = new SEED_Local( $this->sLocalStrs(),
                                    @$raParms['lang'] ?: $this->oApp->lang,     // specify lang or use oApp's lang
                                    'myprojects' );
    }

    function CanReadOtherUsers()
    {
        return( $this->oApp->sess->CanRead('SLProfileOtherUsers') );
    }

    function CanWriteOtherUsers()
    {
        return( $this->oApp->sess->CanWrite('SLProfileOtherUsers') );
    }

    private function sLocalStrs()
    {
        return( ['ns'=>'myprojects', 'strs'=> [
            'Join Our Community Seed Growouts'
                => ['EN'=>"[[]]",
                    'FR'=>"Rejoignez nos projets de semences communautaires"],
            // More with chevron
            'More'
                => ['EN'=>"[[]]",
                    'FR'=>"Voir plus"],
            'Sorry no longer available'
                => ['EN'=>"[[]]",
                    'FR'=>"D&eacute;sol&eacute;, n'est plus disponible"],
            'Choose a variety'
                => ['EN'=>"[[]]",
                    'FR'=>"Choisissez une vari&eacute;t&eacute;"],
        ]] );
    }
}

class ProjectsTabProjects
{
    private $oCTS;
    private $oP;
    private $oMbr;
    private $oSLDB;

    private $oUIProfile, $oUIRecord;

    private $kCurrMbr = 0;
    private $kfrCurrVI = null;
    private $bNew = false;      // a new record is requested

    function __construct( ProjectsCommon $oP, MyConsole02TabSet $oCTS )
    {
        $this->oCTS = $oCTS;
        $this->oP = $oP;
        $this->oMbr = new Mbr_Contacts($this->oP->oApp);
        $this->oSLDB = new SLDBProfile($this->oP->oApp);

        // ui components of this tab view
        $this->oUIProfile = new ProjectsTabProjects_UI_Profile($this, $oP);
        $this->oUIRecord = new ProjectsTabProjects_UI_Record($this, $oP);

        // default to current login - change to the SVA selected kMbr if allowed to do that below
        $this->kCurrMbr = $this->oP->oApp->sess->GetUID();
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

        /* kCurrMbr is GetUID unless office mode allows member selection
         */

        /* kfrCurrVI and kCurrMbr must be correct before initializing ui components
         */
        $this->kfrCurrVI = $this->oUIRecord->Init($this->kCurrMbr);     // returns kfrCurrVI because Update could have created a new record (if bNew)

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


        /* Show projects
         */
        $sLeft = $sOfficePanel = $sProfile = "";

        // Add New Project button
        if( $this->oP->CanWriteOtherUsers() ) {
            $sLeft .= "<form method='post'><input type='submit' name='action' value='Add New Project'/></form>";
        }


// TODO: show current year projects before sign-ups, past project after sign-ups. When sign-ups go invisible they will all be together, but still would be good to have a marker between.
        /* 2025 and 2024 projects
         */
        if( ($u = intval($this->kCurrMbr)) ) {
            $raY = [];
            foreach([2026,2025,2024] as $year) {
                foreach( $this->oP->oProfilesDB->GetVarInstNames($u, $year) as $ra ) {
                    if(!isset($raY[$year])) {
                        $sLeft .= "<h4>$year projects for {$this->oMbr->GetContactName($u)}</h4>";
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
                            .$this->oUIRecord->DrawRecord( $this->kCurrMbr /*kluge: remove when kCurrMbr is known in Init()*/);
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


    private function cgoSignup()
    {
        $s = "";

        include_once("cgo_signup.php");

// oProfilesDB is obsolete as a named relation object - use oProfilesDB->oSLDB
        $bRegisteredGC     = $this->oP->oProfilesDB->GetCount('VI', "fk_mbr_contacts={$this->kCurrMbr} AND metadata LIKE '%project=cgo2026gc%'");
        $bRegisteredTomato = $this->oP->oProfilesDB->GetCount('VI', "fk_mbr_contacts={$this->kCurrMbr} AND metadata LIKE '%project=cgo2026tomato%'");
        $bRegisteredBean   = $this->oP->oProfilesDB->GetCount('VI', "fk_mbr_contacts={$this->kCurrMbr} AND metadata LIKE '%project=cgo2026bean%'");

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

        return($s);
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


class ProjectsTabSites
{
    private $oP;

    function __construct( ProjectsCommon $oP )
    {
        $this->oP = $oP;
    }

    function Init()
    {
    }

    function ControlDraw()
    {
    }

    function ContentDraw()
    {
        $s = "";

        $s = "Sites will be here soon";

        return( $s );
    }
}

class ProjectsTabOffice
{
    private $oCTS;
    private $oP;
    private $oMbr;
    private $oSLDB;

    function __construct( ProjectsCommon $oP, MyConsole02TabSet $oCTS )
    {
        $this->oCTS = $oCTS;
        $this->oP = $oP;
        $this->oMbr = new Mbr_Contacts($this->oP->oApp);
        $this->oSLDB = new SLDBProfile($this->oP->oApp);
    }

    function Init()
    {
    }

    function ControlDraw()
    {
        $s = "";

        return($s);
    }

    function ContentDraw()
    {
        $s = "";

        $oForm = new SEEDCoreFormSVA($this->oCTS->TabSetGetSVACurrentTab('main'), 'A',
                                     ['fields'=>['all'          =>['control'=>'checkbox'],
                                                 'ground-cherry'=>['control'=>'checkbox'],
                                                 'tomato'       =>['control'=>'checkbox'],
                                                 'bean'         =>['control'=>'checkbox'],
                                     ]]);
        $oForm->Update();
        $s .= "<div style='display:inline-block;border:1px solid #aaa;border-radius:5px;padding:1em'><form>
               <p>".$oForm->Select('mode', ["CGO growers"=>'cgo_growers', "Core growers"=>'core_growers', "Profile Observations"=>'desc_obs'])."</p>
               <p>".$oForm->Select('year', ['2025'=>2025, '2024'=>2024])."</p>
               <p>".$oForm->Text('workflow','',['size'=>4])." min workflow</p>"

/*
               <p>".$oForm->Checkbox('all', "All")."</p>
               <p>".$oForm->Checkbox('ground-cherry', "Ground cherry")."</p>
               <p>".$oForm->Checkbox('tomato', "Tomato")."</p>
               <p>".$oForm->Checkbox('bean', "Bean")."</p>
*/
             ."<p><input type='submit' value='Show'/></p>
               </form></div>
               <div style='display:inline-block;vertical-align:top;padding-left:1em'>
                   <a href='?xlsx=1' target='_blank'><img src='https://seeds.ca/w/std/img/dr/xls.png' style='height:30px'/></a>
               </div>";

        switch($oForm->Value('mode')) {
            case 'cgo_growers':     $s .= $this->drawCGOGrowers($oForm);    break;
            case 'core_growers':    $s .= $this->drawCoreGrowers($oForm);   break;
            case 'desc_obs':        $s .= $this->drawDescObs($oForm);       break;
        }

        return( $s );
    }

    private function drawCGOGrowers( SEEDCoreForm $oForm )
    {
        $bShow = true;
        $raProj = [];
        $s = "";

        if( $oForm->Value('all') ) {
            $bShow = true;
        } else {
            foreach( ['ground-cherry','tomato','bean'] as $proj ) {
                if( $oForm->Value($proj) ) {
                    $bShow = true;
                    $raProj[] = $proj;
                }
            }
        }

        if( !$bShow )  goto done;

        /* Integrity tests
         */
        if( ($n = $this->oSLDB->GetCount('VI', "year='{$oForm->ValueDB('year')}' AND projcode<>'core' AND projcode NOT LIKE 'cgo_%'")) ) {
            $this->oP->oApp->oC->AddErrMsg("$n records have invalid projcode<br/>");
        }

        if( ($raTest = $this->oP->oApp->kfdb->QueryRowsRA(
                "SELECT V1.fk_mbr_contacts as kMbr , V1._key FROM {$this->oP->oApp->DBName('seeds1')}.sl_varinst V1, {$this->oP->oApp->DBName('seeds1')}.sl_varinst V2
                 WHERE V1._status=0 AND V2._status=0 AND
                       V1.year={$oForm->ValueDB('year')} AND V2.year={$oForm->ValueDB('year')} AND
                       V1.fk_mbr_contacts=V2.fk_mbr_contacts AND
                       V1._key < V2._key AND
                       V1.projcode <> V2.projcode")) )
        {
            $this->oP->oApp->oC->AddErrMsg(count($raTest)." growers are in multiple projects: ".SEEDCore_ArrayExpandRows($raTest, "[[kMbr]] ")."<br/>");
        }

        $sCond = "year='{$oForm->ValueDB('year')}' AND projcode LIKE 'cgo_%'"
                .(($iWorkflow = $oForm->ValueInt('workflow')) ? " AND workflow >= $iWorkflow" : "")
                .($raProj ? (" AND psp in ('".implode("','", $raProj)."')") : "");

        $raMbr = [];
        foreach( $this->oSLDB->GetList('VI', $sCond) as $raVI ) {
            $kMbr = $raVI['fk_mbr_contacts'];

            if( !isset($raMbr[$kMbr]) ) {
                $ra = $this->oMbr->oDB->GetRecordVals('M', $kMbr);
                $raMbr[$kMbr] = ['member_name' => $ra ? $this->oMbr->GetContactNameFromMbrRA($ra) : "",
                                 'member_email'=> @$ra['email'],
                                 'ground-cherry' => '',
                                 'tomato' => '',
                                 'bean' => '',
                ];
            }

// use ComputeVarInstName - needs kfrVI instead of raVI
            $kfrLot = $raVI['fk_sl_inventory'] ? $this->oSLDB->GetKFR('IxAxP', $raVI['fk_sl_inventory']) : null;
            $psp = $kfrLot ? $kfrLot->Value('P_psp') : $raVI['psp'];

            switch( $psp ) {
                case 'ground-cherry':
                    $raMbr[$kMbr]['ground-cherry'] = 1;
                    break;
                case 'tomato':
                case 'bean':
                    if( $kfrLot ) {
                        $raMbr[$kMbr][$psp] .= "{$kfrLot->Value('P_name')} ({$kfrLot->Value('inv_number')}) ";
                    }
                    break;
            }
        }

        if( SEEDInput_Int('xlsx') ) {
            // output as a spreadsheet
            include_once( SEEDCORE."SEEDXLSX.php" );

            $title = "Seeds of Diversity CGO Projects {$oForm->Value('year')}";
            $oXLSX = new SEEDXlsWrite( ['title'=> $title,
                                        'filename'=>$title.'.xlsx',
                                        'creator'=>$this->oP->oApp->sess->GetName(),
                                        'author'=>$this->oP->oApp->sess->GetName()] );

            $raKeys = ['member_name','member_email','ground-cherry','tomato','bean'];

            $oXLSX->WriteHeader( 0, array_merge(['member'],$raKeys));

            $iRow = 2;  // rows are origin-1 so this is the row below the header
            foreach( $raMbr as $kMbr => $ra ) {
                // reorder the $ra values to the same order as $raKeys
                $oXLSX->WriteRow( 0, $iRow++, SEEDCore_utf8_encode( array_merge([$kMbr],array_replace(array_fill_keys($raKeys,''), array_intersect_key($ra,array_fill_keys($raKeys,''))))) );
            }

            $oXLSX->OutputSpreadsheet();
            exit;
        }

        $s .= "<style>.myproj_table td, .myproj_table th {padding:0 5px}</style>
               <table class='myproj_table' style=''><tr><th>Member</th><th>email</th><th>Ground cherry</th><th>Tomato</th><th>Bean</th></tr>";
        foreach( $raMbr as $kMbr => $ra ) {
            $s .= "<tr><td>{$ra['member_name']} ({$kMbr})</td><td>{$ra['member_email']}</td>
                       <td>{$ra['ground-cherry']}</td><td>{$ra['tomato']}</td><td>{$ra['bean']}</td></tr>";
        }
        $s .= "</table>";

        done:
        return($s);
    }

    private function drawCoreGrowers( SEEDCoreForm $oForm )
    {
        $s = "";

        /* We want to show all seeds being grown by Core growers, but those aren't necessarily all Core seeds. e.g. some will be cgo_gc
         * Get distinct kMbr of all the growers doing Core projects of the chosen workflow,
         * then get all their projects regardless of projcode.
         */
        $sCond = "year='{$oForm->ValueDB('year')}'"
                .(($iWorkflow = $oForm->ValueInt('workflow')) ? " AND workflow >= $iWorkflow" : "");

        // growers doing projects with the basic constraints where at least one is a Core project - this returns [kMbr1, kMbr2, kMbr2, ...]
        $raMbr = $this->oSLDB->Get1List('VI', 'fk_mbr_contacts', $sCond." AND projcode='core'", ['sGroupAliases'=>"fk_mbr_contacts"]);
        // all projects with the basic constraints, for those growers, regardless of projcode
        $raVIRows = $this->oSLDB->GetList('VI', $sCond." AND fk_mbr_contacts IN (".implode(',',$raMbr).")", ['sSortCol'=>"fk_mbr_contacts"]);

// make VIxM_IxAxP_P2
        $raOut = [];
        foreach( $raVIRows as $raVI ) {
            $kMbr = $raVI['fk_mbr_contacts'];
            $raM = $this->oMbr->oDB->GetRecordVals('M', $kMbr);

// use ComputeVarInstName - needs kfrVI instead of raVI
            $kfrLot = $raVI['fk_sl_inventory'] ? $this->oSLDB->GetKFR('IxAxP', $raVI['fk_sl_inventory']) : null;

            $raOut[] = ['member' => $kMbr,
                        'member_name'  => ($raM ? $this->oMbr->GetContactNameFromMbrRA($raM) : ""),
                        'member_email' => $raM['email'],
                        'projcode'     => $raVI['projcode'],
                        'psp'          => $kfrLot ? $kfrLot->Value('P_psp')  : ($raVI['psp'] ?: $raVI['osp']),
                        'pname'        => $kfrLot ? $kfrLot->Value('P_name') : ($raVI['pname'] ?: $raVI['oname']),
                        'lot'          => $kfrLot ? $kfrLot->Value('inv_number') : ""
                       ];
        }

        if( SEEDInput_Int('xlsx') ) {
            // output as a spreadsheet
            include_once( SEEDCORE."SEEDXLSX.php" );

            $title = "Seeds of Diversity Core Projects {$oForm->Value('year')}";
            $oXLSX = new SEEDXlsWrite( ['title'=> $title,
                                        'filename'=>$title.'.xlsx',
                                        'creator'=>$this->oP->oApp->sess->GetName(),
                                        'author'=>$this->oP->oApp->sess->GetName()] );

            $raKeys = ['member','member_name','member_email','projcode','psp','pname','lot'];

            $oXLSX->WriteHeader( 0, $raKeys);

            $iRow = 2;  // rows are origin-1 so this is the row below the header
            foreach( $raOut as $ra ) {
                // reorder the $ra values to the same order as $raKeys
                $oXLSX->WriteRow( 0, $iRow++, SEEDCore_utf8_encode( array_replace(array_fill_keys($raKeys,''), array_intersect_key($ra,array_fill_keys($raKeys,'')))) );
            }

            $oXLSX->OutputSpreadsheet();
            exit;
        }

        $s .= "<style>.myproj_table td, .myproj_table th {padding:0 5px}</style>
               <table class='myproj_table' style=''><tr><th>Member</th><th>email</th><th>project</th><th>psp</th><th>pname</th><th>Lot</th></tr>";
        foreach( $raOut as $ra ) {
            $s .= "<tr><td>{$ra['member_name']} ({$ra['member']})</td><td>{$ra['member_email']}</td>
                       <td>{$ra['projcode']}</td><td>{$ra['psp']}</td><td>{$ra['pname']}</td><td>{$ra['lot']}</td></tr>";
        }
        $s .= "</table>";

        done:
        return($s);
    }

    private function drawDescObs( SEEDCoreForm $oForm )
    {
        $bShow = false;
        $raProj = [];
        $s = "";

        // you can only select one of these
        $psp = '';
        if( $oForm->Value('bean') )   $psp = 'bean';
        if( $oForm->Value('tomato') ) $psp = 'tomato';
        if( $oForm->Value('ground-cherry') ) $psp = 'ground-cherry';

        if( !$psp) {
            $s .= "<b>Choose a species</b>";
            goto done;
        }

        $year = $oForm->ValueDB('year');
        $sCond = "year='{$year}' AND VI.psp='{$psp}'";

        $raMbr = [];
        $raVI = [];
        $raDescKeys = [];
        foreach( $this->oSLDB->GetList('VOxVI', $sCond) as $vo ) {
            $kVI = $vo['fk_sl_varinst'];
            if( !isset($raVI[$kVI]) ) {
                $raVI[$kVI]['VO'] = [];

                $raVI[$kVI]['psp'] = $psp;
                $raVI[$kVI]['year'] = $year;
                $raVI[$kVI]['kMbr'] = $vo['VI_fk_mbr_contacts'];

                if( ($raMbr = $this->oMbr->GetBasicValues($vo['VI_fk_mbr_contacts'])) ) {
                    $raVI[$kVI]['member_province'] = $raMbr['province'];
                    $raVI[$kVI]['member_email'] = $raMbr['email'];
                } else {
                    $raVI[$kVI]['member_province'] = "";
                    $raVI[$kVI]['member_email'] = "";
                }

                ($cv = $vo['VI_pname'])
                or
                ($cv = $vo['VI_oname'])
                or
                ($vo['VI_fk_sl_pcv'] && ($cv = $this->oSLDB->GetRecordVal1('P', $vo['VI_fk_sl_pcv'], 'pname')))
                or
                ($vo['VI_fk_sl_inventory'] && ($cv = $this->oSLDB->GetRecordVal1('IxAxP', $vo['VI_fk_sl_inventory'], 'P_pname')));

                $raVI[$kVI]['cv'] = $cv;
            }
            $raVI[$kVI]['VO-record'][$vo['k']] = $vo['v'];
            $raDescKeys[$vo['k']] = 1;
        }

        // $raVI[]['VO'] is an array of descObs_k => descObs_v for each varinst : an arbitrary set of those in arbitrary order
        // Using $raDescKeys which is a set of all descObs_k transform each ['VO'] to an identical format filling unknown values with ''
        $raDescKeys = array_keys($raDescKeys);
        foreach( $raVI as $kVI => $ra ) {
            $raVI[$kVI]['VO-expanded'] = array_replace(array_fill_keys($raDescKeys,''), array_intersect_key($ra['VO-record'],array_fill_keys($raDescKeys,'')));
        }

        if( SEEDInput_Int('xlsx') ) {
            // output as a spreadsheet
            include_once( SEEDCORE."SEEDXLSX.php" );

            $title = "Seeds of Diversity Projects {$oForm->Value('year')}";
            $oXLSX = new SEEDXlsWrite( ['title'=> $title,
                                        'filename'=>$title.'.xlsx',
                                        'creator'=>$this->oP->oApp->sess->GetName(),
                                        'author'=>$this->oP->oApp->sess->GetName()] );

            $raKeys = ['member_name','member_email','member_province','year','species','cultivar'];

            $oXLSX->WriteHeader( 0, array_merge(['member'],$raKeys, $raDescKeys));

            $iRow = 2;  // rows are origin-1 so this is the row below the header
            foreach( $raVI as $k => $ra ) {
                // reorder the $ra values to the same order as $raKeys
                $oXLSX->WriteRow( 0, $iRow++, SEEDCore_utf8_encode(
                    array_merge( [$ra['kMbr'], '', $ra['member_email'], $ra['member_province'], $ra['year'], $ra['psp'], $ra['cv']], $ra['VO-expanded'] )) );
            }

            $oXLSX->OutputSpreadsheet();
            exit;
        }

        $s .= "<style>.myproj_table td, .myproj_table th {padding:0 5px}</style>
               <table class='myproj_table' style=''><tr><th>Member</th><th>email</th><th>province</th><th>Species</th><th>Cultivar</th><th>Profile</th></tr>";
        foreach( $raVI as $kVI => $ra ) {
            $s .= "<tr><td>{$ra['kMbr']}</td><td>{$ra['member_email']}</td><td>{$ra['member_province']}</td><td>{$ra['psp']}</td><td>{$ra['cv']}</td>
                       <td>".SEEDCore_ArrayExpandSeries($ra['VO-record'], "[[k]]=[[v]], ")."</td></tr>";
        }
        $s .= "</table>";

        done:
        return($s);
    }
}




$oCTS = new MyConsole02TabSet( $oApp );
$s = $oApp->oC->DrawConsole( "[[TabSet:main]]", ['oTabSet'=>$oCTS] );

echo Console02Static::HTMLPage( SEEDCore_utf8_encode($s), "", 'EN',
                                ['consoleSkin'=>'green',
                                 'raScriptFiles' => [$oApp->UrlW()."js/SEEDCore.js",
                                                     $oApp->UrlW()."seedapp/MbrContactsSelect2.js"],
                                 'bSelect2' => true,
                                ] );
?>

<script>SEEDCore_CleanBrowserAddress();</script>

<!--  CGO Signup  -->

<script>
$(document).ready( function () {
    $('.cgosignup-opener').click(function () {
        $(this).hide();
        let j = $(this).closest('.row').next();
        j.slideDown();
    });
});


class CGOSignup
{
    static doRegister(jThis)
    {
        let jForm = jThis.closest('.cgosignup-form');
        let projName = jForm.data('project');
        let iLot = 0;

        if(projName=='cgo2026tomato') {
            iLot = document.getElementById('cgosignup-form-tomatoselect').value;
        }
        if(projName=='cgo2026bean') {
            iLot = document.getElementById('cgosignup-form-beanselect').value;
        }

        let o = {qcmd:'myprojects--add',
                projectName: projName,
                uid: CGOSignup_Uid,
                iLot: iLot};
        let rQ = SEEDJXAsync2("myprojects.php", o,
                     function (rQ) {
                         if( rQ['bOk'] ) {
                             jForm.find('.cgosignup-form-btn-container-notregistered').hide();
                             jForm.find('.cgosignup-form-btn-container-registered').show();
                         }
                     });

        console.log(rQ);
    }

	static doUnregister(jThis)
    {
        let jForm = jThis.closest('.cgosignup-form');
        let projName = jForm.data('project');

        let o = {qcmd:'myprojects--remove',
                projectName: projName,
                uid: CGOSignup_Uid};
        let rQ = SEEDJXAsync2("myprojects.php", o,
                     function (rQ) {
                         if( rQ['bOk'] ) {
                             jForm.find('.cgosignup-form-btn-container-notregistered').show();
                             jForm.find('.cgosignup-form-btn-container-registered').hide();
                         }
                     });

    }

    static doChooseBean(jThis)
    {
        let jForm = jThis.closest('.cgosignup-form');
        let iLot = document.getElementById('cgosignup-form-beanselect').value;

        iLot = parseInt(iLot) || 0;
        if( !iLot ) return;

        let o = {qcmd:'myprojects--choosebean',
                 uid: CGOSignup_Uid,
                 iLot: iLot};
        let rQ = SEEDJXAsync2("myprojects.php", o,
                     function (rQ) {
                         if( rQ['bOk'] ) {
                             jForm.html("<div class='alert alert-success'>Thanks! We'll send your seeds right away</div>");
                         } else {
                             jForm.html("<div class='alert alert-warning'>Sorry, something's wrong. Please contact our office at <a href='mailto:office@seeds.ca'>office@seeds.ca</a></div>");
                         }
                     });

        console.log(rQ);
    }
}


class CGOSignup_GroundCherry
{
    static doValidate()
    {
        let r1 = document.getElementById('cgosignup-form-gc1').checked;
        let r2 = document.getElementById('cgosignup-form-gc2').checked;
        $('#cgosignup-form-gcbutton').prop('disabled', !(r1 && r2));
    }
}

class CGOSignup_Tomato
{
    static doValidate()
    {
        let r1 = document.getElementById('cgosignup-form-tomato1').checked;
        let r2 = document.getElementById('cgosignup-form-tomato2').checked;
        let r3 = document.getElementById('cgosignup-form-tomato3').checked;
        let sel = document.getElementById('cgosignup-form-tomatoselect').value > 0;

        $('#cgosignup-form-tomatobutton').prop('disabled', !(r1 && r2 && r3 && sel));
    }
}

class CGOSignup_Bean
{
    static doValidate()
    {
        let r1 = document.getElementById('cgosignup-form-bean1').checked;
        let r2 = document.getElementById('cgosignup-form-bean2').checked;
        let sel = document.getElementById('cgosignup-form-beanselect').value > 0;
        $('#cgosignup-form-beanbutton').prop('disabled', !(r1 && r2 && sel));
    }
}
</script>

<style>
.cgosignup-box {
    border:1px solid #aaa;
    border-radius:5px;
    box-shadow: #ccc 6px 6px;
    padding: 1em;
}

.cgosignup-form-button {
    background-color:green;
    color:white;
    font-weight:bold;
    transition: all ease-in-out 0s;
}
.cgosignup-form-button[disabled] {
    background-color:#ccc;
    color:#555;
}
</style>
