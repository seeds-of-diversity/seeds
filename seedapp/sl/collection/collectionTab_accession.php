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
        $oFormI = new KeyframeForm($this->sldbCollection->KFRel('I'), 'I', ['DSParms'=>['fn_DSPreStore'=> [$this,'dsPreStoreI']]]);
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

    function dsPreStoreI(Keyframe_DataStore $oDS)
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

// do this in construct and SetKFR(A) there so $this->oFormA is available in accForm()
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
    }

    private function accForm()
    {
        $parentLot = "";    // inv_number from GetKFR('I',$this->oFormA->Value('kLotParent'))

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
               ||| *Parent Lot #*   || [[Value:kLotParent]]
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
