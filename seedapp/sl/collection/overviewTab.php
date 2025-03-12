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

        $raOps = ['All lots'=>'lot_all',
                  'All varieties'=>'cv_all',
                  'Adopted varieties'=>'cv_adopted',
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
            case 'cv_all':      $s = $this->drawCVOverview('all');      break;
            case 'cv_adopted':  $s = $this->drawCVOverview('adopted');  break;
        }

        return( $s );
    }

    private function drawLotOverview()
    {
        $s = "";
$bUnionCSCI = false;

        $qCmd = $bUnionCSCI ? 'collreport-cultivarlistdataUnionCSCI' : 'collreport-cultivarlistdata';
        $sTitle = "Summary of All Active Lots in the Seed Library Collection" . ($bUnionCSCI ? " + Seed Finder" : "");

        $rQ = $this->oQCollReports->Cmd( $qCmd, ['kCollection'=>1, 'modes'=>" raIxG "] );

        if( $rQ['bOk'] ) {
            $s1 = "";
            $c = 0;
            foreach( $rQ['raOut'] as $ra ) {
                $sTDClass = "class='td$c'";

                $sCol1 = $sCol2 = $sCol3 = $sCol4 = "";
                foreach( (@$ra['raIxA'] ?? []) as $kEncodesYear => $raI ) {
                    $y = intval($kEncodesYear);
                    $sCol1 .= "{$raI['location']} {$raI['inv_number']}: {$raI['g_weight']} g from $y<br/>";
                    $sCol2 .= ($raI['latest_germtest_date'] ? "{$raI['latest_germtest_result']}% on {$raI['latest_germtest_date']}" : "&nbsp;")."<br/>";
                    $sCol3 .= "<br/>";
                    $sCol4 .= ($raI['latest_germtest_result'] ? "%%" : "")."<br/>";
                }

                $s1 .= "<tr><td $sTDClass>{$ra['species']}</td><td $sTDClass>{$ra['cultivar']}</td>"
                      ."<td $sTDClass>{$ra['csci_count']}</td><td $sTDClass>{$ra['adoption']}</td>"
                      ."<td $sTDClass>{$ra['newest_lot_year']}</td><td $sTDClass>{$ra['total_grams']}</td>"
                      ."<td $sTDClass>".str_replace( " | ", "<br/>", $ra['notes'] )."</td>"
                      ."<td $sTDClass>{$ra['newest_lot_germ_result']}</td><td $sTDClass>{$ra['newest_lot_germ_year']}</td>"
                      ."<td $sTDClass>$sCol1</td>
                        <td $sTDClass>$sCol2</td>
                        <td $sTDClass>$sCol3</td>
                        <td $sTDClass>$sCol4</td>
                        </tr>";
                $c = $c ? 0 : 1;
            }
            $s .= $this->drawReport( $sTitle, $qCmd,
                          "<th>&nbsp;</th><th>&nbsp;</th><th>Companies</th><th>Adoption</th><th>Newest</th><th>Total grams</th><th>&nbsp;</th>",
                          $s1 );
        } else {
            $this->oW->oC->ErrMsg( $rQ['sErr'] );
        }
        return( $s );
    }

    private function drawReport( $sTitle, $qCmd, $sTableHeaders, $sTableBody )
    {
        $s = "<div><h3 style='display:inline-block;margin-right:3em;'>$sTitle</h3>
                <a style='display:inline-block' href='".$this->oApp->UrlQ('index2.php')."?qcmd=$qCmd&kCollection=1&qfmt=xls' target='_blank'>
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

    private function drawLotOverview1()
    {
        $s = "";

        $raLots = $this->oSLDB->GetList('IxAxPxS', "I.fk_sl_collection=1", ['sSortCol'=>'S_psp,P_name']);
        foreach( $raLots as $ra ) {
            $s .= SEEDCore_ArrayExpand($ra, "<p>[[_key]] : [[S_psp]] [[P_name]]</p>");
        }


        return( $s );
    }

    private function drawCVOverview(string $mode)
    {
        $s = "";

        return( $s );
    }
}
