<?php

/* SEEDBasketUI.php
 *
 * Copyright (c) 2016-2021 Seeds of Diversity Canada
 *
 * UI widgets for building SEEDBasket apps
 */

include_once( "SEEDBasket.php" );


class SEEDBasketFulfilUI
/***********************
 */
{
    private $oSB;

    function __construct( SEEDBasketCore $oSB )
    {
        $this->oSB = $oSB;
    }

    /* Draw an edit widget for the given basket.
     *      kProduct     = int or [int, ...] of products available to add to the basket
     *   or
     *      uidSeller    = int or [int, ...] of uidSeller of products available to add to the basket
     *      product_type = string or [string, ...] of product types available to add to the basket
     */
    function DrawBasketEditor( int $kB, array $raParms )
    {
        $bOk = false;
        $s = "";

// TODO: require that the current user is allowed to edit the basket
        if( !($oB = new SEEDBasket_Basket($this->oSB, $kB)) )  goto done;

        $s = "Good so far from fulfiller {$raParms['uidSeller']}";

        $bOk = true;

        done:
        return( [$bOk,$s] );
    }

}