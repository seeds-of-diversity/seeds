<?php


class CollectionTab_GerminationTests
{
    private $oApp;
    private $kInventory;
    private $sldbCollection;

    function __construct( SEEDAppConsole $oApp, $kInventory )
    {
        $this->oApp = $oApp;
        $this->kInventory = $kInventory;
        $this->sldbCollection = new SLDBCollection($oApp);
    }

    function Init()
    {

    }

    function ControlDraw()
    {
        return( "" );
    }

    function ContentDraw()
    {
        $oForm = new KeyframeForm($this->sldbCollection->GetKfrel("G"));
        $oForm->Update();
        $oForm->SetKFR($this->sldbCollection->GetKfrel("G")->CreateRecord());
        $ra = $this->sldbCollection->GetList("G", "fk_sl_inventory = {$this->kInventory}", ['sSortCol'=>'dStart','bSortDown'=>true]);
        $s = "<form method='post'>{$oForm->Hidden("fk_sl_inventory",['value'=>$this->kInventory])}<table><tr><th>Start Date</th><th style='text-align:center;padding-left: 10px;'>Number Sown</th><th style='padding-left: 20px;'>Germination %</th><th style='text-align:center;'>Notes</th></tr>";
        $s .= "<tr><td>{$oForm->Date("dStart")}</td><td>{$oForm->Text("nSown")}</td><td>{$oForm->Text("nGerm")}</td><td>{$oForm->Text("notes","",['width'=>'100%'])}</td><td><input type='submit' /></td></tr>";
        $s .= SEEDCore_ArrayExpandRows($ra, "<tr style='text-align:center'><td>[[dStart]]</td><td>[[nSown]]</td><td>[[nGerm]]</td><td style='text-align:left'>[[notes]]</td></tr>");
        $s .= "</table></form>";
        return( $s );
    }

}
