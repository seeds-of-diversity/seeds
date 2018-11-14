<?php

/* Member Seed Directory Q Layer
 *
 * Copyright (c) 2018 Seeds of Diversity
 */

class MSDQ extends SEEDQ
{
    const SEEDDRAW_SCREEN = 0;      // draw category and species
    const SEEDDRAW_SCREENLIST = 1;  // omit category and species because items are in a list
    const SEEDDRAW_PRINT = 2;       // printed directory layout
    const SEEDDRAW_TINY = 3;        // the most minimal

    private $oMSDCore;
    private $bIsAdmin = false;
    private $currYear = 0;

    function __construct( SEEDAppConsole $oApp, $raParms )
    {
        parent::__construct( $oApp, $raParms );
        $this->oMSDCore = new MSDCore( $oApp );

        $this->bIsAdmin = $oApp->sess->CanAdmin( "MSDAdmin" );
        $this->currYear = @$raParms['year'] ?: date("Y");
    }

    function Cmd( $cmd, $raParms = array() )
    {
        $rQ = $this->GetEmptyRQ();

        $kSeed = intval(@$raParms['kS']);   // this is a product number

        if( SEEDCore_StartsWith( $cmd, 'msdSeed--' ) ) {
            /* Get kfrS and ensure write access (kSeed is allowed to be 0)
             */
            $rQ['bHandled'] = true;

            $kfrS = $kSeed ? $this->oMSDCore->GetSeedKfr( $kSeed ) : $this->oMSDCore->CreateSeedKfr();
            if( !$kfrS || !($kSeed && $this->canWriteSeed($kfrS) )) {
                $rQ['sErr'] = "<p>Cannot update information for seed #$kSeed.</p>";
                goto done;
            }

        } else
        if( SEEDCore_StartsWith( $cmd, 'msdSeed-' ) ) {
            /* Get kfrS and ensure read access (kSeed must be non-zero
             */
            $rQ['bHandled'] = true;

            if( !($kfrS = $this->oMSDCore->GetSeedKfr( $kSeed )) || !$this->canReadSeed($kfrS) ) {
                $rQ['sErr'] = "<p>Cannot read information for seed #$kSeed.</p>";
                goto done;
            }
        }

        switch( $cmd ) {
            case 'msdSeed-Draw':
                list($rQ['bOk'],$rQ['sOut'],$rQ['sErr']) = $this->seedDraw( $kfrS, @$raParms['eDrawMode'] );
                break;

            case "msdSeed--Update":
                /* msd editor submitted a change to a seed listing
                 *     kS = product key of the seed (0 means insert a new one)
                 *
                 * output: bOk, sErr, raOut=validated and stored seed record, sOut=revised html seedDraw
                 */
                list($rQ['bOk'],$rQ['sErr']) = $this->seedUpdate( $kfrS, $raParms );
                if( $rQ['bOk'] ) {
                    // extract seed data from the kfr in a standardized way
                    $rQ['raOut'] = $this->oMSDCore->GetSeedRAFromKfr( $kfrS );
                    list($bDummy,$rQ['sOut'],$sDummy) = $this->seedDraw( $kfrS, self::SEEDDRAW_SCREEN );
                }
                break;
        }

        if( !$rQ['bHandled'] )  $rQ = parent::Cmd( $cmd, $raParms );

        done:
        return( $rQ );
    }

    private function seedUpdate( KeyframeRecord &$kfrS, $raParms )
    /*************************************************************
        Update/Insert the given seed product record. It has already been validated with canWrite().

        If bOk==true is returned, the caller expects that its $kfrS will contain validated data exactly as stored in the db
     */
    {
        $bOk = false;
        $sErr = "";


        $bOk = true;

        return( array($bOk,$sErr) );
    }

    private function seedDraw( $kfrS, $eDrawMode )
    /*********************************************
        Draw the given seed record. It has already validated with canRead().
     */
    {
        $bOk = false;
        $sOut = $sErr = "";

        // Show the category and species
        if( $eDrawMode == self::SEEDDRAW_SCREEN ) {
            $sCat = $kfrS->value('category');
            if( $this->oApp->lang == 'FR' ) {

            }
            $sSpecies = $kfrS->value('species');
            if( $this->oApp->lang == 'FR' ) {

            }
            $sOut .= "<div class='sed_category'><h2>$sCat</h2></div>"
                    ."<div class='sed_species'><h2>$sSpecies</h2></div>";
        }

        $bOk = true;

        done:
        return( array($bOk,$sOut,$sErr) );
    }

    private function canReadSeed( $kfrP )
    /************************************
        Anyone is allowed to see an ACTIVE seed product. Sellers can see their own non-ACTIVE seed products.
     */
    {
        $ok = $kfrP
           && $kfrP->Value('product_type') == 'seeds'
           && ($kfrP->Value('eStatus')=='ACTIVE' || $kfrP->Value('uid_seller')==$this->oApp->sess->GetUID() || $this->bIsAdmin);

        return( $ok );
    }

    private function canWriteSeed( $kfrP )
    /*************************************
        You have to be the seller or an admin.
     */
    {
        $ok = $kfrP
           && $kfrP->Value('product_type') == 'seeds'
           && ($kfrP->Value('uid_seller')==$this->oApp->sess->GetUID() || $this->bIsAdmin);

        return( $ok );
    }
}


class MSDCore
/************
    Basic Member Seed Directory support built on top of SEEDBasket.
    In general, this should only be used by seedlib-level code. App-level code should use MSDQ instead of this.
 */
{
    private $oApp;
    private $raConfig;
    private $oSBDB;

    function __construct( SEEDAppConsole $oApp, $raConfig = array() )
    {
        $this->oApp = $oApp;
        $this->raConfig = $raConfig;
        $this->oSBDB = new SEEDBasketDB( $oApp->kfdb, $oApp->sess->GetUID(), $oApp->logdir );
    }

    function GetSeedKeys( $set = "" )
    /********************************
        The official set of keys that make a seed product record that makes sense outside of SEEDBasket.
        i.e. not including things like product_type and quant_type which *define* the product.
     */
    {
        $kfrKeys = array( '_key', '_created', '_created_by', '_updated', '_updated_by' );   // not _status because we manage hidden/deleted using eStatus
        $prodKeys = array( 'uid_seller', 'eStatus', 'img', 'item_price' );
        $prodExtraKeys = array( 'category', 'species', 'variety', 'bot_name', 'days_maturity', 'days_maturity_seed',
                                'quantity', 'origin', 'description', 'eOffer', 'year_1st_listed' );

        switch( $set ) {
            default:
                return( $kfrKeys + $prodKeys + $prodExtraKeys );
            case 'PRODUCT':
                return( $prodKeys );
            case 'PRODEXTRA':
                return( $prodExtraKeys );
            case 'PRODUCT PRODEXTRA':
                return( $prodKeys + $prodExtraKeys );
        }
    }

    function CreateSeedKfr()
    /***********************
     */
    {
        $kfr = $this->oSBDB->KFRel('P')->CreateRecord();

        // product defaults
        $kfr->SetValue( 'uid_seller', $this->oApp->sess->GetUID() );    // correct for single-user updater; multi-user editors will have to re-set this
        $kfr->SetValue( 'eStatus', 'ACTIVE' );
        $kfr->SetValue( 'item_price', '' );  // blank is the right default  '3.50' );

        // prodextra defaults
        $kfr->SetValue( 'eOffer', "member" );
        $kfr->SetValue( 'year_1st_listed', $this->currYear );

        return( $kfr );
    }

    function GetSeedKfr( $kProduct )
    /*******************************
        Get the kfr for this product and store prodextra values in the kfr too.
        Only store the standard msd prodextra keys so nobody can overwrite a crucial product field in the kfr by using a prodextra with that name
     */
    {
        $kfrP = null;

        if( $kProduct && ($kfrP = $this->oSBDB->GetKFR( 'P', $kProduct )) ) {
            $raPE = $this->oSBDB->GetProdExtraList( $kProduct );
            foreach( $this->GetSeedKeys('PRODEXTRA') as $k ) {
                $kfrP->SetValue( $k, @$raPE[$k] );
            }
        }
        return( $kfrP );
    }

    function GetSeedRAFromKfr( KeyframeRecord $kfrS )
    /************************************************
        Return an array of msd seed values from the kfr+prodextra
     */
    {
        $raOut = array();

        foreach( $this->GetSeedKeys('ALL') as $k ) {
            $raOut[$k] = $kfrS->Value($k);
        }

        return( $raOut );
    }
}
