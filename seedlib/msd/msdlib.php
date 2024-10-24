<?php

/* msdlib
 *
 * Copyright (c) 2009-2024 Seeds of Diversity
 *
 * Support for MSD app-level code that shouldn't know about MSDCore but can't get what it needs from MSDQ.
 *
 * Office-Admin functions.
 */

include_once( "msdcore.php" );
include_once( SEEDCORE."SEEDDate.php" );
include_once( SEEDCORE."SEEDProblemSolver.php" );
include_once( SEEDCORE."SEEDLocal.php" );

class MSDLib
{
    public  $oApp;
    public  $oL;            // SEEDLocal strings available to all MSE apps
    private $oMSDCore;
    private $dbname1;
    public  $oTmpl;

    function __construct( SEEDAppConsole $oApp, $raConfig = [] )
    {
        $this->oApp = $oApp;
        $this->oMSDCore = new MSDCore( $oApp, ['sbdb' => @$raConfig['sbdb']] );
        $this->dbname1 = $this->oApp->GetDBName('seeds1');                              // except if sbdb is not seeds1, but it always is so far
        $this->oL = new SEED_Local(  $this->SLocalStrs(), $this->oApp->lang, 'mse' );
        $this->oTmpl = SEEDTemplateMaker2(
                        ['fTemplates' => [SEEDAPP."templates/msd.html", SEEDAPP."templates/msd-edit.html"],
                         'sFormCid'   => 'Plain',
                         //'raResolvers'=> array( array( 'fn'=>array($this,'ResolveTag'), 'raParms'=>array() ) ),
                         'raVars' => ['lang'=>$this->oApp->lang]
                        ]);
    }

    function PermOfficeW()  { return( $this->oMSDCore->PermOfficeW() ); }
    function PermAdmin()    { return( $this->oMSDCore->PermAdmin() ); }

    function GetCurrYear()            { return( $this->oMSDCore->GetCurrYear() ); }
    function GetFirstDayForCurrYear() { return( $this->oMSDCore->GetFirstDayForCurrYear() ); }

    function GetSpeciesNameFromKey($kSp) { return( $this->oMSDCore->GetSpeciesNameFromKlugeKey2($kSp) ); }

    function GetSpeciesSelectOpts( string $sCond = "", array $raParms = [] )
    {
        $raOpts = [];
        $raParmsLookup = [];

        if( ($cat = @$raParms['category']) ) {
            $raParmsLookup['category'] = $cat;
        }

        foreach( $this->oMSDCore->LookupSpeciesList($sCond, $raParmsLookup) as $ra ) {
            $raOpts["{$ra['species']} - {$ra['category']}"] = $ra['klugeKey2'];
        }
        return( $raOpts );
    }

    function TranslateCategory( $sCat ) { return( $this->oMSDCore->TranslateCategory( $sCat ) ); }
    function TranslateSpecies( $sSp )   { return( $this->oMSDCore->TranslateSpecies( $sSp ) ); }
    function TranslateSpecies2( $sSp )  { return( $this->oMSDCore->TranslateSpecies2( $sSp ) ); }

    function KFRelG()   { return( $this->oMSDCore->KFRelG() ); }
    function KFRelGxM() { return( $this->oMSDCore->KFRelGxM() ); }

    function IsGrowerDone( KeyframeRecord $kfrG )  { return( $this->oMSDCore->IsGrowerDone($kfrG) ); }
    function IsGrowerDoneFromDate( string $dDone ) { return( $this->oMSDCore->IsGrowerDoneFromDate($dDone) ); }

    function CondIsGrowerDone()                    { return( $this->oMSDCore->CondIsGrowerDone() ); }
    function CondIsGrowerListable( string $prefix) { return( $this->oMSDCore->CondIsGrowerListable($prefix) ); }


    function BulkRenameSpecies( int $klugeKey2From, int $klugeKey2To )
    /*****************************************************************
        Where the inputs are category/species klugeKey2s, rename PEspecies.v,PEcategory.v
        klugeKey2s are arbitrary Product keys that identify cat/sp tuples
     */
    {
        $ok = false;
        $sMsg = "";

        if( $klugeKey2From == $klugeKey2To ) {
            $sMsg = "Same species";
            goto done;
        }

// you could just do this in a query with the ints
        list($sFromSp,$sFromCat) = $this->oMSDCore->GetSpeciesNameFromKlugeKey2($klugeKey2From);
        list($sToSp,$sToCat) = $this->oMSDCore->GetSpeciesNameFromKlugeKey2($klugeKey2To);

        if( !$sFromSp || !$sToSp ) {
            $sMsg = "Can't find names for $klugeKey2From or $klugeKey2To";
            goto done;
        }

        // For all Products with From cat/sp tuples, rename their cat/sp to the To tuple.
        $dbFromSp  = addslashes($sFromSp);
        $dbFromCat = addslashes($sFromCat);
        $dbToSp    = addslashes($sToSp);
        $dbToCat   = addslashes($sToCat);
        $sql = "UPDATE {$this->dbname1}.SEEDBasket_Products P,{$this->dbname1}.SEEDBasket_ProdExtra PE_sp,{$this->dbname1}.SEEDBasket_ProdExtra PE_cat "
                     ."SET PE_sp.v='$dbToSp', PE_cat.v='$dbToCat' "
                     ."WHERE P.product_type='seeds' AND P._key=PE_sp.fk_SEEDBasket_Products AND P._key=PE_cat.fk_SEEDBasket_Products AND "
                           ."P._status=0 AND PE_sp._status=0 AND PE_cat._status=0 AND "
                           ."PE_sp.k='species' AND PE_cat.k='category' AND "
                           ."PE_sp.v='$dbFromSp' AND PE_cat.v='$dbFromCat'";
var_dump($sql);
        $this->oApp->kfdb->Execute($sql);
        $nAffected = $this->oApp->kfdb->GetAffectedRows();

        $sMsg = SEEDCore_HSC("Renamed $nAffected listings: $sFromSp ($sFromCat) to $sToSp ($sToCat)");
        $ok = true;

        done:
        return( [$ok,$sMsg] );
    }

    function RecordGrowerStats( KeyframeRecord $kfrG )
    /*************************************************
        Update seed counts and dates for the given grower.

        Dates when the grower made changes (_updated_G_mbr, _updated_S_mbr) are only updated when the grower is logged in,
        so if another person makes later changes the date _updated doesn't obscure that history.
     */
    {
        if( !($mbrid = $kfrG->Value('mbr_id')) ) goto done;

        /* Update seed counts in grower record
         */
        $sql = "SELECT count(*) FROM {$this->dbname1}.SEEDBasket_Products P,{$this->dbname1}.SEEDBasket_ProdExtra PE "
              ."WHERE P._key=PE.fk_SEEDBasket_Products AND P._status='0' AND P.product_type='seeds' AND "
                    ."P.uid_seller='$mbrid' AND P.eStatus='ACTIVE' AND PE.k='category' ";

        $kfrG->SetValue('nTotal',  $this->oMSDCore->oApp->kfdb->Query1($sql));
        $kfrG->SetValue('nFlower', $this->oMSDCore->oApp->kfdb->Query1($sql."  AND v='flowers'"));
        $kfrG->SetValue('nFruit',  $this->oMSDCore->oApp->kfdb->Query1($sql."  AND v='fruit'"));
        $kfrG->SetValue('nGrain',  $this->oMSDCore->oApp->kfdb->Query1($sql."  AND v='grain'"));
        $kfrG->SetValue('nHerb',   $this->oMSDCore->oApp->kfdb->Query1($sql."  AND v='herbs'"));
        $kfrG->SetValue('nTree',   $this->oMSDCore->oApp->kfdb->Query1($sql."  AND v='trees'"));
        $kfrG->SetValue('nVeg',    $this->oMSDCore->oApp->kfdb->Query1($sql."  AND v='vegetables'"));
        $kfrG->SetValue('nMisc',   $this->oMSDCore->oApp->kfdb->Query1($sql."  AND v='misc'"));


        /* Update dates in grower record
         * sed_curr_growers._updated_S     : the latest _updated in a seed-product owned by this grower
         * sed_curr_growers._updated_S_by  : who made that change
         * sed_curr_growers._updated_S_mbr : the latest _update in a seed-product owned by this grower, made by owner themselves i.e. where _updated_by==mbr_id
         */
        list($kP,$dLatest,$uidLatestBy) = $this->oMSDCore->GetLastUpdated("P.product_type='seeds'", ['uid_seller'=>$mbrid]);
        $kfrG->SetValue('_updated_S',     $dLatest);
        $kfrG->SetValue('_updated_S_by',  $uidLatestBy);

        if( $this->oApp->sess->GetUID() == $mbrid ) {
            $kfrG->SetVerbatim('tsGLogin', "NOW()" );

            /* If a different user updates the product that was the current grower's most recent product, then this computation will return the grower's second most recent product.
             * Therefore only store this computation when the grower is updating their own records.
             */
            list($kPByOwner,$dLatestByOwner,$uidOwner) = $this->oMSDCore->GetLastUpdated("P.product_type='seeds'", ['uid_seller'=>$mbrid,'bUpdatedByOwner'=>true]);
            $kfrG->SetValue('_updated_S_mbr', $dLatestByOwner);
        }

        $kfrG->PutDBRow( ['bNoChangeTS'=>true] );               // don't change sed_curr_growers._updated because we depend on that to know when real edits happened

        done:
        return;
    }

    function AdminNormalizeStuff()
    {
        $s = "";

        if( !$this->PermAdmin() ) goto done;

        $s = "<p>Trimmed and upper-cased species strings. Updated seed counts and _updated* for all growers.</p>";

        $this->oApp->kfdb->Execute( "UPDATE {$this->dbname1}.SEEDBasket_Products P,{$this->dbname1}.SEEDBasket_ProdExtra PE "
                                   ."SET PE.v=UPPER(TRIM(v)) "
                                   ."WHERE P.product_type='seeds' AND P._key=PE.fk_SEEDBasket_Products AND PE.k='species'" );

        if( ($kfrc = $this->oMSDCore->KFRelG()->CreateRecordCursor()) ) {
            while( $kfrc->CursorFetch() ) {
                $this->RecordGrowerStats($kfrc);
            }
        }

        done:
        return( $s );
    }

    function AdminCopyToArchive( $year )
    /***********************************
        Delete all archive records for $year.
        Copy current active growers and seeds to archive and give them $year.
     */
    {
        $ok = true;
        $s = "";

        $this->oApp->kfdb->Execute( "DELETE FROM {$this->dbname1}.sed_growers WHERE year='$year'" );
        $this->oApp->kfdb->Execute( "DELETE FROM {$this->dbname1}.sed_seeds WHERE year='$year'" );

        /* Archive growers
         */
        $fields = "mbr_id,mbr_code,frostfree,soiltype,organic,zone,cutoff,eDateRange,dDateRangeStart,dDateRangeEnd,eReqClass,notes, _created,_created_by,_updated,_updated_by";
        $sql = "INSERT INTO {$this->dbname1}.sed_growers (_key,_status, year, $fields )"
              ."SELECT NULL,0, '$year', $fields "
              ."FROM {$this->dbname1}.sed_curr_growers G WHERE G._status=0 AND {$this->oMSDCore->CondIsGrowerListable('G')} AND G.year='$year'";
//echo($sql);
        if( $this->oApp->kfdb->Execute($sql) ) {
            $s .= "<h4 style='color:green'>{$this->oApp->kfdb->GetAffectedRows()} Growers Successfully Archived</h4>"
                 ."<p style='margin-left:30px'><pre>$sql</pre></p>";
        } else {
            $s .= "<h4 style='color:red'>Archiving Growers Failed</h4>"
                 ."<p style='margin-left:30px'><pre>$sql</pre></p>"
                 ."<p style='margin-left:30px'><pre>{$this->oApp->kfdb->GetErrMsg()}</pre></p>";
            $ok = false;
        }

        /* Archive seeds
         *
         * Copy active seeds to the archive using INSERT...SELECT...
         * Use custom kfrel to fetch all ACTIVE seeds and their prodExtra fields, one per row per seed.
         * Override the fields in the SELECT so they match the fields in the INSERT.  All that matters is that they're in the same order.
         */
        $raSelectFields = [
             // create new _key in archive with the same create/update information as the seeds
             // (note this does not capture _updated/by from the PE fields so timestamps of latest changes to descriptions etc will not be reflected in the archive)
             'VERBATIM_newkey' => 'NULL',
             '_created' => 'P._created',
             '_created_by' => 'P._created_by',
             '_updated' => 'P._updated',
             '_updated_by' => 'P._updated_by',

             'mbr_id'=>'P.uid_seller',
             'category' => 'PEcategory.v',
             'species' => 'PEspecies.v',
             'variety' => 'PEvariety.v',
             'bot_name' => 'PEbot_name.v',
             'days_maturity' => 'PEdays_maturity.v',
             'days_maturity_seed' => 'PEdays_maturity_seed.v',
             'quantity' => 'PEquantity.v',
             'origin' => 'PEorigin.v',
             'eOffer' => 'PEeOffer.v',
             'year_1st_listed' => 'PEyear_1st_listed.v',
             'description' => 'PEdescription.v',
             'VERBATIM_year' => "'$year'"
        ];
//        $sSelectSql = $this->oMSDCore->GetSeedSql( "eStatus='ACTIVE'", ['raFieldsOverride'=> $raSelectFields] );
        $sSelectSql = $this->oMSDCore->GetSeedSql2( 'PxGxPEMSD', "eStatus='ACTIVE' AND {$this->CondIsGrowerListable('G')} AND G.year='$year'", ['raFieldsOverride'=> $raSelectFields] );

        $sInsertFields = "mbr_id,category,type,variety,bot_name,days_maturity,days_maturity_seed,quantity,origin,eOffer,year_1st_listed,description,year";

        /* Archive seeds
         */
        $sql = "INSERT INTO {$this->dbname1}.sed_seeds (_key,_created,_created_by,_updated,_updated_by, $sInsertFields ) $sSelectSql";
//echo ($sql);
        if( $this->oApp->kfdb->Execute($sql) ) {
            $s .= "<h4 style='color:green'>{$this->oApp->kfdb->GetAffectedRows()} Seeds Successfully Archived</h3>"
                 ."<p style='margin-left:30px'><pre>$sql</pre></p>";
        } else {
            $s .= "<h4 style='color:red'>Archiving Seeds Failed</h4>"
                 ."<p style='margin-left:30px'><pre>$sql</pre></p>"
                 ."<p style='margin-left:30px'><pre>".$this->oApp->kfdb->GetErrMsg()."</pre></p>";
            $ok = false;
        }

        return( array( $ok, $s ) );
    }

    function DrawAvailability( KeyframeRecord $kfrP, KeyframeRecord $kfrGxM )
    /************************************************************************
        State the availability of a product, given a Product and a Grower kfr.
     */
    {
        $s = "";

        $eRequestable = $this->oApp->sess->IsLogin() ? $this->oMSDCore->IsRequestableByUser($kfrP) : MSDCore::REQUESTABLE_NO_NOLOGIN;
        $bRequestable = ($eRequestable==MSDCore::REQUESTABLE_YES);

        if( $bRequestable ) {   // this also verifies that the current user can access grower contact info
            if( $kfrGxM->Value('M_firstname') || $kfrGxM->Value('M_lastname') ) {
                $who = $kfrGxM->Expand( "[[M_firstname]] [[M_lastname]] in [[M_province]]" );  // left out city for privacy of email-only growers
            } else {
                $who = $kfrGxM->Expand( "[[M_company]] in [[M_province]]" );
            }
        } else {
            $who = $kfrGxM->Expand( "a Seeds of Diversity member in [[M_province]]" );
        }

        $s .= "<p>This is offered by $who for $".$kfrP->Value('item_price')." in {$this->DrawPaymentMethod($kfrGxM)}.</p>";

        return( [$s,$eRequestable] );
    }

    function DrawOrderSlide( MSDBasketCore $oSB, KeyframeRecord $kfrP )
    {
        $s = "";

        $kP = $kfrP->Key();
        $kM = $kfrP->Value('uid_seller');
        $raPE = $oSB->oDB->GetProdExtraList( $kP );                 // prodExtra
        if( !($kfrGxM = $this->KFRelGxM()->GetRecordFromDB( "G.mbr_id='$kM'" )) ) goto done;

        list($sAvailability, $eRequestable) = $this->DrawAvailability( $kfrP, $kfrGxM );
        $bRequestable = ($eRequestable==MSDCore::REQUESTABLE_YES);

        // make this false to prevent people from ordering
//use bShutdown
        $bEnableAddToBasket = true;

        $sMbrCode = $kfrGxM->Value('mbr_code');
        $sButton1Attr = $bRequestable && $bEnableAddToBasket ? "onclick='AddToBasket_Name($kP);'"
                                                             : "disabled='disabled'";
        $sButton2Attr = true /*$bRequestable*/ ? "onclick='msdShowSeedsFromGrower($kM,\"$sMbrCode\");'"
                                      : "disabled='disabled'";

        $sG = "";
        if( $kfrGxM ) {
            $sG = "<div style='width:100%;margin:20px auto;max-width:80%;border:1px solid #777;background-color:#f8f8f8'>"
                 .$this->DrawGrowerBlock( $kfrGxM, true )
                 ."</div>";
        }

        switch( $eRequestable ) {
            default:
            case MSDCore::REQUESTABLE_YES:
                $sReq = "";
                break;
            case MSDCore::REQUESTABLE_NO_NOLOGIN:
                $sReq = "<p>Please login to request seeds.</p>";
                break;
            case MSDCore::REQUESTABLE_NO_INACTIVE:
                $sReq = "<p>This seed offer is not currently active.</p>";
                break;
            case MSDCore::REQUESTABLE_NO_OUTOFSEASON:
                $sDateStart = ($d = $kfrGxM->Value('dDateRangeStart')) ? SEEDDate::NiceDateStrFromDate($d, 'EN', SEEDDate::OMIT_YEAR) : "January 1";
                $sDateEnd   = ($d = $kfrGxM->Value('dDateRangeEnd'))   ? SEEDDate::NiceDateStrFromDate($d, 'EN', SEEDDate::OMIT_YEAR) : "May 31";
                $sReq = "<p class='alert alert-warning'>This grower only offers these seeds from <strong>$sDateStart</strong> to <strong>$sDateEnd</strong></p>";
                break;
            case MSDCore::REQUESTABLE_NO_NONGROWER:
                $sReq = "<p>These seeds are only available to members who also offer seeds in the Seed Exchange.</p></p>";
                break;
        }


        $s = "" //"<div style='display:none' class='msd-order-info msd-order-info-$kP'>"
                .SEEDCore_ArrayExpand( $raPE, "<p><b>[[species]] - [[variety]]</b></p>" )
                ."<p>$sAvailability</p>"
                .$sReq
                ."<p><button $sButton1Attr>Add this request to your basket</button>&nbsp;&nbsp;&nbsp;"
                   ."<button $sButton2Attr>Show other seeds from this grower</button></p>"
                .($bRequestable ? $sG : "")
            ;//."</div>";

        done:
        return( $s );
    }

    function DrawGrowerBlock( KeyframeRecord $kfrGxM, $bFull = true )
    {
        $s = $kfrGxM->Expand( "<b>[[mbr_code]]: [[M_firstname]] [[M_lastname]] ([[mbr_id]]) " )
             .($kfrGxM->value('organic') ? $this->oL->S('Organic') : "")."</b>"
             ."<br/>";

        if( $bFull ) {
            $s .= $kfrGxM->ExpandIfNotEmpty( 'company', "<strong>[[]]</strong>><br/>" );

            if( $kfrGxM->Value('eReqClass')!='email' ) {
                // don't show mailing address if requests are accepted by email only
                $s .= $kfrGxM->Expand( "[[M_address]], [[M_city]] [[M_province]] [[M_postcode]]<br/>" );
            }

            $s1 = "";
            if( !$kfrGxM->value('unlisted_email') )  $s1 .= $kfrGxM->ExpandIfNotEmpty( 'M_email', "<i>[[]]</i>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" );
            if( !$kfrGxM->value('unlisted_phone') )  $s1 .= $kfrGxM->ExpandIfNotEmpty( 'M_phone', "[[]]" );
            if( $s1 )  $s .= $s1."<br/>";

            //$s .= $kfrGxM->ExpandIfNotEmpty( 'cutoff', "No requests after: [[]]<br/>" );

            $s1 = $kfrGxM->ExpandIfNotEmpty( 'frostfree', "[[]] frost free days. " );
                 //.$kfrGxM->ExpandIfNotEmpty( 'soiltype',  "Soil: [[]]. " )
                 //.$kfrGxM->ExpandIfNotEmpty( 'zone',      "Zone: [[]]. " );
            if( $s1 )  $s .= $s1."<br/>";

            $s .= "Requests accepted "
                 .($kfrGxM->Value('eDateRange')=='all_year'
                     ? "all year round"
                     : ("from ".date_format(date_create($kfrGxM->Value('dDateRangeStart')), "M d")." to ".date_format(date_create($kfrGxM->Value('dDateRangeEnd')), "M d"))
                  )
                 .($kfrGxM->Value('eReqClass')=='mail' ? " by mail only" : ($kfrGxM->Value('eReqClass')=='email' ? " by e-payment only" : ""))
                 .".<br/>";

            $ra = array();
            foreach( array('nFlower' => array('flower','flowers'),
                           'nFruit'  => array('fruit','fruits'),
                           'nGrain'  => array('grain','grains'),
                           'nHerb'   => array('herb','herbs'),
                           'nTree'   => array('tree/shrub','trees/shrubs'),
                           'nVeg'    => array('vegetable','vegetables'),
                           'nMisc'   => array('misc','misc')
                           ) as $k => $raV ) {
                if( $kfrGxM->value($k) == 1 )  $ra[] = "1 ".$raV[0];
                if( $kfrGxM->value($k) >  1 )  $ra[] = $kfrGxM->value($k)." ".$raV[1];
            }
            $s .= $kfrGxM->value('nTotal')." listings: ".implode( ", ", $ra ).".<br/>";

            if( ($sPM = $this->DrawPaymentMethod($kfrGxM)) ) {
                $s .= "<i>Payment method: $sPM</i><br/>";
            }

            $s .= $kfrGxM->ExpandIfNotEmpty( 'notes', "Notes: [[]]<br/>" );
        }

        return( $s );
    }

    private $ePaymentMethods = [
        'pay_cash'      => ['epay'=>0, 'en' => "Cash",                'fr' => "" ], // fr now supplied via SEEDLocal so strings here are obsolete
        'pay_cheque'    => ['epay'=>0, 'en' => "Cheque",              'fr' => "" ],
        'pay_stamps'    => ['epay'=>0, 'en' => "Stamps",              'fr' => "" ],
        'pay_ct'        => ['epay'=>0, 'en' => "Canadian Tire money", 'fr' => "" ],
        'pay_mo'        => ['epay'=>0, 'en' => "Money order",         'fr' => "" ],
        'pay_etransfer' => ['epay'=>1, 'en' => "E-transfer",          'fr' => "" ],
        'pay_paypal'    => ['epay'=>1, 'en' => "Paypal",              'fr' => "" ] ];

    function DrawPaymentMethod( KeyframeRecord $kfrGxM )
    {
        $raPay = [];

        foreach( $this->ePaymentMethods as $k => $ra ) {
            if( $kfrGxM->value('eReqClass')=='email' && !$ra['epay'] ) continue;    // exclude non-epay methods if grower only accepts epay

            if( $kfrGxM->value($k) )  $raPay[] = $ra['en'];
        }
        if( $kfrGxM->value('pay_other') )  $raPay[] = $kfrGxM->value('pay_other');

        return( implode( ", ", $raPay ) );
    }

    function SLocalStrs()
    {
        $raStrs = [ 'ns'=>'mse', 'strs'=> [
            'Organic'               => ['EN'=>"[[]]", 'FR'=>"Biologique"],

            'pay_cash'              => ['EN'=>"Cash",                'FR'=>"Comptant"],
            'pay_cheque'            => ['EN'=>"Cheque",              'FR'=>"Ch&eacute;que"],
            'pay_stamps'            => ['EN'=>"Stamps",              'FR'=>"Timbres"],
            'pay_ct'                => ['EN'=>"Canadian Tire money", 'FR'=>"Argent Canadian Tire"],
            'pay_mo'                => ['EN'=>"Money order",         'FR'=>"Mandat postale"],
            'pay_etransfer'         => ['EN'=>"E-transfer",          'FR'=>"T&eacute;l&eacute;virement"],
            'pay_paypal'            => ['EN'=>"Paypal",              'FR'=>"Paypal"],
            'pay_other'             => ['EN'=>"Other",               'FR'=>"Autre"],    // used in UI but not in payment methods string (pay_other is appended verbatim)

        ]];

        return( $raStrs );
    }
}
