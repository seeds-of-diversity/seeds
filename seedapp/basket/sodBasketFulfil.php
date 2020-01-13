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
                                                    array("col"=>"kBasket",         "type"=>"I"),
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
        if( !($this->fltStatus = $oApp->sess->SmartGPC( 'fltStatus', [ "", "All", "Not-accounted", "Not-mailed",
                                                                       MBRORDER_STATUS_FILLED, MBRORDER_STATUS_CANCELLED ] )) )
        {
            // "" defaults to either Not-accounted or Not-mailed depending on whom you are
            $this->fltStatus = $this->oApp->sess->GetUID() == 4879 ? "Not-accounted" : "Not-mailed";
        }
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

        $s .= "<form action='".$this->oApp->PathToSelf()."'>"
             ."<p>Show: "
             .SEEDForm_Select2( 'fltStatus',
                        array( "Not Accounted" => "Not-accounted",
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
                $cond = "eStatus NOT IN ('".MBRORDER_STATUS_FILLED."','".MBRORDER_STATUS_CANCELLED."')";
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
        = $kfr->Expand( "<a href='?row=[[_key]]'>[[_key]]</a>"
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
         ."<b>".(($kfr->value('eStatus')==MBRORDER_STATUS_FILLED && $this->GetMailStatus_Pending($kfr)) ? "Accounted" : $kfr->value('eStatus'))."</b>"
         .($kfr->value('eStatus')=='New' ? $this->changeToPaidButton($kfr->Key()): "")
         .$this->mailStatus( $kfr, $raOrder );

    $sFulfilment
        = $this->buttonBuildBasket( $kfr )
         .$this->showBasket( $kfr );

    $s .= "<tr data-kOrder='".$kfr->Key()."'>"
         ."<td valign='top'>$sOrderNum</td>"
         ."<td valign='top' $style>$sName</td>"
         ."<td valign='top'>$sAddress</td>"
         ."<td valign='top'>$sEbulletin</td>"
         ."<td valign='top'>$sConciseSummary".$this->mailNothingButton( $kfr, $raOrder )."</td>"
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

        if( $this->GetMailStatus_Pending($kfr) && $kfr->Value('eStatus') != MBRORDER_STATUS_CANCELLED &&
            in_array( $this->oApp->sess->GetUID(), array( 1, 1499, 10914 ) ) )  // dev, Bob, Christine
        {
            $kOrder = $kfr->Key();
            $s .= "<div id='status2x_$kOrder' class='status2x'><button>Nothing to mail</button></div>";
        }

        return( $s );
    }

    private function buttonBuildBasket( KeyframeRecord $kfr )
    /********************************************************
     */
    {
        $s = "";

        if( in_array( $this->oApp->sess->GetUID(), [1, 1499] ) ) { // dev, Bob
            $kOrder = $kfr->Key();
            $s .= "<div data-kOrder='$kOrder' class='doBuildBasket'><button>basket</button></div>";
        }

        return( $s );
    }

    private function showBasket( KeyframeRecord $kfr )
    /*************************************************
     */
    {
        $s = "";

        if( !($kB = $kfr->Value('kBasket')) )  goto done;

        $oOrder = new SoDOrder_MbrOrder( $this->oApp );
        $s = $oOrder->ShowBasket( $kB );

        done:
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


class SoDOrder_MbrOrder
{
    private $oOrder;
    private $oSB;

    function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;
        $this->oOrder = new SodOrder( $oApp );

        $this->oSB = new SEEDBasketCore( $oApp->kfdb, $oApp->sess, $oApp, SEEDBasketProducts_SoD::$raProductTypes,


// SBC should use oApp instead
            ['logdir'=>$oApp->logdir, 'db'=>'seeds'] );
    }

    function CreateFromMbrOrder( int $kOrder )
    {
        //$this->oApp->kfdb->SetDebug(2);
        if( !($kfrMbrOrder = $this->oOrder->KfrelOrder()->GetRecordFromDBKey( $kOrder )) )  goto done;
        //var_dump($kfrMbrOrder->ValuesRA());

        $kB = $kfrMbrOrder->Value('kBasket');
        $oB = new SEEDBasket_Basket( $this->oSB, $kB );    // look up the Basket or create an empty one

        if( !$kB ) {
            // fill new basket fields
            $oB->SetValue( 'uid_buyer', $kfrMbrOrder->UrlParmGet('sExtra', 'mbrid') );

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
                      'buyer_lang'      => 'mail_lang',
                      'buyer_notes'     => 'notes',
                ] as $k=>$v )
            {
                $oB->SetValue( $k, $kfrMbrOrder->Value($v) );
            }
            $oB->PutDBRow();
            $kB = $oB->Key();

            $kfrMbrOrder->SetValue('kBasket', $kB );
            $kfrMbrOrder->PutDBRow();
        }

        $raProdKeys = $oB->GetProductsInBasket( ['returnType'=>'keys'] );

        if( ($m = $kfrMbrOrder->Value('mbr_type')) ) {
            if( ($oP = $this->oSB->FindProduct( "uid_seller='1' AND product_type='membership' AND name='$m'" )) ) {
                if( !in_array($oP->GetKey(), $raProdKeys) ) {
                    $oBP = new SEEDBasket_Purchase( $this->oSB, 0 );
                    $oBP->StorePurchase( $oB, $oP, ['n'=>1] );
                }
            }
        }

/*
        if( ($d = floatval($kfrMbrOrder->Value('donation'))) > 0.0 ) {
            if( ($oP = $this->oSB->FindProduct( "uid_seller='1' AND product_type='donation' AND name='general" )) ) {
                $oBP = new SEEDBasket_Purchase( $this->oSB, 0 );
                $oBP->StorePurchase( $oB, $oP, ['f'=>$d] );
            }
        }

        foreach( ['nPubSSH-EN6','nPubSSH-FR6','nPubEverySeed','nPubKent2012'] as $v ) {
            if( ($n = $kfrMbrOrder->UrlParmGet('sExtra', $v)) ) {
                if( ($oP = $this->oSB->FindProduct( "uid_seller='1' AND product_type='book' AND name='$v'" )) ) {
                    $oBP = new SEEDBasket_Purchase( $this->oSB, 0 );
                    $oBP->StorePurchase( $oB, $oP, ['n'=>$n] );
                }
            }
        }

        if( ($d = floatval($kfrMbrOrder->Value('nPubEverySeed_Shipping'))) ) {
            if( ($oP = $this->oSB->FindProduct( "uid_seller='1' AND product_type='book' AND name='shipping" )) ) {
                $oBP = new SEEDBasket_Purchase( $this->oSB, 0 );
                $oBP->StorePurchase( $oB, $oP, ['f'=>$d] );
            }
        }
*/

        done:
        return;
    }

    function ShowBasket( $kB )
    {
        $s = "";

        $oB = new SEEDBasket_Basket( $this->oSB, $kB );
        $raProd = $oB->GetProductsInBasket( ['returnType'=>'objects'] );

        foreach( $raProd as $oProd ) {
            $s .= $oProd->GetName()."<br/>";
        }
        $raBContents = $oB->ComputeBasketContents( false );
        if( @$raBContents['raSellers'][1] ) {
            $s .= "<table>";
            $s .= "<tr><td>&nbsp;</td><td valign='top' style='border-bottom:1px solid'>$&nbsp;{$raBContents['raSellers'][1]['fTotal']}</td></tr>";
            foreach( $raBContents['raSellers'][1]['raItems'] as $ra ) {
                $s .= SEEDCore_ArrayExpand( $ra, "<tr><td valign='top'>[[sItem]]</td><td valign='top'>[[fAmount]]</td></tr>" );
            }
            $s .= "</table>";
        }

        return( $s );
    }

}
