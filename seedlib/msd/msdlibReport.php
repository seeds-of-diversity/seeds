<?php

class MSDLibReport
{
    private $oMSDLib;
    private $oMSDQ;

    function __construct( MSDLib $oMSDLib )
    {
        $this->oMSDLib = $oMSDLib;
        $this->oMSDQ = new MSDQ( $oMSDLib->oApp, ['config_currYear' => $oMSDLib->GetCurrYear()] );
    }

    function Report()
    {
        $s = "";

        $report = SEEDInput_Smart( "doReport", array("","JanGrowers","JanSeeds","SeptGrowers","SeptSeeds") );

        // only output the information for growers who don't have email addresses
        $bNoEmail = SEEDInput_Int( 'noemail' );

        switch( $report ) {
            case 'JanGrowers':
                header( "Content-type: text/html; charset=ISO-8859-1");
                $s .= $this->styleReport()
                     .$this->janGrowers();
                break;

            case 'JanSeeds':
                header( "Content-type: text/html; charset=ISO-8859-1");
                $s .= $this->styleReport()
                     .$this->janSeeds();
                break;

            case 'SeptGrowers':
                header( "Content-type: text/html; charset=ISO-8859-1");
                $s .= $this->styleReport()
                     .$this->septGrowers( $bNoEmail );
                break;

            case 'SeptSeeds':
                $s .= $this->styleReport()
                     .$this->septSeeds( $bNoEmail );
                break;

            case 'aug_gxls':
//                $this->Report_Aug_GXLS();
                break;
            default:
                echo "Unknown report";
        }
        return( $s );
    }

    private function janGrowers()
    {
        $s = "";

        $s .= "<div class='msd_growers'>"
             ."<h2>Growers</h2>";

        $gCond = "NOT G.bSkip AND NOT G.bDelete AND G._status='0'";

        // SoD
        if( ($kfrGxM = $this->oMSDLib->KFRelGxM()->CreateRecordCursor( "G.mbr_id=1 AND $gCond" )) ) {
            while( $kfrGxM->CursorFetch() ) {
                $s .= "<div class='msd_grower'>".$this->oMSDLib->DrawGrowerBlock( $kfrGxM, true )."</div>";
            }
        }

        // Canada
        if( ($kfrGxM = $this->oMSDLib->KFRelGxM()->CreateRecordCursor( "G.mbr_id<>1 AND M.country='CANADA' AND $gCond", ['sSortCol'=>'mbr_code'] )) ) {
            while( $kfrGxM->CursorFetch() ) {
                $s .= "<div class='msd_grower'>".$this->oMSDLib->DrawGrowerBlock( $kfrGxM, true )."</div>";
            }
        }

        // USA
        $s .= "<h3>U.S.A.</h3>";
        if( ($kfrGxM = $this->oMSDLib->KFRelGxM()->CreateRecordCursor( "M.country<>'CANADA' AND $gCond", ['sSortCol'=>'mbr_code'] )) ) {
            while( $kfrGxM->CursorFetch() ) {
                $s .= "<div class='msd_grower'>".$this->oMSDLib->DrawGrowerBlock( $kfrGxM, true )."</div>";
            }
        }

        return( $s );
    }

    private function janSeeds()
    {
        $s = "";
        $lastCat = $lastSp = "";

// replace this with MSDQ::msdSeedList-GetData
// Even faster is to make MSDQ::msdSeedList-DrawList (using SEEDCursor like msdSeedList-GetData does) with different draw modes, but don't call back to DrawProduct because it just calls
// msdSeed-Draw with each key; do that inside msdSeedList-DrawList
        $oW = new SEEDApp_Worker( $this->oMSDLib->oApp->kfdb, $this->oMSDLib->oApp->sess, $this->oMSDLib->oApp->lang );

        $oSB = new SEEDBasketCore( $this->oMSDLib->oApp->kfdb, $this->oMSDLib->oApp->sess, $this->oMSDLib->oApp,
                                   SEEDBasketProducts_SoD::$raProductTypes );

        if( ($kfrP = $oSB->oDB->GetKFRC( "PxPE3",
                                         "product_type='seeds' AND "
                                        ."eStatus='ACTIVE' AND "
                                        ."PE1.k='category' AND "
                                        ."PE2.k='species' AND "
                                        ."PE3.k='variety'"
// uncomment one of these to limit the query to a section
//." AND PE1.v in ('flowers')"
//." AND PE1.v in ('fruit','grain','herbs','misc','trees')"
//." AND PE1.v in ('vegetables') AND PE2.v not like 'TOMATO%'"
//." AND PE1.v in ('vegetables') AND PE2.v like 'TOMATO%'"
                                        ,
                                        array('sSortCol'=>'PE1_v,PE2_v,PE3_v') )) )
        {
            while( $kfrP->CursorFetch() ) {

                if( ($sCat = $kfrP->Value('PE1_v')) != $lastCat ) {
                    /* Start a new category
                     */
                    /*
                    if( $this->oMSDLib->oApp->lang == 'FR' ) {
                        foreach( $this->raCategories as $ra ) {
                            if( $ra['db'] == $kfrS->value('category') ) {
                                $sCat = $ra['FR'];
                                break;
                            }
                        }
                    }
                    */
                    $s .= "<div class='sed_category'><h2>$sCat</h2></div>";
                    $lastCat = $sCat;
                    $lastSp = "";   // in case this code is used in a search on a species that appears in more than one category
                }
                if( ($sSp = $kfrP->Value('PE2_v')) != $lastSp ) {
                    /* Start a new species
                     */
                    $lastSp = $sSp;
                    if( ($sFR = $this->oMSDLib->TranslateSpecies2( $sSp )) ) {
                        $sSp .= " @T@ $sFR";
                    }
                    $s .= "<div class='sed_type'><h3><b>$sSp</b></h3></div>";
                }

                $s .= $oSB->DrawProduct( $kfrP, SEEDBasketProductHandler_Seeds::DETAIL_PRINT_NO_SPECIES, ['bUTF8'=>false] );
            }
        }

        return( $s );
    }

    private function septGrowers( $bNoEmail )
    /****************************************
        Grower information form.  This is a DocRep document with merged fields.
        When you print this from the browser, each grower form should fit on one page.
     */
    {
        $s = "";

        $raG = $this->getGrowerTable();

        // use obsolete code to create a DocRepDB on seeds2
        include_once( SEEDCOMMON."doc/docUtil.php" );
        include_once( STDINC."DocRep/DocRepWiki.php" );
        $kfdb2 = SiteKFDB( 'seeds2' );
        $oDocRepDB = New_DocRepDB_WithMyPerms( $kfdb2, $this->oMSDLib->oApp->sess->GetUID(), array('bReadonly'=>true, 'db'=>'seeds2') );
        $oDocRepWiki = new DocRepWiki( $oDocRepDB, "" );

        $s .= "<style type='text/css'>"
            ." .docPage    { page-break-after: always; }"
            ." .mbr        { font-family: arial; }"
            ." .mbr H3     { page-break-before: always; font-size: 13pt;}"
            ." .mbr H4     { font-size: 11pt;}"
            ." TD, .inst   { font-size: 9pt; }"
            ." H2          { font-size: 16pt; }"
            ."</style>";

        foreach( $raG as $ra ) {
            // optionally restrict output to growers who don't have email addresses
            if( $bNoEmail && $ra['email'] ) continue;

            $oDocRepWiki->SetVars( $ra );
            $sDocOutput = $oDocRepWiki->TranslateDoc( "sed_august_grower_package_page1" );

            $s .= "<div class='docPage'>".$sDocOutput."</div>";
        }

        return( $s );
    }

    private function septSeeds( $bNoEmail )
    /**************************************
        Seed listings per grower.  When you print this from the browser, each grower should start on a new page.

        Parms: g=1234 - just show the given grower
     */
    {
        $s = "";

        $s .= "<style type='text/css'>"
            ." .mbr        { font-family: arial; }"
            ." .mbr H3     { page-break-before: always; font-size: 13pt;}"
            ." .mbr H4     { font-size: 11pt;}"
            ." TD, .inst   { font-size: 9pt; }"
            ." H2          { font-size: 16pt; }"
            ."</style>";

// TODO: replace this with MSDQ::msdlist-stats
        $cond = "_status=0 and not bDelete";
        $s .= "<H2>Listings for the ".(date("Y")+1)." Member Seed Directory</H2>"
            ."<DIV style='background-color:#f8f8f8'>"
            .$this->oMSDLib->oApp->kfdb->Query1( "SELECT count(*) FROM sed_curr_growers where $cond" )." Growers<BR/>"
            .$this->oMSDLib->oApp->kfdb->Query1( "SELECT count(*) FROM sed_curr_seeds   where $cond and not bSkip" )." Seed Listings ("
            .$this->oMSDLib->oApp->kfdb->Query1( "SELECT count(*) FROM sed_curr_seeds   where $cond" )." including skips)<BR/>"
            .$this->oMSDLib->oApp->kfdb->Query1( "SELECT count(distinct type) FROM sed_curr_seeds where $cond and not bSkip" )." Types ("
            .$this->oMSDLib->oApp->kfdb->Query1( "SELECT count(distinct type) FROM sed_curr_seeds where $cond" )." including skips)<BR/>"
            .$this->oMSDLib->oApp->kfdb->Query1( "SELECT count(distinct type,variety) FROM sed_curr_seeds where $cond and not bSkip" )." Varieties ("
            .$this->oMSDLib->oApp->kfdb->Query1( "SELECT count(distinct type,variety) FROM sed_curr_seeds where $cond" )." including skips)<BR/>"
            ."</DIV>";

        $nGrowers = $nSeeds = 0;

        /* Get list of growers. For each grower get list of seeds and print their listings.
         */
        $cond = "NOT G.bDelete";
        if( ($g = SEEDInput_Int('g')) )  $cond .= " AND G.mbr_id='$g'";

        if( ($kfrG = $this->oMSDLib->KFRelGxM()->CreateRecordCursor( $cond, ["sSortCol"=>"M.country,G.mbr_code"]) ) ) {
            while( $kfrG->CursorFetch() ) {
                $kMbr = $kfrG->Value('mbr_id');
                $raMbr = $this->oMSDLib->oApp->kfdb->QueryRA( "SELECT * FROM seeds2.mbr_contacts WHERE _key='$kMbr'" );

                // optionally restrict output to growers who don't have email addresses
                if( $bNoEmail && $raMbr['email'] ) continue;

                $rQ = $this->oMSDQ->Cmd( 'msdSeedList-GetData', ['kUidSeller'=>$kMbr, 'eStatus'=>"'ACTIVE','INACTIVE'"] );
                if( !$rQ['bOk'] ) {
                    $s .= "<p>Error reading MSD data for grower $kMbr</p>";
                }

                $s .= "<div class='mbr'>"
                        ."<H3>".$kfrG->value('mbr_code')." - ".$raMbr['firstname']." ".$raMbr['lastname']." ".$raMbr['company']."</H3>"
                        ."<H4>Listings for Seeds of Diversity's ".$this->oMSDLib->GetCurrYear()." Member Seed Directory</H4>"
                        ."<UL class='inst'>"
                        ."<LI>Please make corrections in red ink.</LI>"
                        ."<LI>To permanently remove a listing, draw a large 'X' through it.</LI>"
                        ."<LI>To temporarily remove a listing from the ".$this->oMSDLib->GetCurrYear()." directory, but keep it on this list next fall, check \"Skip a Year\".</LI>"
                        ."</UL>";

                foreach( $rQ['raOut'] as $raS ) {
                    $s .= $this->septSeedsDrawListing( $raS, $kfrG->value('mbr_code') );
                }

                $nS = count($rQ['raOut']);
                $s .= "<p style='font-size: 7pt;'>$nS listing".($nS !== 1 ? "s" : "")."</p>"
                     ."</div>\n";  // mbr

                $nSeeds += $nS;
                ++$nGrowers;
            }
        }

        $s .= "<P style='page-break-before: always;'>"
             .$nGrowers." growers<BR>"
             .$nSeeds." listings"
             ."</P>";

         return( $s );
    }

    private function septSeedsDrawListing( $raS, $mbrCode )
    /******************************************************
     */
    {
        $raS['T-category'] = $this->oMSDLib->TranslateCategory( $raS['category'] );
        $raS['T-species'] = $this->oMSDLib->TranslateSpecies( $raS['species'] );

        $s = "<P style='page-break-inside: avoid;'>"
             ."<TABLE border=0 width='100%'>"
             ."<TR><TD valign='top'>"
             ."<B><INPUT type='checkbox'> Skip a year</B>";
        if( $raS['eStatus'] == 'INACTIVE' )  $s .= " (skipped last year)";
        $s .= "</TD>"
            .SEEDCore_ArrayExpand( $raS,
                "<TD align='right' valign='top' style='font-size: 7pt;'>$mbrCode listed since [[year_1st_listed]]</TD></TR>"

                ."<TR><TD valign='top' width='50%'><B>Category:</B> [[T-category]]</TD>"
                               ."<TD valign='top' width='50%'><B>Type:</B> [[T-species]]</TD></TR>"

                           ."<TR><TD colspan=2 valign='top' width='100%'><B>Variety:</B> [[variety]]</TD></TR>"

                           ."<TR><TD valign='top' width='50%'><B>Botanical name:</B> [[bot_name]]</TD>"
                               ."<TD valign='top' width='50%'><B>Days to maturity:</B> [[days_maturity]]</TD></TR>"

                           ."<TR><TD valign='top' width='50%'><B>Quantity:</B> [[quantity]]</TD>"
                               ."<TD valign='top' width='50%'><B>Origin:</B> [[origin]]</TD></TR>"

                           ."<TR><TD colspan=2 valign='top' width='100%'><B>Description:</B> [[description]]</TD></TR>" )

            ."</TABLE></P>"
            ."<HR/>";

        return( $s );
    }


    function Report_Aug_GXLS()
    /*************************
     */
    {
        $raG = $this->getGrowerTable();

        $bCSV = false;
        // the csv/xls logic could be handled in KFTable. When it does, update gcgc_report too

        if( !$bCSV ) {
            include_once( STDINC."KeyFrame/KFRTable.php" );

            $xls = new KFTableDump();
            $xls->xlsStart( "sed_growers.xls" );
        }

        /* Header row
         */
        $raHdr = array( "mbr_id",
                        "mbr_code",
                        "name",
                        "company",
                        "address",
                        "city",
                        "province",
                        "postcode",
                        "country",
                        "expires",
                        "phone",
                        "email",
                        "cutoff",
                        "frost_soil_zone",
                        "organic",
                        "payment" );

        $row = $i = 0;
        foreach( $raHdr as $h ) {
            if( $bCSV ) {
                echo $h."\t";
            } else {
                $xls->xlsWrite( 0, $i++, $h );
            }
        }
        if( $bCSV ) echo "\n";

        foreach( $raG as $ra ) {
            if( $bCSV ) {
                echo implode( "\t", $ra )."\n";
            } else {
                $i = 0;
                $row++;
                foreach( $ra as $k => $s ) {
                    if( $k == 'notes' ) continue;   // don't write notes because the Write_Excel module imposes a 255-char limit on strings (workaround is to use xls->write_note in KFTableDump)
                    $xls->xlsWrite( $row, $i, $s );
                    $i++;
                }
            }
        }

        if( !$bCSV ) $xls->xlsEnd();
    }


    function getGrowerTable( $bInclSkip = true )    // in August, we include growers that were skipped last year
    /*******************************************
        Return a table of all the grower data that we use for the August package (grower info sheet, mailing labels)
     */
    {
        $raG = array();

        $cond = "NOT G.bDelete";
        if( !$bInclSkip )                $cond .= " AND NOT G.bSkip";
        if( ($g = SEEDInput_Int('g')) )  $cond .= " AND G.mbr_id='$g'";

        if( ($kfrG = $this->oMSDLib->KFRelGxM()->CreateRecordCursor( $cond, ["sSortCol"=>"M.country,G.mbr_code"]) ) ) {
            while( $kfrG->CursorFetch() ) {

                $ra = array();
                $ra['mbr_id']   = $kfrG->Value("mbr_id");
                $ra['mbr_code'] = $kfrG->Value("mbr_code");
                $ra['name']     = $kfrG->Value("M_firstname")." ".$kfrG->Value("M_lastname");
                $ra['company']  = $kfrG->Value("M_company");
                $ra['address']  = $kfrG->Value("M_address");
                $ra['city']     = $kfrG->Value("M_city");
                $ra['province'] = $kfrG->Value("M_province");
                $ra['postcode'] = $kfrG->Value("M_postcode");
                $ra['country']  = $kfrG->Value("M_country");

                $yExpires = intval(substr($kfrG->Value('M_expires'),0,4));
//TODO: standardize special expires codes
                $ra['expires'] = ($yExpires == 2020 ? "Complimentary" :
                                 ($yExpires == 2100 ? "AUTO" :
                                 ($yExpires == 2200 ? "Lifetime" : $yExpires)));

                $ra['phone'] = ($kfrG->Value("unlisted_phone") ? "(you have chosen not to list phone)" : $kfrG->Value("M_phone"));
                $ra['email'] = ($kfrG->Value("unlisted_email") ? "(you have chosen not to list email)" : $kfrG->Value("M_email"));
                // important to have text before this one so spreadsheet doesn't mangle dates
                $ra['cutoff'] = "No requests after: ".$kfrG->Value("cutoff");
                $ra['frost_soil_zone'] = ($kfrG->Value("frostfree") ? ($kfrG->Value("frostfree")." frost free days. ") : "")
                                        .($kfrG->Value("soiltype") ? ("Soil: ".$kfrG->Value("soiltype").". ") : "")
                                        .($kfrG->Value("zone") ? ("Zone: ".$kfrG->Value("zone").". ") : "");
                $ra['organic'] = ($kfrG->Value("organic") ? "Organic" : "" );

                $raPay = array();
                if( $kfrG->Value("pay_stamps") ) $raPay[] = "Stamps";
                if( $kfrG->Value("pay_cash") )   $raPay[] = "Cash";
                if( $kfrG->Value("pay_ct") )     $raPay[] = "Canadian Tire";
                if( $kfrG->Value("pay_cheque") ) $raPay[] = "Cheque";
                if( $kfrG->Value("pay_mo") )     $raPay[] = "Money Order";
                if( $kfrG->Value("pay_other") )  $raPay[] = $kfrG->Value("pay_other");

                $ra['payment'] = implode(", ", $raPay);
                $ra['notes'] = $kfrG->Value("notes");

                $raG[] = $ra;
            }
            $kfrG->CursorClose();
        }
        return( $raG );
    }

    private function styleReport()
    {
        return(
            "<style>
            .sed_growers    { }
            .sed_grower     { font-size: 10pt; width:60%; }
            .sed_grower_skip  { color:gray; }
            .sed_grower_delete{ color:red; }
            .sed_grower_done{ background-color:#99DD99; }
            .sed_categories { }
            .sed_types      { }
            .sed_typesfull  { }
            .sed_type       { }
            .sed_type h3    { font-family:\"Minion Pro\"; font-size:11pt; }
            .sed_seed       { width:60%; font-family:\"Minion Pro\"; font-size:8pt; }
            .sed_seed_skip  { color:gray; }
            .sed_seed_delete{ color:red; }
            .sed_seed_change{ background-color:#99DD99; }
            .sed_seed_mc    { float:right; }
            .sed_seed_form  { }
            </style>"
        );
    }
}
