<?php

class CollectionTab_Accession
{
    private $oApp;
    private $kInventory;
    private $sldbCollection;
    private $oFormA = null;    // kfr AxPxS for the displayed Accession
    private $kfrI = null;      // kfr I for current Lot ($kInventory)

    function __construct( SEEDAppConsole $oApp, $kInventory )
    {
        $this->oApp = $oApp;
        $this->kInventory = $kInventory;
        $this->sldbCollection = new SLDBCollection($oApp);
    }

    function Init()
    {
        /* Accession and Lot updates are submitted in the same <form>.
         * There can be multiple Lot records, including but not limited to kInventory.
         * oFormI is not persisted because it is only used for drawing forms later and needs to be reloaded per Lot
         */
        ($this->oFormA = new KeyframeForm($this->sldbCollection->KFRel('AxPxS'), 'A', ['DSParms'=>['fn_DSPreStore'=> [$this,'dsPreStoreA']]]))
            ->Update();

        (new KeyframeForm($this->sldbCollection->KFRel('I'), 'I', ['DSParms'=>['fn_DSPreStore'=> [$this,'dsPreStoreI']]]))
            ->Update();

        /* Fetch Accession and Lot for current kInventory. Set up oFormA. It might already be loaded correctly from Update() above, but not necessarily.
         */
        if( $this->kInventory &&
            ($this->kfrI = $this->sldbCollection->GetKFR('I', $this->kInventory)) &&
            ($kfrA = $this->sldbCollection->GetKFR('AxPxS', $this->kfrI->Value('fk_sl_accession'))) )
        {
            $this->oFormA->SetKFR($kfrA);
        } else {
            $this->oFormA->Clear();
        }
    }

    function dsPreStoreA( Keyframe_DataStore $oDS )
    {
        // the form replaces kLotParent with iLotParent. Reverse that replacement.
        if( ($iLotParent = $oDS->Value('iLotParent')) &&
// parameterize kColl
            ($kfr = $this->sldbCollection->GetKFR_LotFromNumber(1, $iLotParent)) )
        {
            $oDS->SetValue('kLotParent', $kfr->Key());
        }

        return(true);
    }

    /**
     * Multiple lots can be saved from <form>, including but not limited to $this->kInventory.
     */
    function dsPreStoreI( Keyframe_DataStore $oDS )
    {
        if(!$oDS->Value('g_weight')) $oDS->SetValue('g_weight',0.0);    // db needs this to be 0.0 if the user enters blank

        return(true);
    }

    function ControlDraw()
    {
        return("");
    }

    function ContentDraw()
    {
        $s = $sLeft = $sRight = "";

        if( !$this->kInventory || !$this->oFormA->GetKey() )  goto done;

        /* Left side is the Accession information
         */
        $sLeft = $this->oFormA->HiddenKey()
                .(new SEEDFormExpand($this->oFormA))->ExpandForm($this->accForm());

        /* Right side is the Lot information
         */
        $kfrC = $this->sldbCollection->GetKFR('C', 1); // $this->oSCA->kCurrCollection   this app doesn't have multiple collections
        $nNextInv = $kfrC ? $kfrC->Value('inv_counter') : 0;

        if( ($kfrcI = $this->sldbCollection->GetKFRC('I', "fk_sl_accession='{$this->oFormA->GetKey()}'")) ) {
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
    }

    private function accForm()
    {
        // add a dummy field iLotParent (sl_inventory.inv_number) to the form instead of kLotParent (sl_inventory._key)
        list($iLotParent,$kCollDummy) = $this->sldbCollection->Get_LotNumberFromKey($this->oFormA->Value('kLotParent'));
        $this->oFormA->SetValue('iLotParent',$iLotParent);

        $sCultivarInfo = [];
        if( ($kPcv = $this->oFormA->Value('fk_sl_pcv')) &&
            ($rQ = (new QServerRosetta($this->oApp, ['config_bUTF8'=>false]))->Cmd('rosetta-cultivarinfo', ['kPcv'=>$kPcv, 'mode'=>'all'])) &&
            $rQ['bOk'] )
        {
            $sCultivarInfo =
                 // display:inline-block fits the div's width to its content so it centers the content (because content doesn't know to center itself)
                 "<div style='background-color:#eee;padding:1em;text-align:center'>
                      <h3>About {$rQ['raOut']['PxS']['P_name']} {$rQ['raOut']['PxS']['S_name_en']}</h3>
                      <p>{$rQ['raOut']['PxS']['P_packetLabel']}</p>
                      <p>".nl2br($rQ['raOut']['PxS']['P_notes'])."</p>
                      <h3>Collection Status of {$rQ['raOut']['PxS']['P_name']} {$rQ['raOut']['PxS']['S_name_en']}</h3>
                      <div style='margin:0 auto;display:inline-block'>{$rQ['raOut']['sTable_IxA']}</div>
                  </div>";


        }

        $s =  "<div class='container-fluid'>
               <div class='myc_accform_static'>"

             ."|||BOOTSTRAP_TABLE(class='col-md-4'|class='col-md-8')
               ||| *Cultivar*                || [[Value:P_psp]] : [[Value:P_name]] ([[Value:P__key]])
               ||| *Original Name*           || [[Value:oname]]
               ||| *Grower/Source*           || [[Value:x_member]]
               ||| *Date Harvested*          || [[Value:x_d_harvest]]
               ||| *Notes* || &nbsp;
               ||| {replaceWith class='col-md-12'} <div style='border:1px solid #aaa;padding:5px'>[[nl2br: [[Value:notes]] ]]</div>
               ||| &nbsp;     || \n
               ||| *Grams original*   || [[Value:g_original]]
               ||| *Grams 100 seeds*   || [[Value:g_100]]
               ||| *Parent Lot #*   || [[Value:iLotParent]]
               ||| *Grower rating* || [[Value:iGrowerRating]]
               ||| <div id='editbutton'><button onclick='doEdit()'>Edit</button></div> &nbsp; || \n"

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

             .$sCultivarInfo
             ."</div>" // myc_accform_static

             ."<div class='myc_accform_edit' style='display:none'>"

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
               ||| *Parent Lot #*   || [[iLotParent]]
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

            .$sCultivarInfo
            ."</div>
              </div>";

        $s .= "<script>
function doEdit()
{
    event.preventDefault();
    $('.myc_accform_static').hide();
    $('.myc_accform_edit').show();
    $('#editbutton').html('');      /* remove the edit button so typing Enter in text field goes to Save instead of this (even though it's hidden) */
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

        $s = "<div class='myc_accform_edit' style='display:none'>"
            ."<fieldset>" //"<DIV style='border:1px solid #333;margin:20px;padding:10px;'>"
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
             ."</fieldset></div>";

        $s .=  "<div class='myc_accform_static'>"
            ."<fieldset>" //"<DIV style='border:1px solid #333;margin:20px;padding:10px;'>"
            ."<legend>".($oFormI->GetKey() ? ("Lot # $sInvPrefix-".$oFormI->Value('inv_number'))
                                           : "Add New Lot <span style='font-size:10pt'>( next number is $sNextInv )</span>" )
            ."</legend>"

                .(new SEEDFormExpand($oFormI))->ExpandForm(
                     "|||TABLE(border='0')
                      ||| Weight (g)    || [[Value:g_weight]]"
                    .($bShowLoc ? "||| Location      || [[Value:location]]" : "")
." ".($oFormI->GetKey() ? ($this->oApp->kfdb->Query1( "SELECT loc_old FROM sl_inventory WHERE _key='".$oFormI->GetKey()."'")) : "")
//                    ."||| Split from    || [[parent_kInv]]"
//                    ."||| Split date    || [[dCreation]]"
                    .($bShowDeacc ? "||| Deaccessioned &nbsp;&nbsp; || [[Value:bDeAcc]]" : "")
                    ."|||ENDTABLE"
                 )
             ."</fieldset></div><P>&nbsp;</P>";


        done:
        return( $s );
    }
}
