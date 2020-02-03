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
    public function GetUIParmsListOld( $cid )
    {
        // return an array of all UI parms from the previous page
        return( array() );
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

    function __construct( SEEDUI $oUI, $cid = 'A', $raCompConfig = array() )
    {
        $this->oUI = $oUI;
        $this->cid = $cid;
        $this->raCompConfig = $raCompConfig;

        // the SEEDUI keeps a list of all the components
        $oUI->RegisterComponent( $this );

        $this->oForm = $this->factory_SEEDForm( $this->Cid(),
                                                isset($raCompConfig['raSEEDFormParms']) ? $raCompConfig['raSEEDFormParms'] : array() );

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
     */
    {
        /* Notify every widget of the new and old ui parms. They return arrays of state change advisories.
         */
        $raAdvisories = array();
        foreach( $this->raWidgets as $ra ) {
            $raAdvisories = array_merge( $raAdvisories,
                                         $ra['oWidget']->Init1_NotifyUIParms( $this->oUI->GetUIParmsListOld($this->cid),
                                                                              $this->oUI->GetUIParmsList($this->cid) ) );
        }
        $raAdvisories = array_unique($raAdvisories);

        /* Provide the list of advisories (even if it's empty) to every widget so they can tell SEEDUI how to alter the uiparms wrt state changes.
         */
        foreach( $this->raWidgets as $ra ) {
            $ra['oWidget']->Init2_NotifyUIStateChanges( $raAdvisories );
        }

        /* Request sql filters from every widget, now that uiparms are stable and reflect the View state.
         */
        $raSqlCond = array();
        foreach( $this->raWidgets as $ra ) {
            if( ($cond = $ra['oWidget']->Init3_RequestSQLFilter()) ) {
                $raSqlCond[] = "($cond)";
            }
        }

        /* sqlCond is a gift to a derived class that would actually implement db access
         */
        $this->sSqlCond = implode( " AND ", $raSqlCond );
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
    A full view or a view slice is supported by the same parameters: a full view is iViewSliceOffset=0, nViewSize==count(raViewRows)

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
    private $iViewSliceOffset = 0;          // origin-0 index of the first row in raViewRows relative to the whole view
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
        $this->iViewSliceOffset = @$raParms['iViewSliceOffset'] ?: 0;
        $this->nViewSize        = @$raParms['nViewSize'] ?: 0;

        /* If kCurr is given, set iCurr to that row because it is not always set in a synchronized way.
         * We need both because a window can be scrolled using a view slice such that the current row is not in the slice anymore
         * so kCurr alone can't tell us where it is. We rely on iCurr being propagated during that scroll.
         */
        if( $this->bEnableKeys && $this->oComp->Get_kCurr() ) {
            $this->bCurrentRowOutsideViewSlice = true;  // default result if row not found
            $i = 0;
            foreach( $this->raViewRows as $ra ) {
                if( @$ra['_key'] == $this->oComp->Get_kCurr() ) {
                    $this->oComp->Set_iCurr( $i + $this->iViewSliceOffset );
                    $this->bCurrentRowOutsideViewSlice = false;
                    break;
                }
                ++$i;
            }
        }

        /* If kCurr is not given, try to get the current key from the current value of iCurr.
         * If iCurr is outside of the view slice, default to the start of the slice
         * (probably this will happen when something is reset so the slice will start at the top of the view anyway)
         * Note that iCurr < 0 have special meanings that are invalid for this case.
         */
        if( $this->bEnableKeys && !$this->oComp->Get_kCurr() && $this->oComp->Get_iCurr() >= 0 ) {
            // If iCurr is over the viewSize, reset it to viewOffset 0. Something weird happened that made the view shrink.
            if( $this->oComp->Get_iCurr() >= $this->nViewSize ) {
                $this->oComp->Set_iCurr(0);
            }
            // If there is a valid key at the current row, make that the kCurr
            $this->oComp->Set_kCurr( @$this->raViewRows[$this->oComp->Get_iCurr()]['_key'] ?: 0 );
        }
    }

    function GetWindowData()
    /***********************
        return view rows contained in the window
     */
    {
        return( $this->GetViewData( $this->oComp->Get_iWindowOffset(), $this->oComp->Get_nWindowSize() ) );
    }

    function GetViewData( $iViewSliceOffset, $nViewSliceSize )
    /*********************************************************
        return nViewSliceSize rows of the view starting at origin-0 row iViewSliceOffset
     */
    {
        if( !$this->raViewRows ) {
            // View rows haven't been loaded yet. Fetch them using the derived object.
            list($rows,$iVO,$nVS) = $this->FetchViewSlice( $iViewSliceOffset, $nViewSliceSize );
            $this->SetViewSlice( $rows, ['iViewSliceOffset'=>$iVO,'nViewSize'=>$nVS] );
        }

        $iOffsetOfRequestedSliceWithinLoadedRows = $iViewSliceOffset - $this->iViewSliceOffset;
        return( $this->raViewRows && ($iOffsetOfRequestedSliceWithinLoadedRows >=0)
                    ? array_slice($this->raViewRows, $iOffsetOfRequestedSliceWithinLoadedRows, $nViewSliceSize)
                    : [] );
    }

    function FetchViewSlice( $iViewSliceOffset, $nViewSliceSize )
    {
        // Override to fetch the view data. You can fetch any amount of data, since the slice is re-obtained after the fetch.
        $raViewData = [];
        $iReturnedViewSliceOffset = 0;  // returned data is a slice starting at this offset of the view (can be lower number than requested if that's convenient)
        $nViewSize = 0;                 // total size of the view (in case we didn't have it yet)
        return( [$raViewData,$iReturnedViewSliceOffset,$nViewSize] );
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
