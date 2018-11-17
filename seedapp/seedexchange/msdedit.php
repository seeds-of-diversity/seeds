<?php

/* msdedit
 *
 * Copyright (c) 2018 Seeds of Diversity
 *
 * App to edit Member Seed Directory, built on top of SEEDBasket
 */

// for the most part, msd apps try to access seedlib/msd via MSDQ()
include_once( SEEDLIB."msd/msdq.php" );

class MSDAppSeedEdit
/*******************
    Show a list of seed listings with a UI to modify it
 */
{
    private $oSB;

    function __construct( SEEDBasketCore $oSB )
    {
        $this->oSB = $oSB;
    }

    function Draw()
    {
        $s = "";
        $sForm = $sList = "";

        $oProdHandler = $this->oSB->GetProductHandler( "seeds" ) or die( "Seeds ProductHandler not defined" );
        $oMSDQ = new MSDQ( $this->oSB->oApp, array() );
        $oMSDCore = new MSDCore( $this->oSB->oApp, array() );

        $raSeeds = array();
//        $kfrcP = $this->oC->oSB->oDB->GetKFRC( "PxPE3", "product_type='seeds' AND uid_seller='1' "
//                                                   ."AND PE1.k='category' "
//                                                   ."AND PE2.k='species' "
//                                                   ."AND PE3.k='variety' ",
//                                                   array('sSortCol'=>'PE1_v,PE2_v,PE3_v') );
        if( ($kfrcP = $oMSDCore->SeedCursorOpen( "uid_seller='1'" )) ) {
            $category = "";
            while( $oMSDCore->SeedCursorFetch( $kfrcP ) ) { // $kfrcP->CursorFetch() ) {
                $kP = $kfrcP->Key();
                $bCurr = false; //($kCurrProd && $kfrcP->Key() == $kCurrProd);
                $sStyleCurr = $bCurr ? "border:2px solid blue;" : "";
                if( $category != $kfrcP->Value('PE1_v') ) {
                    $category = $kfrcP->Value('PE1_v');
                    $sList .= "<div><h2>".@$oMSDCore->GetCategories()[$category]['EN']."</h2></div>";
                }
                $sButtonSkip   = $kfrcP->Value('eStatus') == 'INACTIVE' ? "Un-Skip" : "Skip";
                $sButtonDelete = $kfrcP->Value('eStatus') == 'DELETED' ? "Un-Delete" : "Delete";
                $sList .= "<div id='msdSeed$kP' class='well msdSeedContainer' style='margin:5px'>"
                             ."<div class='msdSeedEditButtonContainer' style='float:right'>"
                                 ."<button class='msdSeedEditButton_edit' id='msdSeedEditButton_edit$kP'>Edit</button><br/>"
                                 ."<button class='msdSeedEditButton_skip' id='msdSeedEditButton_skip$kP'>$sButtonSkip</button><br/>"
                                 ."<button class='msdSeedEditButton_delete' id='msdSeedEditButton_delete$kP'>$sButtonDelete</button></div>"
                             ."<div class='msdSeedMsg'></div>"
                             ."<div class='msdSeedText' style='padding:0px;$sStyleCurr'>"
                                 .$this->oSB->DrawProduct( $kfrcP, SEEDBasketProductHandler_Seeds::DETAIL_EDIT_WITH_SPECIES )
                             ."</div>"
                         ."</div>";
                $raSeeds[$kP] = $oProdHandler->GetProductValues( $kfrcP );
            }
        }


        if( $sForm ) {
            $sForm = "<form method='post'>"
                    ."<div>$sForm</div>"
                    ."<div><input type='submit' value='Save' style='margin:20px 0px 0px 20px'/></div>"
                    ."</form>";
        }
        $s = "<div class='container-fluid'><div class='row'>"
                ."<div class='col-sm-9'>$sList</div>"
                ."<div class='col-sm-3'>$sForm</div>"
            ."</div></div>";

//$s .= $this->oC->oSB->DrawProductNewForm( 'base-product' );
//$s .= $this->oC->oSB->DrawProductNewForm( 'membership' );
//$s .= $this->oC->oSB->DrawProductNewForm( 'donation' );
//$s .= $this->oC->oSB->DrawProductNewForm( 'book' );
//$s .= $this->oC->oSB->DrawProductNewForm( 'seeds' );

$msdSeedEditForm = <<<msdSeedEditForm
    <table><tr>
        <td><input type='text' id='msdSeedEdit_species' name='species' class='msdSeedEdit_inputText' placeholder='Species e.g. LETTUCE'/></td><td>&nbsp;</td>
    </tr><tr>
        <td><input type='text' id='msdSeedEdit_variety' name='variety' class='msdSeedEdit_inputText' placeholder='Variety e.g. Grand Rapids'/></td><td>&nbsp;</td>
    </tr><tr>
        <td><input type='text' id='msdSeedEdit_bot_name' name='bot_name' class='msdSeedEdit_inputText' placeholder='botanical name (optional)'/></td><td>&nbsp;</td>
    </tr><tr>
        <td colspan='2'><textarea style='width:100%' rows='4' id='msdSeedEdit_description' name='description' placeholder='Describe the produce, the plant, how it grows, and its uses'></textarea></td>
    </tr><tr>
        <td><input type='text' id='msdSeedEdit_days_maturity' name='days_maturity' size='5'/>&nbsp;&nbsp;&nbsp;<input type='text' id='msdSeedEdit_days_maturity_seeds' name='days_maturity_seeds' size='5'/></td>
        <td><div class='msdSeedEdit_instruction'><b>Days to maturity</b>: In the first box, estimate how many days after sowing/transplanting it takes for the produce to ripen for best eating. In the second box estimate the number of days until the seed is ripe to harvest. Leave blank if not applicable.</div></td>
    </tr><tr>
        <td><input type='text' id='msdSeedEdit_origin' name='origin' class='msdSeedEdit_inputText'/></td>
        <td><div class='msdSeedEdit_instruction'><b>Origin</b>: Record where you got the original seeds. e.g. another member, a seed company, a local Seedy Saturday.</div></td>
    </tr><tr>
        <td><select id='msdSeedEdit_quantity' name='quantity'><option value=''></option><option value='LQ'>Low Quantity</option><option value='PR'>Please Re-offer</option></select></td>
        <td><div class='msdSeedEdit_instruction'><b>Quantity</b>: If you have a low quantity of seeds, or if you want to ask requestors to re-offer seeds, indicate that here.</div></td>
    </tr><tr>
        <td><select id='msdSeedEdit_eOffer' name='eOffer'><option value='member'>All Members</option><option value='grower-member'>Only members who also list seeds</option><option value='public'>General public</option></select></td>
        <td><p class='msdSeedEdit_instruction'><b>Who may request these seeds from you</b>: <span id='msdSeedEdit_eOffer_instructions'></span></p></td>
    </tr><tr>
        <td><nobr>$<input type='text' id='msdSeedEdit_price' name='price' class='msdSeedEdit_inputText'/></nobr></td>
        <td><div class='msdSeedEdit_instruction'><b>Price</b>: We recommend $3.50 for seeds and $12.00 for roots and tubers. That is the default if you leave this field blank. Members who offer seeds (like you!) get an automatic discount of $1 per item.</div></td>
    </tr></table>
    <input type='submit' value='Save'/> <button class='msdSeedEditCancel' type='button'>Cancel</button>
msdSeedEditForm;
$msdSeedEditForm = str_replace("\n","",$msdSeedEditForm);   // jquery doesn't like linefeeds in its selectors

$s .= <<<basketStyle
<style>
.msdSeedText_species { font-size:14pt; font-weight:bold; }
.sed_seed_offer              { font-size:10pt; padding:2px; background-color:#fff; }
.sed_seed_offer_member       { color: #484; border:2px solid #484 }
.sed_seed_offer_growermember { color: #08f; border:2px solid #08f }
.sed_seed_offer_public       { color: #f80; border:2px solid #f80 }
.sed_seed_mc     { font-weight:bold;text-align:right }

.msdSeedEdit { width:100%;display:none;margin-top:5px;padding-top:10px;border-top:1px dashed #888 }
.msdSeedEdit_inputText   { width:95%;margin:3px 0px }
.msdSeedEdit_instruction { background-color:white;border:1px solid #aaa;margin:3px 0px 0px 30px;padding:3px }
.msdSeedEditButtonContainer { text-align:center;margin-left:20px;width:10%;max-width:100px; }
</style>
basketStyle;

$s .= <<<basketScript
<script>
var msdSeedEditVars = { qUrl:"", raSeeds:[] };
var msdSeedContainerCurr = null;  // the current msdSeedContainer

$(document).ready( function() {
    // on click of an msdSeedText or an Edit button, open the edit form
    $(".msdSeedText, .msdSeedEditButton_edit").click( function(e) {
        SeedEditForm( $(this).closest(".msdSeedContainer").attr("id") );
    });
});

function SeedEditForm( id )
/**************************
    Open the edit form for a clicked seed listing.
    Input is the verbatim id of the msdSeedContainer
 */
{
    // only one msdSeedContainer can be selected at a time
    if( msdSeedContainerCurr != null ) { console.log("Cannot open multiple forms"); return; }

    // validate that this is an msdSeedContainer and get the kProduct
    let k = 0;
    if( id.substring(0,7) != 'msdSeed' || !(k=parseInt(id.substring(7))) ) { console.log("Invalid id "+id); return; }

    // clear previous edit indicators
    $(".msdSeedContainer").css({border:"1px solid #e3e3e3"});
    $(".msdSeedMsg").html("");


    msdSeedContainerCurr = $("#"+id);
    msdSeedContainerCurr.css({border:"1px solid blue"});

    // Create a form and it inside msdSeedContainer, after msdSeedText. It is initially non-displayed, but fadeIn shows it.
    let msdSeedEdit = $("<div class='msdSeedEdit'><form>$msdSeedEditForm</form></div>");
    msdSeedContainerCurr.append(msdSeedEdit);

    SeedEditSelectEOffer( msdSeedEdit );
    msdSeedEdit.find('#msdSeedEdit_eOffer').change( function() { SeedEditSelectEOffer( msdSeedEdit ); } );

    for( var i in msdSeedEditVars.raSeeds[k] ) {
        msdSeedEdit.find('#msdSeedEdit_'+i).val(msdSeedEditVars.raSeeds[k][i]);
    }
    msdSeedEdit.fadeIn(500);

    msdSeedEdit.find("form").submit( function(e) { e.preventDefault(); SeedEditSubmit(k); } );
    msdSeedEdit.find(".msdSeedEditCancel").click( function(e) { e.preventDefault(); SeedEditCancel(); } );

    // disable all control buttons while the form is open
    $(".msdSeedEditButtonContainer button").attr("disabled","disabled");
}

function SeedEditSelectEOffer( msdSeedEdit )
{
    switch( msdSeedEdit.find("#msdSeedEdit_eOffer").val() ) {
        default:
        case 'member':
            msdSeedEdit.find('#msdSeedEdit_eOffer_instructions').html( "Only members of Seeds of Diversity will be able to request these seeds. Although the listing will be visible to the public, your contact information will only be available to members." );
            break;
        case 'grower-member':
            msdSeedEdit.find('#msdSeedEdit_eOffer_instructions').html( "Only members of Seeds of Diversity <b>who also list seeds in this directory</b> will be able to request these seeds. Although the listing will be visible to the public, your contact information will only be available to members." );
            break;
        case 'public':
            msdSeedEdit.find('#msdSeedEdit_eOffer_instructions').html( "Anyone who visits the online Seed Directory will be able to request these seeds, whether or not they are a member of Seeds of Diversity. <b>Your name and contact information will be visible to the public.</b> The printed Seed Directory is still only available to members." );
            break;
     }
}

function SeedEditSubmit(k)
{
    if( msdSeedContainerCurr == null ) return;

    let p = "cmd=msdSeed--Update&kS="+k+"&"+msdSeedContainerCurr.find('select, textarea, input').serialize();
    //alert(p);

    let oRet = SEEDJXSync( msdSeedEditVars.qURL+"basketJX.php", p );
    console.log(oRet);
    let ok = oRet['bOk'];

    if( ok ) {
        // raOut contains the validated seed data as stored in the database - save that here so it appears if you open the form again
        msdSeedEditVars.raSeeds[k]=oRet['raOut'];

        // sOut contains the revised msdSeedText
        msdSeedContainerCurr.find(".msdSeedText").html( oRet['sOut'] );

        SeedEditClose( ok );
    } else {
        // show the error and leave the form open
        msdSeedContainerCurr.find(".msdSeedMsg").html(
            "<div class='alert alert-danger' style='font-size:10pt;margin-bottom:5px;padding:3px 10px;display:inline-block'>"+oRet['sErr']+"</div>" );
    }

    return( ok );
}

function SeedEditCancel()
{
    SeedEditClose( false );
}

function SeedEditClose( ok )
{
    if( msdSeedContainerCurr == null ) return;

    msdSeedEdit = msdSeedContainerCurr.find('.msdSeedEdit');
    msdSeedEdit.fadeOut(500, function() {
            msdSeedEdit.remove();      // wait for the fadeOut to complete before removing the msdSeedEdit
            if( ok ) {
                // do this after fadeOut because it looks better afterward
                msdSeedContainerCurr.find(".msdSeedMsg").html( "<div class='alert alert-success' style='font-size:10pt;margin-bottom:5px;padding:3px 10px;display:inline-block'>Saved</div>" );
            }
            // allow another block to be clicked
            msdSeedContainerCurr = null;
        } );

    // re-enable all control buttons
    $(".msdSeedEditButtonContainer button").removeAttr("disabled");
}

</script>
basketScript;

        // Set parameters for msdSeedEdit. These are initialized to blank, required before you click on anything
        $s .= "<script>
               msdSeedEditVars.raSeeds = ".json_encode($raSeeds).";
               msdSeedEditVars.qURL = '".SITEROOT_URL."app/q/';
               </script>";

        return( $s );
    }

}
