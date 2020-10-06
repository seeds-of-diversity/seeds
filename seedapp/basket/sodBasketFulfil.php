<?php

/* Temporary location for mbrOrder fulfilment code.
 * This should all be converted to use SEEDBasket and its fulfilment system.
 */

include_once( SEEDAPP."basket/basketProductHandlers_seeds.php" );


class SodOrder
{
    public $oApp;
    private $kfrel;

    function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;
        $this->kfrel = new Keyframe_Relation( $oApp->kfdb, $this->kdefOrder, $oApp->sess->GetUID(),
                                              ['logfile' => $oApp->logdir."SodBasketFulfil.log"] );
    }

    function KfrelOrder() { return( $this->kfrel ); }

    private $kdefOrder =
        [ "Tables" => [ "O" => [ "Table" => 'seeds_1.mbr_order_pending',
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
                                                    array("col"=>"sExtra",          "type"=>"S") )
        ]]];
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

    function GetMailStatus_Pending( KeyframeRecord $kfr )  { return( $kfr->Value('eStatus2') == 0 ); }
    function GetMailStatus_Sent( KeyframeRecord $kfr )     { return( $kfr->Value('eStatus2') == 1 ); }
    function GetMailStatus_Nothing( KeyframeRecord $kfr )  { return( $kfr->Value('eStatus2') == 2 ); }
}


class SodOrderFulfilUI extends SodOrderFulfil
{
    private $pRow = 0;
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
        if( !($this->fltStatus = $oApp->sess->SmartGPC( 'fltStatus', [ "", "All", "Not-accounted", "Not-recorded", "Not-mailed",
                                                                       MBRORDER_STATUS_FILLED, MBRORDER_STATUS_CANCELLED ] )) )
        {
            // "" defaults to either Not-accounted or Not-mailed depending on whom you are
            $this->fltStatus = $this->oApp->sess->GetUID() == 4879 ? "Not-accounted" : "Not-recorded";
        }
        if( !($this->fltYear = intval($oApp->sess->SmartGPC( 'fltYear', array() ))) ) {
            $this->fltYear = $this->yCurrent;
        }
    }

    function GetCurrOrderKey()  { return( $this->pRow ); }

    function Style()
    {
        return( "
            <style>
            .SodBasketFulfil_basketContents td { font-size:x-small; }
            </style>
        ");
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
                               "Filled"        => MBRORDER_STATUS_FILLED,
                               "Cancelled"     => MBRORDER_STATUS_CANCELLED,
                               "All"           => "All",
                        ),
                        $this->fltStatus,
                        array( "selectAttrs" => "onChange='submit();'" ) );
        if( in_array( $this->fltStatus, ["All", MBRORDER_STATUS_FILLED, MBRORDER_STATUS_CANCELLED] ) ) {
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
                $cond = "eStatus <> '".MBRORDER_STATUS_CANCELLED."' AND bDoneRecording=0";
                $bSortDown = false;
                break;
            case "Not-mailed":
                $label = "Non-Mailed";
                $cond = "eStatus2='0' AND eStatus NOT IN ('".MBRORDER_STATUS_NEW."','".MBRORDER_STATUS_CANCELLED."')";
                $bSortDown = false;
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

        if( in_array( $kfr->value('eStatus'), [ MBRORDER_STATUS_PAID, MBRORDER_STATUS_FILLED ] ) ) {
            $style = "style='color:green;background-color:#efe'";
        } else {
            $style = "";
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
         ."<b>".(($kfr->value('eStatus')==MBRORDER_STATUS_FILLED && $this->GetMailStatus_Pending($kfr)) ? "Accounted" : $kfr->value('eStatus'))."</b>"
         .$this->paidButton( $kfr )
         .$this->mailStatus( $kfr, $raOrder );

    $sFulfilment
        = $this->doneAccountingButton( $kfr )
         .$this->doneRecordingButton( $kfr );

    $s .= "<tr class='mbro-row' data-kOrder='".$kfr->Key()."'>"
         ."<td valign='top'>$sOrderNum</td>"
         ."<td valign='top' $style>$sName</td>"
         ."<td valign='top'>$sAddress</td>"
         ."<td valign='top'>$sEbulletin</td>"
         ."<td valign='top'>$sConciseSummary".$this->mailNothingButton( $kfr, $raOrder ).$this->basketContents( $kfr )."</td>"
         ."<td valign='top' $style>$sPayment</td>"
         ."<td valign='top' $style>$sFulfilment</td>"
         ."</tr>";

         return( $s );
    }

    private function linkSendEmail( $kfr )
    {
        $ra = SEEDCore_ParmsURL2RA( $kfr->value('sExtra') );
        $to = @$ra['mbrid'] ?: $kfr->value('mail_email');
        return( "window.open(\"../int/emailme.php?to=$to\",\"_blank\",\"width=900,height=800,scrollbars=yes\")" );
    }

    private function paidButton( $kfr )
    {
        $s = "";

        if( $kfr->value('eStatus')=='New' ) {
            $s = "<div class='doStatusPaid' data-kOrder='".$kfr->Key()."'><button>Change to Paid</button></div>";
        }

        return( $s );
    }


    private function mailNothingButton( KeyframeRecord $kfr, $raOrder )
    /******************************************************************
        Show "Nothing to Mail" button if mail status is 0

        eStatus2 : 0 = not decided
                   1 = mailed and date recorded
                   2 = nothing to mail
     */
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


    private function basketContents( KeyframeRecord $kfr )
    /*****************************************************
     */
    {
        $s = "";

        // use this to compute the basket, but only show the details to dev/Bob
        list($sContents,$fTotal,$bContactNeeded,$bDonNotRecorded) = $this->oSoDBasket->ShowBasketContents( $kfr->Value('kBasket') );

        if( $bContactNeeded && $kfr->value('eStatus') != 'Cancelled' ) {
            $s .= "<div class='alert alert-danger'>The contact has to be recorded for this order</div>";
        }

        if( in_array( $this->oApp->sess->GetUID(), [1, 1499] ) ) { // dev, Bob

            if( $bDonNotRecorded && $kfr->value('eStatus') != 'Cancelled' ) {
                $s .= "<div data-kOrder='{$kfr->Key()}' class='doRecordDonation alert alert-danger'>The donation is not recorded <button>Record</button></div>";
            }

            $cBorder = $fTotal == $kfr->Value('pay_total') ? 'green' : 'red';
            $cSuccess = $fTotal == $kfr->Value('pay_total') ? 'success' : 'danger';
            //$s .= "<div style='margin:5px;padding:5px;background-color:#ddd;border:1px solid $cBorder'>$sContents</div>";
            $s .= "<div class='alert alert-$cSuccess'>$sContents</div>";

// data-kOrder is also present in the enclosing <tr>
            $s .= "<div data-kOrder='{$kfr->Key()}' class='doBuildBasket'><button>rebuild this basket</button></div>";
        }
        return( $s );
    }

    private function mailStatus( KeyframeRecord $kfr, $raOrder )
    {
        $s = "";

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


// SBC should use oApp instead
            ['logdir'=>$oApp->logdir, 'sbdb_config'=>['db'=>$config_KFDB['seeds']['kfdbDatabase'] ] ] );
    }

    function ShowBasketContents( $kB, $bFulfilControls = false )
    /***********************************************************
        Get sContents:       summary of basket contents
            fTotal:          total amount
            bContactNeeded:  uid_buyer required to be set
            bDonNotRecorded: there is a donation without a kRef to mbr_donations
     */
    {
        $s = "";
        $fTotal = 0.0;
        $bContactNeeded = false;
        $bDonNotRecorded = false;

        if( !$kB )  goto done;

        $oB = new SEEDBasket_Basket( $this->oSB, $kB );
// you can do this with GetPurchasesInBasket too, and simplify some code below
        $raProd = $oB->GetProductsInBasket( ['returnType'=>'objects'] );

        // Find out if there is a membership or donation in this order.
        $bHasMbrProduct = $bHasDonProduct = false;
        foreach( $raProd as $oProd ) {
            if( $oProd->GetProductType() == 'membership' ) { $bHasMbrProduct = true; }
            if( $oProd->GetProductType() == 'donation' )   { $bHasDonProduct = true; }
        }

        $raBContents = $oB->ComputeBasketContents( false );
        if( @$raBContents['raSellers'][1] ) {
            $s .= "<table class='SodBasketFulfil_basketContents' style='text-align:right;width:100%'>"
                 ."<tr><td>&nbsp;</td><td valign='top' style='border-bottom:1px solid'>$&nbsp;{$raBContents['raSellers'][1]['fTotal']}</td></tr>";
            foreach( $raBContents['raSellers'][1]['raItems'] as $ra ) {
                if( !($oPur = $ra['oPur']) ) continue;

                $sButtons = "";
                if( $bFulfilControls && $oPur->IsFulfilmentActive() ) {
                    switch( $oPur->GetProductType() ) {
                        case 'donation':
                            $sButtons = !$oPur->IsFulfilled()
                                ? "<button data-kPurchase='{$oPur->GetKey()}' class='doPurchaseFulfil'>Accept donation</button>"
                                : "<button data-kPurchase='{$oPur->GetKey()}' class='doPurchaseFulfilUndo'>Undo</button>";
                            break;
                    }
                }

                $s .= "<tr><td valign='top' style='padding-right:5px'>{$ra['sItem']}</td>"
                         ."<td valign='top'>{$oPur->GetPrice()}</td>"
                         .($bFulfilControls ? "<td valign='top' style='text-align:left'> $sButtons</td>" : "")
                     ."</tr>";
            }
            $s .= "</table>";
        }

        $bContactNeeded = ( ($bHasMbrProduct || $bHasDonProduct) && !$oB->GetBuyer() );

        $fTotal = $raBContents['fTotal'];

        // Donations with kRef=0 are not recorded in mbr_donations yet. All Paid donations must be recorded there, even if non-receiptable.
        foreach( $oB->GetPurchasesInBasket() as $oPur ) {
            if( $oPur->GetProductType()=='donation' && !$oPur->GetKRef() ) {
                $bDonNotRecorded = true;
                break;
            }
        }

        done:
        return( [$s,$fTotal,$bContactNeeded,$bDonNotRecorded] );
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

        $this->oSB = new SEEDBasketCore( $oApp->kfdb, $oApp->sess, $oApp, SEEDBasketProducts_SoD::$raProductTypes,


// SBC should use oApp instead
            ['logdir'=>$oApp->logdir, 'db'=>'seeds'] );
    }


    function ProcessCmd( $cmd, $raParms )
    /************************************
        Process sb- JX commands
     */
    {
        // returns array containing 'bHandled'=>false/true
        return( $this->oSB->Cmd( $cmd, $raParms ) );
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
                    $oBP->StorePurchase( $oB, $oP, ['f'=>$d] );
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
