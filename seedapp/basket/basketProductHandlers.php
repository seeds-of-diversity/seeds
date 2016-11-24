<?php

/* Basket product handlers
 *
 * Copyright (c) 2016 Seeds of Diversity Canada
 */

include_once( SEEDCORE."SEEDBasket.php" );


class SEEDBasketProductHandler_Membership extends SEEDBasketProductHandler
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }

    function ProductDefine0( KeyFrameUIForm $oFormP )
    {
        $s = "<h3>Membership Definition Form</h3>";

        if( !$oFormP->GetKey() ) {
            // initialize values for new form
            $oFormP->SetValue( 'quant_type', "ITEM-1" );
        }

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
}

class SEEDBasketProductHandler_Donation extends SEEDBasketProductHandler
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }
}

class SEEDBasketProductHandler_Book extends SEEDBasketProductHandler
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }

    function ProductDefine0( KeyFrameUIForm $oFormP )
    {
        $s = "<h3>Publications Product Form</h3>";

        if( !$oFormP->GetKey() ) {
            // initialize values for new form
            $oFormP->SetValue( 'quant_type', "ITEM-N" );
        }

        $s .= $oFormP->HiddenKey()
             ."<table>"
             .$oFormP->ExpandForm(
                     "||| Seller       || [[text:uid_seller|readonly]]"
                    ."||| Product type || [[text:product_type]]"
                    ."||| Status       || ".$oFormP->Select2( 'eStatus', array('ACTIVE'=>'ACTIVE','INACTIVE'=>'INACTIVE','DELETED'=>'DELETED') )
                    ."<br/><br/>"
                    ."||| Title EN  || [[text:title_en]]"
                    ."||| Title FR  || [[text:title_fr]]"
                    ."||| Name     || [[text:name]]"
                    ."||| Images    || [[text:img]]"
                    ."<br/><br/>"
                    ."||| Quantity type  || ".$oFormP->Select2( 'quant_type', array('ITEM-N'=>'ITEM-N','ITEM-1'=>'ITEM-1','MONEY'=>'MONEY') )
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
}

class SEEDBasketProductHandler_Seeds extends SEEDBasketProductHandler
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }
}

class SEEDBasketProductHandler_Event extends SEEDBasketProductHandler
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }
}

?>