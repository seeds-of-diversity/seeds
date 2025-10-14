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
    static function GetPCVReport( SEEDAppSession $oApp, int $kPcv, $sModes = " ALL " )
    /*********************************************************************************
        Get references and stats about the current pcv
     */
    {
        $raOut = ['syn'=>"", 'stats'=>""];

        $bGetSyn   = stripos($sModes, " SYN ") !== false    || stripos($sModes, " ALL ") !== false;
        $bGetStats = stripos($sModes, " STATS ") !== false  || stripos($sModes, " ALL ") !== false;

        $rQ = (new QServerRosetta($oApp))->Cmd('rosetta-cultivarinfo', ['kPcv'=>$kPcv, 'mode'=>'all']);
        if( $rQ['bOk'] ) {
            $raCvOverview = $rQ['raOut'];

            if( $bGetStats ) {
                $s1 =
                     "<strong>Cultivar References: {$raCvOverview['nTotal']}</strong><br/><br/>"
                    ."Seed Library accessions: {$raCvOverview['nAcc']}<br/>"
                    ."Source list records: "
                        .($raCvOverview['nSrcCv1'] ? "PGRC, " : "")
                        .($raCvOverview['nSrcCv2'] ? "NPGS, " : "")
                        .("{$raCvOverview['nSrcCv3']} compan".($raCvOverview['nSrcCv3'] == 1 ? "y" : "ies"))."<br/>"
                    ."Adoptions: {$raCvOverview['nAdopt']}<br/>"
                    ."Profile Observations: {$raCvOverview['nDesc']}<br/>";

                $raOut['stats'] = "<div style='border:1px solid #aaa;padding:10px'>$s1</div>";
            }

            if( $bGetSyn ) {
                $s1 = $rQ['raOut']['raPY']
                            ? ("<b>Also known as</b><div style='margin:0px 20px'>".SEEDCore_ArrayExpandRows($rQ['raOut']['raPY'],"[[name]]<br/>")."</div>")
                            : "";
                if( $s1 ) $raOut['syn'] = "<div style='border:1px solid #aaa;padding:10px'>$s1</div>";
            }
        }

        return( $raOut );
    }
}