<?php

/* SEEDUI.php
 *
 * Copyright (c) 2013-2018 Seeds of Diversity Canada
 *
 * Classes that manage control parms for forms, UI components, and SEEDForms.
 */


class SEEDUI
/***********
    UI's generally have widgets with control parms and typical buttons/controls such as Edit, New, Delete.
    This class renders links and buttons that propagate control parms in a consistent manner.
    Any typical UI system should be able to implement links and buttons by overriding minor parts of this.

    Conventional parms include:
        sf[[cid]]ui_k      = the key of the current element of component cid (could be a list, a form, a drop-down, etc)
        sf[[cid]]ui_i      = the view-offset of the current element of component cid (e.g. 0-based placement in a list)
        sf[[cid]]ui_bNew   = a new element has been ordered for component cid
        sf[[cid]]ui_kDel   = command to delete an element of component cid
        sf[[cid]]ui_iWO    = the window offset of component cid
        sf[[cid]]ui_nWS    = the window size of component cid

    See definitions of Views and Windows in SEEDComponent.
 */
{
    protected $cid;
    protected $raParms;
    protected $lang;       // used for default button labels

    public $kCurr = 0;     // the key of the current element in some list or table
    private $iCurr = 0;    // another way of representing the current item in a list or table
    public $bNew = false;  // true if a new element is being made
    public $kDel = 0;      // non-zero for an element being deleted

    // uiParms are stored this way for extensibility:
    // derived classes can add arbitrary parms and the base class can iterate to manage them
    private $raUIParms = array();

    function __construct( $cid = 'A', $raParms = array() )
    {
        $this->cid = $cid;
        $this->raParms = $raParms;
        $this->lang = @$raParms['lang'] == 'FR' ?: "EN";

        // Initialize the uiParms. It is done one by one through a protected method so derived classes can do this too.
        // The constructor raParms can contain initial values and alternate http names
        // e.g. $raParms['raUIParms']['iCurr'] = array( 'name'=>'xfui[[cid]]_iHere','v'=>1234 )
        foreach( array( 'kCurr'         => array( 'name'=>'sf[[cid]]ui_k',    'v'=>0 ),
                        'iCurr'         => array( 'name'=>'sf[[cid]]ui_i',    'v'=>0 ),
                        'bNew'          => array( 'name'=>'sf[[cid]]ui_bNew', 'v'=>0 ),
                        'kDel'          => array( 'name'=>'sf[[cid]]ui_kDel', 'v'=>0 ),
                        'iWindowOffset' => array( 'name'=>'sf[[cid]]ui_iWO',  'v'=>0 ),
                        'nWindowSize'   => array( 'name'=>'sf[[cid]]ui_nWS',  'v'=>0 ) )
                 as $k => $ra )
        {
            $this->InitUIParm( $k, @$raParms['raUIParms'][$k] ?: $ra );
        }

        // Set default attrs for links and forms
        foreach( array( 'sListUrlPage'   => $_SERVER['PHP_SELF'],
                        'sListUrlTarget' => "_top",
                        'sFormAction'    => $_SERVER['PHP_SELF'],
                        'sFormMethod'    => "post",
                        'sFormTarget'    => "_top" )
                 as $k => $v )
        {
            if( empty($this->raParms[$k]) ) $this->raParms[$k] = $v;
        }
    }

    public function InitUIParm( $k, $ra )
    /************************************
        Derived classes can call this to add custom uiparms.

        The constructor can also take $raParms['raUIParms'][parmname]['v'] to set a custom uiparm or set a value to a base uiparm.
        (That's a good way for derived classes to set uiparm values stored e.g. in session vars)

        If parms are found in _REQUEST those override all other sources.
     */
    {
        if( !isset($this->raUIParms[$k] ) ) {
            $this->raUIParms[$k] = $ra;
        } else {
            // Redefine the name, value, or both
            if( isset($ra['name']) )  $this->raUIParms[$k]['name'] = $ra['name'];
            if( isset($ra['v']) )     $this->setUIParm( $k, $ra['v'] );
        }

        // Set the cid within the parm's http name
        $this->raUIParms[$k]['name'] = str_replace( "[[cid]]", $this->cid, $this->raUIParms[$k]['name'] );

        // Override any uiParm value with http (after the [[cid]] replacement)
        if( isset($_REQUEST[$this->raUIParms[$k]['name']]) ) {
            //This assumes every uiParm is integer
            $this->SetUIParm( $k, SEEDInput_Int($this->raUIParms[$k]['name']) );
        }
    }

    public function Get_kCurr()         { return( $this->GetUIParm('kCurr') ); }
    public function Get_iCurr()         { return( $this->GetUIParm('iCurr') ); }
    public function Get_bNew()          { return( $this->GetUIParm('bNew') ); }
    public function Get_kDel()          { return( $this->GetUIParm('kDel') ); }
    public function Get_iWindowOffset() { return( $this->GetUIParm('iWindowOffset') ); }
    public function Get_nWindowSize()   { return( $this->GetUIParm('nWindowSize') ); }

    public function Set_kCurr( $k )         { $this->SetUIParm('kCurr', $k ); }
    public function Set_iCurr( $i )         { $this->SetUIParm('iCurr', $i ); }
    public function Set_bNew( $b )          { $this->SetUIParm('bNew', $b ); }
    public function Set_kDel( $k )          { $this->SetUIParm('kDel', $k ); }
    public function Set_iWindowOffset( $i ) { $this->SetUIParm('iWindowOffset', $i ); }
    public function Set_nWindowSize( $i )   { $this->SetUIParm('nWindowSize', $i ); }

    // friend classes please only use these for your own custom uiparms - otherwise use the methods above
    public function GetUIParm( $k )        { return( @$this->raUIParms[$k]['v'] ); }
    protected function SetUIParm( $k, $v ) { $this->raUIParms[$k]['v'] = $v; }

    protected function TranslateParms( $ra, $raUserParms = array() )
    /***************************************************************
        This maps parm names from whatever names you use in your method calls to whatever names you want in http.

        raUserParms is available for derived classes to pass special parameters - it is propagated through Link, HRef, LinkParms, etc

        // Derived class could do this:
        $raOut = array();
        $ra = parent::TranslateParms($ra);
        foreach( $ra as $k => $v ) {
            if( $k == 'foo' )
                $raOut['bar'] = $v;
            else
                $raOut[$k] = $v;
        }
        return( $raOut );
     */
    {
        // As a convenience, we map typical alias parm names to the http parm names.
        if( !@$this->raParms['bDisableAliases'] ) {
            foreach( $this->raUIParms as $k => $raDummy ) {
                if( isset($ra[$k]) ) {
                    $ra[$this->raUIParms[$k]['name']] = $ra[$k];
                    unset( $ra[$k] );
                }
            }
        }

        // Base behaviour is to add the kCurr parm if it is not specified explicitly
        // A lot of the time kCurr will be specified because that's how you make links to non-current records on the screen,
        // but if you're just making a link with a control parm (e.g. filter, sort) you'll want kCurr to be propagated.
        if( !isset($ra[$this->raUIParms['kCurr']['name']]) /* here too? */       )  $ra[$this->raUIParms['kCurr']['name']] = $this->Get_kCurr();
        if( !isset($ra[$this->raUIParms['iCurr']['name']]) && $this->Get_iCurr() )  $ra[$this->raUIParms['iCurr']['name']] = $this->Get_iCurr();

        return( $ra );  // the base implementation likes the regular parm names
    }

    public function HRef( $ra = array(), $raUserParms = array() )    // userParms can be used by derived classes
    {
        return( "href='".$this->Link($ra, $raUserParms)."' target='".$this->raParms['sListUrlTarget']."'" );
    }

    public function Link( $ra = array(), $raUserParms = array() )    // userParms can be used by derived classes
    {
        return( $this->raParms['sListUrlPage']."?".$this->LinkParms($ra,$raUserParms) );
    }

    protected function LinkParms( $ra = array(), $raUserParms = array() )    // userParms can be used by derived classes
    {
        return( SEEDCore_ParmsRA2URL( $this->TranslateParms($ra,$raUserParms) ) );
    }

    public function HiddenKCurr()
    {
        // go through HiddenFormParms in case a derived class overrides it or TranslateParms
        return( $this->HiddenFormParms( array( 'kCurr' => $this->Get_kCurr() ) ) );
    }

    public function HiddenFormParms( $ra = array() )
    {
        $s = "";

        $ra = $this->TranslateParms( $ra );
        foreach( $ra as $k => $v ) {
            $s .= "<input type='hidden' id='$k' name='$k' value='".SEEDCore_HSC($v)."'/>";
        }
        return( $s );
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
        Draw a set of controls in a <FORM> that propagates the given parms.
     */
    {
        $sAttr = @$raParms['bInline'] ? "style='display:inline'" : "";
        $sOnSubmit = @$raParms['onSubmit'] ? " onSubmit='{$raParms['onSubmit']}'" : "";

        $s = "<form $sAttr action='{$this->raParms['sFormAction']}' method='{$this->raParms['sFormMethod']}'"
            .(empty($this->raParms['sFormTarget']) ? "" : " target='{$this->raParms['sFormTarget']}'")
            .$sOnSubmit
            .">"
            .$sControls
            .$this->HiddenFormParms( $raHidden )
            ."</form>";

         return( $s );
    }
}


class SEEDUIList
{
    private $oUI;

    function __construct( SEEDUI $oUI )
    {
        $this->oUI = $oUI;

        // Create these UI parms for the interactive list widget
        // sortup is mutex to sortdown: they are the origin-1 index of the column being sorted
        // This is done using a number because raUIParms (currently) can only contain integers
        $this->oUI->InitUIParm( 'sortup',   array( 'name'=>'sf[[cid]]ui_sortup',   'v'=>0 ) );
        $this->oUI->InitUIParm( 'sortdown', array( 'name'=>'sf[[cid]]ui_sortdown', 'v'=>0 ) );
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
                $raRow[$kCol] = SEEDStd_HSC( $vCol );
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
            nWindowSize           = number of rows to draw in the window, required
            iCurr                 = View index of the current row, optional (default 0)

            cols                  = as ListDrawBasic
            tableWidth            = as ListDrawBasic
            fnRowTranslate        = as ListDrawBasic

            bUse_key              = propagate the _key column as the kCurr uiParm - this is necessary for the caller to re-locate a current row after a sort

//          bNewAllowed           = true if the list is allowed to set links that create new records
     */
    {
        $s = "";

        $oLW = new SEEDUIListWindow();
        $oLW->InitListWindow( array(
            //'iViewOffset'    => intval(@$raParms['iViewOffset']),
            'nViewSize'     => (@$raParms['nViewSize'] ? $raParms['nViewSize'] : count($raViewRows)),
            'iWindowOffset' => $this->oUI->Get_iWindowOffset(),
            'nWindowSize'   => @$raParms['nWindowSize'],
            'iCurrOffset'   => $this->oUI->Get_iCurr()
        ) );


        $nWindowRowsAbove = $oLW->RowsAboveWindow();
        $nWindowRowsBelow = $oLW->RowsBelowWindow();
        $raScrollOffsets  = $oLW->ScrollOffsets();
        $iViewOffset      = intval(@$raParms['iViewOffset']);
        $iWindowOffset    = $nWindowRowsAbove;

        //$bNewAllowed = intval(@$raParms['bNewAllowed']);


        $iSortup = $iSortdown = 0;
        $raSortSame = array();
        if( ($iSortup = $this->oUI->GetUIParm('sortup')) ) {
            $raSortSame = array( 'sortup'=>$iSortup );
        } else if( ($iSortdown = $this->oUI->GetUIParm('sortdown')) ) {
            $raSortSame = array( 'sortdown'=>$iSortdown );
        }

        /* List Header and Footer
         */
        $sHeader = "<tr>";
        $c = 1;
        foreach( $raParms['cols'] as $raCol ) {
            $bSortingUp   = $iSortup==$c;
            $bSortingDown = $iSortdown==$c;

            $sCrop = ($bSortingDown ? "position:absolute; top:-14px; left:-20px; clip: rect( 19px, auto, auto, 20px );" :
                      ($bSortingUp ? "position:absolute; top:4px; left:-20px; clip: rect( 0px, auto, 6px, 20px );" :
                                     "" ) );
            $href   = ( $bSortingDown ? $this->oUI->HRef(array("iCurr"=>0,"sortup"   => $c, "sortdown"=>0)) :
                       ($bSortingUp   ? $this->oUI->HRef(array("iCurr"=>0,"sortdown" => $c, "sortup"=>0)) :
                                        $this->oUI->HRef(array("iCurr"=>0,"sortup"   => $c, "sortdown"=>0)) ));

            $sColStyle = "font-size:small;";
            if( ($p = @$raCol['align']) )  $sColStyle .= "text-align:$p;";
            if( ($p = @$raCol['w']) )      $sColStyle .= "width:$p;";

            $sHeader .= "<th style='$sColStyle;vertical-align:baseline'>"
                       ."<a $href>".$raCol['label']
                       .($bSortingUp || $bSortingDown
                          ? ("&nbsp;<div style='display:inline-block;position:relative;width:10px;height:12px;'>"
                           ."<img src='".W_ROOT_STD."img/triangle_blue.png' style='$sCrop' border='0'/></div>")
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
                  //.$this->_listButton( "[10]", array( 'limit'=>10 ) ).SEEDStd_StrNBSP("",5)
                  //.$this->_listButton( "[50]", array( 'limit'=>50 ) ).SEEDStd_StrNBSP("",5)
                  // special case: the list can't yet compute scroll-up links when this button is chosen so offset must be cleared (see note above)
                  //.$this->_listButton( "[All]", array( 'limit'=>-1, 'offset'=>$raScrollOffsets['top'] ) );
                  //.SEEDStd_StrNBSP("",15)
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
            $raViewSlice = array_slice( $raViewRows, $iWindowOffset - $iViewOffset, $raParms['nWindowSize'] );
        } else {
            // get the window as needed
            $raViewSlice = $this->ListFetchViewSlice( $iWindowOffset, $raParms['nWindowSize'] );
        }


        /* Links to activate a row as the current row when it is clicked
         */
// ListDrawBasic can be enabled to do this, given the iWindowOffset, raSortSame**, bUseKey, bMakeLink, and the app has to use sfLui_k instead of kVi
        for( $i = 0; $i < count($raViewSlice); ++$i ) {
            $ra = array_merge( $raSortSame, array( 'iCurr' => $i+$iWindowOffset, 'iWindowOffset' => $iWindowOffset ) );
            $ra['kCurr'] = (@$raParms['bUse_key'] && ($k = @$raViewSlice[$i]['_key'])) ? $k : 0;
            $raViewSlice[$i]['sfuiLink'] = $this->oUI->Link( $ra );
        }

        $raBasicListParms = array(
            'cols' => $raParms['cols'],
            'tableWidth' => $raParms['tableWidth'],
            'fnRowTranslate' => (@$raParms['fnRowTranslate'] ? $raParms['fnRowTranslate'] : null),

            'sHeader' => $sHeader,
            'sFooter' => $sFooter,
            'sTop' => $sTop,
            'sBottom' => $sBottom,
            'iCurrRow' => $this->oUI->Get_iCurr() - $iWindowOffset,
        );
        $s .= $this->ListDrawBasic( $raViewSlice, 0, $raParms['nWindowSize'], $raBasicListParms );

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

        $s = "<a ".$this->oUI->HRef($raChange)." style='color:white;text-decoration:none;font-size:7pt;'>"
            ."<b>$label</b>"
            .($img ? ("&nbsp;<div style='display:inline-block;position:relative;width:10px;height:12px;'>"
                           ."<img src='".W_ROOT_STD."img/triangle_blue.png' style='$sCrop' border='0'/></div>") : "")
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
            $n = SEEDStd_Range( $this->nViewSize - $this->iWindowOffset - $this->nWindowSize, 0 );
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

        $offset = SEEDStd_Range( $this->iCurrOffset - intval($this->nWindowSize/2),
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
        $ra['bottom']   = SEEDStd_Range( $this->nViewSize - $this->nWindowSize,     0 );
        $ra['up']       = SEEDStd_Range( $this->iWindowOffset - 1,                  0, $ra['bottom'] );
        $ra['down']     = SEEDStd_Range( $this->iWindowOffset + 1,                  0, $ra['bottom'] );
        $ra['pageup']   = SEEDStd_Range( $this->iWindowOffset - $this->nWindowSize, 0, $ra['bottom'] );
        $ra['pagedown'] = SEEDStd_Range( $this->iWindowOffset + $this->nWindowSize, 0, $ra['bottom'] );

        return( $ra );
    }

    function CurrRowIsOutsideWindow()
    {
        if( $this->iCurrOffset == -1 ) return(false);   // no current row

        return( $this->bWindowLimited &&
                ($this->iCurrOffset < $this->iWindowOffset || $this->iCurrOffset >= $this->iWindowOffset + $this->nWindowSize) );
    }
}


?>
