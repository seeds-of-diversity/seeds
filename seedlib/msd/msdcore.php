<?php

/* MSDCore
 *
 * Copyright (c) 2018 Seeds of Diversity
 *
 *  Basic Member Seed Directory support built on top of SEEDBasket.
 */


class MSDCore
/************
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
// could just do $kfrcP = $this->oDB->GetKFRC( "P", "product_type='seeds' ".($cond ? "AND $cond " : "") if sorting is not required

// since the PE columns are just brought in for sorting, it makes sense for them to be left-joined so we don't lose products that are missing one of the PE
        $kfrcP = $this->oSBDB->GetKFRC( "PxPE3", "product_type='seeds' ".($cond ? "AND $cond " : "")
                                       ."AND PE1.k='category' "
                                       ."AND PE2.k='species' "
                                       ."AND PE3.k='variety' ",
                                       array('sSortCol'=>'PE1_v,PE2_v,PE3_v') );
        return( $kfrcP );
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

    function TranslateCategory( $sCat )
    {
        return( @$this->raCategories[$sCat][$this->oApp->lang] );
    }

    function TranslateSpecies( $sSpecies )
    {
        return( $sSpecies );
    }


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

