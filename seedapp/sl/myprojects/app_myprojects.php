<?php

/* MyProjects app
 *
 * Copyright (c) 2024-2025 Seeds of Diversity Canada
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
            case 'cgo2025gc':
                /* record project, psp, oname
                 */
                $psp = 'ground-cherry';
                $oname = "Tall-bearing selection from 2024";
                break;
            case 'cgo2025tomato':
                /* record project, kLot, and psp just for good measure
                 */
                $psp = 'tomato';
                if( ($iLot = SEEDInput_Int('iLot')) ) {
                    $kLot = (new SLDBCollection($oApp))->GetRecordVal1Cond('I', "fk_sl_collection='1' AND inv_number='$iLot'", '_key');
                }
                if(!$kLot)  goto skip;
                break;
            case 'cgo2025bean':
                /* record project, psp, oname
                 */
                $psp = 'bean';
                $oname = '[To be chosen]';
                break;
            default:
                $rQ['sErr'] = "no project";
                goto skip;
        }

        if( ($kfr = $oP->oProfilesDB->Kfrel('VI')->CreateRecord()) ) {
            $kfr->SetValue('fk_mbr_contacts', $uid);
            $kfr->SetValue('psp', $psp);
            $kfr->SetValue('oname', $oname);
            $kfr->SetValue('fk_sl_inventory', $kLot);
            $kfr->UrlParmSet('metadata', 'project', $sProjname);
            $kfr->SetValue('year', 2025);

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
            ($kfr = $oP->oProfilesDB->GetKFRCond('VI', "fk_mbr_contacts={$uid} && metadata LIKE '%project=".addslashes($sProjname)."%'")) )
        {
            $kfr->StatusSet(KeyFrameRecord::STATUS_DELETED);
            if( $kfr->PutDBRow() ) {
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
    private $kCurrMbr;

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
    }

    function ControlDraw()
    {
        $s = "";

        if( $this->oP->CanReadOtherUsers() ) {
            $y = 2024;
            $raOpts = [];
            foreach( $this->oSLDB->Get1List('VI', 'fk_mbr_contacts', "VI.year>=$y") as $kMbr ) {
                $raOpts[$this->oMbr->GetContactName($kMbr)." ($kMbr)"] = $kMbr;
            }
            ksort($raOpts);

            $oForm = new SEEDCoreFormSVA($this->oCTS->TabSetGetSVACurrentTab('main'), 'Plain');
            $oForm->Update();
            if( !($this->kCurrMbr = $oForm->Value('kMbr')) ) {
                // if curr mbr not stored in session, initialize to the first in the dropdown
                $this->kCurrMbr = reset($raOpts);   // returns the first value
            }

            $s .= "<div style='float:left'><form method='post'>".$oForm->Select('kMbr', $raOpts, "", ['selected'=>$this->kCurrMbr, 'attrs'=>"onChange='submit();'"])."</form></div>";
        }

        // show the kCurrMbr's name on the right
        $s .= "<div style='text-align:right'><h3 style='padding:0;margin:0;color:white'>{$this->oMbr->GetContactName($this->kCurrMbr)}</h3></div>";

        return( $s );
    }

    function ContentDraw()
    {
        $s = "";

        $oMbr = new Mbr_Contacts($this->oP->oApp);
        $oSLDB = new SLDBProfile($this->oP->oApp);

        $oProfilesDefs = new SLProfilesDefs( $this->oP->oProfilesDB );
        $oProfilesReport = new SLProfilesReport( $this->oP->oProfilesDB, $oProfilesDefs, $this->oP->oApp );

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
            $sR = "<div style='border:1px solid #aaa;padding:1em;'>{$oMbr->DrawAddressBlock($this->kCurrMbr)}</div>";
            $s .= "<div class='container-fluid'><div class='row'>
                       <div class='col-md-6'>$sL</div>
                       <div class='col-md-3'>&nbsp;</div>
                       <div class='col-md-3'>$sR</div>
                   </div>";
        }


        /* 2025 projects (just to show them at the top, not functional like they are at the bottom).
         */
        $year = 2025;
        $sY = "";
        if( ($u = intval($this->kCurrMbr)) ) {
            foreach( $this->oP->oProfilesDB->GetVarInstNames($u, $year) as $ra ) {
                $sY .= "<p>{$ra['sp']} : {$ra['cv']}</p>";
            }
            if($sY) $s .= "<h4>$year projects for {$this->oMbr->GetContactName($u)}</h4>".$sY."<br/>";
        }

        /* CGO Signups
         */
        include("cgo_signup.php");

        $bRegisteredGC     = $this->oP->oProfilesDB->GetCount('VI', "fk_mbr_contacts={$this->kCurrMbr} AND metadata LIKE '%project=cgo2025gc%'");
        $bRegisteredTomato = $this->oP->oProfilesDB->GetCount('VI', "fk_mbr_contacts={$this->kCurrMbr} AND metadata LIKE '%project=cgo2025tomato%'");
        $bRegisteredBean   = $this->oP->oProfilesDB->GetCount('VI', "fk_mbr_contacts={$this->kCurrMbr} AND metadata LIKE '%project=cgo2025bean%'");

        $s .= "<h4 class='alert alert-success' style='color:green'>We have lots of seeds left so we've extended the deadline!</h4>";

        $s .= "<h3>{$this->oP->oL->S('Join Our Community Seed Growouts')}</h3>";
        $s .= (new CGOSignup_GC($this->oP))->Draw($bRegisteredGC)
             ."<br/><br/>"
             .(new CGOSignup_Tomato($this->oP))->Draw($bRegisteredTomato)
             ."<br/><br/>"
             .(new CGOSignup_Bean($this->oP))->Draw($bRegisteredBean);

        /* For Office mode, tell cgosignup the uid to sign up
         */
        $s .= "<script>var CGOSignup_Uid=".($this->oP->CanReadOtherUsers() ? $this->kCurrMbr : 0).";</script>";

        $s .= "<hr/>";

        /* Show projects
         */
        $sLeft = $sRight = "";


        /* 2025 and 2024 projects
         */
        if( ($u = intval($this->kCurrMbr)) ) {
            $raY = [];
            foreach([2025,2024] as $year) {
                if(!isset($raY[$year])) {
                    $sLeft .= "<h4>$year projects for {$this->oMbr->GetContactName($u)}</h4>";
                    $raY[$year] = 1;
                }
                foreach( $this->oP->oProfilesDB->GetVarInstNames($u, $year) as $ra ) {
                    $sLeft .= "<p><a href='?vi={$ra['kVI']}'>{$ra['sp']} : {$ra['cv']}</a></p>";
                }
            }
        }


        if( ($kVI = SEEDInput_Int('vi')) ) {
            $kfrVI = $oSLDB->GetKFR('VI', $kVI);

            list($psp,$sSp,$sCv) = $this->oP->oProfilesDB->ComputeVarInstName($kfrVI);

            $sRight .= "<h2>$sSp : $sCv</h2>";

            // this should be in oCP too
            $oF = new SLProfilesForm( $this->oP->oProfilesDB, $kVI );
            $oF->Update();  // record the sl_desc_obs returned from the form

            $oUI = new SEEDUI();
            $oComp = new SEEDUIComponent( $oUI );
// require $this->kCurrMbr==$this->oApp->sess->GetUID() || $this->oP->CanWriteOtherUsers()
            $oComp->Update();
            if( $kVI ) $oComp->Set_kCurr( $kVI );   // initialize the list to the right row e.g. if we just created a new row


            if( SEEDInput_Int('doForm') ) {
                // Show the form
                $oChooseForm = new SEEDCoreForm('Plain');
                $oChooseForm->Update();
                if( !$oChooseForm->Value('chooseForm') )  $oChooseForm->SetValue('chooseForm', 'cgo');

                $sRight  .= "<div style='float:right'><form method='post'>"
                           ."<p><b>Choose Your Form</b></p>"
                           .$oChooseForm->Select('chooseForm',
                                   ["Trial performance form for Community Grow-outs" => 'cgo',
                                    "Shortened descriptive form"                     => 'short',
                                    "Full taxonomic form"                            => 'long'],
                                   "",
                                   ['attrs'=>"onchange='submit()'"] )
                           ."<input type='hidden' name='doForm' value='1'/>
                             <input type='hidden' name='vi' value='{$kVI}'/>
                             </form></div>"

                            ."<h3>Edit Record for $sSp : $sCv</h3>" // (#$kVI)</h3>
                           .$oProfilesReport->DrawVIForm( $kVI, $oComp, $oChooseForm->Value('chooseForm') );
            } else {
                // Show the summary
                $sRight  .= "<div style='border-left:1px solid #ddd;border-bottom:1px solid #ddd'>"
                           ."<div style='float:left;margin-right:20px;'>"
                               ."<form method='post'>"
                                   //.$oComp->HiddenFormUIParms( array('kCurr', 'sortup', 'sortdown') )
                                   //.$oComp->HiddenKCurr()
                                   ."<input type='hidden' name='doForm' value='1'/>"
                                   ."<input type='hidden' name='vi' value='{$kVI}'/>"
                                   ."<input type='submit' value='Edit'/>"
                               ."</form>"
                           ."</div>"
                           //."<h3>Record #$kVI</h3>"
                           .$oProfilesReport->DrawVIRecord( $kVI, true )
                           ."</div>";
            }

        }

        $s .= "<div class='container-fluid'><div class='row'>
              <div class='col-md-3'>$sLeft</div>
              <div class='col-md-9'>$sRight</div>
              </div></div>";

        return( $s );
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
               <p>".$oForm->Select('year', ['2025'=>2025, '2024'=>2024])."</p>
               <p>".$oForm->Checkbox('all', "All")."</p>
               <p>".$oForm->Checkbox('ground-cherry', "Ground cherry")."</p>
               <p>".$oForm->Checkbox('tomato', "Tomato")."</p>
               <p>".$oForm->Checkbox('bean', "Bean")."</p>
               <p><input type='submit' value='Show'/></p>
               </form></div>
               <div style='display:inline-block;vertical-align:top;padding-left:1em'>
                   <a href='?xlsx=1' target='_blank'><img src='https://seeds.ca/w/std/img/dr/xls.png' style='height:30px'/></a>
               </div>";

        $bShow = false;
        $raProj = [];
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

        $sCond = "year='{$oForm->ValueDB('year')}'";
        if( $raProj )  $sCond .= " AND psp in ('".implode("','", $raProj)."')";

        $raMbr = [];
        foreach( $this->oSLDB->GetList('VI', $sCond) as $raVI ) {
            $kMbr = $raVI['fk_mbr_contacts'];

            if( !isset($raMbr[$kMbr]) ) {
                $ra = $this->oMbr->oDB->GetRecordVals('M', $kMbr);
                $raMbr[$kMbr] = ['member_name' => $this->oMbr->GetContactNameFromMbrRA($ra),
                                 'member_email'=> $ra['email'],
                                 'ground-cherry' => '',
                                 'tomato' => '',
                                 'bean' => '',
                ];
            }

            switch( $raVI['psp'] ) {
                case 'ground-cherry':
                    $raMbr[$kMbr]['ground-cherry'] = 1;
                    break;
                case 'tomato':
                    if( $raVI['fk_sl_inventory'] && ($kfrLot = $this->oSLDB->GetKFR('IxAxP', $raVI['fk_sl_inventory'])) ) {
                        $raMbr[$kMbr]['tomato'] = $kfrLot->Value('P_name');
                    }
                    break;
                case 'bean':
                    $raMbr[$kMbr]['bean'] = 1;
                    break;
            }
        }

        if( SEEDInput_Int('xlsx') ) {
            // output as a spreadsheet
            include_once( SEEDCORE."SEEDXLSX.php" );

            $title = "Seeds of Diversity Projects {$oForm->Value('year')}";
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
        return( $s );
    }
}




$oCTS = new MyConsole02TabSet( $oApp );
$s = $oApp->oC->DrawConsole( "[[TabSet:main]]", ['oTabSet'=>$oCTS] );

echo Console02Static::HTMLPage( SEEDCore_utf8_encode($s), "", 'EN',
                                ['consoleSkin'=>'green',
                                'raScriptFiles' => [$oApp->UrlW()."js/SEEDCore.js"] ] );
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

        if(projName=='cgo2025tomato') {
            iLot = document.getElementById('cgosignup-form-tomatoselect').value;
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
        $('#cgosignup-form-beanbutton').prop('disabled', !(r1 && r2));
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
