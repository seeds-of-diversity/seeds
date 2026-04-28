<?php

/* sl_sources_rosetta.php
 *
 * Copyright 2015-2024 Seeds of Diversity Canada
 *
 * Implement references between sl_cv_sources_* and Rosetta species/cultivars
 */


class SLSourceCV_Build
/*********************
    Static methods for trying to build the cv-src index.
    This can be used on sl_cv_sources, sl_cv_sources_archive, and sl_tmp_cv_sources
 */
{
    function __construct() {}

    static function BuildAll( SEEDAppDB $oApp, $dbtable, $raParms = [] )
    /*******************************************************************
        Build fk_sl_sources keys as needed (do not clear).
        Clear and rebuild fk_sl_species keys.
        Clear and rebuild fk_sl_pcv keys.

        raParms:
            bIncludeOldSources : when indexing companies (only for sl_tmp_cv_sources) ignore sl_sources._status to include companies out of business

     */
    {
        self::checkTable( $oApp, $dbtable );

        $s = "<h4>Rebuilding the indexes for $dbtable</h4>";

        /* Company name is only stored in sl_tmp_cv_sources to save space in the larger tables.
         * That means you can't clear fk_sl_sources in the permanent tables and expect it to be rebuilt.
         */
        if( self::IsTmpTable($oApp, $dbtable) ) {
            self::BuildSourcesIndex( $oApp, $dbtable, @$raParms['bIncludeOldSources'] ? ['iStatusSrc'=>-1] : [] );
        }

        /* Delete the sp and cv index
         */
        self::ClearIndex( $oApp, $dbtable );

        /* Species: fill in all the fk_sl_species keys that we can find in RosettaSEED
         */
        self::BuildSpeciesIndex( $oApp, $dbtable, "" );

        /* Cultivars: fill in all the cv keys that we can find in RosettaSEED (for SrcCV records that have species keys now)
         */
        self::BuildCultivarIndex( $oApp, $dbtable, "" );

        /* Compute soundex and metaphone for unmatched names
         */
        self::BuildSoundIndex( $oApp, $dbtable );

        return( $s );
    }

    static function GetTableStatus( SEEDAppDB $oApp, string $dbtable )
    {
        $raOut = ['nRows'             => $oApp->kfdb->Query1("SELECT count(*) as c FROM $dbtable WHERE _status=0"),
                  'raSrcMatched'      => $oApp->kfdb->QueryRowsRA(
                                            "SELECT fk_sl_sources as k,S.name_en as company
                                             FROM {$dbtable}, {$oApp->DBName('seeds1')}.sl_sources S
                                             WHERE fk_sl_sources=S._key AND fk_sl_sources<>0
                                             GROUP BY fk_sl_sources,S.name_en"),
                  'raSrcNotMatched'   => $oApp->kfdb->QueryRowsRA(
                                            "SELECT company FROM {$dbtable}
                                             WHERE fk_sl_sources=0 GROUP BY company"),
                  'nSpMatched'        => $oApp->kfdb->Query1("SELECT count(*) FROM $dbtable WHERE _status=0 AND fk_sl_species GROUP BY fk_sl_species"),
                  'raSpNotMatched'    => $oApp->kfdb->QueryRowsRA1("SELECT osp FROM $dbtable WHERE _status=0 AND fk_sl_species=0 GROUP BY osp"),
                  'nRowsCvMatched'    => $oApp->kfdb->Query1("SELECT count(*) FROM $dbtable WHERE _status=0 AND fk_sl_pcv"),
                  'nRowsCvNotMatched' => $oApp->kfdb->Query1("SELECT count(*) FROM $dbtable WHERE _status=0 AND fk_sl_pcv=0"),
                  'sReport'           => ""
        ];

        $raOut['sReport'] .=
            "<p>Companies: ".count($raOut['raSrcMatched'])." matched; "
                            .count($raOut['raSrcNotMatched'])." not matched<br/>"
           .SEEDCore_ArrayExpandRows($raOut['raSrcNotMatched'], "<span style='color:red'>[[company]]</span><br/>")
           ."</p>"
           ."<p>Species: {$raOut['nSpMatched']} matched; "
                        .count($raOut['raSpNotMatched'])." not matched<br/>"
           .SEEDCore_ArrayExpandSeries($raOut['raSpNotMatched'], "<span style='color:red'>[[]]</span><br/>")
           ."<p>Cultivars: {$raOut['nRowsCvMatched']} rows matched; {$raOut['nRowsCvNotMatched']} rows not matched</p>";

        return($raOut);
    }

    static function ClearIndex( SEEDAppDB $oApp, $dbtable )
    /******************************************************
        Since we don't store company names in the permanent tables, we don't clear fk_sl_sources.
        If you want to clear and rebuild the sources index in sl_tmp_cv_sources, do it manually and use BuildSourcesIndex
     */
    {
        self::checkTable( $oApp, $dbtable );

        $oApp->kfdb->Execute( "UPDATE $dbtable SET fk_sl_species=0,fk_sl_pcv=0" );
        self::ClearSoundIndex( $oApp, $dbtable );
    }

    static function ClearSoundIndex( SEEDAppDB $oApp, $dbtable )
    /***********************************************************
        Only the sl_cv_sources table uses sound indices to try to work out synonyms.
        This could be extended to sl_cv_sources_archive if computing power allows.
     */
    {
        if( $dbtable == "{$oApp->DBName('seeds1')}.sl_cv_sources" ) {
            $oApp->kfdb->Execute( "UPDATE $dbtable SET sound_soundex='',sound_metaphone=''" );
        }
    }

    static function BuildSourcesIndex( SEEDAppDB $oApp, $dbtable, $raParms = [] )
    /****************************************************************************
        Fill in the fk_sl_sources keys for matching company names.

        Only used with sl_tmp_cv_sources because we don't store company names in the permanent tables.

        raParms:
            sCond      = sql condition
            iStatusSrc = filter on sl_sources._status : -1 means don't care
                         This is useful when building an index for old SrcCV data that includes companies that are out of business
     */
    {
        $sCond = @$raParms['sCond'];
        $iStatusSrc = intval(@$raParms['iStatusSrc']);

        self::IsTmpTable($oApp, $dbtable) or die( "Can't build sources for table $dbtable - only allowed for {$oApp->DBName('seeds1')}.sl_tmp_cv_sources" );

        $ok =
        $oApp->kfdb->Execute(
                        "UPDATE $dbtable SrcCV,{$oApp->DBName('seeds1')}.sl_sources Src "
                       ."SET SrcCV.fk_sl_sources=Src._key "
                       ."WHERE SrcCV._status=0 ".($iStatusSrc == -1 ? "" : "AND Src._status='$iStatusSrc' ")
                       ."AND SrcCV.fk_sl_sources=0 "
                       ."AND SrcCV.company<>'' "   // shouldn't happen
                       ."AND (SrcCV.company IN(Src.name_en, Src.name_fr))"
                       .($sCond ? " AND ($sCond)" : "" ) );
        return( $ok );
    }

    static function BuildSpeciesIndex( SEEDAppDB $oApp, $dbtable, $sCond = "" )
    /**************************************************************************
        Fill in the fk_sl_species keys for any matching names anywhere in RosettaSEED
     */
    {
        $debug = false;

        self::checkTable( $oApp, $dbtable );
$sCond="SrcCV.fk_sl_sources>=3";

        // fk_sl_species match sl_species
        $sql1 = "UPDATE $dbtable SrcCV,{$oApp->DBName('seeds1')}.sl_species S
                 SET SrcCV.fk_sl_species=S._key
                 WHERE SrcCV._status=0 AND S._status=0 AND
                       SrcCV.fk_sl_species=0 AND
                       SrcCV.osp<>'' AND
                       (SrcCV.osp IN(S.name_en, S.name_fr, S.name_bot, S.psp, S.iname_en, S.iname_fr))"
                      .($sCond ? " AND ($sCond)" : "" );

        // fk_sl_species match sl_species_syn
        $sql2 = "UPDATE $dbtable SrcCV,{$oApp->DBName('seeds1')}.sl_species_syn SY
                 SET SrcCV.fk_sl_species=SY.fk_sl_species
                 WHERE SrcCV._status=0 AND SY._status=0 AND
                       SrcCV.fk_sl_species=0 AND
                       SrcCV.osp<>'' AND
                       SrcCV.osp=SY.name"
                      .($sCond ? " AND ($sCond)" : "" );

        if( $debug ) {
            echo "<pre>$sql1</pre><pre>$sql2</pre>";
            $ok = true;
        } else {
            $ok = $oApp->kfdb->Execute($sql1) && $oApp->kfdb->Execute($sql2);
        }

        return( $ok );
    }


    static function BuildCultivarIndex( SEEDAppDB $oApp, $dbtable, $sCond = "" )
    /***************************************************************************
        Fill in the fk_sl_pcv keys for any matching names anywhere in RosettaSEED
     */
    {
        self::checkTable( $oApp, $dbtable );
$sCond .= ($sCond ? " AND " : "")."SrcCV.fk_sl_sources>=3";
        // Skip rows where fk_sl_species is 0:  these are either rows to be deleted (species is blank) or where species was not found in sl_species*
        // Also skip rows where cultivar is empty, because we don't support unnamed cultivars in Rosetta. Sorry, you can't search for those in the seed finder.
        $ok =
        // sl_pcv
        $oApp->kfdb->Execute(
                        "UPDATE $dbtable SrcCV,{$oApp->DBName('seeds1')}.sl_pcv P "
                       ."SET SrcCV.fk_sl_pcv=P._key "
                       ."WHERE SrcCV._status=0 AND P._status=0 "
                       ."AND SrcCV.fk_sl_species AND SrcCV.fk_sl_pcv=0 "
                       ."AND SrcCV.ocv<>'' AND P.name<>'' "
                       ."AND SrcCV.fk_sl_species=P.fk_sl_species "
                       ."AND SrcCV.ocv=P.name "
                       .($sCond ? " AND ($sCond)" : "" ) )

        &&
        // sl_pcv_syn
        $oApp->kfdb->Execute(
                        "UPDATE $dbtable SrcCV,{$oApp->DBName('seeds1')}.sl_pcv P,{$oApp->DBName('seeds1')}.sl_pcv_syn PY "
                       ."SET SrcCV.fk_sl_pcv=PY.fk_sl_pcv "
                       ."WHERE SrcCV._status=0 AND P._status=0 AND PY._status=0 "
                       ."AND SrcCV.fk_sl_species AND SrcCV.fk_sl_pcv=0 "
                       ."AND SrcCV.ocv<>'' AND PY.name<>'' "                // AND P.name<>''  -- not relevant
                       ."AND SrcCV.fk_sl_species=P.fk_sl_species "
                       ."AND P._key=PY.fk_sl_pcv "
                       ."AND SrcCV.ocv=PY.name "
                       .($sCond ? " AND ($sCond)" : "" ) );

        return( $ok );
    }

    /**
     * Index one fk_sl_pcv in sl_cv_sources. Removes broken references, adds new names, handles renames.
     */
    static function CultivarIndex_Single( SEEDAppDB $oApp, int $kPcv )
    {
        $dbtable = "{$oApp->DBName('seeds1')}.sl_cv_sources";

        /* New pname/sname     : second command indexes
         * Renamed pname/sname : first command dereferences old name, second command indexes new name
         * Deleted pname/sname : first command dereferences
         */
        $oApp->kfdb->Execute("UPDATE $dbtable SET fk_sl_pcv=0 WHERE fk_sl_pcv={$kPcv}");
        self::BuildCultivarIndex($oApp, $dbtable, "P._key={$kPcv}");  // constrain to this kPcv
    }

    static function BuildSoundIndex( SEEDAppDB $oApp, $dbtable )
    /***********************************************************
        Only the sl_cv_sources table uses sound indices to try to work out synonyms.
        This could be extended to sl_cv_sources_archive if computing power allows.
     */
    {
        if( $dbtable == "{$oApp->DBName('seeds1')}.sl_cv_sources" ) {
//        $kfdb->Execute( "UPDATE seeds_1.sl_cv_sources SET sound_soundex=soundex(ocv) WHERE sound_soundex=''" );
//        $kfdb->Execute( "UPDATE seeds_1.sl_cv_sources SET sound_metaphone=metaphone(ocv) WHERE sound_metaphone=''" );

//        $this->oW->kfdb->Execute( "UPDATE seeds_1.sl_pcv SET sound_soundex=soundex(name) WHERE sound_soundex=''" );
//        $this->oW->kfdb->Execute( "UPDATE seeds_1.sl_pcv SET sound_metaphone=metaphone(name) WHERE sound_metaphone=''" );
        }
    }

    static function IsTmpTable( SEEDAppDB $oApp, string $dbtable )
    {
        return($dbtable == "{$oApp->DBName('seeds1')}.sl_tmp_cv_sources");
    }

    static private function checkTable( SEEDAppDB $oApp, $dbtable )
    {
        in_array( $dbtable, ["{$oApp->DBName('seeds1')}.sl_cv_sources",
                             "{$oApp->DBName('seeds1')}.sl_cv_sources_archive",
                             "{$oApp->DBName('seeds1')}.sl_tmp_cv_sources"] )  or  die( "$dbtable not allowed" );
    }
}
