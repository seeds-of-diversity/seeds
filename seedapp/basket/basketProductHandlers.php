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

    function ProductDefine0( KFRecord $kfrP = null )        // the default = null allows a null to be passed here without error from the type hinting
    {
        if( $kfrP ) {
            return( "<P>This is the Membership Form</P>" );
        } else {
            return( "<P>This is the Membership Form for a NEW product</P>" );
        }
    }

    function ProductDraw( KFRecord $kfrP, $bDetail )
    {
        $s = "<h4>".$kfrP->Value('title')."</h4>";

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

    function ProductDefine0( KFRecord $kfrP = null )        // the default = null allows a null to be passed here without error from the type hinting
    {
        if( $kfrP ) {
            return( "<P>This is the Book Form</P>" );
        } else {
            return( "<P>This is the Book Form for a NEW book</P>" );
        }
    }

    function ProductDraw( KFRecord $kfrP, $bDetail )
    {
        $s = "<h4>".$kfrP->Value('title')."</h4>";

        if( $bDetail ) {
            $s .= $kfrP->Expand( "Name: [[name]]<br/>Price: [[item_price]]" );
        }
        return( $s );
    }
}

?>