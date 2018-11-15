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
    private $kUidSeller;
    private $currYear = 0;

    function __construct( SEEDAppConsole $oApp, $raConfig )
    /******************************************************
        raConfig: config_OverrideUidSeller = the uid_seller for multi-grower app, only allowed if sess->CanAdmin('MSDAdmin')
                  config_year              = the MSD year for new listings
     */
    {
        parent::__construct( $oApp, $raConfig );
        $this->oMSDCore = new MSDCore( $oApp );

        $this->kUidSeller = (($k = intval(@$raConfig['config_OverrideUidSeller'])) && $oApp->sess->CanAdmin( "MSDAdmin" ))
                            ? $k : $oApp->sess->GetUID();
        $this->currYear = @$raConfig['config_year'] ?: date("Y");
    }

    function Cmd( $cmd, $raParms = array() )
    {
        $rQ = $this->GetEmptyRQ();

        $kSeed = intval(@$raParms['kS']);   // this is a product number

        if( SEEDCore_StartsWith( $cmd, 'msdSeedList-' ) ) {
            /* These don't necessarily have a kSeed
             */
            $rQ['bHandled'] = true;

        } else
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
            case 'msdSeedList-GetData':
                list($rQ['bOk'],$rQ['raOut'],$rQ['sErr']) = $this->seedListGetData( $raParms );
                break;

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
                    list($dummy,$rQ['sOut'],$dummy) = $this->seedDraw( $kfrS, self::SEEDDRAW_SCREEN );
                }
                break;
        }

        if( !$rQ['bHandled'] )  $rQ = parent::Cmd( $cmd, $raParms );

        done:
        return( $rQ );
    }


    private function seedListGetData( $raParms )
    /*******************************************
        Return an array of standard msd seed records.
     */
    {
        $bOk = false;
        $raOut = array();
        $sErr = "";

        if( @$raParms['bAll'] ) {
            $cond = "";
        } else if( ($kS = intval(@$raParms['kS'])) ) {
            $cond = "_key='$kS'";
        } else if( ($uid = intval(@$raParms['uid_seller'])) ) {
            $cond = "uid_seller='$uid' && product_type='seeds'";
        } else {
            $sErr = "seedListGetData: no input parameters";
            goto done;
        }

        if( ($kfrc = $this->oMSDCore->SeedCursorOpen( $cond )) ) {
            while( $this->oMSDCore->SeedCursorFetch($kfrc) ) {
                $raOut[$kfrc->Key()] = $this->oMSDCore->GetSeedRAFromKfr( $kfrc );
            }
        }

        $bOk = true;

        done:
        return( array($bOk,$raOut,$sErr) );

    }

    private function seedUpdate( KeyframeRecord &$kfrS, $raParms )
    /*************************************************************
        Update/Insert the given seed product record. It has already been validated with canWrite().

        If bOk==true is returned, the caller expects that its $kfrS will contain validated data exactly as stored in the db
     */
    {
        $bOk = false;
        $sErr = "";

        // overlay the raParms on the kfr
        foreach( $this->oMSDCore->GetSeedKeys('PRODUCT PRODEXTRA') as $k ) {
            if( isset($raParms[$k]) ) {
                $kfrS->SetValue( $k, $raParms[$k] );
            }
        }

        // force defaults
        $kfrS->SetValue( 'uid_seller', $this->kUidSeller );     // especially this one because a) it's included in GetSetKeys so vulnerable to tampering, b) MSDCore assumes GetUID()
        $kfrS->SetValue( 'product_type', "seeds" );
        $kfrS->SetValue( 'quant_type', "ITEM-1" );
        if( !$kfrS->Value('year_1st_listed') ) {
            $kfrS->SetValue( 'year_1st_listed', $this->currYear );
        }
        // force price to float or set default price - use floatval because "0.00" is not false if it's a string
        if( !($price = floatval($kfrS->Value('item_price'))) ) {
            $price = in_array( $kfrS->Value('type'), array('POTATO','JERUSALEM ARTICHOKE','ONION','GARLIC') ) ? "12.00" : "3.50";
        }
        $kfrS->SetValue( 'item_price', $price );

        // validate the result
        if( !isset( $this->oMSDCore->GetCategories()[$kfrS->Value('category')] ) ) {
            $sErr = $kfrS->Value('category')." is not a seed directory category";
            goto done;
        }

        // save the product and prodextra
        if( !($bOk = $this->oMSDCore->PutSeedKfr( $kfrS )) ) {
            $sErr = "Database error saving seed record";
        }

        done:
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
                return( array_merge($kfrKeys, $prodKeys, $prodExtraKeys ) );
            case 'PRODUCT':
                return( $prodKeys );
            case 'PRODEXTRA':
                return( $prodExtraKeys );
            case 'PRODUCT PRODEXTRA':
                return( array_merge( $prodKeys, $prodExtraKeys ) );
        }
    }

    function CreateSeedKfr()
    /***********************
     */
    {
        $kfr = $this->oSBDB->KFRel('P')->CreateRecord();

        // product defaults
        $kfr->SetValue( 'uid_seller', $this->oApp->sess->GetUID() );    // correct for single-user updater; multi-user editors will have to re-set this
        $kfr->SetValue( 'product_type', "seeds" );
        $kfr->SetValue( 'quant_type', "ITEM-1" );
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
        kfrS is a SEEDBasket_Product
        Return an array of standard msd seed values. The kfr must have come from one of the methods above so it has prodextra information included in it.
     */
    {
        $raOut = array();

        foreach( $this->GetSeedKeys('ALL') as $k ) {
            $raOut[$k] = $kfrS->Value($k);
        }

        return( $raOut );
    }

    function PutSeedKfr( KeyframeRecord $kfrS )
    /******************************************
        kfrS is a SEEDBasket_Product
        Save a seed kfr to database. It must already be validated (or move validation code here?)
     */
    {
        // Save/update the product and get a product key if it's a new row.  Then save/update all the prodextra items.
        if( ($bOk = $kfrS->PutDBRow()) ) {
            foreach( $this->GetSeedKeys('PRODEXTRA') as $k ) {
                $this->oSBDB->SetProdExtra( $kfrS->Key(), $k, $kfrS->Value($k) );
            }
        }
        return( $bOk );
    }

    function SeedCursorOpen( $cond )
    {
        return( $this->oSBDB->GetKFRC( 'P', $cond ) );
    }

    function SeedCursorFetch( KeyframeRecord &$kfrP )
    /************************************************
        kfrS is a SEEDBasket_Product
     */
    {
        if( ($ok = $kfrP->CursorFetch()) ) {
            $raPE = $this->oSBDB->GetProdExtraList( $kfrP->Key() );
            foreach( $this->GetSeedKeys('PRODEXTRA') as $k ) {
                $kfrP->SetValue( $k, @$raPE[$k] );
            }
        }
        return( $ok );
    }

    function GetCategories() { return( $this->raCategories ); }

    private $raCategories = array(
            'flowers'    => array( 'db' => "FLOWERS AND WILDFLOWERS", 'EN' => "Flowers and Wildflowers", 'FR' => "Fleurs et gramin&eacute;es sauvages et ornementales" ),
            'vegetables' => array( 'db' => "VEGETABLES",              'EN' => "Vegetables",              'FR' => "L&eacute;gumes" ),
            'fruit'      => array( 'db' => "FRUIT",                   'EN' => "Fruits",                  'FR' => "Fruits" ),
            'herbs'      => array( 'db' => "HERBS AND MEDICINALS",    'EN' => "Herbs and Medicinals",    'FR' => "Fines herbes et plantes m&eacute;dicinales" ),
            'grain'      => array( 'db' => "GRAIN",                   'EN' => "Grains",                  'FR' => "C&eacute;r&eacute;ales" ),
            'trees'      => array( 'db' => "TREES AND SHRUBS",        'EN' => "Trees and Shrubs",        'FR' => "Arbres et arbustes" ),
            'misc'       => array( 'db' => "MISC",                    'EN' => "Miscellaneous",           'FR' => "Divers" ),
        );
}
