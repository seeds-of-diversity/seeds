<?php

/* RosettaSEED Species tab
 *
 * Copyright (c) 2014-2024 Seeds of Diversity Canada
 *
 */

class RosettaSpeciesListForm extends KeyframeUI_ListFormUI
{
    private $oSLDB;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oSLDB = new SLDBRosetta( $oApp );

        $cols = [['label'=>"Sp #",      'col'=>"_key",      'w'=>30 ],
                 ['label'=>"psp",       'col'=>"psp",       'w'=>80 ],
                 ['label'=>"Name EN",   'col'=>"name_en",   'w'=>120 ],
                 ['label'=>"Index EN",  'col'=>"iname_en",  'w'=>120 ],
                 ['label'=>"Name FR",   'col'=>"name_fr",   'w'=>120 ], //, "colsel" => array("filter"=>"")),
                 ['label'=>"Index FR",  'col'=>"iname_fr",  'w'=>120 ],
                 ['label'=>"Botanical", 'col'=>"name_bot",  'w'=>120 ],
                 ['label'=>"Family EN", 'col'=>"family_en", 'w'=>120 ],
                 ['label'=>"Family FR", 'col'=>"family_fr", 'w'=>120 ],
                 ['label'=>"Category",  'col'=>"category",  'w'=>60, "colsel" => array("filter"=>"") ],
                ];
        $raConfig = $this->GetConfigTemplate(
            ['sessNamespace'        => 'RosettaSpecies',
             'cid'                  => 'R',
             'kfrel'                => $this->oSLDB->GetKfrel('S'),
             'raListConfig_cols'    => $cols,
             'raSrchConfig_filters' => $cols,
            ]
        );
        parent::__construct( $oApp, $raConfig );
    }

    function Init()         { parent::Init(); }
    function ControlDraw()  { return( $this->DrawSearch() ); }
    function ContentDraw()  { return( $this->ContentDraw_NewDelete() ); }

    function PreStore( Keyframe_DataStore $oDS )
    {
        return( true );
    }

    function FormTemplate( SEEDCoreForm $oForm )
    {
        list($sSyn,$sStats) = $this->getMeta();

        $s = $this->oComp->DrawFormEditLabel('Species')
            ."|||TABLE( || class='slAdminForm' width='100%' border='0')"
            ."||| *psp*       || [[text:psp|size=30]]      || *Name EN*  || [[text:name_en|size=30]]  || *Name FR*  || [[text:name_fr|size=30]]"
            ."||| *Botanical* || [[text:name_bot|size=30]] || *Index EN* || [[text:iname_en|size=30]] || *Index FR* || [[text:iname_fr|size=30]]"
            ."||| *Category*  || [[text:category]] || *Family EN*|| [[text:family_en|size=30]]|| *Family FR*|| [[text:family_fr|size=30]]"
            ."||| *Notes*     || {colspan='3'} ".$oForm->TextArea( "notes", array('width'=>'100%') )
            ."<td colspan='2'>{$sSyn}{$sStats}&nbsp;</td>"
            ."|||ENDTABLE"
            ."[[hiddenkey:]]"
            ."<INPUT type='submit' value='Save'>";
            return( $s );
    }

    private function getMeta()
    {
        $sSyn = $sStats = "";

        // If a cultivar is selected, get info about it (e.g. references in collection, sources, etc)
        if( ($kSp = $this->oComp->Get_kCurr()) ) {
            $rQ = (new QServerRosetta($this->oApp))->Cmd('rosetta-speciesoverview', ['kSp'=>$kSp]);
            if( $rQ['bOk'] ) {
                $raOverview = $rQ['raOut'];

                $sStats =
                     "<strong>References: {$raOverview['nTotal']}</strong><br/><br/>"
                    ."Cultivars: {$raOverview['nP']}<br/>"
                    ."Seed Library lots: {$raOverview['nI']}<br/>"
                    ."Source list records: "
                        .($raOverview['nSrcCv1'] ? "PGRC, " : "")
                        .($raOverview['nSrcCv2'] ? "NPGS, " : "")
                        .("{$raOverview['nSrcCv3']} compan".($raOverview['nSrcCv3'] == 1 ? "y" : "ies"))."<br/>"
                    ."Adoptions: {$raOverview['nAdopt']}<br/>"
                    ."Profile Observations: {$raOverview['nProfile']}<br/>";

                $sStats = "<div style='border:1px solid #aaa;padding:10px'>$sStats</div>";

                $sSyn = $rQ['raOut']['raSY']
                            ? ("<b>Also known as</b><div style='margin:0px 20px'>".SEEDCore_ArrayExpandRows($rQ['raOut']['raSY'],"[[name]]<br/>")."</div>")
                            : "";
                if( $sSyn ) $sSyn = "<div style='border:1px solid #aaa;padding:10px'>$sSyn</div>";
            }
        }

        return( [$sSyn,$sStats] );
    }
}
