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
}
