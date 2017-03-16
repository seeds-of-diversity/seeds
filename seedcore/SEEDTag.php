<?php

/* SEEDTag
 *
 * Copyright (c) 2014-2017 Seeds of Diversity Canada
 *
 * Parse and process tags of the form [[tag: p0 | p1...]]
 *
 * Usage:
 *     SEEDTagParser::ProcessTags( $sTemplate )  returns an expansion of all tags within the template.
 *     Override SEEDTagParser::HandleTag() to implement your own tag language.
 *         or
 *     Provide $raParms['fnHandleTag'] function to do the same thing, and it should probably call SEEDTagParser:HandleTag() on default
 *         and/or
 *     Provide $raParms['raResolvers'] = array( array('fn'=> fnResolveTags, 'raParms'), ... ) a list of tag resolvers
 *
 *     Nested tags are handled by ProcessTags() recursively.
 *
 * Note that the parser is procedural, because a regex solution is blocked by the inability to capture an arbitrary
 * number of repeating groups i.e. (| pN)
 */

include_once( "SEEDCore.php" );        // SEEDCore_EmailAddress
include_once( "SEEDDataStore.php" );

class SEEDTagParser
{
    protected $raParms = array();
    private $oDSVars = array();

    function __construct( $raParms = array(), SEEDDataStore $oDSVars = null ) {
        $this->raParms = $raParms;

        // use a global shared datastore, or make a local one
        $this->oDSVars = $oDSVars ? $oDSVars : new SEEDDataStore();
    }

    function SetVars( $raVars )
    {
        //$this->oDSVars->Clear();    this method is not for merging variables, don't use it that way just because Clear() doesn't exist
        $this->oDSVars->SetValuesRA( $raVars );
    }

    function AddVars( $raVars )
    {
        $this->oDSVars->SetValuesRA( $raVars );
    }

    function ProcessTags( $sTemplate )
    /*********************************
        Given a sTemplate like "alpha [[tag1: foo | [[inner: bar | bar [[expand-me]] ]] | blart ]] beta [[code]] gamma"

        Parse out:
            alpha
            [[tag1: foo | [[inner: bar | bar [[expand-me]] ]] | blart ]]
            beta
            [[code]]
            gamma

        Call HandleTag on each of the top-level [[..]] to expand them.  HandleTag recursively parses and expands the nested tags.
        Return the expanded template.
     */
    {
        $sOut = "";

        $iCurr = 0;

        for(;;) {
            // Find the next [[...]] tag starting at iCurr
            list($iStart,$iEnd) = $this->findNextTag( $sTemplate, $iCurr );  // $iStart is offset of [[, $iEnd is offset of ]]
            if( $iEnd == 0 ) {
                // No tag found.  Eat any remaining plain text.
                $sOut .= substr( $sTemplate, $iCurr );
                break;
            } else {
                // Found a tag. Eat any text preceding it, and expand the tag.
                $sOut .= substr( $sTemplate, $iCurr, $iStart - $iCurr );
                $sOut .= $this->expandTag( substr( $sTemplate, $iStart, $iEnd - $iStart + 2 ) );  // that's [[...]]
                $iCurr = $iEnd + 2;
            }
        }

        return( $sOut );
    }

    function HandleTag( $raTag )
    /***************************
        $raTag is the parsed version of [[tag: p0 | p1...]]
            raTag['tag'] = tag  ('' if not defined)
            raTag['raParms'] = array( p0, p1, ... )
            raTag['target'] = p0  (for convenience)

        Return the expansion of the tag. Note that the parser calls ProcessTags() for each pN to expand nested tags.

        Override with your own favourite tag language processor.

        If you have more than one (independent) special-purpose tag set, it will be hard to use inheritance to chain together
        the HandleTags because you'd have to explicitly extend each class to the other.  e.g. if you have a tag set for math and
        another for french, you have to define class math extends french and class french extends SEEDTag. That's fine for french, but
        not for math. i.e. adding one tag set into SEEDTag is fine, but not two unless they logically fit a class heirarchy

        1) So override HandleTag to handle extra tags if you have only one such handler.

        2) If you have more than one extra handler, use the ResolveTags mechanism, which calls a sequence of Resolvers to
           expand tag sets.

        3) You can also override HandleTag to implement an alternate ResolveTags mechanism

        Retagging: if a Resolver's third return value is true then the fourth is a replacement tag for the next Resolver in the list
     */
    {
        if( @$this->raParms['raResolvers'] ) {
            // call a given sequence of ResolveTags functions to try to handle the tag
            foreach( $this->raParms['raResolvers'] as $ra ) {
                // list($bHandled,$s,$bRetagged,$raReTag) = call_user_func()
                $raRet = call_user_func( $ra['fn'], $raTag, @$ra['raParms'] ? $ra['raParms'] : array() );
                $bHandled  = $raRet[0];
                $s         = $raRet[1];
                $bRetagged = @$raRet[2];

                if( $bHandled )  return( $s );

                // Retagging: if a Resolver's third return value is true then the fourth is a replacement tag for the next Resolver in the list
                if( $bRetagged )  $raTag = $raRet[3];
            }
        }

        $target = @$raTag['target'];
        $p0 = $target;   // same as raParms[0]
        $p1 = @$raTag['raParms'][1];
        $p2 = @$raTag['raParms'][2];
        $p3 = @$raTag['raParms'][3];

        /* if      p0     then echo p1 else echo p2      -- if:    test | good | bad
         * ifdef   p0     then echo p0 else echo p1      -- ifdef: var | default
         * ifeq    p0==p1 then echo p2 else echo p3      -- ifeq:  v1 | v2 | they match | they're different
         * ifnotMT p0     then find the token p2
         *                     (default "[]") in p1 and
         *                     subst it with p0
         *                -- ifnotMT: v1 | the value is []
         *                -- ifnotMT: v1 | the value is {here} | {here}
         */
        switch( strtolower($raTag['tag']) ) {
            case 'concat':    $s = ""; foreach( $raTag['raParms'] as $s1 ) $s .= $s1; return( $s );
            case 'if':        return( $target ? $p1 : $p2 );
            case 'ifdef':     return( $target ? $target : $p1 );
            case 'ifeq':      return( $p0 == $p1 ? $p2 : $p3 );
            case 'ifnotMT':   return( $target ? str_replace( ($p2 ? $p2 : '[]'), $this->oDSVars->Value($target), $p1 ) : "" );
            case 'nbsp':      return( ($n = intval($target)) ? SEEDStd_StrNBSP('',$n) : "" );
            case 'trim':      return( trim($target) );
            case 'lower':     return( strtolower($target) );
            case 'upper':     return( strtoupper($target) );
            case 'var':       return( $this->oDSVars->Value($target) );
            case 'setvar':    if( $target ) { $this->oDSVars->SetValue($target, $p1); }  return( "" );

            case 'urlencode': return( urlencode($target) );

            case 'currentmonth':     return( date('m') );
            case 'currentmonthname': return( date('F') );
            case 'currentday':       return( date('d') );
            case 'currentdayname':   return( date('l') );
            case 'currentyear':      return( date('Y') );
            case 'currenttime':      return( date('H:i') );
            case 'nextyear':         return( date('Y') + 1 );


            case 'sitename':         return( $_SERVER['HTTP_HOST'] );
            case 'siteroot':         return( SITEROOT );
            case 'siteroot_url':     return( SITEROOT_URL );

            default:          return( "" );
        }
    }

    private function findNextTag( $sTemplate, $iCurr )
    /*************************************************
        Scan $sTemplate starting at $iCurr and find the next [[...]]
        Return the offsets of [[ and ]]
     */
    {
        $iStart = 0;
        $iEnd = 0;

        $iDeep = 0;
        $bDone = false;
        while( !$bDone ) {
            list($sToken,$iOffset) = $this->findNextToken( $sTemplate, $iCurr, false );  // don't return : token
            switch( $sToken ) {
                case '[[':
                    if( ++$iDeep == 1 ) {
                        // found the start of the top level tag
                        $iStart = $iOffset;
                    }
                    break;
                case ']]':
                    // weird things can happen if you have a bad open tag like [foo]] later on [[this doesn't work]]
                    if( $iDeep ) --$iDeep;
                    // ==0 means we found the end of the top level tag
                    // > 0 means we found the end of a nested tag
                    // < 0 means the top level tag was truncated, which we interpret as an error
                    //     because otherwise the iEnd offset is undefined and who knows what the calling code could screw up
                    if( $iDeep == 0 ) {
                        $iEnd = $iOffset;
                        $bDone = true;
                    }
                    if( $iDeep < 0 ) {
                        $iEnd = 0;
                        $bDone = true;
                    }
                    break;
                case '':
                    $bDone = true;
                    break;
                default:
                    break;
            }
            $iCurr = $iOffset + strlen( $sToken );
        }

        return( array( $iStart, $iEnd ) );
    }

    private function expandTag( $sTag )
    /**********************************
        Given "[[tag: p0 | p1...]] where pN can contain nested [[...]], return its expansion.

        Do this by parsing the tag (which can contain nested tags) and calling HandleTag.
     */
    {
        $sOut = "";

        // verify that the tag is bounded by [[...]] and strip those
        $sTag = trim($sTag);
        if( substr($sTag,0,2) != '[[' || substr($sTag,-2,2) != ']]' )  return( "" );
        $sTag = substr( $sTag, 2, strlen($sTag)-4 );

        // Parse the tag's contents to get raTag
        $raTag = $this->parseTagContents( $sTag );

        // Expand the parsed tag contents according to your own favourite tag language
        if( isset($this->raParms['fnHandleTag']) ) {
            // your function might want to call $this->HandleTag() if it finds a tag that it doesn't know
            $sOut = call_user_func( $this->raParms['fnHandleTag'], $raTag );
        } else {
            $sOut = $this->HandleTag( $raTag );
        }

        return( $sOut );
    }

    private function parseTagContents( $sTag )
    /*****************************************
        Given "tag: p0 | p1..." parse into an raTag array ready for HandleTag()
     */
    {
        $raTag = array( 'tag' => "", 'target' => "", 'raParms' => array() );

        $iCurr = 0;
        $iLastPartition = 0;
        $iDeep = 0;
        $iP = 0;
        $iFirstToken = true;    // the colon marks 'tag:' if it is the first token found
        $bDone = false;
        while( !$bDone ) {
            list($sToken,$iOffset) = $this->findNextToken( $sTag, $iCurr, $iFirstToken );
            switch( $sToken ) {
                case '[[':
                    ++$iDeep;
                    break;
                case ']]':
                    // weird things can happen if you have a bad open tag like [foo]] later on [[this doesn't work]]
                    if( $iDeep ) --$iDeep;
                    break;
                case ':':
                    if( $iFirstToken ) {
                        $raTag['tag'] = trim( substr( $sTag, 0, $iOffset ) );
                        $iLastPartition = $iOffset + 1;
                    }
                    break;
                case '|':
                    if( $iDeep == 0 ) {
                        $p = $this->ProcessTags( substr( $sTag, $iLastPartition, $iOffset-$iLastPartition) );
                        $raTag['raParms'][] = $this->pNormalize($p);
                        $iLastPartition = $iOffset + 1;
                    }
                    break;
                default:
                    // End of tag. Eat any remaining text.
                    // Note that an erroneous open tag will be written as plain text.
                    $p = $this->ProcessTags( substr( $sTag, $iLastPartition ) );
                    $raTag['raParms'][] = $this->pNormalize($p);
                    $bDone = true;
                    break;
            }
            $iCurr = $iOffset + strlen($sToken);
            $iFirstToken = false;
        }
        if( count($raTag['raParms']) ) {
            $raTag['target'] = $raTag['raParms'][0];
        }

        return( $raTag );
    }

    private function pNormalize( $p )
    /********************************
       $p is an expanded parm found in [[... | p | ... ]]
       Do some tidying, like trim().
       If it has no spaces and starts with $, resolve its variable value
     */
    {
        $p = trim( $p );

        if( substr( $p, 0, 1 ) == '$' && strpos( $p, ' ' ) === false ) {
            $p = $this->oDSVars->Value( substr($p,1) );
        }
        return( $p );
    }

    private function findNextToken( $sTemplate, $iCurr, $bIncludeColon = false )
    /***************************************************************************
        Return the next of [[, ]], |, or optionally :, and the offset where it is found.
     */
    {
        $sNextToken = "";
        $iOffset = 0;

        $iStart = strpos( $sTemplate,   "[[", $iCurr );  // look for a nested start tag
        $iEnd = strpos( $sTemplate,     "]]", $iCurr );  // look for an end tag
        $iDivider = strpos( $sTemplate, "|",  $iCurr );  // look for a parm delimiter
        if( $bIncludeColon ) {
            $iColon = strpos( $sTemplate, ":", $iCurr ); // look for a tag delimiter
        }

        // The "next" token is the one with the lowest offset, that exists.
        if( $iStart   !== false )                                                 { $sNextToken = "[[";  $iOffset = $iStart; }
        if( $iEnd     !== false && (empty($sNextToken) || $iEnd < $iOffset) )     { $sNextToken = "]]";  $iOffset = $iEnd; }
        if( $iDivider !== false && (empty($sNextToken) || $iDivider < $iOffset) ) { $sNextToken = "|";   $iOffset = $iDivider; }
        if( $bIncludeColon ) {
            if( $iColon !== false && (empty($sNextToken) || $iColon < $iOffset) ) { $sNextToken = ":";   $iOffset = $iColon; }
        }

        return( array( $sNextToken, $iOffset ) );
    }
}


class SEEDTagBasicResolver
{
    private $cssPrefix = "SEEDTag";
    private $LinkBase = "";
    private $ImgBase = "";
    private $bForceTargetBlank = false;

    function __construct( $raParms = array() )
    {
        if( ($p = @$raParms['cssPrefix']) )  $this->cssPrefix = $p;
        if( ($p = @$raParms['LinkBase']) )   $this->LinkBase = $p;
        if( ($p = @$raParms['ImgBase']) )    $this->ImgBase = $p;
        if( @$raParms['bForceTargetBlank'] ) $this->bForceTargetBlank = true;
    }

    function ResolveTag( $raTag )
    /****************************
        Given a SEEDTagParser-parsed $raTag, expand it for certain basic tags
        The really fundamental tags are handled in the SEEDTagParser base class, which should be called too,
        but a SEEDTagParser derivation can call this first to try to resolve some basic but not fundamental tags.

        The form for tag handlers is: if the tag is handled, return array(true,s); else return array(false,'')

        What if a parm in the $raTag is a variable or something?  It won't be.  There are only a few ways that variables can be accessed
        (e.g. by [[Var:foo]] or $foo) and those are all handled before this happens.  Why?  Because $foo is normalized by the parser,
        and all nested [[tags:]] are expanded recursively bottom-up.

        The bottom line is that all parms of $raTag are already fully expanded so they can be assumed to be verbatim text.
     */
    {
        $s = "";
        $bHandled = true;

        switch( strtolower($raTag['tag']) ) {
            case 'image':   $s = $this->doImage( $raTag );   break;

            case 'ftp':
            case 'http':
            case 'https':   $s = $this->doHttp( $raTag );    break;

            case 'mailto':  $s = $this->doMailto( $raTag );  break;

            case 'link':    $s = $this->doLink( $raTag );    break;

            default:
                $bHandled = false;
                break;
        }

        return( array($bHandled,$s) );
    }

    private function doImage( $raTag )
    /*********************************
        Found a link like [[Image:target(|parms)]]   where parms can contain parm1|parm2...

        [[Image: src | left/right/frame/frame left/frame right {imgAttrs} | caption]]

        src = url of the image.  Override imageGetURL to resolve local names to a global url
        left/right/frame = frame puts a DIV around the image, optionally aligned left or right.  left/right alone aligns the image.
        img attrs = content of {} is inserted into img tag. Useful for specifying width, height
        caption = placed in alt text, formatted into a caption if frame specified
     */
    {
        // if the target is a full url like http://foo.com or //foo.com, use it verbatim
        // otherwise prepend the ImgBase (probably something like "http://seeds.ca/d?n=")
        $src = $raTag['target'];
        if( substr($src,0,2) != '//' && strpos($src,"://")===false ) {
            $src = $this->ImgBase.$src;
        }


        // parse p1 to get left/right/imgAttrs etc
        //    does this work? sscanf( $raTag['parms'][1], "%s {%s}", $align, $attrs );
        $raMatches = array();
        preg_match( "/([^\{]*)\{?([^\}]*)\}?/", @$raTag['raParms'][1], $raMatches );
        $align = (strpos( @$raMatches[1], "left" ) !== false ? "left" :
                 (strpos( @$raMatches[1], "right" ) !== false ? "right" : ""));
        $bFrame = (strpos( @$raMatches[1], "frame" ) !== false);
        $imgAttrs = @$raMatches[2];

        $caption = @$raTag['raParms'][2];

        if( !$bFrame && !empty($align) ) $imgAttrs .= " align='$align'";
        if( !empty($caption) )           $imgAttrs .= " alt='$caption'";
        $s = "<img src='$src' class='{$this->cssPrefix}_img' $imgAttrs>";

        if( $bFrame ) {
            // Put a DIV around the IMG
            $style = empty($align) ? "" : "style='float:$align'";
            $s = "<div class='{$this->cssPrefix}_imgFrame' $style>"
                .$s
                .($caption ? "<div class='{$this->cssPrefix}_imgCaption'>$caption</div>" : "")
                ."</div>";
        }

        return( $s );
    }

    private function doHttp( $raTag )
    {
        return( $this->doLink( $raTag ) );
    }

    private function doMailto( $raTag )
    {
        return( $this->doLink( $raTag ) );
    }

    private function doLink( $raTag )
    /********************************
        [[Link:   link | window | attrs | (extensible - put more here) | caption]]
        [[http:   link | window | attrs | ...                          | caption]]
        [[https:  link | window | attrs | ...                          | caption]]
        [[ftp:    link | window | attrs | ...                          | caption]]
        [[mailto: link | window | attrs | ...                          | caption]]

        Output: <a href='{LinkBase}{link}' {attrs} target='{window}'>{caption}</a>
                LinkBase is only used for link: tags, since they are the only links that can be non-absolute

        The caption is always the last parm.
        More fields can be added before the caption.
        Any fields preceding the caption can be omitted.
        The caption can be blank but its location must be indicated with a final '|'

        [[Link: link | caption]]
        [[Link: link | foo | caption]]      -- open link in new window foo
        [[Link: link | new | caption]]      -- open link in new window _blank
        [[Link: link | | attrs | caption]]  -- specify attrs, open link in same window
        [[Link: link | ]]                   -- caption=target
        [[Link: link | | attrs | ]]         -- caption=target
        [[Link: link]]                      -- special case, caption=target
     */
    {
        $link = $caption = $window = $attrs = "";

        if( !($link = $raTag['raParms'][0]) ) {
            // A blank link is okay as long as a LinkBase is defined (at least it has to be '/')
            // Note this means an undefined caption will also be blank, but that's a problem for fools
            if( !$this->LinkBase )  return( "" );
        }

        // caption is always the last parm. if count==1 it naturally means caption==link, but set this after the link is processed
        $iCap = count($raTag['raParms']) - 1;
        $caption = $raTag['raParms'][$iCap];

        // window is always parm 1, unless parm 1 is the caption
        if( $this->bForceTargetBlank ) {
            $window = "_blank";
        } else if( $iCap > 1 ) {
            $window = $raTag['raParms'][1];
            if( $window == "new" )  $window = "_blank";
        }

        // attrs is always parm 2, unless parm 2 is the caption
        if( $iCap > 2 ) {
            $attrs = $raTag['raParms'][2];
        }

        if( $raTag['tag'] == 'mailto' ) {
            list($s1,$s2) = explode( '@', $raTag['target'], 2 );  // split the email address for Javascript spamproofer
            $s = SEEDCore_EmailAddress( $s1, $s2, $caption, array(), "class={$this->cssPrefix}_mailto" );
        } else {
//            if( empty($raTag['tag']) && substr($raTag['target'], 0, 4) === 'www.' && strpos(trim($raTag['target']),' ') === false ) {
//                /* The author made a [[www.domain.com]] link and forgot to use http:
//                 */
//                $link = "http://".$raTag['target'];
//                if( empty($caption) )  $caption = $raTag['target'];
//            } else {
//                $link = ($raTag['tag'] ? ($raTag['tag'].":") : "").$raTag['target'];
//            }
            if( strtolower($raTag['tag']) == 'link' ) {
                // prepend the LinkBase only if this is a local link
                $link = $this->LinkBase.$link;
            } else {
                // ftp, http, https : reassemble the link with its prefix
                $link = $raTag['tag'].":".(substr($link,0,2)!='//'?"//":"").$link;
            }
            if( !empty($window) )  $window = " target='$window'";
            if( empty($caption) )  $caption = $link;
            $s = "<a class='{$this->cssPrefix}_link' href='$link'$window $attrs>$caption</a>";
        }

        return( $s );
    }
}

?>
