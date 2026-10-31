<?php

/* RosettaSEED Cultivars--Synonyms subtab
 *
 * Copyright (c) 2014-2026 Seeds of Diversity Canada
 *
 */

class Rosetta_CultivarTabs_Synonyms
{
    private $oApp;
    private int $kPcv;
    private $oSLDB;

    function __construct(SEEDAppConsole $oApp, int $kPcv)
    {
        $this->oApp = $oApp;
        $this->kPcv = $kPcv;
        $this->oSLDB = new SLDBRosetta( $oApp );
    }

    function Init()
    {
    }

    function ContentDraw()
    {
        $s = "";

        if( !$this->kPcv )  goto done;

// $oR could have this
        $kfrP = $this->oSLDB->GetKFR('PxS',$this->kPcv);
        $s .= "<h3>Synonyms for {$kfrP->Value('name')} {$kfrP->Value('S_name_en')}</h3>";

        /* delete synonym - this is also how germ tests are deleted
         */
        if( ($kDel = SEEDInput_Int('syndel')) && ($kfrDel = $this->oSLDB->GetKFR('PY', $kDel)) ) {
            $kfrDel->StatusSet( KeyframeRecord::STATUS_DELETED );
            $kfrDel->PutDBRow();
            $this->reindex_Srccv();     // dereference any appearances of this synonym in sl_cv_sources
        }

        /* Show the synonyms for the current kPcv
         */
        $oForm = new KeyframeForm($this->oSLDB->KFRel('PYxPxS'), 'A', ['DSParms'=>['fn_DSPreStore'=> [$this,'dsPreStore'],
                                                                                   'fn_DSPostStore'=> [$this,'dsPostStore'] ]]);
        $oForm->Update(['sCharsetHTTP'=>"utf8", 'sCharsetDb'=>'cp1252']);
        if( ($kfrc = $this->oSLDB->GetKFRC('PY',"fk_sl_pcv={$this->kPcv}",['sSortCol'=>"name"])) ) {
            while($kfrc->CursorFetch()) {
                $oForm->SetKFR($kfrc);
                $s .= $this->drawRow($oForm);
                $oForm->IncRowNum();
            }
        }

        // New synonym
        $oForm->SetKFR($this->oSLDB->KFRel('PY')->CreateRecord());
        $oForm->SetValue('fk_sl_pcv', $this->kPcv);
        $s .= $this->drawRow($oForm);

        $s .= "<input type='submit' value='Save'/>";

        $s = "<form method='post'><div class='container-fluid'>{$s}</div></form>";

        done:
        return($s);
    }

    private function drawRow(KeyframeForm $oForm)
    {
        $s = "<div class='row'>
              <div class='col-md-3'>
                  {$oForm->Text('name',"",$oForm->GetKey()?[]:['placeholder'=>"New synonym"])}
                  {$oForm->HiddenKey()}
                  {$oForm->Hidden('fk_sl_pcv')}
              </div>
              <div class='col-md-7'>
                  {$oForm->TextArea('notes', ['nRows'=>3,'width'=>"100%"])}
              </div>
              <div class='col-md-1'>"
                  .($oForm->GetKey() ? "<a href='{$this->oApp->PathToSelf()}?syndel={$oForm->GetKey()}'>
                                        <img src='".SEEDW_URL."img/ctrl/delete01.png' height='20'/>
                                        </a>"
                                     : "")
            ."</div>
              </div>";

        return($s);
    }

    function dsPreStore(SEEDDataStore $oDS)
    {
        $ok = false;

        if( $oDS->Value('name') ) {     // don't save the "new synonym" entry if it's still blank
            $ok = true;
        }
        return($ok);
    }

    // mixed because cannot be more specific than parent class
    function dsPostStore(mixed $kfr, SEEDCoreForm $oForm)  { $this->reindex_Srccv(); }

    private function reindex_Srccv()
    {
        // after adding/renaming/deleting a synonym, reindex sl_cv_sources for this kPcv
        SLSourceCV_Build::CultivarIndex_Single($this->oApp, $this->kPcv);
    }
}
