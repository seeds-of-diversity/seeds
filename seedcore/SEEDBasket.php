<?php

/* SEEDBasket.php
 *
 * Copyright (c) 2016 Seeds of Diversity Canada
 *
 * Manage a shopping basket of diverse products
 */

include_once( "SEEDBasketDB.php" );
include_once( "SEEDBasketProductHandler.php" );
include_once( "SEEDBasketUpdater.php" );
include_once( STDINC."KeyFrame/KFUIForm.php" );


class SEEDBasketCore
/*******************
    Core class for managing a shopping basket
 */
{
    public $oDB;
    public $sess;   // N.B. user might not be logged in so use $this->GetUID() instead of $this->sess->GetUID()
                    // No, make sure this is always a SEEDSessionAccount (it's SEEDSession in the constructor!) and it will do the right thing

    private $raHandlerDefs;
    private $raHandlers = array();
    private $raParms = array();
    private $kBasket;       // always access this via GetBasketKey

    function __construct( KeyFrameDB $kfdb, SEEDSession $sess, $raHandlerDefs, $raParms = array() )
    {
        $this->sess = $sess;
        $this->oDB = new SEEDBasketDB( $kfdb, $this->GetUID_SB() );
        $this->raHandlerDefs = $raHandlerDefs;
        $this->kBasket = $this->GetBasketKey();
        $this->raParms = $raParms;
    }

    function Cmd( $cmd, $raParms = array(), $bGPC = false )
    /******************************************************
        If raParms is _REQUEST, set bGPC=true
        If raParms is an ordinary array, set bGPC=false
        Then SEEDSafeGPC will do the right thing
     */
    {
        $raOut = array( 'bHandled'=>false, 'bOk'=>false, 'sOut'=>"", 'sErr'=>"" );

        switch( strtolower($cmd) ) {    // don't have to strip slashes because no arbitrary commands
            case "addtobasket":
                /* Add a product to the current basket
                 * 'sb_product' = name of product to add to current basket (if it is_numeric this is the product _key)
                 * $raParms also contains BP parameters prefixed by sb_*
                 */
                $raOut['bHandled'] = true;
                $kfrP = null;

                if( ($prodName = SEEDInput_Str('sb_product')) ) {
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
                list($raOut['bOk'],$raOut['sOut']) = $this->addProductToBasket( $kfrP, $raParms, $bGPC );
                break;

            case "removefrombasket":
                // kBP = key of purchase to remove
                $raOut['bHandled'] = true;

                if( ($kBP = SEEDSafeGPC_GetInt('kBP',$raParms)) ) {
                    list($raOut['bOk'],$raOut['sOut']) = $this->removeProductFromBasket( $kBP );
                }
                break;

            case "clearbasket":
                $raOut['bHandled'] = true;

                list($raOut['bOk'],$raOut['sOut']) = $this->clearBasket();
                break;
        }

        done:
        return( $raOut );
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

    function BasketAcquire()
    /***********************
        Find the current basket, or create a new one.
        We don't return the basket key, just to remind you to check BasketIsOpen() after you do this.
     */
    {
        if( !$this->GetBasketKey() ) {
            $kfrB = $this->oDB->GetKfrel("B")->CreateRecord();
            $kfrB->SetValue( 'uid_buyer', $this->GetUID_SB() );
            $kfrB->PutDBRow();
            $this->SetBasketKey( $kfrB->Key() );
        }
    }

    function BasketIsOpen()
    /**********************
        True if there is a current basket and it is open for adding/updating/deleting by the purchaser
     */
    {
        $bOk = false;

        if( ($kB = $this->GetBasketKey()) &&
            ($kfrB = $this->oDB->GetBasket($kB)) &&
            ($kfrB->Value('eStatus') == 'New') ) {
            $bOk = true;
        }

        return( $bOk );
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
                                      array('DSParms'=>array('fn_DSPreStore' =>array($oHandler,'ProductDefine1'),
                                                             'fn_DSPostStore'=>array($oHandler,'ProductDefine2PostStore') )) );
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

    function DrawProduct( KFRecord $kfrP, $eDetail )
    {
        return( ($oHandler = $this->getHandler( $kfrP->Value('product_type') ))
                ? $oHandler->ProductDraw( $kfrP, $eDetail ) : "" );
    }

    function DrawPurchaseForm( $prodName )
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
        $s .= $oHandler->Purchase0( $kfrP );

        done:
        return( $s );
    }

    function DrawBasketContents( $raParms = array() )
    /************************************************
        Draw the contents of the current basket.

        raParms:
            kBPHighlight : highlight this BP entry
     */
    {
        $s = "";

$s .= "<style>
       .sb_basket_table { display:table }
       .sb_basket_tr    { display:table-row }
       .sb_basket_td    { display:table-cell; text-align:left;border-bottom:1px solid #eee;padding:3px 10px 3px 0px }
       </style>";

        if( !$this->GetBasketKey() ) goto done;

        $kBPHighlight = intval(@$raParms['kBPHighlight']);

        $raSummary = $this->ComputeBasketSummary();

        foreach( $raSummary['raSellers'] as $uidSeller => $raSeller ) {
            if( isset($this->raParms['fn_sellerNameFromUid']) ) {
                $sSeller = call_user_func( $this->raParms['fn_sellerNameFromUid'], $uidSeller );
            } else {
                $sSeller = "Seller $uidSeller";
            }

            $s .= "<div style='margin-top:10px;font-weight:bold'>$sSeller (total ".$this->dollar($raSeller['fTotal']).")</div>";

            $s .= "<div class='sb_basket_table'>";
            foreach( $raSeller['raItems'] as $raItem ) {
                $sClass = ($kBPHighlight && $kBPHighlight == $raItem['kBP']) ? " sb_bp-change" : "";
                $s .= "<div class='sb_basket_tr sb_bp$sClass'>"
                     ."<div class='sb_basket_td'>".$raItem['sItem']."</div>"
                     ."<div class='sb_basket_td'>".$this->dollar($raItem['fAmount'])."</div>"
                             ."<div class='sb_basket_td' style='' onclick='RemoveFromBasket(".$raItem['kBP'].");'>"
                         // use full url instead of W_ROOT because this html can be generated via ajax (so not a relative url)
                         ."<img height='14' src='http://seeds.ca/w/img/ctrl/delete01.png'/>"
                         ."</div>"
                     ."</div>";
            }
            $s .= "</div>";
        }

        if( !$s ) goto done;

        $s .= "<div style='text-alignment:right;font-size:12pt;color:green'>Your Total: \${$raSummary['fTotal']}</div>";

        $s = "<div class='sb_basket-contents'>$s</div>";

        done:
        return( $s ? $s : "Your Basket is Empty" );
    }

    function ComputeBasketSummary()
    /******************************
        Compute information about the current basket

        fTotal            : the total amount to pay
        raSellers         : array( uidSeller1 => array( fTotal => the total amount to pay to this seller,
                                                        raItems => array( kBP=>kBP, sItem => describes the item, fAmount => amount to pay ) )

                            N.B. shipping / discount are formatted as individual raItems immediately following their item with kBP==0
     */
    {
        $raOut = array( 'fTotal'=>0.0, 'raSellers'=>array() );

        if( ($kfrBPxP = $this->oDB->GetPurchasesKFRC( $this->GetBasketKey() )) ) {
            while( $kfrBPxP->CursorFetch() ) {
                $uidSeller = $kfrBPxP->Value('P_uid_seller');
// handle volume pricing, shipping, discount
                $fAmount = $this->getAmount( $kfrBPxP );
                $raOut['fTotal'] += $fAmount;
                if( !isset($raOut['raSellers'][$uidSeller]) ) {
                    $raOut['raSellers'][$uidSeller] = array( 'fTotal'=>0.0, 'raContents'=>array() );
                }

                $oHandler = $this->getHandler( $kfrBPxP->Value('P_product_type') );
                $sItem = $oHandler->PurchaseDraw( $kfrBPxP );
                $raOut['raSellers'][$uidSeller]['fTotal'] += $fAmount;
                $raOut['raSellers'][$uidSeller]['raItems'][] = array( 'kBP'=>$kfrBPxP->Key(), 'sItem'=>$sItem, 'fAmount'=>$fAmount );

                // derived class adjustment
// k is non-zero if the user is a current grower member
if( ($this->oDB->kfdb->Query1( "SELECT _key FROM seeds.sed_curr_growers WHERE mbr_id='".$this->GetUID_SB()."' AND NOT bSkip" )) ) {
    if( floatval($kfrBPxP->Value('P_item_price')) == 12.00 ) {
        $discount = -2.0;
    } else {
        $discount = -1.0;
    }
    $raOut['raSellers'][$uidSeller]['fTotal'] += $discount;
    $raOut['raSellers'][$uidSeller]['raItems'][] = array( 'kBP'=>0, 'sItem'=>"Your grower member discount", 'fAmount'=>$discount );
}
                // add other items for shipping / discount

            }
        }
        return( $raOut );
    }

    private function getAmount( KFRecord $kfrBPxP )
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
            $f = $this->dollar( $sRange );
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

    function dollar( $d )  { return( "$".sprintf("%0.2f", $d) ); }


    /**
        Command methods
     */

    private function addProductToBasket( KFRecord $kfrP, $raParmsBP )
    {
        $kBPNew = 0;

        // Create a basket if there isn't one, and make sure any existing basket is open.
        $this->BasketAcquire();
        if( !$this->BasketIsOpen() )  goto done;

        // The input parms can be http or just ordinary arrays
        //     sb_n   (int):    quantity to add
        //     sb_f   (float):  amount to add
        //     sb_p_* (string): arbitrary parameters known to Purchase0 and Purchase2
        $raPurchaseParms = array();
        if( ($n = intval(@$raParmsBP['sb_n'])) )    $raPurchaseParms['n'] = $n;
        if( ($f = floatval(@$raParmsBP['sb_f'])) )  $raPurchaseParms['f'] = $f;
        foreach( $raParmsBP as $k => $v ) {
            if( substr($k,0,5) != 'sb_p_' || strlen($k) < 6 ) continue;

            $raPurchaseParms[substr($k,5)] = $v;
        }

        if( ($oHandler = $this->getHandler( $kfrP->Value('product_type') )) ) {
            $kBPNew = $oHandler->Purchase2( $kfrP, $raPurchaseParms );
        }

        done:
        return( $kBPNew ? array( true, $this->DrawBasketContents( array( 'kBPHighlight'=>$kBPNew ) ) )
                        : array( false, "" ) );
    }

    private function removeProductFromBasket( $kBP )
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
        $s = $this->DrawBasketContents();
        return( array($bOk,$s) );
    }

    private function clearBasket()
    /*****************************
     */
    {
        $bOk = false;
        $s = "";

        if( !$this->BasketIsOpen() || !($kB = $this->GetBasketKey()) )  goto done;

        $bOk = $this->oDB->kfdb->Execute( "DELETE FROM seeds.SEEDBasket_BP WHERE fk_SEEDBasket_Baskets='$kB'" );

        done:
        $s = $this->DrawBasketContents();
        return( array($bOk,$s) );
    }

    function GetCurrentBasketKFR()
    /*****************************
        Return a kfr of the current basket.
        If there isn't one, create one and start using it.
     */
    {
        if( ($kB = $this->GetBasketKey()) ) {
            $kfrB = $this->oDB->GetBasket( $kB );
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
