<?php

class CollectionTab_Accession
{
    private $oApp;
    private $kInventory;
    private $sldbCollection;
    private $oForm;

    function __construct( SEEDAppConsole $oApp, $kInventory )
    {
        $this->oApp = $oApp;
        $this->kInventory = $kInventory;
        $this->sldbCollection = new SLDBCollection($oApp);
    }

    function Init()
    {
//use these to draw the form
        $oFormA = new KeyframeForm($this->sldbCollection->KFRel('AxPxS'), 'A', []);
        $oFormA->Update();
        $oFormI = new KeyframeForm($this->sldbCollection->KFRel('I'), 'I', []);
        $oFormI->Update();

/*
        $this->oForm = new KeyframeForm($this->sldbCollection->GetKfrel("G"), 'G', ['DSParms'=>['fn_DSPreStore'=> [$this,'dsPreStore']]]);
        $this->oForm->Update();

        if( ($kDel = SEEDInput_Int('germdel')) && ($kfr = $this->sldbCollection->GetKFR('G', $kDel)) ) {
            $kfr->StatusSet( KeyframeRecord::STATUS_DELETED );
            $kfr->PutDBRow();
        }
*/
    }

    function ControlDraw()
    {
        return("");
    }

    function ContentDraw()
    {
        $s = $sLeft = $sRight = "";

        if( !$this->kInventory ||
            !($kfrI = $this->sldbCollection->GetKFR('I', $this->kInventory)) ||
            !($kfrA = $this->sldbCollection->GetKFR('AxPxS', $kfrI->Value('fk_sl_accession'))) )
        {
            goto done;
        }

        $oFormA = new KeyframeForm($this->sldbCollection->KFRel('AxPxS'), 'A', []); // array('DSParms'=>array('fn_DSPreStore'=>array($this,'DSPreStore_Acc'))) );
        $oFormA->SetKFR($kfrA);

        /* Left side is the Accession information
         */
        $sLeft = $oFormA->HiddenKey()
                .(new SEEDFormExpand($oFormA))->ExpandForm($this->accForm());

        /* Right side is the Lot information
         */
        $kfrC = $this->sldbCollection->GetKFR('C', 1); // $this->oSCA->kCurrCollection   this app doesn't have multiple collections
        $nNextInv = $kfrC ? $kfrC->Value('inv_counter') : 0;

        if( ($kfrcI = $this->sldbCollection->GetKFRC('I', "fk_sl_accession='{$kfrA->Key()}'")) ) {
            $iRow = 0;
            $oFormI = new KeyframeForm($this->sldbCollection->KFRel('I'), 'I', []); // array('DSParms'=>array('fn_DSPreStore'=>array($this,'DSPreStore_Acc'))) );

            while( $kfrcI->CursorFetch() ) {
                $oFormI->SetKFR($kfrcI);
                $oFormI->SetRowNum($iRow++);
                $sRight .= $this->drawInvForm( $oFormI, $nNextInv );
            }
        }

        done:
        $s .= "<form method='post' action='".$this->oApp->PathToSelf()."'>"
             ."<table class='table' style='width:100%'><tr>"
             ."<td style='width:60%'>$sLeft</td>"
             ."<td>$sRight</td>"
             ."</tr></table>"
             ."<input type='hidden' name='pMode' value='editacc'/>"    // newacc goes to editacc
             ."</form>";

        return( $s );


        $s = "<style>
              th { text-align:center }
              .germRowInput input {margin-top:5px;}
              </style>";



        $this->oForm->SetKFR( $this->sldbCollection->GetKfrel("G")->CreateRecord() );
        $s .= "<form method='post'>"
             .$this->oForm->Hidden( "fk_sl_inventory", ['value'=>$this->kInventory] )
             ."<table><tr><th>Start Date</th><th>End Date</th><th># Seeds Sown</th><th># Seeds Germinated</th><th>Notes</th></tr>"
             .$this->germRowInput( $this->oForm );

        $raKfrG = $this->sldbCollection->GetKfrel('G')->GetRecordSet( "fk_sl_inventory='{$this->kInventory}'",
                                                                      ['sSortCol'=>'dStart','bSortDown'=>true] );
        foreach( $raKfrG as $kfr ) {
            if( $kfr->value('dEnd') && $kfr->value('dEnd') != "0000-00-00" ) {
                // nGerm is always the % and it has been verified correct. nGerm_count is only set by the new system so there are many nGerm_count==0
                if( !$kfr->Value('nGerm_count') && $kfr->Value('nGerm') > 0 ) {
                    // calculate nGerm_count so the correct value is used below
                    $kfr->SetValue('nGerm_count', ($kfr->Value('nGerm') * $kfr->Value('nSown')) / 100 );
                }
                $nGermPercent = $this->germPercent( $kfr->value('nSown'), $kfr->value('nGerm_count') );
                $nGermPercent = $nGermPercent ? "($nGermPercent%)" : "";
                $s .= $kfr->Expand( "<tr style='text-align:center' class='germTests'>"
                                   ."<td>[[dStart]]</td><td>[[dEnd]]</td><td>[[nSown]]</td><td>[[nGerm_count]] &nbsp;&nbsp; $nGermPercent</td>"
                                   ."<td style='text-align:left'>[[notes]]</td>"
                                   ."<td>&nbsp;</td>"   // space for the delete button
                                   ."<td style='padding-left:30px'>{$this->deleteButton($kfr)}</td>"
                                   ."</tr>");
            } else {
                $this->oForm->IncRowNum();
                $this->oForm->SetKFR( $kfr );
                $s .= $this->germRowInput( $this->oForm );
            }
        }
        $s .= "</table></form>";

        $s .= "<p style='margin-top:30px'>Required: Number seeds sown.</p><p>Start date defaults to today. Records shown until End Date set.</p>";

        //done:
        return($s);
    }

    private function accForm()
    {
        $s =  "<div id='myc_accform_static'>"

             ."|||BOOTSTRAP_TABLE(class='col-md-4'|class='col-md-8')
               ||| *Cultivar*                || [[Value:P_psp]] : [[Value:P_name]] ([[Value:P__key]])
               ||| *Original Name*           || [[Value:oname]]
               ||| *Grower/Source*           || [[Value:x_member]]
               ||| *Date Harvested*          || [[Value:x_d_harvest]]
               ||| *Notes* || &nbsp;
               ||| {replaceWith class='col-md-12'} <div style='border:1px solid #aaa;padding:5px'>[[Value:notes]]</div>
               ||| &nbsp;     || \n
               ||| *Grams original*   || [[Value:g_original]]
               ||| *Grams 100 seeds*   || [[Value:g_100]]
               ||| *Parent Lot #*   || [[Value:kLotParent]]
               ||| *Grower rating* || [[Value:iGrowerRating]]
               ||| <button onclick='doEdit()'>Edit</button> || \n"

             .($this->oApp->sess->GetUID() == 1499 ?
              "||| &nbsp; || \n
               ||| *--- deprecate ---* || \n
               ||| old Parent Lot #   || [[Value:parent_acc]]
               ||| Parent Desc    || [[Value:parent_src]]
               ||| Date Received (no parent lot) || [[Value:x_d_received]]
               ||| Batch || [[Value:batch_id]]
               ||| spec || [[Value:spec]]
               ||| location || [[Value:location]]
               ||| g have || [[Value:g_have]]
               ||| g pgrc || [[Value:g_pgrc]]
               ||| bDeAcc || [[Value:bDeAcc]]
               ||| psp_obsolete || [[Value:psp_obsolete]]"
              : "")
             ."|||ENDTABLE "

             ."</div>" // myc_accform_static

             ."<div id='myc_accform_edit' style='display:none'>"

             ."|||BOOTSTRAP_TABLE(class='col-md-4'|class='col-md-8')
               ||| <input type='submit' value='Save'> || \n
               ||| *Cultivar*                || <span id='cultivarText' style='font-size:9pt'>[[Value:P_psp]] : [[Value:P_name]] ([[Value:P__key]])</span>
                                                [[dummy_pcv | size:10 class:SFU_AutoComplete | placeholder='Search']]
                                                [[hidden:fk_sl_pcv]]
                                                <select class='SFUAC_Select'></select>
               ||| *Original Name*  || [[oname | width:100%]]
               ||| *Grower/Source*  || [[x_member | width:100%]]
               ||| *Date Harvested* || [[x_d_harvest | width:100%]]
               ||| *Notes* || &nbsp;
               ||| {replaceWith class='col-md-12'} [[Textarea:notes| width:100%]]
               ||| &nbsp;     || \n
               ||| *Grams original*   || [[g_original]]
               ||| *Grams 100 seeds*   || [[g_100]]
               ||| *Parent Lot #*   || [[kLotParent]]
               ||| *Grower rating* || [[iGrowerRating]]"

             .($this->oApp->sess->GetUID() == 1499 ?
              "||| &nbsp; || \n
               ||| *--- deprecate ---* || \n
               ||| old Parent Lot #   || [[parent_acc]]
               ||| Parent Desc    || [[parent_src | width:100%]]
               ||| Date Received (no parent lot) || [[x_d_received]]
               ||| Batch || [[batch_id]]
               ||| spec || [[spec]]
               ||| location || [[location]]
               ||| g have || [[g_have]]
               ||| g pgrc || [[g_pgrc]]
               ||| bDeAcc || [[bDeAcc]]
               ||| psp_obsolete || [[psp_obsolete]]"
              : "")
             ."|||ENDTABLE "

            ."</div>";

        $s .= "<script>
function doEdit()
{
    event.preventDefault();
    $('#myc_accform_static').hide();
    $('#myc_accform_edit').show();
}
</script>";

        return($s);
    }


    function drawInvForm( $oFormI, $nNextInv, $bShowDeacc = true, $bWeightRO = false, $bShowLoc = true )
    {
        $s = "";

        if( $oFormI->GetKey() ) {
            $sInvPrefix = ($kfrC = $this->sldbCollection->GetKFR('C', $oFormI->Value('fk_sl_collection'))) ? $kfrC->Value('inv_prefix') : "X";
        } else {
            goto done;

            // this is a blank form
            if( !$this->kCurrCollection )  return( "" );    // don't allow new inventory on All Collections

            $kfrC = $this->oSLDBMaster->GetKFR( "C", $this->kCurrCollection );
            $sNextInv = $kfrC ? ($kfrC->Value('inv_prefix')."-".($nNextInv++)) : "unknown";
        }

        $s = "<fieldset>" //"<DIV style='border:1px solid #333;margin:20px;padding:10px;'>"
            ."<legend>".($oFormI->GetKey() ? ("Lot # $sInvPrefix-".$oFormI->Value('inv_number'))
                                           : "Add New Lot <span style='font-size:10pt'>( next number is $sNextInv )</span>" )
            ."</legend>"
            .$oFormI->HiddenKey()
            .$oFormI->Hidden( 'fk_sl_collection' )
            .$oFormI->Hidden( 'fk_sl_accession' )

                .(new SEEDFormExpand($oFormI))->ExpandForm(
                     "|||TABLE(border='0')
                      ||| Weight (g)    || ".($bWeightRO ? "[[Value:g_weight]]" : "[[g_weight]]")
                    .($bShowLoc ? "||| Location      || [[location]]" : "")
." ".($oFormI->GetKey() ? ($this->oApp->kfdb->Query1( "SELECT loc_old FROM sl_inventory WHERE _key='".$oFormI->GetKey()."'")) : "")
//                    ."||| Split from    || [[parent_kInv]]"
//                    ."||| Split date    || [[dCreation]]"
                    .($bShowDeacc ? "||| Deaccessioned || [[bDeAcc]]" : "")
                    ."|||ENDTABLE"
                 )
             ."</fieldset><P>&nbsp;</P>";

        done:
        return( $s );
    }



    private function germPercent( $nSown, $nGerm_count )
    {
        return( $nSown ? intval(floatval($nGerm_count) / floatval($nSown) * 100.00) : 0 );
    }

    private function germRowInput( KeyframeForm $oForm )
    {
        // blank looks nicer than zero
        if( !$oForm->Value('nSown') ) $oForm->SetValue('nSown', '');
        if( !$oForm->Value('nGerm_count') ) $oForm->SetValue('nGerm_count', '');

        return( "<tr class='germRowInput'>{$oForm->HiddenKey()}"
                   ."<td>{$oForm->Date('dStart')}</td><td>{$oForm->Date('dEnd')}</td>"
                   ."<td>{$oForm->Text('nSown')}</td><td>{$oForm->Text('nGerm_count')}</td>"
                   ."<td>{$oForm->Text('notes','',['size'=>'40'])}</td>"
                   ."<td><input type='submit' value='Save' /></td>"
                   ."<td style='padding-left:30px'>{$this->deleteButton($oForm->GetKFR())}</td>"
               ."</tr>" );
    }

    private function deleteButton( KeyframeRecord $kfr )
    /***************************************************
        Make a button that will delete the given germ test (only if it's your test, and it's pretty recent)

        sf{cid}d{R} doesn't work the way we want it to here, so do it with a custom parameter instead
     */
    {
        $bRecent  = ($t = strtotime($kfr->Value('_created'))) > 1000000     // probably a valid timestamp
                    && time() - $t < (3600*24*60);                          // past 60 days

        $sDeleteButton = ($kfr->Key() && $kfr->Value('_created_by')==$this->oApp->sess->GetUID()
                                      && $bRecent)
                                ? ("<a href='{$this->oApp->PathToSelf()}?germdel={$kfr->Key()}'>"
                                  ."<img src='".SEEDW_URL."img/ctrl/delete01.png' height='20'/>"
                                  ."</a>")
                                : "";
        return( $sDeleteButton );
    }

    function dsPreStore( Keyframe_DataStore $oDS )
    {
// this should have more in common with CollectionBatchOps::PreStoreGerm
        $oDS->CastInt('nSown');
        $oDS->CastInt('nGerm');

        // nSown is the only required field
        if( !$oDS->Value('nSown') ) return( false );

        // if dStart not defined default to today
        if( !$oDS->Value('dStart') ) {
            $oDS->SetValue( 'dStart', date('Y-m-d') );
        }
        // if dEnd not defined yet it has to be NULL in the db because DATE doesn't allow ''
        if( !$oDS->Value('dEnd') ) {
            $oDS->SetNull('dEnd');
        }
// temp: nGerm is %, should be the count
        $oDS->SetValue( 'nGerm', $this->germPercent($oDS->Value('nSown'), $oDS->Value('nGerm_count')) );

// could set fk_sl_inventory here instead of sending it via http

        return( true );
    }
}
