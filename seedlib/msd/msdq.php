<?php

/* Member Seed Directory Q Layer
 *
 * Copyright (c) 2018 Seeds of Diversity
 */

include_once( SEEDLIB."msd/msdcore.php" );


class MSDQ extends SEEDQ
{
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
                    list($dummy,$rQ['sOut'],$dummy) = $this->seedDraw( $kfrS, self::SEEDDRAW_EDIT.' '.self::SEEDDRAW_VIEW_SHOWSPECIES );
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
            $cond = "P._key='$kS'";
        } else if( ($uid = intval(@$raParms['uid_seller'])) ) {
            $cond = "P.uid_seller='$uid'";
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

    const SEEDDRAW_VIEW              = 'VIEW';              // plain screen view
    const SEEDDRAW_VIEW_REQUESTABLE  = 'VIEW_REQUESTABLE';  // screen view showing indicator that you can click on it
    const SEEDDRAW_VIEW_SHOWCATEGORY = 'VIEW_SHOWCATEGORY'; // add this to the above to include the category in the output block
    const SEEDDRAW_VIEW_SHOWSPECIES  = 'VIEW_SHOWSPECIES';  // add this to the above to include the species in the output block
    const SEEDDRAW_EDIT              = 'EDIT';              // editor view
    const SEEDDRAW_PRINT             = 'PRINT';             // output for printing

    private function seedDraw( $kfrS, $eDrawMode )
    /*********************************************
        Draw the given seed record. It has already validated with canRead().
     */
    {
        $bOk = false;
        $sOut = $sErr = "";

        $bModeEdit = strpos( $eDrawMode, 'EDIT' ) !== false;
        $bModePrint = strpos( $eDrawMode, 'PRINT' ) !== false;


        $mbrCode = "SODC";

        // Show the category and species
        if( strpos( $eDrawMode, 'VIEW_SHOWCATEGORY' ) !== false ) {
            $sOut .= "<div class='msdSeedText_category'>".$this->oMSDCore->TranslateCategory( $kfrS->value('category') )."</div>";
        }
        if( strpos( $eDrawMode, 'VIEW_SHOWSPECIES' ) !== false ) {
            $sOut .= "<div class='msdSeedText_species'>".$this->oMSDCore->TranslateSpecies( $kfrS->value('species') )."</div>";
        }

        // The variety line has a clickable look in the basket view, a plain look in other views, and a different format for print
        $sV = "<b>".$kfrS->value('variety')."</b>"
             .( $bModePrint ? (" @M@ <b>$mbrCode</b>".$kfrS->ExpandIfNotEmpty( 'bot_name', "<br/><b><i>[[]]</i></b>" ))
                            : ($kfrS->ExpandIfNotEmpty( 'bot_name', " <b><i>[[]]</i></b>" )) );
        $sOut .= strpos($eDrawMode, 'VIEW_REQUESTABLE') !== false
                    ? "<span style='color:#428bca;cursor:pointer;'>$sV</span>"  // color is bootstrap's link color
                    : $sV;


        $sOut .= "<br/>"
                .$kfrS->ExpandIfNotEmpty( 'days_maturity', "[[]] dtm. " )
               // this doesn't have much value and it's readily mistaken for the year of harvest
               //  .($this->bReport ? "@Y@: " : "Y: ").$kfrS->value('year_1st_listed').". "
                .$kfrS->value('description')." "
                .$kfrS->ExpandIfNotEmpty( 'origin', ($bModePrint ? "@O@" : "Origin").": [[]]. " )
                .$kfrS->ExpandIfNotEmpty( 'quantity', "<b><i>[[]]</i></b>" );

        if( ($price = $kfrS->Value('item_price')) != 0.00 ) {
             $sOut .= " ".($this->oApp->lang=='FR' ? "Prix" : "Price")." $".$price;
        }


        // Edit mode shows some contextual facts floated right that are not shown or shown in other places in other views
        if( $bModeEdit && $kfrS->value('eStatus')=='ACTIVE' ) {
            $sFloatRight = "";
            switch( $kfrS->Value('eOffer') ) {
                default:
                case 'member':        $sFloatRight .= "<div class='sed_seed_offer sed_seed_offer_member'>Offered to All Members</div>";  break;
                case 'grower-member': $sFloatRight .= "<div class='sed_seed_offer sed_seed_offer_growermember'>Offered to Members who offer seeds in the Directory</div>";  break;
                case 'public':        $sFloatRight .= "<div class='sed_seed_offer sed_seed_offer_public'>Offered to the General Public</div>"; break;
            }
            $sFloatRight .= "<div class='sed_seed_mc'>$mbrCode</div>";
            $sOut = "<div style='float:right'>$sFloatRight</div>".$sOut;
        }

        // Show colour-coded backgrounds for Deletes, Skips, and Changes
        if( $bModeEdit ) {
            if( $kfrS->value('eStatus') == 'DELETED' ) {
                $sOut = "<div class='sed_seed_delete'><b><i>".($this->oApp->lang=='FR' ? "Supprim&eacute;" : "Deleted")."</i></b>"
                    .SEEDCore_NBSP("   ")
                    //.$sButtons2
                    ."<br/>$sOut</div>";
            } else if( $kfrS->value('eStatus') == 'INACTIVE' ) {
                $sOut = "<div class='sed_seed_skip'><b><i>".($this->oApp->lang=='FR' ? "Pass&eacute;" : "Skipped")."</i></b>"
                    .SEEDCore_NBSP("   ")
                    //.$sButtons2
                    ."<br/>$sOut</div>";
            } else if( $kfrS->value('bChanged') ) {
                $sOut = "<div class='sed_seed_change'>$sOut</div>";
            }
        }


        // close the text in an outer div
// if( !$bModePrint ) -- not sure whether this div is good with print
        $sOut = "<div class='sed_seed' id='Seed".$kfrS->Key()."'>$sOut</div>";


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

