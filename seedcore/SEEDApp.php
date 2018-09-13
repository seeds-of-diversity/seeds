<?php

/* SEEDApp
 *
 * Copyright (c) 2017-2018 Seeds of Diversity Canada
 *
 * Common classes and functions useful across Seed Apps
 */

include_once( "SEEDCore.php" );
include_once( "SEEDSessionAccount.php" );
include_once( SEEDROOT."Keyframe/KeyframeDB.php" );
include_once( SEEDCORE."console/console02.php" );


class SEEDAppBase
/****************
    Properties that an application should know about
 */
{
    public $lang = 'EN';
    public $logdir = '';    // directory where this application writes log files

    function __construct( $raConfig )
    {
        if( isset($raConfig['lang']) )    $this->lang = $raConfig['lang'];
        if( isset($raConfig['logdir']) )  $this->logdir = $raConfig['logdir'];
    }
}

class SEEDAppDB extends SEEDAppBase
/**************
    Create and hold a KeyframeDB
 */
{
//  public $lang is inherited
//  public $logdir is inherited
    public $kfdb;

    function __construct( $raParms )
    /*******************************
        raParms: kfdbHost, kfdbUserid, kfdbPassword, and kfdbDatabase are required
     */
    {
        parent::__construct( $raParms );

        if( !($this->kfdb = new KeyframeDatabase( $raParms['kfdbUserid'], $raParms['kfdbPassword'], @$raParms['kfdbHost'] )) ) {    // kfdbHost is optional
            die( "Cannot connect to database" );
        }

        if( !$this->kfdb->Connect( $raParms['kfdbDatabase'] ) ) {
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

    function __construct( $raParms )
    {
        // This is structured as a SEEDAppSession so client code (like Console) can use it as that base class.
        // However since $sess is itself subclassed, it is built as base SEEDSession then replaced by SEEDSessionAccount.
        parent::__construct( $raParms );

        // SEEDSessionAccount parms are in a sub-array of raParms. Feed it the logdir if that's defined at the top level of the array.
        $raSessParms = @$raParms['sessParms'] ?: array();
        if( !isset($raSessParms['logfile']) && !isset($raSessParms['logdir']) && isset($raParms['logdir']) ) {
            $raSessParms['logdir'] = $raParms['logdir'];
        }
        $this->sess = new SEEDSessionAccount( $this->kfdb, $raParms['sessPermsRequired'], $raSessParms );
    }
}

class SEEDAppConsole extends SEEDAppSessionAccount
{
//  public $lang is inherited
//  public $logdir is inherited
//  public $kfdb is inherited
//  public $sess is inherited
    public $oC;     // ConsoleUI gets the SEEDAppSession part of this class

    function __construct( $raParms )
    {
        parent::__construct( $raParms );
        $this->oC = new Console02( $this ); // Console02 takes SEEDAppSession
    }
}

class SEEDApp_Worker
{
    public $kfdb;
    public $sess;
    public $lang;

    function __construct( KeyFrameDB $kfdb, SEEDSessionAccount $sess, $lang )
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

    function __construct( Console01 $oC, KeyFrameDB $kfdb, SEEDSessionAccount $sess, $lang )
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
    public $raParms;
    public $bUTF8 = false;

    function __construct( SEEDAppDB $oApp, $raParms = array() )     // you can use any SEEDApp* object
    {
        $this->oApp = $oApp;
        $this->raParms = $raParms;
        $this->bUTF8 = intval(@$raParms['bUTF8']);
    }

    function Cmd( $cmd, $parms )
    {
        $rQ = $this->GetEmptyRQ();

        /* Derived classes should implement their own cmd processors.
         */

        if( $cmd == 'test' ) {
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
        If the input is cp1252, the output will be the charset defined by $this->bUTF8
     */
    {
        return( $this->bUTF8 ? utf8_encode( $s ) : $s );
    }

    function GetEmptyRQ()
    /********************
     */
    {
        return( array( 'bOk'=>false, 'sOut'=>"", 'sErr'=>"", 'sLog'=>"", 'raOut'=>array(), 'raMeta'=>array() ) );
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
