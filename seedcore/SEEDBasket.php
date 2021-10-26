<?php

/* SEEDBasket.php
 *
 * Copyright (c) 2016-2020 Seeds of Diversity Canada
 *
 * Manage a shopping basket of diverse products
 */

include_once( "SEEDBasketDB.php" );
include_once( "SEEDBasketProductHandler.php" );
include_once( "SEEDBasketUpdater.php" );
include_once( SEEDROOT."Keyframe/KeyframeForm.php" );


class SEEDBasketBuyer
/********************
    Manage shopping baskets containing diverse products
 */
{
    private $oSB;
    private $kfrBasketCurr = null;       // always access this via GetCurrentBasketKFR/GetBasketKey

    function __construct( SEEDBasketCore $oSB )
    {
        $this->oSB = $oSB;
    }
}

class SEEDBasketBuyerSession
/***************************
    Manage a basket in a current user session.
    This does the same things as SEEDBasketBuyer but using the basket found in the current user session.

EVERYTHING IN SEEDBasketCore that involves a current basket should go here instead
 */
{
    private $oSB;
    private $oBuyer;

    function __construct( SEEDBasketCore $oSB )
    {
        $this->oSB = $oSB;
        $this->oBuyer = new SEEDBasketBuyer( $oSB );
    }


}

class SEEDBasketCore
/*******************
    Core class for advertising and selling products, buying them, and fulfilling orders

    SEEDBasketBuyer* uses this to manage shopping baskets
    SEEDBasketProductHandler_* uses this to create and advertise products
    SEEDBasketFulfillment uses this to fulfil orders
 */
{
    public $oApp;
    public $oDB;
    public $sess;   // N.B. user might not be logged in so use $this->GetUID() instead of $this->sess->GetUID()
                    // No, make sure this is always a SEEDSessionAccount (it's SEEDSession in the constructor!) and it will do the right thing

    private $raHandlerDefs;
    private $raHandlers = array();
    private $raParms = array();
    private $kfrBasketCurr = null;       // always access this via GetCurrentBasketKFR/GetBasketKey

    private $dbname;

    function __construct( KeyframeDatabase $kfdb = null, SEEDSession $sess = null,      // deprecate these -- null means use oApp
                          SEEDAppConsole $oApp, array $raHandlerDefs, array $raParms = [] )
    {
        /* raParms:
         *     sbdb_config              => raConfig for SEEDBasketDB
         *     sbdb                     => logical name of SEEDBasket's db (can also be sbdb_config.sbdb)
         *     fn_sellerNameFromUid     => callback to get the seller's name from the uid_seller
         */
// deprecate these, use oApp instead
if( !$kfdb ) $kfdb = $oApp->kfdb;
if( !$sess ) $sess = $oApp->sess;

        $this->oApp = $oApp;
        $this->sess = $sess;

        // sbdb can be specified in two places
        $raSBDBConfig = @$raParms['sbdb_config'] ?: [];
        if( @$raParms['sbdb'] ) $raSBDBConfig['sbdb'] = $raParms['sbdb'];

        // save the db name for below; sql requiring dbname should be encapsulated into SEEDBasketDB
        if( ($sbdb = @$raSBDBConfig['sbdb']) ) {
            global $config_KFDB;
            $this->dbname = $config_KFDB[$sbdb]['kfdbDatabase'];
        } else {
            $this->dbname = $kfdb->GetDB();
        }

        $this->oDB = new SEEDBasketDB( $kfdb, $this->GetUID_SB(), $oApp->logdir ?: @$raParms['logdir'], // deprecate raParms['logdir']
                                       $raSBDBConfig );
        $this->raHandlerDefs = $raHandlerDefs;
        $this->GetCurrentBasketKFR();
        $this->raParms = $raParms;
    }

    function Cmd( $cmd, $raParms = array() )
    /***************************************
klugeUTF8 = true: return sOut and sErr in utf8
     */
    {
        $raOut = array( 'bHandled'=>false, 'bOk'=>false, 'sOut'=>"", 'sErr'=>"" );

        switch( strtolower($cmd) ) {    // don't have to strip slashes because no arbitrary commands
// move this to SEEDBasketBuyerSession and use SEEDBasketCore::AddProductToBasket($b,$p)
            case "sb-addtobasket":
            case "addtobasket":     // deprecate
                /* Add a product to the current basket
                 * 'sb_product' = name of product to add to current basket (if it is_numeric this is the product _key)
                 * $raParms also contains BP parameters prefixed by sb_*
                 */
                $raOut['bHandled'] = true;
                $kfrP = null;

                if( ($prodName = SEEDInput_Str('sProduct')) ) {
                    if( is_numeric($prodName) ) {
                        $kfrP = $this->oDB->GetProduct( intval($prodName) );
                    } else {
                        $kfrP = $this->oDB->GetKFRCond( 'P', "name='".addslashes($prodName)."'" );
                    }
                }
                if( !$kfrP ) {
                    $raOut['sErr'] = "There is no product '$prodName'";
                    goto done;
                }
                list($raOut['bOk'],$raOut['sOut'],$raOut['sErr']) = $this->addProductToBasket( $kfrP, $raParms, @$raParms['klugeUTF8'] );
                break;

            case "sb-removefrombasket":
            case "removefrombasket":    // deprecate
// move this to SEEDBasketBuyerSession and use SEEDBasketCore::RemoveProductFromBasket($bp)
                // kBP = key of purchase to remove
                $raOut['bHandled'] = true;

                if( ($kBP = SEEDInput_Int('kBP')) ) {
                    list($raOut['bOk'],$raOut['sOut']) = $this->removeProductFromBasket( $kBP, @$raParms['klugeUTF8'] );
                }
                break;

            case "sb-clearbasket":
            case "clearbasket":     // deprecate
// move this to SEEDBasketBuyerSession and use SEEDBasketCore::ClearBasket($b)
                $raOut['bHandled'] = true;

                list($raOut['bOk'],$raOut['sOut']) = $this->clearBasket( @$raParms['klugeUTF8'] );
                break;
        }

        done:
        return( $raOut );
    }

    function GetCurrentBasketKFR()
    /*****************************
        Find the current basket, set $this->kfrBasketCurr and return that.
        Always use this to get the current basket instead of accessing kfrBasketCurr directly

        The most recently created basket where uid_buyer==sess->GetUID is generally the current basket, regardless of its eStatus.
        In this situation, SVA.kBasket will normally be the same key as that basket.
        However, a non-login user can create a basket, load some products, and then login. The newer SVA.kBasket should override the uid_buyer basket.

        1) if you're logged in, and the most recent basket where uid_buyer==you matches SVA.kBasket, then obviously that's your current basket.
        2) if you're logged in, and the most recent basket where uid_buyer==you doesn't match SVA.kBasket, then you must have
           created a basket before you logged in, so SVA.kBasket wins and that becomes owned by you.
        3) if you're logged in and only one or the other exists, then that one is your current basket.
        4) if you're not logged in, and you have a SVA.kBasket, that's your current basket.
        5) if you don't have either one, whether logged in or not, this function returns null.

        The above simplifies to:
        if SVA.kBasket { that is your current basket, and its uid_buyer should be set to your uid }
          else if there is a most-recent basket where uid_buyer==you { that is your current basket }
          else { null }
    */
    {
        if( !$this->kfrBasketCurr ) {
            $kB = 0;

            /* Try to find the most recent basket, either by uid or by SVA.kBasket stored in SVA.
             */
            $oSVA = new SEEDSessionVarAccessor( $this->sess, "SEEDBasket" );
            if( ($kB = $oSVA->VarGetInt( 'kBasket' )) ) {
                $this->kfrBasketCurr = $this->oDB->GetBasket( $kB );
            } else if( ($uid = $this->sess->GetUID()) &&
// get the db name from SEEDBasketDB
                       ($kB = $this->oDB->kfdb->Query1( "SELECT _key FROM {$this->dbname}.SEEDBasket_Baskets WHERE uid_buyer='$uid' ORDER BY _created DESC LIMIT 1" )) )
            {
                // no because this gets your most recent membership renewal, book order, etc, which is very confusing and you don't want to add seeds to it because it should be closed but it isn't at present   $this->kfrBasketCurr = $this->oDB->GetBasket( $kB );
            }
            /* But if the most recent basket is no longer opened or confirmed, open a new basket
             */
            if( $this->kfrBasketCurr && !in_array( $this->kfrBasketCurr->Value('eStatus'), array("Open","Confirmed") ) ) {
                $this->kfrBasketCurr = null;
            }

            // Store the kBasket in this session so it survives if the user is not logged in
            $oSVA->VarSet( 'kBasket', $this->kfrBasketCurr ? $this->kfrBasketCurr->Key() : 0 );

            // if there is a current anonymous basket, and the user is logged in, it means that they just logged in so now they can own the basket
            if( $this->kfrBasketCurr && $this->kfrBasketCurr->Value('uid_buyer')==0 && $this->sess->GetUID() ) {
                $this->kfrBasketCurr->SetValue( 'uid_buyer', $this->sess->GetUID() );
                $this->kfrBasketCurr->PutDBRow();
            }
        }

        return( $this->kfrBasketCurr );
    }

    function GetBasketKey()
    {
        return( ($kfr = $this->GetCurrentBasketKFR()) ? $kfr->Key() : 0 );
    }

    function BasketAcquire()
    /***********************
        Find the current basket, or create a new one.
        We don't return the basket key, just to remind you to check BasketIsOpen() after you do this.
     */
    {
        if( !$this->GetBasketKey() ) {
            $kfrB = $this->oDB->GetKfrel("B")->CreateRecord();
            $kfrB->SetValue( 'uid_buyer', $this->sess->GetUID() );  // this is zero if !IsLogin
            $kfrB->PutDBRow();

            // Set basket key in the session so GetCurrentBasketKey will find it
            $oSVA = new SEEDSessionVarAccessor( $this->sess, "SEEDBasket" );
            $oSVA->VarSet( 'kBasket', $kfrB->Key() );
            $this->GetCurrentBasketKFR();
        }
    }

    function BasketIsOpen()
    /**********************
        True if there is a current basket and it is open for adding/updating/deleting by the purchaser
     */
    {
        return( $this->BasketStatusGet() == 'Open' );
    }

    function BasketStatusGet()
    /*************************
     */
    {
        return( ($kfr = $this->GetCurrentBasketKFR()) ? $kfr->Value('eStatus') : "Open" );
    }

    function BasketStatusSet( $eStatusChange )
    /*****************************************
     */
    {
        if( $this->kfrBasketCurr ) {
            $this->kfrBasketCurr->SetValue( 'eStatus', $eStatusChange );
            $this->kfrBasketCurr->PutDBRow();
        }
    }

    function GetUID_SB()
    /*******************
     */
    {
        return( method_exists( $this->sess, 'GetUID' ) ? $this->sess->GetUID() : 0 );
    }

    function CreateCursor( $sType, $sCond, $raKFRC )
    /***********************************************
        Return a cursor for the given kind of SEEDBasket object

            $sType = basket | product | purchase
     */
    {
        $o = new SEEDBasketCursor( $this, $sType, $sCond, $raKFRC );
        return( $o->IsValid() ? $o : null );
    }

    function DrawProduct( KeyframeRecord $kfrP, $eDetail, $raParms = [] )
    {
        return( ($oHandler = $this->getHandler( $kfrP->Value('product_type') ))
                ? $oHandler->ProductDraw( $kfrP, $eDetail, $raParms ) : "" );
    }

    function DrawPurchaseForm( $prodName, $raParms = [] )
    /*************************************
        Given a product name, get the form that you would see in a store for purchasing it
     */
    {
        $s = "";

        if( is_numeric($prodName) ) {
            $kfrP = $this->oDB->GetProduct( intval($prodName) );
        } else {
            $kfrP = $this->oDB->GetKFRCond( 'P', "name='".addslashes($prodName)."'" );
        }
        if( !$kfrP ) {
            $s .= "<div style='display:inline-block' class='alert alert-danger'>Unknown product $prodName</div>";
            goto done;
        }

        $oHandler = $this->getHandler( $kfrP->Value('product_type') );
        $s .= $oHandler->Purchase0( $kfrP, $raParms );

        done:
        return( $s );
    }

    function DrawBasketContents( $raParms = array(), $klugeUTF8 = false )
    /************************************************
        Draw the contents of the current basket.

        raParms:
            kBPHighlight : highlight this BP entry
     */
    {
        $s = "";

        if( $this->GetCurrentBasketKFR() && ($kBasket = $this->GetBasketKey()) ) {
            $oB = new SEEDBasket_Basket( $this, $kBasket );
            $s = $oB->DrawBasketContents( $raParms, $klugeUTF8 );
        }
        return( $s );
    }

    function dollar( $d )  { return( "$".sprintf("%0.2f", $d) ); }

    /**
        Command methods
     */

    private function addProductToBasket( KeyframeRecord $kfrP, $raParmsBP, $klugeUTF8 )
    {
        $kBPNew = 0;
        $sOut = $sErr = "";
        $bOk = false;

        // Create a basket if there isn't one, and make sure any existing basket is open.
        $this->BasketAcquire();
        if( !$this->BasketIsOpen() ) {
            $sErr = "Please login";     // one common reason why this fails (not the only one?)
            goto done;
        }

        // The input parms can be http or just ordinary arrays
        //     sb_n   (int):    quantity to add
        //     sb_f   (float):  amount to add
        //     sb_p_* (string): arbitrary parameters known to Purchase0 and Purchase2
        $raPurchaseParms = array();
        if( ($n = intval(@$raParmsBP['sb_n'])) )    $raPurchaseParms['n'] = $n;
        if( ($f = floatval(@$raParmsBP['sb_f'])) )  $raPurchaseParms['f'] = $f;
        foreach( $raParmsBP as $k => $v ) {
            if( substr($k,0,5) == 'sb_p_' && ($k1 = substr($k,5)) ) {
                $raPurchaseParms[$k1] = $v;
            }
        }

        if( ($oHandler = $this->getHandler( $kfrP->Value('product_type') )) ) {
            list($bPurchaseOk,$sErr1) = $oHandler->Purchase1( $kfrP );
            if( $bPurchaseOk && ($kBPNew = $oHandler->Purchase2( $kfrP, $raPurchaseParms )) ) {
                $sOut = $this->DrawBasketContents( ['kBPHighlight'=>$kBPNew], $klugeUTF8 );
                $bOk = true;
            }
        } else {
            $sErr .= $sErr1;
        }

        done:
        return( [$bOk, $sOut, $sErr] );
    }

    private function removeProductFromBasket( $kBP, $klugeUTF8 )
    /***********************************************
        Delete the given BP from the current basket
     */
    {
        $bOk = false;
        $s = "";

        if( !$this->BasketIsOpen() )  goto done;

        if( ($kfrBPxP = $this->oDB->GetKFR( 'BPxP', $kBP )) ) {
            // Verify that kBP belongs to the current basket
            if( $kfrBPxP->Value('fk_SEEDBasket_Baskets') == $this->GetBasketKey() ) {
                $oHandler = $this->getHandler( $kfrBPxP->Value('P_product_type') );
                $bOk = $oHandler->PurchaseDelete( $kfrBPxP );   // takes a kfrBP
            }
        }

        done:
        // always return the current basket because some interfaces will just draw it no matter what happened
        $s = $this->DrawBasketContents( [], $klugeUTF8 );
        return( array($bOk,$s) );
    }

    private function clearBasket( $klugeUTF8 )
    /*****************************
     */
    {
        $bOk = false;
        $s = "";

        if( !$this->BasketIsOpen() || !($kB = $this->GetBasketKey()) )  goto done;
// get the db name from SEEDBasketDB
        $bOk = $this->oDB->kfdb->Execute( "DELETE FROM {$this->dbname}.SEEDBasket_BP WHERE fk_SEEDBasket_Baskets='$kB'" );

        done:
        $s = $this->DrawBasketContents( [], $klugeUTF8 );
        return( array($bOk,$s) );
    }

    public function GetProductHandler( $prodType )
    {
        return( $this->getHandler( $prodType ) );
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


    function FindBasket( $sCond )
    /****************************
        Return a SEEDBasket_Basket for the product that matches the sql condition
            e.g. uid_buyer='1' AND eStatus='Paid'
        Only the first match is returned, if more than one product matches
     */
    {
        $ra = $this->oDB->GetBasketList( $sCond );
        return( @$ra[0]['_key'] ? new SEEDBasket_Product( $this, $ra[0]['_key'] ) : null );
    }

    function FindProduct( $sCond )
    /*****************************
        Return a SEEDBasket_Product for the product that matches the sql condition
            e.g. uid_seller='1' AND product_type='widget'
        Only the first match is returned, if more than one product matches
     */
    {
        $ra = $this->oDB->GetProductList( $sCond );
        return( @$ra[0]['_key'] ? new SEEDBasket_Product( $this, $ra[0]['_key'] ) : null );
    }

    function GetProductObj( int $kP, $prodType = "", $raConfig = [] )
    /****************************************************************
        Get a SEEDBasket_Product_{prodType} object for the given product

        $kP = 0, $prodType = "" : create an empty SEEDBasket_Product
        $kP = 0, $prodType<> "" : create an empty SEEDBasket_Product_{prodType}
        $kP<> 0, $prodType = "" : look up the product, find the prodType, then create a SEEDBasket_Product_{prodType} for $kP
        $kP<> 0, $prodType<> "" : create a SEEDBasket_Product_{prodType} for $kP using given prodType to skip the lookup, and make sure it's correct
     */
    {
        $oProd = null;
        $bCheckProdMatch = ($kP && $prodType);  // loading an existing product with a prodType hint, so at the end make sure the hint was correct

        // if an existing product and no prodType given, look it up
        if( $kP && !$prodType ) {
            // look up the product's product_type so the correct derived class can be created
            $oProd = new SEEDBasket_Product( $this, $kP, $raConfig );
            $prodType = $oProd->GetProductType();
        }

        // find the class name to instantiate
        $classname = $prodType && class_exists($c = "SEEDBasket_Product_$prodType") ? $c : "SEEDBasket_Product";

        // create the object
        $oProd = new $classname( $this, $kP, $raConfig );

        // if prodType hint was given as an arg, make sure it was correct
        if( $bCheckProdMatch && $oProd->GetProductType() != $prodType ) {
            // error message?
            $oProd = new SEEDBasket_Product( $this, $kP, $raConfig );   // will probably not do the right thing, but unlikely to do a bad thing
        }

        return( $oProd );
    }

    function GetPurchaseObj( int $kPur, $prodType = "", array $raParms = [] )
    /************************************************************************
        Get a SEEDBasket_Purchase_{prodType} object for the given purchase

        $kPur = 0, $prodType = "" : create an empty SEEDBasket_Purchase
        $kPur = 0, $prodType<> "" : create an empty SEEDBasket_Purchase_{prodType}
        $kPur<> 0, $prodType = "" : look up the purchase/product, find the prodType, then create a SEEDBasket_Purchase_{prodType} for $kPur
        $kPur<> 0, $prodType<> "" : create a SEEDBasket_Purchase_{prodType} for $kPur using given prodType to skip the lookup, and make sure it's correct

        raParms['kfr'] can hold a Purchase kfr, from which the above parameters can be taken instead
     */
    {
        $oPur = null;

        // this overrides kPur and prodType
        if( ($kfrPur = @$raParms['kfr']) ) {
            $kPur = $kfrPur->Key();
            $prodType = $kfrPur->Value('P_product_type');

            $bCheckProdMatch = false;   // only applies if loading the kfr based on kPur and prodType
        } else {
            $bCheckProdMatch = ($kPur && $prodType);  // loading an existing purchase with a prodType hint, so at the end make sure the hint was correct
        }

        // If an existing purchase and no prodType given, look it up using the base class so the correct derived class can be created.
        // This is redundant so it's preferred to provide the prodType and/or kfr in the args.
        if( $kPur && !$prodType ) {
            $prodType = (new SEEDBasket_Purchase( $this, $kPur ))->GetProductType();
        }

        // find the class name to instantiate
        $classname = $prodType && class_exists($c = "SEEDBasket_Purchase_$prodType") ? $c : "SEEDBasket_Purchase";

        // Create the object. If kfr is given in raParms it will be done without a redundant lookup
        $oPur = new $classname( $this, $kPur, $raParms );

        // if prodType hint was given as an arg and the kfr was not already provided, make sure it was correct
        if( $bCheckProdMatch && $oPur->GetProductType() != $prodType ) {
            // error message?
            $oPur = new SEEDBasket_Purchase( $this, $kPur, $raParms );   // will probably not do the right thing, but unlikely to do a bad thing
        }

        return( $oPur );
    }

    static function PriceFromRange( $sRange, $n )
    /********************************************
        Return the price of $n items given an item range
        e.g. 5                          $5 each
             5:1,4.5:2,4.1:3-5,4:6+     $5 for 1, $4.50 each for 2, $4.1 for 3-5, $4 for 6 or more
     */
    {
        $f = 0.00;

        if( strpos( $sRange, ',' ) === false && strpos( $sRange, ':' ) === false ) {
            // There is just a single price for all quantities
            $f = $sRange;
        } else {
            $raRanges = explode( ',', $sRange );
            foreach( $raRanges as $r ) {
                $r = trim($r);

                // $r has to be price:N or price:M-N or price:M+
                list($price,$sQRange) = explode( ":", $r );
                if( strpos( '-', $sQRange) !== false ) {
                    list($sQ1,$sQ2) = explode( '-', $sQRange );
                    if( $n >= intval($sQ1) && $n <= intval($sQ2) )  $f = $price;
                } else if( substr( $sQRange, -1, 1 ) == "+" ) {
                    $sQ1 = $sQRange;
                    if( $n >= intval($sQ1) )  $f = $price;
                } else {
                    $sQ1 = $sQRange;
                    if( $n == intval($sQ1) ) $f = $price;
                }

                if( $f ) break;
            }
        }
        return( floatval($f) );
    }
}


class SEEDBasket_Basket
/**********************
    Implement a basket
 */
{
    private $kfr;
    private $oSB;
    private $raObjPur = null;       // [] of SEEDBasket_Purchase in this basket (loaded on demand)
    private $raExtraItems = null;   // [] of extra items in this basket (loaded on demand)
    private $raProdTypes = null;    // [] of distinct product_types in this basked (loaded on demand)
    private $raComputed = null;     // computed contents (see ComputeBasketContents for format)

    function __construct( SEEDBasketCore $oSB, $kB )
    {
        $this->oSB = $oSB;
        $this->SetKey( $kB );
    }

    function Key()     { return( $this->kfr ? $this->kfr->Key() : 0 ); }
    function GetKey()  { return( $this->kfr ? $this->kfr->Key() : 0 ); }

    function SetKey( $k )
    {
        $this->kfr = $k ? $this->oSB->oDB->GetBasketKFR($k) : $this->oSB->oDB->GetBasketKFREmpty();
    }

    function GetBuyer()  { return( $this->kfr ? $this->kfr->Value('uid_buyer') : "" ); }
    function GetDate()   { return( $this->kfr ? $this->kfr->Value('_created') : "" ); }     // not sure this is always what you want
    function GetEStatus(){ return( $this->kfr ? $this->kfr->Value('eStatus') : "" ); }

    function SetValue( $k, $v ) { if( $this->kfr ) $this->kfr->SetValue( $k, $v ); }
    function PutDBRow()         { if( $this->kfr ) $this->kfr->PutDBRow(); }

    // intended to only be used by SEEDBasket internals e.g. SEEDBasketCursor::GetNext()
    function _setKFR( KeyframeRecord $kfr ) { $this->kfr = $kfr; }

    function GetTotal( $uidSeller = -1 )
    {
        $raBContents = $this->ComputeBasketContents();
        if( $uidSeller == -1 ) {
            $fTotal = $raBContents['fTotal'];
        } else {
            $fTotal = @$raBContents['raSellers'][$uidSeller]['fSellerTotal'] ?: 0.0;
        }
        return( $fTotal );
    }

    function GetProductTypesInBasket()
    {
        if( $this->raProdTypes === null )  $this->GetPurchasesInBasket();   // raProdTypes is a side-effect
        return( $this->raProdTypes );
    }

    function GetProductsInBasket( $raParms )
    /***************************************
        Return a list of the products currently in the basket.

        raParms:
            returnType = keys:    array of kProduct
                         objects: array of kProduct=>oProduct
     */
    {
        $bReturnKeys = @$raParms['returnType'] == 'keys';

        $raOut = [];
        foreach( $this->GetPurchasesInBasket() as $oPur ) {
            if( ($kP = $oPur->GetProductKey()) ) {
                if( $bReturnKeys ) {
                    $raOut[] = $kP;
                } else {
// use SEEDBasketCore::GetProductObj()
                    $raOut[$kP] = new SEEDBasket_Product( $this->oSB, $kP );
                }
            }
        }

        return( $raOut );
    }

    function GetPurchasesInBasket()
    /******************************
        Return array of SEEDBasket_Purchase_{prodType}
     */
    {
        if( $this->raObjPur === null ) {
            $this->raObjPur = [];
            $this->raProdTypes = [];    // collected as a side-effect

            if( $this->Key() ) {
                //foreach( $this->oSB->oDB->GetPurchasesList($this->kfr->Key()) as $ra ) {
                //    $raObjPur[] = $this->oSB->GetPurchaseObj( $ra['_key'], $ra['P_product_type'] );
                //}
                if( ($kfrPur = $this->oSB->oDB->GetPurchasesKFRC( $this->Key() )) ) {
                    while( $kfrPur->CursorFetch() ) {
                        // Uses kfr to get the correct SEEDBasket_Purchase_* derivation, and avoids redundant db lookup.
                        // N.B. Pass a copy of the kfr because otherwise the object will have a reference to kfrPur and it will change
                        $this->raObjPur[] = $this->oSB->GetPurchaseObj( 0, "", ['kfr'=>$kfrPur->Copy()] );

                        // collect raProdTypes as a side-effect
                        if( !in_array($kfrPur->Value('P_product_type'), $this->raProdTypes) ) {
                            $this->raProdTypes[] = $kfrPur->Value('P_product_type');
                        }
                    }
                }
            }
        }

        return( $this->raObjPur );
    }

    function DrawBasketContents( $raParms = array(), $klugeUTF8 = false )
// MOVE TO SEEDBasketUI_BasketWidget
    {
        $s = "";

$s .= "<style>
       .sb_basket_table { display:table }
       .sb_basket_tr    { display:table-row }
       .sb_basket_td    { display:table-cell; text-align:left;border-bottom:1px solid #eee;padding:3px 10px 3px 0px }
       </style>";

        $kBPHighlight = intval(@$raParms['kBPHighlight']);

        $raSummary = $this->ComputeBasketContents();

        foreach( $raSummary['raSellers'] as $uidSeller => $raSeller ) {
            if( isset($this->raParms['fn_sellerNameFromUid']) ) {
                $sSeller = call_user_func( $this->raParms['fn_sellerNameFromUid'], $uidSeller );
            } else {
                $sSeller = "Seller $uidSeller";
            }

            $s .= "<div style='margin-top:10px;font-weight:bold'>$sSeller (total ".$this->oSB->dollar($raSeller['fSellerTotal']).")</div>";

            $s .= "<div class='sb_basket_table'>";
            foreach( $raSeller['raPur'] as $pur ) {
                $oPur = $pur['oPur'];
                $kPur = $oPur->GetKey();
                $kfrP = $this->oSB->oDB->GetKFR( 'P', $oPur->GetProductKey() );
                $sItem = $this->oSB->DrawProduct( $kfrP, SEEDBasketProductHandler_Seeds::DETAIL_TINY, ['bUTF8'=>false] );

                $sClass = ($kBPHighlight && $kBPHighlight == $kPur) ? " sb_bp-change" : "";
                $s .= "<div class='sb_basket_tr sb_bp$sClass'>"
                     ."<div class='sb_basket_td'>$sItem</div>"
                     ."<div class='sb_basket_td'>".$this->oSB->dollar($oPur->GetPrice())."</div>"
                     ."<div class='sb_basket_td'>"
                         // Use full url instead of W_ROOT because this html can be generated via ajax (so not a relative url)
                         // Only draw the Remove icon for items with kBP because discounts, etc, are coded with kBP==0 and those shouldn't be removable on their own
                         .($kPur ? ("<img height='14' onclick='RemoveFromBasket($kPur);' src='//seeds.ca/wcore/img/ctrl/delete01.png'/>") : "")
                         ."</div>"
                     ."</div>";
            }
            foreach( $raSeller['raExtraItems'] as $xtra ) {
                $sClass = "";
                $s .= "<div class='sb_basket_tr sb_bp$sClass'>"
                     ."<div class='sb_basket_td'>{$xtra['sLabel']}</div>"
                     ."<div class='sb_basket_td'>".$this->oSB->dollar($xtra['fAmount'])."</div>"
                     ."<div class='sb_basket_td'></div>"
                     ."</div>";
            }
            $s .= "</div>";
        }

        if( !$s ) goto done;

        $s .= "<div style='text-alignment:right;font-size:12pt;color:green'>Your Total: ".$this->oSB->dollar($raSummary['fTotal'])."</div>";

        $s = "<div class='sb_basket-contents'>$s</div>";

        done:
        return( $s ? $s : "Your Basket is Empty" );
    }

/*
    function ComputeBasketContents__old( $klugeUTF8 )
    {
        $raOut = [ 'fTotal'=>0.0, 'raSellers'=>[] ];

        if( !($kBasket = $this->GetKey()) )  goto done;

        if( ($kfrBPxP = $this->oSB->oDB->GetPurchasesKFRC( $kBasket )) ) {
            while( $kfrBPxP->CursorFetch() ) {
                $uidSeller = $kfrBPxP->Value('P_uid_seller');
// handle volume pricing, shipping, discount
                $fAmount = $this->getAmount( $kfrBPxP );
                $raOut['fTotal'] += $fAmount;
                if( !isset($raOut['raSellers'][$uidSeller]) ) {
                    $raOut['raSellers'][$uidSeller] = [ 'fTotal'=>0.0, 'raContents'=>[] ];
                }

                $oHandler = $this->oSB->GetProductHandler( $kfrBPxP->Value('P_product_type') );
                $sItem = $oHandler->PurchaseDraw( $kfrBPxP, ['bUTF8'=>$klugeUTF8] );
                $raOut['raSellers'][$uidSeller]['fTotal'] += $fAmount;
                $raOut['raSellers'][$uidSeller]['raItems'][] = [
                    'kBP'=>$kfrBPxP->Key(), 'sItem'=>$sItem, 'fAmount'=>$fAmount,
                    'oPur'=>$this->oSB->GetPurchaseObj( $kfrBPxP->Value('_key'), $kfrBPxP->Value('P_product_type') ) ];

                // derived class adjustment
// k is non-zero if the user is a current grower member
if( $kfrBPxP->Value('P_product_type') == 'seeds' ) {
    if( ($this->oSB->oDB->kfdb->Query1( "SELECT _key FROM {$this->oSB->oApp->GetDBName('seeds1')}.sed_curr_growers WHERE mbr_id='".$this->oSB->GetUID_SB()."' AND NOT bSkip" )) ) {
        if( floatval($kfrBPxP->Value('P_item_price')) > 10.00 ) {
            $discount = -2.0;
        } else {
            $discount = -1.0;
        }
        $raOut['fTotal'] += $discount;
        $raOut['raSellers'][$uidSeller]['fTotal'] += $discount;
        $raOut['raSellers'][$uidSeller]['raItems'][] = array( 'kBP'=>0, 'sItem'=>"Your grower member discount", 'fAmount'=>$discount );
    }
}
                // add other items for shipping / discount

            }
        }
        done:
        return( $raOut );
    }
*/

    function ComputeBasketContents()
    /*******************************
        Return [ 'fTotal'               =>     total price of the basket
                 'raSellers' =>
                     [ uid                  =>     per seller:
                        [ 'fSellerTotal'    =>         total price for this seller
                          'raPur'           =>         array of purchases from this seller
                            [ 'oPur'        =>             purchase obj
                              metadata      =>             other info about this purchase
                            ]
                          'raExtraItems'    =>         array of non-purchase items calculated from purchases (e.g. discount, shipping fee)
                            [ 'sLabel'      =>             item label
                              'fAmount'     =>             dollar amount
     */
    {
        if( $this->raComputed ) goto done;

        $this->raComputed = [ 'fTotal'=>0.0, 'raSellers'=>[] ];

        if( !$this->GetKey() )  goto done;

        foreach( $this->GetPurchasesInBasket() as $oPur ) {
            $uidSeller = $oPur->GetSeller();

// handle volume pricing, shipping, discount
            $fAmount = $oPur->GetPrice();
            $this->raComputed['fTotal'] += $fAmount;
            if( !isset($this->raComputed['raSellers'][$uidSeller]) ) {
                $this->raComputed['raSellers'][$uidSeller] = [ 'fSellerTotal'=>0.0, 'raPur'=>[], 'raExtraItems'=>[] ];
            }

            $this->raComputed['raSellers'][$uidSeller]['fSellerTotal'] += $fAmount;
            $this->raComputed['raSellers'][$uidSeller]['raPur'][] = [ 'oPur'=>$oPur, 'fAmount'=>$fAmount ];

// make this a derived class adjustment:  ComputeExtraItems( oPur, uidSeller, uidBuyer ) : [[sLabel,fAmount], ]
// you get a discount if you are a grower member (but only on products over $2)
if( $oPur->GetProductType() == 'seeds' &&
    $this->oSB->oDB->kfdb->Query1( "SELECT _key FROM {$this->oSB->oApp->GetDBName('seeds1')}.sed_curr_growers WHERE mbr_id='".$this->oSB->GetUID_SB()."' AND NOT bSkip" ) &&
    $oPur->GetPrice() > 2.00 )
{
    if( floatval($oPur->GetPrice()) > 10.00 ) {
        $discount = -2.0;
    } else {
        $discount = -1.0;
    }
    $this->raComputed['fTotal'] += $discount;
    $this->raComputed['raSellers'][$uidSeller]['fSellerTotal'] += $discount;
    $this->raComputed['raSellers'][$uidSeller]['raExtraItems'][] = ['sLabel'=>"Your grower member discount", 'fAmount'=>$discount];
}
                // add other items for shipping / discount
        }

        done:
        return( $this->raComputed );
    }

// deprecated: use SEEDBasket_Purchase::GetPrice()
    private function getAmount( KeyframeRecord $kfrBPxP )
    {
        $amount = 0.0;

        switch( $kfrBPxP->Value('P_quant_type') ) {
            case 'ITEM-1':
                $amount = $kfrBPxP->Value('P_item_price');
                break;

            case 'ITEM-N':
                $n = $kfrBPxP->Value('n');
                $amount = $this->priceFromRange( $kfrBPxP->Value('P_item_price'), $n ) * $n;
                break;

            case 'MONEY':
                $amount = $kfrBPxP->Value('f');
                break;
        }
        $amount = floatval($amount);

        return( $amount );
    }

    function priceFromRange( $sRange, $n )
    {
        return( SEEDBasketCore::PriceFromRange( $sRange, $n ) );
    }

}

class SEEDBasket_Product
/***********************
    Implement a product
 */
{
    private $kfr;
    private $oSB;

    private $cache_oHandler = null;

    function __construct( SEEDBasketCore $oSB, $kP, $raConfig = [] )
    {
        $this->oSB = $oSB;
        $this->SetKey( $kP );
        if( !$kP && ($pt = @$raConfig['product_type']) ) {
            $this->SetValue( 'product_type', $pt );
        }
    }

    private function clearCachedProperties()
    {
        $this->cache_oHandler = null;
    }

    function Key()     { return( $this->kfr ? $this->kfr->Key() : 0 ); }
    function GetKey()  { return( $this->kfr ? $this->kfr->Key() : 0 ); }

    function SetKey( $k )
    {
        $this->clearCachedProperties();
        $this->kfr = $k ? $this->oSB->oDB->GetProductKFR($k) : $this->oSB->oDB->GetProductKFREmpty();
    }

    function SetValue( $k, $v ) { if( $this->kfr ) $this->kfr->SetValue( $k, $v ); }
    function PutDBRow()         { if( $this->kfr ) $this->kfr->PutDBRow(); }

    function GetName()          { return( $this->kfr ? $this->kfr->Value('name') : "" ); }
    function GetProductType()   { return( $this->kfr ? $this->kfr->Value('product_type') : "" ); }

    function FormIsAjax()
    {
        return( ($oHandler = $this->GetHandler()) && $oHandler->ProductFormIsAjax() );
    }

    // intended to only be used by SEEDBasket internals e.g. SEEDBasketCursor::GetNext()
    function _setKFR( KeyframeRecord $kfr ) { $this->clearCachedProperties(); $this->kfr = $kfr; }

    function GetHandler()
    {
        if( $this->cache_oHandler )  return( $this->cache_oHandler );
        return( $this->cache_oHandler = $this->oSB->GetProductHandler( $this->kfr->Value('product_type') ) );
    }

    function Draw( $eDetail, $raParms )
    {
// DrawProduct code should live here
        return( $this->oSB->DrawProduct( $this->kfr, $eDetail, $raParms ) );
    }

    function DrawProductForm( $cid = 'A' )
    /*************************************
        Update and draw the form for the product.
        If this is a new product you have to SetProductType() first.
     */
    {
        $s = "";

        if( !$this->kfr->Value('product_type') ) goto done;

        if( !($oHandler = $this->GetHandler()) )  goto done;

        if( $oHandler->ProductFormIsAjax() ) {
            $s = $oHandler->ProductFormDrawAjax( $this->Key() );
        } else {
            /* Create a form with the correct ProductDefine1() and use that to Update any current form submission,
             * then load up the current product (or create a new one) and draw the form for it.
             */
            $oFormP = new KeyframeForm( $this->oSB->oDB->GetKfrel("P"), $cid,
                                        ['DSParms'=>['fn_DSPreStore' =>[$oHandler,'ProductDefine1'],
                                                     'fn_DSPostStore'=>[$oHandler,'ProductDefine2PostStore'] ]] );
            $oFormP->SetKFR( $this->kfr );

            // This part is the common form setup for all products
            if( !$oFormP->Value('uid_seller') ) {
                if( !($uid = $this->oSB->GetUID_SB()) ) die( "ProductDefine0 not logged in" );
                $oFormP->SetValue( 'uid_seller', $uid );
            }

            // This part is the custom form setup for the productType
            $s = "<form method='post'>"
                .$oHandler->ProductDefine0( $oFormP )
                ."<input type='submit'>"
                ."</form>";
        }

        done:
        return( $s );
    }
}

class SEEDBasket_ProductKeyframeForm extends KeyframeForm
/***********************************
    This attempts to solve a catch-22 when updating a product via http form parms: the form has to be
    created per product_type but it might not always be obvious which product is being updated.

    To solve this problem, this form can be created with a given product_type, or if that is not defined
    it will sniff sf{cid}p_product_type to see what it is supposed to do.

    Basically, you create this object and use it like a KeyframeForm e.g. $o->Update()
 */
{
    function __construct( SEEDBasketCore $oSB, $sProductType, $cid = 'A' )
    {
        $raConfig = [];

        // get the product type, either given or sniffed from the http parms
        if( !$sProductType ) {
            $oSFP = new SEEDFormParms($cid);
            $sProductType = SEEDInput_Str( $oSFP->sfParmField('product_type') );
        }

        // get the handler for this product type and set up the parent object with the right PreStore/PostStore
        if( $sProductType &&  ($oHandler = $oSB->GetProductHandler($sProductType)) ) {
            $raConfig = ['DSParms' => ['fn_DSPreStore' =>[$oHandler,'ProductDefine1'],
                                       'fn_DSPostStore'=>[$oHandler,'ProductDefine2PostStore'] ]];
        }

        parent::__construct( $oSB->oDB->GetKfrel("P"), $cid, $raConfig );
    }

    /*
Somewhere something is supposed to enforce the forceFlds

        // force per-prodtype fixed values
        if( isset(SEEDBasketProducts_SoD::$raProductTypes[$sProductType_ifNew]['forceFlds']) ) {
            foreach( SEEDBasketProducts_SoD::$raProductTypes[$sProductType_ifNew]['forceFlds'] as $k => $v ) {
                $kfrP->SetValue( $k, $v );
            }
        }
*/
}

class SEEDBasket_Purchase
/************************
    Implement a purchase of a product in a basket
 */
{
    private $kfr;
    protected $oSB;

    private $oBasket = NULL;
    private $oProduct = NULL;

    function __construct( SEEDBasketCore $oSB, int $kBP, array $raParms = [] )
    {
        /* raParms can contain properties that avoid redundant lookups
         * e.g. new SEEDBasket_Purchase($oSB, 0, ['kfr'=>$kfrPur]) sets the kfr without looking it up again
         */
        $this->oSB = $oSB;

        if( @$raParms['kfr'] ) {        // in this case kBP is not used, overridden by kfr->Key() (should be the same)
            $this->kfr = $raParms['kfr'];
        } else {
            $this->SetKey( $kBP );
        }

        if( @$raParms['oBasket'] )  $this->oBasket = $raParms['oBasket'];
        if( @$raParms['oProduct'] ) $this->oProduct = $raParms['oProduct'];
    }

    function GetKey()  { return( $this->kfr->Key() ); }

    function SetKey( $k )
    {
        $this->kfr = $k ? $this->oSB->oDB->GetPurchaseKFR($k) : $this->oSB->oDB->GetPURKFREmpty();  // GetPurchaseKFR is PURxP
        $this->oBasket = NULL;
        $this->oProduct = NULL;
    }

    function GetBasketKey()  { return( $this->kfr->Value('fk_SEEDBasket_Baskets') ); }
    function GetProductKey() { return( $this->kfr->Value('fk_SEEDBasket_Products') ); }
    function GetN()          { return( $this->kfr->Value('n') ); }
    function GetF()          { return( $this->kfr->Value('f') ); }
    function GetEStatus()    { return( $this->kfr->Value('eStatus') ); }
    function GetKRef()       { return( $this->kfr->Value('kRef') ); }
    function GetExtra( $k )  { return( $this->kfr->UrlParmGet('sExtra', $k) ); }
    function Value( $k ) { return( $this->kfr->Value($k) ); }

    function SetExtra( $k, $v ) { return( $this->kfr->UrlParmSet('sExtra', $k, $v) ); }

    function GetSeller()     { return( $this->kfr->Value('P_uid_seller') ); }

    function GetFulfilControls()
    /***************************
        Return an array of labels for the fulfilment controls for this purchase
     */
    {
        return( ['fulfilButtonLabel' => "Fulfil",
                 'statusFulfilled' => "fulfilled",
                 'statusNotFulfilled' => "not fulfilled" ] );
    }


    function GetPrice()
    {
        $amount = 0.0;

        if( !$this->kfr ) goto done;

        switch( $this->kfr->Value('P_quant_type') ) {
            case 'ITEM-1':
                $amount = $this->kfr->Value('P_item_price');
                break;

            case 'ITEM-N':
                $n = $this->kfr->Value('n');
                $amount = SEEDBasketCore::PriceFromRange( $this->kfr->Value('P_item_price'), $n ) * $n;
                break;

            case 'MONEY':
                $amount = $this->kfr->Value('f');
                break;
        }

        done:
        return( floatval($amount) );
    }


    function GetProductType(){ return( $this->kfr->Value('P_product_type') ); }
    function GetProductName(){ return( $this->kfr->Value('P_name') ); }

    function GetDisplayName( $raParms )
    {
        $s = $this->kfr->Value('P_title_en');

        if( $this->kfr->Value('quant_type') == 'ITEM_N' && ($n = $this->kfr->Value('n')) > 1 ) {
            $s .= " ($n @ ".$this->oSB->dollar($this->oSB->priceFromRange($this->kfr->Value('item_price'), $n)).")";
        }
        return( $s );
    }

    /* Workflow flags: product handlers can assign any meaning to any flag bits but here are some that they might like to use in standard ways
     */
    const WORKFLOW_FLAG_ACCOUNTED = 1;
    const WORKFLOW_FLAG_RECORDED = 2;
    const WORKFLOW_FLAG_MAILED = 4;
    function GetWorkflowFlag( int $flag )
    {
        return( $this->kfr ? ($this->kfr->value('flagsWorkflow') & $flag) : 0 );
    }
    function SetWorkflowFlag( int $flag )
    {
        if( $this->kfr )  $this->kfr->SetValue( 'flagsWorkflow', ($this->kfr->value('flagsWorkflow') | $flag) );
    }
    function UnsetWorkflowFlag( int $flag )
    {
        if( $this->kfr )  $this->kfr->SetValue( 'flagsWorkflow', ($this->kfr->value('flagsWorkflow') & ~$flag) );
    }


    function GetProductHandler()
    {
        return( $this->kfr && $this->kfr->value('P_product_type') ? $this->oSB->GetProductHandler( $this->kfr->value('P_product_type') ) : null );
    }

    function StorePurchase( SEEDBasket_Basket $oB, SEEDBasket_Product $oP, $raParms )
    {
        $this->kfr->SetValue( 'fk_SEEDBasket_Baskets', $oB->GetKey() );
        $this->kfr->SetValue( 'fk_SEEDBasket_Products', $oP->GetKey() );

        foreach( $raParms as $k => $v ) {
            if( in_array( $k, ['n', 'f', 'eStatus', 'kRef', 'flagsWorkflow'] ) ) {
                $this->kfr->SetValue( $k, $v );
            } else {
                $this->kfr->UrlParmSet( 'sExtra', $k, $v );
            }
        }
        $this->kfr->PutDBRow();

        return( $this->kfr->Key() );
    }

    function SetValue( $k, $v ) { if( $this->kfr ) $this->kfr->SetValue( $k, $v ); }
    function PutDBRow()         { $this->SaveRecord(); } // deprecate
    function SaveRecord()       { if( $this->kfr ) $this->kfr->PutDBRow(); }

    function GetBasketObj()
    {
        if( !$this->oBasket && ($kB = $this->kfr->Value('fk_SEEDBasket_Baskets')) ) {
            $oB = new SEEDBasket_Basket( $this->oSB, $kB );
            if( $oB && $oB->GetKey() ) $this->oBasket = $oB;
        }

        return( $this->oBasket );
    }

    function GetProductObj()
    {
        if( !$this->oProduct && ($kP = $this->kfr->Value('fk_SEEDBasket_Products')) ) {
            $oP = new SEEDBasket_Product( $this->oSB, $kP );
            if( $oP && $oP->GetKey() ) $this->oProduct = $oP;
        }

        return( $this->oProduct );
    }

    // non-zero results indicate success
    const FULFIL_RESULT_FAILED = 0;
    const FULFIL_RESULT_SUCCESS = 1;
    const FULFIL_RESULT_ALREADY_FULFILLED = 2;  // consider this successful if trying to fulfil

    const FULFILUNDO_RESULT_FAILED = 0;         // either !CanFilfulUndo() or a failure
    const FULFILUNDO_RESULT_SUCCESS = 1;
    const FULFILUNDO_RESULT_NOT_FULFILLED = 2;  // consider this successful if trying to undo

// need to standardize how eStatus, flagsWorkflow, fulfil/undo really relate to each other
// maybe IsFulfilled() is just eStatus==FILLED, but there are different and multiple stages of fulfilment (recording, mailing, receipting) for each product

// completed workflows (end with FILLED or CANCELLED):
// NEW, PAID, FILLED
// NEW, CANCELLED
// NEW, PAID, CANCELLED (refund)
// NEW, PAID, FILLED, CANCELLED (fulfilUndo, refund)

// uncompleted workflows:
// NEW
// NEW, PAID

//*** Purchases don't get PAID status, baskets do

    function IsFulfilled()
    /*********************
        Return true if the seller has already fulfilled this purchase
     */
    {
        return( false );    // handled only by derived classes
        //return( ($oHandler = $this->GetProductHandler()) ? $oHandler->PurchaseIsFulfilled($this) : false );
    }

    function CanFulfil()
    /*******************
        Indicates whether this purchase is ready for Fulfil()
     */
    {
        // typically ( $this->_canFulfilOrUndo() && !$this->IsFulfilled() )
        return( false );    // $this->_canFulfilOrUndo()    don't allow UI to show fulfil buttons for purchases that don't have derived classes
    }

    protected function _canFulfilOrUndo()
    /************************************
        The state of the purchase is suitable for changing fulfilment status.
     */
    {
        // this base condition can be made more stringent per product_type
        return( $this->GetKey() &&
                $this->GetEStatus() != 'CANCELLED' &&            // maybe just use basket.eStatus
                ($oB = $this->GetBasketObj()) &&
//                in_array($oB->GetEStatus(), ['Paid','Filled'])   // Cancelled baskets have to be uncancelled before fulfilment operations allowed
// allowing Open temporarily because basket.eStatus is not updated yet
                in_array($oB->GetEStatus(), ['Open','Paid','Filled'])
            );
    }

    function Fulfil()
    /****************
        Record that the seller has fulfilled this purchase
     */
    {
        /* deprecate: use derivation instead */
        return( ($oHandler = $this->GetProductHandler()) ? $oHandler->PurchaseFulfil($this) : self::FULFIL_RESULT_FAILED );
    }

    function CanFulfilUndo()
    /***********************
        Indicates whether the fulfilment can be reversed, or is it too late.
     */
    {
        // typically ( $this->_canFulfilOrUndo() && $this->IsFulfilled() )
        return( false );  // don't allow UI to show Undo buttons for purchases that don't have derived classes
    }

    function FulfilUndo()
    /********************
        Reverse the fulfilment if possible
     */
    {
        return( self::FULFILUNDO_RESULT_FAILED );
    }

    // intended to only be used by SEEDBasket internals e.g. SEEDBasketCursor::GetNext()
    function _setKFR( KeyframeRecord $kfr ) { $this->kfr = $kfr; }
}

class SEEDBasketCursor
/*********************
     Get a sequence of baskets, products, or purchases
 */
{
    private $oSB;
    private $sType;
    private $kfrc = null;

    function __construct( SEEDBasketCore $oSB, $sType, $sCond, $raKFRC = [], $raParms = [] )
    {
        $this->oSB = $oSB;
        $this->sType = $sType;

        switch( $sType ) {
            case 'basket':   $r = 'B';   break;
            case 'product':  $r = 'P';   break;
            case 'purchase': $r = 'BP';  break;
            default:
                goto done;
        }
        $this->kfrc = $this->oSB->oDB->GetKFRC( $r, $sCond, $raKFRC );

        done:;
    }

    function IsValid() { return( $this->kfrc != null ); }

    function GetNext()
    {
        $o = null;

        if( $this->kfrc->CursorFetch() ) {
            switch( $this->sType ) {
                case 'basket':   $o = new SEEDBasket_Basket( $this->oSB, 0 );    $o->_setKFR( $this->kfrc );  break;
                case 'product':  $o = new SEEDBasket_Product( $this->oSB, 0 );   $o->_setKFR( $this->kfrc );  break;
                case 'purchase': $o = new SEEDBasket_Purchase( $this->oSB, 0 );  $o->_setKFR( $this->kfrc );  break;
            }
        }

        return( $o );
    }
}
