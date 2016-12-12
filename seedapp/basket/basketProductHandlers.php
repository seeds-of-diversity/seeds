<?php

/* Basket product handlers
 *
 * Copyright (c) 2016 Seeds of Diversity Canada
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

    function ProductDefine0( KeyFrameUIForm $oFormP )
    {
        $s = "<h3>Membership Definition Form</h3>";

        $s .= $oFormP->HiddenKey()
             ."<table>"
             .$oFormP->ExpandForm(
                     "||| Seller        || [[text:uid_seller|readonly]]"
                    ."||| Product type  || [[text:product_type|readonly]]"
                    ."||| Quantity type || [[text:quant_type|readonly]]"
                    ."||| Status        || ".$oFormP->Select2( 'eStatus', array('ACTIVE'=>'ACTIVE','INACTIVE'=>'INACTIVE','DELETED'=>'DELETED') )
                    ."<br/><br/>"
                    ."||| Title EN      || [[text:title_en | size:40]]"
                    ."||| Title FR      || [[text:title_fr | size:40]]"
                    ."||| Name          || [[text:name]]"
                    ."<br/><br/>"
                    ."||| Price         || [[text:item_price]]"
                    ."||| Price U.S.    || [[text:item_price_US]]"
                     )
             ."</table> ";

        return( $s );
    }

    function ProductDefine1( KeyFrameDataStore $oDS )
    {
        return( parent::ProductDefine1( $oDS ) );
    }

    function ProductDraw( KFRecord $kfrP, $bDetail )
    {
        $s = "<h4>".$kfrP->Value('title_en')."</h4>";

        if( $bDetail ) {
            $s .= $kfrP->Expand( "Name: [[name]]<br/>Price: [[item_price]]" );
        }
        return( $s );
    }

    function Purchase2( KFRecord $kfrP, $raParmsBP, $bGPC )
    /******************************************************
        Add a membership to the basket. Only one membership is allowed per basket, so remove any others.
     */
    {
//        $s = "";

        $raBPxP = $this->oSB->oDB->GetPurchasesList( $this->oSB->GetBasketKey() );
        foreach( $raBPxP as $ra ) {
            if( $ra['P_product_type'] == 'membership' ) {
                $this->oSB->RemoveProductFromBasket( $ra['_key'] );
//                $s .= "<p>Removed a membership</p>";
            }
        }

        return( parent::Purchase2( $kfrP, $raParmsBP, $bGPC ) );
    }
}

class SEEDBasketProductHandler_Donation extends SEEDBasketProductHandler
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }

    function ProductDefine0( KeyFrameUIForm $oFormP )
    {
        $s = "<h3>Donation Definition Form</h3>";

        $s .= $oFormP->HiddenKey()
             ."<table>"
             .$oFormP->ExpandForm(
                     "||| Seller        || [[text:uid_seller|readonly]]"
                    ."||| Product type  || [[text:product_type|readonly]]"
                    ."||| Quantity type || [[text:quant_type|readonly]]"
                    ."||| Status        || ".$oFormP->Select2( 'eStatus', array('ACTIVE'=>'ACTIVE','INACTIVE'=>'INACTIVE','DELETED'=>'DELETED') )
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

    function ProductDefine0( KeyFrameUIForm $oFormP )
    {
        $s = "<h3>Publications Product Form</h3>";

        $s .= $oFormP->HiddenKey()
             ."<table>"
             .$oFormP->ExpandForm(
                     "||| Seller        || [[text:uid_seller|readonly]]"
                    ."||| Product type  || [[text:product_type|readonly]]"
                    ."||| Quantity type || [[text:quant_type|readonly]]"
                    ."||| Status        || ".$oFormP->Select2( 'eStatus', array('ACTIVE'=>'ACTIVE','INACTIVE'=>'INACTIVE','DELETED'=>'DELETED') )
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

    function ProductDraw( KFRecord $kfrP, $bDetail )
    {
        $s = "<h4>".$kfrP->Value('title_en')."</h4>";

        if( $bDetail ) {
            $s .= $kfrP->Expand( "Name: [[name]]<br/>" )
                 .$this->ExplainPrices( $kfrP );
        }
        return( $s );
    }
}

class SEEDBasketProductHandler_Misc extends SEEDBasketProductHandler
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }

    function ProductDefine0( KeyFrameUIForm $oFormP )
    {
        $s = "<h3>Misc Payment Definition Form</h3>";

        $s .= $oFormP->HiddenKey()
             ."<table>"
             .$oFormP->ExpandForm(
                     "||| Seller        || [[text:uid_seller|readonly]]"
                    ."||| Product type  || [[text:product_type|readonly]]"
                    ."||| Quantity type || [[text:quant_type|readonly]]"
                    ."||| Status        || ".$oFormP->Select2( 'eStatus', array('ACTIVE'=>'ACTIVE','INACTIVE'=>'INACTIVE','DELETED'=>'DELETED') )
                    ."<br/><br/>"
                    ."||| Title EN      || [[text:title_en]]"
                    ."||| Title FR      || [[text:title_fr]]"
                    ."||| Name          || [[text:name]]"
                     )
             ."</table> ";

        return( $s );
    }
}

class SEEDBasketProductHandler_Seeds extends SEEDBasketProductHandler
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }

    function ProductDefine0( KeyFrameUIForm $oFormP )
    /************************************************
        Draw a form to edit the given product.
        If _key==0 draw a New product form.
     */
    {
        /* Override this with a form for the product type
         */

        $s = "<h3>Default Product Form</h3>";

        $s .= $oFormP->HiddenKey()
             ."<table>"
             .$oFormP->ExpandForm(
                     "||| Seller        || [[text:uid_seller|readonly]]"
                    ."||| Product type  || [[text:product_type|readonly]]"
                    ."||| Quantity type || [[text:quant_type|readonly]]"
                    ."||| Status       || ".$oFormP->Select2( 'eStatus', array('ACTIVE'=>'ACTIVE','INACTIVE'=>'INACTIVE','DELETED'=>'DELETED') )
                    ."<br/><br/>"
                    ."||| Title EN  || [[text:title_en]]"
                    ."||| Title FR  || [[text:title_fr]]"
                    ."||| Name      || [[text:name]]"
                    ."||| Images    || [[text:img]]"
                    ."<br/><br/>"
                    ."||| Category  || [[text:v_t1]]"
                    ."||| Species   || [[text:v_t2]]"
                    ."||| Variety   || [[text:v_t3]]"
                     ."<br/><br/>"
                    ."||| Min in basket  || [[text:bask_quant_min]]"
                    ."||| Max in basket  || [[text:bask_quant_max]]"
                    ."<br/><br/>"
                    ."||| Price          || [[text:item_price]]"
                    ."||| Discount       || [[text:item_discount]]"
                    ."||| Shipping       || [[text:item_shipping]]"
                    ."||| Price U.S.     || [[text:item_price_US]]"
                    ."||| Discount U.S.  || [[text:item_discount_US]]"
                    ."||| Shipping U.S.  || [[text:item_shipping_US]]"

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