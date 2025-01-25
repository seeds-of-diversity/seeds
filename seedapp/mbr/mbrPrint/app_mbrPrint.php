<?php

/* mbrPrint
 *
 * Copyright 2020-2024 Seeds of Diversity Canada
 *
 * App that prints membership and donation slips, and donation receipts
 */

include_once( SEEDROOT."Keyframe/KeyframeUI.php" );
include_once( SEEDCORE."SEEDPrint.php" );
include_once( SEEDCORE."SEEDCoreFormSession.php" );
include_once( SEEDAPP."mbr/mbrApp.php" );
include_once( SEEDCORE."console/console02.php" );
include_once( SEEDLIB."mbr/MbrContacts.php" );
include_once( SEEDLIB."mbr/MbrDonations.php" );

//list($kfdb,$sess,$lang) = SiteStartSessionAccount( array( 'R MBR' ) );

$yCurr = SEEDInput_Int('year') ?: date('Y');

$consoleConfig = [
    'CONSOLE_NAME' => "mbrPrint",
    'HEADER' => "Memberships and Donations",
    //'HEADER_LINKS' => array( array( 'href' => 'mbr_email.php',    'label' => "Email Lists",  'target' => '_blank' ),
    //    array( 'href' => 'mbr_mailsend.php', 'label' => "Send 'READY'", 'target' => '_blank' ) ),
    'TABSETS' => ['main'=> ['tabs' => [ 'renewalRequests'  => ['label'=>'Renewal Notices'],
                                        'donationRequests' => ['label'=>'Donation Requests'],
                                        'donationReceipts' => ['label'=>'Donation Receipts'],
                                        'donationReceipts2' => ['label'=>'Donation Receipts2'],
                                        'donations'        => ['label'=>'Donations'],
                                        'donationsSL'      => ['label'=>'Seed Library Adoptions'],
                                        'admin'            => ['label'=>'Admin'],
                                      ],
                            // this doubles as sessPermsRequired and console::TabSetPermissions
                            'perms' => MbrApp::$raAppPerms['mbrPrint'],
                           ],
                 ],
    'urlLogin'=>'../login/',

    'consoleSkin' => 'green',
];

$oApp = SEEDConfig_NewAppConsole( ['db'=>'seeds2',
    'sessPermsRequired' => $consoleConfig['TABSETS']['main']['perms'],
    'consoleConfig' => $consoleConfig] );

$kfdb = $oApp->kfdb;
$sess = $oApp->sess;

SEEDPRG();


if( SEEDInput_Str('cmd') == 'printDonationReceipt' || SEEDInput_Str('cmd') == 'printDonationReceipt2' ) {
    if( ($rngReceipt = SEEDInput_Str('donorReceiptRange')) ) {
        list($sHead,$sBody) = (new MbrDonations($oApp))->DrawDonationReceipt( $rngReceipt, SEEDInput_Str('cmd') == 'printDonationReceipt' ? 'HTML' : 'PDF_STREAM', false ); // don't record
        // HTML returns with these vars; PDF_STREAM does not return
        echo Console02Static::HTMLPage( SEEDCore_utf8_encode($sBody), $sHead, 'EN', ['bBootstrap'=>false] );   // sCharset defaults to utf8
        exit;
    } else {
        $oApp->oC->AddErrMsg( 'Enter a receipt number' );
    }
}


$o3UpDonors = new Mbr3UpDonors( $oApp, $yCurr, SEEDInput_Int('dLargeDonation') ?: 200 );
$o3UpMbr    = new Mbr3UpMemberRenewals( $oApp, $kfdb, $yCurr );
$oPrint     = new SEEDPrint3UpHTML();

$sMod = SEEDInput_Smart( 'module', array( 'donor', 'member') );
$o3Up = $sMod == 'donor' ? $o3UpDonors : $o3UpMbr;

$o3UpMbr->Load();
$o3UpDonors->Load();

$mode = $o3Up->GetMode();

$sHead = $sBody = "";

if( SEEDInput_Str('cmd') == 'printMemberSlips' ) {

    $sTmpl = $o3UpMbr->GetTemplate();
    $raRows = $o3UpMbr->GetRows();
    // For some reason the first page is printed slightly lower than the others so it can be cut off at the bottom line,
    // and it has to be cut differently, and the second page is forced blank.
    // Instead of fixing this, insert three bogus slips and waste the first two pages
    $raRows = [ ['firstname'=>'nobody'], ['firstname'=>'nobody'], ['firstname'=>'nobody'] ] + $raRows;
    $oPrint->Do3Up( $raRows, $sTmpl );

    $sHead = $oPrint->GetHead();
    $sBody = $oPrint->GetBody();

    echo Console02Static::HTMLPage( SEEDCore_utf8_encode($sBody), $sHead, 'EN', ['bBootstrap'=>false] );   // sCharset defaults to utf8

    exit;
}

if( SEEDInput_Str('cmd') == 'printDonationSlips' ) {

    $sTmpl = $o3UpDonors->GetTemplate();
    $raRows = $o3UpDonors->GetRows();
    // For some reason the first page is printed slightly lower than the others so it can be cut off at the bottom line,
    // and it has to be cut differently, and the second page is forced blank.
    // Instead of fixing this, insert three bogus slips and waste the first two pages
    $raRows = array_merge([['firstname'=>'nobody'], ['firstname'=>'nobody'], ['firstname'=>'nobody']], $raRows);
    $oPrint->Do3Up( $raRows, $sTmpl );

    $sHead = $oPrint->GetHead();
    $sBody = $oPrint->GetBody();

    echo Console02Static::HTMLPage( SEEDCore_utf8_encode($sBody), $sHead, 'EN', ['bBootstrap'=>false] );   // sCharset defaults to utf8

    exit;
}

/*
<div class='s_credit' style='position:absolute;right:0;top:1.25in;width:3.5in'>
  &#9744; Cheque (enclosed) &nbsp;&nbsp;&nbsp;&nbsp; &#9744; Visa &nbsp;&nbsp;&nbsp;&nbsp; &#9744; MasterCard<br/><br/>
  Credit card number:<span style='text-decoration: underline; white-space: pre;'>                                                        </span><br/><br/>
  Expiry date (month/year):<span style='text-decoration: underline; white-space: pre;'>                                               </span><br/><br/>
  Name on card:<span style='text-decoration: underline; white-space: pre;'>                                                                 </span><br/><br/><br/>
  Signature:<span style='text-decoration: underline; white-space: pre;'>                                                                        </span>
</div>
 */

if( SEEDInput_Str('cmd') == 'printSFG2020Slips' ) {
    $o3UpDonors = new Mbr3UpSFG2020( $oApp, $yCurr );
    $oPrint     = new SEEDPrint3UpHTML();

    $o3UpMbr->Load();
    $o3UpDonors->Load();

    $sTmpl = $o3UpDonors->GetTemplate();
    $raRows = $o3UpDonors->GetRows();
    // For some reason the first page is printed slightly lower than the others so it can be cut off at the bottom line,
    // and it has to be cut differently, and the second page is forced blank.
    // Instead of fixing this, insert three bogus slips and waste the first two pages
    $raRows = [ ['firstname'=>'nobody'], ['firstname'=>'nobody'], ['firstname'=>'nobody'] ] + $raRows;
    $oPrint->Do3Up( $raRows, $sTmpl );

    $sHead = $oPrint->GetHead();
    $sBody = $oPrint->GetBody();

    echo Console02Static::HTMLPage( SEEDCore_utf8_encode($sBody), $sHead, 'EN', ['bBootstrap'=>false] );   // sCharset defaults to utf8

    exit;

}


class MyConsole02TabSet extends Console02TabSet
{
    private $oApp;
    private $oW;

    private $o3UpMbr;
    private $o3UpDonors;
    private $oContacts;
    private $oDonations;

    private $oDonRqstCtrlForm;  // Control area form for donation requests

    private $yThis;
    private $yLast;

    function __construct( SEEDAppConsole $oApp )
    {
        global $consoleConfig, $o3UpMbr, $o3UpDonors;
        parent::__construct( $oApp->oC, $consoleConfig['TABSETS'] );

        $this->oApp = $oApp;
        $this->o3UpMbr = $o3UpMbr;
        $this->o3UpDonors = $o3UpDonors;

        $this->oContacts = new Mbr_Contacts( $oApp );
        $this->oDonations = new MbrDonations($oApp);

        $this->yThis = date('Y');
        $this->yLast = $this->yThis - 1;
    }

    function TabSet_main_renewalRequests_ContentDraw()
    {
        /* Show the options form
         */
        $sBody = "<h4>Use 24lb paper for slips because it's easier to avoid picking up two at a time.</h4>"
                ."<div>".$this->o3UpMbr->OptionsForm()."</div>"
                .$this->o3UpMbr->ShowDetails();

        return( $sBody );
    }

    function TabSet_main_donationRequests_Init()
    {
        $this->oDonRqstCtrlForm = new SEEDCoreFormSVA( $this->TabSetGetSVACurrentTab('main'), 'D');
        $this->oDonRqstCtrlForm->Update();
    }

    function TabSet_main_donationRequests_ControlDraw()
    {
        return( "<form method='post'>{$this->oDonRqstCtrlForm->Text('dDonThreshold')} &nbsp;&nbsp;<input type='submit' value='Set Donation Threshold'/></form>"
            ."Note: This does nothing because the code to print slips is outside of the tabset so the groups are generated before this form can be accessed.
              <br/>Currently you have to do this via url ?dLargeDonation=NNN" );
    }

    function TabSet_main_donationRequests_ContentDraw()
    {
        /* Show the options form
         */
        $sBody = "<h4>Use 24lb paper for slips because it's easier to avoid picking up two at a time.</h4>"
                ."<div>".$this->o3UpDonors->OptionsForm()."</div>"
                .$this->o3UpDonors->ShowDetails();

        return( $sBody );
    }

    function TabSet_main_donationReceipts_ContentDraw()
    {
        $s = "<form target='_blank'>
              <input type='hidden' name='cmd' value='printDonationReceipt'>
              <input type='text' name='donorReceiptRange'/>
              <input type='submit' value='Make Receipts'/>
              </form>";

        return( $s );
    }

    function TabSet_main_donationReceipts2_ContentDraw()
    {
        $kMbr = "";
        $y = 0;
        $sMbrReceiptsLinks = "";
        $raReceiptNumbers = [];

        if( SEEDInput_Str('cmd')=='showReceiptLinksForMember' && ($kMbr = SEEDInput_Int('kMbr') ) ) {
            if( ($sMbrReceiptsLinks = $this->oDonations->DrawReceiptLinks($kMbr)) ) {
                $sMbrReceiptsLinks = "<div style='margin:10px; padding:10px; background-color:#eee'>
                                        <h4>Click to download official donation receipts</h4>$sMbrReceiptsLinks
                                      </div>";
            }
        }
        if( SEEDInput_Str('cmd')=='showReceiptsNotAccessed' && ($y = SEEDInput_Int('year') ) ) {
            foreach( $this->oDonations->GetListDonationsNotAccessedByDonor($y) as $raD ) {
                $raReceiptNumbers[] = $raD['receipt_num'];
            }
        }

        $s = "<div class='container-fluid'><div class='row'>
                  <div class='col-md-3'>
                      <form target='_blank'>
                          <input type='hidden' name='cmd' value='printDonationReceipt2'>
                          <input type='text' name='donorReceiptRange'/>
                          <input type='submit' value='Make Receipts'/>
                      </form>
                  </div>
                  <div class='col-md-3'>
                      <form>
                          <input type='hidden' name='cmd' value='showReceiptLinksForMember'>
                          <input type='text' name='kMbr' value='$kMbr' />
                          <input type='submit' value='List Receipts for Member'/>
                      </form>
                      $sMbrReceiptsLinks
                  </div>
                  <div class='col-md-3'>
                      <form>
                          <input type='hidden' name='cmd' value='showReceiptsNotAccessed'>
                          <select name='year'>
                              <option value='{$this->yLast}'>{$this->yLast}</option>
                              <option value='{$this->yThis}'>{$this->yThis}</option>
                          </select>
                          <input type='submit' value='List Receipts Not Accessed by Donor'/>
                      </form>
                      <p>Use this to print and mail receipts in February</p>
                      <p style='border:1px solid #aaa;background-color:#eee'>".SEEDCore_MakeRangeStr($raReceiptNumbers)."</p>
                      <textarea>".implode("\n",$raReceiptNumbers)."</textarea>
                  </div>
              </div></div>";

        return( $s );
    }

    function TabSet_main_donations_Init()         { $this->oW = new MbrDonationsListForm( $this->oApp ); $this->oW->Init(); }
    function TabSet_main_donations_ControlDraw()  { return( $this->oW->ControlDraw() ); }
    function TabSet_main_donations_ContentDraw()  { return( $this->oW->ContentDraw() ); }

    function TabSet_main_donationsSL_Init()       { $this->oW = new MbrAdoptionsListForm( $this->oApp ); $this->oW->Init(); }
    function TabSet_main_donationsSL_ControlDraw(){ return( $this->oW->ControlDraw() ); }
    function TabSet_main_donationsSL_ContentDraw(){ return( $this->oW->ContentDraw() ); }

    function TabSet_main_admin_Init(Console02TabSet_TabInfo $oT) { $this->oW = new MbrDonationsTab_Admin($this->oApp, $oT->oSVA); $this->oW->Init(); }
    function TabSet_main_admin_ControlDraw()  { return( $this->oW->ControlDraw() ); }
    function TabSet_main_admin_ContentDraw()  { return( $this->oW->ContentDraw() ); }
}

class MbrDonationsListForm extends KeyframeUI_ListFormUI
{
    private $oMbrDB;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oMbrDB = new Mbr_ContactsDB($oApp);

        $raConfig = [
            'sessNamespace' => "Donations",
            'cid'   => 'D',
            'kfrel' => $this->oMbrDB->Kfrel('DxM'),
            'KFCompParms' => ['raSEEDFormParms'=>['DSParms'=>['fn_DSPreStore'=> [$this,'dsPreStore']]]],

            'raListConfig' => [
                'bUse_key' => true,     // probably makes sense for KeyFrameUI to do this by default
                'cols' => [
                    [ 'label'=>"k",         'col'=>"_key",          'w'=>'5%' ],
                    [ 'label'=>"Member",    'col'=>"M__key",        'w'=>'5%' ],
                    [ 'label'=>"Firstname", 'col'=>"M_firstname",   'w'=>'15%' ],
                    [ 'label'=>"Lastname",  'col'=>"M_lastname",    'w'=>'15%' ],
                    [ 'label'=>"Company",   'col'=>"M_company",     'w'=>'20%' ],
                    [ 'label'=>"Received",  'col'=>"date_received", 'w'=>'10%' ],
                    [ 'label'=>"Amount",    'col'=>"amount",        'w'=>'5%' ],
                    [ 'label'=>"Category",  'col'=>"category",      'w'=>'5%' ],
                    [ 'label'=>"Issued",    'col'=>"date_issued",   'w'=>'10%' ],
                    [ 'label'=>"Receipt #", 'col'=>"receipt_num",   'w'=>'5%' ],
                ],
               // 'fnRowTranslate' => [$this,"listRowTranslate"],
            ],

            'raSrchConfig' => [
                'filters' => [
                    ['label'=>'First name',    'col'=>'M.firstname'],
                    ['label'=>'Last name',     'col'=>'M.lastname'],
                    ['label'=>'Company',       'col'=>'M.company'],
                    ['label'=>'Member #',      'col'=>'M._key'],
                    ['label'=>'Donation key',  'col'=>'D._key'],
                    ['label'=>'Amount',        'col'=>'amount'],
                    ['label'=>'Category',      'col'=>'category'],
                    ['label'=>'Date received', 'col'=>'date_received'],
                    ['label'=>'Date issued',   'col'=>'date_issued'],
                    ['label'=>'Receipt #',     'col'=>'receipt_num'],
                ]
            ],

            'raFormConfig' => [ 'fnExpandTemplate'=>[$this,'donationForm'] ],
        ];
        parent::__construct( $oApp, $raConfig );
    }

    function dsPreStore( Keyframe_DataStore $oDS )
    {
        // This allows date_issued to be erased. A DATE cannot be '' but it can be NULL.
        // However, if it is already NULL KF will try to set it to NULL again and log that.
        // So you'll see date_issued=NULL in the log when it was already NULL.
        // The reason is that KF stores a NULL value's snapshot as '' so it thinks the value is changing from '' to NULL.
        $kfr = $oDS->GetKFR();  // because there is no oDS->SetNull, though there could be if you can generalize it for the base SEEDDataStore
        if( !$kfr->value('date_issued') ) $kfr->SetNull('date_issued');

        return( true );
    }

    function Init()
    {
        parent::Init();

        // Add record to mbr_donation_receipts_accessed saying that the donor viewed their receipt (use this when sending them a receipt manually)
        if( SEEDInput_Str('cmd') == 'addReceiptAccess' &&
            ($kMbr = $this->oComp->oForm->Value('fk_mbr_contacts')) && ($kDonation = $this->oComp->oForm->GetKey()) )
        {
            $this->oMbrDB->StoreDonationReceiptAccessed($kDonation, $kMbr);
        }

        // Delete mbr_donation_receipts_accessed record
        // ** the time column has CURRENT_TIMESTAMP_ON_UPDATE so this actually updates the time
        if( SEEDInput_Str('cmd') == 'deleteReceiptAccess' && ($kR = SEEDInput_Int('kR')) ) {
            if( ($kfr = $this->oMbrDB->GetKFR('R',$kR)) ) {
                $kfr->StatusSet(KeyframeRecord::STATUS_DELETED);
                $kfr->PutDBRow();
            }
        }
    }

    function ControlDraw()  { return( $this->DrawSearch() ); }

    function ContentDraw()
    {
        $s = $this->DrawStyle()
           ."<style>.donationFormTable td { padding:3px;}</style>
             <div>{$this->DrawList()}</div>
             <div style='margin-top:20px;padding:20px;border:2px solid #999'>
                 {$this->DrawForm()}
                 <br/><br/>"
                 // this goes outside of the oComp form so it can have its own <form> elements
                 .$this->donationData($this->oComp->oForm->GetKFR())
           ."</div>";

        return( $s );
    }

    function donationForm( KeyframeForm $oForm )
    {
        $sReceiptInstructions = "<p style='font-size:x-small'>" //Receipt Instructions:<br/>"
                               ."-1 = no receipt, see note<br/>-2 = no receipt, below threshold<br/>-3 = Canada Helps</p>";

        list($jsShow,$jsScript) = $this->jsSetValues($oForm);

        $s = "|||TABLE( || class='donationFormTable' width='100%' border='0')"
            ."||| *Member*     || [[text:fk_mbr_contacts|size=30]]"
            ." || *Amount*     || [[text:amount|size=30]]"
            ." || *Cat / Purpose* || [[text:category|size=10]] [[text:purpose|size=16]]"
            ." || {colspan='1' rowspan='3'}".$jsShow
            ."||| &nbsp        || &nbsp;"
            ." || *Received*   || [[text:date_received|size=30]]"
            ." || *Issued*     || [[text:date_issued|size=30]]"

            ."||| *Notes*      || {colspan='3' rowspan='2'} ".$oForm->TextArea( "notes", ['width'=>'90%','nRows'=>'3'] )
            ." || *Receipt #*  || [[text:receipt_num|size=30]]"

            ."||| &nbsp;        || "
            ." ||  ".$sReceiptInstructions
            ." || <input type='submit' value='Save'>"
            ."|||ENDTABLE"
            ."[[hiddenkey:]]"
            .$jsScript;

        return( $s );
    }

    private function jsSetValues( SEEDCoreForm $oForm )
    /**************************************************
        If there is no receipt_num, make a js-link that sets the date_issued the same as the most recent record, and receipt_num++
     */
    {
        $sShow = $sScript = "";

        if( !$oForm->Value('receipt_num') &&
            // Get the donation row with the greatest receipt_num as a template for the next row.
            ($kfrLastDonation = $this->oMbrDB->GetKFRCond('D', "", ['sSortCol'=>'receipt_num','bSortDown'=>true])) &&
            $kfrLastDonation->Value('date_issued') )
        {
            //$cat = $kfrLastDonation->Value('category');
            $iss = $kfrLastDonation->Value('date_issued');
            $rec = $kfrLastDonation->ValueInt('receipt_num') + 1;

            //$ctrlCat = $oForm->Name('category');
            $ctrlIss = $oForm->Name('date_issued');
            $ctrlRec = $oForm->Name('receipt_num');

            $sScript = "<script>
                    function donSetCtrls()
                    {
                        event.preventDefault();
                    ".    //$('.donationFormTable #{$ctrlCat}').val('{$cat}');
                    "    $('.donationFormTable #{$ctrlIss}').val('{$iss}');
                        $('.donationFormTable #{$ctrlRec}').val('{$rec}');
                    }
                   </script>
                  ";

            // #337ab7 is bootstrap's link colour
            $sShow = $kfrLastDonation->Expand(
                "<div onclick='donSetCtrls()'
                      style='border:1px solid #337ab7;color:#337ab7;padding:3px;cursor:pointer'>Fill:<br/>$iss<br/>$rec</div>" );
        }
        return( [$sShow,$sScript] );
    }


    private function donationData( KeyframeRecord $kfrD )
    {
        $s = "";
        $sTicket = $sReceipt = "";

        $kDonation = $kfrD->Key();
        $kMbr = $this->oComp->oForm->ValueInt('fk_mbr_contacts');

//        $ra = $this->oApp->kfdb->QueryRA( "SELECT * FROM {$this->oApp->GetDBName('seeds2')}.mbr_contacts WHERE _key='$kMbr'" );
//        $s .= "<p>Mbr database:<br/>donation_receipt: ".@$ra['donation_receipt']."</p>";

        /* Show the order ticket for this donation
         */
        include_once( SEEDAPP."basket/basketProductHandlers.php" );
        $oSB = new SEEDBasketCoreSoD( $this->oApp );
        $oHandler = new SEEDBasketProductHandler_Donation( $oSB );
        if( ($oPur = $oHandler->GetPurchaseFromKDonation( $kDonation )) &&
            ($kB = $oPur->GetBasketKey()) &&
            ($kOrder = $this->oApp->kfdb->Query1("SELECT _key FROM {$this->oApp->GetDBName('seeds1')}.mbr_order_pending WHERE kBasket='$kB'")) )
        {
            // this is coming from seedsx
            include_once( SEEDCOMMON."mbr/mbrOrder.php" );
            $kfdb = SiteKFDB();
            $oMbrOrder = new MbrOrder( $this->oApp, $kfdb, "EN", $kOrder );
            $sTicket = $oMbrOrder->DrawTicket();
        }

        if( $kfrD->ValueInt('receipt_num') > 0 ) {
            /* Show the receipt views
             */
            $sReceipt = "<p><a href='?cmd=printDonationReceipt2&donorReceiptRange={$kfrD->ValueInt('receipt_num')}' target='_blank'>Print this receipt</a></p>"
                       ."<h4>Receipt viewed by</h4>";
            if( ($kfrR = $this->oMbrDB->GetKFRC('RxD_M', "D._key=$kDonation",['sSortCol'=>'R.time'])) ) {
                while( $kfrR->CursorFetch() ) {
                    $sColour = $kfrR->Value('uid_accessor')==$kMbr ? 'green' : 'red';   // green if the access was by the donor
                    $sDelete = "<form method='post' style='display:inline-block'>
                                    <input type='hidden' name='cmd' value='deleteReceiptAccess'/>
                                    <input type='hidden' name='kR' value='{$kfrR->Key()}'/>
                                    <input type='submit' value='delete' style='font-size:x-small'/> </form>";
                    $sReceipt .= $kfrR->Expand("<span style='color:$sColour'>[[uid_accessor]]</span> at [[time]]&nbsp;&nbsp;&nbsp;")."{$sDelete}<br/>";
                }
            }
            $sReceipt .= "<form method='post'><input type='submit' value='Add viewed by $kMbr now'/>
                                               <input type='hidden' name='cmd' value='addReceiptAccess'/>
                                               <input type='hidden' name='kMbr' value='$kMbr'/></form>";
        }

        $s = "<table><tr><td valign='top' style='padding-right:20px'>$sTicket</td>
                         <td valign='top'>$sReceipt</td>
              </tr></table>";

        return( $s );
    }
}

include_once( SEEDLIB."sl/sldb.php" );
include_once( SEEDAPP."sl/sl_ts_adoptions.php");
include_once( "adminTab.php" );


$oCTS = new MyConsole02TabSet( $oApp );

$sBody = $oApp->oC->DrawConsole( "[[TabSet:main]]".$sBody, ['oTabSet'=>$oCTS] );


echo Console02Static::HTMLPage( SEEDCore_utf8_encode($sBody), $sHead, 'EN',
                                ['consoleSkin'=>'green',
// adoption cultvar select doesn't work because SLPcvSelector is authenticating on seeds_1
                                 'raScriptFiles'=>[$oApp->UrlW()."js/SEEDUI.js",           // for SearchControl reset button
                                                   $oApp->UrlW()."js/SFUTextComplete.js",  // for SLPcvSelector.js
                                                   $oApp->UrlW()."js/SLPcvSelector.js",    // for cultivar search
                                                  ]
                                ]);

//echo Console01Static::HTMLPage( $sBody, $sHead, "EN", array( 'bBootstrap' => false,    // we want to control the CSS completely, thanks anyway Bootstrap
//                                                             'sCharset'=>'utf8' ) );


class Mbr3UpSFG2020
{
    private $oApp;
    private $lang = "EN";
    private $year;

    private $raData = array();

    function __construct( SEEDAppConsole $oApp, $year )
    {
        $this->oApp = $oApp;
        $this->year = $year;
    }

    function GetRows()
    {
        return( $this->raData );
    }

    function Load()
    {

        $yStart = $this->year - 2;              // include donors from two years ago
        $yEnd = $this->year;                    // until this year

//$this->oApp->kfdb->SetDebug(2);

        $oMbr = new Mbr_Contacts($this->oApp);


//        $raMbr1 = $this->oApp->kfdb->QueryRowsRA1(
//            "SELECT * FROM seeds_2.mbr_contacts M LEFT JOIN seeds_2.mbr_donations D ON (M._key=D.fk_mbr_contacts) WHERE "
$cond =                 "M._status='0' AND M.province='ON' AND "         // country='Canada'
                ."M.address IS NOT NULL AND M.address<>'' AND "   // address is blanked out if mail comes back RTS
                ."NOT M.bNoDonorAppeals AND "
                ."(year(M.expires)>='$yStart' "
              //." OR (M.donation_date is not null AND year(M.donation_date)>='$yStart') "  obsolete
                ." OR (D.date_received is not null AND year(D.date_received)>='$yStart') "
                .") ";
//                ."ORDER by cast(M.donation as decimal)+cast(D.amount as decimal) desc,lastname,firstname" );

//        $raMbr2 = $this->oApp->kfdb->QueryRowsRA(
//            "SELECT * from seeds_2.mbr_contacts WHERE _key in (".implode(',',$raMbr1).")" );

        $raMbr1 = $oMbr->oDB->GetList( 'M_D', $cond, ['sSortCol'=>"cast(M.donation as decimal)+cast(D.amount as decimal)", 'bSortDown'=>true] );

$ra2=[];
foreach( $raMbr1 as $ra )
{
    if( !isset($ra2[$ra['_key']]) ) {
        $ra2[$ra['_key']] = ['n'=>1,'name'=>"{$ra['firstname']} {$ra['lastname']} {$ra['company']}"];
    } else {
        $ra2[$ra['_key']]['n']++;
    }
}
foreach( $ra2 as $k => $ra ) {
    if( $ra['n'] > 1 ) echo "Duplicate x {$ra['n']} : {$k} {$ra['name']}<br/>";
}
exit;
        $raMbr = [];
        foreach( $raMbr1 as $ra )
        {
            $this->raData[] = $ra + ['SEEDPrint:addressblock' => MbrDrawAddressBlockFromRA( $ra )];
        }
    }

    function GetTemplate()
    {
        $lang = $this->lang;

$sTitle       = "Yes, I want to help kids learn about food and gardening in schools!";
$sWantOneTime = $lang=='EN' ? "I want to make a one-time donation of" : "Je d&eacute;sire faire un don unique de";
$sOther       = $lang=='EN' ? "Other" : "Autre";

$sRight       = "<p>Your charitable donation will help kids of all ages to learn food and gardening skills in safe, outdoor School Food Gardens -
  healthy food, nutrition, sustainable food practices, and much more. We'll even make web-based activities for the
  kids who are learning online from home this year.</p>
  <p>You can also make your donation online at <b><u>www.seeds.ca/donate</u></b>.</p>";

$sAddrChanged = $lang=='EN' ? "Has your address or contact information changed?"
                            : "Votre adresse ou vos coordonn&eacute;es ont-elles chang&eacute;?";
$sEmail       = $lang=='EN' ? "Email": "Courriel";
$sPhone       = $lang=='EN' ? "Phone": "T&eacute;l&eacute;phone";
$sMember      = $lang=='EN' ? "Member" : "Membre";

$sFooter      = $lang=='EN' ? "Seeds of Diversity is a registered charitable organization. We provide receipts for donations of $20 and over. Our charitable registration number is 89650 8157 RR0001."
                            : "Les Semences du patrimoine sont un organisme de bienfaisance enregistr&eacute;. Nous faisons parvenir un re&ccedil;u &agrave; fins d'imp&ocirc;t pour tous les dons de 20 $ et plus.<br/>Notre num&eacute;ro d'enregistrement est 89650 8157 RR0001";

//<img style='float:right;width:0.75in' src='http://seeds.ca/i/img/logo/logoA_v-en-bw-300x.png'/>

// 2018-11 changed right: from 0.125in to 0.375in to prevent the right side cut off. 0.3in for the logo to make room for text at top
$s = "
<img style='position:absolute;top:0.125in;right:0.3in;width:0.75in' src='https://seeds.ca/i/img/logo/logoA_v-".($lang=='EN' ? "en":"fr")."-bw-300x.png'/>
<div class='s_title'>$sTitle</div>
<div class='s_form'>
  <table>
  <tr><td>&nbsp;</td></tr>
  <tr><td>&#9744; $sWantOneTime</td><td>&#9744; $20</td><td>&#9744; $50</td><td>&#9744; $100</td><td>&#9744; $200</td><td>&#9744; $sOther <span style='text-decoration: underline; white-space: pre;'>          </span></td></tr>
  </table>
</div>
<div class='s_right' style='position:absolute;right:0.375in;top:1.125in;width:4.25in'>
  $sRight
  <div style='border:1px solid #aaa;background-color:#f4f4f4;margin-left:0.75in;padding:0.125in'>
    <div>$sAddrChanged</div>
    <div style='margin-top:0.125in'>
    $sEmail: [[email]]<br/>
    $sPhone: [[phone]]</div>
  </div>
  <div style='font-size:8pt;margin-top:0.05in;float:right'>$sMember [[_key]]</div>
</div>
<div class='s_note' style='position:absolute;bottom:0.125in;left:0.325in;text-alignment:left'>
  $sFooter
</div>
";

        return( $s );
    }
}


class Mbr3UpDonors
{
    public $mode = "";

    private $oApp;
    private $lang = "EN";
    private $year;
    private $dLargeDonation;    // threshold for large donations

    private $raDonorEN, $raDonorFR, $raNonDonorEN, $raNonDonorFR;

    private $raDonor, $raNonDonor;

    private $raData = array();

    function __construct( SEEDAppConsole $oApp, int $year, int $dLargeDonation )
    {
        $this->oApp = $oApp;
        $this->year = $year;
        $this->dLargeDonation = $dLargeDonation;

        $this->oMbr = new Mbr_Contacts($oApp);
        $this->oMbrList = new MbrContactsList($oApp, ['dLargeDonation'=>$this->dLargeDonation]);

        $this->mode = SEEDInput_Smart( 'mode', array( '', 'donorEN','donor100EN','donor99EN','donorFR','donor100FR','donor99FR','nonDonorEN','nonDonorFR' ) );
        $this->lang = substr( $this->mode, -2 ) ?: 'EN';
    }

    function GetMode()  { return( $this->mode ); }

    function GetRows()
    {
        return( $this->mode ? $this->oMbrList->GetGroup($this->mode)['raList'] : [] );
    }

    function OptionsForm()
    {
        $s = "";

        return( $s );
    }

    function Load()
    {
return;
        if( $this->mode == 'details' ) {
            //$this->kfdb->SetDebug(2);
        }

        var_dump($sqlEN, count($raDonorEN));exit;




        $d100 = "donation is not null AND donation >= 100";
        $d99  = "(donation is null OR donation < 100)";

        $sCondDonorEN = "$dYes AND $lEN";
        $sCondDonorFR = "$dYes AND $lFR";
        $sCondNonDonorMemberEN = "$dNo AND $lEN";
        $sCondNonDonorMemberFR = "$dNo AND $lFR";



// donation_date is the most recent, `donation` is the total donations for year(donation_date)
// so you can always say Thanks for your donation of `donation` in year(`donation_date`)" and mean the total for that year.
// And you can limit the list to `donation_date` < (date() - some reasonable margin of the recent past)

        $yStart = $this->year - 2;              // include donors from two years ago
        $yEnd = $this->year;                    // until this year
        $dRecentThreshold = "{$yEnd}-07-01";

        $lEN = "lang<>'F'";
        $lFR = "lang='F'";
        $dYes = "donation_date is not null AND year(donation_date)>='$yStart'";    // recent donors - null check is required for the NOT(expr)
        $dNo = "NOT($dYes) AND year(expires)>='$yStart'";                          // recent members who are not donors
        $dGlobal = "_status='0' AND country='Canada' AND "
                  ."address IS NOT NULL AND address<>'' AND "   // address is blanked out if mail comes back RTS
                  ."NOT bNoDonorAppeals AND "
                  ."NOT(donation_date is not null AND donation_date>'$dRecentThreshold')";

        $d100 = "donation is not null AND donation >= 100";
        $d99  = "(donation is null OR donation < 100)";

        $sCondDonorEN = "$dYes AND $lEN";
        $sCondDonorFR = "$dYes AND $lFR";
        $sCondNonDonorMemberEN = "$dNo AND $lEN";
        $sCondNonDonorMemberFR = "$dNo AND $lFR";

        $dbname2 = $this->oApp->GetDBName('seeds2');
        $this->raDonorEN    = $this->kfdb->QueryRowsRA("SELECT * FROM {$dbname2}.mbr_contacts WHERE $dGlobal AND $sCondDonorEN order by cast(donation as decimal),lastname,firstname" );
        $this->raDonorFR    = $this->kfdb->QueryRowsRA("SELECT * FROM {$dbname2}.mbr_contacts WHERE $dGlobal AND $sCondDonorFR order by cast(donation as decimal),lastname,firstname" );
        $this->raNonDonorEN = $this->kfdb->QueryRowsRA("SELECT * FROM {$dbname2}.mbr_contacts WHERE $dGlobal AND $sCondNonDonorMemberEN order by lastname,firstname" );
        $this->raNonDonorFR = $this->kfdb->QueryRowsRA("SELECT * FROM {$dbname2}.mbr_contacts WHERE $dGlobal AND $sCondNonDonorMemberFR order by lastname,firstname" );
        $this->raDonor    = $this->lang=='EN' ? $this->raDonorEN    : $this->raDonorFR;
        $this->raNonDonor = $this->lang=='EN' ? $this->raNonDonorEN : $this->raNonDonorFR;

        foreach( $this->getGroups() as $k => $ra )
        {
            $this->raData[$k] = $this->oApp->kfdb->QueryRowsRA("SELECT * FROM {$this->oApp->GetDBName('seeds2')}.mbr_contacts WHERE $dGlobal AND ".implode(' AND ',$ra['cond'])." order by {$ra['order']}" );
            // now for each row in the result, insert the address block in the row
            $ra1 = array();
            foreach( $this->raData[$k] as $k1=>$ra1 ) {
                $this->raData[$k][$k1]['SEEDPrint:addressblock'] = SEEDCore_utf8_encode(MbrDrawAddressBlockFromRA( $ra1 ));
            }
        }
    }

    function ShowDetails()
    {
        $sDonThresholdUpper = $this->dLargeDonation;
        $sDonThresholdLower = $this->dLargeDonation - 1;

        $s = "<p>Donors English: {$this->oMbrList->GetGroupCount('donorEN')} - \${$sDonThresholdUpper}/\${$sDonThresholdLower} = {$this->oMbrList->GetGroupCount('donor100EN')}/{$this->oMbrList->GetGroupCount('donor99EN')}</p>"
            ."<p>Donors French:  {$this->oMbrList->GetGroupCount('donorFR')} - \${$sDonThresholdUpper}/\${$sDonThresholdLower} = {$this->oMbrList->GetGroupCount('donor100FR')}/{$this->oMbrList->GetGroupCount('donor99FR')}</p>"
            ."<p>Non-donor Members English: {$this->oMbrList->GetGroupCount('nonDonorEN')}</p>"
            ."<p>Non-donor Members French:  {$this->oMbrList->GetGroupCount('nonDonorFR')}</p>"
            ."<p>&nbsp</p>"
            ."<p>English: ".($this->oMbrList->GetGroupCount('donorEN')+$this->oMbrList->GetGroupCount('nonDonorEN'))."</p>"
            ."<p>French: ".($this->oMbrList->GetGroupCount('donorFR')+$this->oMbrList->GetGroupCount('nonDonorFR'))."</p>";

        $s .= "<table border='1'>";
        foreach( [ ['donorEN','donor100EN','donor99EN'],['donorFR','donor100FR','donor99FR'],['nonDonorEN','nonDonorFR'] ]  as $raRow ) {
            $s .= "<tr>";
            foreach( $raRow as $k ) {
                $s .= "<td valign='top'><h3>{$this->oMbrList->GetGroup($k)['title']}</h3>"
                     ."<p>{$this->oMbrList->GetGroupCount($k)}</p>"
                     .$this->drawButton( $k )
                     ."<p style='font-size:7pt'>{$this->oMbrList->GetGroup($k)['sql']}</p>"
                     ."</td>";
            }
            $s .= "</tr>";
        }
        $s .= "</table>";

        $sLine = "<tr><td>[[M_firstname]] [[M_lastname]] [[M_company]]</td><td>[[D_amountTotal]]</td><td>[[D_amount]]</td><td>[[D_date_received]]</td></tr>";

        $s .= "<h3>Donors English</h3>"
             ."<table border='1'>"
                 ."<th>&nbsp;</th><th>Total 2 years</th><th>Most recent</th><th>Most recent</th>"
                 .SEEDCore_ArrayExpandRows( $this->oMbrList->GetGroup('donorEN')['raList'], $sLine )."</table>"
             ."<h3>Donors French</h3>"
             ."<table border='1'>".SEEDCore_ArrayExpandRows( $this->oMbrList->GetGroup('donorFR')['raList'], $sLine )."</table>"
             ."<h3>Non-Donors English</h3>"
             ."<table border='1'>".SEEDCore_ArrayExpandRows( $this->oMbrList->GetGroup('nonDonorEN')['raList'], $sLine )."</table>"
             ."<h3>Non-Donors French</h3>"
             ."<table border='1'>".SEEDCore_ArrayExpandRows( $this->oMbrList->GetGroup('nonDonorFR')['raList'], $sLine )."</table>"
             ."</table>";

         return( $s );
    }

    private function getGroups()
    {
        $yStart = $this->year - 2;              // include donors from two years ago
        $yEnd = $this->year;                    // until this year
        $dRecentThreshold = "{$yEnd}-07-01";

        $lEN = "lang<>'F'";
        $lFR = "lang='F'";
        $dYes = "donation_date is not null AND year(donation_date)>='$yStart'";    // recent donors - null check is required for the NOT(expr)
        $dNo = "NOT($dYes) AND year(expires)>='$yStart'";                          // recent members who are not donors
        $dGlobal = "_status='0' AND country='Canada' AND "
                  ."address IS NOT NULL AND address<>'' AND "   // address is blanked out if mail comes back RTS
                  ."NOT bNoDonorAppeals AND "
                  ."NOT(donation_date is not null AND donation_date>'$dRecentThreshold')";

        $d100 = "donation is not null AND donation >= 100";
        $d99  = "(donation is null OR donation < 100)";

//dGlobal should be in all these conditions
        $raGroups = array(
            'donorEN'    => array( 'title'=>'Donors English',       'cond'=>[$dYes, $lEN],        'order'=>"cast(donation as decimal) desc,lastname,firstname"),
            'donorFR'    => array( 'title'=>'Donors French',        'cond'=>[$dYes, $lFR],        'order'=>"cast(donation as decimal) desc,lastname,firstname"),
            'donor100EN' => array( 'title'=>'Donors English $100+', 'cond'=>[$dYes, $lEN, $d100], 'order'=>"cast(donation as decimal) desc,lastname,firstname"),
            'donor100FR' => array( 'title'=>'Donors French $100+',  'cond'=>[$dYes, $lFR, $d100], 'order'=>"cast(donation as decimal) desc,lastname,firstname"),
            'donor99EN'  => array( 'title'=>'Donors English $99-',  'cond'=>[$dYes, $lEN, $d99],  'order'=>"cast(donation as decimal) desc,lastname,firstname"),
            'donor99FR'  => array( 'title'=>'Donors French $99-',   'cond'=>[$dYes, $lFR, $d99],  'order'=>"cast(donation as decimal) desc,lastname,firstname"),
            'nonDonorEN' => array( 'title'=>'Non-donors English',   'cond'=>[$dNo,  $lEN],        'order'=>"lastname,firstname"),
            'nonDonorFR' => array( 'title'=>'Non-donors French',    'cond'=>[$dNo,  $lFR],        'order'=>"lastname,firstname")
        );

        return( $raGroups );
    }

    private function drawButton( $k )
    {
        return( "<form method='get' target='mbr3pdf'><input type='hidden' name='cmd' value='printDonationSlips'/><input type='hidden' name='mode' value='$k'/><input type='submit' value='Make Slips'/></form>" );
    }

    function GetTemplate()
    {
        $lang = $this->lang;

$sTitle       = $lang=='EN' ? "Yes, I would like to help save Canadian seed diversity!"
                            : "Oui, je veux contribuer &agrave; sauvegarder la diversit&eacute; semenci&egrave;re du Canada!";
$sWantOneTime = $lang=='EN' ? "I want to make a charitable donation of" : "Je d&eacute;sire faire un don de";
//$sWantMonthly = $lang=='EN' ? "I want to make a monthly donation of" : "Je d&eacute;sire faire un une contribution <u>mensuelle</u> de";
$sOther       = $lang=='EN' ? "Other" : "Autre";

$sRight       = $lang=='EN' ? "<p>Your charitable donation this year will help save hundreds of rare plant varieties next year.
  Seeds of Diversity will use your donation to find seeds that need rescuing, and organize seed savers across the country to grow them in ".(date('Y')+1).".</p>
  <p>You can also make your donation online at <b><u>www.seeds.ca/donate</u></b>.</p>"
                            : "<p>Votre don de charit&eacute; de cette ann&eacute;e aidera &agrave; sauver des centaines de vari&eacute;t&eacute;s rares l'an prochain.
  Semences du patrimoine utilisera votre don pour trouver des semences qui ont besoin d'&ecirc;tre secourues, et pour trouver des conservateurs de semences &agrave; travers le Canada afin de les cultiver en ".(date('Y')+1).".</p>
  <p>Vous pouvez &eacute;galement faire un don en ligne au <b><u>www.semences.ca/don</u></b>.</p>";

$sAddrChanged = $lang=='EN' ? "Has your address or contact information changed?"
                            : "Votre adresse ou vos coordonn&eacute;es ont-elles chang&eacute;?";
$sEmail       = $lang=='EN' ? "Email": "Courriel";
$sPhone       = $lang=='EN' ? "Phone": "T&eacute;l&eacute;phone";
$sMember      = $lang=='EN' ? "Member" : "Membre";

$sFooter      = $lang=='EN' ? "Seeds of Diversity is a registered charitable organization (no. 89650 8157 RR0001). We provide receipts for donations of $20 and over."
                            : "Les Semences du patrimoine sont un organisme de bienfaisance enregistr&eacute; (no. 89650 8157 RR0001). Nous faisons parvenir un re&ccedil;u &agrave; pour les dons de 20 $ et plus.";

//<img style='float:right;width:0.75in' src='http://seeds.ca/i/img/logo/logoA_v-en-bw-300x.png'/>

// 2018-11 changed right: from 0.125in to 0.375in to prevent the right side cut off. 0.3in for the logo to make room for text at top
$s = "
<img style='position:absolute;top:0.125in;right:0.3in;width:0.75in' src='https://seeds.ca/i/img/logo/logoA_v-".($lang=='EN' ? "en":"fr")."-bw-300x.png'/>
<div class='s_title'>$sTitle</div>
<div class='s_form'>
  <br/>
  <table>
  <tr><td>$sWantOneTime</td><td>&#9744; $20</td><td>&#9744; $50</td><td>&#9744; $100</td><td>&#9744; $200</td><td>&#9744; $sOther <span style='text-decoration: underline; white-space: pre;'>          </span></td></tr>
"
//  <tr><td>&#9744; $sWantMonthly</td><td>&#9744; $10</td><td>&#9744; $20</td><td colspan='2'>&#9744; $sOther <span style='text-decoration: underline; white-space: pre;'>           </span></td></tr>
."
  </table>
</div>
<div class='s_right' style='position:absolute;right:0.375in;top:1.125in;width:4.25in'>
  $sRight
  <div style='border:1px solid #aaa;background-color:#f4f4f4;margin-left:0.75in;padding:0.125in'>
    <div>$sAddrChanged</div>
    <div style='margin-top:0.125in'>
    $sEmail: [[M_email]]<br/>
    $sPhone: [[M_phone]]</div>
  </div>
  <div style='font-size:8pt;margin-top:0.05in;float:right'>$sMember [[M__key]]</div>
</div>
<div class='s_note' style='position:absolute;bottom:0.125in;left:0.325in;text-alignment:left'>
  $sFooter
</div>
";

        return( $s );
    }
}


class Mbr3UpMemberRenewals
{
    private $mode = "";

    private $oApp;
    private $kfdb;
    private $year;

    private $raMbr, $raMbrEN, $raMbrFR;

    function __construct( SEEDAppConsole $oApp, KeyFrameDatabase $kfdb, $year )
    {
        $this->oApp = $oApp;
        $this->kfdb = $kfdb;
        $this->year = $year;
    }

    function GetMode()  { return( $this->mode ); }

    function GetRows()
    {
        return( $this->raMbr );
    }

    function OptionsForm()
    {
        $s = "<form target='_blank'>"
            ."<input type='hidden' name='cmd' value='printMemberSlips'/>"
            ."<select name='lang'>"
                ."<option value='EN'>English</option>"
                ."<option value='FR'>French</option>"
            ."</select>"
            ."<br/><br/>"
            ."<input type='submit' value='Make Slips'/>"
            ."</form>";

        return( $s );
    }

    function Load()
    {
        $this->mode = SEEDInput_Smart( 'mode', array( '', 'details', '3Up' ) );
        $this->lang = SEEDInput_Smart( 'lang', array( 'EN', 'FR') );

        if( $this->mode == 'details' ) {
            //$this->kfdb->SetDebug(2);
        }

        $yLastYear   = $this->year - 1;
        $yBeforeThat = $this->year - 2;

        $lEN = "lang<>'F'";
        $lFR = "lang='F'";
        $dGlobal = "_status='0' AND country='Canada' AND "
                  ."address IS NOT NULL AND address<>'' AND "   // address is blanked out if mail comes back RTS
                  ."NOT bNoDonorAppeals AND "                   // they probably see this as the same thing
                  ."expires IS NOT NULL AND year(expires) IN ($yLastYear,$yBeforeThat)";

        $dbname2 = $this->oApp->GetDBName('seeds2');
        $this->raMbrEN = $this->kfdb->QueryRowsRA("SELECT * FROM {$dbname2}.mbr_contacts WHERE $dGlobal AND $lEN order by lastname,firstname" );
        $this->raMbrFR = $this->kfdb->QueryRowsRA("SELECT * FROM {$dbname2}.mbr_contacts WHERE $dGlobal AND $lFR order by lastname,firstname" );

        $this->raMbr   = $this->lang=='EN' ? $this->raMbrEN : $this->raMbrFR;
        foreach( $this->raMbr as &$ra ) {
            $ra['SEEDPrint:addressblock'] = SEEDCore_utf8_encode(MbrDrawAddressBlockFromRA( $ra ));
        }
    }

    function ShowDetails()
    {
        $s = "<p>".count($this->raMbrEN)." English, ".count($this->raMbrFR)." French</p>";

        return( $s );
    }

    function GetTemplate()
    {
        $lang = $this->lang;

$sLocalStyle =
            "<style>"
           .".right_p {margin:0.02in 0pt;padding:0.02in 0pt;}"
           ."</style>";

$sTitle       = $lang=='EN' ? "Yes, please renew my membership to Seeds of Diversity!"
                            : "Oui, je d&eacute;sire renouveler mon abonnement aux Semences du patrimoine!";
$sForm = $lang=='EN'
            ? ("<div style='font-size:8pt;margin:0.05in'><i>Memberships include a subscription to our magazine, monthly e-bulletin and an online seed directory every year.</i></div>"
              ."<table style='font-size:11pt'>"
              ."<tr><td><b>&#9744; One year</b></td><td style='margin-left:0.5in'><b>$35</b></td><td style='padding-left:0.5in'>&#9744; Please send me a printed copy of the Member Seed Directory - <b>Add $10 <u>per year</u></b></td>"
              ."<tr><td><b>&#9744; Three year</b></td><td style='margin-left:0.5in'><b>$100</b></td><td>&nbsp;</td>"
              ."<tr><td><b>&#9744; Lifetime</b></td><td style='margin-left:0.5in'><b>$1000</b></td><td>&nbsp;</td>"
              ."</table>")
            : ("<div style='font-size:8pt;margin:0.05in'><i>L'adh&eacute;sion annuelle comprend un abonnement de la revue, l'e-bulletin mensuel, et l'acc&egrave;s en ligne au catalogue des semences.</i></div>"
              ."<table style='font-size:11pt'>"
              ."<tr><td><b>&#9744; Un an</b></td><td style='margin-left:0.5in'><b>35 $</b></td><td style='padding-left:0.5in'>&#9744; Je souhaite une copie papier du catalogue des semences - <b>10 $ <u>par ann&eacute;e</u></b></td>"
              ."<tr><td><b>&#9744; Trois ans</b></td><td style='margin-left:0.5in'><b>100 $</b></td><td>&nbsp;</td>"
              ."<tr><td><b>&#9744; &Agrave; vie</b></td><td style='margin-left:0.5in'><b>1000 $</b></td><td>&nbsp;</td>"
              ."</table>");

$sWantMonthly = $lang=='EN' ? "I want to make a monthly donation of" : "Je d&eacute;sire faire un une contribution <u>mensuelle</u> de";
$sOther       = $lang=='EN' ? "Other" : "Autre";

$sRight = $lang=='EN'
            ? ("<p class='right_p' style='font-weight:bold;'>Add a Donation</p>"
              ."<p class='right_p'>We count on your generosity to help protect Canada's unique plant diversity. "
              ."Membership fees only pay the cost of service to members. Please support our projects by adding a tax-receiptable donation.</p>"
              ."<p class='right_p' ><b>&#9744; I would like to add a one-time donation of $ _______</b></p>"
              ."<p class='right_p' style='font-size:7pt'>(Flip to the other side to make a monthly donation.)</p>")
            : ("<p class='right_p' style='font-weight:bold;'>Faites un don</p>"
              ."<p class='right_p'>Nous comptons sur votre g&eacute;n&eacute;rosit&eacute; pour contribuer &agrave; la sauvegarde "
              ."de notre diversit&eacute; horticole. Le montant de l'adh&eacute;sion ne couvre que"
              ."le service aux membres. Soutenez nos projets en faisant un don.</p>"
              ."<p class='right_p' ><b>&#9744; Je souhaite faire un don unique de $ _______</b></p>"
              ."<p class='right_p' style='font-size:7pt'>(Pour offrir des dons sur une base mensuelle, voir au verso.)</p>");

$sAddrChanged = $lang=='EN' ? "Has your address or contact information changed?"
                            : "Votre adresse ou vos coordonn&eacute;es ont-elles chang&eacute;?";
$sEmail       = $lang=='EN' ? "Email": "Courriel";
$sPhone       = $lang=='EN' ? "Phone": "T&eacute;l&eacute;phone";
$sMember      = $lang=='EN' ? "Member" : "Membre";

$sFooter      = $lang=='EN' ? "Seeds of Diversity is a registered charitable organization. We provide receipts for donations of $20 and over. Our charitable registration number is 89650 8157 RR0001."
                            : "Les Semences du patrimoine sont un organisme de bienfaisance enregistr&eacute;. Nous faisons parvenir un re&ccedil;u &agrave; fins d'imp&ocirc;t pour tous les dons de 20 $ et plus.<br/>Notre num&eacute;ro d'enregistrement est 89650 8157 RR0001";

//<img style='float:right;width:0.75in' src='http://seeds.ca/i/img/logo/logoA_v-en-bw-300x.png'/>
$sTmpl = $sLocalStyle."
<img style='position:absolute;top:0.125in;right:0.3in;width:0.75in' src='https://seeds.ca/i/img/logo/logoA_v-".($lang=='EN' ? "en":"fr")."-bw-300x.png'/>
<div class='s_title'>[[Var:sTitle]]</div>
<div class='s_form'>
  [[Var:sForm]]
</div>
<div class='s_right' style='position:absolute;right:0.375in;top:1.125in;width:4.25in'>
  [[Var:sRight]]
  <div style='border:1px solid #aaa;background-color:#f4f4f4;margin:0 0.125in 0 0.75in;padding:0.125in'>
    <div>$sAddrChanged</div>
    <div style='margin-top:0.125in'>
    $sEmail: [[email]]<br/>
    $sPhone: [[phone]]</div>
  </div>
  <div style='font-size:8pt;margin:0.05in 0.125in 0 0;float:right'>$sMember [[_key]]</div>
</div>
<div class='s_note' style='position:absolute;bottom:0.125in;left:0.325in;text-alignment:left'>
  $sFooter
</div>
";

$s = str_replace( "[[Var:sTitle]]", $sTitle, $sTmpl );
$s = str_replace( "[[Var:sForm]]", $sForm, $s );
$s = str_replace( "[[Var:sRight]]", $sRight, $s );


        return( $s );
    }
}
