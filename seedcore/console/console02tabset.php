<?php

/* console02tabset
 *
 * Console TabSet handler
 *
 * Copyright (c) 2009-2019 Seeds of Diversity Canada
 *
 * A tabset is a set of tabs that operate as a group. One tab is always active, by default the first one.
 * More than one tabset can be in a console, and they can exist in any arrangement (side by side, nested).
 *
 * Each tab has a tabname (used by the software to identify it) and a label (for display). Typically, punctuation and spaces
 * should be avoided in the name, but anything is allowed in the label. That's why there are both.
 *
 * Each tabset has a tsid.
 * So a tab can be uniquely identified by consoleAppName_tsid_tabname
 */


class Console02TabSet
{
    const PERM_HIDE  = 0;  // don't show the tab at all
    const PERM_SHOW  = 1;  // show the tab fully
    const PERM_GHOST = 2;  // show a non-clickable tab, no way to hack to the content (users will know that tab exists but can't use it)

    private $tsLinkPrefix = "c02ts_";   // http parm prefix for tab links ( $_REQUEST[$tsLinkPrefix.$tsid] = $tabname )
    private $oC;

    function __construct( Console02 $oC )
    {
        $this->oC = $oC;

        // Respond to a user clicking on a tab
        foreach( $_REQUEST as $k => $v ) {
            if( SEEDCore_StartsWith( $k, $this->tsLinkPrefix ) ) {
                $tsid = substr( $k, strlen($this->tsLinkPrefix) );
                $this->oC->oSVAInt->VarSet( "TabSet_".$tsid, $v );  // current tabname for the tsid
            }
        }
    }

    function TabSetGetSVA( $tsid, $tabname )
    /***************************************
        Every tab can have its own SVA for saving application state information.
     */
    {
        return( new SEEDSessionVarAccessor( $this->oC->oApp->sess, "console02TabSet_".$this->oC->GetConsoleName()."_${tsid}_${tabname}" ) );
    }

    function TabSetDraw( $tsid )
    /***************************
        Draw a tabbed form, getting the current tab from the console session vars.

        $raConfig['TABSETS'] must have [ $tsid => ['tabs' => [tabname1 => ['label'=>label1,'perms'=>...], tabname2 => ['label'=>label2,'perms'=> ...
     */
    {
        $s = "";

        if( !($raTS = $this->oC->GetConfig()['TABSETS'][$tsid]) )  goto done;

        // Get the current tab, defaulting to the first one
        $sTabCurr = $this->TabSetGetCurrentTab( $tsid );
        if( !$sTabCurr || !isset($raTS['tabs'][$sTabCurr]) ) {
            reset( $raTS['tabs'] );          // point to the first element
            $sTabCurr = key($raTS['tabs']);  // the key of the first element (the first tabname)
        }
        $sLabelCurr = $raTS['tabs'][$sTabCurr]['label'];

        // Tell the TabSet to initialize itself on the current tab
        $this->TabSetInit( $tsid, $sTabCurr );

        $s .= "<div class='console02-tabset-frame'>"
             ."<div class='console02-tabset-tabs'>";

        foreach( $raTS['tabs'] as $tabname => $raTab ) {
            $eAllowed = $this->TabSetPermission( $tsid, $tabname );
            if( $eAllowed == self::PERM_HIDE )  continue;

            if( $tabname == $sTabCurr ) {
                // This is the current tab. Show that it's special.
                $class = 'console02-tabset-tab-current';
                $label = $raTab['label'];
                $raLinkParms = $this->TabSetExtraLinkParms( $tsid, $tabname, array('bCurrent'=>true) );
                $raLinkParms[$this->tsLinkPrefix.$tsid] = $tabname;
                $label = "<a href='{$_SERVER['PHP_SELF']}?".SEEDCore_ParmsRA2URL($raLinkParms)."' style='color:inherit;text-decoration:none'>{$raTab['label']}</a>";

            } else if( $eAllowed == self::PERM_SHOW ) {
                // This is an available non-current tab. Put a link in it.
                $class = 'console02-tabset-tab-link';
                $raLinkParms = $this->TabSetExtraLinkParms( $tsid, $tabname, array('bCurrent'=>false) );
                $raLinkParms[$this->tsLinkPrefix.$tsid] = $tabname;
                $label = "<a href='{$_SERVER['PHP_SELF']}?".SEEDCore_ParmsRA2URL($raLinkParms)."' style='color:inherit;text-decoration:none'>{$raTab['label']}</a>";

            } else {
                // This is a ghost tab. It has no link so nothing happens if you click on it.
                $class = 'console02-tabset-tab-ghost';
                $label = $raTab['label'];
            }
            $s .= "<div class='console02-tabset-tab $class'>$label</div>";
        }
        $s .= "</div>";     // console02-tabset-tabs

        // Control and Content areas
        $s .= "<div class='console02-tabset-controlarea'>"
             ."<br/><br/><br/><br/>"
             ."</div>"
             ."<div class='console02-tabset-contentarea'>"
             ."<br/><br/><br/><br/>"
             ."<br/><br/><br/><br/>"
             ."<br/><br/><br/><br/>"
             ."</div>";

        $s .= "</div>";   // console02-tabset-frame


        $sControl = $sContent = "";
        if( $this->TabSetPermission($tsid,$sTabCurr) == Console01::TABSET_PERM_SHOW ) {
            $mContent = $tsid.$sTabCurr.'Content';
            $mControl = $tsid.$sTabCurr.'Control';

            $sControl = method_exists( $this, $mControl ) ? $this->$mControl() : $this->TabSetControlDraw($tsid,$sTabCurr);
            $sContent = method_exists( $this, $mContent ) ? $this->$mContent() : $this->TabSetContentDraw($tsid,$sTabCurr);
        }

        $sSpacer1_1 = "<IMG height='1' width='1' src='".W_ROOT_SEEDCOMMON."console/spacer.gif'/>";
        $sSpacer12_1  = "<IMG height='1' width='12' src='".W_ROOT_SEEDCOMMON."console/spacer.gif'/>";
        $sSpacer12_19 = "<IMG height='19' width='12' src='".W_ROOT_SEEDCOMMON."console/spacer.gif'/>";
        $sSpacer12_20 = "<IMG height='20' width='12' src='".W_ROOT_SEEDCOMMON."console/spacer.gif'/>";
        $sSpacer15_20 = "<IMG height='20' width='15' src='".W_ROOT_SEEDCOMMON."console/spacer.gif'/>";

$s .= "<div class='console01_frame2-ctrl'>"
     ."<div class='console01_frame2-ctrl-tl'></div>"
     ."<div class='console01_frame2-ctrl-tc'></div>"
     ."<div class='console01_frame2-ctrl-tr'></div>"
     ."<div class='console01_frame2-ctrl-cl'></div>"
     ."<div class='console01_frame2-ctrl-cc'></div>"
     ."<div class='console01_frame2-ctrl-cr'></div>"
     ."<div class='console01_frame2-ctrl-bl'></div>"
     ."<div class='console01_frame2-ctrl-bc'></div>"
     ."<div class='console01_frame2-ctrl-br'></div>"
     ."<div class='console01_frame2-ctrl-body'>"
     .(!empty($sControl) ? "<DIV style='margin:10px'>$sControl</DIV>" : "&nbsp;")
     ."</div>"
     ."</div>";

        $s .= "<TABLE border='0' cellpadding='0' cellspacing='0' width='100%'>"
/*        // frame top
        ."<TR valign='top'>"
        ."<TD class='console01_frame-1-1-topleft' width='12' height='20'>$sSpacer1_1</TD>"
        ."<TD class='console01_frame-1-2-topmiddle' height='20'>$sSpacer1_1</TD>"
        ."<TD class='console01_frame-1-3-topright' width='15' height='20'>$sSpacer1_1</TD>"
        ."</TR>"
        ."<TR valign='top'>"
        ."<TD class='console01_frame-2-1-topleft' width='12'>$sSpacer1_1</TD>"
        ."<TD class='console01_frame-2-2-topmiddle'>"
        .(!empty($sControl) ? "<DIV style='margin:10px'>$sControl</DIV>" : "&nbsp;")
        ."</TD>"
        ."<TD class='console01_frame-2-3-topright' width='15'>$sSpacer1_1</TD>"
        ."</TR>"
        ."<TR valign='top'>"
        ."<TD class='console01_frame-3-1-topleft' width='12' height='20'>$sSpacer1_1</TD>"
        ."<TD class='console01_frame-3-2-topmiddle' height='20'>$sSpacer1_1</TD>"
        ."<TD class='console01_frame-3-3-topright' width='12' height='20'>$sSpacer1_1</TD>"
        ."</TR>"
*/
        // frame body
        ."<TR valign='top'>"
        ."<TD class='console01_frame-4-1-middleleft' width='12'>$sSpacer1_1</TD>"
        ."<TD>"
        ."<DIV class='console01_tabsetcontent' style='".@$raTF['divstyle']."'>"
        .$sContent."&nbsp;"
        ."</DIV></TD>"
        ."<TD class='console01_frame-4-3-middleright' width='15'>$sSpacer12_19</TD>"
        ."</TR>"
        // frame bottom
        ."<TR valign='top'>"
        ."<TD class='console01_frame-5-1-bottomleft' width='12' height='19'>$sSpacer1_1</TD>"
        ."<TD class='console01_frame-5-2-bottommiddle' height='19'>$sSpacer1_1</TD>"
        ."<TD class='console01_frame-5-3-bottomright' width='15' height='19'>$sSpacer1_1</TD>"
        ."</TR></TABLE>"
        ."</DIV>";

        done:
        return( $s );
    }


    function TabSetGetCurrentTab( $tsid )
    {
        $tab = $this->oC->oSVAInt->VarGet( "TabSet_".$tsid );
        // if blank or not in tabs list or not visible, reset and get the first item in the tabs list.
        // if not visble get the next, repeat.
        // if none are visible, return blank.
        // save the current tab in $this->oC->oSVAInt
        return( $tab );
    }

    function TabSetInit( $tsid, $tabname )
    {
        /* You can make a derived class overriding this method to initialize the tab set.
         * Otherwise, we will try to find other methods where you did that.
         */
        if( method_exists( $this->oC, "TabSetInit" ) ) {
            // You added this method to your derivation of Console02
            $this->oC->TabSetInit( $tsid, $tabname );
        } else {
            // Maybe you created a method named after the tabset/tabname in a derivation of this class, or of Console02
            $m = "TabSet_{$tsid}_{$tabname}_Init";
            if( method_exists( $this->oC, $m ) ) {
                $this->oC->$m();
            } else if( method_exists( $this, $m ) ) {
                $this->$m();
            } else {
                // Nope, no initialization method
            }
        }
    }

    function TabSetExtraLinkParms( $tsid, $tabname, $raParms )
    {
        // Return an array of k=>v that should be encoded into the link on the given tab label
        //
        // $raParms    : parms used by this method
        //                   bCurrent = this is the current tab
        return( array() );
    }

    function TabSetPermission( $tsid, $tabname )
    /*******************************************
        Return self::PERM_* based on whether the current user has the required permissions on the given tab
     */
    {
        $ret = self::PERM_HIDE;

        if( ($raP = @$this->oC->GetConfig()['TABSETS'][$tsid]['perms']) ) {                      // HIDE if nobody defined the perms
            $ret = isset($raP[$tabname]) && $this->oC->oApp->sess->TestPermRA($raP[$tabname])    // Can be [], which always succeeds
                        ? self::PERM_SHOW
                        : self::PERM_GHOST;
        }

        return( $ret );
    }

    function TabSetControlDraw( $tsid, $tabname )
    {
        // override to place controls in the upper frame area
        return( "" );
    }

    function TabSetContentDraw( $tsid, $tabname )
    {
        // override to draw the main content
        return( "" );
    }



}