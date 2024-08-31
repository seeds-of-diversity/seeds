<?php

/* mse-edit tabset for growers tab
 *
 * Copyright (c) 2018-2024 Seeds of Diversity
 *
 */

include_once(SEEDCORE."SEEDCoreFormSession.php");

class MSEEditAppTabGrower
/************************
    Tabset handler for MSE Edit Grower tab
 */
{
    protected $oApp;
    private   $oMEApp;
    protected $oMSDLib;
    protected $kfrGxM = null;

    private   $kGrower = 0;         // the current grower
    private   $bOffice = false;     // activate office features
    private   $raGrowerList = [];   // bOffice list of growers that match the filter controls
    private   $oGrowerForm;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oMEApp = new MSEEditApp($oApp);
        $this->oMSDLib = new MSDLib($oApp, ['sbdb'=>'seeds1']);     // should be given by caller?
        $this->oMSDLib->oL->AddStrs($this->sLocalStrs());
    }

    function Init_Grower( int $kGTmp )
    {
        list($this->bOffice, $this->kGrower) = $this->oMEApp->NormalizeParms($kGTmp, 'grower');     // this can change kGrower so don't use kGTmp below
        $bKGrowerIsMe = $this->kGrower == $this->oApp->sess->GetUID();

// Activate your seed list -- Done! should be Active (summary of seeds active, skipped, deleted)

        /* First execute a generic update so new http values are saved in the db.
         * Then look up GxM and place that into the form so the G_* and M_* values can be drawn.
         */
        $this->oGrowerForm = new MSDAppGrowerForm( $this->oMSDLib );
        $this->oGrowerForm->Update();

        if( !($this->kfrGxM = $this->oMSDLib->KFRelGxM()->GetRecordFromDB( "mbr_id='{$this->kGrower}'" )) ) {
            // create the Grower Record
            $tmpkfr = $this->oMSDLib->KFRelG()->CreateRecord();
            $tmpkfr->SetValue( 'mbr_id', $this->kGrower );
            $tmpkfr->SetVerbatim( 'tsGLogin', "NOW()" );    // need this to prevent insert error
            $tmpkfr->PutDBRow();
            $tmpErr = $tmpkfr->KFRel()->KFDB()->GetErrMsg();    // if the insert failed, here's the reason
            // now fetch with with the Member data joined
// this is not going to work if mbr_contacts record is not there.
// G_M would work although with blank M_*, but kfrGxM will be null
            if( !($this->kfrGxM = $this->oMSDLib->KFRelGxM()->GetRecordFromDB( "mbr_id='{$this->kGrower}'" )) ) {
                $this->oApp->Log( 'MSEEdit.log', "create grower {$this->kGrower} failed, probably mbr_contacts row doesn't exist : $tmpErr" );
                goto done;
            }
        }
        $this->oGrowerForm->SetKFR( $this->kfrGxM );

        $eOp = '';
        if( ($k = SEEDInput_Int( 'gdone' )) && $k == $this->kGrower ) {
//            $this->kfrGxM->SetValue( 'bDone', !$this->kfrGxM->value('bDone') );
//            $this->kfrGxM->SetValue( 'bDoneMbr', $this->kfrGxM->value('bDone') );  // make this match bDone
            $this->kfrGxM->SetValue( 'dDone', $this->oMSDLib->IsGrowerDone($this->kfrGxM) ? '' : date('Y-m-d') );
            $this->kfrGxM->SetValue( 'dDone_by', $this->oApp->sess->GetUID() );
            $eOp = 'gdone';
        }
        if( ($k = SEEDInput_Int( 'gskip' )) && $k == $this->kGrower ) {
            $this->kfrGxM->SetValue( 'bSkip', !$this->kfrGxM->value('bSkip') );
            $eOp = 'gskip';
        }
        if( ($k = SEEDInput_Int( 'gdelete' )) && $k == $this->kGrower ) {
            $this->kfrGxM->SetValue( 'bDelete', !$this->kfrGxM->value('bDelete') );
            $eOp = 'gdelete';
        }
        if( $eOp ) {
            if( $bKGrowerIsMe ) {
                $this->kfrGxM->SetValue( '_updated_G_mbr', date("Y-m-d") );  // record that you changed this in your own record
            }
            if( !$this->kfrGxM->PutDBRow( $bKGrowerIsMe ? [] : ['bNoChangeTS'=>true] ) ) {  // dDone,bSkip,bDelete shouldn't change _updated unless it's your own record
                $this->oApp->Log( 'MSEEdit.log', "$eOp {$this->kGrower} by user {$this->oApp->sess->GetUID()} failed: ".$this->kfrGxM->KFRel()->KFDB()->GetErrMsg() );
            }
        }

        // Kind of brute force to put this here - make sure seed counts and _updated_S* are up to date
        $this->oMSDLib->RecordGrowerStats($this->kfrGxM);   // stores record with bNoChangeTS

        done:;
    }

    function ControlDraw_Grower()
    {
        $s = "";

        // move this to StyleDraw_Grower() when this is drawn by Console02
        $s .= "
            <style>
            .mse-edit-grower-block      { border:1px solid #aaa; padding:5px; }
            .mse-edit-grower-block-done { color:green; background:#cdc; }
            </style>
        ";

        if( !$this->oMSDLib->PermOfficeW() )  goto done;

        $oForm = new SEEDCoreFormSVA($this->oApp->oC->oSVA, 'A');
        $oForm->Update();

        // check boxes that are checked
        $raChecked = [];
        foreach(['bDone','bSkip','bDel','bExpired','bNoChange','bZeroSeeds'] as $k ) {
            switch($oForm->Value($k)) {
                case 1: $raChecked[$k] = true;  break;     // only show growers with $k
                case 0: $raChecked[$k] = false; break;     // only show growers with !$k
                default:                                   // !isset means all growers
            }
        }
        // Get the list of growers that matches the controls. This array is used in ControlDraw too.
        $this->raGrowerList = $this->oMEApp->GetGrowerList($oForm->Value('sort'), $raChecked);
        $s .= "<div class='container-fluid'><div class='row'>
               <div class='col-md-5'>"
             .$this->oMEApp->MakeGrowerNamesSelect($this->raGrowerList, $this->kGrower, false)     // kluge to convert names to utf8, required for seeds tab but not growers tab
             ."</div>
               <div class='col-md-2'>
                   <form method='post'>"
                 .$oForm->Select('sort', ['First name'=>'firstname', 'Last name'=>'lastname', 'Mbr code'=>'mbrcode'], "Sort&nbsp;", ['attrs'=>"onchange='submit()'"])
                 ."</form>
               </div>
               <div class='col-md-2'>
                   <form method='post'>
                   <table><tr><td><b>Done<br/>Skipped&nbsp;</br>Deleted&nbsp;</b></td>
                     </td><td>"
                    .$oForm->Select('bDone', ['--'=>-1, 'Done'=>1,    'Not Done'=>0],    "", ['attrs'=>"onchange='submit()'"])."<br/>"
                    .$oForm->Select('bSkip', ['--'=>-1, 'Skipped'=>1, 'Not Skipped'=>0], "", ['attrs'=>"onchange='submit()'"])."<br/>"
                    .$oForm->Select('bDel',  ['--'=>-1, 'Deleted'=>1, 'Not Deleted'=>0], "", ['attrs'=>"onchange='submit()'"])."<br/>
                   </td></tr></table>
                   </form>
               </div>
               <div class='col-md-2'>
                   <form method='post'>
                   <table><tr><td><b>Member Expiry&nbsp;<br/>Changes by mbr&nbsp;</br>#Seeds</b></td>
                     </td><td>"
                    .$oForm->Select('bExpired',    ['--'=>-1, '< 2022'=>1, '>= 2022'=>0], "", ['attrs'=>"onchange='submit()'"])."<br/>"
                    .$oForm->Select('bNoChange',   ['--'=>-1, '< Aug 2023'=>1, '>= Aug 2023'=>0], "", ['attrs'=>"onchange='submit()'"])."<br/>"
                    .$oForm->Select('bZeroSeeds',  ['--'=>-1, 'Zero'=>1, 'Not Zero'=>0], "", ['attrs'=>"onchange='submit()'"])."<br/>
                   </td></tr></table>
                   </form>
               </div>
               </div></div>";

        done:
        return( $s );
    }

    function ContentDraw_Grower()
    {
        $sLeft = $sRight = "";

        if( !$this->kfrGxM ) goto done;

        if( !($this->kfrGxM->Value('M__key')) ) {
// also show this if zero seeds have been entered and this is their first year
            $sLeft .=
                  "<h4>Hello {$this->oApp->sess->GetName()}</h4>"
                 ."<p>This is your first time listing seeds in our Member Seed Exchange. "
                 ."Please fill in this form to register as a seed grower. <br/>"
                 ."After that, you will be able to enter the seeds that you want to offer to other Seeds of Diversity members.</p>"
                 ."<p>Thanks for sharing your seeds!</p>";
        }

        $sLeft .= "<h3>{$this->kfrGxM->Value('mbr_code')} : ".Mbr_Contacts::GetContactNameFromMbrRA($this->kfrGxM->ValuesRA(), ['fldPrefix'=>'M_'])."</h3>"
                ."<p>{$this->oMSDLib->oL->S('Grower block heading', [], 'mse-edit-app')}</p>"
                ."<div class='mse-edit-grower-block".($this->oMSDLib->IsGrowerDone($this->kfrGxM) ? ' mse-edit-grower-block-done' : '')."'>"
                .$this->oMSDLib->DrawGrowerBlock( $this->kfrGxM, true )
                ."</div><br/>"
                .($this->oMSDLib->IsGrowerDone($this->kfrGxM)
                     ? "<p style='font-size:16pt;margin-top:20px;'>Done! Thank you!</p>
                        <a href='{$this->oApp->PathToSelf()}?gdone={$this->kGrower}'>Click here if you're not really done</a>"
                     : "<form method='post'><input type='hidden' name='gdone' value='{$this->kGrower}'/>
                                            <div class='alert alert-danger'>
                                            <h3>Your seed listings are not active yet</h3>
                                            <p>Click here when you are ready (you can undo this)</p>
                                            <p><input type='submit' value='Ready for {$this->oMSDLib->GetCurrYear()}'/></p></div>
                        </form>")
                .($this->bOffice ? $this->drawGrowerOfficeSummary() : "");

        $sRight = "<div style='border:1px solid black; margin:10px; padding:10px'>"
                 .$this->oGrowerForm->DrawGrowerForm()
                 ."</div>";

        done:

        $s = "<div class='container-fluid'><div class='row'>"
            ."<div class='col-lg-6'>$sLeft</div>"
            ."<div class='col-lg-6'>$sRight</div>"
            ."</div></div>";

        if( $this->bOffice ) {
            $s = "<div class='container-fluid'><div class='row'>
                   <div class='col-md-3'>".$this->oMEApp->MakeGrowerNamesTable($this->raGrowerList, $this->kGrower, false)."</div><div class='col-md-9'>$s</div>
                   </div></div>";
        }

        return( $s );
    }

    private function drawGrowerOfficeSummary()
    {
        $kfrG = $this->kfrGxM;
        $kGrower = $kfrG->Value('mbr_id');

        $raD = [];

        $nSActive = $this->oApp->kfdb->Query1( "SELECT count(*) FROM {$this->oApp->DBName('seeds1')}.SEEDBasket_Products
                                                WHERE product_type='seeds' AND _status='0' AND
                                                      uid_seller='$kGrower' AND eStatus='ACTIVE'" );

        $dMbrExpiry = $this->oApp->kfdb->Query1( "SELECT expires FROM {$this->oApp->DBName('seeds2')}.mbr_contacts WHERE _key='$kGrower'" );
        $raD['dLastLogin'] = $this->oApp->kfdb->Query1( "SELECT left(MAX(_created),10) FROM {$this->oApp->DBName('seeds1')}.SEEDSession WHERE uid='$kGrower'" );

        $sSkip = $kfrG->Value('bSkip')
                    ? ("<div style='background-color:#ee9'><span style='font-size:12pt'>Skipped</span>"
                      ." <a href='{$_SERVER['PHP_SELF']}?gskip=$kGrower'>Unskip this grower</a></div>")
                    : ("<div><a href='{$_SERVER['PHP_SELF']}?gskip=$kGrower'>Skip this grower</a></div>");
        $sDel = $kfrG->Value('bDelete')
                    ? ("<div style='background-color:#fdf'><span style='font-size:12pt'>Deleted</span>"
                      ." <a href='{$_SERVER['PHP_SELF']}?gdelete=$kGrower'>UnDelete this grower</a></div>")
                    : ("<div><a href='{$_SERVER['PHP_SELF']}?gdelete=$kGrower'>Delete this grower</a></div>");

        // Grower record
        $raD['dGLogin']        = substr( $kfrG->Value('tsGLogin'), 0, 10 );            // latest read or write of this record by the member (can be '')
        $raD['dGUpdated']      = substr( $kfrG->Value('_updated'), 0, 10 );            // latest update of this record
        $raD['dGUpdatedByMbr'] = substr( $kfrG->Value('_updated_G_mbr'), 0, 10 );      // latest update of this record by the member (can be "")
        $kGUpdatedBy           = $kfrG->Value('_updated_by');

if($kfrG->Value('mbr_id')==$this->oApp->sess->GetUID()) {
    $raD['dGLogin'] = "_unavailable_";  // if this is you, the db field was set to NOW() above so the kfr value is blank
}
        // Seed records - includes INACTIVE and DELETED because we want to know if someone set those states
        $raD['dSUpdated']      = substr( $kfrG->Value('_updated_S'), 0, 10 );          // latest update of any seed records (can be "" if no seeds)
        $raD['dSUpdatedByMbr'] = substr( $kfrG->Value('_updated_S_mbr'), 0, 10 );      // latest update of seed records that were updated by the member (can be "")
        $kSUpdatedBy           = $kfrG->Value('_updated_S_by');                        // who made the latest update of any seed records

        // Done status
        $raD['dDone']          = substr( $kfrG->Value('dDone'), 0, 10 );
        $kDone                 = $kfrG->Value('dDone_by');

        foreach(['dGLogin','dLastLogin','dDone','dGUpdated','dGUpdatedByMbr','dSUpdated','dSUpdatedByMbr'] as $k ) {
            if( ($d = $raD[$k]) ) {
                // highlight dates that are within 120 days of today
                try {
                    if( (new DateTime())->diff(new DateTime($d))->days < 120 ) {
                        $raD[$k] = "<span style='color:green;background-color:#cdc'>$d</span>";
                    }
                } catch (Exception $e) {}
            } else {
                $raD[$k] = "?";
            }
        }
        $s = "<div style='border:1px solid black; margin:10px; padding:10px'>"
            ."<p>Seeds active: $nSActive</p>"
            ."<p>Membership expiry: $dMbrExpiry</p>"
            ."<p>Last mse edit login: {$raD['dGLogin']}   Last general login: {$raD['dLastLogin']}</p>"
            ."<p>Last grower record change: <b>{$raD['dGUpdatedByMbr']} by member</b>".($kGUpdatedBy <> $kGrower ? " ({$raD['dGUpdated']} by $kGUpdatedBy)" : "")."</p>"
            ."<p>Last seed record change: <b>{$raD['dSUpdatedByMbr']} by member</b>".($kSUpdatedBy <> $kGrower ? " ({$raD['dSUpdated']} by $kSUpdatedBy)" : "")."</p>"
            ."<p>Done: <b>{$raD['dDone']}</b> ".($kDone == $kGrower ? "<b>by member</b>" : "by $kDone")."</p>"
            .$sSkip
            .$sDel
            ."</div>";

        return( $s );
    }


    private function sLocalStrs()
    {
        return( ['ns'=>'mse-edit-app', 'strs'=> [
            'Grower block heading'
                => [ 'EN'=>"This is how your Grower Member profile will look to other members.",
                     'FR'=>"Ceci est ce qu'aura l'air votre adresse de membre cultivateur dans le Catalogue des semences." ]
        ]] );
    }
}



class MSDAppGrowerForm extends KeyframeForm
/*********************
    The SEEDForm for the sed_curr_grower record
 */
{
    private $oMSDLib;
    private $bOffice;
    private $oL;

    function __construct( MSDLib $oMSDLib )
    {
// calling app should create G record if not exist, and give this a valid kfrGxM
        $this->oMSDLib = $oMSDLib;
        $this->bOffice = $this->oMSDLib->PermOfficeW();     // activate office features if permission

        // add new strings to the shared SEEDLocal - beware that this is persistent so they might override strings that you expect to use later
        $this->oMSDLib->oL->AddStrs( $this->seedlocalStrs() );
$this->oL = $this->oMSDLib->oL;

        // do the right thing when these checkboxes are unchecked (http parms are absent, stored value is 1, so change stored value to 0)
        $fields = ['unlisted_phone' => ['control'=>'checkbox'],
                   'unlisted_email' => ['control'=>'checkbox'],
                   'organic'        => ['control'=>'checkbox'],
                   'pay_cash'       => ['control'=>'checkbox'],
                   'pay_cheque'     => ['control'=>'checkbox'],
                   'pay_stamps'     => ['control'=>'checkbox'],
                   'pay_ct'         => ['control'=>'checkbox'],
                   'pay_mo'         => ['control'=>'checkbox'],
                   'pay_etransfer'  => ['control'=>'checkbox'],
                   'pay_paypal'     => ['control'=>'checkbox'],
                   //'bDone'          => ['control'=>'checkbox']
                  ];

        // KFForm is created with KFRelG for purpose of form updates, but SetKGrower() can put a kfrGxM in the form for convenience
        parent::__construct( $this->oMSDLib->KFRelG(), 'G', ['fields'=>$fields, 'DSParms'=> ['fn_DSPreStore'=>[$this,'growerForm_DSPreStore']]] );
    }


    function growerForm_DSPreStore( $oDS )
    /*************************************
        Fix up the grower record before writing to db. Return true to proceed with the db write.
    */
    {
        if( !$this->bOffice ) {
            // regular users can only update their own listings
            if( $oDS->Value('mbr_id') != $this->oMSDLib->oApp->sess->GetUID() ) {
                die( "Cannot update grower information - mismatched grower code" );
            }

// *** Do this for seeds too
            // record when the member saved the record because office changes overwrite _updated
            $oDS->SetValue( '_updated_G_mbr', date("Y-m-d") );  // this really should be the new _updated but that's hard to get
        }

        if( !$oDS->Value('year') )  $oDS->SetValue( 'year', $this->oMSDLib->GetCurrYear() );

        $oDS->SetValue( 'bChanged', 1 );

        return( true );
    }

    function DrawGrowerForm()
    {
        $s = "
<style>
.msd_grower_edit_form       { padding:0px 1em; font-size:9pt; }
.msd_grower_edit_form td    { font-size:9pt; }
.msd_grower_edit_form input { font-size:8pt;}
.msd_grower_edit_form h3    { font-size:12pt; }
.msd_grower_edit_form input[type='submit'] { background-color:#07f;color:white;font-size:9pt;font-weight:bold; }
.msd_grower_edit_form .help { padding:0 10px;font-weight:bold; font-size:10pt; color:#07f; }
</style>
";

        $bNew = !$this->Value('mbr_id');  // only bOffice can instantiate this form with kGrower==0


//        $oForm = new KeyframeForm( $kfrGxM->KFRel(), "A" );
//        $oForm->SetKFR($kfrGxM);
        $oFE = new SEEDFormExpand( $this );

        $s .= "<div class='msd_grower_edit_form'>"
             ."<h3>".($bNew ? "Add a New Grower"
                            : $this->GetKFR()->Expand( "{$this->oL->S('Edit Grower')} [[mbr_code]] : [[M_firstname]] [[M_lastname]] [[M_company]]" ))."</h3>"

             .(!$bNew ? ("<div style='background-color:#ddd; margin-bottom:1em; padding:1em; font-size:9pt;'>{$this->oL->S('inform_office')}</div>") : "")

             ."<form method='post'>
               <div class='container-fluid'>
                   <div class='row'>
                       <div class='col-md-6'>"
                         .$oFE->ExpandForm(
                             "|||BOOTSTRAP_TABLE(class='col-md-4' | class='col-md-8')
                              ||| <input type='submit' value='{$this->oL->S('Save')}'/><br/><br/> || [[HiddenKey:]]
                              ||| *{$this->oL->S('Member #')}*        || ".($this->bOffice && $bNew ? "[[mbr_id]]" : "[[mbr_id | readonly]]" )."
                              ||| *{$this->oL->S('Member Code')}*     || ".($this->bOffice ? "[[mbr_code]]" : "[[mbr_code | readonly]]")."<span class='help SEEDPopover SPop_mbr_code'>?</span>
                              ||| *{$this->oL->S('Email unlisted')}*  || [[Checkbox:unlisted_email]]&nbsp;&nbsp; {$this->oL->S('do not publish')} <span class='help SEEDPopover SPop_unlisted'>?</span>
                              ||| *{$this->oL->S('Phone unlisted')}*  || [[Checkbox:unlisted_phone]]&nbsp;&nbsp; {$this->oL->S('do not publish')}
                              ||| *{$this->oL->S('Frost free days')}* || [[frostfree | size:5]]&nbsp;&nbsp; <span class='help SEEDPopover SPop_frost_free'>?</span>
                              ||| *{$this->oL->S('Organic')}*         || [[Checkbox: organic]]&nbsp;&nbsp; {$this->oL->S('organic_question')}  <span class='help SEEDPopover SPop_organic'>?</span>
                              ||| *Notes*                || &nbsp;
                              ||| {replaceWith class='col-md-12'} [[TextArea: notes | width:100% rows:10]]
                             " )
                     ."<div style='margin-top:10px;border:1px solid #aaa; padding:10px'>
                         <p><strong>{$this->oL->S('I accept seed requests')}:</strong></p>
                         <p>".$this->Radio('eDateRange', 'use_range')."&nbsp;&nbsp;{$this->oL->S('Between these dates')}</p>
                         <p style='margin-left:20px'>{$this->oL->S('dateRange_between_explain')}</p>
                         <p style='margin-left:20px'>".$this->Date('dDateRangeStart')."</p>
                         <p style='margin-left:20px'>".$this->Date('dDateRangeEnd')."</p>
                         <p>&nbsp;</p>
                         <p>".$this->Radio('eDateRange', 'all_year')."&nbsp;&nbsp;{$this->oL->S('All year round')}</p>
                         <p style='margin-left:20px'>{$this->oL->S('dateRange_allyear_explain')}</p>
                       </div>
                       </div>
                       <div class='col-md-6'>
                         <div style='border:1px solid #aaa; padding:10px'>
                         <p><strong>{$this->oL->S('I accept seed requests and payment')}:</strong></p>

                         <p>".$this->Radio('eReqClass', 'mail_email')."&nbsp;&nbsp;{$this->oL->S('By mail or email')}</p>
                         <ul>{$this->oL->S('payment_both_explain')}</ul>
                         <p>".$this->Radio('eReqClass', 'mail')."&nbsp;{$this->oL->S('By mail only')}</p>
                         <ul>{$this->oL->S('payment_mailonly_explain')}</ul>
                         <p>".$this->Radio('eReqClass', 'email')."&nbsp;{$this->oL->S('By email only')}</p>
                         <ul>{$this->oL->S('payment_emailonly_explain')}</ul>

                         <p><strong>{$this->oL->S('Payment Types Accepted')}</strong></p>
                         <p>".$this->Checkbox( 'pay_cash',      $this->oL->S('pay_cash'     ) ).SEEDCore_NBSP("",4)
                             .$this->Checkbox( 'pay_cheque',    $this->oL->S('pay_cheque'   ) ).SEEDCore_NBSP("",4)
                             .$this->Checkbox( 'pay_stamps',    $this->oL->S('pay_stamps'   ) )."<br/>"
                             .$this->Checkbox( 'pay_ct',        $this->oL->S('pay_ct'       ) ).SEEDCore_NBSP("",4)
                             .$this->Checkbox( 'pay_mo',        $this->oL->S('pay_mo'       ) )."<br/>"
                             .$this->Checkbox( 'pay_etransfer', $this->oL->S('pay_etransfer') ).SEEDCore_NBSP("",4)
                             .$this->Checkbox( 'pay_paypal',    $this->oL->S('pay_paypal'   ) )."<br/>"
                             .$this->Text( 'pay_other', $this->oL->S('pay_other',)." ", ['size'=> 30] )
                       ."</p>
                         </div>
                       </div>
                   </div>
               </div></form>
               </div>";

/*
        $s .= "<TABLE border='0'>";
        $nSize = 30;
        $raTxtParms = array('size'=>$nSize);
        if( $bNew ) {
            $s .= $bOffice ? ("<TR>".$oKForm->TextTD( 'mbr_id', "Member #", $raTxtParms  )."</TR>")
                           : ("<TR><td>Member #</td><td>".$oKForm->Value('mbr_id')."</td></tr>" );
        }
        //if( $this->sess->CanAdmin('sed') ) {  // Only administrators can change a grower's code
        if( $this->bOffice ) {  // Only the office application can change a grower's code
            $s .= "<TR>".$oKForm->TextTD( 'mbr_code', "Member Code", $raTxtParms )."</TR>";
        }
        $s .= "<TR>".$oKForm->CheckboxTD( 'unlisted_phone', "Phone", array('sRightTail'=>" do not publish" ) )."</TR>"
             ."<TR>".$oKForm->CheckboxTD( 'unlisted_email', "Email", array('sRightTail'=>" do not publish" ) )."</TR>"
             ."<TR>".$oKForm->TextTD( 'frostfree', "Frost free", $raTxtParms )."<TD></TD></TR>"
             ."<TR>".$oKForm->TextTD( 'soiltype', "Soil type", $raTxtParms )."<TD></TD></TR>"
             ."<TR>".$oKForm->CheckboxTD( 'organic', "Organic" )."</TR>"
             ."<TR>".$oKForm->TextTD( 'zone', "Zone", $raTxtParms )."</TR>"
             ."<TR>".$oKForm->TextTD( 'cutoff', "Cutoff", $raTxtParms )."</TR>"

             ."</TD></TR>"
             ."<TR>".$oKForm->TextAreaTD( 'notes', "Notes", 35, 8, array( 'attrs'=>"wrap='soft'"))."</TD></TR>"
             //."<TR>".$oKForm->CheckboxTD( 'bDone', "This Grower is Done:" )."</TR>"
             ."</TABLE>"
             ."<BR><INPUT type=submit value='Save' />"
             ;

*/

        return( $s );
    }


    private function seedlocalStrs()
    {
        $raStrs = [ 'ns'=>'mse', 'strs'=> [
            'Edit Grower'               => ['EN'=>"[[]]", 'FR'=>"Modifier producteur"],
            'Member #'                  => ['EN'=>"[[]]", 'FR'=>"<nobr>No de membre</nobr>"],
            'Member Code'               => ['EN'=>"[[]]", 'FR'=>"<nobr>Code de membre</nobr>"],
            'Email unlisted'            => ['EN'=>"[[]]", 'FR'=>"Courriel confidentiel"],
            'Phone unlisted'            => ['EN'=>"[[]]", 'FR'=>"T&eacute;l&eacute;phone confidentiel"],
            'do not publish'            => ['EN'=>"[[]]", 'FR'=>"ne pas publier"],
            'Frost free days'           => ['EN'=>"[[]]", 'FR'=>"Jours sans gel"],
            'Organic'                   => ['EN'=>"[[]]", 'FR'=>"Biologique"],
            'organic_question'          => ['EN'=>"are your seeds organically grown?", 'FR'=>"Vos semences sont-elles de culture biologique?"],

            'I accept seed requests'    => ['EN'=>"[[]]", 'FR'=>"J'accepte les demandes"],
            'Between these dates'       => ['EN'=>"[[]]", 'FR'=>"Entre ces dates"],
            'dateRange_between_explain' => ['EN'=>"Members will not be able to make online requests outside of this period. Our default is January 1 to May 31.",
                                            'FR'=>"Les demandes en ligne peuvent se faire seulement dans cette p&eacute;riode (par d&eacute;faut: 1 janvier-31 mai)."],
            'All year round'            => ['EN'=>"[[]]", 'FR'=>"Toute l'ann&eacute;e"],
            'dateRange_allyear_explain' => ['EN'=>"Members will be able to request your seeds at any time of year.",
                                            'FR'=>"Les demandes peuvent se faire en tout temps."],

            'I accept seed requests and payment'
                                        => ['EN'=>"[[]]", 'FR'=>"J'accepte les demandes de semences et le paiement"],
            'By mail or email'          => ['EN'=>"[[]]", 'FR'=>"Par la poste ou par courriel"],
            'payment_both_explain'      => ['EN'=>"<li>Members will see your mailing address and email address.</li>
                                                   <li>You will receive seed requests in the mail and by email.</li>
                                                   <li>Members will be prompted to send payment as you specify below.</li>",
                                            'FR'=>"<li>Les membres verront votre adresse postale et votre courriel.</li>
                                                   <li>Vous recevrez les demandes par la poste et par courriel.</li>
                                                   <li>On demandera aux membres de payer selon le mode que vous indiquerez ci-dessous.</li>"],
            'By mail only'              => ['EN'=>"[[]]", 'FR'=>"Par la poste seulement"],
            'payment_mailonly_explain'  => ['EN'=>"<li>Members will see your mailing address.</li>
                                                   <li>You will receive seed requests by mail only.</li>
                                                   <li>Members will be prompted to send payment as you specify below.</li>",
                                            'FR'=>"<li>Les membres verront votre adresse postale.</li>
                                                   <li>Vous recevrez les demandes par la poste seulement.</li>
                                                   <li>On demandera aux membres de payer selon le mode que vous indiquerez ci-dessous.</li>"],
            'By email only'             => ['EN'=>"[[]]", 'FR'=>"Par courriel seulement"],
            'payment_emailonly_explain' => ['EN'=>"<li>Members will not see your mailing address.</li>
                                                   <li>You will receive seed requests by email only.</li>
                                                   <li>Members will be prompted to send payment as you specify below (e-transfer and/or Paypal only).</li>",
                                            'FR'=>"<li>Les membres ne verront pas votre adresse postale.</li>
                                                   <li>Vous recevrez les demandes par courriel seulement.</li>
                                                   <li>On demandera aux membres de payer selon le mode que vous indiquerez ci-dessous (t&eacute;l&eacute;virement ou PayPal seulement)</li>."],

            'Payment Types Accepted'    => ['EN'=>"[[]]", 'FR'=>"Modes de paiement accept&eacute;s"],

            'Save'                      => ['EN'=>"[[]]", 'FR'=>"Enregistrer"],
            'inform_office'             => ['EN'=>"If your name, address, phone number, or email have changed, please notify our office",
                                            'FR'=>"Veuillez nous informer de tout changement &agrave; vos nom, adresse, num&eacute;ro de t&eacute;l&eacute;phone ou courriel"],
        ]];

        return( $raStrs );
    }
}

