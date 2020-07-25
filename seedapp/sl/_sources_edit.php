<?php

/* _sources_edit.php
 *
 * Copyright 2016-2019 Seeds of Diversity Canada
 *
 * Implement the user interface for directly editing sl_cv_sources and sl_cv_sources_archive (for seed companies)
 */

include_once( SEEDCORE."SEEDCoreFormSession.php" );

class SLSourcesAppEdit
{
    private $oApp;
    private $oSVA;  // where to store state variables for this app
    private $oUIPills;

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;

        $raPills = [ 'srccv-edit-company' => array( "SrcCV Edit Company"),
                     'srccv-edit-archive' => array( "SrcCV Edit Archive"),
        ];

        $this->oUIPills = new SEEDUIWidgets_Pills( $raPills, 'pMode', array( 'oSVA' => $this->oSVA, 'ns' => '' ) );
    }

    function Draw()
    {
        $sMenu = $this->oUIPills->DrawPillsVertical();

        $sBody = "";
        switch( $this->oUIPills->GetCurrPill() ) {
            case 'srccv-edit-company':
                $o = new SLSourcesAppEditCompany( $this->oApp, $this->oSVA );
                $sBody = $o->Main();
                break;
            case 'srccv-edit-archive':
                $o = new SLSourcesAppEditArchive( $this->oApp, $this->oSVA );
                $sBody = $o->Main();
                break;
        }

        $s = "<div class='container-fluid'><div class='row'>"
                ."<div class='col-md-2'>$sMenu</div>"
                ."<div class='col-md-10'>$sBody</div>"
            ."</div></div>";

        return( $s );
    }
}

class SLSourcesAppEditCompany
{
    private $oApp;
    private $oSVA;  // where to store state variables for this app
    private $oSrcLib;

    private $kCompany = 0;  // current company selected
    private $sCompanyName = "";

    private $oTmpl;

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;
        $this->oSrcLib = new SLSourcesLib( $this->oApp );

        $raTmplParms = [
            'fTemplates' => [ SEEDAPP."templates/sl-srccv-edit.html" ],
            'sFormCid'   => 'Plain',
            // Tell Twig that the template and variables are cp1252.
            // This prevents messing up accents when Twig auto-escapes  {{cvName}} using HSC('utf8')
            'charset'    => 'iso-8859-1',
            //'raResolvers'=> array( array( 'fn'=>array($this,'ResolveTag'), 'raParms'=>array() ) ),
            'vars'       => ['W_CORE_URL'=>W_CORE_URL]      // this is not being found by ExpandTmpl's twig handler
        ];
        $this->oTmpl = SEEDTemplateMaker2( $raTmplParms );
    }

    function Main()
    {
        $s = "<h3>SrcCV Edit Company</h3>"
            .$this->oTmpl->ExpandTmpl( 'srccv-edit-style', [] )
            .$this->oTmpl->ExpandTmpl( 'srccv-edit-script', ['W_CORE_URL'=>W_CORE_URL] );  // would be nice for twig to know about vars defined in SEEDTemplateMaker

        $cmd = SEEDInput_Str('cmd');

        // Company Selector sets $this->kCompany and $this->sCompanyName
        $s .= $this->drawCompanySelector();
        // Download/Upload
        $s .= $this->drawDownloadUploadBox();
        if( !$this->kCompany ) {
            $s .= "<p style='width:60%'>Choose a company to edit. You can only edit one company at a time because the entire list of all companies' seeds "
                ."would be too long for the screen. However, you can download a complete list of all seeds by clicking on the link above.</p>";
            goto done;
        }

        $oFormB = new KeyframeForm( $this->oSrcLib->oSrcDB->KFRel('SRCCV'), 'B',
                                    ["DSParms" => ['fn_DSPreStore'=>[$this,'seedDSPreStore']]] );
        $oFormB->Update();
//$this->oSLDBSrc->kfdb->SetDebug(0);

        // Also process novelsp1/cv1
        if( ($sNovelSp = SEEDInput_Str('novelsp1')) &&
            ($sNovelCv = SEEDInput_Str('novelcv1')) )
        {
            $this->oSrcLib->AddSrcCV( ['osp'=>$sNovelSp, 'ocv'=>$sNovelCv, 'fk_sl_sources'=>$this->kCompany] );
        }


        // Get current seed list from db. If uploading a spreadsheet, overlay that on the seed list to see changes.
        $raSeeds = $this->oSrcLib->GetSrcCVListFromSource( $this->kCompany );
        if( $cmd == 'upload' ) {
            $raUploadedSeeds = $this->uploadSeeds();
            $raSeeds = $this->overlayUploadedSeeds( $raSeeds, $raUploadedSeeds );
        }

        $s .= $this->drawSeedEdit( $raSeeds, $oFormB );

// If there were changes uploaded from a file, write the JS directives here
$sUploadJS = "";
$s .= "<script>$sUploadJS</script>";

        done:
        return( $s );
    }

    private function drawCompanySelector()
    {
        $oForm = new SEEDCoreFormSVA( $this->oSVA->CreateChild('-edit-company'), 'A' );
        $oForm->Update();
        $this->kCompany = $oForm->Value('kCompany');
        $this->sCompanyName = "";

        list($s,$this->sCompanyName) = $this->oSrcLib->DrawCompanySelector( $oForm, 'kCompany' );

        return( $s );
    }

    private function drawDownloadUploadBox()
    {
        $s = "";

        $sUpload = "";

        // only allow upload of one company at a time
        if( $this->kCompany ) {
            $sUpload = "<p>Upload a spreadsheet of {$this->sCompanyName}</p>"
                      ."<form style='width:100%;text-align:left' action='".$this->oApp->PathToSelf()."' method='post' enctype='multipart/form-data'>"
                      ."<div style='margin:0px auto;width:60%'>"
                      ."<input type='hidden' name='MAX_FILE_SIZE' value='10000000' />"
                      ."<input type='hidden' name='cmd' value='upload' />"
                      ."<input type='file' name='uploadfile'/><br/>"
                      ."<input type='submit' value='Upload'/>"
                      ."</div></form>";
        }

        $urlXLS = "https://seeds.ca/app/q/q2.php?"     // use old Q until the new Q does this
               ."qcmd=srcCSCI"
               .($this->kCompany ? "&kSrc={$this->kCompany}" : "")
               ."&qname=".urlencode($this->kCompany ? $this->sCompanyName : "All Companies")
               ."&qfmt=xls";
        $s .= "<div style='display:inline-block;float:right;border:1px solid #999;border-radius:10px;"
                         ."margin-left:10px;padding:10px;background-color:#f0f0f0;text-align:center'>"
                ."<a href='$urlXLS' target='_blank' style='text-decoration:none'>"
                ."<img src='".W_CORE_URL."img/icons/xls.png' height='25'/>"
                .SEEDCore_NBSP("",5)."Download a spreadsheet of ".($this->kCompany ? $this->sCompanyName : "all companies")
                ."</a>"
                ."<hr style='width:85%;border:1px solid #aaa'/>"
                .$sUpload
            ."</div>";

        return( $s );
    }

    private function uploadSeeds()
    {
        return( [] );
    }

    private function overlayUploadedSeeds( $raSeedsDb, $raSeedsUploaded )
    {
        $raSeeds = $raSeedsDb;

        return( $raSeeds );
    }

    private function drawSeedEdit( $raSeeds, KeyframeForm $oFormB )
    {
        $sTree = $this->drawSeedTree( $this->kCompany, $raSeeds, $oFormB ); // to be safe with order of side-effects, do this here before GetRowNum

        $s = $this->oTmpl->ExpandTmpl( 'srccv-edit-seededitor', ['seedTree'=>$sTree,
                                                                 'nextRowNum'=>$oFormB->GetRowNum(),
                                                                 'W_CORE_URL'=>W_CORE_URL ] );  // try to get this in globals
        return( $s );
    }

    function seedDSPreStore( SEEDDataStore $oDS )
    {
        // Make sure every new row has a kCompany.
        // Not essential for edits because if (_key,ocv) is defined the existing kCompany in the db row will be unchanged.
        // It matters for new rows because the kCompany is not propagated in http for each row (it is always the same for every list).
        //
        // Also require osp && ocv
//var_dump($oDS->kfr->_values);
        if( !$this->kCompany )  return( false );
        if( $oDS->IsEmpty('osp') || $oDS->IsEmpty('ocv') )  return( false );

        if( $oDS->IsEmpty('fk_sl_sources') )  $oDS->SetValue( 'fk_sl_sources', $this->kCompany );

        // http input parms are utf8 but data is stored cp1252
        $oDS->SetValue( 'osp', SEEDCore_utf8_decode($oDS->Value('osp')) );
        $oDS->SetValue( 'ocv', SEEDCore_utf8_decode($oDS->Value('ocv')) );

        return( true );
    }

    private function drawSeedTree( $kCompany, $raSpCv, SEEDCoreForm $oForm )
    {
        $s = "";

        $prevSp = "";
        $raCV = array();

        foreach( $raSpCv as $ra1 ) {
            $sp = $ra1['SRCCV_osp'];
            $cv = $ra1['SRCCV_ocv'];
            if( $prevSp && $sp != $prevSp ) {
                $s .= $this->drawSeedTreeSection( $prevSp, $raCV, $oForm );
                $raCV = array();
            }
            $prevSp = $sp;
            $raCV[] = array( 'sp'=>$sp, 'cv'=>$cv, 'k'=>$ra1['SRCCV__key'], 'bOrganic'=>$ra1['SRCCV_bOrganic'] );
        }
        if( count($raCV) ) {
            $s .= $this->drawSeedTreeSection( $prevSp, $raCV, $oForm );
        }

        return( $s );
    }

    private function drawSeedTreeSection( $sp, $raCV, SEEDCoreForm $oForm )
    {
        $s = "";

        if( !count($raCV) )  goto done;

        /* <div class='slsrcedit_sp' slsrc_osp='osp'>
         *     <div class='slsrcedit_spName'> SPECIES NAME </div>
         *     <div class='slsrcedit_cvgroup'>
         *         <div class='slsrcedit_cv'
         *              kSRCCV='{k}'          the sl_cv_sources key (0 for a new row)
         *              iRow='{r}'            the sf iRow
         *              bOrganic='{b}'        stored here so we don't have to fill the http parm stream with these unless they change
         *             >
         *             <div class='slsrcedit_cvOrgBtn'></div>
         *             <div class='slsrcedit_cvName'> CULTIVAR NAME </div>
         *             <div class='slsrcedit_cvBtns'> New Edit Del buttons </div>
         *             <div class='slsrcedit_cvCtrlKey'> on any change, insert sfBk here and never remove it </div>
         *             <div class='slsrcedit_cvCtrlOrg'> on bOrganic toggle, insert sfBp_bOrganic with the new value </div>
         *             <div class='slsrcedit_cvCtrls'> sf input tags dynamically inserted here </div>
         *         </div>
         *     </div>
         * </div>
         *
         * New:        create a new row with hidden osp obtained from the slsrcedit_sp container
         * Edit:       insert key in cvCtrlKey, ocv text control in Ctrl area
         * Del toggle: insert key in cvCtrlKey; if Ctrl area contains op_del clear Ctrl; else insert op_del in Ctrl area (overwriting any Edit)
         * Org toggle: insert key in cvCtrlKey; insert bOrganic hidden tag in cvCtrlOrg.  This allows Edit and Org to coexist.
         */

        $spEsc = SEEDCore_HSC($sp);
        $s .= "<div class='slsrcedit_sp' osp='$spEsc'>"
                 ."<div class='slsrcedit_spName'>$spEsc</div>"
                 ."<div class='slsrcedit_spBtns'><img class='slsrcedit_spBtns_new' height='14' src='".W_CORE_URL."img/ctrl/new01.png'/></div>"
                 ."<div class='slsrcedit_cvgroup'>";
        $i = 1;
        foreach( $raCV as $r ) {
            $s .= $this->oTmpl->ExpandTmpl( 'srccv-edit-seededitor-row',
                                            ['W_CORE_URL'=>W_CORE_URL,  // try to get this in globals
                                             'kSRCCV' => $r['k'],
                                             'bOrganic' => $r['bOrganic'],
                                             'cvName' => $r['cv'],      // escaped naturally by twig
                                             'iStripe' => ($i%2),
                                             'iRow'    => $oForm->GetRowNum(),
                                            ] );
            $i++;
            $oForm->IncRowNum();
        }

        $s .= "</div></div>";

        done:
        return( $s );
    }
}


class SLSourcesAppEditArchive
{
    private $oApp;
    private $oSVA;  // where to store state variables for this app
    private $oSrcLib;

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;
        $this->oSrcLib = new SLSourcesLib( $this->oApp );
    }

    function Main()
    {
        $s = "<h3>SrcCV Edit Archive</h3>";

        if( !$this->oApp->sess->TestPermRA( ['W SLSrcArchive', 'A SLSources', 'A SL', '|'] ) ) {
            $s .= "<p>Editing the archive is not enabled</p>";
            goto done;
        }

        $oForm = new SEEDCoreFormSVA( $this->oSVA->CreateChild('-edit-archive'), 'Plain' );
        $oForm->Update();

        $s .= "<div><form>"
             .$oForm->Text( 'k1', 'Key 1 ' )
             .SEEDCore_NBSP('     ')
             .$oForm->Text( 'k2', 'Key 2 ' )
             .SEEDCore_NBSP('     ')
             ."<input type='submit'/>"
             ."</form></div>";

        foreach( [$oForm->Value('k1'),$oForm->Value('k2')] as $k ) {
            if( !$k ) continue;

            // using SRCCVxSRC instead of SRCCV_SRC because existence of SRC should be enforced in SRCTMP and integrity tests should validate SRCCVxSRC
            $ra = $this->oSrcLib->oSrcDB->GetRecordVals( 'SRCCVxSRC', $k );
            $s .= "<h4 style='margin-top:30px'>SrcCV for key $k</h4>"
                 ."<table>"
                 .SLSourcesLib::DrawSRCCVRow( $ra, '', ['no_key'=>true] )
                 ."</table>";

            $raRows = $this->oSrcLib->oSrcDB->GetList( 'SRCCVAxSRC', "SRCCVA.sl_cv_sources_key='$k'", ['sSortCol'=>'SRC_name_en,year,osp,ocv'] );
            if( count($raRows) ) {
                $s .= "<h4 style='margin-top:30px'>SrcCVArchive for key $k</h4>"
                     ."<table>";
                foreach( $raRows as $ra ) {
                    $s .= SLSourcesLib::DrawSRCCVRow( $ra, '', ['no_key'=>true] );
                }
                $s .= "</table>";
            }
            $s .= "<hr style='border:1px solid #aaa'/>";
        }

        done:
        return( $s );
    }
}
