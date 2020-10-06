<?php

class EventsDB extends Keyframe_NamedRelations
{
    function __construct( SEEDAppSessionAccount $oApp )
    {
        parent::__construct( $oApp->kfdb, $oApp->sess->GetUID(), $oApp->logdir );
    }

    function initKfrel( KeyframeDatabase $kfdb, $uid, $logdir )
    {
        $raKfrel = array();

        $def = ["Tables" => [
                    "E" => ["Table" => "seeds.ev_events",
                            "Type"  => 'Base',
                            "Fields" => 'Auto'
               ]]];

        $parms = $logdir ? ['logfile'=>$logdir."events.log"] : [];

        $raKfrel['E']   = new Keyframe_Relation( $kfdb, $def, $uid, $parms );

        return( $raKfrel );
    }
}
