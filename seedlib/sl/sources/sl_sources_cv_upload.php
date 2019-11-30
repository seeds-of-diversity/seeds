<?php

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

    private function uploadCond()
    {
        return( $this->kUpload ? "kUpload='{$this->kUpload}'" : "1=1" );
    }

    function IsTmpTableEmpty()
    {
        $c = $this->oApp->kfdb->Query1( "SELECT count(*) FROM {$this->tmpTable} WHERE ".$this->uploadCond() );

        return( !$c );
    }

    function ClearTmpTable()
    {
        $this->oApp->kfdb->Execute( "DELETE FROM {$this->tmpTable} WHERE ".$this->uploadCond() );
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
     */
    {

    }

    function ReportTmpTable()
    /************************
        Report on the current build status of seeds.sl_tmp_cv_sources
     */
    {
// TODO: this is not smart enough to take eReplace into account
        $raReport = [
            'nRows'              => $this->oApp->kfdb->Query1( "SELECT count(*) FROM {$this->tmpTable} WHERE ".$this->uploadCond() ),
            'nRowsUncomputed'    => $this->oApp->kfdb->Query1( "SELECT count(*) FROM {$this->tmpTable} WHERE op=''  AND ".$this->uploadCond() ),
            'nRowsSame'          => $this->oApp->kfdb->Query1( "SELECT count(*) FROM {$this->tmpTable} WHERE op='-' AND ".$this->uploadCond() ),
            'nRowsN'             => $this->oApp->kfdb->Query1( "SELECT count(*) FROM {$this->tmpTable} WHERE op='N' AND ".$this->uploadCond() ),
            'nRowsU'             => $this->oApp->kfdb->Query1( "SELECT count(*) FROM {$this->tmpTable} WHERE op='U' AND ".$this->uploadCond() ),
            'nRowsV'             => $this->oApp->kfdb->Query1( "SELECT count(*) FROM {$this->tmpTable} WHERE op='V' AND ".$this->uploadCond() ),
            'nRowsY'             => $this->oApp->kfdb->Query1( "SELECT count(*) FROM {$this->tmpTable} WHERE op='Y' AND ".$this->uploadCond() ),
            'nRowsD1'            => $this->oApp->kfdb->Query1( "SELECT count(*) FROM {$this->tmpTable} WHERE op='D' AND ".$this->uploadCond() ),
            'nRowsD2'            => $this->oApp->kfdb->Query1( "SELECT count(*) FROM {$this->tmpTable} WHERE op='X' AND ".$this->uploadCond() ),
            'nDistinctCompanies' => $this->oApp->kfdb->Query1( "SELECT count(distinct fk_sl_sources) FROM {$this->tmpTable} "
                                                                  ."WHERE fk_sl_sources<>'0' AND ".$this->uploadCond() ),

            // rows with unmatched companies, ignoring those where species is blank or company is blank (those are rows to be deleted)
            'raUnknownCompanies' => $this->oApp->kfdb->QueryRowsRA(
                    "SELECT company FROM {$this->tmpTable} WHERE ".$this->uploadCond()
                        ." AND fk_sl_sources='0' AND osp<>'' AND company<>'' GROUP BY 1 ORDER BY 1" ),
            // rows with unmatched species, ignoring those where species is blank or company is blank (those are rows to be deleted)
            'raUnknownSpecies' => $this->oApp->kfdb->QueryRowsRA(
                    "SELECT osp FROM {$this->tmpTable} WHERE ".$this->uploadCond()
                        ." AND fk_sl_species='0' AND osp<>'' AND company<>'' GROUP BY 1 ORDER BY 1" ),
            // rows with unmatched cultivars, not counting those where species was unmatched (reported above and prerequisite)
            'raUnknownCultivars' => $this->oApp->kfdb->QueryRowsRA(
                    "SELECT osp,ocv FROM {$this->tmpTable} WHERE ".$this->uploadCond()
                        ." AND fk_sl_pcv='0' AND fk_sl_species<>'0' GROUP BY 1,2 ORDER BY 1,2" ),
        ];

        return( $raReport );
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
