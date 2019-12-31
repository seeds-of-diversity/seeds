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

    private $bDebug = false;    // make this true to show what we're doing

    function __construct( SEEDAppSession $oApp, $raConfig )
    {
        $this->oApp = $oApp;
        $this->raConfig = $raConfig;
        if( !isset($this->raConfig['fSizePercentThreshold']) )  die( "fSizePercentThreshold not defined in SEEDImgManLib raConfig" );
        if( !isset($this->raConfig['bounding_box']) )           die( "bounding_box not defined in SEEDImgManLib raConfig" );
        if( !isset($this->raConfig['jpg_quality']) )            die( "jpg_quality not defined in SEEDImgManLib raConfig" );

        $this->oIM = new SEEDImgMan();
    }

    function ImgInfo( $filename )   { return( $this->oIM->ImgInfoByFilename( $filename ) ); }

    function ShowImg( $filename )   { $this->oIM->ShowImg( $filename ); }

    function GetAllImgInDir( $dir )
    /******************************
        Return [dir][filename][ext] = image info
     */
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
            $raFiles[$dir][$filename]['exts'][$ext] = $this->ImgInfo( $dir.$filename.'.'.$ext );
            $raFiles[$dir][$filename]['info'] = array();
            $raFiles[$dir][$filename]['action'] = '';
            $raFiles[$dir][$filename]['actionMsg'] = '';
        }
        ksort($raFiles);

        return( $raFiles );
    }

    function AnalyseImages( $raFiles )
    /*********************************
        With a file list from GetAllImgDir, decide what needs to be done
     */
    {
        foreach( $raFiles as $dir => &$raF ) {
            foreach( $raF as $file => &$raFVar ) {
                $raExts = $raFVar['exts'];

                foreach( $raExts as $ext => $raFileinfo ) {
                    if( $ext == "jpeg" ) {
                        $raFVar['info']['sizeJpeg'] = $raFileinfo['filesize'];
                        $raFVar['info']['filesize_human_Jpeg'] = $raFileinfo['filesize_human'];
                        $raFVar['info']['scaleJpeg'] = $raFileinfo['w'];
                        $raFVar['info']['sScaleX_Jpeg'] = $raFileinfo['w'].' x '.$raFileinfo['h'];
                    } else {
                        $raFVar['info']['otherExt'] = $ext;
                        $raFVar['info']['sizeOther'] = $raFileinfo['filesize'];
                        $raFVar['info']['filesize_human_Other'] = $raFileinfo['filesize_human'];
                        $raFVar['info']['scaleOther'] = $raFileinfo['w'];
                        $raFVar['info']['sScaleX_Other'] = $raFileinfo['w'].' x '.$raFileinfo['h'];
                    }
                }
                if( ($scaleJpeg = @$raFVar['info']['scaleJpeg']) && ($scaleOther = @$raFVar['info']['scaleOther']) ) {
                    $raFVar['info']['scalePercent'] = floatval($scaleJpeg) / floatval($scaleOther) * 100;
                }
                if( ($sizeJpeg = @$raFVar['info']['sizeJpeg']) && ($sizeOther = @$raFVar['info']['sizeOther']) ) {
                    $raFVar['info']['sizePercent'] = floatval($sizeJpeg) / floatval($sizeOther) * 100;
                }

                // If there is a jpg/JPG but no jpeg, CONVERT
                if( (isset($raExts['jpg']) || isset($raExts['JPG'])) && !isset($raExts['jpeg']) ) {
                    $raFVar['action'] = 'CONVERT';
                }

                // If there are scales and sizes of two files to compare, recommend an action
                if( $scaleJpeg && $scaleOther && $sizeJpeg && $sizeOther ) {
                    if( $raFVar['info']['sizePercent'] <= $this->raConfig['fSizePercentThreshold'] ) {
                        $raFVar['action'] = 'DELETE_ORIG MAJOR_FILESIZE_REDUCTION';
                    } else if( $sizeJpeg < $sizeOther ) {
                        $raFVar['action'] = 'KEEP_ORIG MINOR_FILESIZE_REDUCTION';
                    } else if( $sizeJpeg > $sizeOther ) {
                        $raFVar['action'] = 'KEEP_ORIG FILESIZE_INCREASE';
                    } else {
                        $raFVar['action'] = 'KEEP_ORIG FILESIZE_UNCHANGED';
                    }
                }
            }
        }

        return( $raFiles );
    }

    function DoAction( $dir, $filebase, $raFVar )
    {
        list($action) = explode( ' ', $raFVar['action'] );    // because KEEP_ORIG and DELETE_ORIG multiplex their action information

        switch( $action ) {
            case 'CONVERT':
                $ext = isset($raFVar['exts']['jpg']) ? 'jpg' : 'JPG';
                $exec = "convert \"${dir}${filebase}.${ext}\" "
                       ."-quality {$this->raConfig['jpg_quality']} "
                       ."-resize {$this->raConfig['bounding_box']}x{$this->raConfig['bounding_box']}\> "
                       ."\"${dir}${filebase}.jpeg\"";
                if( $this->bDebug ) echo $exec."<br/>";
                exec( $exec );
                // note cannot chown apache->other_user because only root can do chown (and we don't run apache as root)
                break;

            case 'KEEP_ORIG':
                // We like the original foo.jpg better than the converted foo.jpeg
                // Delete foo.jpeg and move foo.jpg -> foo.jpeg
                $movefrom = $dir.$filebase.".".$raFVar['info']['otherExt'];
                $moveto = $dir.$filebase.".jpeg";
                if( file_exists($moveto) ) {
                    if( $this->bDebug )  echo "Delete $moveto<br/>";
                    unlink($moveto);
                }
                if( $this->bDebug )  echo "Move $movefrom to $moveto<br/>";
                rename( $movefrom, $moveto );
                break;

            case 'DELETE_ORIG':
                $fullname = $dir.$filebase.".".$raFVar['info']['otherExt'];
                if( file_exists($fullname) ) {
                    if( $this->bDebug )  echo "Delete $fullname<br/>";
                    unlink($fullname);
                }
                break;

            default:
                die( "Unexpected action $action" );
        }
    }
}
