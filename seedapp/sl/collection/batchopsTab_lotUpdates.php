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

    private $bCtrlSame = false;     // true if one set of controls applies to all Lots

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
                                                                     'defCtrlDeAcc'   =>['control'=>'checkbox'],
                                                                     'defCtrlSame'    =>['control'=>'checkbox']
        ]] );
        $oFormL->Update();
        $this->bCtrlSame = $oFormL->Value('defCtrlSame');

        // The right form contains controls for the defined Lots
        $oFormR = new SEEDCoreForm( 'R' );
        //$oFormR->Update();  processUpdates uses Deserialize to examine the response directly

        $sResults = $this->processUpdates( $oFormR );

        $s = "<div class='container-fluid'><div class='row'>"

            ."<div class='col-md-6'>"
                ."<div style='margin:15px;padding:15px;border:1px solid #aaa'>"
                ."<form>{$this->drawLeftForm($oFormL, $oFormR)}<br/><input type='submit' value='Edit Lots'/></form>"
                ."</div>"
            ."</div>"
            ."<div class='col-md-6'>"
                .($sResults ? "<div style='background-color:#ddd;margin:10px;padding:10px;border-radius:5px'>$sResults</div>" : "")
                ."<form>{$this->drawRightForm($oFormL,$oFormR)}<br/><input type='submit'></form>"
            ."</div>"

            ."</div></div>";

        return( $s );
    }

    private function processUpdates( SEEDCoreForm $oFormR )
    {
        $sResults = "";

        $raRows = $oFormR->GetSEEDFormParms()->Deserialize( $_REQUEST )['rows'];
        if( $this->bCtrlSame && count($raRows) ) {
            // row 0 contains the values that apply to all Lots
            $pLocation = $raRows[0]['values']['location'];
            $pGrams    = $raRows[0]['values']['g_weight'];
            $pDeAcc    = $raRows[0]['values']['bDeAcc'];
        }

        foreach( $raRows as $ra ) {
            // $ra is the sf-encoded parms for each row of the form
            $kLot = $ra['values']['kLot'];
            if( ($kfr = $this->kfrLot($kLot)) ) {
                $p = $this->bCtrlSame ? $pLocation : $ra['values']['location'];
                if( $p && $p != $kfr->Value('location') ) {
                    $sResults .= "<p>Change {$kfr->Value('location')} to {$p} for Lot #$kLot</p>";
                }

                $p = $this->bCtrlSame ? $pGrams : $ra['values']['g_weight'];
                if( $p && $p != $kfr->Value('g_weight') ) {
                    $sResults .= "<p>Change {$kfr->Value('g_weight')} to {$p} for Lot #$kLot</p>";
                }

                $p = $this->bCtrlSame ? $pDeAcc : $ra['values']['bDeAcc'];
                if( $p && $p != $kfr->Value('bDeAcc') ) {
                    $sResults .= "<p>Change {$kfr->Value('bDeAcc')} to {$p} for Lot #$kLot</p>";
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

    private function drawLeftForm( SEEDCoreForm $oFormL, SEEDCoreForm $oFormR )
    {
        $s = "<div>Lot numbers:<br/>".$oFormL->Text('rLots')."</div>"
            ."<div>{$oFormL->Checkbox('defCtrlLocation')} Location</div>"
            ."<div>{$oFormL->Checkbox('defCtrlGrams')} Grams</div>"
            ."<div>{$oFormL->Checkbox('defCtrlDeAcc')} Deaccession</div>"
            ."<div style='margin-top:15px'>{$oFormL->Checkbox('defCtrlSame')} Same for every Lot</div>";

        return( $s );
    }

    private function drawRightForm( SEEDCoreForm $oFormL, SEEDCoreForm $oFormR )
    {
        $sR = "";

        foreach( $this->getCurrentLots( $oFormL->Value('rLots') ) as $ra ) {
            $sCtrlLoc = $sCtrlGrams = $sCtrlDeAcc = "";
            if( !$this->bCtrlSame && $ra['kfr'] ) {
                // make separate controls for each Lot
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

        if( $this->bCtrlSame ) {
            // make one set of controls that will apply to all Lots
            $oFormR->SetRowNum(0);  // values will be found in sf-row 0
            $sR .= "<div style='margin:10px;padding:10px;border:1px solid #aaa'>Apply Changes to All Lots"
                  .($oFormL->Value('defCtrlLocation') ? "<div>{$oFormR->Text('location')} Location </div>" : "")
                  .($oFormL->Value('defCtrlGrams') ? "<div>{$oFormR->Text('g_weight')} Grams</div>" : "")
                  .($oFormL->Value('defCtrlDeAcc') ? "<div>{$oFormR->Text('bDeAcc')} Deaccessioned</div>" : "")
                  ."</div>";
        }

        return( $sR );
    }
}