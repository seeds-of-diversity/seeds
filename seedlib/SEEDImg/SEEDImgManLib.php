<?php

/* SEEDImgManLib.php
 *
 * Copyright (c) 2010-2018 Seeds of Diversity Canada
 *
 * The basics of an Image Management interface.
 */

include_once( SEEDCORE."SEEDImgMan.php" );

class SEEDImgManLib
{
    private $oApp;
    private $oIM;

    function __construct( SEEDAppSession $oApp )
    {
        $this->oApp = $oApp;
        $this->oIM = new SEEDImgMan();
    }

    function ImgInfo( $filename )   { return( $this->oIM->ImgInfoByFilename( $filename ) ); }

    function ShowImg( $filename )
    {
        if( file_exists($filename) ) {
            $info = $this->ImgInfo($filename);
            if( ($f = fopen( $filename, 'rb' )) ) {
                header( "Content-Type: ".$info['mime'] );
                header( "Content-Length: ".$info['filesize'] );
                fpassthru( $f );
            }
        }
        exit;
    }

    function GetAllImgInDir( $dir )
    {
        $s = "";

        $oFile = new SEEDFile();
        $oFile->Traverse( $dir, array('eFetch'=>'FILE') );

        $raFiles = array();
        foreach( $oFile->GetTraverseItems() as $k => $ra ) {
            $dir = $ra[0];
            $filename = $ra[1];
            if( ($i = strrpos( $filename, '.' )) !== false ) {
                $ext = substr( $filename, $i+1 );
                $filename = substr( $filename, 0, $i );
            }
            $raFiles[$dir][$filename][] = $ext;
        }
	ksort($raFiles);

        return( $raFiles );
    }

    function FindOverlap( $raFiles )
    /*******************************
        Return the portions of raFiles that have identical filenames with different exts
     */
    {
        $raOverlap = array();

        foreach( $raFiles as $dir => $raFilenames ) {
            foreach( $raFilenames as $filename => $raExts ) {
                if( count($raExts) > 1 ) {
                    $raOverlap[$dir][$filename] = $raExts;
                }
            }
        }

        done:
        return( $raOverlap );
    }

}

