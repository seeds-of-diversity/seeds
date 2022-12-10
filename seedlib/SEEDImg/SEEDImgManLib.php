<?php

/* SEEDImgManLib.php
 *
 * Copyright (c) 2010-2022 Seeds of Diversity Canada
 *
 * The basics of an Image Management interface.
 */

include_once( SEEDCORE."SEEDImgMan.php" );

class SEEDImgManLib
{
    private $oApp;
    private $raConfig;
    private $oIM;

    public $targetExt = "webp";    // default can be overridden by raConfig['targetExt']

    private $bDebug = false;    // make this true to show what we're doing

    function __construct( SEEDAppSession $oApp, $raConfig )
    {
        $this->oApp = $oApp;
        $this->raConfig = $raConfig;
        if( @$raConfig['targetExt'] )   $this->targetExt = $raConfig['targetExt'];

        if( !isset($this->raConfig['fSizePercentThreshold']) )  die( "fSizePercentThreshold not defined in SEEDImgManLib raConfig" );
        if( !isset($this->raConfig['bounding_box']) )           die( "bounding_box not defined in SEEDImgManLib raConfig" );
        if( !isset($this->raConfig['jpg_quality']) )            die( "jpg_quality not defined in SEEDImgManLib raConfig" );

        $this->oIM = new SEEDImgMan();
    }

    function ImgInfo( $filename )   { return( $this->oIM->ImgInfoByFilename( $filename ) ); }

    function ShowImg( $filename )   { $this->oIM->ShowImg( $filename ); }

    function GetAllImgInDir( $dir, $bSubdirs = true )
    /************************************************
        Return [dir][filename_base] = ['r'=> [image info about _r file],
                                       'o'=> [image info about any other file with same filename_base],
                                       'action' => recommended action for this filename_base,
                                       'actionMsg => '']
        where filename_base is the PATHINFO_FILENAME without any _r suffix
        and if more than one file has the same filename_base but not _r the first one is recorded and the rest are ignored for now with a warning
     */
    {
        $s = "";

        $oFile = new SEEDFile();
        $oFile->Traverse( $dir, ['eFetch'=>'FILE', 'bRecurse'=>(bool)$bSubdirs] );  // SEEDCore_SmartVal need this as bool type

        $raFiles = array();
        foreach( $oFile->GetTraverseItems() as $k => $ra ) {
            $dir = $ra[0];                                          // dir/
            $filename = $ra[1];                                     // filename.ext
            $filename_base = pathinfo($ra[1],PATHINFO_FILENAME);    // basic filename before the extension, modulo any _r suffix
            if( ($isReduced = SEEDCore_EndsWith( $filename_base, "_r" )) ) {
                $filename_base = substr($filename_base,0,-2);
            }

            // create empty info array for new names
            if( !isset($raFiles[$dir][$filename_base]) ) {
                $raFiles[$dir][$filename_base] = ['r' => ['filename'=>''],  // use filename to determine whether this was found
                                                  'o' => ['filename'=>''],  // use filename to determine whether this was found
                                                  'action' => '',
                                                  'actionMsg' => '' ];
            }

            if( $isReduced ) {
                if( $raFiles[$dir][$filename_base]['r']['filename'] ) {
                    $this->oApp->oC->AddErrMsg( "Duplicate file {$dir}{$filename_base}_r" );
                } else {
                    $raFiles[$dir][$filename_base]['r']['filename'] = $filename;
                    $raFiles[$dir][$filename_base]['r']['info'] = $this->ImgInfo( $dir.$filename );
                }
            } else {
                if( $raFiles[$dir][$filename_base]['o']['filename'] ) {
                    $this->oApp->oC->AddErrMsg( "Duplicate file {$dir}{$filename_base}" );
                } else {
                    $raFiles[$dir][$filename_base]['o']['filename'] = $filename;
                    $raFiles[$dir][$filename_base]['o']['info'] = $this->ImgInfo( $dir.$filename );
                }
            }
/*
            if( ($ext = pathinfo($ra[1],PATHINFO_EXTENSION)) ) {
                $raFiles[$dir][$filename_base]['exts'][$ext] =
                $raFiles[$dir][$filename_base]['info'] = array();
                $raFiles[$dir][$filename_base]['action'] = '';
                $raFiles[$dir][$filename_base]['actionMsg'] = '';
            }
*/
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
                // If there is an orig but no reduced version, CONVERT.
                // If there is a reduced but no orig, do nothing.
                // If there are both, recommend to KEEP or DELETE.
                if( $raFVar['o']['filename'] && !$raFVar['r']['filename'] ) {
                    if( $raFVar['o']['info']['filesize'] ) {
                        // the file should be converted - unless it is not a convertible image (e.g. mp4, docx, etc)
                        // sort of a kluge: if the original file is not a convertible type it is not given a filesize in ImgInfoByFilename()
                        $raFVar['action'] = 'CONVERT';
                    }
                } else
                if( $raFVar['o']['filename'] && $raFVar['r']['filename'] ) {
                    $raFVar['analysis']['sizeO'] = $raFVar['o']['info']['filesize'];
                    $raFVar['analysis']['sizeR'] = $raFVar['r']['info']['filesize'];
                    $raFVar['analysis']['sizeHumanO'] = $raFVar['o']['info']['filesize_human'];
                    $raFVar['analysis']['sizeHumanR'] = $raFVar['r']['info']['filesize_human'];
                    $raFVar['analysis']['scaleO'] = $raFVar['o']['info']['w'];
                    $raFVar['analysis']['scaleR'] = $raFVar['r']['info']['w'];
                    $raFVar['analysis']['sScaleO'] = $raFVar['o']['info']['w'].' x '.$raFVar['o']['info']['h'];
                    $raFVar['analysis']['sScaleR'] = $raFVar['r']['info']['w'].' x '.$raFVar['r']['info']['h'];

                    $sizeR  = floatval($raFVar['analysis']['sizeR']);
                    $sizeO  = floatval($raFVar['analysis']['sizeO']);
                    $scaleR = floatval($raFVar['analysis']['sizeR']);
                    $scaleO = floatval($raFVar['analysis']['sizeO']);

                    $raFVar['analysis']['sizePercent']  = $sizeO  ? ($sizeR / $sizeO * 100) : 100.0;    // default is no reduction when original size is unreadable
                    $raFVar['analysis']['scalePercent'] = $scaleO ? ($scaleR / $scaleO * 100) : 100.0;

                    if( $raFVar['analysis']['sizePercent'] <= $this->raConfig['fSizePercentThreshold'] ) {
                        $raFVar['action'] = 'DELETE_ORIG MAJOR_FILESIZE_REDUCTION';
                    } else if( $sizeR < $sizeO ) {
                        $raFVar['action'] = 'KEEP_ORIG MINOR_FILESIZE_REDUCTION';
                    } else if( $sizeR > $sizeO ) {
                        $raFVar['action'] = 'KEEP_ORIG FILESIZE_INCREASE';
                    } else {
                        $raFVar['action'] = 'KEEP_ORIG FILESIZE_UNCHANGED';
                    }
                }//var_dump($raFVar);
            }
        }

        return( $raFiles );
    }

    function DoAction( $dir, $filebase, $raFVar, $bBackground = false )
    {
        $ret = null;

        list($action) = explode( ' ', $raFVar['action'] );    // because KEEP_ORIG and DELETE_ORIG multiplex their action information

        switch( $action ) {
            case 'CONVERT':
                $sFileO = $dir.$raFVar['o']['filename'];
                $sFileR = "${dir}${filebase}_r.{$this->targetExt}";
                $exec = "convert \"${sFileO}\" "
                       ."-quality {$this->raConfig['jpg_quality']} "
                       ."-resize {$this->raConfig['bounding_box']}x{$this->raConfig['bounding_box']}\> "
                       ."\"{$sFileR}\""
                       /* To convert in background put & at end of command line.
                        * exec() will wait to collect output unless the output is redirected somewhere
                        * echo $! outputs the process's pid
                        */
                       .($bBackground ? "> /dev/null 2>&1 & echo $!;" : "");
                if( $this->bDebug ) echo $exec."<br/>";
                $ret = exec( $exec );   // if bBackground this will be the pid of the convert process
                // note cannot chown apache->other_user because only root can do chown (and we don't run apache as root)
                break;

            case 'KEEP_ORIG':
                // We like the original foo.jpg better than the converted foo.webp
                // Delete foo.webp and move foo.jpg -> foo_r.jpg
                $delR = $dir.$raFVar['r']['filename']; //"${dir}${filebase}_r.{$this->targetExt}";
                $moveOFrom = $dir.$raFVar['o']['filename'];
                $moveOTo = $dir.pathinfo($raFVar['o']['filename'], PATHINFO_FILENAME)."_r.".pathinfo($raFVar['o']['filename'], PATHINFO_EXTENSION);
                if( file_exists($delR) ) {
                    if( $this->bDebug )  echo "Delete $delR<br/>";
                    unlink($delR);
                }
                if( $this->bDebug )  echo "Move $moveOFrom to $moveOTo<br/>";
                rename( $moveOFrom, $moveOTo );
                break;

            case 'DELETE_ORIG':
                $delO = $dir.$raFVar['o']['filename'];
                if( file_exists($delO) ) {
                    if( $this->bDebug )  echo "Delete $delO<br/>";
                    unlink($delO);
                }
                break;

            default:
                die( "Unexpected action $action" );
        }

        return( $ret );
    }
}
