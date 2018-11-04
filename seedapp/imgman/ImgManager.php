<?php

include_once( SEEDCORE."console/console02.php" );
include_once( SEEDLIB."SEEDImg/SEEDImgManLib.php" );

class SEEDAppImgManager
{
    private $oApp;
    private $rootdir;
    private $oIML;

    private $bDebug = false;    // make this true to show what we're doing

    function __construct( SEEDAppConsole $oApp, $raConfig )
    {
        $this->oApp = $oApp;
        $this->rootdir = $raConfig['rootdir'];
        $this->showDelLinks = intval(@$_REQUEST['showDelLinks']);
        $this->oIML = new SEEDImgManLib( $oApp );
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

        if( SEEDInput_Str('cmd') == 'convert' ) {
            $nConverted = 0;
            $raFiles = $this->oIML->GetAllImgInDir( $this->rootdir );
            foreach( $raFiles as $dir => $raF ) {
                foreach( $raF as $file => $raExt ) {
                    if( (in_array('jpg', $raExt) || in_array('JPG', $raExt)) && !in_array('jpeg', $raExt) ) {
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
        $raFiles = $this->oIML->GetAllImgInDir( $this->rootdir );
        $raOverlap = $this->oIML->FindOverlap( $raFiles );


//        if( count($raOverlap) ) {
//            $s .= "<h3>You have overlapping image versions</h3>";
//            $s .= $this->DrawOverlaps( $raOverlap );
//            goto done;
//        }


        $nJPG = 0;
        foreach( $raFiles as $dir => $raF ) {
            foreach( $raF as $file => $raExt ) {
                if( (in_array('jpg', $raExt) || in_array('JPG', $raExt)) && !in_array('jpeg', $raExt) ) {
                    $nJPG++;
                }
            }
        }

        $s .= "<div style='float:right'><form><input type='hidden' name='showDelLinks' value='1'/><input type='submit' value='Show Delete Links'/></form></div>";

        if( $nJPG ) {
            $s .= "<h3>You have unconverted JPG images</h3>"
                ."<p><a href='?cmd=convert'>Click here to convert $nJPG jpg files to jpeg</a></p>";
        } else {
            $s .= "<h3>Files under {$this->rootdir}</h3>";
        }
        $s .= $this->DrawFiles( $raFiles );


        done:
        return( $s );
    }

    function DrawFiles( $raFiles )
    {
        $s = "<style>#drawfilestable td { padding-right:20px }</style>"
            ."<table id='drawfilestable' style='border:none'>";

        foreach( $raFiles as $dir => $raF ) {
            $s .= "<tr><td colspan='5'>$dir</td></tr>";
            foreach( $raF as $filename => $raExts ) {
                $reldir = substr($dir,strlen($this->rootdir));
                $relfile = $reldir.$filename;
                $s .= "<tr><td width='30px'>&nbsp;</td>"
                     ."<td style='max-width:150px'>$filename</td>";
                $infoJpeg = array(); $infoOther = array();
                $sizeJpeg = $sizeOther = $scaleJpeg = $scaleOther = $sizePercent = $scalePercent = 0;
                $sMsg = "";
                $colour = "";
                foreach( $raExts as $ext ) {
                    $fullname = $dir.$filename.".".$ext;
                    $relfname = $relfile.".".$ext;
                    $info = $this->oIML->ImgInfo($fullname);
                    if( $ext == "jpeg" ) {
                        $infoJpeg = $info;
                        $sizeJpeg = $info['filesize'];
                        $scaleJpeg = $info['w'];
                    } else {
                        $infoOther = $info;
                        $sizeOther = $info['filesize'];
                        $scaleOther = $info['w'];
                    }
                    $s .= "<td>"
                             ."<a href='?n=$relfname' target='_blank'>$ext</a>&nbsp;&nbsp;"
                             .($this->showDelLinks ? "<a href='?del=$relfname' style='color:red'>Del</a>" : "")
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
                $bSizedALot = $sizePercent && ($sizePercent <= 90);

                //$bSized = ($infoJpeg['filesize'] < $infoOther['filesize']);
                //    $bScaled = ($infoJpeg['w'] < $infoOther['w']);
$bSized = $bScaled = false;
                $linkDelJpg = "<b><a href='?del=$relfile.jpg' style='color:red'>Delete</a></b>";
                $linkKeepJpg = "<b><a href='?move=$relfile.jpg' style='color:green'>Keep</a></b>";

                if( !$scaleJpeg || !$scaleOther || !$sizeJpeg || !$sizeOther ) {
                    $sMsg = "";
                } else
                if( $bSizedALot ) {
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

    function DrawOverlaps( $raOverlap )
    {
        $s = "";

        foreach( $raOverlap as $dir => $raFiles ) {
            $s .= "<div>$dir</div>";
            foreach( $raFiles as $filename => $raExts ) {
                $reldir = substr($dir,strlen($this->rootdir));
                $relfile = $reldir.$filename;
                $s .= "<div style='margin-left:30px' class='row'>";
                $infoJpeg = array(); $infoOther = array();
                $sizeJpeg = $sizeOther = $scaleJpeg = $scaleOther = 0;
                foreach( $raExts as $ext ) {
                    $fullname = $dir.$filename.".".$ext;
                    $relfname = $relfile.".".$ext;
                    $info = $this->oIML->ImgInfo($fullname);
                    if( $ext == "jpeg" ) {
                        $infoJpeg = $info;
                        $sizeJpeg = $info['filesize'];
                        $scaleJpeg = max($info['w'], $info['h']);
                    } else {
                        $infoOther = $info;
                        $sizeOther = $info['filesize'];
                        $scaleOther = max($info['w'], $info['h']);
                    }
                    $s .= "<div class='col-md-3'>"
                             ."<a href='?n=$relfname' target='_blank'>$filename.$ext</a>&nbsp;&nbsp;"
                             ."<a href='?del=$relfname' style='color:red'>Del</a>"
                         ."</div>";
                }
                $bSized = (floatval($infoJpeg['filesize'])/floatval($infoOther['filesize']) < 0.8);
                $bScaled = ($infoJpeg['w'] < $infoOther['w']);
                $linkDelJpg = "<a href='?del=$relfile.jpg' style='color:red'>Del</a>";
                if( $bSized && $bScaled ) {
                    $sMsg = "Jpeg is scaled and smaller file - delete JPG $linkDelJpg";
                    $colour = "#e66";
                } else if( $bSized ) {
                    $sMsg = "Jpeg is smaller file - delete JPG $linkDelJpg";
                    $colour = "#ea6";
                } else if( $bScaled ) {
                    $sMsg = "Jpeg is scaled - delete JPG $linkDelJpg";
                    $colour = "#ee6";
                } else {
                    $sMsg = "JPG is good - rename to overwrite Jpeg";
                    $colour = "green";
                }

                // Third column shows scale
                if( $bScaled ) {
                    $sScale = "<span style='color:green'>${infoJpeg['w']} x ${infoJpeg['h']}</span> < "
                             ." <span>${infoOther['w']} x ${infoOther['h']}</span>";
                } else if( $infoJpeg['w'] == $infoOther['w'] ) {
                    $sScale = "${infoJpeg['w']} x ${infoJpeg['h']} both";
                } else {
                    $sScale = "${infoJpeg['w']} x ${infoJpeg['h']} > "
                             ."${infoOther['w']} x ${infoOther['h']}";
                }
                $s .= "<div class='col-md-2' style='font-size:8pt'>$sScale</div>";

                // Fourth column shows filesize
                if( $bSized) {
                    $sSize = "<span style='color:green'>${infoJpeg['filesize_human']}</span> < "
                             ." <span>${infoOther['filesize_human']}</span>";
                } else if( $infoJpeg['filesize'] == $infoOther['filesize'] ) {
                    $sSize = "${infoJpeg['filesize_human']} both";
                } else {
                    $sSize = "${infoJpeg['filesize_human']} > ${infoOther['filesize_human']}";
                }
                $s .= "<div class='col-md-1' style='font-size:8pt'>$sSize</div>";

                // Fifth column shows action
                $s .= "<div class='col-md-3' style='color:$colour'>$sMsg</div>";
                $s .= "</div>";
            }
        }
        return( $s );
    }
}


function ImgManagerApp( SEEDAppConsole $oApp, $rootdir )
{
    $oImgApp = new SEEDAppImgManager( $oApp, array( 'rootdir'=>$rootdir ) );

    echo Console02Static::HTMLPage( utf8_encode($oImgApp->Main()), "", 'EN', array() );   // sCharset defaults to utf8
}

?>