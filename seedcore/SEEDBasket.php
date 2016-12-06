<?php

/* SEEDBasket.php
 *
 * Copyright (c) 2016 Seeds of Diversity Canada
 *
 * Manage a shopping basket of diverse products
 */

include_once( "SEEDBasketDB.php" );
include_once( "SEEDBasketProductHandler.php" );
include_once( STDINC."KeyFrame/KFUIForm.php" );


class SEEDBasketCore
/*******************
    Core class for managing a shopping basket
 */
{
    public $oDB;
    public $sess;   // N.B. user might not be logged in so use $this->GetUID() instead of $this->sess->GetUID()

    private $raHandlerDefs;
    private $raHandlers = array();
    private $kBasket;

    function __construct( KeyFrameDB $kfdb, SEEDSession $sess, $raHandlerDefs )
    {
        $this->sess = $sess;
        $this->oDB = new SEEDBasketDB( $kfdb, $this->GetUID_SB() );
        $this->raHandlerDefs = $raHandlerDefs;
        $this->kBasket = $this->GetBasketKey();
    }

    function GetBasketKey()
    {
        if( !$this->kBasket ) {
            $oSVA = new SEEDSessionVarAccessor( $this->sess, "SEEDBasket" );
            $this->kBasket = $oSVA->VarGetInt( 'kBasket' );
        }
        return( $this->kBasket );
    }

    function SetBasketKey( $kB )
    {
        $this->kBasket = $kB;
        $oSVA = new SEEDSessionVarAccessor( $this->sess, "SEEDBasket" );
        $oSVA->VarSet( 'kBasket', $kB );
    }

    function GetUID_SB()
    /*******************
     */
    {
        return( method_exists( $this->sess, 'GetUID' ) ? $this->sess->GetUID() : 0 );
    }

    function DrawProductNewForm( $sProductType, $cid = 'A' )
    /*******************************************************
        Draw a new product form for a given product type
     */
    {
        return( $this->drwProductForm( 0, $sProductType, $cid ) );
    }

    function DrawProductForm( $kP, $cid = 'A' )
    /******************************************
        Update and draw the form for an existing product
     */
    {
        return( $this->drwProductForm( $kP, "", $cid ) );
    }

    private function drwProductForm( $kP, $sProductType_ifNew, $cid )
    /****************************************************************
        Multiplex a New and Edit form.
        New:  kP==0, $sProductType specified
        Edit: kP<>0, $sProductType==""
     */
    {
        $s = "";

        // Catch-22: need to know the product_type to get the oHandler, but need the oHandler to get ProductDefine1 before loading the kfr.
        // Solution: load the current record to get the oHandler, Update(), and reload the record.
        if( $kP ) {
            if( !($kfrP = $this->oDB->GetKfrel("P")->GetRecordFromDBKey( $kP )) ) goto done;
            $sPT = $kfrP->Value('product_type');
        } else {
            $sPT = $sProductType_ifNew;
        }
        if( !($oHandler = $this->getHandler( $sPT )) )  goto done;

        /* Create a form with the correct ProductDefine1() and use that to Update any current form submission,
         * then load up the current product (or create a new one) and draw the form for it.
         */
        $oFormP = new KeyFrameUIForm( $this->oDB->GetKfrel("P"), $cid,
                                      array('DSParms'=>array('fn_DSPreStore'=>array($oHandler,'ProductDefine1'))) );
        $oFormP->Update();

        if( $kP ) {
            $kfrP = $this->oDB->GetKfrel("P")->GetRecordFromDBKey( $kP );
        } else {
            if( ($kfrP = $this->oDB->GetKfrel("P")->CreateRecord()) ) {
                $kfrP->SetValue( 'product_type', $sProductType_ifNew );

                // force per-prodtype fixed values
                if( isset(SEEDBasketProducts_SoD::$raProductTypes[$sProductType_ifNew]['forceFlds']) ) {
                    foreach( SEEDBasketProducts_SoD::$raProductTypes[$sProductType_ifNew]['forceFlds'] as $k => $v ) {
                        $kfrP->SetValue( $k, $v );
                    }
                }
            }
        }
        if( !$kfrP ) goto done;

        $oFormP->SetKFR( $kfrP );

        // This part is the common form setup for all products
        if( !$oFormP->Value('uid_seller') ) {
            if( !($uid = $this->GetUID_SB()) ) die( "ProductDefine0 not logged in" );

            $oFormP->SetValue( 'uid_seller', $uid );
        }

        // This part is the custom form setup for the productType
        $s = $oHandler->ProductDefine0( $oFormP );

        done:
        return( $s );
    }

    function DrawProduct( KFRecord $kfrP, $bDetail )
    {
        return( ($oHandler = $this->getHandler( $kfrP->Value('product_type') ))
                ? $oHandler->ProductDraw( $kfrP, $bDetail ) : "" );
    }

    function DrawPurchaseForm( $prodName )
    /*************************************
        Given a product name, get the form that you would see in a store for purchasing it
     */
    {
        $s = "";

        if( !($kfrP = $this->oDB->GetKFRCond( 'P', "name='".addslashes($prodName)."'" )) ) {
            $s .= "<div style='display:inline-block' class='alert alert-danger'>Unknown product $prodName</div>";
            goto done;
        }

        $oHandler = $this->getHandler( $kfrP->Value('product_type') );
        $s .= $oHandler->Purchase0( $kfrP );

        done:
        return( $s );
    }

    function DrawBasketContents( $kBP = 0 )
    /**************************************
        Draw the contents of the current basket. If kBP is set, highlight it.
     */
    {
        $s = "";

        if( !$this->kBasket ) goto done;

        if( ($kfrBPxP = $this->oDB->GetKFRC( 'BPxP', "fk_SEEDBasket_Baskets='{$this->kBasket}'" )) ) {
            while( $kfrBPxP->CursorFetch() ) {
                $oHandler = $this->getHandler( $kfrBPxP->Value('P_product_type') );
                $sClass = ($kBP && $kBP == $kfrBPxP->Key()) ? " sb_bp-change" : "";
                $s .= "<div class='sb_bp$sClass'>"
                     .$oHandler->PurchaseDraw( $kfrBPxP )
                     ."<div style='display:inline-block;float:right;padding-left:10px' onclick='RemoveFromBasket(".$kfrBPxP->Key().");'>"
                         // use full url instead of W_ROOT because this html can be generated via ajax (so not a relative url)
                         ."<img class='slsrcedit_cvBtns_del' height='14' src='http://seeds.ca/w/img/ctrl/delete01.png'/>"
                         ."</div>"
                     ."<div style='display:inline-block;float:right'>$".$kfrBPxP->Value('P_item_price')."</div>"
                     ."</div>";
            }
        }

        $s = "<div class='sb_basket-contents'>$s</div>";

        done:
        return( $s ? $s : "Your Basket is Empty" );
    }

    function AddProductToBasket_kProd( $kP, $raParmsBP, $bGPC = false )
    /******************************************************************
        Add a product to the current basket.
        $raBP are the BxP parameters with names appropriate for http, bGPC is true if raBP are http
     */
    {
// Verify that basket is open

        return( ($kfrP = $this->oDB->GetProduct( 'P', $kP ))
                  ? $this->addProductToBasket( $kfrP, $raParmsBP, $bGPC )
                  : array( false, "There is no product '$kP'" ) );
    }

    function AddProductToBasket_Name( $prodName, $raParmsBP, $bGPC = false )
    /***********************************************************************
        Add a named product to the current basket.
     */
    {
// Verify that basket is open

        return( ($kfrP = $this->oDB->GetKFRCond( 'P', "name='".addslashes($prodName)."'" ))
                  ? $this->addProductToBasket( $kfrP, $raParmsBP, $bGPC )
                  : array( false, "There is no product called '$prodName'" ) );
    }

    private function addProductToBasket( KFRecord $kfrP, $raParmsBP, $bGPC )
    {
        $oHandler = $this->getHandler( $kfrP->Value('product_type') );
        return( ($kBP = $oHandler->Purchase2( $kfrP, $raParmsBP, $bGPC ))
                  ? array( true, $this->DrawBasketContents( $kBP ) )
                  : array( false, "" ) );
    }

    function DeleteProductFromBasket( $kBP )
    /***************************************
        Delete the given BP from the current basket
     */
    {
// Verify that basket is open
// Verify that kBP belongs to the current basket
        $bOk = false;
        $s = "";

        if( ($kfrBPxP = $this->oDB->GetKFR( 'BPxP', $kBP )) ) {
            $oHandler = $this->getHandler( $kfrBPxP->Value('P_product_type') );
            $bOk = $oHandler->PurchaseDelete( $kfrBPxP );   // takes a kfrBP
        }
        // always return the current basket because some interfaces will just draw it no matter what happened
        $s = $this->DrawBasketContents();
        return( array($bOk,$s) );
    }


    function GetCurrentBasketKFR()
    /*****************************
        Return a kfr of the current basket.
        If there isn't one, create one and start using it.
     */
    {
        if( $this->kBasket ) {
            $kfrB = $this->oDB->GetBasket( $this->kBasket );
        } else {
            // create a new basket and save it
            $kfrB = $this->oDB->GetKfrel('B')->CreateRecord();
            $kfrB->PutDBRow();
            $this->SetBasketKey( $kfrB->Key() );    // sets $this->kBasket and stores in session var
        }

        return( $kfrB );
    }


    private function getHandler( $prodType )
    {
        if( isset($this->raHandlers[$prodType]) )  return( $this->raHandlers[$prodType] );

        $o = null;
        if( isset($this->raHandlerDefs[$prodType]['classname']) ) {
            $o = new $this->raHandlerDefs[$prodType]['classname']( $this );
            if( $o ) {
                $this->raHandlers[$prodType] = $o;
            }
        } else {
            // the base class can handle some basic stuff but you should really make a derived class for every product type
            $o = new SEEDBasketProductHandler( $this );
        }
        return( $o );
    }


}

?>
