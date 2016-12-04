<?php

/* SEEDBasket.php
 *
 * Copyright (c) 2016 Seeds of Diversity Canada
 *
 * Manage a shopping basket of diverse products
 */

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

        if( ($kfrBP = $this->oDB->GetKFRC( 'BP', "fk_SEEDBasket_Baskets='{$this->kBasket}'" )) ) {
            while( $kfrBP->CursorFetch() ) {
                if( ($kfrP = $this->oDB->GetProduct( $kfrBP->Value( 'fk_SEEDBasket_Products' ) )) ) {
                    $oHandler = $this->getHandler( $kfrP->Value('product_type') );
                    $sStyle = ($kBP && $kBP == $kfrBP->Key()) ? "background-color:#cec" : "";
                    $s .= "<div style='$sStyle'>".$oHandler->PurchaseDraw( $kfrBP, $kfrP )."</div>";
                }
            }
        }

        done:
        return( $s ? $s : "Your Basket is Empty" );
    }

    function AddProductToBasket_kProd( $kP, $raBP, $bGPC = false )
    /*************************************************************
        Add a product to the current basket.
        $raBP are the BxP parameters with names appropriate for http, bGPC is true if raBP are http
     */
    {
        if( ($kfrP = $this->oDB->GetProduct( 'P', $kP )) ) {
            $oHandler = $this->getHandler( $kfrP->Value('product_type') );
            return( $oHandler->Purchase2( $kfrP, $raBP, $bGPC ) );
        } else {
            return( array( false, "There is no product '$kP'" ) );
        }
    }

    function AddProductToBasket_Name( $prodName, $raBP, $bGPC = false )
    /******************************************************************
        Add a named product to the current basket.
     */
    {
        if( ($kfrP = $this->oDB->GetKFRCond( 'P', "name='".addslashes($prodName)."'" )) ) {
            $oHandler = $this->getHandler( $kfrP->Value('product_type') );
            return( $oHandler->Purchase2( $kfrP, $raBP, $bGPC ) );
        } else {
            return( array( false, "There is no product called '$name'" ) );
        }
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


class SEEDBasketProductHandler
/*****************************
    Every time you do something with a product, you use a derivation of this.
    So you have to make a Handler for every productType that you use.

    ProductDefine0          Draw a form to create/update a product definition
    ProductDefine1          Validate a product definition
    ProductDefine2          Save a product definition
    ProductDraw( bDetail )  Show a description of a product in more or less detail
    ProductDelete( bHard )  Remove a product from the system (only does soft delete if the product is referenced by any BP)

    Purchase0               Draw a form for the purchase details stored in a BasketXProduct
    Purchase1               Validate a purchase before adding/updating a BP
    Purchase2               Add/update a BP
    PurchaseDraw( bDetail ) Show a description of a BP in more or less detail, from a buyer's perspective
    PurchaseDelete          Remove a BP from its basket

    FulfilDraw( bDetail )   Show a description of a BP in more or less detail, from the seller's perspective
 */
{
    private $oSB;

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

    function ProductDefine2( KFRecord $kfrP )
    /******************************************
        Save a product definition
     */
    {
        // probably never used because SEEDBasketCore is just going to use PutDBRow()
    }

    function ProductDraw( KFRecord $kfrP, $bDetail )
    /***********************************************
        Show a product definition in more or less detail
     */
    {
        // Override this with a product type specific method

        if( !$kfrP ) return( "Error: no product record" );

        $s = $kfrP->Expand( "<h4>[[product_type]] [[title_en]]</h4>" );
        if( $bDetail ) {
            $s .= $kfrP->Expand( "Name: [[name]]<br/>" )
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
        Given a product, draw the form that a store would show to purchase it
     */
    {
        return( $kfrP->Value('title_en') );
    }

    function Purchase2( KFRecord $kfrP, $raBP, $bGPC )
    /*************************************************
        Given a product, add it to the current basket
     */
    {
        $bOk = false;
        $s = "";

        if( !($kfrB = $this->oSB->GetCurrentBasketKFR()) )  goto done;

        if( ($kfrBP = $this->oSB->oDB->GetKfrel('BP')->CreateRecord()) ) {
            $kfrBP->SetValue( 'fk_SEEDBasket_Products', $kfrP->Key() );
            $kfrBP->SetValue( 'fk_SEEDBasket_Baskets', $kfrB->Key() );
            $kfrBP->SetValue( 'n', ($bGPC ? SEEDSafeGPC_GetInt('n') : intval(@$raBP['n'])) );
            $kfrBP->SetValue( 'f', ($bGPC ? SEEDSafeGPC_GetStrPlain('f') : floatval(@$raBP['f'])) );
            $kfrBP->PutDBRow();
            $bOk = true;
            $s = $this->oSB->DrawBasketContents( $kfrBP->Key() );
        }

        done:
        return( array($bOk, $s) );
    }

    function PurchaseDraw( KFRecord $kfrBP, $bDetail = false, KFRecord $kfrP = null )
    /********************************************************************************
        Draw a product in a basket, in more or less detail. Give kfrP for convenience if you already know it.
     */
    {
        if( !$kfrP )  $kfrP = $this->oSB->oDB->GetProduct( $kfrBP->Value('fk_SEEDBasket_Products') );

        $s = $kfrP->Value( 'title_en' );

        return( $s );
    }


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

    function dollar( $d )  { return( "$".$d ); }

    function ExplainPriceRange( $sRange )
    /************************************
        Explain the contents of a price range

        e.g. '15', '15:1-9,12:10-19,10:20+'
     */
    {
        $s = "";

        if( strpos( $sRange, ',' ) === false && strpos( $sRange, ':' ) === false ) {
            // There is just a single price for all quantities
            $s = $this->dollar( $sRange );
        } else {
            $raRanges = explode( ',', $sRange );
            foreach( $raRanges as $r ) {
                $r = trim($r);

                // $r has to be price:N or price:M-N or price:M+
                list($price,$sQRange) = explode( ":", $r );
                if( strpos( '-', $sQRange) !== false ) {
                    list($sQ1,$sQ2) = explode( '-', $sQRange );
                    $s .= ($s ? ", " : "").$this->dollar($price)." for $sQ1 to $sQ2 items";
                } else if( substr( $sQRange, -1, 1 ) == "+" ) {
                    $sQ1 = intval($sQRange);
                    $s .= ($s ? ", " : "").$this->dollar($price)." for $sQ1 items or more";
                } else {
                    $s .= ($s ? ", " : "").$this->dollar($price)." for $sQRange items";
                }


            }
        }

        return( $s );
    }


}


class SEEDBasketDB extends KeyFrameNamedRelations
{
    function __construct( KeyFrameDB $kfdb, $uid )
    {
        parent::__construct( $kfdb, $uid );
    }


    function GetBasket( $kBasket )    { return( $this->GetKFR( 'B', $kBasket ) ); }
    function GetProduct( $kProduct )  { return( $this->GetKFR( 'P', $kProduct ) ); }

    function GetBasketList( $sCond, $raKFParms = array() )  { return( $this->GetList( 'B', $sCond, $raKFParms ) ); }
    function GetBasketKFRC( $sCond, $raKFParms = array() )  { return( $this->GetList( 'B', $sCond, $raKFParms ) ); }
    function GetProductList( $sCond, $raKFParms = array() ) { return( $this->GetKFRC( 'P', $sCond, $raKFParms ) ); }
    function GetProductKFRC( $sCond, $raKFParms = array() ) { return( $this->GetKFRC( 'P', $sCond, $raKFParms ) ); }

    protected function initKfrel( KeyFrameDB $kfdb, $uid )
    {
        /* raKfrel['B']   base relation for SEEDBasket_Baskets
         * raKfrel['P']   base relation for SEEDBasket_Products
         * raKfrel['BP']  base relation for SEEDBasket_BP map table
         * raKfrel['BxP'] joins baskets and products via B x BP x P
         */
        $kdefBaskets =
            array( "Tables" => array( array( "Table" => 'seeds.SEEDBasket_Baskets',
                                             "Alias" => "B",
                                             "Type" => "Base",
                                             "Fields" => "Auto" ) ) );
        $kdefProducts =
            array( "Tables" => array( array( "Table" => 'seeds.SEEDBasket_Products',
                                             "Alias" => "P",
                                             "Type" => "Base",
                                             "Fields" => "Auto" ) ) );
        $kdefBP =
            array( "Tables" => array( array( "Table" => 'seeds.SEEDBasket_BP',
                                             "Alias" => "BP",
                                             "Type" => "Base",
                                             "Fields" => "Auto" ) ) );
        $kdefBxP =
            array( "Tables" => array( array( "Table" => 'seeds.SEEDBasket_Baskets',
                                             "Alias" => "B",
                                             "Type" => "Base",
                                             "Fields" => "Auto" ),
                                      array( "Table" => 'seeds.SEEDBasket_BP',
                                             "Alias" => "BP",
                                             "Type" => "Map",
                                             "Fields" => "Auto" ),
                                      array( "Table" => 'seeds.SEEDBasket_Products',
                                             "Alias" => "P",
                                             "Type" => "Children",
                                             "Fields" => "Auto" ) ) );

        $raParms = array( 'logfile' => SITE_LOG_ROOT."SEEDBasket.log" );
        $raKfrel = array();
        $raKfrel['B']   = new KeyFrameRelation( $kfdb, array_merge( array('ver',2), $kdefBaskets ),  $uid, $raParms );
        $raKfrel['P']   = new KeyFrameRelation( $kfdb, array_merge( array('ver',2), $kdefProducts ), $uid, $raParms );
        $raKfrel['BP']  = new KeyFrameRelation( $kfdb, array_merge( array('ver',2), $kdefBP ),       $uid, $raParms );
        $raKfrel['BxP'] = new KeyFrameRelation( $kfdb, array_merge( array('ver',2), $kdefBxP ),      $uid, $raParms );

        return( $raKfrel );
    }
}




define("SEEDS_DB_TABLE_SEEDBASKET_BASKETS",
"
CREATE TABLE SEEDBasket_Baskets (
        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    -- About the buyer
    uid_buyer       INTEGER NOT NULL DEFAULT '0',
    buyer_firstname VARCHAR(100) NOT NULL DEFAULT '',
    buyer_lastname  VARCHAR(100) NOT NULL DEFAULT '',
    buyer_company   VARCHAR(100) NOT NULL DEFAULT '',
    buyer_addr      VARCHAR(100) NOT NULL DEFAULT '',
    buyer_city      VARCHAR(100) NOT NULL DEFAULT '',
    buyer_prov      VARCHAR(100) NOT NULL DEFAULT '',
    buyer_postcode  VARCHAR(100) NOT NULL DEFAULT '',
    buyer_country   VARCHAR(100) NOT NULL DEFAULT '',
    buyer_phone     VARCHAR(100) NOT NULL DEFAULT '',
    buyer_email     VARCHAR(100) NOT NULL DEFAULT '',
    buyer_lang      ENUM('E','F') NOT NULL DEFAULT 'E',
    buyer_notes     TEXT NOT NULL DEFAULT '',

    buyer_extra     TEXT NOT NULL DEFAULT '',


    -- About the products

    prod_extra      TEXT NOT NULL DEFAULT '',


    -- About the payment
    pay_eType       ENUM('PayPal','Cheque') NOT NULL DEFAULT 'PayPal',
    pay_total       DECIMAL(8,2)            NOT NULL DEFAULT 0,
    pay_currency    ENUM('CDN','USD')       NOT NULL DEFAULT 'CDN',

    pay_extra       TEXT NOT NULL DEFAULT '',

    pp_name         VARCHAR(200),   -- Set by PPIPN
    pp_txn_id       VARCHAR(200),
    pp_receipt_id   VARCHAR(200),
    pp_payer_email  VARCHAR(200),
    pp_payment_status VARCHAR(200),


    -- About the fulfilment
    eStatus         ENUM('New','Paid','Filled','Cancelled') NOT NULL DEFAULT 'New',
    notes           TEXT NOT NULL DEFAULT '',


    sExtra          TEXT NOT NULL DEFAULT ''            -- e.g. urlencoded metadata about the purchase
-- in sExtra   mail_eBull      BOOL            DEFAULT 1,
-- in sExtra   mail_where      VARCHAR(100),
);
"
);

define("SEEDS_DB_TABLE_SEEDBASKET_PRODUCTS",
"
CREATE TABLE SEEDBasket_Products (
        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    uid_seller      INTEGER NOT NULL DEFAULT '0',
    product_type    VARCHAR(100) NOT NULL,
    eStatus         ENUM( 'ACTIVE', 'INACTIVE', 'DELETED' ) NOT NULL DEFAULT 'ACTIVE',

    title_en        VARCHAR(200) NOT NULL DEFAULT '',
    title_fr        VARCHAR(200) NOT NULL DEFAULT '',
    name            VARCHAR(100) NOT NULL DEFAULT '',
    img             TEXT NOT NULL DEFAULT '',          -- multiple images can be separated by \t

    quant_type      ENUM('ITEM-N',                     -- you can order one or more at a time
                         'ITEM-1',                     -- it only makes sense to order one of this product at a time
                         'MONEY')                      -- this product is a buyer-specified amount of money (e.g. a donation)
                      NOT NULL DEFAULT 'ITEM-N',

    bask_quant_min  INTEGER NOT NULL DEFAULT '0',      -- you have to put at least this many in a basket if you have any
    bask_quant_max  INTEGER NOT NULL DEFAULT '0',      -- you can't put more than this in a basket at once (-1 means no limit)

    item_price      VARCHAR(100) NOT NULL DEFAULT '',  -- e.g. '15', '15:1-9,10:10-24,8:25+'
    item_discount   VARCHAR(100) NOT NULL DEFAULT '',  -- e.g. '0', '0:1-9,5:10-24,7:25+'

    item_price_US    VARCHAR(100) NOT NULL DEFAULT '',
    item_discount_US VARCHAR(100) NOT NULL DEFAULT '',

    item_shipping    VARCHAR(100) NOT NULL DEFAULT '',  -- e.g. '10', '10:1-9,5:10-14,0:15+'
    item_shipping_US VARCHAR(100) NOT NULL DEFAULT '',




    mbr_type        VARCHAR(100),
    donation        INTEGER,
    pub_ssh_en      INTEGER,
    pub_ssh_fr      INTEGER,
    pub_nmd         INTEGER,
    pub_shc         INTEGER,
    pub_rl          INTEGER,


    v_i1             INTEGER NOT NULL DEFAULT 0,
    v_i2             INTEGER NOT NULL DEFAULT 0,
    v_i3             INTEGER NOT NULL DEFAULT 0,

    v_t1             TEXT NOT NULL DEFAULT '',
    v_t2             TEXT NOT NULL DEFAULT '',
    v_t3             TEXT NOT NULL DEFAULT '',

    sExtra          TEXT NOT NULL DEFAULT ''            -- e.g. urlencoded metadata about the product
);
"
);


define("SEEDS_DB_TABLE_SEEDBASKET_BP",
"
CREATE TABLE SEEDBasket_BP (
        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    fk_SEEDBasket_Baskets  INTEGER NOT NULL,
    fk_SEEDBasket_Products INTEGER NOT NULL,
    n                      INTEGER NOT NULL,        -- the number of items if ITEM type
    f                      DECIMAL(7,2) NOT NULL,   -- the amount if MONEY type
    eStatus                ENUM('NEW','PAID','FILLED','CANCELLED') NOT NULL DEFAULT 'NEW',
    bAccountingDone        TINYINT NOT NULL DEFAULT '0',

    sExtra                 TEXT NOT NULL DEFAULT ''            -- e.g. urlencoded metadata about the purchase
);
"
);


/* Test data

INSERT INTO seeds.SEEDBasket_Baskets ( buyer_firstname, buyer_lastname, eStatus ) VALUES ( 'Bob', 'Wildfong', 'PAID' );

INSERT INTO seeds.SEEDBasket_Products ( uid_seller,product_type,eStatus,title_en,name,quant_type,bask_quant_min,bask_quant_max,item_price ) VALUES (1,'donation','ACTIVE','Donation','donation','MONEY',0,-1,-1);
INSERT INTO seeds.SEEDBasket_Products ( uid_seller,product_type,eStatus,title_en,name,quant_type,bask_quant_min,bask_quant_max,item_price ) VALUES (1,'book','ACTIVE','How to Save Your Own Seeds, 6th edition','ssh6-en','ITEM-N',1,-1,15);
INSERT INTO seeds.SEEDBasket_Products ( uid_seller,product_type,eStatus,title_en,name,quant_type,bask_quant_min,bask_quant_max,item_price ) VALUES (1,'membership','ACTIVE','Membership - One Year','mbr25','ITEM-1',1,1,25);

INSERT INTO seeds.SEEDBasket_BP (fk_SEEDBasket_Baskets,fk_SEEDBasket_Products,n,f,eStatus) VALUES (1,1,0,123.45,'PAID');
INSERT INTO seeds.SEEDBasket_BP (fk_SEEDBasket_Baskets,fk_SEEDBasket_Products,n,f,eStatus) VALUES (1,2,5,0,'PAID');
INSERT INTO seeds.SEEDBasket_BP (fk_SEEDBasket_Baskets,fk_SEEDBasket_Products,n,f,eStatus) VALUES (1,3,1,0,'PAID');


 */



?>
