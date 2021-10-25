<?php

/* QServerBasket
 *
 * Copyright 2017-2020 Seeds of Diversity Canada
 *
 * SEEDBasket functionality
 */

include_once( "Q.php" );
include_once( SEEDCORE."SEEDBasketUI.php" );

class QServerBasket extends SEEDQ
{
    private $oSB;

    function __construct( SEEDAppConsole $oApp, $raConfig )
    {
        parent::__construct( $oApp, $raConfig );
        $this->oSB = new SEEDBasketCore( null, null, $oApp, SEEDBasketProducts_SoD::$raProductTypes, ['sbdb'=>'seeds1'] );
    }

    function Cmd( $cmd, $parms )
    {
        $rQ = self::GetEmptyRQ();

        // common inputs that may or may not be defined
        $k = intval(@$parms['k']);
        $kBP = intval(@$parms['kBP']);

        $rQ['bHandled'] = true;
        switch( $cmd ) {
            case 'sb--basketStatus':
                /* kBasket, eStatus, sNote : set the eStatus for this basket and record optional sNote
                 */
                if( ($eStatus = SEEDCore_ArraySmartVal( $parms, 'eStatus', ['','Open','Paid','Filled','Cancelled'])) &&
                    ($kBasket = intval(@$parms['kBasket'])) &&
// put this in SEEDBasket_Basket::StatusChange()
                    ($kfrB = $this->oSB->oDB->GetBasketKFR($kBasket))
/*&& $this->permsBasket($kfrB)*/   // test permission and ownership of this basket (buyers can confirm and cancel, system can set paid/filled, vendors only fill purchases)
                ) {
                    $kfrB->SetValue( 'eStatus', $eStatus );

                    // record the status change in the notes, with optional sNote
                    $kfrB->SetValue( 'notes', $kfrB->Value('notes')
                                             ."[".$this->oApp->sess->GetName()." at ".date( "Y-M-d h:i")."] Changed status to $eStatus. {$sNote}\n" );

                    $rQ['bOk'] = $kfrB->PutDBRow();
                    $rQ['sOut'] = "Changed eStatus to $eStatus";

// TODO: remove this when mbr_order.php shows eStatus from basket
                    $this->oApp->kfdb->Execute( "UPDATE {$this->oApp->DBName('seeds1')}.mbr_order_pending "
                                               ."SET eStatus='$eStatus' "
                                               ."WHERE kBasket='$kBasket'" );

// TODO: store this note in SEEDBasket_Baskets instead
                    $s1 = $this->oApp->kfdb->Query1( "SELECT notes FROM {$this->oApp->DBName('seeds1')}.mbr_order_pending WHERE kBasket='$kBasket'" )
                         ."[".$this->oApp->sess->GetName()." at ".date( "Y-M-d h:i")."] Changed status to $eStatus. "
                         .@$parms['sNote']."\n";
                    $this->oApp->kfdb->Execute( "UPDATE {$this->oApp->DBName('seeds1')}.mbr_order_pending SET notes='".addslashes($s1)."' WHERE kBasket='$kBasket'" );
                }
                break;

            case 'sb--addNote':
                /* kBasket, sNote : record sNote in basket
                 */
                if( ($kBasket = intval(@$parms['kBasket'])) &&
                    ($sNote = @$parms['sNote']) &&
// put this in SEEDBasket_Basket::AddNote()
                    ($kfrB = $this->oSB->oDB->GetBasketKFR($kBasket))
/*&& $this->permsBasket($kfrB)*/   // test permission and ownership of this basket
                ) {
                    $kfrB->SetValue( 'notes', $kfrB->Value('notes')
                                             ."[".$this->oApp->sess->GetName()." at ".date( "Y-M-d h:i")."] {$sNote}\n" );
                    $rQ['bOk'] = $kfrB->PutDBRow();

// mbr_order: remove this when mbr_order.php shows notes from basket
                    $s1 = $this->oApp->kfdb->Query1( "SELECT notes FROM {$this->oApp->DBName('seeds1')}.mbr_order_pending WHERE kBasket='$kBasket'" )
                         ."[".$this->oApp->sess->GetName()." at ".date( "Y-M-d h:i")."] "
                         .$parms['sNote']."\n";
                    $this->oApp->kfdb->Execute( "UPDATE {$this->oApp->DBName('seeds1')}.mbr_order_pending SET notes='".addslashes($s1)."' where kBasket='$kBasket'" );
                }
                break;


            case 'sb--purchaseFulfil':
                // $k is SEEDBasket_BP._key in this case
                if( $k && ($oPur = $this->oSB->GetPurchaseObj( $k ))
&& $this->permsfulfil( $oPur )   // test permissions and ownership of this purchase
                ) {
                    switch( $oPur->Fulfil() ) { // checks CanFulfil() and IsFulfilled() internally
                        case SEEDBasket_Purchase::FULFIL_RESULT_SUCCESS:
                            $rQ['bOk'] = true;
                            break;
                        case SEEDBasket_Purchase::FULFIL_RESULT_ALREADY_FULFILLED:
                            $rQ['sErr'] = "Purchase already fulfilled";
                            break;
                    }
                }
                break;

            case 'sb--purchaseFulfilUndo':
                // $k is SEEDBasket_BP._key in this case
                if( $k && ($oPur = $this->oSB->GetPurchaseObj( $k ))
&& $this->permsfulfil( $oPur )   // test permissions and ownership of this purchase
                ) {
                    switch( $oPur->FulfilUndo() ) { // checks CanFulfilUndo() and IsFulfilled() internally
                        case SEEDBasket_Purchase::FULFILUNDO_RESULT_SUCCESS:
                            $rQ['bOk'] = true;
                            break;
                        case SEEDBasket_Purchase::FULFILUNDO_RESULT_NOT_FULFILLED:
                            $rQ['sErr'] = "Purchase not fulfilled, cannot undo";
                            break;
                    }
                }
                break;

            case 'sbfulfil--drawBasketEditor':
                /* Draw an editor for the given basket
                 *  k   = kBasket
                 *  raConfig['fulfil_uidSeller'] = int or [int,...] of uidSellers of products available to add to the basket
                 *  raConfig['fulfil_product_type'] = string or [string,...] of product types available to add to the basket
                 */
                list($rQ['bOk'],$rQ['sOut'],$oBasketDummy) = (new SEEDBasketUI_BasketWidget($this->oSB))
                        ->DrawBasketWidget($k, 'EditAddDelete', ['uidSeller'=>intval(@$this->raConfig['fulfil_uidSeller'])]);
                break;

            case "basketProdUnfill":    // do not use
                if( $kBP ) {
                    $this->oApp->kfdb->Execute( "UPDATE seeds_1.SEEDBasket_BP SET eStatus='PAID' WHERE _key='$kBP'" );
                    $rQ['bOk'] = true;
                }
                break;

            case "basketProdCancel":    // do not use
                if( $kBP ) {
                    $this->oApp->kfdb->Execute( "UPDATE seeds_1.SEEDBasket_BP SET eStatus='CANCELLED' WHERE _key='$k'" );
                    $rQ['bOk'] = true;
                }
                break;

            case "basketProdUncancel":    // do not use
                if( $kBP ) {
                    $this->oApp->kfdb->Execute( "UPDATE seeds_1.SEEDBasket_BP SET eStatus='PAID' WHERE _key='$k'" );
                    $rQ['bOk'] = true;
                }
                break;

            case "basketPurchaseAccount":    // do not use
                if( $kBP ) {
                    $this->oApp->kfdb->Execute( "UPDATE seeds_1.SEEDBasket_BP SET flagsWorkflow=flagsWorkflow | 1 WHERE _key='$kBP'" );
                    $rQ['bOk'] = true;
                }
                break;
            case "basketPurchaseUnaccount":    // do not use
                if( $kBP ) {
                    $this->oApp->kfdb->Execute( "UPDATE seeds_1.SEEDBasket_BP SET flagsWorkflow=flagsWorkflow & ~1 WHERE _key='$kBP'" );
                    $rQ['bOk'] = true;
                }
                break;

            default:
                $rQ['bHandled'] = false;
        }

        done:
        return( $rQ );
    }

    private function permsfulfil( SEEDBasket_Purchase $oPur )
    {
        return( true );
    }
}
