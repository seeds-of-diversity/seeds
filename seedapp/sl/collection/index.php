<?php

/* Seed collection manager
 *
 * Copyright 2020 Seeds of Diversity Canada
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

include_once( "collectiontab_germtests.php" );
include_once( "collectiontab_packetlabels.php" );
include_once( "batchoperations.php" );

$consoleConfig = [
    'CONSOLE_NAME' => "collection",
    'HEADER' => "My Seed Collection",
//    'HEADER_LINKS' => array( array( 'href' => 'mbr_email.php',    'label' => "Email Lists",  'target' => '_blank' ),
//                             array( 'href' => 'mbr_mailsend.php', 'label' => "Send 'READY'", 'target' => '_blank' ) ),
    'TABSETS' => ['main'=> ['tabs' => [ 'collection'   => ['label'=>'My Collection'],
                                        'batch'        => ['label'=>'Batch Operations'],
                                        //'cultivarsyn'  => ['label'=>'Cultivar Synonyms'],
                                        //'ghost'        => ['label'=>'Ghost']
                                      ],
                            'perms' =>[ 'collection'   => ["PUBLIC"],
                                        'batch'        => ["PUBLIC"],
                                        //'cultivarsyn'  => ["W SL"],
                                        //'ghost'        => ['A notyou'],
                                        '|'  // allows screen-login even if some tabs are ghosted
                                      ],
                           ],

                  // sub-tabs for collection
                  'colltabs'=> ['tabs' => [ 'germ' => ['label'=>'Germination Tests'],
                                            'packetlabels' => ['label'=>'Packet Labels'],
                                          ],
                                'perms' =>[ 'germ'   => ["PUBLIC"],
                                            'packetlabels'   => ["PUBLIC"],
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

$oApp = SEEDConfig_NewAppConsole( ['consoleConfig'=>$consoleConfig, 'sessPermsRequired'=>["W SLCollection", "A SL", "|"]] );
$oApp->kfdb->SetDebug(1);

SEEDPRG();


class MyConsole02TabSet extends Console02TabSet
{
    private $oApp;
    private $oW;

    function __construct( SEEDAppConsole $oApp )
    {
        global $consoleConfig;
        parent::__construct( $oApp->oC, $consoleConfig['TABSETS'] );

        $this->oApp = $oApp;
        $this->oSLDB = new SLDBCollection( $this->oApp );
    }

    function TabSet_main_collection_Init()         { $this->oW = new CollectionListForm( $this->oApp ); $this->oW->Init(); }
    function TabSet_main_collection_ControlDraw()  { return( $this->oW->ControlDraw() ); }
    function TabSet_main_collection_ContentDraw()  { return( $this->oW->ContentDraw() ); }

    function TabSet_main_batch_Init( Console02TabSet_TabInfo $oT ) { $this->oW = new CollectionBatchOps( $this->oApp, $oT->oSVA ); $this->oW->Init(); }
    function TabSet_main_batch_ControlDraw()       { return( $this->oW->ControlDraw() ); }
    function TabSet_main_batch_ContentDraw()       { return( $this->oW->ContentDraw() ); }
}

class CollectionListForm extends KeyframeUI_ListFormUI
{
    private $oSLDB;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oSLDB = new SLDBCollection( $oApp );

        $raConfig = [
            'sessNamespace' => "Collection",
            'cid'   => 'C',
            'kfrel' => $this->oSLDB->GetKfrel('IxAxPxS'),
            'KFCompParms' => ['raSEEDFormParms'=>['DSParms'=>['fn_DSPreStore'=> [$this,'dsPreStore']]]],

            'raListConfig' => [
                'bUse_key' => true,     // probably makes sense for KeyFrameUI to do this by default
                'cols' => [
                    [ 'label'=>"Species",   'col'=>"S_name_en",     'w'=>'20%' ],
                    [ 'label'=>"Cultivar",  'col'=>"P_name",        'w'=>'40%', 'trunc'=>50 ],
                    [ 'label'=>"Year",      'col'=>"A_x_d_harvest", 'w'=>'10%', 'align'=>'left' ],
                    [ 'label'=>"Lot",       'col'=>"inv_number",    'w'=>'10%', 'align'=>'left'],
                    [ 'label'=>"Location",  'col'=>"location",      'w'=>'10%', 'align'=>'left'],
                    [ 'label'=>"g",         'col'=>"g_weight",      'w'=>'10%', 'align'=>'right'],
                ],
                'fnRowTranslate' => [$this,"listRowTranslate"],
            ],

            'raSrchConfig' => [
                'filters' => [
                    ['label'=>'Species',           'col'=>'S.name_en'],
                    ['label'=>'Cultivar',          'col'=>'P.name'],
                    ['label'=>'Botanical name',    'col'=>'S.name_bot'],
                    ['label'=>'Original Acc name', 'col'=>'S.name_bot'],
                    ['label'=>'Acc #',             'col'=>'A._key'],
                    ['label'=>'Lot #',             'col'=>'I.inv_number'],
                    ['label'=>'Location',          'col'=>'I.location'],
                    ['label'=>'Notes',             'col'=>'A.notes'],
                ]
            ],

            'raFormConfig' => [ 'fnExpandTemplate'=>[$this,'collectionForm'] ],
        ];
        parent::__construct( $oApp, $raConfig );
    }

    function dsPreStore( Keyframe_DataStore $oDS )
    {
        return( true );
    }

    function listRowTranslate( $raRow )
    {
        if( ($kfrC = $this->oSLDB->GetKFR( "C", $raRow['fk_sl_collection'] )) ) {
            $raRow['inv_number'] = $kfrC->Value('inv_prefix')."-".$raRow['inv_number'];
        }

        $raRow['g_weight'] = ($raRow['g_weight'] ? $raRow['g_weight'] : '0')." g";

        if( !$raRow['A_x_d_harvest'] ) $raRow['A_x_d_harvest'] = $raRow['A_x_d_received'];

        if( $raRow['bDeAcc'] )  $raRow['P_name'] = "<span class='color:red'>{$raRow['P_name']} (Deaccessioned)</span>";

        return( $raRow );
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
           ."<style>
                #summary-table tr:nth-last-child(2) .weight {
                    border-bottom: 1px dotted black;
                }
                #summary-table td { padding-right:10px }
             </style>"
           ."<div class='container-fluid'><div class='row'>"
           ."<div class='col-sm-3'>".$this->drawSummary()."</div>"
           ."<div class='col-sm-9'>".$this->DrawList()."</div>"
           ."</div></div>"
           ."<div style='margin-top:15px'>"
               ."<h4 style='margin-left:30px'>Lot # {$this->oComp->oForm->Value('inv_number')}</h4>"
               .$this->drawCollectionSubtabs()
           ."</div>";
           //."<div style='margin-top:20px;padding:20px;border:2px solid #999'>".$this->DrawForm()."</div>";

        return( $s );
    }

    private function drawSummary()
    {
        $s = "";

        if( !$this->oComp->oForm->GetKey() ) {
            $s = "<p>Please select a seed lot from the list</p>";
            goto done;
        }

        $raLots = $this->oSLDB->GetList("I", "fk_sl_accession = {$this->oComp->oForm->Value("A__key")}");
        $totalWeight = array_sum(array_column($raLots, "g_weight"));

        $s = "<table id='summary-table'>
                <tr>
                    <td colspan='3'><strong>{$this->oComp->oForm->Value("S_name_en")} {$this->oComp->oForm->Value("P_name")}</strong> (cv {$this->oComp->oForm->Value("P__key")})</td>
                </tr><tr>
                    <td>Original name:</td>
                    <td colspan='2'>{$this->oComp->oForm->Value("P_name")}</td>
                </tr><tr>
                    <td>Batch:</td>
                    <td colspan='2'>{$this->oComp->oForm->Value("A_batch_id")}</td>
                </tr><tr>
                    <td>Grower/Source:</td>
                    <td colspan='2'>{$this->oComp->oForm->Value("A_x_member")}</td>
                </tr><tr>
                    <td>Harvest:</td>
                    <td colspan='2'>{$this->oComp->oForm->Value("A_x_d_harvest")}</td>
                </tr><tr>
                    <td>Received:</td>
                    <td colspan='2'>{$this->oComp->oForm->Value("A_x_d_received")}</td>
                </tr>"
            .SEEDCore_ArrayExpandRows( $raLots,
                                       "<tr><td>SoD-[[inv_number]]</td><td class='weight'>[[g_weight]] g</td><td class='location'>@ [[location]]</td></tr>" )
              ."<tr><td>&nbsp;</td><td>{$totalWeight} g</td></tr>"
            ."</table>";

        done:
        return( $s );
    }

    private function drawCollectionSubTabs()
    {
        $s = "";

        $oCTS = new Collection_Console02TabSet( $this->oApp, $this->oComp->oForm->Value("_key") );  // tell the subtabs the current selection in the list
        $s = $oCTS->TabSetDraw( 'colltabs' );

        return( $s );
    }

    function collectionForm( $oForm )
    {
        $sStats = "";
        $s = "|||TABLE( || class='slAdminForm' width='100%' border='0')"
            ."||| "
//            ."||| *psp*       || [[text:psp|size=30]]      || *Name EN*  || [[text:name_en|size=30]]  || *Name FR*  || [[text:name_fr|size=30]]"
//            ."||| *Botanical* || [[text:name_bot|size=30]] || *Index EN* || [[text:iname_en|size=30]] || *Index FR* || [[text:iname_fr|size=30]]"
//            ."||| *Category*  || [[text:category]] || *Family EN*|| [[text:family_en|size=30]]|| *Family FR*|| [[text:family_fr|size=30]]"
//            ."||| *Notes*     || {colspan='3'} ".$oForm->TextArea( "notes", array('width'=>'100%') )
//            ."<td colspan='2'>foo".$sStats."&nbsp;</td>"
            ."|||ENDTABLE"
            ."[[hiddenkey:]]"
            ."<INPUT type='submit' value='Save'>";


        return( $s );
    }
}

class Collection_Console02Tabset extends Console02TabSet
{
    private $oApp;
    private $oW;
    private $kInventory;    // the key of the sl_inventory currently selected in the list

    function __construct( SEEDAppConsole $oApp, $kInventory )
    {
        global $consoleConfig;
        parent::__construct( $oApp->oC, $consoleConfig['TABSETS'] );

        $this->oApp = $oApp;
        $this->kInventory = $kInventory;
    }

    function TabSet_colltabs_germ_Init()         { $this->oW = new CollectionTab_GerminationTests( $this->oApp, $this->kInventory ); $this->oW->Init(); }
    function TabSet_colltabs_germ_ControlDraw()  { return( $this->oW->ControlDraw() ); }
    function TabSet_colltabs_germ_ContentDraw()  { return( $this->oW->ContentDraw() ); }

    function TabSet_colltabs_packetlabels_Init()         { $this->oW = new CollectionTab_PacketLabels( $this->oApp, $this->kInventory ); $this->oW->Init(); }
    function TabSet_colltabs_packetlabels_ControlDraw()  { return( $this->oW->ControlDraw() ); }
    function TabSet_colltabs_packetlabels_ContentDraw()  { return( $this->oW->ContentDraw() ); }
}



$s = "[[TabSet:main]]";

$oCTS = new MyConsole02TabSet( $oApp );

$s = $oApp->oC->DrawConsole( $s, ['oTabSet'=>$oCTS] );


echo Console02Static::HTMLPage( SEEDCore_utf8_encode($s), "", 'EN',
                                ['consoleSkin'=>'green',
                                'raScriptFiles' => [$oApp->UrlW()."js/SEEDCore.js",$oApp->UrlW()."seedapp/sl/collection-batch.js"] ] );

echo "<script>SEEDCore_CleanBrowserAddress();</script>";

if( SEED_isLocal ) {
    echo "<script>collectionBatch.qUrl = 'http://localhost/~bob/seedsx/seeds.ca2/app/q/index.php';</script>";
}
