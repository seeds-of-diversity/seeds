<?php

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

        $s .= "<h3>Is nGerm the number or the percent in the db?</h3>";

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
                $s .= $kfr->Expand( "<tr style='text-align:center' class='germTests'><td>[[dStart]]</td><td>[[dEnd]]</td><td>[[nSown]]</td><td>[[nGerm]]</td><td style='text-align:left'>[[notes]]</td></tr>");
            } else {
                $oForm->IncRowNum();
                $oForm->SetKFR( $kfr );
                $s .= $this->germRowInput( $oForm );
            }
        }
        $s .= "</table></form>";

        done:
        return( $s );
    }

    private function germRowInput( KeyframeForm $oForm )
    {
        return( "<tr class='germRowInput'>{$oForm->HiddenKey()}"
                   ."<td>{$oForm->Date('dStart')}</td><td>{$oForm->Date('dEnd')}</td>"
                   ."<td>{$oForm->Text('nSown')}</td><td>{$oForm->Text('nGerm')}</td>"
                   ."<td>{$oForm->Text('notes','',['size'=>'40'])}</td>"
                   ."<td><input type='submit' /></td>"
               ."</tr>" );
    }

    function dsPreStore( Keyframe_DataStore $oDS )
    {
        // This is called before the record is written to the db. Adjust anything necessary and validate; return false to abort the write.

        if( !$oDS->Value('dStart') || !$oDS->Value('nSown') ) return( false );

        if( !$oDS->Value('dEnd') ) {
            // you don't have to give an end date but the DATE field in the db doesn't allow '', only NULL
            $oDS->SetNull('dEnd');
        }

        return( true );
    }
}
