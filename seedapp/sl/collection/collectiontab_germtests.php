<?php

// alter table sl_germ add nGerm_count integer not null default 0;
// eventually just use nGerm_count and compute percentage

class CollectionTab_GerminationTests
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
        $this->oForm = new KeyframeForm($this->sldbCollection->GetKfrel("G"), 'G', ['DSParms'=>['fn_DSPreStore'=> [$this,'dsPreStore']]]);
        $this->oForm->Update();

        if( ($kDel = SEEDInput_Int('germdel')) && ($kfr = $this->sldbCollection->GetKFR('G', $kDel)) ) {
            $kfr->StatusSet( KeyframeRecord::STATUS_DELETED );
            $kfr->PutDBRow();
        }
    }

    function ControlDraw()
    {
        return( "" );
    }

    function ContentDraw()
    {
        $s = "<style>
              th { text-align:center }
              .germRowInput input {margin-top:5px;}
              </style>";

        if( !$this->kInventory ) goto done;

        $this->oForm->SetKFR( $this->sldbCollection->GetKfrel("G")->CreateRecord() );
        $s .= "<form method='post'>"
             .$this->oForm->Hidden( "fk_sl_inventory", ['value'=>$this->kInventory] )
             ."<table><tr><th>Start Date</th><th>End Date</th><th># Seeds Sown</th><th># Seeds Germinated</th><th>Notes</th></tr>"
             .$this->germRowInput( $this->oForm );

        $raKfrG = $this->sldbCollection->GetKfrel('G')->GetRecordSet( "fk_sl_inventory='{$this->kInventory}'",
                                                                      ['sSortCol'=>'dStart','bSortDown'=>true] );
        foreach( $raKfrG as $kfr ) {
            if( $kfr->value('dEnd') && $kfr->value('dEnd') != "0000-00-00" ) {
                if( $kfr->Key() < 999 ) {
                    // old germ tests: nGerm is percentage, so calculate nGerm_count so the correct value appears
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
        Make a button that will delete the given germ test (only if it's your test)

        sf{cid}d{R} doesn't work the way we want it to here, so do it with a custom parameter instead
     */
    {
        $sDeleteButton = ($kfr->Key() && $kfr->Value('_created_by')==$this->oApp->sess->GetUID())
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
