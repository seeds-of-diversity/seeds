<?php

/* store2_basket.php
 *
 * Copyright 2026 Seeds of Diversity Canada
 *
 * Second generation Seeds of Diversity store - basket manager
 */

class Store2_Basket
{
    private $oApp, $oForm;

    function __construct( SEEDAppConsole $oApp, SEEDCoreFormSession $oForm )
    {
        $this->oApp = $oApp;
        $this->oForm = $oForm;
    }

    public $raDonPref = [
        'donpref_sl'  => ['label'=>'seed conservation'],
        'donpref_yfs' => ['label'=>'youth programs'],
        'donpref_ss'  => ['label'=>'seedy events'],
        'donpref_any' => ['label'=>'where needed most']
    ];


    function ClearBasket()  { $this->oForm->Clear(); }

    function UpdateBasket()
    {
        var_dump($_REQUEST);
        $this->oForm->Update();

        if(($p = $this->oForm->Value('donationX'))) {
            // The type-your-own-number donation box
            $this->oForm->SetValue('donation', $p);
            $this->oForm->SetValue('donationX', '');
        }
    }

    function HasContents()
    {
        $bHasContent = ($this->oForm->Value('donation') || false);
        return($bHasContent);
    }

    function DrawBasket( array $raParms )
    {
        $s = "";

        $bNotesEditable = SEEDCore_ArraySmartBool($raParms, 'bNotesEditable', false);

        $sBasket = "";
        if(($p = $this->oForm->Value('donation'))) {
            $raSPurpose = [];
            foreach($this->raDonPref as $k=>$raDP) {
                if($this->oForm->Value($k)) $raSPurpose[] = $raDP['label'];
            }
            $sPurpose = $raSPurpose ? (" - <span style='font-size:small'>".implode(", ", $raSPurpose)."</span>") : "";
            $sBasket .= "<div class='store2_drawbasket_item'>Donation: {$p} {$sPurpose}</div>";
        }
        if(($p = $this->oForm->Value('slAdopt_amount'))) {
            $sBasket .= "<div class='store2_drawbasket_item'>Seed Adoption donation: {$p}</div>";
            // show adoption details

        }

        $sBasket .= $bNotesEditable ? "<div class='store2_drawbasket_item'><p><strong>Send us a note</strong></p>{$this->oForm->Textarea('notes')}</div>"
                                    : "<div style='border:1px solid #ccc;padding:1em'>{$this->oForm->Value('notes')}</div>";


        $s .= "<div style='border:1px solid #aaa;margin:1em; border-radius:3px;text-align:center'><span style='font-size:large;color:gray'>Your Basket</span> $sBasket</div>";

        return($s);
    }

    function Style() {
        return("
            <style>
            .store2_drawbasket_item { border-top:1px solid #ccc; }
            </style>
        ");
    }
}