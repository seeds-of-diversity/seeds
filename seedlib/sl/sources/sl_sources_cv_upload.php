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

    function UploadToTmpTable( $filename )
    {

    }

    function ValidateTmpTable()
    /**************************
        Fill index columns, compute differences between tmp table and sl_cv_sources
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
}
