<?php

/* RosettaSEED app
 *
 * Copyright (c) 2014-2023 Seeds of Diversity Canada
 *
 */

include_once( SEEDCORE."console/console02.php" );
include_once( SEEDCORE."SEEDUI.php" );
include_once( SEEDROOT."Keyframe/KeyframeUI.php" );
include_once( SEEDLIB."sl/QServerRosetta.php" );

include_once( "rosetta_ts_cultivarsyn.php" );

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
                            'perms' =>[ 'species'      => ["W SLbob"],
                                        'cultivar'     => ["W SLbob"],
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

class RosettaCultivarListForm extends KeyframeUI_ListFormUI
{
    private $oSLDB;
    private $raCvOverview = [];

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oSLDB = new SLDBRosetta( $oApp );

        $raConfig = [
            'sessNamespace' => "RosettaCultivar",
            'cid'   => 'R',
            'kfrel' => $this->oSLDB->GetKfrel('PxS'),
            'KFCompParms' => ['raSEEDFormParms'=>['DSParms'=>['fn_DSPreStore'=> [$this,'dsPreStore'], 'fn_DSPreOp'=>[$this,'dsPreOp']]]],

            'raListConfig' => [
                'bUse_key' => true,     // probably makes sense for KeyFrameUI to do this by default
                'cols' => [
                    [ 'label'=>"Cultivar #",  'col'=>"_key",      'w'=>'10%'],
                    [ 'label'=>"Species",     'col'=>"S_psp",     'w'=>'30%'],
                    [ 'label'=>"Name",        'col'=>"name",      'w'=>'60%'],
                  //  [ 'label'=>"Index EN",  'col'=>"iname_en",  'w'=>120 ],
                  //  [ 'label'=>"Name FR",   'col'=>"name_fr",   'w'=>120 ], //, "colsel" => array("filter"=>"")),
                  //  [ 'label'=>"Index FR",  'col'=>"iname_fr",  'w'=>120 ],
                  //  [ 'label'=>"Botanical", 'col'=>"name_bot",  'w'=>120 ],
                  //  [ 'label'=>"Family EN", 'col'=>"family_en", 'w'=>120 ],
                  //  [ 'label'=>"Family FR", 'col'=>"family_fr", 'w'=>120 ],
                  //  [ 'label'=>"Category",  'col'=>"category",  'w'=>60, "colsel" => array("filter"=>"") ],
                ],
                // 'fnRowTranslate' => [$this,"listRowTranslate"],
            ],

            'raFormConfig' => [ 'fnExpandTemplate'=>[$this,'cultivarForm'] ],
        ];
        $raConfig['raSrchConfig']['filters'] = $raConfig['raListConfig']['cols'];     // conveniently the same format

        parent::__construct( $oApp, $raConfig );
    }

    function dsPreStore( Keyframe_DataStore $oDS )
    {
        if( !($kSp = $oDS->value('fk_sl_species')) ) {
            $this->oComp->oUI->SetErrMsg("Species cannot be blank");
            return( false );
        }

        //$oStats = new SLDB_Admin_Stats( $this->kfdb );
        //$raRef = $oStats->GetReferencesToPCV( $)

        $this->oComp->oUI->SetUserMsg("Saved");

        return( true );
    }

    function dsPreOp( Keyframe_DataStore $oDS, $op )
    {
        $bOk = false;   // if op=='d' return true if it's okay to delete

        if( $op != 'd' && $op != 'h' ) {
            $bOk = true;
            goto done;
        }

        /* N.B. The preOp method is called before Init() because the UI's _key is going to be the row below the deleted row.
         *      i.e. the UI state is not based on the deleted row.
         *      This means you have to get rosetta-cultivaroverview for the deleted row here.
         */
        $rQ = (new QServerRosetta($this->oApp))->Cmd('rosetta-cultivaroverview', ['kPcv'=>$oDS->Key()]);
        $raOverview = $rQ['bOk'] ? $rQ['raOut'] : [];

        // Don't delete a cultivar if it's referenced in a table (return false to disallow delete)
        // This function only tests for fk rows with _status==0 because deletion causes the cultivar row to be _status=1 so
        // referential integrity is preserved if all related rows are "deleted"
        if( @$raOverview['nTotal'] ) {
            $this->oComp->oUI->SetErrMsg( "Cannot delete this cultivar because it is referenced in another list (see references below)" );
        } else {
            $this->oComp->oUI->SetUserMsg( "Deleted {$oDS->Key()}: {$oDS->Value('S_psp')} {$oDS->Value('name')}" );
            $bOk = true;
        }

        done:
        return( $bOk );
    }

    function Init()
    {
        parent::Init();

        // If a cultivar is selected, get info about it (e.g. references in collection, sources, etc)
        if( $kPcv = $this->oComp->Get_kCurr() ) {
            $rQ = (new QServerRosetta($this->oApp))->Cmd('rosetta-cultivaroverview', ['kPcv'=>$kPcv]);
            $this->raCvOverview = $rQ['bOk'] ? $rQ['raOut'] : [];
        }
    }

    function ControlDraw()
    {
        return( $this->DrawSearch() );
    }

    function ContentDraw()
    {
        return( $this->ContentDraw_NewDelete() );
    }

    function cultivarForm( $oForm )
    {
        $sStats = "";
        if( $this->raCvOverview ) {
            $sStats =
                 "<strong>References: {$this->raCvOverview['nTotal']}</strong><br/><br/>"
                ."Seed Library accessions: {$this->raCvOverview['nAcc']}<br/>"
                ."Source list records: "
                    .($this->raCvOverview['nSrcCv1'] ? "PGRC, " : "")
                    .($this->raCvOverview['nSrcCv2'] ? "NPGS, " : "")
                    .("{$this->raCvOverview['nSrcCv3']} compan".($this->raCvOverview['nSrcCv3'] == 1 ? "y" : "ies"))."<br/>"
                ."Adoptions: {$this->raCvOverview['nAdopt']}<br/>"
                ."Profile Observations: {$this->raCvOverview['nDesc']}<br/>";

                 $sStats = "<div style='border:1px solid #aaa;padding:10px'>$sStats</div>";
//                 .($sSyn ? ("Synonyms: $sSyn<br/>") : "")
        }

        // get all species for dropdown
        $raSpOpts = ["-- Choose --"=>0];
        foreach( $this->oSLDB->GetList('S', "", ['sSortCol'=>'psp']) as $ra ) {
            if( $ra['psp'] ) {    // !psp is not allowed in Rosetta, but that rule can be broken though invalid
                $raSpOpts[$ra['psp']] = $ra['_key'];
            }
        }
        $sForm =
             "|||TABLE( || class='slAdminForm' width='100%' border='0')
              ||| *Species*       || ".SEEDFormBasic::DrawSelectCtrlFromOptsArray('sfRp_fk_sl_species','fk_sl_species',$raSpOpts, ['sSelAttrs'=>"style='width:90%'"])."
              ||| *Name*          || [[text:name|width:90%]]
              ||| *Description<br/> for seed<br/> packets* || [[textarea:packetLabel|width:90% nRows:3]]
              ||| *Notes* || [[textarea:notes|width:90% nRows:7]]
              |||ENDTABLE";


        $s = "<h4>".($this->oComp->IsNewRowState() ? "New" : "Edit")." Cultivar</h4>
              <div class='container-fluid'><div class='row'>
                  <div class='col-md-8'>{$sForm}</div>
                  <div class='col-md-4'>{$sStats}</div>
              </div></div>"
            ."[[hiddenkey:]]"
            ."<INPUT type='submit' value='Save'>";
        return( $s );
    }
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
        return( $this->ContentDraw_NewDelete() );

    }

    function speciesForm( $oForm )
    {
        $sStats = "";
        $s = "|||TABLE( || class='slAdminForm' width='100%' border='0')"
            ."||| *psp*       || [[text:psp|size=30]]      || *Name EN*  || [[text:name_en|size=30]]  || *Name FR*  || [[text:name_fr|size=30]]"
            ."||| *Botanical* || [[text:name_bot|size=30]] || *Index EN* || [[text:iname_en|size=30]] || *Index FR* || [[text:iname_fr|size=30]]"
            ."||| *Category*  || [[text:category]] || *Family EN*|| [[text:family_en|size=30]]|| *Family FR*|| [[text:family_fr|size=30]]"
            ."||| *Notes*     || {colspan='3'} ".$oForm->TextArea( "notes", array('width'=>'100%') )
            ."<td colspan='2'>".$sStats."&nbsp;</td>"
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
