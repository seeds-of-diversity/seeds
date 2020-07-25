<?php

/* Seed Sources app
 *
 * Copyright (c) 2012-2020 Seeds of Diversity Canada
 *
 */

include_once( SEEDCORE."console/console02.php" );
include_once( SEEDCORE."SEEDUI.php" );
include_once( SEEDROOT."Keyframe/KeyframeUI.php" );
include_once( SEEDLIB."sl/sldb.php" );
include_once( SEEDLIB."sl/sources/sl_sources_lib.php" );
include_once( "_sources_edit.php" );
include_once( "_sources_download.php" );

$consoleConfig = [
    'CONSOLE_NAME' => "sources",
    'HEADER' => "Seed Sources",
//    'HEADER_LINKS' => array( array( 'href' => 'mbr_email.php',    'label' => "Email Lists",  'target' => '_blank' ),
//                             array( 'href' => 'mbr_mailsend.php', 'label' => "Send 'READY'", 'target' => '_blank' ) ),
    'TABSETS' => ['main'=> ['tabs' => [ 'sources'         => ['label'=>'Sources'],
                                        'edit'            => ['label'=>'Edit'],
                                        'downloadupload'  => ['label'=>'Download/Upload'],
                                      ],
                            'perms' =>[ 'sources'         => [ "W SLSources", "A SL", "|" ],  // SLSources-W OR SL-A],
                                        'edit'            => [ "W SLSources", "A SL", "|" ],
                                        'downloadupload'  => [ "W SLSources", "A SL", "|" ],
                                        '|'  // allows screen-login even if some tabs are ghosted
                                      ],
                           ],
                 ],
    'pathToSite' => '../../',

    'consoleSkin' => 'green',
];

$oApp = SEEDConfig_NewAppConsole( ['sessPermsRequired' => $consoleConfig['TABSETS']['main']['perms'],
                                   'consoleConfig' => $consoleConfig] );
$oApp->kfdb->SetDebug(1);

SEEDPRG();

class MyConsole02TabSet extends Console02TabSet
{
    private $oApp;
    private $oSLDB;

    private $oComp;
    private $oW;    // object that does the work for the chosen tab

    function __construct( SEEDAppConsole $oApp )
    {
        global $consoleConfig;
        parent::__construct( $oApp->oC, $consoleConfig['TABSETS'] );

        $this->oApp = $oApp;
        $this->oSLDB = new SLDBRosetta( $this->oApp );
    }

    function TabSet_main_sources_Init()         { $this->oW = new SourcesListForm( $this->oApp ); $this->oW->Init(); }
    function TabSet_main_sources_ControlDraw()  { return( $this->oW->ControlDraw() ); }
    function TabSet_main_sources_ContentDraw()  { return( $this->oW->ContentDraw() ); }


    function TabSet_main_edit_Init()
    {
        $this->oW = new SLSourcesAppEdit( $this->oApp, $this->TabSetGetSVA('main','edit') );
    }

    function TabSet_main_edit_ControlDraw()
    {
        $s = "<style>.console02-tabset-controlarea { padding:15px; }</style>"
            ."AAA";

        return( $s );
    }

    function TabSet_main_edit_ContentDraw()
    {
        $s = "<style>.console02-tabset-contentarea { padding:15px; }</style>"
            .$this->oW->Draw();

        return( $s );
    }

    function TabSet_main_downloadupload_Init()
    {
        $this->oW = new SLSourcesAppDownload( $this->oApp, $this->TabSetGetSVA('main','downloadupload') );
    }

    function TabSet_main_downloadupload_ControlDraw()
    {
        $s = "<style>.console02-tabset-controlarea { padding:15px; }</style>"
            ."AAA";

        return( $s );
    }

    function TabSet_main_downloadupload_ContentDraw()
    {
        $s = "<style>.console02-tabset-contentarea { padding:15px; }</style>"
            .$this->oW->Draw();

        return( $s );
    }
}



class SourcesListForm extends KeyframeUI_ListFormUI
{
    private $oSLDB;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oSLDB = new SLDBRosetta( $oApp );

        $raConfig = [
            'sessNamespace' => "Sources",
            'cid'   => 'S',
            'kfrel' => $this->oSLDB->GetKfrel('S'),
            'KFCompParms' => ['raSEEDFormParms'=>['DSParms'=>['fn_DSPreStore'=> [$this,'dsPreStore']]]],

            'raListConfig' => [
                'bUse_key' => true,     // probably makes sense for KeyFrameUI to do this by default
                'cols' => [
                    [ 'label'=>"Sp #",      'col'=>"_key",      'w'=>30 ],
                    [ 'label'=>"psp",       'col'=>"psp",       'w'=>80 ],
                    [ 'label'=>"Name EN",   'col'=>"name_en",   'w'=>120 ],
                    [ 'label'=>"Index EN",  'col'=>"iname_en",  'w'=>120 ],
                    [ 'label'=>"Name FR",   'col'=>"name_fr",   'w'=>120 ], //, "colsel" => array("filter"=>"")),
                    [ 'label'=>"Index FR",  'col'=>"iname_fr",  'w'=>120 ],
                    [ 'label'=>"Botanical", 'col'=>"name_bot",  'w'=>120 ],
                    [ 'label'=>"Family EN", 'col'=>"family_en", 'w'=>120 ],
                    [ 'label'=>"Family FR", 'col'=>"family_fr", 'w'=>120 ],
                    [ 'label'=>"Category",  'col'=>"category",  'w'=>60, "colsel" => array("filter"=>"") ],
                ],
               // 'fnRowTranslate' => [$this,"listRowTranslate"],
            ],

            'raSrchConfig' => [
                'filters' => [
                    ['label'=>'Species #',  'col'=>'S._key'],
                    ['label'=>'Name',       'col'=>'S.name_en'],
                    ['label'=>'Bot name',   'col'=>'S.name_bot'],
                ]
            ],

            'raFormConfig' => [ 'fnExpandTemplate'=>[$this,'speciesForm'] ],
        ];
        parent::__construct( $oApp, $raConfig );
    }

    function dsPreStore( Keyframe_DataStore $oDS )
    {
        return( true );
    }

    function Init()
    {
        parent::Init();
    }

    function ControlDraw()
    {
        return( $this->DrawSearch() );
    }

    function ContentDraw()
    {
        $s = $this->DrawStyle()
           ."<style></style>"
           ."<div>".$this->DrawList()."</div>"
           ."<div style='margin-top:20px;padding:20px;border:2px solid #999'>".$this->DrawForm()."</div>";

        return( $s );
    }

    function speciesForm( $oForm )
    {
        $sStats = "";
        $s = "|||TABLE( || class='slAdminForm' width='100%' border='0')"
            ."||| *psp*       || [[text:psp|size=30]]      || *Name EN*  || [[text:name_en|size=30]]  || *Name FR*  || [[text:name_fr|size=30]]"
            ."||| *Botanical* || [[text:name_bot|size=30]] || *Index EN* || [[text:iname_en|size=30]] || *Index FR* || [[text:iname_fr|size=30]]"
            ."||| *Category*  || [[text:category]] || *Family EN*|| [[text:family_en|size=30]]|| *Family FR*|| [[text:family_fr|size=30]]"
            ."||| *Notes*     || {colspan='3'} ".$oForm->TextArea( "notes", array('width'=>'100%') )
            ."<td colspan='2'>foo".$sStats."&nbsp;</td>"
            ."|||ENDTABLE"
            ."[[hiddenkey:]]"
            ."<INPUT type='submit' value='Save'>";
            return( $s );
    }
}


$s = "[[TabSet:main]]";

$oCTS = new MyConsole02TabSet( $oApp );

$s = $oApp->oC->DrawConsole( $s, ['oTabSet'=>$oCTS] );


echo Console02Static::HTMLPage( SEEDCore_utf8_encode($s), "", 'EN',
                                ['consoleSkin'=>'green',
                                'raScriptFiles' => [$oApp->UrlW()."js/SEEDCore.js"] ] );

?>
<script>SEEDCore_CleanBrowserAddress();</script>
