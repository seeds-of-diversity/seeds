<?php

/* SEEDUIWidgets.php
 *
 * Copyright (c) 2013-2020 Seeds of Diversity Canada
 *
 * Implement some basic SEEDUI widgets
 */

class SEEDUIWidget_Base
/**********************
    All widgets extend from this class
 */
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


    function Init1_NotifyUIParms( $raOldParms, $raNewParms )
    {
        $raAdvisories = array();

        if( ($i = intval($this->oComp->GetUIParm('sortup'))) && ($col = @$this->raConfig['cols'][$i-1]['col']) ) {
            // sortup sorts the i'th column
            $this->oComp->SetUIParm( 'sSortCol', $col );
            $this->oComp->SetUIParm( 'bSortDown', 0 );
        }
        if( ($i = intval($this->oComp->GetUIParm('sortdown'))) && ($col = @$this->raConfig['cols'][$i-1]['col']) ) {
            // sortdown sorts the i'th column
            $this->oComp->SetUIParm( 'sSortCol', $col );
            $this->oComp->SetUIParm( 'bSortDown', 1 );
        }

        return( $raAdvisories );
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

            iCurrWindowRow = the element of $raList that is the current row (<iOffset or >=iOffset+nSize means no current row is shown)
                            default is -1 (or more generally <0), which is always no-row
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
        $iCurrWindowRow = SEEDCore_ArraySmartVal1( $raParms, 'iCurrWindowRow', -1, true );          // if empty not allowed, 0 is interpreted as empty and converted to -1 !
        //if( $iCurrWindowRow < $iOffset || $iCurrWindowRow >= $iOffset + $nSize )  $iCurrWindowRow = -1;

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

            if( $i == $iCurrWindowRow ) {
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

    function ListDrawInteractive( SEEDUIComponent_ViewWindow $oViewWindow, $raParms )
    /********************************************************************************
        Draw a list widget for a given Window on a given View of rows in an array.

        $raParms:
            nWindowSize           = number of rows to draw in the window (default 10)
            cols                  = as ListDrawBasic
            tableWidth            = as ListDrawBasic
            fnRowTranslate        = as ListDrawBasic

//          bNewAllowed           = true if the list is allowed to set links that create new records
     */
    {
        $s = "";

        // If the caller called SetViewSlice() then the oViewWindow is all set up (e.g. the nViewSize is known, iCurr is set).
        // If not we have to do this before anything else.
        $raViewSlice = $oViewWindow->GetWindowData();

        //$bNewAllowed = intval(@$raParms['bNewAllowed']);

// kluge: $this->raConfig has things that Start() needs to know. Some of those used to be in $raParms so the code below expects them
// to be there. Look for them in in $raConfig instead, but meanwhile, merge the arrays.
$raParms = array_merge( $this->raConfig, $raParms );

        // uiparms overrides raParms overrides default
        if( !$this->oComp->Get_nWindowSize() )  $this->oComp->Set_nWindowSize( @$raParms['nWindowSize'] ?: 10 );
        $raParms['tableWidth'] = @$raParms['tableWidth'] ?: "100%";

        $nWindowRowsAbove = $oViewWindow->RowsAboveWindow();
        $nWindowRowsBelow = $oViewWindow->RowsBelowWindow();
        $raScrollOffsets  = $oViewWindow->ScrollOffsets();

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
                           ."<img src='".W_CORE_URL."img/ctrl/triangle_blue.png' style='$sCrop' border='0'/></div>")
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
        if( $oViewWindow->IsCurrRowOutsideWindow() ) {
            $sTop .= $this->listButton( "<span style='display:inline-block;background-color:#ddd;color:#222;font-weight:bold;'>"
                                       ."&nbsp;FIND SELECTION&nbsp;</span>", ['offset'=>$oViewWindow->IdealWindowOffset()] )
                    .SEEDCore_NBSP("",10);
        }
        if( $nWindowRowsAbove ) {
            $sTop .= $this->listButton( "TOP", array_merge( $raSortSame, ['offset'=>$raScrollOffsets['top']] ) )
                    .SEEDCore_NBSP("",5)
                    .$this->listButton( "PAGE", array_merge( $raSortSame, ['offset'=>$raScrollOffsets['pageup'], 'img'=>"up2"] ) )
                    .SEEDCore_NBSP("",5)
                    .$this->listButton( "UP", array_merge( $raSortSame, ['offset'=>$raScrollOffsets['up'], 'img'=>"up"] ) );
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
            $sBottom .= $this->listButton( "BOTTOM", array_merge( $raSortSame, ['offset'=>$raScrollOffsets['bottom']] ) )
                       .SEEDCore_NBSP("",5)
                       .$this->listButton( "PAGE", array_merge( $raSortSame, ['offset'=>$raScrollOffsets['pagedown'], 'img'=>"down2"] ) )
                       .SEEDCore_NBSP("",5)
                       .$this->listButton( "DOWN", array_merge( $raSortSame, ['offset'=>$raScrollOffsets['down'], 'img'=>"down"] ) );
        }
        $sBottom .= "</span>";

        /* Links to activate a row as the current row when it is clicked
         */
        for( $i = 0; $i < count($raViewSlice); ++$i ) {
            $ra = $raSortSame;
            $ra['iCurr'] = $i + $this->oComp->Get_iWindowOffset();
            $ra['iWindowOffset'] = $this->oComp->Get_iWindowOffset();
            if( $oViewWindow->IsEnableKeys() && ($k = @$raViewSlice[$i]['_key']) ) {
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
            'iCurrWindowRow' => ($i = $this->oComp->Get_iCurr()) >= 0 ? ($i - $this->oComp->Get_iWindowOffset()) : $i,   // -1 and -2 have special meaning
        );
        $s .= $this->ListDrawBasic( $raViewSlice, 0, $this->oComp->Get_nWindowSize(), $raBasicListParms );

        return( $s );
    }

//obsolete delete
//    function ListFetchViewSlice( $iOffset, $nSize )
//    /**********************************************
//        Override to get an array slice of the View
//     */
//    {
//        return( array() );
//    }

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

        $s = "<a ".$this->oComp->oUI->HRef( $this->oComp->Cid(), $raChange)." style='color:white;text-decoration:none;font-size:7pt;'>"
            ."<b>$label</b>"
            .($img ? ("&nbsp;<div style='display:inline-block;position:relative;width:10px;height:12px;'>"
                           ."<img src='".W_CORE_URL."img/ctrl/triangle_blue.png' style='$sCrop' border='0'/></div>") : "")
            ."</a>";
        return( $s );
    }
}
