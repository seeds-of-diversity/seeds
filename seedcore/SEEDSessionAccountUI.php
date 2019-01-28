<?php

/* SEEDSessionAccountUI
 *
 * Copyright 2015-2019 Seeds of Diversity Canada
 *
 * Extensible and templatable UI for user accounts and login
 */

include_once( "SEEDSessionAccount.php" );

class SEEDSessionAccountUI
{
    private $oApp;
    private $bLoginRequired = true;

    function __construct( SEEDAppSessionAccount $oApp, $raConfig = array() )
    {
        $this->oApp = $oApp;    // Already established the user/login state

        if( @$raConfig['bLoginNotRequired'] )  $this->bLoginRequired = false;
    }

    function DoUI()
    {
        if( !$this->oApp->sess->IsLogin() && $this->bLoginRequired ) {
            echo "You have to login";
            exit;
        }
    }
}
