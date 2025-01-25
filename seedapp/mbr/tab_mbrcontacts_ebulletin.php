<?php

/* tab_mbrcontacts_ebulletin
 *
 * Copyright 2023-2024 Seeds of Diversity Canada
 *
 * TabSet tab for managing ebulletin subscribers
 */

include_once(SEEDLIB."mbr/MbrEbulletin.php");

class MbrContactsTabEBulletin
{
    private $oApp;
    private $oSVA;
    private $oContacts;

    function __construct( SEEDAppConsole $oApp, Mbr_Contacts $oContacts, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;
        $this->oContacts = $oContacts;
        $this->oOpPicker = new Console02UI_OperationPicker('currOp', $oSVA,
                                ['Add / Remove ebulletin addresses' => '',
                                 'PostMark suppressions'            => 'postmark'] );
    }

    function Init() {}

    function ControlDraw()  { return( $this->oOpPicker->DrawDropdown() ); }

    function ContentDraw()
    {
        $s = "";

        switch( $this->oOpPicker->Value() ) {
            case '':
            default:
                $s = "<h3>Add / Remove ebulletin addresses</h3>";
                break;

            case 'postmark':
                $s = (new MbrContactsTabEBulletin_Postmark($this->oApp, $this->oSVA))->Main();
                break;
        }

        return($s);
    }
}

class MbrContactsTabEBulletin_Postmark
{
    private $oApp;
    private $oSVA;
    private $oEbull;
    private $oMbr;

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;
        $this->oEbull = new MbrEbulletin($oApp);
        $this->oMbr = new Mbr_Contacts($oApp);
    }

    function Main()
    {
        $s = "";

        $oForm = new SEEDCoreForm('A');
        $oForm->Update();

        $nNotFound = 0;
        $raEmails = [];

        switch($oForm->Value('cmd')) {
            case 'upload':
                // upload json file and get emails
                if( ($fname = $_FILES['upfile']['tmp_name']) &&
                    ($sFile = file_get_contents($fname)) &&
                    ($ra = json_decode($sFile)) )
                {
                    foreach($ra as $o) {
                        $email = $o->email_address;
                        if( $this->oEbull->IsSubscriber($email, MbrEbulletin::SUBSCRIBER_ANYSOURCE) ) {
                            $raEmails[] = $email;
                        } else {
                            ++$nNotFound;
                        }
                    }
                } else {
                    $this->oApp->oC->AddErrMsg( "Could not upload file" );
                    goto show_form;
                }

                $s .= "<h4>Emails to be unsubscribed</h4>
                       <p>File contained ".(count($raEmails)+$nNotFound)." emails.</p>
                       <p>$nNotFound already unsubscribed.</p>
                       <p>".count($raEmails)." queued to be unsubscribed.</p>
                       <div style='border:1px solid #aaa;height:25em;overflow-y:scroll'>"
                      .SEEDCore_ArrayExpandSeries($raEmails, "[[]]<br/>")
                      ."</div>";

                $this->oSVA->VarSet( 'uploadedPostmarkEmails', $raEmails );

                // draw Commit button
                $s .= "<br/><br/>
                       <form action='{$this->oApp->PathToSelf()}'>
                       <input type='submit' value='Commit Changes'/>
                       {$oForm->Hidden('cmd', ['value'=>'uploadCommit'])}
                       </form>";
                    break;

            case 'uploadCommit':
                $raEmails = $this->oSVA->VarGet('uploadedPostmarkEmails') ?? [];

                $s .= "<h3>Unsubscribed ".count($raEmails)." emails</h3>";
                foreach($raEmails as $e) {
                    [$eRetBull,$eRetMbr,$sResult] = $this->oEbull->RemoveSubscriber($e);
                    $s .= $sResult;
                }
                break;
        }

        $s .= "<hr/>";

        show_form:

        $s .= "<h4>Upload PostMark suppressions file (json)</h4>
               <form action='{$this->oApp->PathToSelf()}' method='post' enctype='multipart/form-data'>
                   <input type='hidden' name='MAX_FILE_SIZE' value='10000000' />
                   {$oForm->Hidden('cmd', ['value'=>'upload'])}
                   <table style='margin-left:20px' border='0'>
                   <tr><td><input style='display:inline' type='file' name='upfile'/></td>
                       <td><input style='display:inline' type='submit' name='action' value='Upload' style='float:right'/></td>
                   </tr></table>
               </form>";

        return($s);
    }
}

