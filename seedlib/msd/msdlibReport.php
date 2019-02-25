<?php

//include_once( SEEDCORE."SEEDPerms.php" );
include_once( SEEDCOMMON."doc/docUtil.php" );
include_once( STDINC."DocRep/DocRepWiki.php" );


class MSDLibReport
{
    private $oMSDLib;

    function __construct( MSDLib $oMSDLib)
    {
        $this->oMSDLib = $oMSDLib;
    }

    function Report()
    {
        $s = "";

        $report = SEEDInput_Smart( "doReport", array("","JanGrowers","JanSeeds","aug_g","aug_s","aug_gxls") );

        switch( $report ) {
            case 'JanGrowers':
                header( "Content-type: text/html; charset=ISO-8859-1");
                $s .= $this->styleReport()
                     .$this->janGrowers();
                break;

            case 'JanSeeds':
                header( "Content-type: text/html; charset=ISO-8859-1");
                $s = "Seeds report";
                break;

            case 'jan_g':
                header( "Content-type: text/html; charset=ISO-8859-1");
                echo sed_style_report();
                echo $this->sed->DrawGrowers();
                break;
            case 'jan_s':
                header( "Content-type: text/html; charset=ISO-8859-1");
                echo sed_style_report();

                // use console01table to draw a bunch of drawSeed()
                $this->sed->oSed->oConsoleTable = new SedSeedConsole01Table( $this->sed->oSed, NULL );
                $this->sed->oSed->oConsoleTableDrawParms = array();

                echo $this->sed->drawSeedsAll();
                break;
            case 'aug_g':
                header( "Content-type: text/html; charset=ISO-8859-1");
                $this->Report_Aug_G();
                break;
            case 'aug_s':
                header( "Content-type: text/html; charset=ISO-8859-1");
                $this->Report_Aug_S();
                break;
            case 'aug_gxls':
                $this->Report_Aug_GXLS();
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


    function Report_Aug_G()
    /**********************
        Grower information form.  This is a DocRep document with merged fields.  When you print this from the browser, each grower form should fit on one page.
     */
    {
        $raG = $this->getGrowerTable();

        $oDocRepDB = New_DocRepDB_WithMyPerms( $this->sed->kfdb2, $this->sed->sess->GetUID(), array('bReadonly'=>true) );
        $oDocRepWiki = new DocRepWiki( $oDocRepDB, "" );

        echo "<STYLE type='text/css'>"
            ." .docPage    { page-break-after: always; }"
            ." .mbr        { font-family: arial; }"
            ." .mbr H3     { page-break-before: always; font-size: 13pt;}"
            ." .mbr H4     { font-size: 11pt;}"
            ." TD, .inst   { font-size: 9pt; }"
            ." H2          { font-size: 16pt; }"
            ."</STYLE>";

        foreach( $raG as $ra ) {
            $oDocRepWiki->SetVars( $ra );
            $sDocOutput = $oDocRepWiki->TranslateDoc( "sed_august_grower_package_page1" );

            echo "<DIV class='docPage'>".$sDocOutput."</DIV>";
        }
    }

    function Report_Aug_S()
    /**********************
        Seed listings per grower.  When you print this from the browser, each grower should start on a new page.

        Parms: g=1234 - just show the given grower
     */
    {
        echo "<STYLE type='text/css'>"
            ." .mbr        { font-family: arial; }"
            ." .mbr H3     { page-break-before: always; font-size: 13pt;}"
            ." .mbr H4     { font-size: 11pt;}"
            ." TD, .inst   { font-size: 9pt; }"
            ." H2          { font-size: 16pt; }"
            ."</STYLE>";

        $cond = "_status=0 and not bDelete";
        echo "<H2>Listings for the ".(date("Y")+1)." Member Seed Directory</H2>"
            ."<DIV style='background-color:#f8f8f8'>"
            .$this->sed->kfdb->KFDB_Query1( "SELECT count(*) FROM sed_curr_growers where $cond" )." Growers<BR/>"
            .$this->sed->kfdb->KFDB_Query1( "SELECT count(*) FROM sed_curr_seeds   where $cond and not bSkip" )." Seed Listings ("
            .$this->sed->kfdb->KFDB_Query1( "SELECT count(*) FROM sed_curr_seeds   where $cond" )." including skips)<BR/>"
            .$this->sed->kfdb->KFDB_Query1( "SELECT count(distinct type) FROM sed_curr_seeds where $cond and not bSkip" )." Types ("
            .$this->sed->kfdb->KFDB_Query1( "SELECT count(distinct type) FROM sed_curr_seeds where $cond" )." including skips)<BR/>"
            .$this->sed->kfdb->KFDB_Query1( "SELECT count(distinct type,variety) FROM sed_curr_seeds where $cond and not bSkip" )." Varieties ("
            .$this->sed->kfdb->KFDB_Query1( "SELECT count(distinct type,variety) FROM sed_curr_seeds where $cond" )." including skips)<BR/>"
            ."</DIV>";

        $pGid = SEEDSafeGPC_GetInt("g");

        $nGrowers = $nSeeds = $nSeedsPerGrower = 0;

        $mbr_id = 0;
        $cond = "S.mbr_id=G.mbr_id AND NOT G.bDelete AND NOT S.bDelete";
        if( $pGid ) $cond .= " AND G.mbr_id='$pGid'";

// Would like to make this a three-way join with M, get the member name below, sort this by M.country,G.mbr_code,category,type,variety
        if( ($kfrS = $this->sed->kfrelSxG->CreateRecordCursor( $cond, array("sSortCol"=>"G.mbr_code,category,type,variety")) ) ) {
            while( $kfrS->CursorFetch() ) {
                ++$nSeeds;
                ++$nSeedsPerGrower;

                if( $mbr_id != $kfrS->value('mbr_id') ) {
                    if( $mbr_id ) {
                        $this->augS_footer( $nSeedsPerGrower );
                    }
                    $mbr_id = $kfrS->value('mbr_id');

                    ++$nGrowers;
                    $nSeedsPerGrower = 0;

                    $raMbr = $this->sed->kfdb2->KFDB_QueryRA("SELECT * FROM mbr_contacts WHERE _key='$mbr_id'");

                    echo "<DIV class='mbr'>"
                        ."<H3>".$kfrS->value('G_mbr_code')." - ".$raMbr['firstname']." ".$raMbr['lastname']." ".$raMbr['company']."</H3>"
                        ."<H4>Listings for Seeds of Diversity's ".(date("Y")+1)." Member Seed Directory</H4>"
                        ."<UL class='inst'>"
                        ."<LI>Please make corrections in red ink.</LI>"
                        ."<LI>To permanently remove a listing, draw a large 'X' through it.</LI>"
                        ."<LI>To temporarily remove a listing from the ".(date("Y")+1)." directory, but keep it on this list next summer, check \"Skip a Year\".</LI>"
                        ."</UL>";
                }
                $this->augS_drawListing( $kfrS );
            }
            $this->augS_footer( $nSeedsPerGrower );
        }

        echo "<P style='page-break-before: always;'>"
             .$nGrowers." growers<BR>"
             .$nSeeds." listings"
             ."</P>";
    }

    function augS_footer( $nSeedsPerGrower )
    /***************************************
     */
    {
        echo "<P style='font-size: 7pt;'>$nSeedsPerGrower listing".($nSeedsPerGrower > 1 ? "s" : "")."</P>"
            ."</DIV>\n";  // mbr
    }

    function augS_drawListing( $kfrS )
    /*********************************
     */
    {
        echo "<P style='page-break-inside: avoid;'>"
             ."<TABLE border=0 width='100%'>"
             ."<TR><TD valign='top'>"
             ."<B><INPUT type='checkbox'> Skip a year</B>";
        if( $kfrS->value('bSkip') ) echo " (skipped last year)";
        echo "</TD>"
            ."<TD align='right' valign='top' style='font-size: 7pt;'>".$kfrS->value('G_mbr_code')." listed since ".$kfrS->value('year_1st_listed')."</TD></TR>"

            .$kfrS->Expand( "<TR><TD valign='top' width='50%'><B>Category:</B> [[category]]</TD>"
                               ."<TD valign='top' width='50%'><B>Type:</B> [[type]]</TD></TR>"

                           ."<TR><TD colspan=2 valign='top' width='100%'><B>Variety:</B> [[variety]]</TD></TR>"

                           ."<TR><TD valign='top' width='50%'><B>Botanical name:</B> [[bot_name]]</TD>"
                               ."<TD valign='top' width='50%'><B>Days to maturity:</B> [[days_maturity]]</TD></TR>"

                           ."<TR><TD valign='top' width='50%'><B>Quantity:</B> [[quantity]]</TD>"
                               ."<TD valign='top' width='50%'><B>Origin:</B> [[origin]]</TD></TR>"

                           ."<TR><TD colspan=2 valign='top' width='100%'><B>Description:</B> [[description]]</TD></TR>" )

            ."</TABLE></P>"
            ."<HR/>";
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

        $cond = "G.mbr_id=M._key AND NOT G.bDelete";    // the join condition could be removed if G.fk_mbr_contacts is implemented instead, even though the tables are in different databases
        if( !$bInclSkip ) {
            $cond .= " AND NOT G.bSkip";
        }

if( !empty($_REQUEST['g']) ) $cond .= " AND G.mbr_id='".intval($_REQUEST['g'])."'";

        if( ($kfrG = $this->sed->kfrelGxC->CreateRecordCursor( $cond, array("sSortCol"=>"M.country,G.mbr_code")) ) ) {
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
            .sed_type h3    { font-family:helvetica,sans-serif; }
            .sed_seed       { width:60%; }
            .sed_seed_skip  { color:gray; }
            .sed_seed_delete{ color:red; }
            .sed_seed_change{ background-color:#99DD99; }
            .sed_seed_mc    { float:right; }
            .sed_seed_form  { }
            </style>"
        );
    }
}
