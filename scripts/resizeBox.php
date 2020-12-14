<?php

/* Resize images to a minimum bounding square
 *
 * php -f resize.php box_size [args]                      = show what the script would do
 * php -f resize.php box_size resize [args]               = do it
 *
 * box_size: if it contains an 'x' it is used literally
 *           if it is numeric it is interpreted as {box_size}x{box_size}
 * args: recurse = do subdirectories too
 */

$sBound = @$argv[1];
if( !$sBound )  die( "php -f resize.php box_size [recurse] [resize]  :  box_size is a literal {X}x{Y} (either can be blank) or a number used as {N}x{N}\n\n" );

if( is_numeric($sBound) ) {
    // box_size is just a number so use {N}x{N}
    $nBoundX = $nBoundY = intval($sBound);
    $sBound = $sBound."x".$sBound;
} else if( strpos( $sBound, "x" ) !== false ) {
    // box_size is an imagick bound:  XxY, Xx, or xY
    // blank values are converted to 0 for analysis below
    list($nBoundX,$nBoundY) = explode( 'x', $sBound );
    $nBoundX = intval($nBoundX);
    $nBoundY = intval($nBoundY);
} else {
    die( "Unexpected format of box_size" );
}


$bResize = (@$argv[2]=='resize' || @$argv[3]=='resize');
$bRecurse = (@$argv[2]=='recurse' || @$argv[3]=='recurse');

if( !$bResize ) echo "***  SHOWING WHAT WOULD HAPPEN ***\n";


function doDir( $dir )
{
    global $bResize, $bRecurse, $sBound, $nBoundX, $nBoundY;

// uncomment this if convert is throwing an error and not telling you which dir it is in
//echo $dir."\n";

    if( !($od = opendir($dir)) ) {
        die( "Cannot open directory ". $dir );
    }
    while( ($od_file = readdir($od)) !== false ) {
        if( $od_file == "." || $od_file == ".." )  continue;
        $od_file = realpath($dir."/".$od_file);
        if( is_dir($od_file) ) {
            if( $bRecurse ) {
                doDir($od_file);
            }
            continue;
        }
        if( !in_array( pathinfo($od_file,PATHINFO_EXTENSION), ['jpg','jpeg'] ) ) {
            continue;
        }

        if( !($img_size = getimagesize( $od_file )) ) {
            echo "*** Error reading file $od_file ***\n";
            continue;
        }
        if( ($nBoundX && $img_size[0] > $nBoundX) || ($nBoundY && $img_size[1] > $nBoundY) ) {
            echo $img_size[0]."x".$img_size[1]." ".$od_file."\n";
            $s = "convert \"$od_file\" -resize {$sBound}\> -quality 85 \"$od_file\"";
            if( $bResize ) {
                system($s);
                echo "* $s\n";
            } else {
                echo "*** Would do ***  $s\n";
            }
            echo "\n";
        }
    }
    closedir($od);
}

doDir( "." );

?>
