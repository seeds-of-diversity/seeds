<?php

/* SEEDBasketProductHandler.php
 *
 * Copyright (c) 2016-2021 Seeds of Diversity Canada
 *
 * Base class of the product handlers that you use whenever you do anything involving a product
 */


class SEEDBasketProductHandler
/*****************************
    Every time you do something with a product, you use a derivation of this.
    So you have to make a Handler for every productType that you use.

    ProductDefine0          Draw a form to create/update a product definition
    ProductDefine1          Validate a product definition
    ProductDefine2PostStore Called after a successful Store
    ProductDraw( eDetail )  Show a description of a product in more or less detail
    ProductDelete( bHard )  Remove a product from the system (only does soft delete if the product is referenced by any BP)

    Purchase0               Draw a form for the purchase details stored in a BasketXProduct
    Purchase1               Validate a purchase before adding/updating a BP
    Purchase2               Add/update a BP
    PurchaseDraw( bDetail ) Show a description of a BP in more or less detail, from a buyer's perspective
    PurchaseDelete          Remove a BP from its basket

    FulfilDraw( bDetail )   Show a description of a BP in more or less detail, from the seller's perspective
 */
{
    const DETAIL_TINY    = "Tiny";
    const DETAIL_SUMMARY = "Summary";
    const DETAIL_ALL     = "All";
    // These can be extended: any derived product handler can accept any DETAIL_* string it wants to

    protected $oSB;

    private  $klugeUTF8 = false;
    function GetKlugeUTF8()         { return( $this->klugeUTF8 ); }
    function SetKlugeUTF8( $bUTF8 ) { $this->klugeUTF8 = $bUTF8; }


    function __construct( SEEDBasketCore $oSB )
    {
        $this->oSB = $oSB;
    }

    // If the product form is interactive ajax, the first method here should return true and the second should draw the form
    function ProductFormIsAjax()         { return( false ); }
    function ProductFormDrawAjax( $kP )  { return( "OVERRIDE ProductFormDrawAjax()" ); }


    function ProductDefine0( KeyFrameForm $oFormP )
    /**********************************************
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
                     "||| Seller       || [[text:uid_seller|readonly]]"
                    ."||| Product type || [[text:product_type]]"
                    ."||| Status       || ".$oFormP->Select( 'eStatus', array('ACTIVE'=>'ACTIVE','INACTIVE'=>'INACTIVE','DELETED'=>'DELETED') )
                    ."<br/><br/>"
                    ."||| Title EN  || [[text:title_en]]"
                    ."||| Title FR  || [[text:title_fr]]"
                    ."||| Name     || [[text:name]]"
                    ."||| Images    || [[text:img]]"
                    ."<br/><br/>"
                    ."||| Quantity type  || ".$oFormP->Select( 'quant_type', array('ITEM-N'=>'ITEM-N','ITEM-1'=>'ITEM-1','MONEY'=>'MONEY') )
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

    function ProductDefine1( Keyframe_DataStore $oDS )
    /*************************************************
        Validate a new/updated product definition.
        Return true if the product definition makes sense, otherwise false and an error message.
     */
    {
        // set current user as seller if seller is not defined
        if( !$oDS->Value('uid_seller') ) {
            if( !($uid = $this->oSB->GetUID_SB()) ) die( "ProductDefine1 not logged in" );

            $oDS->SetValue( 'uid_seller', $uid );
        }

        if( $oDS->Value('bask_quant_min') > $oDS->Value('bask_quant_max') ) {
            $oDS->SetValue( 'bask_quant_max', $oDS->Value('bask_quant_min') );
        }

        return( true );
    }

    function ProductDefine2PostStore( KeyframeRecord $kfrP, KeyframeForm $oFormP )
    /*****************************************************************************
        Called after a successful Update().Store
     */
    {
        // e.g. a derived class might store metadata in SEEDBasket_ProdExtra
    }

    function ProductDraw( KeyframeRecord $kfrP, $eDetail, $raParms = [] )
    /*****************************************************
        Show a product definition in more or less detail
     */
    {
        // Override this with a product type specific method

        if( !$kfrP ) return( "Error: no product record" );

        switch( $eDetail ) {
            case SEEDBasketProductHandler::DETAIL_TINY:
                $s = $kfrP->Expand( "<p>[[title_en]] ([[product_type]]:[[name]])</p>" );
                break;
            case SEEDBasketProductHandler::DETAIL_SUMMARY:
            case SEEDBasketProductHandler::DETAIL_ALL:
            default:
                $s = $kfrP->Expand( "<h4>[[title_en]] ([[product_type]]:[[name]])</h4>" )
                                   .$this->ExplainPrices( $kfrP );
        }

        return( $s );
    }

    function ProductDelete( $kP, $bHardDelete = false )
    /**************************************************
         Remove a product from the system.
         Soft delete just inactivates and hides the record.
         Hard delete actually deletes the record (not allowed if a BP refers to it).
     */
    {
        if( ($kfrP = $this->oSB->oDB->GetProduct( $kP )) ) {
            $kfrP->SetValue( 'eStatus', 'DELETED' );

            // do hard delete only if there is not a BP that references the product
            if( $bHardDelete && !($kfrBPDummy = $this->oSB->oDB->GetKFRCond( "BP", "fk_SEEDBasket_Products='$kP'" )) ) {
                $kfrP->StatusSet( KFRECORD_STATUS_DELETED );    // not really all that hard of a delete anyway
            }

            $kfrP->PutDBRow();
        }
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
        return( $kfrP->Value('title_en') );
    }

    function Purchase1( KeyframeRecord $kfrP )
    /*****************************************
        Given a product, determine whether it can be added to the current basket.
        e.g. some combinations of items might not be allowed at the same time
     */
    {
        $bOk = true;
        $sErr = "";

        return( [$bOk,$sErr] );     // return false to disallow purchase and explain why
    }

    function Purchase2( KeyframeRecord $kfrP, $raPurchaseParms )
    /***********************************************************
        Given a product, add it to the current basket and return the new kBP

        raPurchaseParms:
            n (int)    : quantity to add
            f (float)  : amount to add
            * (string) : any parms from Purchase0 that were prefixed with sbp_ (prefix has been removed)

        Return the new BP._key
     */
    {
        if( !($kfrB = $this->oSB->GetCurrentBasketKFR()) )  goto done;

        if( ($kfrBP = $this->oSB->oDB->GetKfrel('BP')->CreateRecord()) ) {
            $kfrBP->SetValue( 'fk_SEEDBasket_Products', $kfrP->Key() );
            $kfrBP->SetValue( 'fk_SEEDBasket_Baskets', $kfrB->Key() );

            $kfrBP->SetValue( 'n', @$raPurchaseParms['n'] ?: 0 );
            $kfrBP->SetValue( 'f', @$raPurchaseParms['f'] ?: 0.0 );
            unset( $raPurchaseParms['n'] );
            unset( $raPurchaseParms['f'] );

            if( count($raPurchaseParms) ) {
                $kfrBP->SetValue( 'sExtra', SEEDCore_ParmsRA2URL($raPurchaseParms) );
            }

            $kfrBP->PutDBRow();
        }

        done:
        return( $kfrBP ? $kfrBP->Key() : 0 );
    }

    function PurchaseDraw( KeyframeRecord $kfrBPxP, $raParms = [] )
    /**************************************************************
        Draw a product in a basket, in more or less detail.

        raParms:
            eDetail: SEEDBasketProductHandler::DETAIL_*
            bUTF8:   output in utf8
     */
    {
        $s = $kfrBPxP->Value( 'P_title_en' );

        if( $kfrBPxP->Value('quant_type') == 'ITEM_N' && ($n = $kfrBPxP->Value('n')) > 1 ) {
            $s .= " ($n @ ".$this->oSB->dollar($this->oSB->priceFromRange($kfrBPxP->Value('item_price'), $n)).")";
        }

        return( $s );
    }

    function PurchaseIsFulfilled( SEEDBasket_Purchase $oPurchase )  /* deprecate: use SEEDBasket_Purchase::IsFulfilled()
    /*************************************************************
        Return true if this purchase has already been fulfilled
     */
    {
        return( false );
    }

    function PurchaseFulfil( SEEDBasket_Purchase $oPurchase )
    /********************************************************
        The seller wants to record that this purchase has been fulfilled.
     */
    {
        return( SEEDBasket_Purchase::FULFIL_RESULT_FAILED );    // you can't fulfil a purchase using the base class because it has nothing to record
    }

    function PurchaseDelete( KeyframeRecord $kfrBP )  // this can also receive a kfrBPxP or kfrBPxPxB
    /***********************************************
        Delete the given BP from the current basket.

        No need to do a KF soft delete.
     */
    {
        $bOk = false;

        if( !$this->oSB->BasketIsOpen() ) goto done;
// should this verify that the BP belongs to this basket? Every derivation would have to, unless the Core checks this and everyone promises not to call here independently

        $bOk = $kfrBP->KFrel()->KFDB()->Execute( "DELETE FROM {$this->oSB->oApp->GetDBName('seeds1')}.SEEDBasket_BP WHERE _key='".$kfrBP->Key()."'" );

        done:
        return( $bOk );
    }


    /*****
        Support methods
     */

    function ExplainPrices( KeyframeRecord $kfrP )
    {
        $s = "";

        if( $kfrP->Value('item_price') )       $s .= "<br/>Price: ".$this->ExplainPriceRange( $kfrP->Value('item_price') );
        if( $kfrP->Value('item_discount') )    $s .= "<br/>Discount: ".$this->ExplainPriceRange( $kfrP->Value('item_discount') );
        if( $kfrP->Value('item_shipping') )    $s .= "<br/>Shipping: ".$this->ExplainPriceRange( $kfrP->Value('item_shipping') );

        if( $kfrP->Value('item_price_US') )    $s .= "<br/>Price U.S.: ".$this->ExplainPriceRange( $kfrP->Value('item_price_US') );
        if( $kfrP->Value('item_discount_US') ) $s .= "<br/>Discount U.S.: ".$this->ExplainPriceRange( $kfrP->Value('item_discount_US') );
        if( $kfrP->Value('item_shipping_US') ) $s .= "<br/>Shipping U.S.: ".$this->ExplainPriceRange( $kfrP->Value('item_shipping_US') );

        return( $s );
    }

    function ExplainPriceRange( $sRange )
    /************************************
        Explain the contents of a price range

        e.g. '15', '15:1-9,12:10-19,10:20+'
     */
    {
        $s = "";

        if( strpos( $sRange, ',' ) === false && strpos( $sRange, ':' ) === false ) {
            // There is just a single price for all quantities
            $s = $this->oSB->dollar( $sRange );
        } else {
            $raRanges = explode( ',', $sRange );
            foreach( $raRanges as $r ) {
                $r = trim($r);

                // $r has to be price:N or price:M-N or price:M+
                list($price,$sQRange) = explode( ":", $r );
                if( strpos( '-', $sQRange) !== false ) {
                    list($sQ1,$sQ2) = explode( '-', $sQRange );
                    $s .= ($s ? ", " : "").$this->oSB->dollar($price)." for $sQ1 to $sQ2 items";
                } else if( substr( $sQRange, -1, 1 ) == "+" ) {
                    $sQ1 = intval($sQRange);
                    $s .= ($s ? ", " : "").$this->oSB->dollar($price)." for $sQ1 items or more";
                } else {
                    $s .= ($s ? ", " : "").$this->oSB->dollar($price)." for $sQRange items";
                }


            }
        }

        return( $s );
    }


    function GetProductValues( KeyframeRecord $kfrP, $raParms = array() )
    /********************************************************************
        Given a product record, return an array of values normalized for the product type.
     */
    {
        return( $kfrP->ValuesRA() );    // this doesn't do anything - please override
    }

    function SetProductValues( $raNormal )
    /*************************************
        Given an array of normalized product values, return two arrays: SEEDBasket_Products values and SEEDBasket_ProdExtra values
     */
    {
        return( array($raNormal,array()) );    // this doesn't do anything - please override
    }
}


class SEEDBasketProductHandler_Item1 extends SEEDBasketProductHandler
/***********************************
    A generic product handler for quant_type ITEM-1
 */
{
    function __construct( SEEDBasketCore $oSB ) { parent::__construct( $oSB ); }

    function ProductDefine0_Item1( KeyframeForm $oFormP, $sItemLabel )
    {
        $oFormX = new SEEDFormExpand( $oFormP );

        $s = "<h3>$sItemLabel Definition Form</h3>";

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
}


class SEEDBasketProductHandler_ItemN extends SEEDBasketProductHandler
/***********************************
    A generic product handler for quant_type ITEM-N
 */
{
    function __construct( SEEDBasketCore $oSB ) { parent::__construct( $oSB ); }

    function ProductDefine0_ItemN( KeyframeForm $oFormP, $sItemLabel )
    {
        $oFormX = new SEEDFormExpand( $oFormP );

        $s = "<h3>$sItemLabel Definition Form</h3>";

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
    /********************************************************
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

class SEEDBasketProductHandler_MONEY extends SEEDBasketProductHandler
/***********************************
    A generic product handler for quant_type MONEY
 */
{
    function __construct( SEEDBasketCore $oSB ) { parent::__construct( $oSB ); }

    function ProductDefine0_MONEY( KeyframeForm $oFormP, $sItemLabel )
    {
        $oFormX = new SEEDFormExpand( $oFormP );

        $s = "<h3>$sItemLabel Definition Form</h3>";

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
