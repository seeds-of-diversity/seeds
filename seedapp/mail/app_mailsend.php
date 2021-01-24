<?php

/* mailsend.php
 *
 * Copyright 2010-2021 Seeds of Diversity Canada
 *
 * Send email staged by mailsetup.
 *
 * Usage: 1) http://.../mailsend.php?nQuantity=5&nDelay20
 *        2) php -f mailsend.php -nQuantity5 -nDelay20

 *  nQuantity = number of emails to send
 *  nDelay    = seconds to delay between each send, and seconds to delay before http redirect
 */

if( !defined( "SEEDROOT" ) ) {
    if( php_sapi_name() == 'cli' ) {
        // script has been run from the command line
        define( "SEEDROOT", pathinfo($_SERVER['PWD'].'/'.$_SERVER['SCRIPT_NAME'],PATHINFO_DIRNAME)."/../../" );
    } else {
        define( "SEEDROOT", "../../" );
    }

    define( "SEED_APP_BOOT_REQUIRED", true );
    include_once( SEEDROOT."seedConfig.php" );
}

include_once( SEEDLIB."mail/SEEDMail.php" );


$oApp = SEEDConfig_NewAppConsole_LoginNotRequired( ['db'=>'seeds2'] );   // anonymous access

$s = "";

if( SEED_isCLI ) {
    // parms from command line e.g.  -nQuantity5 -nDelay20
    $ra = getopts( "nQuantity:nDelay" );
    $nQuantity = intval($ra['nQuantity']);
    $nDelay    = intval($ra['nDelay']);
} else {
    // parms from http e.g. ?nQuantity=5&nDelay=20
    $nQuantity = SEEDInput_Int('nQuantity');
    $nDelay = SEEDInput_Int('nDelay');
}
if( !$nQuantity ) $nQuantity = 1;


$oMailSend = new SEEDMailSend( $oApp );
$nToSend = $oMailSend->GetCountReadyToSend();


function testMailHistory( SEEDAppConsole $oApp )  { return( [true,""] ); }


$sBody = "<h2>Seeds of Diversity Bulk Mailer</h2>"
        ."<p>There are $nToSend emails ready to send at ".date('Y-m-d H:i:s').".</p>";

list($bTestOk,$sTest) = testMailHistory( $oApp );
$sBody .= $sTest;

$bSendMail = ($bTestOk && $nToSend);

if( $bSendMail ) {
    $sBody .= "<p>Sending one email every 20 seconds.</p>";
    $sBody .= "<br/><br/>";

    while( $bTestOk && $nQuantity-- ) {
        ob_start();     // on dev installations catch the pretend-to-send output so it doesn't get sent before headers
        list($ok, $s1) = $oMailSend->SendOne();
        $oApp->oC->AddUserMsg( ob_get_contents() );
        ob_end_clean();

        $sBody .= $s1;

        if( $nQuantity && $nDelay ) sleep($nDelay);
    }
}

$sBody = $oApp->oC->DrawConsole($sBody);

echo Console02Static::HTMLPage( $sBody,
                                //($bSendMail ? "<meta http-equiv='refresh' CONTENT='20; URL=https://seeds.ca/office/mbr/mbr_mailsend.php'>" : ""),
                                ($bSendMail ? "<meta http-equiv='refresh' content='20'>" : ""),
                                'EN', [] );
