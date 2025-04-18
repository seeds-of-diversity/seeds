<?php

/* docmanagerui.php
 *
 * Copyright 2006-2024 Seeds of Diversity Canada
 *
 * UI components for document management
 */

include_once( SEEDROOT."DocRep/DocRep.php" );
include_once( SEEDROOT."DocRep/QServerDocRep.php" );


class DocManagerUI_Documents
/***************************
    Application UI for the DocRep document tree and ctrlview
 */
{
    private $oApp;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
    }

    static function ScriptFiles()
    /****************************
        JS files required for the document UI
     */
    {
        return( [SEEDW_URL."seedapp/DocRep/DocRepApp.js",           // central standard application
                 SEEDW_URL."seedapp/DocRep/DocRepApp_Tree.js",      // tree widget
                 SEEDW_URL."seedapp/DocRep/DocRepApp_CtrlView.js",  // tabbed ctrl widget

                 SEEDW_URL."seedapp/DocRep/docmanager.js",          // custom application (runs code defined in above files so include after)
                 SEEDW_URL."seedapp/DocRep/myDocRep/myDocRepCtrlView_Rename.js",
                 SEEDW_URL."seedapp/DocRep/myDocRep/myDocRepCtrlView_Add.js",
                 "https://cdn.ckeditor.com/4.17.1/standard/ckeditor.js"
        ] );
    }

    static function StyleFiles()
    /***************************
        CSS files required for the document UI
     */
    {
        return( [SEEDW_URL."seedapp/DocRep/DocRepApp.css"] );
    }

    function DrawDocumentsUI( $raConfig )
    {
        $s = "<div class='docman_doctree'>"
             ."<div class='container-fluid'>"
                 ."<div class='row'>"
                     ."<div class='col-md-6'> <div id='docmanui_tree'></div> </div>"
                     ."<div class='col-md-6'> <div id='docmanui_ctrlview'></div> </div>"
                 ."</div>"
            ."</div></div>";

//        $s = str_replace( "[[DocRepApp_TreeForm_View_Text]]", $o->oDocMan->GetDocHTML(), $s );

        /* oDocRepApp02_Config is defined in docmanager.js with default config values.
         * Set them here to reflect the actual application environment.
         *     q_url='' means the current application must handle dr-* ajax commands via QServerDocRep
         *     eUILevel = 1 for basic UI; 2 for more advanced UI; 3 for full UI
         *     docsPreloaded allows docs to be provided at construction instead of fetched on demand
         */
        $seedw_url = @$raConfig['seedw_url'] ?: SEEDW_URL;      // url to seeds/wcore/
        $q_url     = @$raConfig['q_url']     ?: '';             // url to QServerDocRep ajax handler

        $eUILevel = $this->oApp->sess->CanRead('DocRepMgr3') ? 3 :
                   ($this->oApp->sess->CanRead('DocRepMgr2') ? 2 : 1);

        $oDocRepDB = DocRepUtil::New_DocRepDB_WithMyPerms( $this->oApp );
        $raTree = $oDocRepDB->GetSubTreeDescendants( 0, -1 );

        $s .= "<script>oDocRepApp02_Config.env.seedw_url = '{$seedw_url}';
                       oDocRepApp02_Config.env.q_url = '{$q_url}';
                       oDocRepApp02_Config.docsPreloaded = new Map( [".$this->outputTree( $oDocRepDB, 0, $raTree )." ] );
                       oDocRepApp02_Config.ui.eUILevel = {$eUILevel};
               </script>";

        return( $s );
    }

    private function outputTree( $oDocRepDB, $kDoc, $raChildren )
    {
        $s = "";

        if( $kDoc ) {
            if( !($oDoc = $oDocRepDB->GetDocRepDoc( $kDoc )) )  goto done;

            $n = SEEDCore_HSC($oDoc->GetName());
            $ti = SEEDCore_HSC($oDoc->GetTitle(''));
            $t = $oDoc->GetType() == 'FOLDER' ? 'folder' : 'page';
            $p = $oDoc->GetParent();
            $schedule = SEEDCore_HSC($oDoc->GetDocMetadataValue('schedule'));
            $permclass = $oDoc->GetPermclass();
            $raDocMetadata = $oDoc->GetValue('raDocMetadata', DocRepDoc2::FLAG_INDEPENDENT);
        } else {
            // kDoc is zero only at the root recursion when the fake "zero node" holds the root forest. The record has no metadata, just children.
            $p = 0;
            $n = '';
            $ti = '';
            $t = 'folder';
            $schedule = '';
            $permclass = '';
            $raDocMetadata = [];
        }
        $c = implode(',', array_keys($raChildren));
        $md = SEEDCore_ArrayExpandSeries( $raDocMetadata, "'[[k]]':'[[v]]'", true, ['delimiter'=>","] );

        $s .= "[$kDoc, { k:$kDoc, name:'$n', title:'$ti', doctype:'$t', kParent:$p, children: [$c], schedule:'$schedule', permclass:'$permclass', docMetadata:{ $md } }],";

        foreach( $raChildren as $k => $ra ) {
            $s .= $this->outputTree( $oDocRepDB, $k, $ra['children'] );
        }

        done:
        return( $s );
    }
}
