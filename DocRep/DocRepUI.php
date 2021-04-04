<?php

/* DocRepApp - Application level UI methods. Stateful with respect to a currently-selected document.
 *
 * DocRepUI  - Provides basic UI methods for viewing/updating a DocRepository. Stateless.
 *
 * DocRepQ   - QServer methods for reading/writing a DocRepository
 *
 * DocRepDB  - Elementary database access to DocRepository, using methods that guarantee integrity
 *
 * Copyright (c) 2006-2021 Seeds of Diversity Canada
 *
 */

include_once( SEEDCORE."SEEDIcon.php" );
include_once( "DocRepDB.php" );

class DocRepUI
/*************
    Stateless UI methods for viewing/updating a DocRepository
 */
{
    protected $oDocRepDB;
    protected $pathToSelf;

    function __construct( DocRepDB2 $oDocRepDB, $pathToSelf )
    {
        $this->oDocRepDB = $oDocRepDB;
        $this->pathToSelf = $pathToSelf;
    }

    function DrawTree( $kTree, $raParms, $iLevel = 1 )
    /*************************************************
        Draw the tree rooted at $kTree.
        Don't draw $kTree. This allows the drawn part to be a forest (children of $kTree),
            or a tree with a single root (single child of $kTree).

        $iLevel is a recursion marker for internal use (don't use it).
     */
    {
        $s = "";

        // If a doc is currently selected in the UI, get its info. This is cached in DocRepDB.
        $oDocSelected = ($kSelectedDoc = intval(@$raParms['kSelectedDoc'])) ? $this->oDocRepDB->GetDoc( $kSelectedDoc ) : null;

        // If the UI provides a list of currently-expanded nodes, get ready to use it.
        $raTreeExpanded = @$raParms['raTreeExpanded'] ?: array();


// depth== 2: get the immediate children but also count the grandchildren so count($ra['children']) is set.
// other than that count we only need depth==1; there's probably a more efficient way to get count($ra['children'])
        $raTree = $this->oDocRepDB->GetSubTree( $kTree, 2 );
        $s .= "<div class='DocRepTree_level DocRepTree_level$iLevel'>";
        foreach( $raTree as $k => $ra ) {
            if( !($oDoc = $this->oDocRepDB->GetDocRepDoc( $k )) )  continue;

            if( @$raTreeExpanded[$k] ) {
                $sExpandCmd = 'collapse';           // This doc is expanded so if you click it will collapse
            } else if( count($ra['children']) ) {
                $sExpandCmd = 'expand';             // This doc is collapsed and has children so if you click it will expand
            } else {
                $sExpandCmd = '';                   // This doc has no children so it cannot be expanded
            }
            $raTitleParms = array(
                'bSelectedDoc' => ($k == $kSelectedDoc),
                'sExpandCmd' => $sExpandCmd,
            );
            $s .= "<div class='DocRepTree_doc'>"
                 .$this->DrawTree_title( $oDoc, $raTitleParms );
            if( @$raTreeExpanded[$k] || ($oDocSelected && in_array( $k, $oDocSelected->GetAncestors()) ) ) {
                $s .= $this->DrawTree( $k, $raParms, $iLevel + 1 );
            }
            $s .= "</div>";  // doc
        }
        $s .= "</div>";  // level level$level

        return( $s );
    }

    function DrawTree_title( DocRepDoc2 $oDoc, $raTitleParms )
    {
        $c = $raTitleParms['bSelectedDoc'] ? "DocRepTree_titleSelected" : "";

        return( "<div class='DocRepTree_title $c'>"
               .$this->treeTitleExpandCtrl( $oDoc, $raTitleParms )     // the triangle for opening/closing a folder
               .$this->treeTitleIcon( $oDoc, $raTitleParms )           // the icon of the doc type
               ."&nbsp;"
               .$this->treeTitleTitle( $oDoc, $raTitleParms )          // the title
               ."</div>" );
    }

    protected function treeTitleExpandCtrl( DocRepDoc2 $oDoc, $raTitleParms )
    {
        $s = "";

        if( $oDoc->GetType() == 'FOLDER' ) {
            $s = "<a href='{$this->pathToSelf}?k={$oDoc->GetKey()}'>"
                    .(@$raTitleParms['bOpened'] ? SEEDIcon::TriangleDown() : SEEDIcon::TriangleLeft())
                ."</a>";
        } else {
            // blank space instead of a folder-expand ctrl
            $s = "<div style='width:16px;height:10px;display:inline-block'>&nbsp;</div>";
        }

        return( $s );
    }

    protected function treeTitleIcon( DocRepDoc2 $oDoc, $raTitleParms )
    {
        switch( $oDoc->GetType() ) {
            case 'FOLDER':  $img = 'folder.png';    break;
            case 'DOC':     $img = 'text.png';      break;
            case 'IMAGE':   $img = 'image.png';     break;
            default:        $img = 'default.png';   break;
        }

        return( "<img src='".SEEDW_URL."img/icons/$img' width='20' />" );
    }

    protected function treeTitleTitle( DocRepDoc2 $oDoc, $raTitleParms )
    {
        return( "<a href='?k={$oDoc->GetKey()}'><nobr>"
               .($oDoc->GetTitle('') ?: ($oDoc->GetName() ?: "Untitled"))
               ."</nobr></a>" );
    }

    function View( DocRepDoc2 $oDoc, $flag = "" )
    {
        $s = "";

        switch( $oDoc->GetType() ) {
            case 'DOC':
                $s = $oDoc->GetText( $flag );
                break;
            case 'FOLDER':
                break;

        }
        return( $s );
    }
}

class DocRepApp1
/***************
    Application level UI, stateful with respect to a currently-selected doc.
 */
{
    private $oUI;
    private $oDB;
    private $raConfig;
    public  $oDoc = null;   // current doc

    function __construct( DocRepUI $oUI, DocRepDB2 $oDB, $kSelectedDoc, $raConfig = array() )
    {
        $this->oUI = $oUI;
        $this->oDB = $oDB;
        $this->raConfig = $raConfig;
        if( $kSelectedDoc ) $this->oDoc = $this->oDB->GetDocRepDoc( $kSelectedDoc );

    }

    static function Style()
    {
        $s = "<style>
              .docrepapp_treetabs { margin-left: -25px; margin-bottom: -17px; }
              .docrepapp_treetabs li {
                  display: inline-block;
                  padding: 2px 15px;
                  color:#444;
                  border-radius: 8px 8px 0px 0px;
                  border:1px solid #777;
                  border-bottom:none;
              }

              /* Each set of children is indented.
                 Since the root forest is a fake doc containing an (indented) level of root-children, compensate by back-indenting that doc.
               */
              .DocRepTree_doc[data-kdoc='0'] { margin-left:-30px; }
              .DocRepTree_doc {}
              .DocRepTree_level            { margin-left:30px; }



              .DocRepTree_title {
                  padding: 1px;
                  color: #337ab7;
                  text-decoration: none;
                  cursor: pointer;
              }
              .DocRepTree_titleSelected {
                  font-weight: bold;
              }

              </style>";

        $s .= <<<DocRepApp1_Script
            <script>
            $(document).ready( function() {

                /* Click on a treetab to show the corresponding form
                 */
                $(".docrepapp_treetabs li").click( function() {
                    var f = $(this).attr('data-form');

                    /* highlight the tab
                     */
                    $(".docrepapp_treetabs li").css('background-color','transparent');
                    $(this).css('background-color','#eee');

                    /* show the corresponding form
                     */
                    $(".docrepapp_treeform").hide();
                    $(".docrepapp_treeform_"+f).show();
                });

                /* Handle form submission: Rename
                 */
                $("#docrepapp_treeform_rename_form").on('submit', function(e) {
                    e.preventDefault();
                    alert( "Rename" );
                });


                /* Initialize to show View tab
                 */
                $(".docrepapp_treeform_view").show();
                $(".docrepapp_treetabs li[data-form='view']").css('background-color','#eee');

            });
            </script>
DocRepApp1_Script;

        return( $s );
    }

    function TreeTabs()
    {
        $bFolder = $this->oDoc->GetType() == 'FOLDER';

        $s = "<div class='docrepapp_treetabs'><ul>"
                .(!$bFolder ? "<li data-form='view'>View</li>" : "")
                ."<li data-form='new'>New</li>"
                .(!$bFolder ? "<li data-form='edit'>Edit</li>" : "")
                ."<li data-form='rename'>Rename</li>"
                ."<li data-form='delete'>Delete</li>"
                .(@$raConfig['bTabAdvanced']? "<li data-form='advanced'>Advanced</li>" : "")
            ."</ul></div>";
        return( $s );
    }

    function TreeForms()
    {
        $s = <<<DocRepApp1_TreeForms
            <div class='docrepapp_treeform docrepapp_treeform_view' style='display:none'>
                <div class='docrepapp_treeform_view_text'>[[DocRepApp_TreeForm_View_Text]]</div>
            </div>
            <div class='docrepapp_treeform docrepapp_treeform_new' style='display:none'>
                NEW FORM
            </div>
            <div class='docrepapp_treeform docrepapp_treeform_edit' style='display:none'>
                EDIT FORM
            </div>
            <div class='docrepapp_treeform docrepapp_treeform_rename' style='display:none'>
                <form id='docrepapp_treeform_rename_form'>
                    <!-- oForm->Hidden( 'k', array( 'value' => k ) ) -->
                    <!-- oForm->Hidden( 'action', array( 'value' => 'rename2' ) ) -->
                    <input type='text' name='doc_name' id='doc_name' value=''/>
                    <input type='submit' value='Rename'/>
                </form>
            </div>
            <div class='docrepapp_treeform docrepapp_treeform_delete' style='display:none'>
                DELETE FORM
            </div>
            <div class='docrepapp_treeform docrepapp_treeform_advanced' style='display:none'>
                ADVANCED FORM
            </div>
DocRepApp1_TreeForms;

        return( $s );
    }


    function GetSelectedDocKey()
    {
        return( $this->oDoc ? $this->oDoc->GetKey() : 0 );
    }

    function DrawDocTree( $kTree )
    {
        $kSelectedDoc = $this->GetSelectedDocKey();
        $s = $this->oUI->DrawTree( $kTree, array('kSelectedDoc'=>$kSelectedDoc) );

        return( $s );
    }

    function PreviewDoc()
    {
        return( $this->oDoc ? $this->oUI->View( $this->oDoc ) : "" );
    }

    function GetDocHTML()
    {
        // If the Doc is html, return it
        return( $this->oDoc && (true /* $this->oDoc->type is html */) ? $this->oDoc->GetText( $flag = "" ) : "" );
    }
}
