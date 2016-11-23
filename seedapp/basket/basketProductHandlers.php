<?php

/* Basket product handlers
 *
 * Copyright (c) 2016 Seeds of Diversity Canada
 */

include_once( SEEDCORE."SEEDBasket.php" );


class SEEDBasketProductHandler_Membership extends SEEDBasketProductHandler
{
    function __construct( SEEDBasketCore $oSB )
    {
        parent::__construct( $oSB );
    }

    function ProductDefine0( KeyFrameUIForm $oFormP )
    {
        $s = "<h3>Membership Definition Form</h3>";

        if( !$oFormP->GetKey() ) {
            // initialize values for new form
            $oFormP->SetValue( 'quant_type', "ITEM-1" );
        }

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

    function ProductDraw( KFRecord $kfrP, $bDetail )
    {
        $s = "<h4>".$kfrP->Value('title_en')."</h4>";

        if( $bDetail ) {
            $s .= $kfrP->Expand( "Name: [[name]]<br/>Price: [[item_price]]" );
        }
        return( $s );
    }
}

class SEEDBasketProductHandler_Book extends SEEDBasketProductHandler
{
    function __construct( SEEDBasketCore $oSB )
    {
        parent::__construct( $oSB );
    }

    function ProductDefine0( KeyFrameUIForm $oFormP )
    {
        if( $oFormP->GetKey() ) {
            return( "<P>This is the Book Form</P>" );
        } else {
            return( "<P>This is the Book Form for a NEW book</P>" );
        }
    }

    function ProductDraw( KFRecord $kfrP, $bDetail )
    {
        $s = "<h4>".$kfrP->Value('title_en')."</h4>";

        if( $bDetail ) {
            $s .= $kfrP->Expand( "Name: [[name]]<br/>Price: [[item_price]]" );
        }
        return( $s );
    }
}

?>