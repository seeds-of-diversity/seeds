<?php

/* SEEDApp
 *
 * Copyright (c) 2017-2018 Seeds of Diversity Canada
 *
 * Common classes and functions useful across Seed Apps
 */

include_once( SEEDCORE."SEEDCore.php" );
include_once( SEEDCORE."SEEDSession.php" );
include_once( SEEDROOT."Keyframe/KeyframeDB.php" );


class SEEDAppDB
/**************
    Create and hold a KeyframeDB
 */
{
    public $kfdb;

    function __construct( $raParms )
    /*******************************
        raParms: kfdbHost, kfdbUserid, kfdbPassword, and kfdbDatabase are required
     */
    {
        if( !($this->kfdb = new KeyframeDatabase( $raParms['kfdbHost'], $raParms['kfdbUserid'], $raParms['kfdbPassword'] )) ) {
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
//  public $kfdb is inherited
    public $sess;

    function __construct( $raParms = array() )
    {
        parent::__construct( $raParms );
        $this->sess = new SEEDSession();
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

?>
