<?php

/* Basket product handlers
 *
 * Copyright (c) 2016-2019 Seeds of Diversity Canada
 */

include_once( SEEDCORE."SEEDBasket.php" );

class SEEDBasketProducts_SoD
/***************************
    Seeds of Diversity's products
 */
{
    // This is a super-set of SEEDBasketCore's raProductHandlers; it can be passed directly to that constructor
    // and also used as a look-up for other things
    static public $raProductTypes = array(
            'membership' => array( 'label'=>'Membership',
                                   'classname'=>'SEEDBasketProductHandler_Membership',
                                   'forceFlds' => array('quant_type'=>'ITEM-1') ),
            'donation'   => array( 'label'=>'Donation',
                                   'classname'=>'SEEDBasketProductHandler_Donation',
                                   'forceFlds' => array('quant_type'=>'MONEY') ),
            'book'       => array( 'label'=>'Publication',
                                   'classname'=>'SEEDBasketProductHandler_Book',
                                   'forceFlds' => array('quant_type'=>'ITEM-N') ),
            'misc'       => array( 'label'=>'Miscellaneous Payment',
                                   'classname'=>'SEEDBasketProductHandler_Misc',
                                   'forceFlds' => array('quant_type'=>'MONEY') ),
            'seeds'      => array( 'label'=>'Seeds',
                                   'classname'=>'SEEDBasketProductHandler_Seeds',
                                   'forceFlds' => array('quant_type'=>'ITEM-N') ),
            'event'      => array( 'label'=>'Event',
                                   'classname'=>'SEEDBasketProductHandler_Event',
                                   'forceFlds' => array('quant_type'=>'ITEM-1') ),
    );
}



class SEEDBasketProductHandler_Membership extends SEEDBasketProductHandler
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }

    function ProductDefine0( KeyframeForm $oFormP )
    {
        $oFormX = new SEEDFormExpand( $oFormP );

        $s = "<h3>Membership Definition Form</h3>";

        $s .= $oFormP->HiddenKey()
             .$oFormX->ExpandForm(
                     "|||BOOTSTRAP_TABLE(class='col-sm-4',class='col-sm-8')\n"
                    ."||| Seller        || [[text:uid_seller|readonly]]\n"
                    ."||| Product type  || [[text:product_type|readonly]]\n"
                    ."||| Quantity type || [[text:quant_type|readonly]]\n"
                    ."||| Status        || ".$oFormP->Select( 'eStatus', array('ACTIVE'=>'ACTIVE','INACTIVE'=>'INACTIVE','DELETED'=>'DELETED') )
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

        $s .= $oFormP->HiddenKey()
             ."<table>"
             .$oFormX->ExpandForm(
                     "||| Seller        || [[text:uid_seller|readonly]]"
                    ."||| Product type  || [[text:product_type|readonly]]"
                    ."||| Quantity type || [[text:quant_type|readonly]]"
                    ."||| Status        || ".$oFormP->Select( 'eStatus', array('ACTIVE'=>'ACTIVE','INACTIVE'=>'INACTIVE','DELETED'=>'DELETED') )
                    ."<br/><br/>"
                    ."||| Title EN      || [[text:title_en]]"
                    ."||| Title FR      || [[text:title_fr]]"
                    ."||| Name          || [[text:name]]"
                     )
             ."</table> ";

        return( $s );
    }
}

class SEEDBasketProductHandler_Book extends SEEDBasketProductHandler
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }

    function ProductDefine0( KeyFrameForm $oFormP )
    {
        $oFormX = new SEEDFormExpand( $oFormP );

        $s = "<h3>Publications Product Form</h3>";

        $s .= $oFormP->HiddenKey()
             ."<table>"
             .$oFormX->ExpandForm(
                     "||| Seller        || [[text:uid_seller|readonly]]"
                    ."||| Product type  || [[text:product_type|readonly]]"
                    ."||| Quantity type || [[text:quant_type|readonly]]"
                    ."||| Status        || ".$oFormP->Select( 'eStatus', array('ACTIVE'=>'ACTIVE','INACTIVE'=>'INACTIVE','DELETED'=>'DELETED') )
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
                     )
             ."</table> ";

        return( $s );
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

        $s .= $oFormP->HiddenKey()
             ."<table>"
             .$oFormX->ExpandForm(
                     "|||BOOTSTRAP_TABLE(4,8)"
                    ."||| Seller        || [[text:uid_seller|readonly]]"
                    ."||| Product type  || [[text:product_type|readonly]]"
                    ."||| Quantity type || [[text:quant_type|readonly]]"
                    ."||| Status        || ".$oFormP->Select( 'eStatus', array('ACTIVE'=>'ACTIVE','INACTIVE'=>'INACTIVE','DELETED'=>'DELETED') )
                    ."<br/><br/>"
                    ."||| Title EN      || [[text:title_en]]"
                    ."||| Title FR      || [[text:title_fr]]"
                    ."||| Name          || [[text:name]]"
                     )
             ."</table> ";

        return( $s );
    }
}


class SEEDBasketProductHandler_Event extends SEEDBasketProductHandler
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }
}

?>