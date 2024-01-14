<?php

include_once( SEEDROOT."Keyframe/KeyframeUI.php" );
include_once( SEEDLIB."mbr/MbrIntegrity.php" );

class MbrContactsTabContacts extends KeyframeUI_ListFormUI // implements Console02TabSet_Worker or a flavour that is a KeyframeUI_ListFormUI
{
    private $oContacts;

    function __construct( SEEDAppConsole $oApp, Mbr_Contacts $oContacts )
    {
        $this->oContacts = $oContacts;
        parent::__construct($oApp, $this->getListFormConfig());
    }

    function Init()
    {
        parent::Init();
    }

    function ControlDraw()
    {
        $s = "";

        return( $s );
    }

    function ContentDraw()
    {
        return( $this->ContentDraw_NewDelete() );
    }

    private function getListFormConfig()
    {
        $raConfig = [
            'sessNamespace' => "MbrContacts_Contacts",
            'cid'   => 'M',
            'kfrel' => $this->oContacts->oDB->GetKfrel('M'),
            'KFCompParms' => ['raSEEDFormParms'=>['DSParms'=>['fn_DSPreStore'=> [$this,'contactsPreStore'], 'fn_DSPreOp'=>[$this,'contactsPreOp']]]],

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

    function contactsPreOp( Keyframe_DataStore $oDS, $op )
    {
        $bOk = false;   // if op=='d' return true if it's okay to delete

        if( $op != 'd' && $op != 'h' ) {
            $bOk = true;
            goto done;
        }

        // Don't delete a contact if it's referenced in a table (return false to disallow delete)
        // This function only tests for fk rows with _status==0 because deletion causes the contact row to be _status=1 so
        // referential integrity is preserved if all related rows are "deleted"

        $bDelete = false;

        if( $oDS && $oDS->Key() ) {
            $oMI = new MbrIntegrity($this->oApp);
            $ra = $oMI->WhereIsContactReferenced($oDS->Key());
            if( $ra['nTotal'] ) {
                $this->oComp->oUI->SetErrMsg( "Cannot delete contact {$oDS->Key()}:<br/><ul>{$oMI->ExplainContactReferencesLong($ra)}</ul>" );
            } else {
                $this->oComp->oUI->SetUserMsg( "Deleted {$oDS->Key()}: {$oDS->Value('firstname')} {$oDS->Value('lastname')} {$oDS->Value('company')}" );
                $bOk = true;
            }
        }
        done:
        return( $bOk );
    }

    function contactsFormTemplate()
    {
        $s = "<h4>".($this->oComp->IsNewRowState() ? "New" : "Edit")." Contact</h4>
              <div style='font-size:x-small;margin-bottom:10px;'>
              The information in this database is private and confidential. It may only be used by Seeds of Diversity staff, and core volunteers.
              </div>";

        $s .= "<div class='container-fluid'><div class='row'><div class='col-md-6'>"

             ."|||BOOTSTRAP_TABLE(class='col-md-4'|class='col-md-8')
               ||| *Contact #*               || [[Key: | readonly]]
               ||| *First / last name*       || [[Text:firstname|width:45%]]  [[Text:lastname|width:45%]]
               ||| *First / last name 2*     || [[Text:firstname2|width:45%]] [[Text:lastname2|width:45%]]
               ||| *Company*                 || [[Text:company|width:91%]]
               ||| *Email*                   || [[Text:email|width:91%]]
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
              </div>";

            return( $s );
    }
}
