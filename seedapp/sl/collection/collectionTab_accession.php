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

        $oFormA = new KeyframeForm( $this->sldbCollection->KFRel('AxPxS'), 'A', [] ); // array('DSParms'=>array('fn_DSPreStore'=>array($this,'DSPreStore_Acc'))) );
        $oFormA->SetKFR($kfrA);

// $this already has a Console01, maybe it can be a Console01KFUI?
$raParms = array();


        $oFormAExpand = new SEEDFormExpand($oFormA);
        $sLeft = "<table border='0' cellpadding='0' width='90%' style='position:relative' class='SFUAC_Anchor'>"
             .$oFormA->HiddenKey()
             .$oFormAExpand->ExpandForm($this->accForm())
           ."</table>";

goto done;
        if( $this->oFormA->GetKey() ) {
            $raKFR = $this->kfrelI->GetRecordSet( "A._key='".$this->oFormA->GetKey()."'" );
            $raKFR[] = $this->kfrelI->CreateRecord();
        } else {
            // New Accession:  make two empty inventory subforms
            $raKFR = array();
            $raKFR[] = $this->kfrelI->CreateRecord();
            $raKFR[] = $this->kfrelI->CreateRecord();
        }

        $kfrC = $this->oSCA->oSLDBMaster->GetKFR( "C", $this->oSCA->kCurrCollection );
        $nNextInv = $kfrC ? $kfrC->Value('inv_counter') : 0;

        $iRow = 0;
        foreach( $raKFR as $kfr ) {

            $this->oFormI->SetKFR( $kfr );
            $this->oFormI->SetRowNum( $iRow++ );

            $this->oFormI->SetValue( 'fk_sl_accession', $this->oFormA->GetKey() );

            $sRight .= $this->oSCA->drawInvForm( $this->oFormI, $nNextInv );
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
        $s =  "<div class='container-fluid'><div class='row'><div class='col-md-6'>"

             ."|||BOOTSTRAP_TABLE(class='col-md-4'|class='col-md-8')
               ||| *Cultivar*                || [[Value:P_psp]] : [[Value:P_name]] ([[Value:P__key]])
               ||| *Original Name*           || [[Value:oname]]
               ||| *Grower/Source*           || [[Value:x_member]]
               ||| *Date Harvested*          || [[Value:x_d_harvest]]
               ||| *Notes* || &nbsp;
               ||| {replaceWith class='col-md-12'} [[Textarea:notes| width:100% | readonly]]
               ||| &nbsp;     || \n
               ||| *Grams original*   || [[Value:g_original]]
               ||| *Grams 100 seeds*   || [[Value:g_100]]
               ||| *Parent Lot #*   || [[Value:kLotParent]]
               ||| old Parent Lot #   || [[Value:parent_acc]]
               ||| Parent Desc    || [[Value:parent_src]]
               ||| Date Received (no parent lot) || [[Value:x_d_received]]
               ||| Batch || [[Value:batch_id]]
               ||| *Grower rating* || [[Value:iGrowerRating]]
               ||| &nbsp;     || \n
               ||| spec || [[Value:spec]]
               ||| location || [[Value:location]]
               ||| g have || [[Value:g_have]]
               ||| g pgrc || [[Value:g_pgrc]]
               ||| bDeAcc || [[Value:bDeAcc]]
               ||| psp_obsolete || [[Value:psp_obsolete]]
               |||ENDTABLE "

             ."</div><div class='col-md-6'>"

             ."|||BOOTSTRAP_TABLE(class='col-md-4'|class='col-md-8')
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
               ||| old Parent Lot #   || [[parent_acc]]
               ||| Parent Desc    || [[parent_src | width:100%]]
               ||| Date Received (no parent lot) || [[x_d_received]]
               ||| Batch || [[batch_id]]
               ||| *Grower rating* || [[iGrowerRating]]
               ||| &nbsp;     || \n
               ||| spec || [[spec]]
               ||| location || [[location]]
               ||| g have || [[g_have]]
               ||| g pgrc || [[g_pgrc]]
               ||| bDeAcc || [[bDeAcc]]
               ||| psp_obsolete || [[psp_obsolete]]
               |||ENDTABLE "
            ."</div>
              </div>
              <input type='submit' value='Save'>
              </div>";

        return($s);
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
