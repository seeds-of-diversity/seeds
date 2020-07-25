<?php

/* ebulletin.php
 *
 * Copyright 2020 Seeds of Diversity Canada
 *
 * System for managing ebulletin subscription/unsubscription
 */

//include_once( SEEDCORE."SEEDMetaTable.php" );

class SoDWebsite_EBulletin
{
    private $oApp;

    function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;
    }


}
