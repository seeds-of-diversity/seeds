<?php

/* QServerDocRep
 *
 * Copyright 2021-2022 Seeds of Diversity Canada
 *
 * Serve queries for Document Repositories
 */

include_once( "DocRep.php" );

include_once( SEEDROOT.'/vendor/autoload.php');

use Jfcherng\Diff\Differ;
use Jfcherng\Diff\DiffHelper;
use Jfcherng\Diff\Factory\RendererFactory;
use Jfcherng\Diff\Renderer\RendererConstant;

class QServerDocRep extends SEEDQ
{
    private $oDocRepDB;

    function __construct( SEEDAppSessionAccount $oApp, $raConfig = [] )
    {
        parent::__construct( $oApp, $raConfig );
        $this->oDocRepDB = DocRepUtil::New_DocRepDB_WithMyPerms( $oApp );
    }

    function Cmd( $cmd, $parms )
    {
        $rQ = $this->GetEmptyRQ();

        if( !SEEDCore_StartsWith( $cmd, 'dr-' ) ) goto done;
        $rQ['bHandled'] = true;

        // check permissions

        // kDoc must be given : -1 indicates root of forest (normally kDoc==0)
        $kDoc = intval(@$parms['kDoc']);

        switch( $cmd ) {
            case 'dr-preview':
                list($rQ['bOk'],$rQ['sOut']) = $this->doPreview($kDoc, $parms);
                break;

            case 'dr-getTree':
                list($rQ['bOk'],$rQ['raOut']) = $this->doGetTree($kDoc, $parms);
                break;

            case 'dr--add':
                list($rQ['bOk'],$rQ['sOut'],$rQ['raMeta']['kDocNew']) = $this->doAdd($kDoc, $parms);
                break;

            case 'dr--update':
                list($rQ['bOk'],$rQ['sOut']) = $this->doUpdate($kDoc, $parms);
                break;

            case 'dr--rename':
                list($rQ['bOk'],$rQ['sOut']) = $this->doRename($kDoc, $parms);
                break;

            case 'dr-versions':
                list($rQ['bOk'],$rQ['sOut']) = $this->doVersions($kDoc, $parms);
                break;

            case 'dr-versionsDiff':
                list($rQ['bOk'],$rQ['sOut']) = $this->doVersionsDiff($parms['kDoc1'], $parms['kDoc2'], $parms['ver1'], $parms['ver2']);
                break;

            case 'dr--versionsDelete':
                list($rQ['bOk'],$rQ['sOut']) = $this->doVersionsDelete($kDoc, $parms);
                break;

            case 'dr--versionsRestore':
                list($rQ['bOk'],$rQ['sOut']) = $this->doVersionsRestore($kDoc, $parms);
                break;

            case 'dr--schedule':
                list($rQ['bOk'],$rQ['sOut']) = $this->doSchedule($kDoc, $parms);
                break;

            case 'dr--docMetadataStoreAll':
                list($rQ['bOk'],$rQ['sOut']) = $this->doDocMetadataStoreAll($kDoc, $parms);
                break;

            case 'dr-XMLExport':
                list($rQ['bOk'],$rQ['sOut']) = $this->doXMLExport($kDoc);
                break;

            case 'dr--XMLImport':
                list($rQ['bOk'],$rQ['sOut']) = $this->doXMLImport($kDoc, $parms);
                break;
        }

        done:
        return( $rQ );
    }

    private function doPreview( $kDoc, $parms )
    {
        $s = "Preview";
        $bOk = false;

        if( $kDoc && ($oDoc = $this->oDocRepDB->GetDocRepDoc( $kDoc )) ) {
            switch( $oDoc->GetType() ) {
                case 'FOLDER':
                    $s = "FOLDER";
                    break;
                //case 'LINK':          not a type; it's a storage method
                //    $s = "Link to another doc";
                //    break;
                case 'TEXT':
                case 'TEXTFRAGMENT':
                    $s = $oDoc->GetText('');
                    if( @$parms['bExpand'] ) {
                        include_once( SEEDLIB."SEEDTemplate/masterTemplate.php" );
                        include_once( SEEDCORE."SEEDSessionAccountTag.php" );
                        // start with this doc's variables and a basic config
                        $raVars = $oDoc->GetDocMetadataRA_Inherited();
                        $raConfigMT = ['DocRepParms'=>['oDocRepDB'=>$this->oDocRepDB,
// not sure whether these are needed by the direct expansion below
                                                       'oDocReference'=>$oDoc, 'raVarsFromIncluder'=>$raVars]];
                        // this permissive handler should only be provided when an admin is looking at the preview
// should be a flag to tell SoDMasterTemplate to set different levels of SessionAccountTag abilities, instead of putting this code everywhere
                        if( $this->oApp->sess->GetUID() == 1499 ) {
                            $raConfigMT['oSessionAccountTag'] = new SEEDSessionAccountTagHandler($this->oApp, ['bAllowKMbr'=>true, 'bAllowPwd'=>true, 'db'=>'seeds1']);
                        }

                        if( ($drTemplate = @$raVars['docrep-template']) ) {
                            // expand the doc within a template
                            if( ($oDocTemplate = $this->oDocRepDB->GetDoc($drTemplate)) ) {
                                /* Template text is expanded with the template's vars overridden by the main doc's vars
                                 * and oDocReference set to the main doc so metadata (name, title, parent, etc) come from there.
                                 */
                                $sTemplate = $oDocTemplate->GetText('');
                                $raVars = array_merge( $oDocTemplate->GetDocMetadataRA_Inherited(), $raVars );
                                $raConfigMT['oDocRepDB']['raVarsFromIncluder'] = $raVars;
                                $raConfigMT['oDocRepDB']['bKlugeInTemplate'] = true;
                                $oTmpl = (new SoDMasterTemplate($this->oApp, $raConfigMT))->GetTmpl();
                                $s = $oTmpl->ExpandStr($sTemplate, $raVars);
                            } else {
                                $s = "Cannot find template '$drTemplate'";
                            }
                        } else {
                            // expand the doc directly
                            $oTmpl = (new SoDMasterTemplate($this->oApp, $raConfigMT))->GetTmpl();
                            $s = $oTmpl->ExpandStr($s, $raVars);
                        }
                    }
                    break;
                case 'BIN':
                case 'IMAGE':
                    if( SEEDCore_StartsWith( $oDoc->GetValue( 'mimetype', ''), 'image/' ) ) {
                        $s = "This should be an <img/>";
                    } else {
                        $s = "This should be a <a>link to download the file</a>";
                    }
                    break;
            }

            $bOk = true;
        }

        return( [$bOk,$s] );
    }

    /**
     * @param int $kDoc
     * @param array $parms
     * @return array of docinfo for descendants of kDoc (not including kDoc)
     */
    private function doGetTree( int $kDoc, array $parms )
    /****************************************************
        includeRootDoc : true = return data for kDoc; false = exclude kDoc (default)
        depth : -1 = whole subtree rooted at kDoc
                 0 = just kDoc (only makes sense with includeRootDoc)
                >0 = levels of subtree to descend
     */
    {
        $ok = false;
        $raOut = [];

        /* kDoc required in parms, so use kDoc==-1 to refer to root of forest
         */
        if( !$kDoc ) goto done;     // here 0 means undefined
        if( $kDoc < 0 ) $kDoc = 0;  // from here 0 means root forest

        $flag = @$parms['flag'] ?? 'PUB';   // default published version
        $depth = @$parms['depth'] ?: -1;    // default full depth

        /* If depth != -1, fetch subtree to depth+1 so leaf nodes have children array set
         */
        $subtreeDepth = $depth >= 0 ? $depth+1 : -1;

        /* 3 cases:
         *     $kDoc >= 0 & !includeRootDoc  = get the forest of subtrees whose parent is kDoc, but not that parent (kDoc can be 0)
         *     $kDoc > 0 & includeRootDoc    = get get the given doc + optional subtree as depth
         *     $kDoc = 0 & includeRootDoc    = create a fake zero root doc + optional subtree as depth
         *
         */
        if( @$parms['includeRootDoc'] ) {
            if( $kDoc == 0 ) {
                // get the top-level forest at the requested depth
                $raForest = $this->oDocRepDB->GetSubTreeDescendants($kDoc, $subtreeDepth, []);
                // create a zero root node with the top level forest as its children
                $raOut[0] = $this->getTree_output_doc(null, $raForest);
                // if fetching descendants, add those too
                if( $depth > 0 ) $raOut = array_merge($raOut, $this->getTree_output($raForest, $depth==-1 ? -1 : $depth-1) );

            } else {
                // fetch the given doc and optionally its subtree
                $raTree = $this->oDocRepDB->GetSubtree($kDoc, $subtreeDepth, []);
                $raOut = $this->getTree_output($raTree, $depth);
            }

        } else {
            // get the forest whose parent is kDoc
            if( ($raForest = $this->oDocRepDB->GetSubTreeDescendants($kDoc, $subtreeDepth, [])) ) {
                $raOut = $this->getTree_output($raForest, $depth);
            }
        }

        $ok = true;

        done:
        return([$ok,$raOut]);
    }

    private function getTree_output( $raTreeLevel, int $depth, bool $bZeroNode = false )
    /***************************************************************************
        Write the nodes of the given level of the tree, optionally descending.
        bZeroNode is a special mode: create a root-forest zero node, using raTreeLevel as its children
     */
    {
        $raDocs = [];

        if( $bZeroNode ) {
            // kDoc is zero only at the root recursion when the fake "zero node" holds the root forest. The record has no metadata, just children.
            $raDocs[0] = $this->getTree_output_doc(null, $raTreeLevel);
            if( $depth >0 ) $raDocs = array_merge($raDocs, $this->getTree_output($raTreeLevel, $depth==-1 ? -1 : $depth-1) );
            goto done;
        }

        foreach( $raTreeLevel as $d ) {
            $oDoc = $d['oDoc'];

            $raDocs[$oDoc->GetKey()] = $this->getTree_output_doc($d['oDoc'], $d['children']);

            /* Add children to output array if depth unlimited (-1) or if haven't reached defined depth yet.
             * Note the subtree fetch gets one level deeper than the requested depth so $raChildren can be recorded above.
             */
            if( $depth > 0 ) {
                $raDocs = array_merge( $raDocs, $this->getTree_output( $d['children'], $depth==-1 ? -1 : $depth-1) );
            }
        }

        done:
        return( $raDocs );
    }

    private function getTree_output_doc( ?DocRepDoc2 $oDoc, array $raChildren )
    /**************************************************************************
        Write an oDoc as an array that is convertible to a json string.
        raChildren is an array of DocRepDoc2 keyed by kDoc, so array_keys gets the keys of the children
        oDoc==null means to write the root forest zero node with children (the top-level nodes)
     */
    {
        if( $oDoc ) {
            $kDoc = $oDoc->GetKey();
            $n = SEEDCore_HSC($oDoc->GetName());
            $ti = SEEDCore_HSC($oDoc->GetTitle(''));
            $t = $oDoc->GetType() == 'FOLDER' ? 'folder' : 'page';
            $p = $oDoc->GetParent();
            $permclass = $oDoc->GetPermclass();
            $md = SEEDCore_ArrayExpandSeries( $oDoc->GetValue('raDocMetadata', DocRepDoc2::FLAG_INDEPENDENT),
                                              "'[[k]]':'[[v]]'", true, ['delimiter'=>","] );                    // does SEEDCore_HSC
            $schedule = SEEDCore_HSC($oDoc->GetDocMetadataValue('schedule'));
        } else {
            $kDoc = 0;
            $n = '';
            $ti = '';
            $t = 'folder';
            $p = 0;
            $permclass = '';
            $md = '';
            $schedule = '';
        }
        $children = implode(',', array_keys($raChildren));

        return( ['k'=>$kDoc, 'name'=>$n, 'title'=>$ti, 'doctype'=>$t, 'kParent'=>$p, 'permclass'=>$permclass, 'docMetadata'=>$md, 'children'=>$children] );
    }


    private function doAdd ( $kDoc, $parms ){
        $s = "";
        $kDocNew = 0;
        $oDoc = new DocRepDoc2_Insert( $this->oDocRepDB );

        switch( @$parms['type'] ) {
            case 'text':    $kDocNew = $oDoc->InsertText( "", $parms );   break;      // todo: allow optional text string to be input
            case 'file':    $kDocNew = $oDoc->InsertFile( "", $parms );   break;      // todo: this needs a filename for the first argument
            case 'folder':  $kDocNew = $oDoc->InsertFolder($parms);       break;
        }
        return( [($kDocNew != 0), $s, $kDocNew] );
    }

    private function doUpdate( $kDoc, $parms )
    /*****************************************
        Replace a document's content
        p_src         = TEXT, FILE, SFILE
        p_text        = text content
        p_bNewVersion = true:create a new version
     */
    {
        $s = "";
        $bOk = false;

        if( $kDoc && ($oDoc = $this->oDocRepDB->GetDocRepDoc( $kDoc )) ) {
            $bOk = $oDoc->Update( ['src' => SEEDCore_ArraySmartVal( $parms, 'p_src', ['TEXT','FILE','SFILE'] ),
                                   'data_text' => @$parms['p_text'],
                                   'bNewVersion' => intval(@$parms['p_bNewVersion'])] );
        }

        return( [$bOk,$s] );
    }

    private function doRename( $kDoc, $parms )
    {
        $s = "";
        $bOk = false;

        if( $kDoc && ($oDoc = $this->oDocRepDB->GetDocRepDoc( $kDoc )) ) {
            $bOk = ($oDoc->Rename( $parms ) && $oDoc->UpdatePermClass( $parms ));
        }

        return( [$bOk,$s] );
    }

    private function doVersions( $kDoc, $parms )
    /**
     * return 1 or all versions of a document
     */
    {
        $s = "";
        $bOk = false;

        if( $kDoc && ($oDoc = $this->oDocRepDB->GetDocRepDoc( $kDoc )) ) {
            if(array_key_exists('version', $parms)){
                $s = $oDoc->GetValuesVer($parms['version']);
            }
            else{
                $s = $oDoc->GetAllVersions();
            }
            $bOk = true;
        }

        return( [$bOk,$s] );
    }

    private function doVersionsDiff( $kDoc1, $kDoc2, $ver1, $ver2 )
    /**
     * return html showing the difference between 2 versions
     */
    {
        $s = "";
        $bOk = false;

        if( $kDoc1 && $kDoc2 ){

            $oDoc1 = $this->oDocRepDB->GetDocRepDoc( $kDoc1 );
            $oDoc2 = $this->oDocRepDB->GetDocRepDoc( $kDoc2 );

            $current = $oDoc1->GetValuesVer($ver1)['data_text'];
            $previous = $oDoc2->GetValuesVer($ver2)['data_text'];

            // add new line so php-diff will display it as multiple lines
            $current = str_replace(["</p>", "</h1>", "</h2>", "</h3>", "<br>"], ["</p>\n", "</h1>\n", "</h2>\n", "</h3>\n", "<br>\n"], $current);
            $previous = str_replace(["</p>", "</h1>", "</h2>", "</h3>", "<br>"], ["</p>\n", "</h1>\n", "</h2>\n", "</h3>\n", "<br>\n"], $previous);

            // TODO: add something like if a paragraph is multiple lines, put a \n every 30 char


            // config for php-diff
            // list of configs available can be found at
            // https://github.com/jfcherng/php-diff

            // renderer class name:
            //     Text renderers: Context, JsonText, Unified
            //     HTML renderers: Combined, Inline, JsonHtml, SideBySide
            $rendererName = 'Combined';

            // the Diff class options
            $differOptions = [

            ];

            // the renderer class options
            $rendererOptions = [
                'detailLevel' => 'word',
            ];

            $result = DiffHelper::calculate($previous, $current, $rendererName, $differOptions, $rendererOptions);

            // show html elements as formatting
            $result = str_replace(["&lt;", "&gt;", "&amp;", "&nbsp;"], ["<", ">", "&", " "], $result);

            // quotation marks might mess something up

            $s .= $result;

            $bOk = true;
        }

        return( [$bOk,$s] );
    }


    private function doVersionsDelete( $kDoc, $parms )
    {
        $s = "";
        $bOk = false;

        if( $kDoc && ($oDoc = $this->oDocRepDB->GetDocRepDoc( $kDoc )) ) {
            if(sizeof($oDoc->GetAllVersions()) <= 1){ // if there is only 1 version, need to delete the entire doc
                return; // do nothing for now TODO: add option to delete entire doc
            }
            $bOk = $oDoc->deleteVersion($parms['version']);
        }

        return( [$bOk,$s] );
    }

    private function doVersionsRestore( $kDoc, $parms )
    {
        $s = "";
        $bOk = false;

        if( $kDoc && ($oDoc = $this->oDocRepDB->GetDocRepDoc( $kDoc )) ) {

            $bOk = $oDoc->restoreVersion($parms['version']);
        }

        return( [$bOk,$s] );
    }

    private function doSchedule( $kDoc, $parms )
    /**
     * update schedule in database
     */
    {
        $s = "";
        $bOk = false;

        if( $kDoc && ($oDoc = $this->oDocRepDB->GetDocRepDoc( $kDoc )) ) {
            $bOk = $oDoc->UpdateSchedule( $parms );
        }
        return( [$bOk, $s] );
    }

    private function doDocMetadataStoreAll( $kDoc, $parms )
    /******************************************************
        Replace this doc's docMetadata with the given key/values
     */
    {
        $s = "";
        $bOk = false;

        if( $kDoc && ($oDoc = $this->oDocRepDB->GetDocRepDoc( $kDoc )) ) {
            $p = @$parms['p_docMetadata'];
            $ra = $p ? json_decode($p) : [];
            $oDoc->SetDocMetadataRA( $ra );
            $bOk = true;
        }
        return( [$bOk, $s] );
    }

    private function doXMLExport( $kDoc )
    /**
     * output xml string containing info of all files/folders under current kDoc
     */
    {
        $s = "";
        $bOk = false;
        if( $kDoc ) {
            $s = $this->oDocRepDB->ExportXML($kDoc);
            $bOk = true;
        }
        return( [$bOk, $s] );
    }

    private function doXMLImport( $kDoc, $parms )
    /**
     * takes in a xml string in parms
     * deconstruct xml and add files/folders under kDoc
     */
    {
        $s = "";
        $bOk = false;
        if( isset($kDoc) && $parms['xml'] ) {
            $this->oDocRepDB->ImportXML($parms['kDoc'], $parms['xml']);
            $bOk = true;
        }
        return( [$bOk, $s] );
    }
}
