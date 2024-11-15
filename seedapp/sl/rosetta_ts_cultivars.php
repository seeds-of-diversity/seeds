<?php

/* RosettaSEED Cultivars tab
 *
 * Copyright (c) 2014-2024 Seeds of Diversity Canada
 *
 */

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
    function ContentDraw()  { return( $this->ContentDraw_NewDelete() ); }

    /* These are not called directly, but referenced in raConfig
     */
    function FormTemplate( SEEDCoreForm $oForm )
    {
        list($sSyn,$sStats) = $this->getMeta();

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

    private function getMeta()
    /*************************
        Get references and stats about the current pcv
     */
    {
        $sSyn = $sStats = "";

        // If a cultivar is selected, get info about it (e.g. references in collection, sources, etc)
        if( ($kPcv = $this->oComp->Get_kCurr()) ) {
            $rQ = (new QServerRosetta($this->oApp))->Cmd('rosetta-cultivaroverview', ['kPcv'=>$kPcv]);
            if( $rQ['bOk'] ) {
                $raCvOverview = $rQ['raOut'];

                $sStats =
                     "<strong>References: {$raCvOverview['nTotal']}</strong><br/><br/>"
                    ."Seed Library accessions: {$raCvOverview['nAcc']}<br/>"
                    ."Source list records: "
                        .($raCvOverview['nSrcCv1'] ? "PGRC, " : "")
                        .($raCvOverview['nSrcCv2'] ? "NPGS, " : "")
                        .("{$raCvOverview['nSrcCv3']} compan".($raCvOverview['nSrcCv3'] == 1 ? "y" : "ies"))."<br/>"
                    ."Adoptions: {$raCvOverview['nAdopt']}<br/>"
                    ."Profile Observations: {$raCvOverview['nDesc']}<br/>";

                $sStats = "<div style='border:1px solid #aaa;padding:10px'>$sStats</div>";

                $sSyn = $rQ['raOut']['raPY']
                            ? ("<b>Also known as</b><div style='margin:0px 20px'>".SEEDCore_ArrayExpandRows($rQ['raOut']['raPY'],"[[name]]<br/>")."</div>")
                            : "";
                if( $sSyn ) $sSyn = "<div style='border:1px solid #aaa;padding:10px'>$sSyn</div>";
            }
        }

        return( [$sSyn,$sStats] );
    }
}
