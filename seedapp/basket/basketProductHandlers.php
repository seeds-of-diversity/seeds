<?php

/* Basket product handlers
 *
 * Copyright (c) 2016-2020 Seeds of Diversity Canada
 */

include_once( SEEDCORE."SEEDBasket.php" );

class SEEDBasketProducts_SoD
/***************************
    Seeds of Diversity's products
 */
{
    // This is a super-set of SEEDBasketCore's raProductHandlers; it can be passed directly to that constructor
    // and also used as a look-up for other things
    static public $raProductTypes = [
            'membership' => [ 'label'=>'Membership',
                              'classname'=>'SEEDBasketProductHandler_Membership',
                              'forceFlds' => ['quant_type'=>'ITEM-1'] ],
            'donation'   => [ 'label'=>'Donation',
                              'classname'=>'SEEDBasketProductHandler_Donation',
                              'forceFlds' => ['quant_type'=>'MONEY'] ],
            'book'       => [ 'label'=>'Publication',
                              'classname'=>'SEEDBasketProductHandler_Book',
                              'forceFlds' => ['quant_type'=>'ITEM-N'] ],
            'misc'       => [ 'label'=>'Miscellaneous Payment',
                              'classname'=>'SEEDBasketProductHandler_Misc',
                              'forceFlds' => ['quant_type'=>'MONEY'] ],
            'seeds'      => [ 'label'=>'Seeds',
                              'classname'=>'SEEDBasketProductHandler_Seeds',
                              'forceFlds' => ['quant_type'=>'ITEM-N'] ],
            'event'      => [ 'label'=>'Event',
                              'classname'=>'SEEDBasketProductHandler_Event',
                              'forceFlds' => ['quant_type'=>'ITEM-1'] ],
            'special1'   => [ 'label'=>'Special Single Item',
                              'classname'=>'SEEDBasketProductHandler_Special1',
                              'forceFlds' => ['quant_type'=>'ITEM-1'] ],
    ];
}



class SEEDBasketProductHandler_Membership extends SEEDBasketProductHandler_Item1
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }

    function ProductDefine0( KeyframeForm $oFormP )
    {
        return( parent::ProductDefine0_Item1( $oFormP, "Membership" ) );
    }

    function Purchase2( KeyframeRecord $kfrP, $raPurchaseParms )
    /***********************************************************
        Add a membership to the basket. Only one membership is allowed per basket, so remove any others.
     */
    {
        $raBPxP = $this->oSB->oDB->GetPurchasesList( $this->oSB->GetBasketKey() );
        foreach( $raBPxP as $ra ) {
            if( $ra['P_product_type'] == 'membership' ) {
                $this->oSB->Cmd( 'removeFromBasket', array('kBP'=> $ra['_key']) );
            }
        }

        return( parent::Purchase2( $kfrP, $raPurchaseParms ) );
    }
}

class SEEDBasketProductHandler_Donation extends SEEDBasketProductHandler_MONEY
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }

    function ProductDefine0( KeyFrameForm $oFormP )
    {
        return( parent::ProductDefine0_MONEY( $oFormP, "Donation" ) );
    }

    function PurchaseDraw( KeyframeRecord $kfrBPxP, $raParms = [] )
    {
        $s = parent::PurchaseDraw( $kfrBPxP, $raParms )
            .(($kRef = $kfrBPxP->Value('kRef')) ? " ($kRef)" : " (NOT RECORDED)");      // show the mbr_donations _key if it is set

        return( $s );
    }

    function PurchaseIsFulfilled( SEEDBasket_Purchase $oPurchase )
    {
    }

    function PurchaseFulfil( SEEDBasket_Purchase $oPurchase )
    {

    }
}

class SEEDBasket_Purchase_donation extends SEEDBasket_Purchase
{
    function __construct( SEEDBasketCore $oSB, $kP )
    {
        parent::__construct( $oSB, $kP );
    }

    /**************************************
        A donation is considered fulfiled when Basket::uid_buyer is set and Purchase::kRef points to an mbr_donation.
        The fulfilment system cannot create an mbr_donation until the uid_buyer is identified so we assume that kRef alone indicates fulfilment.

        N.B. Fulfilment does not create a receipt number, nor record that a receipt is mailed. Both of those are done by a separate system.
             All this system does is create an mbr_donation and point to it from kRef.
     */
    function IsFulfilmentAllowed()
    {
        return( parent::IsFulfilmentAllowed() && $this->GetBasketObj()->GetBuyer() );  // require ui_buyer to be set
    }

    function IsFulfilled()
    {
        return( $this->GetWorkflowFlag(self::WORKFLOW_FLAG_RECORDED) && $this->GetKRef() );
    }

    function Fulfil()
    {
        $ret = self::FULFIL_RESULT_FAILED;

        // check if fulfilment is allowed
        if( !$this->IsFulfilmentAllowed() ) goto done;

        // check if already fulfilled
        if( $this->IsFulfilled() ) {
            $ret = self::FULFIL_RESULT_ALREADY_FULFILLED;
            goto done;
        }

        $oB = $this->GetBasketObj();    // succeeds because of the check above

// Date paid is not necessarily the date the order was made.  We might enter cheque orders long after they are received.
$dateReceived = $oB->GetDate();

        $oMbr = new Mbr_Contacts( $this->oSB->oApp );
        $kDonation = $oMbr->AddMbrDonation(
                        ['kMbr' => $oB->GetBuyer(),
                         'date_received' => $dateReceived,
                         'amount' => $this->GetF(),
                         'receipt_num' => 0 ] );

        if( $kDonation ) {
            $this->SetValue( 'kRef', $kDonation );
            $this->SetWorkflowFlag( self::WORKFLOW_FLAG_RECORDED );
            $this->SaveRecord();

            $ret = self::FULFIL_RESULT_SUCCESS;
        }

        done:
        return( $ret );
    }
}


class SEEDBasketProductHandler_Book extends SEEDBasketProductHandler_ItemN
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }

    function ProductDefine0( KeyFrameForm $oFormP )
    {
        return( parent::ProductDefine0_ItemN( $oFormP, "Publications" ) );
    }
}

class SEEDBasketProductHandler_Misc extends SEEDBasketProductHandler_MONEY
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }

    function ProductDefine0( KeyframeForm $oFormP )
    {
        return( parent::ProductDefine0_MONEY( $oFormP, "Misc Payment" ) );
    }
}

class SEEDBasketProductHandler_Special1 extends SEEDBasketProductHandler_Item1
/*****************************************************************************
    Special Single Item
 */
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }

    function ProductDefine0( KeyframeForm $oFormP )
    {
        return( parent::ProductDefine0_Item1( $oFormP, "Special Single Item" ) );
    }

    function Purchase1( KeyframeRecord $kfrP )
    /*****************************************
        Only one of this product may be in the basket.
     */
    {
        $bOk = true;
        $sErr = "";

        if( ($raBPxP = $this->oSB->oDB->GetPurchasesList( $this->oSB->GetBasketKey() )) ) {
            foreach( $raBPxP as $ra ) {
                if( $ra['P_product_type']==$kfr->Value('product_type') && $ra['P_name']==$kfrP->Value('name') ) {
                    $bOk = false;
                    $sErr = "Only one ".$kfrP->Value('title_en')." can be added at a time.";
                    goto done;
                }
            }
        }

        done:
        return( [$bOk,$sErr] );
    }
}


class SEEDBasketProductHandler_Event extends SEEDBasketProductHandler
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }
}
