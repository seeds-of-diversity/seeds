<?php

/* SEEDMailer
 *
 * Copyright (c) 2010-2021 Seeds of Diversity Canada
 */

include_once( "SEEDMailerDB.php" );

class SEEDMailer
{
    private $oApp;
    private $oDB;

    function __construct( SEEDAppConsole $oApp, $k )
    {
        $this->oApp = $oApp;
        $this->oDB = new SEEDMailerDB( $oApp );
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

class SEEDMailerSend
{
    private $oApp;
    private $oDB;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oDB = new SEEDMailerDB( $oApp );
    }

    function SendOne()
    {

    }
}
