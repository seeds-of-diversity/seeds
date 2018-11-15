<?php

/* Member Seed Directory Q Layer
 *
 * Copyright (c) 2018 Seeds of Diversity
 */

include_once( SEEDLIB."msd/msdcore.php" );


class MSDQ extends SEEDQ
{
    const SEEDDRAW_SCREEN = 0;      // draw category and species
    const SEEDDRAW_SCREENLIST = 1;  // omit category and species because items are in a list
    const SEEDDRAW_PRINT = 2;       // printed directory layout
    const SEEDDRAW_TINY = 3;        // the most minimal
    const SEEDDRAW_CLICKABLE = 8;   // bitwise-and this to make clickable

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

    private function seedDraw( $kfrS, $eDrawMode )
    /*********************************************
        Draw the given seed record. It has already validated with canRead().
     */
    {
        $bOk = false;
        $sOut = $sErr = "";

        $bClickable = $eDrawMode & self::SEEDDRAW_CLICKABLE;
        $eDrawMode = $eDrawMode % 8;

        $mbrCode = "SODC";

        // Show the category and species
        if( $eDrawMode == self::SEEDDRAW_SCREEN ) {
            $sCat = $kfrS->value('category');
            if( $this->oApp->lang == 'FR' ) {

            }
            $sSpecies = $kfrS->value('species');
            if( $this->oApp->lang == 'FR' ) {

            }
            //$sOut .= "<div class='msdSeedText_category'><h3>$sCat</h3></div>"
            $sOut .= "<div class='msdSeedText_species'>$sSpecies</div>";
        }

        $sOut .= "<b>".$kfrS->value('variety')."</b>";
        if( $eDrawMode == self::SEEDDRAW_PRINT ) {
            $sOut .= " @M@ <b>$mbrCode</b>".$kfrS->ExpandIfNotEmpty( 'bot_name', "<br/><b><i>[[]]</i></b>" );
        } else {
            $sOut .= $kfrS->ExpandIfNotEmpty( 'bot_name', " <b><i>[[]]</i></b>" );
        }
//        if( in_array( $eDrawMode, array(self::SEEDDRAW_SCREEN,self::SEEDDRAW_SCREENLIST) ) ) {
//            // Make the variety and mbr_code blue and clickable
//            $sOut .= "<span style='color:blue;cursor:pointer;' onclick='console01FormSubmit(\"ClickSeed\",".$kfrS->Key().");'>$s</span>";
//        }

//        $s .= $sButtons1;

        $sOut .= "<br/>";

        $sOut .= $kfrS->ExpandIfNotEmpty( 'days_maturity', "[[]] dtm. " )
               // this doesn't have much value and it's readily mistaken for the year of harvest
               //  .($this->bReport ? "@Y@: " : "Y: ").$kfrS->value('year_1st_listed').". "
                .$kfrS->value('description')." "
                .$kfrS->ExpandIfNotEmpty( 'origin', ($eDrawMode == self::SEEDDRAW_PRINT ? "@O@" : "Origin").": [[]]. " )
                .$kfrS->ExpandIfNotEmpty( 'quantity', "<b><i>[[]]</i></b>" );

        if( ($price = $kfrS->Value('item_price')) != 0.00 ) {
             $sOut .= " ".($this->oApp->lang=='FR' ? "Prix" : "Price")." $".$price;
        }

/*
        if( in_array($this->eReportMode, array("EDIT","REVIEW")) ) {
            // Show colour-coded backgrounds for Deletes, Skips, and Changes
            if( $kfrS->value('bDelete') ) {
                $s = "<div class='sed_seed_delete'><b><i>".($this->lang=='FR' ? "Supprim&eacute;" : "Deleted")."</i></b>"
                    .SEEDCore_NBSP("   ")
                    .$sButtons2
                    ."<br/>$s</div>";
            } else if( $kfrS->value('bSkip') ) {
                $sStyle = ($this->eReportMode == 'REVIEW') ? "style='background-color:#aaa'" : "";    // because this is used without <style>
                $s = "<div class='sed_seed_skip' $sStyle><b><i>".($this->lang=='FR' ? "Pass&eacute;" : "Skipped")."</i></b>"
                    .SEEDCore_NBSP("   ")
                    .$sButtons2
                    ."<br/>$s</div>";
            } else if( $kfrS->value('bChanged') ) {
                $s = "<div class='sed_seed_change'>$s</div>";
            }
        }
*/

        /* FloatRight contains everything that goes in the top-right corner
         */
        $sFloatRight = "";
        //if( $this->eReportMode == 'EDIT' && !$kfrS->value('bSkip') && !$kfrS->value('bDelete') ) {
            switch( $kfrS->Value('eOffer') ) {
                default:
                case 'member':        $sFloatRight .= "<div class='sed_seed_offer sed_seed_offer_member'>Offered to All Members</div>";  break;
                case 'grower-member': $sFloatRight .= "<div class='sed_seed_offer sed_seed_offer_growermember'>Offered to Members who offer seeds in the Directory</div>";  break;
                case 'public':        $sFloatRight .= "<div class='sed_seed_offer sed_seed_offer_public'>Offered to the General Public</div>"; break;
            }
        //}
        if( $eDrawMode != self::SEEDDRAW_PRINT )  $sFloatRight .= "<div class='sed_seed_mc'>$mbrCode</div>";


        // Put the FloatRight at the very top of the output block
        $sOut = "<div style='float:right'>$sFloatRight</div>".$sOut;


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

