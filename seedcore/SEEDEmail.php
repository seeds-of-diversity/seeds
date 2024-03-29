<?php

/* SEEDEmail
 *
 * Copyright (c) 2018-2023 Seeds of Diversity Canada
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
    $bPretendToSend = SEED_isLocal;
    // Or uncomment this to send on dev machines (you have to configure your php.ini with an smtp)
    //$bPretendToSend = false;


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
            .nl2br($bodyText)
            ."</div>";

        $ok = true;
    } else {
        /* On production machines use the local SMTP
         */
        include_once( SEEDCORE."os/cPHPezMail.php" );

        $oMail = new cPHPezMail();
        $oMail->SetFrom( $sFromEmail, $sFromName );
        $oMail->AddHeader( 'Reply-to', @$raParms['reply-to'] ?: $sFromEmail );
        $oMail->AddTo( $to );
        $oMail->SetSubject( $subject );
        if( @$raParms['cc'] ) {
            foreach( $raParms['cc'] as $a ) {
                $oMail->AddCc( $a );
            }
        }
        if( @$raParms['bcc'] ) {
            foreach( $raParms['bcc'] as $a ) {
                $oMail->AddBcc( $a );
            }
        }

        if( @$raParms['attachments'] ) {
            foreach( $raParms['attachments'] as $attachmentFilename ) {
                $oMail->AddAttachLocalFile( $attachmentFilename, '' );  // expecting ezmail to figure out the mimetype
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

function SEEDEmailSend_Postmark( $from, $to, $subject, $bodyText, $bodyHTML = "", $raParms = [] )
/************************************************************************************************
    $from             = email  OR  [email, screen_name]   e.g. ["webmaster@site.ca", "Webmaster at Site.ca"]
    $to               = string of one or more emails comma separated
    $raParms:
        cc            = [cc1, cc2, ...]
        bcc           = [bcc1, bcc2, ...]
        MessageStream = Postmark message stream (default broadcast)
        bInputNotUTF8 = subject and body must be converted to utf8 (must be set by caller so default assumes utf8 content)
 */
{
    $errcode = -1; $sMsg = "POSTMARK_API_TOKEN required";

    if( !defined("POSTMARK_API_TOKEN") )  goto done;

    $sMessageStream = SEEDCore_ArraySmartVal($raParms,'MessageStream',['broadcast','outbound']);
    if( @$raParms['bInputNotUTF8'] ) {
        $subject  = SEEDCore_utf8_encode($subject);
        $bodyText = SEEDCore_utf8_encode($bodyText);
        $bodyHTML = SEEDCore_utf8_encode($bodyHTML);
    }

    if( is_string($from) ) {
        $sFromEmail = $from;
        $sFromName = "";
    } else {
        $sFromEmail = $from[0];
        $sFromName  = @$from[1] ?? "";
    }

    $oPM = new \Postmark\PostmarkClient(POSTMARK_API_TOKEN);
    $message = [
        'From'          => $from,
        'To'            => $to,
        'Subject'       => $subject,
        'TextBody'      => $bodyText,
        'HtmlBody'      => "<html><body>$bodyHTML</body></html>",
        //'Tag'           => "New Year's Email Campaign",
        'MessageStream' => $sMessageStream
    ];
    if( isset($raParms['cc']) )   $message['Cc']  = implode(",",$raParms['cc']);
    if( isset($raParms['bcc']) )  $message['Bcc'] = implode(",",$raParms['bcc']);

/*
    $body['From'] = $from;
		$body['To'] = $to;
		$body['Cc'] = $cc;
		$body['Bcc'] = $bcc;
		$body['Tag'] = $tag;
		$body['ReplyTo'] = $replyTo;
		$body['Headers'] = $this->fixHeaders($headers);
		$body['TrackOpens'] = $trackOpens;
		$body['Attachments'] = $attachments;
		$body['TemplateModel'] = $templateModel;
		$body['InlineCss'] = $inlineCss;
		$body['Metadata'] = $metadata;
		$body['MessageStream'] = $messageStream;
*/

    // Email batches take an array of messages, and return an array of results.
    // This function also has a nice arg format, but if there's a function that takes just one message array we could use that too.
    $oResult = $oPM->sendEmailBatch([$message]);
    $errcode = $oResult[0]['ErrorCode'];
    $sMsg    = $oResult[0]['Message'];

/*
    if( @$raParms['attachments'] ) {
        foreach( $raParms['attachments'] as $attachmentFilename ) {
            $oMail->AddAttachLocalFile( $attachmentFilename, '' );  // expecting ezmail to figure out the mimetype
        }
    }
    if( empty($bodyText) ) $bodyText = strip_tags( $bodyHTML );
    $oMail->SetBodyText( $bodyText );
    if( !empty( $bodyHTML ) ) {
        $oMail->SetBodyHTML( $bodyHTML );
    }

    $ok = $oMail->Send();
*/

    done:
    return( [$errcode==0, $errcode, $sMsg] );
}
