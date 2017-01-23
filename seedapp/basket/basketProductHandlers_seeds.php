<?php

/* Basket product handler for Seeds
 *
 * Copyright (c) 2016-2017 Seeds of Diversity Canada
 */
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
// *Year first listed* should do the same thing
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
    /***********************************************
        DETAIL_TINY     : species variety
        DETAIL_SUMMARY  : what you see in the seed directory
        DETAIL_ALL      : species + what you see in the seed directory
     */
    {
        include_once( SEEDCOMMON."sl/sed/sedCommonDraw.php" );
        $oSed = new SEDCommonDraw( $this->oSB->oDB->kfdb, $this->oSB->GetUID_SB(), "EN",
                                   $this->oSB->sess->CanRead("sed") ? "VIEW-MBR" : "VIEW-PUB" );

        $raPE = $this->oSB->oDB->GetProdExtraList( $kfrP->Key() );
        foreach( $this->raProdExtraKeys as $k ) {
            $kfrP->SetValue( $k, @$raPE[$k] );
        }

        switch( $eDetail ) {
            case SEEDBasketProductHandler::DETAIL_TINY:
                $s = $kfrP->Expand( "<p>[[species]] - [[variety]]</p>" );
                break;
            default:

                $kfrP->SetValue( 'type',   $kfrP->Value('species') );
                $kfrP->SetValue( 'mbr_id', $kfrP->Value('uid_seller') );

                $s = "";
                if( $eDetail == SEEDBasketProductHandler::DETAIL_ALL ) {
// msd uses class sed_seed for clicking, which is created in DrawSeedFromKFR. Therefore can't click on this heading
                    $s .= "<strong style='font-size:14pt'>".$kfrP->Value('species')."</strong><br/>";
                }
                $s .= $oSed->DrawSeedFromKFR( $kfrP, array( 'bNoSections'=>true ) );
                break;
        }
        return( $s );
    }


    function Purchase0( KFRecord $kfrP )
    {
        $s = "<div style='display:inline-block'>".$this->ProductDraw( $kfrP, SEEDBasketProductHandler::DETAIL_ALL )."</div>"
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

?>