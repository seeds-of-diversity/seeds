<?php

/* msdedit
 *
 * Copyright (c) 2018-2019 Seeds of Diversity
 *
 * App to edit Member Seed Directory, built on top of SEEDBasket
 */

/*
update seeds.SEEDBasket_ProdExtra set v='flowers' where v='FLOWERS AND WILDFLOWERS' and k='category';
update seeds.SEEDBasket_ProdExtra set v='vegetables' where v='VEGETABLES' and k='category';
update seeds.SEEDBasket_ProdExtra set v='fruit' where v='FRUIT' and k='category';
update seeds.SEEDBasket_ProdExtra set v='herbs' where v='HERBS AND MEDICINALS' and k='category';
update seeds.SEEDBasket_ProdExtra set v='grain' where v='GRAIN' and k='category';
update seeds.SEEDBasket_ProdExtra set v='trees' where v='TREES AND SHRUBS' and k='category';
update seeds.SEEDBasket_ProdExtra set v='misc' where v='MISC' and k='category';
 */



// for the most part, msd apps try to access seedlib/msd via MSDQ()
include_once( SEEDLIB."msd/msdq.php" );
include_once( SEEDAPP."seedexchange/msdCommon.php" );   // DrawMSDList() should be a seedlib thing

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

    private $sContainer =
        "<div class='well msdSeedContainer' data-kproduct='[[kP]]' style='margin:5px'>"
           ."<div class='msdSeedEditButtonContainer' style='float:right'>"
               ."<button class='msdSeedEditButton_edit' style='display:none'>Edit</button><br/>"
               ."<button class='msdSeedEditButton_skip' style='display:none'>Skip</button><br/>"
               ."<button class='msdSeedEditButton_delete' style='display:none'>Delete</button></div>"
           ."<div class='msdSeedMsg'></div>"
           ."<div class='msdSeedText' style='padding:0px'>[[sSeedText]]</div>"
       ."</div>";

    function Draw( $uidSeller )
    {
        $s = "";
        $sForm = $sList = "";

        if( !$uidSeller )  $uidSeller = $this->oSB->oApp->sess->GetUID();

        $oProdHandler = $this->oSB->GetProductHandler( "seeds" ) or die( "Seeds ProductHandler not defined" );
        $oMSDQ = new MSDQ( $this->oSB->oApp, array() );
        $oMSDCore = new MSDCore( $this->oSB->oApp, array() );

        $sList .= "<h3>".$this->oSB->oApp->kfdb->Query1( "SELECT mbr_code FROM seeds.sed_curr_growers WHERE mbr_id='$uidSeller'" )." : "
                 .utf8_encode($oMSDCore->GetGrowerName($uidSeller))."</h3><hr/>";

        $raSeeds = array();
//        $kfrcP = $this->oC->oSB->oDB->GetKFRC( "PxPE3", "product_type='seeds' AND uid_seller='1' "
//                                                   ."AND PE1.k='category' "
//                                                   ."AND PE2.k='species' "
//                                                   ."AND PE3.k='variety' ",
//                                                   array('sSortCol'=>'PE1_v,PE2_v,PE3_v') );
        if( ($kfrcP = $oMSDCore->SeedCursorOpen( "uid_seller='$uidSeller'" )) ) {
            $category = "";
            while( $oMSDCore->SeedCursorFetch( $kfrcP ) ) { // $kfrcP->CursorFetch() ) {
                $kP = $kfrcP->Key();
                if( $category != $kfrcP->Value('PE1_v') ) {
                    $category = $kfrcP->Value('PE1_v');
                    $sList .= "<div><h2>".@$oMSDCore->GetCategories()[$category]['EN']."</h2></div>";
                }
//                $sButtonSkip   = $kfrcP->Value('eStatus') == 'INACTIVE' ? "Un-Skip" : "Skip";
//                $sButtonDelete = $kfrcP->Value('eStatus') == 'DELETED' ? "Un-Delete" : "Delete";

                $sC = $this->sContainer;
                $sC = str_replace( '[[kP]]', $kP, $sC );
//                $sC = str_replace( '[[sButtonSkip]]', $sButtonSkip, $sC );
//                $sC = str_replace( '[[sButtonDelete]]', $sButtonDelete, $sC );

                $sC = str_replace( '[[sSeedText]]', $this->oSB->DrawProduct( $kfrcP, SEEDBasketProductHandler_Seeds::DETAIL_EDIT_WITH_SPECIES, ['bUTF8'=>true] ), $sC );
                $sList .= $sC;

                $raSeeds[$kP] = $oProdHandler->GetProductValues( $kfrcP, array('bUTF8'=>true) );
            }
        }


        if( $sForm ) {
            $sForm = "<form method='post'>"
                    ."<div>$sForm</div>"
                    ."<div><input type='submit' value='Save' style='margin:20px 0px 0px 20px'/></div>"
                    ."</form>";
        }
        $sForm = "<div class='msdSeedEditGlobalControls' style='position:fixed'>"
                    ."<button class='msdSeedEditButton_new'>Add New Seed</button>"
                ."</div>";

        $oDraw = new MSDCommonDraw( $this->oSB );
        $msdList = $oDraw->DrawMSDList();
        $raTmplParms = array(
            'fTemplates' => array( SEEDAPP."templates/msd.html" ),
            'sFormCid'   => 'Plain',
            //'raResolvers'=> array( array( 'fn'=>array($this,'ResolveTag'), 'raParms'=>array() ) ),
            'vars'       => array()
        );
        $oTmpl = SEEDTemplateMaker( $raTmplParms );

        $s = $oTmpl->ExpandTmpl( 'msdSpeciesListScript', array() )
            .$oTmpl->ExpandTmpl( 'msdEditStyle', array() )
            ."<div class='container-fluid'><div class='row'>"
                ."<div class='col-sm-2 msd-list-col'>$msdList</div>"
                ."<div class='col-sm-8'><div class='msdSeedContainerList'>$sList</div></div>"
                ."<div class='col-sm-2'>$sForm</div>"
            ."</div></div>";

        $s .= "<script>$('.msd-list').css({position:'relative',top:'0px'});</script>";

//$s .= $this->oC->oSB->DrawProductNewForm( 'base-product' );
//$s .= $this->oC->oSB->DrawProductNewForm( 'membership' );
//$s .= $this->oC->oSB->DrawProductNewForm( 'donation' );
//$s .= $this->oC->oSB->DrawProductNewForm( 'book' );
//$s .= $this->oC->oSB->DrawProductNewForm( 'seeds' );

$categoryOpts = "";
foreach( $oMSDCore->GetCategories() as $cat => $raCat ) {
    $categoryOpts .= "<option value='$cat'>{$raCat['EN']}</option>";
}

$msdSeedEditForm = <<<msdSeedEditForm
    <table style='width:100%'><tr>
        <td><select id='msdSeedEdit_category' name='category' class='msdSeedEdit_inputText'>$categoryOpts</select></td><td>&nbsp;</td>
    </tr><tr>
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
        <td><div class='msdSeedEdit_instruction'><b>Quantity</b>: If you have a low quantity of seeds, or if you want to ask requesters to re-offer seeds, indicate that here.</div></td>
    </tr><tr>
        <td><select id='msdSeedEdit_eOffer' name='eOffer'><option value='member'>All Members</option><option value='grower-member'>Only members who also list seeds</option><option value='public'>General public</option></select></td>
        <td><p class='msdSeedEdit_instruction'><b>Who may request these seeds from you</b>: <span id='msdSeedEdit_eOffer_instructions'></span></p></td>
    </tr><tr>
        <td><nobr>$<input type='text' id='msdSeedEdit_item_price' name='item_price' class='msdSeedEdit_inputText'/></nobr></td>
        <td><div class='msdSeedEdit_instruction'><b>Price</b>: We recommend $3.50 for seeds and $12.00 for roots and tubers. That is the default if you leave this field blank. Members who offer seeds (like you!) get an automatic discount of $1 per item.</div></td>
    </tr></table>
    <input type='submit' value='Save'/> <button class='msdSeedEditCancel' type='button'>Cancel</button>
msdSeedEditForm;
$msdSeedEditForm = str_replace("\n","",$msdSeedEditForm);   // jquery doesn't like linefeeds in its selectors

$msdSeedContainerTemplate = $this->sContainer;
$msdSeedContainerTemplate = str_replace( '[[kP]]', "", $msdSeedContainerTemplate );
//$msdSeedContainerTemplate = str_replace( '[[sButtonSkip]]', "Skip", $msdSeedContainerTemplate );
//$msdSeedContainerTemplate = str_replace( '[[sButtonDelete]]', "Delete", $msdSeedContainerTemplate );
$msdSeedContainerTemplate = str_replace( '[[sSeedText]]', "<h3>New Seed</h3>", $msdSeedContainerTemplate );


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
.msdSeedEditGlobalControls { border:1px solid #aaa; border-radius:2px;padding:10px; }
</style>
basketStyle;

$s .= <<<basketScript
<script>
var msdSeedEditVars = { qUrl:"", raSeeds:[], overrideUidSeller:0 };
var msdSeedContainerCurr = null;                // the current msdSeedContainer
var msdSeedContainerCurrFormOpen = false;       // true when the form is open (generally all other inputs are disabled)
var msdSeedContainerCurrFormOpenIsNew = false;  // true when an open form is a new entry (so Cancel will .remove() it)

$(document).ready( function() {
    // on click of an msdSeedText or an Edit button, open the edit form
/*
    $(".msdSeedText, .msdSeedEditButton_edit").click( function(e) { SeedEditFormOpen( $(this).closest(".msdSeedContainer") ); });

    // skip and delete buttons
    $(".msdSeedEditButton_skip").click( function(e)   { SeedEditSkip(   $(this).closest(".msdSeedContainer") ); });
    $(".msdSeedEditButton_delete").click( function(e) { SeedEditDelete( $(this).closest(".msdSeedContainer") ); });
*/
    $(".msdSeedContainer").each( function() { SeedEditInitButtons( $(this) ); });
    // new button
    $(".msdSeedEditButton_new").click( function(e)    { SeedEditNew(); });
});

function SeedEditInitButtons( container )
/****************************************
    Attach event listeners to the controls in a container. Show/hide the buttons based on eStatus.
 */
{
    // on click of an msdSeedText or an Edit button, open the edit form
    container.find(".msdSeedText, .msdSeedEditButton_edit").click( function(e) { SeedEditFormOpen( container ); });

    // skip and delete buttons
    container.find(".msdSeedEditButton_skip").click( function(e)   { SeedEditSkip(   container ); });
    container.find(".msdSeedEditButton_delete").click( function(e) { SeedEditDelete( container ); });

    let kSeed = SeedEditGetKProduct( container );
    if( kSeed ) {
        let eStatus = msdSeedEditVars.raSeeds[kSeed]['eStatus'];
        SeedEditSetButtonLabels( container, eStatus )
    }
}

function SeedEditFormOpen( container )
/*************************************
    Open the edit form for a clicked seed listing.
    Input is the msdSeedContainer of the listing.
 */
{
if( msdSeedContainerCurrFormOpen ) return;  // ValidateContainer returns 0 if already open, but that is ignored if a New form is currently open

    let kSeed = SeedEditValidateContainer( container );
    if( !kSeed && !msdSeedContainerCurrFormOpenIsNew ) return;
    if( kSeed && msdSeedEditVars.raSeeds[kSeed]['eStatus'] == 'DELETED' )  return;   // ignore click on msdText if deleted record

    SeedEditSelectContainer( container, true );

    // Create a form and put it inside msdSeedContainer, after msdSeedText. It is initially non-displayed, but fadeIn shows it.
    let msdSeedEdit = $("<div class='msdSeedEdit'><form>$msdSeedEditForm</form></div>");
    msdSeedContainerCurr.append(msdSeedEdit);

    SeedEditSelectEOffer( msdSeedEdit );
    msdSeedEdit.find('#msdSeedEdit_eOffer').change( function() { SeedEditSelectEOffer( msdSeedEdit ); } );

    if( kSeed ) {
        // eOffer==member is not explicitly stored
        if( !msdSeedEditVars.raSeeds[kSeed]['eOffer'] ) msdSeedEditVars.raSeeds[kSeed]['eOffer'] = 'member';

        // fill in the form with values stored in msdSeedEditVars
        for( let i in msdSeedEditVars.raSeeds[kSeed] ) {
            msdSeedEdit.find('#msdSeedEdit_'+i).val(msdSeedEditVars.raSeeds[kSeed][i]);
        }
    } else {
        // this is a new form - set defaults

    }
    msdSeedEdit.fadeIn(500);

    msdSeedEdit.find("form").submit( function(e) { e.preventDefault(); SeedEditSubmit(kSeed); } );
    msdSeedEdit.find(".msdSeedEditCancel").click( function(e) { e.preventDefault(); SeedEditFormCancel(); } );

    // disable all control buttons while the form is open
    $(".msdSeedEditButtonContainer button").attr("disabled","disabled");
    $(".msdSeedEditGlobalControls  button").attr("disabled","disabled");
}

function SeedEditFormCancel()
{
    SeedEditFormClose( false );
}

function SeedEditFormClose( ok )
{
    if( msdSeedContainerCurr == null || !msdSeedContainerCurrFormOpen ) return;

    msdSeedEdit = msdSeedContainerCurr.find('.msdSeedEdit');
    msdSeedEdit.fadeOut(500, function() {
            msdSeedEdit.remove();      // wait for the fadeOut to complete before removing the msdSeedEdit
            if( ok ) {
                // do this after fadeOut because it looks better afterward
                msdSeedContainerCurr.find(".msdSeedMsg").html( "<div class='alert alert-success' style='font-size:10pt;margin-bottom:5px;padding:3px 10px;display:inline-block'>Saved</div>" );
            }

            if( msdSeedContainerCurrFormOpenIsNew ) {
                if( ok ) {
                    // Closing after successful submit of New form
                } else {
                    // Closing after Cancel on New form
                    msdSeedContainerCurr.remove();
                    msdSeedContainerCurr = null;
                }
            }
            // allow another block to be clicked (keep msdSeedContainerCurr so a New container can be inserted after it)
            msdSeedContainerCurrFormOpen = false;
            msdSeedContainerCurrFormOpenIsNew = false;
        } );

    // re-enable all control buttons
    $(".msdSeedEditButtonContainer button").removeAttr("disabled");
    $(".msdSeedEditGlobalControls  button").removeAttr("disabled");
}


function SeedEditValidateContainer( container )
{
    let k = 0;

    // generally don't allow an msdSeedContainer to be be selected when a form is open
    if( msdSeedContainerCurrFormOpen ) {
        console.log("Cannot open multiple forms");
    } else {
        k = SeedEditGetKProduct( container );
    }
    return( k );
}

function SeedEditGetKProduct( container )
{
    k = parseInt(container.attr("data-kproduct")) || 0;     // apparently this is zero if parseInt returns NaN
    return( k );
}


function SeedEditSelectContainer( container, bOpenForm )
{
    if( msdSeedContainerCurrFormOpen ) return( false );

    msdSeedContainerCurr = container;
    msdSeedContainerCurrFormOpen = bOpenForm;

    // clear previous edit indicators
    $(".msdSeedContainer").css({border:"1px solid #e3e3e3"});
    $(".msdSeedMsg").html("");

    // show the current container is selected
    msdSeedContainerCurr.css({border:"1px solid blue"});

    return( true );
}

/*
function SeedEditGetContainerFromId( id )
{
    // generally don't allow an msdSeedContainer to be be selected when a form is open
    if( msdSeedContainerCurrFormOpen ) { console.log("Cannot open multiple forms"); return( null ); }

    // validate that this is an msdSeedContainer and get the kProduct
//TODO: it would be more sensible just to verify the class (if you want) and store the kProduct in data-kProduct="k"
    let k = 0;
    if( id.substring(0,7) != 'msdSeed' || !(k=parseInt(id.substring(7))) ) { console.log("Invalid id "+id); return( null ); }

    return( $("#"+id) );
}
*/

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

function SeedEditSubmit( kSeed )
{
    if( msdSeedContainerCurr == null || !msdSeedContainerCurrFormOpen ) return;

    let p = "cmd=msdSeed--Update&kS="+kSeed+"&"
          + (msdSeedEditVars['overrideUidSeller'] ? ("config_OverrideUidSeller="+msdSeedEditVars['overrideUidSeller']+"&") : "")
          + msdSeedContainerCurr.find('select, textarea, input').serialize();
    console.log(p);

    let oRet = SEEDJXSync( msdSeedEditVars.qURL+"basketJX.php", p );
    console.log(oRet);

    if( oRet['bOk'] ) {
        SeedEditAfterSuccess( msdSeedContainerCurr, oRet );

        SeedEditFormClose( oRet['bOk'] );
    } else {
        // show the error and leave the form open
        SeedEditAfterError( msdSeedContainerCurr, oRet );
    }

    return( oRet['bOk'] );
}

function SeedEditSkip( container )
{
    let kSeed = SeedEditValidateContainer( container );
    if( !kSeed ) return;

    SeedEditSelectContainer( container, false );    // make this the current container but don't open the form

    let oRet = SEEDJXSync( msdSeedEditVars.qURL+"basketJX.php", "cmd=msdSeed--ToggleSkip&kS="+kSeed );
    if( oRet['bOk'] ) {
        SeedEditAfterSuccess( container, oRet );
    }
}

function SeedEditDelete( container )
{
    let kSeed = SeedEditValidateContainer( container );
    if( !kSeed ) return;

    SeedEditSelectContainer( container, false );    // make this the current container but don't open the form

    let oRet = SEEDJXSync( msdSeedEditVars.qURL+"basketJX.php", "cmd=msdSeed--ToggleDelete&kS="+kSeed );
    if( oRet['bOk'] ) {
        SeedEditAfterSuccess( container, oRet );
    }
}

function SeedEditNew()
{
    if( msdSeedContainerCurrFormOpen ) return( null );

    // subst the [[]] in the container template
    let sContHtml = "$msdSeedContainerTemplate";

    // insert a msdSeedContainer in a nice place
    let container = $(sContHtml);
    if( msdSeedContainerCurr ) {
        container.insertAfter( msdSeedContainerCurr );
    } else {
        $(".msdSeedContainerList").prepend( container );
    }
    SeedEditInitButtons( container );

    // make it the msdSeedContainerCurr, open the form in the container, mark it as a New form so Cancel will remove() it
    msdSeedContainerCurrFormOpenIsNew = true;
    SeedEditFormOpen( container );
}

function SeedEditAfterSuccess( container, rQ )
/*********************************************
    After a successful update, store the updated data and update the UI to match it
 */
{
    let kSeed = rQ['raOut']['_key'];    // for New items this will be novel information

    // raOut contains the validated seed data as stored in the database - save that here so it appears if you open the form again
    msdSeedEditVars.raSeeds[kSeed]=rQ['raOut'];

    // sOut contains the revised msdSeedText
    container.find(".msdSeedText").html( rQ['sOut'] );

    // set data-kproduct for New items
    if( msdSeedContainerCurrFormOpenIsNew ) {
        container.attr( 'data-kproduct', kSeed );
    }

    SeedEditSetButtonLabels( container, rQ['raOut']['eStatus'] );
}

function SeedEditSetButtonLabels( container, eStatus )
{
    switch( eStatus ) {
        default:
        case 'ACTIVE':
            container.find(".msdSeedEditButton_edit").show().html( "Edit" );
            container.find(".msdSeedEditButton_skip").show().html( "Skip" );
            container.find(".msdSeedEditButton_delete").show().html( "Delete" );
            break;
        case 'INACTIVE':
            container.find(".msdSeedEditButton_edit").show().html( "Edit" );
            container.find(".msdSeedEditButton_skip").show().html( "Un-skip" );
            container.find(".msdSeedEditButton_delete").show().html( "Delete" );
            break;
        case 'DELETED':
            container.find(".msdSeedEditButton_edit").hide();
            container.find(".msdSeedEditButton_skip").hide();
            container.find(".msdSeedEditButton_delete").show().html( "Un-delete" );
            break;
    }
}

function SeedEditAfterError( container, rQ )
{
    container.find(".msdSeedMsg").html(
                "<div class='alert alert-danger' style='font-size:10pt;margin-bottom:5px;padding:3px 10px;display:inline-block'>"+rQ['sErr']+"</div>" );
}

</script>
basketScript;

        /* Set parameters for msdSeedEdit. These are initialized to blank, required before you click on anything.
         *
         * raSeeds           : All the seed information is drawn to the UI but also stored here. This is how we get the info to
         *                     fill the edit form. When submitted, a fresh copy of the normalized data is returned and stored here.
         * qURL              : Directory of our ajax handlers
         * overrideUidSeller : This is the uid of the grower whose seeds are being edited. This is passed to msdq.
         *                     It is ignored if you don't have MSDOffice:W perms (the current user is uidSeller in that case, regardless of this).
         */
        $s .= "<script>
               var msdSeedEditVars = {};
               msdSeedEditVars.raSeeds = ".json_encode($raSeeds).";
               msdSeedEditVars.qURL = '".SITEROOT_URL."app/q/';
               msdSeedEditVars.overrideUidSeller = $uidSeller;
               </script>";

        return( $s );
    }

}
