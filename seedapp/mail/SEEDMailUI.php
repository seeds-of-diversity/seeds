<?php

/* SeedMailUI.php
 *
 * Copyright 2021 Seeds of Diversity Canada
 *
 * UI for apps that send email using SEEDMail
 */

include_once( SEEDROOT.'Keyframe/KeyframeForm.php' );

class SEEDMailUI
{
    private $oApp;
    private $oDB;
    private $kMail = 0;

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

        if( SEEDInput_Str('cmd') == 'CreateMail' ) {
            // store an empty mail record and make it current
            $oM = new SEEDMail( $oApp, 0 );
            $this->kMail = $oM->Store( ['sSubject'=>'New'] );
        } else {
            $oForm = new KeyframeForm( $this->oDB->KFRel('M'), 'M' );
            $oForm->Update();
        }
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
            $sClass = $ra['_key'] == $this->CurrKMail() ? "maillist-item-selected" : "";
            $s .= "<div class='maillist-item $sClass'
                        onclick='location.replace(\"{$this->oApp->PathToSelf()}?kMail={$ra['_key']}\");'>"
                     ."Message {$ra['_key']}<br/>"
                     ."Subject: <strong>{$ra['sSubject']}</strong><br/>"
                     ."From: {$ra['sFrom']}<br/>"

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

        $oForm = new KeyframeForm( $this->oDB->KFRel('M'), 'M' );
        if( !($kfr = $this->oDB->GetKFR('M', $this->kMail)) )  goto done;
        $oForm->SetKFR( $kfr );

        $oFE = new SEEDFormExpand( $oForm );
        $s .= "<form method='post' action='{$this->oApp->PathToSelf()}'>"
             .$oForm->HiddenKey()
             ."<div class='container-fluid'>"
             .$oFE->ExpandForm(
                    "<table class='mailitem-form-table'>"
                   ."<tr><td style='width:50%'>Document: <br/> [[Text:docnum]]</td><td><div style='background-color:#bde'>Document name or number</div></td></tr>"
                   ."<tr><td style='width:50%'>From: <br/> [[Text:sFrom]]</td><td><input type='submit' value='Save'/></td></tr>"
                   ."<tr><td colspan='2'>Subject: <br/> [[Text:sSubject | width:100%]]</td></tr>"
                   ."<tr><td colspan='2'>Email addresses / member numbers: <br/> [[TextArea:sAddresses | width:100% nRows:20]]</td></tr>"
              )
             ."</form></div>";

        done:
        return( $s );
    }


}
