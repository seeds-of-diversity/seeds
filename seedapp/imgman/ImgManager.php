<?php

include_once( SEEDCORE."console/console02.php" );
include_once( SEEDLIB."SEEDImg/SEEDImgManLib.php" );

function ImgManagerApp( SEEDAppConsole $oApp )
{
    $rootdir = '/home/bob/junk/imgtest/';
    $s = "";

    $oIML = new SEEDImgManLib( $oApp );

    if( ($n = SEEDInput_Str('n')) ) {
        $fullname = $rootdir.$n;
        if( file_exists($fullname) ) {
            $info = $oIML->ImgInfo($fullname);
            if( ($f = fopen( $fullname, 'rb' )) ) {
                header( "Content-Type: ".$info['mime'] );
                header( "Content-Length: ".$info['filesize'] );
                fpassthru( $f );
            }
        }
        exit;
    }

    if( ($n = SEEDInput_Str('del')) ) {
        $fullname = $rootdir.$n;
        if( file_exists($fullname) ) {
            unlink($fullname);
        }
    }


    $raFiles = $oIML->GetAllImgInDir( $rootdir );
    $raOverlap = $oIML->FindOverlap( $raFiles );

    if( count($raOverlap) ) {
        $s .= "<h3>You have overlapping image versions</h3>";
        foreach( $raOverlap as $dir => $raFiles ) {
            $s .= "<div>$dir</div>";
            foreach( $raFiles as $filename => $raExts ) {
                $reldir = substr($dir,strlen($rootdir));
                $s .= "<div style='margin-left:30px' class='row'>";
                foreach( $raExts as $ext ) {
                    $fullname = $dir.$filename.".".$ext;
                    $relfname = $reldir.$filename.".".$ext;
                    $info = $oIML->ImgInfo($fullname);
                    $s .= "<div class='col-md-3'>"
                             ."<a href='?n=$relfname' target='_blank'>$filename.$ext (${info['w']} x ${info['h']}) ${info['filesize_human']}</a>&nbsp;&nbsp;"
                             ."<a href='?del=$relfname' style='color:red'>Del</a>"
                         ."</div>";
                }
                $s .= "</div>";
            }
        }
    }



    echo Console02Static::HTMLPage( utf8_encode($s), "", 'EN', array() );   // sCharset defaults to utf8
}

?>