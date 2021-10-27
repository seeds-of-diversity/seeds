<?php

/* SEEDBasketUI.php
 *
 * Copyright (c) 2016-2021 Seeds of Diversity Canada
 *
 * UI widgets for building SEEDBasket apps
 */

include_once( "SEEDBasket.php" );


class SEEDBasketUI_BasketWidget
/******************************
 * Draw a basket in various ways
 *
 * eMode: Readonly       = customer readonly - show what's in the basket with no controls or status
 *        ReadonlyStatus = vendor readonly - no controls but show fulfilment status
 *        EditDelete     = customer store - purchases can be deleted via buttons
 *        EditAdd        = vendor editor - purchases can be added via a picklist
 *        EditAddDelete  = vendor editor - purchases can be removed, and added
 *        Fulfil         = vendor fulfilment - fulfilment controls
 */
{
    private $oSB;

    function __construct( SEEDBasketCore $oSB )
    {
        $this->oSB = $oSB;
    }

    function DrawBasketWidget( SEEDBasket_Basket $oB, string $eMode, array $raParms )
    /********************************************************************************
        raParms:
            uidSeller : int = only show products from this seller
                        [int,...] = from these sellers
                        -1 (default) = all sellers
     */
    {
        $bOk = false;
        $s = "";

$bFulfilControls = ($eMode == 'Fulfil');
$bShowStatus = ($eMode == 'ReadonlyStatus');

// TODO: require that the current user is allowed to edit the basket

        /* specify which sellers' products to show
         */
        if( ($raUidSellers = @$raParms['uidSeller']) ) {
            if( is_integer($raUidSellers) ) {
                $raUidSellers = $raUidSellers > 0 ? [$raUidSellers] : [];  // empty array means all sellers
            }
        }

        //$raPur = $oB->GetPurchasesInBasket();

        $raBContents = $oB->ComputeBasketContents();
        foreach( $raBContents['raSellers'] as $uidSeller => $raSeller ) {
            if( $raUidSellers && !in_array($uidSeller, $raUidSellers) )  continue;

            $s .= "<div style='margin-top:10px;font-weight:bold'>{$this->sellerName($uidSeller)} (total ".$this->oSB->dollar($raSeller['fSellerTotal']).")</div>";

            $s .= "<table class='sbfulfil_basket_table' style='text-align:right;width:100%'>"
                 ;//."<tr><td>&nbsp;</td><td valign='top' style='border-bottom:1px solid'>$&nbsp;{$raSeller['fSellerTotal']}</td></tr>";

            /* Show Purchases
             */
            foreach( $raSeller['raPur'] as $ra ) {
                $oPur = @$ra['oPur'];

                $sCol1 = "";      // first col is a fulfil button or fulfilment record
                $sCol2 = "";      // second col is an undo button if fulfilled and canfulfilundo and bFulfilControls
                if( $bFulfilControls || $bShowStatus ) {
                    // using [onclick=fn(kBP)] instead of [data-kPurchase='{$oPur->GetKey()}' class='doPurchaseFulfil']
                    // because inconvenient to reconnect event listener when basketDetail redrawn
                    $sFulfilButtonLabel = $sFulfilNote = "";
                    $raF = $oPur->GetFulfilControls();
                    $sFulfilButtonLabel = $raF['fulfilButtonLabel'];
                    $sFulfilStatusY = $raF['statusFulfilled'];
                    $sFulfilStatusN = $raF['statusNotFulfilled'];

                    // typically only one of these parameters is true
                    if( $bFulfilControls ) {
                        // col1 is the status if fulfilled, or a fulfillment button (or blank if not allowed)
                        // col2 is an undo button if fulfilled
                        $sCol1 = $oPur->IsFulfilled()
                                  ? $sFulfilStatusY
                                  : ($oPur->CanFulfil()
                                      ? "<button onclick='SoDBasketFulfilment.doPurchaseFulfil(\$(this),{$oPur->GetKey()})'>$sFulfilButtonLabel</button>"
                                      : "");
                        $sCol2 = $oPur->CanFulfilUndo()
                                        ? "<button onclick='SoDBasketFulfilment.doPurchaseFulfilUndo(\$(this),{$oPur->GetKey()})'>Undo</button>"
                                        : "";
                    }
                    if( $bShowStatus) {
                        // col1 is the status, col2 is blank
                        $sCol1 = $oPur->IsFulfilled() ? $sFulfilStatusY : "<div class='alert alert-warning' style='padding:3px;border-color:orange'>$sFulfilStatusN</div>";
                        $sCol2 = "";
                    }
                }

                $s .= "<tr><td valign='top' style='padding-right:5px'>{$oPur->GetDisplayName(['bFulfil'=>$bFulfilControls])}</td>"
                         ."<td valign='top'>{$oPur->GetPrice()}</td>"
                         .(($bFulfilControls || $bShowStatus) ? "<td valign='top' style='text-align:left'> $sCol1</td><td valign='top' style='text-align:left'> $sCol2</td>" : "")
                     ."</tr>";
            }

            /* Show Extra Items
             */
            foreach( $raSeller['raExtraItems'] as $ra ) {
                $s .= "<tr><td valign='top' style='padding-right:5px'>{$ra['sLabel']}</td>"
                         ."<td valign='top'>{$ra['fAmount']}</td>"
                     ."</tr>";
            }

            $s .= "</table>";
        }

        $bOk = true;

        done:
        return( [$bOk,$s] );
    }

    private function getAddableProducts( $raParms )
    {
        /* raParms defines which products can be added to the basket
         *      raKProduct    = [int, ...] of products addable to the basket
         *   or
         *      the conjunction of:
         *      raUidSeller   = [int, ...] of uid_seller of products addable to the basket
         *      raProductType = [string, ...] of product types available to add to the basket
         */


    }

    private function sellerName( $uidSeller )
    {
        return( "Seller #$uidSeller" );
    }

}
