<?php

/* SEEDBasketDB.php
 *
 * Copyright (c) 2016 Seeds of Diversity Canada
 *
 * DB layer for shopping baskets
 */


class SEEDBasketDB extends KeyFrameNamedRelations
{
    public $kfdb;   // just so third parties can find this in a likely place

    function __construct( KeyFrameDB $kfdb, $uid )
    {
        $this->kfdb = $kfdb;
        parent::__construct( $kfdb, $uid );
    }


    function GetBasket( $kBasket )    { return( $this->GetKFR( 'B', $kBasket ) ); }
    function GetProduct( $kProduct )  { return( $this->GetKFR( 'P', $kProduct ) ); }
    function GetBP( $kBP )            { return( $this->GetKFR( 'BP', $kBP ) ); }

    function GetBasketList( $sCond, $raKFParms = array() )  { return( $this->GetList( 'B', $sCond, $raKFParms ) ); }
    function GetBasketKFRC( $sCond, $raKFParms = array() )  { return( $this->GetList( 'B', $sCond, $raKFParms ) ); }
    function GetProductList( $sCond, $raKFParms = array() ) { return( $this->GetKFRC( 'P', $sCond, $raKFParms ) ); }
    function GetProductKFRC( $sCond, $raKFParms = array() ) { return( $this->GetKFRC( 'P', $sCond, $raKFParms ) ); }

    function GetPurchasesList( $kB, $raKFParms = array() ) { return( $this->GetList('BPxP', "fk_SEEDBasket_Baskets='$kB'", $raKFParms) ); }
    function GetPurchasesKFRC( $kB, $raKFParms = array() ) { return( $this->GetKFRC('BPxP', "fk_SEEDBasket_Baskets='$kB'", $raKFParms) ); }

    function GetProdExtraList( $kProduct )
    /*************************************
        Get all product-extra items associated with this product.
     */
    {
        $raExtra = array();

        if( ($raRows = $this->GetList( 'PE', "fk_SEEDBasket_Products='$kProduct'" ))) {
            foreach( $raRows as $ra ) {
                $raExtra[$ra['k']] = $ra['v'];
            }
        }

        return( $raExtra );
    }

    function SetProdExtra( $kProduct, $k, $v )
    /*****************************************
        Set a product-extra item. This makes no db change if the value is unchanged.
     */
    {
        // iStatus==-1 fetches any _status of the prodExtra row, so we can re-use a deleted/hidden row if any
        if( ($kfr = $this->GetKFRCond( 'PE', "fk_SEEDBasket_Products='$kProduct' AND k='".addslashes($k)."'", array('iStatus'=>-1))) ) {
            $kfr->StatusSet( KFRECORD_STATUS_NORMAL );  // because maybe this is an old row that has been deleted or hidden
        } else {
            $kfr = $this->GetKfrel( 'PE' )->CreateRecord();
            $kfr->SetValue( 'fk_SEEDBasket_Products', $kProduct );
        }
        $kfr->SetValue( 'k', $k );
        $kfr->SetValue( 'v', $v );
        $ok = $kfr->PutDBRow();

        return( $ok );
    }

    function SetProdExtraList( $kProduct, $raExtra )
    /***********************************************
        Set a bunch of product-extra items. This does not clear any existing items that are not mentioned in the array.
     */
    {
        foreach( $raExtra as $k => $v ) {
            $this->SetProdExtra( $kProduct, $k, $v );
        }
    }

    function DeleteProdExtra( $kProduct, $k )
    {
        // Normally, you don't delete or hide prodExtra for a deleted/hidden product. You just do that for the product row.
        // Something should purge the prodExtra rows when you hard-delete a product though.
    }

    protected function initKfrel( KeyFrameDB $kfdb, $uid )
    {
        /* raKfrel['B']    base relation for SEEDBasket_Baskets
         * raKfrel['P']    base relation for SEEDBasket_Products
         * raKfrel['BP']   base relation for SEEDBasket_BP map table
         * raKfrel['BxP']  joins baskets and products via B x BP x P
         * raKfrel['BPxP'] tells you about the products in a basket and allows updates to the purchases
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
        $kdefProdExtra =
            array( "Tables" => array( array( "Table" => 'seeds.SEEDBasket_ProdExtra',
                                             "Alias" => "PE",
                                             "Type" => "Base",
                                             "Fields" => "Auto" ) ) );
        $kdefPxPE =
            array( "Tables" => array( array( "Table" => 'seeds.SEEDBasket_Products',
                                             "Alias" => "P",
                                             "Type" => "Base",
                                             "Fields" => "Auto" ),
                                      array( "Table" => 'seeds.SEEDBasket_ProdExtra',
                                             "Alias" => "PE",
                                             "Type" => "Children",
                                             "Fields" => "Auto" ) ) );
        // Products joined with ProdExtra twice, which is only useful if at least one ProdExtra is constrained by k
        // i.e. what are all the products and their PE2.v that have PE1.v='foo'
        $kdefPxPE2 =
            array( "Tables" => array( array( "Table" => 'seeds.SEEDBasket_Products',
                                             "Alias" => "P",
                                             "Type" => "Base",
                                             "Fields" => "Auto" ),
                                      array( "Table" => 'seeds.SEEDBasket_ProdExtra',
                                             "Alias" => "PE1",
                                             "Type" => "Children",
                                             "Fields" => "Auto" ),
                                      array( "Table" => 'seeds.SEEDBasket_ProdExtra',
                                             "Alias" => "PE2",
                                             "Type" => "Children",
                                             "Fields" => "Auto" ) ) );
        $kdefPxPE3 = $kdefPxPE2;
        $kdefPxPE3['Tables'][] =      array( "Table" => 'seeds.SEEDBasket_ProdExtra',
                                             "Alias" => "PE3",
                                             "Type" => "Children",
                                             "Fields" => "Auto" );
        $kdefBP =
            array( "Tables" => array( array( "Table" => 'seeds.SEEDBasket_BP',
                                             "Alias" => "BP",
                                             "Type" => "Base",
                                             "Fields" => "Auto" ) ) );
        // really BxBPxP but this abbreviation is not ambiguous
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
        $kdefBPxP =
            array( "Tables" => array( array( "Table" => 'seeds.SEEDBasket_BP',
                                             "Alias" => "BP",
                                             "Type" => "Base",
                                             "Fields" => "Auto" ),
                                      array( "Table" => 'seeds.SEEDBasket_Products',
                                             "Alias" => "P",
                                             "Type" => "Children",
                                             "Fields" => "Auto" ) ) );

        $raParms = array( 'logfile' => SITE_LOG_ROOT."SEEDBasket.log" );
        $raKfrel = array();
        $raKfrel['B']    = new KeyFrameRelation( $kfdb, array_merge( array('ver',2), $kdefBaskets ),  $uid, $raParms );
        $raKfrel['P']    = new KeyFrameRelation( $kfdb, array_merge( array('ver',2), $kdefProducts ), $uid, $raParms );
        $raKfrel['PE']   = new KeyFrameRelation( $kfdb, array_merge( array('ver',2), $kdefProdExtra), $uid, $raParms );
        $raKfrel['PxPE'] = new KeyFrameRelation( $kfdb, array_merge( array('ver',2), $kdefPxPE),      $uid, $raParms );
        $raKfrel['PxPE2']= new KeyFrameRelation( $kfdb, array_merge( array('ver',2), $kdefPxPE2),     $uid, $raParms );
        $raKfrel['PxPE3']= new KeyFrameRelation( $kfdb, array_merge( array('ver',2), $kdefPxPE3),     $uid, $raParms );
        $raKfrel['BP']   = new KeyFrameRelation( $kfdb, array_merge( array('ver',2), $kdefBP ),       $uid, $raParms );
        $raKfrel['BxP']  = new KeyFrameRelation( $kfdb, array_merge( array('ver',2), $kdefBxP ),      $uid, $raParms );
        $raKfrel['BPxP'] = new KeyFrameRelation( $kfdb, array_merge( array('ver',2), $kdefBPxP ),     $uid, $raParms );

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


    sExtra          TEXT NOT NULL DEFAULT '',            -- e.g. urlencoded metadata about the purchase
-- in sExtra   mail_eBull      BOOL            DEFAULT 1,
-- in sExtra   mail_where      VARCHAR(100),

    INDEX (uid_buyer)
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

    sExtra          TEXT NOT NULL DEFAULT '',           -- e.g. urlencoded metadata about the product

    INDEX(product_type)
);
"
);

define("SEEDS_DB_TABLE_SEEDBASKET_PRODEXTRA",
"
CREATE TABLE SEEDBasket_ProdExtra (
        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    fk_SEEDBasket_Products INTEGER NOT NULL,
    k                      TEXT NOT NULL DEFAULT '',
    v                      TEXT NOT NULL DEFAULT '',

    INDEX(fk_SEEDBasket_Products),
    INDEX(k(20)),
    INDEX(v(20))
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

    sExtra                 TEXT NOT NULL DEFAULT '',            -- e.g. urlencoded metadata about the purchase

  --  INDEX(fk_SEEDBasket_Products),  does anyone use this?
    INDEX(fk_SEEDBasket_Baskets)
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
