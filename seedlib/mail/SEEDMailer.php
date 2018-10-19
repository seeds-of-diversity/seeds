<?php

include_once( "SEEDMailerDB.php" );

class SEEDMailerSetup
{
    private $oApp;
    private $oDB;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oDB = new SEEDMailerDB( $oApp );
    }

    function NewMail( $raParms )
    {

    }

    function ApproveMail( $kMail )
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

?>