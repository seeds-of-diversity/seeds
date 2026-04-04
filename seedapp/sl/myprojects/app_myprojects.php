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

include_once("myprojects_ts_signup.php");
include_once("myprojects_ts_projects.php");
include_once("myprojects_ts_office.php");

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
    'TABSETS' => ['main'=> ['tabs' => [
                                        'signup'   => ['label'=>'Join a Project'],
                                        'projects' => ['label'=>'My Projects'],
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
                                   'consoleConfig' => $consoleConfig,
                                   'sessUIConfig_fTemplates'=>[__DIR__.'/myprojects_login.html']
                                  ] );
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
        $psp = $oname = $projcode = "";

        if( !($uid = $oP->CanWriteOtherUsers() ? SEEDInput_Int('uid') : $oApp->sess->GetUID()) )  goto skip;

        switch( ($projcode = SEEDInput_Str('projcode')) ) {
            case 'cgo_gc':
                /* record project, psp, oname
                 */
                $psp = 'ground-cherry';
                $oname = "Tall-bearing selection from ".(date('Y')-1)."";
                break;
            case 'cgo_tomato':
            case 'cgo_bean':
                /* record project, kLot, and psp just for good measure
                 */
                $psp = substr($projcode,4); // 'tomato' or 'bean'
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
// obsolete            $kfr->UrlParmSet('metadata', 'project', $sProjname);
            $kfr->SetValue('projcode', $projcode);
            $kfr->SetValue('year', $oP->CurrentYear());

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

        if( ($projcode = SEEDInput_Str('projcode')) &&
// oProfilesDB is obsolete as a named relation object - use oProfilesDB->oSLDB
            ($kfr = $oP->oProfilesDB->GetKFRCond('VI', "fk_mbr_contacts={$uid} AND year={$oP->CurrentYear()} AND projcode='{$projcode}'")) )
        {
            $kfr->StatusSet(KeyFrameRecord::STATUS_DELETED);
            if( $kfr->PutDBRow() ) {
                $rQ['bOk'] = true;
            }
        }
    }

// obsolete since 2025
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
    private $oProjectsUI;
    private $oW = null;

    function __construct( SEEDAppConsole $oApp )
    {
        global $consoleConfig;
        parent::__construct( $oApp->oC, $consoleConfig['TABSETS'] );

        $this->oApp = $oApp;
        $this->oProjects = new ProjectsCommon($this->oApp);
        $this->oProjectsUI = new ProjectsCommonUI($this->oProjects, $this);
    }

    function TabSetPermission( $tsid, $tabname )
    {
        switch($tabname) {
            case 'signup':
            case 'projects':
            case 'sites':
                return( Console02TabSet::PERM_SHOW );
            case 'office':
            case 'settings':
                return( $this->oProjects->CanReadOtherUsers() ? Console02TabSet::PERM_SHOW : Console02TabSet::PERM_HIDE );
        }
        return( Console02TabSet::PERM_HIDE );
    }

    function TabSetGetDefaultTab( string $tsid ) : string
    {
        /* Specify the default tab when not stored in the session vars (on a cold start).
         * Use signups if the logged-in user has no projects this year.
         * Use projects if they have projects this year.
         *
         * N.B. $this->oW==null at this point because this is called before any tab is drawn.
         */
        return( count($this->oProjects->oProfilesDB->GetVarInstNames($this->oApp->sess->GetUID(), $this->oProjects->CurrentYear())) ? "projects" : "signup" );
    }

    function TabSet_main_signup_Init()           { $this->oW = new ProjectsTabSignup($this->oProjects, $this->oProjectsUI); $this->oW->Init(); }
    function TabSet_main_signup_StyleDraw()      { return( $this->oW->StyleDraw() ); }

    function TabSet_main_projects_Init()         { $this->oW = new ProjectsTabProjects($this->oProjects, $this->oProjectsUI); $this->oW->Init(); }
    function TabSet_main_projects_StyleDraw()    { return( $this->oW->StyleDraw() ); }
//    function TabSet_main_projects_ControlDraw()  { return( $this->oW->ControlDraw() ); }
//    function TabSet_main_projects_ContentDraw()  { return( $this->oW->ContentDraw() ); }

    function TabSet_main_sites_Init()         { $this->oW = new ProjectsTabSites($this->oProjects, $this->oProjectsUI); $this->oW->Init(); }
//    function TabSet_main_sites_ControlDraw()  { return( $this->oW->ControlDraw() ); }
//    function TabSet_main_sites_ContentDraw()  { return( $this->oW->ContentDraw() ); }

    function TabSet_main_office_Init()          { $this->oW = new ProjectsTabOffice($this->oProjects, $this->oProjectsUI); $this->oW->Init(); }
//    function TabSet_main_office_ControlDraw()   { return( $this->oW->ControlDraw() ); }
//    function TabSet_main_office_ContentDraw()   { return( $this->oW->ContentDraw() ); }

    function TabSetControlDraw($tsid, $tabname) { return( $this->oW->ControlDraw() ); }
    function TabSetContentDraw($tsid, $tabname) { return( $this->oW->ContentDraw() ); }

//    function TabSet_main_settings_Init()         { $this->oW = new GrowoutsTabSettings($this->oGO); $this->oW->Init(); }
//    function TabSet_main_settings_ControlDraw()  { return( $this->oW->ControlDraw() ); }
//    function TabSet_main_settings_ContentDraw()  { return( $this->oW->ContentDraw() ); }
}

class ProjectsCommon
{
    const  BUCKET_NS = 'AppMyProjects';
    public $oApp;
    public $oProfilesDB;
    public $oMbr;
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

    private $currYear;
    private $kCurrMbr = 0;

    function __construct( SEEDAppConsole $oApp, array $raConfig = [] )
    {
        $this->oApp = $oApp;
        $this->oProfilesDB = new SLProfilesDB($oApp);
        $this->oMbr = new Mbr_Contacts($oApp);
        $this->oL = new SEED_Local( $this->sLocalStrs(),
                                    @$raConfig['lang'] ?: $this->oApp->lang,     // specify lang or use oApp's lang
                                    'myprojects' );

        $this->currYear = @$this->raConfig['currYear'] ?: date("Y", time()+3600*24*30 );  // year of 30 days from now (so Dec,Jan-Nov is same year as Jan)

        // default to current login - office staff can choose the current participant via FormSVA
        $this->kCurrMbr = $this->oApp->sess->GetUID();
    }

    /**
     * The current project year
     */
    function CurrentYear() : int  { return($this->currYear); }

    /**
     * The current participant
     */
    function KCurrMbr() : int     { return($this->kCurrMbr); }
    function SetKCurrMbr(int $k)  { $this->kCurrMbr = $k; }


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


class ProjectsCommonUI
{
    private $oP;
    public  $oCTS;

    function __construct(ProjectsCommon $oP, MyConsole02TabSet $oCTS )
    {
        $this->oP = $oP;
        $this->oCTS = $oCTS;
    }

    /**
     * Same ControlDraw for Signup, Projects, and Sites
     */
    function Participant_ControlDraw()
    {
        $s = "";

// put all this in a class and get kCurrMbr in Init()
        if( $this->oP->CanReadOtherUsers() ) {
// make a checkbox Show What Members See to turn off CanReadOtherUsers() -- except for that checkbox
            $y = 2024;

//            $oFormCurrentTab = new SEEDCoreFormSVA($this->oCTS->TabSetGetSVACurrentTab('main'), 'Plain');
            // use the same SVA for all tabs so values are the same across them (projects is an arbitrary choice)
            $oForm = new SEEDCoreFormSVA($this->oCTS->TabSetGetSVA('main','projects'), 'Plain');

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
            foreach( $this->oP->oProfilesDB->oSLDB->Get1List('VI', 'fk_mbr_contacts', "VI.year>=$y {$condWorkflow}") as $kMbr ) {
                $raOpts[$this->oP->oMbr->GetContactName($kMbr)." ($kMbr)"] = $kMbr;                     // uniquifies the list
            }
            ksort($raOpts);

            /* If member selected via search
             */
            if( $kMbrSearch ) {
                $name = $this->oP->oMbr->GetContactName($kMbrSearch);
                $raOpts["$name ($kMbrSearch)"] = $kMbrSearch;  // add to the dropdown (idempotent if it is already there)
                $this->oP->SetKCurrMbr($kMbrSearch);           // current in ui
            } else
            /* If member chosen from dropdown or recalled from oSVA.
             * If workflow changed, it's best to forget the kMbr state so the default reset() behaviour should happen instead.
             * Adding to dropdown for rare cases where kMbr already selected but no projects yet. e.g. search for member without project, click Add Project : won't be loaded into dropdown
             */
            if( !$bWorkflowChanged && ($kMbr = $oForm->ValueInt('kMbr')) ) {
                $name = $this->oP->oMbr->GetContactName($kMbr);
                $raOpts["$name ($kMbr)"] = $kMbr;           // add to the dropdown (idempotent if it is already there)
                $this->oP->SetKCurrMbr($kMbr);              // current in ui
            } else {
                $this->oP->SetKCurrMbr(reset($raOpts));     // default to first kMbr in the list
            }
            $oForm->SetValue('kMbr', $this->oP->KCurrMbr()); // make this participant persistent in oFormSVA

            $s .= "<div style='display:inline-block'>
                       <form method='post'>".$oForm->Select('kMbr', $raOpts, "", ['selected'=>$this->oP->KCurrMbr(), 'attrs'=>"onChange='submit();'"])
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
        $s .= "<div style='text-align:right'><h3 style='padding:0;margin:0;color:white'>{$this->oP->oMbr->GetContactName($this->oP->KCurrMbr())}</h3></div>";

        return( $s );
    }

    /**
     * Show participant's status and suggest verify/update contact info
     */
    function Participant_StatusAndRenewal()
    {
        $s = "";

        if($this->oP->KCurrMbr()) {
            $parms = $this->oP->oL->GetLang()=='EN'
                        ? ['sExtra_Current' => "<br/>We're glad to help at <a href='mailto:growers@seeds.ca'>growers@seeds.ca</a>.",
                           'sExtra_Expired' => "Then refresh this page and join our projects.<br/><br/>"]
                        : ['sExtra_Current' => "<br/>Nous sommes heureux de vous aider &agrave; <a href='mailto:growers@seeds.ca'>growers@seeds.ca</a>.",
                           'sExtra_Expired' => "Rafra&icirc;chissez ensuite cette page et rejoignez nos projets.<br/><br/>"];
            $parms['lang'] = $this->oP->oL->GetLang();

            $oMbrDraw = new MbrContactsDraw($this->oP->oApp);
            $sL = $oMbrDraw->DrawExpiryNotice($this->oP->KCurrMbr(), $parms );

            $sR = "<div style='border:1px solid #aaa;padding:1em;'>{$oMbrDraw->DrawAddressBlock($this->oP->KCurrMbr(), ['bShowEmail'=>true])}</div>";
            $s .= "<div class='container-fluid'><div class='row'>
                       <div class='col-md-6'>$sL</div>
                       <div class='col-md-3'>&nbsp;</div>
                       <div class='col-md-3'>$sR</div>
                   </div>";
        }

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
        let projcode = jForm.data('projcode');
        let iLot = 0;

        if(projcode=='cgo_tomato') {
            iLot = document.getElementById('cgosignup-form-tomatoselect').value;
        }
        if(projcode=='cgo_bean') {
            iLot = document.getElementById('cgosignup-form-beanselect').value;
        }

        let o = {qcmd:'myprojects--add',
                projcode: projcode,
                uid: CGOSignup_Uid,
                iLot: iLot};
        let rQ = SEEDJXAsync2("myprojects.php", o,
                     function (rQ) {
                         if( rQ['bOk'] ) {
                             jForm.find('.cgosignup-form-btn-container-notregistered').hide();
                             jForm.find('.cgosignup-form-btn-container-registered').show();
                         }
                     });

        //console.log(rQ);
    }

	static doUnregister(jThis)
    {
        let jForm = jThis.closest('.cgosignup-form');
        let projcode = jForm.data('projcode');

        let o = {qcmd:'myprojects--remove',
                projcode: projcode,
                uid: CGOSignup_Uid};
        let rQ = SEEDJXAsync2("myprojects.php", o,
                     function (rQ) {
                         if( rQ['bOk'] ) {
                             jForm.find('.cgosignup-form-btn-container-notregistered').show();
                             jForm.find('.cgosignup-form-btn-container-registered').hide();
                         }
                     });

    }

    static doChooseBean_ObsoleteSince2025(jThis)
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
