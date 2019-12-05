<?php

include_once( "sl_sources_rosetta.php" );   // for rebuilding indexes

class SLSourcesCVUpload
/**********************
    Upload company / seedbank data from spreadsheets to sl_cv_sources

    Normal process:
        Clear the tmp table.
        Load tmp table from spreadsheet, validate it, compute difference with sl_cv_sources.
        Optionally archive sl_cv_sources rows that will be changed.
        Copy rows from tmp table to sl_cv_sources.

        At each stage, the differences are recomputed so the final state should be an intact tmp table with null ops.
 */
{
    // When overwriting sl_cv_sources with uploaded rows, specify what to do with existing rows not mentioned in the new data
    const ReplaceVerbatimRows = 1;       // don't delete unreferenced rows; only replace the sl_cv_sources rows given in the spreadsheet
    const ReplaceWholeCompanies = 2;     // delete all unreferenced rows from companies mentioned in tmpTable
    const ReplaceWholeCSCI = 3;          // delete and replace the entire CSCI (removes companies not mentioned in tmpTable)

    private $oApp;
    private $eReplace;
    private $kUpload;
    private $tmpTable = "seeds.sl_tmp_cv_sources";

    function __construct( SEEDAppSession $oApp, $eReplace, $kUpload = 0 )
    {
//    __construct( kUpload == 0 ) prepares the object for a potential Load(). Nothing else will work.
//    __construct( kUpload != 0 ) prepares the object to work with the rows in sl_tmp_cv_sources identified by kUpload.

        $this->oApp = $oApp;
        $this->eReplace = $eReplace;
        $this->kUpload = $kUpload;
    }

    private function uploadCond( $tableAlias = 'T.' )
    {
        return( $this->kUpload ? "{$tableAlias}kUpload='{$this->kUpload}'" : "1=1" );
    }

    function ClearTmpTable()
    {
        $this->oApp->kfdb->Execute( "DELETE FROM {$this->tmpTable} WHERE ".$this->uploadCond('') );
    }

    function LoadToTmpTable( $raRows )
    /*********************************
        Copy data from $raRows to sl_tmp_cv_sources

            $raRows must contain at least
                'k'        => copy of sl_cv_sources._key
                'company'  => copy of sl_sources.name_en
                'species'  => copy of sl_cv_sources.osp
                'cultivar' => copy of sl_cv_sources.ocv
                'organic'  => copy of sl_cv_sources.bOrganic (allows a variety of boolean ways to say 'yes')
                'bulk'     => copy of the bulk quantity notation
                'notes'    => copy of the notes that people use while editing the spreadsheet

            Optional columns:
                'year'
     */
    {
        $sOk = $sWarn = $sErr = "";
        $bOk = false;
//$this->oApp->kfdb->SetDebug(2);

// There's code here to handle multiple updates simultaneously. That means you don't want to drop and recreate the table all the time.
// Let's keep the multiple-update facility but not use it and just drop/create the table at the start of every update.
$this->oApp->kfdb->Execute( "DROP TABLE IF EXISTS {$this->tmpTable}" );
$this->oApp->kfdb->Execute( SLDB_Create::SEEDS_DB_TABLE_SL_TMP_CV_SOURCES );

        /* This number groups this upload's rows in the db table. It doesn't matter what the number is, as long as it's different from others in the kUpload column
         */
        $this->kUpload = $this->uniqueNumber();


        /* Copy the rows to a temporary table, alerting where rows have invalid blank content
         *     (A)  k && company && species            = existing row with possible changes
         *     (B) !k && company && species            = new row
         *
         *     (C)  k && !company && !species          = this means delete row k
         *     (D) !k && !company && !species          = ignore empty row
         *
         *     (E) company xor species                 = not allowed
         */
        $sqlRows = array();
        $nRow = 0;
        foreach( $raRows as $ra ) {
            $nRow++;

            $k = intval($ra['k']);
            $company = trim(addslashes($ra['company']));
            $species = trim(addslashes($ra['species']));
            $cultivar = trim(addslashes($ra['cultivar']));
            $organic = in_array( trim($ra['organic']), [1,'1','x','X','y','Y','yes','YES'] ) ? 1 : 0;
            $bulk = trim(addslashes($ra['bulk']));
            $notes = trim(addslashes($ra['notes']));
            $year = intval(@$ra['year']) or ($year = date("Y"));

            // (D) skip blank lines (but increment the nRow counter)
            if( !$k && !$company && !$species )  continue;

            // (E) all valid cases require both company+species or neither
            if( empty($company) xor empty($species) ) {
                $sWarn .= "Row ".($nRow+1)." has a blank "             // +1 because of the header row
                         .(empty($species) ? "species" : "company").", so it will be skipped.<br/>";
                continue;
            }

            // (A,B,C) copy to sl_tmp_cv_sources for processing
            $sqlRows[] = "($k,'$company','$species','$cultivar',$organic,'$bulk','$notes',$year,$this->kUpload,now(),0)";
        }
        $nRowsAffected = 0;
        if( count($sqlRows) ) {
            if( !$this->oApp->kfdb->Execute( "INSERT INTO {$this->tmpTable} (k,company,osp,ocv,organic,bulk,notes,year,kUpload,_created,_status) "
                                            ."VALUES ".implode(',', $sqlRows) ) )
            {
                $s1 = "Database error inserting : ".$this->oApp->kfdb->GetErrMsg();
                $this->oApp->oC->AddErrMsg( $s1 );
                $sErr .= $s1;
                goto done;
            }
            $nRowsAffected = $this->oApp->kfdb->GetAffectedRows();
        }

        $sOk .= "Uploaded $nRowsAffected rows from the spreadsheet.<br/>";

//Check for duplicates and fail if they exist
//Ignore rows where k!=0, company='' because those are for deletion

        $bOk = true;

        done:
        return( [$bOk,$sOk,$sWarn,$sErr] );
    }

    function ValidateTmpTable()
    /**************************
        Fill index columns, compute differences between tmp table and sl_cv_sources

        Validate data
            Companies must all be known and convertible to fk_sl_sources
            No duplicate (company,species,cultivar) allowed
            Warnings for unknown species and cultivars

        Row types admitted by Load():
            (A)  k && company && species            = existing row with possible changes
            (B) !k && company && species            = new row
            (C)  k && !company && !species          = this means delete row k
     */
    {
        $bOk = false;
//$this->oApp->kfdb->SetDebug(2);


        $condKUpload = $this->uploadCond();     // uses table alias "T."

        // Index company names.
        // Index species and cultivars using Rosetta
        SLSourceCV_Build::BuildAll( $this->oApp->kfdb, $this->tmpTable, [] );   // uploadCond()

        /* Compute Operations to perform on the rows
         *
         *  N = new:     tmp.k==0
         *  M = new2:    tmp.k<>0 but doesn't exist in sl_cv_sources; allows deleted SrcCv to be restored from spreadsheet with same keys
         *  U = update1: tmp.k<>0, tmp.fk_sl_sources<>0, some data and year changed
         *  V = update2: tmp.k<>0, tmp.fk_sl_sources<>0, some data changed but year is the same
         *  Y = year:    tmp.k<>0, tmp.fk_sl_sources<>0, only year changed
         *  - = same:    tmp.k<>0, tmp.fk_sl_sources<>0, data and year not changed
         *  . = same:    tmp.k<>0, tmp.fk_sl_sources<>0, data and year not changed, but tmp.k<>cvsrc.k (data entry error re key)
         *  D = delete1: tmp.k<>0, tmp.fk_sl_sources==0
         *  X = delete2: tmp.k is missing in the set of rows that should match sl_cv_sources rows
         *
         * The tests below are very stringent, assuming nothing, so outlying cases wind up "uncomputed" and flagged
         *
         * Archiving
         *     Rows are archived when their year changes or when they are deleted.
         *     (U V Y -) include all combinations of changes to data and year. Changes of year (U Y) trigger an archive.
         *     (V) does not trigger an archive.
         *     That way the archive contains only old rows no longer contained in current sl_cv_sources, and you can
         *     make current-year corrections without having to correct any archived copy too.
         */
        $condBase              = "($condKUpload AND T.fk_sl_sources<>'0')";     // all ops require this
        $condUpdateCase        = "($condBase AND C._key=T.k)";                  // all rows with matching key are potential updates
        // test if rows the same without/with considering somebody changed osp to a sl_syn
        $condDataBasicSame     = "(C.osp=T.osp                                              AND C.fk_sl_sources=T.fk_sl_sources AND C.ocv=T.ocv )";
        $condDataBasicSameFkSp = "(C.fk_sl_species=T.fk_sl_species AND T.fk_sl_species<>'0' AND C.fk_sl_sources=T.fk_sl_sources AND C.ocv=T.ocv )";
        $condDataSame          = "($condDataBasicSame AND C.bOrganic=T.organic AND C.bulk=T.bulk AND C.notes=T.notes)";

        // Clear old ops
        $this->oApp->kfdb->Execute( "UPDATE {$this->tmpTable} SET op='',op_data=0 WHERE ".$this->uploadCond('') );

        // First: any rows in the tmp table whose non-blank (fk_sl_sources, osp/fk_sl_species, ocv) are identical
        // to sl_cv_sources are deemed to be matches. If their keys are different, that is a mistake in data entry.
        // The Upload procedure should correct this (by copying the old keys to the tmp table) before re-computing the ops.
        // Note that this is the only place where C._key is conveniently known, so it is stored in op_data for later
        $this->oApp->kfdb->Execute( "UPDATE {$this->tmpTable} T,seeds.sl_cv_sources C SET T.op='.',T.op_data=C._key "
                                   ."WHERE $condBase AND "
                                         ."($condDataBasicSame OR $condDataBasicSameFkSp) AND C.fk_sl_sources>='3' "
                                         ."AND C._key<>T.k" );
        if( $this->oApp->kfdb->Query1("SELECT count(*) FROM {$this->tmpTable} T WHERE $condBase AND T.op='.'") ) {
            goto done;
        }

        // N (tmp.k==0)
        $this->oApp->kfdb->Execute( "UPDATE {$this->tmpTable} T SET T.op='N' WHERE $condBase AND T.k='0'" );

        // M (tmp.k<>0 but doesn't exist in sl_cv_sources)
        $this->oApp->kfdb->Execute( "UPDATE {$this->tmpTable} T LEFT JOIN seeds.sl_cv_sources C ON (T.k=C._key) "
                                    ."SET T.op='M' WHERE $condBase AND T.k<>0 AND C._key IS NULL" );

        // U (data and year changed)
        $this->oApp->kfdb->Execute( "UPDATE {$this->tmpTable} T,seeds.sl_cv_sources C SET T.op='U' "
                                   ."WHERE $condUpdateCase AND "
                                         ."NOT $condDataSame AND C.year<>T.year" );

        // V (data changed but year the same)
        $this->oApp->kfdb->Execute( "UPDATE {$this->tmpTable} T,seeds.sl_cv_sources C SET T.op='V' "
                                   ."WHERE $condUpdateCase AND "
                                         ."NOT $condDataSame AND C.year=T.year" );

        // Y (only the year changed)
        $this->oApp->kfdb->Execute( "UPDATE {$this->tmpTable} T,seeds.sl_cv_sources C SET T.op='Y' "
                                   ."WHERE $condUpdateCase AND "
                                         ."$condDataSame AND C.year<>T.year" );

        // - (perfect match on all columns including year)
        $this->oApp->kfdb->Execute( "UPDATE {$this->tmpTable} T,seeds.sl_cv_sources C SET T.op='-' "
                                   ."WHERE $condUpdateCase AND "
                                         ."$condDataSame AND C.year=T.year" );

        // D (company and osp are blank)
        $this->oApp->kfdb->Execute( "UPDATE {$this->tmpTable} T SET T.op='D' WHERE $condBase AND T.k<>'0' AND T.company='' AND T.osp=''" );

        // X (rows in sl_cv_sources don't exist in tmp) - implement this by adding them to tmp
        if( $this->eReplace != self::ReplaceVerbatimRows ) {
            $this->oApp->kfdb->Execute(
                "INSERT INTO {$this->tmpTable} (k,kUpload,op) "
                ."SELECT SRCCV._key,{$this->kUpload},'X' FROM seeds.sl_cv_sources SRCCV LEFT JOIN {$this->tmpTable} T2 "
                    ."ON SRCCV._key=T2.k WHERE T2.k IS NULL AND "
                    .($this->eReplace == self::ReplaceWholeCSCI
                        // if replacing all companies then delete all rows that are missing in tmpTable (except seed banks)
                        ? "SRCCV.fk_sl_sources >= '3'"
                        // if replacing specific companies then delete missing rows from those companies only
                        : ("SRCCV.fk_sl_sources IN (SELECT distinct(fk_sl_sources) FROM {$this->tmpTable} T WHERE $condBase)")) );
        }

        $bOk = true;

        done:
        return( $bOk );
    }

    function FixMatchingRowKeys()
    /****************************
        Sometimes the upload table can have (src,sp,cv) tuples that match rows in SrcCv but the keys are different.
        Data entry errors or recalculation of SrcCv keys can cause this.
        Especially if you upload and commit a spreadsheet, then upload it again, the k==0 rows will then be like updates instead of inserts.
     */
    {
        // Validate stores the "matching" SrcCv._key in op_data so it doesn't have to be found again (not easy to do)
        $this->oApp->kfdb->Execute( "UPDATE {$this->tmpTable} SET k=op_data WHERE op='.' AND ".$this->uploadCond('') );
    }

    function CalculateUploadReport()
    /*******************************
        Report on the current build status of seeds.sl_tmp_cv_sources

        If kUpload==0 just report on the whole table, assuming there is only one update happening and we're too disorganized to keep track anyway
     */
    {
        $dbtable = $this->tmpTable;
        $kfdb = $this->oApp->kfdb;
        $kUploadCond = $this->uploadCond('');    // don't use table prefix T.

        $raReport = array(
            'nRows'              => $kfdb->Query1( "SELECT count(*) FROM {$dbtable} WHERE $kUploadCond" ),
            'nRowsUncomputed'    => $kfdb->Query1( "SELECT count(*) FROM {$dbtable} WHERE $kUploadCond AND op=''" ),
            'nRowsSame'          => $kfdb->Query1( "SELECT count(*) FROM {$dbtable} WHERE $kUploadCond AND op='-'" ),
            'nRowsSameDiffKeys'  => $kfdb->Query1( "SELECT count(*) FROM {$dbtable} WHERE $kUploadCond AND op='.'" ),
            'nRowsN'             => $kfdb->Query1( "SELECT count(*) FROM {$dbtable} WHERE $kUploadCond AND op='N'" ),
            'nRowsU'             => $kfdb->Query1( "SELECT count(*) FROM {$dbtable} WHERE $kUploadCond AND op='U'" ),
            'nRowsV'             => $kfdb->Query1( "SELECT count(*) FROM {$dbtable} WHERE $kUploadCond AND op='V'" ),
            'nRowsY'             => $kfdb->Query1( "SELECT count(*) FROM {$dbtable} WHERE $kUploadCond AND op='Y'" ),
            'nRowsD1'            => $kfdb->Query1( "SELECT count(*) FROM {$dbtable} WHERE $kUploadCond AND op='D'" ),
            'nRowsD2'            => $kfdb->Query1( "SELECT count(*) FROM {$dbtable} WHERE $kUploadCond AND op='X'" ),
            'nDistinctCompanies' => $kfdb->Query1( "SELECT count(distinct fk_sl_sources) FROM {$dbtable} WHERE $kUploadCond "
                                                       ."AND fk_sl_sources<>'0'" ),
            'nDistinctSpKeys'    => $kfdb->Query1( "SELECT count(distinct fk_sl_species) FROM {$dbtable} WHERE $kUploadCond "
                                                       ."AND fk_sl_species<>'0'" ),
            'nDistinctCvKeys'    => $kfdb->Query1( "SELECT count(distinct fk_sl_pcv) FROM {$dbtable} WHERE $kUploadCond "
                                                       ."AND fk_sl_pcv<>'0'" ),

            // rows with unmatched companies, ignoring those where species is blank or company is blank (those are rows to be deleted)
            'raUnknownCompanies' => $kfdb->QueryRowsRA( "SELECT company FROM {$dbtable} WHERE $kUploadCond "
                                                       ."AND fk_sl_sources='0' AND osp<>'' AND company<>'' GROUP BY 1 ORDER BY 1" ),
            // rows with unmatched species, ignoring those where species is blank or company is blank (those are rows to be deleted)
            'raUnknownSpecies'   => $kfdb->QueryRowsRA( "SELECT osp FROM {$dbtable} WHERE $kUploadCond "
                                                       ."AND fk_sl_species='0' AND osp<>'' AND company<>'' GROUP BY 1 ORDER BY 1" ),
            // rows with unmatched cultivars, not counting those where species was unmatched (reported above and prerequisite)
            'raUnknownCultivars' => $kfdb->QueryRowsRA( "SELECT osp,ocv FROM {$dbtable} WHERE $kUploadCond "
                                                       ."AND fk_sl_pcv='0' AND fk_sl_species<>'0' GROUP BY 1,2 ORDER BY 1,2" ),

            // rows where (src,sp,cv) are duplicated
            'raDuplicates'       => $kfdb->QueryRowsRA( "SELECT A.company as company,A.osp as osp,A.ocv as ocv,A.k as kA,B.k as kB "
                                                       ."FROM {$dbtable} A,{$dbtable} B "
                                                       ."WHERE ".$this->uploadCond('A.')." AND ".$this->uploadCond('B.')." "
                                                              ."AND A.fk_sl_sources<>'0' AND B.fk_sl_sources<>'0' "
                                                              ."AND A.fk_sl_species<>'0' AND B.fk_sl_species<>'0' "
                                                              ."AND A._key<B._key AND A.fk_sl_sources=B.fk_sl_sources "
                                                              ."AND A.fk_sl_species=B.fk_sl_species AND A.ocv=B.ocv" ),
        );

        return( $raReport );
    }

    function IsCommitAllowed( $raReport )
    /************************************
        Return true if the current upload state is stable enough for a Commit
     */
    {
        return( $raReport['nRows'] && !$raReport['nRowsUncomputed'] &&
                !count($raReport['raUnknownCompanies']) && !count($raReport['raDuplicates']) );
    }

    function DrawUploadReport( $raReport = null )
    /********************************************
        Show a report of the status of the upload table
     */
    {
        $s = "<style>"
               .".companyUploadResultsTable    { border-collapse-collapse; text-align:center }"
               .".companyUploadResultsTable th { text-align:center }"
               .".companyUploadResultsTable td { border:1px solid #aaa; padding:3px; text-align:center }"
               ."</style>";

        if( !$raReport ) $raReport = $this->CalculateUploadReport();

        /* Report error when rows in the tmp table have identical data to SrcCv but different keys.
         * This has to be fixed before other things will work.
         */
        if( ($c = $raReport['nRowsSameDiffKeys']) ) {
            $s .= "<div class='alert alert-danger'>$c rows in {$this->tmpTable} have the same data as sl_cv_sources but different keys. "
                 ."<span style='color:#888'>SELECT * FROM {$this->tmpTable} WHERE ".$this->uploadCond('')." AND op='.'</span></div>";
        }

        /* Report error if duplicate rows in the upload table.
         * This is more serious than it looks because it can lead to infinite validation loops where we try to fix incorrect keys on matched rows.
         */
        if( count($raReport['raDuplicates']) ) {
            $s .= "<div class='alert alert-danger'><p>These entries are duplicated in the upload.</p>"
                 ."<ul style='background-color:#f8f8f8;max-height:200px;overflow-y:scroll'>"
                 .SEEDCore_ArrayExpandRows( $raReport['raDuplicates'], "<li>[[company]] : [[osp]] : [[ocv]] ([[kA]], [[kB]])</li>")."</ul></div>";
        }

        /* Report error if unindexed companies. Cannot commit this, and not all validation will work without this index complete.
         */
        if( count($raReport['raUnknownCompanies']) ) {
            $s .= "<div class='alert alert-danger'><p>These companies are not indexed. Please add to Sources list and try again.</p>"
                    ."<ul>".SEEDCore_ArrayExpandRows( $raReport['raUnknownCompanies'], "<li>[[company]]</li>")."</ul></div>";
        }


        $sErrorUnknownCompanies = $sErrorUnknownSpecies = $sErrorUnknownCultivars = "";
        if( ($n = count($raReport['raUnknownCompanies'])) ) {
            $sErrorUnknownCompanies = "<span style='color:red'> + $n unindexed</span>";
        }
        if( ($n = count($raReport['raUnknownSpecies'])) ) {
            $sErrorUnknownSpecies = "<span style='color:red'> + $n unindexed</span>";
        }
        if( ($n = count($raReport['raUnknownCultivars'])) ) {
            $sErrorUnknownCultivars = "<span style='color:red'> + $n unindexed</span>";
        }

        $s .= "<table class='companyUploadResultsTable'><tr><th>Existing</th><th width='50%'>Upload<br/>({$raReport['nRows']} rows)</th></tr>"
               ."<tr><td>&nbsp;</td><td>{$raReport['nDistinctCompanies']} companies indexed $sErrorUnknownCompanies</td></tr>"
               ."<tr><td>&nbsp;</td><td>{$raReport['nDistinctSpKeys']} distinct species indexed $sErrorUnknownSpecies</td></tr>"
               ."<tr><td>&nbsp;</td><td>{$raReport['nDistinctCvKeys']} distinct cultivars indexed $sErrorUnknownCultivars</td></tr>"
               ."<tr><td colspan='2'>{$raReport['nRowsSame']} rows are identical including the year</td></tr>"
               ."<tr><td colspan='2'>{$raReport['nRowsY']} rows are identical except for the year (will be archived)</td></tr>"
               ."<tr><td colspan='2'>{$raReport['nRowsU']} rows have changed from previous year (will be archived)</td></tr>"
               ."<tr><td colspan='2'>{$raReport['nRowsV']} rows have corrections for current-year (won't be archived)</td></tr>"
               ."<tr><td>&nbsp;</td><td>{$raReport['nRowsN']} rows are new</td></tr>"
               ."<tr><td>&nbsp;</td><td>{$raReport['nRowsD1']} rows are marked in the spreadsheet for deletion</td></tr>"
               ."<tr><td>{$raReport['nRowsD2']} rows will be deleted because they are missing in the upload</td><td>&nbsp;</td></tr>"
               ."<tr><td>&nbsp;</td><td><span style='color:red'>{$raReport['nRowsUncomputed']} rows are not computed</span></td></tr>"
               ."</table><br/>";

        /* Warn about unindexed species and cultivars, unless company is blank (action C-delete).
         */
        if( count($raReport['raUnknownSpecies']) ) {
            $s .= "<div class='alert alert-warning'><p>These species are not indexed. Please add to Species list or Species Synonyms and try again.</p>"
                 ."<ul style='background-color:#f8f8f8;max-height:200px;overflow-y:scroll'>"
                 .SEEDCore_ArrayExpandRows( $raReport['raUnknownSpecies'], "<li>[[osp]]</li>")."</ul></div>";
        }

        /* Warn about unindexed cultivars that are not indexed, unless company is blank (action C-delete).
         */
        if( count($raReport['raUnknownCultivars']) ) {
            $s .= "<div class='alert alert-warning'><p>These cultivars are not indexed. They will be matched by name as much as possible, but you should add them to the Cultivars list.</p>"
                 ."<ul style='background-color:#f8f8f8;max-height:200px;overflow-y:scroll'>"
                 .SEEDCore_ArrayExpandRows( $raReport['raUnknownCultivars'], "<li>[[osp]] : [[ocv]]</li>")."</ul></div>";
        }

        done:
        return( $s );
    }

    function Commit()
    /****************
        Update sl_cv_sources with the rows in the given upload

        N:     insert new rows
        U,V,Y: overwrite existing rows
        D,X:   delete rows
     */
    {
        $bOk = false;
        $sOk = $sErr = "";

        $raReport = $this->CalculateUploadReport();

        // Commit is only allowed if the upload state is valid
        if( !$this->IsCommitAllowed($raReport) )  goto done;

//+$this->oApp->kfdb->SetDebug(2);
        $uid = $this->oApp->sess->GetUID();

        // N = new rows (k==0)
        $ok = $this->oApp->kfdb->Execute(
                "INSERT INTO seeds.sl_cv_sources "
                   ."(fk_sl_sources,fk_sl_pcv,fk_sl_species,company_name,osp,ocv,bOrganic,bulk,year,notes,_created,_updated,_created_by,_updated_by) "
               ."SELECT fk_sl_sources,fk_sl_pcv,fk_sl_species,company,osp,ocv,organic,bulk,year,notes,now(),now(),'$uid','$uid' "
               ."FROM {$this->tmpTable} WHERE op='N'" );
        if( $ok ) {
            $sOk .= "<div class='alert alert-success'>Committed ".$this->oApp->kfdb->GetAffectedRows()." new rows</div>";
        } else {
            $sErr = $this->oApp->kfdb->GetErrMsg();
        }
        $bOk = $ok;

        // M = new rows (k<>0)
        $ok = $this->oApp->kfdb->Execute(
                "INSERT INTO seeds.sl_cv_sources "
                   ."(_key,fk_sl_sources,fk_sl_pcv,fk_sl_species,company_name,osp,ocv,bOrganic,bulk,year,notes,_created,_updated,_created_by,_updated_by) "
               ."SELECT k,fk_sl_sources,fk_sl_pcv,fk_sl_species,company,osp,ocv,organic,bulk,year,notes,now(),now(),'$uid','$uid' "
               ."FROM {$this->tmpTable} WHERE op='M'" );
        if( $ok ) {
            $sOk .= "<div class='alert alert-success'>Committed ".$this->oApp->kfdb->GetAffectedRows()." new rows</div>";
        } else {
            $sErr = $this->oApp->kfdb->GetErrMsg();
        }
        $bOk = $ok;

        // U,V,Y = rows with updated columns (k<>0)
        $ok = $this->oApp->kfdb->Execute(
                "UPDATE seeds.sl_cv_sources C,{$this->tmpTable} T "
               ."SET C.fk_sl_sources=T.fk_sl_sources,C.fk_sl_pcv=T.fk_sl_pcv,C.fk_sl_species=T.fk_sl_species,"
                   ."C.company_name=T.company,C.osp=T.osp,C.ocv=T.ocv,C.bOrganic=T.organic,C.bulk=T.bulk,C.year=T.year,C.notes=T.notes,_updated=now(),_updated_by='$uid' "
               ."WHERE C._key=T.k AND T.op in ('U','V','Y')" );
        if( $ok ) {
            $sOk .= "<div class='alert alert-success'>Committed ".$this->oApp->kfdb->GetAffectedRows()." changed rows</div>";
        } else {
            $sErr = $this->oApp->kfdb->GetErrMsg();
        }
        $bOk = $bOk && $ok;

        // D,X = deleted rows
        $ok = $this->oApp->kfdb->Execute(
                "DELETE C FROM seeds.sl_cv_sources C,{$this->tmpTable} T "
               ."WHERE C._key=T.k AND T.op in ('D','X')" );
        if( $ok ) {
            $sOk .= "<div class='alert alert-success'>Deleted ".$this->oApp->kfdb->GetAffectedRows()." rows identified for removal</div>";
        } else {
            $sErr = $this->oApp->kfdb->GetErrMsg();
        }
        $bOk = $bOk && $ok;

        done:
        return( [$bOk,$sOk,$sErr] );
    }

    private function uniqueNumber()
    /******************************
        Make a unique number by incrementing the _key of a table that we know exists during the lifetime of an upload.
        This could be any table with an auto-inc.
     */
    {
        if( ($k = $this->oApp->kfdb->InsertAutoInc( "INSERT INTO seeds.sl_cv_sources (_key) VALUES (NULL)" )) ) {
            $this->oApp->kfdb->Execute( "DELETE FROM seeds.sl_cv_sources WHERE _key='$k'" );
        }
        return( $k );
    }
}


class SLSourcesCVArchive
/***********************
    Manage the sl_cv_sources_archive table
 */
{
    private $oApp;

    function __construct( SEEDAppSession $oApp )
    {
        $this->oApp = $oApp;
    }

    function IsSrcCv2ArchiveSimple()
    /*******************************
        If copying SrcCv to SrcCvArchive would be a simple replacement of exactly a single year, return that year. Otherwise false;
     */
    {
        $y = false;

        $raYears = $this->oApp->kfdb->QueryRowsRA( "SELECT year FROM seeds.sl_cv_sources WHERE fk_sl_sources>=3 GROUP BY 1" );
        if( count($raYears) == 1 ) {
            $y = $raYears[0]['year'];
        }

        return( $y );
    }

    function CopySrcCv2Archive()
    /***************************
        In the simple case where all SrcCv records of src>=3 have the same year, delete that year from SrcCvArchive and copy
        all those SrcCv records to SrcCvArchive.
     */
    {
        $sOk = $sErr = "";
        $bOk = false;

        if( !($y = $this->IsSrcCv2ArchiveSimple()) ) {
            $sErr .= "Cannot archive: SrcCv records are not of a single year";
            goto done;
        }

        // delete sl_cv_sources_archive records of year $y
        $this->oApp->kfdb->Execute( "DELETE FROM seeds.sl_cv_sources_archive WHERE year='$y'" );

        $bOk = $this->oApp->kfdb->Execute(
                "INSERT INTO seeds.sl_cv_sources_archive "
                    ."(sl_cv_sources_key,fk_sl_sources,fk_sl_pcv,fk_sl_species,osp,ocv,bOrganic,bulk,year,notes,"
                    ."op,"  // obsolete but requires a default value
                    ."_created,_updated,_created_by,_updated_by) "
               ."SELECT C._key,C.fk_sl_sources,C.fk_sl_pcv,C.fk_sl_species,C.osp,C.ocv,C.bOrganic,C.bulk,C.year,C.notes,"
                    ."'',"
            ."_created,_updated,_created_by,_updated_by "
               ."FROM seeds.sl_cv_sources C "
               ."WHERE C.fk_sl_sources >= 3 AND year='$y'" );
        if( $bOk ) {
            $sOk .= "<div class='alert alert-success'>Archived ".$this->oApp->kfdb->GetAffectedRows()." rows for year $y</div>";
        } else {
            $sErr = $this->oApp->kfdb->GetErrMsg();
        }

        done:
        return( [$bOk,$sOk,$sErr] );
    }

    // Archive was coded to update as changes are made in the Upload process.
    /*

    function Archive()
    [*****************
        Archive is an accumulation of sl_cv_sources rows that have been deleted or updated from year-to year.
        Updating a current-year row does not trigger that row to be copied.

        For any rows in sl_cv_sources that are going to be A) deleted or B) updated and the year is being increased, make sure they
        are copied to the archive table.
     *]
    {
        // This assumes that rows are only copied to archive when the year column is changed or a row is deleted.
        // If you repeat this operation during the same upload you will get duplicate rows.
        //
        // There could be a test here to see if the copying has already happened, but it's easier to just make a garbage collector
        // that removes those duplicates if they ever happen.

        $ok = false;
        $sOk = $sWarn = $sErr = "";

        [* Archive U = change in data and year
         *         Y = change in year (data the same, so this records that the entry also existed in the previous year)
         *         D = marked for deletion in the spreadsheet
         *         X = to be deleted because it's missing in the spreadsheet
         *]
        $raReport = $this->ReportPendingUpload( $this->kUpload, $this->eReplace );

        $sOk = "<p>Archiving</p>"
              ."<ul>"
              ."<li>{$raReport['nRowsU']} rows where the data and year are being updated</li>"
              ."<li>{$raReport['nRowsY']} rows where the year is being updated (data not changed)</li>"
              ."<li>{$raReport['nRowsD1']} rows that are marked for deletion in the spreadsheet</li>"
              ."<li>{$raReport['nRowsD2']} rows that are missing from the spreadsheet and will be deleted</li>"
              ."</ul>";

        $uid = $this->oW->sess->GetUID();

        $ok = $this->oW->kfdb->Execute(
                "INSERT INTO seeds.sl_cv_sources_archive "
                    ."(sl_cv_sources_key,fk_sl_sources,fk_sl_pcv,fk_sl_species,osp,ocv,bOrganic,year,notes,op,"
                    ." _created,_updated,_created_by,_updated_by) "
               ."SELECT C._key,C.fk_sl_sources,C.fk_sl_pcv,C.fk_sl_species,C.osp,C.ocv,C.bOrganic,C.year,C.notes,T.op,"
                      ."now(),now(),'$uid','$uid' "
               ."FROM seeds.sl_cv_sources C, {$this->tmpTable} T "
               ."WHERE C._key=T.k AND kUpload='{$this->kUpload}' AND T.op IN ('U','Y','D','X')" );
        if( !$ok ) {
            $sErr = $this->oW->kfdb->GetErrMsg();
        }

        done:
        return( array($ok,$sOk,$sWarn,$sErr) );
    }


     */

}
