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
