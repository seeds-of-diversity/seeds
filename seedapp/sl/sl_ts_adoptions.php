<?php

include_once( SEEDLIB."mbr/MbrContacts.php" );
include_once( SEEDLIB."mbr/MbrDonations.php" );
include_once(SEEDLIB."sl/sl_integrity.php");
include_once(SEEDLIB."sl/QServerRosetta.php");

class MbrAdoptionsListForm extends KeyframeUI_ListFormUI
{
    private $oMbrContacts;
    private $oSLDB;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oMbrContacts = new Mbr_Contacts($oApp);
        $this->oSLDB = new SLDBBase($oApp);

        $cols = [['label'=>"#",                 'col'=>"_key",              'w'=>" 5%"],
                 ['label'=>"Adopter",           'col'=>"M_lastname",        'w'=>"15%"],    // replaced by GetContactName() (will sort by M_lastname)
                 ['label'=>"Recognized as",     'col'=>"public_name",       'w'=>"20%"],
                 ['label'=>"Date",              'col'=>"D_date_received",   'w'=>"10%"],
                 ['label'=>"Amount",            'col'=>"amount",            'w'=>" 5%"],
                 ['label'=>"Request",           'col'=>"sPCV_request",      'w'=>"20%"],
                 ['label'=>"Variety adopted",   'col'=>"P_name",            'w'=>"20%"],    // replaced by variety name (will sort by number)
                 ['label'=>"Total for variety", 'col'=>"tmp_total",         'w'=>" 5%", 'noSort'=>false ], // replaced by total adoptions for this variety, cannot be sorted from db data
        ];
        $raSrch = [['label'=>'First name',    'col'=>'M.firstname'],
                   ['label'=>'Last name',     'col'=>'M.lastname'],
                   ['label'=>'Company',       'col'=>'M.company'],
                   ['label'=>'Member #',      'col'=>'M._key'],
                   ['label'=>'Variety #',     'col'=>'P._key'],
                   ['label'=>'Variety name',  'col'=>'P.name'],
                   ['label'=>'Amount',        'col'=>'D_amount'],
                   ['label'=>'Date received', 'col'=>'D_date_received'],
                   ['label'=>'Request',       'col'=>'sPCV_request'],
//                   ['label'=>'Total for variety', 'col'=>'tmp_total'],    can't put tmp_total in WHERE because it's an alias not a column; HAVING would work
                  ];

        $kfrel = $this->oMbrContacts->oDB->Kfrel('AxM_D_P_S');
        // sum all adoptions in table (not just the current list) with the same pcv
        $kfrel->SetKFParms(['raFieldsAdd'=>['tmp_total'=>"(select sum(amount) from {$oApp->DBName('seeds1')}.sl_adoption B where B.fk_sl_pcv=A.fk_sl_pcv AND B.fk_sl_pcv<>0)"]]);

        $raConfig = $this->GetConfigTemplate(
            ['sessNamespace'        => 'SLAdoptionManager',
             'cid'                  => 'A',

             // A=Adoption, D=Donation -- different from sldb where A=Accession, D=Adoption
             'kfrel'                => $kfrel,
             'raListConfig_cols'    => $cols,
             'raSrchConfig_filters' => $raSrch,
            ]);
        // note that raConfig references methods like FormTemplate() which use $this->oComp which is not defined now but will be after Init()
        parent::__construct($oApp, $raConfig);
    }

    function Init()
    {
        parent::Init();
    }

    function ControlDraw()
    {
        return( $this->DrawSearch() . "<div style='float:right;margin-top:-40px'>Checkbox to edit adoption record (Request, Amount, etc)<br/>Button to split current record</div>" );
    }

    function ContentDraw()
    {
        $s = $this->DrawStyle()
           ."<style>.donationFormTable td { padding:3px;}</style>"
           ."<div>".$this->DrawList()."</div>"
           ."<div style='margin-top:20px;padding:20px;border:2px solid #999'>".$this->DrawForm()."</div>";

        return( $s );
    }

    function ListRowTranslate($raRow)
    {
        // show the donor's real name
        $raRow['M_lastname'] = $this->oMbrContacts->GetContactName($raRow['fk_mbr_contacts']);

        if( ($kPcv = @$raRow['fk_sl_pcv']) ) {
            // show variety name with species and kPcv
            $raRow['P_name'] = @$raRow['P_name']." ".@$raRow['S_psp']." ($kPcv)";

            // show total adoptions for this variety
            $raAdopt = $this->oSLDB->Get1List('D', 'amount', "fk_sl_pcv='$kPcv'");
        } else {
            $raRow['tmp_total'] = "";       // don't show zero
        }

        return($raRow);
    }

    function PreStore( Keyframe_DataStore $oDS )
    {
        // This allows date_issued to be erased. A DATE cannot be '' but it can be NULL.
        // However, if it is already NULL KF will try to set it to NULL again and log that.
        // So you'll see date_issued=NULL in the log when it was already NULL.
        // The reason is that KF stores a NULL value's snapshot as '' so it thinks the value is changing from '' to NULL.
        $kfr = $oDS->GetKFR();  // because there is no oDS->SetNull, though there could be if you can generalize it for the base SEEDDataStore
        if( !$kfr->value('date_issued') ) $kfr->SetNull('date_issued');

        return( true );
    }

    function FormTemplate( SEEDCoreForm $oForm )
    {
        $sAdopter = $this->oMbrContacts->GetContactName($oForm->Value('fk_mbr_contacts'));
        $sLinkRosetta = ($k = $oForm->Value('P__key')) ? "<a href='./rosetta.php?c02ts_main=cultivar&sfRui_k=$k' target='_blank'>See this in Rosetta</a>" : "";
        list($sSyn,$sStats) = ($kPcv = $oForm->Value('fk_sl_pcv')) ? SLIntegrity::GetPCVReport($this->oApp, $kPcv) : ["",""];

        $urlQ = SITEROOT_URL."app/q/index.php";     // rosettaPCVSearch is still in the original Q code

        $s =  "<style>
               .slAdoptionFormInfo { border:1px solid #aaa; margin:2em; padding:1em }
               </style>

               <div class='container-fluid'>
               <div class='row'><div class='col-md-6'>

               |||BOOTSTRAP_TABLE(class='col-md-4'|class='col-md-8')
               ||| *Adopter*    || $sAdopter ([[text:fk_mbr_contacts|readonly]])
               ||| *Recognized as* || [[text:public_name|readonly]]
               ||| *Request*    || [[text:sPCV_request|readonly]]
               ||| *Amount*     || [[text:amount|readonly]]
               ||| *Received*   || [[text:D_date_received|readonly]]

               ||| *Variety adopted*    || <span id='cultivarText'>[[Value:P_psp]] : [[Value:P_name]] ([[Value:P__key]])</span>&nbsp;&nbsp;&nbsp;$sLinkRosetta
               ||| &nbsp;               || <div style='position:relative'>
                                           <input type='text' id='dummy_pcv' size='10' class='SFU_TextComplete' placeholder='Search'/>
                                           </div>
                                           [[hidden:fk_sl_pcv]]

               ||| &nbsp        || &nbsp;
               ||| *Notes*      || {colspan='2'} ".$oForm->TextArea( "notes", ['width'=>'90%','nRows'=>'5'] )."
               ||| &nbsp;       || <input type='submit' value='Save'/>
               |||ENDTABLE

               <div class='slAdoptionFormInfo'>{$this->getMemberAdoptionHistory($oForm)}</div>
               </div><div class='col-md-6'>

               <div class='slAdoptionFormInfo'>{$this->getCultivarAdoptionHistory($oForm)}</div>
               <div class='slAdoptionFormInfo'>{$this->getCollectionInfo($oForm)}</div>
               <div style='margin:2em'>{$sSyn}{$sStats}</div>
              </div></div>

              [[hiddenkey:]]
              </div>

              <script>
               function setupMbrSelector() {
               let o = new SLPcvSelector( { urlQ:'{$urlQ}',
                                            idTxtSearch:'dummy_pcv',
                                            idOutReport:'cultivarText',
                                            idOutKey:'sfAp_fk_sl_pcv' } );
               }
               setupMbrSelector();
               </script>";

        return( $s );
    }

    private function getCultivarAdoptionHistory( SEEDCoreForm $oForm )
    {
        $s = "";
        if( !($kPcv = $oForm->ValueInt('fk_sl_pcv')) )  goto done;

        $s = "Adoption history for <b>{$oForm->Value('P_name')}</b> {$oForm->Value('S_name_en')} : <table style='width:100%'>";

        $raAdopt = $this->oMbrContacts->oDB->GetList('AxM_D_P_S', "A.fk_sl_pcv='{$kPcv}'");
        foreach($raAdopt as $ra) {
            $s .= "<tr><td style='width:10%'>&nbsp;</td>
                       <td>{$ra['D_date_received']}</td>
                       <td>{$this->oMbrContacts->GetContactName($ra['fk_mbr_contacts'])} ({$ra['fk_mbr_contacts']})</td>
                       <td>{$ra['amount']}</td></tr>";
        }
        $s .= "</table>";

        done:
        return($s);
    }

    private function getCollectionInfo( SEEDCoreForm $oForm )
    {
        $s = "";

        if( !($kPcv = $oForm->ValueInt('fk_sl_pcv')) )  goto done;

        $s = "<p>Collection status for <b>{$oForm->Value('P_name')}</b> {$oForm->Value('S_name_en')}</p>";

        $rQ = (new QServerRosetta($this->oApp))->Cmd('rosetta-cultivarinfo', ['kCollection'=>1 /*ignored currently*/, 'kPcv'=>$kPcv, 'mode'=>"all"]);
        if( $rQ['bOk'] ) {
            $s .= "<table><tr><th>&nbsp;</th><th>&nbsp;</th><th style='text-align:center'>germ</th><th style='text-align:center'>est viable pops</th></tr>";
            foreach( (@$rQ['raOut']['raIxA'] ?? []) as $kEncodesYear => $raI ) {
                $sCol1 = "<nobr>{$raI['location']} {$raI['inv_number']}: {$raI['g_weight']} g</nobr>";
                $sCol2 = ($y = intval($kEncodesYear)) ?: "";
                $sCol3 = $raI['latest_germtest_date'] ? "<nobr>{$raI['latest_germtest_result']}% on {$raI['latest_germtest_date']}</nobr>" : "";
                $sCol4 = $raI['pops_estimate'];

                $s .= "<tr><td style='padding:0 1em;border:1px solid #bbb'>$sCol1</td>
                           <td style='padding:0 1em;border:1px solid #bbb'>$sCol2</td>
                           <td style='padding:0 1em;border:1px solid #bbb'>$sCol3</td>
                           <td style='padding:0 1em;border:1px solid #bbb'>$sCol4</td>
                       </tr>";
            }
            $s .= "</table>";
        }

        done:
        return($s);
    }

    private function getMemberAdoptionHistory( SEEDCoreForm $oForm )
    {
        $s = "";
        if( !$oForm->Value('fk_mbr_contacts') )  goto done;

        $s = "{$this->oMbrContacts->GetContactName($oForm->Value('fk_mbr_contacts'))} has these adoptions : <table style='width:100%'>";

        $raAdopt = $this->oMbrContacts->oDB->GetList('AxM_D_P_S', "A.fk_mbr_contacts='{$oForm->Value('fk_mbr_contacts')}'");
        foreach($raAdopt as $ra) {
            $s .= "<tr><td style='width:10%'>&nbsp;</td>
                       <td>{$ra['D_date_received']}</td>
                       <td>{$ra['P_name']} {$ra['S_psp']}</td>
                       <td>{$ra['amount']}</td></tr>";
        }
        $s .= "</table>";

        done:
        return($s);
    }
}
