<?php

/* SEEDBasketDB.php
 *
 * Copyright (c) 2016-2019 Seeds of Diversity Canada
 *
 * DB layer for shopping baskets
 */


class SEEDBasketDB extends Keyframe_NamedRelations
{
    public $kfdb;   // just so third parties can find this in a likely place
    private $raCustomProductKfrelDefs = array();
    private $db;

    function __construct( KeyframeDatabase $kfdb, $uid, $logdir, $raConfig = array() )
    {
        $this->kfdb = $kfdb;
        $this->db = @$raConfig['db'] ?: $kfdb->GetDB();
        $this->raCustomProductKfrelDefs = @$raConfig['raCustomProductKfrelDefs'] ?: array();

        parent::__construct( $kfdb, $uid, $logdir );
    }


    function GetBasketKFR( $kBasket )    { return( $this->GetKFR( 'B', $kBasket ) ); }
    function GetProductKFR( $kProduct )  { return( $this->GetKFR( 'P', $kProduct ) ); }
    function GetPURKFR( $kPur )          { return( $this->GetKFR( 'PUR', $kPur ) ); }
    function GetPurchaseKFR( $kPur )     { return( $this->GetKFR( 'PURxP', $kPur ) ); }
    /*deprecated*/ function GetBasket($k)  { return($this->GetBasketKFR($k)); }
    /*deprecated*/ function GetProduct($k) { return($this->GetProductKFR($k)); }
    /*deprecated*/ function GetBP($k)      { return($this->GetPURKFR($k)); }
    function GetBasketKFREmpty()         { return( $this->Kfrel('B')->CreateRecord() ); }
    function GetProductKFREmpty()        { return( $this->Kfrel('P')->CreateRecord() ); }
    function GetPURKFREmpty()            { return( $this->Kfrel('PUR')->CreateRecord() ); }

    // deprecated old names
    function GetBPKFR( $kBP )            { return( $this->GetPURKFR($kBP) ); }
    function GetBPKFREmpty()             { return( $this->GetPURKFREmpty() ); }


    function GetBasketList( $sCond, $raKFParms = array() )  { return( $this->GetList( 'B', $sCond, $raKFParms ) ); }
    function GetBasketKFRC( $sCond, $raKFParms = array() )  { return( $this->GetList( 'B', $sCond, $raKFParms ) ); }
    function GetProductList( $sCond, $raKFParms = array() ) { return( $this->GetKFRC( 'P', $sCond, $raKFParms ) ); }
    function GetProductKFRC( $sCond, $raKFParms = array() ) { return( $this->GetKFRC( 'P', $sCond, $raKFParms ) ); }

    function GetPurchasesList( $kB, $raKFParms = array() ) { return( $this->GetList('PURxP', "fk_SEEDBasket_Baskets='$kB'", $raKFParms) ); }
    function GetPurchasesKFRC( $kB, $raKFParms = array() ) { return( $this->GetKFRC('PURxP', "fk_SEEDBasket_Baskets='$kB'", $raKFParms) ); }

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


    function ProductLastUpdated( $cond, $raParms = array() )
    /*******************************************************
        Return P._key, _updated, _updated_by of the most recent update to a product.
        _key is always product key
        _updated* is either the product record or a prodextra record, whichever is newer

        Note that product._updated can be older than prodextra._updated

        e.g cond=>"P._key='123'"        returns the most recent of P._updated or related PE._updated for the given P
            cond=>"P.uid_seller='456'   returns the most recently updated product of that seller, whether _updated from P or PE
            cond=>"P.uid_seller='456' and P.product_type='widget'

        raParms:
            iStatus = filter _status (default 0; -1 means no filter)
     */
    {
        if( !$cond )  $cond = "1=1";

        $iStatus = intval(@$raParms['iStatus']);
        if( $iStatus == -1 ) {
            $cond1 = $cond2 = $cond;
        } else {
            $cond1 = $cond." AND P._status='$iStatus'";
            $cond2 = $cond." AND P._status='$iStatus' AND PE._status='$iStatus'";
        }

        $ra = $this->kfdb->QueryRA(
                "SELECT _updated,_updated_by,_key FROM
                     (
                     (SELECT P._updated as _updated,P._updated_by as _updated_by,P._key as _key
                         FROM {$this->db}.SEEDBasket_Products P
                         WHERE $cond1 ORDER BY 1 DESC LIMIT 1)
                     UNION
                     (SELECT PE._updated as _updated,PE._updated_by as _updated_by,P._key as _key
                         FROM {$this->db}.SEEDBasket_ProdExtra PE,{$this->db}.SEEDBasket_Products P
                         WHERE P._key=PE.fk_SEEDBasket_Products AND
                               $cond2 ORDER BY 1 DESC LIMIT 1)
                     ) as A
                 ORDER BY 1 DESC LIMIT 1" );

        return( array(@$ra['_key'], @$ra['_updated'], @$ra['_updated_by']) );
    }

    protected function initKfrel( KeyframeDatabase $kfdb, $uid, $logdir )
    {
        /* raKfrel['B']    base relation for SEEDBasket_Baskets
         * raKfrel['P']    base relation for SEEDBasket_Products
         * raKfrel['PUR']  base relation for SEEDBasket_Purchase map table
         * raKfrel['BxP']  joins baskets and products via B x PUR x P
         * raKfrel['PURxP'] tells you about the products in a basket and allows updates to the purchases
         */
        $kdefBaskets =
            array( "Tables" => array( "B" => array( "Table" => "{$this->db}.SEEDBasket_Baskets",
                                                    "Fields" => "Auto" ) ) );
        $kdefProducts =
            array( "Tables" => array( "P" => array( "Table" => "{$this->db}.SEEDBasket_Products",
                                                    "Fields" => "Auto" ) ) );
        $kdefProdExtra =
            array( "Tables" => array( "PE" => array( "Table" => "{$this->db}.SEEDBasket_ProdExtra",
                                                     "Fields" => "Auto" ) ) );
        $kdefPxPE =
            array( "Tables" => array( "P"=> array( "Table" => "{$this->db}.SEEDBasket_Products",
                                                   "Type" => "Base",
                                                   "Fields" => "Auto" ),
                                      "PE"=> array( "Table" => "{$this->db}.SEEDBasket_ProdExtra",
                                                    "Fields" => "Auto" ) ) );
        // Products joined with ProdExtra twice, which is only useful if at least one ProdExtra is constrained by k
        // i.e. what are all the products and their PE2.v that have PE1.v='foo'
        $kdefPxPE2 = array( "Tables" =>
            array( "P" => array( "Table" => "{$this->db}.SEEDBasket_Products",
                                 "Type" => "Base",
                                 "Fields" => "Auto" ),
                   "PE1" => array( "Table" => "{$this->db}.SEEDBasket_ProdExtra",
                                   "Fields" => "Auto" ),
                   "PE2" => array( "Table" => "{$this->db}.SEEDBasket_ProdExtra",
                                   "Fields" => "Auto" ) ) );
        $kdefPxPE3 = $kdefPxPE2;
        $kdefPxPE3['Tables']['PE3'] = array( "Table" => "{$this->db}.SEEDBasket_ProdExtra",
                                             "Fields" => "Auto" );
        $kdefPUR =
            array( "Tables" => array( "PUR" => array( "Table" => "{$this->db}.SEEDBasket_BP",
                                                      "Fields" => "Auto" ) ) );

        $kdefBxPURxP = array( "Tables" =>
            array( "B" => array( "Table" => "{$this->db}.SEEDBasket_Baskets",
                                 "Type" => "Base",
                                 "Fields" => "Auto" ),
                   "PUR"=> array( "Table" => "{$this->db}.SEEDBasket_BP",
                                 "Fields" => "Auto" ),
                   "P" => array( "Table" => "{$this->db}.SEEDBasket_Products",
                                 "Alias" => "P",
                                 "Type" => "Children",
                                 "Fields" => "Auto" ) ) );
        $kdefPURxP = array( "Tables" =>
            array( "PUR" => array( "Table" => "{$this->db}.SEEDBasket_BP",
                                  "Type" => "Base",
                                  "Fields" => "Auto" ),
                   "P" =>  array( "Table" => "{$this->db}.SEEDBasket_Products",
                                  "Fields" => "Auto" ) ) );

        $raParms = array( 'logfile' => $logdir."SEEDBasket.log" );
        $raKfrel = array();
        $raKfrel['B']    = new Keyframe_Relation( $kfdb, $kdefBaskets,   $uid, $raParms );
        $raKfrel['P']    = new Keyframe_Relation( $kfdb, $kdefProducts,  $uid, $raParms );
        $raKfrel['PE']   = new Keyframe_Relation( $kfdb, $kdefProdExtra, $uid, $raParms );
        $raKfrel['PxPE'] = new Keyframe_Relation( $kfdb, $kdefPxPE,      $uid, $raParms );
        $raKfrel['PxPE2']= new Keyframe_Relation( $kfdb, $kdefPxPE2,     $uid, $raParms );
        $raKfrel['PxPE3']= new Keyframe_Relation( $kfdb, $kdefPxPE3,     $uid, $raParms );
        $raKfrel['PUR']   = new Keyframe_Relation( $kfdb, $kdefPUR,      $uid, $raParms );
        $raKfrel['BxPURxP']  = new Keyframe_Relation( $kfdb, $kdefBxPURxP,     $uid, $raParms );
        $raKfrel['PURxP'] = new Keyframe_Relation( $kfdb, $kdefPURxP,    $uid, $raParms );

        // deprecated older names
        $raKfrel['BP'] = $raKfrel['PUR'];
        $raKfrel['BxP'] = $raKfrel['BxPURxP'];
        $raKfrel['BPxP'] = $raKfrel['PURxP'];

        /* Given an array of kfrel names => [PE keys], make named kfrels that left join Products with each PE
           i.e. [name1 => ['a','b'] ]  creates kfrel called name1 that does P_PEa_PEb where PEa.k='a' and PEb.k='b'
         */
        foreach( $this->raCustomProductKfrelDefs as $kfName => $raPE ) {
            $kdef = $kdefProducts;
            foreach( $raPE as $k ) {
                $kdef['Tables']['PE_'.$k] = [ "Table" => 'seeds.SEEDBasket_ProdExtra',
                                              "Type" => 'LeftJoin',
                                              "JoinOn" => "PE_{$k}.fk_SEEDBasket_Products=P._key AND PE_{$k}.k='$k'",
                                              "Fields" => "Auto" ];
            }
            $raKfrel[$kfName] = new Keyframe_Relation( $kfdb, $kdef, $uid, $raParms );
        }

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
    buyer_notes     TEXT,

    buyer_extra     TEXT,


    -- About the products

    prod_extra      TEXT,


    -- About the payment
    pay_eType       ENUM('PayPal','Cheque') NOT NULL DEFAULT 'PayPal',
    pay_total       DECIMAL(8,2)            NOT NULL DEFAULT 0,
    pay_currency    ENUM('CAD','USD')       NOT NULL DEFAULT 'CAD',

    pay_extra       TEXT,

    pp_name         VARCHAR(200),   -- Set by PPIPN
    pp_txn_id       VARCHAR(200),
    pp_receipt_id   VARCHAR(200),
    pp_payer_email  VARCHAR(200),
    pp_payment_status VARCHAR(200),


    -- About the fulfilment
    eStatus         ENUM('Open','Confirmed','Paid','Filled','Cancelled') NOT NULL DEFAULT 'Open',
    notes           TEXT,


    sExtra          TEXT,                 -- e.g. urlencoded metadata about the purchase
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
    img             TEXT,                              -- multiple images can be separated by \t

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

    v_t1             TEXT,
    v_t2             TEXT,
    v_t3             TEXT,

    sExtra          TEXT,           -- e.g. urlencoded metadata about the product

    INDEX(uid_seller),
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
    k                      TEXT,
    v                      TEXT,

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

    sExtra                 TEXT,                -- e.g. urlencoded metadata about the purchase

  --  INDEX(fk_SEEDBasket_Products),  does anyone use this?
    INDEX(fk_SEEDBasket_Baskets)
);
"
);


/* Test data

INSERT INTO seeds.SEEDBasket_Baskets ( buyer_firstname, buyer_lastname, eStatus) VALUES ( 'Bob', 'Wildfong', 'PAID' );

INSERT INTO seeds.SEEDBasket_Products ( uid_seller,product_type,eStatus,title_en,name,quant_type,bask_quant_min,bask_quant_max,item_price ) VALUES (1,'donation','ACTIVE','Donation','donation','MONEY',0,-1,-1);
INSERT INTO seeds.SEEDBasket_Products ( uid_seller,product_type,eStatus,title_en,name,quant_type,bask_quant_min,bask_quant_max,item_price ) VALUES (1,'book','ACTIVE','How to Save Your Own Seeds, 6th edition','ssh6-en','ITEM-N',1,-1,15);
INSERT INTO seeds.SEEDBasket_Products ( uid_seller,product_type,eStatus,title_en,name,quant_type,bask_quant_min,bask_quant_max,item_price ) VALUES (1,'membership','ACTIVE','Membership - One Year','mbr25','ITEM-1',1,1,25);

INSERT INTO seeds.SEEDBasket_BP (fk_SEEDBasket_Baskets,fk_SEEDBasket_Products,n,f,eStatus) VALUES (1,1,0,123.45,'PAID');
INSERT INTO seeds.SEEDBasket_BP (fk_SEEDBasket_Baskets,fk_SEEDBasket_Products,n,f,eStatus) VALUES (1,2,5,0,'PAID');
INSERT INTO seeds.SEEDBasket_BP (fk_SEEDBasket_Baskets,fk_SEEDBasket_Products,n,f,eStatus) VALUES (1,3,1,0,'PAID');


 */


?>
