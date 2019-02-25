<?php

/* msdlib
 *
 * Copyright (c) 2009-2019 Seeds of Diversity
 *
 * Support for MSD app-level code that shouldn't know about MSDCore but can't get what it needs from MSDQ.
 *
 * Office-Admin functions.
 */

require_once "msdcore.php";
require_once SEEDCORE."SEEDProblemSolver.php";


class MSDLib
{
    public  $oApp;
    private $oMSDCore;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oMSDCore = new MSDCore( $oApp, array() );
    }

    function PermOfficeW()  { return( $this->oMSDCore->PermOfficeW() ); }
    function PermAdmin()    { return( $this->oMSDCore->PermAdmin() ); }

    function GetCurrYear()  { return( $this->oMSDCore->GetCurrYear() ); }

    function GetSpeciesNameFromKey( $kSp ) { return( $this->oMSDCore->GetKlugeSpeciesNameFromKey( $kSp ) ); }

    function KFRelGxM() { return( $this->oMSDCore->KfrelGxM() ); }

    function AdminNormalizeStuff()
    {
        $s = "";

        if( !$this->PermAdmin() ) goto done;

        if( ($dbc = $this->oMSDCore->oApp->kfdb->CursorOpen( "SELECT mbr_id FROM seeds.sed_curr_growers" )) ) {
            $i = 0;
            while( $ra = $this->oMSDCore->oApp->kfdb->CursorFetch($dbc) ) {
                $sCond = "mbr_id='{$ra['mbr_id']}' AND _status='0' AND NOT bSkip AND NOT bDelete";

                $sql = "SELECT count(*) FROM seeds.SEEDBasket_Products P,seeds.SEEDBasket_ProdExtra PE "
                      ."WHERE P._key=PE.fk_SEEDBasket_Products AND P._status='0' AND P.product_type='seeds' AND "
                            ."P.uid_seller='{$ra['mbr_id']}' AND P.eStatus='ACTIVE' AND PE.k='category' ";

                $nTotal  = $this->oMSDCore->oApp->kfdb->Query1( $sql );
                $nFlower = $this->oMSDCore->oApp->kfdb->Query1( $sql."  AND v='flowers'" );
                $nFruit  = $this->oMSDCore->oApp->kfdb->Query1( $sql."  AND v='fruit'" );
                $nGrain  = $this->oMSDCore->oApp->kfdb->Query1( $sql."  AND v='grain'" );
                $nHerb   = $this->oMSDCore->oApp->kfdb->Query1( $sql."  AND v='herbs'" );
                $nTree   = $this->oMSDCore->oApp->kfdb->Query1( $sql."  AND v='trees'" );
                $nVeg    = $this->oMSDCore->oApp->kfdb->Query1( $sql."  AND v='vegetables'" );
                $nMisc   = $this->oMSDCore->oApp->kfdb->Query1( $sql."  AND v='misc'" );

                $this->oMSDCore->oApp->kfdb->Execute(
                        "UPDATE seeds.sed_curr_growers "
                       ."SET nTotal='$nTotal',nFlower='$nFlower',nFruit='$nFruit',"
                       ."nGrain='$nGrain',nHerb='$nHerb',nTree='$nTree',nVeg='$nVeg',nMisc='$nMisc' "
                       ."WHERE mbr_id='{$ra['mbr_id']}'");
                ++$i;
            }
            $s = "<p>Removed NULLs, trimmed and upper-cased strings. Updated offer counts for $i growers.</p>";
            $this->oMSDCore->oApp->kfdb->CursorClose($dbc);
        }


        done:
        return( $s );
    }

    function DrawGrowerBlock( KeyFrameRecord $kfrGxM, $bFull = true )
    {
        $s = $kfrGxM->Expand( "<strong>[[mbr_code]]: [[M_firstname]] [[M_lastname]] ([[mbr_id]]) " )
             .($kfrGxM->value('organic') ? $this->S('Organic') : "")."</strong>"
             ."<br/>";

        if( $bFull ) {
            $s .= $kfrGxM->ExpandIfNotEmpty( 'company', "<strong>[[]]</strong>><br/>" )
                 .$kfrGxM->Expand( "[[M_address]], [[M_city]] [[M_province]] [[M_postcode]]<br/>" );

            $s1 = "";
            if( !$kfrGxM->value('unlisted_email') )  $s1 .= $kfrGxM->ExpandIfNotEmpty( 'M_email', "[[]]&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" );
            if( !$kfrGxM->value('unlisted_phone') )  $s1 .= $kfrGxM->ExpandIfNotEmpty( 'M_phone', "[[]]" );
            if( $s1 )  $s .= $s1."<br/>";

            $s .= $kfrGxM->ExpandIfNotEmpty( 'cutoff', "No requests after: [[]]<br/>" );

            $s1 = $kfrGxM->ExpandIfNotEmpty( 'frostfree', "[[]] frost free days. " )
                 .$kfrGxM->ExpandIfNotEmpty( 'soiltype',  "Soil: [[]]. " )
                 .$kfrGxM->ExpandIfNotEmpty( 'zone',      "Zone: [[]]. " );
            if( $s1 )  $s .= $s1."<br/>";

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

            if( ($sPM = $this->drawPaymentMethod($kfrGxM)) ) {
                $s .= "<em>Payment method: $sPM</em><br/>";
            }

            $s .= $kfrGxM->ExpandIfNotEmpty( 'notes', "Notes: [[]]<br/>" );
        }

        return( $s );
    }

    private function drawPaymentMethod( $kfrGxM )
    {
        $ra = array();
        if( $kfrGxM->value('pay_cash') )        $ra[] = "Cash";
        if( $kfrGxM->value('pay_cheque') )      $ra[] = "Cheque";
        if( $kfrGxM->value('pay_stamps') )      $ra[] = "Stamps";
        if( $kfrGxM->value('pay_ct') )          $ra[] = "Canadian-Tire";
        if( $kfrGxM->value('pay_mo') )          $ra[] = "Money order";
        if( !$kfrGxM->IsEmpty('pay_other') )    $ra[] = $kfrGxM->value('pay_other');

        return( implode( ", ", $ra ) );
    }

    private function S( $sTranslate )
    {
        if( $sTranslate == 'Organic' ) return( $this->oApp->lang=='EN' ? $sTranslate : "Biologique" );

        return( $sTranslate );
    }
}
