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



class SEEDBasketProductHandler_Membership extends SEEDBasketProductHandler
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }

    function ProductDefine0( KeyframeForm $oFormP )
    {
        $oFormX = new SEEDFormExpand( $oFormP );

        $s = "<h3>Membership Definition Form</h3>";

        $s .= $oFormX->ExpandForm(
                     "|||BOOTSTRAP_TABLE(class='col-md-4'|class='col-md-8')\n"
                    ."||| Product #     || [[key:]]"
                    ."||| Seller        || [[text:uid_seller|readonly]]\n"
                    ."||| Product type  || [[text:product_type|readonly]]\n"
                    ."||| Quantity type || [[text:quant_type|readonly value=ITEM-1]]\n"
                    ."||| Status        || ".$oFormP->Select( 'eStatus', ['ACTIVE','INACTIVE','DELETED'], "", ['bValsCompacted'=>true] )
                    ."<br/><br/>\n"
                    ."||| Title EN      || [[text:title_en | ]]\n"
                    ."||| Title FR      || [[text:title_fr | ]]\n"
                    ."||| Name          || [[text:name]]"
                    ."<br/><br/>\n"
                    ."||| Price         || [[text:item_price]]\n"
                    ."||| Price U.S.    || [[text:item_price_US]]\n"
                     );

        return( $s );
    }

    function ProductDefine1( Keyframe_DataStore $oDS )
    {
        $oDS->SetValue( 'quant_type', 'ITEM-1' );
        return( parent::ProductDefine1( $oDS ) );
    }

    function ProductDraw( KeyframeRecord $kfrP, $eDetail, $raParms = [] )
    {
        switch( $eDetail ) {
            case SEEDBasketProductHandler::DETAIL_TINY:
                $s = $kfrP->Expand( "<p>[[title_en]] ([[name]])</p>" );
                break;
            default:
                $s = $kfrP->Expand( "<h4>[[title_en]] ([[name]])</h4>" )
                    .$this->ExplainPrices( $kfrP );
        }
        return( $s );
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

class SEEDBasketProductHandler_Donation extends SEEDBasketProductHandler
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }

    function ProductDefine0( KeyFrameForm $oFormP )
    {
        $oFormX = new SEEDFormExpand( $oFormP );

        $s = "<h3>Donation Definition Form</h3>";

        $s .= $oFormX->ExpandForm(
                     "|||BOOTSTRAP_TABLE(class='col-md-4'|class='col-md-8')\n"
                    ."||| Product #     || [[key:]]"
                    ."||| Seller        || [[text:uid_seller|readonly]]"
                    ."||| Product type  || [[text:product_type|readonly]]"
                    ."||| Quantity type || [[text:quant_type|readonly value=MONEY]]"
                    ."||| Status        || ".$oFormP->Select( 'eStatus', ['ACTIVE','INACTIVE','DELETED'], "", ['bValsCompacted'=>true] )
                    ."<br/><br/>"
                    ."||| Title EN      || [[text:title_en]]"
                    ."||| Title FR      || [[text:title_fr]]"
                    ."||| Name          || [[text:name]]"
                     );

        return( $s );
    }

    function ProductDefine1( Keyframe_DataStore $oDS )
    {
        $oDS->SetValue( 'quant_type', 'MONEY' );
        return( parent::ProductDefine1( $oDS ) );
    }
}

class SEEDBasketProductHandler_Book extends SEEDBasketProductHandler
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }

    function ProductDefine0( KeyFrameForm $oFormP )
    {
        $oFormX = new SEEDFormExpand( $oFormP );

        $s = "<h3>Publications Product Form</h3>";

        $s .= $oFormX->ExpandForm(
                     "|||BOOTSTRAP_TABLE(class='col-md-4'|class='col-md-8')\n"
                    ."||| Product #     || [[key:]]"
                    ."||| Seller        || [[text:uid_seller|readonly]]"
                    ."||| Product type  || [[text:product_type|readonly]]"
                    ."||| Quantity type || [[text:quant_type|readonly value=ITEM-N]]"
                    ."||| Status        || ".$oFormP->Select( 'eStatus', ['ACTIVE','INACTIVE','DELETED'], "", ['bValsCompacted'=>true] )
                    ."<br/><br/>"
                    ."||| Title EN      || [[text:title_en]]"
                    ."||| Title FR      || [[text:title_fr]]"
                    ."||| Name          || [[text:name]]"
                    ."||| Images        || [[text:img]]"
                     ."<br/><br/>"
                    ."||| Min in basket  || [[text:bask_quant_min]] (0 means no limit)"
                    ."||| Max in basket  || [[text:bask_quant_max]] (0 means no limit)"
                    ."<br/><br/>"
                    ."||| Price          || [[text:item_price]] (e.g. 15 or 15:1-9,12:10-19,10:20+)"
                    ."||| Discount       || [[text:item_discount]]"
                    ."||| Shipping       || [[text:item_shipping]]"
                    ."||| Price U.S.     || [[text:item_price_US]]"
                    ."||| Discount U.S.  || [[text:item_discount_US]]"
                    ."||| Shipping U.S.  || [[text:item_shipping_US]]"
                     );

        return( $s );
    }

    function ProductDefine1( Keyframe_DataStore $oDS )
    {
        $oDS->SetValue( 'quant_type', 'ITEM-N' );
        return( parent::ProductDefine1( $oDS ) );
    }

    function ProductDraw( KeyframeRecord $kfrP, $eDetail, $raParms = [] )
    {
        switch( $eDetail ) {
            case SEEDBasketProductHandler::DETAIL_TINY:
                $s = $kfrP->Expand( "<p>[[title_en]] ([[name]])</p>" );
                break;
            default:
                $s = $kfrP->Expand( "<h4>[[title_en]] ([[name]])</h4>" )
                    .$this->ExplainPrices( $kfrP );
        }
        return( $s );
    }

    function Purchase0( KeyframeRecord $kfrP, $raParms = [] )
    /*****************************************
        Given a product, draw the form that a store would show to purchase it.
        Form parms can be:
            n     (int)
            f     (float)
            sbp_* (string)
     */
    {
        $s = $kfrP->Value('title_en')
            ."&nbsp;&nbsp;<input type='text' name='sb_n' value='1'/>"
            ."<input type='hidden' name='sb_product' value='".$kfrP->Value('name')."'/>";

        return( $s );
    }

}

class SEEDBasketProductHandler_Misc extends SEEDBasketProductHandler
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }

    function ProductDefine0( KeyframeForm $oFormP )
    {
        $oFormX = new SEEDFormExpand( $oFormP );

        $s = "<h3>Misc Payment Definition Form</h3>";

        $s .= $oFormX->ExpandForm(
                     "|||BOOTSTRAP_TABLE(class='col-md-4'|class='col-md-8')"
                    ."||| Product #     || [[key:]]"
                    ."||| Seller        || [[text:uid_seller|readonly]]"
                    ."||| Product type  || [[text:product_type|readonly]]"
                    ."||| Quantity type || [[text:quant_type|readonly value=MONEY]]"
                    ."||| Status        || ".$oFormP->Select( 'eStatus', ['ACTIVE','INACTIVE','DELETED'], "", ['bValsCompacted'=>true] )
                    ."<br/><br/>"
                    ."||| Title EN      || [[text:title_en]]"
                    ."||| Title FR      || [[text:title_fr]]"
                    ."||| Name          || [[text:name]]"
                     );

        return( $s );
    }

    function ProductDefine1( Keyframe_DataStore $oDS )
    {
        $oDS->SetValue( 'quant_type', 'MONEY' );
        return( parent::ProductDefine1( $oDS ) );
    }
}

class SEEDBasketProductHandler_Special1 extends SEEDBasketProductHandler
/***********************************************************************
    Special Single Item
 */
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }

    function ProductDefine0( KeyframeForm $oFormP )
    {
        $oFormX = new SEEDFormExpand( $oFormP );

        $s = "<h3>Special Single Item Definition Form</h3>";

        $s .= $oFormX->ExpandForm(
                     "|||BOOTSTRAP_TABLE(class='col-md-4'|class='col-md-8')\n"
                    ."||| Product #     || [[key:]]"
                    ."||| Seller        || [[text:uid_seller|readonly]]\n"
                    ."||| Product type  || [[text:product_type|readonly]]\n"
                    ."||| Quantity type || [[text:quant_type|readonly value=ITEM-1]]\n"
                    ."||| Status        || ".$oFormP->Select( 'eStatus', ['ACTIVE','INACTIVE','DELETED'], "", ['bValsCompacted'=>true] )
                    ."<br/><br/>\n"
                    ."||| Title EN      || [[text:title_en | ]]\n"
                    ."||| Title FR      || [[text:title_fr | ]]\n"
                    ."||| Name          || [[text:name]]"
                    ."<br/><br/>\n"
                    ."||| Price         || [[text:item_price]]\n"
                    ."||| Price U.S.    || [[text:item_price_US]]\n"
                     );

        return( $s );
    }

    function ProductDefine1( Keyframe_DataStore $oDS )
    {
        $oDS->SetValue( 'quant_type', 'ITEM-1' );
        return( parent::ProductDefine1( $oDS ) );
    }

    function ProductDraw( KeyframeRecord $kfrP, $eDetail, $raParms = [] )
    {
        switch( $eDetail ) {
            case SEEDBasketProductHandler::DETAIL_TINY:
                $s = $kfrP->Expand( "<p>[[title_en]] ([[name]])</p>" );
                break;
            default:
                $s = $kfrP->Expand( "<h4>[[title_en]] ([[name]])</h4>" )
                    .$this->ExplainPrices( $kfrP );
        }
        return( $s );
    }

    function Purchase1( KeyframeRecord $kfrP )
    /*****************************************
        Only one of this product may be in the basket.
     */
    {
        $bOk = false;

        if( ($raBPxP = $this->oSB->oDB->GetPurchasesList( $this->oSB->GetBasketKey() )) ) {
            foreach( $raBPxP as $ra ) {
                if( $ra['P_product_type']==$kfr->Value('product_type') && $ra['P_name']==$kfrP->Value('name') ) {
                    // this product is already in the basket
                    goto done;
                }
            }
        }

        $bOk = true;

        done:
        return( $bOk );
    }
}


class SEEDBasketProductHandler_Event extends SEEDBasketProductHandler
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }
}
