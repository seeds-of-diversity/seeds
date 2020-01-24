<?php

include_once( "eventsDB.php" );

class EventsLib
{
    public $oApp;
    public $oDB;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oDB = new EventsDB( $oApp );
    }
}

class Events_event
{
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

        $date = $this->_getValue( "date_alt" ) ?: SEEDDateDB2Str( $this->kfr->value("date_start"), $this->oE->oApp->lang );

        $s = $city;
        return( $s );



        switch( $kfrEV->value("type") ) {
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



        if( $this->bPrn ) {
            // only used in unilingual mode

            // this is not the best format for EV-type events, but it works for SS

            $s .= "<DIV class='evPRNTitle'>$title</DIV>";
            $s .= "</TD><TD valign=top>";
            $s .= "<P><B>"
                 .$date . SEEDStd_StrNBSP("",6) . $kfrEV->value("time")."</B>"
                 .( !empty($location) ? ("<BR>".$location) : "")
                 ."</P>";
            $s .= $this->_drawEventDetails( $kfrEV );
            if( $kfrEV->IsEmpty("contact") ) {
                $s .= "<P>Contact: ".$kfrEV->valueEnt("contact")."</P>";
            }
            $s .= "</TD></TR>\n<TR><TD align=left valign=top>";
        } else {
            $s = "<DIV class='EV_Event'>";

            $s .= "<H3>$title</H3>"
                 //."<BLOCKQUOTE>"
                 ."<P><B>"
                 .$date .SEEDStd_StrNBSP("",10).$kfrEV->value("time")."<br/>"
                 .(!empty($location) ? ($location."<br/>") : "")
                 .(!empty($city) ? ($city."<br/>") : "")
                 ."</B></P>";
            $s .= $this->_drawEventDetails( $kfrEV );
            if( !$kfrEV->IsEmpty("contact") ) {
                $s .= "<P>Contact: "
                     .($this->oWiki ? $this->oWiki->TranslateLinksOnly($kfrEV->value("contact")) : $kfrEV->value("contact"))
                     ."</P>";
            }
            if( !$kfrEV->IsEmpty("url_more") ) {
                $s .= "<p>".($this->lang == 'FR' ? "Plus d'information" : "More information").": "
                     .$this->oTag->ProcessTags( $kfrEV->value("url_more") )
                     ."</p>";
/*
 A new More Information link

                $sUrl = $kfrEV->value("url_more");
                $s .= "<a style='text-decoration:none;' target='_blank' href='$sUrl'>"
                     ."<div class='btn btn-success'>"
                     .($this->lang == 'FR' ? "Plus d'information" : "More information")
                     ."</div>"
                     ."</a>";
*/
            }
            $s .= //"</BLOCKQUOTE>"
            "</DIV>\n";
        }


    }
}