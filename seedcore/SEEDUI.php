<?php

/* SEEDUI.php
 *
 * Copyright (c) 2013-2020 Seeds of Diversity Canada
 *
 * Classes that manage control parms for forms, UI components, and SEEDForms.
 *
 * SEEDUI
 *     Knows the list of Components registered in the UI.
 *     Stores persistent UI parms for all Components to simplify storage (derived classes can use e.g. session vars)
 *
 * SEEDUI_Session
 *     A convenient derivation of SEEDUI that stores UI parms in a session namespace
 *
 * SEEDUIComponent( SEEDUI )
 *     Knows about a View on an abstract data relation, and uses a SEEDForm to update row(s) in the View.
 *     Knows about the current state of UI variables (current row, window size, etc).
 *     Non-base variables are defined through an initialization method, not usually a derived class.
 *     Derived classes provide methods for reading and writing. Derived SEEDForm classes are created by factory to facilitate updating rows.
 *
 * SEEDUIWidget*( SEEDUIComponent )
 *     Draw UI controls such as lists, forms, search boxes.
 *     Obtain data by requesting it from Component. If a Widget needs a particular data format or datastore, it can use a specialized
 *     Component method to request it. You just have to make a Component that has the required method.
 *     Use SEEDUI to create links and buttons that affect the UI state.
 *     Influence the SEEDUIComponent parameters when the View is computed.
 *
 * After all widgets are constructed:
 *      SEEDUIComponent->Update() processes any form submission(s), row deletions, etc.
 *      Each widget->Init() evaluates current state via SEEDUI variables and sets view conditions on SEEDUIComponent.
 *      SEEDUIComponent->GetView() or ->GetWindow() uses those conditions to fetch required data.
 *      Each widget->Draw() requests data from component and/or SEEDUI to draw its widget.
 *
 *
 * 1) When a Widget is constructed it Registers its uiparms with SEEDUI, which then loads them from _REQUEST.  (Also indicate which should be propagated/stored?)
 * 2) The Widget also Registers itself with the Component.
 * 3) When all Widgets are constructed, call Component::Start.
 *    That calls each Widget with Init1, providing SEEDUI's lists of current uiparms and previous uiparms. The base SEEDUI has an empty array for previous uiparms,
 *    but derived classes should store them somewhere. Each Widget looks at the current and previous uiparms to determine whether some UI state should change as a result.
 *    It returns an array of UI state change advisories. array() means no changes.
 * 4) The Component collects all unique UI state change advisories and calls each Widget with Init2 and the list of advisories. Each Widget responds to advisories by
 *    telling SEEDUI to change uiparms accordingly.
 * 5) Then the Component calls each Widget with Init3 to obtain an array of sql filters corresponding to view conditions based on UI parms.
 * 6) The Component fetches the current row, formulates the sql query for the View, and fetches a Window.
 * 7) When you call the Draw method of a Widget, it will give you the correct html.
 */

include_once( "SEEDUIWidgets.php" );


class SEEDUI
/***********
 */
{
    protected $raConfig;
    protected $lang;       // used for default button labels
    protected $raComps = array();

    /* UIParms are stored this way for extensibility. e.g. you could add a field to signify persistence
     * Each row contains the ui parms for a Component:
     *
     * array( cidA => array( simple-name1 => array( 'v'=>current-value, 'http'=>'sfAp_name', extensible-thing=>some-value ),
     *                       simple-name2 => ... )
     *        cidB => array( simple-name1 => ...
     *
     * Derived classes can add arbitrary parms and the base class can iterate to manage them
     */
    private $raUIParms = array();

    function __construct( $raConfig = array() )
    {
        $this->raConfig = $raConfig;
        $this->lang = @$raConfig['lang'] == 'FR' ?: "EN";

        /* Set default attrs for links and forms
         */
// see SEEDAppBase::PathToSelf() re the use of HSC
// ideally this should be using PathToSelf() anyway
        foreach( array( 'sListUrlPage'   => SEEDCore_HSC($_SERVER['PHP_SELF']),
                        'sListUrlTarget' => "_top",
                        'sFormAction'    => SEEDCore_HSC($_SERVER['PHP_SELF']),
                        'sFormMethod'    => "post",
                        'sFormTarget'    => "_top" )
                 as $k => $v )
        {
            if( empty($this->raConfig[$k]) ) $this->raConfig[$k] = $v;
        }
    }

    public function Config( $k )        { return( @$this->raConfig[$k] ); }

    public function RegisterComponent( SEEDUIComponent $oComp )
    {
        $this->raComps[] = $oComp;
    }

    /***************************************
        UIParm storage: Override these five functions to implement alternate UIParm storage in e.g. session vars
     */
    public function GetUIParm( $cid, $name )
    {
        return( @$this->raUIParms[$cid][$name]['v'] );
    }
    public function SetUIParm( $cid, $name, $v )
    {
        if( in_array( substr($name,0,1), array('i','k','n') ) )  $v = intval($v);      // force these types to integers
        $this->raUIParms[$cid][$name]['v'] = $v;
    }
    public function ExistsUIParm( $cid, $name )
    {
        return( isset($this->raUIParms[$cid][$name]) );
    }
    public function GetUIParmsList( $cid )
    {
        $raOut = array();
        if( isset($this->raUIParms[$cid]) ) {
            foreach( $this->raUIParms[$cid] as $name => $ra ) {
                $raOut[$name] = $ra['v'];
            }
        }
        return( $raOut );
    }
    /* Override the functions above to implement alternate UIParm storage
     ***************************************/


    public function RegisterUIParm( $cid, $name, $def )
    /**************************************************
        A widget is saying that it wants a ui parm to be read from $_REQUEST and propagated by all links/forms in the UI.
        Within the SEEDUI* code the parm is known by the value $k, but as an http parm it is mapped to $ra['name'] as below.

        $ra = array( 'name'=>'sf[[cid]]ui_foo', 'v'=>initial-value )

        SEEDUIComponent::__construct can also take $raCompConfig['raUIParms'][parmname] to set a custom uiparm and/or
        set a value to a base uiparm. (That's a good way for derived classes to set uiparm values stored e.g. in session vars)

        If parms are found in _REQUEST those override all other sources.
     */
    {
        $this->raUIParms[$cid][$name] = $def;

        // Subst the cid within the parm's http name
        $httpname = $this->raUIParms[$cid][$name]['http'] = str_replace( "[[cid]]", $cid, $this->raUIParms[$cid][$name]['http'] );

        // If there is http input for this parm, get it now. Otherwise set the default value.
        // Use SetUIParm to benefit from its type logic, and in case it is overridden.
        if( isset($_REQUEST[$httpname]) ) {
            $this->SetUIParm( $cid, $name, $_REQUEST[$httpname] );
        } else if( !$this->ExistsUIParm( $cid, $name ) ) {
            // set the default (this is already there in the base class because we use the $def as storage)
            $this->SetUIParm( $cid, $name, @$def['v'] );
        }
    }


    /***************************************
        Compose links and hidden form elements that contain http names of uiparms
     */
    public function HRef( $cid, $ra = array(), $raUserParms = array() )    // userParms can be used by derived classes
    {
        return( "href='".$this->Link($cid, $ra, $raUserParms)."' target='".$this->raConfig['sListUrlTarget']."'" );
    }

    public function Link( $cid, $ra = array(), $raUserParms = array() )    // userParms can be used by derived classes
    {
        return( $this->raConfig['sListUrlPage']."?".$this->LinkParms($cid, $ra,$raUserParms) );
    }

    public function HiddenFormParms( $cid, $ra = array() )
    {
        $s = "";

        $ra = $this->translateParms( $cid, $ra );
        foreach( $ra as $k => $v ) {
            $s .= "<input type='hidden' id='$k' name='$k' value='".SEEDCore_HSC($v)."'/>";
        }
        return( $s );
    }

    protected function LinkParms( $cid, $ra = array(), $raUserParms = array() )    // userParms can be used by derived classes
    {
        return( SEEDCore_ParmsRA2URL( $this->translateParms($cid,$ra,$raUserParms) ) );
    }

    protected function translateParms( $cid, $raP, $raUserParms = array() )
    /**********************************************************************
        Map the internal parm names e.g. kCurr to http parm names e.g. sfAui_k

        raUserParms is available for derived classes to pass special parameters - it is propagated through Link, HRef, LinkParms, etc

        // Derived class could do this:
        $raOut = array();
        $ra = parent::translateParms($ra);
        foreach( $ra as $k => $v ) {
            if( $k == 'foo' )
                $raOut['bar'] = $v;
            else
                $raOut[$k] = $v;
        }
        return( $raOut );
     */
    {
        if( !isset($this->raUIParms[$cid]) )  goto done;

        // You almost always want kCurr so we add it to the array every time so you don't have to
        if( !isset($raP['kCurr']) )  $raP['kCurr'] = $this->GetUIParm( $cid, 'kCurr' );

        // Map every known parm name to its http name
        foreach( $this->raUIParms[$cid] as $k => $def ) {
            if( isset($raP[$k]) ) {
                $raP[$def['http']] = $raP[$k];
                unset( $raP[$k] );
            }
        }

        done:
        return( $raP );
    }
}

class SEEDUI_Session extends SEEDUI
/*******************
    SEEDUI exposes current uiParms and previous uiParms to each Widget so they can decide what to do wrt those changes.
    However it has no mechanism for storing previous uiParms. This derivation uses a session namespace to store uiParms between page loads.
 */
{
    private $oSVA;

    function __construct( SEEDSession $sess, $sApplication )
    {
        parent::__construct();
        $this->oSVA = new SEEDSessionVarAccessor( $sess, 'seedui-'.$sApplication );
    }

    // override SEEDUI methods to implement session-based uiParm storage
    function GetUIParm( $cid, $name )      { return( $this->oSVA->VarGet( "$cid|$name" ) ); }
    function SetUIParm( $cid, $name, $v )  { $this->oSVA->VarSet( "$cid|$name", $v ); }
    function ExistsUIParm( $cid, $name )   { return( $this->oSVA->VarIsSet( "$cid|$name" ) ); }
    function GetUIParmsList( $cid )
    {
        $ra = $this->oSVA->VarGetAllRA( "$cid|" );
        $raOut = [];
        foreach( $ra as $k => $v ) {
            $raOut[substr($k,strlen("$cid|"))] = $v;  // remove the $cid| prefix from the keys
        }
        return( $raOut );
    }
}


class SEEDUIComponent
/********************
    A UI Component has a View on a data relation, and a SEEDForm that can act on the row(s).

    Data, and the data relation, are provided by a derived class.
    The SEEDForm is created with a factory, because the derived component will probably want a SEEDForm that knows about its datasource.

    This class renders links and buttons that propagate control parms in a consistent manner.
    Any typical UI system should be able to implement links and buttons by overriding minor parts of this.

    Widgets call:
        SetViewParms()   : impose conditions on the View
        GetView()        : fetch all the data of the View
        GetWindow()      : fetch the data of a defined Window on the View
        RegisterWidget() : the component knows about all of the widgets that use it so it can notify them when the View changes

    Conventional parms include:
        sf[[cid]]ui_k      = the key of the current element of component cid (could be a list, a form, a drop-down, etc)
        sf[[cid]]ui_i      = the view-offset of the current element of component cid (e.g. 0-based placement in a list)
//        sf[[cid]]ui_bNew   = a new element has been ordered for component cid
//        sf[[cid]]ui_kDel   = command to delete an element of component cid
        sf[[cid]]ui_iWO    = the window offset of component cid
        sf[[cid]]ui_nWS    = the window size of component cid
 */
{
    public    $oUI;
    protected $cid;
    protected $raCompConfig = array();
    public    $oForm;                   // widgets use this to draw controls

//    public $kCurr = 0;     // the key of the current element in some list or table
//    private $iCurr = 0;    // another way of representing the current item in a list or table
//    public $bNew = false;  // true if a new element is being made
//    public $kDel = 0;      // non-zero for an element being deleted

    protected $sSqlCond = "";           // sql condition for the View, built here just to be nice to the derived class that implements db access

    private   $raWidgets = array();     // every widget registers itself with the component for the initialization routine

    private   $raUIParmsListOld = [];   // a snapshot of the uiParms before initialization, so session-based uiParms can be compared

    function __construct( SEEDUI $oUI, $cid = 'A', $raCompConfig = array() )
    {
        $this->oUI = $oUI;
        $this->cid = $cid;
        $this->raCompConfig = $raCompConfig;

        // the SEEDUI keeps a list of all the components
        $oUI->RegisterComponent( $this );

        $this->oForm = $this->factory_SEEDForm( $this->Cid(),
                                                isset($raCompConfig['raSEEDFormParms']) ? $raCompConfig['raSEEDFormParms'] : array() );

        // Before initializing the uiParms, save a copy of the last page's uiParms so we can compare them.
        // If this method is not implemented, the array will be blank.
        $this->raUIParmsListOld = $this->oUI->GetUIParmsList( $this->cid );

        /* Initialize the uiParms. It is done one by one through a public method so derived SEEDUIComponents and widgets can do this too.
         * The constructor raConfig can contain initial values and alternate http names
         * e.g. $raConfig['raUIParms']['iCurr'] = array( 'name'=>'xfui[[cid]]_iHere','v'=>1234 )
         */
        $raUIParms = array( 'kCurr'         => array( 'http'=>'sf[[cid]]ui_k',    'v'=>0 ),
                            'iCurr'         => array( 'http'=>'sf[[cid]]ui_i',    'v'=>0 ),
//                            'bNew'          => array( 'http'=>'sf[[cid]]ui_bNew', 'v'=>0 ),
//                            'kDel'          => array( 'http'=>'sf[[cid]]ui_kDel', 'v'=>0 ),
                            'iWindowOffset' => array( 'http'=>'sf[[cid]]ui_iWO',  'v'=>0 ),
                            'nWindowSize'   => array( 'http'=>'sf[[cid]]ui_nWS',  'v'=>0 ) );
        if( isset($raCompConfig['raUIParms']) ) {
            $raUIParms = array_merge( $raUIParms, $raCompConfig['raUIParms'] );
        }
        foreach( $raUIParms as $k => $ra )
        {
            $this->oUI->RegisterUIParm( $cid, $k, $ra );
        }
    }

    public function Cid()                   { return( $this->cid ); }

    public function Get_kCurr()             { return( $this->GetUIParmInt('kCurr') ); }
    public function Get_iCurr()             { return( $this->GetUIParmInt('iCurr') ); }
    public function Set_kCurr( $k )         { $this->SetUIParmInt('kCurr', $k ); }
    public function Set_iCurr( $i )         { $this->SetUIParmInt('iCurr', $i ); }

    public function Get_iWindowOffset()     { return( $this->GetUIParmInt('iWindowOffset') ); }
    public function Get_nWindowSize()       { return( $this->GetUIParmInt('nWindowSize') ); }
    public function Set_iWindowOffset( $i ) { $this->SetUIParmInt('iWindowOffset', $i ); }
    public function Set_nWindowSize( $i )   { $this->SetUIParmInt('nWindowSize', $i ); }

//    public function Get_bNew()          { return( $this->GetUIParm('bNew') ); }
//    public function Get_kDel()          { return( $this->GetUIParm('kDel') ); }
//    public function Set_bNew( $b )          { $this->SetUIParm('bNew', $b ); }
//    public function Set_kDel( $k )          { $this->SetUIParm('kDel', $k ); }

    public function GetUIParm( $k )         { return( $this->oUI->GetUIParm( $this->cid, $k ) ); }
    public function GetUIParmInt( $k )      { return( intval($this->GetUIParm($k)) ); }
    public function SetUIParm( $k, $v )     { $this->oUI->SetUIParm( $this->cid, $k, $v ); }
    public function SetUIParmInt( $k, $v )  { return( $this->SetUIParm($k, intval($v)) ); }


    protected function factory_SEEDForm( $cid, $raSFParms )   // Override if the SEEDForm is a derived class
    {
        return( new SEEDCoreForm( $cid, $raSFParms ) );
    }


    function Update()
    /****************
        Call this before reading any data or drawing any widgets
     */
    {
        $this->oForm->Update();

        /* Since the Form->Update() can insert a new row, fix up the kCurr to reflect that, and tell the derived component about it.
         * We defensively check here that the derived oForm has a GetKey, but you should always have that if you're using a SEEDUIComponent.
         */
        if( method_exists($this->oForm,'GetKey') && ($k = $this->oForm->GetKey()) ) {
            $this->Set_kCurr( $k );
//            $this->SetKey( $k );                // this was $this->kfuiCurrRow->SetKey(  );
        }
/*
        if( $this->oForm->ControlGet('newrow') == 1 ) {
            // command has been issued to create a new row
            $this->bNewRow = true;
            $this->kfuiCurrRow->SetKey( 0 ); // clear the curr row; this also clears the kfr in the oForm
        }

        if( $this->oForm->ControlGet('deleterow') == 1 ) {
            // command has been issued to delete the current row
            //
            // This can be accomplished with sfAd=1 (would be handled by the oForm->Update above) but it's harder to
            // detect that here so that the UI can be reset to reflect the missing row
            if( $this->oForm->GetKey() && ($kfr = $this->kfuiCurrRow->GetKFR()) ) {
                $bDelOk = isset($this->raCompConfig['fnPreDelete']) ? call_user_func( $this->raCompConfig['fnPreDelete'], $kfr ) : true;
                //var_dump($bDelOk);
                if( $bDelOk ) {
                //    $kfr->StatusSet( KFRECORD_STATUS_DELETED );
                //    $kfr->PutDBRow();
                //    $this->kfuiCurrRow->SetKey( 0 ); // clear the curr row; this also clears the kfr in the oForm
                }
            }
        }
*/

/*
SEEDUI should pick these up

sortcol1,sortcol2,sortcolN
sortup1,sortup2,sortupN
status
groupcol
*/
    }

    function Start()
    /***************
        Call this after Update(), and after Widgets are constructed, to initialize the widgets and the View.

        The order of initializations takes into account:
            - Some parm transitions cause significant UI state changes (e.g. changed search parameters cause a VIEW_RESET)
            - Those UI changes might possibly change view parameters.
            - View parameters are needed before computing default kCurr / finding the window that contains kCurr

        Init1 : notify widgets of new and old ui parms. They respond with state changes. e.g. VIEW_RESET when search parms change
        Init2 : notify widgets of state changes. They modify uiparms appropriately. e.g. List sets kCurr=0,iWO=0.
        Init3 : request view parameters from each widget. e.g. Search returns its sql condition.
        Init4 : widgets can read view data and reset uiparms as necessary. e.g. List sets kCurr to first item in list.
                No widget may rely on those uiparms until the next step. e.g. List can change your kCurr if it comes later in the Init round.
        Init5 : ui parms are final. Widgets may initialize internal states.
     */
    {
        /* Init1: Notify every widget of the new and old ui parms. They return arrays of state change advisories.
         */
        $raAdvisories = [];
        foreach( $this->raWidgets as $ra ) {
            $raAdvisories += $ra['oWidget']->Init1_NotifyUIParms( $this->raUIParmsListOld, // $this->oUI->GetUIParmsListOld($this->cid),
                                                                  $this->oUI->GetUIParmsList($this->cid) );
        }
        $raAdvisories = array_unique($raAdvisories);

        /* Init2: Send advisories (even if empty) to every widget so they can alter uiparms wrt state changes.
         */
        foreach( $this->raWidgets as $ra ) {
            $ra['oWidget']->Init2_NotifyUIStateChanges( $raAdvisories );
        }

        /* Init3: Request view parameters from every widget. (Note that the window/kCurr/iCurr state is not necessarily stable yet)
         */
        $raSqlCond = [];
        foreach( $this->raWidgets as $ra ) {
            list($cond) = $ra['oWidget']->Init3_RequestViewParameters();
            if( $cond ) {
                $raSqlCond[] = "($cond)";
            }
        }
        // although this base class doesn't do sql, derived classes can use this
        $this->sSqlCond = implode( " AND ", $raSqlCond );

        /* Init4: Widgets set up their view/window data and alter window/kCurr/iCurr uiparms as necessary.
         */
        foreach( $this->raWidgets as $ra ) {
            $ra['oWidget']->Init4_EstablishViewState();
        }

        /* Init5: Widgets set up their internal state now that uiparms are unchangeable
         */
        foreach( $this->raWidgets as $ra ) {
            $ra['oWidget']->Init5_UIStateFinalized();
        }
    }

    function RegisterWidget( SEEDUIWidget_Base $o, $raUIParms )
    /**********************************************************
        Every widget calls this with a reference to itself, so the component can do the initialization routine with all widgets.
        raUIParms is the list of uiparms for the given widget, that SEEDUI should propagate in links/forms of other widgets.
     */
    {
        $this->raWidgets[] = array( 'oWidget'=>$o, 'uiparmsdef'=>$raUIParms );
        foreach( $raUIParms as $name => $def ) {
            $this->oUI->RegisterUIParm( $this->cid, $name, $def );
        }
    }


    function DrawWidgetInForm( $sControls, $oWidget, $raParms = array() )
    /********************************************************************
        Draw the given input control string in a form using SEEDUI's form config.
        Add hidden parms for all uiparms except for the given widget (those uiparms are assumed to be in the controls string).

        You can prevent uiparms from being propagated (e.g. because they are already encoded in sControls) by naming them in raParms['omitUIParms'].
        To make the array format similar to the HRef and Link methods, please use array( 'uiparm1'=>dummy, 'uiparm2'=>dummy ) instead of listing the uiparms as values.
     */
    {
        $raOtherUIParms = $this->getUIParmsFromOtherWidgets( $oWidget, @$raParms['omitUIParms'] ?: array() );

        $sAttr = @$raParms['bInline'] ? "style='display:inline'" : "";
        $sOnSubmit = @$raParms['onSubmit'] ? " onSubmit='{$raParms['onSubmit']}'" : "";

        $s = "<form $sAttr action='".$this->oUI->Config('sFormAction')."' method='".$this->oUI->Config('sFormMethod')."'"
            .(($p = $this->oUI->Config('sFormTarget')) ? " target='$p'" : "")
            .$sOnSubmit
            .">"
            .$sControls
            .$this->oUI->HiddenFormParms( $this->cid, $raOtherUIParms )
            ."</form>";

         return( $s );
    }

    function HRefForWidget( SEEDUIWidget_Base $oWidget, $raUIParms )
    /***************************************************************
        Make a href with the given uiparms, plus the uiparms for other widgets.
        i.e. raUIParms are assumed to be all the uiparms for the given widget, plus any base uiparms that need to change, if the link is clicked.
     */
    {
        $raUIParms = array_merge( $raUIParms, $this->getUIParmsFromOtherWidgets( $oWidget, $raUIParms ) );
        return( $this->oUI->HRef( $this->cid, $raUIParms ) );
    }

    function LinkForWidget( SEEDUIWidget_Base $oWidget, $raUIParms )
    /***************************************************************
        Make a link with the given uiparms, plus the uiparms for other widgets
     */
    {
        $raUIParms = array_merge( $raUIParms, $this->getUIParmsFromOtherWidgets( $oWidget, $raUIParms ) );
        return( $this->oUI->Link( $this->cid, $raUIParms ) );
    }

    private function getUIParmsFromOtherWidgets( $oWidget, $raMyUIParms )
    {
        $raOtherUIParms = array();
        if( $oWidget ) {
            foreach( $this->raWidgets as $ra ) {
                if( $ra['oWidget'] !== $oWidget ) {     // matches the actual instance of the object, not just comparing content
                    foreach( $ra['uiparmsdef'] as $k => $dummy ) {
                        $raOtherUIParms[$k] = $this->GetUIParm($k);
                    }
                }
            }
        }

        // Add base uiparms unless they are specifically named in the given uiparms (meaning they are given by the caller with new values)
        foreach( array('kCurr','iCurr','iWindowOffset','nWindowSize') as $k ) {
            if( !isset($raMyUIParms[$k]) )  $raOtherUIParms[$k] = $this->GetUIParm($k);
        }

        return( $raOtherUIParms );
    }


    public function HiddenKCurr()
    {
        // go through HiddenFormParms in case a derived class overrides it
        return( $this->oUI->HiddenFormParms( $this->cid, array( 'kCurr' => $this->Get_kCurr() ) ) );
    }

    public function HiddenFormUIParms( $ra )
    {
        $raUI = array();
        foreach( $ra as $k ) {
            $raUI[$k] = $this->GetUIParm( $k );
        }
        return( $this->oUI->HiddenFormParms( $this->cid, $raUI ) );
    }

    public function Button( $sLabel = "", $raParms = array() )
    /*********************************************************
        Makes a single-button form with parms specified by raPropagate
     */
    {
        if( !$sLabel )  $sLabel = "button";

        // propagate these parms when the button is clicked
        $raPropagate = @$raParms['raPropagate'] ?: array();

        return( $this->FormDraw( "<input type='submit' value='".SEEDCore_HSC($sLabel)."'/>", $raPropagate, $raParms ) );
    }

    public function ButtonNew( $sLabel = "", $raParms = array() )
    /************************************************************
        Makes a button with bNew=1,kCurr={kCurr} + your other parms
        You can override the kCurr behaviour by specifying kCurr in raPropagate
     */
    {
        if( !$sLabel )  $sLabel = ($this->lang == 'FR' ? "Ajouter" : "New");

        // propagate these parms when the button is clicked
        $raPropagate = @$raParms['raPropagate'] ?: array();
        $raPropagate = array_merge( /*array('kCurr'=>$this->Get_kCurr()),*/ $raPropagate, array('bNew' => 1) );

        return( $this->FormDraw( "<INPUT type='submit' value='$sLabel'/>", $raPropagate, $raParms ) );
    }

    public function ButtonDelete( $kDelete = 0, $sLabel = "", $raParms = array() )
    /*****************************************************************************
        Makes a button with kDel={kDelete},kCurr={kCurr} + your other parms
        You can override the kCurr behaviour by specifying kCurr in otherParms

        kDelete==0 means kDelete=$this->Get_kCurr()
     */
    {
        // delete current row unless another row is specified
        if( !$kDelete && !($kDelete = $this->Get_kCurr()) )  return( "" );

        if( !$sLabel )  $sLabel = ($this->lang == 'FR' ? "Supprimer" : "Delete");

        // propagate these parms when the button is clicked
        $raPropagate = @$raParms['raPropagate'] ?: array();
        $raPropagate = array_merge( array('kCurr'=>$this->Get_kCurr()), $raPropagate, array('bDel' => $kDelete) );

        return( $this->FormDraw( "<INPUT type='submit' value='$sLabel'/>", $raPropagate, $raParms ) );
    }

    public function ButtonEdit( $kEdit, $sLabel = "", $raParms = array() )
    /*********************************************************************
        Makes a button with kCurr={kEdit} + your other parms

        All this really does is to make the kEdit row current (your UI has to interpret that as an edit).
        If your UI differentiates between choosing a record and editing it, you'll have to send a control parm here too.
     */
    {
        if( !$kEdit )  return( "" );

        if( !$sLabel )  $sLabel = ($this->lang == 'FR' ? "Modifier" : "Edit");

        // propagate these parms when the button is clicked
        $raPropagate = @$raParms['raPropagate'] ?: array();
        $raPropagate = array_merge( $raPropagate, array('kCurr' => $kEdit) );

        return( $this->FormDraw( "<INPUT type='submit' value='$sLabel'/>", $raPropagate, $raParms ) );
    }

    public function LinkEdit( $kEdit, $sLabel = "", $raParms = array() )
    /*******************************************************************
        Same as ButtonEdit but draws a link
     */
    {
        if( !$kEdit )  return( "" );

        if( !$sLabel )  $sLabel = ($this->lang == 'FR' ? "Modifier" : "Edit");

        // propagate these parms when the button is clicked
        $raPropagate = @$raParms['raPropagate'] ?: array();
        $raPropagate = array_merge( $raPropagate, array('kCurr' => $kEdit) );

        return( "html not implemented yet" );
    }


    protected function FormDraw( $sControls, $raHidden = array(), $raParms = array() )
    /*********************************************************************************
        Draw a set of controls in a <form> that propagates the given parms.
     */
    {
        $sAttr = @$raParms['bInline'] ? "style='display:inline'" : "";
        $sOnSubmit = @$raParms['onSubmit'] ? " onSubmit='{$raParms['onSubmit']}'" : "";

        $s = "<form $sAttr action='{$this->raConfig['sFormAction']}' method='{$this->raConfig['sFormMethod']}'"
            .(empty($this->raConfig['sFormTarget']) ? "" : " target='{$this->raConfig['sFormTarget']}'")
            .$sOnSubmit
            .">"
            .$sControls
            .$this->oUI->HiddenFormParms( $this->cid, $raHidden )
            ."</form>";

         return( $s );
    }

    function FetchViewSlice( $iViewSliceOffset, $nViewSliceSize )
    /************************************************************
        If a widget needs a slice of a view it can call here to get it.

        Override to fetch view data from a data source.
     */
    {
        $raViewData = [];
        $iReturnedViewSliceOffset = 0;  // returned data is a slice starting at this offset of the view (can be lower number than requested if that's convenient)
        $nViewSize = 0;                 // total size of the view (in case we didn't have it yet)
        return( [$raViewData,$iReturnedViewSliceOffset,$nViewSize] );
    }
}

class SEEDUIComponent_ViewWindow
/*******************************
    Encapsulates the view and window computation.

    Call this after SEEDUIComponent::Start()

    iWindowOffset, nWindowSize, kCurr and/or iCurr should already be set in the uiParms.
    This takes a view or view slice and figures out:
        1.  where the window actually lies within the view (iWindowOffset is corrected if the window extends past the view)
        2a. where iCurr actually is wrt the window.
        2b. if keys enabled, iCurr is ignored and kCurr is used to find iCurr in the view
        3.  how to navigate the window (the target of a page-up or page-down, find selection, etc)

    View data can be provided in the constructor, or fetched via a derivation of SEEDUIComponent::FetchViewData().
    A full view or a view slice is supported by the same parameters: a full view is iViewRowsOffset=0, nViewSize==count(raViewRows)

    raConfig in __construct:
        bEnableKeys      : use kCurr to find the current row; otherwise use iCurr (but this is impossible in an interactive list)
        iViewSliceOffset : the origin-0 index of the first row in the slice, relative to the whole view
                           if view data given in the constructor, this offset refers to that data
                           if null given for view data, this is passed to SEEDUIComponent::FetchViewData() which must return that slice
        nViewSize        : the total view size, regardless of the size of the data provided or view slice needed (used for navigation metrics)
 */
{
    private $oComp;
    private $bEnableKeys;
    private $raViewRows = null;
    private $iViewRowsOffset = 0;          // origin-0 index of the first row in raViewRows relative to the whole view
    private $nViewSize = 0;                 // size of whole view, possibly larger than count(raViewRows)
    private $bCurrentRowOutsideViewSlice = false;   // true if raViewRows is a partial view slice and kCurr is given but not found in the slice

    function __construct( SEEDUIComponent $oComp, $raConfig = [] )
    {
        $this->oComp = $oComp;
        $this->bEnableKeys = @$raConfig['bEnableKeys'] ?: false;
    }

    function IsEnableKeys()             { return( $this->bEnableKeys ); }

    function IsWindowSmallerThanView()  { return( $this->rowsOutsideWindow() > 0 ); }

    function IsCurrRowOutsideWindow()
    {
        $iCurr = $this->oComp->Get_iCurr();
        $iWO   = $this->oComp->Get_iWindowOffset();

        if( $this->bCurrentRowOutsideViewSlice )  return( true );   // if it's outside of the view slice it's outside of the window
        if( $iCurr < 0 )                          return( false );   // no current row

        return( $iCurr < $iWO ||                                   // current row above window
                $iCurr >= $iWO + $this->oComp->Get_nWindowSize()   // current row below window
        );
    }

    function RowsAboveWindow()
    {
        return( $this->oComp->Get_iWindowOffset() );
    }
    function RowsBelowWindow()
    {
        return( $this->IsWindowSmallerThanView()
                    ? SEEDCore_Bound( $this->rowsOutsideWindow() - $this->oComp->Get_iWindowOffset(), 0 )
                    : 0 );
    }
    private function rowsOutsideWindow()
    /***********************************
        This is the same number as RowsAboveWindow() + RowsBelowWindow().
        It is also the maximum possible value for iWindowOffset.
     */
    {
        return( SEEDCore_Bound($this->nViewSize - $this->oComp->Get_nWindowSize(), 0) );
    }

    function SetViewSlice( $raViewRows, $raParms )
    {
        $this->raViewRows = $raViewRows;
        $this->iViewRowsOffset = @$raParms['iViewSliceOffset'] ?: 0;
        $this->nViewSize       = @$raParms['nViewSize'] ?: 0;
    }

    private function _setViewSlice( $rows, $iOffset, $nViewSize )
    {
        // Store this, but it's independent of everything below
        $this->nViewSize = $nViewSize;

        // Add/prepend/append/overlay new rows into raViewRows
        $raJoin = [];
        if( !$this->raViewRows || $iOffset <= $this->iViewRowsOffset ) {      /* the new rows prepend or overlap the start of raViewRows, or raViewRows==[] */
            // Add the new rows first
            $newOffset = $iOffset;
            $raJoin = $rows;

            // If there is a gap between the new slice and the old slice, put in some gap rows.
            // This should never happen, but it would be bad if it did and we didn't pad the gap.
            for( $i = $newOffset + count($raJoin); $i < $this->iViewRowsOffset; ++$i ) {
                $raJoin[] = [];
            }

            // If any part of the old slice extends past the new slice, add that part
            for( $i = $newOffset + count($raJoin); $this->raViewRows && ($i < $this->iViewRowsOffset + count($this->raViewRows)); ++$i ) {
                $raJoin[] = $this->raViewRows[$i - $this->iViewRowsOffset];
            }
        } else {                                        /* the new rows append or overlap the end of raViewRows */
            $newOffset = $this->iViewRowsOffset;
            // if any part of the old slice extends before the new slice, add that part first
            for( $i = $this->iViewRowsOffset; $i < min($iOffset, $this->iViewRowsOffset + count($this->raViewRows)); ++$i ) {
                $raJoin[] = $this->raViewRows[$i - $this->iViewRowsOffset];
            }

            // If there is a gap between the old slice and the old slice, put in some gap rows.
            // This should never happen, but it would be bad if it did and we didn't pad the gap.
            for( $i = $newOffset + count($raJoin); $i < $iOffset; ++$i ) {
                $raJoin[] = [];
            }

            // Add the new slice here
            foreach( $rows as $ra ) {
                $raJoin[] = $ra;
            }
        }

        $this->raViewRows = $raJoin;
        $this->iViewRowsOffset = $newOffset;
    }

    function GetRowData( $iViewRow, $bFirstTry = true )
    /**************************************************
        Return one view row. iViewRow is the origin-0 index in the view.  i.e. GetRowData( Get_iCurr() ) gets the currently selected row
     */
    {
        if( $this->isViewRowLoaded($iViewRow) ) {
            return( $this->raViewRows[$iViewRow - $this->iViewRowsOffset] );
        } else if( $bFirstTry ) {
            $this->GetViewData( $iViewRow, 1 );
            return( $this->GetRowData( $iViewRow, false ) );
        }
        return( [] );
    }

    function GetWindowData()
    /***********************
        return view rows contained in the window
     */
    {
        return( $this->GetViewData( $this->oComp->Get_iWindowOffset(), $this->oComp->Get_nWindowSize() ) );
    }

    function GetViewData( $iRowStart, $nRows )
    /*****************************************
        Return a View slice of nRows starting at origin-0 row iRowStart
     */
    {
        /* If the requested slice is already loaded, return it.
         */
        if( $this->isViewRowLoaded($iRowStart) && $this->isViewRowLoaded($iRowStart + $nRows -1) ) {
            goto done;
        }

        /* Fetch the requested slice using the component's derived object.
         * If your component doesn't support this method you have to use SetViewSlice() before calling here.
         */
        list($rows,$iVO,$nVS) = $this->oComp->FetchViewSlice( $iRowStart, $nRows );
        $this->_setViewSlice( $rows, $iVO, $nVS );

        done:
        $iOffsetOfRequestedSliceWithinLoadedRows = $iRowStart - $this->iViewRowsOffset;
        return( $this->raViewRows && ($iOffsetOfRequestedSliceWithinLoadedRows >=0)
                    ? array_slice($this->raViewRows, $iOffsetOfRequestedSliceWithinLoadedRows, $nRows)
                    : [] );
    }

    private function isViewRowLoaded( $iRow )
    /****************************************
        True if the origin-0 iRow of the View is loaded in $this->raViewRows[$iRow - $this->iViewRowsOffset]
     */
    {
        return( $this->raViewRows && ($iRow >= $this->iViewRowsOffset && $iRow < $this->iViewRowsOffset + count($this->raViewRows)) );
    }

    function IdealWindowOffset()
    /***************************
        To reposition the window so it includes the selected row, find the window offset that puts
        the row in the middle of the window, then adjust for boundaries.

        If the iCurr row is outside of the view slice, we can't search for it by key so we are relying on the system to
        propagate iCurr correctly since the last time we knew it.
     */
    {
        if( $this->oComp->Get_iCurr() < 0 ) return(0);   // no current row

        $offset = SEEDCore_Bound( $this->oComp->Get_iCurr() - intval($this->oComp->Get_nWindowSize()/2),
                                  0, $this->rowsOutsideWindow() );
        return( $offset );
    }

    function ScrollOffsets()
    /***********************
        The window offsets that would scroll the window to various places
     */
    {
        $ra = array();

//TODO: If you scroll an iLimited window down by some offset, then change iLimit to -1, you get a case where iOffset>0 but !bWindowLimited.
//      The following calculations are necessary to draw the scroll-up links, but with !bWindowLimited that doesn't happen
//      For now, implementations should set offset=0 whenever they dynamically set iLimit=-1

        $iWO = $this->oComp->Get_iWindowOffset();
        $nWS = $this->oComp->Get_nWindowSize();

        $ra['top']      = 0;
        $ra['bottom']   = $this->rowsOutsideWindow();   // maximum value for iWindowOffset
        $ra['up']       = SEEDCore_Bound( $iWO - 1,    0, $ra['bottom'] );
        $ra['down']     = SEEDCore_Bound( $iWO + 1,    0, $ra['bottom'] );
        $ra['pageup']   = SEEDCore_Bound( $iWO - $nWS, 0, $ra['bottom'] );
        $ra['pagedown'] = SEEDCore_Bound( $iWO + $nWS, 0, $ra['bottom'] );

        return( $ra );
    }


    function InitViewWindow()
    /************************
     * Call this when the View is fetchable, but window/iCurr/kCurr state is not stable yet.
     *
     * Ordinarily, kCurr/iCurr are in sync and iWO scrolls such that the selection may or may not be in the window.
     * The calling code will use ViewWindow to load only the ViewSlice corresponding to the window, showing it with or without
     * the current selection. That works for basic cases of row-selection and scrolling, but more information is needed for special cases.
     *
     * Here are several special cases to prepare the ViewWindow:
     *
     * 1) [e.g. Initialization or SearchControl parms change]
     *    After VIEW_RESET (or application starts) the selected row is unknown or possibly not in the new view.
     *    The solution is that the first row in the new view should be selected.
     *    If keys are enabled, kCurr should be initialized to raViewRows[(iCurr=0)]['_key'].
     *    Calling code must do the reset of kCurr=iCurr=0.
     *    The code below detects that kCurr=0 and loads the first view row to set kCurr.
     *
     * 2) [e.g. Sorting]
     *    When the view changes in such a way that the selected row is still in the view (not necessarily in the window) but at a different
     *    offset, we have no way to predict the new iCurr.
     *    If keys not enabled, the calling code should do a VIEW_RESET so sorting causes the selection to jump to the first row.
     *    If keys are enabled, the solution is to search the new view for kCurr in order to set iCurr.
     *    Calling code must set iCurr=-1.
     *    The code below detects iCurr=-1 and searches the view for kCurr in order to set iCurr.
     *
     * 3) [e.g. Sorting]
     *    When the view changes in such a way that the Window's content is still in the view (selected row might not be in the window)
     *    but not in the same position, nor contiguous, the window shouldn't really stay at the same offset.
     *    The most usual case is that the selected row is in the window and you'd like it to stay in the window after sorting.
     *    The solution is to reposition the window around the re-computed iCurr.
     *    If keys not enabled, this case will not happen because the above case will cause a VIEW_RESET anyway.
     *    The code below repositions the iWO after it re-computes iCurr as above.
     */
    {
        if( !$this->bEnableKeys )  goto done;       // everything below applies only when keys are enabled

        /* See case (1) above.
         * Initialize kCurr to the first row iCurr=0.
         * This also tries to work for cases where iCurr>0 but that probably doesn't happen?
         */
        if( !$this->oComp->Get_kCurr() && $this->oComp->Get_iCurr() >= 0 ) {
            if( ($raRow = $this->GetRowData( $this->oComp->Get_iCurr() )) ) {
                $this->oComp->Set_kCurr( @$raRow['_key'] ?: 0 );
            }

            /* MAYBE OBSOLETE
             * There was a weird case that made the view shrink smaller than iCurr. It might not happen anymore, but this was the fix.
             * It's necessary to put it here because nViewSize is not known until some view data is fetched, which might not happen
             * before the GetRowData() above.
             */
            if( $this->oComp->Get_iCurr() >= $this->nViewSize ) {
                $this->oComp->Set_iCurr(0);
                $this->oComp->Set_iWindowOffset(0);
                if( ($raRow = $this->GetRowData( $this->oComp->Get_iCurr() )) ) {
                    $this->oComp->Set_kCurr( @$raRow['_key'] ?: 0 );
                }
            }
        }


        /* See case (2) and (3) above.
         * Search for iCurr and reposition iWO after the view is sorted (or similarly reordered without losing kCurr).
         */
        if( $this->oComp->Get_kCurr() && $this->oComp->Get_iCurr() < 0 ) {
            // fetch 100 rows at a time
            for( $i = 0; $i < ($this->nViewSize ?: 1); ) {  // nViewSize is not known until after the first call to GetViewData so do at least once
                $rows = $this->GetViewData( $i, 100 );
                foreach( $rows as $ra ) {
                    if( @$ra['_key'] == $this->oComp->Get_kCurr() ) {
                        $this->oComp->Set_iCurr( $i );
                        goto doneSearch;
                    }
                    ++$i;
                }
            }
            doneSearch:

            // reposition iWO to include the new iCurr row
            $this->oComp->Set_iWindowOffset( $this->IdealWindowOffset() );
        }

//if( in_array($this->oComp->oUI->oApp->sess->GetUID(), [1,1499]) )
var_dump("k:".$this->oComp->Get_kCurr()." i:".$this->oComp->Get_iCurr()." iWO:".$this->oComp->Get_iWindowOffset());

        done:;
    }
}


// OBSOLETE
class SEEDUIListWindow
/*************************
    This encapsulates the view and window computation

    nViewSize       = total number of rows in view, required
    iWindowOffset   = 0-origin view-row number of the first displayed row
    nWindowSize     = number of rows to display in window
    iCurrOffset     = 0-origin view-row of the current row (-1 means there is no current row, but this is poorly implemented)
                                                           (-2 means it is outside of the ViewSlice so cannot be located, also poorly implemented)
 */
{
    private $nViewSize = 0;
    private $iWindowOffset = 0;
    private $nWindowSize = 0;
    private $iCurrOffset = 0;

    private $bWindowLimited = 0;    // computed based on view/window sizes

    function __construct() {}

    function InitListWindow( $raParms )
    /**********************************
        Initialize the simple case where the data for the whole view is available
     */
    {
        $this->nViewSize      = $raParms['nViewSize'];
        $this->iWindowOffset  = $raParms['iWindowOffset'];
        $this->nWindowSize    = $raParms['nWindowSize'];
        $this->iCurrOffset    = $raParms['iCurrOffset'];

// prefer not to have this as an input parm - callers should always send the viewsize
        $this->bWindowLimited = @$raParms['bWindowLimited'] ? $raParms['bWindowLimited'] : ($this->nWindowSize < $this->nViewSize);
    }

/* never used this
    function InitListWindow_PartialView( $raParms )
    [**********************************************
        Initialize the case where the total number of view rows is known, but the data for the whole view might not be available
     *]
    {
        $this->iWindowOffset  = intval(@$raParms['iWindowOffset']);
        $this->nWindowSize    = intval(@$raParms['nWindowSize']);
        $this->bWindowLimited = true;
        $this->iCurrOffset = $raParms['iCurrOffset'];
        $nViewRowsAbove = intval(@$raParms['nViewRowsAbove']);
        $nViewRowsBelow = intval(@$raParms['nViewRowsBelow']);
        $nDataRows      = intval(@$raParms['nDataRows']);

        $this->nViewSize      = $nDataRows + $nViewRowsAbove + $nViewRowsBelow;

        // these define the view-offset of the top and bottom rows that have data defined in raViewRows
        $iDataMin = $nViewRowsAbove;
        $iDataMax = $nViewRowsAbove + $nDataRows - 1;     // this is -1 if the list is empty!

        // the window can't show higher or lower than the defined portion of the view
        if( $this->iWindowOffset < $iDataMin ) {
            // the top of the window is above the defined data; shift it down to the top of the data
            $this->iWindowOffset = $iDataMin;
        }
        if( $this->iWindowOffset > $iDataMax ) {
            // the top of the window is above the defined data; shift it up, but not above the defined data
            $this->iWindowOffset = max( $iDataMin, $iDataMax - $this->nWindowSize );
        }
        if( $this->iWindowOffset + $this->nWindowSize - 1 > $iDataMax ) {
            // the top of the window is now guaranteed to be in the data region, but the bottom is below; shorten the window
            $this->nWindowSize = max( 0, $iDataMax - $this->iWindowOffset + 1 );
        }
    }
*/
}


class SEEDUIWidget_Form extends SEEDUIWidget_Base
{
    function __construct( SEEDUIComponent $oComp, $raConfig )
    {
        parent::__construct( $oComp, $raConfig );
    }

    function Draw()
    {
        $s = "";

        if( ($sTmpl = @$this->raConfig['sTemplate']) ) {
            // the form html is given verbatim by $sTmpl
            $s = $sTmpl;
        } else if( ($fn = @$this->raConfig['fnTemplate']) ) {
            // the form html is the result of $fn
            $s = call_user_func( $fn, $this->oComp->oForm );
        } else {
            $o = new SEEDFormExpand( $this->oComp->oForm );
            if( ($sTmpl = @$this->raConfig['sExpandTemplate']) ) {
                // the form html is the expansion of $sTmpl
                $s = $o->ExpandForm( $sTmpl );
            } else if( ($fn = @$this->raConfig['fnExpandTemplate']) ) {
                // the form html is the expansion of the result of $fn
                $sTmpl = call_user_func( $fn, $this->oComp->oForm );
                $s = $o->ExpandForm( $sTmpl );
            }
        }

        $sFormAttrs = "";
        if( @$raConfig['sAction'] )  $sFormAttrs .= " target='{$raConfig['sAction']}'";
        if( @$raConfig['sTarget'] )  $sFormAttrs .= " target='{$raConfig['sTarget']}'";

        $s = "<form method='".(@$raConfig['sMethod']?:"post")."' $sFormAttrs>"
            .$s."</form>";

        return( $s );
    }
}


class SEEDUIWidgets_Pills   // does not depend on SEEDWidget_Base or SEEDUIComponent
/************************
    Draw a list of Bootstrap pills that you can select

        $raPills          = array( name => array( label ), ... )
        $httpKeyName      = name of the http parm that contains pill name when you click on it
        $raConfig['oSVA'] = a SEEDSessionVarAccessor where the current pill name is stored
 */
{
    private $raPills;
    private $httpKeyName;
    private $currPill = "";

    function __construct( $raPills, $httpKeyName, $raConfig )
    {
        $this->raPills = $raPills;
        $this->httpKeyName = $httpKeyName;

        $oSVA = @$raConfig['oSVA'];

        $p = SEEDInput_Str( $this->httpKeyName );
        if( !$p && $oSVA ) {
            $p = $oSVA->VarGet( "CurrPill" );
        }
        // make this check after oSVA because the raPills can change depending on application modes (so a missing previous CurrPill should default to first pill)
        if( !$p || !isset($this->raPills[$p]) ) {
            // get the first pill in the array
            reset($this->raPills); $p = key($this->raPills);
        }
        if( $oSVA )  $oSVA->VarSet( "CurrPill", $p );

        $this->currPill = $p;
    }

    function GetCurrPill()  { return( $this->currPill ); }

    function DrawPillsVertical()
    {
        $s = "<style>"
            .".nav-pills > li.notactive > a { background-color:#eee; }"
            ."</style>"
            ."<ul class='nav nav-pills nav-stacked'>";
        foreach( $this->raPills as $k => $ra ) {
            $active = ($k == $this->currPill) ? "active" : "notactive";
            $s .= "<li class='$active'><a href='?{$this->httpKeyName}=$k'>{$ra[0]}</a></li>";
        }
        $s .= "</ul>";

        return( $s );
    }
}
