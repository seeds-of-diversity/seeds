<?php

/* MbrIntegrity
 *
 * Copyright 2020-2023 Seeds of Diversity Canada
 *
 * Check / ensure integrity of mbr tables
 */

class MbrIntegrity
{
    private $oApp;

    function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;
    }

    function WhereIsContactReferenced( $kMbr, $bIncludeCancelled = false )
    {
        $ra = [];

        $ra['nSBBaskets' ]  = $this->oApp->kfdb->Query1( "SELECT count(*) from {$this->oApp->DBName('seeds1')}.SEEDBasket_Baskets  WHERE _status='0' AND uid_buyer='$kMbr'"
                                .($bIncludeCancelled ? "" : " AND eStatus<>'Cancelled'" ) );    // want to be able to delete mbr_contacts for spam memberships after their orders cancelled
        $ra['nSProducts']   = $this->oApp->kfdb->Query1( "SELECT count(*) from {$this->oApp->DBName('seeds1')}.SEEDBasket_Products WHERE _status='0' AND uid_seller='$kMbr'" );
        $ra['nDescSites']   = $this->oApp->kfdb->Query1( "SELECT count(*) from {$this->oApp->DBName('seeds1')}.mbr_sites           WHERE _status='0' AND uid='$kMbr'" );
        $ra['nMSE']         = $this->oApp->kfdb->Query1( "SELECT count(*) from {$this->oApp->DBName('seeds1')}.sed_curr_growers    WHERE _status='0' AND mbr_id='$kMbr'" );
        $ra['nSLAdoptions'] = $this->oApp->kfdb->Query1( "SELECT count(*) from {$this->oApp->DBName('seeds1')}.sl_adoption         WHERE _status='0' AND fk_mbr_contacts='$kMbr'" );
        $ra['nDonations']   = $this->oApp->kfdb->Query1( "SELECT count(*) from {$this->oApp->DBName('seeds2')}.mbr_donations       WHERE _status='0' AND fk_mbr_contacts='$kMbr'" );

        $ra['nTotal'] = $ra['nSBBaskets'] + $ra['nSProducts'] + $ra['nDescSites'] + $ra['nMSE'] + $ra['nSLAdoptions'] + $ra['nDonations'];

        return( $ra );
    }

    /**
     * @param array $ra - output from WhereIsContactReferenced
     * @return string - explanation of contact references
     */
    function ExplainContactReferencesLong( array $ra )
    {
        $s = "";
        if( ($n = $ra['nSBBaskets']) )   { $s .= "<li>Has $n orders recorded in the order system</li>"; }
        if( ($n = $ra['nSProducts']) )   { $s .= "<li>Has $n offers in the seed exchange</li>"; }
        if( ($n = $ra['nDescSites']) )   { $s .= "<li>Has $n crop descriptions in their name</li>"; }
        if( ($n = $ra['nMSE']      ) )   { $s .= "<li>Is listed in the seed exchange</li>"; }
        if( ($n = $ra['nSLAdoptions']) ) { $s .= "<li>Has $n seed adoptions in their name</li>"; }
        if( ($n = $ra['nDonations']) )   { $s .= "<li>Has $n donation records in their name</li>"; }

        return($s);
    }

    /**
     * @param array $ra - output from WhereIsContactReferenced
     * @return string - explanation of contact references
     */
    function ExplainContactReferencesShort( array $ra )
    {
        $s = "";
        if( ($n = $ra['nSBBaskets']) )   { $s .= "$n orders<br/>"; }
        if( ($n = $ra['nSProducts']) )   { $s .= "$n seed exchange offers<br/>"; }
        if( ($n = $ra['nDescSites']) )   { $s .= "$n crop descriptions<br/>"; }
        if( ($n = $ra['nMSE']      ) )   { $s .= "Listed in MSE<br/>"; }
        if( ($n = $ra['nSLAdoptions']) ) { $s .= "$n seed adoptions<br/>"; }
        if( ($n = $ra['nDonations']) )   { $s .= "$n donation records<br/>"; }

        return($s);
    }


    function AssessDonations()
    {
        $raD = [];

        $dbname1 = $this->oApp->GetDBName('seeds1');
        $dbname2 = $this->oApp->GetDBName('seeds2');

        /* Find mbr_donations that are in an unpaid SEEDBasket (since 2020-01-01)
         */
        $sql = "SELECT D._key as kDonation, D.date_received as dReceived, Pur.kBasket as kBasket, Pur.eStatusBasket as eStatusBasket
                FROM {$dbname2}.mbr_donations D
                    "./* join the donations with all Purchases that are donations, and find all baskets that aren't FILLED*/ "
                     JOIN (SELECT B._key as kBasket, B.eStatus as eStatusBasket, BP.kRef as kRef
                                FROM {$dbname1}.SEEDBasket_BP BP
                                     JOIN {$dbname1}.SEEDBasket_Products P ON (P._key=BP.fk_SEEDBasket_Products)
                                     JOIN {$dbname1}.SEEDBasket_Baskets B ON (B._key=BP.fk_SEEDBasket_Baskets)
                                WHERE P.product_type='donation') Pur    "./* don't hide deleted because they're a problem  AND P._status='0' AND B._status='0' AND BP._status='0' */ "
                          ON D._key=Pur.kRef
                WHERE Pur.eStatusBasket<>'Filled' AND D._status='0' AND D.date_received>='2020-01-01'";
        $raD['raDonationsWithUnfulfilledBasket'] = $this->oApp->kfdb->QueryRowsRA($sql);

        /* Find duplicate receipt numbers
         */
        $sql = "SELECT D1._key as k1,D2._key as k2, D1.receipt_num as receipt_num
                FROM {$dbname2}.mbr_donations D1,{$dbname2}.mbr_donations D2
                WHERE D1._key<D2._key and D1.receipt_num>0 and D1.receipt_num=D2.receipt_num
                ORDER by D1.receipt_num";
        $raD['raDupReceiptNum'] = $this->oApp->kfdb->QueryRowsRA( $sql );

        /* Find donations in mbr_contacts that are not in mbr_donations (we stopped recording these 2019-12-31)
         */
        $sql = "SELECT M._key as kMbr, M.donation_date as date, M.donation as amount, M.donation_receipt as receipt_num
                FROM {$dbname2}.mbr_contacts M
                     LEFT JOIN {$dbname2}.mbr_donations D
                     ON (M._key=D.fk_mbr_contacts AND M.donation_date=D.date_received)
                WHERE M.donation>0 AND D.fk_mbr_contacts IS NULL";
        $raD['raOrphansInMbrContacts'] = $this->oApp->kfdb->QueryRowsRA( $sql );

        /* Find donations in SEEDBasket that are not in mbr_donations (starting 2020-01-01 when we stopped recording donations in mbr_contacts)

           Need O._created because baskets have been generated by admin process (that process should copy _created from mbr_order_pending
         */
        $sql = "SELECT O._key as kOrder, B.uid_buyer as kMbr, DATE(O._created) as date, BP.f as amount, P.name as P_name
                FROM {$dbname1}.mbr_order_pending O
                     JOIN {$dbname1}.SEEDBasket_Baskets B ON (O.kBasket=B._key)
                     JOIN {$dbname1}.SEEDBasket_BP BP ON (B._key=BP.fk_SEEDBasket_Baskets)
                     JOIN {$dbname1}.SEEDBasket_Products P ON (P._key=BP.fk_SEEDBasket_Products)
                     LEFT JOIN {$dbname2}.mbr_donations D ON (D.fk_mbr_contacts=B.uid_buyer AND D.date_received=DATE(B._created) )
                WHERE D.fk_mbr_contacts IS NULL AND
                      B.eStatus<>'Cancelled' AND
                      YEAR(B._created)>=2020 AND P.uid_seller='1' AND P.product_type='donation'";
        $raD['raOrphansInSEEDBasket'] = $this->oApp->kfdb->QueryRowsRA($sql);

        /* Find otherwise integral donations in (SEEDBasket,mbr_donations) where category='SLAdopt' is wrong (either way)
         */
        $sql = "SELECT D._key as kDonation, B.uid_buyer as kMbr, DATE(B._created) as date, BP.f as amount, P.name as P_name
                FROM {$dbname1}.SEEDBasket_Baskets B
                     JOIN {$dbname1}.SEEDBasket_BP BP ON (B._key=BP.fk_SEEDBasket_Baskets)
                     JOIN {$dbname1}.SEEDBasket_Products P ON (P._key=BP.fk_SEEDBasket_Products)
                     JOIN {$dbname2}.mbr_donations D ON (BP.kRef=D._key)
                WHERE B.eStatus<>'Cancelled' AND
                      YEAR(B._created)>=2020 AND P.uid_seller='1' AND P.product_type='donation' AND
                      (P.name='seed-adoption' AND D.category<>'SLAdopt' OR P.name<>'seed-adoption' AND D.category='SLAdopt')";
        $raD['raMismatchedSLAdoptCategory'] = $this->oApp->kfdb->QueryRowsRA($sql);

        /* Find mbr_donations that are orphans because they have no SEEDBasket_Purchase (since 2020-01-01)
         */
        $sql = "SELECT D._key as kDonation
                FROM {$dbname2}.mbr_donations D
                    "./* left join the donations with all Purchases that are donations, and find all D that have no Pur */ "
                     LEFT JOIN (SELECT BP._key as kBP,BP.kRef as kRef
                                FROM {$dbname1}.SEEDBasket_BP BP
                                     JOIN {$dbname1}.SEEDBasket_Products P ON (P._key=BP.fk_SEEDBasket_Products)
                                WHERE P.product_type='donation' AND P._status='0' AND BP._status='0') Pur
                          ON D._key=Pur.kRef
                WHERE Pur.kRef IS NULL AND D._status='0' AND D.date_received>='2020-01-01'";
        $raD['raDonationsWithNoPurchase'] = $this->oApp->kfdb->QueryRowsRA($sql);

        return( $raD );
    }

    function ReportDonations( $raD = null )
    {
        $s =
            "<style>
             .integ_heading {
                 font-weight:bold;
             }
             .integ_result_block {
                 margin-left:30px;
                 margin-bottom:30px;
                 max-height:200px;
                 overflow-y: scroll;
                 border: 1px solid #aaa;
             }
             </style>";

        $raD = $raD ?: $this->AssessDonations();

        /* Report mbr_donations that are linked to a SEEDBasket that is not Filled
         */
        $s .= "<div class='integ_heading'>Donations in a Basket that is not Filled (i.e. unpaid or cancelled)</div>"
             ."<div class='integ_result_block'>"
             .SEEDCore_ArrayExpandRows( $raD['raDonationsWithUnfulfilledBasket'],
                                        "<p>[[dReceived]] : kDonation:[[kDonation]], basket:[[kBasket]], basket status:[[eStatusBasket]]" )
             ."</div>";

        /* Report duplicate receipt_num
         */
        $s .= "<div class='integ_heading'>Duplicate Receipt Numbers</div>"
             ."<div class='integ_result_block'>"
             .SEEDCore_ArrayExpandRows( $raD['raDupReceiptNum'],
                                        "<p>receipt #:[[receipt_num]]</p>" )
             ."</div>";

        /* Report orphaned donations found in mbr_contacts (up to 2019-12-31)
         */
        $s .= "<div class='integ_heading'>Missing mbr_donations from mbr_contacts (up to 2019-12-31)</div>"
             ."<div class='integ_result_block'>"
             .SEEDCore_ArrayExpandRows( $raD['raOrphansInMbrContacts'],
                                        "<p>mbr:[[kMbr]], received:[[date]], $[[amount]], receipt #:[[receipt_num]]</p>" )
             ."</div>";

        /* Report orphaned donations found in SEEDBasket (starting 2020-01-01)
         */
        $s .= "<div class='integ_heading'>Donations in SEEDBasket that don't appear in mbr_donations (starting 2020-01-01)</div>"
             ."<div class='integ_result_block'>"
             .SEEDCore_ArrayExpandRows( $raD['raOrphansInSEEDBasket'],
                                        "<p>order:[[kOrder]], mbr:[[kMbr]], received:[[date]], $[[amount]], type:[[P_name]]</p>" )
             ."</div>";

        /* Report mismatched SLAdopt category of otherwise integral donation records
         */
        $s .= "<div class='integ_heading'>Mismatched SLAdopt category (either marked SLAdopt on a general donation, or vice versa)</div>"
             ."<div class='integ_result_block'>"
             .SEEDCore_ArrayExpandRows( $raD['raMismatchedSLAdoptCategory'],
                                        "<p>donation:[[kDonation]], mbr:[[kMbr]], received:[[date]], $[[amount]]</p>" )
             ."</div>";

        /* Report mbr_donations that are not linked to a SEEDBasket_Purchase
         */
        $s .= "<div class='integ_heading'>Donations that are not linked to a Purchase</div>"
             ."<div class='integ_result_block'>"
             .SEEDCore_ArrayExpandRows( $raD['raDonationsWithNoPurchase'],
                                        "<p>donation:[[kDonation]]" )
             ."</div>";

        return( $s );
    }

/*
    function FixDonations( $raD = null )
    {
        $s = "";

        $raD = $raD ?: $this->AssessDonations();

        // Create new mbr_donations records for any orphans found in mbr_contacts (up to 2019-12-31)
        foreach( $raD['raOrphansFromMbrContacts']  as $ra ) {
            if( !$ra['date'] ) {
                $s .= "<p>{$ra['kMbr']}: Skipping blank date</p>";
                continue;
            }

            $oMbr = new Mbr_Contacts( $this->oApp );
            $oMbr->AddMbrDonation( ['kMbr'=>$ra['kMbr'], 'date_received'=>$ra['date'], 'amount'=>$ra['amount'], 'receipt_num'=>$ra['receipt_num'] ] );
        }

        // Create new mbr_donations records for any orphans found in SEEDBasket (starting 2020-01-01)
        foreach( $raD['raOrphansFromSEEDBasket']  as $ra ) {
            if( !$ra['date'] ) {
                $s .= "<p>{$ra['kMbr']}: Skipping blank date</p>";
                continue;
            }

            $oMbr = new Mbr_Contacts( $this->oApp );
            $oMbr->AddMbrDonation( ['kMbr'=>$ra['kMbr'], 'date_received'=>$ra['date'], 'amount'=>$ra['amount'], 'receipt_num'=>0 ] );
        }

        $s .= "<p>Adding ".count($raD['raOrphansFromMbrContacts'])." records from mbr_contacts, "
             ."and ".count($raD['raOrphansFromSEEDBasket'])." records from SEEDBasket from </p>";

        return( $s );
    }
*/

}