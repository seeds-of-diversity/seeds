<?php

include_once( SEEDCORE."console/console02ui.php");

class MbrDonationsTab_Admin
{
    private $oApp;
//    private $oSVA;  // session vars for the Console tab
    private $oOpPicker;
    private $oMbrDb;

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;    // this tab's SVA
        $raOps = ['-- Choose --'=>'',
                  'Integrity test'                 =>'integrity',
                  'Upload Canada Helps spreadsheet'=>'uploadCH'];
        $this->oOpPicker = new Console02UI_OperationPicker('currOp', $oSVA, $raOps);
        $this->oMbrDb = new Mbr_ContactsDB($oApp);
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
                $s = $this->doUpload();
                break;
        }

        return( $s );
    }

    private $raSheetCols =
        ['trid'=>'TRANSACTION NUMBER',
         'monthly'=>'MONTHLY GIFT ID',
         'email'=>'DONOR EMAIL ADDRESS',
        ];

    private function doUpload()
    {
        $s = "";

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
                     .$this->uploadDrawValidateView();
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

    private function uploadDrawValidateView()
    {
        $s = "";

        $s .= $this->uploadDrawDiff();

        // draw Commit button
        $oForm = new SEEDCoreForm('A');
        $s .= "<form action='{$this->oApp->PathToSelf()}'>
               <input type='submit' value='Commit Changes'/>
               {$oForm->Hidden('cmd', ['value'=>'uploadCommit'])}
               </form>";

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

        $s .= "</table>";

        return($s);
    }

    private function uploadDrawDiffRecord( string $k, array $raD )
    {
        $s = "";

        [$status,$errmsg] = array_pad(explode(':', $raD['status']), 2, "");

        $s1 = "Found $k";
        $s2 = $raD['M_name'];
        $s3 = $s4 = "";
        $c1 = $c2 = 'success';
        $c3 = $c4 = "warning";
        switch($status) {
            case 'ok':
                $s3 = "$ {$raD['amount']} on {$raD['date']}";
                $c3 = $c4 = 'success';
                break;
            case 'notfound':
                $s1 = "Can't find donation $k";
                $s4 = "Add donation record";
                $c1 = $c2 = 'warning';
                break;
            case 'notfound_nocontact':
                $s1 = "Can't find donation $k";
                $s2 = "contact unknown";
                $c1 = $c2 = 'warning';
                break;
            case 'wrongamount':
            case 'wrongdate':
                $s3 = $errmsg;
                $s4 = "Update "
                     .(($v = @$raD['actions']['amount']) ? "amount $ $v " : "")
                     .(($v = @$raD['actions']['date']) ? "date $v " : "")
                     ."on donation {$raD['actions']['kD']}";
                break;
                $s3 = $errmsg;
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
        $raSingle = $raMonthly = [];

        /* Aggregate monthly donations to sum(amount) and max(date) for each donor
         * Store single donations in raSingle
         */
        foreach($raRawRows as $ra) {
            if( ($k = trim(@$ra[$this->raSheetCols['monthly']] ??"")) ) {
                // monthly donations
                $total_amount = floatval(@$ra['AMOUNT']) + @$raMonthly[$k]['amount'];
                $last_date = max(@$ra['DONATION DATE'], @$raMonthly[$k]['date']);
                $raMonthly[$k] = ['amount'=>$total_amount, 'date'=>$last_date, 'raOriginal'=>$ra];
            } else {
                // single donations
                $trid = @$ra[$this->raSheetCols['trid']];
                $raSingle[$trid] = ['raOriginal'=>$ra];
                $raSingle[$trid] = ['amount'=>$ra['AMOUNT'], 'date'=>$ra['DONATION DATE'], 'raOriginal'=>$ra];
            }
        }

        // compare monthly donations with mbr_donations
        foreach($raMonthly as $k => $ra) {
            $raMonthly[$k]['date'] = date("Y-m-d",\PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($raMonthly[$k]['date']));

            $v = addslashes($k);
            $kfrD = $this->oMbrDb->GetKFRCond('DxM', "v1='monthly_{$v}'");
            $raMonthly[$k] += $this->uploadPreprocessRecord($kfrD, $ra);
        }

        // compare single donations with mbr_donations
        foreach($raSingle as $k => $ra) {
            $v = addslashes($k);
            $kfrD = $this->oMbrDb->GetKFRCond('DxM', "v1='{$v}'");
            $raSingle[$k] += $this->uploadPreprocessRecord($kfrD, $ra);
        }

        $this->oSVA->VarSet( 'uploadedCHData', ['raRaw'=>$raRawRows, 'raSingle'=>$raSingle, 'raMonthly'=>$raMonthly] );
    }

    private function uploadPreprocessRecord( ?KeyframeRecord $kfrD, array $raD )
    {
        $raOut = ['status'=>"", 'M_name'=>"", 'actions'=>[]];

        // Excel stores dates as the number of days since Jan 1 1990 so it is format independent. You can set the format when you read with PHPSpreadsheet but we translate here.
        // Kluge: this method seems to return the date one day early, so adding +1
        $raD['date'] = date("Y-m-d",\PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($raD['date'] + 1));

        if( !$kfrD ) {
            $raOut['status'] = 'notfound_nocontact';
            $raOut['M_name'] = "";

            if( ($e = $raD['raOriginal'][$this->raSheetCols['email']]) ) {
                $e = addslashes($e);
                if( ($kfrM = $this->oMbrDb->GetKFRCond('M', "email='$e'")) ) {
                    $raOut['status'] = 'notfound';
                    $raOut['M_name'] = (new Mbr_Contacts($this->oApp))->GetContactName($kfrM->Key());

                    // the donation record doesn't exist but the contact does
                    $raOut['actions'] = ['new'=>true, 'fk_mbr_contacts'=>$kfrM->Key(), 'amount'=>$raD['amount'], 'date'=>$raD['date']];
                }
            }
            goto done;
        }

        $raOut['M_name'] = (new Mbr_Contacts($this->oApp))->GetContactName($kfrD->Value('M__key'));

        if( $raD['amount'] != $kfrD->Value('amount') ) {
            $raOut['status'] = "wrongamount:CanHelps total is {$raD['amount']} but db amount is {$kfrD->Value('amount')}";
            $raOut['actions']['kD'] = $kfrD->Key();
            $raOut['actions']['amount'] = $raD['amount'];
        } else
        if( $raD['date'] != $kfrD->Value('date_received') ) {
            $raOut['status'] = "wrongdate:CanHelps date is {$raD['date']} but db date is {$kfrD->Value('date_received')}";
            $raOut['actions']['kD'] = $kfrD->Key();
            $raOut['actions']['date'] = $raD['date'];
        } else {
            $raOut['status'] = 'ok';
        }

        done:
        return( $raOut );
    }

    private function uploadDoCommit()
    {
        $s = "";

        $raCHData = $this->oSVA->VarGet('uploadedCHData');
        foreach($raCHData['raMonthly'] as $k => $raD) {
            // commit changes to amount and date (this happens every time there's another monthly donation
            if( @$raD['actions']['kD'] && ($kfrD = $this->oMbrDb->GetKFR('D', $raD['actions']['kD'])) ) {
                if( ($v = @$raD['actions']['amount']) )  $kfrD->SetValue('amount', $v);
                if( ($v = @$raD['actions']['date']) )    $kfrD->SetValue('date_received', $v);
                $kfrD->PutDBRow();
            }

            // add a new monthly donation
            if( @$raD['actions']['new'] && ($kfrD = $this->oMbrDb->KFRel('D')->CreateRecord()) ) {
                $kfrD->SetValue('fk_mbr_contacts', $raD['actions']['fk_mbr_contacts']);
                $kfrD->SetValue('amount',          $raD['actions']['amount']);
                $kfrD->SetValue('date_received',   $raD['actions']['date']);
                $kfrD->SetValue('receipt_num',     -3);     // CanadaHelps

                $kfrD->SetValue('v1',              "monthly_$k");
                $kfrD->SetValue('notes',           "CH monthly total for ".substr($raD['actions']['date'], 0, 4));

                $kfrD->SetNull('date_issued');

                $kfrD->PutDBRow();
            }
        }

        return($s);
    }
}
