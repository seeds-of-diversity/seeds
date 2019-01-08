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


class ConsoleTabSet
{
    private $oC;

    function __construct( Console02 $oC )
    {
        $this->oC = $oC;
    }

    function TabSetGetSVA( $tsid, $tabname )
    /***************************************
        Every tab can have its own SVA for saving application state information.
     */
    {
        return( new SEEDSessionVarAccessor( $this->oC->oApp->sess, "console_".$this->oC->GetConsoleName()."_${tsid}_${tabname}" ) );
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

        $s .= "<div class='console02_tabsetframe'>"
             ."<ul class='console02_TStabs'>";

        $i = 0;
        $tabnamePrev = "";
        foreach( $raTS['tabs'] as $tabname => $raTab ) {
            $eAllowed = $this->TabSetPermission( $tsid, $tabname );
            if( $eAllowed == Console01::TABSET_PERM_HIDE )  continue;

            // tabA0  first, not current
            // tabA1  first, current
            // tabB01 non-first, current
            // tabB10 non-first, previous current
            // tabB00 non-first, not current and previous not current
            // tabC0  tail of the last tab, not current
            // tabC1  tail of the last tab, current

            $bCurrent = ($tabname == $sTabCurr);

            if( $i == 0 ) {
                // first tab
                $class = 'console01_TFtabA'.($bCurrent ? "1" : "0");
            } else {
                // not the first tab
                $class = 'console01_TFtabB'.($bCurrent ? "01" :
                ($tabnamePrev == $sTabCurr ? "10" : "00") );
            }
            if( $eAllowed == Console01::TABSET_PERM_SHOW ) {
                $raLinkParms = $this->TabSetExtraLinkParms( $tsid, $tabname, array('bCurrent'=>$bCurrent) );
                // tell console to make tabname the active tab in the tabset tsid
                $raLinkParms['c01tf_'.$tsid] = $tabname;
                $sLink = "<A HREF='{$_SERVER['PHP_SELF']}?".SEEDStd_ParmsRA2URL($raLinkParms)."'>{$raTab['label']}</A>";
            } else {
            	// TABSET_PERM_GHOST
                $sLink = "<SPAN style='color:grey'>{$raTab['label']}</SPAN>";
            }
            $s .= "<TD class='$class'><NOBR>$sLink</NOBR></TD>";

            ++$i;
            $tabnamePrev = $tabname;
        }
        $s .= "<TD class='console01_TFtabC".($tabnamePrev == $sTabCurr ? "1" : "0")."'>&nbsp;</TD>"
             ."</TR></TABLE>";

        // Control and Content areas
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
        $tab = $this->oSVAInt->VarGet( "TS".$tsid );
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
            $m = "TS".$tsid.$tabname.'Init';
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
    {
        // Return values: TABSET_PERM_HIDE, TABSET_PERM_SHOW, TABSET_PERM_GHOST

        return( Console01::TABSET_PERM_SHOW );
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