<?php

/* Seed collection manager - batch operations - record germination tests in batches
 *
 * Copyright 2020-2026 Seeds of Diversity Canada
 */


class Collection_GermTest
{
// factor common code from batchopsTab_germtest and collectionTab_germtest, and put this somewhere else
}


class CollectionBatchOps_GermTest
{
    private $oApp;
    private $oSLDB;
    private $sGermFeedback = "";

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oSLDB = new SLDBCollection($this->oApp);
    }

    function Draw()
    {
        $s = "";

        $sRowTmpl = "<tr>[[hiddenkey:]]
                         <td style='width:10%'> @LotNum@ </td>
                         <td style='width:15%'>[[XCvName     | width:100% | class='@XCvClass@' disabled]]</td>
                         <td style='width:10%'>[[nSown       | width:100% | @OthersDisabled@ ]]</td>
                         <td style='width:10%'>[[nGerm_count | width:100% | @OthersDisabled@ ]]</td>
                         <td style='width:10%'>[[Date:dStart | width:100% | @OthersDisabled@ ]]</td>
                         <td style='width:10%'>[[Date:dEnd   | width:100% | @OthersDisabled@ ]]</td>
                         <td style='width:40%'>[[notes       | width:100% | @OthersDisabled@ ]]</td>
                         <td style='width:10%'> @DelButton@</td>
                     </tr>";

        // the blank rows use a special lotnum field (onblur looks up the XCvName) and never have a delete button
        $sRowBlank = str_replace( ['@LotNum@',                                    '@XCvClass@', '@OthersDisabled@', '@DelButton@'],
                                  ["[[XLotNum | width:100% | class='gtLotNum']]", "gtCVName",   "",                 "&nbsp"],
                                  $sRowTmpl );

        // my rows have a normal disabled lotnum field, @DelButton@ is replaced per-row
        $sRowMine = str_replace( ['@LotNum@',                                 '@XCvClass@', '@OthersDisabled@'],
                                 ["[[I_inv_number| width:100% | disabled]]",  "",           ""],
                                 $sRowTmpl );

        // other peoples' rows are like mine but all disabled and with no delete button
        $sRowOthers = str_replace( ['@LotNum@',                               '@XCvClass@', '@OthersDisabled@', '@DelButton@'],
                                 ["[[I_inv_number| width:100% | disabled]]",  "",           "disabled",         ""],
                                 $sRowTmpl );

        // this is also in CollectionTab_GerminationTests
        if( ($kDel = SEEDInput_Int('germdel')) && ($kfr = $this->oSLDB->GetKFR('G', $kDel)) ) {
            $kfr->StatusSet( KeyframeRecord::STATUS_DELETED );
            $kfr->PutDBRow();
        }


        // This updates the individual rows, but not the Multi row
        $oForm = new KeyFrameForm( $this->oSLDB->KFRel('G'), 'A', ['DSParms'=>['fn_DSPreStore'=>[$this,'PreStoreGerm']] ] );
        $oForm->Update(['sCharsetHTTP'=>"utf8", 'sCharsetDb'=>'cp1252']);


        // This updates the Multi row
        $oFormMulti = new SEEDCoreForm('M');
        $oFormMulti->Update();
        $sMultiLotNum = $oFormMulti->Value('MultiLotNum');
        $nMultiNSown = $oFormMulti->ValueInt('MultiNSown');
        $dMultiStart = $oFormMulti->Value('MultiDStart');
        $sMultiNotes = $oFormMulti->Value('MultiNotes');
        if($sMultiLotNum) {
            // lot numbers can be SeedCoreRanges, where whitespace is treated as a comma
            if( ($raMultiLots = SEEDCore_ParseRangeStrToRA(preg_replace('#\s+#', ',', trim($sMultiLotNum)))) &&
                $nMultiNSown )
            {
// this should do the same as PreStoreGerm()
                if( !$dMultiStart ) {
                    $this->sGermFeedback .= "Defaulting multiple tests to today's date<br/>";
                    $dMultiStart = date('Y-m-d');
                }
                foreach($raMultiLots as $iLot) {
// is this parameterized somewhere
                    $kColl = 1;
                    if( ($kfrI = $this->oSLDB->GetKFR_LotFromNumber($kColl, $iLot)) ) {
                        $kfrG = $this->oSLDB->KFRel('G')->CreateRecord();
                        $kfrG->SetValue('fk_sl_inventory', $kfrI->Key());
                        $kfrG->SetValue('nSown', $nMultiNSown);
                        $kfrG->SetValue('dStart', $dMultiStart);
                        $kfrG->SetNull('dEnd');
                        $kfrG->SetValue('notes', SEEDCore_utf8_decode($sMultiNotes));
                        $kfrG->PutDBRow();
                    }
                }
            }
        }
        $oFormMulti->Clear();   // the multi form should be drawn blank every time (instead of having previous screen's inputs)
        $sMultiRow = (new SEEDFormExpand($oFormMulti))->ExpandForm(
                "<tr><td colspan='8'>&nbsp;</td></tr>
                 <tr><td colspan='8'><h4>Multiple lots</h4></td></tr>
                 <tr><td colspan='2'>[[MultiLotNum | width:95%]] </td>
                     <td>            [[MultiNSown | width:100%]]</td>
                     <td>            [[Dummy_Germ_count | width:100% | disabled ]]</td>
                     <td>            [[Date:MultiDStart | width:100%]]</td>
                     <td>            [[Date:Dummy_dEnd  | width:100% | disabled]]</td>
                     <td>            [[MultiNotes | width:100%]]</td>
                     </tr>");


        $sBlank = $sMine = $sOthers = "";

        /* Draw individual blank test rows
         */
        $oFE = new SEEDFormExpand($oForm);
        // initialize form to draw blank entries
        $oForm->SetKFR( $this->oSLDB->KFRel('G')->CreateRecord() );
        // blank looks nicer than zero
        $oForm->SetValue('nSown', '');
        $oForm->SetValue('nGerm_count', '');
        for( $i = 0; $i < 5; ++$i ) {
            $sBlank .= $oFE->ExpandForm( $sRowBlank );
            $oForm->IncRowNum();
        }


        /* Get current tests underway
         * sMine   = tests created by current user
         * sOthers = tests created by other people
         */
        if( ($raKfrG = $this->oSLDB->KFRel('GxIxAxPxS')->GetRecordSet( 'dEnd is null', ['sSortCol'=>'dStart','bSortDown'=>true] )) ) {
            foreach( $raKfrG as $kfr ) {
                $oForm->SetKFR($kfr);

                $oForm->SetValue( 'XLotNum', 'I_inv_number_already_set' );
                $oForm->SetValue( 'XCvName', $oForm->Value('S_name_en').' '.$oForm->Value('P_name') );

                // blank looks nicer than zero
                if( !$oForm->Value('nGerm_count') ) $oForm->SetValue('nGerm_count', '');

                if( $oForm->Value('_created_by')==$this->oApp->sess->GetUID() ) {
                    $sMine .= $oFE->ExpandForm( str_replace( '@DelButton@', $this->deleteButton($kfr), $sRowMine ) );
                } else {
                    $sOthers .= $oFE->ExpandForm( str_replace( '@DelButton@', $this->deleteButton($kfr, true), $sRowMine ) );
// this used to show other peoples' tests but not allow editing - maybe a control could switch editing on - for now allow editing
//                    $sOthers .= $oFE->ExpandForm( $sRowOthers );
                }
                $oForm->IncRowNum();
            }
        }

        //$s .= "<div id='collection-batch-germ-container'></div>";
        $s .= "<form method='post'><input type='submit'/>"
             ."<table>"
             ."<tr><th>Lot</th><th>Cultivar</th><th>Number Sown</th><th>Number Germ</th><th>Start Date</th><th>End Date</th><th>Notes</th></tr>"
             .$sBlank
             .$sMultiRow
             ."<tr><td colspan='8'>&nbsp;</td></tr>
               <tr><td colspan='8'>&nbsp;</td></tr>
               <tr><td colspan='8'><h4>Your Tests In Progress</h4></td></tr>
               {$sMine}"
             .($sOthers ? "<tr><td colspan='8'>&nbsp;</td></tr>
                           <tr><td colspan='8'><h4>Other Peoples' Tests In Progress</h4></td></tr>
                           {$sOthers}"
                        : "")
             ."</table></form>
               <p style='margin-top:30px'>{$this->sGermFeedback}</p>
               <p style='margin-top:30px;padding:10px;background-color:#ddd'>
                 1. Enter Lot #, number of seeds sown.<br/>
                 2. On first count enter number germinated.<br/>
                 3. If further counts, update number germinated<br/>
                 4. When all counts are done, set end date. Only records without end dates are shown here.
               </p>";

        return( $s );
    }

    // this is also in CollectionTab_GerminationTests
    private function deleteButton( KeyframeRecord $kfr, bool $bOverrideSelftest = false )
    /***************************************************
        Make a button that will delete the given germ test (only if it's your test)

        sf{cid}d{R} doesn't work the way we want it to here, so do it with a custom parameter instead
     */
    {
        $sDeleteButton = ($kfr->Key() && ($bOverrideSelftest || $kfr->Value('_created_by')==$this->oApp->sess->GetUID()))
                                ? ("<a href='{$this->oApp->PathToSelf()}?germdel={$kfr->Key()}'>"
                                  ."<img src='".SEEDW_URL."img/ctrl/delete01.png' height='20'/>"
                                  ."</a>")
                                : "";
        return( $sDeleteButton );
    }

    function PreStoreGerm( KeyFrame_DataStore $oDS )
    {
        if( $oDS->Key() ) {
            // The I_inv_num is there because the server filled it in on an existing germ record
            $iLot = ($ra = $this->oSLDB->GetRecordVals( 'GxIxAxPxS', $oDS->Key() )) ? $ra['I_inv_number'] : 0;      // really only need GxI

        } else {
            // The I_inv_num/XLotNum was typed into a new germ record.
            // Validate that it's an integer, then look up the Lot and set fk_sl_inventory

            if( !($iLot = $oDS->ValueInt('XLotNum')) ) {
                // somebody typed a non-integer?
                $this->oApp->oC->AddErrMsg( "Not processing lot # {$oDS->Value('XLotNum')}<br/>" );
                return( false );
            }
            if( !($kfrLot = $this->oSLDB->GetKFRCond('I', "inv_number='$iLot' AND fk_sl_collection='1'")) ) {
                $this->oApp->oC->AddErrMsg( "Lot # $iLot not found<br/>" );
                return( false );
            }

            $oDS->SetValue( 'fk_sl_inventory', $kfrLot->Key() );
        }

        $oDS->CastInt('nSown');
        $oDS->CastInt('nGerm_count');

        if( !$oDS->Value('nSown') ) {
            $this->oApp->oC->AddErrMsg( "Not recording record for lot # $iLot : 0 seeds sown<br/>" );
            return( false );
        }

        // if dStart not defined default to today
        if( !$oDS->Value('dStart') ) {
            //$this->oApp->oC->AddUserMsg( "Defaulting Lot # $iLot test to today's date<br/>" );
            $this->sGermFeedback .= "Defaulting Lot # $iLot test to today's date<br/>";
            $oDS->SetValue( 'dStart', date('Y-m-d') );
        }
        // if dEnd not defined yet it has to be NULL in the db because DATE doesn't allow ''
        if( !$oDS->Value('dEnd') ) {
            $oDS->SetNull('dEnd');
        }

// temp: nGerm is %, should be the count
        $oDS->SetValue( 'nGerm', $this->germPercent($oDS->Value('nSown'), $oDS->Value('nGerm_count')) );

        // if dEnd is set this record will not be shown in the list anymore
        if( $oDS->Value('dEnd') ) {
            $this->sGermFeedback .= "Saving Lot # $iLot, {$oDS->Value('dStart')} to {$oDS->Value('dEnd')}, "
                  ."germ {$oDS->ValueInt('nGerm_count')}/{$oDS->ValueInt('nSown')} ({$oDS->ValueInt('nGerm')}%) {$oDS->Value('notes')}<br/>";
        }

        return( true );
    }

    private function germPercent( $nSown, $nGerm_count )
    {
        return( $nSown ? intval(floatval($nGerm_count) / floatval($nSown) * 100.00) : 0 );
    }
}
