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
            .slreportui_packetLabel { border:1px solid #888; border-radius:5px; padding:10px; margin-bottom:10px; max-width:30em; }
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
}