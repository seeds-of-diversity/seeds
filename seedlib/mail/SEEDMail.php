<?php

/* SEEDMail.php
 *
 * Manage sending email via SEEDMail
 *
 * Copyright (c) 2010-2021 Seeds of Diversity Canada
 */

include_once( "SEEDMailDB.php" );
include_once( SEEDROOT."DocRep/DocRep.php" );

class SEEDMail
/*************
    Class for a SEEDMail record
 */
{
    public  $oApp;
    private $oDB;
    private $kfr;

    function __construct( SEEDAppConsole $oApp, $k )
    {
        $this->oApp = $oApp;
        $this->oDB = new SEEDMailDB( $oApp );
        $this->kfr = $k ? $this->oDB->GetKFR('M',$k) : $this->oDB->KFRel('M')->CreateRecord();
    }

    function Key()  { return( $this->kfr->Key() ); }

    function GetKFR()  { return($this->kfr); }

    function GetMessageText( $raParms = [] )    // blank is the default behaviour
    {
        $s = "";

        if( $this->kfr ) {
            $s = $this->kfr->Value('sBody');
            $raVars = []; // $raVars = SEEDCore_ParmsURL2RA( $kfrStage->Value('sVars') );  have to choose a kfrStage first
            $s = self::ExpandMessage( $this->oApp, $s, ['raVars'=>$raVars] );
        }

        return( $s );
    }

    static function ExpandMessage( SEEDAppSessionAccount $oApp, $sMsg, $raParms )
    /****************************************************************************
        If the given string is a docrep code, fetch it.
        Expand SEEDTags in the message.

        N.B. This is used by SEEDMail and SEEDMailStaged, or whereever message texts are retrieved so it cannot depend on
             any local state (e.g. the local kfr)
     */
    {
        $bDocRepFetch = SEEDCore_ArraySmartBool( $raParms, 'bDocRepFetch', true );  // fetch docrep by default (if it's a docrep code)
        $bExpandTags  = SEEDCore_ArraySmartBool( $raParms, 'bExpandTags', true );   // expand tags by default
        $raVars       = @$raParms['raVars'] ?: [];

        if( $bDocRepFetch && SEEDCore_StartsWith( $sMsg, "DocRep:" ) ) {
            $ra = explode( ":", $sMsg );
            $db = @$ra[1];
            $docid = @$ra[2];

            if( $docid ) {
                $oDocRepDB = DocRepUtil::New_DocRepDB_WithMyPerms( $oApp, ['bReadonly'=>true, 'db'=>$db] );
                $oDoc = new DocRepDoc2( $oDocRepDB, $docid );
                $sMsg = $oDoc->GetText('');
            }
        }

        if( $bExpandTags ) {
            include_once( SEEDLIB."SEEDTemplate/masterTemplate.php" );
            include_once( SEEDCORE."SEEDSessionAccountTag.php" );
$raVars['kMbrTo']=2;
            // override the default cautious SessionAccountTagHander with this more permissive one
            $raConfig = ['oSessionAccountTag' => new SEEDSessionAccountTagHandler($oApp,
                                                        ['bAllowKMbr'=>true, 'bAllowPwd'=>true,
                                                         'db'=>'seeds1'])];
            $oTmpl = (new SoDMasterTemplate( $oApp, $raConfig ))->GetTmpl();
            $sMsg = $oTmpl->ExpandStr( $sMsg, $raVars );
        }

        return( $sMsg );
    }

    function Store( $raParms )
    /*************************
        Copy the given parms to the current SEEDMail record and store it in the db
     */
    {
        if( !$this->kfr->Key() ) {
            // make sure defaults are set for new record
            $this->kfr->SetValue( 'eStatus', 'NEW' );
        }
        foreach( $raParms as $k=>$v ) {
            $this->kfr->SetValue( $k, $v );
        }
        return( $this->kfr->PutDBRow() ? $this->kfr->Key() : 0 );
    }

    function StageMail()
    {
        if( !$this->kfr )  goto done;

        $raAddr = explode( "\n", $this->kfr->Value('sAddresses') );
        foreach( $raAddr as $e ) {
            $e = trim($e);

            $oMS = new SEEDMailStaged( $this );
            $oMS->Store( ['fk_SEEDMail'=>$this->kfr->Key(), 'eStageStatus'=>'READY', 'sTo'=>$e] );
        }
        $this->Store( ['eStatus'=>'READY'] );

        done:
        return;
    }

    function DeleteMail( $kMail )
    {

    }
}


class SEEDMailStaged
/*******************
    Class for a SEEDMail_Staged record
 */
{
    private $oSMail;
    private $oDB;
    private $kfr;

    function __construct( SEEDMail $oSMail )
    {
        $this->oSMail = $oSMail;
        $this->oDB = new SEEDMailDB( $oSMail->oApp );
    }

    function Store( $raParms )
    /*************************
        Copy the given parms into the SEEDMail_Staged record and store it in the db
     */
    {
        $this->kfr = $this->oDB->KFRel('MS')->CreateRecord();
        foreach( $raParms as $k=>$v ) {
            $this->kfr->SetValue( $k, $v );
        }
        $this->kfr->SetValue('fk_SEEDMail', $this->oSMail->Key() );
        if( !$this->kfr->Value('tsSent') ) $this->kfr->SetNull('tsSent');   // force NULL for db
        $this->kfr->PutDBRow();
    }
}


include_once( SEEDCORE."SEEDEmail.php" );
class SEEDMailSend
{
    private $oApp;
    private $oDB;
    private $dbname;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oDB = new SEEDMailDB( $oApp );
        $this->dbname = $oApp->GetDBName('seeds2');
    }

    function SendOne()
    /*****************
        Send one email that is marked 'READY'
        Return the _key of the email that was attempted, or 0 if no more are 'READY'
     */
    {
        $sMsg = "";
        $ok = false;

        $kfrStage = $this->oDB->GetKFRCond( 'MS', "eStageStatus='READY'" );
        if( !$kfrStage || !$kfrStage->Key() ) {
            $sMsg = "No emails are ready to send";
            goto done;
        }

        $kMail = $kfrStage->Value('fk_SEEDMail');   // master SEEDMail record for this email

        /* sTo is one of: kMbr, email, (email,email,...)
         *
         * if is_numeric, convert the single kMbr to an email, otherwise just use sTo
         */
        if( is_numeric($sTo = $kfrStage->Value('sTo')) ) {
            // use Mbr_Contacts to get the email
        }
        $sFrom = $kfrStage->Value("M_sFrom");
        $sSubject = $kfrStage->Value("M_sSubject");

        if( !$sTo || !$sFrom || !$sSubject ) {
            $sMsg = $kfrStage->Key()." cannot send. To='$sTo', From='$sFrom', Subject='$sSubject'";
            $kfrStage->SetValue( "eStageStatus", "FAILED" );
            $kfrStage->PutDBRow();
            $this->oApp->Log("mailsend.log", $sMsg);
            goto done;
        }


        $raVars = SEEDCore_ParmsURL2RA( $kfrStage->Value('sVars') );
        // array( 'kMbrTo' => $kMbr, 'lang'=>$lang )
        //$oDocRepWiki->AddVars( $raDRVars );
        //$oDocRepWiki->AddVar( 'kMbrTo', $kMbr );
        //$oDocRepWiki->AddVar( 'sEmailTo', $sEmailTo );
        //$oDocRepWiki->AddVar( 'sEmailSubject', $sEmailSubject );
        $sBody = SEEDMail::ExpandMessage( $this->oApp, $kfrStage->Value('M_sBody'), ['raVars'=>$raVars] );

        $ok = SEEDEmailSend( $sFrom, $sTo, $sSubject, "", $sBody, [] );
        $kfrStage->SetValue( "iResult", $ok );    // we only get a boolean from mail()
        //$kfrStage->SetValue( "eStageStatus", $ok ? "SENT" : "FAILED");
        $kfrStage->PutDBRow();
        $this->oApp->kfdb->Execute( "UPDATE {$this->dbname}.SEEDMail_Staged SET tsSent=NOW() WHERE _key='{$kfrStage->Key()}'" );
        $this->oApp->Log( "mailsend.log", $kfrStage->Expand( "[[_key]] [[fk_SEEDMail]] [[eStageStatus]] [[sTo]] [[tsSent]]" ) );

        /* If this is the first message of a mail batch, update the SEEDMail record status
         */
        if( $kfrStage->Value('M_eStatus') == 'READY' ) {
            $oMail = new SEEDMail( $this->oApp, $kMail );
            $oMail->Store( ['eStatus'=>'SENDING'] );
        }

        /* If there are no more READY emails for this mail doc, record that SENDING is finished.
         * Clear SEEDMail.sAddresses and the SEEDMail_Staged records because the relevant information has been logged and
         * these take a lot of space in the db.
         */
        if( !$this->oDB->GetKFRCond( 'MS', "eStageStatus='READY' AND fk_SEEDMail='$kMail'" ) ) {
            $oMail = new SEEDMail( $this->oApp, $kMail );
            $oMail->Store( ['eStatus'=>'DONE'] );
            $oMail->Store( ['sAddresses'=>''] );

            //$this->oApp->kfdb->Execute( "DELETE FROM {$this->dbname}.SEEDMail_Staged WHERE fk_SEEDMail='$kMail'" );
        }

        done:
        return( [$ok ? $kfrStage->Key() : 0, $sMsg] );
    }
}
