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

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;
        $this->oSrcLib = new SLSourcesLib( $this->oApp );
    }

    function Main()
    {
        $s = "<h3>SrcCV Edit Company</h3>"
            .$this->Style();

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
        $oForm = new SEEDCoreFormSession( $this->oApp->sess, 'SLSourcesEdit', 'A' );
        $oForm->Update();
        $this->kCompany = $oForm->Value('kCompany');
        $this->sCompanyName = "";

        $raSrc = $this->oSrcLib->oSrcDB->GetList( 'SRC', '_key>=3', ['sSortCol'=>'name_en'] );
        $raOpts = [ " -- Choose a Company -- " => 0 ];
        foreach( $raSrc as $ra ) {
            if( $this->kCompany && $this->kCompany == $ra['_key'] ) {
                $this->sCompanyName = $ra['name_en'];
            }
            $raOpts[$ra['name_en']] = $ra['_key'];
        }

        $s = "<div style='padding:1em'><form method='post' action=''>"
            .$oForm->Select( 'kCompany', $raOpts )
            ."<input type='submit' value='Choose'/>"
            ."</form></div>";

        return( $s );
    }

    private function drawDownloadUploadBox()
    {
        $s = "";

        $sUpload = "";

        // only allow upload of one company at a time
        if( $this->kCompany ) {
            $sUpload = "<p>Upload a spreadsheet of {$this->sCompanyName}</p>"
                      ."<form style='width:100%;text-align:left' action='{$_SERVER['PHP_SELF']}' method='post' enctype='multipart/form-data'>"
                      ."<div style='margin:0px auto;width:60%'>"
                      ."<input type='hidden' name='MAX_FILE_SIZE' value='10000000' />"
                      ."<input type='hidden' name='cmd' value='upload' />"
                      ."<input type='file' name='uploadfile'/><br/>"
                      ."<input type='submit' value='Upload'/>"
                      ."</div></form>";
        }

        $urlXLS = "https://seeds.ca/app/q/index.php?"     // use old Q until the new Q does this
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
        $s = "";

        $s .= "<form method='post' action=''>"
             ."<input style='float:right' type='submit' value='Save'/>"
             .$this->drawSeedTree( $this->kCompany, $raSeeds, $oFormB )
// Propagate the company of FormA so it retains state when this form is saved
//.$oFormA->Hidden('kCompany')
             ."<input style='float:right' type='submit' value='Save'/>"
             ."</form>";

        // The New buttons in the tree create new form entries dynamically. This tells the javascript which row number to use.
        $s .= "<script>slsrceditRowNum = ".$oFormB->GetRowNum().";</script>";

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

        $s .= "<h4>Add New Species</h4>
               <p>To add a species that isn't in the list above, enter it here with any cultivar name</p>
               <div class='slsrcedit_novelgroup'>
                 <div class='slsrcedit_novel'>
                   <input class='slsrcedit_novelsp' name='novelsp1' value=''/>
                   <input class='slsrcedit_novelcv' name='novelcv1' value=''/>
                   <div class='slsrcedit_cvBtns'>
                     <img class='slsrcedit_novelBtns_new' height='14' src='".W_ROOT."img/ctrl/new01.png'/>
                   </div>
                 </div>
               </div>";

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
            $cOrganic = $r['bOrganic'] ? "slsrcedit_cvorganic" : "";
            $cStripe = "slsrcedit_stripe".($i%2);
            $kSRCCV = $r['k'];
            $iRow = $oForm->GetRowNum();
            $bOrganic = $r['bOrganic'];
            $cvEsc = SEEDCore_HSC($r['cv']);

            $s .= "<div class='slsrcedit_cv $cStripe $cOrganic'
                          kSRCCV='$kSRCCV'
                          iRow='$iRow'
                          bOrganic='$bOrganic'>
                     <div class='slsrcedit_cvOrgBtn'></div>
                     <div class='slsrcedit_cvName'>$cvEsc</div>
                     <div class='slsrcedit_cvBtns'>
                       <img class='slsrcedit_cvBtns_new' height='14' src='".W_CORE_URL."img/ctrl/new01.png'/>
                       <img class='slsrcedit_cvBtns_edit' height='14' src='".W_CORE_URL."img/ctrl/edit01.png'/>
                       <img class='slsrcedit_cvBtns_del' height='14' src='".W_CORE_URL."img/ctrl/delete01.png'/>
                     </div>"
                     // if any change is requested, put the sfBk here and never remove it. No problem if it's issued when other ctrls don't exist.
                     ."<div class='slsrcedit_cvCtrlKey'></div>"
                     // put bOrganic hidden ctrl here if it the state has changed, so it is independent of other ctrls e.g. Edit
                     ."<div class='slsrcedit_cvCtrlOrg'></div>"
                     // if an edit/del is requested, put sfBp and sfBd ctrls here, and replace any previous content
                     ."<div class='slsrcedit_cvCtrls'></div>"
                 ."</div>";
            $i++;
            $oForm->IncRowNum();
        }

        $s .= "</div></div>";

        done:
        return( $s );
    }

    private function Style()
    {
        $s = "<style>
              .slsrcedit_spName  { display:inline-block; width:363px; font-family:verdana,helvetica,sans serif;font-size:10pt; font-weight:bold; }
              .slsrcedit_spBtns  { display:inline-block; margin-left:10px; }
              .slsrcedit_cvgroup { margin:0px 0px 10px 50px; }
              .slsrcedit_cvOrgBtn { display:inline-block; width:10px; height:10px; margin-right:3px; }
              .slsrcedit_cvName  { display:inline-block; width:300px; font-family:verdana,helvetica,sans serif;font-size:10pt; }

              /* OrgBtn and Name have different colours depending on their container's .slsrcedit_cvorganic
               */
              .slsrcedit_cvOrgBtn { background-color: #aaa; }
              .slsrcedit_cvorganic
                  .slsrcedit_cvOrgBtn { background-color: #ada; }
              .slsrcedit_cvorganic
                  .slsrcedit_cvName { color: green; background-color:#cec; }

              .slsrcedit_cvBtns  { display:inline-block; margin-left:10px; }
              .slsrcedit_cvCtrls { display:inline-block; width:50px; font-family:verdana,helvetica,sans serif;font-size:10pt; margin-left:10px; }
              .slsrcedit_cvCtrlKey  { display:inline-block; }
              .slsrcedit_cvCtrlOrg  { display:inline-block; }

              .slsrcedit_stripe1 { background-color:#f4f4f4; }
              .slsrcedit_stripe_new { background-color:#abf; }

              .slsrcedit_err { border:1px solid black;color:red;padding:10px; }
        </style>";

        $s .= <<<SLSrcEditScript
              <script>

              var slsrceditRowNum = 0;

              function SLSrcEdit_GetClosestDivCV( e )
              {
                  var div_sp = e.closest(".slsrcedit_sp");                      // from the clicked element, search up for the sp div
                  var div_cv = e.closest(".slsrcedit_cv");                      // from the clicked element, search up for the cv div
                  return( SLSrcEdit_GetDivCVDetails( div_sp, div_cv ) );
              }

              function SLSrcEdit_GetDivCVDetails( div_sp, div_cv )
              {
                  var o = { divCV        : div_cv,
                            divCVOrg     : div_cv.find(".slsrcedit_cvOrgBtn"),  // the button that changes bOrganic; also where we keep that <hidden>
                            divCVName    : div_cv.find(".slsrcedit_cvName"),    //   then down from there to the name
                            divCVCtrls   : div_cv.find(".slsrcedit_cvCtrls"),   //   and the div where input controls are written (except bOrganic)
                            divCVCtrlKey : div_cv.find(".slsrcedit_cvCtrlKey"), //   and the div where the sf key is written
                            divCVCtrlOrg : div_cv.find(".slsrcedit_cvCtrlOrg"), //   and the div where the sf bOrganic ctrl is written
                            kSRCCV       : div_cv.attr("kSRCCV"),               // the sl_cv_sources._key
                            iRow         : div_cv.attr("iRow"),                 // the oForm->iR
                            bOrganic     : div_cv.attr("bOrganic") == 1,        // the bOrganic state (changes when you click the bOrganic button)
                            osp          : div_sp.attr("osp")                   // the osp of this cv
                  };
                  o['ocv'] = o['divCVName'].html();
                  return( o );
              }

              function SLSrcEdit_SetCVKey( oDivCV )
              {
                  k = oDivCV['kSRCCV'];

                  oDivCV['divCVCtrlKey'].html( "<input type='hidden' name='sfBk"+oDivCV['iRow']+"' value='"+k+"'/>" );
              }

              function SLSrcEdit_ToggleOrganic( oDivCV )
              {
                  var newVal = (oDivCV['bOrganic'] ? 0 : 1);

                  if( oDivCV['bOrganic'] ) {
                      oDivCV['divCV'].removeClass( 'slsrcedit_cvorganic' );
                  } else {
                      oDivCV['divCV'].addClass( 'slsrcedit_cvorganic' );
                  }
                  oDivCV['divCV'].attr( 'bOrganic', newVal );
                  oDivCV['divCVCtrlOrg'].html( "<input type='hidden' name='sfBp"+oDivCV['iRow']+"_bOrganic' value='"+newVal+"' />" );
                  SLSrcEdit_SetCVKey( oDivCV );
              }

              function SLSrcEdit_AddNewRow( oDivCV, bBelow )
              {
                  //oDivCV['divCVName'].css( 'color', '#000' );
                  //oDivCV['divCVName'].css( 'text-decoration', 'none' );

                  var s = slsrceditNewCV;
                  s = s.replace( /%%i%%/g, slsrceditRowNum++ );    // global replace
                  s = s.replace( /%%osp%%/, oDivCV['osp'] );

                  var oNewCV = null;
                  if( bBelow ) {
                      oDivCV['divCV'].after( s );
                      oNewCV = oDivCV['divCV'].next();
                  } else {
                      oDivCV['divCV'].before( s );
                      oNewCV = oDivCV['divCV'].prev();
                  }

                  /* jQuery doesn't automatically connect handlers for new DOM elements, so connect them now.
                   */

                  // Connect the Organic button to the ToggleOrganic function.
                  $(oNewCV.find('.slsrcedit_cvOrgBtn')).click(function() {
                      var oDivCVNew = SLSrcEdit_GetClosestDivCV( $(this) );
                      SLSrcEdit_ToggleOrganic( oDivCVNew );
                  });
                  // Connect the New button so you can make yet another new row.
                  $(oNewCV.find('.slsrcedit_cvBtns_new')).click(function() {
                      var oDivCVNew = SLSrcEdit_GetClosestDivCV( $(this) );
                      SLSrcEdit_AddNewRow( oDivCVNew, true );
                  });
                  // Define a function for deleting the new row.
                  $(oNewCV.find('.slsrcedit_cvBtns_delnew')).click(function() {
                      var oDivCVNew = SLSrcEdit_GetClosestDivCV( $(this) );
                      oDivCVNew['divCV'].remove();
                  });

              }

              function SLSrcEdit_Edit( oDivCV )
              {
                  // Edit implies an Undo-delete
                  oDivCV['divCVName'].css( 'color', '#000' );
                  oDivCV['divCVName'].css( 'text-decoration', 'none' );

                  oDivCV['divCVCtrls'].html( "<input type='text' name='sfBp"+oDivCV['iRow']+"_ocv' value=\""+oDivCV['ocv']+"\"/>" );
                  SLSrcEdit_SetCVKey( oDivCV );
              }

              function SLSrcEdit_Delete( oDivCV )
              {
                  oDivCV['divCVName'].css( 'color', 'red' );
                  oDivCV['divCVName'].css( 'text-decoration', 'line-through' );

                  oDivCV['divCVCtrls'].html( "<input type='hidden' name='sfBd"+oDivCV['iRow']+"' value='1'/>" );
                  SLSrcEdit_SetCVKey( oDivCV );
              }

              function SLSrcEdit_DeleteKey( kDel )
              {
                  var oDivCV = SLSrcEdit_GetDivCVFromKey( kDel );
                  SLSrcEdit_Delete( oDivCV );
              }
              function SLSrcEdit_EditKey( kDel, sCultivar )
              {
                  var oDivCV = SLSrcEdit_GetDivCVFromKey( kDel );
                  if( sCultivar ) oDivCV['ocv'] = sCultivar;
                  SLSrcEdit_Edit( oDivCV );
              }

              function SLSrcEdit_GetDivCVFromKey( kSRCCV )
              {
                  var j = ".slsrcedit_cv[kSRCCV='"+kSRCCV+"']";
                  var div_cv = $(j);
                  var div_sp = div_cv.closest(".slsrcedit_sp");    // search up for the sp div
                  var oDivCV = SLSrcEdit_GetDivCVDetails( div_sp, div_cv );
                  return( oDivCV );
              }

              $(document).ready(function() {
                  /* Click on the cultivar name to cancel all changes
                   */
                  $(".slsrcedit_cvName").click(function() {
                      var oDivCV = SLSrcEdit_GetClosestDivCV( $(this) );

                      oDivCV['divCVName'].css( 'color', '#000' );
                      oDivCV['divCVName'].css( 'text-decoration', 'none' );

                      oDivCV['divCVCtrls'].html( "" );
                      oDivCV['divCVCtrlKey'].html( "" );
                      oDivCV['divCVCtrlOrg'].html( "" );
                  });

                  /* Click the New button to create a whole divCV with an empty text input field, and an empty kSrccv
                   */
                  $(".slsrcedit_cvBtns_new").click(function() {
                      var oDivCV = SLSrcEdit_GetClosestDivCV( $(this) );
                      SLSrcEdit_AddNewRow( oDivCV, true );
                  });
                  /* The New button beside the species name opens a new row above the first cv
                   */
                  $(".slsrcedit_spBtns_new").click(function() {
                      var div_sp = $(this).closest(".slsrcedit_sp");                      // from the clicked element, search up for the sp div
                      var div_cv = div_sp.find(".slsrcedit_cvgroup .slsrcedit_cv:first");

                      var oDivCV = SLSrcEdit_GetDivCVDetails( div_sp, div_cv );
                      SLSrcEdit_AddNewRow( oDivCV, false );
                  });

                  /* Click the delete button to delete a cultivar
                   */
                  $(".slsrcedit_cvBtns_del").click(function() {
                      var oDivCV = SLSrcEdit_GetClosestDivCV( $(this) );
                      SLSrcEdit_Delete( oDivCV );
                  });

                  /* Click the Edit button to create a text input field, initialize with the cultivar name and kSrccv
                   */
                  $(".slsrcedit_cvBtns_edit").click(function() {
                      var oDivCV = SLSrcEdit_GetClosestDivCV( $(this) );
                      SLSrcEdit_Edit( oDivCV );
                  });

                  /* Click the Org button while in N mode to change bOrganic to true
                   */
                  $(".slsrcedit_cvOrgBtn").click(function() {
                      var oDivCV = SLSrcEdit_GetClosestDivCV( $(this) );
                      SLSrcEdit_ToggleOrganic( oDivCV );
                  });

              });


              var wroot = "../../w/";
              var slsrceditNewCV =
                      "<div class='slsrcedit_cv slsrcedit_stripe_new' iRow='%%i%%' kSRCCV='0' bOrganic='0'>                \
                           <div class='slsrcedit_cvOrgBtn'></div>                                                          \
                           <div class='slsrcedit_cvName'>                                                                  \
                               <input type='hidden' name='sfBp%%i%%_osp' value='%%osp%%'/>                                 \
                               <input type='text' name='sfBp%%i%%_ocv' value=''/>                                          \
                           </div>                                                                                          \
                           <div class='slsrcedit_cvBtns' style='margin-left:1px'>                                          \
                               <img class='slsrcedit_cvBtns_new'    height='14' src='"+wroot+"img/ctrl/new01.png'/>        \
                               <img class='slsrcedit_cvBtns_delnew' height='14' src='"+wroot+"img/ctrl/delete01.png'>      \
                           </div>                                                                                          \
                           <div class='slsrcedit_cvCtrlKey'><input type='hidden' name='sfBk%%i%%' value='0'/></div>        \
                           <div class='slsrcedit_cvCtrlOrg'></div>                                                         \
                       </div>";

              </script>
SLSrcEditScript;

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

        $k1 = $this->oSVA->SmartGPC('srccv-edit-archive-k1');
        $k2 = $this->oSVA->SmartGPC('srccv-edit-archive-k2');

        $oForm = new SEEDCoreForm( 'Plain' );

        $s .= "<div><form>"
             .$oForm->Text( 'k1', 'Key 1 ', ['value'=>$k1] )
             .SEEDCore_NBSP( '     ')
             .$oForm->Text( 'k2', 'Key 2 ', ['value'=>$k2] )
             .SEEDCore_NBSP( '     ')
             ."<input type='submit'/>"
             ."</form></div>";

        foreach( [$k1,$k2] as $k ) {
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
