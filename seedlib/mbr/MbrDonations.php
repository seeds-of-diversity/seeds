<?php

/* MbrDonations
 *
 * Copyright 2023 Seeds of Diversity Canada
 *
 * Information about member donations
 */

class MbrDonations
{
    private $oApp;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
    }

    /**
     * Get donation history for one person
     * @param int $kMbr - member to retrieve history
     * @param array $raParms - filter/sort the returned records
     * @return array - array of donation records
     */
    function GetDonationInfo( int $kMbr, array $raParms = [] )
    {

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
    function DrawDonationReceipt( string $rngReceipt, $eFmt = 'PDF' )
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
/*
Implement SetVerbatim()

            $kfrAccess = $oContacts->oDB->KFRel('RxMxD')->CreateRecord();
            $kfrAccess->SetValue('fk_mbr_contacts', $this->oApp->sess->GetUID());
            $kfrAccess->SetValue('fk_mbr_donations', $kfrD->Key());
            $kfrAccess->SetVerbatim('time', 'NOW()');
            $kfrAccess->PutDBRow();
*/
            $uid = $this->oApp->sess->GetUID();
            $this->oApp->kfdb->Execute("INSERT INTO {$this->oApp->DBName('seeds2')}.mbr_donation_receipts_accessed
                                        (_key,_created,_created_by,_updated,_updated_by,_status,fk_mbr_contacts,fk_mbr_donations,time)
                                        VALUES (NULL,NOW(),{$uid},NOW(),{$uid},0,{$uid},{$kfrD->Key()},NOW())");
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
                $dompdf->stream( 'file.pdf', ['Attachment' => 0] );
                exit;
            case 'PDF':
                break;
            case 'HTML':
                // return sHead,sBody
                break;
        }

        return( [$sHead,$sBody] );
    }
}