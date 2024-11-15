<?php

/* RosettaSEED app
 *
 * Copyright (c) 2014-2024 Seeds of Diversity Canada
 *
 */

include_once( SEEDCORE."console/console02.php" );
include_once( SEEDCORE."SEEDUI.php" );
include_once( SEEDROOT."Keyframe/KeyframeUI.php" );
include_once( SEEDLIB."sl/QServerRosetta.php" );

include_once( "rosetta_ts_cultivars.php" );
include_once( "rosetta_ts_cultivarsyn.php" );
include_once( "rosetta_ts_species.php" );


$consoleConfig = [
    'CONSOLE_NAME' => "rosetta",
    'HEADER' => "RosettaSEED",
//    'HEADER_LINKS' => array( array( 'href' => 'mbr_email.php',    'label' => "Email Lists",  'target' => '_blank' ),
//                             array( 'href' => 'mbr_mailsend.php', 'label' => "Send 'READY'", 'target' => '_blank' ) ),
    'TABSETS' => ['main'=> ['tabs' => [ 'cultivar'     => ['label'=>'Cultivars'],
                                        'species'      => ['label'=>'Species'],
                                        'cultivarsyn'  => ['label'=>'Cultivar Synonyms'],
                                        'speciessyn'   => ['label'=>'Species Synonyms'],
                                        'admin'        => ['label'=>'Admin']
                                      ],
                            'perms' =>[ 'cultivar'     => ["W SL"],
                                        'species'      => ["W SL"],
                                        'speciessyn'   => ["W SLbob"],
                                        'cultivarsyn'  => ["W SL"],
                                        'admin'        => ['A notyou'],
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

    function TabSet_main_species_Init( Console02TabSet_TabInfo $oT )     { $this->oW = new RosettaSpeciesListForm($this->oApp);              $this->oW->Init(); }
    function TabSet_main_cultivar_Init( Console02TabSet_TabInfo $oT )    { $this->oW = new RosettaCultivarListForm($this->oApp);             $this->oW->Init(); }
    function TabSet_main_speciessyn_Init( Console02TabSet_TabInfo $oT )  { $this->oW = new RosettaSpeciesSynonyms($this->oApp, $oT->oSVA);   $this->oW->Init(); }
    function TabSet_main_cultivarsyn_Init( Console02TabSet_TabInfo $oT ) { $this->oW = new RosettaCultivarSynonyms($this->oApp, $oT->oSVA);  $this->oW->Init(); }

    function TabSetControlDraw( $tsid, $tabname )  { return( $this->oW->ControlDraw() ); }
    function TabSetContentDraw( $tsid, $tabname )  { return( $this->oW->ContentDraw() ); }
}

class RosettaSpeciesSynonyms // extends KeyframeUI_ListFormUI
{
    function __construct( SEEDAppConsole $oApp )
    {

    }

    function Init()
    {
        //        parent::Init();
    }

    function ControlDraw()
    {
        //        return( $this->DrawSearch() );
    }

    function ContentDraw()
    {
        //        $s = $this->DrawStyle()
        //        ."<style></style>"
        //            ."<div>".$this->DrawList()."</div>"
        //                ."<div style='margin-top:20px;padding:20px;border:2px solid #999'>".$this->DrawForm()."</div>";
        //
        //                return( $s );
    }

}


$oCTS = new MyConsole02TabSet( $oApp );

$s = $oApp->oC->DrawConsole( "[[TabSet:main]]", ['oTabSet'=>$oCTS] );

echo Console02Static::HTMLPage( SEEDCore_utf8_encode($s),
                                "", 'EN',
                                ['consoleSkin'=>'green',
                                 'raScriptFiles' => [$oApp->UrlW()."js/SEEDCore.js"] ] );

?>
<script>SEEDCore_CleanBrowserAddress();</script>
