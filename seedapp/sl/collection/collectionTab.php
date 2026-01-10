<?php

include_once( "collectionTab_accession.php" );
include_once( "collectionTab_germtests.php" );
include_once( "collectionTab_packetlabels.php" );

class CollectionMain_NewMode
{
    private $oApp;
    private $oSLDB;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oSLDB = new SLDBCollection($oApp);
    }

    function Init()
    {
    }

    function ControlDraw()
    {
        $s = "";
        return( $s );
    }

    function ContentDraw()
    {
        $raTmplParms = [
            'fTemplates' => [SEEDAPP."templates/mycollection2.html", SEEDAPP."templates/mycollection2_js.html"],
            'sFormCid'   => 'Plain',
            //'raResolvers'=> array( array( 'fn'=>array($this,'ResolveTag'), 'raParms'=>array() ) ),
            'vars'       => []
        ];
        $oTmpl = SEEDTemplateMaker($raTmplParms);

        $s = "<h3>Add New Accession</h3>"
            .$oTmpl->ExpandTmpl('mycollStyle')
            .$oTmpl->ExpandTmpl('mycollJS', ['qUrl'=>$this->oApp->UrlQ(),
                                                                'qUrlOld'=>SITEROOT_URL."app/q/index.php"])
            .$oTmpl->ExpandTmpl('mycollConsolePage_AddNewLot', ['qUrl'=>$this->oApp->UrlQ(),
                                                                'qUrlOld'=>SITEROOT_URL."app/q/index.php"]);   // rosettaPCVSearch is still in the original Q code

        return($s);
    }
}


class CollectionMain_EditMode extends KeyframeUI_ListFormUI
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
        $s = "<div style='float:right'><form method='post'><button>Add new accession</button><input type='hidden' name='doAddNewAcc' value='1'/></form></div>"
            .$this->DrawSearch();

        return( $s );
    }

    function ContentDraw()
    {
        $sAccession = $sDrawList = $sLots = $sSubTabs = "";

        if( !$this->oComp->oForm->GetKey() ) {
            $sAccession = "<p>Please select a seed lot from the list</p>";
            goto draw;
        }

        $sDrawList = $this->DrawList();

        $raLots = $this->oSLDB->GetList("I", "fk_sl_accession = {$this->oComp->oForm->Value("A__key")}");

        $sAccession = $this->drawAccession($raLots);

        $sLots = SEEDCore_ArrayExpandSeries(
                        $raLots,
                        function ($k,$v,$parms) {
                            if( $v['_key'] == $this->oComp->oForm->GetKey() ) {
                                $cSelected = "lot-selected";
                                $sHref = "";
                            } else {
                                $cSelected = "";

                                // Try to look up the view offset of the lot in the List, so kCurr/iCurr can both be specified in the link. This avoids searching the whole View.
                                // If not found, link to kCurr>0,iCurr=-1 which forces a search for the row containing that key, which can be several seconds (same as the initial search).
                                // SearchForRowIfLoaded() returns $iRow==-1 if not found
                                list($iRow,$raRow) = $this->oComp->SearchForRowIfLoaded('_key', $v['_key']);
                                $sHref = $this->oComp->HRefForWidget(null, ['kCurr'=>$v['_key'], 'iCurr'=>$iRow]);
                            }
                            $sRet = "<div class='lotsummary $cSelected'>
                                    <p><span style='font-size:150%'>Lot # [[v|inv_number]]</span>&nbsp;&nbsp;&nbsp;[[v|location]]&nbsp;&nbsp;&nbsp;[[v|g_weight]] g</p>
                                    </div>";

                            return("<div class='col-md-3'>"
                                  .($sHref ? "<a $sHref style='text-decoration:none;color:black'>$sRet</a>" : $sRet)
                                  ."</div>" );
                        });

        $sSubTabs = $this->drawCollectionSubtabs();

        draw:

        $s = $this->DrawStyle()
           ."<style>
                #summary-table tr:nth-last-child(2) .weight {
                    border-bottom: 1px dotted black;
                }
                #summary-table td { padding-right:10px }
                .lotsummary { border:1px solid #aaa; border-radius:10px; padding:1em }
                .lot-selected { border:2px solid blue !important; background-color:#f0f0ff }
             </style>
             <div class='container-fluid'>
               <div class='row'>
                 <div class='col-sm-3'>{$sAccession}</div>
                 <div class='col-sm-9'>{$sDrawList}</div>
               </div>
               <div class='row' style='margin-top:1em'>{$sLots}</div>
             </div>
             <div style='margin-top:1em'>{$sSubTabs}</div>";
           //."<div style='margin-top:20px;padding:20px;border:2px solid #999'>".$this->DrawForm()."</div>";

        return( $s );
    }

    private function drawAccession( array $raLots )
    {
        $s = "";

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

    function TabSet_colltabs_accession_Init()     { $this->oW = new CollectionTab_Accession( $this->oApp, $this->kInventory ); $this->oW->Init(); }
    function TabSet_colltabs_germ_Init()          { $this->oW = new CollectionTab_GerminationTests( $this->oApp, $this->kInventory ); $this->oW->Init(); }
    function TabSet_colltabs_packetlabels_Init()  { $this->oW = new CollectionTab_PacketLabels( $this->oApp, $this->kInventory ); $this->oW->Init(); }

    function TabSetControlDraw( $tsid, $tabname ) { return( $this->oW->ControlDraw() ); }
    function TabSetContentDraw( $tsid, $tabname ) { return( $this->oW->ContentDraw() ); }
}
