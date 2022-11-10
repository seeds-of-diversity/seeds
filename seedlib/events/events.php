<?php

/* events.php
 *
 * Copyright (c) 2019-2022 Seeds of Diversity Canada
 *
 * Record and manage events with volunteers and exhibit materials
 *
 * type:
 *  SS: title is not stored: created via "Seedy Saturday/Sunday {city}"
 *      city = the city or town
 *      location = the venue and address
 *
 *  EV: title = the name of the event
 *      city = the city or town
 *      location = the venue and address
 *
 *  VIRTUAL:
 *      title = the name of the event
 *      city = blank
 *      province = blank
 *      location = blank
 */

include_once( "eventsDB.php" );

class EventsLib
{
    public $oApp;
    public $oDB;
    private $oTag;

    function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;
        $this->oDB = new EventsDB( $oApp );

        /* Certain fields can contain SEEDTags. Do local ResolveTag first then BasicResolver
         */
        $this->oBasicResolver = new SEEDTagBasicResolver( ['bLinkTargetBlank'=>true,             // force links to open new windows/tabs
                                                           'bLinkPlainDefaultCaption'=>true] );  // use a nice default caption for the link
        $raResolvers = [ ['fn'=>[$this,'ResolveTag'], 'raParms'=>[]],
                         ['fn'=>[$this->oBasicResolver,'ResolveTag'], 'raParms'=>[]]
                       ];
        $this->oTag = new SEEDTagParser( ['raResolvers' => $raResolvers] );
    }

    function ExpandStr( $s )
    {
        return( $this->oTag->ProcessTags($s) );
    }

    function ResolveTag( $raTag, SEEDTagParser $oTagDummy, $raParmsDummy )
    /*********************************************************************
        Called before SEEDTag's BasicResolver. Does these conversions:
            1) tags with no namespace that look like email addresses [[foo@seeds.ca]]          -> mailto:foo@seeds.ca
            2) tags with no namespace that don't look like email addresses [[seeds.ca/events]] -> http://seeds.ca/events

            N.B. This actually changes [[seeds.ca/events]] to [[http:seeds.ca/events]] without the forward slashes.
                 BasicResolver knows to do the right thing.
     */
    {
        $bHandled = false;
        $bRetagged = false;
        $s = "";

        if( $raTag['tag'] == '' ) {
            // If the tag has no namespace change it to http or mailto.
            $raTag['tag'] = (strpos($raTag['target'],'@') !== false) ? 'mailto' : 'http';
            $bRetagged = true;
        }

        // ReTagging works by returning true as the third return value and a new raTag as the fourth. Subsequent Resolvers will use the new raTag.
        return( [$bHandled, $s, $bRetagged, $raTag] );
    }
}

class Events_event
{
    private $oE;
    private $kfr = null;

    function __construct( EventsLib $oE, $kEvent )
    {
        $this->oE = $oE;
        $this->SetEvent($kEvent);
    }

    function SetEvent( $kEvent )
    {
        $this->kfr = $this->oE->oDB->KFRel('E')->GetRecordFromDBKey($kEvent);
        return( $this->kfr != null );
    }

    function GetTitle( $kfr )
    {
        if( $this->kfr->value("type") == "SS" ) {
            $city = $this->kfr->value('city');

            if( $this->oE->oApp->lang == "FR" ) {
                $title = "F&ecirc;te des semences $city";
            } else if( ($l = @date('l', @strtotime($this->kfr->value("date_start")))) ) {
                $title = "$city Seedy $l";          // e.g. Charlottetown Seedy Sunday
            } else {
                $title = "$city Seedy Saturday";    // default to Saturday
            }
        } else {
            $title = $this->_getValue( "title" );
        }
        return( $title );
    }


    protected function _getValue( $field, $bEnt = true )
    /***************************************************
        Get the English or French value, or the other one if empty
     */
    {
        $e = $this->kfr->value($field);
        $f = $this->kfr->value($field."_fr");
        $v = (($this->oE->oApp->lang=="EN" && $e) || ($this->oE->oApp->lang=="FR" && !$f)) ? $e : $f;
        if( $bEnt ) $v = SEEDCore_HSC($v);
        return( $v );
    }


    function DrawEvent()
    {
        $s = "";

//        if( $this->kfr->IsEmpty('latlong') ) { $this->geocode( $kfr ); }

        $city     = $this->kfr->Expand( "[[city]], [[province]]" );
        $location = $this->kfr->ValueEnt("location");
        $title    = $this->GetTitle( $this->kfr );
        $date     = $this->_getValue( "date_alt" ) ?: SEEDDateDB2Str( $this->kfr->value("date_start"), $this->oE->oApp->lang );

        switch( $this->kfr->value("type") ) {
            case 'EV':
            default:
                // show title, city, province, location as recorded
                break;

            case 'SS':
                // show city, location, title has been set to "$city Seedy Saturday"
                break;

            case 'VIRTUAL':
                // show title, don't show city, province, location
                $city = "";
                $location = "";
        }

        $s = "<div class='EV_Event'>".SEEDCore_ArrayExpandSeries($this->kfr->ValuesRA(), "[[k]]=>[[v]]<br/>").$this->kfr->Key()
                ."<h3>$title</h3>"
                ."<p><strong>"
                    .$date.SEEDCore_NBSP("",6).$this->kfr->value("time")."<br/>"
                    .($location ? "$location<br/>" : "")
                    .($city     ? "$city<br/>"     : "")
                ."</b></p>"
                .$this->drawEventDetails()
                .(($c = $this->kfr->Value("contact")) ? "<p>Contact: {$this->oE->ExpandStr($c)}</p>" : "")
                .(($u = $this->kfr->Value("url_more"))
                        ? ("<p>$u".($this->oE->oApp->lang == 'FR' ? "Plus d'information" : "More information").": {$this->oE->ExpandStr($u)}</p>")
                        : "" )
                .(($x = $this->kfr->Value("url_more"))
                        ? ("<p>AAA<a style='text-decoration:none;' target='_blank' href='$x'>BBB".$x
                     ."<div class='btn btn-success'>"
                     .($this->oE->oApp->lang == 'FR' ? "Plus d'information" : "More information")
                     ."</div>"
                     ."</a>CCC</p>"
                        )
                        : "")
                ."</div>";

/*
 A new More Information link

                $sUrl = $kfrEV->value("url_more");
                $s .= "";
*/

            return( $s );
    }

    private function drawEventDetails()
    /**********************************
     */
    {
        $details = $this->_getValue( "details", false );    // do not expand entities because this is allowed to contain HTML
        $details = trim($details);                                  // get rid of trailing blank lines

        if( intval(substr($this->kfr->value("date_start"),0,4)) < 2008 ) {
            // prior to 2008 we used plaintext, now use Wiki
            $s = nl2br($details);
        } else {
            $details = nl2br($details);
//correct new way            $s = $this->oTag->ProcessTags( $details );

//REMOVE
      //      $s = ($this->oWiki ? $this->oWiki->TranslateLinksOnly($details) : $details);
            $s = $details;
        }
        return( "<p style='width:80%'>$s</p>" );
    }


    function GetVolunteerLine()
    {
        $s = "";

        if( $this->kfr ) {
            $raMbrVol = ($kVol = $this->kfr->Value('vol_kMbr')) ? $this->oE->oApp->kfdb->QueryRA( "SELECT * FROM seeds_2.mbr_contacts WHERE _key='$kVol'" ) : [];
            $s .= @$raMbrVol['_key'] ? "{$raMbrVol['firstname']} {$raMbrVol['lastname']} in {$raMbrVol['city']} ({$raMbrVol['_key']})" : "";
        }

        return( $s );
    }
}