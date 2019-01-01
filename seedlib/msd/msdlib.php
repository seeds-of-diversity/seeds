<?php

/* msdlib
 *
 * Copyright (c) 2018-2019 Seeds of Diversity
 *
 * Support for MSD app-level code that shouldn't know about MSDCore but can't get what it needs from MSDQ
 */

require_once "msdcore.php";


class MSDLib
{
    private $oMSDCore;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oMSDCore = new MSDCore( $oApp, array() );
    }

    function PermOfficeW()  { return( $this->oMSDCore->PermOfficeW() ); }
    function PermAdmin()    { return( $this->oMSDCore->PermAdmin() ); }
}