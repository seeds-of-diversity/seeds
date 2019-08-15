<?php

include_once( SEEDCORE."SEEDBasket.php" );
include_once( SEEDAPP."basket/basketProductHandlers.php" );
include_once( SEEDAPP."basket/basketProductHandlers_seeds.php" );

class basketMan
{
    private $oApp;
    private $oSB;

    function __construct( SEEDAppConsole $oApp )
    {
define( "SITE_LOG_ROOT", $oApp->logdir );   // SEEDBasketCore should use oApp->logdir instead of the inflexible constant

        $this->oApp = $oApp;
        $this->oSB = new SEEDBasketCore( $oApp->kfdb, $oApp->sess, $oApp, SEEDBasketProducts_SoD::$raProductTypes, array('logdir'=>SITE_LOG_ROOT) );

    }

    function CreateDB()
    {
        $this->oApp->kfdb->SetDebug(2);

        $this->oApp->kfdb->Execute( "DROP TABLE SEEDBasket_Baskets" );
        $this->oApp->kfdb->Execute( "DROP TABLE SEEDBasket_Products" );
        $this->oApp->kfdb->Execute( "DROP TABLE SEEDBasket_ProdExtra" );
        $this->oApp->kfdb->Execute( "DROP TABLE SEEDBasket_BP" );
        $this->oApp->kfdb->Execute( "DROP TABLE SEEDBasket_Buyers" );
        $this->oApp->kfdb->Execute( "DROP TABLE SEEDBasket_Purchases" );

        $this->oApp->kfdb->Execute( SEEDS_DB_TABLE_SEEDBASKET_BUYERS );
        $this->oApp->kfdb->Execute( SEEDS_DB_TABLE_SEEDBASKET_PRODUCTS );
        $this->oApp->kfdb->Execute( SEEDS_DB_TABLE_SEEDBASKET_PRODEXTRA );
        $this->oApp->kfdb->Execute( SEEDS_DB_TABLE_SEEDBASKET_PURCHASES );

        $this->oApp->kfdb->Execute("INSERT INTO SEEDBasket_Buyers ( buyer_firstname, buyer_lastname, eStatus) VALUES ( 'Bob', 'Wildfong', 'PAID' )" );

        $this->oApp->kfdb->Execute("INSERT INTO SEEDBasket_Products ( uid_seller,product_type,eStatus,title_en,name,quant_type,bask_quant_min,bask_quant_max,item_price ) VALUES (1,'donation','ACTIVE','Donation','donation','MONEY',0,-1,-1)" );
        $this->oApp->kfdb->Execute("INSERT INTO SEEDBasket_Products ( uid_seller,product_type,eStatus,title_en,name,quant_type,bask_quant_min,bask_quant_max,item_price ) VALUES (1,'book','ACTIVE','How to Save Your Own Seeds, 6th edition','ssh6-en','ITEM-N',1,-1,15)" );
        $this->oApp->kfdb->Execute("INSERT INTO SEEDBasket_Products ( uid_seller,product_type,eStatus,title_en,name,quant_type,bask_quant_min,bask_quant_max,item_price ) VALUES (1,'membership','ACTIVE','Membership - One Year','mbr25','ITEM-1',1,1,25)" );

        $this->oApp->kfdb->Execute("INSERT INTO SEEDBasket_Purchases (fk_SEEDBasket_Buyers,fk_SEEDBasket_Products,n,f,eStatus) VALUES (1,1,0,123.45,'PAID')" );
        $this->oApp->kfdb->Execute("INSERT INTO SEEDBasket_Purchases (fk_SEEDBasket_Buyers,fk_SEEDBasket_Products,n,f,eStatus) VALUES (1,2,5,0,'PAID')" );
        $this->oApp->kfdb->Execute("INSERT INTO SEEDBasket_Purchases (fk_SEEDBasket_Buyers,fk_SEEDBasket_Products,n,f,eStatus) VALUES (1,3,1,0,'PAID')" );

        $this->oApp->kfdb->SetDebug(0);
    }

    function ShowProducts()
    {
        $s = "";

        $kCurrProd = 0;


// This code works, but it's SEEDBasket 1.0  (boo)

// The better 2.0 way is to unpack all the functionality out of SEEDBasketCore and put it into classes like SEEDBasket_Basket, SEEDBasket_Product, SEEDBasket_Purchase.
// 1. Look at the bottom of SEEDBasketCore for the initial implementations of these classes. You'll have to add a lot to them.
// 2. Make a method in SEEDBasketCore :: $oSB->GetProductList("uid_seller='1'") that returns an array of SEEDBasket_Product.
//    That method can use GetProductKFRC to find all the product records, then make a SEEDBasket_Product for each product and put it in an array.
// 3. Then below you foreach through that array and use oProduct->Draw() which does the same thing as oSB->DrawProduct().
//
// That's a nice way to separate the product stuff out of one big class (SEEDBasketCore) and besides now you can pass product objects around
// which you couldn't really do before.


        if( ($kfrcP = $this->oSB->oDB->GetProductKFRC("uid_seller='1'")) ) {
            while( $kfrcP->CursorFetch() ) {
                $kP = $kfrcP->Key();
                $bCurr = ($kCurrProd && $kfrcP->Key() == $kCurrProd);
                $sStyleCurr = $bCurr ? "border:2px solid blue;" : "";
                $s .= "<div class='well' style='padding:5px;margin:5px;$sStyleCurr' onclick='location.replace(\"?kP=$kP\")' ".($bCurr ? "style='border:1px solid #333'" : "").">"
                     .$this->oSB->DrawProduct( $kfrcP, $bCurr ? SEEDBasketProductHandler::DETAIL_ALL : SEEDBasketProductHandler::DETAIL_TINY, ['bUTF8'=>false] )
                     ."</div>";
            }
        }



        return( $s );
    }
}




function SEEDBasketManagerApp( SEEDAppConsole $oApp )
{
    $s = "";

    $oBM = new basketMan( $oApp );

    switch( SEEDInput_Str('cmd') ) {
        case 'createdb':
            $s = $oBM->CreateDB();
            break;
        case 'showproducts':
            $s = $oBM->ShowProducts();
            break;
        case 'showpurchases':
            break;
    }

    $s .= "<div>"
         ."<p><a href='?cmd=createdb'>Create/Re-create database tables</a></p>"
         ."<p><a href='?cmd=showproducts'>Show products</a></p>"
         ."<p><a href='?cmd=showproducts'>Show purchases in all baskets</a></p>"
         ."</div>";

    return( $s );
}