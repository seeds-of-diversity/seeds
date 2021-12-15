<?php

/* SEEDBasketStore.php
 *
 * Copyright (c) 2021 Seeds of Diversity Canada
 *
 * Base implementation of a shopping cart
 */

include_once( SEEDCORE."SEEDBasket.php" );


class SEEDBasketStore
/********************
    Core of the store implementation
 */
{
    protected $oSB;

    protected function __construct( SEEDBasketCore $oSB )
    {
        $this->oSB = $oSB;
    }

    public function Log( $s )
    {
        $this->oSB->oApp->Log( 'SEEDBasketStore.log', $s );
    }
}

class SEEDBasketStore_Basket
/***************************
    Perform store operations on a basket
 */
{
    function __construct( SEEDBasket_Basket $oB )
    {

    }

    function OnConfirmed()
    {

    }

    function OnPayment()
    {

    }
}

