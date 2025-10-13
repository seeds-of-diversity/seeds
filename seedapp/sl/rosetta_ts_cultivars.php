<?php

/* RosettaSEED Cultivars tab
 *
 * Copyright (c) 2014-2024 Seeds of Diversity Canada
 *
 */

include_once(SEEDLIB."sl/sl_integrity.php");

class RosettaCultivarListForm extends KeyframeUI_ListFormUI
{
    private $oSLDB;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oSLDB = new SLDBRosetta( $oApp );

        $cols = [['label'=>"Cultivar #",  'col'=>"_key",  'srchcol'=>'P__key',  'w'=>'10%'],    // have to use srchcol because _key is ambiguous in search condition
                 ['label'=>"Species",     'col'=>"S_psp",                       'w'=>'30%'],
                 ['label'=>"Name",        'col'=>"name",                        'w'=>'60%'],
                ];
        $raConfig = $this->GetConfigTemplate(
            ['sessNamespace'        => 'RosettaCultivars',
             'cid'                  => 'R',
             'kfrel'                => $this->oSLDB->GetKfrel('PxS'),
             'raListConfig_cols'    => $cols,
             'raSrchConfig_filters' => $cols,
             'charsets'             => "HttpUtf8&DbLatin"
            ]
        );
        parent::__construct($oApp, $raConfig);
    }

    function Init()         { parent::Init(); }
    function ControlDraw()  { return( $this->DrawSearch() ); }
    function ContentDraw()
    {
        $s = "";

        $oCTS = new Rosetta_CultivarTabs_Console02TabSet( $this, $this->oApp, $this->oComp->oForm->ValueInt('_key') );  // tell the subtabs the current selection in the list

        $s .= $this->DrawStyle()
             ."<div class='content-upper-section'>{$this->DrawList()}</div>"
             .$this->cultivarInfo()
             .$oCTS->TabSetDraw('cultivartabs');

        return($s);
    }

    private function cultivarInfo()
    {
        $s = "";

        if( !$kPcv = $this->oComp->oForm->ValueInt('_key') )  goto done;

        $s .= "<div style='margin:1em 0'>Foo</div>";

        done:
        return($s);
    }



    /* These are not called directly, but referenced in raConfig
     */
    function FormTemplate( SEEDCoreForm $oForm )
    {
        // If a cultivar is selected, get info about it (e.g. references in collection, sources, etc)
        list($sSyn,$sStats) = ($kPcv = $this->oComp->Get_kCurr()) ? SLIntegrity::GetPCVReport($this->oApp, $kPcv) : ["",""];

        // get all species for dropdown
        $raSpOpts = ["-- Choose --"=>0];
        foreach( $this->oSLDB->GetList('S', "", ['sSortCol'=>'psp']) as $ra ) {
            if( $ra['psp'] ) {    // !psp is not allowed in Rosetta, but that rule can be broken though invalid
                $raSpOpts[$ra['psp']] = $ra['_key'];
            }
        }
        $sForm =
             "|||TABLE(width='10%'|width='40%'|width='50%' || class='slAdminForm' width='100%' border='0')
              ||| *Species*       || ".SEEDFormBasic::DrawSelectCtrlFromOptsArray('sfRp_fk_sl_species','fk_sl_species',$raSpOpts, ['sSelAttrs'=>"style='width:95%'"])."
              ||| *Name*          || [[text:name|width:95%]]
              ||| {colspan='2'} *Description for seed packets (write what you would put in a seed catalogue)*  || *Origin / History*
              ||| {colspan='2'} [[textarea:packetLabel|width:95% nRows:3]]                                     || {replaceWith width='50%'}[[textarea:originHistory|width:95% nRows:3]]
              ||| {colspan='2'} *Notes*
              ||| {colspan='2'} [[textarea:notes|width:95% nRows:7]]
              |||ENDTABLE";


        $s = $this->oComp->DrawFormEditLabel('Cultivar')
            ."<div class='container-fluid'><div class='row'>
                  <div class='col-md-9'>{$sForm}</div>
                  <div class='col-md-3'>{$sSyn}{$sStats}</div>
              </div></div>"
            ."[[hiddenkey:]]"
            ."<INPUT type='submit' value='Save'>";

        return( $s );
    }

    function PreStore( Keyframe_DataStore $oDS )
    {
        $ok = false;

        if( !($kSp = $oDS->value('fk_sl_species')) ) {
            $this->oComp->oUI->SetErrMsg("Species cannot be blank");
            goto done;
        }

        $ok = true;
        $this->oComp->oUI->SetUserMsg("Saved");

        done:
        return( $ok );
    }

    function PreOp( Keyframe_DataStore $oDS, string $op )
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
}


class Rosetta_CultivarTabs_Console02Tabset extends Console02TabSet
{
    private $oR;
    private $oApp;
    private $oW;
    private $kInventory;    // the key of the sl_inventory currently selected in the list

    function __construct( RosettaCultivarListForm $oR, $oApp, $kPcv )
    {
        global $consoleConfig;
        parent::__construct( $oApp->oC, $consoleConfig['TABSETS'] );

        $this->oR = $oR;
        $this->oApp = $oApp;
        $this->kInventory = $kPcv;
    }

    function TabSet_colltabs_germ_Init()         { $this->oW = new CollectionTab_GerminationTests( $this->oApp, $this->kInventory ); $this->oW->Init(); }
    function TabSet_colltabs_germ_ControlDraw()  { return( $this->oW->ControlDraw() ); }
    function TabSet_cultivartabs_edit_ContentDraw()  { return( $this->oR->Buttons_NewDeleteMsg()."<div class='content-form-container'>{$this->oR->DrawForm()}</div>" ); }  //return( $this->oW->ContentDraw() ); }

    function TabSet_colltabs_packetlabels_Init()         { $this->oW = new CollectionTab_PacketLabels( $this->oApp, $this->kInventory ); $this->oW->Init(); }
    function TabSet_colltabs_packetlabels_ControlDraw()  { return( $this->oW->ControlDraw() ); }
    function TabSet_colltabs_packetlabels_ContentDraw()  { return( $this->oW->ContentDraw() ); }
}

