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
        $this->oForm->Update();

        if(($p = $this->oForm->Value('donationX'))) {
            // The type-your-own-number donation box
            $this->oForm->SetValue('donation', $p);
            $this->oForm->SetValue('donationX', '');
        }

        if(($p = $this->oForm->Value('slAdopt_amountX'))) {
            // The type-your-own-number donation box
            $this->oForm->SetValue('slAdopt_amount', $p);
            $this->oForm->SetValue('slAdopt_amountX', '');
        }
        if( !($slAdopt_cv = $this->oForm->Value("slAdopt_cv")) ) {
            if( $this->oForm->Value("slcvchoose") == "as_needed" ) {
                $this->oForm->SetValue('slAdopt_cv', "as needed");
            }
            if(($p = $this->oForm->Value('slAdopt_kPcv'))) {
                $kfr = (new SLDBRosetta($this->oApp))->GetKFR('PxS',$p);
                $this->oForm->SetValue('slAdopt_cv', $kfr->Expand("[[P_name]] [[S_name_en]]"));
            }
        }

        switch($this->oForm->Value('slnamechoose')) {
            case 1: $this->oForm->SetValue('slAdopt_name', "anonymous");    break;
            case 2: $this->oForm->SetValue('slAdopt_name', "as a gift to: {$this->oForm->Value('slAdopt_name')}");    break;
        }
    }

    function HasContents()
    {
        $bHasContent = ($this->oForm->Value('donation') || floatval($this->oForm->Value('slAdopt_amount')));
        return($bHasContent);
    }

    function DrawBasket( array $raParms )
    {
        $s = "";

        $bNotesEditable = SEEDCore_ArraySmartBool($raParms, 'bNotesEditable', false);

        $sContact =
            "<div class='store2_drawbasket_item'>
             {$this->oForm->Value('mail_firstname')} {$this->oForm->Value('mail_lastname')}<br/>"
           .(($p = $this->oForm->Value('mail_company')) ? "$p<br/>" : "")
           ."{$this->oForm->Value('mail_address')}<br/>
             {$this->oForm->Value('mail_city')} {$this->oForm->Value('mail_prov')} {$this->oForm->Value('mail_postcode')}<br/>
             {$this->oForm->Value('mail_email')} {$this->oForm->Value('mail_phone')}
             </div>";

        $sBasket = "";
        if(($p = $this->oForm->Value('donation'))) {
            $raSPurpose = [];
            foreach($this->raDonPref as $k=>$raDP) {
                if($this->oForm->Value($k)) $raSPurpose[] = $raDP['label'];
            }
            $sPurpose = $raSPurpose ? (" - <span style='font-size:small'>".implode(", ", $raSPurpose)."</span>") : "";
            $sBasket .= "<div class='store2_drawbasket_item'>Donation: \${$p} {$sPurpose}</div>";
        }
        if(($p = $this->oForm->Value('slAdopt_amount'))) {
            $sBasket .= "<div class='store2_drawbasket_item'>Seed Adoption donation: \${$p}<br/>{$this->oForm->Value('slAdopt_cv')}<br/>{$this->oForm->Value('slAdopt_name')}</div>";
            // show adoption details

        }

        if(($p = floatval($this->oForm->Value('fTotal')))) {
            $sBasket .= "<div class='store2_drawbasket_item' style='padding-top:1em'><strong>Total: \${$p}</strong></div>";
        }

        $sBasket .= $bNotesEditable ? "<div class='store2_drawbasket_item' style='padding:1em 0'><p><strong>Send us a note</strong></p>{$this->oForm->Textarea('notes')}</div>"
                                    : "<div style='border:1px solid #ccc;padding:1em'>{$this->oForm->Value('notes')}</div>";


        $s .= "<div style='border:1px solid #aaa;margin:1em; border-radius:3px;text-align:center;max-width:40em'>
                   <div style='font-size:large;color:gray'>Your Basket</div>
                   $sContact
                   $sBasket
               </div>";

        return($s);
    }

    function Style() {
        return("
            <style>
            .store2_drawbasket_item { border-top:1px solid #ccc; padding:0.5em 0 }
            </style>
        ");
    }
}