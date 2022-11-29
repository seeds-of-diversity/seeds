<?php

class EventsDB extends Keyframe_NamedRelations
{
    private $oApp;

    function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;
        parent::__construct( $oApp->kfdb, $oApp->sess->GetUID(), $oApp->logdir );
    }

    function initKfrel( KeyframeDatabase $kfdb, $uid, $logdir )
    {
        $raKfrel = array();

        $def = ["Tables" => [
                    "E" => ["Table" => "{$this->oApp->DBName('seeds1')}.ev_events",
                            "Type"  => 'Base',
                            "Fields" => 'Auto'
               ]]];

        $parms = $logdir ? ['logfile'=>$logdir."events.log"] : [];

        $raKfrel['E'] = new Keyframe_Relation( $kfdb, $def, $uid, $parms );

        return( $raKfrel );
    }

    const SqlCreate = "
        CREATE TABLE IF NOT EXISTS ev_events (
                _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
                _created    DATETIME,
                _created_by INTEGER,
                _updated    DATETIME,
                _updated_by INTEGER,
                _status     INTEGER DEFAULT 0,

            type        enum('SS','EV','VIRTUAL') NOT NULL DEFAULT 'SS',  # SS = Seedy Sat/Sun, EV = regular, VIRTUAL = no location
            date_start  DATE NOT NULL DEFAULT '2017-01-01',
            date_end    DATE NOT NULL DEFAULT '2017-01-01',
            date_alt    VARCHAR(200),
            date_alt_fr VARCHAR(200),
            time        VARCHAR(200),
            location    VARCHAR(200),
            city        VARCHAR(200),
            province    VARCHAR(200),
            title       VARCHAR(200),
            title_fr    VARCHAR(200),
            spec        VARCHAR(200),                           # control tags (like texttype for the details)
            details     TEXT,
            details_fr  TEXT,
            contact     VARCHAR(200),
            url_more    VARCHAR(200),                           # click for more info, poster, special page, etc
            latlong     VARCHAR(200),                           # latitude and longitude urlencoded (blank means it needs to be geocoded)
            attendance  INTEGER,
            notes_priv  TEXT,                                   # internal notes

            vol_kMbr    INTEGER NOT NULL DEFAULT 0,             # our main volunteer there
            vol_notes   TEXT,                                   # materials to send and notes about volunteer/event
            vol_dSent   VARCHAR(20),                            # YYYY-MM-DD when materials sent ('' == not sent yet, anything else means no need to send)

            tsSync      INTEGER DEFAULT 0,                      # for synchronizing with SEEDGoogleSheetsUtil::SyncSheetAndDB()

            INDEX (date_start),
            INDEX (province)
        );
    ";
}
