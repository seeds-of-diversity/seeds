<?php

/* Member Seed Directory Q Layer
 *
 * Copyright (c) 2018-2019 Seeds of Diversity
 */

include_once( SEEDLIB."msd/msdcore.php" );


class MSDQ extends SEEDQ
{
    private $oMSDCore;
    private $kUidSeller;

    function __construct( SEEDAppConsole $oApp, $raConfig )
    /******************************************************
        raConfig: config_OverrideUidSeller = the uid_seller for multi-grower app, only allowed if sess->CanWrite('MSDOffice')
                  config_currYear          = the MSD year for new listings
     */
    {
        parent::__construct( $oApp, $raConfig );
        $this->oMSDCore = new MSDCore( $oApp, array('currYear'=>@$raConfig['config_currYear']) );

        // If MSDOffice is editing a seed list by species (multiple growers) this is -1.
        // That means don't change the uid_seller. You can't add items in that mode, for the same reason.
        $this->kUidSeller = (($k = intval(@$raConfig['config_OverrideUidSeller'])) && $this->oMSDCore->PermOfficeW())
                            ? $k : $oApp->sess->GetUID();
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
            if( !$kfrS || ($kSeed && !$this->canWriteSeed($kfrS) )) {
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

            case 'msdSeed--Update':
                /* msd editor submitted a change to a seed listing
                 *     kS = product key of the seed (0 means insert a new one)
                 *
                 * output: bOk, sErr, raOut=validated and stored seed record, sOut=revised html seedDraw
                 */
                if( ($this->kUidSeller == 0 || $this->kUidSeller == -1) && !$kSeed ) {
                    // -1 is only possible with MSDOffice. It means don't override uid_seller, not allowed for Add
                    $rQ['sErr'] = "Cannot add a seed item in species-edit mode";
                    goto done;
                }

                list($rQ['bOk'],$rQ['sErr']) = $this->seedUpdate( $kfrS, $raParms );
                if( $rQ['bOk'] ) {
                    // extract seed data from the kfr in a standardized way
                    $rQ['raOut'] = $this->oMSDCore->GetSeedRAFromKfr( $kfrS, array('bUTF8'=>$this->bUTF8) );
                    list($dummy,$rQ['sOut'],$dummy) = $this->seedDraw( $kfrS, self::SEEDDRAW_EDIT.' '.self::SEEDDRAW_VIEW_SHOWSPECIES );
                }
                break;

            case 'msdSeed--ToggleSkip':
                switch( $kfrS->value('eStatus') ) {
                    default:         $kfrS->SetValue( 'eStatus', 'INACTIVE' ); break;
                    case 'INACTIVE': $kfrS->SetValue( 'eStatus', 'ACTIVE' ); break;
                }
                $rQ['bOk'] = $kfrS->PutDBRow();
                $rQ['raOut'] = $this->oMSDCore->GetSeedRAFromKfr( $kfrS, array('bUTF8'=>$this->bUTF8) );
                list($dummy,$rQ['sOut'],$dummy) = $this->seedDraw( $kfrS, self::SEEDDRAW_EDIT.' '.self::SEEDDRAW_VIEW_SHOWSPECIES );
                break;

            case 'msdSeed--ToggleDelete':
                switch( $kfrS->value('eStatus') ) {
                    default:        $kfrS->SetValue( 'eStatus', 'DELETED' ); break;
                    case 'DELETED': $kfrS->SetValue( 'eStatus', 'ACTIVE' ); break;
                }
                $rQ['bOk'] = $kfrS->PutDBRow();
                $rQ['raOut'] = $this->oMSDCore->GetSeedRAFromKfr( $kfrS, array('bUTF8'=>$this->bUTF8) );
                list($dummy,$rQ['sOut'],$dummy) = $this->seedDraw( $kfrS, self::SEEDDRAW_EDIT.' '.self::SEEDDRAW_VIEW_SHOWSPECIES );
                break;
        }

        if( !$rQ['bHandled'] )  $rQ = parent::Cmd( $cmd, $raParms );

        done:
        return( $rQ );
    }


    private function seedListGetData( $raParms )
    /*******************************************
        Return an array of standard msd seed records.

        Filter by:
            kProduct
            kUidSeller
            kSp
            bAll  :  must specify to force unfiltered list

        Secondary filter by:
            eStatus  :  any combination of quoted and comma-separated 'ACTIVE','INACTIVE','DELETED' or ALL
                        Required but independent of primary filters
     */
    {
        $bOk = false;
        $raOut = array();
        $sErr = "";
        $bCheckEStatus = true;

        $raCond = [];
        if( ($kProduct = intval(@$raParms['kProduct'])) ) {
            $raCond[] = "P._key='$kProduct'";
            $bCheckEStatus = false;                 // eStatus is irrelevant if a single product is specified
        }
        if( ($uid = intval(@$raParms['kUidSeller']))
            || ($uid = intval(@$raParms['uid_seller'])) ) { // deprecated
            $raCond[] = "P.uid_seller='$uid'";
        }
        if( ($kSp = intval(@$raParms['kSp'])) ) {
            $raCond[] = "PE2.v='".addslashes($this->oMSDCore->GetKlugeSpeciesNameFromKey($kSp))."'";
        }

        if( !count($raCond) && !@$raParms['bAll'] ) {       // this is why eStatus is a secondary parameter; it is required but at least one primary filter is also required
            $sErr = "seedListGetData: no input parameters";
            goto done;
        }

        // eStatus is combinations of quoted and comma-separated 'ACTIVE','INACTIVE','DELETED' or ALL
        if( $bCheckEStatus ) {
            if( !($eStatus = @$raParms['eStatus']) ) {
                $sErr = "eStatus required";
                goto done;
            }
            if( $eStatus != 'ALL' ) {
                $raCond[] = "eStatus IN ($eStatus)";
            }
        }

//$this->oApp->kfdb->SetDebug(2);
        // PE1.k='category'
        // PE2.k='species'
        // PE3.k='variety'
        if( ($kfrc = $this->oMSDCore->SeedCursorOpen( implode(' AND ', $raCond) )) ) {
            while( $this->oMSDCore->SeedCursorFetch($kfrc) ) {
                $raOut[$kfrc->Key()] = $this->oMSDCore->GetSeedRAFromKfr( $kfrc, array('bUTF8'=>$this->bUTF8) );
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

        // Overlay the raParms on the kfr. The kfr always contains cp1252, so if the parms are utf8 convert the encoding.
        foreach( $this->oMSDCore->GetSeedKeys('PRODUCT PRODEXTRA') as $k ) {
            if( isset($raParms[$k]) ) {
                $v = $raParms[$k];
                if( $this->bUTF8 ) $v = utf8_decode($v);
                $kfrS->SetValue( $k, $v );
            }
        }

        // force defaults
        $kfrS->SetValue( 'product_type', "seeds" );
        $kfrS->SetValue( 'quant_type', "ITEM-1" );
        if( !$kfrS->Value('year_1st_listed') ) {
            $kfrS->SetValue( 'year_1st_listed', $this->oMSDCore->GetCurrYear() );
        }
        // If MSDOffice is editing a seed list by species (multiple growers) uidSeller is -1.
        // That means don't change the uid_seller. You can't add items in that mode anyway, for the same reason.
        if( $this->kUidSeller && $this->kUidSeller != -1 ) {
            $kfrS->SetValue( 'uid_seller', $this->kUidSeller );
        }

        /* item_price can be anything you want, but if you give a plain number render it as %0.2d
         * and if it is blank set the default price
         */
        if( !($price = $kfrS->Value('item_price')) ) {
            // blank so set default
            $price = in_array( $kfrS->Value('species'), array('POTATO','JERUSALEM ARTICHOKE','ONION','GARLIC') ) ? "12.00" : "3.50";
            $kfrS->SetValue( 'item_price', $price );
        } else if( is_numeric($price) ) {
            $price = sprintf( "%.2f", floatval($price) );
            $kfrS->SetValue( 'item_price', $price );
        }

        // validate the result
        if( !isset( $this->oMSDCore->GetCategories()[$kfrS->Value('category')] ) ) {
            $sErr = $kfrS->Value('category')." is not a seed directory category";
            goto done;
        }
        if( !$kfrS->Value('species') ) {
            $sErr = "Please enter a species name";
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

        /* There are four kinds of views:
         *     VIEW                 plain screen view
         *     VIEW_REQUESTABLE     screen view for seed that the user is allowed to request
         *     EDIT                 drawn in the editor
         *     PRINT                printed directory
         */
        $eView = "VIEW";
        foreach( array('VIEW_REQUESTABLE','EDIT','PRINT') as $e ) {
            if( strpos( $eDrawMode, $e ) !== false ) { $eView = $e; break; }
        }
        // except the VIEW_REQUESTABLE mode is only allowed if the current user is allowed to request the seed
        if( $eView == 'VIEW_REQUESTABLE' ) {
            $eView = $this->oMSDCore->IsRequestableByUser( $kfrS ) ? 'VIEW_REQUESTABLE' : 'VIEW';
        }

        $mbrCode = $this->oApp->kfdb->Query1( "SELECT mbr_code FROM seeds.sed_curr_growers WHERE mbr_id='".addslashes($kfrS->value('uid_seller'))."'" );

        $raSeed = $this->oMSDCore->GetSeedRAFromKfr( $kfrS, array('bUTF8'=>$this->bUTF8) );

        // Show the category and species
        if( strpos( $eDrawMode, 'VIEW_SHOWCATEGORY' ) !== false ) {
            // if category were stored in utf8 and its translations were too, it would not be complicated to decide where to change the encoding
            $sOut .= "<div class='msdSeedText_category'>"
                    .$this->QCharset( $this->oMSDCore->TranslateCategory( $kfrS->value('category') ) )
                    ."</div>";
        }
        if( strpos( $eDrawMode, 'VIEW_SHOWSPECIES' ) !== false ) {
            // if species were stored in utf8 and its translations were too, it would not be complicated to decide where to change the encoding
            $sOut .= "<div class='msdSeedText_species'>"
                    .$this->QCharset( $this->oMSDCore->TranslateSpecies( $kfrS->value('species') ) )
                    ."</div>";
        }

        // The variety line has a clickable look in the basket view, a plain look in other views, and a different format for print
        $sV = "<b>{$raSeed['variety']}</b>"
             .( $eView=='PRINT' ? (" @M@ <b>$mbrCode</b>".SEEDCore_ArrayExpandIfNotEmpty( $raSeed, 'bot_name', "<br/><b><i>[[]]</i></b>" ))
                                : (SEEDCore_ArrayExpandIfNotEmpty( $raSeed, 'bot_name', " <b><i>[[]]</i></b>" )) );
        $sOut .= $eView=='VIEW_REQUESTABLE'
                    ? "<span style='color:#428bca;cursor:pointer;'>$sV</span>"  // color is bootstrap's link color
                    : $sV;


        $sOut .= "<br/>"
                .$kfrS->ExpandIfNotEmpty( 'days_maturity', "[[]] dtm. " )
               // this doesn't have much value and it's readily mistaken for the year of harvest
               //  .($this->bReport ? "@Y@: " : "Y: ").$kfrS->value('year_1st_listed').". "
                .$raSeed['description']." "
                .SEEDCore_ArrayExpandIfNotEmpty( $raSeed, 'origin', ($eView=='PRINT' ? "@O@" : "Origin").": [[]]. " )
                .SEEDCore_ArrayExpandIfNotEmpty( $raSeed, 'quantity', "<b><i>[[]]</i></b>" );

        /* item_price can be whatever you want. If it is numeric it is forced to %.2f format.
         * A literal zero becomes 0.00, which suppresses the Price label.
         */
        if( ($price = $kfrS->Value('item_price')) != '0.00' ) {
             $sOut .= " ".($this->oApp->lang=='FR' ? "Prix:" : "Price:")." "
                     .(is_numeric($price) ? SEEDCore_Dollar( $price, $this->oApp->lang ) : $price);
        }

        $sFloatRight = "";
        // Edit mode shows some contextual facts floated right that are not shown or shown in other places in other views
        if( $eView=='EDIT' && $kfrS->value('eStatus')=='ACTIVE' ) {
            switch( $kfrS->Value('eOffer') ) {
                default:
                case 'member':        $sFloatRight .= "<div class='sed_seed_offer sed_seed_offer_member'>Offered to All Members</div>";  break;
                case 'grower-member': $sFloatRight .= "<div class='sed_seed_offer sed_seed_offer_growermember'>Offered to Members who offer seeds in the Directory</div>";  break;
                case 'public':        $sFloatRight .= "<div class='sed_seed_offer sed_seed_offer_public'>Offered to the General Public</div>"; break;
            }
            $sFloatRight .= "<div class='sed_seed_mc'>$mbrCode</div>"
                           ."<div style='text-align:right'>First listed: ".$kfrS->Value('year_1st_listed')."</div>";
        } else if( $eView =='VIEW_REQUESTABLE' ) {
            $sFloatRight .= "<div class='sed_seed_mc'>$mbrCode</div>";
        }
        if( $sFloatRight ) $sOut = "<div style='float:right'>$sFloatRight</div>".$sOut;

        // Show colour-coded backgrounds for Deletes, Skips, and Changes
        if( $eView=='EDIT' ) {
            if( $kfrS->value('eStatus') == 'DELETED' ) {
                $sOut = "<div class='sed_seed_delete'><b><i>".($this->oApp->lang=='FR' ? "Supprim&eacute;" : "Deleted")."</i></b>"
                       ."<br/>$sOut</div>";
            } else if( $kfrS->value('eStatus') == 'INACTIVE' ) {
                $sOut = "<div class='sed_seed_skip'><b><i>".($this->oApp->lang=='FR' ? "Pass&eacute;" : "Skipped")."</i></b>"
                       ."<br/>$sOut</div>";
            } else {
                $rLastUpdate = $this->oMSDCore->GetLastUpdated( "P._key='".$kfrS->Key()."'" );
                if( ($d = substr($rLastUpdate[1],0,10)) ) {
                    $dUpdate = strtotime($d);
                    $dThreshold = strtotime( date('Y-m-d')." -10 months");   // 10 months ago from right now
                    if( $dUpdate > $dThreshold ) {
                        $sOut = "<div class='sed_seed_change'><b><i>".($this->oApp->lang=='FR' ? "Enregistr&eacute;" : "Saved")." ".substr($rLastUpdate[1],0,10)."</i></b>"
                               ."<br/>$sOut</div>";
                    }
                }
            }
        }


        // close the text in an outer div
// if( $eView!='PRINT' ) -- not sure whether this div is good with print
        $sOut = "<div class='sed_seed' id='Seed".$kfrS->Key()."'>$sOut</div>";


        $bOk = true;

        done:
        return( array($bOk,$sOut,$sErr) );
    }

    private function canReadSeed( $kfrP )
    /************************************
        Anyone is allowed to see an ACTIVE seed product. Sellers can see their own non-ACTIVE seed products.
        Office personnel can see any seed product.
     */
    {
        $ok = $kfrP
           && $kfrP->Value('product_type') == 'seeds'
           && ($kfrP->Value('eStatus')=='ACTIVE' || $kfrP->Value('uid_seller')==$this->oApp->sess->GetUID() || $this->oMSDCore->PermOfficeW());

        return( $ok );
    }

    private function canWriteSeed( $kfrP )
    /*************************************
        You have to be the seller of this seed or an office personnel.
     */
    {
        $ok = $kfrP
           && $kfrP->Value('product_type') == 'seeds'
           && ($kfrP->Value('uid_seller')==$this->oApp->sess->GetUID() || $this->oMSDCore->PermOfficeW());

        return( $ok );
    }
}

