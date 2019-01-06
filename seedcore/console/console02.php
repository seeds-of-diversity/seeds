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
    private $raParms = array();
    private $sConsoleName = ""; // from raParms['CONSOLE_NAME'] : this keeps different console apps from conflicting in the session var space
    private $raMsg = array( 'usermsg'=>"", 'errmsg'=>"" );

    public $oSVA;      // user's stuff with namespace of CONSOLE_NAME
    private $oSVAInt;  // console's own stuff

    function __construct( SEEDAppConsole $oApp, $raParms = array() )
    {
        $this->oApp = $oApp;
        $this->SetConfig( $raParms );
    }

    function GetConfig()  { return( $this->raParms ); }

    function SetConfig( $raConfig )
    /******************************
       Same as the parms in the constructor
     */
    {
        $this->raParms = array_merge( $this->raParms, $raConfig );

        /* You can reset the sConsoleName and the oSVAs using SetConfig
         */
        $this->sConsoleName = @$raParms['CONSOLE_NAME'];

        /* oSVA is for the client to use, namespaced by sConsoleName.
         * oSVAInt is for the console's housekeeping - also namespaced by sConsoleName but clients should not use it
         */
        $this->oSVA = new SEEDSessionVarAccessor( $this->oApp->sess, "console02".$this->sConsoleName );
        $this->oSVAInt = new SEEDSessionVarAccessor( $this->oApp->sess, "console02i_".$this->sConsoleName );
    }

    function GetConsoleName()  { return( $this->sConsoleName ); }

    function AddUserMsg( $s )  { $this->AddMsg( $s, 'usermsg' ); }
    function GetUserMsg()      { $this->GetMsg( 'usermsg' ); }

    function AddErrMsg( $s )   { $this->AddMsg( $s, 'errmsg' ); }
    function GetErrMsg()       { $this->GetMsg( 'errmsg' ); }

    function GetMsg( $sKey )     { return( @$this->raMsg[$sKey] ); }
    function AddMsg( $s, $sKey ) { @$this->raMsg[$sKey] .= $s; }

    function DrawConsole( $sTemplate, $bExpand = true )
    /**************************************************
        Draw the body of a console.
        Use DrawPage to put it in an html page.
     */
    {
        $s = $this->Style();

        $title = @$this->raParms['HEADER'];
        $tail  = @$this->raParms['HEADER_TAIL'];

        // Do this here so template callbacks can set usermsg and errmsg, etc
        $sTemplate = ($bExpand ? $this->ExpandTemplate( $sTemplate ) : $sTemplate);

        if( ($m = $this->GetErrMsg() ) ) {
            $s .= $this->bBootstrap ? "<div class='alert alert-danger'>$m</div>"
                                    : "<p style='background-color:#fee;color:red;padding:1em'>$m</p>";
        }
        if( ($m = $this->GetUserMsg() ) ) {
            $s .= $this->bBootstrap ? "<div class='alert alert-success'>$m</div>"
                                    : "<p style='background-color:#eee;color:black;padding:1em'>$m</p>";
        }

        /* Heading and header links
         */
        $s .= "<table border='0' width='100%'><tr>"
             ."<td valign='top'>"
             .(@$this->raParms['bLogo'] ? "<img src='//www.seeds.ca/i/img/logo/logoA-60x.png' width='60' height='50' style='display:inline-block'/>" : "")
             .($title ? "<span class='console02-header-title'>$title</span>" : "")
             ."</td>"
             ."<td valign='top'>$tail &nbsp;</td>"
             ."<td valign='top' style='float:right'>";
        if( isset($this->raParms['HEADER_LINKS']) ) {
            foreach( $this->raParms['HEADER_LINKS'] as $ra ) {
                $s .= "<a href='${ra['href']}' class='console02-header-link'"
                     .(isset($ra['target']) ? " target='${ra['target']}'" : "")
                     .(isset($ra['onclick']) ? " onclick='${ra['onclick']}'" : "")
                     .">"
                     .$ra['label']."</a>".SEEDStd_StrNBSP("",5);
            }
            $s .= SEEDCore_NBSP("",20);
        }
        if( $this->oApp->sess->IsLogin() ) {
            $s .= "<a href='".SITEROOT."login/' class='console02-header-link'>Home</a>".SEEDCore_NBSP("",5)
                 ."<a href='".SITEROOT."login/?sessioncmd=logout' class='console01-header-link'>Logout</a>";
        }
        $s .= "</td></tr></table>"
             ."<div id='console-body' width='100%'>"
             .$sTemplate
             ."</div>";

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
                $oCTS = new ConsoleTabSet( $this );
                $s .= $oCTS->TabSetDraw( $tag );
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

    function Style()
    {
        $sSkin = 'blue';
        $color1 = ($sSkin == 'green' ? 'green' : '');

        $s = "<STYLE>"
        .".console01-header-title { display:inline-block;" // IE needs this display type to draw borders
                                  ."font-size:14pt;font-weight:bold;padding:3px;"
                                  ."border-top:2px $color1 solid;"
                                  ."border-bottom:2px $color1 solid; }\n"
        .".console01-header-link { font-size:10pt;color:green;text-decoration:none }\n"
        ."#console-body {margin:10px}\n";

        return( $s );
    }
}



class Console02Static
{
    static function HTMLPage( $sBody, $sHead, $lang, $raParms = array() )
    /********************************************************************
        Assemble an html page

        raParms:
            bBootstrap    : use bootstrap by default, =>false to disable
            bJQuery       : load JQuery by default
            sCharset      : UTF-8 by default
            bCTHeader     : output header(Content-type) by default, =>false to disable
            sTitle        : <title>
            sHttpPrefix   : specify http or https, same as page by default
            sBodyAttr     : attrs for body tag e.g. onload
            raScriptFiles : script files for the header
            raCSSFiles    : css files for the header
     */
    {
        // use bootstrap and JQuery by default
        $bBootstrap = (@$raParms['bBootstrap'] !== false);
        $bJQuery    = (@$raParms['bJQuery'] !== false);

        // match <head> links to the page's ssl
        if( !($sHttpPrefix = @$raParms['sHttpPrefix']) ) {
            $sHttpPrefix = @$_SERVER['HTTPS'] == 'on' ? "https" : "http";
        }

        // by default we output header(Content-type) and use UTF-8
        $bCTHeader = (@$raParms['bCTHeader'] !== false);
        $sCharset  = @$raParms['sCharset'] ?: "UTF-8";

        // <body> can have attrs and an optional margin (use the margin with bootstrap)
        $sBodyAttr   = (@$raParms['sBodyAttr']);
        $bBodyMargin = (@$raParms['bBodyMargin'] == true);

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

        if( @$raParms['sTitle'] ) {
            $sHead = "<title>{$raParms['sTitle']}</title>".$sHead;
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
                     ."<script src='$sHttpPrefix://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js'></script>"
                     ."<script src='$sHttpPrefix://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js'></script>"
                     ."<![endif]-->";
        }
        if( @$raParms['raCSSFiles'] ) {
            foreach( $raParms['raCSSFiles'] as $v ) {
                $sHead .= "<link rel='stylesheet' type='text/css' href='$v'></link>";
            }
        }
        if( @$raParms['raScriptFiles'] ) {
            foreach( $raParms['raScriptFiles'] as $v ) {
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