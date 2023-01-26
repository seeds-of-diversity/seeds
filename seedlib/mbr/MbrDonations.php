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
            if( !($kfr = $oContacts->oDB->GetKFRCond('DxM', "receipt_num='$nReceipt'")) ) {
                $sBody .= "<div class='donReceipt_page'>Unknown receipt number $nReceipt</div>";
                continue;
            }

// use MbrContacts::DrawAddressBlock
            $vars = [
                'donorName' => $kfr->Expand("[[M_firstname]] [[M_lastname]]")
                              .( ($name2 = trim($kfr->Expand("[[M_firstname2]] [[M_lastname2]]"))) ? " &amp; $name2" : "")
                              .$kfr->ExpandIfNotEmpty('M_company', "<br/>[[]]"),
                'donorAddr' => $kfr->Expand("[[M_address]]<br/>[[M_city]] [[M_province]] [[M_postcode]]"),
                'donorReceiptNum' => $nReceipt,
                'donorAmount'  => $kfr->Value('amount'),
                'donorDateReceived' => $kfr->Value('date_received'),
                'donorDateIssued' => $kfr->Value('date_issued'),
                'taxYear' => substr($kfr->Value('date_received'), 0, 4)     // should be the year for which the donation applies
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