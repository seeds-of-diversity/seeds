<?php

/* An application to easily send email to people we know.
 *
 * Copyright (c) 2014-2018   Seeds of Diversity Canada
 *
 */

include_once( SEEDCORE."SEEDCoreForm.php" );
include_once( SEEDCORE."SEEDEmail.php" );

class EmailMeApp
{
    private $oApp;
    private $raConfig;
    private $oForm;

    function __construct( SEEDAppConsole $oApp, $raConfig )
    {
        $this->oApp = $oApp;
        $this->raConfig = $raConfig;
        $this->oForm = new SEEDCoreForm( 'Plain' );
        $this->oForm->Update();

        // if the 'to' field is a number, look up the corresponding email.
        // if the 'to' field is an email, put it in the form
        // otherwise just work with whatever is already in the form
        if( ctype_digit($to = $this->oForm->Value('to')) && ($to = intval($to)) ) {
            $this->oForm->SetValue( 'toKey', $to );
            $this->oForm->SetValue( 'to', $this->LookupEmailFromInt( $to ) );
        } else {
// Don't overwrite toKey when the Update button is clicked. But don't keep it if the email address is manually changed.
// That's why neither to be allowed to be changed.
            //$this->oForm->SetValue( 'toKey', 0 );
        }
    }

    function App()
    {
        $s = "";

        if( !($this->oApp->sess->CanWrite( 'EmailMeApp' ) || $this->oApp->sess->CanRead( 'MBR' )) ) {
            echo "You don't have permission to use this app";
            goto done;
        }

        if( SEEDInput_Str('cmd') == 'Send' ) {
            if( $this->Send() ) {
                // Record the email in mbox format. Emails are stored in files named by the 'to' key
                $from_munge_body = "";
                // mbox munges lines starting with From
                $ra = explode( "\n", $this->oForm->Value('mailbody') );
                foreach( $ra as $line ) {
                    if( substr( $line, 0, 4 ) == 'From' ) {
                        $line = '>'.$line;
                    }
                    $from_munge_body .= $line."\n";
                }

                $sLog = "From ".$this->getFrom()." ".date('D M d h:i:s Y')."\n"
                       ."To: ".$this->oForm->Value('to')."\n"
                       ."Subject: ".$this->oForm->Value('subject')."\n\n"
                       .$from_munge_body."\n\n";

                $fname = $this->raConfig['logdir'].intval($this->oForm->Value('toKey')).".mbox";
                if( $fp = fopen( $fname, "a" ) ) {
                    fwrite( $fp, $sLog );
                    fclose( $fp );
                }
            }
        }

        $s .= $this->DrawMailForm();

        done:
        return( $s );
    }

    function Send()
    {
        $s = "";

        $ok = false;

        if( !$this->oForm->Value('to') ) {
            $this->oApp->oC->AddErrMsg( "Specify the 'to' address<br/>" );
            goto done;
        }

        if( !$this->oForm->Value('subject') ) {
            $this->oApp->oC->AddErrMsg( "Specify the subject" );
            goto done;
        }

        $ok = SEEDEmailSend( $this->getFrom(),
                             $this->oForm->Value('to'),
                             $this->oForm->Value('subject'),
                             $this->oForm->Value('mailbody'),
                             $bodyHTML = "", ['bcc'=> array('bob@seeds.ca')] );

        $this->oApp->oC->AddUserMsg( "Mail sent" );

        done:
        return( $ok );
    }

    function DrawMailForm()
    {
        $s = "<style>.mailformline { margin:5px 0px } </style>";

        $s .= "<div style='width:800px'>"
             ."<form method='post'>"
             ."<div class='mailformline'>From: ".htmlentities($this->getFrom())."</div>"     // show <{email}> on-screen instead of turning it into an html tag
             ."<div class='mailformline'>To:".$this->oForm->Text( 'to', "", ['width'=>'50%'] )." ".$this->oForm->Text( 'toKey', "", ['readonly'=>true])."</div>"
             ."<div class='mailformline'>Subject:".$this->oForm->Text( 'subject', "", ['width'=>'50%'] )."</div>"
             ."<div class='mailformline'>".$this->oForm->TextArea( 'mailbody', ['width'=>'100%'] )."</div>"
             ."<div style='float:left'><input type='submit' name='cmd' value='Update'/></div>"
             ."<div style='float:right'><input type='submit' name='cmd' value='Send'/></div>"
             ."</form>"
             ."</div>";

        return( $s );
    }

    protected function LookupEmailFromInt( int $k )
    /**********************************************
        Look up a member's email from their key. Override to use a different lookup (like if you're not a Seeds of Diversity application).
     */
    {
        $ra = $this->oApp->kfdb->QueryRA( "SELECT * FROM seeds2.mbr_contacts WHERE _key='$k'" );
        $email = $ra['email'] ? SEEDCore_ArrayExpand( $ra, "[[firstname]] [[lastname]] <[[email]]>" ) : "";
        return( $email );
    }


    private function getFrom()
    {
        return( $this->oApp->sess->GetName()." <".$this->oApp->sess->GetEmail().">" );
    }
}
