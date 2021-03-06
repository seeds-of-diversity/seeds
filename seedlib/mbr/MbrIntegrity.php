<?php

/* MbrIntegrity
 *
 * Copyright 2020 Seeds of Diversity Canada
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

    function AssessDonations()
    {
        $raD = [];

        $dbname1 = $this->oApp->GetDBName('seeds1');
        $dbname2 = $this->oApp->GetDBName('seeds2');

        /* Find donations in mbr_contacts that are not in mbr_donations (up to 2019-12-31)
         */
        $sql = "SELECT M._key as kMbr, M.donation_date as date, M.donation as amount, M.donation_receipt as receipt_num
                FROM {$dbname2}.mbr_contacts M
                     LEFT JOIN {$dbname2}.mbr_donations D
                     ON (M._key=D.fk_mbr_contacts AND M.donation_date=D.date_received)
                WHERE M.donation>0 AND D.fk_mbr_contacts IS NULL";
        $raD['raOrphansFromMbrContacts'] = $this->oApp->kfdb->QueryRowsRA( $sql );

        /* Find donations in SEEDBasket that are not in mbr_donations

           Need O._created because baskets have been generated by admin process (that process should copy _created from mbr_order_pending
         */
        $sql = "SELECT B.uid_buyer as kMbr, DATE(O._created) as date, BP.f as amount, P.name as P_name
                FROM {$dbname1}.mbr_order_pending O
                     JOIN {$dbname1}.SEEDBasket_Baskets B ON (O.kBasket=B._key)
                     JOIN {$dbname1}.SEEDBasket_BP BP ON (B._key=BP.fk_SEEDBasket_Baskets)
                     JOIN {$dbname1}.SEEDBasket_Products P ON (P._key=BP.fk_SEEDBasket_Products)
                     LEFT JOIN {$dbname2}.mbr_donations D ON (D.fk_mbr_contacts=B.uid_buyer AND D.date_received=DATE(B._created) )
                WHERE D.fk_mbr_contacts IS NULL AND YEAR(B._created)>=2020 AND P.uid_seller='1' AND P.product_type='donation'";
        $raD['raOrphansFromSEEDBasket'] = $this->oApp->kfdb->QueryRowsRA($sql);

        return( $raD );
    }

    function ReportDonations( $raD = null )
    {
        $s = "";

        $raD = $raD ?: $this->AssessDonations();

        /* Report orphaned donations found in mbr_contacts (up to 2019-12-31)
         *
         * kMbr, date, amount, receipt_num
         */
        $s .= "<p style='font-weight:bold'>Missing mbr_donations from mbr_contacts (up to 2019-12-31)</p>";
        foreach( $raD['raOrphansFromMbrContacts']  as $ra ) {
            $s .= "<p style='margin-left:30px'>mbr:{$ra['kMbr']}, received: {$ra['date']}, $ {$ra['amount']}, receipt # {$ra['receipt_num']}</p>";
        }

        /* Report orphaned donations found in SEEDBasket (starting 2020-01-01)
         *
         * kMbr, date, amount, Product.name (general or seed-adoption)
         */
        $sGen = $sAdopt = "";
        foreach( $raD['raOrphansFromSEEDBasket']  as $ra ) {
            if( $ra['P_name'] == 'general' ) {
                $sGen .= "<p style='margin-left:30px'>mbr:{$ra['kMbr']}, received: {$ra['date']}, $ {$ra['amount']}</p>";
            } else {
                $sAdopt .= "<p style='margin-left:30px'>mbr:{$ra['kMbr']}, received: {$ra['date']}, $ {$ra['amount']}</p>";
            }
        }

        $s .= "<p style='font-weight:bold'>Missing mbr_donations (general) from SEEDBasket (starting 2020-01-01)</p>"
             .$sGen
             ."<p style='font-weight:bold'>Missing mbr_donations (seed-adoption) from SEEDBasket (starting 2020-01-01)</p>"
             .$sAdopt;

        return( $s );
    }

    function FixDonations( $raD = null )
    {
        $s = "";

        $raD = $raD ?: $this->AssessDonations();

        /* Create new mbr_donations records for any orphans found in mbr_contacts (up to 2019-12-31)
         */
        foreach( $raD['raOrphansFromMbrContacts']  as $ra ) {
            if( !$ra['date'] ) {
                $s .= "<p>{$ra['kMbr']}: Skipping blank date</p>";
                continue;
            }

            $oMbr = new Mbr_Contacts( $this->oApp );
            $oMbr->AddMbrDonation( ['kMbr'=>$ra['kMbr'], 'date_received'=>$ra['date'], 'amount'=>$ra['amount'], 'receipt_num'=>$ra['receipt_num'] ] );
        }

        /* Create new mbr_donations records for any orphans found in SEEDBasket (starting 2020-01-01)
         */
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
}