<?php

include_once( SEEDCORE."console/console02.php" );
include_once( SEEDLIB."SEEDImg/SEEDImgManLib.php" );

class SEEDAppImgManager
{
    private $oApp;
    private $rootdir;
    private $oIML;

    // controls
    private $currSubdir;
    private $bShowDelLinks;
    private $bShowOnlyOverlap;

    private $bDebug = false;    // make this true to show what we're doing

    function __construct( SEEDAppConsole $oApp, $raConfig )
    {
        $this->oApp = $oApp;
        $this->oIML = new SEEDImgManLib( $oApp );

        $this->rootdir = $raConfig['rootdir'];
        $this->currSubdir = $oApp->oC->oSVA->SmartGPC( 'imgman_currSubdir', array() );

        // how do you turn off a checkbox with SmartGPC (unchecked comes back as unset which means use the previous value)
        //$this->bShowDelLinks = $oApp->oC->oSVA->SmartGPC( 'imgman_bShowDelLinks', array(0,1) );
        if( isset($_REQUEST['bControlsSubmitted']) ) {  // this just says that the control form was submitted
            $oApp->oC->oSVA->VarSet( 'imgman_bShowDelLinks', intval(@$_REQUEST['imgman_bShowDelLinks']) );
            $oApp->oC->oSVA->VarSet( 'imgman_bShowOnlyOverlap', intval(@$_REQUEST['imgman_bShowOnlyOverlap']) );
        }
        $this->bShowDelLinks = $oApp->oC->oSVA->VarGet( 'imgman_bShowDelLinks' );
        $this->bShowOnlyOverlap = $oApp->oC->oSVA->VarGet( 'imgman_bShowOnlyOverlap' );
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

        if( ($n = SEEDInput_Str('move')) ) {
            // Move foo.jpg to foo.jpeg (usually because foo.jpg is a good file)
            $newfilename = $this->rootdir.substr($n,0,strrpos($n,'.')).".jpeg";
            $oldfilename = $this->rootdir.$n;
            if( file_exists($newfilename) ) {
                unlink($newfilename);
            }
            rename( $oldfilename, $newfilename );
        }

        $currDir = $this->rootdir.$this->currSubdir;
        if( substr($currDir,-1,1) != '/' ) $currDir .= '/';

        if( SEEDInput_Str('cmd') == 'convert' ) {
            $nConverted = 0;
            $raFiles = $this->oIML->GetAllImgInDir( $currDir );
            /* $raFiles = array( dir => array( filebase => array( ext1 => fileinfo, ext2 => fileinfo, ...
             */
            foreach( $raFiles as $dir => $raF ) {
                foreach( $raF as $file => $raExt ) {
                    if( (isset($raExt['jpg']) || isset($raExt['JPG'])) && !isset($raExt['jpeg']) ) {
                        $nConverted++;
                        $exec = "convert \"${dir}${file}.jpg\" -quality 85 -resize 1200x1200\> \"${dir}${file}.jpeg\"";
                        if( $this->bDebug ) echo $exec."<br/>";
                        exec( $exec );

                        // note cannot chown apache->other_user because only root can do chown (and we don't run apache as root)
                    }
                }
            }
        }

        // if converted above, re-run this
        $raFiles = $this->oIML->GetAllImgInDir( $currDir );

//        $raOverlap = $this->oIML->FindOverlap( $raFiles );
//        if( count($raOverlap) ) {
//            $s .= "<h3>You have overlapping image versions</h3>";
//            $s .= $this->DrawOverlaps( $raOverlap );
//            goto done;
//        }


        $nJPG = 0;
        /* $raFiles = array( dir => array( filebase => array( ext1 => fileinfo, ext2 => fileinfo, ...
         */
        foreach( $raFiles as $dir => $raF ) {
            foreach( $raF as $file => $raExt ) {
                if( (isset($raExt['jpg']) || isset($raExt['JPG'])) && !isset($raExt['jpeg']) ) {
                    $nJPG++;
                }
            }
        }

        $s .= "<div style='float:right'><form method='post'><input type='hidden' name='bControlsSubmitted' value='1'/>"
                 ."<div><input type='checkbox' name='imgman_bShowDelLinks' value='1' ".($this->bShowDelLinks ? 'checked' : "")."/> Show Del Links</div>"
                 ."<div><input type='checkbox' name='imgman_bShowOnlyOverlap' value='1' ".($this->bShowOnlyOverlap ? 'checked' : "")."/> Show Only Incomplete Files</div>"
                 ."<div><input type='text' name='imgman_currSubdir' value='".SEEDCore_HSC($this->currSubdir)."' size='30'/> Current Subdirectory</div>"
                 ."<div><input type='submit' value='Set Controls'/></div>"
             ."</form></div>";

        if( $nJPG ) {
            $s .= "<h3>You have unconverted JPG images</h3>"
                ."<p><a href='?cmd=convert'>Click here to convert $nJPG jpg files to jpeg</a></p>";
        } else {
            $s .= "<h3>Files under $currDir</h3>";
        }
        $s .= $this->DrawFiles( $raFiles );

        $s .= "<script>SEEDCore_CleanBrowserAddress();</script>";

        done:
        return( $s );
    }

    function DrawFiles( $raFiles )
    {
        $s = "<style>#drawfilestable td { padding-right:20px }</style>"
            ."<table id='drawfilestable' style='border:none'>";

        /* $raFiles = array( dir => array( filebase => array( ext1 => fileinfo, ext2 => fileinfo, ...
         */
        foreach( $raFiles as $dir => $raF ) {
            $reldir = substr($dir,strlen($this->rootdir));

            $s .= "<tr><td colspan='5' style='font-weight:bold'><br/><a href='?imgman_currSubdir=$reldir'>$dir</a></td></tr>";
            foreach( $raF as $filename => $raExts ) {
                if( $this->bShowOnlyOverlap && count($raExts)==1 && isset($raExts['jpeg']) ) {
                    // don't show files that have been completed
                    continue;
                }

                $relfile = $reldir.$filename;
                $s .= "<tr><td width='30px'>&nbsp;</td>"
                     ."<td style='max-width:150px'>$filename</td>";
                $infoJpeg = array(); $infoOther = array();
                $sizeJpeg = $sizeOther = $scaleJpeg = $scaleOther = $sizePercent = $scalePercent = 0;
                $sMsg = "";
                $colour = "";
                foreach( $raExts as $ext => $raFileinfo ) {
                    $relfname = $relfile.".".$ext;
                    if( $ext == "jpeg" ) {
                        $infoJpeg = $raFileinfo;
                        $sizeJpeg = $raFileinfo['filesize'];
                        $scaleJpeg = $raFileinfo['w'];
                    } else {
                        $infoOther = $raFileinfo;
                        $sizeOther = $raFileinfo['filesize'];
                        $scaleOther = $raFileinfo['w'];
                    }
                    $s .= "<td>"
                             ."<a href='?n=$relfname' target='_blank'>$ext</a>&nbsp;&nbsp;"
                             .($this->bShowDelLinks ? "<a href='?del=$relfname' style='color:red'>Del</a>" : "")
                         ."</td>";
                }
                if( count($raExts) == 1 ) {
                    // extra column needed
                    $s .= "<td>&nbsp;</td>";
                }

                // Third column shows scale
                if( $scaleJpeg && $scaleOther ) {
                    $scalePercent = intval( (floatval($scaleJpeg) / floatval($scaleOther)) * 100);

                    if( $scaleJpeg < $scaleOther ) {
                        $sScale = "<span style='color:green'>${infoJpeg['w']} x ${infoJpeg['h']}</span> < "
                                 ." <span>${infoOther['w']} x ${infoOther['h']}</span>";
                    } else if( $scaleJpeg > $scaleOther ) {
                        $sScale = "<span style='color:red'>${infoJpeg['w']} x ${infoJpeg['h']}</span> > "
                                 ." <span>${infoOther['w']} x ${infoOther['h']}</span>";
                    } else {
                        $sScale = "${infoJpeg['w']} x ${infoJpeg['h']}";
                    }
                } else {
                    if( $scaleJpeg ) {
                        $sScale = "${infoJpeg['w']} x ${infoJpeg['h']}";
                    } else if( $scaleOther ) {
                        $sScale = "${infoOther['w']} x ${infoOther['h']}";
                    } else {
                        $sScale = "&nbsp;";
                    }
                }
                $s .= "<td style='font-size:8pt'>$sScale</td>";

                // Fourth column shows filesize
                if( $sizeJpeg && $sizeOther ) {
                    $sizePercent = intval( (floatval($sizeJpeg) / floatval($sizeOther)) * 100);

                    if( $sizeJpeg < $sizeOther ) {
                        $sSize = "<span style='color:green'>${infoJpeg['filesize_human']}</span> < "
                                 ." <span>${infoOther['filesize_human']}</span> ($sizePercent)%";
                    } else if( $sizeJpeg > $sizeOther ) {
                        $sSize = "<span style='color:red'>${infoJpeg['filesize_human']}</span> > "
                                 ." <span>${infoOther['filesize_human']}</span> ($sizePercent)%";
                    } else {
                        $sSize = $infoJpeg['filesize_human'];
                    }
                } else {
                    if( $sizeJpeg ) {
                        $sSize = $infoJpeg['filesize_human'];
                    } else if( $scaleOther ) {
                        $sSize = $infoOther['filesize_human'];
                    } else {
                        $sSize = "&nbsp;";
                    }
                }
                $s .= "<td style='font-size:8pt'>$sSize</td>";

                // Fifth column shows action
                $linkDelJpg = "<b><a href='?del=$relfile.jpg' style='color:red'>Delete</a></b>";
                $linkKeepJpg = "<b><a href='?move=$relfile.jpg' style='color:green'>Keep</a></b>";

$nSizePercentThreshold = 90;

                if( !$scaleJpeg || !$scaleOther || !$sizeJpeg || !$sizeOther ) {
                    $sMsg = "";
                } else
                if( $sizePercent <= $nSizePercentThreshold ) {
                    // arbitrary scalePercentThreshold not related to nSizePercentThreshold
                    if( $scalePercent > 90 ) {
                        $sMsg = "Filesize reduced a lot with ".($scalePercent == 100 ? "no" : "<b>minor</b>")." loss of scale - delete original JPG $linkDelJpg";
                        $colour = "orange";
                    } else {
                        $sMsg = "Filesize reduced a lot with significant loss of scale - delete original JPG $linkDelJpg";
                        $colour = "red";
                    }
                } else
                if( $sizeJpeg < $sizeOther ) {
                    $sMsg = "Minor filesize reduction -- keep original JPG $linkKeepJpg";
                    $colour = "green";
                } else
                if( $sizeJpeg > $sizeOther ) {
                    $sMsg = "File got bigger -- keep original JPG $linkKeepJpg";
                    $colour = "green";
                } else {
                    $sMsg = "Filesize not changed -- keep original JPG $linkKeepJpg";
                    $colour = "green";
                }

                $s .= "<td style='color:$colour'>$sMsg</td>";
                $s .= "</tr>";
            }
        }

        $s .= "</table>";

        return( $s );
    }
}


function ImgManagerApp( SEEDAppConsole $oApp, $rootdir )
{
    $oImgApp = new SEEDAppImgManager( $oApp, array( 'rootdir'=>$rootdir ) );

    $raParms = array( "raScriptFiles" => array( W_CORE."js/SEEDCore.js" ) );
    echo Console02Static::HTMLPage( utf8_encode($oImgApp->Main()), "", 'EN', $raParms );   // sCharset defaults to utf8
}

?>