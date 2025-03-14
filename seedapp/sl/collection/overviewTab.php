<?php

class CollectionOverview
{
    private $oApp;
    private $oSVA;  // session vars for the UI tab (all batch ops modules)
    private $oOpPicker;
    private $oSLDB;
    private $oQCollReports;

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;
        $this->oSLDB = new SLDBCollection($oApp);
        $this->oQCollReports = new QServerSLCollectionReports($oApp);

        $raOps = ['Active lots'=>'lot_all',
                  'Adopted varieties'=>'lot_adopted',
                  'Other Operation'=>'other'];
        $this->oOpPicker = new Console02UI_OperationPicker('overview', $oSVA, $raOps);
    }

    function Init()
    {
        // Independent of any state of the worker because that only exists in ContentDraw
    }

    function ControlDraw()  { return( $this->oOpPicker->DrawDropdown() ); }

    function ContentDraw()
    {
        $s = "";

        switch( $this->oOpPicker->Value() ) {
            case 'lot_all':     $s = $this->drawLotOverview('all');      break;
            case 'lot_adopted':  $s = $this->drawLotOverview('adopted');  break;
        }

        return( $s );
    }

    private function drawLotOverview( string $mode )
    {
        $s = "";
$bUnionCSCI = false;

        $qCmd = $bUnionCSCI ? 'collreport-cultivarlistdataUnionCSCI' : 'collreport-cultivarlistdata';
        $sTitle = "Summary of All Active Lots in the Seed Library Collection" . ($bUnionCSCI ? " + Seed Finder" : "");

        $rQ = $this->oQCollReports->Cmd( $qCmd, ['kCollection'=>1, 'modes'=>" raIxG "] );

        if( $rQ['bOk'] ) {
            $s1 = "";
            $c = 1;
            foreach( $rQ['raOut'] as $ra ) {
                if( $mode == 'adopted' && !$ra['adoption'] ) continue;

                $c = $c ? 0 : 1;
                $sTDClass = "class='td$c'";

                $sCol1 = $sCol2 = $sCol3 = $sCol4 = $sCol5 = "";
                foreach( (@$ra['raIxA'] ?? []) as $kEncodesYear => $raI ) {
                    $y = intval($kEncodesYear);
                    $sCol1 .= "{$raI['location']} {$raI['inv_number']}: {$raI['g_weight']} g from $y<br/>";
                    $sCol2 .= ($raI['latest_germtest_date'] ? "{$raI['latest_germtest_result']}% on {$raI['latest_germtest_date']}" : "")."<br/>";
                    $sCol3 .= "{$raI['current_germ_estimate']}%<br/>";
                    $sCol4 .= ($raI['latest_germtest_date'] ? "({$raI['current_germ_model']}%)" : "")."<br/>";

                    if( in_array($ra['species'], ['bean','squash']) ) {
                        if($raI['g_weight_viable_estimate'] > 200) { $clr = 'green'; } else
                        if($raI['g_weight_viable_estimate'] > 20)  { $clr = 'orange'; } else
                                                                   { $clr = 'red'; }
                    } else if( in_array($ra['species'], ['barley','oat','wheat']) ) {
                        if($raI['g_weight_viable_estimate'] > 50)  { $clr = 'green'; } else
                        if($raI['g_weight_viable_estimate'] > 10)  { $clr = 'orange'; } else
                                                                   { $clr = 'red'; }
                    } else {
                        if($raI['g_weight_viable_estimate'] > 1)  { $clr = 'green'; } else
                        if($raI['g_weight_viable_estimate'] > 0.5){ $clr = 'orange'; } else
                                                                  { $clr = 'red'; }
                    }
                    $sCol5 .= "<span style='color:$clr'>{$raI['g_weight_viable_estimate']}</span><br/>";
                }

                $s1 .= "<tr><td $sTDClass>{$ra['species']}</td><td $sTDClass>{$ra['cultivar']}</td>"
                      ."<td $sTDClass>{$ra['csci_count']}</td><td $sTDClass>{$ra['adoption']}</td>"
                      //."<td $sTDClass>{$ra['newest_lot_year']}</td>"

                      ."<td $sTDClass>$sCol1</td>
                        <td $sTDClass>$sCol2</td>
                        <td $sTDClass>$sCol3</td>
                        <td $sTDClass>$sCol4</td>
                        <td $sTDClass>$sCol5</td>

                        <td $sTDClass>{$ra['total_grams']}</td>
                        <td $sTDClass>{$ra['est_total_viable_grams']}</td>
                        <td $sTDClass>{$ra['est_total_viable_seeds']}</td>
                        <td $sTDClass>{$ra['est_total_viable_pops']}</td>
                        </tr>";
            }
            $s .= $this->drawReport( $sTitle, $qCmd,
                          "<th>&nbsp;</th><th>&nbsp;</th><th>Companies</th><th>Adoption</th>
                           <th>Lot detail</th><th>Germ tests</th><th>Est. current germ</th><th>(model germ)</th><th>viable grams</th>
                           <th>Total grams</th><th>Estimated total viable grams</th><th>Est Total viable seeds</th><th>Est Total viable pops</th>",
                          $s1 );
        } else {
            $this->oW->oC->ErrMsg( $rQ['sErr'] );
        }
        return( $s );
    }

    private function drawReport( $sTitle, $qCmd, $sTableHeaders, $sTableBody )
    {
        $s = "<div><h3 style='display:inline-block;margin-right:3em;'>$sTitle</h3>
                <a style='display:inline-block' href='".$this->oApp->UrlQ('index2.php')."?qcmd=$qCmd&kCollection=1&mode=%20raIxG%20&qfmt=xls' target='_blank'>
                  <img src='".W_ROOT."std/img/dr/xls.png' height='25'/>
                </a>
              </div>"

            ."<style>
                .collReportTable th   { padding-right:15px; }
                .collReportTable .td0 { padding-right:15px; vertical-align:top; background-color:#ddd;}
                .collReportTable .td1 { padding-right:15px; vertical-align:top; background-color:white;}
              </style>"

            ."<table class='collReportTable'><tr>$sTableHeaders</tr> $sTableBody </table>";

        return( $s );
    }
}
