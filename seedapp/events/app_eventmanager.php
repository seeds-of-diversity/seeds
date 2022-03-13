<?php

/* app_eventmanager.php
 *
 * Copyright 2010-2022 Seeds of Diversity Canada
 *
 * Manage event lists
 */

if( !defined( "SEEDROOT" ) ) {
    if( php_sapi_name() == 'cli' ) {    // available as SEED_isCLI after seedConfig.php
        // script has been run from the command line
        define( "SEEDROOT", pathinfo($_SERVER['PWD'].'/'.$_SERVER['SCRIPT_NAME'],PATHINFO_DIRNAME)."/../../" );
    } else {
        define( "SEEDROOT", "../../" );
    }

    define( "SEED_APP_BOOT_REQUIRED", true );
    include_once( SEEDROOT."seedConfig.php" );
}


include("../../seedlib/google/GoogleSheets.php" );

$oApp = SEEDConfig_NewAppConsole_LoginNotRequired( ['db'=>'seeds2'] ); //, 'sessPermsRequired'=>["W events"]] );


$oES = new EventsSheet($oApp);

if( $oES->IsLoaded() ) {

    /* Try to match up the columns
     */





    //$sheets->WriteValues( 'Sheet1!A23:B23', [['1','2']] );

    //var_dump($oES->values());
}

$s = $oES->DrawForm()
    .$oES->DrawTable();

echo $s;


class EventsSheet
/****************
    Connect events with a Google sheet
 */
{
    private $oApp;

    private $oSheets = null;
    private $p_idSheet;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;

        $this->p_idSheet = SEEDInput_Str('idSheet');

        if( $this->p_idSheet ) {
            $this->oSheets = new SEEDGoogleSheets(
                                    ['appName' => 'My PHP App',
                                     'authConfigFname' => SEEDCONFIG_DIR."/sod-public-outreach-info-e36071bac3b1.json",
                                     'idSpreadsheet' => $this->p_idSheet,
                                     //'idSheet' => "1npC38lFv84iG1YhfS9oBpiLXYjbrjiXqHQTaibonl5I"
                                     //"1NkwvvyO71XcRlsGK9vEi2T2uB_b0fJ5-LdwFcCAeDQk"
                                     //"1l1WuajTKtVqLTTR7vWCoDan-vD9WUS9li7ErA5BXThI"
                                    ] );
        }
    }

    function IsLoaded()  { return( $this->oSheets ); }

    function values()
    {
        $range = 'A1:Z1';
        list($values,$range) = $this->oSheets->GetValues( $range );
        return( $values );
    }

    function DrawForm()
    {
        $s = "<form method='post'>
              <div><input type='text' name='idSheet' value='{$this->p_idSheet}' size='60'/>&nbsp;
                   <input type='submit'/>
              </div>
              </form>";

        return( $s );
    }

    function DrawTable()
    {
        $s = "";

        return( $s );
    }
}
