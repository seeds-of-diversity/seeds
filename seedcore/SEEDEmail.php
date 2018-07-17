<?php

/* SEEDEmail
 *
 * Copyright (c) 2018 Seeds of Diversity Canada
 *
 * Send email
 */

function SEEDEmailSend( $from, $to, $subject, $bodyText, $bodyHTML = "", $raParms = array() )
/********************************************************************************************
    $from = email  OR  array( email, screen_name )   e.g. array( "webmaster@site.ca", "Webmaster at Site.ca" )
    $to   = string of one or more emails comma separated
    $raParms['cc'] = array( cc1, cc2, ...)
    $raParms['bcc'] = array( bcc1, bcc2, ...)
 */
{
    // If this is localhost, just draw the mail on the screen because that's easier in development.
    $bPretendToSend = ($_SERVER["SERVER_NAME"] == "localhost");
    // Or uncomment this to send on dev machines (you have to configure your php.ini with an smtp)
    // static $bPretendToSend = false;


    if( is_string($from) ) {
        $sFromEmail = $from;
        $sFromName = "";
    } else {
        $sFromEmail = $from[0];
        $sFromName  = @$from[1];
    }

    if( $bPretendToSend ) {
        /* On development machines just draw the mail on the screen, since we're probably not set up with smtp anyway.
         */
        echo "<div style='margin:10px;padding:10px;background-color:#ffe;border:1px solid #888;border-radius:5px'>"
            ."From: $sFromName &lt;$sFromEmail&gt;<br/>"
            ."To: $to<br/>"
            .(@$raParms['cc'] ? ("CC: ".implode( ", ", $raParms['cc'] )."<br/>") : "")
            .(@$raParms['bcc'] ? ("BCC: ".implode( ", ", $raParms['bcc'] )."<br/>") : "")
            ."Subject: $subject<br/>"
            ."-----<br/>"
            .nl2br($bodyHTML)
            ."</div>";

        $ok = true;
    } else {
        /* On production machines use the local SMTP
         */
        include_once( SEEDCORE."os/cPHPezMail.php" );

        $oMail = new cPHPezMail();
        $oMail->SetFrom( $sFromEmail, $sFromName );
        $oMail->AddHeader( 'Reply-to', $sFromEmail );
        $oMail->AddTo( $to );
        $oMail->SetSubject( $subject );
        if( count(@$raParms['cc']) ) {
            foreach( $raParms['cc'] as $a ) {
                $oMail->AddCc( $a );
            }
        }
        if( count(@$raParms['bcc']) ) {
            foreach( $raParms['bcc'] as $a ) {
                $oMail->AddBcc( $a );
            }
        }

        if( empty($bodyText) ) $bodyText = strip_tags( $bodyHTML );
        $oMail->SetBodyText( $bodyText );
        if( !empty( $bodyHTML ) ) {
            $oMail->SetBodyHTML( $bodyHTML );
        }

        $ok = $oMail->Send();
    }
    return( $ok );
}

?>