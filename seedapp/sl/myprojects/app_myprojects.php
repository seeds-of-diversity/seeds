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
                            'perms' =>[ 'projects' => ['PUBLIC'],           // anyone can login to see their own data
                                        'sites'    => ['PUBLIC'],
                                        'office'   => ['SL W'],
                                        //'settings'     => ['label'=>'Settings']
                                        '|'
                                      ],
                           ],
                 ],
    'pathToSite' => '../../',

    'consoleSkin' => 'green',
];


$oApp = SEEDConfig_NewAppConsole( ['sessPermsRequired' => $consoleConfig['TABSETS']['main']['perms'],
                                   'consoleConfig' => $consoleConfig] );
//$oApp->kfdb->SetDebug(1);

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

    function TabSet_main_projects_Init()         { $this->oW = new ProjectsTabProjects($this->oProjects, $this); $this->oW->Init(); }
    function TabSet_main_projects_ControlDraw()  { return( $this->oW->ControlDraw() ); }
    function TabSet_main_projects_ContentDraw()  { return( $this->oW->ContentDraw() ); }

    function TabSet_main_sites_Init()         { $this->oW = new ProjectsTabSites($this->oProjects); $this->oW->Init(); }
    function TabSet_main_sites_ControlDraw()  { return( $this->oW->ControlDraw() ); }
    function TabSet_main_sites_ContentDraw()  { return( $this->oW->ContentDraw() ); }

    function TabSet_main_office_Init()          { $this->oW = new ProjectsTabOffice($this->oProjects, $this); $this->oW->Init(); }
    function TabSet_main_office_Permission()    { return( $this->oProjects->CanReadOtherUsers() ? Console02TabSet::PERM_SHOW : Console02TabSet::PERM_HIDE ); }
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

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
    }

    function CanReadOtherUsers()
    {
        return( $this->oApp->sess->CanRead('SLProfileOtherUsers') );
    }

    function CanWriteOtherUsers()
    {
        return( $this->oApp->sess->CanWrite('SLProfileOtherUsers') );
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

            $s = "<form method='post'>".$oForm->Select('kMbr', $raOpts, "", ['selected'=>$this->kCurrMbr, 'attrs'=>"onChange='submit();'"])."</form>";
        }

        return( $s );
    }

    function ContentDraw()
    {
        $sLeft = $sRight = "";
        $year = 2024;

        $oMbr = new Mbr_Contacts($this->oP->oApp);
        $oSLDB = new SLDBProfile($this->oP->oApp);

        $oProfilesDB = new SLProfilesDB( $this->oP->oApp );
        $oProfilesDefs = new SLProfilesDefs( $oProfilesDB );
        $oProfilesReport = new SLProfilesReport( $oProfilesDB, $oProfilesDefs, $this->oP->oApp );

        if( ($u = intval($this->kCurrMbr)) ) {
            foreach( $oProfilesDB->GetVarInstNames($this->kCurrMbr, $year) as $ra ) {
                $sLeft .= "<p><a href='?vi={$ra['kVI']}'>{$ra['sp']} : {$ra['cv']}</a></p>";
            }
        }

        if( ($kVI = SEEDInput_Int('vi')) ) {
            $kfrVI = $oSLDB->GetKFR('VI', $kVI);

            list($psp,$sSp,$sCv) = $oProfilesDB->ComputeVarInstName($kfrVI);

            $sRight .= "<h2>$sSp : $sCv</h2>";

            // this should be in oCP too
            $oF = new SLProfilesForm( $oProfilesDB, $kVI );
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

        $s = "<div class='container-fluid'><div class='row'>
              <div class='col-md-3'><h4>$year projects for {$this->oMbr->GetContactName($this->kCurrMbr)}</h4>$sLeft</div>
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
