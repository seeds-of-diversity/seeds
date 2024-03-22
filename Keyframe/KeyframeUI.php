<?php

/* KeyframeUI
 *
 * UI classes for Keyframe
 *
 * Copyright (c) 2009-2020 Seeds of Diversity Canada
 */

include_once( SEEDCORE."SEEDUI.php" );
include_once( "KeyframeForm.php" );

class KeyframeUIComponent extends SEEDUIComponent
{
    private $kfrel;
    private $raViewParms = array();

    function __construct( SEEDUI $o, Keyframe_Relation $kfrel, $cid = "A", $raCompConfig = array() )
    {
         $this->kfrel = $kfrel;     // set this before the parent::construct because that uses the factory_SEEDForm
         parent::__construct( $o, $cid, $raCompConfig );
    }

    protected function factory_SEEDForm( $cid, $raSFParms )
    {
        // Any widget can find this KeyframeForm at $this->oComp->oForm
        return( new KeyframeForm( $this->kfrel, $cid, $raSFParms ) );
    }

    function Start()
    {
        parent::Start();

        /* Now the Component is all set up with its uiparms and widgets, but the oForm is not initialized to
         * the current key (unless it got loaded during Update).
         */
        $this->oForm->LoadKFR($this->Get_kCurr());
    }

    function FetchViewSlice( $iViewSliceOffset, $nViewSliceSize )
    /************************************************************
        If a widget needs a slice of a view it can call here to get it.

        Returns the rows in the view slice and the total number of rows in the view (used for navigation by the caller)
     */
    {
        list($oView,$raWindowRows) = $this->GetViewWindow($iViewSliceOffset,$nViewSliceSize);

        // This redundantly returns the iViewSliceOffset because the method is designed to allow a larger slice to be returned if convenient.
        // The caller must assume that the returned offset could be lower than requested.
        return( [$raWindowRows, $iViewSliceOffset, $oView->GetNumRows()] );
    }

    function GetViewWindow( $iWindowOffset, $nWindowSize )
    {
        $raViewParms = ['sSortCol'  => $this->GetUIParm('sSortCol'),
                        'bSortDown' => $this->GetUIParm('bSortDown'),
                        'sGroupCol' => $this->GetUIParm('sGroupCol'),
                        'iStatus'   => $this->GetUIParm('iStatus')];

        $oView = new KeyframeRelationView( $this->kfrel, $this->sSqlCond, $raViewParms );
        $raWindowRows = $oView->GetDataWindowRA( $iWindowOffset, $nWindowSize );

        return( [$oView, $raWindowRows] );
    }

    function DeleteRow( int $kDel )
    /******************************
        Called when SEEDUIComponent gets a kDel uiParm.
        Use the component's form to perform fn_DSPreOp and delete, since it's configured for the app.
     */
    {
        $bDeleted = false;

        if( !$kDel )  goto done;

        if( $this->oForm->GetKey() != $kDel )  $this->oForm->LoadKFR($kDel);

        // Component can be configured with 'KFCompParms' => ['raSEEDFormParms'=>['DSParms'=>['fn_DSPreOp'=>[$this,'myPreOp']...
        if( $this->oForm->DeleteKFRecord() ) {
            if( !$this->oUI->GetUserMsg() )  $this->oUI->SetUserMsg("Deleted $kDel");           // generic message; don't do this if fn_DSPreOp already did
            $bDeleted = true;
        } else {
            if( !$this->oUI->GetErrMsg() )  $this->oUI->SetErrMsg("Could not delete $kDel");    // generic message; don't do this if fn_DSPreOp already did
        }

        done:
        return( $bDeleted );
    }
}

class KeyframeUIWidget_List extends SEEDUIWidget_List
{
    function __construct( KeyframeUIComponent $oComp, $raConfig = array() )
    {
        parent::__construct( $oComp, $raConfig );
    }
}

class KeyframeUIWidget_Form extends SEEDUIWidget_Form
{
    function __construct( KeyframeUIComponent $oComp, $raConfig = array() )
    {
        parent::__construct( $oComp, $raConfig );
    }

    function Draw()
    {
        $s = "";

        if( $this->oComp->oForm->GetKey() ) {
            $s = parent::Draw();
        } else {
            $s = parent::Draw();
        }
        return( $s );
    }
}

class KeyFrameUI_ListFormUI
/**************************
    A UI subsystem containing a single Component and comprised of a List, a Form, and a Search Control.

    Usage: give the config what it needs to know, then:
           Init() any time after construction, but before you want to read any Component state values (e.g. kCurr)
           DrawList() to get the html for the list
           DrawForm() to get the html for the form
           DrawSearch() to get the html for the search control
           DrawStyle() to get the <style> for this set of widgets

    raConfig:
        sessNamespace   = ns for the oSVA where UI parms are stored
        cid             = a letter designating the Component id
        kfrel           = the Component's kf relation
        KFCompParms     = parms for KeyframeUIComponent   e.g. raSEEDFormParms
        raListConfig    = define the list widget
        raFormConfig    = define the form widget
        raSrchConfig    = define the search widget
 */
{
    protected $oApp;
    protected $raConfig;
    protected $oComp;
    protected $oSrch;
    protected $oList;
    protected $oForm;

    function __construct( SEEDAppSession $oApp, $raConfig )
    {
        $this->oApp = $oApp;
        $this->raConfig = $raConfig;
    }

    function Init()
    {
        $oUI = new SEEDUI_Session( $this->oApp->sess, $this->raConfig['sessNamespace'] );
        $this->oComp = new KeyframeUIComponent( $oUI, $this->raConfig['kfrel'], $this->raConfig['cid'], $this->raConfig['KFCompParms'] );
        $this->oComp->Update();

        $this->oSrch = new SEEDUIWidget_SearchControl( $this->oComp, $this->raConfig['raSrchConfig'] );
        $this->oList = new KeyframeUIWidget_List( $this->oComp, $this->raConfig['raListConfig'] );
        $this->oForm = new KeyframeUIWidget_Form( $this->oComp, $this->raConfig['raFormConfig'] );

        $this->oComp->Start();    // call this after the widgets are registered
    }

    function DrawStyle()
    /*******************
        Return <style> for all widgets
     */
    {
        return( $this->oList->Style() );    // add styles for search control
    }

    function DrawList()
    {
        $oViewWindow = new SEEDUIComponent_ViewWindow( $this->oComp, ['bEnableKeys'=>true] );

        $raListParms = [          // variables that might be computed or altered during state computation
//            'iViewOffset' => $this->oComp->Get_iWindowOffset(),
//            'nViewSize' => $oView->GetNumRows()
        ];

        $sList = $this->oList->ListDrawInteractive( $oViewWindow, $raListParms );

        return( $sList );
    }

    function DrawForm()
    {
        return( $this->oForm->Draw() );
    }

    function DrawSearch()
    {
        return( "<div style='padding:15px'>".$this->oSrch->Draw()."</div>" );   // put this padding in the search control css (see DrawStyle method)
    }

    /* Call this from ContentDraw() to implement a ListForm with New and Delete buttons, with Messages between them
     */
    function ContentDraw_NewDelete() { return($this->ContentDraw_Vert_NewDelete()); }  // deprecate

    function ContentDraw_Vert_NewDelete()
    {
        $cid = $this->oComp->Cid();

        $s = $this->DrawStyle()
           ."<style>
             .content-upper-section  { }
             .content-lower-section  { border:1px solid #777; padding:15px; }
             .content-form-container { width:100%;padding:20px;border:2px solid #999;clear:both }
             </style>"
           ."<div class='content-upper-section'>{$this->DrawList()}</div>"
           ."<div class='content-lower-section'>
                 {$this->Buttons_NewDeleteMsg()}
                 <div class='content-form-container'>{$this->DrawForm()}</div>
             </div>";

        return( $s );
    }

    function ContentDraw_Horz_NewDelete()
    {
        $cid = $this->oComp->Cid();

        $s = $this->DrawStyle()
           ."<style>
             .content-left-section  { }
             .content-right-section  { border:1px solid #777; padding:15px; }
             .content-form-container { width:100%;padding:20px;border:2px solid #999;clear:both }
             </style>
             <div class='container-fluid'><div class='row'>
                 <div class='col-md-6'>{$this->DrawList()}</div>
                 <div class='col-md-6 content-right-section'>
                     {$this->Buttons_NewDeleteMsg()}
                     <div class='content-form-container'>{$this->DrawForm()}</div>
                 </div>
             </div></div>";

        return( $s );
    }


    function Buttons_NewDeleteMsg()
    {
        $sMessages = "";
        if( ($msg = $this->oComp->oUI->GetUserMsg()) )  $sMessages .= "<div class='alert alert-success'>$msg</div>";
        if( ($msg = $this->oComp->oUI->GetErrMsg()) )   $sMessages .= "<div class='alert alert-danger'>$msg</div>";

        $s = "<style>
             .content-button-new     { margin-bottom:5px; float:left; width:10%; clear:both; }
             .content-messages       { float:left; width:80%; }
             .content-button-del     { margin-bottom:5px; float:right; width:10%; text-align:right; }
             </style>
             <div>
                 <div class='content-button-new'>{$this->oComp->ButtonNew()}</div>
                 <div class='content-messages'>{$sMessages}</div>
                 <div class='content-button-del'>{$this->oComp->ButtonDelete()}</div>
             </div>";
        return( $s );
    }

    function GetConfigTemplate( $raParms )
    /*************************************
        __construct takes raConfig to define component UI. This returns an raConfig with default behaviour, so you only have to specify the novel parts.

        Required:
            sessNamespace = ns for session vars
            cid           = for the component's form
            kfrel         = the component's relation

        Typically:
            raListConfig_cols    = labels and kfrel cols in list widget
            raSrchConfig_filters = filter defs for search control; often the same as raListConfig_cols
     */
    {
        $raConfig = [
                'sessNamespace' => $raParms['sessNamespace'],
                'cid'           => $raParms['cid'],
                'kfrel'         => $raParms['kfrel'],

                // derived class must provide list cols, may optionally override ListRowTranslate
                'raListConfig' => [
                    'cols'           => @$raParms['raListConfig_cols'] ?: [['label'=>'_replace_', 'col'=>'_me_']],
                    'fnRowTranslate' => [$this,'ListRowTranslate'],
                    'bUse_key'       => true,     // probably makes sense for KeyFrameUI to do this by default
                ],

                // derived class must provide search control filters, which sometimes can be simply a copy of the list cols array
                'raSrchConfig' => ['filters'=> @$raParms['raSrchConfig_filters'] ?: [['label'=>'_replace_', 'col'=>'_me_']] ],

                // derived class usually provides a form template in fnExpandTemplate format; if not, remove this and replace with another format e.g. sTemplate, fnTemplate
                'raFormConfig' => ['fnExpandTemplate'=>[$this,'FormTemplate']],

                // derived class may optionally override these methods
                'KFCompParms' => ['raSEEDFormParms'=>['DSParms'=>['fn_DSPreStore'=> [$this,'PreStore'],
                                                                  'fn_DSPreOp'   => [$this,'PreOp']]]],
        ];

        return($raConfig);
    }

    /* These are not called directly (i.e. their derived implementations are not called by any base code), but methods with this interface are referenced in raConfig.
     * raConfig can reference the implementations of these methods, or any other methods with the same interface.
     */
    function ListRowTranslate( $raRow )                    { return( $raRow ); }    // override to alter list values (only affects display)
    function PreStore( Keyframe_DataStore $oDS )           { return( true ); }      // override to validate/alter values before save; return false to cancel save
    function PreOp( Keyframe_DataStore $oDS, string $op )  { return( false ); }     // override to validate/alter values before op; return false to cancel op
    function FormTemplate( SEEDCoreForm $oForm )           { return( "" ); }        // override to draw the Form
}
