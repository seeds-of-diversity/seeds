<?php

/* SeedMailUI.php
 *
 * Copyright 2021 Seeds of Diversity Canada
 *
 * UI for apps that send email using SEEDMail
 */

include_once( SEEDROOT.'Keyframe/KeyframeForm.php' );
include_once( SEEDLIB.'mail/SEEDMail.php' );

class SEEDMailUI
{
    private $oApp;
    private $oDB;
    private $kMail = 0;
    private $oMailItemForm;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oDB = new SEEDMailDB( $oApp );
    }

    function CurrKMail()  { return( $this->kMail ); }

    function Init()
    {
        /* kMail is the current message.
         * If creating a new message set kMail to that,
         * else allow Update of current message.
         */
        $this->kMail = $this->oApp->oC->oSVA->SmartGPC('kMail');

        $this->oMailItemForm = new KeyframeForm( $this->oDB->KFRel('M'), 'M', ['DSParms'=>['fn_DSPreStore'=>[$this,'PreStoreMailItem']]] );

        $cmd = SEEDInput_Str('cmd');
        if( $cmd == 'CreateMail' ) {
            // store an empty mail record and make it current
            $oM = new SEEDMail( $this->oApp, 0 );
            $this->kMail = $oM->Store( ['sSubject'=>'New'] );
            $this->oMailItemForm->SetKFR( $oM->GetKFR() );
        } else {
            $this->oMailItemForm->Update();

            if( $this->kMail && ($kfr = $this->oDB->GetKFR('M', $this->kMail)) ) {
                $this->oMailItemForm->SetKFR( $kfr );
            }
        }

        if( $cmd == 'Approve' ) {
            if( $this->kMail ) {
                $oM = new SEEDMail( $this->oApp, $this->kMail );
                $oM->StageMail();
            }
        }
    }

    function PreStoreMailItem( KeyFrame_DataStore $oDS )
    {
        if( !$oDS->Value('eStatus') )  $oDS->SetValue('eStatus', 'NEW');

        // convert whitespace and commas to \n
        $oDS->SetValue('sAddresses', str_replace([' ',','], "\n", $oDS->Value('sAddresses')) );

        return( true );
    }

    function GetMessageList( $eStatus = '' )
    /***************************************
        Get the list of messages of given eStatus, highlight the current one
     */
    {
        $s = "<style>
              .maillist-item { cursor:pointer; border:1px solid #888; padding:10px; background-color:#eee }
              .maillist-item-selected { font-weight:bold;background-color:#bde }
              </style>
              ";

        $cond = $eStatus ? "eStatus='{$this->oApp->kfdb->Escape($eStatus)}'" : "";
        $raM = $this->oDB->GetList( 'M', $cond );

        foreach( $raM as $ra ) {
            $bCurr = $ra['_key'] == $this->CurrKMail();
            $sClass = $bCurr ? "maillist-item-selected" : "";

            $sLeft = "{$ra['_key']}<br/>{$ra['eStatus']}<br/>".substr($ra['_created'],0,10);
            $sMiddle = "Subject: <strong>{$ra['sSubject']}</strong><br/>"
                      ."From: {$ra['sFrom']}<br/>"
                      .($ra['eStatus']<>'NEW' ? ("To: ".$this->oDB->GetCount('MS', "fk_SEEDMail='{$ra['_key']}'")." recipients<br/>") : "")
                      ."Doc: {$ra['sBody']}<br/>"
                      ;
            $buttonApprove = $ra['eStatus'] == 'NEW'
                        ? ("<form action=''>
                            <input type='hidden' name='cmd' value='Approve'/>
                            <input type='submit' value='Approve'".($bCurr?"":"disabled")."/></form>")
                        : "";
            $s .= "<div class='maillist-item $sClass'
                        onclick='location.replace(\"{$this->oApp->PathToSelf()}?kMail={$ra['_key']}\");'>"
                     ."<div class='row'>"
                         ."<div class='col-md-2'>$sLeft</div>"
                         ."<div class='col-md-8'>$sMiddle</div>"
                         ."<div class='col-md-2'>$buttonApprove</div>"
                     ."</div>"
                 ."</div>";
        }

        return( $s );
    }

    function MailItemForm()
    /**********************
        Draw the form for the current mail item
     */
    {
        $s = "<style>
              .mailitem-form-table    { width: 100%; }
              .mailitem-form-table td { padding-bottom:10px }
             </style>
             ";

        $oFE = new SEEDFormExpand( $this->oMailItemForm );
        $s .= "<form method='post' action='{$this->oApp->PathToSelf()}'>"
             .$this->oMailItemForm->HiddenKey()
             ."<input type='hidden' name='kMail' value='{$this->oMailItemForm->GetKey()}'/>"
             ."<div class='container-fluid'>"
             .$oFE->ExpandForm(
                    "<table class='mailitem-form-table'>"
                   ."<tr><td style='width:50%'>Document: <br/> [[Text:sBody]]</td><td><div style='background-color:#bde'>Document name or number</div></td></tr>"
                   ."<tr><td style='width:50%'>From: <br/> [[Text:sFrom]]</td><td><input type='submit' value='Save'/></td></tr>"
                   ."<tr><td colspan='2'>Subject: <br/> [[Text:sSubject | width:100%]]</td></tr>"
                   ."<tr><td colspan='2'>Email addresses / member numbers: <br/> [[TextArea:sAddresses | width:100% nRows:20]]</td></tr>"
              )
             ."</form></div>";

        done:
        return( $s );
    }


}
