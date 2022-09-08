<?php

/* SEEDMail.php
 *
 * Manage sending email via SEEDMail
 *
 * Copyright (c) 2010-2022 Seeds of Diversity Canada
 *
 * SEEDMailCore     - core class implements SEEDMailDB and methods non-specific to a particular mail item
 * SEEDMailMessage  - implement a SEEDMail record (an email message that can be staged any number of times)
 * SEEDMailStaged   - implement a SEEDMail_Staged record (an instance of a message ready to be sent to someone)
 * SEEDMailSend     - class for sending staged messages
 */

include_once( "SEEDMailDB.php" );
include_once( SEEDROOT."DocRep/DocRep.php" );

class SEEDMailCore
//unextend when everything uses a method that encapsulates SEEDMailDB
extends SEEDMailDB
{
    public $oApp;
    private $oDB;

    function __construct( SEEDAppSessionAccount $oApp, $raConfig = [] )
    {
        parent::__construct( $oApp, $raConfig );
        $this->oApp = $oApp;
        $this->oDB = new SEEDMailDB($oApp, $raConfig);
    }


    function KFRelMessage() :KeyFrame_Relation   { return( $this->oDB->KFRel('M') ); }
    function KFRelStaged()  :KeyFrame_Relation   { return( $this->oDB->KFRel('MS') ); }

    function GetKFRMessage( $keyOrName )
    {
        if( is_numeric($keyOrName) ) {
            $kfr = $keyOrName ? $this->oDB->GetKFR('M',$keyOrName) : $this->oDB->KFRel('M')->CreateRecord();
        } else {
            $kfr = $this->oDB->GetKFRCond('M',"sName='{$this->oApp->kfdb->EscapeString($keyOrName)}'")
                         ?: $this->oDB->KFRel('M')->CreateRecord();
        }
        return( $kfr );
    }

    function GetKFRStagedReady()
    /***************************
        Get one READY email from the staging table.
     */
    {
        return( $this->oDB->GetKFRCond( 'MS', "eStageStatus='READY'" ) );
    }

    function GetKFRStaged( $kStaged )
    /********************************
        Get the given email from the staging table
     */
    {
        return( $kStaged ?  : $this->oDB->KFRel('MS')->CreateRecord() );
    }

    function GetStagedList( $sCond )
    /*******************************
        Return array of staged emails that match the condition
     */
    {
        return( $this->oDB->GetList('MS',$sCond) );
    }

    function GetStagedCount( $sCond )
    /********************************
        Return the number of staged emails that match the condition
     */
    {
        return( $this->oDB->GetCount( 'MS', $sCond ) );
    }

    function SetTSSent( $kStaged )  // this can be done via KFR once kfr->SetVerbatim() is implemented
    {
        $this->oApp->kfdb->Execute( "UPDATE {$this->dbname}.SEEDMail_Staged SET tsSent=NOW() WHERE _key='$kStaged'" );
    }

    static function ExpandMessage( SEEDAppSessionAccount $oApp, $sMsg, $raParms )
    /****************************************************************************
        Expand the given email message using SEEDTags.
        If the string is a docrep code, fetch the doc and expand it.

        N.B. This is intended to be independent of any local mailer state so that it can probably be ported to a more generic place.
     */
    {
        $ok = true;

        $bDocRepFetch = SEEDCore_ArraySmartBool( $raParms, 'bDocRepFetch', true );  // fetch docrep by default (if it's a docrep code)
        $bExpandTags  = SEEDCore_ArraySmartBool( $raParms, 'bExpandTags', true );   // expand tags by default
        $raVars       = @$raParms['raVars'] ?: [];

        if( $bDocRepFetch && SEEDCore_StartsWith( $sMsg, "DocRep:" ) ) {
            $ra = explode( ":", $sMsg );
            $db = @$ra[1];
            $docid = @$ra[2];

            if( $docid ) {
                $oDocRepDB = DocRepUtil::New_DocRepDB_WithMyPerms( $oApp, ['bReadonly'=>true, 'db'=>$db] );
                if( ($oDoc = $oDocRepDB->GetDoc($docid)) ) { // new DocRepDoc2( $oDocRepDB, $docid );
                    $sMsg = $oDoc->GetText('');
                } else {
                    $oApp->Log("mailsend.log", "*** Failed to expand mail message - '$docid' not found in DocRep - a blank email was probably sent anyway" );
                    $sMsg = "";
                    $ok = false;
                }
            }
        }

        if( $bExpandTags ) {
            include_once( SEEDLIB."SEEDTemplate/masterTemplate.php" );
            include_once( SEEDCORE."SEEDSessionAccountTag.php" );

            // override the default cautious SessionAccountTagHander with this more permissive one
            $raConfig = ['oSessionAccountTag' => new SEEDSessionAccountTagHandler($oApp,
                                                        ['bAllowKMbr'=>true, 'bAllowPwd'=>true,
                                                         'db'=>'seeds1'])];
            $oTmpl = (new SoDMasterTemplate( $oApp, $raConfig ))->GetTmpl();
            $sMsg = $oTmpl->ExpandStr( $sMsg, $raVars );
        }

        return( [$ok,$sMsg] );
    }
}

class SEEDMailMessage
// unextend when SEEDMail is obsolete
extends SEEDMail
{
    private $oCore;
    private $raConfig;
    private $kfrMsg;

    function __construct( SEEDMailCore $oCore, $keyOrName, $raConfig = [] )
    {
        parent::__construct( $oCore->oApp, $keyOrName, $raConfig );
        $this->oCore = $oCore;
        $this->raConfig = $raConfig;
        $this->kfrMsg = $oCore->GetKFRMessage($keyOrName);
    }

    function GetKFR() { return( $this->kfrMsg ); }

    function AddRecipient( $e )     // e can be email or kMbr
    /**************************
     */
    {
        if( $this->kfrMsg && ($e = trim( str_replace("\n", "", $e) )) ) {
            $this->Store(['sAddresses'=>$this->kfrMsg->Value('sAddresses')."\n$e"]);
        }
    }

    function StageMail()
    /*******************
        Create a SEEDMail_Staged record for each recipient address listed, then clear the recipient addresses,
        and set status to READY
     */
    {
        if( !$this->kfrMsg )  goto done;

        foreach( $this->GetRAUnstagedRecipients() as $e ) {
            if( !($e = trim($e)) ) continue;

            $oMS = new SEEDMailStaged( $this->oCore, 0 );   // create a new record
            $oMS->Store( ['fk_SEEDMail'=>$this->kfrMsg->Key(), 'eStageStatus'=>'READY', 'sTo'=>$e] );
        }

        // clear the unstaged recipient addresses, and mark this message as READY
// the message should not be READY; instead the message staging status should be based simply on the number of READY staged recipients
        $this->Store( ['eStatus'=>'READY', 'sAddresses'=>""] );

        done:
        return;
    }

    function GetRAUnstagedRecipients()
    /*********************************
        Array of email/kMbr of recipients not yet staged, on this message
     */
    {
        return( $this->kfrMsg && ($sAddr = $this->kfrMsg->Value('sAddresses')) ? explode("\n", $sAddr) : [] );
    }

    function GetRAStagedRecipients( $eStageStatus = '' )
    /***************************************************
        Array of email/kMbr of staged recipients on this message, optionally filtered by eStageStatus
     */
    {
        $raOut = [];

        $cond = $eStageStatus ? " AND eStageStatus='$eStageStatus'" : "";
        foreach( $this->oCore->GetStagedList( "fk_SEEDMail='{$this->kfrMsg->Key()}' $cond" ) as $ra ) {
            $raOut[] = $ra['sTo'];
        }
        return( $raOut );
    }

    // probably quicker to count the number of delimiters in the string, but this is easier.
    function GetCountUnstagedRecipients()               { return( count($this->GetRAUnstagedRecipients()) ); }
    // quicker to use SEEDMailDB->GetCount(), but this is easier.
    function GetCountStagedRecipients( $eStageStatus )  { return( count($this->GetRAStagedRecipients($eStageStatus)) ); }


    function Store( $raParms )
    /*************************
        Copy the given parms to the current SEEDMail record and store it in the db
     */
    {
        if( !$this->kfrMsg->Key() ) {
            // make sure defaults are set for new record
            $this->kfrMsg->SetValue( 'eStatus', 'NEW' );
        }
        foreach( $raParms as $k=>$v ) {
            $this->kfrMsg->SetValue( $k, $v );
        }
        return( $this->kfrMsg->PutDBRow() ? $this->kfrMsg->Key() : 0 );
    }

}


// deprecate for SEEDMailCore and SEEDMailMessage
class SEEDMail
/*************
    Class for a SEEDMail record
 */
{
    public  $oApp;
    private $oDB;
    private $kfr;
    private $raConfig;

    function __construct( SEEDAppConsole $oApp, $keyOrName, $raConfig = [] )
    {
        $this->oApp = $oApp;
        $this->raConfig = $raConfig;
// pass this through constructor
        $this->oDB = new SEEDMailCore( $oApp, $raConfig );

        if( is_numeric($keyOrName) ) {
            $this->kfr = $keyOrName ? $this->oDB->GetKFR('M',$keyOrName) : $this->oDB->KFRel('M')->CreateRecord();
        } else {
            $this->kfr = $this->oDB->GetKFRCond('M',"sName='{$this->oApp->kfdb->EscapeString($keyOrName)}'")
                         ?: $this->oDB->KFRel('M')->CreateRecord();
        }
    }

    function Key()  { return( $this->kfr->Key() ); }

    function GetKFR()  { return($this->kfr); }

    function GetMessageText( $raParms = [] )    // blank is the default behaviour
    {
        $s = "";

        if( $this->kfr ) {
            $s = $this->kfr->Value('sBody');
            $raVars = []; // $raVars = SEEDCore_ParmsURL2RA( $kfrStage->Value('sVars') );  have to choose a kfrStage first
            list($okDummy,$s) = SEEDMailCore::ExpandMessage( $this->oApp, $s, ['raVars'=>$raVars] );    // returns $s=='' if failure but that only happens if DocRep can't find msg
        }

        return( $s );
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

    function DeleteMail( $kMail )
    {

    }
}


class SEEDMailStaged
/*******************
    Class for a SEEDMail_Staged record
 */
{
    private $oCore;
    private $kfr;

    function __construct( SEEDMailCore $oCore, $kStaged = 0 )
    {
        $this->oCore = $oCore;
        $this->kfr = $oCore->GetKFRStaged($kStaged);
    }

    function Store( $raParms )
    /*************************
        Copy the given parms into the SEEDMail_Staged record and store it in the db
     */
    {
        if( $this->kfr ) {
            foreach( $raParms as $k=>$v ) {
                $this->kfr->SetValue( $k, $v );
            }

            if( !$this->kfr->Value('tsSent') ) $this->kfr->SetNull('tsSent');   // force NULL for db
            $this->kfr->PutDBRow();
        }
    }
}


include_once( SEEDCORE."SEEDEmail.php" );
include_once( SEEDLIB."mbr/MbrContacts.php" );
class SEEDMailSend
{
    private $oCore;

    function __construct( SEEDMailCore $oCore )
    {
        $this->oCore = $oCore;
    }

    function GetCountReadyToSend()
    /*****************************
        Return the total number of READY emails in the staging table
     */
    {
        return( $this->oCore->GetStagedCount("eStageStatus='READY'") );
    }

    function SendOne()
    /*****************
        Send one email that is marked 'READY'
        Return the _key of the email that was attempted, or 0 if no more are 'READY'
     */
    {
        $sMsg = "";
        $ok = false;

        // Get one random READY email from the staging table
        $kfrStage = $this->oCore->GetKFRStagedReady();
        if( !$kfrStage || !$kfrStage->Key() ) {
            $sMsg = "No emails are ready to send";
            goto done;
        }

        $kMail = $kfrStage->Value('fk_SEEDMail');   // master SEEDMail record for this email
//        $oMessage = new SEEDMailMessage( $this->oCore, $kMail );

        /* sTo is either kMbr or email. Use Mbr_Contacts to try to get full contact info, otherwise just use email.
         */
        $sTo = @$raMbr['email'] ?: $kfrStage->Value('sTo');

        $oMbrContacts = new Mbr_Contacts( $this->oCore->oApp );
        $raMbr = $oMbrContacts->GetBasicValues( $kfrStage->Value('sTo') );
        if( @$raMbr['_key'] ) {
// make and use GetContactNameFromMbrRA()
$sContact = $oMbrContacts->GetContactName($raMbr['_key']);
$sContact = str_replace( ['<','>'], ['',''], $sContact );   // don't let the arbitrary contact name to disrupt the <email> delimiters
            if( $sContact ) $sTo = $sContact." <$sTo>";
        }

        $kMbrTo = intval(@$raMbr['_key']);
        $sFrom = $kfrStage->Value("M_sFrom");
        $sSubject = $kfrStage->Value("M_sSubject");

        if( !$sTo || !$sFrom || !$sSubject ) {
            $sLog = $kfrStage->Key()." cannot send. To='$sTo', From='$sFrom', Subject='$sSubject'";
            $kfrStage->SetValue( "eStageStatus", "FAILED" );
            $kfrStage->PutDBRow();
            $this->oApp->Log("mailsend.log", $sLog);
            goto done;
        }


        $raVars = SEEDCore_ParmsURL2RA( $kfrStage->Value('sVars') );
        $raVars['kMbrTo'] = $kMbrTo;
$raVars['lang'] = $this->oCore->oApp->lang;

        //$oDocRepWiki->AddVars( $raDRVars );
        //$oDocRepWiki->AddVar( 'kMbrTo', $kMbr );
        //$oDocRepWiki->AddVar( 'sEmailTo', $sEmailTo );
        //$oDocRepWiki->AddVar( 'sEmailSubject', $sEmailSubject );
        list($ok,$sBody) = SEEDMailCore::ExpandMessage( $this->oCore->oApp, $kfrStage->Value('M_sBody'), ['raVars'=>$raVars] );

        // if ExpandMessage failed, don't send the message (sBody probably blank) - SEEDMail should have logged the problem (e.g. DocRep doc not found)
        if( $ok ) {
// either here or in SEEDEmail put <html><body> </body></html> around the message if it doesn't already have that
            $ok = SEEDEmailSend( $sFrom, $sTo, $sSubject, "", $sBody, ['bcc'=>['bob@seeds.ca']] );
        }

        $kfrStage->SetValue( "iResult", $ok );    // we only get a boolean from mail()
        $kfrStage->SetValue( "eStageStatus", $ok ? "SENT" : "FAILED");
        $kfrStage->PutDBRow();

// the staging record should be removed, not updated. Get NOW() from kfdb and log everything that's known.
$this->oCore->SetTSSent( $kfrStage->Key() );
$this->oCore->oApp->Log( "mailsend.log", $kfrStage->Expand( "[[_key]] [[fk_SEEDMail]] [[eStageStatus]] [[sTo]] [[tsSent]]" ) );

        /* If this is the first message of a mail batch, update the SEEDMail record status
         */
// don't think the message should have sending/staging state
//        if( $kfrStage->Value('M_eStatus') == 'READY' ) {
//            $oMessage->Store( ['eStatus'=>'SENDING'] );
//        }

        /* If there are no more READY emails for this mail doc, record that SENDING is finished.
         * Clear SEEDMail.sAddresses and the SEEDMail_Staged records because the relevant information has been logged and
         * these take a lot of space in the db.
         */
//        if( !$oMail->GetCountStaged() ) {
//            $oMail->Store( ['eStatus'=>'DONE'] );
//            //$this->oApp->kfdb->Execute( "DELETE FROM {$this->dbname}.SEEDMail_Staged WHERE fk_SEEDMail='$kMail'" );
//        }

        done:
        return( [$ok ? $kfrStage->Key() : 0, $sMsg] );
    }
}


include_once( SEEDLIB."mbr/MbrContacts.php" );
include_once( SEEDLIB."mbr/MbrEbulletin.php" );

class SEEDMailTestHistory
{
    private $oApp;
    private $oMbrContacts;
    private $oMbrEbulletin;

    private $raF = [ 'mybackup'=>[], 'delayed'=>[], 'discardedTooFast'=>[], 'discardedMaxFail'=>[], 'failed'=>[], 'unknown'=>[] ];    // results collected from files
    private $nFiles = 0;
    private $bFatalError = false;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oMbrContacts = new Mbr_Contacts( $oApp );
        $this->oMbrEbulletin = new MbrEbulletin( $oApp );
    }

    function TestMailHistory()
    {
        $s = "";
        $sShowFname = $sShowFileContents = "";

        $dirMail = (SEED_isLocal ? "/home/bob/" : "/home/seeds/")."mail/new";
        $dirArchive = (SEED_isLocal ? "/home/bob/" : "/home/seeds/")."mail/seed_mail_archive";

        if( !is_dir($dirMail) ) {
            $s = "<p class='alert alert-warning'>$dirMail does not exist</p>";  // not a failure, return bOk==true
            goto done;
        }

        // process delete requests
        if( ($fnameDel = SEEDInput_Str('d')) ) {
            unlink( $dirMail."/".$fnameDel );
        }
        if( ($e = SEEDInput_Str('de')) ) {
            list($d1,$d2,$sResult) = $this->oMbrEbulletin->RemoveSubscriber( $e );
            $this->oApp->oC->AddUserMsg( $sResult );
        }

        foreach( new DirectoryIterator($dirMail) as $f ) {
            // look at the files that start with 1*
            if( $f->isDot() || $f->isDir() || !SEEDCore_StartsWith($f->getFilename(),'1') ) continue;

            $sFileContents = file_get_contents($f->getPathname());
            $fname = $f->getFilename();

            if( !$this->testMyBackup($fname, $sFileContents) &&
                !$this->testDelayed($fname, $sFileContents) &&
                !$this->testDiscardedTooFast($fname, $sFileContents) &&  // Check for discards before fails because they have the same headers
                !$this->testDiscardedMaxFail($fname, $sFileContents) &&
                !$this->testFailed($fname, $sFileContents) )
            {
                // store this fname as 'unknown' because we don't know what it's for
                $this->raF['unknown'][] = $fname;
            }

            if( ($sShowFname = SEEDInput_Str('f')) == $fname ) {
                $sShowFileContents = $sFileContents;
            }

            ++$this->nFiles;
        }

        $s .= "<style>
               .SEEDMailTestHistory_ResultsTable { width:100%; font-family:monospace; max-height:30vh; overflow-y:scroll; }
               .SEEDMailTestHistory_FileView     { width:100%; font-family:monospace; max-height:30vh; overflow-y:scroll; }
               </style>";

        $tmplFile = "<td><a href='?f=[[vu]]'>[[v]]</a></td><td>&nbsp;</td><td><a href='?d=[[vu]]'>Delete</a></td>";
        $tmplFileAddress = "<td>[[v]]</td><td><a href='?f=[[ku]]'>[[k]]</a></td><td><a href='?d=[[ku]]'>Delete</a></td>";
        $raSections = [
            ['k'=>'mybackup',         'l'=>'mybackup emails',          't'=>$tmplFile],
            ['k'=>'delayed',          'l'=>'delayed emails',           't'=>$tmplFile],
            ['k'=>'discardedTooFast', 'l'=>'over send quota',          't'=>$tmplFileAddress],
            ['k'=>'discardedMaxFail', 'l'=>'discarded - max failures', 't'=>$tmplFileAddress],
            ['k'=>'failed',           'l'=>'failed',                   't'=>$tmplFileAddress],
            ['k'=>'unknown',          'l'=>'unknown',                  't'=>$tmplFile],
        ];
        $s .= "<div style='margin:20px'>"
             ."<hr/>"
             ."<div style='margin-bottom:30px'>{$this->nFiles} notifications</div>";
        foreach( $raSections as $ra ) {
            if( !($n = count($this->raF[$ra['k']])) ) continue;

            $sFailManage = "";
            if( $ra['k'] == 'failed' ) {
                // manage email addresses for failures
                $sFailManage .= "<table class='SEEDMailTestHistory_ResultsTable'>";
                foreach( $this->raF['failed'] as $file => $email ) {
                    $uEmail = urlencode($email);
                    $raEmailStatus = $this->getEmailStatus( $email );
                    $sStatus = ($raEmailStatus['bEbull'] ? "<span style='color:green'>Subscribed</span>" : "Not")
                              ." in ebulletin, "
                              .($raEmailStatus['bMbrExists'] ? ($raEmailStatus['bMbrEbull'] ? "<span style='color:green'>Subscribed</span>" : "Not subscribed")
                                                             : "Not")
                              ." in member list";

                    $linkRemove = ($raEmailStatus['bEbull'] || $raEmailStatus['bMbrEbull'])
                                    ? "<a href='?de=$uEmail'>Unsubscribe</a>"
                                    : "&nbsp";

                    $sFailManage .= "<tr><td>$email</td><td>$sStatus</td><td>$linkRemove</td></tr>";
                }
                $sFailManage .= "</table>";
            }

            $s .= "<div>$n {$ra['l']}:</div>"
                 ."<div class='well SEEDMailTestHistory_ResultsTable'>"
                     ."<table style='width:100%'>"
                         .SEEDCore_ArrayExpandSeries( $this->raF[$ra['k']], "<tr>{$ra['t']}</tr>" )
                     ."</table>"
                     .$sFailManage
                 ."</div>";
        }

        if( $sShowFileContents ) {
            $s .= "<div style='margin-top:30px'>$sShowFname</div>"
                 ."<div class='well SEEDMailTestHistory_FileView'>".nl2br(SEEDCore_HSC($sShowFileContents))."</div>";
        }

        done:
        return( [!$this->bFatalError,$s] );
    }

    private function testMyBackup( $fname, $sFileContents )
    {
        $bFound = false;

        // test if this is a notice of myBackup

        if( preg_match( "/\/home\/seeds\/_back1\/myBackup/", $sFileContents) ) {
            $this->raF['mybackup'][] = $fname;
            $bFound = true;
        }
        return( $bFound );
    }
    private function testDelayed( $fname, $sFileContents )
    {
        $bFound = false;

        // test if an email is delayed but will be retried (this notice can be ignored)

        if( preg_match( "/\nAction\: delayed\n/", $sFileContents) ) {
            $this->raF['delayed'][] = $fname;
            $bFound = true;
        }
        return( $bFound );
    }
    private function testDiscardedTooFast( $fname, $sFileContents )
    {
        $bFound = false;

        // test if an email was discarded because the sending server sent more than the hourly quota (fatal error; stop sending)

        if( preg_match( "/exceeded the max emails per hour .* discarded.\n/", $sFileContents) &&
            preg_match( "/\nX-Failed-Recipients: (.*)\n/", $sFileContents, $match) )
        {
            $this->raF['discardedTooFast'][$fname] = $match[1];
            $this->bFatalError = true;      // stop sending emails
            $bFound = true;
        }

        return( $bFound );
    }
    private function testDiscardedMaxFail( $fname, $sFileContents )
    {
        $bFound = false;
        $match = [];

        // test if an email was discarded because the sending server has too many fails (fatal error; stop sending)

        if( preg_match( "/exceeded the max defers and failures per hour .* discarded.\n/", $sFileContents) &&
            preg_match( "/\nX-Failed-Recipients: (.*)\n/", $sFileContents, $match) )
        {
            $this->raF['discardedMaxFail'][$fname] = $match[1];
            $this->bFatalError = true;      // stop sending emails
            $bFound = true;
        }

        return( $bFound );
    }
    private function testFailed( $fname, $sFileContents )
    {
        $bFound = false;
        $match = [];

        // test if an email failed at its destination server (e.g. invalid address)

        if( preg_match( "/\nAction: failed\n/", $sFileContents) &&
            preg_match( "/\nX-Failed-Recipients: (.*)\n/", $sFileContents, $match) )
        {
            $this->raF['failed'][$fname] = $match[1];
            $bFound = true;
        }
        else
        if( preg_match( "/could not deliver mail to (.*)\.  The account/", $sFileContents, $match ) ) {
            $this->raF['failed'][$fname] = $match[1];
            $bFound = true;
        }
        else
        if( preg_match( "/Your message to (.*) couldn't be delivered\./", $sFileContents, $match ) ) {
            $this->raF['failed'][$fname] = $match[1];
            $bFound = true;
        }
        else
        if( preg_match( "/\*\* Address not found \*\* Your message wasn't delivered to (.*)\n/", $sFileContents, $match ) ) {
            $this->raF['failed'][$fname] = $match[1];
            $bFound = true;
        }
        else
        if( preg_match( "/\nStatus: 5.0.0 \(permanent failure\)/", $sFileContents) &&
            preg_match( "/\nFinal-Recipient\: rfc822\;(.*)\n/", $sFileContents, $match) )
        {
            $this->raF['failed'][$fname] = $match[1];
            $bFound = true;
        }

        return( $bFound );
    }

    private function getEmailStatus( $email )
    {
        $raOut = [];

        $ra = $this->oMbrEbulletin->GetSubscriber( $email );
        $raOut['bEbull'] = @$ra['email'] <> '';

        $ra = $this->oMbrContacts->GetAllValues( $email );
        $raOut['bMbrExists'] = @$ra['email'] <> '';
        $raOut['bMbrEbull'] = $raOut['bMbrExists'] && !@$ra['bNoEBull'];

        return( $raOut );
    }
}