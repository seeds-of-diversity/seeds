<?php

/* QServerBasket
 *
 * Copyright 2017-2019 Seeds of Diversity Canada
 *
 * SEEDBasket functionality
 */

include_once( "Q.php" );

class QServerBasket extends SEEDQ
{
    function __construct( SEEDAppConsole $oApp, $raConfig )
    {
        parent::__construct( $oApp, $raConfig );
    }

    function Cmd( $cmd, $parms )
    {
        $rQ = $this->GetEmptyRQ();

        // common inputs that may or may not be defined
        $kBP = intval(@$parms['kBP']);

        switch( $cmd ) {
            case "basketProdUnfill":
                $this->oQ->oApp->kfdb->Execute( "UPDATE seeds.SEEDBasket_BP SET eStatus='PAID' WHERE _key='$k'" );
                $rQ['bOk'] = true;
                break;

            case "basketProdCancel":
                $this->oQ->oApp->kfdb->Execute( "UPDATE seeds.SEEDBasket_BP SET eStatus='CANCELLED' WHERE _key='$k'" );
                $rQ['bOk'] = true;
                break;

            case "basketProdUncancel":
                $this->oQ->oApp->kfdb->Execute( "UPDATE seeds.SEEDBasket_BP SET eStatus='PAID' WHERE _key='$k'" );
                $rQ['bOk'] = true;
                break;

            case "basketPurchaseAccount":
                if( $kBP ) {
                    $this->oQ->oApp->kfdb->Execute( "UPDATE seeds.SEEDBasket_BP SET bAccountingDone=1 WHERE _key='$kBP'" );
                    $rQ['bOk'] = true;
                }
                break;
            case "basketPurchaseUnaccount":
                if( $kBP ) {
                    $this->oQ->oApp->kfdb->Execute( "UPDATE seeds.SEEDBasket_BP SET bAccountingDone=0 WHERE _key='$kBP'" );
                    $rQ['bOk'] = true;
                }
                break;
        }

        done:
        return( $rQ );
    }
}
