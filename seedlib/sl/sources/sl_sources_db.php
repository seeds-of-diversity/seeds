<?php

class SLSourcesDBTest
/********************
    Db integrity tests for sl_cv_sources* tables
 */
{
    private $oApp;

    function __construct( SEEDAppDB $oApp )
    {
        $this->oApp = $oApp;
    }

    function TestForDuplicateSRCCV( $dbtable, $raParms = array() )
    /************************************************************
        Find rows with identical (fk_sl_sources,fk_sl_species/osp,fk_sl_pcv/ocv)

        raParms:
            year = limit to rows of the given year
     */
    {
        $raCond = [];

        // if year specified limit tables to that year
        if( ($y = @$raParms['year']) )  $raCond[] = "(A.year='".addslashes($y)."' AND A.year=B.year)";

        switch( $dbtable ) {
            case 'sl_cv_archive':
                // archive should only be tested per year (duplicates normally exist year-after-year)
                $raCond[] = "A.year=B.year";
                break;
            case 'sl_tmp_cv_sources':
                // specify the upload set
                $raCond[] = "(A.kUpload=B.kUpload AND A.kUpload='".addslashes($raParms['kUpload'])."')";
                break;
        }
        $raRows = $this->oApp->kfdb->QueryRowsRA(
                "SELECT A._key as A__key,B._key as B__key,S.name_en as S_name_en,"
                      ."A.osp as A_osp,B.osp as B_osp,A.fk_sl_species as A_fk_sl_species,B.fk_sl_species as B_fk_sl_species,"
                      ."A.ocv as A_ocv,B.ocv as B_ocv,A.fk_sl_pcv as A_fk_sl_pcv,B.fk_sl_pcv as B_fk_sl_pcv,"
                      ."A.bOrganic as A_bOrganic,B.bOrganic as B_bOrganic,A.year as A_year,B.year as B_year,"
                      ."A.notes as A_notes,B.notes as B_notes " // A.bulk as A_bulk,B.bulk as B_bulk
               ."FROM $dbtable A, $dbtable B LEFT JOIN sl_sources S ON (S._key=B.fk_sl_sources) "
               ."WHERE A._key<B._key AND A.fk_sl_sources=B.fk_sl_sources "
                     ."AND ((A.osp<>'' AND A.osp=B.osp) OR (A.fk_sl_species<>'0' AND A.fk_sl_species=B.fk_sl_species)) "
                     ."AND ((A.ocv<>'' AND A.ocv=B.ocv) OR (A.fk_sl_pcv<>'0' AND A.fk_sl_pcv=B.fk_sl_pcv)) "
                     .(count($raCond) ? (' AND '.implode(' AND ',$raCond)) : "")
                     ."AND A.fk_sl_sources >=3 "
               ."ORDER BY S_name_en,A_year,A_osp,A_ocv");

        return( $raRows );
    }
}

class SLSourcesLib
{
    static function DrawSRCCVRow( $ra, $prefix = '', $raParms = array() )
    {
        $raTmp = $ra;

        // specify a prefix to customize the fields. e.g. prefix 'A_' will use A_year,A_osp,A_ocv,etc
        if( $prefix ) {
            foreach( ['_key','year','osp','ocv','bOrganic','notes'] as $f ) { $raTmp[$f] = $ra[$prefix.$f]; }   // 'bulk'
        }
        $s = SEEDCore_ArrayExpand( $raTmp,
                "<tr><td style='vertical-align:top;padding-right:5px'>[[_key]]</td>"
                   ."<td style='vertical-align:top;padding-right:5px'><em>[[S_name_en]]</em></td>"
                   ."<td style='vertical-align:top;padding-right:5px'>[[year]]</td>"
                   ."<td style='vertical-align:top;padding-right:5px'><strong>[[osp]] : [[ocv]]</strong> "
                       .($raTmp['bOrganic']?"<span style='color:green;font-size:small'>organic</span>":"")."</td>"
                   ."<td style='vertical-align:top;padding-right:5px'>[[notes]]</td>"
               ."</tr>" );
        return( $s );
    }

}