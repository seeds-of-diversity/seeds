<?php

/* Seed collection manager - batch operations - update Lot information in batches
 *
 * Copyright 2021 Seeds of Diversity Canada
 */

include_once( SEEDCORE."SEEDCoreFormSession.php" );

class CollectionBatchOps_UpdateLots
{
    private $oApp;
    private $oSVA;  // where to keep state info for this tool
    private $oSLDB;

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;
        $this->oSLDB = new SLDBCollection($this->oApp);
    }

    function Draw()
    {
        $s = "";

        // The left form contains rLots and defCtrl*, which define what is drawn on the right form.
        $oFormL = new SEEDCoreFormSVA( $this->oSVA, 'L' );
        $oFormL->Update();
        $rLots = $oFormL->Value('rLots');
        $bCtrlsSame = $oFormL->Value('bCtrlsSame');

        // The right form contains controls for the defined Lots
        $oFormR = new SEEDCoreForm( 'R' );
        //$oFormR->Update();  processUpdates uses Deserialize to examine the response directly

        $sResults = $this->processUpdates( $oFormR );
        $raLots = $this->getCurrentLots( $rLots );

        $s = "<div class='container-fluid'><div class='row'>"

            ."<div class='col-md-6'>"
                ."<div style='margin:15px;padding:15px;border:1px solid #aaa'>"
                ."<form>{$this->drawLeftForm($oFormL)}<br/><br/><input type='submit' value='Use These Lot Numbers'/></form>"
                ."</div>"
            ."</div>"
            ."<div class='col-md-6'>"
                .($sResults ? "<div style='background-color:#ddd;margin:10px;padding:10px;border-radius:5px'>$sResults</div>" : "")
                ."<form>{$this->drawRightForm($oFormR,$raLots)}<br/><br/><input type='submit'></form>"
            ."</div>"

            ."</div></div>";

        return( $s );
    }

    private function processUpdates( SEEDCoreForm $oFormR )
    {
        $sResults = "";

        $raRows = $oFormR->GetSEEDFormParms()->Deserialize( $_REQUEST )['rows'];
        foreach( $raRows as $ra ) {
            // $ra is the sf-encoded parms for each row of the form
            $kLot = $ra['values']['kLot'];
            if( ($kfr = $this->kfrLot($kLot)) ) {
                if( $kfr->Value('P_name') != $ra['values']['cv'] ) {
                    $sResults .= "<p>Change {$kfr->Value('P_name')} to {$ra['values']['cv']} for Lot #$kLot</p>";
                }
            }
        }

        return( $sResults );
    }

    private function getCurrentLots( $rLots )
    {
        $raLots = [];
        foreach( SEEDCore_ParseRangeStrToRA($rLots) as $kLot ) {
            $raLots[$kLot] = ['kLot'=>$kLot, 'cv'=>'Unknown Cultivar', 'bUnknown'=>true];

            if( ($kfr = $this->kfrLot($kLot)) ) {
                $raLots[$kLot]['cv'] = $kfr->Value('P_name');
                $raLots[$kLot]['bUnknown'] = false;
            }
        }

        return( $raLots );
    }

    private function kfrLot( $kLot )
    {
        return( $this->oSLDB->GetKFRCond( 'IxAxPxS', "fk_sl_collection='1' AND inv_number='$kLot'") );
    }
    private function drawLeftForm( SEEDCoreForm $oFormL )
    {
        $s = $oFormL->Text('rLots');

        return( $s );
    }

    private function drawRightForm( SEEDCoreForm $oFormR, $raLots )
    {
        $sR = "";
        foreach( $raLots as $ra ) {
            $oFormR->SetValue('cv', $ra['cv']);
            $sR .= "<div style='margin-bottom:10px;padding:10px;background-color:#ddd'>Lot {$ra['kLot']}: <em>{$ra['cv']}</em>";
            if( !$ra['bUnknown'] ) {
                $sR .= "<br/><br/>".$oFormR->Text('cv')
                      .$oFormR->Hidden( 'kLot', ['value'=>$ra['kLot']] );
                $oFormR->IncRowNum();
            }
            $sR .= "</div>";
        }

        return( $sR );
    }
}