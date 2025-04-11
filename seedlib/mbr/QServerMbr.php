<?php

/* QServerMbr
 *
 * Copyright 2019-2023 Seeds of Diversity Canada
 *
 * Contacts Q layer
 */

include_once( "MbrContacts.php" );
include_once( "MbrDonations.php" );

class QServerMbr extends SEEDQ
{
    private $oMbrContacts;

    function __construct( SEEDAppConsole $oApp, $raConfig )
    /******************************************************
     */
    {
        parent::__construct( $oApp, $raConfig );
        $this->oMbrContacts = new Mbr_Contacts($oApp);
    }

    function Cmd( $cmd, $raParms = array() )
    {
        $rQ = SEEDQ::GetEmptyRQ();

        // determine if this is the handler for this class of cmd, and if the current user has permission
        if( $cmd == 'mbrdonation.printReceipt' ) {
            // exception: mbrdonation-printReceipt only needs you to be logged in (it later authorizes based on identity)
            $bPermOk = $this->oApp->sess->IsLogin();
            $sErr = $bPermOk ? "" : "<p>Please login to view donation receipt</p>";
        } else if( SEEDCore_StartsWith($cmd, 'mbrdonation') ) {
            list($bPermOk,$sErr) = $this->TestPerm($cmd, 'mbrdonation');
        } else if( SEEDCore_StartsWith($cmd, 'mbr') ) {
            list($bPermOk,$sErr) = $this->TestPerm($cmd, 'mbr', 'MBR');
        } else {
            $rQ['bHandled'] = false;  // other handlers can try instead
            goto done;
        }
        $rQ['bHandled'] = true;       // even if not bPermOk, this is the handler so other handlers won't be attempted
        if( !$bPermOk ) {
            $rQ['sErr'] = $sErr;
            goto done;
        }


        switch( $cmd ) {
            /* Read commands
             */
            /* Get the lists of fields pertaining to certain categories of readers
             */
            case 'mbr-getFldsBasic':
                $rQ['raOut'] = $this->oMbrContacts->GetBasicFlds();
                $rQ['bOk'] = true;
                break;
            case 'mbr-getFldsOffice':
                $rQ['raOut'] = $this->oMbrContacts->GetOfficeFlds();
                $rQ['bOk'] = true;
                break;
            // Read but only accessible by admin
            case 'mbr---getFldsAll':
                $rQ['raOut'] = $this->oMbrContacts->GetAllFlds();
                $rQ['bOk'] = true;
                break;

            /* Get data for one contact
             */
            case 'mbr!getBasic':        // this does not require R perm, but is not accessible via ajax; used in non-office code that fetches a user's own info
                $rQ['bHandled'] = true; // fall through to mbr-getBasic
            case 'mbr-getBasic':
                list($rQ['bOk'],$rQ['raOut'],$rQ['sErr']) = $this->mbrGet( $raParms, 'basic' );
                break;
            case 'mbr!getOffice':       // this does not require R perm, but is not accessible via ajax; used in non-office code that fetches a user's own info
                $rQ['bHandled'] = true; // fall through to mbr-getOffice
            case 'mbr-getOffice':   // allows different perms access this in the future
                list($rQ['bOk'],$rQ['raOut'],$rQ['sErr']) = $this->mbrGet( $raParms, 'office' );
                break;
            // Requires read permission but only accessible by internal code
            case 'mbr-!-getAll':
                list($rQ['bOk'],$rQ['raOut'],$rQ['sErr']) = $this->mbrGet( $raParms, 'sensitive' );     // not implemented
                break;

            /* Get a list of contact data, filtered by lots of parameters
             */
            // Requires read permission but only accessible by internal code
            case 'mbr-!-getListOffice':
                list($rQ['bOk'],$rQ['raOut'],$rQ['sOut'],$rQ['sErr']) = $this->mbrGetList( $raParms, 'office' );
                break;

            case 'mbr-search':
                list($rQ['bOk'],$rQ['raOut'],$rQ['sErr']) = $this->mbrSearch( $raParms );
                break;

            /* Donation read-only
             */
            // print donation receipt to pdf/html and exit
            case 'mbrdonation.printReceipt':    // only donor can access, without explicit mbr-read permission
            case 'mbrdonation!printReceipt':    // only internal tools can access, to print any receipt
                $this->mbrDonationPrintReceipt( $raParms, $cmd == 'mbrdonation!printReceipt' );
                exit;

            /* Write commands
             */
            case 'mbr--putBasic':   list($rQ['bOk'],$rQ['sErr']) = $this->mbrPut( $raParms, 'basic' );     break;
            case 'mbr--putOffice':  list($rQ['bOk'],$rQ['sErr']) = $this->mbrPut( $raParms, 'office' );    break;
            // only accessible by internal code
//what is ----
            case 'mbr----putAll':   list($rQ['bOk'],$rQ['sErr']) = $this->mbrPut( $raParms, 'sensitive' ); break;
        }

        if( !$rQ['bHandled'] )  $rQ = parent::Cmd( $cmd, $raParms );

        done:
        return( $rQ );
    }

/* Moved to Mbr_Contacts
    private $raFlds = [
        'firstname'  => ['l_en'=>'First name'],
        'lastname'   => ['l_en'=>'Last name'],
        'firstname2' => ['l_en'=>'First name 2'],
        'lastname2'  => ['l_en'=>'Last name 2'],
        'company'    => ['l_en'=>'Company'],
        'dept'       => ['l_en'=>'Dept'],
        'address'    => ['l_en'=>'Address'],
        'city'       => ['l_en'=>'City'],
        'province'   => ['l_en'=>'Province'],
        'postcode'   => ['l_en'=>'Postal code'],
        'country'    => ['l_en'=>'Country'],
        'email'      => ['l_en'=>'Email'],
        'phone'      => ['l_en'=>'Phone'],
        'comment'    => ['l_en'=>'Comment'],

        'lang'       => ['l_en'=>'Language'],
        'referral'   => ['l_en'=>'Referral'],
        'bNoEBull'   => ['l_en'=>'No E-bulletin'],
        'bNoDonorAppeals' => ['l_en'=>'No Donor Appeals'],
        'expires'    => ['l_en'=>'Expires'],
        'lastrenew'  => ['l_en'=>'Last Renewal'],
        'startdate'  => ['l_en'=>'Start Date'],
//      'bNoSED'     => ['l_en'=>'Online MSD'],     obsolete and no longer updated
        'bPrintedMSD' => ['l_en'=>'Printed MSD'],

    ];
*/

    private function mbrGet( $raParms, $eDetail )
    /********************************************
        Return information about a given contact

        eDetail : basic | office | sensitive

        kMbr : contact key
            OR
        sEmail : contact email
     */
    {
        $bOk = false;
        $raOut = array();
        $sErr = "";

        if( ($kMbr = intval(@$raParms['kMbr'])) ) {
            $kfr = $this->oMbrContacts->oDB->GetKFR('M',$kMbr);
        } else if( ($dbEmail = addslashes(@$raParms['sEmail'])) ) {
            $kfr = $this->oMbrContacts->oDB->GetKFRCond('M', "email='$dbEmail'");
        } else {
            goto done;
        }

        if( $kfr ) {
            $raOut['_key'] = $kfr->Key();
            $raFlds = $eDetail=='office' ? $this->oMbrContacts->GetOfficeFlds() : $this->oMbrContacts->GetBasicFlds();  // sensitive not implemented
            foreach( $raFlds as $k =>$raDummy ) {
                $raOut[$k] = $this->QCharsetFromLatin($kfr->Value($k));
            }
            $bOk = true;
        }

        done:
        return( [$bOk, $raOut, $sErr] );
    }

    private function mbrGetList( $raParms, $eDetail )
    /************************************************
        Return a list of contacts' information

        eDetail : basic | office | sensitive

        raParms:
            bExistsEmail     : only if email<>''
            bExistsAddress   : only if address/city/postcode <> ''
            bGetEbulletin    : only if ebulletin subscribed
            bGetPrintedMSD   : only if they receive the printed MSD
            bGetDonorAppeals : only if they accept donor appeals
            yMbrExpires      : comma separated years of membership expiry; '+' suffix indicates greater than or equal e.g. 2020+
            provinceIn       : space separated provinces e.g. "AB SK MB"
            postcodeIn       : space separated postcode prefixes e.g. "M L N1 N2 K9"
            lang             : filter by language {EN,FR,default both/any}
     */
    {
        $bOk = false;
        $raOut = array();
        $sOut = "";
        $sErr = "";

        $raCond = [];

        if( @$raParms['bExistsEmail'] ) {
            $raCond[] = "(email IS NOT NULL AND email<>'')";
        }
        if( @$raParms['bExistsAddress'] ) {
            $raCond[] = "(address IS NOT NULL AND address<>'' AND "
                        ."city IS NOT NULL AND city<>'' AND "
                        ."postcode IS NOT NULL AND postcode<>'')";
        }
        if( @$raParms['bGetEbulletin'] )    { $raCond[] = "bNoEBull='0'"; }
        if( @$raParms['bGetPrintedMSD'] )   { $raCond[] = "bPrintedMSD='1'"; }
        if( @$raParms['bGetDonorAppeals'] ) { $raCond[] = "bNoDonorAppeals='0'"; }

        if( ($p = @$raParms['yMbrExpires']) && ($ra = explode(',',$p))) {
            // comma separated years of membership expiry with optional '+' to indicate greater-equal e.g. 2018,2020+
            $sGE = "";
            $raY = [];
            foreach( $ra as $y ) {
                if( substr($y,-1,1) == '+' ) {
                    $sGE = substr($y,0,4);    // greater than or equal to this year (assuming only one such year given)
                } else {
                    $raY[] = $y;
                }
            }
            // year conditions are disjunctive
            $sExp = "";
            if( $sGE ) $sExp .= "YEAR(expires)>='".addslashes($sGE)."'";
            if( $raY ) {
                if( $sExp ) $sExp .= " OR ";
                $sExp .= "YEAR(expires) IN (".addslashes(implode(',',$raY)).")";
            }
            if( $sExp ) $raCond[] = "($sExp)";
        }

        if( ($p = @$raParms['provinceIn']) && ($ra = explode(' ',$p))) {
            array_walk( $ra, function(&$v, $k) { $v = "'".addslashes($v)."'"; } );
            $raCond[] = "province IN (".implode(',', $ra).")";

// postcodeIn is not implemented: if both are defined it should be raCond[] = (province IN (...) OR postcode IN (...))

        }

        if( ($p = SEEDCore_ArraySmartVal( $raParms, 'lang', ['','EN','FR'] )) ) {
            $p = addslashes(substr($p,0,1));        // E or F
            $raCond[] = "lang in ('','B','$p')";    // '' or B means they want both so any input will match them
        }

        $sCond = implode(' AND ', $raCond );

        if( ($kfrc = $this->oMbrContacts->oDB->GetKFRC('M', $sCond)) ) {
            while( $kfrc->CursorFetch() ) {
                $ra = ['_key' => $kfrc->Key()];
                $raFlds = $eDetail=='office' ? $this->oMbrContacts->GetOfficeFlds() : $this->oMbrContacts->GetBasicFlds();  // sensitive not implemented
                foreach( $raFlds as $k =>$raDummy ) {
                    $ra[$k] = $this->QCharsetFromLatin($kfrc->Value($k));
                }
                $raOut[] = $ra;
            }
            $bOk = true;
        }

        done:
        return( [$bOk, $raOut, "GetList: $sCond", $sErr] );
    }

    private function mbrSearch( $raParms )
    /*************************************
        Find a row in mbr_contacts by searching various fields
            sSearch   : search string
            nMinChars : don't search if sSearch is fewer than this many chars (default 3)
     */
    {
        $bOk = false;
        $raOut = [];
        $sErr = "";

        // impose a lower limit to the number of chars in the search string to prevent unwieldy results
        if( !($nMinChars = intval(@$raParms['nMinChars'])) )  $nMinChars = 3;
        if( strlen( ($dbSearch = addslashes(@$raParms['sSearch']??"")) ) < $nMinChars )  goto done;

        $raM = $this->oMbrContacts->oDB->GetList( 'M',
                            "(_key='{$dbSearch}' OR "
                            // picks up matches in firstname, lastname, or both with space between
                           ."CONCAT(firstname, ' ',lastname)  LIKE '%{$dbSearch}%' OR
                             CONCAT(firstname2,' ',lastname2) LIKE '%{$dbSearch}%' OR
                             company    LIKE '%{$dbSearch}%' OR
                             email      LIKE '%{$dbSearch}%' OR
                             address    LIKE '%{$dbSearch}%' OR
                             city       LIKE '%{$dbSearch}%')" );

        if( $raM ) {
            foreach( $raM as $ra ) {
                // get all elements of $ra that have the same keys as BasicFlds (_key,firstname,lastname,etc)
                $raR = array_intersect_key($ra, $this->oMbrContacts->GetBasicFlds());
                $raR['fullname'] = $this->oMbrContacts->GetContactNameFromMbrRA($ra);

                $raOut[] = $raR;
            }

            $raOut = $this->QCharsetFromLatin($raOut);

            $bOk = true;
        }

        done:
        return( [$bOk, $raOut, $sErr] );
    }

    private function mbrPut( $raParms, $eDetail )
    /********************************************
        Store information about a given contact

        eDetail : basic | office | sensitive

        kMbr : contact key

        N.B. This method will only update a mbr_contact, not create one.
     */
    {
        $bOk = false;
        $sErr = "";

        if( ($kMbr = intval(@$raParms['kMbr'])) && ($kfr = $this->oMbrContacts->oDB->GetKFR('M',$kMbr)) ) {     // CreateRecord not supported
            $raFlds = $eDetail=='office' ? $this->oMbrContacts->GetOfficeFlds() : $this->oMbrContacts->GetBasicFlds();  // sensitive not implemented
            foreach( $raFlds as $k =>$raDummy ) {
                if( isset($raParms[$k]) )  $kfr->SetValue($k, $this->QCharsetToLatin($raParms[$k]));
            }
            $bOk = $kfr->PutDBRow();
        }

        done:
        return( [$bOk, $sErr] );
    }

    private function mbrDonationPrintReceipt( array $raParms, $bOffice = false )
    /***************************************************************************
        bOffice == false:   Requires the donor to be logged in
                            receiptnum : output this single receipt number as pdf, record that this user has accessed it

        bOffice == true:    Only accessible to internal tools
                            receiptnum : can be a range of receipt numbers, output in one pdf, don't record that they have been accessed
     */
    {
        $oDon = new MbrDonations($this->oApp);

        if( ($receiptnum = @$raParms['receiptnum']) ) {
            if( $bOffice ) {
                if( $this->oApp->GetUID() == 1499 )  $oDon->DrawDonationReceipt($receiptnum, 'PDF_STREAM', false);      // receiptnum can be a range string; do not record access
            } else {
                if( ($receiptnum = intval($receiptnum)) && $oDon->CurrentUserCanAccessReceipt($receiptnum) ) {          // receiptnum must be single integer
                    $oDon->DrawDonationReceipt($receiptnum, 'PDF_STREAM', true);                                        // record access
                }
            }
        }

        exit;
    }
}
