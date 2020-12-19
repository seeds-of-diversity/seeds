<?php

/* mbrPrint
 *
 * Copyright 2020 Seeds of Diversity Canada
 *
 * App that prints membership and donation slips, and donation receipts
 */

include_once( SEEDROOT."Keyframe/KeyframeUI.php" );
include_once( SEEDCORE."SEEDPrint.php" );
include_once( SEEDAPP."mbr/mbrApp.php" );
include_once( SEEDCORE."console/console02.php" );
include_once( SEEDLIB."mbr/MbrContacts.php" );

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
                                        'donations'        => ['label'=>'Donations'],
                                        'donationsSL'      => ['label'=>'Seed Library Adoptions'],
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


if( SEEDInput_Str('cmd') == 'printDonationReceipt' ) {
    include_once( SEEDLIB."SEEDTemplate/masterTemplate.php" );

    if( !($rngReceipt = SEEDInput_Str('donorReceiptRange')) ) {
        $oApp->oC->AddErrMsg( 'Enter a receipt number' );
        goto printDonationReceiptAbort;
    }

    $oMT = new SoDMasterTemplate( $oApp, ['raSEEDTemplateMakerParms'=>['fTemplates'=>[SEEDAPP."templates/donation_receipt.html"]]] );

    list($raReceipts) = SEEDCore_ParseRangeStr( $rngReceipt );

    $oContacts = new Mbr_Contacts( $oApp );
    $sBody = $oMT->GetTmpl()->ExpandTmpl( 'donation_receipt_page', [] );;
    foreach( $raReceipts as $nReceipt ) {
        if( !($kfr = $oContacts->oDB->GetKFRCond('DxM', "receipt_num='$nReceipt'")) ) {
            $sBody .= "<div class='donReceipt_page'>Unknown receipt number $nReceipt</div>";
            continue;
        }

// use MbrContacts::DrawAddressBlock
        $vars = [
            'donorName' => $kfr->Expand("[[M_firstname]] [[M_lastname]]")
                          .( ($name2 = trim($kfr->Expand("[[M_firstname2]] [[M_lastname2]]"))) ? " &amp; $name2" : "")
                          .$kfr->ExpandIfNotEmpty('M_company', "<br/>[[]]"),
            'donorAddr' => $kfr->Expand("[[M_address]]<br/>[[M_city]] [[M_province]] [[M_postcode]]"),
            'donorReceiptNum' => $nReceipt,
            'donorAmount'  => $kfr->Value('amount'),
            'donorDateReceived' => $kfr->Value('date_received'),
            'donorDateIssued' => $kfr->Value('date_issued')
        ];

        $sBody .= $oMT->GetTmpl()->ExpandTmpl( 'donation_receipt_page', $vars );
    }

    $sHead = "";
    echo Console02Static::HTMLPage( SEEDCore_utf8_encode($sBody), $sHead, 'EN', ['bBootstrap'=>false] );   // sCharset defaults to utf8

    exit;

    printDonationReceiptAbort:
}


$o3UpDonors = new Mbr3UpDonors( $oApp, $kfdb, $yCurr );
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
    $raRows = [ ['firstname'=>'nobody'], ['firstname'=>'nobody'], ['firstname'=>'nobody'] ] + $raRows;
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
    private $o3UpMbr;
    private $o3UpDonors;
    private $oContacts;

    function __construct( SEEDAppConsole $oApp )
    {
        global $consoleConfig, $o3UpMbr, $o3UpDonors;
        parent::__construct( $oApp->oC, $consoleConfig['TABSETS'] );

        $this->oApp = $oApp;
        $this->o3UpMbr = $o3UpMbr;
        $this->o3UpDonors = $o3UpDonors;

        $this->oContacts = new Mbr_Contacts( $oApp );
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
        $s = $this->oContacts->BuildDonorTable();

        $s .= "<form target='_blank'>
              <input type='hidden' name='cmd' value='printDonationReceipt'>
              <input type='text' name='donorReceiptRange'/>
              <input type='submit' value='Make Receipt'/>
              </form>";

        return( $s );
    }

    function TabSet_main_donations_Init()         { $this->oW = new MbrDonationsListForm( $this->oApp ); $this->oW->Init(); }
    function TabSet_main_donations_ControlDraw()  { return( $this->oW->ControlDraw() ); }
    function TabSet_main_donations_ContentDraw()  { return( $this->oW->ContentDraw() ); }
    function TabSet_main_donationsSL_Init()       { $this->oW = new MbrAdoptionsListForm( $this->oApp ); $this->oW->Init(); }
    function TabSet_main_donationsSL_ControlDraw(){ return( $this->oW->ControlDraw() ); }
    function TabSet_main_donationsSL_ContentDraw(){ return( $this->oW->ContentDraw() ); }
}

class MbrDonationsListForm extends KeyframeUI_ListFormUI
{
    function __construct( SEEDAppConsole $oApp )
    {
        $raConfig = [
            'sessNamespace' => "Donations",
            'cid'   => 'D',
            'kfrel' => (new Mbr_Contacts( $oApp ))->oDB->Kfrel('DxM'),
            'KFCompParms' => ['raSEEDFormParms'=>['DSParms'=>['fn_DSPreStore'=> [$this,'dsPreStore']]]],

            'raListConfig' => [
                'bUse_key' => true,     // probably makes sense for KeyFrameUI to do this by default
                'cols' => [
                    [ 'label'=>"k",         'col'=>"_key",          'w'=>30 ],
                    [ 'label'=>"Member",    'col'=>"M__key",        'w'=>80 ],
                    [ 'label'=>"Firstname", 'col'=>"M_firstname",   'w'=>120 ],
                    [ 'label'=>"Lastname",  'col'=>"M_lastname",    'w'=>120 ],
                    [ 'label'=>"Company",   'col'=>"M_company",     'w'=>120 ],
                    [ 'label'=>"Received",  'col'=>"date_received", 'w'=>120 ],
                    [ 'label'=>"Amount",    'col'=>"amount",        'w'=>120 ],
                    [ 'label'=>"Issued",    'col'=>"date_issued",   'w'=>120 ],
                    [ 'label'=>"Receipt #", 'col'=>"receipt_num",   'w'=>120 ],
                ],
               // 'fnRowTranslate' => [$this,"listRowTranslate"],
            ],

            'raSrchConfig' => [
                'filters' => [
                    ['label'=>'First name',    'col'=>'M.firstname'],
                    ['label'=>'Last name',     'col'=>'M.lastname'],
                    ['label'=>'Company',       'col'=>'M.company'],
                    ['label'=>'Member #',      'col'=>'M._key'],
                    ['label'=>'Amount',        'col'=>'amount'],
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
    }

    function ControlDraw()
    {
        return( $this->DrawSearch() );
    }

    function ContentDraw()
    {
        $s = $this->DrawStyle()
           ."<style>.donationFormTable td { padding:3px;}</style>"
           ."<div>".$this->DrawList()."</div>"
           ."<div style='margin-top:20px;padding:20px;border:2px solid #999'>".$this->DrawForm()."</div>";

        return( $s );
    }

    function donationForm( KeyframeForm $oForm )
    {
        $sReceiptInstructions = "<p style='font-size:x-small'>" //Receipt Instructions:<br/>"
                               ."-1 = no receipt, see note<br/>-2 = no receipt, below threshold<br/>-3 = Canada Helps</p>";

        $s = "|||TABLE( || class='donationFormTable' width='100%' border='0')"
            ."||| *Member*     || [[text:fk_mbr_contacts|size=30]]"
            ." || *Amount*     || [[text:amount|size=30]]"
            ." || *Received*   || [[text:date_received|size=30]]"
            ."||| &nbsp        || &nbsp;"
            ." || *Receipt #*  || [[text:receipt_num|size=30]]"
            ." || *Issued*     || [[text:date_issued|size=30]]"
            ."||| *Notes*      || {colspan='1'} ".$oForm->TextArea( "notes", ['width'=>'90%','nRows'=>'2'] )
            ." || &nbsp; || ".$sReceiptInstructions
            ." || &nbsp; || ".$this->donationData( $oForm->Value('_key'), $oForm->Value('fk_mbr_contacts') )
            ."|||ENDTABLE"
            ."[[hiddenkey:]]"
            ."<input type='submit' value='Save'>";

        return( $s );
    }

    private function donationData( $kDonation, $kMbr )
    {
        $s = "";

        $ra = $this->oApp->kfdb->QueryRA( "SELECT * FROM {$this->oApp->GetDBName('seeds2')}.mbr_contacts WHERE _key='$kMbr'" );
        $s .= "<p>Mbr database:<br/>donation_receipt: {$ra['donation_receipt']}</p>";

        return( $s );
    }
}

include_once( SEEDLIB."sl/sldb.php" );
class MbrAdoptionsListForm extends KeyframeUI_ListFormUI
{
    function __construct( SEEDAppConsole $oApp )
    {
        $raConfig = [
            'sessNamespace' => "Donations",
            'cid'   => 'D',
            'kfrel' => (new Mbr_Contacts($oApp))->oDB->Kfrel('AxM_D_P'),
            'KFCompParms' => ['raSEEDFormParms'=>['DSParms'=>['fn_DSPreStore'=> [$this,'dsPreStore']]]],

            'raListConfig' => [
                'bUse_key' => true,     // probably makes sense for KeyFrameUI to do this by default
                'cols' => [
                    [ 'label'=>"k",         'col'=>"_key",          'w'=>30 ],
                    [ 'label'=>"Donor",     'col'=>"M_lastname",    'w'=>80 ],
                    [ 'label'=>"Request",   'col'=>"sPCV_request",  'w'=>80 ],

/*
                    [ 'label'=>"Member",    'col'=>"M__key",        'w'=>80 ],
                    [ 'label'=>"Firstname", 'col'=>"M_firstname",   'w'=>120 ],
                    [ 'label'=>"Lastname",  'col'=>"M_lastname",    'w'=>120 ],
                    [ 'label'=>"Company",   'col'=>"M_company",     'w'=>120 ],
                    [ 'label'=>"Received",  'col'=>"date_received", 'w'=>120 ],
                    [ 'label'=>"Amount",    'col'=>"amount",        'w'=>120 ],
                    [ 'label'=>"Issued",    'col'=>"date_issued",   'w'=>120 ],
                    [ 'label'=>"Receipt #", 'col'=>"receipt_num",   'w'=>120 ],
*/
                ],
               // 'fnRowTranslate' => [$this,"listRowTranslate"],
            ],

            'raSrchConfig' => [
                'filters' => [
                    ['label'=>'First name',    'col'=>'M.firstname'],
                    ['label'=>'Last name',     'col'=>'M.lastname'],
                    ['label'=>'Company',       'col'=>'M.company'],
                    ['label'=>'Member #',      'col'=>'M._key'],
                    ['label'=>'Amount',        'col'=>'amount'],
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
    }

    function ControlDraw()
    {
        return( $this->DrawSearch() );
    }

    function ContentDraw()
    {
        $s = $this->DrawStyle()
           ."<style>.donationFormTable td { padding:3px;}</style>"
           ."<div>".$this->DrawList()."</div>"
           ."<div style='margin-top:20px;padding:20px;border:2px solid #999'>".$this->DrawForm()."</div>";

        return( $s );
    }

    function donationForm( KeyframeForm $oForm )
    {
        $sReceiptInstructions = "<p style='font-size:x-small'>" //Receipt Instructions:<br/>"
                               ."-1 = no receipt, see note<br/>-2 = no receipt, below threshold<br/>-3 = Canada Helps</p>";

        $s = "|||TABLE( || class='donationFormTable' width='100%' border='0')"
            ."||| *Member*     || [[text:fk_mbr_contacts|size=30]]"
            ." || *Amount*     || [[text:amount|size=30]]"
            ." || *Received*   || [[text:date_received|size=30]]"
            ."||| &nbsp        || &nbsp;"
            ." || *Receipt #*  || [[text:receipt_num|size=30]]"
            ." || *Issued*     || [[text:date_issued|size=30]]"
            ."||| *Notes*      || {colspan='1'} ".$oForm->TextArea( "notes", ['width'=>'90%','nRows'=>'2'] )
            ." || &nbsp; || ".$sReceiptInstructions
            ." || &nbsp; || ".$this->donationData( $oForm->Value('_key'), $oForm->Value('fk_mbr_contacts') )
            ."|||ENDTABLE"
            ."[[hiddenkey:]]"
            ."<input type='submit' value='Save'>";

        return( $s );
    }

    private function donationData( $kDonation, $kMbr )
    {
        $s = "";

        $ra = $this->oApp->kfdb->QueryRA( "SELECT * FROM {$this->oApp->GetDBName('seeds2')}.mbr_contacts WHERE _key='$kMbr'" );
        $s .= "<p>Mbr database:<br/>donation_receipt: {$ra['donation_receipt']}</p>";

        return( $s );
    }
}

$oCTS = new MyConsole02TabSet( $oApp );

$sBody = $oApp->oC->DrawConsole( "[[TabSet:main]]".$sBody, ['oTabSet'=>$oCTS] );


echo Console02Static::HTMLPage( SEEDCore_utf8_encode($sBody), $sHead, 'EN', ['consoleSkin'=>'green'] );   // sCharset defaults to utf8


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
<img style='position:absolute;top:0.125in;right:0.3in;width:0.75in' src='http://seeds.ca/i/img/logo/logoA_v-".($lang=='EN' ? "en":"fr")."-bw-300x.png'/>
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
    private $kfdb;
    private $lang = "EN";
    private $year;

    private $raDonorEN, $raDonorFR, $raNonDonorEN, $raNonDonorFR;

    private $raDonor, $raNonDonor;

    private $raData = array();

    function __construct( SEEDAppConsole $oApp, KeyFrameDatabase $kfdb, $year )
    {
        $this->oApp = $oApp;
        $this->kfdb = $kfdb;
        $this->year = $year;
    }

    function GetMode()  { return( $this->mode ); }

    function GetRows()
    {
        return( $this->mode && isset($this->raData[$this->mode]) ? $this->raData[$this->mode] : array() );
    }

    function OptionsForm()
    {
        $s = "";

        return( $s );
    }

    function Load()
    {
        $this->mode = SEEDInput_Smart( 'mode', array( '', 'donorEN','donor100EN','donor99EN','donorFR','donor100FR','donor99FR','nonDonorEN','nonDonorFR' ) );
        $this->lang = substr( $this->mode, -2 ) ?: 'EN';

        if( $this->mode == 'details' ) {
            //$this->kfdb->SetDebug(2);
        }

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
            $this->raData[$k] = $this->kfdb->QueryRowsRA("SELECT * FROM {$dbname2}.mbr_contacts WHERE $dGlobal AND ".implode(' AND ',$ra['cond'])." order by {$ra['order']}" );
            // now for each row in the result, insert the address block in the row
            $ra1 = array();
            foreach( $this->raData[$k] as $k1=>$ra1 ) {
                $this->raData[$k][$k1]['SEEDPrint:addressblock'] = SEEDCore_utf8_encode(MbrDrawAddressBlockFromRA( $ra1 ));
            }
        }
    }

    function ShowDetails()
    {
        $s = "<p>Donors English: ".count($this->raDonorEN)." / ".count($this->raData['donorEN'])." - $100/$99 = ".count($this->raData['donor100EN'])."/".count($this->raData['donor99EN'])."</p>"
            ."<p>Donors French: ".count($this->raDonorFR)." / ".count($this->raData['donorFR'])." - $100/$99 = ".count($this->raData['donor100FR'])."/".count($this->raData['donor99FR'])."</p>"
            ."<p>Non-donor Members English: ".count($this->raNonDonorEN)." / ".count($this->raData['nonDonorEN'])."</p>"
            ."<p>Non-donor Members French: ".count($this->raNonDonorFR)." / ".count($this->raData['nonDonorFR'])."</p>"
            ."<p>&nbsp</p>"
            ."<p>English: ".(count($this->raDonorEN)+count($this->raNonDonorEN))."</p>"
            ."<p>French: ".(count($this->raDonorFR)+count($this->raNonDonorFR))."</p>";

        $raGroups = $this->getGroups();
        $s .= "<table border='1'>";
        foreach( [ ['donorEN','donor100EN','donor99EN'],['donorFR','donor100FR','donor99FR'],['nonDonorEN','nonDonorFR'] ]  as $raG ) {
            $s .= "<tr>";
            foreach( $raG as $k ) {
                $s .= "<td valign='top'><h3>{$raGroups[$k]['title']}</h3>"
                     ."<p>".count($this->raData[$k])."</p>"
                     .$this->drawButton( $k )
                     ."<p style='font-size:9pt'>".implode( "<br/>", $raGroups[$k]['cond'])."</p>"
                     ."</td>";
            }
            $s .= "</tr>";
        }
        $s .= "</table>";



        $sLine = "<tr><td>[[firstname]] [[lastname]] [[company]]</td><td>[[donation]]</td><td>[[donation_date]]</td></tr>";

        $s .= "<h3>Donors English</h3>"
             ."<table border='1'>".SEEDCore_ArrayExpandRows( $this->raData['donorEN'], $sLine )."</table>"
             ."<h3>Donors French</h3>"
             ."<table border='1'>".SEEDCore_ArrayExpandRows( $this->raData['donorFR'], $sLine )."</table>"
             ."<h3>Non-Donors English</h3>"
             ."<table border='1'>".SEEDCore_ArrayExpandRows( $this->raData['nonDonorEN'], $sLine )."</table>"
             ."<h3>Non-Donors French</h3>"
             ."<table border='1'>".SEEDCore_ArrayExpandRows( $this->raData['nonDonorFR'], $sLine )."</table>"
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
$sWantOneTime = $lang=='EN' ? "I want to make a one-time donation of" : "Je d&eacute;sire faire un don unique de";
$sWantMonthly = $lang=='EN' ? "I want to make a monthly donation of" : "Je d&eacute;sire faire un une contribution <u>mensuelle</u> de";
$sOther       = $lang=='EN' ? "Other" : "Autre";

$sRight       = $lang=='EN' ? "<p>Your charitable donation this year will help save hundreds of rare plant varieties next year.
  Seeds of Diversity will use your donation to find seeds that need rescuing, and organize seed savers across the country to grow them in 2020.</p>
  <p>You can also make your donation online at <b><u>www.seeds.ca/donate</u></b>.</p>"
                            : "<p>Votre don de charit&eacute; de cette ann&eacute;e aidera &agrave; sauver des centaines de vari&eacute;t&eacute;s rares l'an prochain.
  Semences du patrimoine utilisera votre don pour trouver des semences qui ont besoin d'&ecirc;tre secourues, et pour trouver des conservateurs de semences &agrave; travers le Canada afin de les cultiver en 2020.</p>
  <p>Vous pouvez &eacute;galement faire un don en ligne au <b><u>www.semences.ca/don</u></b>.</p>";

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
<img style='position:absolute;top:0.125in;right:0.3in;width:0.75in' src='http://seeds.ca/i/img/logo/logoA_v-".($lang=='EN' ? "en":"fr")."-bw-300x.png'/>
<div class='s_title'>$sTitle</div>
<div class='s_form'>
  <table>
  <tr><td>&#9744; $sWantOneTime</td><td>&#9744; $20</td><td>&#9744; $50</td><td>&#9744; $100</td><td>&#9744; $200</td><td>&#9744; $sOther <span style='text-decoration: underline; white-space: pre;'>          </span></td></tr>
  <tr><td>&#9744; $sWantMonthly</td><td>&#9744; $10</td><td>&#9744; $20</td><td colspan='2'>&#9744; $sOther <span style='text-decoration: underline; white-space: pre;'>           </span></td></tr>
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
<img style='position:absolute;top:0.125in;right:0.3in;width:0.75in' src='http://seeds.ca/i/img/logo/logoA_v-".($lang=='EN' ? "en":"fr")."-bw-300x.png'/>
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
