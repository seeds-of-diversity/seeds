<?php

/* SEEDMail database access
 *
 * Copyright (c) 2010-2021 Seeds of Diversity Canada
 */


class SEEDMailDB extends Keyframe_NamedRelations
/***************
 */
{
    private $dbname = "";

    function __construct( SEEDAppSessionAccount $oApp, $raConfig = array() )
    {
        $this->dbname = $this->oApp->GetDBName(@$raConfig['db'] ?: 'seeds1');
        $logdir = @$raConfig['logdir'] ?: $oApp->logdir;
        parent::__construct( $oApp->kfdb, $oApp->sess->GetUID(), $logdir );
    }

    protected function initKfrel( KeyframeDatabase $kfdb, $uid, $logdir )
    {
        // Don't set the logfile. The mailer should log mail that is sent, but we don't need to record every update to the tables.
        $parms = [];

        $fldM  = [ ['col'=>'sBody',          'type'=>'S'],
                   ['col'=>'fk_docrep_docs', 'type'=>'I'],
                   ['col'=>'docrepDB',       'type'=>'S'],
                   ['col'=>'email_from',     'type'=>'S'],
                   ['col'=>'email_subject',  'type'=>'S'],
                   ['col'=>'eStatus',        'type'=>'S'],
                   ['col'=>'sExtra',         'type'=>'S'],
                   ['col'=>'sStagedAddrs',   'type'=>'S'] ];

        $fldMS = [ ['col'=>'fk_SEEDMail',    'type'=>'K'],
                   ['col'=>'email_to',       'type'=>'S'],
                   ['col'=>'vars',           'type'=>'S'],
                   ['col'=>'eStageStatus',   'type'=>'S'],
                   ['col'=>'iResult',        'type'=>'S'],
                   ['col'=>'ts_sent',        'type'=>'S'],
                   ['col'=>'sExtra',         'type'=>'S'] ];

        $raKfrel = [];
        $raKfrel['M']  = new KeyFrame_Relation( $kfdb, ['Tables' => ['M' => ['Table' => "{$this->dbname}.SEEDMail",
                                                                             'Fields' => $fldM ]]],
                                                $uid, $parms );
        $raKfrel['MS'] = new KeyFrame_Relation( $kfdb, ['Tables' => ['MS' => ['Table' => "{$this->dbname}.SEEDMail_Staged",
                                                                              'Fields' => $fldMS],
                                                                     'M'  => ['Table' => "{$this->dbname}.SEEDMail",
                                                                              'Fields' => $fldM ]]],
                                                $uid, $parms );

        return( $raKfrel );
    }
}


class SEEDMailDB_Create
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

    sBody           TEXT,              # Message body can be text or a DocRep doc id/name
    email_from      VARCHAR(100),      # From:
    email_subject   VARCHAR(200),      # Subject:  (can contain SEEDTags expanded per-recipient)
    eStatus         enum('NEW','APPROVE','READY','SENDING','DONE') DEFAULT 'NEW',
    sExtra          TEXT,              # urlencoded extensions e.g. cc, bcc (which can be comma-separated lists)
    sStagedAddrs    TEXT,              # list of addresses/keys while mail is being set up
    sResults        VARCHAR(200)       # urlencoded summary of results e.g. SENT=25&FAILED=0 (details of each send are logged and SEEDMail_Staged rows are deleted eventually)
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
