<?php


class SLSourceCV_Build
/*********************
    Static methods for trying to build the cv-src index.
    This can be used on sl_cv_sources, sl_cv_sources_archive, and sl_tmp_cv_sources
 */
{
    function __construct() {}

    static function BuildAll( KeyframeDatabase $kfdb, $dbtable, $raParms = array() )
    /*******************************************************************************
        Build fk_sl_sources keys as needed (do not clear).
        Clear and rebuild fk_sl_species keys.
        Clear and rebuild fk_sl_pcv keys.

        raParms:
            bIncludeOldSources : when indexing companies (only for sl_tmp_cv_sources) ignore sl_sources._status to include companies out of business

     */
    {
        self::checkTable( $dbtable );

        $s = "<h4>Rebuilding the indexes for $dbtable</h4>"
            ."<p style='margin-left:10px'>";

        $cAll = $kfdb->Query1( "SELECT count(*) as c FROM $dbtable WHERE _status='0'" );

        /* Company name is only stored in sl_tmp_cv_sources to save space in the larger tables.
         * That means you can't clear fk_sl_sources in the permanent tables and expect it to be rebuilt.
         */
        if( $dbtable == 'seeds.sl_tmp_cv_sources' ) {
            self::BuildSourcesIndex( $kfdb, $dbtable, @$raParms['bIncludeOldSources'] ? ['iStatusSrc'=>-1] : [] );
            $s .= "<p>Rebuilt source keys in upload table.</p>";
        }

        /* Delete the sp and cv index
         */
        $c = $kfdb->Query1( "SELECT count(*) as c FROM $dbtable WHERE _status='0' AND (fk_sl_species OR fk_sl_pcv)" );
        self::ClearIndex( $kfdb, $dbtable );
        $s .= "<p>Species/cultivar index deleted ($c entries)</p>";

        /* Species: fill in all the fk_sl_species keys that we can find in RosettaSEED
         */
        self::BuildSpeciesIndex( $kfdb, $dbtable, "" );
        $c = $kfdb->Query1( "SELECT count(*) as c FROM $dbtable WHERE _status='0' AND fk_sl_species" );
        $s .= "<p>Species index rebuilt ($c / $cAll)</p>";

        /* Cultivars: fill in all the cv keys that we can find in RosettaSEED (for SrcCV records that have species keys now)
         */
        self::BuildCultivarIndex( $kfdb, $dbtable, "" );
        $c = $kfdb->Query1( "SELECT count(*) as c FROM $dbtable WHERE _status='0' AND fk_sl_pcv" );
        $s .= "<p>Cultivar index rebuilt ($c / $cAll)</p>";

        /* Compute soundex and metaphone for unmatched names
         */
        self::BuildSoundIndex( $kfdb, $dbtable );

        return( $s );
    }

    static function ClearIndex( KeyframeDatabase $kfdb, $dbtable )
    /*************************************************************
        Since we don't store company names in the permanent tables, we don't clear fk_sl_sources.
        If you want to clear and rebuild the sources index in sl_tmp_cv_sources, do it manually and use BuildSourcesIndex
     */
    {
        self::checkTable( $dbtable );

        $kfdb->Execute( "UPDATE $dbtable SET fk_sl_species='0',fk_sl_pcv='0'" );
        self::ClearSoundIndex( $kfdb, $dbtable );
    }

    static function ClearSoundIndex( KeyframeDatabase $kfdb, $dbtable )
    /******************************************************************
        Only the sl_cv_sources table uses sound indices to try to work out synonyms.
        This could be extended to sl_cv_sources_archive if computing power allows.
     */
    {
        if( $dbtable == 'seeds.sl_cv_sources' ) {
            $kfdb->Execute( "UPDATE $dbtable SET sound_soundex='',sound_metaphone=''" );
        }
    }

    static function BuildSourcesIndex( KeyframeDatabase $kfdb, $dbtable, $raParms = array() )
    /****************************************************************************************
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

        //self::checkTable( $dbtable );
        ($dbtable == "seeds.sl_tmp_cv_sources") or die( "Can't build sources for table $dbtable - only allowed for seeds.sl_tmp_cv_sources" );

        $ok =
        $kfdb->Execute( "UPDATE $dbtable SrcCV,seeds.sl_sources Src "
                       ."SET SrcCV.fk_sl_sources=Src._key "
                       ."WHERE SrcCV._status='0' ".($iStatusSrc == -1 ? "" : "AND Src._status='$iStatusSrc' ")
                       ."AND SrcCV.fk_sl_sources='0' "
                       ."AND SrcCV.company<>'' "   // shouldn't happen
                       ."AND (SrcCV.company=Src.name_en OR SrcCV.company=Src.name_fr)"
                       .($sCond ? " AND ($sCond)" : "" ) );
        return( $ok );
    }


    static function BuildSpeciesIndex( KeyframeDatabase $kfdb, $dbtable, $sCond = "" )
    /*********************************************************************************
        Fill in the fk_sl_species keys for any matching names anywhere in RosettaSEED
     */
    {
        self::checkTable( $dbtable );

        $ok =
        // sl_species
        $kfdb->Execute( "UPDATE $dbtable SrcCV,seeds.sl_species S "
                       ."SET SrcCV.fk_sl_species=S._key "
                       ."WHERE SrcCV._status='0' AND S._status='0' "
                       ."AND SrcCV.fk_sl_species='0' "
                       ."AND SrcCV.osp<>'' "
                       ."AND (SrcCV.osp=S.name_en OR SrcCV.osp=S.name_fr OR SrcCV.osp=S.name_bot OR SrcCV.osp=S.psp OR "
                            ."SrcCV.osp=S.iname_en OR SrcCV.osp=S.iname_fr)"
                       .($sCond ? " AND ($sCond)" : "" ) )
        &&
        // sl_species_syn
        $kfdb->Execute( "UPDATE $dbtable SrcCV,seeds.sl_species_syn SY "
                       ."SET SrcCV.fk_sl_species=SY.fk_sl_species "
                       ."WHERE SrcCV._status='0' AND SY._status='0' "
                       ."AND SrcCV.fk_sl_species='0' "
                       ."AND SrcCV.osp<>'' "
                       ."AND SrcCV.osp=SY.name"
                       .($sCond ? " AND ($sCond)" : "" ) );

        return( $ok );
    }


    static function BuildCultivarIndex( KeyframeDatabase $kfdb, $dbtable, $sCond = "" )
    /**********************************************************************************
        Fill in the fk_sl_pcv keys for any matching names anywhere in RosettaSEED
     */
    {
        self::checkTable( $dbtable );

        // Skip rows where fk_sl_species is 0:  these are either rows to be deleted (species is blank) or where species was not found in sl_species*
        // Also skip rows where cultivar is empty, because we don't support unnamed cultivars in Rosetta. Sorry, you can't search for those in the seed finder.
        $ok =
        // sl_pcv
        $kfdb->Execute( "UPDATE $dbtable SrcCV,seeds.sl_pcv P "
                       ."SET SrcCV.fk_sl_pcv=P._key "
                       ."WHERE SrcCV._status='0' AND P._status='0' "
                       ."AND SrcCV.fk_sl_species AND SrcCV.fk_sl_pcv='0' "
                       ."AND SrcCV.ocv<>'' AND P.name<>'' "
                       ."AND SrcCV.fk_sl_species=P.fk_sl_species "
                       ."AND SrcCV.ocv=P.name "
                       .($sCond ? " AND ($sCond)" : "" ) )

        &&
        // sl_pcv_syn
        $kfdb->Execute( "UPDATE $dbtable SrcCV,seeds.sl_pcv P,seeds.sl_pcv_syn PY "
                       ."SET SrcCV.fk_sl_pcv=PY.fk_sl_pcv "
                       ."WHERE SrcCV._status='0' AND P._status='0' AND PY._status='0' "
                       ."AND SrcCV.fk_sl_species AND SrcCV.fk_sl_pcv='0' "
                       ."AND SrcCV.ocv<>'' AND PY.name<>'' "                // AND P.name<>''  -- not relevant
                       ."AND SrcCV.fk_sl_species=P.fk_sl_species "
                       ."AND P._key=PY.fk_sl_pcv "
                       ."AND SrcCV.ocv=PY.name "
                       .($sCond ? " AND ($sCond)" : "" ) );

        return( $ok );
    }

    static function BuildSoundIndex( KeyframeDatabase $kfdb, $dbtable )
    /******************************************************************
        Only the sl_cv_sources table uses sound indices to try to work out synonyms.
        This could be extended to sl_cv_sources_archive if computing power allows.
     */
    {
        if( $dbtable == 'seeds.sl_cv_sources' ) {
//        $kfdb->Execute( "UPDATE seeds.sl_cv_sources SET sound_soundex=soundex(ocv) WHERE sound_soundex=''" );
//        $kfdb->Execute( "UPDATE seeds.sl_cv_sources SET sound_metaphone=metaphone(ocv) WHERE sound_metaphone=''" );

//        $this->oW->kfdb->Execute( "UPDATE seeds.sl_pcv SET sound_soundex=soundex(name) WHERE sound_soundex=''" );
//        $this->oW->kfdb->Execute( "UPDATE seeds.sl_pcv SET sound_metaphone=metaphone(name) WHERE sound_metaphone=''" );
        }
    }

    static private function checkTable( $dbtable )
    {
        in_array( $dbtable, array("seeds.sl_cv_sources", "seeds.sl_cv_sources_archive", "seeds.sl_tmp_cv_sources") )  or  die( "$dbtable not allowed" );
    }
}

?>
