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
                
            case 'dr-diffVersion':
                $rQ['bHandled'] = true;
                list($rQ['bOk'],$rQ['sOut']) = $this->diffVersion($parms['kDoc1'], $parms['kDoc2'], $parms['ver1'], $parms['ver2']);
                break;
                
            case 'dr--deleteVersion':
                $rQ['bHandled'] = true;
                list($rQ['bOk'],$rQ['sOut']) = $this->deleteVersion($kDoc, $parms);
                break;
                
            case 'dr--restoreVersion':
                $rQ['bHandled'] = true;
                list($rQ['bOk'],$rQ['sOut']) = $this->restoreVersion($kDoc, $parms);
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
    
    private function diffVersion( $kDoc1, $kDoc2, $ver1, $ver2 )
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
            
            
            // renderer class name:
            //     Text renderers: Context, JsonText, Unified
            //     HTML renderers: Combined, Inline, JsonHtml, SideBySide
            $rendererName = 'Inline';
            
            // the Diff class options
            $differOptions = [
                // show how many neighbor lines
                // Differ::CONTEXT_ALL can be used to show the whole file
                'context' => 3,
                // ignore case difference
                'ignoreCase' => false,
                // ignore whitespace difference
                'ignoreWhitespace' => false,
            ];
            
            // the renderer class options
            $rendererOptions = [
                // how detailed the rendered HTML in-line diff is? (none, line, word, char)
                'detailLevel' => 'word',
                // renderer language: eng, cht, chs, jpn, ...
                // or an array which has the same keys with a language file
                'language' => 'eng',
                // show line numbers in HTML renderers
                'lineNumbers' => true,
                // show a separator between different diff hunks in HTML renderers
                'separateBlock' => true,
                // show the (table) header
                'showHeader' => true,
                // the frontend HTML could use CSS "white-space: pre;" to visualize consecutive whitespaces
                // but if you want to visualize them in the backend with "&nbsp;", you can set this to true
                'spacesToNbsp' => false,
                // HTML renderer tab width (negative = do not convert into spaces)
                'tabSize' => 4,
                // this option is currently only for the Combined renderer.
                // it determines whether a replace-type block should be merged or not
                // depending on the content changed ratio, which values between 0 and 1.
                'mergeThreshold' => 0.8,
                // this option is currently only for the Unified and the Context renderers.
                // RendererConstant::CLI_COLOR_AUTO = colorize the output if possible (default)
                // RendererConstant::CLI_COLOR_ENABLE = force to colorize the output
                // RendererConstant::CLI_COLOR_DISABLE = force not to colorize the output
                'cliColorization' => RendererConstant::CLI_COLOR_AUTO,
                // this option is currently only for the Json renderer.
                // internally, ops (tags) are all int type but this is not good for human reading.
                // set this to "true" to convert them into string form before outputting.
                'outputTagAsString' => false,
                // this option is currently only for the Json renderer.
                // it controls how the output JSON is formatted.
                // see available options on https://www.php.net/manual/en/function.json-encode.php
                'jsonEncodeFlags' => \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
                // this option is currently effective when the "detailLevel" is "word"
                // characters listed in this array can be used to make diff segments into a whole
                // for example, making "<del>good</del>-<del>looking</del>" into "<del>good-looking</del>"
                // this should bring better readability but set this to empty array if you do not want it
                'wordGlues' => [' ', '-'],
                // change this value to a string as the returned diff if the two input strings are identical
                'resultForIdenticals' => null,
                // extra HTML classes added to the DOM of the diff container
                'wrapperClasses' => ['diff-wrapper'],
            ];
            

            $differ = new Differ(explode("\n", $previous), explode("\n", $current), $differOptions);
            $renderer = RendererFactory::make($rendererName, $rendererOptions); // or your own renderer object
            $result = $renderer->render($differ);
            
            $result = DiffHelper::calculate($previous, $current, $rendererName, $differOptions, $rendererOptions);
            
            $result = str_replace("&lt;", "<", $result);
            $result = str_replace("&gt;", ">", $result);
            $result = str_replace("&amp;", "&", $result);
            $result = str_replace("&nbsp;", " ", $result);
            // quotation marks might mess something up 
            
            $s .= $result;
            
            $bOk = true;
        }
        
        return( [$bOk,$s] );
    }
    
    
    private function deleteVersion( $kDoc, $parms )
    {
        $s = "";
        $bOk = false;
        
        if( $kDoc && ($oDoc = $this->oDocRepDB->GetDocRepDoc( $kDoc )) ) {
            
            // delete version 
        }
        
        return( [$bOk,$s] );
    }
    
    private function restoreVersion( $kDoc, $parms )
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
