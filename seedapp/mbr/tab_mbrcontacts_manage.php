<?php

/* tab_mbrcontacts_manage
 *
 * Copyright 2024 Seeds of Diversity Canada
 *
 * TabSet tab for advanced management of member contact list
 */


class MbrContactsTabManage
{
    private $oApp;
    private $oContacts;
    private $oForm;

    private $kMbrFrom;
    private $kfrMbrFrom = null;
    private $kMbrTo;
    private $kfrMbrTo = null;
    private $raMbr = [0 => ['k'=>0, 'kfr'=>null, 'refs'=>[]],       // left hand column (member to be removed)
                      1 => ['k'=>0, 'kfr'=>null, 'refs'=>[]] ];     // right hand column (member to receive moved records)

    function __construct( SEEDAppConsole $oApp, Mbr_Contacts $oContacts )
    {
        $this->oApp = $oApp;
        $this->oContacts = $oContacts;
        $this->oIntegrity = new MbrIntegrity($oApp);
    }

    function Init()
    {
        $this->oForm = new SEEDCoreForm('Plain');
        $this->oForm->Update();

        $this->raMbr[0]['k'] = $this->oForm->Value('kMbrFrom');
        $this->raMbr[1]['k'] = $this->oForm->Value('kMbrTo');

        foreach( [0,1] as $i ) {
            if( $this->raMbr[$i]['k'] ) {
                // get mbr_contacts kfrs : N.B. These are retrieved even if _status-1 so you have to look at that
                $this->raMbr[$i]['kfr'] = $this->oContacts->oDB->GetKFR('M',$this->raMbr[$i]['k']);
            }
        }

        switch($this->oForm->Value('cmd')) {
            case 'Move':
                if( $this->raMbr[0]['k'] && $this->raMbr[1]['k'] ) {
                    /* Change all fk_mbr_contacts from kMbrFrom to kMbrTo
                     */
                    $this->oIntegrity->MoveContactReferences( $this->raMbr[0]['k'], $this->raMbr[1]['k'] );
                    $this->oApp->Log("mbr_contacts.log", "MoveContactReferences(): changed all fk_mbr_contacts of {$this->raMbr[0]['k']} to {$this->raMbr[1]['k']}" );
                }
                break;
            case 'Delete':
                if( $this->raMbr[0]['k'] ) {
                    /* Delete from mbr_contacts and SEEDSession_Users
                     */
                    if( $this->raMbr[0]['kfr'] ) {
                        $this->raMbr[0]['kfr']->StatusSet( KeyframeRecord::STATUS_DELETED );
                        $this->raMbr[0]['kfr']->PutDBRow();
                    }
                    /* Delete SEEDSession_User and metadata records
                     */
                    (new SEEDSessionAccountDB2($this->oApp->kfdb, $this->oApp->sess->GetUID(), ['logdir'=>$this->oApp->logdir, 'dbname'=>$this->oApp->DBName('seeds1')]))
                        ->DeleteUser($this->raMbr[0]['k']);
                }
                break;
        }

        // do this after Move cmd
        foreach( [0,1] as $i ) {
            if( $this->raMbr[$i]['k'] ) {
                // get references to each mbr
                $this->raMbr[$i]['refs'] = $this->oIntegrity->WhereIsContactReferenced($this->raMbr[$i]['k']);
            }
        }
    }

    function ControlDraw() { return(""); }

    function ContentDraw()
    {
        $s = $sCol1 = $sCol2 = $sCol3 = "";

        $this->kMbrFrom = $this->raMbr[0]['k'];
        $this->kMbrTo = $this->raMbr[1]['k'];
        $this->kfrMbrFrom = $this->raMbr[0]['kfr'];
        $this->kfrMbrTo = $this->raMbr[1]['kfr'];

        /* State 0: draw form to choose the member number that will be removed
         */
        if( !$this->raMbr[0]['k'] ) {
            $sCol1 = "<form method='post'>
                      Choose a member number that you want to remove<br/>
                      {$this->oForm->Text('kMbrFrom')}<br/>
                      <input type='submit' value='Show Member Details'/>
                      </form>";
            goto draw;
        }

        /* State 1: a member number has been chosen for removal.
         *          If there are no referenced records give a button to delete that member number
         *          If there are referenced records, draw form to choose the member number to move records to
         */
        $sCol1 = $this->detailsOfMbr(0);

        if( $this->isValidMbr(0) ) {
            $sCol1 .= $this->hasRefs(0)
                        ? "<div class='alert alert-danger'>Cannot delete</div>"
                        : "<div class='alert alert-success'>There are no attached records, so this number can be deleted.</div>";
        }

        if( !$this->raMbr[1]['k'] ) {
            $sCol2 = "<form method='post'>
                      {$this->oForm->Hidden('kMbrFrom')}
                      Choose another number for the same member<br/>
                      {$this->oForm->Text('kMbrTo')}<br/>
                      <input type='submit' value='Show Member Details'/>
                      </form>";
            goto draw;
        }

        $sCol2 = $this->detailsOfMbr(1);

        /* State 2: a member number has been chosen in both forms.
         *          Under the right conditions, show a third column with a control to move records from one member to the other, or delete the first member.
         */
        if( $this->hasRefs(0) && $this->isValidMbr(1) ) {
            $sCol3 = "<form method='post'>
                      {$this->oForm->Hidden('kMbrFrom')}
                      {$this->oForm->Hidden('kMbrTo')}
                      <h4>Move records from {$this->raMbr[0]['k']} to {$this->raMbr[1]['k']}</h4>
                      <p><input type='submit' name='cmd' value='Move'/></p>
                      </form>";
        } else if( $this->isValidMbr(0) && !$this->hasRefs(0) ) {
            $sCol3 = "<form method='post'>
                      {$this->oForm->Hidden('kMbrFrom')}
                      {$this->oForm->Hidden('kMbrTo')}
                      <h4>Delete the left-hand member number {$this->raMbr[0]['k']}</h4>
                      <input type='submit' name='cmd' value='Delete'/>
                      </form>";
        }

        draw:
        $s .= "<div class='container-fluid'><div class='row'>
               <div class='col-md-2'>$sCol1</div>
               <div class='col-md-2'>$sCol2</div>
               <div class='col-md-3'>$sCol3</div>";

        return( $s );
    }

    private function isValidMbr( int $iMbr )  { return( $this->raMbr[$iMbr]['kfr'] && $this->raMbr[$iMbr]['kfr']->Value('_status')==0 ); }

    private function hasRefs( int $iMbr )     { return( $this->raMbr[$iMbr]['refs'] && $this->raMbr[$iMbr]['refs']['nTotal'] ); }

    private function detailsOfMbr( int $iMbr )
    {
        $s = "<h4>Details of member {$this->raMbr[$iMbr]['k']}</h4>"
            .($this->isValidMbr($iMbr)
              ? "<div>".Mbr_Contacts::DrawAddressBlockFromRA($this->raMbr[$iMbr]['kfr']->ValuesRA())."</div>
                 <div style='margin-top:20px;padding:5px;background-color:#ddd'>References:<br/></br/>
                 {$this->oIntegrity->ExplainContactReferencesShort($this->raMbr[$iMbr]['refs'])}</div>"
              : "<div class='alert alert-danger'>This member has been deleted</div>");

        return( $s );
    }
}
