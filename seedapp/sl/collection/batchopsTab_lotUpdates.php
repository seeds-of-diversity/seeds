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
        $oFormL = new SEEDCoreFormSVA( $this->oSVA, 'L', ['fields'=>['defCtrlLocation'=>['control'=>'checkbox'],
                                                                     'defCtrlGrams'   =>['control'=>'checkbox'],
                                                                     'defCtrlDeAcc'   =>['control'=>'checkbox'] ]] );
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
                ."<form>{$this->drawLeftForm($oFormL)}<br/><br/><input type='submit' value='Edit Lots'/></form>"
                ."</div>"
            ."</div>"
            ."<div class='col-md-6'>"
                .($sResults ? "<div style='background-color:#ddd;margin:10px;padding:10px;border-radius:5px'>$sResults</div>" : "")
                ."<form>{$this->drawRightForm($oFormR,$oFormL,$raLots)}<br/><br/><input type='submit'></form>"
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
                if( $ra['values']['location'] && $kfr->Value('location') != $ra['values']['location'] ) {
                    $sResults .= "<p>Change {$kfr->Value('location')} to {$ra['values']['location']} for Lot #$kLot</p>";
                }
            }
        }

        return( $sResults );
    }

    private function getCurrentLots( $rLots )
    /****************************************
        array of [kLot, kfr, cv] and/or [kLot, null, 'Unknown Cultivar']
     */
    {
        $raLots = [];
        foreach( SEEDCore_ParseRangeStrToRA($rLots) as $kLot ) {
            $raLots[$kLot] = ['kLot'=>$kLot, 'kfr'=>$this->kfrLot($kLot)];
            $raLots[$kLot]['cv'] = $raLots[$kLot]['kfr'] ? $raLots[$kLot]['kfr']->Value('P_name') : 'Unknown Cultivar';
        }

        return( $raLots );
    }

    private function kfrLot( $kLot )
    {
        return( $this->oSLDB->GetKFRCond( 'IxAxPxS', "fk_sl_collection='1' AND inv_number='$kLot'") );
    }

    private function drawLeftForm( SEEDCoreForm $oFormL )
    {
        $s = "<div>Lot numbers:<br/>".$oFormL->Text('rLots')."</div>"
            ."<div>{$oFormL->Checkbox('defCtrlLocation')} Location</div>"
            ."<div>{$oFormL->Checkbox('defCtrlGrams')} Grams</div>"
            ."<div>{$oFormL->Checkbox('defCtrlDeAcc')} Deaccession</div>";

        return( $s );
    }

    private function drawRightForm( SEEDCoreForm $oFormR, SEEDCoreForm $oFormL, $raLots )
    {
        $sR = "";

        foreach( $raLots as $ra ) {
            $sCtrlLoc = $sCtrlGrams = $sCtrlDeAcc = "";
            if( $ra['kfr'] ) {
                $sCtrlLoc .= $oFormL->Value('defCtrlLocation') ? $oFormR->Text('location') : "";
                $sCtrlGrams .= $oFormL->Value('defCtrlGrams') ? $oFormR->Text('g_weight') : "";
                $sCtrlDeAcc .= $oFormL->Value('defCtrlDeAcc') ? $oFormR->Text('bDeAcc') : "";
            }

            $sR .= "<div style='margin-bottom:10px;padding:10px;background-color:#ddd'>"
                      ."<table border='0'>"
                          ."<tr><td>Lot {$ra['kLot']}: <em>{$ra['cv']}</em></td><td>&nbsp</td></tr>"
                          .($ra['kfr'] ?
                              ("<tr><td>Location: {$ra['kfr']->Value('location')}</td><td>$sCtrlLoc</td></tr>"
                              ."<tr><td>Grams: {$ra['kfr']->Value('g_weight')}</td><td>$sCtrlGrams</td></tr>"
                              ."<tr><td>Deaccessioned: {$ra['kfr']->Value('bDeAcc')}</td><td>$sCtrlDeAcc</td></tr>")
                              : "")
                      ."</table>"
                  ."</div>";

            if( $ra['kfr'] ) {
                $sR .= $oFormR->Hidden( 'kLot', ['value'=>$ra['kLot']] );
                $oFormR->IncRowNum();
            }
        }

        return( $sR );
    }
}