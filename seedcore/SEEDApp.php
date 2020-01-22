<?php

/* SEEDApp
 *
 * Copyright (c) 2017-2019 Seeds of Diversity Canada
 *
 * Common classes and functions useful across Seed Apps
 */

include_once( "SEEDCore.php" );
include_once( "SEEDSessionAccount.php" );
include_once( "SEEDSessionAccountUI.php" );
include_once( SEEDROOT."Keyframe/KeyframeDB.php" );
include_once( SEEDCORE."console/console02.php" );


class SEEDAppBase
/****************
    Properties that every application should know about
 */
{
    public $lang = 'EN';
    public $logdir = '';    // directory where this application writes log files

    function __construct( $raConfig )
    {
        if( isset($raConfig['lang']) )          $this->lang = $raConfig['lang'];
        if( isset($raConfig['logdir']) )        $this->logdir = $raConfig['logdir'];
    }

    function Log( $file, $s )
    {
        if( $this->logdir && ($fp = fopen( $this->logdir.$file, "a" )) ) {
            fwrite( $fp, sprintf( "-- %d %s %s\n", time(), date("Y-m-d H:i:s"), $s ) );
            fclose( $fp );
        }
    }
}

class SEEDAppDB extends SEEDAppBase
/**************
    Create and hold a KeyframeDatabase
 */
{
//  public $lang is inherited
//  public $logdir is inherited
    public $kfdb;

    function __construct( $raConfig )
    /********************************
        raParms: kfdbHost, kfdbUserid, kfdbPassword, and kfdbDatabase are required
     */
    {
        parent::__construct( $raConfig );

        if( !($this->kfdb = new KeyframeDatabase( $raConfig['kfdbUserid'], $raConfig['kfdbPassword'], @$raConfig['kfdbHost'] )) ) {    // kfdbHost is optional
            die( "Cannot connect to database" );
        }

        if( !$this->kfdb->Connect( $raConfig['kfdbDatabase'] ) ) {
            die( $this->kfdb->GetErrMsg() );
        }
    }
}

class SEEDAppSession extends SEEDAppDB
/*******************
    Create and hold a KeyframeDB and a SEEDSession
 */
{
//  public $lang is inherited
//  public $logdir is inherited
//  public $kfdb is inherited
    public $sess;

    function __construct( $raParms )
    {
        parent::__construct( $raParms );
        $this->sess = new SEEDSession();
    }
}

class SEEDAppSessionAccount extends SEEDAppSession
{
//  public $lang is inherited
//  public $logdir is inherited
//  public $kfdb is inherited
//  public $sess is inherited

    function __construct( $raConfig )
    {
        // This is structured as a SEEDAppSession so client code (like Console) can use it as that base class.
        // However since $sess is itself subclassed, it is built as base SEEDSession then replaced by SEEDSessionAccount.
        parent::__construct( $raConfig );

        /* SEEDSessionAccount config is in a sub-array of raConfig.
         *
         * raConfig['oSessUI']              = SEEDSessionAccountUI object to handle the UI
         * raConfig['sessConfig']['logfile'] = the logfile for SEEDSession table changes
         * raConfig['sessConfig']['logdir']  = the logdir for SEEDSession table changes - use raConfig['logdir'] if not defined
         */
        // Feed it the logdir if that's defined at the top level of the array.
        $raSessConfig = @$raConfig['sessConfig'] ?: array();
        if( !isset($raSessConfig['logfile']) && !isset($raSessConfig['logdir']) && isset($raConfig['logdir']) ) {
            $raSessConfig['logdir'] = $raConfig['logdir'];
        }
        $this->sess = new SEEDSessionAccount( $this->kfdb, $raConfig['sessPermsRequired'], $raSessConfig );

        // Handle the session UI (e.g. draw login form if !IsLogin, logout, send password)
        if( !($oUI = @$raConfig['oSessUI']) ) {
            $oUI = new SEEDSessionAccountUI( $this, @$raConfig['sessUIConfig'] ?: [] );
        }
        $oUI->DoUI();   // if this outputs anything to the browser, it must exit and never return to here
    }
}

class SEEDAppConsole extends SEEDAppSessionAccount
{
//  public $lang is inherited
//  public $logdir is inherited
//  public $kfdb is inherited
//  public $sess is inherited
    public $oC;
    private $fnPathToSelf = null;
    private $urlQ = '';

    function __construct( $raConfig )
    {
        parent::__construct( $raConfig );
        if( isset($raConfig['fnPathToSelf']) )  $this->fnPathToSelf = $raConfig['fnPathToSelf'];
        $this->urlQ = @$raConfig['urlQ'];

        $this->oC = new Console02( $this, @$raConfig['consoleConfig'] ?: array() ); // Console02 and SEEDAppConsole are circularly referenced
    }

    function PathToSelf()
    /********************
        Return the path that will make a link or form go to the current page.

        Note <form action=""> is not desirable because it defaults to the current browser address including any GET parms that are currently there
     */
    {
        if( $this->fnPathToSelf ) {
            $s = call_user_func( $this->fnPathToSelf );
        } else {
            // PHP_SELF is unsafe because page requests can look like seeds.ca/foo/index.php/"><script>alert(1);</script><span class="
            // Use htmlspecialchars to make injected JS non-parseable
            $s = SEEDCore_HSC($_SERVER['PHP_SELF']);
        }
        return( $s );
    }

    function UrlQ()  { return( $this->urlQ ); }
}

class SEEDApp_Worker
{
    public $kfdb;
    public $sess;
    public $lang;

    function __construct( KeyframeDatabase $kfdb, SEEDSessionAccount $sess, $lang )
    {
        $this->kfdb = $kfdb;
        $this->sess = $sess;
        $this->lang = $lang;
    }
}

class SEEDApp_WorkerC extends SEEDApp_Worker
// Since this is a SEEDApp_Worker it can be passed to any function that uses just a SEEDApp_Worker.
// However, how does the console know about the oW? It would be better for this to be the console instead of just owning a console.

// The right thing to do is to split Console01 into a) SEEDApp_WorkerC which knows about error messages, SVAs, current tabs and other "control" elements that are separate
// from presentation; b) Console02 which knows how to draw tabsets, assemble HTML5 pages, etc.
// Then SEEDApp_WorkerC can be passed around to logic code that wants to use SVAs and set error messages.
// If you want to pass SEEDApp_WorkerC to code that uses SEEDApp_Worker, it's easy.
// Console02 owns a SEEDApp_WorkerC
// Other worker classes could derive from SEEDApp_WorkerC, and Console02 could own those instead and still be happy.
{
    public $oC;

    function __construct( /*Console01*/ $oC, KeyframeDatabase $kfdb, SEEDSessionAccount $sess, $lang )
    {
        parent::__construct( $kfdb, $sess, $lang );
        $this->oC = $oC;
    }
}



/* Base classes to support the Q paradigm for ajax-compatible methods
 */

class SEEDQ
{
    public $oApp;
    public $raConfig;
    public $bUTF8 = true;

    /* By convention, configuration parameters start with 'config_' and are given to the constructor. (These include config for derived Q handlers).
     * All other command parameters are given to Cmd().
     * This allows an ajax http parameter array to differentiate config from command parms, and pass the same array to both.
     */
    function __construct( SEEDAppDB $oApp, $raConfig = array() )     // you can use any SEEDApp* object as the first argument
    {
        $this->oApp = $oApp;
        $this->raConfig = $raConfig;
        if( isset($raConfig['config_bUTF8']) ) $this->bUTF8 = intval($raConfig['config_bUTF8']);
    }

    function Cmd( $cmd, $parms )
    /* Derived classes implement their own cmd processors, which can be chained via bHandled
     */
    {
        $rQ = $this->GetEmptyRQ();

        if( $cmd == 'test' ) {
            $rQ['bHandled'] = true;
            $rQ['bOk'] = true;
            $rQ['sOut'] = "Test is successful";
            $rQ['raOut'] = array( array( 'first name' => "Fred", 'last name' => "Flintstone" ),
                                  array( 'first name' => "Barney", 'last name' => "Rubble" ) );
            $rQ['raMeta']['title'] = "Test";
            $rQ['raMeta']['name'] = "qtest";
        }

        return( $rQ );
    }

    function QCharset( $s )
    /**********************
        Use this on fields that are cp1252: the output will be the charset defined by $this->bUTF8
     */
    {
        return( $this->bUTF8 ? utf8_encode( $s ) : $s );
    }

    function GetEmptyRQ()
    /********************
     */
    {
        return( array( 'bHandled'=>false, 'bOk'=>false, 'sOut'=>"", 'sErr'=>"", 'sLog'=>"", 'raOut'=>array(), 'raMeta'=>array() ) );
    }
}


class SEEDQCursor
{
    public $kfrc;
    private $fnGetNextRow;    // function to translate kfrc->values to the GetNextRow values
    private $raParms;

    function __construct( KeyframeRecordCursor $kfrc, $fnGetNextRow, $raParms )
    {
        $this->kfrc = $kfrc;
        $this->fnGetNextRow = $fnGetNextRow;
        $this->raParms = $raParms;
    }

    function GetNextRow()
    {
        $raOut = null;
        if( $this->kfrc->CursorFetch() ) {
            if( $this->fnGetNextRow ) {
                $raOut = call_user_func( $this->fnGetNextRow, $this, $this->raParms );
            } else {
                $raOut = $this->kfrc->ValuesRA();
            }
        }
        return( $raOut );
    }
}

?>