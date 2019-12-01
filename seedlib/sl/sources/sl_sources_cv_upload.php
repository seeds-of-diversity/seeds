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

    function __construct( SEEDAppDB $oApp, $eReplace, $kUpload = 0 )
    {
//    __construct( kUpload == 0 ) prepares the object for a potential Load(). Nothing else will work.
//    __construct( kUpload != 0 ) prepares the object to work with the rows in sl_tmp_cv_sources identified by kUpload.

        $this->oApp = $oApp;
        $this->eReplace = $eReplace;
        $this->kUpload = $kUpload;
    }

    private function uploadCond( $bUseTableAlias = true )
    {
        $alias = $bUseTableAlias ? "T." : "";
        return( $this->kUpload ? "{$alias}kUpload='{$this->kUpload}'" : "1=1" );
    }

    function ClearTmpTable()
    {
        $this->oApp->kfdb->Execute( "DELETE FROM {$this->tmpTable} WHERE ".$this->uploadCond(false) );
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
        $sOk = $sWarn = $sErr = "";
        $bOk = false;
//$this->oApp->kfdb->SetDebug(2);
//      uploadCond() does the right thing if kUpload is blank
//        if( !$this->kUpload ) goto done;


        // Index companies
//        $this->oApp->kfdb->Execute( "UPDATE {$this->tmpTable} T,seeds.sl_sources SRC SET T.fk_sl_sources=SRC._key "
//                                   ."WHERE SRC._status='0' AND T.company<>'' AND T.company=SRC.name_en AND ".$this->uploadCond() );

        // Index company names.
        // Index species and cultivars using Rosetta
        SLSourceCV_Build::BuildAll( $this->oApp->kfdb, $this->tmpTable, [] );   // uploadCond()
goto foo;

            /* Compute Operations to perform on the rows
             *
             *  N = new:     tmp.k==0
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
            $condUpdateCase = "($condKUpload AND C._key=T.k AND T.fk_sl_sources<>'0')";
            // test if rows the same without/with considering somebody changed osp to a sl_syn
            $condDataBasicSame     = "(C.osp=T.osp                                              AND C.fk_sl_sources=T.fk_sl_sources AND C.ocv=T.ocv )";
            $condDataBasicSameFkSp = "(C.fk_sl_species=T.fk_sl_species AND T.fk_sl_species<>'0' AND C.fk_sl_sources=T.fk_sl_sources AND C.ocv=T.ocv )";
            $condDataSame          = "($condDataBasicSame AND C.bOrganic=T.organic AND C.notes=T.notes)";

            // Before computing operations, any rows in the tmp table whose non-blank (fk_sl_sources,osp/fk_sl_species,ocv) are identical
            // to sl_cv_sources are deemed to be matches. If their keys are different, that is a mistake in data entry.
            $this->oW->kfdb->Execute(
                "UPDATE {$this->tmpTable} T,seeds.sl_cv_sources C SET T.op='.' "
               ."WHERE T.op=' ' AND $condKUpload AND T.fk_sl_sources<>'0' AND "
                     ."($condDataBasicSame OR $condDataBasicSameFkSp) AND C.fk_sl_sources>='3' "
                     ."AND C._key<>T.k" );
            if( ($c = $this->oW->kfdb->Query1( "SELECT count(*) FROM {$this->tmpTable} T WHERE $condKUpload AND T.op='.'" )) ) {
                $sErr .= "$c rows in {$this->tmpTable} have the same data as sl_cv_sources but different keys. "
                        ."<span style='color:#888'>SELECT * FROM {$this->tmpTable} T LEFT JOIN seeds.sl_cv_sources C ON ($condDataBasicSame OR $condDataBasicSameFkSp) WHERE $condKUpload AND T.op='.'</span>";
                goto done;
            }

            // N (tmp.k==0)
            $this->oW->kfdb->Execute( "UPDATE {$this->tmpTable} T SET T.op='N' WHERE $condKUpload AND T.k='0'" );

            // U (data and year changed)
            $this->oW->kfdb->Execute(
                "UPDATE {$this->tmpTable} T,seeds.sl_cv_sources C SET T.op='U' "
               ."WHERE $condUpdateCase AND "
                     ."NOT $condDataSame AND C.year<>T.year" );

            // V (data changed but year the same)
            $this->oW->kfdb->Execute(
                "UPDATE {$this->tmpTable} T,seeds.sl_cv_sources C SET T.op='V' "
               ."WHERE $condUpdateCase AND "
                     ."NOT $condDataSame AND C.year=T.year" );

            // Y (only the year changed)
            $this->oW->kfdb->Execute(
                "UPDATE {$this->tmpTable} T,seeds.sl_cv_sources C SET T.op='Y' "
               ."WHERE $condUpdateCase AND "
                     ."$condDataSame AND C.year<>T.year" );

            // - (perfect match on all columns including year)
            $this->oW->kfdb->Execute(
                "UPDATE {$this->tmpTable} T,seeds.sl_cv_sources C SET T.op='-' "
               ."WHERE $condUpdateCase AND "
                     ."$condDataSame AND C.year=T.year" );

            // D (company and osp are blank)
            $this->oW->kfdb->Execute( "UPDATE {$this->tmpTable} T SET T.op='D' WHERE $condKUpload AND T.k<>'0' AND T.company='' AND T.osp=''" );

            // X (rows in sl_cv_sources don't exist in tmp) - implement this by adding them to tmp
            if( $this->eReplace != self::ReplaceVerbatimRows ) {
                $this->oW->kfdb->Execute(
                    "INSERT INTO {$this->tmpTable} (k,kUpload,op) "
                    ."SELECT SRCCV._key,{$this->kUpload},'X' FROM seeds.sl_cv_sources SRCCV LEFT JOIN {$this->tmpTable} T2 "
                        ."ON SRCCV._key=T2.k WHERE T2.k IS NULL AND "
                        .($this->eReplace == self::ReplaceWholeCSCI
                            // if replacing all companies then delete all rows that are missing in tmpTable (except seed banks)
                            ? "SRCCV.fk_sl_sources >= '3'"
                            // if replacing specific companies then delete missing rows from those companies only
                            : ("SRCCV.fk_sl_sources IN (SELECT distinct(fk_sl_sources) FROM {$this->tmpTable} T "
                                                      ."WHERE $condKUpload AND T.fk_sl_sources<>'0')")) );
            }

foo:

        $bOk = true;

        done:
        return( [$bOk,$sOk,$sErr,$sWarn] );
    }

    function CalculateUploadReport()
    /*******************************
        Report on the current build status of seeds.sl_tmp_cv_sources
     */
    {
// TODO: this is not smart enough to take eReplace into account
        return( SLSourceCV_Build::ReportTmpTable( $this->oApp->kfdb, $this->kUpload ) );
    }

    function DrawUploadReport( $raReport = null )
    /********************************************
        Show a report of the status of the upload table
     */
    {
        if( !$raReport ) $raReport = $this->CalculateUploadReport();

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

        $s = "<style>"
               .".companyUploadResultsTable    { border-collapse-collapse; text-align:center }"
               .".companyUploadResultsTable th { text-align:center }"
               .".companyUploadResultsTable td { border:1px solid #aaa; padding:3px; text-align:center }"
               ."</style>";

        $s .= "<table class='companyUploadResultsTable'><tr><th>Existing</th><th width='50%'>Upload<br/>({$raReport['nRows']} rows)</th></tr>"
               ."<tr><td>&nbsp;</td><td>{$raReport['nDistinctCompanies']} companies indexed $sErrorUnknownCompanies</td></tr>"
               ."<tr><td>&nbsp;</td><td>{$raReport['nDistinctSpKeys']} distinct species indexed $sErrorUnknownSpecies</td></tr>"
               ."<tr><td>&nbsp;</td><td>{$raReport['nDistinctCvKeys']} distinct cultivars indexed $sErrorUnknownCultivars</td></tr>"
               ."<tr><td colspan='2'>{$raReport['nRowsSame']} rows are identical including the year</td></tr>"
               ."<tr><td colspan='2'>{$raReport['nRowsY']} rows are exactly the same except for the year (will be archived)</td></tr>"
               ."<tr><td colspan='2'>{$raReport['nRowsU']} rows have changed from previous year (will be archived)</td></tr>"
               ."<tr><td colspan='2'>{$raReport['nRowsV']} rows have corrections for current-year (won't be archived)</td></tr>"
               ."<tr><td>&nbsp;</td><td>{$raReport['nRowsN']} rows are new</td></tr>"
               ."<tr><td>&nbsp;</td><td>{$raReport['nRowsD1']} rows are marked in the spreadsheet for deletion</td></tr>"
               ."<tr><td>{$raReport['nRowsD2']} rows will be deleted because they are missing in the upload</td><td>&nbsp;</td></tr>"
               ."<tr><td>&nbsp;</td><td><span style='color:red'>{$raReport['nRowsUncomputed']} rows are not computed</span></td></tr>"
               ."</table><br/>";

        /* Warn about unindexed companies
         */
        if( count($raReport['raUnknownCompanies']) ) {
            $s .= "<div class='alert alert-danger'><p>These companies are not indexed. Please add to Sources list and try again.</p>"
                    ."<ul>".SEEDCore_ArrayExpandRows( $raReport['raUnknownCompanies'], "<li>[[company]]</li>")."</ul></div>";
        }

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

        return( $s );
    }

    private function Commit()
    {
        $s = "";



        return( $s );
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
