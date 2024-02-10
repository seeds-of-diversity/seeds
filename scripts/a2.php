<?php
// a.php  url  dirtop  filetop  [dirbot]  [filebot]  [dirpad]  [filepad]


$url = $argv[1];
$dirtop   = intval($argv[2]);
$filetop  = intval($argv[3]);
$dirbot   = intval(@$argv[4]);  //default 0
$filebot  = intval(@$argv[5]);  //default 0
$dirpad   = intval(@$argv[6]);
$filepad  = intval(@$argv[7]);

if( !$filetop )  die( "a.php http://example.com/00%02d/%02d.jpg dirtop filetop [dirbot=0] [filebot=0] [dirpad=based on dirtop] [filepad=based on filetop]\n" );

if( !$dirpad ) {
    $dirpad  = ($dirtop > 100  ? 3 : ($dirtop > 10  ? 2 : 1 ));
    $filepad = ($filetop > 100 ? 3 : ($filetop > 10 ? 2 : 1 ));
}

var_dump($dirbot,$dirtop);
for( $i = $dirbot; $i <= $dirtop; ++$i ) {
    $s = sprintf( "mkdir %0{$dirpad}d\n", $i );
    echo $s;
    exec( $s );
    for( $j = $filebot; $j <= $filetop; ++$j ) {
        $s = sprintf( "curl $url > %0{$dirpad}d/%0{$filepad}d.jpg\n", $i, $j, $i, $j );
        echo $s;
        exec( $s );
    }
}

?>
