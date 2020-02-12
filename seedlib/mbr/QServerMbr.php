<?php

/* QServerMbr
 *
 * Copyright 2019-2020 Seeds of Diversity Canada
 *
 * Contacts Q layer
 */

include_once( "MbrContacts.php" );

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
        $rQ = $this->GetEmptyRQ();

        if( SEEDCore_StartsWith( $cmd, 'mbr---' ) ) {
            $rQ['bHandled'] = true;

            if( !$this->oApp->sess->CanAdmin('MBR') ) {
                $rQ['sErr'] = "<p>You do not have permission to admin mbr information.</p>";
                goto done;
            }
        } else
        if( SEEDCore_StartsWith( $cmd, 'mbr--' ) ) {
            $rQ['bHandled'] = true;

            if( !$this->oApp->sess->CanWrite('MBR') ) {
                $rQ['sErr'] = "<p>You do not have permission to change mbr information.</p>";
                goto done;
            }
        } else
        if( SEEDCore_StartsWith( $cmd, 'mbr-' ) ) {
            $rQ['bHandled'] = true;

            if( !$this->oApp->sess->CanRead('MBR') ) {
                $rQ['sErr'] = "<p>You do not have permission to read mbr information.</p>";
                goto done;
            }
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
            case 'mbr-getBasic':
                list($rQ['bOk'],$rQ['raOut'],$rQ['sErr']) = $this->mbrGet( $raParms, 'basic' );
                break;
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

            /* Write commands
             */
            case 'mbr--putBasic':   list($rQ['bOk'],$rQ['sErr']) = $this->mbrPut( $raParms, 'basic' );     break;
            case 'mbr--putOffice':  list($rQ['bOk'],$rQ['sErr']) = $this->mbrPut( $raParms, 'office' );    break;
            // only accessible by internal code
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
     */
    {
        $bOk = false;
        $raOut = array();
        $sErr = "";

        if( ($kMbr = intval(@$raParms['kMbr'])) && ($kfr = $this->oMbrContacts->oDB->GetKFR('M',$kMbr)) ) {
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
        $raOut = array();
        $sErr = "";

        // impose a lower limit to the number of chars in the search string to prevent unwieldy results
        if( !($nMinChars = intval(@$raParms['nMinChars'])) )  $nMinChars = 3;
        if( strlen( ($sSearch = @$raParms['sSearch']) ) < $nMinChars )  goto done;

        $raM = $this->oApp->kfdb->QueryRowsRA( "SELECT * FROM seeds2.mbr_contacts WHERE _status='0' AND "
                                                ."(_key='$sSearch' OR "
                                                 ."firstname LIKE '%$sSearch%' OR "
                                                 ."lastname  LIKE '%$sSearch%' OR "
                                                 ."company   LIKE '%$sSearch%' OR "
                                                 ."email     LIKE '%$sSearch%' OR "
                                                 ."address   LIKE '%$sSearch%' OR "
                                                 ."city      LIKE '%$sSearch%')" );

        if( $raM && count($raM) ) {
            foreach( $raM as $ra ) {
                $raR = array();
                $raR['_key']      = $ra['_key'];
                $raR['firstname'] = $this->QCharSet($ra['firstname']);
                $raR['lastname']  = $this->QCharSet($ra['lastname']);
                $raR['company']   = $this->QCharSet($ra['company']);
                $raR['email']     = $this->QCharSet($ra['email']);
                $raR['phone']     = $this->QCharSet($ra['phone']);
                $raR['address']   = $this->QCharSet($ra['address']);
                $raR['city']      = $this->QCharSet($ra['city']);
                $raR['province']  = $this->QCharSet($ra['province']);
                $raR['postcode']  = $this->QCharSet($ra['postcode']);

                $raOut[] = $raR;
            }

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
}

