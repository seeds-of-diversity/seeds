<?php

/* Temporary location for mbrOrder fulfilment code.
 * This should all be converted to use SEEDBasket and its fulfilment system.
 */

include_once( SEEDLIB."q/QServerBasket.php" );
include_once( SEEDAPP."basket/basketProductHandlers_seeds.php" );


class SodOrder
{
    public $oApp;
    private $kfrel;

    function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;
        $this->kfrel = new Keyframe_Relation( $oApp->kfdb, $this->kdefOrder(), $oApp->sess->GetUID(),
                                              ['logfile' => $oApp->logdir."SodBasketFulfil.log"] );
    }

    function KfrelOrder() { return( $this->kfrel ); }

    private function kdefOrder()
    { return(
        [ "Tables" => [ "O" => [ "Table" => "{$this->oApp->GetDBName('seeds1')}.mbr_order_pending",
                                 "Fields" => array( array("col"=>"mail_firstname",  "type"=>"S"),
                                                    array("col"=>"mail_lastname",   "type"=>"S"),
                                                    array("col"=>"mail_company",    "type"=>"S"),
                                                    array("col"=>"mail_addr",       "type"=>"S"),
                                                    array("col"=>"mail_city",       "type"=>"S"),
                                                    array("col"=>"mail_prov",       "type"=>"S"),
                                                    array("col"=>"mail_postcode",   "type"=>"S"),
                                                    array("col"=>"mail_country",    "type"=>"S"),
                                                    array("col"=>"mail_phone",      "type"=>"S"),
                                                    array("col"=>"mail_email",      "type"=>"S"),
                                                    array("col"=>"mail_lang",       "type"=>"I"),
                                                    array("col"=>"mail_eBull",      "type"=>"I", "default"=>1),
                                                    array("col"=>"mail_where",      "type"=>"S"),
                                                    array("col"=>"mbr_type",        "type"=>"S"),
                                                    array("col"=>"donation",        "type"=>"F"),
                                                    array("col"=>"pub_ssh_en",      "type"=>"I"),
                                                    array("col"=>"pub_ssh_fr",      "type"=>"I"),
                                                    array("col"=>"pub_nmd",         "type"=>"I"),
                                                    array("col"=>"pub_shc",         "type"=>"I"),
                                                    array("col"=>"pub_rl",          "type"=>"I"),
                                                    array("col"=>"notes",           "type"=>"S"),
                                                    array("col"=>"pay_total",       "type"=>"F"),
                                                    //array("col"=>"pay_type",        "type"=>"I"),
                                                    //array("col"=>"pay_status",      "type"=>"I"),
                                                    array("col"=>"pp_name",         "type"=>"S"),
                                                    array("col"=>"pp_txn_id",       "type"=>"S"),
                                                    array("col"=>"pp_receipt_id",   "type"=>"S"),
                                                    array("col"=>"pp_payer_email",  "type"=>"S"),
                                                    array("col"=>"pp_payment_status","type"=>"S"),
                                                    array("col"=>"eStatus",         "type"=>"S", "default"=>'New'),
                                                    array("col"=>"eStatus2",        "type"=>"I"),
                                                    array("col"=>"dMailed",         "type"=>"S"),
                                                    array("col"=>"bDoneAccounting", "type"=>"I"),
                                                    array("col"=>"bDoneRecording",  "type"=>"I"),
                                                    array("col"=>"kBasket",         "type"=>"I"),
                                                    array("col"=>"ePayType",        "type"=>"S", "default"=>'PayPal'),
                                                    ['col'=>"depositCode",          'type'=>'S'],
                                                    array("col"=>"sExtra",          "type"=>"S") )
        ]]] );
    }
}


class SodOrderFulfil
{
    public $oApp;
    protected $oOrder;
    protected $oSoDBasket;

    function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;
        $this->oOrder = new SodOrder( $oApp );
        $this->oSoDBasket = new SoDOrderBasket( $oApp );
    }

    public function KfrelOrder() { return( $this->oOrder->KfrelOrder() ); }

    function SetOrderStatus( KeyframeRecord $kfr, $eStatus )
    {
        $kfr->SetValue( 'eStatus', $eStatus );
        return( $kfr->PutDBRow() );
    }

    function GetBasketKFR( $kB )
    {
        return( $this->oSoDBasket->GetBasketKFR( $kB ) );
    }

/*
    function SetMailedToday( KeyframeRecord $kfr )
    {
        $kfr->SetValue( "eStatus2", 1 );
        $kfr->SetValue( "dMailed", date('Y-m-d') );
        return( $kfr->PutDBRow() );
    }

    function SetMailedNothing( KeyframeRecord $kfr )
    {
        $kfr->SetValue( "eStatus2", 2 );
        return( $kfr->PutDBRow() );
    }
*/

    function GetMailStatus_Pending( KeyframeRecord $kfr )  { return( $kfr->Value('eStatus2') == 0 ); }
    function GetMailStatus_Sent( KeyframeRecord $kfr )     { return( $kfr->Value('eStatus2') == 1 ); }
    function GetMailStatus_Nothing( KeyframeRecord $kfr )  { return( $kfr->Value('eStatus2') == 2 ); }
}


class SodOrderFulfilUI extends SodOrderFulfil
{
    private $pRow = 0;          // deprecate since controls are by ajax now
    public $pAction = "";
    public $fltStatus = "";
    public $fltYear = 0;
    private $yCurrent;

    function __construct( SEEDAppConsole $oApp )
    {
        parent::__construct( $oApp );

        $this->yCurrent = intval(date("Y"));

        $this->pRow = SEEDInput_Int( 'row' );
        $this->pAction = SEEDInput_Str( 'action' );

        // Filters
        if( !($this->fltStatus = $oApp->sess->SmartGPC( 'fltStatus', [ "", "All", "Not-accounted", "Not-recorded", "Not-mailed", "Bob",
                                                                       MBRORDER_STATUS_FILLED, MBRORDER_STATUS_CANCELLED ] )) )
        {
            // "" defaults to either Not-accounted or Not-mailed depending on whom you are
            $this->fltStatus = $this->oApp->sess->GetUID() == 4879 ? "Not-accounted" : "Not-recorded";
        }
        if( !($this->fltYear = intval($oApp->sess->SmartGPC( 'fltYear', array() ))) ) {
            $this->fltYear = $this->yCurrent;
        }
    }

    function GetCurrOrderKey()  { return( $this->pRow ); }      // deprecate since controls are by ajax now

    function Style()
    {
        return( "
            <style>
            </style>
        ");
    }

    function ProcessCmd( $cmd, $raParms )
    /************************************
        Process jx commands for the SoDBasket UI
     */
    {
        /* handle basic sb- commands
         *
         * fulfil_uiSeller = seller(s) whose products are available in this fulfilment tool
         *                   SoD is the only one using this tool, but other tools can be for other sellers/fulfillers
         */
        $rQ = (new QServerBasket( $this->oApp, ['fulfil_uidSeller'=>1] ))->Cmd( $cmd, $raParms );
        if( $rQ['bHandled'] ) goto done;

        $rQ = SEEDQ::GetEmptyRQ();
        $rQ['bHandled'] = true;
        switch( $cmd ) {
            default:
                $rQ['bHandled'] = false;
                break;
        }

        done:
        return( $rQ );
    }


    function DrawFormFilters()
    {
        $s = "";

        $raYearOpt = array();
        for( $y = $this->yCurrent; $y > 2010; --$y ) {
            $raYearOpt[strval($y)] = $y;
        }
        $raYearOpt["2010 and before"] = 2010;

        $s .= "<form action='".$this->oApp->PathToSelf()."'>"
             ."<p>Show: "
             .SEEDForm_Select2( 'fltStatus',
                        array( "Not Accounted" => "Not-accounted",
                               "Not Recorded"  => "Not-recorded",
                               "Not Mailed"    => "Not-mailed",
                               "Bob's Review"  => 'Bob',
                               "Filled"        => MBRORDER_STATUS_FILLED,
                               "Cancelled"     => MBRORDER_STATUS_CANCELLED,
                               "All"           => "All",
                        ),
                        $this->fltStatus,
                        array( "selectAttrs" => "onChange='submit();'" ) );
        if( in_array( $this->fltStatus, ["All", 'Bob', MBRORDER_STATUS_FILLED, MBRORDER_STATUS_CANCELLED] ) ) {
            $s .= SEEDCore_NBSP("",5)
                 .SEEDForm_Select2( 'fltYear',
                            $raYearOpt,
                            $this->fltYear,
                            array( "selectAttrs" => "onChange='submit();'" ) );
        }
        $s .= "</p></form>";

        return( $s );
    }

    function GetFilterDetails()
    /**************************
        Details about the currently selected filter
     */
    {
        $label = $cond = "";
        $bSortDown = false;

        switch( $this->fltStatus ) {
            case MBRORDER_STATUS_FILLED:
                $label = "Filled {$this->fltYear}";
                $cond = "eStatus='{$this->fltStatus}' AND eStatus2<>'0' AND ".$this->getYearCond();
                $bSortDown = true;
                break;
            case MBRORDER_STATUS_CANCELLED:
                $label = "Cancelled {$this->fltYear}";
                $cond = "eStatus='{$this->fltStatus}' AND ".$this->getYearCond();
                $bSortDown = true;
                break;
            case "Not-accounted":
                $label = "Non-Accounted";
                $cond = "eStatus <> '".MBRORDER_STATUS_CANCELLED."' AND bDoneAccounting=0";
                $bSortDown = false;
                break;
            case "Not-recorded":
                $label = "Non-Recorded";
                $cond = "eStatus NOT IN ('".MBRORDER_STATUS_FILLED."','".MBRORDER_STATUS_CANCELLED."') AND NOT bDoneRecording";
                $bSortDown = false;
                break;
            case "Not-mailed":
                $label = "Non-Mailed";
                $cond = "eStatus2='0' AND eStatus NOT IN ('".MBRORDER_STATUS_NEW."','".MBRORDER_STATUS_CANCELLED."')";
                $bSortDown = false;
                break;
            case 'Bob':
                $label = "Bob's Review";
                $cond = $this->getYearCond()." AND eStatus='Paid'";
                $bSortDown = true;
                break;
            case "All":
            default:
                $label = "All {$this->fltYear}";
                $cond = $this->getYearCond();
                $bSortDown = true;
                break;
        }

        return( [$label, $cond, $bSortDown] );
    }

    private function getYearCond()
    {
        $y = $this->fltYear;

        if( !$y )  return( "1=1" );

        if( $y <= 2010 )  return( "year(_created) <= '2010'" );

        return( "year(_created)='$y'" );
    }


    function DrawOrderSummaryRow( KeyframeRecord $kfr, $sConciseSummary, $raOrder )
    {
        $s = "";

$uidSeller = 1;     // only showing SoD's part of the basket

        $bPaid = in_array( $kfr->value('eStatus'), [MBRORDER_STATUS_PAID,MBRORDER_STATUS_FILLED] );

        list($sContents,$oB) = $this->oSoDBasket->ShowBasketWidget( $kfr->Value('kBasket'), $bPaid ? 'ReadonlyStatus' : 'Readonly' );

        $fTotal = $oB->GetTotal( $uidSeller );

        // Donations with kRef=0 are not recorded in mbr_donations yet. All Paid donations must be recorded there, even if non-receiptable.
        $bDonNotRecorded = false;
        $raPur = $oB->GetPurchasesInBasket();
        foreach( $raPur as $oPur ) {
            if( $oPur->GetProductType()=='donation' && !$oPur->GetKRef() ) {
                $bDonNotRecorded = true;
                break;
            }
        }

        // if there is a membership or donation in this order we'll require the member to be recorded
        $bContactNeeded = !$oB->GetBuyer() &&
                          (in_array( 'membership', $oB->GetProductTypesInBasket() ) ||
                           in_array( 'donation',   $oB->GetProductTypesInBasket() ));

        // kluge Bob Review by skipping rows that don't meet the criteria
        if( $this->fltStatus == 'Bob' ) {
            if( !$bContactNeeded && !$bDonNotRecorded && $fTotal == $kfr->Value('pay_total') ) goto done;   // this row does not need review
        }

        switch( $kfr->value('eStatus') ) {
            case MBRORDER_STATUS_NEW:       $style = "style='background-color:white'";            break;
            case MBRORDER_STATUS_PAID:      $style = "style='background-color:#ffffd8'";            break;
            case MBRORDER_STATUS_FILLED:    $style = "style='color:green;background-color:#efe'";   break;
            case MBRORDER_STATUS_CANCELLED: $style = "style='color:#844; background-color:#fff0f0'";   break;
            default:                        $style = "";
        }

// kluge to make the membership labels easier to differentiate
$sConciseSummary = str_replace( "One Year Membership with on-line Seed Directory", "One Year Membership", $sConciseSummary );
$sConciseSummary = str_replace( "One Year Membership with printed and on-line Seed Directory", "One Year Membership with printed Seed Directory", $sConciseSummary );


    $kMbr = @$ra['mbrid'] ?: 0;

    $sOrderNum
        = $kfr->Expand( "[[_key]]"
                       ."<div style='float:right; padding-right:15px'>".substr($kfr->value('_created'),0,10)."</div>"
                       ."<br/><br/>"
                       ."<div><form action='https://seeds.ca/office/mbr/mbr_labels.php' target='MbrLabels' method='get'>"
                           ."<input type='hidden' name='orderadd' value='[[_key]]'/><input type='submit' value='Add to Label Maker'/>"
                       ."</form></div>"
                       ."<div class='mbrOrderShowTicket' data-kOrder='[[_key]]' data-expanded='0' style='text-align:center;font-size:14pt;padding-top:15px'><a>Show Ticket</a></div>" );

    $sName
        = $kfr->Expand( "[[mail_firstname]] [[mail_lastname]]<br/>[[mail_company]]<br/>" )
         .$kfr->ExpandIfNotEmpty( 'pp_name', "([[]] on credit card)<br/>" )
         .($kMbr ? "<br/>Member $kMbr" : "");

    $sAddress
        = $kfr->Expand( "[[mail_addr]]<br/>[[mail_city]] [[mail_prov]] [[mail_postcode]]" )
         .($kfr->Value('mail_country') != 'Canada' ? ("<br/>".$kfr->Value('mail_country')) : "" )
         ."<br/>"
         ."<br/>"
         .$kfr->Expand( "[[mail_phone]]<br/>[[mail_email]]" )
         ."<div><a style='cursor:pointer' onclick='".$this->linkSendEmail($kfr)."'>Send Email</a></div>";

    $sEbulletin
        = ($kfr->value('mail_lang') ? 'French' : 'En')
         ."<br/><br/>"
         .($kfr->value('mail_eBull') ? "<span style='color:green'>Y</span>" : "<span style='color:red'>N</span>");

    $sPayment
        = SEEDCore_Dollar($kfr->value('pay_total'))." by ".$kfr->value('ePayType')."<br/>"
         //."<b>".@$mbr_PayStatus[$kfr->value('pay_status')]."</b><br/>"
         //."<b>".(($kfr->value('eStatus')==MBRORDER_STATUS_FILLED && $this->GetMailStatus_Pending($kfr)) ? "Accounted" : $kfr->value('eStatus'))."</b>"
         ."<b>".($kfr->value('eStatus') == 'New' ? "Not paid" : $kfr->value('eStatus'))."</b>"
         //if( $kfr->value('eStatus')=='New' ) {
         //   $s = "<div class='doStatusPaid' data-kOrder='".$kfr->Key()."'><button>Change to Paid</button></div>";
         //}
         .$this->mailStatus( $kfr, $raOrder );

    $sFulfilment
        = $this->doneAccountingButton( $kfr )
         .$this->doneRecordingButton( $kfr );

    $s .= "<tr class='mbro-row' data-kOrder='".$kfr->Key()."'>"
         ."<td valign='top' $style>$sOrderNum</td>"
         ."<td valign='top' $style>$sName</td>"
         ."<td valign='top' $style>$sAddress</td>"
         ."<td valign='top' $style>$sEbulletin</td>"
         ."<td valign='top' $style>$sConciseSummary". /*$this->mailNothingButton( $kfr, $raOrder ).*/ $this->basketContents( $kfr, $sContents, $fTotal, $bContactNeeded )."</td>"
         ."<td valign='top' $style>$sPayment</td>"
         ."<td valign='top' $style>$sFulfilment</td>"
         ."</tr>";

         done:
         return( $s );
    }

    private function linkSendEmail( $kfr )
    {
        $ra = SEEDCore_ParmsURL2RA( $kfr->value('sExtra') );
        $to = @$ra['mbrid'] ?: $kfr->value('mail_email');
        return( "window.open(\"../int/emailme.php?to=$to\",\"_blank\",\"width=900,height=800,scrollbars=yes\")" );
    }

/*
    private function mailNothingButton( KeyframeRecord $kfr, $raOrder )
    [******************************************************************
        Show "Nothing to Mail" button if mail status is 0

        eStatus2 : 0 = not decided
                   1 = mailed and date recorded
                   2 = nothing to mail
     *]
    {
        $s = "";

        if( $this->GetMailStatus_Pending($kfr) && $kfr->Value('eStatus') != MBRORDER_STATUS_CANCELLED &&
            in_array( $this->oApp->sess->GetUID(), array( 1, 1499, 10914 ) ) )  // dev, Bob, Christine
        {
            $kOrder = $kfr->Key();
            $s .= "<div id='status2x_$kOrder' class='status2x'><button>Nothing to mail</button></div>";
        }

        return( $s );
    }
*/

    private function doneAccountingButton( KeyframeRecord $kfr )
    /***********************************************************
     */
    {
        $s = "";

        if( $kfr->Value('bDoneAccounting') ) {
            $s .= "<div>Bookkeeping done</div>";
        } else {
            $s .= "<div class='doAccountingDone' data-kOrder='".$kfr->Key()."'><button>Click when bookkeeping done</button></div>";
        }

        return( $s );
    }
    private function doneRecordingButton( KeyframeRecord $kfr )
    /**********************************************************
     */
    {
        $s = "";

        if( $kfr->Value('bDoneRecording') ) {
            $s .= "<div>Database record done</div>";
        } else {
            $s .= "<div class='doRecordingDone' data-kOrder='".$kfr->Key()."'><button>Click when database record done</button></div>";
        }

        return( $s );
    }


    private function basketContents( KeyframeRecord $kfr, $sContents, $fTotal, $bContactNeeded )
    /*******************************************************************************************
     */
    {
        $s = "";

        $bGood = in_array( $kfr->value('eStatus'), ['Paid','Filled'] );

        if( $bGood && $bContactNeeded ) {
            $s .= "<div class='alert alert-danger'>The contact has to be recorded for this order</div>";
        }

        $c = $bGood ? ($fTotal == $kfr->Value('pay_total') ? 'alert alert-success' : 'alert alert-danger') : "";
        $s .= "<div class='$c'>$sContents</div>";

        if( in_array( $this->oApp->sess->GetUID(), [1, 1499] ) ) { // dev, Bob
// data-kOrder is also present in the enclosing <tr>
            $s .= "<div data-kOrder='{$kfr->Key()}' class='doBuildBasket'><button>rebuild this basket</button></div>";
        }
        return( $s );
    }

    private function mailStatus( KeyframeRecord $kfr, $raOrder )
    {
        $s = "";

/*
        $bMailed = $kfr->Value( 'eStatus2' )==1;
        $bNothingToMail = $kfr->Value( 'eStatus2' )==2;

        if( in_array( $this->oApp->sess->GetUID(), array( 1, 1499, 10914 ) ) ) {    // dev, Bob, Christine
            if( $this->GetMailStatus_Pending($kfr) && $kfr->Value('eStatus') != MBRORDER_STATUS_CANCELLED ) {
                $kOrder = $kfr->Key();
                $s .= "<div id='status2_$kOrder' class='status2'><button>Mail Today</button></div>&nbsp;";
            } else if( $this->GetMailStatus_Sent($kfr) ) {
                $s .= "<br/>Order mailed ".$kfr->Value('dMailed');
            }
        } else {
            if( $this->GetMailStatus_Sent($kfr) || count($raOrder['pubs']) ) {
                $s .= "<br/>Order ".($this->GetMailStatus_Sent($kfr) ? "": "not")." mailed ".$kfr->Value('dMailed');
            }
        }
*/

        if( in_array( $kfr->Value('eStatus'), [MBRORDER_STATUS_NEW, MBRORDER_STATUS_CANCELLED] ) ) {
            goto done;
        }

        done:
        return( $s );
    }
}

class SoDOrderBasket
/*******************
    Manage SoD's SEEDBaskets (with no reference to mbrOrder - use SoDOrder_MbrOrder for transition)
 */
{
    private $oApp;
    private $oSB;

    function __construct( SEEDAppSessionAccount $oApp )
    {
        global $config_KFDB;
        $this->oApp = $oApp;
        $this->oSB = new SEEDBasketCore( $oApp->kfdb, $oApp->sess, $oApp, SEEDBasketProducts_SoD::$raProductTypes,

// SBC should use oApp->logdir instead
            ['logdir'=>$oApp->logdir, 'sbdb'=>'seeds1'] );
    }

    function GetBasketKFR( $kB )
    {
        return( $this->oSB->oDB->GetBasketKFR( $kB ) );
    }

// transitional method - caller should use $oB and DrawBasketWidget directly
    function ShowBasketWidget( int $kB, string $eMode )
    {
        $s = "";

        if( ($oB = new SEEDBasket_Basket($this->oSB, $kB)) ) {
            list($bDummy,$s) = (new SEEDBasketUI_BasketWidget($this->oSB))->DrawBasketWidget( $oB, $eMode, [] );
        }
        return( [$s,$oB] );
    }
}

class SoDOrder_MbrOrder
// Transition from mbrOrder to SoDOrderBasket
{
    private $oApp;
    private $oSB;
    private $oOrder;

    function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;
        $this->oOrder = new SodOrder( $oApp );

        $this->oSB = new SEEDBasketCore( null, null, $oApp, SEEDBasketProducts_SoD::$raProductTypes, ['sbdb'=>'seeds1'] );
    }


    function UpdateBasketsForAllOrders()
    {
        $nUpdated = 0;

        if( ($kfr = $this->oOrder->KfrelOrder()->CreateRecordCursor( "year(_created)='2020'" )) ) {
            while( $kfr->CursorFetch() ) {
                $nUpdated += $this->createFromMbrOrderKfr( $kfr );
            }
        }
        return( $nUpdated );
    }


    function CreateFromMbrOrder( int $kOrder )
    {
        $ok = false;

        if( ($kfr = $this->oOrder->KfrelOrder()->GetRecordFromDBKey( $kOrder )) ) {
            $ok = $this->createFromMbrOrderKfr( $kfr );
        }

        return( $ok ? 1 : 0 );
    }

    private function createFromMbrOrderKfr( $kfrMbrOrder )
    {
        /* SoD products

            INSERT INTO seeds_1.SEEDBasket_Products
                (_key,_created,_created_by,_updated,_updated_by,_status,
                 uid_seller,eStatus,img,v_t1,v_t2,v_t3,sExtra,
                 product_type,quant_type,item_price,item_price_us,bask_quant_min,bask_quant_max,name,title_en,title_fr)
            VALUES
                (NULL,NOW(),1,NOW(),1,0,1,'ACTIVE','','','','','', 'membership','ITEM-1',35,35,1,1,  'mbr1_35',      'Membership 1 Year','Adhesion 1 an'),
                (NULL,NOW(),1,NOW(),1,0,1,'ACTIVE','','','','','', 'membership','ITEM-1',45,45,1,1,  'mbr1_45sed',   'Membership 1 Year with Seed Directory','Adhesion 1 an avec catalogue'),
                (NULL,NOW(),1,NOW(),1,0,1,'ACTIVE','','','','','', 'membership','ITEM-1',100,100,1,1,'mbr3_100',     'Membership 3 Years','Adhesion 3 ans'),
                (NULL,NOW(),1,NOW(),1,0,1,'ACTIVE','','','','','', 'membership','ITEM-1',130,130,1,1,'mbr3_130sed',  'Membership 3 Years with Seed Directory','Adhesion 3 ans avec catalogue'),
                (NULL,NOW(),1,NOW(),1,0,1,'ACTIVE','','','','','', 'donation',  'MONEY',0,0,-1,-1,   'general',      'Donation','Don charitable'),
                (NULL,NOW(),1,NOW(),1,0,1,'ACTIVE','','','','','', 'donation',  'MONEY',0,0,-1,-1,   'seed-adoption','Donation','Don charitable'),
                (NULL,NOW(),1,NOW(),1,0,1,'ACTIVE','','','','','', 'book',      'ITEM-N',15,15,1,-1, 'ssh6en',       'How to Save Your Own Seeds',''),
                (NULL,NOW(),1,NOW(),1,0,1,'ACTIVE','','','','','', 'book',      'ITEM-N',15,15,1,-1, 'ssh6fr',       'La conservation des semences',''),
                (NULL,NOW(),1,NOW(),1,0,1,'ACTIVE','','','','','', 'book',      'ITEM-N',35,35,1,-1, 'everyseed',    'Every Seed Tells a Tale',''),
                (NULL,NOW(),1,NOW(),1,0,1,'ACTIVE','','','','','', 'book',      'ITEM-N',15,15,1,-1, 'chan2012',     'Conserving Native Pollinators',''),
                (NULL,NOW(),1,NOW(),1,0,1,'ACTIVE','','','','','', 'book',      'ITEM-N', 8, 8,1,-1, 'kent2012',     'How to Make a Pollinator Garden',''),

                (NULL,NOW(),1,NOW(),1,0,1,'ACTIVE','','','','','', 'special',   'ITEM-1',15,15,1,1,  'bulbils15',      'Garlic Bulbils',"Bulbilles d'ails")
                ;
         */

        $ok = false;

        $kB = $kfrMbrOrder->Value('kBasket');
        $oB = new SEEDBasket_Basket( $this->oSB, $kB );    // look up the Basket or create an empty one

        if( !$kB ) {
            // fill new basket fields

            foreach( ['buyer_firstname' => 'mail_firstname',
                      'buyer_lastname'  => 'mail_lastname',
                      'buyer_company'   => 'mail_company',
                      'buyer_addr'      => 'mail_addr',
                      'buyer_city'      => 'mail_city',
                      'buyer_prov'      => 'mail_prov',
                      'buyer_postcode'  => 'mail_postcode',
                      'buyer_country'   => 'mail_country',
                      'buyer_email'     => 'mail_email',
                      'buyer_phone'     => 'mail_phone',
                      //'buyer_lang'      => 'mail_lang',
                      'buyer_notes'     => 'notes',
                ] as $k=>$v )
            {
                $oB->SetValue( $k, $kfrMbrOrder->Value($v) );
            }
            $oB->SetValue( 'buyer_lang', $kfrMbrOrder->Value('mail_lang') ? "F" : "E" );   // mail_lang: 0=english, 1=french

            // new basket has the same create date as the mbrOrder - things like donation.date_received depend on this
            $oB->SetValue( '_created', $kfrMbrOrder->Value('_created') );

            $oB->PutDBRow();
            $kB = $oB->Key();

            $kfrMbrOrder->SetValue('kBasket', $kB );
            $kfrMbrOrder->PutDBRow();
        }

        // Copy this field here because the mbrid can be set manually during "Recording" after the basket is created
        $oB->SetValue( 'uid_buyer', $kfrMbrOrder->UrlParmGet('sExtra', 'mbrid') );
        $oB->PutDBRow();


        // Add products in mbrOrder to basket

        $raProdKeys = $oB->GetProductsInBasket( ['returnType'=>'keys'] );

        if( ($m = $kfrMbrOrder->Value('mbr_type')) ) {
            if( ($oP = $this->oSB->FindProduct( "uid_seller='1' AND product_type='membership' AND name='$m'" )) ) {
                if( !in_array($oP->GetKey(), $raProdKeys) ) {
                    $oBP = new SEEDBasket_Purchase( $this->oSB, 0 );
                    $oBP->StorePurchase( $oB, $oP, ['n'=>1] );
                }
            }
        }

        // Donation - General
        if( ($d = floatval($kfrMbrOrder->Value('donation'))) > 0.0 ) {
            if( ($oP = $this->oSB->FindProduct( "uid_seller='1' AND product_type='donation' AND name='general'" )) ) {
                if( !in_array($oP->GetKey(), $raProdKeys) ) {
                    $oBP = new SEEDBasket_Purchase( $this->oSB, 0 );
                    $oBP->StorePurchase( $oB, $oP, ['f'=>$d] );
                }
            }
        }

        // Donation - Seed Adoption
        if( ($d = floatval($kfrMbrOrder->UrlParmGet('sExtra','slAdopt_amount'))) ) {
            if( ($oP = $this->oSB->FindProduct( "uid_seller='1' AND product_type='donation' AND name='seed-adoption'" )) ) {
                if( !in_array($oP->GetKey(), $raProdKeys) ) {
                    $oBP = new SEEDBasket_Purchase( $this->oSB, 0 );
                    $oBP->StorePurchase( $oB, $oP, ['f'=>$d,
                                                    'slAdopt_cv'=>$kfrMbrOrder->UrlParmGet('sExtra','slAdopt_cv'),
                                                    'slAdopt_name'=>$kfrMbrOrder->UrlParmGet('sExtra','slAdopt_name') ] );
                }
            }
        }

        // Books
        foreach( ['nPubSSH-EN6' => 'ssh6en',
                  'nPubSSH-FR6' => 'ssh6fr',
                  'nPubEverySeed' => 'everyseed',
                  'nPubSueChan2012' => 'chan2012',
                  'nPubKent2012' => 'kent2012'] as $nameOld => $nameNew )
        {
            if( ($n = $kfrMbrOrder->UrlParmGet('sExtra', $nameOld)) ) {
                if( ($oP = $this->oSB->FindProduct( "uid_seller='1' AND product_type='book' AND name='$nameNew'" )) ) {
                    if( !in_array($oP->GetKey(), $raProdKeys) ) {
                        $oBP = new SEEDBasket_Purchase( $this->oSB, 0 );
                        $oBP->StorePurchase( $oB, $oP, ['n'=>$n] );
                    }
                }
            }
        }

/*
        // Book shipping
        if( ($d = floatval($kfrMbrOrder->Value('nPubEverySeed_Shipping'))) ) {
            if( ($oP = $this->oSB->FindProduct( "uid_seller='1' AND product_type='book' AND name='shipping" )) ) {
                if( !in_array($oP->GetKey(), $raProdKeys) ) {
                    $oBP = new SEEDBasket_Purchase( $this->oSB, 0 );
                    $oBP->StorePurchase( $oB, $oP, ['f'=>$d] );
                }
            }
        }
*/

        // Garlic bulbils
        if( $kfrMbrOrder->UrlParmGet('sExtra', 'bBulbils15') ) {
            if( ($oP = $this->oSB->FindProduct( "uid_seller='1' AND product_type='special1' AND name='bulbils15'" )) ) {
                if( !in_array($oP->GetKey(), $raProdKeys) ) {
                    $oBP = new SEEDBasket_Purchase( $this->oSB, 0 );
                    $oBP->StorePurchase( $oB, $oP, ['n'=>1] );
                }
            }
        }

        $ok = true;

        return( $ok );
    }

    function RecordDonations( int $kOrder )
    {
        $ok = false;

        /* Must have a valid SEEDBasket with a uid_buyer so that person can be recorded in mbr_donations
         */
        if( ($kfrOrder = $this->oOrder->KfrelOrder()->GetRecordFromDBKey( $kOrder )) &&
            ($kB = $kfrOrder->Value('kBasket')) &&
            ($oB = new SEEDBasket_Basket( $this->oSB, $kB )) &&
            $oB->GetBuyer() )
        {
            // Donations with kRef=0 are not recorded in mbr_donations yet. All Paid donations must be recorded there, even if non-receiptable.
            foreach( $oB->GetPurchasesInBasket() as $oPur ) {
                if( $oPur->GetProductType()=='donation' ) {
                    $ok = ($oPur->Fulfil() == SEEDBasket_Purchase::FULFIL_RESULT_SUCCESS);  // checks IsFulfilled() internally
                }
            }
        }

        return( $ok );
    }
}
