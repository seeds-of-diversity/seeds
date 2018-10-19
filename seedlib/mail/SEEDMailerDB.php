<?php

/* SEEDMailer database access
 *
 * Copyright (c) 2010-2018 Seeds of Diversity Canada
 */


class SEEDMailerDB extends Keyframe_NamedRelations
/*****************
 */
{
    private $dbname = "";

    function __construct( SEEDAppSession $oApp, $raConfig = array() )
    {
        if( isset($raConfig['dbname']) ) {
            $this->dbname = $raConfig['dbname'].".";    // prepend this to the table names
        }
        $logdir = @$raConfig['logdir'] ?: $oApp->logdir;
        parent::__construct( $oApp->kfdb, $oApp->sess->GetUID(), $logdir );
    }

    protected function initKfrel( KeyframeDatabase $kfdb, $uid, $logdir )
    {
        // Don't set the logfile. The mailer should log mail that is sent, but we don't need to record every update to the tables.
        $parms = array();

        $fldM  = array( array('col'=>'sBody',          'type'=>'S'),
                        array('col'=>'fk_docrep_docs', 'type'=>'I'),
                        array('col'=>'docrepDB',       'type'=>'S'),
                        array('col'=>'email_from',     'type'=>'S'),
                        array('col'=>'email_subject',  'type'=>'S'),
                        array('col'=>'eStatus',        'type'=>'S'),
                        array('col'=>'sExtra',         'type'=>'S'),
                        array('col'=>'sStagedAddrs',   'type'=>'S') );

        $fldMS = array( array('col'=>'fk_SEEDMail',    'type'=>'K'),
                        array('col'=>'email_to',       'type'=>'S'),
                        array('col'=>'vars',           'type'=>'S'),
                        array('col'=>'eStageStatus',   'type'=>'S'),
                        array('col'=>'iResult',        'type'=>'S'),
                        array('col'=>'ts_sent',        'type'=>'S'),
                        array('col'=>'sExtra',         'type'=>'S') );

        $raKfrel = array();
        $raKfrel['M']  = new KeyFrame_Relation( $kfdb, array( "Tables" => array( 'M' => array( "Table" => "{$this->dbname}SEEDMail",
                                                                                               "Fields" => $fldM) ) ),
                                                $uid, $parms );
        $raKfrel['MS'] = new KeyFrame_Relation( $kfdb, array( "Tables" => array( 'MS' => array( "Table" => "{$this->dbname}SEEDMail_Staged",
                                                                                                "Fields" => $fldMS),
                                                                                 'M'  => array( "Table" => "{$this->dbname}SEEDMail",
                                                                                                "Fields" => $fldM) ) ),
                                                $uid, $parms );

        return( $raKfrel );
    }
}






class SEEDMailerDB_Create
{
const SEEDS2_DB_TABLE_SEEDMAIL =
"
CREATE TABLE SEEDMail (
        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    -- Message body can be a DocRep doc or text stored here
    sBody           TEXT,
    fk_docrep_docs  INTEGER NOT NULL,
    docrepDB        VARCHAR(20) NOT NULL DEFAULT '',    # if docrep is used this must contain the db name e.g. foo.docrep_docs

    email_from      VARCHAR(100),      # From:
    email_subject   VARCHAR(200),      # Subject:  (can contain SEEDTags expanded per-recipient)
    eStatus         enum('NEW','APPROVE','READY','SENDING','DONE') DEFAULT 'NEW',
    sExtra          TEXT,              # urlencoded extensions e.g. cc, bcc (which can be comma-separated lists)
    sStagedAddrs    TEXT               # list of addresses/keys while mail is being set up
);
";

const SEEDS2_DB_TABLE_SEEDMAIL_STAGED =
"
CREATE TABLE SEEDMail_Staged (
        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    fk_SEEDMail     INTEGER NOT NULL,

    email_to        TEXT,              # To: email address or numeric key of some other table (can contain comma-separated list of multiple recipients)
    vars            TEXT,              # url-encoded string of variables to be applied to the message body and email_subject
    eStageStatus    enum('READY','SENDING','SENT','FAILED') DEFAULT 'READY',
    iResult         INTEGER DEFAULT 0, # return value from smtp
    ts_sent         TIMESTAMP,
    sExtra          TEXT,              # urlencoded extensions e.g. cc, bcc (which can be comma-separated lists)

    INDEX (fk_SEEDMail,eStageStatus)   # optimize grouping by message, also lookup for a READY recipient of a given message
);
";
}

?>
