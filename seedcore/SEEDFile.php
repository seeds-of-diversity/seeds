<?php

/* SEEDFile
 *
 * Copyright 2008-2018 Seeds of Diversity Canada
 *
 * File and filesystem management
 */

class SEEDFile {
    private $raTraverseItems = array();
    private $nTraverseRecurseLevel = 1;

    function __construct() { $this->Clear(); }

    function Clear()
    {
        $this->raTraverseItems = array();
        $this->nTraverseRecurseLevel = 1;
    }

    function GetTraverseItems()  { return( $this->raTraverseItems ); }

    function Traverse( $dir, $raParms = array() )
    /********************************************
        Fetch all subdirectories and files from the given directory, populate raTraverseItems, and call TraverseItem[Dir/File]().
        Directories are distinguished from files by trailing '/'

        dir:     a fully expanded real filesystem directory with or without a trailing '/'
        raParms: raMatchExtension = array( ".jpg", ".JPG", ".jpeg", ".JPEG" )
                 eFetch = "FILE" | "DIR" | "FILE_DIR"  (FILE is default)
                 bRecurse = true by default, false: only list the subdirs/files in the given directory

        Output:  raTraverseItems lists dirs (sorted) then files (sorted), for each level, recursing at each dir.
                 The key is the full path below $dir, the value is array( leading path, last path component, nTraverseLevel )

                 for  x/ x2.jpg
                         x1.txt
                         y/ y1.txt

                 raTraverseItems will contain:
                     x/y/       => ( x/, y/, 1 )
                     x/y/y1.txt => ( x/y/, y1.txt, 2 )
                     x/x1.txt   => ( x/, x1.txt, 1 )
                     x/x2.jpg   => ( x/, x2.jpg, 1 )
     */
    {
        // add trailing '/' to dir, if not exists
        if( substr( $dir, -1, 1 ) != '/' )  $dir = $dir.'/';

        $eFetch = SEEDCore_ArraySmartVal( $raParms, 'eFetch', array("FILE", "DIR", "FILE_DIR") );
        $bRecurse = SEEDCore_ArraySmartVal( $raParms, 'bRecurse', array( true, false ) );

        $raDir = array();
        $raFile = array();

        if( ($od = @opendir($dir)) ) {
            while( ($od_file = readdir($od)) !== false ) {
                if( substr($od_file, 0, 1) == "." )  continue;        // skip . and .. directories, and files starting with . (e.g. .svn)

                if( is_dir($dir.$od_file) ) {
                    if( $bRecurse || in_array( $eFetch, array( "DIR", "FILE_DIR" ) ) ) {    // collect dirs if bRecurse && FILE so subdirs can be traversed
                        $raDir[$dir.$od_file.'/'] = array( $dir, $od_file, $this->nTraverseRecurseLevel );
                    }
                } else if( in_array( $eFetch, array( "FILE", "FILE_DIR" ) ) ) {
                    $raFile[$dir.$od_file] = array( $dir, $od_file, $this->nTraverseRecurseLevel );
                }
            }
            closedir( $od );
        }

        ksort( $raDir );
        ksort( $raFile );
        foreach( $raDir as $k => $r ) {
            // k is the full path, r[0] is the containing dir, r[1] is the subdir
            list( $bList, $bItemRecurse ) = $this->TraverseItemDir( $r[0], $r[1] );    // derived class can refuse recursion on this dir
            if( $bList && in_array( $eFetch, array( "DIR", "FILE_DIR" ) ) ) {
                $this->raTraverseItems[$k] = $r;
            }
            if( $bRecurse && $bItemRecurse ) {
                ++$this->nTraverseRecurseLevel;
                $this->TraverseIn();
                $this->Traverse( $k, $raParms );
                $this->TraverseOut();
                --$this->nTraverseRecurseLevel;
            }
        }
        foreach( $raFile as $k => $r ) {
            // k is the full path, r[0] is the containing dir, r[1] is the filename
            $bSet = true;
            if( isset($raParms['raMatchExtension']) ) {
                $bSet = false;
                foreach( $raParms['raMatchExtension'] as $ext ) {
                    $lenExt = strlen($ext);
                    if( substr($r[1], -$lenExt, $lenExt ) == $ext ) {
                        $bSet = true;
                        break;
                    }
                }
            }
            if( $bSet ) {
                if( $this->TraverseItemFile( $r[0], $r[1] ) ) {
                    $this->raTraverseItems[$k] = $r;
                }
            }
        }
    }

    function TraverseItemDir( $dir, $subdir )
    /* return bool:include this item in raTraverseItems
              bool:recurse into this subdir (even if first value is false)
     */
    {
        return( array(true,true) );
    }
    function TraverseItemFile( $dir, $file )
    /* return bool:include this item in raTraverseItems
     */
    {
        return( true );
    }
    function TraverseIn() {}
    function TraverseOut() {}


    function ListDirFiles( $dir, $raParms = array() )
    /************************************************
        Use Traverse to draw a list of directories

        dir:  a fully expanded real filesystem directory with or without a trailing '/'
        raParms:  same as Traverse (the parms are passed directly there)
                  bShowRootDir: true = show the root directory as a link, and prepend it to all links, false = omit the root directory
                  dirMark: the dir relative to dirroot that should be highlighted
                      'x' = $dirroot.'x'
                      ''  = $dirroot
     */
    {
        $s = "";
        $nLevel = 0;

        $this->Traverse( $dir, $raParms );

        // remove trailing '/' if any
        if( substr( $dir, -1, 1 ) == '/' )  $dir = substr( $dir, 0, strlen($dir)-1 );

        // dirstart is the last path component (the root directory) with no '/'
        // dirprefix is the path before that with a trailing '/'
        $dirstart = substr( strrchr( $dir, '/' ), 1 );
        $dirprefix = substr( $dir, 0, strlen($dir)-strlen($dirstart) );
        $dirLinkPrefix = "";

        $s .= $this->ListDirFiles_Style()
             ."<div class='SEEDFile_LD'>";

        if( @$raParms['bShowRootDir'] ) {
            $dirLinkPrefix = $dirstart."/";  // prepend the start dir to all links
            $bDirMark = isset($raParms['dirMark']) && $raParms['dirMark']=="";
            $s .= $this->ListDirFiles_Link( $dirstart, $dirstart, $bDirMark );  // first parm is dirLinkPrefix with no trailing '/'
        }

        foreach( $this->raTraverseItems as $k => $r ) {
            while( $r[2] > $nLevel ) {
                $s .= "<div class='SEEDFile_LD_Level'>";
                ++$nLevel;
            }
            while( $r[2] < $nLevel ) {
                $s .= "</div>";
                --$nLevel;
            }
            $dirrel = substr($r[0].$r[1], strlen($dir)+1);
            $s .= $this->ListDirFiles_Link( $dirLinkPrefix.$dirrel, $r[1], ( $dirLinkPrefix.$dirrel == @$raParms['dirMark'] ) );
        }
        while( $nLevel ) {
            $s .= "</div>";
            --$nLevel;
        }
        $s .= "</div>";

        return( $s );
    }

    function ListDirFiles_Style()
    /****************************
        Override to change the style
     */
    {
        return( "<style>"
               .".SEEDFile_LD {}"
               .".SEEDFile_LD_Level { margin-left:2em; }"
               .".SEEDFile_LD_Link {}"
               .".SEEDFile_LD_Link_Mark { font-weight:bold; background-color:#ccc;padding:0px 5px; }"
               ."</style>" );
    }

    function ListDirFiles_Link( $dir, $dircomponent, $bDirMark )
    /***********************************************************
        Override to change the link format

        dir = the directory relative to dirroot
        dircomponent = the last component of dir (for display)
     */
    {
        $class = $bDirMark ? "SEEDFile_LD_Link_Mark" : "SEEDFile_LD_Link";

        return( "<a class='$class' HREF='{$_SERVER["PHP_SELF"]}?dir=".urlencode($dir)."'>$dircomponent</a><br/>" );
    }
}



function SEEDFile_GetFilenameTree( $dirRoot, $bRecurse = true, $raParms = array() )
/***********************************************************************************
    Return an array of the filenames rooted at the fully expanded real directory dirRoot.
    Prepend the dirRootPrefix string to each filename (this is used in recursion).
    raParms: raMatchExtension = array( ".jpg", ".JPG", ".jpeg", ".JPEG" )
             eFetch = "FILE" | "DIR" | "FILE_DIR"  (FILE is default)

MAYBE WANT TO RETURN THE SHORT od_file TOO, SINCE IT MIGHT BE USED IN A UI
*/
{
    $eFetch = SEEDCore_ArraySmartVal( $raParms, 'eFetch', array("FILE", "DIR", "FILE_DIR") );

    $ra = array();
    if( ($od = opendir($dirRoot)) ) {
        while( ($od_file = readdir($od)) !== false ) {
            if( substr($od_file, 0, 1) == "." )  continue;        // skip . and .. directories, and files starting with . (e.g. .svn)
            $realfile = $dirRoot.'/'.$od_file;
            if( is_dir($realfile) ) {
                if( in_array( $eFetch, array( "FILE", "FILE_DIR" ) ) ) {
                    $ra[] = $realfile;
                }
                if( $bRecurse ) {
                    $ra = array_merge( $ra, SEEDFile_GetFilenameTree( $realfile, true, $raParms ) );
                }
            } else {
                $bSkip = false;
                if( isset($raParms['raMatchExtension']) ) {
                    foreach( $raParms['raMatchExtension'] as $ext ) {
                        $lenExt = strlen($ext);
                        if( strlen($od_file) > $lenExt &&
                            substr($od_file, strlen($od_file)-$lenExt, $lenExt ) == $ext )
                        {
                            $bSkip = true;
                            break;
                        }
                    }
                }
                if( !$bSkip ) {
                    $ra[] = $realfile;
                }
            }
        }
    }
    return( $ra );
}

?>
