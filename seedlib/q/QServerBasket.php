<?php

/* QServerBasket
 *
 * Copyright 2017-2020 Seeds of Diversity Canada
 *
 * SEEDBasket functionality
 */

include_once( "Q.php" );

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

        switch( $cmd ) {
            case 'sb--purchaseFulfil':
                // $k is SEEDBasket_BP._key in this case
                if( $k && ($oPur = $this->oSB->GetPurchaseObj( $k ))
&& $this->canfulfil( $oPur )   // test permissions and ownership of this purchase
                ) {
                    switch( $oPur->Fulfil() ) { // checks IsFulfilled() internally
                        case SEEDBasket_Purchase::FULFIL_RESULT_SUCCESS:
                            $rQ['bOk'] = true;
                            break;
                        case SEEDBasket_Purchase::FULFIL_RESULT_ALREADY_FULFILLED:
                            $rQ['sErr'] = "Purchase already fulfilled";
                            break;
                    }
                }
                $rQ['bHandled'] = true;
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
        }

        done:
        return( $rQ );
    }

    private function canfulfil( SEEDBasket_Purchase $oPur )
    {
        return( true );
    }
}
