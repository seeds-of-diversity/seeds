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

    function TranslateCategory( $sCat ) { return( $this->oMSDCore->TranslateCategory( $sCat ) ); }
    function TranslateSpecies( $sSp )   { return( $this->oMSDCore->TranslateSpecies( $sSp ) ); }
    function TranslateSpecies2( $sSp )  { return( $this->oMSDCore->TranslateSpecies2( $sSp ) ); }

    function KFRelGxM() { return( $this->oMSDCore->KfrelGxM() ); }

    function AdminNormalizeStuff()
    {
        $s = "";

        if( !$this->PermAdmin() ) goto done;

        $this->oApp->kfdb->Execute( "UPDATE seeds.SEEDBasket_Products P,seeds.SEEDBasket_ProdExtra PE "
                                   ."SET PE.v=UPPER(TRIM(v)) "
                                   ."WHERE P.product_type='seeds' AND P._key=PE.fk_SEEDBasket_Products AND PE.k='species'" );

        /* Update offer counts in grower table (should happen after every edit)
         */
        $i = 0;
        if( ($dbc = $this->oMSDCore->oApp->kfdb->CursorOpen( "SELECT mbr_id FROM seeds.sed_curr_growers" )) ) {
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
            $this->oMSDCore->oApp->kfdb->CursorClose($dbc);
        }

        $s = "<p>Removed NULLs, trimmed and upper-cased strings. Updated offer counts for $i growers.</p>";

        done:
        return( $s );
    }

    function AdminCopyToArchive( $year )
    /***********************************
        Delete all archive records for $year.
        Copy current active growers and seeds to archive and give them $year.
     */
    {
        $ok = true;
        $s = "";

        $this->oApp->kfdb->Execute( "DELETE FROM seeds.sed_growers WHERE year='$year'" );
        $this->oApp->kfdb->Execute( "DELETE FROM seeds.sed_seeds WHERE year='$year'" );

        /* Archive growers
         */
        $fields = "mbr_id,mbr_code,frostfree,soiltype,organic,zone,cutoff,notes, _created,_created_by,_updated,_updated_by";
        $sql = "INSERT INTO seeds.sed_growers (_key,_status, year, $fields )"
              ."SELECT NULL,0, '$year', $fields "
              ."FROM seeds.sed_curr_growers WHERE _status=0 AND NOT bSkip AND NOT bDelete";
        if( $this->oApp->kfdb->Execute($sql) ) {
            $s .= "<h4 style='color:green'>Growers Successfully Archived</h4>"
                 ."<p style='margin-left:30px'><pre>$sql</pre></p>";
        } else {
            $s .= "<h4 style='color:red'>Archiving Growers Failed</h4>"
                 ."<p style='margin-left:30px'><pre>$sql</pre></p>"
                 ."<p style='margin-left:30px'><pre>".$this->oApp->kfdb->GetErrMsg()."</pre></p>";
            $ok = false;
        }


        /* Archive seeds
         */
        $fields = "mbr_id,category,type,variety,bot_name,days_maturity,quantity,origin,year_1st_listed,description";
        $sql = "INSERT INTO seeds.sed_seeds (_key,_created,_created_by,_updated,_updated_by,_status, '$year', $fields )"
              ."SELECT NULL,NOW(),".$this->oApp->sess->GetUID().",NOW(),".$this->oApp->sess->GetUID().",0, $fields "
              ."FROM seeds.sed_curr_seeds WHERE _status=0 AND NOT bSkip AND NOT bDelete";
        if( $this->oApp->kfdb->Execute($sql) ) {
            $s .= "<h4 style='color:green'>Seeds Successfully Archived</h3>"
                 ."<p style='margin-left:30px'><pre>$sql</pre></p>";
        } else {
            $s .= "<h4 style='color:red'>Archiving Seeds Failed</h4>"
                 ."<p style='margin-left:30px'><pre>$sql</pre></p>"
                 ."<p style='margin-left:30px'><pre>".$this->oApp->kfdb->GetErrMsg()."</pre></p>";
            $ok = false;
        }

        return( array( $ok, $s ) );
    }

    function DrawGrowerBlock( KeyFrameRecord $kfrGxM, $bFull = true )
    {
        $s = $kfrGxM->Expand( "<b>[[mbr_code]]: [[M_firstname]] [[M_lastname]] ([[mbr_id]]) " )
             .($kfrGxM->value('organic') ? $this->S('Organic') : "")."</b>"
             ."<br/>";

        if( $bFull ) {
            $s .= $kfrGxM->ExpandIfNotEmpty( 'company', "<strong>[[]]</strong>><br/>" )
                 .$kfrGxM->Expand( "[[M_address]], [[M_city]] [[M_province]] [[M_postcode]]<br/>" );

            $s1 = "";
            if( !$kfrGxM->value('unlisted_email') )  $s1 .= $kfrGxM->ExpandIfNotEmpty( 'M_email', "<i>[[]]</i>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" );
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
                $s .= "<i>Payment method: $sPM</i><br/>";
            }

            $s .= $kfrGxM->ExpandIfNotEmpty( 'notes', "Notes: [[]]<br/>" );
        }

        $s = "<div style='font-size:8pt;font-family:Minion Pro;padding-bottom:2pt'>$s</div>";
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
