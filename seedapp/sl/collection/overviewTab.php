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

        $raOps = ['Active lots'        =>'lot_all',
                  'Adoption priorities'=>'adopt_priorities',
                  'Growout priorities' =>'growout_priorities',
                  'Other Operation'    =>'other'];
        $this->oOpPicker = new Console02UI_OperationPicker('overview', $oSVA, $raOps);
    }

    function Init()
    {
        // Independent of any state of the worker because that only exists in ContentDraw
    }

    function ControlDraw()
    {
        $s = "<div style='display:inline-block'>{$this->oOpPicker->DrawDropdown()}</div>";

        return( $s );
    }

    function ContentDraw()
    {
        $s = "";

        switch( $this->oOpPicker->Value() ) {
            case 'lot_all':             $s = $this->drawLotOverview('all');    break;
            case 'adopt_priorities':    $s = $this->drawAdoptionPriorities();  break;
            case 'growout_priorities':  $s = $this->drawGrowoutPriorities();   break;
        }

        return( $s );
    }

    private function drawLotOverview( string $mode )
    {
        $s = "";
$bUnionCSCI = false;

        $qCmd = $bUnionCSCI ? 'collreport-cultivarlist_active_lots_combined_unionCSCI' : 'collreport-cultivarlist_active_lots_combined';
        $raQCmdParms = ['kCollection'=>1, 'modes'=>" raIxG "];
        $sTitle = "Summary of All Active Lots in the Seed Library Collection" . ($bUnionCSCI ? " + Seed Finder" : "");

        $rQ = $this->oQCollReports->Cmd($qCmd, $raQCmdParms);
        if( $rQ['bOk'] ) {
            $s1 = "";
            foreach( $rQ['raOut'] as $ra ) {
                if( $mode == 'adopted' && !$ra['adoption'] ) continue;

                $s1 .= $this->_drawCVRow($ra);
            }
            $s .= $this->drawReport( $sTitle, $qCmd."&".SEEDCore_ParmsRA2URL($raQCmdParms), $s1 );
        } else {
            $this->oApp->oC->ErrMsg( $rQ['sErr'] );
        }
        return( $s );
    }

    private function drawAdoptionPriorities()
    {
        $s = "";

        $qCmd = 'collreport-cultivar_adopt_priorities';
        $raQCmdParms = ['kCollection'=>1];
        $sTitle = "Adoption Priorities for the Seed Library Collection";
        $sInst = "<p>Partial adoptions are at the top, sorted by populations/companies to prioritize workable but rare varieties.</p><p>Non-adopted varieties come afterward, sorted the same way.</p>";

        $rQ = $this->oQCollReports->Cmd($qCmd, $raQCmdParms);
        if( $rQ['bOk'] ) {
            $s1 = "";
            foreach( $rQ['raOut'] as $ra ) {
                $s1 .= $this->_drawCVRow($ra);
            }
            $s .= $this->drawReport( $sTitle, $qCmd."&".SEEDCore_ParmsRA2URL($raQCmdParms), $s1, $sInst );
        } else {
            $this->oApp->oC->ErrMsg( $rQ['sErr'] );
        }
        return( $s );
    }

    private function drawGrowoutPriorities()
    {
        $s = "";

        $qCmd = 'collreport-cultivar_growout_priorities';
        $raQCmdParms = ['kCollection'=>1];
        $sTitle = "Growout Priorities for the Seed Library Collection";
        $sInst = "<p>Adopted varieties at the top, then ordered by viable populations low-high, where pops < 3 and companies=0.</p>";

        $rQ = $this->oQCollReports->Cmd($qCmd, $raQCmdParms);
        if( $rQ['bOk'] ) {
            $s1 = "";
            foreach( $rQ['raOut'] as $ra ) {
                $s1 .= $this->_drawCVRow($ra);
            }
            $s .= $this->drawReport( $sTitle, $qCmd."&".SEEDCore_ParmsRA2URL($raQCmdParms), $s1, $sInst );
        } else {
            $this->oApp->oC->AddErrMsg( $rQ['sErr'] );
        }
        return( $s );
    }

    private function _drawCVRow( array $ra )
    /***************************************
        Given a row from collreport-cultivarlist*, draw one row of the table
     */
    {
        $s = "";
        $sCol1 = $sCol2 = $sCol3 = $sCol4 = $sCol5 = "";

        /* raIxA data is shown for reference. It is aggregated into into other values e.g. total_grams, and not available in xlsx output.
         */
// this is sort of formatted in getLotDetails_PCV and it could be done this way there too?
        foreach( (@$ra['raIxA'] ?? []) as $kEncodesYear => $raI ) {
            $y = intval($kEncodesYear);
            $sCol1 .= "<nobr>{$raI['location']} {$raI['inv_number']}: {$raI['g_weight']} g from $y</nobr><br/>";
            $sCol2 .= ($raI['latest_germtest_date'] ? "<nobr>{$raI['latest_germtest_result']}% on {$raI['latest_germtest_date']}</nobr>" : "")."<br/>";
            $sCol3 .= $raI['current_germ_estimate']."%<br/>";
            $sCol4 .= ($raI['latest_germtest_date'] ? "({$raI['current_germ_model']}%)" : "")."<br/>";
            $sCol5 .= $raI['g_weight_viable_estimate']."<br/>";
        }
        $sTDClass = "";
        $clrPop = $ra['est_total_viable_pops'] >= 10.0 ? 'green' : ($ra['est_total_viable_pops'] >= 5.0 ? 'orange' : 'red');
        $s .= "<tr>
               <td $sTDClass>{$ra['species']}</td><td $sTDClass>{$ra['cultivar']}</td>
               <td $sTDClass>{$ra['csci_count']} : <span style='font-size:60s%'>{$ra['csci_list']}</span></td><td $sTDClass>{$ra['adoption']}</td>
               <td $sTDClass>{$ra['total_grams']}</td>
               <td $sTDClass>{$ra['est_total_viable_grams']}</td>
               <td $sTDClass>{$ra['est_total_viable_seeds']}</td>
               <td style='color:$clrPop'>{$ra['est_total_viable_pops']}</td>

               <td $sTDClass style='font-size:75%;border-left:1px solid #777;padding-left:0.5em'>$sCol1</td>
               <td $sTDClass style='font-size:75%'>$sCol2</td>
               <td $sTDClass style='font-size:75%;padding-left:0.5em'>$sCol3</td>
               <td $sTDClass style='font-size:75%'>$sCol4</td>
               <td $sTDClass style='font-size:75%'>$sCol5</td>

               </tr>";

        return($s);
    }

    private function drawReport( string $sTitle, string $qCmd, string $sTableBody, string $sInst = "" )
    {
        $sTableHeaders = "<th>&nbsp;</th><th>&nbsp;</th><th>Companies</th><th>Adoption</th>
                          <th>Total grams</th><th>Estimated viable grams</th><th>Est viable seeds</th><th>Est viable pops</th>
                          <!-- last cols are details for reference; aggregated into former values -->
                          <th style='font-size:75%'>Lot detail</th><th style='font-size:75%'>Germ tests</th><th style='font-size:75%'>Est. current germ</th>
                          <th style='font-size:75%'>(model germ)</th><th style='font-size:75%'>viable grams</th>";

        $s = "<div>
                  <h3 style='display:inline-block;margin-right:3em;'>$sTitle</h3>
                  <div style='display:inline-block'>
                      <a href='{$this->oApp->UrlQ('index2.php')}?qcmd=$qCmd&qfmt=xls' target='_blank'>
                      <img src='".W_ROOT."std/img/dr/xls.png' height='25'/>
                      </a>
                  </div>
                  <div style='display:inline-block;margin-left:3em'>$sInst</div>
              </div>

              <style>
                .collReportTable                    { margin-top:2em; width:100%; }
                .collReportTable th                 { padding-right:15px; }
                .collReportTable tr:nth-child(even) { padding-right:15px; vertical-align:top; background-color:#ddd;}
                .collReportTable tr:nth-child(odd)  { padding-right:15px; vertical-align:top; background-color:white;}
              </style>

              <table class='collReportTable'><tr>$sTableHeaders</tr> $sTableBody </table>";

        return( $s );
    }
}
