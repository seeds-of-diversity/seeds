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
 *
 * raConfig = [ tsidA => ['tabs'  => [tabname1 => ['label'=>label1],
 *                                    tabname2 => ['label'=>label2] ],
 *                        'perms' => [tabname1 => [SEEDSessionAccount perm def],
 *                                    tabname2 => [SEEDSessionAccount perm def],
 *                                                '|'
 *                       ],
 *              tsidB => ['tabs'  => [tabname1 => ['label'=>label1], ...
 *
 * Notes:
 * 1) You can have more than one tabset, and draw them independently with TabSetDraw(tsid).
 * 2) The perms are defined separately from the tabs so SEEDSessionAccount::TestPermsRA(config[tsid]['perms']) can test whether
 *    the whole perms set allows ANY tabs to be accessed e.g. to determine whether the whole tabset should be visible.
 *    a) '|' has to be specified in the perms array to enable OR permissions.
 *    b) The perms op '|' (or others) is another reason 'perms' is separated from 'tabs'; you can iterate through 'tabs' to get the exact list of tabs.
 *    c) Keys are ignored by TestPermsRA() so the tabnames can be used by Console but don't get in the way for the purpose explained here.
 */


class Console02TabSet
{
    const PERM_HIDE  = 0;  // don't show the tab at all
    const PERM_SHOW  = 1;  // show the tab fully
    const PERM_GHOST = 2;  // show a non-clickable tab, no way to hack to the content (users will know that tab exists but can't use it)

    private $tsLinkPrefix = "c02ts_";   // http parm prefix for tab links ( $_REQUEST[$tsLinkPrefix.$tsid] = $tabname )
    private $oC;
    private $raConfig;

    function __construct( Console02 $oC, $raConfig )
    {
        $this->oC = $oC;
        $this->raConfig = $raConfig;

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

    function TabSetGetSVACurrentTab( $tsid )
    {
        return( $this->TabSetGetSVA( $tsid, $this->TabSetGetCurrentTab($tsid) ) );
    }

    function TabSetDraw( $tsid )
    /***************************
        Draw a tabbed form, getting the current tab from the console session vars.
     */
    {
        $s = "";

        if( !($raTS = $this->raConfig[$tsid]) )  goto done;

        // Get the current tab, defaulting to the first one (can return '' if no tabs have perms)
        if( !($sTabCurr = $this->TabSetGetCurrentTab( $tsid, true )) )  goto done;

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
                $label = "<a href='?".SEEDCore_ParmsRA2URL($raLinkParms)."' style='color:inherit;text-decoration:none'>{$raTab['label']}</a>";

            } else if( $eAllowed == self::PERM_SHOW ) {
                // This is an available non-current tab. Put a link in it.
                $class = 'console02-tabset-tab-link';
                $raLinkParms = $this->TabSetExtraLinkParms( $tsid, $tabname, array('bCurrent'=>false) );
                $raLinkParms[$this->tsLinkPrefix.$tsid] = $tabname;
                $label = "<a href='?".SEEDCore_ParmsRA2URL($raLinkParms)."' style='color:inherit;text-decoration:none'>{$raTab['label']}</a>";

            } else {
                // This is a ghost tab. It has no link so nothing happens if you click on it.
                $class = 'console02-tabset-tab-ghost';
                $label = $raTab['label'];
            }
            $s .= "<div class='console02-tabset-tab $class'>$label</div>";
        }
        $s .= "</div>";     // console02-tabset-tabs

        $sStyle = $sControl = $sContent = "";
        if( $this->TabSetPermission( $tsid, $sTabCurr ) == self::PERM_SHOW ) {
            $sStyle   = $this->TabSetStyleDraw( $tsid, $sTabCurr );
            $sControl = $this->TabSetControlDraw( $tsid, $sTabCurr );
            $sContent = $this->TabSetContentDraw( $tsid, $sTabCurr );
        }

        // Control and Content areas
        $s .= $sStyle
             ."<div class='console02-tabset-controlarea'>$sControl</div>"
             ."<div class='console02-tabset-contentarea'>$sContent</div>"
             ."</div>";   // console02-tabset-frame

        done:
        return( $s );
    }


    function TabSetGetCurrentTab( $tsid, $bFindDefaultIfNoneCurrent = true )
    {
        $tabname = $this->oC->oSVAInt->VarGet( "TabSet_".$tsid );

        if( !($raTS = $this->raConfig[$tsid]) ) goto done;

        // If tab is blank or not in tabs list or not visible, find the first visible tab
        if( !$tabname || !isset($raTS['tabs'][$tabname]) || $this->TabSetPermission( $tsid, $tabname ) != self::PERM_SHOW ) {

            $tabname = '';  // return this if no default found
            if( $bFindDefaultIfNoneCurrent ) {
                // Find the first tab that is PERM_SHOW
                foreach( $raTS['tabs'] as $k => $ra ) {
                    if( $this->TabSetPermission( $tsid, $k ) == self::PERM_SHOW ) {
                        $tabname = $k;
                        break;
                    }
                }
            }
            // Save the current tab in $this->oC->oSVAInt
            $this->oC->oSVAInt->VarSet( "TabSet_".$tsid, $tabname );
        }

        done:
        return( $tabname );
    }

    function TabSetInit( $tsid, $tabname )
    {
        /* Override this method or define another that findMethod will find
         */

        $oTabInfo = new Console02TabSet_TabInfo( $this, $tsid, $tabname );

        if( ($m = $this->findmethod( $tsid, $tabname, "Init" )) ) {
            call_user_func( $m, $oTabInfo );
        }
    }

    function TabSetExtraLinkParms( $tsid, $tabname, $raParms )
    {
        // Return an array of k=>v that should be encoded into the link on the given tab label
        //
        // $raParms    : parms used by this method
        //                   bCurrent = this is the current tab

        /* Override this method or define another that findMethod will find
         */
        $ret = array();

        if( ($m = $this->findmethod( $tsid, $tabname, "ExtraLinkParms" )) ) {
            $ret = call_user_func( $m, $tsid, $tabname );
        }

        return( array() );
    }

    function TabSetPermission( $tsid, $tabname )
    /*******************************************
        Return self::PERM_* based on whether the current user has the required permissions on the given tab
     */
    {
        /* Override this method or define another that findMethod will find
         */

        $ret = self::PERM_HIDE;

        if( ($m = $this->findmethod( $tsid, $tabname, "Permission" )) ) {
            $ret = call_user_func( $m, $tsid, $tabname );
        } else {
            /* Base method tests tab permissions using TestPermRA()
             */
            // use isset because "if(@$raP[$tabname])" would fail for [] which is allowed and always succeeds.
            if( ($raP = @$this->raConfig[$tsid]['perms']) &&
                isset($raP[$tabname]) )
            {
                $ret = $this->oC->oApp->sess->TestPermRA($raP[$tabname])
                            ? self::PERM_SHOW
                            : self::PERM_GHOST;
            }
        }

        return( $ret );
    }


    function TabSetStyleDraw( $tsid, $tabname )
    /******************************************
        <style> tags that apply to the control/content block.
        Anything can be placed here (e.g. script) that should be placed in the html between the tabs and the control area, after Init()
     */
    {
        /* Override this method or define another that findMethod will find
         */
        $s = "";

        if( ($m = $this->findmethod( $tsid, $tabname, "StyleDraw" )) ) {
            $s = call_user_func( $m, $tsid, $tabname );
        }
        return( $s );
    }

    function TabSetControlDraw( $tsid, $tabname )
    {
        /* Override this method or define another that findMethod will find
         */
        $s = "";

        if( ($m = $this->findmethod( $tsid, $tabname, "ControlDraw" )) ) {
            $s = call_user_func( $m, $tsid, $tabname );
        }
        return( $s );
    }

    function TabSetContentDraw( $tsid, $tabname )
    {
        /* Override this method or define another that findMethod will find
         */
        $s = "";

        if( ($m = $this->findmethod( $tsid, $tabname, "ContentDraw" )) ) {
            $s = call_user_func( $m, $tsid, $tabname );
        }
        return( $s );
    }


    private function findmethod( $tsid, $tabname, $method )
    {
        /* Try to find a method that implements the given $method
         */
        $ret = null;

        if( method_exists( $this->oC, "TabSet_$method" ) ) {
            // You added this method to your derivation of Console02.
            // The '_' differentiates this method name from the Console02TabSet base methods because args are different
            $ret = array($this->oC, "TabSet_$method" );
        } else {
            // Maybe you created a method named after the tabset/tabname in a derivation of this class, or of Console02
            $m = "TabSet_{$tsid}_{$tabname}_{$method}";
            if( method_exists( $this, $m ) ) {
                $ret = array( $this, $m );
            } else if( method_exists( $this->oC, $m ) ) {
                $ret = array( $this->oC, $m );
            } else {
                // Nope, couldn't find a method
            }
        }

        return( $ret );
    }
}


/* Summarize the info about a tab in an object that can be passed around where there is no reference to Console02TabSet
 */
class Console02TabSet_TabInfo
{
    public $tsid;
    public $tabname;
    public $oSVA;

    function __construct( Console02TabSet $oCTS, $tsid, $tabname )
    {
        $this->tsid = $tsid;
        $this->tabname = $tabname;
        $this->oSVA = $oCTS->TabSetGetSVA( $tsid, $tabname );
    }
}

interface Console02TabSet_Worker
{
    function Init();
    function ControlDraw();
    function ContentDraw();
}
