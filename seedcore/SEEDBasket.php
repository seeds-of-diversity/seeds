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

    function __construct( KeyframeDatabase $kfdb, SEEDSession $sess, SEEDAppConsole $oApp, $raHandlerDefs, $raParms = array() )
    {
        $this->oApp = $oApp;
        $this->sess = $sess;
        $this->oDB = new SEEDBasketDB( $kfdb, $this->GetUID_SB(),
            //get this from oApp
            @$raParms['logdir'], ['db'=>@$raParms['db']] );
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
                       ($kB = $this->oDB->kfdb->Query1( "SELECT _key FROM SEEDBasket_Baskets WHERE uid_buyer='$uid' ORDER BY _created DESC LIMIT 1" )) )
            {
                $this->kfrBasketCurr = $this->oDB->GetBasket( $kB );
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
        return( new SEEDBasketCursor( $this, $sType, $sCond, $raKFRC ) );
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

    function ComputeBasketSummary( $klugeUTF8 = false )
    /******************************
        Compute information about the current basket

        fTotal            : the total amount to pay
        raSellers         : array( uidSeller1 => array( fTotal => the total amount to pay to this seller,
                                                        raItems => array( kBP=>kBP, sItem => describes the item, fAmount => amount to pay ) )

                            N.B. shipping / discount are formatted as individual raItems immediately following their item with kBP==0
     */
    {
        $raOut = array( 'fTotal'=>0.0, 'raSellers'=>array() );

        if( ($kBasket = $this->GetBasketKey()) ) {
            $oB = new SEEDBasket_Basket( $this, $kBasket );
            $raOut = $oB->ComputeBasketContents( $klugeUTF8 );
        }

        return( $raOut );
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

        if( ($oHandler = $this->getHandler( $kfrP->Value('product_type') )) &&
            ($kBPNew = $oHandler->Purchase2( $kfrP, $raPurchaseParms )) )
        {
            $sOut = $this->DrawBasketContents( ['kBPHighlight'=>$kBPNew], $klugeUTF8 );
            $bOk = true;
        }

        done:
        return( array( $bOk, $sOut, $sErr ) );
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

        $bOk = $this->oDB->kfdb->Execute( "DELETE FROM seeds.SEEDBasket_BP WHERE fk_SEEDBasket_Baskets='$kB'" );

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
}


class SEEDBasket_Basket
/**********************
    Implement a basket
 */
{
    private $kfr;
    private $oSB;

    function __construct( SEEDBasketCore $oSB, $kB )
    {
        $this->oSB = $oSB;
        $this->SetKey( $kB );
    }

    function Key()     { return( $this->kfr->Key() ); }
    function GetKey()  { return( $this->kfr->Key() ); }

    function SetKey( $k )
    {
        $this->kfr = $k ? $this->oSB->oDB->GetBasketKFR($k) : $this->oSB->oDB->GetBasketKFREmpty();
    }

    function SetValue( $k, $v ) { $this->kfr->SetValue( $k, $v ); }
    function PutDBRow()         { $this->kfr->PutDBRow(); }

    // intended to only be used by SEEDBasket internals e.g. SEEDBasketCursor::GetNext()
    function _setKFR( KeyframeRecord $kfr ) { $this->kfr = $kfr; }

    function GetProductsInBasket( $raParms )
    /***************************************
        Return a list of the products currently in the basket.

        returnType = keys, objects (default)
     */
    {
        $bReturnKeys = @$raParms['returnType'] == 'keys';

        $raOut = [];
        if( $this->kfr->Key() ) {
            $raPur = $this->oSB->oDB->GetPurchasesList( $this->kfr->Key() );
            foreach( $raPur as $ra ) {
                if( ($kP = $ra['fk_SEEDBasket_Products']) ) {
                    if( $bReturnKeys ) {
                        $raOut[] = $kP;
                    } else {
                        $raOut[] = new SEEDBasket_Product( $this->oSB, $kP );
                    }
                }
            }
        }
        return( $raOut );
    }

    function DrawBasketContents( $raParms = array(), $klugeUTF8 = false )
    {
        $s = "";

$s .= "<style>
       .sb_basket_table { display:table }
       .sb_basket_tr    { display:table-row }
       .sb_basket_td    { display:table-cell; text-align:left;border-bottom:1px solid #eee;padding:3px 10px 3px 0px }
       </style>";

        $kBPHighlight = intval(@$raParms['kBPHighlight']);

        $raSummary = $this->ComputeBasketContents( $klugeUTF8 );

        foreach( $raSummary['raSellers'] as $uidSeller => $raSeller ) {
            if( isset($this->raParms['fn_sellerNameFromUid']) ) {
                $sSeller = call_user_func( $this->raParms['fn_sellerNameFromUid'], $uidSeller );
            } else {
                $sSeller = "Seller $uidSeller";
            }

            $s .= "<div style='margin-top:10px;font-weight:bold'>$sSeller (total ".$this->oSB->dollar($raSeller['fTotal']).")</div>";

            $s .= "<div class='sb_basket_table'>";
            foreach( $raSeller['raItems'] as $raItem ) {
                $sClass = ($kBPHighlight && $kBPHighlight == $raItem['kBP']) ? " sb_bp-change" : "";
                $s .= "<div class='sb_basket_tr sb_bp$sClass'>"
                     ."<div class='sb_basket_td'>".$raItem['sItem']."</div>"
                     ."<div class='sb_basket_td'>".$this->oSB->dollar($raItem['fAmount'])."</div>"
                     ."<div class='sb_basket_td'>"
                         // Use full url instead of W_ROOT because this html can be generated via ajax (so not a relative url)
                         // Only draw the Remove icon for items with kBP because discounts, etc, are coded with kBP==0 and those shouldn't be removable on their own
                         .($raItem['kBP'] ? ("<img height='14' onclick='RemoveFromBasket(".$raItem['kBP'].");' src='//seeds.ca/w/img/ctrl/delete01.png'/>") : "")
                         ."</div>"
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

    function ComputeBasketContents( $klugeUTF8 )
    {
        $raOut = [ 'fTotal'=>0.0, 'raSellers'=>[] ];

        if( !($kBasket = $this->kfr->Key()) )  goto done;

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
                $raOut['raSellers'][$uidSeller]['raItems'][] = array( 'kBP'=>$kfrBPxP->Key(), 'sItem'=>$sItem, 'fAmount'=>$fAmount );

                // derived class adjustment
// k is non-zero if the user is a current grower member
if( $kfrBPxP->Value('P_product_type') == 'seeds' ) {
    if( ($this->oSB->oDB->kfdb->Query1( "SELECT _key FROM seeds.sed_curr_growers WHERE mbr_id='".$this->oSB->GetUID_SB()."' AND NOT bSkip" )) ) {
        if( floatval($kfrBPxP->Value('P_item_price')) == 12.00 ) {
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

        return( $amount );
    }

    function priceFromRange( $sRange, $n )
    {
        $f = 0.00;

        if( strpos( $sRange, ',' ) === false && strpos( $sRange, ':' ) === false ) {
            // There is just a single price for all quantities
            $f = $this->oSB->dollar( $sRange );
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
            $this->kfr->SetValue( 'product_type', $pt );
        }
    }

    private function clearCachedProperties()
    {
        $this->cache_oHandler = null;
    }

    function Key()     { return( $this->kfr->Key() ); }
    function GetKey()  { return( $this->kfr->Key() ); }

    function SetKey( $k )
    {
        $this->clearCachedProperties();
        $this->kfr = $k ? $this->oSB->oDB->GetProductKFR($k) : $this->oSB->oDB->GetProductKFREmpty();
    }

    function SetValue( $k, $v ) { $this->kfr->SetValue( $k, $v ); }
    function PutDBRow()         { $this->kfr->PutDBRow(); }

    function GetName()          { return( $this->kfr ? $this->kfr->Value('name') : "" ); }
    function GetProductType()   { return( $this->kfr ? $this->kfr->Value('product_type') : "" ); }

    function FormIsAjax()
    {
        return( ($oHandler = $this->getProductHandler()) && $oHandler->ProductFormIsAjax() );
    }

    // intended to only be used by SEEDBasket internals e.g. SEEDBasketCursor::GetNext()
    function _setKFR( KeyframeRecord $kfr ) { $this->clearCachedProperties(); $this->kfr = $kfr; }

    private function getProductHandler()
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

        if( !($oHandler = $this->getProductHandler()) )  goto done;

        if( $oHandler->ProductFormIsAjax() ) {
            $s = $oHandler->ProductFormDrawAjax( $kP );
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
            $s = "<form>"
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
    private $oSB;

    function __construct( SEEDBasketCore $oSB, $kBP )
    {
        $this->oSB = $oSB;
        $this->SetKey( $kBP );
    }

    function GetKey()  { return( $this->kfr->Key() ); }

    function SetKey( $k )
    {
        $this->kfr = $k ? $this->oSB->oDB->GetBPKFR($k) : $this->oSB->oDB->GetBPKFREmpty();
    }

    function StorePurchase( SEEDBasket_Basket $oB, SEEDBasket_Product $oP, $raParms )
    {
        $this->kfr->SetValue( 'fk_SEEDBasket_Baskets', $oB->GetKey() );
        $this->kfr->SetValue( 'fk_SEEDBasket_Products', $oP->GetKey() );

        $raFld = ['n', 'f', 'eStatus', 'bAccountingDone'];
        foreach( $raParms as $k => $v ) {
            if( in_array( $k, $raFld ) ) {
                $this->kfr->SetValue( $k, $v );
            } else {
                $this->kfr->UrlParmSet( 'sExtra', $k, $v );
            }
        }
        $this->kfr->PutDBRow();

        return( $this->kfr->Key() );
    }

    function SetValue( $k, $v ) { $this->kfr->SetValue( $k, $v ); }
    function PutDBRow()         { $this->kfr->PutDBRow(); }

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

    function IsValid() { return( $kfrc != null ); }

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
