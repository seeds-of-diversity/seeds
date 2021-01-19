<?php

/* SEEDMail.php
 *
 * Manage sending email via SEEDMail
 *
 * Copyright (c) 2010-2021 Seeds of Diversity Canada
 */

include_once( "SEEDMailDB.php" );

class SEEDMail
{
    private $oApp;
    private $oDB;

    function __construct( SEEDAppConsole $oApp, $k )
    {
        $this->oApp = $oApp;
        $this->oDB = new SEEDMailDB( $oApp );
    }

    function NewMail( $raParms )
    /***************************
        Create a new SEEDMail
     */
    {

    }

    function UpdateMail( $kMail, $raParms )
    {

    }

    function StageMail( $kMail )
    {

    }

    function DeleteMail( $kMail )
    {

    }
}

class SEEDMailSend
{
    private $oApp;
    private $oDB;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oDB = new SEEDMailDB( $oApp );
    }

    function SendOne()
    {

    }
}
