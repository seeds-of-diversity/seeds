<?php

/* eventsApp.php
 *
 * Copyright (c) 2022 Seeds of Diversity Canada
 *
 * Display events in a UI
 */

include_once( SEEDCORE."SEEDDate.php" );
include_once( SEEDLIB."events/events.php" );

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

    $sList = "";

    $pDate1 = SEEDInput_Str('pDate1');               // show events >= this date
    $pDate2 = SEEDInput_Str('pDate2');               // show events <= this date

    /* Compute FROM-TO date range.
     * FROM: Default is today.
     * TO: If empty or prior to FROM date, default is 30 days past FROM date.
     */
    if( !$pDate1 )  $pDate1 = date("Y-m-d");                        // default today
    if( !$pDate2 || $pDate2 < $pDate1 ) {
        $pDate2 = date("Y-m-d", strtotime($pDate1) + 30*24*3600);   // default 30 days after pDate1
    }
    $sDate1 = SEEDDate::NiceDateStrFromDate($pDate1, $this->oApp->lang);
    $sDate2 = SEEDDate::NiceDateStrFromDate($pDate2, $this->oApp->lang);

$pProv = '';
    $sCond = "(date_start >= '".addslashes($pDate1)."')"
            .($pDate2 ? " AND (date_start <= '".addslashes($pDate2)."')" : "")
            .($pProv  ? " AND (province = '".addslashes($pProv)."')" : "");

    if( ($kfr = $this->oEventsLib->oDB->GetKFRC('E', $sCond, ['sSortCol' => 'date_start', 'bSortDown' => 0] )) ) {
        while( $kfr->CursorFetch() ) {
            $e = Events_event::CreateFromKey( $this->oEventsLib, $kfr->Key() );
            $sList .= "<div style='float:right'>{$e->GetDateNice()}</div>";
            $sList .= $e->DrawEvent();
            $sList .= "<hr style='clear:both'/>";
        }
    }

    $s .= "<form action='' method='post'>"
         ."<div class='container-fluid'>"
         ."<div style='margin-bottom:30px'>"
             .$oForm->Text( 'srch', "", ['attrs'=>"placeholder='Search for events' style='border:1px solid #ccc;height:2.5em;padding:5px;width:30em'"] )
         ."</div>"

         ."<div class='row'>"
         ."<div class='col-md-2'>"
             ."<ul class='nav nav-tabs' role='tablist'>
                 <li role='presentation' class='active'><a href='#date_range' aria-controls='date_range' role='tab' data-toggle='tab'>Date range</a></li>
               </ul>"

."<div role='tabpanel' class='tab-pane active' id='date_range'>
    <div class='events-range input-daterange input-group' id='datepicker' style='border-right:1px solid #ccc;border-bottom:1px solid #ccc;padding:0 20px 20px 0;width:100%'>
        <label for='start'>From</label>
        <div class='input-group events-date-input'>
            <input type='date' title='From' class='form-control' value='$pDate1' name='pDate1' id='pDate1' aria-label='Start Date'>
            <span class='input-group-addon'><i class='am-events'></i></span>
        </div>
        <label for='end'>To</label>
        <div class='input-group events-date-input'>
            <input type='date' title='To' class='form-control' value='$pDate2' name='pDate2' id='pDate2' aria-label='End Date'>
            <span class='input-group-addon'><i class='am-events'></i></span>
        </div>
    </div>
</div>"

         ."</div>"
         ."<div class='col-md-8'>"
             ."<h1 style='text-align:center;margin-bottom:20px'>{$this->S('Events')}</h1>"
             ."<div style='font-size:1.6em;text-align:center;border-top:1px solid #bbb; border-bottom:1px solid #bbb'>$sDate1 - $sDate2</div>"
;
        $s .= $sList;

        $s .= "</div>"
         ."<div class='col-md-2' style='text-align:center'>"
             ."<div style='width:100%;border:1px solid #ccc' id='vmap'></div>"
             .$oForm->Select( 'prov', ["-- Province --"=>''], "" )
         ."</div>"

         ."</div>"
         ."</form>";

$s .= "
<script>
/* onchange is great with date controls if you use the calendar control, but as soon as you try to type in them there will be an onchange for every keystroke.
 * This switches to an onblur if you start typing, and adds a listener for the Enter key.
 */
jQuery('input#pDate1, input#pDate2').change(function() {
    this.form.submit();                                 // ordinarily use onchange for mouse-controlled calendar
});
jQuery('input#pDate1, input#pDate2').keypress(function(e) {  // but when you type something
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
          onRegionClick: function(element, code, region)
          {
              jQuery('#selectedRegion').html('Seedy Events in '+region);
              jQuery('#texthere',window.parent.document).html('Seedy Events in '+region);
          }
      });
    });
    </script>
";

        return( $s );
    }

}
