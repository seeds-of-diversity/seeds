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

        $s .= $this->DrawFiles( $raFiles );


        if( count($raOverlap) ) {
            $s .= "<h3>You have overlapping image versions</h3>";
            $s .= $this->DrawOverlaps( $raOverlap );
            goto done;
        }


        $nJPG = 0;
        foreach( $raFiles as $dir => $raF ) {
            foreach( $raF as $file => $raExt ) {
                if( (in_array('jpg', $raExt) || in_array('JPG', $raExt)) && !in_array('jpeg', $raExt) ) {
                    $nJPG++;
                }
            }
        }


        if( $nJPG ) {
            $s .= "<h3>You have unconverted JPG images</h3>";
            $s .= "<p><a href='?cmd=convert'>Click here to convert $nJPG jpg files to jpeg</a></p>";
            goto done;
        } else {
            $s .= "<h3>Everything looks good</h3>";
        }

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
                $sizeJpeg = $sizeOther = $scaleJpeg = $scaleOther = 0;
                $sMsg = "";
                $colour = "";
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
                    $s .= "<td>"
                             ."<a href='?n=$relfname' target='_blank'>$ext</a>&nbsp;&nbsp;"
                             ."<a href='?del=$relfname' style='color:red'>Del</a>"
                         ."</td>";
                }
                if( $scaleJpeg && $scaleOther ) {
                    $bSized = (floatval($infoJpeg['filesize'])/floatval($infoOther['filesize']) < 0.8);
                    $bScaled = ($infoJpeg['w'] < $infoOther['w']);
                    $linkDelJpg = "<b><a href='?del=$relfile.jpg' style='color:red'>Del</a></b>";
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
                        $sMsg = "JPG is good - rename to overwrite Jpeg <b><a href='?move=$relfile.jpg' style='color:green'>Move</a></b>";
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
                    $s .= "<td style='font-size:8pt'>$sScale</td>";
                } else {
                    $s .= "<td>&nbsp;</td>";
                }

                // Fourth column shows filesize
                if( isset($infoJpeg['filesize']) && isset($infoOther['filesize']) ) {
                    if( $bSized ) {
                        $sSize = "<span style='color:green'>${infoJpeg['filesize_human']}</span> < "
                                 ." <span>${infoOther['filesize_human']}</span>";
                    } else if( $infoJpeg['filesize'] == $infoOther['filesize'] ) {
                        $sSize = "${infoJpeg['filesize_human']} both";
                    } else {
                        $sSize = "${infoJpeg['filesize_human']} > ${infoOther['filesize_human']}";
                    }
                    $s .= "<td style='font-size:8pt'>$sSize</td>";
                } else {
                    $s .= "<td>&nbsp;</td>";
                }

                // Fifth column shows action
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