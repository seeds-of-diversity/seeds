<?php

include_once( "console02tabset.php" );

/* console02
 *
 * Basic console framework
 *
 * Copyright (c) 2009-2023 Seeds of Diversity Canada
 */

class Console02
/* Parms:
 *
 * CONSOLE_NAME: application name prevents different console apps from conflicting in the session var space
 * HEADER:       the page title
 * HEADER_TAIL:  html appended after the page title
 * HEADER_LINKS: add more links at the top -- array( array( 'label'=>text, 'href'=>url, {'target'=>_window} ), ... )
 */
{
    public  $oApp;      // Console02 and SEEDAppConsole are circularly referenced
    private $raConfig = array();
    private $sConsoleName = ""; // from raConfig['CONSOLE_NAME'] : this keeps different console apps from conflicting in the session var space
    private $raMsg = array( 'usermsg'=>"", 'errmsg'=>"" );
    private $bBootstrap;
    private $oTabSet = null;    // Custom Console02TabSet can be specified by DrawConsole parms

    public $oSVA; // should be private and accessed by GetSVA()      // user's stuff with namespace of CONSOLE_NAME
    /* private but used by Console02TabSet */ public $oSVAInt;  // console's own stuff

    function __construct( SEEDAppSession $oApp, $raConfig = array() )
    {
        $this->oApp = $oApp;
        $this->SetConfig( $raConfig );
        $this->bBootstrap = (@$raConfig['bBootstrap'] !== false);   // true if not defined
    }

    function GetConfig()  { return( $this->raConfig ); }

    function SetConfig( $raConfig )
    /******************************
       Same as the parms in the constructor
     */
    {
        $this->raConfig = array_merge( $this->raConfig, $raConfig );

        /* You can reset the sConsoleName and the oSVAs using SetConfig
         */
        $this->sConsoleName = @$this->raConfig['CONSOLE_NAME'];

        /* oSVA is for the client to use, namespaced by sConsoleName.
         * oSVAInt is for the console's housekeeping - also namespaced by sConsoleName but clients should not use it
         */
        $this->oSVA = new SEEDSessionVarAccessor( $this->oApp->sess, "console02".$this->sConsoleName );
        $this->oSVAInt = new SEEDSessionVarAccessor( $this->oApp->sess, "console02i_".$this->sConsoleName );

        $this->raConfig['pathToSite']  = @$this->raConfig['pathToSite']  ?: "../";
        $this->raConfig['pathToLogin'] = @$this->raConfig['pathToLogin'] ?: ($this->raConfig['pathToSite']."login/");
    }

    function GetConsoleName()  { return( $this->sConsoleName ); }

    function AddUserMsg( $s )  { $this->AddMsg( $s, 'usermsg' ); }
    function GetUserMsg()      { return($this->GetMsg( 'usermsg' )); }

    function AddErrMsg( $s )   { $this->AddMsg( $s, 'errmsg' ); }
    function GetErrMsg()       { return($this->GetMsg( 'errmsg' )); }

    function GetMsg( $sKey )     { return( @$this->raMsg[$sKey] ); }
    function AddMsg( $s, $sKey ) { @$this->raMsg[$sKey] .= $s; }

    // get the console's user SVA, or a named child of that
    function GetSVA( $sChildName = "" ) { return( $sChildName ? $this->oSVA->CreateChild($sChildName) : $this->oSVA ); }

    function DrawConsole( $sTemplate, $raParms = array() )
    /*****************************************************
        Draw the body of a console.
        Use HTMLPage to put it in an html page.

        raParms: bExpand = use ExpandTemplate (default true)
                 oTabSet = a Console02TabSet to use for [[TabSet:...]] expansion
     */
    {
        $sMsgs = $sHeader = $sTail = $sLinks = "";

        if( @$raParms['oTabSet'] ) {
            // The caller has specified their own Console02TabSet
            $this->oTabSet = $raParms['oTabSet'];
        }

        // Do this here so template callbacks can set usermsg and errmsg, etc
        if( SEEDCore_ArraySmartVal($raParms, 'bExpand', [true,false]) ) {
            $sTemplate = $this->ExpandTemplate( $sTemplate );
        }

        /* sMsgs appear at the top
         */
        if( ($m = $this->GetErrMsg() ) ) {
            $sMsgs .= $this->bBootstrap ? "<div class='alert alert-danger'>$m</div>"
                                        : "<p style='background-color:#fee;color:red;padding:1em'>$m</p>";
        }
        if( ($m = $this->GetUserMsg() ) ) {
            $sMsgs .= $this->bBootstrap ? "<div class='alert alert-success'>$m</div>"
                                          : "<p style='background-color:#eee;color:black;padding:1em'>$m</p>";
        }

        /* Header and Tail
         */
        $sHeader = (@$this->raConfig['bLogo'] ? "<img src='//www.seeds.ca/i/img/logo/logoA-60x.png' width='60' height='50' style='display:inline-block'/>" : "")
                  .("<span class='console02-header-title'>".@$this->raConfig['HEADER']."</span>");
        $sTail  = @$this->raConfig['HEADER_TAIL'];

        /* Links to the right of the header and tail
         */
        if( isset($this->raConfig['HEADER_LINKS']) ) {
            foreach( $this->raConfig['HEADER_LINKS'] as $ra ) {
                $sLinks .=
                      "<a href='{$ra['href']}' class='console02-header-link'"
                     .(isset($ra['target']) ? " target='{$ra['target']}'" : "")
                     .(isset($ra['onclick']) ? " onclick='{$ra['onclick']}'" : "")
                     .">"
                     .$ra['label']."</a>".SEEDCore_NBSP("",5);
            }
            $sLinks .= SEEDCore_NBSP("",20);
        }
        if( $this->oApp->sess->IsLogin() ) {
            $sLinks .= "<a href='{$this->raConfig['pathToLogin']}' class='console02-header-link'>Home</a>".SEEDCore_NBSP("",5)
                      ."<a href='{$this->raConfig['pathToLogin']}?sessioncmd=logout' class='console01-header-link'>Logout</a>";
        }

        /* Put it all together
         */
        $s = $sMsgs
            ."<table border='0' style='width:100%;margin-bottom:10px'><tr>"
            ."<td valign='top'>$sHeader</td>"
            ."<td valign='top'>$sTail &nbsp;</td>"
            ."<td valign='top'><div style='float:right'>$sLinks</div></td>"
            ."</tr></table>"
            .$sTemplate;

        /* And wrap it in a body div so we can apply a margin
         */
        $s = "<div class='console02-body' width='100%'>$s</div>";

        return( $s );
    }

// Use SEEDTag here instead of this.
// Currently it only replaces [[TabSet:foo]] with the named tabset
    function ExpandTemplate( $sTemplate )
    {
        $regex = '\[\['. // opening brackets
                     '(([^\]]*)\:)?'. // namespace (if any)
                     '([^\]]*?)'. // target
                     '(\|([^\]]*?))?'. // title (if any)
                 '\]\]'; // closing brackets

        $sOut = preg_replace_callback("/$regex/i",array(&$this,"_expandtemplate_callback"), $sTemplate );
        return( $sOut );
    }

    function _expandtemplate_callback( $raMatches )
    /* Handle tags of the form: [[namespace: tag | title]]
     *
     * raMatches[0] = whole tag content including [[ ]]
     * raMatches[1] = namespace (if any) with colon
     * raMatches[2] = namespace (if any) without colon
     * raMatches[3] = tag
     * raMatches[4] = title (if any) with leading |
     * raMatches[5] = title (if any)
     */
    {
        $ns    = trim(@$raMatches[2] ?: "");
        $tag   = trim(@$raMatches[3] ?: "");
        $title = trim(@$raMatches[5] ?: "");
        return( $this->ExpandTemplateTag( $ns, $tag, $title ) );
    }

    function ExpandTemplateTag( $namespace, $tag, $title )
    {
        $s = "";
        switch( $namespace ) {
            case "TabSet":
                $oCTS = null;
                if( $this->oTabSet ) {
                    // caller has specified their own Console02TabSet
                    $oCTS = $this->oTabSet;
                } else if( isset($this->raConfig['TABSETS']) ) {
                    // use the base Console02TabSet
                    $oCTS = new Console02TabSet( $this, $this->raConfig['TABSETS'] );
                }
                if( $oCTS ) {
                    $s .= $oCTS->TabSetDraw( $tag );
                }
                break;
/*
            case "":
                $s .= $this->DrawTag( $tag, $title );
                break;
            default:
                $s .= $this->DrawTagNS( $namespace, $tag, $title );
                break;
*/
        }
        return( $s );
    }



    function HTMLPage( $sBody, $sHead = "" )
    {

    }
}



class Console02Static
{
    static function HTMLPage( $sBody, $sHead, $lang, $raConfig = array() )
    /*********************************************************************
        Assemble an html page

        raConfig:
            bBootstrap    : use bootstrap by default, =>false to disable
            bJQuery       : load JQuery by default
            bSelect2      : load Select2
            sCharset      : UTF-8 by default
            bCTHeader     : output header(Content-type) by default, =>false to disable
            sTitle        : <title>
            icon          : url of favicon - default /favicon.ico
            sBodyAttr     : attrs for body tag e.g. onload
            cssBodyMargin : put a margin on the <body> (useful for bootstrap pages that look tight with bs's default 0 margin)
            raScriptFiles : script files for the header
            raCSSFiles    : css files for the header
     */
    {
        // use bootstrap and JQuery by default
        $bBootstrap = (@$raConfig['bBootstrap'] !== false);
        $bJQuery    = (@$raConfig['bJQuery'] !== false);

        // by default we output header(Content-type) and use UTF-8
        $bCTHeader = (@$raConfig['bCTHeader'] !== false);
        $sCharset  = @$raConfig['sCharset'] ?: "UTF-8";


        /* Content-type is always text/html because this only outputs <!DOCTYPE html> after all
         *
         * Why specify charset in both http and html?
         *   Because 1) if Apache sends a different default charset in http header, the browser could/will trust that (chrome, at least, does)
         *           2) if someone downloads the html to file, there is no http header to define charset
         */
        $sH = "<meta charset='$sCharset'>";
        if( $bCTHeader ) {
            header( "Content-Type:text/html; charset=$sCharset" );
        }

        if( @$raConfig['sTitle'] ) {
            $sH .= "<title>".SEEDCore_HSC($raConfig['sTitle'])."</title>";
        }

        if( $bJQuery ) {
            // prepend jQuery so it precedes any jQuery code in our header script (otherwise $ is not known)
            $sH .= "<script src='".W_CORE_JQUERY."'></script>";
        }

        if( $bBootstrap ) {
//upgrate to 4 or 5
            $sH .= "<link rel='stylesheet' type='text/css' href='".W_CORE_URL."os/bootstrap3/dist/css/bootstrap.min.css'></link>"
                     ."<script src='".W_CORE_URL."os/bootstrap3/dist/js/bootstrap.min.js'></script>"
                     ."<meta name='viewport' content='width=device-width, initial-scale=1.0'>"
                     ."<!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->\n"
                     ."<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->\n"
                     ."<!--[if lt IE 9]>\n"
                     ."<script src='//oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js'></script>"
                     ."<script src='//oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js'></script>"
                     ."<![endif]-->";
        }

        /* Set shortcut icon
         */
        $sH .= "<link rel='shortcut icon' href='".(@$raConfig['icon'] ?: "/favicon.ico")."'/>";

        /* Set the css and js for the requested console skin, and add extra css and js files too.
         */
        $raCSSFiles = @$raConfig['raCSSFiles'] ?? [];
        $raScriptFiles = @$raConfig['raScriptFiles'] ?? [];

        if( @$raConfig['consoleSkin'] == 'green' ) {
            $raCSSFiles[] = W_CORE."css/console02.css";
            //$sH .= "<link rel='stylesheet' type='text/css' href='".W_CORE."css/console02.css'></link>";
        }
        if( @$raConfig['bSelect2'] ) {
            $raCSSFiles[]    = "https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css";
            $raScriptFiles[] = "https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js";
        }
        $sH .= SEEDCore_ArrayExpandSeries($raCSSFiles,    "<link rel='stylesheet' type='text/css' href='[[v]]'></link>");
        $sH .= SEEDCore_ArrayExpandSeries($raScriptFiles, "<script src='[[v]]' type='text/javascript'></script>");

        // <body> can have attrs and an optional margin (use the margin with bootstrap)
        $sBodyAttr   = @$raConfig['sBodyAttr'];
        $cssBodyStyle = ($css = @$raConfig['cssBodyMargin']) ? "margin:$css" : "";
        if($cssBodyStyle) $sBody = "<div style='{$cssBodyStyle}'>{$sBody}</div>";     // div is easier than trying to parse style in sBodyAttr

        $s = "<!DOCTYPE html>
              <html lang='".($lang == 'FR' ? 'fr' : 'en')."'>
              <!-- put user-specified head last so e.g. js vars override defaults in files -->
              <head>{$sH}{$sHead}</head>
              <body {$sBodyAttr}>$sBody</body>
              </html>";

        return( $s );
    }
}
