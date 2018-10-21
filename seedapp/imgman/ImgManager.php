<?php

include_once( SEEDCORE."console/console02.php" );
include_once( SEEDLIB."SEEDImg/SEEDImgManLib.php" );

class SEEDAppImgManager
{
    private $oApp;
    private $rootdir;
    private $oIML;

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


        $raFiles = $this->oIML->GetAllImgInDir( $this->rootdir );
        $raOverlap = $this->oIML->FindOverlap( $raFiles );

        if( count($raOverlap) ) {
            $s .= "<h3>You have overlapping image versions</h3>";
            $s .= $this->DrawOverlaps( $raOverlap );
            goto done;
        }

        $bJPGFound = true; // scan raFiles for JPG
        if( $bJPGFound ) {
            $s .= "<h3>You have unconverted JPG images</h3>";
            $s .= "<p>Click here to convert N files</p>";
            goto done;
        }

        done:
        return( $s );
    }


    function DrawOverlaps( $raOverlap )
    {
        $s = "";

        foreach( $raOverlap as $dir => $raFiles ) {
            $s .= "<div>$dir</div>";
            foreach( $raFiles as $filename => $raExts ) {
                $reldir = substr($dir,strlen($this->rootdir));
                $s .= "<div style='margin-left:30px' class='row'>";
                $infoJpeg = array(); $infoOther = array();
                $sizeJpeg = $sizeOther = $scaleJpeg = $scaleOther = 0;
                foreach( $raExts as $ext ) {
                    $fullname = $dir.$filename.".".$ext;
                    $relfname = $reldir.$filename.".".$ext;
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
                if( $bSized && $bScaled ) {
                    $sMsg = "Jpeg is scaled and smaller file - delete JPG";
                    $colour = "#e66";
                } else if( $bSized ) {
                    $sMsg = "Jpeg is smaller file - delete JPG";
                    $colour = "#ea6";
                } else if( $bScaled ) {
                    $sMsg = "Jpeg is scaled - delete JPG";
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