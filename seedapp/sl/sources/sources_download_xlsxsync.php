<?php

/* Seed Sources Sync sl_cv_sources by uploading an XLSX file
 *
 * Copyright (c) 2026 Seeds of Diversity Canada
 *
 */

include_once(SEEDLIB."sl/sources/sl_sources_rosetta.php");
include_once(SEEDLIB."sl/sources/sl_sources_cv_upload.php");

class SLSourcesAppDownload_CSCIUpload_XLSX
{
    private $oApp;
    private $oUploadLib;
    private $oForm;

// factor with SLSourcesAppDownload_CSCIUpload_GoogleSheet
    private const raSheetColnames = [
        'k'         => ['sheetColName'=>'k',        'type'=>'I', 'bReadFromSheet'=>true],
        'company'   => ['sheetColName'=>'company',  'type'=>'S', 'bReadFromSheet'=>true],
        'species'   => ['sheetColName'=>'species',  'type'=>'S', 'bReadFromSheet'=>true],
        'cultivar'  => ['sheetColName'=>'cultivar', 'type'=>'S', 'bReadFromSheet'=>true],
        'organic'   => ['sheetColName'=>'organic',  'type'=>'I', 'bReadFromSheet'=>true],
        'bulk'      => ['sheetColName'=>'bulk',     'type'=>'I', 'bReadFromSheet'=>true],
        'hybrid'    => ['sheetColName'=>'hybrid',   'type'=>'S', 'bReadFromSheet'=>true],
        'notes'     => ['sheetColName'=>'notes',    'type'=>'S', 'bReadFromSheet'=>true],

        'pcv'       => ['sheetColName'=>'primary',  'type'=>'S', 'bReadFromSheet'=>false],    // written but not read
    ];

    function __construct( SEEDAppConsole $oApp, SLSourcesCVUpload $oUploadLib, array $raConfig )
    {
        $this->oApp = $oApp;
        $this->oUploadLib = $oUploadLib;
        $this->oForm = new SEEDCoreForm();
        $this->oForm->Update();
    }

// make these parameters in constructor and put this class in seedlib
    private $companyTableDef = ['headers-required' => ['k','company','species','cultivar','organic','bulk','notes'],
                                'headers-optional' => [] ];
    function GetFormControls( SEEDSessionVarAccessor $oSVA, array $raParms )
    {
        $oForm = new SEEDCoreFormSVA( $oSVA->CreateChild('-companies'), 'A' );
        $oForm->Update();

        //list($sSelect,$sCompanyName) = (new SLSourcesLib( $this->oApp ))->DrawCompanySelector( $oForm, 'kSrc' );
        //$sDownloadAction = $this->oApp->UrlQ('index.php');     // use old Q until the new Q does this
        //$sDownloadCtrl = $oForm->Hidden( 'qcmd', ['value'=>'srcCSCI'] )
        //                //.$oForm->Hidden( 'qname', "" )
        //                .$oForm->Hidden( 'qfmt', ['value'=>'xls'] )
        //                .$sSelect;

        $raUDParms = [ 'label'=>"Seed Company listings",
                       'download_disable' => true,
                       //'downloadaction'=>$_SERVER['PHP_SELF'],
                       //'downloadctrl'=>$sDownloadCtrl,
                       'uploadaction'=>$this->oApp->PathToSelf(), //$_SERVER['PHP_SELF'],
                       'uploadctrl'=> $raParms['uploadctrl']
//                                 ."<input type='hidden' name='cmd' value='company_upload' />"
                                 ."<select name='eReplace' style='margin:0px 0px 10px 20px'>"
                                 ."<option value='".SLSourcesCVUpload::ReplaceVerbatimRows."'>Just copy the rows in the spreadsheet</option>"
                                 ."<option value='".SLSourcesCVUpload::ReplaceWholeCompanies."'>Replace entire companies mentioned in the spreadsheet</option>"
                                 ."<option value='".SLSourcesCVUpload::ReplaceWholeCSCI."'>Replace entire CSCI</option>"
                                 ."</select>",
                       'upload_nobutton' => true,
                       'uploadctrlbelow' => $raParms['uploadctrlbelow'],
                       'seedTableDef'=>$this->companyTableDef,
                     ];
        return( Console02UI::DownloadUpload( $this->oApp, $raUDParms ) );

        return( "{$this->oForm->Text('xlsx_parm', "", ['size'=>50, 'placeholder'=>"XLSX Parm 1"])}<br/>
                 {$this->oForm->Text('foo',       "", ['size'=>30, 'placeholder'=>"XLSX Parm 2"])}<br/>" );
    }

    function FetchSheetToTmpTable()
    {
        $s = "";
        $ok = false;

        $this->oUploadLib->InitTmpTable();
var_dump($_REQUEST,$_FILES);
        $ok = true;

        done:
        return([$ok,$s]);
    }
}
