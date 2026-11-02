<?php

/* SL Report UI
 *
 * Copyright 2026 Seeds of Diversity Canada
 *
 * Common methods for reporting cultivarData
 */


class SLReportUI
{
    private $oApp;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
    }

    static function Style()
    {
        return( "
            <style>
            .slreportui_packetLabel   { border:1px solid #888; border-radius:5px; padding:10px; margin-bottom:10px; max-width:30em; }
            .slreportui_block_heading { position: relative !important; background-color:#777 !important; padding:5px 15px !important;
                                        font-size:14pt !important; font-weight:bold !important; color:white !important; }
            </style>
        ");
    }

    static function DrawCultivarName( array $raCVData )
    {
        $sNameSpecies = @$raCVData['PxS']['S_name_en'] ?: "[Unknown species]";
        $sNameCultivar = @$raCVData['PxS']['P_name']   ?: "[Unknown cultivar]";
        return( "{$sNameCultivar} {$sNameSpecies}" );
    }

    static function DrawPacketLabel( array $raCVData )   { return( ($p = @$raCVData['PxS']['P_packetLabel']) ? "<div class='slreportui_packetLabel'>$p</div>" : "" ); }

    static function DrawSources( array $raCVData )
    {
        $s = "";

        if(($raSrc = $raCVData['raSrc'])) {
            $cSrc = count($raSrc);
            $s = "<div class='slreportui_block_heading'>Where you can buy it in Canada".($cSrc ? (" ($cSrc companies)") : "")."</div>";
            if( $cSrc ) {
                $s .= "<div style='border:1px solid white;max-height:200px;overflow-y:auto;width:50%'>"
                         .SEEDCore_ArrayExpandRows( $raSrc,
                                                    "<div><a href='http://[[SRC_web]]' target='_blank'>[[SRC_name_en]] in [[SRC_prov]]</a></div>" )
                         ."</div>";
            } else {
                $s .= "<p>We don't know of any Canadian seed companies that sell this. If you do, please tell us.</p>";
            }
        }
        return($s);
    }

    static function DrawCultivarReferences( array $raCVData )
    {
        $s = "<strong>Cultivar References: {$raCVData['nTotal']}</strong><br/><br/>
              Seed Library accessions: {$raCVData['nAcc']}<br/>
              Source list records: "
            .($raCVData['nSrcCv1'] ? "PGRC, " : "")
            .($raCVData['nSrcCv2'] ? "NPGS, " : "")
            .("{$raCVData['nSrcCv3']} compan".($raCVData['nSrcCv3'] == 1 ? "y" : "ies"))."<br/>"
            ."Adoptions: {$raCVData['nAdopt']}<br/>
              Profile Observations: {$raCVData['nDesc']}<br/>";

        $s = "<div style='border:1px solid #aaa;padding:10px'>$s</div>";

        return($s);
    }

    static function DrawCultivarSynonyms( array $raCVData, array $raParms = [] )
    {
        // render differently in internal/public views
        $bFmt1 = !isset($raParms['fmt']) || $raParms['fmt']=='internal';
        $bFmt2 = @$raParms['fmt']=='public';

        $s = $raCVData['raPY']
                ? ("<b>Also known as</b><div style='margin:0px 20px'>".SEEDCore_ArrayExpandRows($raCVData['raPY'],"[[name]]<br/>")."</div>")
                : "";

        if( $bFmt1 && $s ) $s = "<div style='border:1px solid #aaa;padding:10px'>$s</div>";

        return($s);
    }

    static function Rosetta_About_Cultivar( array $raCVData, SLDBRosetta $sldb )
    {

    }
}
