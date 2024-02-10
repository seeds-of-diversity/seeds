<?php

include_once( SEEDROOT."Keyframe/KeyframeUI.php" );
include_once( SEEDLIB."util/location.php" );

class EventManTabEvents extends KeyframeUI_ListFormUI // implements Console02TabSet_Worker or a flavour that is a KeyframeUI_ListFormUI
{
    private $oEvLib;

    function __construct( SEEDAppConsole $oApp, EventsLib $oEvLib )
    {
        $this->oEvLib = $oEvLib;
        parent::__construct($oApp, (new Events_KFUIListForm_Config($oApp, $oEvLib))->GetConfig());  //$this->getListFormConfig());
    }

    function Init()  { parent::Init(); }

    function ControlDraw()
    {
        $s = "";

        return( $s );
    }

    function ContentDraw()
    {
        $cid = $this->oComp->Cid();

        $sEvent = "";
        if( ($kEvent = $this->oComp->oForm->GetKey()) && ($oE = Events_event::CreateFromKey($this->oEvLib, $kEvent)) ) {
            $sEvent = $oE->DrawEvent();
        }

        $s = $this->DrawStyle()
           ."<style>
             .drawEvent { background-color: #ded; }
             </style>
             <div>{$this->DrawList()}</div>
             <div style='border:1px solid #777; padding:15px'>
                 <div style='margin-bottom:5px'><div style='display:inline-block'>{$this->oComp->ButtonNew()}</div>&nbsp;&nbsp;&nbsp;<button>Delete</button></div>
                 <div style='width:100%;padding:20px;border:2px solid #999'>
                     <h4>Edit Event</h4>
                     <div class='container-fluid'><div class='row'>
                         <div class='col-md-6'>{$this->DrawForm()}</div>
                         <div class='col-md-6 drawEvent'>$sEvent</div>
                     </div></div>
                 </div>
             </div>";

        return( $s );
    }
}

function BS_Row2( $raCols, $raParms = array() )
/*********************************************
    Put any number of columns in a row

    $raCols: array( array( col_class, col_content ), ...
 */
{
    $s = "<div class='row'>";

    foreach( $raCols as $raCol ) {
        $s .= "<div class='{$raCol[0]}'>{$raCol[1]}</div>";
    }

    $s .= "</div>";  // row

    return( $s );
}


class Events_KFUIListForm_Config extends KeyFrameUI_ListFormUI_Config
/*******************************
    Get the configuration for a KeyframeUI_ListFormUI on the events table
 */
{
    private $oApp;
    private $oEvLib;

    function __construct( SEEDAppDB $oApp, EventsLib $oEvLib )
    {
        $this->oApp = $oApp;
        $this->oEvLib = $oEvLib;

        parent::__construct();  // sets the default raConfig
        $this->raConfig['sessNamespace'] = 'EventManager_Events';
        $this->raConfig['cid']           = 'E';
        $this->raConfig['kfrel']         = $this->oEvLib->oDB->GetKfrel('E');
        $this->raConfig['raListConfig']['cols'] = [
                    [ 'label'=>'Event #',    'col'=>'_key' ],
                    [ 'label'=>'Type',       'col'=>'type' ],
                    [ 'label'=>'Title',      'col'=>'title' ],
                    [ 'label'=>'City',       'col'=>'city' ],
                    [ 'label'=>'Province',   'col'=>'province' ],
                    [ 'label'=>'Date',       'col'=>'date_start' ],
                    [ 'label'=>'Location',   'col'=>'location' ],
        ];
        // conveniently, we can use the same format for search filters as for the cols (because filters can be cols or aliases)
        $this->raConfig['raSrchConfig']['filters'] = $this->raConfig['raListConfig']['cols'];
    }

    /* These are not called directly, but referenced in raConfig
     */
    function FormTemplate( SEEDCoreForm $oForm )
    {
        $s =  "|||BOOTSTRAP_TABLE(class='col-md-4'|class='col-md-8')
               ||| *Event #*                 || [[Key: | readonly]]
               ||| *Type*                    || ".SEEDFormBasic::DrawSelectCtrlFromOptsArray($oForm->Name('type'), 'type', ["Seedy Saturday/Sunday"=>'SS', "Event"=>'EV', "Virtual"=>'VIRTUAL'] )."

" /*
   SS      shows city and location because the title/fr is generated
   Virtual shows title/fr
   Event   shows all
   */
."
               ||| *Title*                   || [[Text:title| width:100%]]
               ||| *(french)*                || [[Text:title_fr| width:100%]]
               ||| *City/Town*               || [[Text:city|width:80%;margin-bottom:5px]]&nbsp;".SEEDLocation::SelectProvinceWithSEEDForm($oForm, 'province')."
               ||| *Location*                || [[Text:location| width:100%]]

               ||| &nbsp;                    || \n
               ||| *Date*                    || ".$oForm->Date('date_start')."
               ||| *Time*                    || [[Text:time]]
"
/*
date_end is deprecated - use date_alt instead
date_alt / date_alt_fr replace date but date_start is required for sorting

            ."<div class='well'>"
                .BS_Row2( array( array( 'col-md-6', $oForm->Date( 'date_start', "Date" )."<br/>"
                                                //.$oForm->Date( 'date_end', "Date end" )."<br/>"   use date_alt instead of a range
                                                  .$oForm->Text( 'time', "Time" ) ),
                                array( 'col-md-6', "<b>Date Alternate</b><br/>"
                                                  .$oForm->Text( 'date_alt', "(en)" )."<br/>"
                                                  .$oForm->Text( 'date_alt_fr', "(fr)" ) )
                   ))
            ."</div>"
*/
."
               ||| &nbsp;                    || \n
               ||| *Details (English)*       || \n
               |||{replaceWith class='col-md-12'}  ".$oForm->TextArea( 'details', ['width'=>'100%', 'attrs'=>"wrap='soft'"] )."
               ||| *(French)*                || \n
               |||{replaceWith class='col-md-12'} ".$oForm->TextArea( 'details_fr', ['width'=>'100%', 'attrs'=>"wrap='soft'"] )."

               ||| &nbsp;                    || \n
               |||ENDTABLE

              <input type='submit' value='Save'/>";


    $s .=

            "<div id='ev_titlebox' style='margin-bottom:10px'>"
            ."<div class='row'>".$oForm->Text( 'title',    "Title",    array('size'=>40, 'bsCol'=>"md-10,md-2") )."</div>"
            ."<div class='row'>".$oForm->Text( 'title_fr', "(French)", array('size'=>40, 'bsCol'=>"md-10,md-2") )."</div>"
            ."</div>"

            ."<div class='row'>".$oForm->Text( 'contact', "Contact", array('size'=>30, 'bsCol'=>"md-10,md-2") )."</div>"
            ."<div class='row'>".$oForm->Text( 'url_more', "Link to<br/> more info", array('size'=>30, 'bsCol'=>"md-10,md-2") )."</div>"
            ."<br/><br/>"

            ."<br/><br/>"
            ."<div style='padding:1em;margin:0 auto;width:95%;border:thin solid black;font-size:8pt;font-family:verdana,sans serif;'>"
                ."<B>Location</B>: name of venue, address<BR/>"
                ."<B>Date</B>: must be YYYY-MM-DD<BR/>"
                ."<B>Alternate Date Text</B>: enter a Date too, so the list can sort properly, but this will be shown instead. "
                ."e.g. if date is unknown enter 2014-01-01 for Date, TBA as Alternate - the list will show TBA as the date and it will put the event at "
                ."Jan 1, 2014<br/>"
                ."<B>Contact</B>: name, phone, email here instead of in details so we can delete that personal info later.<BR/>"
                ."<BR/>"
                ."Contact and Details use special tags [[mailto:my@email.ca] ] and [[http://my.website.ca] ]"  // escape the [[ because console01 expands template tags
            ."</div>";

            return( $s );
    }

    function PreStore( Keyframe_DataStore $oDS )
    {
        if( !$oDS->Value('date_start') ) $oDS->SetValue('date_start', date('Y-m-d'));

        return( true );
    }
}

