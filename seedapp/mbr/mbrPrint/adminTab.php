<?php

include_once( SEEDCORE."console/console02ui.php");

class MbrDonationsTab_Admin
{
    private $oApp;
    private $oSVA;  // session vars for the Console tab
    private $oOpPicker;

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;    // this tab's SVA
        $this->oOpPicker = new Console02UI_OperationPicker('currOp', $oSVA,
                                ['-- Choose --'=>'',
                                 'Integrity test'                 =>'integrity',
                                 'Upload Canada Helps spreadsheet'=>'uploadCH'] );
    }

    function Init()
    {
    }

    function ControlDraw()  { return( $this->oOpPicker->DrawDropdown() ); }

    function ContentDraw()
    {
        $s = "";

        switch( $this->oOpPicker->Value() ) {
            case 'integrity':
                include_once( SEEDLIB."mbr/MbrIntegrity.php" );
                $s = "<h3>Donation Integrity Tests</h3>"
                    .(new MbrIntegrity($this->oApp))->ReportDonations();
                break;

            case 'uploadCH':
                $s = (new MbrDonationsTab_Admin_CHUpload($this->oApp, $this->oSVA))->Main();
                break;
        }

        return( $s );
    }
}

class MbrDonationsTab_Admin_CHUpload
{
    private $oApp;
    private $oSVA;
    private $oMbr;

    private $raSheetCols =
        ['trid'     => 'TRANSACTION NUMBER',
         'monthly'  => 'MONTHLY GIFT ID',
         'email'    => 'DONOR EMAIL ADDRESS',
         'don_date' => 'DONATION DATE',
         'amount'   => 'AMOUNT',
         'lastname' => 'DONOR LAST NAME'
        ];

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;
        $this->oMbr = new Mbr_Contacts($oApp);
    }

    function Main()
    {
        $s = "";

        $s .= "<div class='alert alert-warning'>This doesn't set Category/Purpose so you have to copy that from the spreadsheet by hand</div>";

        $tableDef = ['headers-required' => $this->raSheetCols,
                     'headers-optional' => [] ];

        $oForm = new SEEDCoreForm('A');
        $oForm->Update();

        switch($oForm->Value('cmd')) {
            case 'upload':
                list($oSheets,$sErrMsg) = SEEDTableSheets_LoadFromUploadedFile( 'upfile', ['raSEEDTableSheetsLoadParms'=> $tableDef + ['charset-sheet'=>'cp1252']] );
                if( !$oSheets ) {
                    $this->oApp->oC->AddErrMsg( $sErrMsg );
                    goto show_form;
                }

                // Get the uploaded data and process it. Views and actions are performed on processed data. Raw data is retained to re-process after confirm so the changes can be seen.
                $raRawRows = $oSheets->GetSheet($oSheets->GetSheetList()[0]);
                $this->uploadPreProcess($raRawRows);
                // Show the differences between the uploaded data and the db data
                $s .= "<h3>Uploaded ".count($raRawRows)." rows</h3>"
                     .$this->uploadDrawDiff();
                // draw Commit button
                $s .= "<form action='{$this->oApp->PathToSelf()}'>
                       <input type='submit' value='Commit Changes'/>
                       {$oForm->Hidden('cmd', ['value'=>'uploadCommit'])}
                       </form>";
                    break;

            case 'uploadCommit':
                // commit the changes based on processed data
                $this->uploadDoCommit();
                // re-process the spreadsheet data compared to the new db data
                $raRawRows = $this->oSVA->VarGet('uploadedCHData')['raRaw'];
                $this->uploadPreProcess($raRawRows);
                // show what it looks like now
                $s .= "<h3>Committing uploaded spreadsheet</h3>"
                     .$this->uploadDrawDiff();
                break;
        }

        $s .= "<hr/>";

        show_form:

        $raUDParms = [ 'label'=>"Canada Helps donation data",
                       'download_disable' => true,
                       'uploadaction'=>$this->oApp->PathToSelf(),
                       'uploadctrl'=> $oForm->Hidden('cmd', ['value'=>'upload']),
                       'seedTableDef'=>$tableDef,
                     ];
        $s .= Console02UI::DownloadUpload( $this->oApp, $raUDParms );

        return($s);
    }

    private function uploadDrawDiff()
    {
        $s = "";

        $raCHData = $this->oSVA->VarGet('uploadedCHData');

        $s = "<table class='table'><tr><th>Monthly</th><th>&nbsp;</th><th>&nbsp;</th><th>Action</th></tr>";
        foreach($raCHData['raMonthly'] as $k => $raD) {
            $s .= $this->uploadDrawDiffRecord($k, $raD);
        }
        $s .= "<tr><td></td></tr>
               <tr><th>Single donations</th><th>&nbsp;</th><th>&nbsp;</th><th>Action</th></tr>";
        foreach($raCHData['raSingle'] as $k => $raD) {
            $s .= $this->uploadDrawDiffRecord($k, $raD);
        }
        $s .= "<tr><td></td></tr>
               <tr><th>Ignored donations</th><th>&nbsp;</th><th>&nbsp;</th><th></th></tr>";
        foreach($raCHData['raIgnored'] as $k => $raD) {
            $s .= "<tr><td>$k : {$raD['name']} {$raD['amount']} on {$raD['date_db']}</td></tr>";
        }

        $s .= "</table>";

        return($s);
    }

    private function uploadDrawDiffRecord( string $k, array $raD )
    {
        $s = "";

        [$status,$errmsg] = SEEDCore_Explode(':', $raD['status'], 2);

        $s1 = "Found $k";
        $s2 = $raD['M_name'];
        $s3 = $s4 = "";
        $c1 = $c2 = 'success';
        $c3 = $c4 = "warning";
        switch($status) {
            case 'ok':
                $s3 = "$ {$raD['amount']} on {$raD['date_db']}";
                $c3 = $c4 = 'success';
                break;
            case 'notfound':
                $s1 = "Can't find donation $k";
                $s4 = "Will create donation record";
                $c1 = $c2 = 'warning';
                break;
            case 'notfound_nocontact':
                $s1 = "Can't find donation $k";
                $s2 = "contact unknown : {$raD['raOriginal'][$this->raSheetCols['lastname']]} {$raD['raOriginal'][$this->raSheetCols['email']]}";
                $c1 = $c2 = 'danger';
                break;
            case 'name_mismatch':
                $c1 = $c2 = $c3 = 'danger';
                $s3 = $errmsg;
                break;
            case 'wrongamount':
            case 'wrongdate':
                $s3 = $errmsg;
                $s4 = "Will update "
                     .(($v = @$raD['action']['amount']) ? "amount=\${$v} " : "")
                     .(($v = @$raD['action']['date_db']) ? "date=$v " : "")
                     ."on donation {$raD['action']['kD']}";
                break;
        }
        $s .= "<tr><td class='$c1'>$s1</td><td class='$c2'>$s2</td><td class='$c3'>$s3</td><td class='$c4'>$s4</td></tr>";

        return( $s );
    }

    private function uploadPreprocess( array $raRawRows )
    {
        /* Create two arrays raSingle  = one-time donations identified by transaction id
                             raMonthly = monthly donations collapsed to record total amount and latest date
         */
        $raSingle = $raMonthly = $raIgnored = [];

        /* Aggregate monthly donations to sum(amount) and max(date) for each donor, per year
         * Store single donations in raSingle
         */
        foreach($raRawRows as $ra) {
            if( $ra[$this->raSheetCols['email']] == 'ANON' ) {
                $raIgnored[$ra[$this->raSheetCols['trid']]] = ['name'=>'ANON',
                                                               'amount'=>$ra[$this->raSheetCols['amount']],
                                                               'date_db'=>$this->getDateFromExcelDate($ra[$this->raSheetCols['don_date']])];
                continue;
            }
            if( $ra[$this->raSheetCols['email']] == 'info@canadahelps.org' ) {  // CH deposits Cause Funds Disbursements from some anonymous source
                $raIgnored[$ra[$this->raSheetCols['trid']]] = ['name'=>"CanHelps",
                                                               'amount'=>$ra[$this->raSheetCols['amount']],
                                                               'date_db'=>$this->getDateFromExcelDate($ra[$this->raSheetCols['don_date']])];
                continue;
            }

            if( ($k = trim(@$ra[$this->raSheetCols['monthly']] ??"")) ) {
                // monthly donations
                $k = "monthly_{$this->getYearFromExcelDate($ra[$this->raSheetCols['don_date']])}_$k";
                $total_amount = floatval(@$ra[$this->raSheetCols['amount']]) + @$raMonthly[$k]['amount'];
                $last_date = max(@$ra[$this->raSheetCols['don_date']], @$raMonthly[$k]['date']);
                $raMonthly[$k] = ['amount'=>$total_amount, 'date'=>$last_date, 'raOriginal'=>$ra];
            } else {
                // single donations
                $trid = @$ra[$this->raSheetCols['trid']];
                $raSingle[$trid] = ['amount'=>$ra[$this->raSheetCols['amount']], 'date'=>$ra[$this->raSheetCols['don_date']], 'raOriginal'=>$ra];
            }
        }

        // compare CanHelps donations with mbr_donations. $k is the CanHelps trid/monthlyid
        foreach($raMonthly as $k => $ra) { $raMonthly[$k] += $this->uploadPreprocessRecord($k, $ra); }
        foreach($raSingle as $k => $ra)  { $raSingle[$k] += $this->uploadPreprocessRecord($k, $ra); }

        $this->oSVA->VarSet( 'uploadedCHData', ['raRaw'=>$raRawRows, 'raSingle'=>$raSingle, 'raMonthly'=>$raMonthly, 'raIgnored'=>$raIgnored] );
    }

    private function uploadPreprocessRecord( string $k, array $raD )
    {
        // $raD contains date, amount, raOriginal.
        // The output array will be merged into $raD
        $raOut = ['status'=>"", 'M_name'=>"", 'action'=>['do'=>''],
                  'date_db'=>$this->getDateFromExcelDate($raD['date'])
                 ];

        // look up the donation record by the CanHelps id and compare it with the CanHelps record
        $kfrD = $this->oMbr->oDB->GetKFRCond('DxM', "v1='".addslashes($k)."'");
        if( !$kfrD ) {
            if( ($e = $raD['raOriginal'][$this->raSheetCols['email']]) &&
                ($e = addslashes($e)) &&
                ($kfrM = $this->oMbr->oDB->GetKFRCond('M', "email='$e'")) )
            {
                // the donation record doesn't exist but the contact does
                $raOut['status'] = 'notfound';
                $raOut['M_name'] = $this->oMbr->GetContactName($kfrM->Key());
                $raOut['action'] = ['do'=>'new', 'fk_mbr_contacts'=>$kfrM->Key(), 'amount'=>$raD['amount'], 'date_db'=>$raOut['date_db']];
            } else {
                // no donation record and contact unknown
                $raOut['status'] = 'notfound_nocontact';
                $raOut['M_name'] = "";
            }

            goto done;
        }

        /* the donation record exists
         */
        $raOut['M_name'] = $this->oMbr->GetContactName($kfrD->Value('M__key'));

        if( $raD['amount'] != $kfrD->Value('amount') ) {
            $raOut['status'] = "wrongamount:CanHelps total is {$raD['amount']} but db amount is {$kfrD->Value('amount')}";
        }
        else
        if( $raOut['date_db'] != $kfrD->Value('date_received') ) {
            $raOut['status'] = "wrongdate:CanHelps date is {$raOut['date_db']} but db date is {$kfrD->Value('date_received')}";
        }
        else
        if( ($n1 = trim(@$raD['raOriginal'][$this->raSheetCols['lastname']])??"") != ($n2 = trim($kfrD->Value('M_lastname'))) ) {
            $raOut['status'] = "name_mismatch:CanHelps last name is $n1 but db last name is $n2";
        } else {
            $raOut['status'] = 'ok';
        }
        if( SEEDCore_StartsWith($raOut['status'], 'wrong') ) {
            // wrongamount or wrongdate : update both and KF will just change the one that needs it
            $raOut['action'] = ['do'=>'update', 'kD'=>$kfrD->Key(), 'amount'=>$raD['amount'], 'date_db'=>$raOut['date_db']];
        }

        done:
        return( $raOut );
    }

    private function uploadDoCommit()
    {
        $s = "";

        $raCHData = $this->oSVA->VarGet('uploadedCHData');
        foreach(array_merge($raCHData['raMonthly'], $raCHData['raSingle']) as $k => $raD) {
            $bIsMonthly = SEEDCore_StartsWith($k, 'monthly');

            if( $raD['action']['do'] == 'update' && ($kfrD = $this->oMbr->oDB->GetKFR('D', $raD['action']['kD'])) ) {  // should only happen for monthly
                // wrongamount or wrongdate
                // commit changes to amount and date (this happens every time there's another monthly donation
                if( ($v = @$raD['action']['amount']) )   $kfrD->SetValue('amount', $v);
                if( ($v = @$raD['action']['date_db']) )  $kfrD->SetValue('date_received', $v);
                $kfrD->PutDBRow();
            }

            if( @$raD['action']['do'] == 'new' && ($kfrD = $this->oMbr->oDB->KFRel('D')->CreateRecord()) ) {
                // notfound
                $kfrD->SetValue('fk_mbr_contacts', $raD['action']['fk_mbr_contacts']);
                $kfrD->SetValue('amount',          $raD['action']['amount']);
                $kfrD->SetValue('date_received',   $raD['action']['date_db']);
                $kfrD->SetValue('receipt_num',     -3);     // CanadaHelps

                $kfrD->SetValue('v1',              $k);
                $kfrD->SetValue('notes',           $bIsMonthly ? ("CH monthly total for ".substr($raD['action']['date_db'], 0, 4)) : "CH donation $k");
                $kfrD->SetNull('date_issued');  // DATE type requires NULL if not specified
                $kfrD->PutDBRow();
            }
        }

        return($s);
    }

    private function getDateFromExcelDate( $date )
    {
        // Excel stores dates as the number of days since Jan 1 1990 so it is format independent. You can set the format when you read with PHPSpreadsheet but we translate here.
        // Kluge: this method seems to return the date one day early, so adding +1
        return( $date ?  date("Y-m-d",\PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($date + 1)) : "" );
    }

    private function getYearFromExcelDate( $date )
    {
        return( $date ? substr($this->getDateFromExcelDate($date), 0, 4) : "" );
    }
}
