<?php

/* docmanagerui.php
 *
 * Copyright 2006-2022 Seeds of Diversity Canada
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
        return( [SEEDW_URL."seedapp/DocRep/DocRepApp.js",
                 SEEDW_URL."seedapp/DocRep/docmanager.js",
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
        $seedw_url = @$raConfig['seedw_url'] ?: SEEDW_URL;      // url to seeds/wcore/
        $q_url     = @$raConfig['q_url']     ?: '';             // url to QServerDocRep ajax handler

        $s = "<div class='docman_doctree'>"
             ."<div class='container-fluid'>"
                 ."<div class='row'>"
                     ."<div class='col-md-6'> <div id='docmanui_tree'></div> </div>"
                     ."<div class='col-md-6'> <div id='docrepctrlview'></div> </div>"
                 ."</div>"
            ."</div></div>";

//        $s = str_replace( "[[DocRepApp_TreeForm_View_Text]]", $o->oDocMan->GetDocHTML(), $s );

        $oDocRepDB = DocRepUtil::New_DocRepDB_WithMyPerms( $this->oApp );
        $raTree = $oDocRepDB->GetSubTree( 0, -1 );

        /* oDocRepApp02_Config is defined in docmanager.js with default config values.
         * Set them here to reflect the actual application environment.
         * q_url='' means the current application must handle dr-* ajax commands via QServerDocRep
         */
        $s .= "<script>oDocRepApp02_Config.env.seedw_url = '${seedw_url}';
                       oDocRepApp02_Config.env.q_url = '${q_url}';
                       oDocRepApp02_Config.docsPreloaded = new Map( [".$this->outputTree( $oDocRepDB, 0, $raTree )." ] );
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
            $perms = $oDoc->GetPermclass();
            $raDocMetadata = $oDoc->GetValue('raDocMetadata', DocRepDoc2::FLAG_INDEPENDENT);
        } else {
            // kDoc is zero only at the root recursion when the fake "zero node" holds the root forest. The record has no metadata, just children.
            $p = 0;
            $n = '';
            $ti = '';
            $t = 'folder';
            $schedule = '';
            $perms = '';
            $raDocMetadata = [];
        }
        $c = implode(',', array_keys($raChildren));
        $md = "";
        foreach( $raDocMetadata as $k => $v ) {
            $md .= ($md ? ", " : "")
                  ."$k:'".SEEDCore_HSC($v)."'";
        }

        $s .= "[$kDoc, { k:$kDoc, name:'$n', title:'$ti', doctype:'$t', kParent:$p, children: [$c], schedule:'$schedule', perms:'$perms', docMetadata:{ $md } }],";

        foreach( $raChildren as $k => $ra ) {
            $s .= $this->outputTree( $oDocRepDB, $k, $ra['children'] );
        }

        done:
        return( $s );
    }
}
