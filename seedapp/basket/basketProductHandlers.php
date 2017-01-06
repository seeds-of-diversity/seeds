<?php

/* Basket product handlers
 *
 * Copyright (c) 2016 Seeds of Diversity Canada
 */

include_once( SEEDCORE."SEEDBasket.php" );

class SEEDBasketProducts_SoD
/***************************
    Seeds of Diversity's products
 */
{
    // This is a super-set of SEEDBasketCore's raProductHandlers; it can be passed directly to that constructor
    // and also used as a look-up for other things
    static public $raProductTypes = array(
            'membership' => array( 'label'=>'Membership',
                                   'classname'=>'SEEDBasketProductHandler_Membership',
                                   'forceFlds' => array('quant_type'=>'ITEM-1') ),
            'donation'   => array( 'label'=>'Donation',
                                   'classname'=>'SEEDBasketProductHandler_Donation',
                                   'forceFlds' => array('quant_type'=>'MONEY') ),
            'book'       => array( 'label'=>'Publication',
                                   'classname'=>'SEEDBasketProductHandler_Book',
                                   'forceFlds' => array('quant_type'=>'ITEM-N') ),
            'misc'       => array( 'label'=>'Miscellaneous Payment',
                                   'classname'=>'SEEDBasketProductHandler_Misc',
                                   'forceFlds' => array('quant_type'=>'MONEY') ),
            'seeds'      => array( 'label'=>'Seeds',
                                   'classname'=>'SEEDBasketProductHandler_Seeds',
                                   'forceFlds' => array('quant_type'=>'ITEM-N') ),
            'event'      => array( 'label'=>'Event',
                                   'classname'=>'SEEDBasketProductHandler_Event',
                                   'forceFlds' => array('quant_type'=>'ITEM-1') ),
    );
}



class SEEDBasketProductHandler_Membership extends SEEDBasketProductHandler
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }

    function ProductDefine0( KeyFrameUIForm $oFormP )
    {
        $s = "<h3>Membership Definition Form</h3>";

        $s .= $oFormP->HiddenKey()
             ."<table>"
             .$oFormP->ExpandForm(
                     "||| Seller        || [[text:uid_seller|readonly]]"
                    ."||| Product type  || [[text:product_type|readonly]]"
                    ."||| Quantity type || [[text:quant_type|readonly]]"
                    ."||| Status        || ".$oFormP->Select2( 'eStatus', array('ACTIVE'=>'ACTIVE','INACTIVE'=>'INACTIVE','DELETED'=>'DELETED') )
                    ."<br/><br/>"
                    ."||| Title EN      || [[text:title_en | size:40]]"
                    ."||| Title FR      || [[text:title_fr | size:40]]"
                    ."||| Name          || [[text:name]]"
                    ."<br/><br/>"
                    ."||| Price         || [[text:item_price]]"
                    ."||| Price U.S.    || [[text:item_price_US]]"
                     )
             ."</table> ";

        return( $s );
    }

    function ProductDefine1( KeyFrameDataStore $oDS )
    {
        return( parent::ProductDefine1( $oDS ) );
    }

    function ProductDraw( KFRecord $kfrP, $eDetail )
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

    function Purchase2( KFRecord $kfrP, $raPurchaseParms )
    /*****************************************************
        Add a membership to the basket. Only one membership is allowed per basket, so remove any others.
     */
    {
        $raBPxP = $this->oSB->oDB->GetPurchasesList( $this->oSB->GetBasketKey() );
        foreach( $raBPxP as $ra ) {
            if( $ra['P_product_type'] == 'membership' ) {
                $this->oSB->Cmd( 'removeFromBasket', array('kBP'=> $ra['_key']) );
            }
        }

        return( parent::Purchase2( $kfrP, $raPurchaseParms ) );
    }
}

class SEEDBasketProductHandler_Donation extends SEEDBasketProductHandler
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }

    function ProductDefine0( KeyFrameUIForm $oFormP )
    {
        $s = "<h3>Donation Definition Form</h3>";

        $s .= $oFormP->HiddenKey()
             ."<table>"
             .$oFormP->ExpandForm(
                     "||| Seller        || [[text:uid_seller|readonly]]"
                    ."||| Product type  || [[text:product_type|readonly]]"
                    ."||| Quantity type || [[text:quant_type|readonly]]"
                    ."||| Status        || ".$oFormP->Select2( 'eStatus', array('ACTIVE'=>'ACTIVE','INACTIVE'=>'INACTIVE','DELETED'=>'DELETED') )
                    ."<br/><br/>"
                    ."||| Title EN      || [[text:title_en]]"
                    ."||| Title FR      || [[text:title_fr]]"
                    ."||| Name          || [[text:name]]"
                     )
             ."</table> ";

        return( $s );
    }
}

class SEEDBasketProductHandler_Book extends SEEDBasketProductHandler
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }

    function ProductDefine0( KeyFrameUIForm $oFormP )
    {
        $s = "<h3>Publications Product Form</h3>";

        $s .= $oFormP->HiddenKey()
             ."<table>"
             .$oFormP->ExpandForm(
                     "||| Seller        || [[text:uid_seller|readonly]]"
                    ."||| Product type  || [[text:product_type|readonly]]"
                    ."||| Quantity type || [[text:quant_type|readonly]]"
                    ."||| Status        || ".$oFormP->Select2( 'eStatus', array('ACTIVE'=>'ACTIVE','INACTIVE'=>'INACTIVE','DELETED'=>'DELETED') )
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
                     )
             ."</table> ";

        return( $s );
    }

    function ProductDraw( KFRecord $kfrP, $eDetail )
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

    function Purchase0( KFRecord $kfrP )
    /***********************************
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

class SEEDBasketProductHandler_Misc extends SEEDBasketProductHandler
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }

    function ProductDefine0( KeyFrameUIForm $oFormP )
    {
        $s = "<h3>Misc Payment Definition Form</h3>";

        $s .= $oFormP->HiddenKey()
             ."<table>"
             .$oFormP->ExpandForm(
                     "||| Seller        || [[text:uid_seller|readonly]]"
                    ."||| Product type  || [[text:product_type|readonly]]"
                    ."||| Quantity type || [[text:quant_type|readonly]]"
                    ."||| Status        || ".$oFormP->Select2( 'eStatus', array('ACTIVE'=>'ACTIVE','INACTIVE'=>'INACTIVE','DELETED'=>'DELETED') )
                    ."<br/><br/>"
                    ."||| Title EN      || [[text:title_en]]"
                    ."||| Title FR      || [[text:title_fr]]"
                    ."||| Name          || [[text:name]]"
                     )
             ."</table> ";

        return( $s );
    }
}

class SEEDBasketProductHandler_Seeds extends SEEDBasketProductHandler
{
    private $raProdExtraKeys = array( 'category', 'species', 'variety', 'bot_name', 'days_maturity', 'quantity', 'origin', 'description' );

    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }

    function ProductDefine0( KeyFrameUIForm $oFormP )
    /************************************************
        Draw a form to edit the given product.
        If _key==0 draw a New product form.
     */
    {
        $s = "";

        if( ($kP = $oFormP->GetKey()) ) {
            // this is not a new product, so fetch any ProdExtra
            $raExtra = $this->oSB->oDB->GetProdExtraList( $kP );
            foreach( $this->raProdExtraKeys as $k ) {
                $oFormP->SetValue( $k, @$raExtra[$k] );
            }
        }

        $oKForm = $oFormP;

        if( !$oKForm->GetKey() ) {
// mbr_id shouldn't be in the form, or propagated, for security when members are editing

            //$oKForm->SetValue( 'mbr_id', $this->kGrowerActive );
            //$oKForm->SetValue( "year_1st_listed", $this->currentYear );
            //$oKForm->SetValue( "type", $this->sess->VarGet('p_seedType') );
        }

        $s = "<h3>".($oKForm->GetKey() ? ("Edit: ".$oKForm->Value('type')." - ".$oKForm->ValueEnt('variety')) : "New Seed Offer")."</h3>";

        $sMbrCode = $oKForm->kfrel->kfdb->Query1("SELECT mbr_code FROM seeds.sed_curr_growers WHERE mbr_id=".$oKForm->oDS->Value('uid_seller') );

        $nSize = 30;
        $raTxtParms = array('size'=>$nSize);
        $txtParms = "size:30";
        $s .= "<table border='0'>"
             .$oFormP->ExpandForm(
                     "||| || <b>$sMbrCode</b> "
                    ."||| <label>Year first listed</label> || [[text:year_1st_listed|readonly]]"
                    ."||| <label>Category</label> || "
// get this array from SEDCommonDraw OR fetch the value automatically from sl_species and put it in Misc if we don't know
                            .$oKForm->Select2( "category", array( "VEGETABLES" => "VEGETABLES",
                                                                  "FLOWERS AND WILDFLOWERS" => "FLOWERS AND WILDFLOWERS",
                                                                  "FRUIT"=>"FRUIT",
                                                                  "GRAIN"=>"GRAIN",
                                                                  "HERBS AND MEDICINALS"=>"HERBS AND MEDICINALS",
                                                                  "MISC"=>"MISC",
                                                                  "TREES AND SHRUBS"=>"TREES AND SHRUBS" ) )
                    ."||| <label>Species</label> || [[text:species|$txtParms]]"
                    ."||| <label>Variety</label> || [[text:variety|$txtParms]]"
                    ."||| <label>Botanical</label> || [[text:bot_name|$txtParms]]"
                    ."||| <label>Days to maturity</label> || [[text:days_maturity|$txtParms]]"
                    ."||| <label>Quantity</label> || "
                            .$oFormP->Select2( "quantity", array( "I have enough to share with any member"=>"",
                                                                  "Low Quantity: offering to grower members only"=>"LQ",
                                                                  "Rare Variety: offering to members who will re-offer if possible"=>"PR"
                            ))
                    ."||| <label>Origin</label> || [[text:origin|$txtParms]]"
                    ."||| <label>Description</label> || "
                            .$oFormP->TextArea( 'description', "", 35, 8, array( 'attrs'=>"wrap='soft'") )

             )
             ."</TABLE>"
             ."<BR><INPUT type=submit value='Save'/>"

                     //             .$this->oSLE->ExpandTags( $oKForm->oDS->Key(), "<P style='text-align:center'><A HREF='{$_SERVER['PHP_SELF']}?[[LinkParmScroll]]'>Close Form</A></P> " );
;

        $s .= $oFormP->HiddenKey()
             ."<table>"
             .$oFormP->ExpandForm(
                     "||| Seller        || [[text:uid_seller|readonly]]"
                    ."||| Product type  || [[text:product_type|readonly]]"
                    ."||| Quantity type || [[text:quant_type|readonly]]"
                    ."||| Status       || ".$oFormP->Select2( 'eStatus', array('ACTIVE'=>'ACTIVE','INACTIVE'=>'INACTIVE','DELETED'=>'DELETED') )
                    ."<br/><br/>"
                    ."||| Title EN  || [[text:title_en]]"
                    ."||| Title FR  || [[text:title_fr]]"
                    ."||| Name      || [[text:name]]"
                    ."||| Images    || [[text:img]]"
                    ."<br/><br/>"
                    ."||| Category  || [[text:v_t1]]"
                    ."||| Species   || [[text:v_t2]]"
                    ."||| Variety   || [[text:v_t3]]"
                     ."<br/><br/>"
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
        This is called as the Product's KFUIForm::Update().PreStore function.
        Validate the product record
     */
    {
        $bOk = true;

        // Force some requirements
        $oDS->SetValue( 'quant_type', 'ITEM-1' );

        return( $bOk );
    }

    function ProductDefine2PostStore( KFRecord $kfrP, KeyFrameUIForm $oFormP )
    /*************************************************************************
        This is called after a successful Update().Store

        Write the seed prodExtra data. This has to be done after the Store because on a new product we don't
        know the fk_SEEDBasket_Products key until PostStore().
     */
    {
        if( $kfrP->Key() ) {
            // Write the prodExtra data from the ProductDefine0 form
            foreach( $this->raProdExtraKeys as $k ) {
                $v = $kfrP->Value($k);
                $this->oSB->oDB->SetProdExtra( $kfrP->Key(), $k, $v );
            }
        }
    }

    function ProductDraw( KFRecord $kfrP, $eDetail )
    {
        $raPE = $this->oSB->oDB->GetProdExtraList( $kfrP->Key() );
        foreach( $this->raProdExtraKeys as $k ) {
            $kfrP->SetValue( $k, @$raPE[$k] );
        }

        switch( $eDetail ) {
            case SEEDBasketProductHandler::DETAIL_TINY:
                $s = $kfrP->Expand( "<p>[[species]] - [[variety]]</p>" );
                break;
            default:
                include_once( SEEDCOMMON."sl/sed/sedCommonDraw.php" );
                $oSed = new SEDCommonDraw( $kfrP->kfrel->kfdb, $this->oSB->sess->GetUID(), "EN", "EDIT" );

                $kfrP->SetValue( 'type',   $kfrP->Value('species') );
                $kfrP->SetValue( 'mbr_id', $kfrP->Value('uid_seller') );

                $s = "<strong>".$kfrP->Value('species')."</strong><br/>"
                    .$oSed->DrawSeedFromKFR( $kfrP, array( 'bNoSections'=>true ) );
        }
        return( $s );
    }


    function Purchase0( KFRecord $kfrP )
    {
        $s = "<div style='display:inline-block'>".$this->ProductDraw( $kfrP, SEEDBasketProductHandler::DETAIL_SUMMARY )."</div>"
            ."&nbsp;&nbsp;<input type='text' name='sb_n' value='1'/>"
            ."<input type='hidden' name='sb_product' value='".$kfrP->Key()."'/>";

        return( $s );
    }

    function PurchaseDraw( KFRecord $kfrBPxP, $bDetail = false )
    /***********************************************************
        Draw a product in a basket, in more or less detail.
     */
    {
        $kfrP = $this->oSB->oDB->GetProduct( $kfrBPxP->Value('P__key') );
        $s = $this->ProductDraw( $kfrP, SEEDBasketProductHandler::DETAIL_TINY );

        if( $kfrBPxP->Value('quant_type') == 'ITEM_N' && ($n = $kfrBPxP->Value('n')) > 1 ) {
            $s .= " ($n @ ".$this->oSB->dollar($this->oSB->priceFromRange($kfrBPxP->Value('item_price'), $n)).")";
        }

        return( $s );
    }
}

class SEEDBasketProductHandler_Event extends SEEDBasketProductHandler
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }
}

?>