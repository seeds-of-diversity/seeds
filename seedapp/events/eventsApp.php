<?php

/* eventsApp.php
 *
 * Copyright (c) 2022 Seeds of Diversity Canada
 *
 * Display events in a UI
 */

include_once( SEEDCORE."SEEDDate.php" );
include_once( SEEDLIB."events/events.php" );
include_once( SEEDLIB."util/location.php" );

class EventsApp
{
    private $oApp;
    private $oEventsLib;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oEventsLib = new EventsLib( $oApp );
    }

    private function S($k)  { return($this->oEventsLib->oL->S($k)); }

    function DrawEventsPage()
    {
        $s = "";

// This is in seedsWPPlugin so map css and js are included in <head> for all wordpress pages, not just the events page. Seemed easier to maintain this code there.
//        if( function_exists('wp_register_style') ) {
//            // we're in wordpress. register the map css and js
//            wp_register_style( 'eventsMap-css', "https://seeds.ca/app/ev/dist/jqvmap.css", [], '1.0' /*, 'screen'*/ );    // optional final parm: not media-specific
//            wp_enqueue_style( 'eventsMap-css' );
//            wp_register_script( 'eventsMap-js', "https://seeds.ca/app/ev/dist/jquery.vmap.js", ['jquery'], '1.0', false );    // put the script at the top of the file because it's (sometimes) called in the middle
//            wp_enqueue_script( 'eventsMap-js' );
//            wp_register_script( 'eventsMapCanada-js', "https://seeds.ca/app/ev/dist/maps/jquery.vmap.canada.js", ['jquery','eventsMap-js'], '1.0', false );    // put the script at the top of the file because it's (sometimes) called in the middle
//            wp_enqueue_script( 'eventsMapCanada-js' );
//        }


    $oForm = new SEEDCoreForm('E');
    $oForm->Update();

    $sList = "";

    /* Normalize the input control values, set those into the form, and return the new form values for convenience
     */
    list($pDate1, $pDate2, $pProv, $pSearch,
         $dbDate1,$dbDate2,$dbProv,$dbSearch) = $this->getControls($oForm);

    $sCond = "(date_start >= '$dbDate1')"
            .($pDate2 ? " AND (date_start <= '$dbDate2')" : "")
            .($pProv  ? " AND (province = '$dbProv')" : "")
            .($pSearch? " AND (title LIKE '%{$dbSearch}%'    OR title_fr LIKE '%{$dbSearch}%' OR
                               city LIKE '%{$dbSearch}%'     OR location LIKE '%{$dbSearch}%' OR
                               details LIKE '%{$dbSearch}%'  OR details_fr LIKE '%{$dbSearch}%')" : "");

    if( ($kfr = $this->oEventsLib->oDB->GetKFRC('E', $sCond, ['sSortCol'=>'date_start', 'bSortDown'=>0] )) ) {
        while( $kfr->CursorFetch() ) {
            $e = Events_event::CreateFromKey( $this->oEventsLib, $kfr->Key() );
            $sList .= "<div style='float:right'>{$e->GetDateNice()}</div>";
            $sList .= $e->DrawEvent();
            $sList .= "<hr style='clear:both'/>";
        }
    }

    $sDate1 = SEEDDate::NiceDateStrFromDate($pDate1, $this->oApp->lang);
    $sDate2 = SEEDDate::NiceDateStrFromDate($pDate2, $this->oApp->lang);

    $sBanner = "<style>
                .events-banner        { text-align:center;border-top:1px solid #bbb; border-bottom:1px solid #bbb; }
                .events-banner-filter { background-color:#ddd;border-radius:5px; }
                </style>"
              ."<div class='events-banner'>"
                  ."<span style='font-size:1.6em'>"
                  ."$sDate1 - $sDate2"
                  ."</span>"
                  .($pProv ? ("<div class='events-banner-filter'>".($this->oApp->lang=='FR' ? "en" : "in")." ".SEEDLocation::ProvinceName($pProv,$this->oApp->lang)."</div>") : "")
                  .($pSearch ? "<div class='events-banner-filter'>containing \"".SEEDCore_HSC($pSearch)."\"</div>" : "")
              ."</div>";


    $s .= "<form id='events_form' action='' method='post'>"
         ."<div class='container-fluid'>"
         ."<div style='margin-bottom:30px'>"
             .$oForm->Text( 'pSearch', "", ['attrs'=>"placeholder='Search for events' style='border:1px solid #ccc;height:2.5em;padding:5px;width:30em'"] )
             ."&nbsp;<input type='submit' value='Search'/>"
         ."</div>"

         ."<div class='row'>"
         ."<div class='col-md-2'>"
             ."<ul class='nav nav-tabs'>
                 <li class='active'><a href='#date_range' aria-controls='date_range' role='tab' data-toggle='tab'>Date range</a></li>
               </ul>"

         ."<div style='border-right:1px solid #ccc;border-bottom:1px solid #ccc;padding:0 20px 20px 0;width:100%'>
               <label for='sfEp_pDate1'>From</label>
               <input type='date' title='From' class='event-date-ctrl form-control' value='{$oForm->Value('pDate1')}' name='sfEp_pDate1' id='sfEp_pDate1'>
               <label for='sfEp_pDate2'>To</label>
               <input type='date' title='To' class='event-date-ctrl form-control' value='{$oForm->Value('pDate2')}' name='sfEp_pDate2' id='sfEp_pDate2'>
           </div>"

         ."</div>"
         ."<div class='col-md-8'>"
             ."<h1 style='text-align:center;margin-bottom:20px'>{$this->S('Events')}</h1>"
             .$sBanner
             .$sList
         ."</div>"
         ."<div class='col-md-2' style='text-align:center'>"
             ."<div style='width:100%;border:1px solid #ccc' id='vmap'></div>"
             //.$oForm->Select( 'pProv', ["-- Province --"=>'',"Ontario"=>'ON'], "", ['attrs'=>"onChange='submit();'"] )
             .SEEDLocation::SelectProvinceWithSEEDForm( $oForm, 'pProv', ['bAll'=>true,'bFullnames'=>true,'lang'=>$this->oApp->lang,'sAttrs'=>"onChange='submit();'"] )
         ."</div>"

         ."</div>"
         ."</form>";

$s .= "
<script>
/* onchange is great with date controls if you use the calendar control, but as soon as you try to type in them there will be an onchange for every keystroke.
 * This switches to an onblur if you start typing, and adds a listener for the Enter key.
 */
jQuery('input.event-date-ctrl').change(function() {
    this.form.submit();                                      // ordinarily use onchange for mouse-controlled calendar
});
jQuery('input.event-date-ctrl').keypress(function(e) {       // but when you type something
    jQuery(this).off('change blur');                         // remove bindings
    jQuery(this).blur(function () { this.form.submit(); });  // bind blur and Enter to submit the form
    if(e.keyCode === 13) { this.form.submit(); }
});
</script>

    <script type='text/javascript'>
    jQuery(document).ready(function() {
        let w = jQuery('#vmap').width();
        w = Math.floor(w);
        jQuery('#vmap').width(w);
        jQuery('#vmap').height(w);


      jQuery('#vmap').vectorMap({
          map: 'canada_en',
          backgroundColor: null,
          color: '#5c882d', //'#c23616',
          hoverColor: '#999999',
          enableZoom: false,
          showTooltip: true,
          selectedRegions: [".($pProv ? "'$pProv'" : "")."],
          onRegionClick: function(element, code, region)
          {
              // User clicked on the map. Set the <select> and submit the form.
              jQuery('#sfEp_pProv').val(code.toUpperCase());
              jQuery('#events_form').submit();

//              jQuery('#selectedRegion').html('Seedy Events in '+region);
//              jQuery('#texthere',window.parent.document).html('Seedy Events in '+region);
          }
      });
    });
    </script>
";

        return( $s );
    }

    private function getControls( SEEDCoreForm $oForm )
    /**************************************************
        Get form control values.
        Normalize them.
        Put the new values into the form.
        Return the new values for convenience.
     */
    {
        /* FROM date: default is today
         * TO date:   If empty or less than FROM, default is the date of the first event that occurs at least 30 days after FROM date.
         *            If pSearch has just been set, TO date is MAX(date_start) so all events after FROM are searched.
         */
        $pDate1  = $oForm->Value('pDate1');               // show events >= this date
        $pDate2  = $oForm->Value('pDate2');               // show events <= this date
        $pProv   = $oForm->Value('pProv');
        $pSearch = $oForm->Value('pSearch');


        /* FROM: Default is today.
         */
        if( !$pDate1 )  $pDate1 = date("Y-m-d");

        /* TO: if pSearch, set to last event so all future events are searched
         */
//TODO: to be complete, this test should only happen if there is a NEW search term.
//      If you search, then change the TO date, that change is ignored.
        if( $pSearch ) {
            if( ($kfr = $this->oEventsLib->oDB->GetKFRCond('E', "", ['sSortCol'=>'date_start','bSortDown'=>true])) ) {
                $pDate2 = $kfr->value('date_start');
            } else {
                $pDate2 = "";   // use the default below
            }
        }
        /* TO: default is the date of the first event that occurs at least 30 days after FROM (or just 30 days later if there are no future events).
         */
        if( !$pDate2 || $pDate2 < $pDate1 ) {
            $pDate2 = date("Y-m-d", strtotime($pDate1) + 30*24*3600);   // 30 days after pDate1

            // make sure there's at least one event in the date range, by extending the window to the next event
            if( ($kfrNext = $this->oEventsLib->oDB->GetKFRCond('E', "date_start >= '".addslashes($pDate2)."'", ['sSortCol'=>'date_start','bSortDown'=>false])) ) {
                $pDate2 = $kfrNext->Value('date_start');
            }
        }

        $oForm->SetValue( 'pDate1',  $pDate1 );
        $oForm->SetValue( 'pDate2',  $pDate2 );
        $oForm->SetValue( 'pProv',   $pProv );
        $oForm->SetValue( 'pSearch', $pSearch );

        return( [$pDate1,$pDate2,$pProv,$pSearch, addslashes($pDate1), addslashes($pDate2), addslashes($pProv), addslashes($pSearch)] );
    }
}
