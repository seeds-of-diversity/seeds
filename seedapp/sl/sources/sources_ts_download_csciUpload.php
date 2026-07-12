<?php

include_once( "sources_download_googlesync.php" );


class SLSourcesAppDownload_CSCIUpload
{
    private $oApp;
    private $tmpTable;
    private $oUploadUIGsheet;
    private $oUploadUIXLS;
    private $oUploadLib;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oUploadLib = new SLSourcesCVUpload($this->oApp, SLSourcesCVUpload::ReplaceWholeCSCI, 0, "");
        $this->oUploadUIGsheet = new SLSourcesAppDownload_CSCIUpload_GoogleSheet($this->oApp, $this->oUploadLib, []);
        $this->oUploadUIXLS = null; // SLSourcesAppDownload_CSCIUpload_XLS($this->oApp, $this->oUploadLib, []);
    }

    function DoUI()
    {
        $s = "";
        $ok = false;

        $iStep = SEEDInput_Int('iStep');  // don't make this sticky $oForm->ValueInt('iStep');

        switch( SEEDInput_Str('actionStep') ) {
            case '':                                $iStep = 0;                           break;      // no form submission - iStep should be zero anyway
            case 'Start over':                      $iStep = 0;                           break;      // override iStep to force restart
            case 'Redo this step':                                                        break;      // use iStep again
            case 'Commit to Seed Finder Database':  if($iStep==3) $this->CommitToDb();    break;
            case 'Write to Google Sheet':           if($iStep==3) $this->oUploadUIGsheet->WriteToSheet();  break;
            default:                                ++$iStep;                             break;      // button label described step 1, 2, 3
        }
        $iStep = min($iStep, 3);

        $raStep = [0 => ['name'=>"Start Sync"],
                   1 => ['name'=>"Load from sheet"],
                   2 => ['name'=>"Validate"],
                   3 => ['name'=>"Prepare update"],


            4 => ['name'=>"Commit to Seed Finder Database"],
                   5 => ['name'=>"Write to sheet"],
            ];


        $sCtrls = $sResults = "";
        switch($iStep) {
            default:
            case 0:
                /* Starting form: get config for spreadsheet to load
                 */
                $ok = true;
                $sCtrls = $this->oUploadUIGsheet->GetFormControls();
                break;
            case 1:
                /* Fetch from google sheet, insert to tmp table
                 */
                list($ok, $sResults) = $this->step1_Fetch();
                break;
            case 2:
                /* Validate tmp table
                 */
                list($ok, $sResults) = $this->step2_Validate();
                break;
            case 3:
                /* Commit tmp table to sl_cv_sources
                 */
                list($ok, $sResults) = $this->step3_PrepareUpdate();
                break;
        }

        $btnNext = "";
        if($iStep < 3) {
            // the first three steps have a Next button
            $btnNext = "<input type='submit' name='actionStep' value='Next: ".$raStep[$iStep+1]['name']."'/>";
        } else {
            // the final step has various operations that lead to the same state
            $raReport = $this->oUploadLib->CalculateUploadReport();
            $sDisableCommit = !($ok && $this->oUploadLib->IsCommitAllowed($raReport)) ? " disabled" : "";
            $sDisableSheetWrite = !($ok && true /* google sheet mode */) ? " disabled" : "";
            $btnNext .= "<input type='submit' name='actionStep' value='Commit to Seed Finder Database' $sDisableCommit/>&nbsp;&nbsp;&nbsp;";
            $btnNext .= "<input type='submit' name='actionStep' value='Write to Google Sheet'          $sDisableSheetWrite/>";
        }

        $s .= "<h3>Step $iStep : {$raStep[$iStep]['name']}</h3>";

        $s .= "<form method='post'>
                   $sCtrls
                   <input type='hidden' name='iStep' value='{$iStep}'/>"
                 // last step has no Next button
                 .$btnNext
                 // first step has no Redo or Start over
                 .($iStep ? "<input style='float:right' type='submit' name='actionStep' value='Redo this step'/>
                             <input style='float:right' type='submit' name='actionStep' value='Start over'/>" : "")
             ."</form>
               <div style='margin:2em'>$sResults</div>";

        return($s);
    }

    private function step1_Fetch()
    {
        $s = "";
        $ok = false;

        list($ok,$s) = $this->oUploadUIGsheet->FetchSheetToTmpTable();

        return([$ok,$s]);
    }

    private function step2_Validate()
    {
        $s = "";
        $ok = false;

        /* Build fk_sl_sources, fk_sl_species, fk_sl_pcv and report success
         */
        $s .= SLSourceCV_Build::BuildAll($this->oApp, $this->oUploadLib->TmpTableName());
        $raStatus = SLSourceCV_Build::GetTableStatus($this->oApp, $this->oUploadLib->TmpTableName());
        $s .= $raStatus['sReport'];

        /* Update pcv column if ocv <> pcv (pcv was found via sl_pcv_syn)
         */
        $this->oApp->kfdb->Execute("UPDATE {$this->oUploadLib->TmpTableName()} G, sl_pcv P
                                    SET G.pcv=P.name
                                    WHERE G.fk_sl_pcv<>0 AND G.fk_sl_pcv=P._key AND G.ocv<>P.name");

        $ok = true;

        done:
        return([$ok,$s]);
    }

    private function step3_PrepareUpdate()
    {
        // prepare and explain update operations in tmp table to be applied to sl_cv_sources
        $s = "";
        $ok = false;

        /* Compute the differences between tmptable and sl_cv_sources
         */
        $o = new SLSourcesCVUpload($this->oApp, SLSourcesCVUpload::ReplaceWholeCSCI, 0, $this->oUploadLib->TmpTableName());
        $o->ComputeDiff();
        $raReport = $o->CalculateUploadReport();
        if( $raReport['nRowsSameDiffKeys'] ) {
            $s .= $o->FixMatchingRowKeys();
        }
        $s .= $o->DrawUploadReport( $raReport );

        $ok = true;

        return([$ok,$s]);
    }

    private function CommitToDb()
    {
        // copy from tmp table to sl_cv_sources
        $s = "";
        $ok = false;

        list($ok,$s,$sErr) = $this->oUploadLib->Commit();
        if($sErr) $s .= "<div class='alert alert-warning'>$sErr</div>";

        return([$ok,$s]);
    }
}

