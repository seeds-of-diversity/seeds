<?php

/* mailsend.php
 *
 * Copyright 2010-2021 Seeds of Diversity Canada
 *
 * Send email staged by mailsetup.
 *
 * Usage: 1) http://.../mailsend.php?nQuantity=5&nDelay20
 *        2) php -f mailsend.php nQuantity5 nDelay20
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

/* Parms are obtained from either http or cli
 *  $nQuantity = number of emails to send
 *  $nDelay    = seconds to delay between each send, and seconds to delay before http redirect
 */
if( SEED_isCLI ) {
    if( SEEDCore_StartsWith( @$argv[1], 'nQuantity' ) ) { $nQuantity = intval( substr($argv[1],9) ); }
    if( SEEDCore_StartsWith( @$argv[2], 'nQuantity' ) ) { $nQuantity = intval( substr($argv[2],9) ); }
    if( SEEDCore_StartsWith( @$argv[1], 'nDelay' ) )    { $nDelay = intval( substr($argv[1],6) ); }
    if( SEEDCore_StartsWith( @$argv[2], 'nDelay' ) )    { $nDelay = intval( substr($argv[2],6) ); }
} else {
    $nQuantity = SEEDInput_Int('nQuantity');
    $nDelay = SEEDInput_Int('nDelay');
}
if( !$nQuantity ) $nQuantity = 1;


$oMailSend = new SEEDMailSend( $oApp );
while( $nQuantity-- ) {
    list($ok, $s1) = $oMailSend->SendOne();
    $s .= $s1;

    if( $nDelay ) sleep($nDelay);
}


echo $s;
