<?php

include_once( "console02tabset.php" );

/* console02
 *
 * Basic console framework
 *
 * Copyright (c) 2009-2019 Seeds of Diversity Canada
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

    public $oSVA;      // user's stuff with namespace of CONSOLE_NAME
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
                      "<a href='${ra['href']}' class='console02-header-link'"
                     .(isset($ra['target']) ? " target='${ra['target']}'" : "")
                     .(isset($ra['onclick']) ? " onclick='${ra['onclick']}'" : "")
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
        return( $this->ExpandTemplateTag( trim(@$raMatches[2]), trim(@$raMatches[3]), trim(@$raMatches[5]) ) );
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
            sCharset      : UTF-8 by default
            bCTHeader     : output header(Content-type) by default, =>false to disable
            sTitle        : <title>
            sBodyAttr     : attrs for body tag e.g. onload
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

        // <body> can have attrs and an optional margin (use the margin with bootstrap)
        $sBodyAttr   = (@$raConfig['sBodyAttr']);
        $bBodyMargin = (@$raConfig['bBodyMargin'] == true);

        /* Content-type is always text/html because this only outputs <!DOCTYPE html> after all
         *
         * Why specify charset in both http and html?
         *   Because 1) if Apache sends a different default charset in http header, the browser could/will trust that (chrome, at least, does)
         *           2) if someone downloads the html to file, there is no http header to define charset
         */
        $sHead = "<meta charset='$sCharset'>".$sHead;
        if( $bCTHeader ) {
            header( "Content-Type:text/html; charset=$sCharset" );
        }

        if( @$raConfig['sTitle'] ) {
            $sHead = "<title>{$raConfig['sTitle']}</title>".$sHead;
        }

        if( $bJQuery ) {
            // prepend jQuery so it precedes any jQuery code in our header script (otherwise $ is not known)
            $sHead = "<script src='".W_ROOT_JQUERY."'></script>".$sHead;
        }

        if( $bBootstrap ) {
            $sHead .= "<link rel='stylesheet' type='text/css' href='".W_ROOT."os/bootstrap3/dist/css/bootstrap.min.css'></link>"
                     ."<script src='".W_ROOT."os/bootstrap3/dist/js/bootstrap.min.js'></script>"
                     ."<meta name='viewport' content='width=device-width, initial-scale=1.0'>"
                     ."<!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->\n"
                     ."<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->\n"
                     ."<!--[if lt IE 9]>\n"
                     ."<script src='//oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js'></script>"
                     ."<script src='//oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js'></script>"
                     ."<![endif]-->";
        }

        /* Set the css and js for the requested console skin, and add extra css and js files too.
         */
        if( @$raConfig['consoleSkin'] == 'green' ) {
            $sHead .= "<link rel='stylesheet' type='text/css' href='".W_CORE."css/console02.css'></link>";
        }
        if( @$raConfig['raCSSFiles'] ) {
            foreach( $raConfig['raCSSFiles'] as $v ) {
                $sHead .= "<link rel='stylesheet' type='text/css' href='$v'></link>";
            }
        }
        if( @$raConfig['raScriptFiles'] ) {
            foreach( $raConfig['raScriptFiles'] as $v ) {
                $sHead .= "<script src='$v' type='text/javascript'></script>";
            }
        }

        $s = "<!DOCTYPE html>"
             ."<html lang='".($lang == 'FR' ? 'fr' : 'en')."'>"
             ."<head>".$sHead."</head>"
             ."<body $sBodyAttr>".$sBody."</body>"
             ."</html>";

        return( $s );
    }
}

?>