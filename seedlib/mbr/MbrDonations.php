<?php

/* MbrDonations
 *
 * Copyright 2023 Seeds of Diversity Canada
 *
 * Information about member donations
 */

include_once("MbrContacts.php");

class MbrDonations
{
    private $oApp;
    public  $oDB;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oDB = new Mbr_ContactsDB($oApp);
    }

    /**
     * Get donation history for one person
     * @param int $kMbr - member to retrieve history
     * @param array $raParms - filter/sort the returned records
     * @return array - array of DxM records
     */
    function GetDonationInfo( int $kMbr, array $raParms = [] )
    {
        $sCond = "";

        if( @$raParms['minDate'] )  $sCond .= " AND (date_received >= '".addslashes($raParms['minDate'])."')";
        if( @$raParms['maxDate'] )  $sCond .= " AND (date_received <= '".addslashes($raParms['maxDate'])."')";

        switch( @$raParms['sort'] ) {
            case 'dateDown':
            default:
                $kfParms = ['sSortCol'=>'date_received','bSortDown'=>true];
                break;
            case 'dateUp':
                $kfParms = ['sSortCol'=>'date_received','bSortDown'=>false];
                break;
        }

        return( $this->oDB->GetList('DxM', "D.fk_mbr_contacts='$kMbr'".$sCond, $kfParms) );
    }


    /**
     * Show a list of links to view receipts for one person
     * @param int $kMbr - member who made donations
     * @param array $raParms - filter/sort the list
     * @return string - html of links to receipts
     */
    function DrawReceiptLinks( int $kMbr, array $raParms = [] )
    {
        $s = "";

// q : mbrdonation-printReceipt&receipt=
//            foreach( $this->oDonations->GetDonationInfo($kMbr,['minDate'=>'2022-01-01']) as $raDon ) {

        foreach( $this->GetDonationInfo($kMbr) as $raDon ) {
            if( $raDon['receipt_num'] ) {
                $s .= SEEDCore_ArrayExpand( $raDon,
                        "<p style=''>Receipt #<a href='{$this->oApp->UrlQ()}?qcmd=mbrdonation.printReceipt&receiptnum=[[receipt_num]]' target='_blank'>[[receipt_num]]</a>
                             for $[[amount]] donated [[date_received]]</p>" );
            } else {
                $s .= SEEDCore_ArrayExpand( $raDon,
                        "<p style=''>$[[amount]] donated [[date_received]] (receipt is being processed)</p>" );
            }
        }
        return( $s );
    }

    /**
     * Validate that the current user is allowed to access a donation receipt
     * @param int $receiptnum - mbr_donations.receipt_num
     * @return bool - access is allowed
     */
    function CurrentUserCanAccessReceipt( int $receiptnum )
    {
        $ok = false;

        if( ($kfr = $this->oDB->GetKFRCond('D', "receipt_num='$receiptnum'")) ) {
            $ok = $kfr->Value('fk_mbr_contacts') == $this->oApp->sess->GetUID();
        }

        return( $ok );
    }


    /**
     * Make a donation receipt
     * @param string $rngReceipt - a SEEDCore_Range of receipt numbers
     * @param string $eFmt       - the output format
     *
     * eFmt:    HTML       = return html string
     *          PDF        = return pdf string
     *          PDF_STREAM = output the pdf format to the return stream and exit
     */
    function DrawDonationReceipt( string $rngReceipt, $eFmt = 'PDF', $bRecordAccess = true )
    {
        $sHead = $sBody = "";

        include_once( SEEDLIB."SEEDTemplate/masterTemplate.php" );

        $oMT = new SoDMasterTemplate( $this->oApp, ['raSEEDTemplateMakerParms'=>['fTemplates'=>[SEEDAPP."templates/donation_receipt.html",
                                                                                                SEEDAPP."templates/donation_receipt2.html"]]] );

        list($raReceipts) = SEEDCore_ParseRangeStr( $rngReceipt );

        $oContacts = new Mbr_Contacts($this->oApp);
        $sBody = $eFmt=='HTML' ? $oMT->GetTmpl()->ExpandTmpl('donation_receipt_page', []) : "";     // HTML needs an extra blank first page
        foreach( $raReceipts as $nReceipt ) {
            if( !($kfrD = $oContacts->oDB->GetKFRCond('DxM', "receipt_num='$nReceipt'")) ) {
                $sBody .= "<div class='donReceipt_page'>Unknown receipt number $nReceipt</div>";
                continue;
            }

            /* Ensure that the current user is allowed to view this receipt
             */
            if( !$this->oApp->sess->IsLogin() || !in_array($this->oApp->sess->GetUID(), [$kfrD->Value('fk_mbr_contacts'), 1499]) ) {
                $sBody .= "<div class='donReceipt_page'>Please login to view donation receipt number $nReceipt</div>";
                continue;
            }


// use MbrContacts::DrawAddressBlock
            $vars = [
                'donorName' => $kfrD->Expand("[[M_firstname]] [[M_lastname]]")
                              .( ($name2 = trim($kfrD->Expand("[[M_firstname2]] [[M_lastname2]]"))) ? " &amp; $name2" : "")
                              .$kfrD->ExpandIfNotEmpty('M_company', "<br/>[[]]"),
                'donorAddr' => $kfrD->Expand("[[M_address]]<br/>[[M_city]] [[M_province]] [[M_postcode]]"),
                'donorReceiptNum' => $nReceipt,
                'donorAmount'  => $kfrD->Value('amount'),
                'donorDateReceived' => $kfrD->Value('date_received'),
                'donorDateIssued' => $kfrD->Value('date_issued'),
                'taxYear' => substr($kfrD->Value('date_received'), 0, 4)     // should be the year for which the donation applies
            ];
            $vars['donorName'] = SEEDCore_utf8_encode($vars['donorName']);
            $vars['donorAddr'] = SEEDCore_utf8_encode($vars['donorAddr']);

            switch($eFmt) {
                case 'PDF_STREAM':
                    // draw on a new page
                    $sBody .= $oMT->GetTmpl()->ExpandTmpl( 'donation_receipt_page2', $vars );
                    break;
                case 'HTML':
                    $sBody .= $oMT->GetTmpl()->ExpandTmpl( 'donation_receipt_page', $vars );
                    break;
            }

            // Record that the current user accessed this receipt
            if( $bRecordAccess ) {
/*

Implement SetVerbatim()

            $kfrAccess = $oContacts->oDB->KFRel('RxD_M')->CreateRecord();
            $kfrAccess->SetValue('uid_accessor', $this->oApp->sess->GetUID());
            $kfrAccess->SetValue('fk_mbr_donations', $kfrD->Key());
            $kfrAccess->SetVerbatim('time', 'NOW()');
            $kfrAccess->PutDBRow();
*/
                $uid = $this->oApp->sess->GetUID();
                $this->oApp->kfdb->Execute("INSERT INTO {$this->oApp->DBName('seeds2')}.mbr_donation_receipts_accessed
                                            (_key,_created,_created_by,_updated,_updated_by,_status,uid_accessor,fk_mbr_donations,time)
                                            VALUES (NULL,NOW(),{$uid},NOW(),{$uid},0,{$uid},{$kfrD->Key()},NOW())");
            }
        }

        switch($eFmt) {
            case 'PDF_STREAM':
                $options = new Dompdf\Options();
                $options->set('isRemoteEnabled', true);
                $options->set('isHtml5ParserEnabled', true);
                $dompdf = new Dompdf\Dompdf($options);
                $dompdf->loadHtml($sBody);
                $dompdf->setPaper('letter', 'portrait');
                $dompdf->render();
                $dompdf->stream( "Seeds of Diversity donation receipt #$nReceipt {$vars['donorName']}.pdf", ['Attachment' => 0] );
                exit;
            case 'PDF':
                break;
            case 'HTML':
                // return sHead,sBody
                break;
        }

        return( [$sHead,$sBody] );
    }

    function GetListDonationsNotAccessedByDonor( int $year )
    /*******************************************************
        Get donations of the given year whose receipts have not been accessed.
        We try to prevent office access from being recorded, but just in case, this is important to make sure we don't incorrectly think a donor got their receipt.
     */
    {
        $raOut = [];

        $raDonations = $this->oDB->GetList('D_R', "YEAR(D.date_received)='$year' AND D.receipt_num");
        /* Difficult to get D where R is null OR R.uid does not include donor.
         * These are either 1) uid==null : left join found no R so include this donation
         *                  2) uid==donor : the donor accessed this, so exclude
         *                  3) uid==other : an office access was accidentally recorded, so include UNLESS the donor also accessed
         * To solve the last case, make a list of 2, then process 1 and 3 while excluding any that overlap 2
         */
        $raDonorAccessed = [];
        foreach( $raDonations as $raDR ) {
            if( $raDR['R_uid_accessor']==$raDR['fk_mbr_contacts'] )  $raDonorAccessed[] = $raDR['_key'];
        }
        foreach( $raDonations as $raDR ) {
            if( !in_array($raDR['_key'], $raDonorAccessed) ) {
                $raOut[] = $raDR;
            }
        }
        return( $raOut );
    }
}