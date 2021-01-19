<?php

/* SeedMailUI.php
 *
 * Copyright 2021 Seeds of Diversity Canada
 *
 * UI for apps that send email using SEEDMail
 */

class SEEDMailUI
{
    private $oApp;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
    }
}