<?php

/* SEEDApp
 *
 * Copyright (c) 2017 Seeds of Diversity Canada
 *
 * Common classes and functions useful across Seed Apps
 */

include_once( SEEDCORE."SEEDCore.php" );
// here is where you include KeyFrame
// and seedsessionaccount
// and console


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
{
    public $oC;

    function __construct( Console01 $oC, KeyFrameDB $kfdb, SEEDSessionAccount $sess, $lang )
    {
        parent::__construct( $kfdb, $sess, $lang );
        $this->oC = $oC;
    }
}

?>
