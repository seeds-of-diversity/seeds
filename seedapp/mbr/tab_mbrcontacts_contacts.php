<?php

include_once( SEEDROOT."Keyframe/KeyframeUI.php" );

class MbrContactsTabContacts extends KeyframeUI_ListFormUI // implements Console02TabSet_Worker or a flavour that is a KeyframeUI_ListFormUI
{
    private $oContacts;

    function __construct( SEEDAppConsole $oApp, Mbr_Contacts $oContacts )
    {
        $this->oContacts = $oContacts;
        parent::__construct($oApp, $this->getListFormConfig());
    }

    function Init()  { parent::Init(); }

    function ControlDraw()
    {
        $s = "";

        return( $s );
    }

    function ContentDraw()
    {
        $cid = $this->oComp->Cid();

        $s = $this->DrawStyle()
           ."<style></style>"
           ."<div>{$this->DrawList()}</div>"
           ."<div style='border:1px solid #777; padding:15px'>"
               ."<div style='margin-bottom:5px'><a href='?sf{$cid}ui_k=0'><button>New</button></a>&nbsp;&nbsp;&nbsp;<button>Delete</button></div>"
               ."<div style='width:100%;padding:20px;border:2px solid #999'>".$this->DrawForm()."</div>"
           ."</div>";

        return( $s );
    }

    private function getListFormConfig()
    {
        $raConfig = [
            'sessNamespace' => "MbrContacts_Contacts",
            'cid'   => 'M',
            'kfrel' => $this->oContacts->oDB->GetKfrel('M'),
            'KFCompParms' => ['raSEEDFormParms'=>['DSParms'=>['fn_DSPreStore'=> [$this,'contactsPreStore']]]],

            'raListConfig' => [
                'bUse_key' => true,     // probably makes sense for KeyFrameUI to do this by default
                'cols' => [
                    [ 'label'=>'Contact #',  'col'=>'_key' ],
                    [ 'label'=>'First Name', 'col'=>'firstname' ],
                    [ 'label'=>'Last Name',  'col'=>'lastname' ],
                    [ 'label'=>'Company',    'col'=>'company' ],
                    [ 'label'=>'Email',      'col'=>'email' ],
                    [ 'label'=>'Province',   'col'=>'province' ],
                    [ 'label'=>'Expiry',     'col'=>'expires' ],
                ],
                'fnRowTranslate' => [$this,'contactsListRowTranslate'],
            ],

            'raFormConfig' => [ 'fnExpandTemplate'=>[$this,'contactsFormTemplate'] ],
        ];
        $raConfig['raSrchConfig']['filters'] = $raConfig['raListConfig']['cols'];     // conveniently the same format

        return( $raConfig );
    }

    function contactsListRowTranslate( $raRow )
    {
        return( $raRow );
    }

    function contactsPreStore( Keyframe_DataStore $oDS )
    {
        return( true );

    }
    function contactsFormTemplate()
    {
        $s = "<h4>Edit Contact</h4>
              <div style='font-size:x-small;margin-bottom:10px;'>
              The information in this database is private and confidential. It may only be used by Seeds of Diversity staff, and core volunteers.
              </div>";

        $s .= "<div class='container-fluid'><div class='row'><div class='col-md-6'>"

             ."|||BOOTSTRAP_TABLE(class='col-md-4'|class='col-md-8')
               ||| *Contact #*               || [[Key: | readonly]]
               ||| *First / last name*       || [[Text:firstname|width:45%]] [[Text:lastname|width:45%]]
               ||| *First / last name 2*     || [[Text:firstname2]] [[Text:lastname2]]
               ||| *Company*                 || [[Text:company|width:90%]]
               ||| *Email*                   || [[Text:email]]
               ||| *Phone*                   || [[Text:phone]]
               ||| &nbsp;                    || \n
               ||| *Referral*                || [[Text:referral]]
               ||| *Language*                || [lang: select EN / FR / Both]
               ||| *E-Bulletin*              || [bNoEbull: checkbox]
               ||| *Donor Appeals*           || [bNoDonorAppeals: checkbox]
               ||| &nbsp;                    || \n
               |||ENDTABLE "

             ."</div><div class='col-md-6'>"

             ."|||BOOTSTRAP_TABLE(class='col-md-4'|class='col-md-8')
               ||| &nbsp;     || \n
               ||| *Address*  || [[Text:address|width:90%]]
               ||| *City*     || [[Text:city|width:90%]]
               ||| *Province* || [[Text:province]]
               ||| *Postcode* || [[Text:postcode]]
               ||| *Country*  || [[Text:country]]
               ||| &nbsp;     || \n
               ||| *Expires*  || [[Text:expires]]
               ||| Printed MSD|| [printMSD checkbox]
               ||| Last renew || [[Text:lastrenew]]
               ||| Start date || [[Text:startdate|readonly]]
               ||| Status     || ?
               |||ENDTABLE "
            ."</div>
              </div>
              <input type='submit' value='Save'>
              <p>The div containing the form element has width 90% but it should be 100%.</p>
              </div>";

            return( $s );
    }
}
