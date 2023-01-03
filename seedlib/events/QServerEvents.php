<?php

/* QServerEvents
 *
 * Copyright 2020 Seeds of Diversity Canada
 *
 * Serve queries about events
 */

include_once( SEEDLIB."q/Q.php" );
include_once( "events.php" );

class QServerEvents extends SEEDQ
{
    private $oE;

    function __construct( SEEDAppSessionAccount $oApp, $raConfig = [] )
    {
        parent::__construct( $oApp, $raConfig );
        $this->oE = new EventsLib( $oApp );
    }

    function Cmd( $cmd, $parms )
    {
        $rQ = $this->GetEmptyRQ();

        //if( $cmd == 'evHelp' ) {
        //    $rQ['bHandled'] = true;
        //    $rQ['bOk'] = true;
        //    $rQ['sOut'] = $this->sHelp;
        //}

        if( $cmd == 'evList' ) {
            $rQ['bHandled'] = true;
            $raParms = $parms; // $this->normalizeParms( $parms );

            $rQ['sLog'] = SEEDCore_ImplodeKeyValue( $raParms, "=", "," );

            list($rQ['bOk'],$rQ['raOut'],$rQ['sOut'],$rQ['sErr']) = $this->getEvList( $raParms );
        } else
        if( $cmd == 'ev-syncSheet' ){
            $rQ['bHandled'] = true;
            $raParms = $parms; // $this->normalizeParms( $parms );
            $rQ['sLog'] = SEEDCore_ImplodeKeyValue( $raParms, "=", "," );

            list($rQ['bOk'],$rQ['sErr']) = $this->syncSheet($raParms);
        }

        return( $rQ );
    }

    private function getEvList( $parms )
    {
        $bOk = false;
        $raOut = [];
        $sOut = $sErr = "";

        $cond = "date_start >= CURRENT_DATE()";
        if( ($p = @$parms['prov']) ) $cond .= " AND province='".addslashes($p)."'";

        $raE = $this->oE->oDB->GetList( 'E', $cond, ['sSortCol'=>"date_start,province,city"] );
        foreach( $raE as $ra ) {
            $ra1 = [];
            foreach( ['_key','type','date_start','title','city','province'] as $k ) {
                $ra1[$k] = $this->QCharsetFromLatin($ra[$k]);
            }
            $raOut[] = $ra1;
        }
        $sOut = "<table>".SEEDCore_ArrayExpandRows( $raOut, "<tr><td>[[date_start]]</td><td>[[city]]</td><td>[[province]]</td></tr>" )."</table>";

        $bOk = true;

        return([$bOk, $raOut, $sOut, $sErr]);
    }

    private function syncSheet( $raParms )
    /*************************************
        Sync the current events db with the EVENTS_GOOGLE_SPREADSHEET_ID google sheet
     */
    {
        $sSheetName = SEED_isLocal ? "Current local" : "Form responses 1";
        include_once( SEEDLIB."events/eventsSheet.php" );
        (new EventsSheet($this->oApp))->SyncSheetAndDB($sSheetName);
        return( [true, ""] );
    }
}
