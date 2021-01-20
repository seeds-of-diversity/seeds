<?php

/* SEEDMail.php
 *
 * Manage sending email via SEEDMail
 *
 * Copyright (c) 2010-2021 Seeds of Diversity Canada
 */

include_once( "SEEDMailDB.php" );

class SEEDMail
/*************
    Class for a SEEDMail record
 */
{
    public  $oApp;
    private $oDB;
    private $kfr;

    function __construct( SEEDAppConsole $oApp, $k )
    {
        $this->oApp = $oApp;
        $this->oDB = new SEEDMailDB( $oApp );
        $this->kfr = $k ? $this->oDB->GetKFR('M',$k) : $this->oDB->KFRel('M')->CreateRecord();
    }

    function Key()  { return( $this->kfr->Key() ); }

//    function GetKFR()  { return($this->kfr); }

    function Store( $raParms )
    /*************************
        Copy the given parms to the current SEEDMail record and store it in the db
     */
    {
        if( !$this->kfr->Key() ) {
            // make sure defaults are set for new record
            $this->kfr->SetValue( 'eStatus', 'NEW' );
        }
        foreach( $raParms as $k=>$v ) {
            $this->kfr->SetValue( $k, $v );
        }
        return( $this->kfr->PutDBRow() ? $this->kfr->Key() : 0 );
    }

    function StageMail()
    {
        if( !$this->kfr )  goto done;

        $raAddr = explode( "\n", $this->kfr->Value('sAddresses') );
        foreach( $raAddr as $e ) {
            $e = trim($e);

            $oMS = new SEEDMailStaged( $this );
            $oMS->Store( ['fk_SEEDMail'=>$this->kfr->Key(), 'eStageStatus'=>'READY', 'sTo'=>$e] );
        }
        $this->Store( ['eStatus'=>'READY'] );

        done:
        return;
    }

    function DeleteMail( $kMail )
    {

    }
}


class SEEDMailStaged
/*******************
    Class for a SEEDMail_Staged record
 */
{
    private $oSMail;
    private $oDB;
    private $kfr;

    function __construct( SEEDMail $oSMail )
    {
        $this->oSMail = $oSMail;
        $this->oDB = new SEEDMailDB( $oSMail->oApp );
    }

    function Store( $raParms )
    /*************************
        Copy the given parms into the SEEDMail_Staged record and store it in the db
     */
    {
        $this->kfr = $this->oDB->KFRel('MS')->CreateRecord();
        foreach( $raParms as $k=>$v ) {
            $this->kfr->SetValue( $k, $v );
        }
        $this->kfr->SetValue('fk_SEEDMail', $this->oSMail->Key() );
        if( !$this->kfr->Value('tsSent') ) $this->kfr->SetNull('tsSent');   // force NULL for db
        $this->kfr->PutDBRow();
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
