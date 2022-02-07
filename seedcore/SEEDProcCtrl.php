<?php

/* SEEDProcCtrl.php
 *
 * Copyright (c) 2022 Seeds of Diversity Canada
 *
 * Manage a set of child processes.
 *
 * N.B. This does not use the PHP pcntl module because it is normally not enabled.
 *
 *      You can fork child processes via exec("cmd > /dev/null 2>&1 & echo $!;")
 *          The " & " launches cmd as a background process.
 *          stdout and stderr are redirected to /dev/null to prevent exec() from trying to gather output before it returns
 *          echo $! provides the output for exec(), which is the pid of the background process
 *
 *      You can also use proc_open to launch a system command and retrieve a pid from its return structure.
 */

class SEEDProcCtrl
{
    private $psetName;

    function __construct( $psetName )
    {
        $this->psetName = $psetName;
        if( !isset($_SESSION[$this->psetName]) ) $this->ClearCache();   // initialize, but don't clear an existing cache
    }

    function ClearCache()
    {
        $_SESSION[$this->psetName] = [];
    }

    function AddProc( $pid )
    {
        $_SESSION[$this->psetName][] = $pid;
    }

    function CountProcList()
    /***********************
        Return the number of processes monitored in the cache
     */
    {
        return( count($_SESSION[$this->psetName]) );
    }

    function RefreshProcList()
    /*************************
        Check whether each process is still running and remove stopped processes from the cache
     */
    {
        $raPids = $_SESSION[$this->psetName];
        $this->ClearCache();

        foreach( $raPids as $pid ) {
            if( file_exists("/proc/$pid") ) {
                $this->AddProc($pid);
            }
        }
    }
}
