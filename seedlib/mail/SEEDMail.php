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

    function __construct( SEEDAppConsole $oApp, $keyOrName )
    {
        $this->oApp = $oApp;
        $this->oDB = new SEEDMailDB( $oApp );

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
            $s = self::ExpandMessage( $this->oApp, $s, ['raVars'=>$raVars] );
        }

        return( $s );
    }

    function AddRecipient( $e )     // e can be email or kMbr
    {
        if( $this->kfr ) {
            $this->Store(['sAddresses'=>$this->kfr->Value('sAddresses')."\n$e"]);
        }
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
                $oDoc = $oDocRepDB->GetDoc($docid); // new DocRepDoc2( $oDocRepDB, $docid );
                $sMsg = $oDoc->GetText('');
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
            if( !$e ) continue;

            $oMS = new SEEDMailStaged( $this );
            $oMS->Store( ['fk_SEEDMail'=>$this->kfr->Key(), 'eStageStatus'=>'READY', 'sTo'=>$e] );
        }
        $this->Store( ['eStatus'=>'READY','sAddresses'=>""] );

        done:
        return;
    }

    function GetCountStaged( $eStageStatus = 'READY' )
    /*************************************************
        Return the number of staged recipients for this message, optionally filtered by eStatusStaged
     */
    {
        $cond = $eStageStatus ? " AND eStageStatus='$eStageStatus'" : "";
        return( $this->oDB->GetCount( 'MS', "fk_SEEDMail='{$this->kfr->Key()}' $cond" ) );
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
include_once( SEEDLIB."mbr/MbrContacts.php" );
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

    function GetCountReadyToSend()
    {
        return( $this->oDB->GetCount('MS', "eStageStatus='READY'") );
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
        $oMail = new SEEDMail( $this->oApp, $kMail );


        /* sTo is either kMbr or email. Use Mbr_Contacts to try to get full contact info, otherwise just use email.
         */
        $oMbrContacts = new Mbr_Contacts( $this->oApp );
        $raMbr = $oMbrContacts->GetBasicValues( $kfrStage->Value('sTo') );

        $sTo = @$raMbr['email'] ?: $kfrStage->Value('sTo');
        $kMbrTo = intval(@$raMbr['_key']);
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
        $raVars['kMbrTo'] = $kMbrTo;
$raVars['lang'] = $this->oApp->lang;

        //$oDocRepWiki->AddVars( $raDRVars );
        //$oDocRepWiki->AddVar( 'kMbrTo', $kMbr );
        //$oDocRepWiki->AddVar( 'sEmailTo', $sEmailTo );
        //$oDocRepWiki->AddVar( 'sEmailSubject', $sEmailSubject );
        $sBody = SEEDMail::ExpandMessage( $this->oApp, $kfrStage->Value('M_sBody'), ['raVars'=>$raVars] );

// either here or in SEEDEmail put <html><body> </body></html> around the message if it doesn't already have that
        $ok = SEEDEmailSend( $sFrom, $sTo, $sSubject, "", $sBody, ['bcc'=>['bob@seeds.ca']] );

        $kfrStage->SetValue( "iResult", $ok );    // we only get a boolean from mail()
        $kfrStage->SetValue( "eStageStatus", $ok ? "SENT" : "FAILED");
        $kfrStage->PutDBRow();
        $this->oApp->kfdb->Execute( "UPDATE {$this->dbname}.SEEDMail_Staged SET tsSent=NOW() WHERE _key='{$kfrStage->Key()}'" );
        $this->oApp->Log( "mailsend.log", $kfrStage->Expand( "[[_key]] [[fk_SEEDMail]] [[eStageStatus]] [[sTo]] [[tsSent]]" ) );

        /* If this is the first message of a mail batch, update the SEEDMail record status
         */
        if( $kfrStage->Value('M_eStatus') == 'READY' ) {
            $oMail->Store( ['eStatus'=>'SENDING'] );
        }

        /* If there are no more READY emails for this mail doc, record that SENDING is finished.
         * Clear SEEDMail.sAddresses and the SEEDMail_Staged records because the relevant information has been logged and
         * these take a lot of space in the db.
         */
        if( !$oMail->GetCountStaged() ) {
            $oMail->Store( ['eStatus'=>'DONE'] );
            //$this->oApp->kfdb->Execute( "DELETE FROM {$this->dbname}.SEEDMail_Staged WHERE fk_SEEDMail='$kMail'" );
        }

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
               .SEEDMailTestHistory_ResultsTable { width:100%; font-family:monospace; }
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
                 ."<div class='well'>"
                     ."<table class='SEEDMailTestHistory_ResultsTable'>"
                         .SEEDCore_ArrayExpandSeries( $this->raF[$ra['k']], "<tr>{$ra['t']}</tr>" )
                     ."</table>"
                     .$sFailManage
                 ."</div>";
        }

        if( $sShowFileContents ) {
            $s .= "<div style='margin-top:30px'>$sShowFname</div>"
                 ."<div class='well' style='font-family:monospace'>".nl2br(SEEDCore_HSC($sShowFileContents))."</div>";
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