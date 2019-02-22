<?php

/* msdlib
 *
 * Copyright (c) 2018-2019 Seeds of Diversity
 *
 * Support for MSD app-level code that shouldn't know about MSDCore but can't get what it needs from MSDQ
 */

require_once "msdcore.php";
require_once SEEDCORE."SEEDProblemSolver.php";


class MSDLib
{
    private $oMSDCore;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oMSDCore = new MSDCore( $oApp, array() );
    }

    function PermOfficeW()  { return( $this->oMSDCore->PermOfficeW() ); }
    function PermAdmin()    { return( $this->oMSDCore->PermAdmin() ); }

    function GetSpeciesNameFromKey( $kSp ) { return( $this->oMSDCore->GetKlugeSpeciesNameFromKey( $kSp ) ); }


    function AdminNormalizeStuff()
    {
        $s = "";

        if( !$this->PermAdmin() ) goto done;

        if( ($dbc = $this->oMSDCore->oApp->kfdb->CursorOpen( "SELECT mbr_id FROM seeds.sed_curr_growers" )) ) {
            $i = 0;
            while( $ra = $this->oMSDCore->oApp->kfdb->CursorFetch($dbc) ) {
                $sCond = "mbr_id='{$ra['mbr_id']}' AND _status='0' AND NOT bSkip AND NOT bDelete";

                $sql = "SELECT count(*) FROM seeds.SEEDBasket_Products P,seeds.SEEDBasket_ProdExtra PE "
                      ."WHERE P._key=PE.fk_SEEDBasket_Products AND P._status='0' AND P.product_type='seeds' AND "
                            ."P.uid_seller='{$ra['mbr_id']}' AND P.eStatus='ACTIVE' AND PE.k='category' ";

                $nTotal  = $this->oMSDCore->oApp->kfdb->Query1( $sql );
                $nFlower = $this->oMSDCore->oApp->kfdb->Query1( $sql."  AND v='flowers'" );
                $nFruit  = $this->oMSDCore->oApp->kfdb->Query1( $sql."  AND v='fruit'" );
                $nGrain  = $this->oMSDCore->oApp->kfdb->Query1( $sql."  AND v='grain'" );
                $nHerb   = $this->oMSDCore->oApp->kfdb->Query1( $sql."  AND v='herbs'" );
                $nTree   = $this->oMSDCore->oApp->kfdb->Query1( $sql."  AND v='trees'" );
                $nVeg    = $this->oMSDCore->oApp->kfdb->Query1( $sql."  AND v='vegetables'" );
                $nMisc   = $this->oMSDCore->oApp->kfdb->Query1( $sql."  AND v='misc'" );

                $this->oMSDCore->oApp->kfdb->Execute(
                        "UPDATE seeds.sed_curr_growers "
                       ."SET nTotal='$nTotal',nFlower='$nFlower',nFruit='$nFruit',"
                       ."nGrain='$nGrain',nHerb='$nHerb',nTree='$nTree',nVeg='$nVeg',nMisc='$nMisc' "
                       ."WHERE mbr_id='{$ra['mbr_id']}'");
                ++$i;
            }
            $s = "<p>Removed NULLs, trimmed and upper-cased strings. Updated offer counts for $i growers.</p>";
            $this->oMSDCore->oApp->kfdb->CursorClose($dbc);
        }


        done:
        return( $s );
    }

    function AdminIntegrityTests()
    {
        $s = "";

        $oSPS = new SEEDProblemSolver( $this->spsDefs(), ['kfdb'=>$this->oMSDCore->oApp->kfdb] );
        $ok = $oSPS->DoTests( 'integ_' );    // default second parm gives boolean return
        $s .= $oSPS->GetOutput();

        return( $s );
    }

    function AdminWorkflowTests()
    {
        $s = "";

        $oSPS = new SEEDProblemSolver( $this->spsDefs(), ['kfdb'=>$this->oMSDCore->oApp->kfdb] );
        $ok = $oSPS->DoTests( 'workflow_' );    // default second parm gives boolean return
        $s .= $oSPS->GetOutput();

        return( $s );
    }

    private function spsDefs()
    {
        $sGNoSkipDel = "G._status='0'   AND NOT G.bSkip  AND NOT G.bDelete";
        $sSActive    = "S._status='0'   AND S.eStatus='ACTIVE'";

        $sSNoSkipDel = "S._status='0'   AND NOT S.bSkip  AND NOT S.bDelete";
        $sS1NoSkipDel = "S1._status='0' AND NOT S1.bSkip AND NOT S1.bDelete";
        $sS2NoSkipDel = "S2._status='0' AND NOT S2.bSkip AND NOT S2.bDelete";
        $yearCurrent = $this->oMSDCore->GetCurrYear();

        return( [
            /* Hard structural integrity tests
             */
            'integ_gmbr_id_unique' =>
                array( 'title' => "Check for duplicate grower ids in sed_curr_growers",
                       'testType' => 'rows0',
                       'failLabel' => "Grower ids duplicated",
                       'failShowRow' => "mbr_id=[[mbr_id]]",
                       'testSql' =>
                           "SELECT G1.mbr_id as mbr_id FROM seeds.sed_curr_growers G1,seeds.sed_curr_growers G2 "
                          ."WHERE G1.mbr_id=G2.mbr_id AND G1._key<G2._key",
                     ),

            'integ_gmbr_code_notblank' =>
                array( 'title' => "Check for blank grower codes in sed_curr_growers",
                       'testType' => 'rows0',
                       'failLabel' => "Grower codes blank",
                       'failShowRow' => "mbr_id=[[mbr_id]]",
                       'testSql' =>
                           "SELECT mbr_id FROM seeds.sed_curr_growers WHERE mbr_code='' OR mbr_code IS NULL",
                     ),

            'integ_gmbr_code_unique1' =>
                array( 'title' => "Check for duplicate grower codes in sed_curr_growers",
                       'testType' => 'rows0',
                       'failLabel' => "Grower codes duplicated",
                       'failShowRow' => "mbr_code=[[mbr_code]]",
                       'testSql' =>
                           "SELECT G1.mbr_code as mbr_code FROM seeds.sed_curr_growers G1,seeds.sed_curr_growers G2 "
                          ."WHERE G1.mbr_code=G2.mbr_code AND G1._key<G2._key",
                     ),

            'integ_grower_code_unique2' =>
                array( 'title' => "Check for grower codes that have changed from previous years",
                       'testType' => 'rows0',
                       'failLabel' => "Warning: Grower codes changed",
                       'failShowRow' => "Member [[mbr_id]] was [[G2_mbr_code]] in [[G2_year]], but is now [[G_mbr_code]]",
                       'bNonFatal' => true,
                       'testSql' =>
                           "SELECT G.mbr_id as mbr_id,G.year as G_year,G2.year as G2_year,G.mbr_code as G_mbr_code,G2.mbr_code as G2_mbr_code "
                          ."FROM seeds.sed_curr_growers G, seeds.sed_growers G2 "
                          ."WHERE (G.mbr_id=G2.mbr_id) AND G.mbr_code <> G2.mbr_code ORDER BY G.mbr_id",
                     ),

            'integ_grower_code_badly_reused' =>
                array( 'title' => "Check for reused grower codes that are the same as someone else's",
                       'testType' => 'rows0',
                       'failLabel' => "Warning: Grower codes same as someone else's",
                       'failShowRow' => "[[mc]] : [[mid1]] [[fn1]] [[ln1]] ([[y1]]) and [[mid2]] [[fn2]] [[ln2]] ([[y2]])",
                       'bNonFatal' => true,
                       'testSql' =>
                           "SELECT G.mbr_code as mc, G.mbr_id as mid1, G2.mbr_id as mid2, 'current' as y1, G2.year as y2, M1.firstname as fn1,M1.lastname as ln1,M2.firstname as fn2,M2.lastname as ln2 "
                              ."FROM seeds.sed_curr_growers G, seeds.sed_growers G2, seeds2.mbr_contacts M1, seeds2.mbr_contacts M2 "
                              ."WHERE G.mbr_code=G2.mbr_code AND G.mbr_id <> G2.mbr_id AND M1._key=G.mbr_id AND M2._key=G2.mbr_id"
                          ." UNION "
                          ."SELECT G.mbr_code as mc, G.mbr_id as mid1, G2.mbr_id as mid2, 'current' as y1, 'current' as y2, M1.firstname as fn1,M1.lastname as ln1,M2.firstname as fn2,M2.lastname as ln2 "
                              ."FROM seeds.sed_curr_growers G, seeds.sed_curr_growers G2, seeds2.mbr_contacts M1, seeds2.mbr_contacts M2 "
                              ."WHERE G.mbr_code=G2.mbr_code AND G.mbr_id <> G2.mbr_id AND M1._key=G.mbr_id AND M2._key=G2.mbr_id ORDER BY 1",
                     ),

            'integ_gmbr_in_contacts' =>
                array( 'title' => "Check that growers are known in mbr_contacts",
                       'testType' => 'rows0',
                       'failLabel' => "Growers are not in mbr_contacts",
                       'failShowRow' => "mbr_id=[[mbr_id]]",
                       'testSql' =>
                           "SELECT G.mbr_id as mbr_id FROM seeds.sed_curr_growers G LEFT JOIN seeds2.mbr_contacts M "
                          ."ON (G.mbr_id=M._key) WHERE M._key IS NULL OR G.mbr_id=0 OR M._status<>0",
                     ),

            'integ_seeds_orphaned' =>
                array( 'title' => "Check for orphaned seeds",
                       'testType' => 'rows0',
                       'failLabel' => "Seeds have no grower",
                       'failShowRow' => "kSeed [[kS]] : mbr_id=[[mbr_id]], [[cat]] - [[type]] - [[var]]",
                       'testSql' =>
/*                           "SELECT S._key as kS, S.mbr_id as mbr_id, S.category as cat, S.type as type, S.variety as var "
                          ."FROM seeds.sed_curr_seeds S LEFT JOIN seeds.sed_curr_growers G ON (S.mbr_id=G.mbr_id) "
                          ."WHERE S._status=0 AND (G.mbr_id IS NULL OR G._status<>0)",
*/
                           "SELECT P._key as kS,P.uid_seller as mbr_id "
                          ."FROM seeds.SEEDBasket_Products P LEFT JOIN seeds.sed_curr_growers G ON (P.uid_seller=G.mbr_id) "
                          ."WHERE P.product_type='seeds' AND P._status='0' AND (G.mbr_id IS NULL OR G._status<>0)",
                     ),

            /* Soft content integrity tests
             */
            // Do this test in winter before publish, not summer before data entry, because the summer procedure clears the flags
            // that this is requiring
            'workflow-winter_growers_not_done_skip_delete' =>
                array( 'title' => "Check for growers that are not Done, Skipped, or Deleted (Later fixes might not work as expected)",
                       'testType' => 'rows0',
                       'failLabel' => "Growers do not have finalized state",
                       'failShowRow' => "mbr_id [[m]] : [[mc]]",
                       'bNonFatal' => true,
                       'testSql' =>
                           "SELECT G.mbr_id as m,G.mbr_code as mc FROM seeds.sed_curr_growers G "
                          ."WHERE NOT (G.bDoneMbr OR G.bDoneOffice) AND $sGNoSkipDel",
                ),

            // Do these for winter and summer
            'workflow_grower_delete_with_nondelete_seeds' =>
                array( 'title' => "Check for deleted growers that have non-deleted seeds",
                       'testType' => 'rows0',
                       'failLabel' => "Deleted growers have non-deleted seeds (solution: delete the seeds)",
                       'failShowRow' => "mbr_id [[m]] : [[mc]]",
                       'testSql' =>
                           "SELECT G.mbr_id as m,G.mbr_code as mc FROM seeds.sed_curr_growers G, seeds.SEEDBasket_Products S "
                          ."WHERE G.mbr_id=S.uid_seller AND S.product_type='seeds' AND G.bDelete AND S.eStatus<>'DELETED' GROUP BY G.mbr_id,G.mbr_code",
                       'remedyLabel' => "Delete seeds for deleted growers",
                       'remedySql' =>
                           "UPDATE seeds.SEEDBasket_Products S, seeds.sed_curr_growers G SET S.eStatus='DELETED' "
                          ."WHERE S.product_type='seeds' AND S.uid_seller=G.mbr_id AND G.bDelete",
                ),

            'workflow_grower_skip_with_nonskip_seeds' =>
                array( 'title' => "Check for skipped growers that have non-skipped seeds",
                       'testType' => 'rows0',
                       'failLabel' => "Skipped growers have non-skipped seeds (solution: skip the seeds)",
                       'failShowRow' => "mbr_id [[m]] : [[mc]]",
                       'testSql' =>
                           "SELECT G.mbr_id as m,G.mbr_code as mc FROM seeds.sed_curr_growers G, seeds.SEEDBasket_Products S "
                          ."WHERE G.mbr_id=S.uid_seller AND S.product_type='seeds' AND G.bSkip AND S.eStatus NOT IN ('INACTIVE','DELETED') GROUP BY G.mbr_id,G.mbr_code",
                       'remedyLabel' => "Skip seeds for skipped growers",
                       'remedySql' =>
                           "UPDATE seeds.SEEDBasket_Products S, seeds.sed_curr_growers G SET S.eStatus='INACTIVE' "
                          ."WHERE S.product_type='seeds' AND S.eStatus='ACTIVE' AND S.uid_seller=G.mbr_id AND G.bSkip",
                ),

            'workflow_grower_with_no_seeds'
                => array( 'title' => "Check for active growers who are offering no seeds",
                          'testType' => 'rows0',
                          'failLabel' => "Growers offering no seeds (solution: skip the growers)",
                          'failShowRow' => "mbr_id [[m]] : [[mc]]",
                          'testSql' =>
                              "SELECT G.mbr_id as m, G.mbr_code as mc FROM seeds.sed_curr_growers G WHERE $sGNoSkipDel "
                             ."AND NOT EXISTS (SELECT * FROM seeds.SEEDBasket_Products S WHERE G.mbr_id=S.uid_seller AND S.product_type='seeds' AND $sSActive)",
                          'remedyLabel' => "Skip active growers who have no active seeds",
                          'remedySql' =>
                              "UPDATE seeds.sed_curr_growers G SET G.bSkip=1 WHERE $sGNoSkipDel "
                             ."AND NOT EXISTS (SELECT * FROM seeds.SEEDBasket_Products S WHERE G.mbr_id=S.uid_seller AND S.product_type='seeds' AND $sSActive)",
                ),

            /* Check that the data is normalized
             */
            'data_mbrcode_8chars' =>
                array( 'title' => "Check for non-standard mbrcode format",
                       'testType' => 'rows0',
                       'failLabel' => "Mbr codes don't have 8 characters",
                       'failShowRow' => "[[m]] : [[mc]]",
                       'bNonFatal' => true,
                       'testSql' =>
                           "SELECT G.mbr_id as m,G.mbr_code as mc FROM seeds.sed_curr_growers G WHERE LENGTH(G.mbr_code)<>8 AND G.mbr_code<>'SODC/SDPC'",
                     ),

            'data_category_normal' =>
                array( 'title' => "Check for non-standard categories",
                       'testType' => 'rows0',
                       'failLabel' => "Seeds have non-standard categories",
                       'failShowRow' => "[[n]] seeds have category '[[category]]'",
                       'testSql' =>
                           "SELECT S.category as category,count(*) as n FROM seeds.sed_curr_seeds S "
                          ."WHERE $sSNoSkipDel AND "
                          ."category NOT IN ('FLOWERS AND WILDFLOWERS','FRUIT','GRAIN','HERBS AND MEDICINALS','TREES AND SHRUBS','VEGETABLES','MISC') "
                          ."GROUP BY category",
                     ),

            'data_type_empty' =>
                array( 'title' => "Check for blank Types (the only value that is not allowed)",
                       'testType' => 'rows0',
                       'failLabel' => "Seeds have blank Type",
                       'failShowRow' => "category=[[category]], variety=[[variety]]",
                       'testSql' =>
                           "SELECT S.category as category,S.variety as variety FROM seeds.sed_curr_seeds S WHERE S.type='' and $sSNoSkipDel",
                     ),

            'data_year_growers' =>
                array( 'title' => "Check for current year in all growers not skipped or deleted",
                       'testType' => 'n0',
                       'failLabel' => "[[n]] rows in sed_curr_growers that are neither bSkip nor bDelete, but don't have the current year",
                       'testSql' =>
                           "SELECT count(*) FROM seeds.sed_curr_growers G WHERE G.year<>'$yearCurrent' AND $sGNoSkipDel",
                       'remedyLabel' => "Set current year for growers",
                       'remedySql' =>
                           "UPDATE seeds.sed_curr_growers G SET G.year='$yearCurrent' WHERE $sGNoSkipDel",
                     ),

            'data_year_seeds' =>
                array( 'title' => "Check for current year in all seeds not skipped or deleted",
                       'testType' => 'n0',
                       'failLabel' => "[[n]] rows in sed_curr_seeds that are neither bSkip nor bDelete, but don't have the current year",
                       'testSql' =>
                           "SELECT count(*) FROM seeds.sed_curr_seeds S WHERE S.year<>'$yearCurrent' AND $sSNoSkipDel",
                       'remedyLabel' => "Set current year for seeds",
                       'remedySql' =>
                           "UPDATE seeds.sed_curr_seeds S SET S.year='$yearCurrent' WHERE $sSNoSkipDel",
                     ),

            'data_count_nTotal' =>
                array( 'title' => "Check that the grower seed-count totals equal sums of category counts",
                       'testType' => 'rows0',
                       'failLabel' => "Grower total count does not match sum of Flower,Fruit,Grain,Herb,Tree,Veg,Misc counts",
                       'failShowRow' => "[[m]] : [[mc]]",
                       'testSql' =>
                           "SELECT G.mbr_id as m, G.mbr_code as mc FROM seeds.sed_curr_growers G WHERE $sGNoSkipDel AND "
                          ."G.nTotal <> G.nFlower + G.nFruit + G.nGrain + G.nHerb + G.nTree + G.nVeg + G.nMisc",
                     ),

            'data_count_seeds' =>
                array( 'title' => "Check that the grower seed-count totals equal the number of seed listings",
                       'testType' => 'rows0',
                       'failLabel' => "Grower total count does not match number of seeds offered",
                       'failShowRow' => "[[m]] : [[mc]] does not really have [[nTotal]] active seed listings",
                       'testSql' =>
                           "SELECT G.mbr_id as m, G.mbr_code as mc, G.nTotal as nTotal FROM seeds.sed_curr_growers G "
                              ."WHERE $sGNoSkipDel AND "
                              ."G.nTotal <> (SELECT count(*) FROM seeds.sed_curr_seeds S WHERE S.mbr_id=G.mbr_id AND $sSNoSkipDel)",
                     ),

            'data_count_sumsGandS' =>
                array( 'title' => "Check that sum of grower seed-count totals equals the total number of seeds listed",
                       'testType' => 'n0',
                       'failLabel' => "Sum of grower totals - count of seeds = [[n]]",
                       'testSql' =>
                           "SELECT (SELECT sum(G.nTotal) FROM seeds.sed_curr_growers G WHERE $sGNoSkipDel) "
                              ." - (SELECT count(*) FROM seeds.sed_curr_seeds S WHERE $sSNoSkipDel) as n",
                     ),


            /* Check for duplicated entries (not fatal since this often happens legitimately)
             */
            'content_dup_types' =>
                array( 'title' => "Check for duplicate Types in different Categories",
                       'testType' => 'rows0',
                       'failLabel' => "Warning: Types duplicated in different Categories",
                       'failShowRow' => "Type [[t]] is in [[c1]] and [[c2]]",
                       'bNonFatal' => true,
                       'testSql' =>
                           "SELECT S1.type as t,S1.category as c1,S2.category as c2 "
                              ."FROM seeds.sed_curr_seeds S1,seeds.sed_curr_seeds S2 "
                              ."WHERE S1.type=S2.type AND S1.category<>S2.category "
                              ."AND $sS1NoSkipDel AND $sS2NoSkipDel GROUP BY 1,2,3 ORDER BY 1,2,3",
                     ),

            'content_dup_var_per_grower' =>
                array( 'title' => "Check for duplicate Type/Varieties from the same grower",
                       'testType' => 'rows0',
                       'failLabel' => "Warning: Varieties duplicated per grower",
                       'failShowRow' => "[[mc]] ([[m]]) has duplicate [[t]] - [[v]] : keys [[ks1]] and [[ks2]]",
                       'bNonFatal' => true,
                       'testSql' =>
                           "SELECT G.mbr_id as m,G.mbr_code as mc,S1.type as t,S1.variety as v,S1._key as ks1,S2._key as ks2 "
                              ."FROM seeds.sed_curr_seeds S1,seeds.sed_curr_seeds S2,seeds.sed_curr_growers G "
                              ."WHERE (G.mbr_id=S1.mbr_id) AND (S1.mbr_id=S2.mbr_id) AND "
                              ."S1._key<S2._key AND S1.type=S2.type AND S1.variety=S2.variety "
                              ."AND $sS1NoSkipDel AND $sS2NoSkipDel ORDER BY 2,3,4",
                     ),

            'content_dup_var_by_type' =>
                array( 'title' => "Check for duplicate Varieties in different Types",
                       'testType' => 'rows0',
                       'failLabel' => "Warning: Varieties duplicated in different Types",
                       'failShowFn' => 'fnContentDupVarByType',//array($this,"fnContentDupVarByType"),     use global function until this function is a method
                       'bNonFatal' => true,
                       'testSql' =>
                           "SELECT S1.variety AS v,S1.type AS t1,S2.type AS t2 FROM seeds.sed_curr_seeds S1,seeds.sed_curr_seeds S2 "
                          ."WHERE S1.variety=S2.variety AND S1.type<>S2.type "
                          ."AND S1.variety NOT IN ('','COMMON','ANNUAL','MIXED','SINGLE') "
                          ."AND S1.variety NOT LIKE '%UNKNOWN%' "
                          ."AND $sS1NoSkipDel AND $sS2NoSkipDel ORDER BY 1,2,3",
                     ),

            /* Check for growers and seeds that have been bDeleted but not actually deleted (though it actually just sets _status=1)
             */
            'delete_old_seeds' =>
                array( 'title' => "Check for deleted seeds",
                       'testType' => 'rows0',
                       'failLabel' => "Seeds deleted but not kfr-deleted",
                       'failShowRow' => "kSeed [[_key]] : mbr_id=[[mbr_id]], [[category]] - [[type]] - [[variety]]",
                       'testSql' => "SELECT _key,mbr_id,category,type,variety FROM seeds.sed_curr_seeds WHERE bDelete AND _status=0 ORDER BY mbr_id,category,type,variety",
                       'remedyLabel' => 'Kfr-delete all deleted seeds',
                       'remedySql' => "UPDATE seeds.sed_curr_seeds SET _status=1 WHERE bDelete"
                     ),

            'delete_old_growers' =>
                array( 'title' => "Check for deleted growers",
                       'testType' => 'rows0',
                       'failLabel' => "Growers deleted but not kfr-deleted",
                       'failShowRow' => "Grower [[mc]] ([[m]])",
                       'testSql' => "SELECT mbr_code as mc, mbr_id as m FROM seeds.sed_curr_growers WHERE bDelete AND _status=0 ORDER BY mbr_code",
                       'remedyLabel' => 'Kfr-delete all deleted growers',
                       'remedySql' => "UPDATE seeds.sed_curr_growers SET _status=1 WHERE bDelete"
                     ),

            /* Purge the records that have been set to _status=1
             */
            'purge_deleted_seeds' =>
                array( 'title' => "Check for deleted seeds",
                       'testType' => 'n0',
                       'failLabel' => "[[n]] seed records are at _status=1 ready to purge",
                       'testSql' => "SELECT count(*) FROM seeds.sed_curr_seeds WHERE _status=1",
                       'remedyLabel' => 'Purge all deleted seed records',
                       'remedySql' => "DELETE FROM seeds.sed_curr_seeds WHERE _status=1"
                     ),

            'purge_deleted_growers' =>
                array( 'title' => "Check for deleted growers",
                       'testType' => 'n0',
                       'failLabel' => "[[n]] grower records are at _status=1 ready to purge",
                       'testSql' => "SELECT count(*) FROM seeds.sed_curr_growers WHERE _status=1",
                       'remedyLabel' => 'Purge all deleted grower records',
                       'remedySql' => "DELETE FROM seeds.sed_curr_growers WHERE _status=1"
                     ),

            /* Clear the workflow flags for a new data entry session
             */
            'clearflags_bDone' =>
                array( 'title' => "Check flags clear - bDone,bDoneMbr,bDoneOffice",
                       'testType' => 'n0',
                       'failLabel' => "[[n]] grower records have bDone, bDoneMbr, or bDoneOffice flag set",
                       'testSql' => "SELECT count(*) FROM seeds.sed_curr_growers WHERE bDone OR bDoneMbr OR bDoneOffice",
                       'remedyLabel' => 'Clear grower.bDone,bDoneMbr,bDoneOffice',
                       'remedySql' => "UPDATE seeds.sed_curr_growers SET bDone=0,bDoneMbr=0,bDoneOffice=0"
                     ),
            'clearflags_bChanged_growers' =>
                array( 'title' => "Check flags clear - bChanged for growers",
                       'testType' => 'n0',
                       'failLabel' => "[[n]] grower records have bChanged flag",
                       'testSql' => "SELECT count(*) FROM seeds.sed_curr_growers WHERE bChanged",
                       'remedyLabel' => 'Clear curr_grower.bChanged',
                       'remedySql' => "UPDATE seeds.sed_curr_growers SET bChanged=0"
                     ),
            'clearflags_bChanged_seeds' =>
                array( 'title' => "Check flags clear - bChanged for seeds",
                       'testType' => 'n0',
                       'failLabel' => "[[n]] seeds records have bChanged flag",
                       'testSql' => "SELECT count(*) FROM seeds.sed_curr_seeds WHERE bChanged",
                       'remedyLabel' => 'Clear curr_seeds.bChanged',
                       'remedySql' => "UPDATE seeds.sed_curr_seeds SET bChanged=0"
                     ),

        ] );
    }
}
