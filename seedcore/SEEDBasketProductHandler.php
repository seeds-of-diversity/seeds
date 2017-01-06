<?php

/* SEEDBasketProductHandler.php
 *
 * Copyright (c) 2016 Seeds of Diversity Canada
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

    protected $oSB;

    function __construct( SEEDBasketCore $oSB )
    {
        $this->oSB = $oSB;
    }

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

    function ProductDefine1( KeyFrameDataStore $oDS )
    /************************************************
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

    function ProductDefine2PostStore( KFRecord $kfrP, KeyFrameUIForm $oFormP )
    /*************************************************************************
        Called after a successful Update().Store
     */
    {
        // e.g. a derived class might store metadata in SEEDBasket_ProdExtra
    }

    function ProductDraw( KFRecord $kfrP, $eDetail )
    /***********************************************
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

    function Purchase0( KFRecord $kfrP )
    /***********************************
        Given a product, draw the form that a store would show to purchase it.
        Form parms can be:
            n     (int)
            f     (float)
            sbp_* (string)
     */
    {
        return( $kfrP->Value('title_en') );
    }

    function Purchase2( KFRecord $kfrP, $raPurchaseParms )
    /*****************************************************
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

            $kfrBP->SetValue( 'n', @$raPurchaseParms['n'] );
            $kfrBP->SetValue( 'f', @$raPurchaseParms['f'] );
            unset( $raPurchaseParms['n'] );
            unset( $raPurchaseParms['f'] );

            if( count($raPurchaseParms) ) {
                $kfrBP->SetValue( 'sExtra', SEEDStd_ParmsRA2URL($raPurchaseParms) );
            }

            $kfrBP->PutDBRow();
        }

        done:
        return( $kfrBP ? $kfrBP->Key() : 0 );
    }

    function PurchaseDraw( KFRecord $kfrBPxP, $bDetail = false )
    /***********************************************************
        Draw a product in a basket, in more or less detail.
     */
    {
        $s = $kfrBPxP->Value( 'P_title_en' );

        if( $kfrBPxP->Value('quant_type') == 'ITEM_N' && ($n = $kfrBPxP->Value('n')) > 1 ) {
            $s .= " ($n @ ".$this->oSB->dollar($this->oSB->priceFromRange($kfrBPxP->Value('item_price'), $n)).")";
        }

        return( $s );
    }

    function PurchaseDelete( KFRecord $kfrBP )  // this can also receive a kfrBPxP or kfrBPxPxB
    /*****************************************
        Delete the given BP from the current basket.

        No need to do a KF soft delete.
     */
    {
        $bOk = false;

        if( !$this->oSB->BasketIsOpen() ) goto done;
// should this verify that the BP belongs to this basket? Every derivation would have to, unless the Core checks this and everyone promises not to call here independently

        $bOk = $kfrBP->kfrel->kfdb->Execute( "DELETE FROM seeds.SEEDBasket_BP WHERE _key='".$kfrBP->Key()."'" );

        done:
        return( $bOk );
    }


    /*****
        Support methods
     */

    function ExplainPrices( $kfrP )
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
}

?>
