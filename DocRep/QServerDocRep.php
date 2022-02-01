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

        // check permissions


        $kDoc = SEEDInput_Int('kDoc');

        switch( $cmd ) {
            case 'dr-preview':
                $rQ['bHandled'] = true;
                list($rQ['bOk'],$rQ['sOut']) = $this->doPreview($kDoc);
                break;

            case 'dr--add':
                $rQ['bHandled'] = true;
                list($rQ['bOk'],$rQ['sOut']) = $this->doAdd($kDoc, $parms);
                break;

            case 'dr--update':
                $rQ['bHandled'] = true;
                list($rQ['bOk'],$rQ['sOut']) = $this->doUpdate($kDoc);
                break;

            case 'dr--rename':
                $rQ['bHandled'] = true;
                list($rQ['bOk'],$rQ['sOut']) = $this->doRename($kDoc, $parms);
                break;
                
            case 'dr-versions':
                $rQ['bHandled'] = true;
                list($rQ['bOk'],$rQ['sOut']) = $this->doVersions($kDoc, $parms);
                break;
                
            case 'dr-versionsDiff':
                $rQ['bHandled'] = true;
                list($rQ['bOk'],$rQ['sOut']) = $this->doVersionsDiff($parms['kDoc1'], $parms['kDoc2'], $parms['ver1'], $parms['ver2']);
                break;
                
            case 'dr--versionsDelete':
                $rQ['bHandled'] = true;
                list($rQ['bOk'],$rQ['sOut']) = $this->doVersionsDelete($kDoc, $parms);
                break;
                
            case 'dr--versionsRestore':
                $rQ['bHandled'] = true;
                list($rQ['bOk'],$rQ['sOut']) = $this->doVersionsRestore($kDoc, $parms);
                break;

            case 'dr--schedule':
                $rQ['bHandled'] = true;
                list($rQ['bOk'],$rQ['sOut']) = $this->doSchedule($kDoc, $parms);
                break;
                
        }

        done:
        return( $rQ );
    }

    private function doPreview( $kDoc )
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

    private function doAdd ( $kDoc, $parms ){
        $s = "";
        $bOk = false;
        $oDoc = new DocRepDoc2_Insert( $this->oDocRepDB );

        if( $parms['type'] == 'file' ) {
            $bOk = $oDoc->InsertFile( "", $parms );
        }
        else if( $parms['type'] == 'folder' ) {
            $bOk = $oDoc->InsertFolder($parms);
        }
        return( [$bOk,$s] );
    }

    private function doUpdate( $kDoc )
    {
        $s = "";
        $bOk = false;

        if( $kDoc && ($oDoc = $this->oDocRepDB->GetDocRepDoc( $kDoc )) ) {
            if( ($p_text = SEEDInput_Str('p_text')) ) {
                $bOk = $oDoc->Update( ['src'=>'TEXT', 'data_text'=>$p_text, 'bNewVersion'=>SEEDInput_Int('p_bNewVersion')] );
            }
        }

        return( [$bOk,$s] );
    }

    private function doRename( $kDoc, $parms )
    {
        $s = "";
        $bOk = false;

        if( $kDoc && ($oDoc = $this->oDocRepDB->GetDocRepDoc( $kDoc )) ) {
            $bOk = $oDoc->Rename( $parms );
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
            
            // delete version 
        }
        
        return( [$bOk,$s] );
    }
    
    private function doVersionsRestore( $kDoc, $parms )
    {
        $s = "";
        $bOk = false;
        
        if( $kDoc && ($oDoc = $this->oDocRepDB->GetDocRepDoc( $kDoc )) ) {
            
            // restore version
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
}
