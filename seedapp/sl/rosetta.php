<?php

/* RosettaSEED app
 *
 * Copyright (c) 2014-2020 Seeds of Diversity Canada
 *
 */

include_once( SEEDCORE."console/console02.php" );
include_once( SEEDCORE."SEEDUI.php" );
include_once( SEEDROOT."Keyframe/KeyframeUI.php" );
include_once( SEEDLIB."sl/sldb.php" );

include_once( "rosetta_ts_cultivarsyn.php" );

$consoleConfig = [
    'CONSOLE_NAME' => "rosetta",
    'HEADER' => "RosettaSEED",
//    'HEADER_LINKS' => array( array( 'href' => 'mbr_email.php',    'label' => "Email Lists",  'target' => '_blank' ),
//                             array( 'href' => 'mbr_mailsend.php', 'label' => "Send 'READY'", 'target' => '_blank' ) ),
    'TABSETS' => ['main'=> ['tabs' => [ 'cultivar'     => ['label'=>'Cultivar'],
                                        'species'      => ['label'=>'Species'],
                                        'cultivarsyn'  => ['label'=>'Cultivar Synonyms'],
                                        'ghost'        => ['label'=>'Ghost']
                                      ],
                            'perms' =>[ 'cultivar'     => ["W SL"],
                                        'species'      => ["W SL"],
                                        'cultivarsyn'  => ["W SL"],
                                        'ghost'        => ['A notyou'],
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

//var_dump($_REQUEST);

class MyConsole02TabSet extends Console02TabSet
{
    private $oApp;
    private $oSLDB;

    private $oComp;
    private $oSrch;
    private $oList;
    private $oForm;

    private $oW;

    function __construct( SEEDAppConsole $oApp )
    {
        global $consoleConfig;
        parent::__construct( $oApp->oC, $consoleConfig['TABSETS'] );

        $this->oApp = $oApp;
        $this->oSLDB = new SLDBRosetta( $this->oApp );
    }

    function TabSet_main_species_Init()         { $this->oW = new RosettaSpeciesListForm( $this->oApp ); $this->oW->Init(); }
    function TabSet_main_species_ControlDraw()  { return( $this->oW->ControlDraw() ); }
    function TabSet_main_species_ContentDraw()  { return( $this->oW->ContentDraw() ); }


    function TabSet_main_cultivar_ControlDraw()
    {
        return( "<div style='padding:20px'>AAA</div>" );
    }

    function TabSet_main_cultivar_ContentDraw()
    {
        return( "<div style='padding:20px'>BBB</div>" );
    }

    function TabSet_main_cultivarsyn_Init( Console02TabSet_TabInfo $oT ) {
                                                      $this->oW = new RosettaCultivarSynonyms( $this->oApp, $oT->oSVA );
                                                      $this->oW->Init(); }
    function TabSet_main_cultivarsyn_ControlDraw()  { return( $this->oW->ControlDraw() ); }
    function TabSet_main_cultivarsyn_ContentDraw()  { return( $this->oW->ContentDraw() ); }
}

class RosettaSpeciesListForm extends KeyframeUI_ListFormUI
{
    private $oSLDB;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oSLDB = new SLDBRosetta( $oApp );

        $raConfig = [
            'sessNamespace' => "RosettaSpecies",
            'cid'   => 'R',
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
