<?php

include_once( SEEDLIB."mbr/MbrContacts.php" );
include_once( SEEDLIB."mbr/MbrDonations.php" );


class MbrAdoptionsListForm extends KeyframeUI_ListFormUI
{
    private $oMbrContacts;
    private $oSLDB;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oMbrContacts = new Mbr_Contacts($oApp);
        $this->oSLDB = new SLDBBase($oApp);

/*
        $raSrch = [['label'=>'First name',    'col'=>'M.firstname'],
                    ['label'=>'Last name',     'col'=>'M.lastname'],
                    ['label'=>'Company',       'col'=>'M.company'],
                    ['label'=>'Member #',      'col'=>'M._key'],
                    ['label'=>'Amount',        'col'=>'amount'],
                    ['label'=>'Date received', 'col'=>'date_received'],
                    ['label'=>'Date issued',   'col'=>'date_issued'],
                    ['label'=>'Receipt #',     'col'=>'receipt_num'],
                ]
            ],
*/


        $cols = [['label'=>"k",                 'col'=>"_key",              'w'=>" 5%", 'noSearch'=>true ],
                 ['label'=>"Adopter",           'col'=>"M_lastname",   'w'=>"15%", 'srchcol'=>"M_lastname" ],   // replaced by GetContactName() (will sort by number)
                 ['label'=>"Recognized as",     'col'=>"public_name",       'w'=>"20%" ],
                 ['label'=>"Date",              'col'=>"D_date_received",   'w'=>"10%" ],
                 ['label'=>"Amount",            'col'=>"amount",            'w'=>" 5%", 'srchcol'=>"D_amount" ],            // A_amount and D_amount ambiguous (should remove A.amount when there's an mbr_donation for every adoption)
                 ['label'=>"Request",           'col'=>"sPCV_request",      'w'=>"20%" ],
                 ['label'=>"Variety adopted",   'col'=>"fk_sl_pcv",         'w'=>"20%" ],                                   // replaced by variety name (will sort by number)
                 ['label'=>"Total for variety", 'col'=>"tmp_total",         'w'=>" 5%", 'noSearch'=>true, 'noSort'=>true ], // replaced by total adoptions for this variety, cannot be sorted from db data
        ];
        $raConfig = $this->GetConfigTemplate(
            ['sessNamespace'        => 'SLAdoptionManager',
             'cid'                  => 'A',

             // A=Adoption, D=Donation -- different from sldb where A=Accession, D=Adoption
             'kfrel'                => $this->oMbrContacts->oDB->Kfrel('AxM_D_P_S'),
             'raListConfig_cols'    => $cols,
             'raSrchConfig_filters' => $cols,  // conveniently, we can use the same format as cols (because filters can be cols or aliases)
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
        return( $this->DrawSearch() );
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
            // show variety name
            $raRow['fk_sl_pcv'] = @$raRow['P_name']." ".@$raRow['S_psp']." ($kPcv)";

            // show total adoptions for this variety
            $raAdopt = $this->oSLDB->Get1List('D', 'amount', "fk_sl_pcv='$kPcv'");
            $raRow['tmp_total'] = array_sum($raAdopt);
        } else {
            // don't show zero
            $raRow['fk_sl_pcv'] = "";
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

        $sVarietyAdoptions = "";
        if( $oForm->Value('P_name') ) {
            $sVarietyAdoptions = "Adoption history for <b>{$oForm->Value('P_name')}</b> {$oForm->Value('S_name_en')} :";
        }

        $s =  "<style>
               .slAdoptionFormInfo { border:1px solid #aaa; margin:2em; padding:1em }
               </style>

               <div class='container-fluid'>
               <div class='row'><div class='col-md-6'>

               |||BOOTSTRAP_TABLE(class='col-md-4'|class='col-md-8')
               ||| *Adopter*    || $sAdopter ([[text:fk_mbr_contacts|readonly]])
               ||| *Request*    || [[text:sPCV_request|readonly]]
               ||| *Amount*     || [[text:amount|readonly]]
               ||| *Received*   || [[text:D_date_received|readonly]]
               ||| &nbsp        || &nbsp;
               ||| *Notes*      || {colspan='2'} ".$oForm->TextArea( "notes", ['width'=>'90%','nRows'=>'2'] )."
               ||| &nbsp;                    || \n
               |||ENDTABLE

               </div><div class='col-md-6'>

               |||BOOTSTRAP_TABLE(class='col-md-4'|class='col-md-8')
               ||| *Variety adopted*    || [[text:fk_sl_pcv|size=30]]
               ||| &nbsp;               || {$oForm->Value('P_name')} {$oForm->Value('S_name_en')}
               |||ENDTABLE

               <div class='slAdoptionFormInfo'>$sVarietyAdoptions</div>

               <div class='slAdoptionFormInfo'>$sAdopter has these adoptions:</div>

              </div></div>

              [[hiddenkey:]]
              <input type='submit' value='Save'>
              </div>";

        return( $s );
    }
}
