<?php

/* SEEDForm.php
 *
 * Copyright (c) 2008-2018 Seeds of Diversity Canada
 *
 * Simplify the development of forms by connecting auto-marshalled parameters, smart form drawing methods, and a virtualized data store.
 * This module provides a base class that can create and manage forms with an array for data storage. It can be extended to work with any storage.
 *
 * See SEEDFormParms for details on parameter management.
 */

include_once( "SEEDFormParms.php" );
include_once( "SEEDDataStore.php" );
include_once( "SEEDTag.php" );

class SEEDForm extends SEEDFormElements
/*************
    SEEDForm creates a direct connection between html form elements and columns in a SEEDDataStore.
    It writes form elements with names encoded using SEEDFormParms (cid and row number), and with values from the data store.
    On Load/Store or Update it deserializes those values from the http parameters, and repopulates the values in the data store.

    SEEDFormElements does the basic drawing of html form elements.

    SEEDForm handles load/save/update and get/set of SEEDDataSource, which is accessed by SEEDFormElements through sfValue/sfSetValue

    SEEDForm{Others} implement advanced form elements e.g. search controls, multi-row forms, etc.


    Usage
    -----
    $o = new SEEDForm( $cid, $raParms );
    $o->Update();             // to receive http parms encoded using cid, deserialize the applicable http parms, update the data store
    <FORM>
    $o->Text(...);            // to draw a form that encodes http parms using cid
    </FORM>

    OR

    $o = new SEEDForm( $cid, $raParms );
    $o->Load();               // to receive http parms encoded using cid, deserialize the applicable http parms, update memory store
    examine/change $o->oDS
    $o->Store();              // update data store (typically does nothing if the data store is in-memory)
    <FORM>
    $o->Text(...);            // to draw a form that encodes http parms using cid
    </FORM>

    Update vs. Load/Store
    ---------------------
    Update essentially performs a Load/Store, and it can be exactly replaced by Load();Store(); if there is only one row.
    This separation is handy for evaluating parms for a db datasource (where data is held in memory before being committed).

     In multiple-row applications, it is unclear how to implement separate Load() and Store() methods. Better to use the PreStore
     override.

     So, in single-row applications:
         $this->Update();    is exactly the same as    $this->Load();
                                                       $this->Store();

     and in multiple-row applications:
         $this->Update();  is the only way to load and store parms, but the PreStore override can allow the client to insert code
                           between the Load and Store phases on each row

    Other raParms
    -------------
        'bTrimValue' = array( 'field1' => true | false, 'field2' => true | false, ... )    // specify whether each field should be trimmed - default true
        'bSkipBlankRows'  // true: do not create new rows if k==0 and all values are empty (only needed for table-forms with db storage)

        'fn_DSPreStore'   // function to call in place of DSPreStore - sometimes it's easier to specify a function rather than make a derived class
 */
{
    protected $oDS = null;              // SEEDDataStore : derived classes must create this before calling SEEDForm constructor

    private $raParms = array();

    //internal
//    var $raCheckboxes = array();      // list of checkboxes in the formdef
//    var $raPresetOnInsert = array();  // list of fields that have forced default values (these are excluded from bSkipBlankRows test)
    private $raCtrlGlobal = array();      // store the global control parms after an Update, so the app can use them. May be kept persistent by a derived class.

    private $raCtrlCurrRow = array();     // (set by sfAu_xyz but accessors not implemented yet)

    function __construct( $cid = null, $raParms = array() )
    {
        parent::__construct( $cid );
        $this->raParms = $raParms;

        if( isset($this->raParms['fields']) ) {
            foreach( $this->raParms['fields'] as $fld => $ra ) {
//                if( @$ra['control'] == 'checkbox' )   $this->raCheckboxes[] = $fld;
//                if( @$ra['presetOnInsert'] == true )  $this->raPresetOnInsert[] = $fld;
//                if( @$ra['urlparm'] )                 $raDSParms['urlparms'][$fld] = $ra['urlparm'];
            }
        }

        // derived classes can create their own datasource objects before constructing this base class
        if( !$this->oDS )  $this->oDS = new SEEDDataStore( $raDSParms );
    }

    protected function sfValue( $k )
    /*******************************
        Methods in SEEDForm that fetch data from the data store should go through this method first.
     */
    {
        return( $this->oDS->Value( $k ) );
    }

    protected function sfSetValue( $k, $v )
    /**************************************
        Methods in this class that write data to the data store should go through this method first.
     */
    {
        $this->oDS->SetValue( $k, $v );
    }


    function Update( $raParms = array() )
    /************************************
        Get the form parms (rows and controls) for the current cid, serialized a la SEEDFormParms.  Normally http parms, but you can get them from any array.
        Update the current data store using those form parms.
        Return the updated data store object from the first row.  Usually, there is only one row anyway; otherwise, the application probably doesn't want them all returned anyway.
     */
    {
        $oRet = NULL;

        $bNoStore = (isset($raParms['bNoStore']) ? $raParms['bNoStore'] : false);       // true: load old values + new parms but don't store the changes

        $raHttpParms = $this->_updateLoadDeserialize( $raParms );
//echo "<BR/><PRE>"; var_dump($raHttpParms); echo "</PRE>";

        foreach( $raHttpParms['control'] as $k => $v ) {    // store the global control parms so the app can use them
            $this->CtrlGlobalSet( $k, $v );
        }

        foreach( $raHttpParms['rows'] as $r => $raRow ) {
            list($bLoaded, $bStorable) = $this->_updateLoad( $r, $raRow );
            if( $bLoaded ) {
                if( $bStorable && !$bNoStore ) {
                    $o = $this->_updateStore();
                } else {
                    // if user requested noStore or if no http parms, just get the DSObj
                    // N.B. this is highly implementation dependent since some derivations do the Load and parm overlay in the DS destination,
                    //      while some, such as DB derivations, do so in memory; this prevents the DB commit.
                    $o = $this->oDS->GetDataObj();
                }
                if( $oRet === NULL && $o != NULL ) $oRet =& $o;     // php4 makes a copy without &, which is probably not desired
            }
        }
        return( $oRet );
    }

    function Load( $raParms = array() )
    /**********************************
        Do the same as Update, except for committing to the data store.
        This is only for single-row applications.
        For in-memory data stores, this typically does the whole update operation because there's no special commit.

        Return true/false success.
     */
    {
        $bLoaded = false;

        $raHttpParms = $this->_updateLoadDeserialize( $raParms );

        foreach( $raHttpParms['control'] as $k => $v ) {    // store the global control parms so the app can use them
            $this->CtrlGlobalSet( $k, $v );
        }

        // Load the first row of httpParms (probably the only one) into the data store
        if( isset($raHttpParms['rows'][0]) ) {
            list($bLoaded, $bStorable) = $this->_updateLoad( 0, $raHttpParms['rows'][0] );
        }
        return( $bLoaded );
    }

    function Store()
    /***************
        Do the data store commit portion of the Update operation, for the current oDS.
        This is only for single-row applications.
        For in-memory data stores, this typically does nothing because the Load method did all the work in the data store anyway.
        This does the PreStore logic, though it's probably not useful in real implementations where Update is separated into Load/Store.

        Return true/false success.
     */
    {
        return( $this->_updateStore( false ) !== NULL );
    }


    private function _updateLoadDeserialize( $raParms )
    /**************************************************
        Get the deserialized http parms
     */
    {
        $raSerial = (isset($raParms['raSerial']) ? $raParms['raSerial'] : $_REQUEST);   // array of serialized parms to read
        $bGPC     = (isset($raParms['bGPC']) ? $raParms['bGPC'] : true);                // is that array GPC

        return( $this->oFormParms->Deserialize( $raSerial, $bGPC ) );
    }

    private function _updateLoad( $r, $raRow )
    /*****************************************
        For a single row, fetch/create the data store row, update from parms
        The steps of this procedure are broken out to simplify the creation of alternate load procedures.

        Returns true/false success.
     */
    {
        $bLoaded = false;
        $bStorable = false;

        if( !$this->_updateRow_bSkipRow( $r, $raRow ) &&  // Check if this row has data (don't create a new row if the user submitted a blank form)
            $this->oDS->Load( $raRow['k'], $r ) )         // Load up the current data store
        {
            $bLoaded = true;
            // copy the data, ops, etc from the http parms to the data store (current row only, if multiple rows were submitted in http parms)
            $bStorable = $this->_updateRow_SetFields( $raRow );
        }
        return( array( $bLoaded, $bStorable ) );
    }

    private function _updateStore()
    /******************************
        Do the Store phase of an Update on the current oDS
        Return the data obj of the DS on success; NULL on fail (or if PreStore returns false)
     */
    {
        // if the prestore succeeds, write the altered data store.
        // DSStore may decide to do nothing if the data has not changed, but data validation is normally done by DSPreStore
        $oRet = ( $this->oDS->PreStore() ? $this->oDS->Store() : NULL );
        if( $oRet ) {
            $this->oDS->PostStore( $oRet, $this );  // $this is the oForm, $oRet is an array of values, session var array, kfr, etc
        }
        return( $oRet );
    }

    private function _updateRow_bSkipRow( $r, $raRow )
    /*************************************************
        Return true if this row should be skipped
     */
    {
        $bSkip = false;
        if( @$this->raParms['bSkipBlankRows'] ) {
            // Don't create a new row when all submitted data fields are blank, and there is no row key.  If there is a row key, but
            // null data fields, do the update normally because that's a normal "clear" of an existing row.  This situation typically
            // happens when table forms map to  persistent rows in a db, and not all rows of the form are filled in.
            if( !$raRow['k'] ) {
                $bBlank = true;
                foreach( $raRow['values'] as $fld => $v ) {
//                    if( in_array( $fld, $this->raPresetOnInsert) )  continue;    // blank rows have this value preset, so skip it
                    if( !empty($v) ) $bBlank = false;
                }
                $bSkip = $bBlank;
            }
        }
        return( $bSkip );
    }

    private function _updateRow_SetFields( $raRow )
    /**********************************************
        Set the data from the given parms-row into the active data store.  Also perform ops.
        Return true if there are http parms that could cause some change to the datasource.
        N.B. nothing here checks whether any http values are different from the old datasource values, just that they are in the http parm stream.
     */
    {
        /* There is a paradigm where everything in the UI is defined using SEEDFormParms, and every page load invokes a SEEDForm::Update.
         * The problem with this is most page loads are just lookups, like clicking in a list, that only issue sfAk. So the Update loads
         * the datasource, calls this method (no changes occur to the datasource), then PreStores, Stores, and PostStores with the old values.
         * The code here attempts to short-cut that cycle by determining when no parms exist that will change the datasource.
         *
         * One problem: http checkboxes don't send a parm when you uncheck them. SEEDForm uses raCheckboxes to try to do the right thing,
         * but if you have a form containing nothing but checkboxes, and you uncheck one of them, there is no way to tell the difference
         * between that submission and a simple lookup of the row.  i.e. the only parm we get here is sfAk
         * So - never use this code with forms that only contain checkboxes. Throw in a text field or something, anything.
         */
        if( count($raRow['values']) == 0 && empty($raRow['op']) && count($raRow['control']) == 0)  return( false );

        /* Perform Ops
         */
        if( !empty($raRow['op']) ) {
            $this->oDS->Op( $raRow['op'] );
        }
        /* Set data fields
         */
        foreach( $raRow['values'] as $fld => $v ) {
            if( !isset($this->raParms['bTrimValue'][$fld]) || $this->raParms['bTrimValue'][$fld] ) {  // trim by default, or if trim===true
                $v = trim($v);
            }
            $this->sfSetValue( $fld, $v );
        }
        /* Account for checkboxes.
         * Checkboxes do not send HTTP parms if they are unchecked. If a checkbox is defined in this row,
         * and there is no parm, assume that the checkbox was unchecked to zero.
         */
//        foreach( $this->raCheckboxes as $fld ) {
//            if( !isset($raRow['values'][$fld]) ) {
//                $this->sfSetValue( $fld, 0 );
//            }
//        }
        /* Set row-level control fields
         */
        $this->raCtrlCurrRow = array();
        foreach( $raRow['control'] as $k => $v ) {
            $this->raCtrlCurrRow[$k] = $v;
        }
        if( ($kForce = @$this->raCtrlCurrRow['setkey']) ) {
            $this->oDS->SetKey( $kForce );
        }

        return( true );
    }

    function CtrlGlobal( $k )
    /************************
        Return the value of the given component-wide control parm.  Only available after Update() or Load().

        OVERRIDE to create a persistent storage
     */
    {
        return( @$this->raCtrlGlobal[$k] );
    }

    function CtrlGlobalIsSet( $k )
    /*****************************
        Sometimes you need to know the difference between empty and !isset
     */
    {
        return( isset( $this->raCtrlGlobal[$k] ) );
    }

    function CtrlGlobalSet( $k, $v )
    /*******************************
        OVERRIDE to create persistent storage
     */
    {
        $this->raCtrlGlobal[$k] = $v;
    }
}

/*
    function DrawElement_FormDef( $fld, $elemParms = array() )
    [*********************************************************
        Draw a single form element as defined in $this->raParms['formdef'].
        elemParms is passed to the specific method that draws the form element.

     *]
    {
        $s = "";

        if( !isset($this->raParms['formdef'][$fld] ) )  return( "" );
        $def = &$this->raParms['formdef'][$fld];

        if( isset($def['readonly']) )  $elemParms['readonly'] = $def['readonly'];

        $label = @$elemParms['bDrawLabel'] ? @$def['label'] : "";

        if( in_array( $fld, array('_sf_op_d','_sf_op_h','_sf_op_r') ) ) {
            $s .= "<INPUT type='checkbox' name='".$this->oFormParms->sfParmOp( substr($fld,-1,1), $this->iR )."' value='1'>";

            if( $label )  $s .= "&nbsp;".$label;
        } else {
            switch( @$def['type'] ) {
                case 'textarea':
                    $cols = isset($def['cols']) ? $def['cols'] : 60;
                    $rows = isset($def['rows']) ? $def['rows'] : 5;
                    $s .= $this->TextArea( $fld, $label, $cols, $rows, $elemParms );
                    break;

                case 'checkbox':
                    $s .= $this->Checkbox( $fld, $label, $elemParms );
                    break;

                case 'select':
                    $s .= $this->Select( $fld, $label, $def['selOptions'], $elemParms );
                    break;

                case 'hidden':
                    $s .= $this->Hidden( $fld );
                    break;

                case 'hidden_key':
                    $s .= $this->HiddenKey();
                    break;

                case 'text':
                default:
                    if( isset($def['size']) )  $elemParms['size'] = $def['size'];
                    $s .= $this->Text( $fld, $label, $elemParms );
                    break;
            }
        }

        return( $s );
    }
*/

class SEEDFormSearchControl extends SEEDFormElements
{
    function SearchControl( $raConfig )
    /**********************************
        Draw a search control with one or more filters.  Each filter has a field list, op list, and text input.

        raConfig = array( 'filters' => array( array( 'label'=>'field', ...list of fields for filter 1 ),
                                              array( 'label', 'field', ...list of fields for filter 2 ), ... ),
                          'template' => " HTML template  ",
                          'controls' => defs of [[controls]] found in the template e.g. select )

        An empty field means "match any of the other fields".  e.g. "---Any---"=>"", 'label'=>'field',...

        The template substitutes the tags [[fieldsN]], [[opN]], [[textN]], where N is the origin-1 filter index

        Default template just separates one row of tags with &nbsp;
     */
    {
        $s = isset($raConfig['template']) ? $raConfig['template'] : "[[fields1]]&nbsp;[[op1]]&nbsp;[[text1]]";

        /* Substitute defined controls
         */
        if( isset( $raConfig['controls']) ) {
            foreach( $raConfig['controls'] as $fld => $ra ) {
                if( strpos( $s, "[[$fld]]" ) !== false ) {
                    // The control exists in the template  (probably we should assume this and not bother to check)
                    if( $ra[0] == 'select' ) {
                        $currVal = $this->CtrlGlobal('srchctl_'.$fld);
                        $c = $this->Select2( 'srchctl_'.$fld, $ra[1], "", array( 'sfParmType'=>'ctrl_global', 'selected'=>$currVal) );
                        $s = str_replace( "[[$fld]]", $c, $s );
                    }
                }
            }
        }


        /* Substitute fields-op-text search controls
         */
        $iFilter = 1;
        foreach( $raConfig['filters'] as $raFld ) {
            $currFld = $this->CtrlGlobal('srch_fld'.$iFilter);
            $currOp  = $this->CtrlGlobal('srch_op'.$iFilter);
            $currVal = $this->CtrlGlobal('srch_val'.$iFilter);

            /* Collect the fields and substitute into the appropriate [[fieldsN]]
             */
            $raCols = array_merge( array("Any"=>''), $raFld );

            $c = $this->Select2( 'srch_fld'.$iFilter, $raCols, "", array('selected'=>$currFld, 'sfParmType'=>'ctrl_global') );

            $s = str_replace( "[[fields$iFilter]]", $c, $s );

            /* Write the [[opN]]
             */
            $c = $this->Select2( 'srch_op'.$iFilter,
                                 array( "contains" => 'like',     "equals" => 'eq',
                                        "starts with" => 'start', "ends with" => 'end',
                                        "less than" => 'less',    "greater than" => 'greater',
                                        "is blank" => 'blank' ),
                                 "",
                                 array('selected'=>$currOp, 'sfParmType'=>'ctrl_global') );
            $s = str_replace( "[[op$iFilter]]", $c, $s );

            /* Write the [[textN]]
             */
            $c = $this->Text( 'srch_val'.$iFilter, "", array('sfParmType'=>'ctrl_global', 'size'=>20) );
            $s = str_replace( "[[text$iFilter]]", $c, $s );

            ++$iFilter;
        }

        return( $s );
    }

    function SearchControlDBCond( $raConfig )
    /****************************************
     */
    {
        $raCond = array();

        /* For each defined control, get a condition clause
         */
        if( isset( $raConfig['controls']) ) {
            foreach( $raConfig['controls'] as $fld => $ra ) {
                if( $ra[0] == 'select' ) {
                    if( ($currVal = $this->CtrlGlobal('srchctl_'.$fld)) ) {
                        $raCond[] = "$fld='".addslashes($currVal)."'";
                    }
                }
            }
        }


        /* For each text-search row, get a condition clause
         */
        $iFilter = 1;
        foreach( $raConfig['filters'] as $raFld ) {
            $currFld = $this->CtrlGlobal('srch_fld'.$iFilter);
            $currOp  = $this->CtrlGlobal('srch_op'.$iFilter);
            $currVal = trim($this->CtrlGlobal('srch_val'.$iFilter));

            if( $currOp == 'blank' ) {
                // process this separately because the text value is irrelevant
                if( !empty($currFld) ) {  // 'Any'=blank is not allowed
                    $raCond[] = "($currFld='' OR $currFld IS NULL)";
                }
            } else if( !empty($currVal) ) {
                if( empty($currFld) ) {
                    // field "Any" is selected, so loop through the rest of the fields
                    $raC = array();
                    foreach( $raFld as $label => $v ) {
                        if( empty($v) )  continue;  // skip 'Any'
                        $raC[] = $this->_searchControlDBCondTerm( $v, $currOp, $currVal );
                    }

                    // glue the conditions together as disjunctions
                    $raCond[] = "(".implode(" OR ",$raC).")";
                } else {
                    $raCond[] = $this->_searchControlDBCondTerm( $currFld, $currOp, $currVal );
                }
            }

            ++$iFilter;
        }
        // glue the filters together as conjunctions
        $sCond = implode(" AND ", $raCond);

        return( $sCond );
    }

    function _searchControlDBCondTerm( $col, $op, $val )
    /***************************************************
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
}


class SEEDFormElements
/*********************
    Draw basic form elements using SEEDFormParms naming conventions

    Usage:
        SetCid()    - default is A
        SetRowNum() - default is 0
        call methods to draw form elements
 */
{
    protected $oFormParms = null;

    private $raStickyParms = array();   // apply these to all elements, overridden by local parms
    private $iR = 0;

    function __construct( $cid = null )
    /**********************************
     */
    {
        $this->oFormParms = new SEEDFormParms($cid);
    }

    public function GetSEEDFormParams() { return( $this->oFormParams ); }

    public function SetCid( $cid )  { $this->oFormParms->SetCid($cid); }
    public function GetCid()        { return( $this->oFormParms->GetCid() ); }
    public function SetRowNum( $iR ){ $this->iR = $iR; }
    public function GetRowNum()     { return( $this->iR ); }
    public function IncRowNum()     { $this->iR++; }

    // Sometimes you want the actual sfAp_ element name
    public function Name( $k )     { return( $this->oFormParms->sfParmName( $k, $this->iR ) ); }   // the actual name of the parm
    public function NameKey()      { return( $this->oFormParms->sfParmKey( $this->iR ) ); }        // the actual name of the key parm

    // Get/Set the datasource values
    public function Value( $k )    { return( $this->sfValue( $k ) ); }
    public function ValueEnt( $k ) { return( $this->sfValueEnt( $k ) ); }
    public function ValueDB( $k )  { return( addslashes( $this->sfValue( $k ) ) ); }
    public function SetValue( $k, $v ) { return( $this->sfSetValue( $k, $v ) ); }

    protected function sfValue( $k )     { return( NULL ); }    // Must override this in a derived class to get real parms from data storage (e.g. session vars, KFRecord)
    protected function sfValueEnt( $k )  { return( SEEDCore_HSC( $this->sfValue($k) ) ); }
    protected function sfSetValue( $k, $v )  { return( NULL ); }    // Must override this in a derived class to get real parms from data storage (e.g. session vars, KFRecord)

    public function SetStickyParms( $raParms )
    {
        $this->raStickyParms = array();                         // stdParms builds on raStickyParms so clear it first
        $this->raStickyParms = $this->stdParms( "", $raParms);  // normalize raParms and store it
    }

    function Hidden( $fld, $raParms = array() )
    /******************************************
        raParms['value'] overrides the value in the oDS. Normally, this is not used, but sometimes
        you want to set a hidden value for an app to use.
     */
    {
        $p = $this->stdParms( $fld, $raParms );
        $pName = $p['name'];
        $pValueEnt = $p['valueEnt'];
        $pAttrs = $p['attrs'];        // though not sure what this would contain

        return( "<input type='hidden' name='$pName' id='$pName' value='$pValueEnt' $pAttrs/>" );
    }

    function HiddenKeyParm( $k )
    /***************************
       Encode the given key as a sfParmKey
     */
    {
        $name = $this->oFormParms->sfParmKey($this->iR);
        return( "<input type='hidden' name='$name' id='$name' value='$k'>" );
    }

    /**
     * @param array $raParms: size, width, value, attrs, disabled, bPassword, sfParmType, bBootstrap, bsCol
     */
    function Text( $fld, $label = "", $parms = array() )
    /***************************************************
        parms can be array or string format

            value = force value
            sfParmType = get the value from an alternate SF namespace e.g. ctrl_global
            size = char width
            width = css width
            attrs = string of other attrs
            readonly = write as plain text and encode a HIDDEN control
            disabled = disabled
            bPassword = make it a password control
            bBootstrap = use bootstrap classes
            bsCol = bootstrapCol{,bootstrapCol}  for the text field{, and the label}  e.g. "md-4,md-2"
     */
    {
        $s = "";

        $raParms = $this->parseParms( $parms, $label );

        /* Do control-specific parms management
         */
        // if size is specified, and width isn't, add size='n' to the attrs.
        // if neither size nor width are specified, use default size
        if( !@$raParms['width'] && ($n = intval(@$raParms['size'])) ) {
            $raParms['attrs'] = "size='$n' ".@$raParms['attrs'];
        }

        // if bootstrap is enabled and bs columns are specified, add the appropriate classes
        $sBSCol = $sBSColLabel = "";
/*
        if( @$this->raParms['bBootstrap'] ) {
            $raParms['classes'] = "form-control ".@$raParms['classes'];
        }
        if( ($sBSCol = @$raParms['bsCol']) ) {
            $ra = explode( ',', $sBSCol );
            if( @$ra[0] ) $sBSCol = 'col-'.$ra[0];
            if( @$ra[1] ) $sBSColLabel = 'col-'.$ra[1];
            $raParms['classes'] = 'input-block-level '.@$raParms['classes'];
        }
*/

        /* Get standardized parm array
         */
        $p = $this->stdParms( $fld, $raParms );
        $pName = $p['name'];
        $pValue = $p['value'];
        $pValueEnt = $p['valueEnt'];
        $pAttrs = $p['attrs'];


        if( $p['readonly'] )  return( $pValueEnt . $this->Hidden($fld, $pValue) );

        if( !empty($label) ) {
            $labelClass = (@$raParms['bBootstrap'] || $sBSColLabel) ? "class='control-label'" : "";

            $s .= ($sBSColLabel ? "<div class='$sBSColLabel'>" : "")
                 ."<LABEL for='$pName' $labelClass>".SEEDCore_NBSP($label)."</LABEL>"
                 .($sBSColLabel ? "</div>" : "");
        }

        $s .= ($sBSCol ? "<div class='$sBSCol'>" : "")
             ."<input type='".($p['bPassword'] ? "password" : "text")."' "
                 ."name='$pName' id='$pName' value='$pValueEnt' $pAttrs />"
             .($sBSCol ? "</div>" : "");

        return( $s );
    }


// TODO: put cols and rows in raParms - see TextArea2
    private function _textArea( $fld, $label = "", $cols = 60, $rows = 5, $parms = array() )
    /******************************************************************************
        Create a TEXTAREA

        $raParms:  attrs, disabled
     */
    {
        $raParms = $this->parseParms( $parms );
        /* Do control-specific parms management
         */
        // if width/height is not specified, set cols/rows based on parms or defaults
        if( !@$raParms['width'] ) {
            if( !($n = @$raParms['nCols']) )  $n = $cols;
            $raParms['attrs'] = "cols='$n' ".@$raParms['attrs'];
        }
        if( !@$raParms['height'] ) {
            if( !($n = @$raParms['nRows']) )  $n = $rows;
            $raParms['attrs'] = "rows='$n' ".@$raParms['attrs'];
        }

        /* Get standardized parm array
         */
        $p = $this->stdParms( $fld, $raParms );
        $pName = $p['name'];
        $pValue = $p['value'];
        $pValueEnt = $p['valueEnt'];
        $pAttrs = $p['attrs'];

        if( $p['readonly'] )  return( $pValueEnt . $this->Hidden($fld, $pValue) );

        $s = "";

        if( !empty($label) )  $label = SEEDCore_NBSP($label.": ");

        $s .= $label."<textarea name='$pName' id='$pName' "
// added to attrs above
//             .($cols ? "cols='$cols' " : "")  // use 0 if width/height are in css
//             .($rows ? "rows='$rows' " : "")
             ."$pAttrs>"
             .$pValueEnt
             ."</textarea>";

        return( $s );
    }

    function TextArea( $fld, $parms = array() )
    {
        $raParms = $this->parseParms( $parms );
        /* Do control-specific parms management
         */
// nCols, nRows
        /* Get standardized parm array
         */
        $p = $this->stdParms( $fld, $raParms );

if( !@$p['nRows'] ) { $p['nRows'] = 5; }
if( !@$p['nCols'] ) { $p['nCols'] = 40; }
        $label = SEEDCore_ArraySmartVal( $raParms, 'sLabel', array("") );

        return( $this->_textArea( $fld, $label, $p['nCols'], $p['nRows'], $raParms ) );
    }

    /**
     * $raParms: checked=>1, attrs, disabled
     */
    function Checkbox( $fld, $label = "", $parms = array() )
    /*******************************************************
        Create a CHECKBOX control

        $raParms:  checked=>1, attrs, disabled
     */
    {
        $raParms = $this->parseParms( $parms );
        $p = $this->stdParms( $fld, $raParms );
        $pName = $p['name'];
        $pValue = $p['value'];
        $pAttrs = $p['attrs'];

        if( @$raParms['checked'] || !empty($pValue) )  $pAttrs .= " CHECKED";

        if( !empty($label) )  $label = SEEDCore_NBSP( " ".$label );

        return( "<input type='checkbox' name='$pName' id='$pName' value='1' $pAttrs />".$label );
    }

    function Radio( $fld, $value, $label = "", $parms = array() )
    /************************************************************
        Create a Radio control

        $raParms:  checked=>1, attrs, disabled
                   bNoBlankMatch=>1             don't check if $value is blank and value($fld) is blank - i.e. the radio button is a null choice but the init setting looks better with no buttons checked
     */
    {
        $raParms = $this->parseParms( $parms );
        $p = $this->stdParms( $fld, $raParms );
        $pName = $p['name'];
        $pValue = $p['value'];
        $pAttrs = $p['attrs'];

        if( @$raParms['checked'] ||
            ($pValue == $value && !(@$raParms['bNoBlankMatches'] && empty($value))) )
        {
            $pAttrs .= " CHECKED";
        }

        if( !empty($label) )  $label = SEEDCore_NBSP( " ".$label );

        return( "<input type='radio' name='$pName' id='$pName' value='".SEEDCore_HSC($value)."' $pAttrs />".$label );
    }

// TODO: implement OPTGROUP

    function Select( $fld, $raValues, $label = "", $parms = array() )
    /****************************************************************
        Create a SELECT control using $raValues = [label1=>val1, label2=>val2,...]

        $raParms: selected=>(value), attrs, disabled
     */
    {
        $raParms = $this->parseParms( $parms );
        $p = $this->stdParms( $fld, $raParms );
        $pName = $p['name'];
        $pValue = $p['value'];
        $pValueEnt = $p['valueEnt'];
        $pAttrs = $p['attrs'];

        $bEnableDisabledOptions = @$parms['bEnableDisabledOptions'];

        if( $p['readonly'] )  return( $pValueEnt . $this->Hidden($fld, $pValue) );

        if( !empty($label) ) {
            $label = "<LABEL for='$pName'>".SEEDCore_NBSP( $label )."</LABEL>";
        }

        $s = $label."<SELECT name='$pName' id='$pName' $pAttrs>";
        foreach( $raValues as $optLabel => $val ) {
            $raOpt = array();

            if( isset($raParms['selected']) && $raParms['selected'] == $val ) {
                $raOpt['selected'] = 1;
            }
            if( $bEnableDisabledOptions && $val=='_disabled_' ) {
                $raOpt['disabled'] = 1;
            }
            $s .= $this->Option( $fld, $val, $optLabel, $raOpt );
        }
        $s .= "</SELECT>";
        return( $s );
    }

    function Option( $fld, $value, $label, $raParms = array() )
    /**********************************************************
        Draw an option within a SELECT control

        $raParms:  selected=>1
                   disabled=>1
     */
    {
//TODO : get the standardized 'value' using stdParms() instead of using sfValue - so this control can be used in ctrl_global,ctrl_row
        $attrs = (@$raParms['selected'] || $this->sfValue($fld)==$value) ? " SELECTED" : "";
        if( @$raParms['disabled'] )  $attrs .= " disabled";

        return( "<OPTION value='".SEEDCore_HSC($value)."' $attrs>$label</OPTION>" );
    }

    function Date( $fld, $label = "", $parms = array() )
    {
        $raParms = $this->parseParms( $parms );
        $p = $this->stdParms( $fld, $raParms );
        $pName = $p['name'];
        $pValue = $p['value'];
        $pValueEnt = $p['valueEnt'];
        $pAttrs = $p['attrs'];

        return( "<input name='$pName' id='$pName' value='$pValueEnt' type='date'/>" );
    }

    function Email( $fld, $label = "", $parms = array() )
    {
        $raParms = $this->parseParms( $parms );
        $p = $this->stdParms( $fld, $raParms );
        $pName = $p['name'];
        $pValue = $p['value'];
        $pValueEnt = $p['valueEnt'];
        $pAttrs = $p['attrs'];

        return( "<input name='$pName' id='$pName' value='$pValueEnt' type='email'/>" );
    }


    /***** SEEDFormDate *****/
/*
    private $bDateCalInit = false;
    function Date( $fld, $label = "", $parms = array() )
    [***************************************************
        raParms:
            readonly, size, attrs
            dateValue = optionally force the initial value
     *]
    {
        $raParms = $this->parseParms( $parms );
        $p = $this->stdParms( $fld, $raParms );
        $pName = $p['name'];
        $pValue = $p['value'];
        $pValueEnt = $p['valueEnt'];
        $pAttrs = $p['attrs'];

        if( $p['readonly'] )  return( $pValueEnt . $this->Hidden($fld, $pValue) );

        $s = "";
        $nSize = !empty($raParms['size']) ? $raParms['size'] : 10;
        $dateValue = isset($raParms['dateValue']) ? $raParms['dateValue'] : $pValue;

        if( !empty($label) ) {
            $s .= "<LABEL for='$pName'>".SEEDCore_NBSP($label)."</LABEL>";
        }

        include_once( "SEEDDate.php" );
        $oCal = new SEEDDateCalendar();
        if( !$this->bDateCalInit ) {
            $s .= $oCal->Setup();
            $this->bDateCalInit = true;
        }
        $s .= $oCal->DrawCalendarControl( $pName, $dateValue, $nSize, $raParms );
        return( $s );
    }

    function DateTD( $fld, $label, $raParms = array() )
    [**************************************************
     *]
    {
        $s = $this->Date($fld, "", $raParms);
        return( $this->_putInTD( $s, $label, $raParms ) );
    }
*/

    function TextTD( $fld, $label = "", $raParms = array() )
                        { return( $this->putInTD( $this->Text($fld, "", $raParms),
                                                  $label, $raParms ) ); }

    function TextAreaTD( $fld, $label = "", $raParms = array() )
                        { return( $this->putInTD( $this->TextArea($fld, "", $raParms),
                                                  $label, $raParms ) ); }

    function CheckboxTD( $fld, $label = "", $raParms = array() )
                        { return( $this->putInTD( $this->Checkbox($fld, "", $raParms),
                                                  $label, $raParms ) ); }

    function RadioTD( $fld, $value, $label = "", $raParms = array() )
                        { return( $this->putInTD( $this->Radio($fld, $value, "", $raParms),
                                                  $label, $raParms ) ); }

    private function putInTD( $sRightCol, $label, $raParms )
    /*******************************************************
        Put a control in a pair of TD cells
     */
    {
//Implement Bootstrap columns

        // labels should be non-breaking and make sure there's at least one &nbsp; in an empty table cell
        $label = ($label = $label ?: @$raParms['sLabel']) ? SEEDCore_NBSP($label) : "&nbsp;";

        $attrsTD1 = @$raParms['attrsTD1'];
        $attrsTD2 = @$raParms['attrsTD2'];
        $sRightHead = @$raParms['sRightHead'];
        $sRightTail = @$raParms['sRightTail'];

        return( "<td valign='top' $attrsTD1><label>$label</label></td>"
               ."<td valign='top' $attrsTD2>".$sRightHead.$sRightCol.$sRightTail."</td>" );
    }

    private function parseParms( $parms, $label = "" )
    /*************************************************
        Control parms can be given as arrays like array( 'readonly'=>true, 'width'=>'200px' )
        or strings like "readonly width=200px"  (as well, "width:200px" will work)

        Figure out which one we got, and turn it into an array
     */
    {
        $raParms = array();

        /* If $parms is an array, use it.  If it's a string, parse it into an array.
         */
        if( is_array($parms) ) {
            $raParms = $parms;
        } else if( is_string($parms) ) {
            // parse "readonly width:200px height=400px" into array('readonly'=>true,'width'=>'200px','height'=>'400px')
            $ra = explode( ' ', $parms );
            foreach( $ra as $item ) {
                $item = trim($item);
                if( empty($item) )  continue;

                if( strpos( $item, "=" ) !== false ) {
                    list($k,$v) = explode( '=', $item, 2 );
                    $raParms[$k] = $v;
                } else if( strpos( $item, ":" ) !== false ) {
                    list($k,$v) = explode( ':', $item, 2 );
                    $raParms[$k] = $v;
                } else {
                    $raParms[$item] = true;
                }
            }
        }

        // this overrides an sLabel in the parms
        if( $label ) {
            $raParms['sLabel'] = $label;
        }

        return( $raParms );
    }

    private function stdParms( $fld, $raParms )
    /******************************************
        Normalize standard parameters.
        Call this with $fld=='' and $raStickyParms=array() to normalize raStickyParms.
        Call with per-element parms to get the combination of local+sticky parms.

        name              out  the http name of the given field
        value        in / out  force value (otherwise it is taken from the oDS or the alternate namespace)
        valueEnt          out  the HSC of 'value'
        sfParmType   in / out  get and encode the value in an alternate SF namespace e.g. ctrl_global, ctrl_row
        classes      in        class(es) to put in the class attr
        raStyles     in        style(s) to put in the style attr
        readonly     in / out  write as plain text and encode a HIDDEN control
        disabled     in        disabled='disabled' is added to the 'attrs'
        attrs        in / out  attribute string to be inserted into the control element
        raAttrs      in / out
        width        in        css value that we put into the style attr
        height       in        css value that we put into the style attr
        bPassword    in / out  turn Text controls into password controls
     */
    {
        $p = array();        // output array

        /* name : the http name of the given field
         * value : can be forced, or gotten from the oDS or alternate namespaces
         *
         * Only do these if $fld is given - it is not available when SetStickyParms calls this
         */
        if( $fld ) {
            // sfParmType : can be "", ctrl_global, ctrl_row (and possibly others if sfParmName does the right thing)
            $p['sfParmType'] = @$raParms['sfParmType'] ?: "";

            $p['name'] = $this->oFormParms->sfParmName( $fld, $this->iR, $p['sfParmType'] );

            if( isset($raParms['value']) ) {
                $v = $raParms['value'];
            } else {
                switch( $p['sfParmType'] ) {
                    case 'ctrl_global': $v = $this->CtrlGlobal($fld);     break;
                    case 'ctrl_row':    $v = @$this->raCtrlCurrRow($fld); break;
                    default:            $v = $this->sfValue($fld);        break;
                }
            }
            $p['value'] = $v;
            $p['valueEnt'] = SEEDCore_HSC($v);
        }


        // string of attrs that is difficult to override but can be specified in formdef tags
        $p['attrs'] = @$this->raStickyParms['attrs'].@$raParms['attrs'];

        // array of attrs : easier to work with (local attrs override sticky)
        $p['raAttrs'] = array_merge( @$this->raStickyParms['raAttrs'] ?: array(),
                                     @$raParms['raAttrs'] ?: array() );

        // content of the 'class' attr (local override sticky)
        // Named plural because in formdef tags it looks like a class= attr otherwise (which it is anyway)
        $p['classes'] = @$raParms['classes'] ?: @$this->raStickyParms['classes'];

        // content of the 'style' attr (local override sticky)
        // Not as useful as raStyles but can be specified in formdef tags
        $p['styles'] = @$raParms['styles'] ?: @$this->raStickyParms['styles'];

        // array of css styles, with many permutations of overrides (sticky raStyles, raAttrs['style'], width, ...)
        $p['raStyles']  = array_merge( @$this->raStickyParms['raStyles'] ?: array(),
                                       @$raParms['raStyles'] ?: array() );

        // readonly, bPassword : normalized and stored
        $p['readonly']   = isset($raParms['readonly'])  ? ($raParms['readonly']==true)  : (@$this->raStickyParms['readonly']==true);
        $p['bPassword']  = isset($raParms['bPassword']) ? ($raParms['bPassword']==true) : (@$this->raStickyParms['bPassword']==true);

        // width, height : css values that we add to the style attr
        if( ($w = @$raParms['width']) )  { $p['raStyles']['width'] = $w; }
        if( ($h = @$raParms['height']) ) { $p['raStyles']['height'] = $w; }

        // disabled elements get the disabled='disabled' attr
        if( @$raParms['disabled'] )  $p['raAttrs']['disabled'] = "disabled";

        /* Finish by assembling the attrs, but only if this is being called by an element method
         */
        if( $fld ) {
            if( isset($p['classes']) ) $p['raAttrs']['class'] = $p['classes'];
            if( isset($p['styles']) )  $p['raAttrs']['style'] = $p['styles'];

            if( isset($p['raStyles']) ) {
                @$p['raAttrs']['style'] .= SEEDCore_ArrayExpandSeriesWithKey( $p['raStyles'], "[[k]]:[[v]];" );
            }

            $p['attrs'] .= SEEDCore_ArrayExpandSeriesWithKey( $p['raAttrs'], " [[k]]='[[v]]'" );
        }

        return( $p );
    }
}


class SEEDFormExpand extends SEEDFormElements
{
    private $oTag = null;


    function ExpandForm( $sTemplate )
    /********************************
        [[foo | size:50 abc:def | attrs ]]  makes a Text control called foo, size and abc interpreted, and attrs placed verbatim in the control
        [[Radio:foo ...]]                   makes a Radio control

        Table structure:
        ||| col1             || col2
        ||| {td attrs} col1  || {td attrs} col2
        ||| {colspan='2'} col1 and col2

        *col* is transformed to <label>col</label> after all processing

        Currently, either a template starts with "||| ", meaning it's a sequence of rows (with an implied </td></tr> at the end)
        or it doesn't which means no table structure.
     */
    {
        $s = "";

        if( !$this->oTag ) {
            $this->oTag = new SEEDTagParser( array( 'fnHandleTag'=>array($this,'fnExpandHandleTag')) );
        }
        $sTemplate = $this->oTag->ProcessTags( $sTemplate );

        // if the template has structural codes e.g. table structure, expand those after the controls so we don't get the | chars mixed up

        $bIsTable = (substr( $sTemplate, 0, 4 ) == "||| ");
        if( $bIsTable ) {
            $raRows = explode( "||| ", substr($sTemplate,4) );  // skip the first ||| to prevent an empty first element
            foreach( $raRows as $row ) {
                $s .= "<tr valign='top'>";
                $raCols = explode( "|| ", $row );
                foreach( $raCols as $col ) {
                    $tdattrs = "";
                    $col = trim($col);

                    // {attrs}
                    $s1 = strpos( $col, "{" );
                    $s2 = strpos( $col, "}" );
                    if( $s1 !== false && $s2 !== false ) {
                        $tdattrs = " ".substr( $col, $s1 + 1, $s2 - $s1 - 1 );
                        $col = substr( $col, $s2 +1 );
                    }

                    // *label*
                    if( substr( $col, 0, 1 ) == '*' && substr( $col, -1, 1 ) == '*' ) {
                        $col = "<label>".substr( $col, 1, -1 )."</label>";
                    }
                    $s .= "<td$tdattrs>$col</td>";
                }
                $s .= "</tr>";
            }
        } else {
            $s = $sTemplate;
        }

        return( $s );
    }

    function ResolveTag( $raTag, SEEDTagParser $oTagDummy = NULL, $raParms = array() )      // NULL allows $this->fnExpandHandleTag to pass null
    /****************************************************************
        Call here from SEEDTagParser::HandleTag to resolve tags having to do with templates

        bRequireFormPrefix:  [[Text:]]  [[Checkbox:]] have to be [[FormText:]] [[FormCheckbox:]]
                             Use this in template contexts where multiple resolvers could compete.
                             Most importantly, [[foo]] is not a synonym for [[Text:foo] ; you have to use [[FormText:foo]]
     */
    {
        $s = "";
        $bHandled = true;

        $bRequireFormPrefix = SEEDCore_ArraySmartVal( $raParms, 'bRequireFormPrefix', array(false,true) );

        // [[ {ControlType:}fieldname | stdparms | attrs ]]
        $sControlType = strtolower($raTag['tag']);
        $sFieldname = $raTag['target'];    // the p0 element is the field name (if the tag is a form control)
        $pStdParms = $this->parseParms(@$raTag['raParms'][1]);
        if( @$raTag['raParms'][2] ) {
            // other attrs that will be simply inserted into the element (don't use attrs in the stdparms section)
            $pStdParms['attrs'] = $raTag['raParms'][2];
        }

        if( $bRequireFormPrefix && substr($sControlType, 0, 4) != 'form' ) {
            $bHandled = false;
            goto done;
        }

        switch( $sControlType ) {
            // different than Text:readonly which also writes a hidden value
            case 'formvalue':
            case 'value':        $s = $this->ValueEnt($sFieldname);                    break;

            // if no tag specified, assume the target is a text field (only if !bRequireFormPrefix)
            case '':
            case 'formtext':
            case 'text':         $s = $this->Text( $sFieldname, "", $pStdParms );      break;

            case 'formtextarea':
            case 'textarea':     $s = $this->TextArea2( $sFieldname, $pStdParms );     break;

            case 'formcheckbox':
            case 'checkbox':     $s = $this->Checkbox( $sFieldname, "", $pStdParms );  break;

            case 'formdate':
            case 'date':         $s = $this->Date( $sFieldname, "", $pStdParms );      break;

            case 'formhidden':
            case 'hidden':       $s = $this->Hidden2( $sFieldname, $pStdParms );        break;

            default:
                $bHandled = false;
                break;
        }

        done:
        return( array($bHandled,$s) );
    }

    function fnExpandHandleTag( $raTag )
    {
        // the SEEDTagParser callback just expects a string - maybe it should be expecting the same as ResolveTag
        list($ok,$s) = $this->ResolveTag( $raTag, NULL, array() );
        if( !$ok && $this->oTag ) $s = $this->oTag->HandleTag( $raTag );    // the base SEEDTagParser::HandleTag for fundamental tags
        return( $s );
    }
}

?>
