<?php

/* sl_integrity.php
 *
 * Copyright 2024 Seeds of Diversity Canada
 *
 * Tests and reports about Seed LIbrary data integrity
 */

include_once( SEEDLIB."sl/QServerRosetta.php" );

class SLIntegrity
{
    static function GetPCVReport( SEEDAppSession $oApp, int $kPcv )
    /**************************************************************
        Get references and stats about the current pcv
     */
    {
        $sSyn = $sStats = "";

        $rQ = (new QServerRosetta($oApp))->Cmd('rosetta-cultivaroverview', ['kPcv'=>$kPcv]);
        if( $rQ['bOk'] ) {
            $raCvOverview = $rQ['raOut'];

            $sStats =
                 "<strong>Cultivar References: {$raCvOverview['nTotal']}</strong><br/><br/>"
                ."Seed Library accessions: {$raCvOverview['nAcc']}<br/>"
                ."Source list records: "
                    .($raCvOverview['nSrcCv1'] ? "PGRC, " : "")
                    .($raCvOverview['nSrcCv2'] ? "NPGS, " : "")
                    .("{$raCvOverview['nSrcCv3']} compan".($raCvOverview['nSrcCv3'] == 1 ? "y" : "ies"))."<br/>"
                ."Adoptions: {$raCvOverview['nAdopt']}<br/>"
                ."Profile Observations: {$raCvOverview['nDesc']}<br/>";

            $sStats = "<div style='border:1px solid #aaa;padding:10px'>$sStats</div>";

            $sSyn = $rQ['raOut']['raPY']
                        ? ("<b>Also known as</b><div style='margin:0px 20px'>".SEEDCore_ArrayExpandRows($rQ['raOut']['raPY'],"[[name]]<br/>")."</div>")
                        : "";
            if( $sSyn ) $sSyn = "<div style='border:1px solid #aaa;padding:10px'>$sSyn</div>";
        }

        return( [$sSyn,$sStats] );
    }
}