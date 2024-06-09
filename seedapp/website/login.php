<?php

/* login.php
 *
 * Copyright 2023 Seeds of Diversity Canada
 *
 * Draw a login page
 */

include_once( SEEDCORE."console/console02.php" );

class SEEDLoginPage
/******************
    Draws the links of a login landing page based on a definition of links, perms, and titles
 */
{
    private $oApp;

    function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;
    }

    function Style()
    {
        $s = <<<HEREDOC
            <STYLE>
            .loginSection {
                margin: 2em;
            }
            .loginSection h {
                font-size: 14pt;
                font-weight: normal;
            }
            .loginSection p {
                margin-left: 3em;
                font-size:10pt;
            }
            </STYLE>
HEREDOC;
        return( $s );
    }

    function DrawLogin( $raDef )
    {
        $s = "";

        foreach( $raDef as $raSection ) {
            // [0] = EN section title, [1] = FR section title, [2] = array of links
            $sBlock = "";
            $sSectionTitle = ($this->oApp->lang == "FR" && @$raSection[1]) ? $raSection[1] : $raSection[0];

            foreach( $raSection[2] as $raLink ) {
                if( $raLink[0] == 'ONE-OF' ) {
                    // Multiple links are defined for this line. Use the first one that has qualifying perms.
                    for( $i = 1; $i < count($raLink); ++$i ) {
                        if( ($sTest = $this->drawLink($raLink[$i])) ) {
                            $sBlock .= $sTest;
                            break;
                        }
                    }
                } else {
                    $sBlock .= $this->drawLink( $raLink );
                }
            }
            if( $sBlock ) {
                $s .= "<DIV class='loginSection'><H>$sSectionTitle</H>$sBlock</DIV>";
            }
        }
        return( $s );
    }

    private function drawLink( $raLink )
    {
        // [0] = link relative to SITEROOT, [1] = perm, [2] = EN label, [3] = FR label, [4] = alternate url (full path)
        $url = @$raLink[4] ? $raLink[4] : (SITEROOT.$raLink[0]);
        $perm = $raLink[1];
        $sTitle = ($this->oApp->lang == "FR" && @$raLink[3]) ? $raLink[3] : $raLink[2];

        if( $perm == "PUBLIC" || $this->oApp->sess->TestPerm( substr($perm,2), substr($perm,0,1) ) ) {
            return( "<P><A HREF='$url'>$sTitle</A></P>" );
        } else {
            return( "" );
        }
    }

    function DrawPage( $sTitle, $sHead, $sBody )
    {
        $sHead = $this->Style()
                .$sHead;

        $sBody =
            "<div style='margin:15px'>
             <img src='//seeds.ca/i/img/logo/logoA_h-".($this->oApp->lang=="EN"?"en":"fr")."-750x.png' width='400'/>
             <div style='float:right; margin:1em 2em;'>
             <a href='{$this->oApp->PathToSelf()}?sessioncmd=logout' style='font-size:12pt;color:green;text-decoration:none;'>Logout</a>
             </div>
             <h3>".($this->oApp->lang == "EN" ? "Welcome" : "Bienvenue")." {$this->oApp->sess->GetName()}</h3>
             <p style='margin-left:2em'>".date('Y-m-d')."</p>
             $sBody
             </div>
             <script>SEEDUI_BoxExpandInit( '{$this->oApp->lang}', '{$this->oApp->UrlW()}' );</script>";

        $s = Console02Static::HTMLPage( SEEDCore_utf8_encode($sBody), $sHead, $this->oApp->lang,
                                  ['sTitle' => $sTitle,
                                   'icon' => $this->oApp->UrlW()."img/sod.ico",
                                   'raScriptFiles'=>[$this->oApp->UrlW()."js/SEEDUI.js"],
                                   'raCSSFiles'=>[$this->oApp->UrlW()."css/SEEDUI.css"]
                                  ]);
        return( $s );
    }
}
