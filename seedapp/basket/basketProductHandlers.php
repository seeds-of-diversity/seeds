<?php

/* Basket product handlers
 *
 * Copyright (c) 2016-2020 Seeds of Diversity Canada
 */

include_once( SEEDCORE."SEEDBasket.php" );
include_once( SEEDLIB."sl/sldb.php" );      // for donation.seed-adoption

class SEEDBasketProducts_SoD
/***************************
    Seeds of Diversity's products
 */
{
    // This is a super-set of SEEDBasketCore's raProductHandlers; it can be passed directly to that constructor
    // and also used as a look-up for other things
    static public $raProductTypes = [
            'membership' => [ 'label'=>'Membership',
                              'classname'=>'SEEDBasketProductHandler_Membership',
                              'forceFlds' => ['quant_type'=>'ITEM-1'] ],
            'donation'   => [ 'label'=>'Donation',
                              'classname'=>'SEEDBasketProductHandler_Donation',
                              'forceFlds' => ['quant_type'=>'MONEY'] ],
            'book'       => [ 'label'=>'Publication',
                              'classname'=>'SEEDBasketProductHandler_Book',
                              'forceFlds' => ['quant_type'=>'ITEM-N'] ],
            'misc'       => [ 'label'=>'Miscellaneous Payment',
                              'classname'=>'SEEDBasketProductHandler_Misc',
                              'forceFlds' => ['quant_type'=>'MONEY'] ],
            'seeds'      => [ 'label'=>'Seeds',
                              'classname'=>'SEEDBasketProductHandler_Seeds',
                              'forceFlds' => ['quant_type'=>'ITEM-N'] ],
            'event'      => [ 'label'=>'Event',
                              'classname'=>'SEEDBasketProductHandler_Event',
                              'forceFlds' => ['quant_type'=>'ITEM-1'] ],
            'special1'   => [ 'label'=>'Special Single Item',
                              'classname'=>'SEEDBasketProductHandler_Special1',
                              'forceFlds' => ['quant_type'=>'ITEM-1'] ],
    ];
}


class SEEDBasketCoreSoD extends SEEDBasketCore
/*********************************************
    Construct the SoD SEEDBasketCore
 */
{
    function __construct( SEEDAppSessionAccount $oApp )
    {
        parent::__construct( null, null, $oApp, SEEDBasketProducts_SoD::$raProductTypes, ['sbdb'=>'seeds1'] );
    }
}



class SEEDBasketProductHandler_Membership extends SEEDBasketProductHandler_Item1
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }

    function ProductDefine0( KeyframeForm $oFormP )
    {
        return( parent::ProductDefine0_Item1( $oFormP, "Membership" ) );
    }

    function Purchase2( KeyframeRecord $kfrP, $raPurchaseParms )
    /***********************************************************
        Add a membership to the basket. Only one membership is allowed per basket, so remove any others.
     */
    {
        $raBPxP = $this->oSB->oDB->GetPurchasesList( $this->oSB->GetBasketKey() );
        foreach( $raBPxP as $ra ) {
            if( $ra['P_product_type'] == 'membership' ) {
                $this->oSB->Cmd( 'removeFromBasket', array('kBP'=> $ra['_key']) );
            }
        }

        return( parent::Purchase2( $kfrP, $raPurchaseParms ) );
    }
}

class SEEDBasket_Purchase_membership extends SEEDBasket_Purchase
{
    function __construct( SEEDBasketCore $oSB, int $kP, array $raParms = [] )
    {
        parent::__construct( $oSB, $kP, $raParms );
    }

    function GetFulfilControls()
    /***************************
        Return an array of labels for the fulfilment controls for this purchase
     */
    {
        return( ['fulfilButtonLabel' => "Record today",
                 'statusFulfilled' => "recorded {$this->GetExtra('dMailed')}",
                 'statusNotFulfilled' => "not recorded" ] );
    }

    /**************************************
        A membership is considered fulfilled when WORKFLOW_FLAG_MAILED.
        In principle this refers to the email notification.
Should there be a separate record for WORKFLOW_FLAG_MAILED for printed MSD?
        uid_buyer cannot be zero.
        The current date is stored in Purchase::sExtra so we can remember when the membership was recorded (and msd mailed)
        but this is nonessential to the workflow.
     */
    function IsFulfilled()
    {
        return( $this->GetWorkflowFlag(self::WORKFLOW_FLAG_MAILED) !== 0 );
    }

    function CanFulfil()
    {
        return( $this->_canFulfilOrUndo() && $this->GetBasketObj()->GetBuyer() && !$this->IsFulfilled() );
    }

    function Fulfil()
    {
        $ret = self::FULFIL_RESULT_FAILED;

        // check if fulfilment is allowed
        if( !$this->CanFulfil() ) goto done;

        // check if already fulfilled
        if( $this->IsFulfilled() ) {
            $ret = self::FULFIL_RESULT_ALREADY_FULFILLED;
            goto done;
        }

        $this->SetWorkflowFlag( self::WORKFLOW_FLAG_MAILED );
        $this->SetExtra( 'dMailed', date("Y-m-d") );
        $this->SaveRecord();

        $ret = self::FULFIL_RESULT_SUCCESS;

        done:
        return( $ret );
    }

    function CanFulfilUndo()
    {
        return( $this->_canFulfilOrUndo() && $this->IsFulfilled() );    // there is no limitation on when you can undo the mailing
    }

    function FulfilUndo()
    {
        $ret = self::FULFILUNDO_RESULT_FAILED;

        // check if fulfilled
        if( !$this->IsFulfilled() ) {
            $ret = self::FULFILUNDO_RESULT_NOT_FULFILLED;
            goto done;
        }

        // check if fulfil undo is allowed
        if( !$this->CanFulfilUndo() ) goto done;

        // clear fulfilment
        $this->UnsetWorkflowFlag( self::WORKFLOW_FLAG_MAILED );
        $this->SaveRecord();

        $ret = self::FULFILUNDO_RESULT_SUCCESS;

        done:
        return( $ret );
    }
}


class SEEDBasketProductHandler_Donation extends SEEDBasketProductHandler_MONEY
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }

    function ProductDefine0( KeyFrameForm $oFormP )
    {
        return( parent::ProductDefine0_MONEY( $oFormP, "Donation" ) );
    }

    function PurchaseDraw( KeyframeRecord $kfrBPxP, $raParms = [] )
    {
        $s = parent::PurchaseDraw( $kfrBPxP, $raParms )
            .(($kRef = $kfrBPxP->Value('kRef')) ? " ($kRef)" : " (NOT RECORDED)");      // show the mbr_donations _key if it is set

        return( $s );
    }

    function PurchaseIsFulfilled( SEEDBasket_Purchase $oPurchase )
    {
    }

    function PurchaseFulfil( SEEDBasket_Purchase $oPurchase )
    {

    }

    function GetPurchaseFromKDonation( $kDonation )
    /**********************************************
        kDonation is seeds2.mbr_donations._key; return seeds1.SEEDBasket_Purchase._key
     */
    {
        $oPur = null;
        if( ($kfrPur = $this->oSB->oDB->GetKFRCond('PURxP', "kRef='$kDonation' AND P.product_type='donation'")) ) {



// use kfr



            $oPur = new SEEDBasket_Purchase_donation( $this->oSB, $kfrPur->Key() );
        }
        return( $oPur );
    }
}

class SEEDBasket_Purchase_donation extends SEEDBasket_Purchase
{
    function __construct( SEEDBasketCore $oSB, int $kP, array $raParms = [] )
    {
        parent::__construct( $oSB, $kP, $raParms );
    }

    function GetDisplayName( $raParms )
    {
        $s = parent::GetDisplayName( $raParms );
        if( @$raParms['bFulfil'] ) {
            $s .= (($kRef = $this->GetKRef()) ? " ($kRef)" : " (NOT RECORDED)");      // show the mbr_donations _key if it is set
        }

        return( $s );
    }

    function GetFulfilControls()
    /***************************
        Return an array of labels for the fulfilment controls for this purchase
     */
    {
        return( ['fulfilButtonLabel' => "Accept donation",
                 'statusFulfilled' => "recorded {$this->GetExtra('dMailed')}",
                 'statusNotFulfilled' => "not recorded" ] );
    }


    /**************************************
        A donation is considered fulfilled when Basket::uid_buyer is set and Purchase::kRef points to an mbr_donation.
        The fulfilment system cannot create an mbr_donation until the uid_buyer is identified so we assume that kRef alone indicates fulfilment.

        Fulfilment does not create a receipt number, nor record that a receipt is mailed. Both of those are done by a separate system.
        Fulfilment can therefore only be undone if the donation has no receipt number.

        Adoptions are treated identically to donations, except an sl_adoption record is created on fulfil and deleted on fulfilUndo,
        with sl_adoption.kDonation pointing to the mbr_donation.
        The fk_sl_pcv is set to zero on fulfilment, so undo is only possible if it is still zero.
     */
    function IsFulfilled()
    {
        // general and adoption are identical here
        return( $this->GetWorkflowFlag(self::WORKFLOW_FLAG_RECORDED) && $this->GetKRef() );     // kRef is the mbr_donations._key
    }

    function CanFulfil()
    {
        // general and adoption are identical here
        return( $this->_canFulfilOrUndo() && $this->GetBasketObj()->GetBuyer() && !$this->IsFulfilled() );
    }

    function Fulfil()
    {
        $ret = self::FULFIL_RESULT_FAILED;

        // check if fulfilment is allowed
        if( !$this->CanFulfil() ) goto done;

        // check if already fulfilled
        if( $this->IsFulfilled() ) {
            $ret = self::FULFIL_RESULT_ALREADY_FULFILLED;
            goto done;
        }

        $oB = $this->GetBasketObj();    // succeeds because of the check above

// Date paid is not necessarily the date the order was made.  We might enter cheque orders long after they are received.
$dateReceived = $oB->GetDate();

        $oMbr = new Mbr_Contacts( $this->oSB->oApp );
        $kDonation = $oMbr->AddMbrDonation(
                        ['kMbr' => $oB->GetBuyer(),
                         'date_received' => $dateReceived,
                         'amount' => $this->GetF(),
                         'receipt_num' => 0 ] );

        if( $kDonation ) {
            $this->SetValue( 'kRef', $kDonation );
            $this->SetWorkflowFlag( self::WORKFLOW_FLAG_RECORDED );
            $this->SaveRecord();

            if( $this->GetProductName() == 'seed-adoption' ) {
                // adoptions are recorded by creating an sl_adoption that refers to the mbr_donation._key
                $oSLDB = new SLDBBase( $this->oSB->oApp );
                $kfrD = $oSLDB->KFRel('D')->CreateRecord();
                $kfrD->SetValue( 'kDonation', $kDonation );
                $kfrD->SetValue( 'fk_mbr_contacts', $oB->GetBuyer() );
                $kfrD->SetValue( 'amount', $this->GetF() );
                $kfrD->SetValue( 'd_donation', $dateReceived );

                $kfrD->SetValue( 'public_name', $this->GetExtra('slAdopt_name') );
                $kfrD->SetValue( 'sPCV_request', $this->GetExtra('slAdopt_cv') );

                $kfrD->PutDBRow();
            }

            $ret = self::FULFIL_RESULT_SUCCESS;
        }

        done:
        return( $ret );
    }

    function CanFulfilUndo()
    {
        $oMbr = new Mbr_Contacts( $this->oSB->oApp );

        // you can undo filfilment if the mbr_donations record referenced by kRef doesn't have a receipt number yet
        $ok = ( $this->_canFulfilOrUndo() && $this->IsFulfilled() &&
                ($kRef = $this->GetKRef()) &&
                ($kfrD = $oMbr->oDB->GetKfr('D',$kRef)) && !$kfrD->Value('receipt_num') );

        if( $ok && $this->GetProductName() == 'seed-adoption' ) {
            // you can only undo an adoption if fk_sl_pcv hasn't been set yet
            $oSLDB = new SLDBBase( $this->oSB->oApp );
            if( ($kfrAdopt = $oSLDB->GetKFRCond( 'D', "kDonation='{$this->GetKRef()}'" )) ) {
                $ok = $kfrAdopt->Value('fk_sl_pcv') == 0;
            }
        }

        return( $ok );
    }

    function FulfilUndo()
    {
        $ret = self::FULFILUNDO_RESULT_FAILED;

        // check if fulfilled
        if( !$this->IsFulfilled() ) {
            $ret = self::FULFILUNDO_RESULT_NOT_FULFILLED;
            goto done;
        }

        // check if fulfil undo is allowed
        if( !$this->CanFulfilUndo() ) goto done;

        // if seed-adoption, delete the sl_adoption record
        if( $this->GetProductName() == 'seed-adoption' ) {
            $oSLDB = new SLDBBase( $this->oSB->oApp );
            if( ($kfrAdopt = $oSLDB->GetKFRCond( 'D', "kDonation='{$this->GetKRef()}'" )) ) {
                $kfrAdopt->StatusSet( KeyframeRecord::STATUS_DELETED );
                $kfrAdopt->PutDBRow();
            }
        }

        // delete the mbr_donations record
        $oMbr = new Mbr_Contacts( $this->oSB->oApp );
        $kfrD = $oMbr->oDB->GetKfr( 'D', $this->GetKRef() );
        $kfrD->StatusSet( KeyframeRecord::STATUS_DELETED );
        $kfrD->SetValue( 'notes', date('Y-m-d').": user {$this->oSB->oApp->sess->GetUID()} did FulfilUndo() on purchase {$this->GetKey()}\n".$kfrD->Value('notes') );
        $kfrD->PutDBRow();

        // clear fulfilment
        $this->SetValue( 'kRef', 0 );
        $this->UnsetWorkflowFlag( self::WORKFLOW_FLAG_RECORDED );
        $this->SaveRecord();

        $ret = self::FULFILUNDO_RESULT_SUCCESS;

        done:
        return( $ret );
    }
}


class SEEDBasketProductHandler_Book extends SEEDBasketProductHandler_ItemN
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }

    function ProductDefine0( KeyFrameForm $oFormP )
    {
        return( parent::ProductDefine0_ItemN( $oFormP, "Publications" ) );
    }
}


/*********************************
    N.B. this class is repurposed for SEEDBasket_Purcase_special1, which is meant for items that are sold individually (e.g. garlic bulbils)
    That's different than books, which can be sold in any quantity, but for now the code is identical.
 */
class SEEDBasket_Purchase_book extends SEEDBasket_Purchase
{
    function __construct( SEEDBasketCore $oSB, int $kP, array $raParms = [] )
    {
        parent::__construct( $oSB, $kP, $raParms );
    }

    function GetFulfilControls()
    /***************************
        Return an array of labels for the fulfilment controls for this purchase
     */
    {
        return( ['fulfilButtonLabel' => "Mail today",
                 'statusFulfilled' => "mailed {$this->GetExtra('dMailed')}",  // status if fulfilled
                 'statusNotFulfilled' => "not mailed" ] );
    }


    /**************************************
        A book order is considered fulfilled when WORKFLOW_FLAG_MAILED.
        uid_buyer can be zero.
        The current date is stored in Purchase::sExtra so we can remember when the book was mailed, but this is nonessential to the workflow.
     */
    function IsFulfilled()
    {
        return( $this->GetWorkflowFlag(self::WORKFLOW_FLAG_MAILED) !== 0 );
    }

    function CanFulfil()
    {
        return( $this->_canFulfilOrUndo() && !$this->IsFulfilled() );
    }

    function Fulfil()
    {
        $ret = self::FULFIL_RESULT_FAILED;

        // check if fulfilment is allowed
        if( !$this->CanFulfil() ) goto done;

        // check if already fulfilled
        if( $this->IsFulfilled() ) {
            $ret = self::FULFIL_RESULT_ALREADY_FULFILLED;
            goto done;
        }

        $this->SetWorkflowFlag( self::WORKFLOW_FLAG_MAILED );
        $this->SetExtra( 'dMailed', date("Y-m-d") );
        $this->SaveRecord();

        $ret = self::FULFIL_RESULT_SUCCESS;

        done:
        return( $ret );
    }

    function CanFulfilUndo()
    {
        return( $this->_canFulfilOrUndo() && $this->IsFulfilled() );    // there is no limitation on when you can undo the mailing
    }

    function FulfilUndo()
    {
        $ret = self::FULFILUNDO_RESULT_FAILED;

        // check if fulfilled
        if( !$this->IsFulfilled() ) {
            $ret = self::FULFILUNDO_RESULT_NOT_FULFILLED;
            goto done;
        }

        // check if fulfil undo is allowed
        if( !$this->CanFulfilUndo() ) goto done;

        // clear fulfilment
        $this->UnsetWorkflowFlag( self::WORKFLOW_FLAG_MAILED );
        $this->SaveRecord();

        $ret = self::FULFILUNDO_RESULT_SUCCESS;

        done:
        return( $ret );
    }
}

class SEEDBasket_Purchase_special1 extends SEEDBasket_Purchase_book
/*********************************
    special1 is meant for items that are sold individually (e.g. garlic bulbils)
    That's different than books, which can be sold in any quantity, but for now the code is identical.
 */
{
    function __construct( SEEDBasketCore $oSB, int $kP, array $raParms = [] )
    {
        parent::__construct( $oSB, $kP, $raParms );
    }
}


class SEEDBasketProductHandler_Misc extends SEEDBasketProductHandler_MONEY
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }

    function ProductDefine0( KeyframeForm $oFormP )
    {
        return( parent::ProductDefine0_MONEY( $oFormP, "Misc Payment" ) );
    }
}

class SEEDBasket_Purchase_misc extends SEEDBasket_Purchase
{
    function __construct( SEEDBasketCore $oSB, int $kP, array $raParms = [] )
    {
        parent::__construct( $oSB, $kP, $raParms );
    }

    /**************************************
        A misc order is considered fulfilled when WORKFLOW_FLAG_RECORDED.
        uid_buyer can be zero.
        The current date is stored in Purchase::sExtra so we can remember when the order was recorded, but this is nonessential to the workflow.
     */
    function IsFulfilled()
    {
        return( $this->GetWorkflowFlag(self::WORKFLOW_FLAG_RECORDED) !== 0 );
    }

    function CanFulfil()
    {
        return( $this->_canFulfilOrUndo() && !$this->IsFulfilled() );
    }

    function Fulfil()
    {
        $ret = self::FULFIL_RESULT_FAILED;

        // check if fulfilment is allowed
        if( !$this->CanFulfil() ) goto done;

        // check if already fulfilled
        if( $this->IsFulfilled() ) {
            $ret = self::FULFIL_RESULT_ALREADY_FULFILLED;
            goto done;
        }

        $this->SetWorkflowFlag( self::WORKFLOW_FLAG_RECORDED );
        $this->SetExtra( 'dMailed', date("Y-m-d") );
        $this->SaveRecord();

        $ret = self::FULFIL_RESULT_SUCCESS;

        done:
        return( $ret );
    }

    function CanFulfilUndo()
    {
        return( $this->_canFulfilOrUndo() && $this->IsFulfilled() );    // there is no limitation on when you can undo the mailing
    }

    function FulfilUndo()
    {
        $ret = self::FULFILUNDO_RESULT_FAILED;

        // check if fulfilled
        if( !$this->IsFulfilled() ) {
            $ret = self::FULFILUNDO_RESULT_NOT_FULFILLED;
            goto done;
        }

        // check if fulfil undo is allowed
        if( !$this->CanFulfilUndo() ) goto done;

        // clear fulfilment
        $this->UnsetWorkflowFlag( self::WORKFLOW_FLAG_RECORDED );
        $this->SaveRecord();

        $ret = self::FULFILUNDO_RESULT_SUCCESS;

        done:
        return( $ret );
    }
}

class SEEDBasketProductHandler_Special1 extends SEEDBasketProductHandler_Item1
/*****************************************************************************
    Special Single Item
 */
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }

    function ProductDefine0( KeyframeForm $oFormP )
    {
        return( parent::ProductDefine0_Item1( $oFormP, "Special Single Item" ) );
    }

    function Purchase1( KeyframeRecord $kfrP )
    /*****************************************
        Only one of this product may be in the basket.
     */
    {
        $bOk = true;
        $sErr = "";

        if( ($raBPxP = $this->oSB->oDB->GetPurchasesList( $this->oSB->GetBasketKey() )) ) {
            foreach( $raBPxP as $ra ) {
                if( $ra['P_product_type']==$kfr->Value('product_type') && $ra['P_name']==$kfrP->Value('name') ) {
                    $bOk = false;
                    $sErr = "Only one ".$kfrP->Value('title_en')." can be added at a time.";
                    goto done;
                }
            }
        }

        done:
        return( [$bOk,$sErr] );
    }
}


class SEEDBasketProductHandler_Event extends SEEDBasketProductHandler
{
    function __construct( SEEDBasketCore $oSB )  { parent::__construct( $oSB ); }
}
