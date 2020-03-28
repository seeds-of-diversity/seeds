<?php

/* SEEDImgMan.php
 *
 * Copyright (c) 2010-2018 Seeds of Diversity Canada
 *
 * The basics of an Image Management interface.
 */

include_once( "SEEDFile.php" );

class SEEDImgMan
/***************
    The basics of an image management system.

    Each image is designated three ways:
        filename: the fully-expanded filesystem name
        img:      the filename relative to an arbitrary root. This is stored in e.g. a database
        k:        a number e.g. database key

    This class assumes that images are stored on a filesystem, and indexed in an arbitrary storage e.g. a database.
    This class does filesystem access, but must be extended to provide mappings among these designations, as well as access to the index storage e.g. database
 */
{
    public $raMissingFiles = array();   // array of fully-expanded filenames (use Filename2Img to get the index name)
    public $raExtraFiles = array();     // array of fully-expanded filenames
    public $nMissingTested = 0;
    public $nMissingFound = 0;
    public $nExtraTested = 0;
    public $nExtraFound = 0;


    function __construct() {}

    /* Override these to provide access to image db/filesystem
     */
    function K2Img( $k )                { die( "Override K2Img" ); }
    function Img2K( $img )              { die( "Override Img2K" ); }   // not used in this base class but definition provided
    function Img2Filename( $img )       { die( "Override Img2Filename" ); }
    function Filename2Img( $filename )  { die( "Override Filename2Img" ); }
    function Img2Url( $img )            { die( "Override Img2Url" ); }
    function RenameImage( $k, $newImg ) { die( "Override Rename" ); }
    function DeleteImage( $k )          { die( "Override Delete" ); }
    function AddImage( $newImg )        { die( "Override Add" ); }
    function GetAllImg( $dir )          { die( "Override GetAllImg" ); }  // get all img that are "LIKE '$dir/%'"

    function ImgInfoByFilename( $filename )
    {
        $ra = array( 'w'=>0, 'h'=>0, 'mime'=>'', 'filesize'=>0, 'filesize_human'=>0 );

        if( !in_array( strtolower(pathinfo($filename,PATHINFO_EXTENSION)), ['gif','png','jpg','jpeg'] ) ) {
            goto done;
        }

        if( file_exists($filename) ) {
            //var_dump($filename);
            if( !($sz = getimagesize($filename)) ) {
                echo "<p>Could not read $filename</p>";
                goto done;
            }
            $ra['w'] = $sz[0];
            $ra['h'] = $sz[1];
            $ra['mime'] = $sz['mime'];
            $ra['filesize'] = filesize($filename);
            $ra['filesize_human'] = SEEDCore_HumanFilesize( $ra['filesize'] );
        }
        done:
        return( $ra );
    }

    /*
     $fileExt2Mimetype = array(
    "bmp"   =>  "image/bmp",
    "ico"   =>  "image/x-icon",
    "gif"   =>  "image/gif",
    "jpg"   =>  "image/jpeg",
    "jpeg"  =>  "image/jpeg",
    "tif"   =>  "image/tiff",
    "tiff"  =>  "image/tiff",
    "eps"   =>  "application/postscript",
    "pdf"   =>  "application/pdf",
    "rtf"   =>  "application/rtf",
    "zip"   =>  "application/zip",
    "js"    =>  "application/x-javascript",

    "doc"   =>  "application/msword",
    "dot"   =>  "application/msword",
    "xls"   =>  "application/vnd.ms-excel",
    "ppt"   =>  "application/vnd.ms-powerpoint",
    "mdb"   =>  "application/x-msaccess",
    "pub"   =>  "application/x-mspublisher",

    "css"   =>  "text/css",
    "htm"   =>  "text/html",
    "html"  =>  "text/html",
    "txt"   =>  "text/plain",

    "mp3"   =>  "audio/mpeg",
    "wav"   =>  "audio/x-wav",
    "mpg"   =>  "video/mpeg",
    "mpeg"  =>  "video/mpeg",
    "mov"   =>  "video/quicktime",
    "qt"    =>  "video/quicktime",
    "avi"   =>  "video/x-msvideo",
);
$mimetype = @$fileExt2Mimetype[strtolower(substr( strrchr( $filename, '.' ), 1 ))];
     */

    function ShowImg( $filename )
    {
        if( file_exists($filename) ) {
            $info = $this->ImgInfoByFilename($filename);
            if( ($f = fopen( $filename, 'rb' )) ) {
                header( "Content-Type: ".$info['mime'] );
                header( "Content-Length: ".$info['filesize'] );
                fpassthru( $f );
            }
        } else {
            die( "No file '$filename'" );
        }
        exit;
    }


    function Update()
    /****************
     */
    {
        $s = "";

        $action = SEEDInput_Str( "sim_action" );
        switch( $action ) {
            case "Match":        // identical to Rename
            case "Rename":
                $k = SEEDInput_Int( 'sim_k' );
                $img1 = SEEDInput_Str( 'sim_img1' );
                if( $k && $img1 ) {
                    $img = $this->K2Img( $k );
                    if( $this->RenameImage( $k, $img1 ) ) {
                        $s .= "Renamed $img to $img1<br/>";
                        // update cache
                        $s .= $this->MEFCacheRemove( 'missing', $this->Img2Filename( $img ) );    // remove the old filename
                        if( $action == 'Match' ) {
                            $s .= $this->MEFCacheRemove( 'extra', $this->Img2Filename( $img1 ) );    // remove the new filename
                        }
                    } else {
                        $s .= "Could not rename $img to $img1<br/>";
                    }
                }
                break;

            case "Delete":
                if( ($k = SEEDInput_Int( 'sim_k' )) ) {
                    $img = $this->K2Img( $k );
                    if( $this->DeleteImage( $k ) ) {
                        $s .= "Deleted $img record<br/>";
                        // update cache
                        $s .= $this->MEFCacheRemove( 'missing', $this->Img2Filename( $img ) );
                    } else {
                        $s .= "Could not delete $img record<br/>";
                    }
                }
                break;

            case "Add":
                if( ($img1 = SEEDInput_Str( 'sim_img1' )) ) {
                    if( $this->AddImage( $img1 ) ) {
                        $s .= "Added $img1 record<br/>";
                        // update cache
                        $s .= $this->MEFCacheRemove( 'extra', $this->Img2Filename( $img1 ) );
                    } else {
                        $s .= "Could not add $img1 record<br/>";
                    }
                }
                break;

            case "Add All":
                /* Add all files in directory "parm1" that are not listed in the database
                 */
                if( ($dir = SEEDInput_Str( 'sim_dir' )) ) {
                    if( $dir == '.' )  $dir = "";
                    $s .= "Adding all new files in '$dir'<br/>";
                    $n1 = $n2 = 0;
                    $this->getMissingAndExtraFiles( $dir );

                    $n = 0;
                    foreach( $this->raExtraFiles as $f ) {
                        $img = $this->Filename2Img( $f );
                        $s .= "Adding $img record<br/>";
                        $this->AddImage( $img );
                        $s .= $this->MEFCacheRemove( 'extra', $this->Img2Filename( $img ) );
                        ++$n;
                    }
                    $s .= "Added $n files<br/>";
                }
                break;

            case "Scale Down":
                // Generalize/Parameterize this
                if( ($imgFilename = SEEDInput_Str( 'sim_filename' )) ) {
                    $cmd = "convert \"$imgFilename\" -resize 800x800\> -quality 85 \"$imgFilename\"";
                    $iRet = 0; system( $cmd, $iRet );
                    $s .= "Scaling $imgFilename<br/>$cmd<br/>Returned $iRet<br/>";
                }
                break;

            default:
                if( !empty($action) )  $s .= "Unknown action '$action'<br/>";
                break;
        }
        return( $s );
    }

    function getMissingAndExtraFiles( $dir, $bReload = true )
    /********************************************************
        Find all the unmatched images under the given dir.
        Missing = named in the db but not found in filesystem
        Extra   = found in the filesystem but not in the db

        bReload == false : don't search the db or filesystem, just use cached names
     */
    {
        if( $bReload ) {
            $this->nMissingTested = $this->nExtraTested = 0;

            $ra1 = $this->GetAllImg( $dir );
            $raImg = array();
            foreach( $ra1 as $img ) {
                $raImg[] = $this->Img2Filename( $img );
            }

            $oSF = $this->factory_SEEDFile();
            $oSF->Traverse( $this->Img2Filename($dir), array('bRecurse'=>true, 'eFetch'=>'FILE') );
            $raFiles = array();
            foreach( $oSF->GetTraverseItems() as $k => $v ) {
                $raFiles[] = $k;
            }
            $this->raMissingFiles = array_diff( $raImg, $raFiles );
            $this->raExtraFiles = array_diff( $raFiles, $raImg );

            $this->nMissingTested = count( $raImg );
            $this->nMissingFound = count( $this->raMissingFiles );
            $this->nExtraTested = count( $raFiles );
            $this->nExtraFound = count( $this->raExtraFiles );

            $this->PutMEFCache();
        } else {
            $this->GetMEFCache();
        }
    }

    function factory_SEEDFile()
    /**************************
        Override to use a custom SEEDFile, e.g. one that uses a custom Traversal filter
     */
    {
        return( new SEEDFile() );
    }


    function GetMEFCache()
    /*********************
        Get raMissingFiles and raExtraFiles from a cache. This implementation uses _SESSION, but can be overridden.
     */
    {
        $this->raMissingFiles = $this->raExtraFiles = array();
        for( $i = 1; isset($_SESSION['sim_mef']['missing'.$i]); ++$i ) {
            if( ($f = $_SESSION['sim_mef']['missing'.$i]) ) {
                $this->raMissingFiles[] = $f;
            }
        }
        for( $i = 1; isset($_SESSION['sim_mef']['extra'.$i]); ++$i ) {
            if( ($f = $_SESSION['sim_mef']['extra'.$i]) ) {
                $this->raExtraFiles[] = $f;
            }
        }
    }

    function PutMEFCache()
    /*********************
        Store raMissingFiles and raExtraFiles in a cache. This implementation uses _SESSION, but can be overridden.
     */
    {
        $this->ClearMEFCache();
        $i = 1;
        foreach( $this->raMissingFiles as $f ) {
            $_SESSION['sim_mef']['missing'.$i] = $f;
            ++$i;
        }
        $i = 1;
        foreach( $this->raExtraFiles as $f ) {
            $_SESSION['sim_mef']['extra'.$i] = $f;
            ++$i;
        }
    }

    function ClearMEFCache()
    /***********************
     */
    {
        $_SESSION['sim_mef'] = NULL;
    }

    function MEFCacheRemove( $sPrefix, $sFilename )
    {
        $s = "";

        if( isset($_SESSION['sim_mef']) ) {
            foreach( $_SESSION['sim_mef'] as $k => $f ) {
                if( substr($k,0,strlen($sPrefix)) == $sPrefix && $f == $sFilename ) {
                    $_SESSION['sim_mef'][$k] = "";
                    $s .= "Deleted $sPrefix $f from cache<BR/>";
                    break;
                }
            }
        }
        return( $s );
    }
}


function SEEDImgMan_ParseFilename( $sName )
{
    $ra = array();
    $raOut = NULL;

    $sRegex = "|^([^0-9]*)([0-9]+)([a-z])?(_([0-9]+))?(.*)(\.[^\.]+)|";

    if( preg_match( $sRegex, $sName, $ra ) ) {
        $raOut = array();
        $raOut['full'] = $ra[0];
        $raOut['prefix'] = $ra[1];
        $raOut['set'] = $ra[2];
        $raOut['set_member'] = $ra[3];
        $raOut['copy'] = $ra[5];
        $raOut['comment'] = $ra[6];
        $raOut['ext'] = $ra[7];
    }

    return( $raOut );
}

?>
