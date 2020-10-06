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
        $this->oSB = new SEEDBasketCore( $oApp->kfdb, $oApp->sess, $oApp, SEEDBasketProducts_SoD::$raProductTypes,
// SBC should use oApp instead
            ['logdir'=>$oApp->logdir, 'db'=>'seeds'] );
    }

    function Cmd( $cmd, $parms )
    {
        $rQ = $this->GetEmptyRQ();

        // common inputs that may or may not be defined
        $kBP = intval(@$parms['kBP']);

        switch( $cmd ) {
            case "basketProdUnfill":
                if( $kBP ) {
                    $this->oApp->kfdb->Execute( "UPDATE seeds_1.SEEDBasket_BP SET eStatus='PAID' WHERE _key='$kBP'" );
                    $rQ['bOk'] = true;
                }
                break;

            case "basketProdCancel":
                if( $kBP ) {
                    $this->oApp->kfdb->Execute( "UPDATE seeds_1.SEEDBasket_BP SET eStatus='CANCELLED' WHERE _key='$k'" );
                    $rQ['bOk'] = true;
                }
                break;

            case "basketProdUncancel":
                if( $kBP ) {
                    $this->oApp->kfdb->Execute( "UPDATE seeds_1.SEEDBasket_BP SET eStatus='PAID' WHERE _key='$k'" );
                    $rQ['bOk'] = true;
                }
                break;

            case "basketPurchaseAccount":
                if( $kBP ) {
                    $this->oApp->kfdb->Execute( "UPDATE seeds_1.SEEDBasket_BP SET flagsWorkflow=flagsWorkflow | 1 WHERE _key='$kBP'" );
                    $rQ['bOk'] = true;
                }
                break;
            case "basketPurchaseUnaccount":
                if( $kBP ) {
                    $this->oApp->kfdb->Execute( "UPDATE seeds_1.SEEDBasket_BP SET flagsWorkflow=flagsWorkflow & ~1 WHERE _key='$kBP'" );
                    $rQ['bOk'] = true;
                }
                break;
        }

        done:
        return( $rQ );
    }
}
