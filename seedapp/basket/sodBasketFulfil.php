<?php

/* Temporary location for mbrOrder fulfilment code.
 * This should all be converted to use SEEDBasket and its fulfilment system.
 */

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
        [ "Tables" => [ "O" => [ "Table" => 'seeds.mbr_order_pending',
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
                                                    array("col"=>"ePayType",        "type"=>"S", "default"=>'PayPal'),
                                                    array("col"=>"sExtra",          "type"=>"S") )
        ]]];
}


class SodOrderFulfil
{
    public $oApp;
    protected $oOrder;

    function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;
        $this->oOrder = new SodOrder( $oApp );
    }

    public function KfrelOrder() { return( $this->oOrder->KfrelOrder() ); }

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
        $this->fltStatus = $oApp->sess->SmartGPC( 'fltStatus', array("", MBRORDER_STATUS_FILLED, MBRORDER_STATUS_CANCELLED) );
        if( !($this->fltYear = intval($oApp->sess->SmartGPC( 'fltYear', array() ))) ) {
            $this->fltYear = $this->yCurrent;
        }
    }

    function GetCurrOrderKey()  { return( $this->pRow ); }

    function DrawFormFilters()
    {
        $s = "";

        $raYearOpt = array();
        for( $y = $this->yCurrent; $y > 2010; --$y ) {
            $raYearOpt[strval($y)] = $y;
        }
        $raYearOpt["2010 and before"] = 2010;

        $s .= "<form action='${_SERVER['PHP_SELF']}'>"
             ."<p>Show: "
             .SEEDForm_Select2( 'fltStatus',
                        array( "Pending / Paid" => "",
                               "Filled"         => MBRORDER_STATUS_FILLED,
                               "Cancelled"      => MBRORDER_STATUS_CANCELLED ),
                        $this->fltStatus,
                        array( "selectAttrs" => "onChange='submit();'" ) )
             .SEEDCore_NBSP("",5)
             .SEEDForm_Select2( 'fltYear',
                        $raYearOpt,
                        $this->fltYear,
                        array( "selectAttrs" => "onChange='submit();'" ) )
             .(!$this->fltStatus ? "&nbsp;&nbsp;<-- the year selector is ignored for pending orders" : "")
             ."</p></form>";

        return( $s );
    }

    function DrawOrderSummaryRow( KeyframeRecord $kfr, $sConciseSummary, $raOrder )
    {
        $s = "";

        if( $kfr->value('eStatus') == MBRORDER_STATUS_PAID ) {
            $style = "style='color:green;background-color:#efe'";
        } else {
            $style = "";
        }

// kluge to make the membership labels easier to differentiate
$sConciseSummary = str_replace( "One Year Membership with on-line Seed Directory", "One Year Membership", $sConciseSummary );
$sConciseSummary = str_replace( "One Year Membership with printed and on-line Seed Directory", "One Year Membership with printed Seed Directory", $sConciseSummary );


    $kMbr = @$ra['mbrid'] ?: 0;

    $sOrderNum
        = $kfr->Expand( "<a href='".$_SERVER['PHP_SELF']."?row=[[_key]]'>[[_key]]</a>"
                       ."<div style='float:right; padding-right:15px'>".substr($kfr->value('_created'),0,10)."</div>"
                       ."<br/><br/>"
                       ."<div><form action='http://seeds.ca/office/mbr/mbr_labels.php' target='MbrLabels' method='get'>"
                           ."<input type='hidden' name='orderadd' value='[[_key]]'/><input type='submit' value='Add to Label Maker'/>"
                       ."</form></div>"
                       ."<div class='mbrOrderShowTicket' data-kOrder='[[_key]]' data-expanded='0'><a href='#'>Show Ticket</a></div>" );

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
         ."<b>".$kfr->value('eStatus')."</b>"
         .($kfr->value('eStatus')=='New' ? $this->changeToPaidButton($kfr->Key()): "")
         .$this->mailStatus( $kfr, $raOrder );


    $s .= "<tr data-kOrder='".$kfr->Key()."'>"
         ."<td valign='top'>$sOrderNum</td>"
         ."<td valign='top' $style>$sName</td>"
         ."<td valign='top'>$sAddress</td>"
         ."<td valign='top'>$sEbulletin</td>"
         ."<td valign='top'>$sConciseSummary".$this->mailNothingButton( $kfr, $raOrder )."</td>"
         ."<td valign='top' $style>$sPayment</td>"
         ."</tr>";

         return( $s );
    }

    private function linkSendEmail( $kfr )
    {
        $ra = SEEDCore_ParmsURL2RA( $kfr->value('sExtra') );
        $to = @$ra['mbrid'] ?: $kfr->value('mail_email');
        return( "window.open(\"../int/emailme.php?to=$to\",\"_blank\",\"width=900,height=800,scrollbars=yes\")" );
    }

    private function changeToPaidButton( $kOrder )
    {
        $s = "<form method='post' action='".Site_path_self()."'>"
            ."<input type='hidden' name='row' value='$kOrder'/>"
            ."<input type='hidden' name='action' value='changeStatusToPaid'/>"
            ."<input type='submit' value='Change to Paid'/>"
            ."</form><br/>";

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

        if( $this->GetMailStatus_Pending($kfr) &&
            in_array( $this->oApp->sess->GetUID(), array( 1, 1499 /*, 10914*/ ) ) )  // dev, Bob, Christine
        {
            $kOrder = $kfr->Key();
            $s .= "<div id='status2x_$kOrder' class='status2x'><button>Nothing to mail</button></div>";
        }

        return( $s );
    }


    private function mailStatus( KeyframeRecord $kfr, $raOrder )
    {
        $s = "";

        $bMailed = $kfr->Value( 'eStatus2' )==1;
        $bNothingToMail = $kfr->Value( 'eStatus2' )==2;

        if( in_array( $this->oApp->sess->GetUID(), array( 1, 1499, 10914 ) ) ) {    // dev, Bob, Christine
            if( $this->GetMailStatus_Pending($kfr) ) {
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
