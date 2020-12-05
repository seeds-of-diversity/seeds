<?php

// alter table sl_germ add nGerm_count integer not null default 0;
// eventually just use nGerm_count and compute percentage

class CollectionTab_GerminationTests
{
    private $oApp;
    private $kInventory;
    private $sldbCollection;

    function __construct( SEEDAppConsole $oApp, $kInventory )
    {
        $this->oApp = $oApp;
        $this->kInventory = $kInventory;
        $this->sldbCollection = new SLDBCollection($oApp);
    }

    function Init()
    {
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

        $oForm = new KeyframeForm($this->sldbCollection->GetKfrel("G"), 'G', ['DSParms'=>['fn_DSPreStore'=> [$this,'dsPreStore']]]);
        $oForm->Update();
        $oForm->SetKFR( $this->sldbCollection->GetKfrel("G")->CreateRecord() );
        $s .= "<form method='post'>"
             .$oForm->Hidden( "fk_sl_inventory", ['value'=>$this->kInventory] )
             ."<table><tr><th>Start Date</th><th>End Date</th><th># Seeds Sown</th><th># Seeds Germinated</th><th>Notes</th></tr>"
             .$this->germRowInput( $oForm );

        $raKfrG = $this->sldbCollection->GetKfrel('G')->GetRecordSet( "fk_sl_inventory='{$this->kInventory}'",
                                                                      ['sSortCol'=>'dStart','bSortDown'=>true] );
        foreach( $raKfrG as $kfr ) {
            if( $kfr->value('dEnd') && $kfr->value('dEnd') != "0000-00-00" ) {
                $nGermPercent = $this->germPercent( $kfr->value('nSown'), $kfr->value('nGerm_count') );
                $nGermPercent = $nGermPercent ? "($nGermPercent%)" : "";
                $s .= $kfr->Expand( "<tr style='text-align:center' class='germTests'>"
                                   ."<td>[[dStart]]</td><td>[[dEnd]]</td><td>[[nSown]]</td><td>[[nGerm_count]] &nbsp;&nbsp; $nGermPercent</td>"
                                   ."<td style='text-align:left'>[[notes]]</td></tr>");
            } else {
                $oForm->IncRowNum();
                $oForm->SetKFR( $kfr );
                $s .= $this->germRowInput( $oForm );
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
               ."</tr>" );
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
        $oDS->SetValue( 'nGerm', $this->germPercent($oDS->Value('nSown'), $oDS->Value('nSown')) );

// could set fk_sl_inventory here instead of sending it via http

        return( true );
    }
}
