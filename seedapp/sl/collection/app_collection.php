<?php

/* Seed collection manager
 *
 * Copyright 2020-2026 Seeds of Diversity Canada
 */

/* You can either execute this script directly and use SEED_APP_BOOT_REQUIRED to initialize config
 * or execute a boot script that includes this script after setting the config parameters
 */
if( !defined( "SEEDROOT" ) ) {
    define( "SEEDROOT", "../../../" );
    define( "SEED_APP_BOOT_REQUIRED", true );
    include_once( SEEDROOT."seedConfig.php" );
}



include_once( SEEDCORE."console/console02.php" );
include_once( SEEDCORE."SEEDUI.php" );
include_once( SEEDROOT."Keyframe/KeyframeUI.php" );
include_once( SEEDLIB."sl/sldb.php" );
include_once( SEEDAPP."sl/sl_ts_adoptions.php");

include_once( "collectionTab.php" );
include_once( "batchopsTab.php" );
include_once( "overviewTab.php" );

class SLApp
{
    // These define permissions for apps. The arrays double for SEEDSessionAccount and TabSetPermissions
    static $raAppPerms = [
        // the My Collection app
        'slCollection' =>
            [ 'slcollMain'  => ["W SLCollection", "A SL", "|"],
              'slcollBatch' => ["W SLCollection", "A SL", "|"],
              'slcollAdopt' => ["W SLCollection", "A SL", "|"],
              'slcollOver'  => ["W SLCollection", "A SL", "|"],
              '|'  // allows screen-login even if some tabs are ghosted
            ],
    ];
}


$consoleConfig = [
    'CONSOLE_NAME' => "collection",
    'HEADER' => "My Seed Collection",
//    'HEADER_LINKS' => array( array( 'href' => 'mbr_email.php',    'label' => "Email Lists",  'target' => '_blank' ),
//                             array( 'href' => 'mbr_mailsend.php', 'label' => "Send 'READY'", 'target' => '_blank' ) ),
    'TABSETS' => ['main'=> ['tabs' => [ 'slcollMain'   => ['label'=>'My Collection'],
                                        'slcollBatch'  => ['label'=>'Batch Operations'],
                                        'slcollAdopt'  => ['label'=>'Adoptions'],
                                        'slcollOver'   => ['label'=>'Overview'],
                                        //'cultivarsyn'  => ['label'=>'Cultivar Synonyms'],
                                        //'ghost'        => ['label'=>'Ghost']
                                      ],
                            'perms' => SLApp::$raAppPerms['slCollection']
                           ],

                  // sub-tabs for collection
                  'colltabs'=> ['tabs' => [ 'accession'    => ['label'=>'Accession'],
                                            'germ'         => ['label'=>'Germination Tests'],
                                            'packetlabels' => ['label'=>'Packet Labels'],
                                          ],
                                'perms' =>[ 'accession'    => ["PUBLIC"],
                                            'germ'         => ["PUBLIC"],
                                            'packetlabels' => ["PUBLIC"],
                                            '|'
                                          ]
                                ],

                  // sub-tabs for batch
                  'batchtabs'=> ['tabs' => [ 'germ' => ['label'=>'Germination Tests'],
                                             //'packetlabels' => ['label'=>'Packet Labels'],
                                          ],
                                'perms' =>[ 'germ'   => ["PUBLIC"],
                                            //'packetlabels'   => ["PUBLIC"],
                                            '|'
                                          ]
                                ],

                 ],
    'pathToSite' => '../../',

    'consoleSkin' => 'green',
];

$oApp = SEEDConfig_NewAppConsole( ['consoleConfig'=>$consoleConfig, 'sessPermsRequired'=>SLApp::$raAppPerms['slCollection']] );
$oApp->kfdb->SetDebug(1);

SEEDPRG();


class MyConsole02TabSet extends Console02TabSet
{
    private $oApp;
    private $oW;
    private $oSLDB;

    function __construct( SEEDAppConsole $oApp )
    {
        global $consoleConfig;
        parent::__construct( $oApp->oC, $consoleConfig['TABSETS'] );

        $this->oApp = $oApp;
        $this->oSLDB = new SLDBCollection( $this->oApp );
    }

    function TabSet_main_slcollMain_Init()
    {
        // this tab's control and content areas are multiplexed by an "Add new accession" button
        $this->oW = SEEDInput_Int('doAddNewAcc') ? (new CollectionMain_NewMode($this->oApp)) : (new CollectionMain_EditMode($this->oApp));
        $this->oW->Init();
    }
    function TabSet_main_slcollBatch_Init(Console02TabSet_TabInfo $oT)  { $this->oW = new CollectionBatchOps($this->oApp, $oT->oSVA); $this->oW->Init(); }
    function TabSet_main_slcollAdopt_Init()                             { $this->oW = new MbrAdoptionsListForm($this->oApp);          $this->oW->Init(); }
    function TabSet_main_slcollOver_Init(Console02TabSet_TabInfo $oT)   { $this->oW = new CollectionOverview($this->oApp, $oT->oSVA); $this->oW->Init(); }

    function TabSetControlDraw($tsid, $tabname)
    {
        return( $tsid == 'main' ? $this->oW->ControlDraw()
                                : parent::TabSetControlDraw($tsid, $tabname)    // maybe need this for sub-tabsets?
        );
    }
    function TabSetContentDraw($tsid, $tabname)
    {
        return( $tsid == 'main' ? $this->oW->ContentDraw()
                                : parent::TabSetContentDraw($tsid, $tabname)    // maybe need this for sub-tabsets?
        );
    }
}


$oCTS = new MyConsole02TabSet( $oApp );

$s = $oApp->oC->DrawConsole( "[[TabSet:main]]", ['oTabSet'=>$oCTS] );

/* Overview tab gets data from QServer in utf8, others are iso8859
 */
if( $oCTS->TabSetGetCurrentTab('main') != 'slcollOver' ) {
    $s = SEEDCore_utf8_encode($s);
}

echo Console02Static::HTMLPage( $s, "", 'EN',
                                ['consoleSkin'=>'green',
                                 'raScriptFiles' => [$oApp->UrlW()."js/SEEDCore.js",
                                                     $oApp->UrlW()."js/SEEDUI.js",           // for SearchControl reset button
                                                     $oApp->UrlW()."js/console02.js",        // for ConsolePage
                                                     $oApp->UrlW()."js/SFUTextComplete.js",  // for SLPcvSelector.js
                                                     $oApp->UrlW()."js/SLPcvSelector.js",    // for cultivar search
                                                     $oApp->UrlW()."seedapp/sl/SLPcvSelect2.js",    // for cultivar search
                                                     $oApp->UrlW()."seedapp/sl/collection-batch.js"],
                                 'bSelect2' => true
                                ] );

echo "<script>SEEDCore_CleanBrowserAddress();</script>";

// use $oApp->urlQ() instead of Site_UrlQ() when app/q2/index.php has QCollection
echo "<script>collectionBatch.qUrl = '".Site_UrlQ()."';</script>";
