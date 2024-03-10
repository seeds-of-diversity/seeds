<?php

include_once( SEEDROOT."Keyframe/KeyframeUI.php" );
include_once( SEEDLIB."util/location.php" );

class EventManTabEvents extends KeyframeUI_ListFormUI // implements Console02TabSet_Worker or a flavour that is a KeyframeUI_ListFormUI
{
    private $oEvLib;

    function __construct( SEEDAppConsole $oApp, EventsLib $oEvLib )
    {
        $this->oEvLib = $oEvLib;

        $cols = [['label'=>'Event #',    'col'=>'_key' ],
                 ['label'=>'Type',       'col'=>'type' ],
                 ['label'=>'Title',      'col'=>'title' ],
                 ['label'=>'City',       'col'=>'city' ],
                 ['label'=>'Province',   'col'=>'province' ],
                 ['label'=>'Date',       'col'=>'date_start' ],
                 ['label'=>'Location',   'col'=>'location' ],
        ];
        $raConfig = $this->GetConfigTemplate(
            ['sessNamespace'        => 'EventManager_Events',
             'cid'                  => 'E',
             'kfrel'                => $this->oEvLib->oDB->GetKfrel('E'),
             'raListConfig_cols'    => $cols,
             'raSrchConfig_filters' => $cols,  // conveniently, we can use the same format as cols (because filters can be cols or aliases)
            ]);
        parent::__construct($oApp, $raConfig);
    }

    function Init()  { parent::Init(); }

    function ControlDraw()  { return( $this->DrawSearch() ); }

    function ContentDraw()
    {
        $s = "<style>
             .drawEvent { background-color: #ded; }
             </style>"
            .$this->ContentDraw_NewDelete();

//        $s = SEEDCore_utf8_encode($s);  // all data is stored in cp-1252 but this app outputs utf8 -- no because then all input has to be transcoded to cp1252

        return( $s );
    }

    /* These are not called directly, but referenced in raConfig
     */
    function FormTemplate( SEEDCoreForm $oForm )
    {
        $cid = $this->oComp->Cid();

        $sEvent = (($kEvent = $this->oComp->oForm->GetKey()) && ($oE = Events_event::CreateFromKey($this->oEvLib, $kEvent)))
                    ? $oE->DrawEvent() : "";

        $sForm = $this->oComp->DrawFormEditLabel('Event')
             ."|||BOOTSTRAP_TABLE(class='col-md-4'|class='col-md-8')
               ||| *Event #*                 || [[Key: | readonly]]
               ||| *Type*                    || ".SEEDFormBasic::DrawSelectCtrlFromOptsArray($oForm->Name('type'), 'type', ["Seedy Saturday/Sunday"=>'SS', "Event"=>'EV', "Virtual"=>'VIRTUAL'] )."

" /*
   SS      shows city and location because the title/fr is generated
   Virtual shows title/fr
   Event   shows all
   */
."
               ||| *Title*                   || [[Text:title| width:100%]]
               ||| *\" (french)*
                                             || [[Text:title_fr| width:100%]]
               ||| *City/Town*               || [[Text:city|width:80%;margin-bottom:5px]]&nbsp;".SEEDLocation::SelectProvinceWithSEEDForm($oForm, 'province')."
               ||| *Location (venue/address)*|| [[Text:location| width:100%]]
               ||| &nbsp;                    || \n

               ||| *Date*                    || {replaceWith class='col-md-4'}".$oForm->Date('date_start')." || {class='col-md-4'} *Alternate date text*
               ||| *Time*                    || {replaceWith class='col-md-4'} [[Text:time]]                 || {class='col-md-4'} [[Text:date_alt| width:100%]]
               ||| &nbsp;                    || \n

               ||| *Contact*                 || [[Text:contact| width:100%]]
               ||| *Link to more info*       || [[Text:url_more| width:100%]]
"
/*
date_end is deprecated - use date_alt instead
date_alt / date_alt_fr replace date but date_start is required for sorting
*/
."
               ||| &nbsp;                    || \n
               ||| *Details (English)*       || \n
               |||{replaceWith class='col-md-12'}  ".$oForm->TextArea( 'details', ['width'=>'100%', 'attrs'=>"wrap='soft'"] )."
               ||| *(French)*                || \n
               |||{replaceWith class='col-md-12'} ".$oForm->TextArea( 'details_fr', ['width'=>'100%', 'attrs'=>"wrap='soft'"] )."

               ||| &nbsp;                    || \n
               |||ENDTABLE

              <input type='submit' value='Save'/>

              <div style='padding:1em;margin-top:30px;width:100%;border:1px solid #777;font-size:8pt'>
                  <b>Location</b>: name of venue, address<br/>
                  <b>Alternate date text</b>: This is shown instead of Date, but you have to enter a Date in the calendar too to sort the event in the list.<br/>
                     e.g. if a March date is not decided yet enter ".date('Y')."-03-01 for Date, and TBA as Alternate - the event will appear at the March 1
                          position among the other events but will show TBA instead of the date.<br/>
                  <b>Contact</b>: put name, phone, email here instead of in details so we can delete that personal info later.<br/>
                  <b>Contact and Details</b>: email and web addresses are magically converted to links in the display view
              </div>";

            $s = "<div class='container-fluid'><div class='row'>
                      <div class='col-md-6'>$sForm</div><div class='col-md-6 drawEvent'>$sEvent</div>
                  </div></div>";

            return( $s );
    }

    function PreStore( Keyframe_DataStore $oDS )
    {
        if( !$oDS->Value('date_start') ) $oDS->SetValue('date_start', date('Y-m-d'));

        return( true );
    }
}
