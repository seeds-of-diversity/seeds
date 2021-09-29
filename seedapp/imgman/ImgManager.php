<?php

if( !defined( "SEEDROOT" ) ) {
    define( "SEEDROOT", "../../" );
    define( "SEED_APP_BOOT_REQUIRED", true );
    include_once( SEEDROOT."seedConfig.php" );
}

include_once( SEEDCORE."console/console02.php" );
include_once( SEEDLIB."SEEDImg/SEEDImgManLib.php" );

if( !isset($oApp) ) {
    $oApp = SEEDConfig_NewAppConsole( ['sessPermsRequired' => [] ] );
}

/* Defaults below are overridden by raConfig defined by an including file
 */
$raConfig = array_replace_recursive(
    [ 'rootdir'   => realpath(dirname(__FILE__)."/".SEEDROOT)."/",
      'imgmanlib' => [ 'fSizePercentThreshold' => 90.0,    // if filesize is reduced below this threshold, use the new file
                       'bounding_box' => 1200,             // scale down to 1200x1200 if larger
                       'outfmt' => 'webp',
                       'jpg_quality' => 85
                     ]
    ],
    (isset($raConfig) ? $raConfig : []) );


$oImgApp = new SEEDAppImgManager( $oApp, $raConfig );
echo Console02Static::HTMLPage( $oImgApp->Main(), "", 'EN', ["raScriptFiles" => [W_CORE_URL."js/SEEDCore.js"]] );   // sCharset defaults to utf8 and filesystem uses utf8


class SEEDAppImgManager
{
    private $oApp;
    private $rootdir;
    private $oIML;

    // controls
    private $currSubdir;
    private $bUsePFork;
    private $bShowDelLinks;
    private $bShowOnlyIncomplete;
    private $bSubdirs;

    function __construct( SEEDAppConsole $oApp, $raConfig )
    {
        $this->oApp = $oApp;
        $this->oIML = new SEEDImgManLib( $oApp, $raConfig['imgmanlib'] );

        $this->rootdir = $raConfig['rootdir'];
        $this->currSubdir = $oApp->oC->oSVA->SmartGPC( 'imgman_currSubdir', array() );

        // how do you turn off a checkbox with SmartGPC (unchecked comes back as unset which means use the previous value)
        //$this->bShowDelLinks = $oApp->oC->oSVA->SmartGPC( 'imgman_bShowDelLinks', array(0,1) );
        if( isset($_REQUEST['bControlsSubmitted']) ) {  // this just says that the control form was submitted
            $oApp->oC->oSVA->VarSet( 'imgman_bShowDelLinks',       SEEDInput_Int('imgman_bShowDelLinks') );
            $oApp->oC->oSVA->VarSet( 'imgman_bShowOnlyIncomplete', SEEDInput_Int('imgman_bShowOnlyIncomplete') );
            $oApp->oC->oSVA->VarSet( 'imgman_bSubdirs',            SEEDInput_Int('imgman_bSubdirs') );
            $oApp->oC->oSVA->VarSet( 'imgman_bUsePFork',           SEEDInput_Int('imgman_bUsePFork') );
        }
        $this->bShowDelLinks = $oApp->oC->oSVA->VarGet( 'imgman_bShowDelLinks' );
        $this->bShowOnlyIncomplete = $oApp->oC->oSVA->VarGet( 'imgman_bShowOnlyIncomplete' );
        $this->bSubdirs = $oApp->oC->oSVA->VarGet( 'imgman_bSubdirs' );
        $this->bUsePFork = $oApp->oC->oSVA->VarGet( 'imgman_bUsePFork' );
    }

    function Main()
    {
        $s = "";

        if( ($n = SEEDInput_Str('n')) ) {
            $this->oIML->ShowImg( $this->rootdir.$n );
            exit;   // ShowImg exits anyway but this makes it obvious
        }

        if( ($n = SEEDInput_Str('del')) ) {
            $fullname = $this->rootdir.$n;
            if( file_exists($fullname) ) {
                unlink($fullname);
            }
        }

        $currDir = $this->rootdir.$this->currSubdir;
        if( !SEEDCore_EndsWith( $currDir, '/' ) ) $currDir .= '/';

        if( ($cmd = SEEDInput_Str('cmd')) ) {
            $raFiles = $this->oIML->AnalyseImages( $this->oIML->GetAllImgInDir( $currDir, $this->bSubdirs ) );
            list($nActionConvert,$nActionKeep,$nActionDelete) = $this->getNActions( $raFiles );

            if( $cmd == 'convert' && $nActionConvert > 200 ) {
                $s .= "<div class='alert alert-danger'>Too many files to convert</div>";
                goto skipCmd;
            }

            if( $cmd == 'singlekeep' || $cmd == 'singledelete' ) {
                if( !($relbase = SEEDInput_Str('relbase')) )  die( "relbase not specified with cmd $cmd" );
                $searchForFilebase = $this->rootdir.$relbase;
            }
            foreach( $raFiles as $dir => $raF ) {
                foreach( $raF as $filebase => $raFVar ) {
                    if( ($cmd == 'convert' && $raFVar['action'] == 'CONVERT') ||
                        ($cmd == 'multikeep' && SEEDCore_StartsWith( $raFVar['action'], 'KEEP_ORIG' )) ||
                        ($cmd == 'multidelete' && SEEDCore_StartsWith( $raFVar['action'], 'DELETE_ORIG' )) )
                    {
                        $this->oIML->DoAction( $dir, $filebase, $raFVar, $this->bUsePFork && $raFVar['action'] == 'CONVERT' );
                    }

                    if( ($cmd == 'singlekeep' && SEEDCore_StartsWith($raFVar['action'],'KEEP_ORIG') && $dir.$filebase == $searchForFilebase) ||
                        ($cmd == 'singledelete' && SEEDCore_StartsWith($raFVar['action'],'DELETE_ORIG') && $dir.$filebase == $searchForFilebase) )
                    {
                        $this->oIML->DoAction( $dir, $filebase, $raFVar );
                    }
                }
            }
        }
        skipCmd:

        // re-run this to get any changes made above
        $raFiles = $this->oIML->AnalyseImages( $this->oIML->GetAllImgInDir( $currDir, $this->bSubdirs ) );
        list($nActionConvert,$nActionKeep,$nActionDelete) = $this->getNActions( $raFiles );

        $s .= "<div style='float:right'><form method='post'><input type='hidden' name='bControlsSubmitted' value='1'/>"
                 ."<div><input type='checkbox' name='imgman_bShowOnlyIncomplete' value='1' ".($this->bShowOnlyIncomplete ? 'checked' : "")."/> Show Only Incomplete Files</div>"
                 ."<div><input type='checkbox' name='imgman_bSubdirs' value='1' ".($this->bSubdirs ? 'checked' : "")."/> Show Subdirectories</div>"
                 ."<div><input type='checkbox' name='imgman_bUsePFork' value='1' ".($this->bUsePFork ? 'checked' : "")."/> Use Multiprocessors</div>"
                 ."<div><input type='checkbox' name='imgman_bShowDelLinks' value='1' ".($this->bShowDelLinks ? 'checked' : "")."/> Show Del Links</div>"
                 ."<div>"
                     ."<input type='text' name='imgman_currSubdir' id='imgman_currSubdir' value='".SEEDCore_HSC($this->currSubdir)."' size='30'/>"
                     ."<button type='button' id='backbutton'>&lt;-</button>"  // type='button' makes it non-submit
                 ."</div>"
                 ."<div><input type='submit' value='Set Controls'/></div>"
             ."</form></div>";

        if( $nActionConvert )  $s .= "<p><a href='?cmd=convert'>Click here to convert $nActionConvert jpg files to {$this->oIML->targetExt}</a></p>";
        if( $nActionKeep )     $s .= "<p><a href='?cmd=multikeep' style='color:green'>Click here to execute the $nActionKeep <b>Keep</b> links below</a></p>";
        if( $nActionDelete )   $s .= "<p><a href='?cmd=multidelete' style='color:red'>Click here to execute the $nActionDelete <b>Delete</b> links below</a></p>";

        $s .= ($realDir=realPath($currDir)) ? "<h3>Files under $realDir</h3>" : "<h3 style='color:red'>Can't find $currDir</h3>";

        $s .= $this->DrawFiles( $raFiles );

        $s .= "<style>
               #backbutton {}
               .imgman-ext { display:inline-block; }
               .imgman-ext-gif, .imgman-ext-png { background-color:#aaf;color:#000;padding:1px 3px; }
               </style>";

        $s .= "<script>
              $(document).ready( function() {
                      $('#backbutton').click( function(e) {
                          e.preventDefault();
                          let v = $('#imgman_currSubdir').val();
                          v = v.match( /^(.*)\/.*\/$/ );
                          $('#imgman_currSubdir').val( v == null ? '' : (v[1]+'/') );
                      });
              });
               </script>";

        $s .= "<script>SEEDCore_CleanBrowserAddress();</script>";

        done:
        return( $s );
    }

    private function getNActions( $raFiles )
    /***************************************
        Count how many actions are recommended
        $raFiles = [dir][filebase] => array( 'exts'=> [ext1 => fileinfo, ext2 => fileinfo, ...], 'action' => ... )
     */
    {
        $nActionConvert = $nActionKeep = $nActionDelete = 0;
        foreach( $raFiles as $dir => $raF ) {
            foreach( $raF as $filebase => $raFVar ) {
                if( !@$raFVar['action'] )  continue;

                if( $raFVar['action'] == 'CONVERT' ) {
                    $nActionConvert++;
                } else if( SEEDCore_StartsWith( $raFVar['action'], 'KEEP_ORIG' ) ) {
                    $nActionKeep++;
                } else if( SEEDCore_StartsWith( $raFVar['action'], 'DELETE_ORIG' ) ) {
                    $nActionDelete++;
                } else {
                    die( "Unexpected action ".$raFVar['action'] );
                }
            }
        }
        return( [$nActionConvert,$nActionKeep,$nActionDelete] );
    }

    function DrawFiles( $raFiles )
    {
        $s = "<style>#drawfilestable td { padding-right:20px }</style>"
            ."<table id='drawfilestable' style='border:none'>";

        /* $raFiles = array( dir => array( filebase => array( ext1 => fileinfo, ext2 => fileinfo, ...
         */
        foreach( $raFiles as $dir => $raF ) {
            $reldir = substr($dir,strlen($this->rootdir));

            $bDrawDir = true;
            $whatYouSaved = 0;
            foreach( $raF as $filebase => $raFVar ) {
                //$raExts = $raFVar['exts'];
                $raExts = [];
                $bOnlyR = $raFVar['r']['filename'] && !$raFVar['o']['filename'];
                if( $this->bShowOnlyIncomplete ) {
                    if( $bOnlyR ) continue;
/*
                    if( count($raExts)==1 && isset($raExts['jpeg']) )  continue;    // don't show files that only have jpeg
                    // we convert gif to webp or call them 'reduced' if( count($raExts)==1 && isset($raExts['gif']) )   continue;    // don't bother showing files that we don't convert
                    if( count($raExts)==1 && isset($raExts['webp']) )  continue;    // assume that webp are already reduced and scaled
                                                                                    // (this is a temporary assumption that will not make sense some day, especially because webp are often better compressed than jpg)
                    if( count($raExts)==1 &&
                        (isset($raExts['png']) || isset($raExts['mp4']) || isset($raExts['webm']) || isset($raExts['gif']) ) &&
                        substr($filebase,-7) == 'reduced' )          continue;    // don't show png or mpg files that have been manually reduced
*/
                }

                // this dir has files to show so draw it
                if( $bDrawDir ) {
                    $s .= "<tr><td colspan='7' style='font-weight:bold'><br/><a href='?imgman_currSubdir=".urlencode($reldir)."'>$dir</a>[[whatYouSaved]]</td></tr>";
                    $bDrawDir = false;
                }

                $relfile = $reldir.$filebase;
                $s .= "<tr><td width='30px'>&nbsp;</td>"
                     ."<td style='max-width:150px'>$filebase</td>";
                $infoJpeg = array(); $infoOther = array();
                $sizeJpeg = $sizeOther = $scaleJpeg = $scaleOther = $sizePercent = $scalePercent = 0;
                $sMsg = "";
                $colour = "";
//$raExts is not used
                foreach( $raExts as $ext => $raFileinfo ) {
                    $relfurl = urlencode($relfile.".".$ext);
                    if( $ext == $this->oIML->targetExt ) {
                        $infoJpeg = $raFileinfo;
                        $sizeJpeg = $raFileinfo['filesize'];
                        $scaleJpeg = $raFileinfo['w'];
                    } else {
                        $infoOther = $raFileinfo;
                        $sizeOther = $raFileinfo['filesize'];
                        $scaleOther = $raFileinfo['w'];
                    }
                    $s .= "<td>"
                             ."<a href='?n=$relfurl' target='_blank'><span class='imgman-ext imgman-ext-$ext'>$ext</span></a>&nbsp;&nbsp;"
                             .($this->bShowDelLinks ? "<a href='?del=$relfurl' style='color:red'>Del</a>" : "")
                         ."</td>";
                }
                if( count($raExts) == 1 ) {
                    // extra column needed
                    $s .= "<td>&nbsp;</td>";
                }
                foreach( ['o','r'] as $i ) {
                    if( $raFVar[$i]['filename'] ) {
                        $ext = pathinfo( $raFVar[$i]['filename'], PATHINFO_EXTENSION );
                        $relfurl = urlencode($reldir.$raFVar[$i]['filename']);
                        $style = $i=='r' ? "color:green" : "";
                        $s .= "<td>"
                                 ."<a href='?n=$relfurl' target='_blank' style='$style'><span class='imgman-ext imgman-ext-$ext'>"
                                 ."$ext".($bOnlyR ? "&nbsp;&nbsp;(R)" : "")."</span></a>&nbsp;&nbsp;"
                                 .($this->bShowDelLinks ? "<a href='?del=$relfurl' style='color:red'>Del</a>" : "")
                             ."</td>";
                    } else {
                        $s .= "<td>&nbsp</td>";
                    }
                }


                // Third column shows scale
                $sScale = "";
                $scaleR = @$raFVar['analysis']['scaleR'];
                $scaleO = @$raFVar['analysis']['scaleO'];

                if( $scaleR && $scaleO ) {
                    if( $raFVar['analysis']['scalePercent'] < 100.0 ) {
                        $sScale = "<span>{$raFVar['analysis']['sScaleO']}</span> &gt; "
                                 ." <span style='color:green'>{$raFVar['analysis']['sScaleR']}</span>";
                    } else if( $raFVar['analysis']['scalePercent'] > 100.0 ) {
                        $sScale = "<span>{$raFVar['analysis']['sScaleO']}</span> &lt; "
                                 ." <span style='color:red'>{$raFVar['analysis']['sScaleR']}</span>";
                    } else {
                        $sScale = $raFVar['analysis']['sScaleR'];
                    }
                } else if( $scaleR ) {
                    $sScale = $raFVar['analysis']['sScaleR'];
                } else if( $scaleO ) {
                    $sScale = $raFVar['analysis']['sScaleO'];
                }
                $s .= "<td style='font-size:8pt'>$sScale</td>";

                // Fourth column shows filesize
                $sSize = "";
                $sizeR = @$raFVar['analysis']['sizeR'];
                $sizeO = @$raFVar['analysis']['sizeO'];
                $fhR = @$raFVar['analysis']['sizeHumanR'];
                $fhO = @$raFVar['analysis']['sizeHumanO'];
                if( $sizeR && $sizeO ) {
                    $percent = intval($raFVar['analysis']['sizePercent']);
                    if( $sizeR < $sizeO ) {
                        $sSize = "<span>$fhO</span> &gt; <span style='color:green'>$fhR</span> ($percent)%";
                    } else if( $sizeR > $sizeO ) {
                        $sSize = "<span>$fhO</span> &lt; <span style='color:red'>$fhR</span> ($percent)%";
                    } else {
                        $sSize = $fhR;
                    }
                    $whatYouSaved += $sizeO - $sizeR;
                } else if( $sizeR ) {
                    $sSize = $fhR;
                } else if( $scaleO ) {
                    $sSize = $fhO;
                }
                $s .= "<td style='font-size:8pt'>$sSize</td>";

                // Fifth column shows action
                $relfurl = urlencode($relfile);
                $linkDelJpg = "<b><a href='?cmd=singledelete&relbase=$relfurl' style='color:red'>Delete</a></b>";
                $linkKeepJpg = "<b><a href='?cmd=singlekeep&relbase=$relfurl' style='color:green'>Keep</a></b>";

$fScalePercentThreshold = 90.0;
                $sMsg = "";
                if( $scaleR && $scaleO && $sizeR && $sizeO && @$raFVar['action'] ) {
                    list($action,$reason) = explode( ' ', $raFVar['action'] );
                    if( $action == 'DELETE_ORIG' && $reason == 'MAJOR_FILESIZE_REDUCTION' ) {
                        if( $raFVar['analysis']['scalePercent'] > $fScalePercentThreshold ) {
                            $sMsg = "$linkDelJpg : Filesize reduced a lot with "
                                   .($raFVar['analysis']['scalePercent'] == 100.0 ? "no" : "<b>minor</b>")
                                   ." loss of scale - delete original JPG";
                            $colour = "orange";
                        } else {
                            $sMsg = " $linkDelJpg : Filesize reduced a lot with significant loss of scale - delete original JPG";
                            $colour = "red";
                        }
                    } else if( $action == 'KEEP_ORIG' && $reason == 'MINOR_FILESIZE_REDUCTION' ) {
                        $sMsg = "$linkKeepJpg : Minor filesize reduction -- keep original JPG";
                        $colour = "green";
                    } else if( $action == 'KEEP_ORIG' && $reason == 'FILESIZE_INCREASE' ) {
                        $sMsg = "$linkKeepJpg : File got bigger -- keep original JPG";
                        $colour = "green";
                    } else if( $action == 'KEEP_ORIG' && $reason == 'FILESIZE_UNCHANGED' ) {
                        $sMsg = " $linkKeepJpg : Filesize not changed -- keep original JPG";
                        $colour = "green";
                    } else {
                        die( "Unexpected action ".$raFVar['action'] );
                    }
                }
                $s .= "<td style='color:$colour'>$sMsg</td>"
                     ."</tr>";
            }
            $s = str_replace( "[[whatYouSaved]]", ($whatYouSaved ? (" (".SEEDCore_HumanFileSize($whatYouSaved)." saved)") : ""), $s );
        }

        $s .= "</table>";

        return( $s );
    }
}
