<?php
// a.php  dir   url   filetop  [filebot] [filepad]
//
//     url can contain a %[n]d code which will be replaced by filebot..filetop
//     dir can be . as a placeholder in the command line


$url      = @$argv[1];
$dir      = @$argv[2];
$filetop  = intval(@$argv[3]);
$filebot  = intval(@$argv[4]);  // default 0
$filepad  = intval(@$argv[5]); 

if( !$url )  die( "a  ( http://example.com/01.jpg | http://example.com/%02d.jpg )  [dir=.]  [filetop=20]  [filebot=0]  [filepad=based on filetop]\n" );

if( !$filetop )  $filetop = 20;
if( !$filepad )  $filepad = ($filetop > 100 ? 3 : ($filetop > 10 ? 2 : 1 ));


if( $dir == "" )  $dir = '.';
if( $dir != '.') {
    exec( "mkdir \"$dir\"" );
}


if( strpos($url,'%') === false ) {
    if( substr($url,-4) == '.jpg' && is_numeric(substr($url,-6,2)) ) {
        $url = substr($url,0,strlen($url)-6) . "%02d.jpg";
    } else {
        die( "Need a format ending with {dd}.jpg - put a %[]d in the url" );
    }
}

echo "\nGetting $url from $filebot to $filetop in $dir/\n\n";

for( $j = $filebot; $j <= $filetop; ++$j ) {
    $s = sprintf( "curl $url > \"$dir/%0${filepad}d.jpg\"\n", $j, $j );
    echo $s;
    exec( $s );
}

?>
