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

    private $oFormL;
    private $bCtrlSame = false;     // true if one set of controls applies to all Lots

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;
        $this->oSLDB = new SLDBCollection($this->oApp);

        // The left form contains rLots and defCtrl*, which define what is drawn on the right form.
        $this->oFormL = new SEEDCoreFormSVA( $this->oSVA, 'L',
                                             ['fields'=>['defCtrl_location'=>['control'=>'checkbox'],
                                                         'defCtrl_grams'   =>['control'=>'checkbox'],
                                                         'defCtrl_bDeAcc'  =>['control'=>'checkbox'],
                                                         'defCtrl_same'    =>['control'=>'checkbox']
        ]] );
        $this->oFormL->Update();
        $this->bCtrlSame = $this->oFormL->Value('defCtrl_same');

        // The right form contains controls for the defined Lots
        $this->oFormR = new SEEDCoreForm( 'R', ['fields'=>['bDeAcc'=>['control'=>'checkbox']]] );
        //$this->oFormR->Update();  processUpdates uses Deserialize to examine the response directly
    }

    function Draw()
    {
        $s = "";

        $sResults = $this->processUpdates();

        $s = "<div class='container-fluid'><div class='row'>"

            ."<div class='col-md-6'>"
                ."<div style='margin:15px;padding:15px;border:1px solid #aaa'>"
                ."<form>{$this->drawLeftForm()}<br/><input type='submit' value='Edit Lots'/></form>"
                ."</div>"
                .($sResults ? "<div style='background-color:#eee;margin:10px;padding:10px;border-radius:5px'>$sResults</div>" : "")
            ."</div>"
            ."<div class='col-md-6'>"
                ."<form>{$this->drawRightForm()}<br/><input type='submit'></form>"
            ."</div>"

            ."</div></div>";

        return( $s );
    }

    private function isUpdatable( $fld )    // evaluate whether location, grams, etc controls are defined
    {
        return( $this->oFormL->Value("defCtrl_$fld") );
    }

    private function processUpdates()
    {
        $sResults = "";

        /* Reading form parms directly, not through SEEDCoreForm::Update().
         * bDeAcc is not recognized as a checkbox so 0 values will be missing.
         */
        $raRows = $this->oFormR->GetFormParms()->Deserialize($_REQUEST)['rows'];

        /* If bCtrlSame, the submitted values apply to all Lots and they are found in row 0
         *         else, every row has its own values
         */
        if( $this->bCtrlSame && count($raRows) ) {
            $pGlobalLocation = @$raRows[0]['values']['location'];
            $pGlobalGrams    = @$raRows[0]['values']['g_weight'];
            $pGlobalDeAcc    = intval(@$raRows[0]['values']['bDeAcc']);     // missing checkbox value == 0
        }

        foreach( $raRows as $ra ) {
            // $ra is the sf-encoded parms for each row of the form
            $kLot = $ra['values']['kLot'];
            if( ($kfr = $this->kfrLot($kLot)) ) {
                $sRes = "";

                if( $this->isUpdatable('location') ) {
                    $p = $this->bCtrlSame ? $pGlobalLocation : $ra['values']['location'];
                    if( $p != $kfr->Value('location') ) {
                        $sRes .= ", location {$kfr->Value('location')} to {$p}";
                        $kfr->SetValue( 'location', $p );
                    }
                }

                if( $this->isUpdatable('grams') ) {
                    $p = $this->bCtrlSame ? $pGlobalGrams : $ra['values']['g_weight'];
                    if( $p != $kfr->Value('g_weight') ) {
                        $sRes .= ", grams {$kfr->Value('g_weight')} to {$p}";
                        $kfr->SetValue( 'g_weight', $p );
                    }
                }

                /* Since oFormR parms are read directly instead of via SEEDCoreForm::Update, bDeAcc (checkbox) values of zero will be missing
                 */
                if( $this->isUpdatable('bDeAcc') ) {
                    $p = $this->bCtrlSame ? $pGlobalDeAcc : intval(@$ra['values']['bDeAcc']);     // missing checkbox value == zero
                    if( $p != $kfr->Value('bDeAcc') ) {
                        $sRes .= ", deacc {$kfr->Value('bDeAcc')} to {$p}";
                        $kfr->SetValue( 'bDeAcc', $p );
                    }
                }
                $kfr->PutDBRow();
                if( $sRes )  $sResults .= "<p>Lot #{$kLot}{$sRes}</p>";
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

    private function drawLeftForm()
    {
        $s = "<div>Lot numbers:<br/>".$this->oFormL->Text('rLots')."</div>"
            ."<div>{$this->oFormL->Checkbox('defCtrl_location')} Location</div>"
            ."<div>{$this->oFormL->Checkbox('defCtrl_grams')} Grams</div>"
            ."<div>{$this->oFormL->Checkbox('defCtrl_bDeAcc')} Deaccession</div>"
            ."<div style='margin-top:15px'>{$this->oFormL->Checkbox('defCtrl_same')} Same for every Lot</div>";

        return( $s );
    }

    private function drawRightForm()
    {
        $sR = "";

        foreach( $this->getCurrentLots( $this->oFormL->Value('rLots') ) as $ra ) {
            $sCtrlLoc = $sCtrlGrams = $sCtrlDeAcc = "";
            if( !$this->bCtrlSame && $ra['kfr'] ) {
                // make separate controls for each Lot
                $this->oFormR->SetValue('location', $ra['kfr']->Value('location') );
                $this->oFormR->SetValue('g_weight', $ra['kfr']->Value('g_weight') );
                $this->oFormR->SetValue('bDeAcc',   $ra['kfr']->Value('bDeAcc') );
                $sCtrlLoc   .= $this->isUpdatable('location') ? $this->oFormR->Text('location') : "";
                $sCtrlGrams .= $this->isUpdatable('grams')    ? $this->oFormR->Text('g_weight') : "";
                $sCtrlDeAcc .= $this->isUpdatable('bDeAcc')   ? $this->oFormR->Checkbox('bDeAcc') : "";
            }

            $sR .= "<div style='margin-bottom:10px;padding:10px;background-color:#ddd'>"
                      ."<table border='0'>"
                          ."<tr><td>Lot {$ra['kLot']}: <em>{$ra['cv']}</em></td><td>&nbsp</td></tr>"
                          .($ra['kfr'] ?
                              ("<tr><td>Location:      {$ra['kfr']->Value('location')}</td><td>$sCtrlLoc</td></tr>"
                              ."<tr><td>Grams:         {$ra['kfr']->Value('g_weight')}</td><td>$sCtrlGrams</td></tr>"
                              ."<tr><td>Deaccessioned: {$ra['kfr']->Value('bDeAcc')}</td><td>$sCtrlDeAcc</td></tr>")
                              : "")
                      ."</table>"
                  ."</div>";

            if( $ra['kfr'] ) {
                $sR .= $this->oFormR->Hidden( 'kLot', ['value'=>$ra['kLot']] );
                $this->oFormR->IncRowNum();
            }
        }

        if( $this->bCtrlSame ) {
            // make one set of controls that will apply to all Lots
            $this->oFormR->SetRowNum(0);  // values will be found in sf-row 0
            $sR .= "<div style='margin:10px;padding:10px;border:1px solid #aaa'>These Values (including blanks) Will be Applied to All of the Above"
                  .($this->isUpdatable('location') ? "<div>{$this->oFormR->Text('location')} Location </div>" : "")
                  .($this->isUpdatable('grams')    ? "<div>{$this->oFormR->Text('g_weight')} Grams</div>" : "")
                  .($this->isUpdatable('bDeAcc')   ? "<div>{$this->oFormR->Checkbox('bDeAcc')} Deaccessioned</div>" : "")
                  ."</div>";
        }

        return( $sR );
    }
}