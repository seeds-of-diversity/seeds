<?php

/* SEEDUI.php
 *
 * Copyright (c) 2013-2018 Seeds of Diversity Canada
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
        foreach( array( 'sListUrlPage'   => $_SERVER['PHP_SELF'],
                        'sListUrlTarget' => "_top",
                        'sFormAction'    => $_SERVER['PHP_SELF'],
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

    public function Cid()               { return( $this->cid ); }

    public function Get_kCurr()         { return( $this->GetUIParm('kCurr') ); }
    public function Get_iCurr()         { return( $this->GetUIParm('iCurr') ); }
//    public function Get_bNew()          { return( $this->GetUIParm('bNew') ); }
//    public function Get_kDel()          { return( $this->GetUIParm('kDel') ); }
    public function Get_iWindowOffset() { return( $this->GetUIParm('iWindowOffset') ); }
    public function Get_nWindowSize()   { return( $this->GetUIParm('nWindowSize') ); }

    public function Set_kCurr( $k )         { $this->SetUIParm('kCurr', $k ); }
    public function Set_iCurr( $i )         { $this->SetUIParm('iCurr', $i ); }
//    public function Set_bNew( $b )          { $this->SetUIParm('bNew', $b ); }
//    public function Set_kDel( $k )          { $this->SetUIParm('kDel', $k ); }
    public function Set_iWindowOffset( $i ) { $this->SetUIParm('iWindowOffset', $i ); }
    public function Set_nWindowSize( $i )   { $this->SetUIParm('nWindowSize', $i ); }

    public function GetUIParm( $k )         { return( $this->oUI->GetUIParm( $this->cid, $k ) ); }
    protected function SetUIParm( $k, $v )  { $this->oUI->SetUIParm( $this->cid, $k, $v ); }


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


class SEEDUIWidget_Base
{
    protected $oComp;
    protected $raConfig;

    function __construct( SEEDUIComponent $oComp, $raConfig )
    {
        $this->oComp = $oComp;
        $this->raConfig = $raConfig;

        $this->RegisterWithComponent();
    }

    protected function RegisterWithComponent()
    /*****************************************
     * Tell the component to add this widget to its list.
     * Also provide the list of uiparms that SEEDUI should propagate in links/forms of other widgets.
     */
    {
        $raUIParms = array();  // array( 'myparm1' => array( 'http'=>'sf[[cid]]ui_myparm1', 'v'=>'my-initial-value' ),
                               //        'myparm2' => array( 'http'=>'sf[[cid]]ui_myparm2', 'v'=>'my-initial-value' ) );
        $this->oComp->RegisterWidget( $this, $raUIParms );
    }

    function Init1_NotifyUIParms( $raOldParms, $raNewParms )
    /*******************************************************
     * Given the uiparms from the previous page and the uiparms sent currently.
     * Look for any changes that affect this widget and return an array of state change advisories.
     */
    {
        $raAdvisories = array();

        // empty array means no changes of ui state wrt this widget

        return( $raAdvisories );
    }

    function Init2_NotifyUIStateChanges( $raAdvisories )
    /***************************************************
     * Given the list of state change advisories obtained from all widgets, tell SEEDUI to change uiparms that correspond to those changes wrt this widget.
     */
    {
        // e.g.
        // foreach( $raAdvisories as $v ) {
        //     if( $v == 'resetSomething' ) {
        //         $this->oComp->oUI->SetUIParm( 'something', 0 );
        //     }
        // }
    }

    function Init3_RequestSQLFilter()
    /********************************
     * At this point the uiparms are read, and reset as necessary for ui state changes. Use them to generate SQL conditions for the View wrt this widget.
     * Return a string containing an sql conditional (or empty).
     */
    {
        return( "" );
    }

    function Draw()
    /**************
     * UIParms are all set and the Component has loaded a view/window. Use those to draw the widget.
     */
    {
        return( "OVERRIDE Draw()" );
    }
}


class SEEDUIWidget_SearchControl extends SEEDUIWidget_Base
/*******************************
    Draw a search control with one or more terms.  Each term has a field list, op list, and text input.

    raConfig = array( 'filters'  => array( array('label=>label1, 'col'=>'fld1'),
                                           array('label=>label2, 'col'=>'fld2') ),
                      'template' => " HTML template containing [[fldN]] [[opN]] [[valN]] " )

    The filters use the same format as the List cols, for convenience in your config.
    The template substitutes the tags [[fldN]], [[opN]], [[valN]], where N is the origin-1 filter index

    Default template just separates one row of tags with &nbsp;
 */
{
    function __construct( SEEDUIComponent $oComp, $raConfig )
    {
        parent::__construct( $oComp, $raConfig );
    }

    function RegisterWithComponent()
    {
        /* Tell SEEDUI that these parms should be read from $_REQUEST, and propagated in all links/forms in the UI
         * Use sfAx_ format because SEEDForm can make controls like that conveniently.
         */
        $raUIParms = array();
        foreach( array(1,2,3) as $i ) {
            $raUIParms["srchfld$i"] = array( 'http'=>"sf[[cid]]x_srchfld$i", 'v'=>"" );
            $raUIParms["srchop$i"]  = array( 'http'=>"sf[[cid]]x_srchop$i",  'v'=>"" );
            $raUIParms["srchval$i"] = array( 'http'=>"sf[[cid]]x_srchval$i", 'v'=>"" );
        }
        $this->oComp->RegisterWidget( $this, $raUIParms );
    }

    function Init1_NotifyUIParms( $raOldParms, $raNewParms )
    {
        $raAdvisories = array();

        // if any search parameters have changed, reset the view
        foreach( array(1,2,3) as $i ) {
            if( ((@$raOldParms["srchfld$i"] || @$raNewParms["srchfld$i"]) && @$raOldParms["srchfld$i"] != @$raNewParms["srchfld$i"]) ||
                ((@$raOldParms["srchop$i"]  || @$raNewParms["srchop$i"])  && @$raOldParms["srchop$i"]  != @$raNewParms["srchop$i"]) ||
                ((@$raOldParms["srchval$i"] || @$raNewParms["srchval$i"]) && @$raOldParms["srchval$i"] != @$raNewParms["srchval$i"]) )
            {
                $raAdvisories[] = "VIEW_RESET";
                break;
            }
        }
        return( $raAdvisories );
    }

    function Init3_RequestSQLFilter()
    {
        $raCond = array();
        $sCond = "";

        if( !@$this->raConfig['filters'] )  goto done;

        /* For each search row, get a condition clause
         */
        foreach( array(1,2,3) as $i ) {
            $fld = $this->oComp->GetUIParm( "srchfld$i" );
            $op  = $this->oComp->GetUIParm( "srchop$i" );
            $val = trim($this->oComp->GetUIParm( "srchval$i" ));

            if( $op == 'blank' ) {
                // process this separately because the text value is irrelevant
                if( $fld ) {  // 'Any'=blank is not allowed
                    $raCond[] = "($fld='' OR $fld IS NULL)";
                }
            } else if( $val ) {
                if( $fld ) {
                    $raCond[] = $this->dbCondTerm( $fld, $op, $val );
                } else {
                    // field "Any" is selected, so loop through all the fields to generate a condition that includes them all
                    $raC = array();
                    foreach( $this->raConfig['filters'] as $raF ) {
                        $label = $raF['label'];
                        $f = $raF['col'];
                        if( empty($f) )  continue;  // skip 'Any'
                        $raC[] = $this->dbCondTerm( $f, $op, $val );   // op and val are the current uiparm values for this search row
                    }

                    // glue the conditions together as disjunctions
                    $raCond[] = "(".implode(" OR ",$raC).")";
                }
            }
        }
        // glue the filters together as conjunctions
        $sCond = implode(" AND ", $raCond);

        done:
        return( $sCond );
    }

    private function dbCondTerm( $col, $op, $val )
    /*********************************************
        eq       : $col = '$val'
        like     : $col LIKE '%$val%'
        start    : $col LIKE '$val%'
        end      : $col LIKE '%$val'
        less     : $col < '$val'
        greater  : $col > '$val'
        blank    : handled by SearchControlDBCond()
     */
    {
        $val = addslashes($val);

        switch( $op ) {
            case 'like':    $s = "$col LIKE '%$val%'";  break;
            case 'start':   $s = "$col LIKE '$val%'";   break;
            case 'end':     $s = "$col LIKE '%$val'";   break;

            case 'less':    $s = "($col < '$val' AND $col <> '')";    break;    // a < '1' is true if a is blank
            case 'greater': $s = "($col > '$val' AND $col <> '')";    break;

            case 'eq':
            default:        $s = "$col = '$val'";       break;
        }

        return( $s );
    }

    function Draw()
    {
        $s = @$raConfig['template'] ?: "[[fld1]]&nbsp;[[op1]]&nbsp;[[text1]]&nbsp;[[submit]]";

        if( !@$this->raConfig['filters'] )  goto done;

        foreach( array(1,2,3) as $i ) {
            $fld = $this->oComp->GetUIParm( "srchfld$i" );
            $op  = $this->oComp->GetUIParm( "srchop$i" );
            $val = trim($this->oComp->GetUIParm( "srchval$i" ));

            /* Collect the fields and substitute into the appropriate [[fieldsN]]
             */
            $raCols['Any'] = "";
            foreach( $this->raConfig['filters'] as $ra ) {
                $raCols[$ra['label']] = $ra['col'];
            }

            // using sfAx_ format in the uiparms because it's convenient for oForm to generate it (instead of sfAui_)
            $c = $this->oComp->oForm->Select( "srchfld$i", $raCols, "", array('selected'=>$fld, 'sfParmType'=>'ctrl_global') );

            $s = str_replace( "[[fld$i]]", $c, $s );

            /* Write the [[opN]]
             */
            // using sfAx_ format in the uiparms because it's convenient for oForm to generate it (instead of sfAui_)
            $c = $this->oComp->oForm->Select(
                    "srchop$i",
                    array( "contains" => 'like',     "equals" => 'eq',
                           "starts with" => 'start', "ends with" => 'end',
                           "less than" => 'less',    "greater than" => 'greater',
                           "is blank" => 'blank' ),
                    "",
                    array('selected'=>$op, 'sfParmType'=>'ctrl_global') );
            $s = str_replace( "[[op$i]]", $c, $s );

            /* Write the [[textN]]
             */
            // using sfAx_ format in the uiparms because it's convenient for oForm to generate it (instead of sfAui_)
            $c = $this->oComp->oForm->Text( "srchval$i", "", array('value'=>$val, 'sfParmType'=>'ctrl_global', 'size'=>20) );
            $s = str_replace( "[[text$i]]", $c, $s );
        }

        $s = str_replace( '[[submit]]', "<input type='submit' value='Search'/>", $s );

        $s = $this->oComp->DrawWidgetInForm( $s, $this, array() );

        done:
        return( $s );
    }
}


class SEEDUIWidget_SearchDropdown extends SEEDUIWidget_Base
{
    function __construct( SEEDUIComponent $oComp, $raConfig )
    {
        parent::__construct( $oComp, $raConfig );
    }

    function Init3_RequestSQLFilter()
    {
        $raCond = array();

        /* For each defined control, get a condition clause
         */
        foreach( $raConfig['controls'] as $fld => $ra ) {
            if( $ra[0] == 'select' ) {
                if( ($currVal = $this->CtrlGlobal('srchctl_'.$fld)) ) {
                    $raCond[] = "$fld='".addslashes($currVal)."'";
                }
            }
        }
    }


    function Draw()
    {
        foreach( $raConfig['controls'] as $fld => $ra ) {
            if( strpos( $s, "[[$fld]]" ) !== false ) {
                // The control exists in the template  (probably we should assume this and not bother to check)
                if( $ra[0] == 'select' ) {
                    $currVal = $oForm->CtrlGlobal('srchctl_'.$fld);
                    $c = $oForm->Select( 'srchctl_'.$fld, $ra[1], "", array( 'sfParmType'=>'ctrl_global', 'selected'=>$currVal) );
                    $s = str_replace( "[[$fld]]", $c, $s );
                }
            }
        }
    }
}


class SEEDUIWidget_List extends SEEDUIWidget_Base
{
    function __construct( SEEDUIComponent $oComp, $raConfig = array() )
    {
        parent::__construct( $oComp, $raConfig );
    }

    function RegisterWithComponent()
    {
        // Tell SEEDUI that these parms should be read from $_REQUEST, and propagated in all links/forms in the UI
        $raUIParms = array( 'sortup'   => array( 'http'=>'sf[[cid]]ui_sortup',   'v'=>0 ),
                            'sortdown' => array( 'http'=>'sf[[cid]]ui_sortdown', 'v'=>0 ) );
        $this->oComp->RegisterWidget( $this, $raUIParms );
    }


    function Init2_NotifyUIStateChanges( $raAdvisories )
    {
    }


    function Style()
    {
        $s = "<style>
               table.sfuiListTable { border-collapse:separate; border-spacing:2px; }   /* allows white lines between List cells - the moz default, but BS collapses to zero */
               .sfuiListRowTop,
               .sfuiListRowBottom  { background-color: #777; font-size:8pt; color: #fff; }
               .sfuiListRowBottom a:link    { color:#fff; }
               .sfuiListRowBottom a:visited { color:#fff; }
               .sfuiListRow     { font-size:8pt; }
               .sfuiListRow0    { background-color: #e8e8e8; }
               .sfuiListRow1    { background-color: #fff; }
               .sfuiListRow2    { background-color: #44f; color: #fff; }
              </style>";

        return( $s );
    }

    function ListDrawBasic( $raList, $iOffset, $nSize, $raParms = array() )
    /**********************************************************************
        Draw the rows of $raList[$iOffset] to $raList[$iOffset + $nSize]
        iOffset is 0-origin
        nSize==-1 is the whole list after iOffset

            Header = labels and controls
            Top    = shows how many rows are above
            Rows   = the data
            Bottom = shows how many rows are below
            Footer = ?

        raParms:
            cols          = the elements of raList to show, and the order to show them
                            array of array( 'label'=>..., 'col'=>..., 'w'=>... etc
                                label  = column header label
                                col    = k in each raList[] to use for this column
                                w      = width of column (css value)
                                trunc  = chars to truncate
                                colsel = array of filter values
                                align  = css value for text-align (left,right,center,justify)

            tableWidth    = css width of table

            sHeader       = content for the header
            sFooter       = content for the footer
            sTop          = content for the top table row
            sBottom       = content for the bottom table row

            iCurrRow      = the element of $raList that is the current row (<iOffset or >=iOffset+nSize means no current row is shown)
                            default is -1, which is always no-row
            fnRowTranslate = function to translate row array into a different row array

        raList:
            Each row contains elements named as raParms['cols'][X]['col'],
            also additional elements:
                sfuiLink  = a link to be activated when someone clicks on the row
     */
    {
        $s = "";

        if( $nSize == -1 ) {
            // window size should contain the whole raList starting at iOffset
            $nSize = count($raList) - $iOffset;
        } else {
            // enforce sensible bounds
            $nSize = SEEDCore_Bound( $nSize, 0, count($raList) - $iOffset );
        }

        /* Create default parms
         *
         * If cols is not specified, create it using the first data row
         */
        if( !isset($raParms['cols']) ) {
            $raParms['cols'] = array();
            foreach( $raList[0] as $k => $v ) {
                $raParms['cols'][] = array( 'label'=>$k, 'col'=>$k );
            }
        }
        $sHeader  = @$raParms['sHeader'];
        $sFooter  = @$raParms['sFooter'];
        $sTop     = SEEDCore_ArraySmartVal1( $raParms, 'sTop', "&nbsp;", false );       // nbsp needed to give height to a blank header
        $sBottom  = SEEDCore_ArraySmartVal1( $raParms, 'sBottom', "&nbsp;", false );
        $iCurrRow = SEEDCore_ArraySmartVal1( $raParms, 'iCurrRow', -1, true );          // if empty not allowed, 0 is interpreted as empty and converted to -1 !
        //if( $iCurrRow < $iOffset || $iCurrRow >= $iOffset + $nSize )  $iCurrRow = -1;

        $nCols = count($raParms['cols']);

        $sTableStyle = ($p = @$raParms['tableWidth']) ? "width:$p;" : "";
        $s .= "<table class='sfuiListTable' style='$sTableStyle'>";

        /* List Header
         */
        $s .= $sHeader;

        /* List Top
         */
        $s .= "<tr class='sfuiListRowTop'><td colspan='$nCols'>$sTop</td></tr>";

        /* List Rows
         */
        for( $i = $iOffset; $i < $iOffset + $nSize; ++$i ) {
            $raRow = array();
            // Clean up any untidy characters.
            // This can be a problem for content that's meant to show html markup.
            // This is done here, instead of below, because we want to allow fnTranslate to insert html markup.
            foreach( $raList[$i] as $kCol => $vCol ) {
                $raRow[$kCol] = SEEDCore_HSC( $vCol );
            }
            if( @$raParms['fnRowTranslate'] ) {
                $raRow = call_user_func( $raParms['fnRowTranslate'], $raRow );
            }

            if( $i == $iCurrRow ) {
                $rowClass = 2;
            } else {
                $rowClass = $i % 2;
            }
            $s .= "<tr class='sfuiListRow sfuiListRow$rowClass'>";
            foreach( $raParms['cols'] as $raCol ) {
                $v = $raRow[$raCol['col']];

                $sColStyle = "cursor:pointer;";
                if( ($p = @$raCol['align']) )  $sColStyle .= "text-align:$p;";
                if( ($p = @$raCol['w']) )      $sColStyle .= "width:$p;";
                if( ($n = intval(@$raCol['trunc'])) && $n < strlen($v) ) {
                    $v = substr( $v, 0, $n )."...";
                }
                $sLink = @$raRow['sfuiLink'] ? "onclick='location.replace(\"{$raRow['sfuiLink']}\");'" : "";

                // $v has already been through HSC above, but before fnTranslation
                $s .= "<td $sLink style='$sColStyle'>$v</td>";
            }
            $s .= "</tr>";
        }

        /* List Bottom
         */
        $s .= "<tr class='sfuiListRowBottom'><td colspan='$nCols'>$sBottom</td></tr>";

        /* List Footer
         */
        $s .= $sFooter;

        $s .= "</table>";

        return( $s );
    }

    function ListDrawInteractive( $raViewRows, $raParms )
    /****************************************************
        Draw a list widget for a given Window on a given View of rows in an array.

        $raViewRows               = a [portion of] rows of a View
                                    if not the complete view, iViewOffset > 0
                                    array of array( 'k1'=>'v1', 'k2'=>'v2' )
                                    Rows are in display order, cols are not ordered (selected by raParms['cols']

        $raParms:
            iViewOffset           = origin-0 row of the view that corresponds to the first element of raViewRows
            nViewSize             = size of View, optional if $raViewRows contains the full view, required if raViewRows is NULL or partial
            iWindowOffset         = top View index that appears in the window, optional (default 0)
            nWindowSize           = number of rows to draw in the window (default 10)
            iCurr                 = View index of the current row, optional (default 0)

            cols                  = as ListDrawBasic
            tableWidth            = as ListDrawBasic
            fnRowTranslate        = as ListDrawBasic

            bUse_key              = activate the use of keys on rows: input and output kCurr uiParm, calculate iCurr/kCurr from each other

//          bNewAllowed           = true if the list is allowed to set links that create new records
     */
    {
        $s = "";

        // uiparms overrides raParms overrides default
        if( !$this->oComp->Get_nWindowSize() )  $this->oComp->Set_nWindowSize( @$raParms['nWindowSize'] ?: 10 );
        $raParms['tableWidth'] = @$raParms['tableWidth'] ?: "100%";


        $bEnableKeys = @$raParms['bUse_key'];
        if( $bEnableKeys ) {
            /* If kCurr is given but not iCurr, search the list for the iCurr.
             * Note the test doesn't notice when kCurr corresponds to the first row (iCurr==0) but the search will be very short.
             */
            if( $this->oComp->Get_kCurr() ) {  //&& !$this->oComp->Get_iCurr() ) {
                foreach( $raViewRows as $i => $ra ) {
                    if( @$ra['_key'] && $ra['_key'] == $this->oComp->Get_kCurr() ) {
                        $this->oComp->Set_iCurr( $i );
                        break;
                    }
                }
            }
            /* If kCurr is not given, Try to get the current key from the current row. By default, that will be row 0, which is fine.
             */
            if( !$this->oComp->Get_kCurr() && ($k = @$raViewRows[$this->oComp->Get_iCurr()]['_key']) ) {
                $this->oComp->Set_kCurr( $k );
            }
        }

        $oLW = new SEEDUIListWindow();
        $oLW->InitListWindow( array(
            //'iViewOffset'    => intval(@$raParms['iViewOffset']),
            'nViewSize'     => (@$raParms['nViewSize'] ? $raParms['nViewSize'] : count($raViewRows)),
            'iWindowOffset' => $this->oComp->Get_iWindowOffset(),
            'nWindowSize'   => $this->oComp->Get_nWindowSize(),
            'iCurrOffset'   => $this->oComp->Get_iCurr()
        ) );


        $nWindowRowsAbove = $oLW->RowsAboveWindow();
        $nWindowRowsBelow = $oLW->RowsBelowWindow();
        $raScrollOffsets  = $oLW->ScrollOffsets();
        $iViewOffset      = intval(@$raParms['iViewOffset']);
        $iWindowOffset    = $nWindowRowsAbove;

        //$bNewAllowed = intval(@$raParms['bNewAllowed']);


        $iSortup = $iSortdown = 0;
        $raSortSame = array();
        if( ($iSortup = $this->oComp->GetUIParm('sortup')) ) {
            $raSortSame = array( 'sortup'=>$iSortup );
        } else if( ($iSortdown = $this->oComp->GetUIParm('sortdown')) ) {
            $raSortSame = array( 'sortdown'=>$iSortdown );
        }

        /* List Header and Footer
         */
        $sHeader = "<tr>";
        $c = 1;
        foreach( $raParms['cols'] as $raCol ) {
            // This just draws the headers based on the sorting criteria. You have to provide the ViewRows already sorted.
            $bSortingUp   = $iSortup==$c;
            $bSortingDown = $iSortdown==$c;

            $sCrop = ($bSortingDown ? "position:absolute; top:-14px; left:-20px; clip: rect( 19px, auto, auto, 20px );" :
                      ($bSortingUp ? "position:absolute; top:4px; left:-20px; clip: rect( 0px, auto, 6px, 20px );" :
                                     "" ) );
            $href   = ( $bSortingDown ? $this->oComp->HRefForWidget( $this, array("iCurr"=>0,"sortup"   => $c, "sortdown"=>0)) :
                       ($bSortingUp   ? $this->oComp->HRefForWidget( $this, array("iCurr"=>0,"sortdown" => $c, "sortup"=>0)) :
                                        $this->oComp->HRefForWidget( $this, array("iCurr"=>0,"sortup"   => $c, "sortdown"=>0)) ));

            $sColStyle = "font-size:small;";
            if( ($p = @$raCol['align']) )  $sColStyle .= "text-align:$p;";
            if( ($p = @$raCol['w']) )      $sColStyle .= "width:$p;";

            $sHeader .= "<th style='$sColStyle;vertical-align:baseline'>"
                       ."<a $href>".$raCol['label']
                       .($bSortingUp || $bSortingDown
                          ? ("&nbsp;<div style='display:inline-block;position:relative;width:10px;height:12px;'>"
                           ."<img src='".W_ROOT."std/img/triangle_blue.png' style='$sCrop' border='0'/></div>")
                          : "")
                       ."</a></th>";
            ++$c;
        }
        $sHeader .= "</tr>";

        $sFooter = "";

        /* List Top
         */
        $sTop = "&nbsp;&nbsp;&nbsp;"
               .($nWindowRowsAbove ? ($nWindowRowsAbove.($nWindowRowsAbove > 1 ? " rows" : " row")." above")
                                   : "Top of List")
               ."<span style='float:right;margin-right:3px;'>";
        if( $oLW->CurrRowIsOutsideWindow() ) {
            $sTop .= $this->listButton( "<span style='display:inline-block;background-color:#ddd;color:#222;font-weight:bold;'>"
                                       ."&nbsp;FIND SELECTION&nbsp;</span>", array('offset'=>$oLW->IdealOffset()))
                    .SEEDCore_NBSP("",10);
        }
        if( $nWindowRowsAbove ) {
            $sTop .= $this->listButton( "TOP", array_merge( $raSortSame, array( 'offset'=>$raScrollOffsets['top'] ) ) )
                    .SEEDCore_NBSP("",5)
                    .$this->listButton( "PAGE", array_merge( $raSortSame, array( 'offset'=>$raScrollOffsets['pageup'], 'img'=>"up2" ) ) )
                    .SEEDCore_NBSP("",5)
                    .$this->listButton( "UP", array_merge( $raSortSame, array( 'offset'=>$raScrollOffsets['up'], 'img'=>"up" ) ) );
        }
        $sTop .= "</span>";

        /* List Bottom
         */
        $sBottom = "&nbsp;&nbsp;&nbsp;"
                  .($nWindowRowsBelow ? ($nWindowRowsBelow.($nWindowRowsBelow > 1 ? " rows" : " row")." below")
                                        // : ("<a ".$this->HRef(array('kCurr'=>0,'bNew'=>true)).">End of List</a>"));
                                      :"End of List")
                  ."<span style='float:right;margin-right:3px;'>"
                  // List size buttons
                  //.$this->_listButton( "[10]", array( 'limit'=>10 ) ).SEEDCore_NBSP("",5)
                  //.$this->_listButton( "[50]", array( 'limit'=>50 ) ).SEEDCore_NBSP("",5)
                  // special case: the list can't yet compute scroll-up links when this button is chosen so offset must be cleared (see note above)
                  //.$this->_listButton( "[All]", array( 'limit'=>-1, 'offset'=>$raScrollOffsets['top'] ) );
                  //.SEEDCore_NBSP("",15)
                  ;
        if( $nWindowRowsBelow ) {
            $sBottom .= $this->listButton( "BOTTOM", array_merge( $raSortSame, array( 'offset'=>$raScrollOffsets['bottom'] ) ) )
                       .SEEDCore_NBSP("",5)
                       .$this->listButton( "PAGE", array_merge( $raSortSame, array( 'offset'=>$raScrollOffsets['pagedown'], 'img'=>"down2" ) ) )
                       .SEEDCore_NBSP("",5)
                       .$this->listButton( "DOWN", array_merge( $raSortSame, array( 'offset'=>$raScrollOffsets['down'], 'img'=>"down" ) ) );
        }
        $sBottom .= "</span>";


        if( $raViewRows ) {
            // get the window within the given portion of the view
            $raViewSlice = array_slice( $raViewRows, $iWindowOffset - $iViewOffset, $this->oComp->Get_nWindowSize() );
        } else {
            // get the window as needed
            $raViewSlice = $this->ListFetchViewSlice( $iWindowOffset, $this->oComp->Get_nWindowSize() );
        }


        /* Links to activate a row as the current row when it is clicked
         */
// ListDrawBasic can be enabled to do this, given the iWindowOffset, raSortSame**, bUseKey, bMakeLink, and the app has to use sfLui_k instead of kVi
        for( $i = 0; $i < count($raViewSlice); ++$i ) {
            $ra = $raSortSame;
            $ra['iCurr'] = $i+$iWindowOffset;
            $ra['iWindowOffset'] = $iWindowOffset;
            if( $bEnableKeys && ($k = @$raViewSlice[$i]['_key']) ) {
                $ra['kCurr'] = $k;
            }
            $raViewSlice[$i]['sfuiLink'] = $this->oComp->LinkForWidget( $this, $ra );
        }

        $raBasicListParms = array(
            'cols' => $raParms['cols'],
            'tableWidth' => $raParms['tableWidth'],
            'fnRowTranslate' => (@$raParms['fnRowTranslate'] ?: null),

            'sHeader' => $sHeader,
            'sFooter' => $sFooter,
            'sTop' => $sTop,
            'sBottom' => $sBottom,
            'iCurrRow' => $this->oComp->Get_iCurr() - $iWindowOffset,
        );
        $s .= $this->ListDrawBasic( $raViewSlice, 0, $this->oComp->Get_nWindowSize(), $raBasicListParms );

        return( $s );
    }

    function ListFetchViewSlice( $iOffset, $nSize )
    /**********************************************
        Override to get an array slice of the View
     */
    {
        return( array() );
    }

    function ListDraw( $raViewRows, $raParms )    // DEPRECATE
    {
        return( $this->ListDrawInteractive( $raViewRows, $raParms ) );
    }

    private function listButton( $label, $raParms )
    /**********************************************
        Draw the TOP, PAGE UP, etc buttons
     */
    {
        $raChange = array();
        if( isset($raParms['offset']) )  $raChange['iWindowOffset'] = $raParms['offset'];
        if( isset($raParms['limit']) )   $raChange['nWindowSize'] = $raParms['limit'];

// kind of want to hand raParms to HRef, and have it recognize these because they're registered into raUIParms
        if( isset($raParms['sortup']) )   $raChange['sortup'] = $raParms['sortup'];
        if( isset($raParms['sortDown']) ) $raChange['sortdown'] = $raParms['sortdown'];

        $img = @$raParms['img'];

        switch( $img ) {
            case 'up':      $sCrop = "position:absolute; top:4px; left:-10px; clip: rect( 0px, 20px, 6px, 10px );";   break;
            case 'up2':     $sCrop = "position:absolute; top:2px; left:-10px; clip: rect( 0px, 20px, 12px, 10px );";   break;
            case 'down':    $sCrop = "position:absolute; top:-14px; left:-10px; clip: rect( 19px, 20px, auto, 10px );";   break;
            case 'down2':   $sCrop = "position:absolute; top:-10px; left:-10px; clip: rect( 13px, 20px, auto, 10px );";   break;
            default:        $sCrop = ""; break;
        }

        $s = "<a ".$this->oComp->oUI->HRef($raChange)." style='color:white;text-decoration:none;font-size:7pt;'>"
            ."<b>$label</b>"
            .($img ? ("&nbsp;<div style='display:inline-block;position:relative;width:10px;height:12px;'>"
                           ."<img src='".W_ROOT."std/img/triangle_blue.png' style='$sCrop' border='0'/></div>") : "")
            ."</a>";
        return( $s );
    }
}


class SEEDUIListWindow
/*************************
    This encapsulates the view and window computation

    nViewSize       = total number of rows in view, required
    iWindowOffset   = 0-origin view-row number of the first displayed row
    nWindowSize     = number of rows to display in window
    iCurrOffset     = 0-origin view-row of the current row (-1 means there is no current row, but this is poorly implemented)
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

    function WindowIsLimited()  { return( $this->bWindowLimited ); }  // true if we are imposing a max size on the window (so offsets and scrolling needed)
    function RowsAboveWindow()  { return( $this->iWindowOffset ); }   // number of rows that you can scroll up to the top of the view

    function RowsBelowWindow()                                        // number of rows that you can scroll down to the bottom of the view
    {
        $n = 0;
        if( $this->bWindowLimited ) {
            $n = SEEDCore_Bound( $this->nViewSize - $this->iWindowOffset - $this->nWindowSize, 0 );
        }
        return( $n );
    }

    function IdealOffset()
    /*********************
        To reposition the window so it includes the selected row, find the window offset that puts
        the row in the middle of the window, then adjust for boundaries
     */
    {
        if( $this->iCurrOffset == -1 ) return(0);   // no current row

        $offset = SEEDCore_Bound( $this->iCurrOffset - intval($this->nWindowSize/2),
                                  0,
                                  $this->nViewSize - $this->nWindowSize );
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

        $ra['top']      = 0;
        $ra['bottom']   = SEEDCore_Bound( $this->nViewSize - $this->nWindowSize,     0 );
        $ra['up']       = SEEDCore_Bound( $this->iWindowOffset - 1,                  0, $ra['bottom'] );
        $ra['down']     = SEEDCore_Bound( $this->iWindowOffset + 1,                  0, $ra['bottom'] );
        $ra['pageup']   = SEEDCore_Bound( $this->iWindowOffset - $this->nWindowSize, 0, $ra['bottom'] );
        $ra['pagedown'] = SEEDCore_Bound( $this->iWindowOffset + $this->nWindowSize, 0, $ra['bottom'] );

        return( $ra );
    }

    function CurrRowIsOutsideWindow()
    {
        if( $this->iCurrOffset == -1 ) return(false);   // no current row

        return( $this->bWindowLimited &&
                ($this->iCurrOffset < $this->iWindowOffset || $this->iCurrOffset >= $this->iWindowOffset + $this->nWindowSize) );
    }
}


class SEEDUIWidget_Form extends SEEDUIWidget_Base
{
    function __construct( SEEDUIComponent $oComp, $raConfig )
    {
        parent::__construct( $oComp, $raConfig );
    }

    function Draw()
    {
        return( "OVERRIDE" );
    }
}

?>