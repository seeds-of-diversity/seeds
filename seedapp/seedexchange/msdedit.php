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
           ."<div class='seededit-form-msg'></div>"
           ."<div class='msdSeedText' style='padding:0px'>[[sSeedText]]</div>"
       ."</div>";

    function Draw( $uidSeller, $kSp )
    {
        $s = "";
        $sForm = $sList = "";
        $raSeeds = array();

// do this via MSDLib because MSDApp isn't supposed to know about MSDCore
        $oMSDCore = new MSDCore( $this->oSB->oApp, array() );
// this whole method should be in msdlib anyway
        $oMSDLib = new MSDLib( $this->oSB->oApp );
        if( $oMSDLib->PermOfficeW() ) {
            if( !$uidSeller && !$kSp ) {
                $sList = "<h3>Please choose a Grower above and/or a Species at the left</h3>";
                goto drawScreen;
            }
        } else {
            if( !$uidSeller )  $uidSeller = $this->oSB->oApp->sess->GetUID();
        }
        if( $uidSeller ) {
            $sList .= "<h3>".$this->oSB->oApp->kfdb->Query1( "SELECT mbr_code FROM seeds.sed_curr_growers WHERE mbr_id='$uidSeller'" )." : "
                     .utf8_encode($oMSDCore->GetGrowerName($uidSeller))."</h3><hr/>";
        } else {
            $sList .= "<h3>All Growers</h3>";
        }

//        $oMSDQ = new MSDQ( $this->oSB->oApp, array() );

        list($sContainers,$raSeeds) = $this->drawListofMSDSeedContainers( $uidSeller, $kSp );
        $sList .= $sContainers;

//        if( $sForm ) {
//            $sForm = "<form method='post'>"
//                    ."<div>$sForm</div>"
//                    ."<div><input type='submit' value='Save' style='margin:20px 0px 0px 20px'/></div>"
//                    ."</form>";
//        }
        if( $uidSeller ) {
            $sForm = "<div class='msdSeedEditGlobalControls' style='position:fixed'>"
                    ."<button class='msdSeedEditButton_new'>Add New Seed</button>"
                    ."</div>";
        }

drawScreen:
        $raTmplParms = array(
            'fTemplates' => array( SEEDAPP."templates/msd.html" ),
            'sFormCid'   => 'Plain',
            //'raResolvers'=> array( array( 'fn'=>array($this,'ResolveTag'), 'raParms'=>array() ) ),
            'vars'       => array()
        );
        $oTmpl = SEEDTemplateMaker( $raTmplParms );

        $oDraw = new MSDCommonDraw( $this->oSB );
        $msdList = $oDraw->DrawMSDList();

        $s = $oTmpl->ExpandTmpl( 'msdSpeciesListScript', array() )
            .$oTmpl->ExpandTmpl( 'msdEditStyle', array() )
            ."<div class='container-fluid'><div class='row'>"
                ."<div class='col-sm-2 msd-list-col'>$msdList</div>"
                ."<div class='col-sm-8'><div class='msdSeedContainerList'>$sList</div></div>"
                ."<div class='col-sm-2'>$sForm</div>"
            ."</div></div>";

        $s .= "<script>$('.msd-list').css({position:'relative',top:'0px'});</script>";

        $s .= <<<MSDSPECIESTITLE
<script>
    $(document).ready( function() {
        /* When you click on a species name in the msd-list, we highlight it and fetch the variety list.
         */
        $(".msd-list-species-title").click( function() {
            // highlight the species title (and reset any previous highlight)
            $(".msd-list-species-title").css({ color: "#333" } );   // bootstrap's default text color
            $(this).css({ color: "#373" } );
            location.replace( "?selectSpecies="+$(this).attr('kSpecies') );
        });
    });
</script>
MSDSPECIESTITLE;

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
msdSeedEditForm;
$msdSeedEditForm = str_replace("\n","",$msdSeedEditForm);   // jquery doesn't like linefeeds in its selectors

$msdSeedContainerTemplate = $this->sContainer;
$msdSeedContainerTemplate = str_replace( '[[kP]]', "", $msdSeedContainerTemplate );
//$msdSeedContainerTemplate = str_replace( '[[sButtonSkip]]', "Skip", $msdSeedContainerTemplate );
//$msdSeedContainerTemplate = str_replace( '[[sButtonDelete]]', "Delete", $msdSeedContainerTemplate );
$msdSeedContainerTemplate = str_replace( '[[sSeedText]]', "<h3>New Seed</h3>", $msdSeedContainerTemplate );


$s .= <<<basketStyle
<style>
.seededit-form { width:100%;display:none;margin-top:5px;padding-top:10px;border-top:1px dashed #888 }


.msdSeedText_species { font-size:14pt; font-weight:bold; }
.sed_seed_offer              { font-size:10pt; padding:2px; background-color:#fff; }
.sed_seed_offer_member       { color: #484; border:2px solid #484 }
.sed_seed_offer_growermember { color: #08f; border:2px solid #08f }
.sed_seed_offer_public       { color: #f80; border:2px solid #f80 }
.sed_seed_mc     { font-weight:bold;text-align:right }

.msdSeedEdit_inputText   { width:95%;margin:3px 0px }
.msdSeedEdit_instruction { background-color:white;border:1px solid #aaa;margin:3px 0px 0px 30px;padding:3px }
.msdSeedEditButtonContainer { text-align:center;margin-left:20px;width:10%;max-width:100px; }
.msdSeedEditGlobalControls { border:1px solid #aaa; border-radius:2px;padding:10px; }
</style>
basketStyle;

$s .= <<<basketScript
<script>
var msdSeedContainerCurr = null;                // the current msdSeedContainer

$(document).ready( function() {
    $(".msdSeedContainer").each( function() { msdSEL.initButtons( $(this) ); });

// implement new button with a list of classes on SEEDEditList constructor that will attach to FormNew
    $(".msdSeedEditButton_new").click( function(e)    { msdSEL.FormNew(); });
});

class SEEDEditList
/*****************
    Show a list of items and allow them to expand to forms one at a time.
    Derivation supplies the static text and the form for each item.
 */
{
    constructor( raConfig )
    {
        this.raConfig = raConfig;

        this.bFormIsOpen = false;
        this.bFormIsNew = false;        // if an open form is new, Cancel will .remove() it
    }

    IsFormOpen()
    {
        return( this.bFormIsOpen );
    }

    IsFormOpenAndNew()
    {
        return( this.bFormIsNew );
    }


    FormNew()
    {
        if( this.IsFormOpen() ) return( null );

        let sItemHtml = "$msdSeedContainerTemplate";

        // insert a new item in a nice place
        let jItem = $(sItemHtml);
        if( msdSeedContainerCurr ) {
            jItem.insertAfter( msdSeedContainerCurr );
        } else {
            $(".msdSeedContainerList").prepend( jItem );
        }

//should this happen in FormOpen_InitForm -- no this is initializing the contents in the whole item not just the form
//msdSEL.initButtons( container );

        // make it the msdSeedContainerCurr, open the form in the container, mark it as a New form so Cancel will remove() it
        this.bFormIsNew = true;
        this.FormOpen( jItem );
    }

    FormOpen( jItem )
    /****************
        Open the edit form for a clicked item.
        Input is the jquery object of the seededit_item.
     */
    {
        /* Other functions use UseItem() to get the id of a selected item but that returns 0 if a form is open to indicate that the selection can't be made.
         * However if the selected item is New, its id is also 0. Use IsFormOpen and IsFormOpenAndNew to tell the difference.
         */

        // Don't allow a new form to be opened if one is already open.
        if( this.IsFormOpen() ) return;

        let kItem = this.UseItem( jItem );

        // if UseItem returns 0 it either means an invalid item or this is a New item
        if( !kItem && !this.IsFormOpenAndNew() ) return;

        if( !this.FormOpen_IsOpenable( jItem, kItem ) )  return;

        this.SelectItem( jItem, true );

        // Create a form and put it inside msdSeedContainer, after msdSeedText. It is initially non-displayed, but fadeIn shows it.
        let jFormDiv = $( this.MakeFormHTML( { formhtml: "$msdSeedEditForm" } ) );
        msdSeedContainerCurr.append(jFormDiv);

        // set listeners for the Save and Cancel buttons. Use saveThis because "this" is not defined in the closures.
        let saveThis = this;
        jFormDiv.find("form").submit( function(e) { e.preventDefault(); saveThis.FormSave( kItem ); } );
        jFormDiv.find(".seededit-form-button-cancel").click( function(e) { e.preventDefault(); saveThis.FormCancel(); } );

        this.FormOpen_InitForm( jFormDiv, kItem );

        jFormDiv.fadeIn(500);
    }

    FormSave( kItem )
// kind of silly to pass kItem from FormOpen but check to see if there's a current item and open form. Why not just get the item id here.
    {
        if( msdSeedContainerCurr == null || !this.IsFormOpen() ) return;

        return( this.FormSave_Action( kItem ) );
    }


    FormCancel()
    {
        this.FormClose( false );
    }

    FormClose( ok )
    {
        if( msdSeedContainerCurr == null || !this.IsFormOpen() ) return;

        let jFormDiv = msdSeedContainerCurr.find('.seededit-form');

        this.FormClose_PreClose( jFormDiv );

        let saveThis = this;    // "this" is not defined in the closure
        jFormDiv.fadeOut(500, function() {
                jFormDiv.remove();      // wait for the fadeOut to complete before removing the seededit-form
                if( ok ) {
                    // do this after fadeOut because it looks better afterward
                    msdSeedContainerCurr.find(".seededit-form-msg").html( "<div class='alert alert-success' style='font-size:10pt;margin-bottom:5px;padding:3px 10px;display:inline-block'>Saved</div>" );
                }

                if( saveThis.IsFormOpenAndNew() ) {
                    if( ok ) {
                        // Closing after successful submit of New form
                    } else {
                        // Closing after Cancel on New form (remove the item)
                        msdSeedContainerCurr.remove();
                        msdSeedContainerCurr = null;
// here the current item is undefined so if you click new again it draws at the top of the page (unscrolled). Better to try to remember the previous current item and make that current here.
                    }
                }
                // allow another block to be clicked (keep msdSeedContainerCurr so a New container can be inserted after it)
                saveThis.bFormIsOpen = false;
                saveThis.bFormIsNew = false;

                saveThis.FormClose_PostClose( jFormDiv );
            } );
    }

    UseItem( jItem )
    /***************
        When an item is clicked or selected, return its item id, unless there is a form open (because other items are supposed to act disabled then).
     */
    {
        let k = 0;

        if( this.IsFormOpen() ) {
            console.log("Cannot open multiple forms");
        } else {
            k = this.GetItemId( jItem );
        }
        return( k );
    }

    GetItemId( jItem )
    {
        let k = parseInt(jItem.attr("data-kproduct")) || 0;     // apparently this is zero if parseInt returns NaN
        return( k );
    }


    SelectItem( jItem, bOpenForm )
    /*****************************
        Set the current item and the status of the form
     */
    {
        if( this.IsFormOpen() ) return( false );

        msdSeedContainerCurr = jItem;
        this.bFormIsOpen = bOpenForm;

        // clear previous edit indicators
        $(".msdSeedContainer").css({border:"1px solid #e3e3e3"});
        $(".seededit-form-msg").html("");

        // show the current container is selected
        msdSeedContainerCurr.css({border:"1px solid blue"});

        return( true );
    }

    MakeFormHTML( raConfig )
    /***********************
        Build the form by defining substitutions in the form below, or override to define the whole form.
     */
    {
        let f = "<div class='seededit-form'>"
                   +"<form>"
                       +"[formhtml]"
                       +"<input type='submit' value='Save'/> "
                       +"<button class='seededit-form-button-cancel' type='button'>Cancel</button>"
                   +"</form>"    
               +"</div>";

        if( typeof raConfig['formhtml'] !== 'undefined' )  { f = f.replace( "[formhtml]", raConfig['formhtml'] ); }

        return( f );
    }


    /* Functions meant to provide derived behaviours
     */

    FormOpen_IsOpenable( jItem, kItem )  { /* override to say whether the selected item is allowed to open a form */    return( true ); }
    FormOpen_InitForm( jFormDiv, kItem ) { /* override to initialize the given form */ }
    FormClose_PreClose( jFormDiv )       { /* override for actions when a form is closed, before it fades out */ }
    FormClose_PostClose( jFormDiv )      { /* override for actions when a form is closed, after it fades out */ }
    FormSave_Action( kItem )             { /* override for the action when a form is saved */                           return( true ); }
}

class MSDSeedEditList extends SEEDEditList
{
    constructor( raConfig )
    {
        super( raConfig );
    }

    FormOpen_IsOpenable( jItem, kItem )
    {
        // ignore click on deleted records
        if( kItem && this.raConfig['raSeeds'][kItem]['eStatus'] == 'DELETED' )  return( false );
        return( true );
    }

    FormOpen_InitForm( jFormDiv, kItem )
    {
        if( kItem ) {
            // eOffer==member is not explicitly stored
            if( !this.raConfig['raSeeds'][kItem]['eOffer'] ) this.raConfig['raSeeds'][kItem]['eOffer'] = 'member';

            // fill in the form with values stored in raSeeds
            for( let i in this.raConfig['raSeeds'][kItem] ) {
                jFormDiv.find('#msdSeedEdit_'+i).val(this.raConfig['raSeeds'][kItem][i]);
            }
        } else {
            // this is a new form - set defaults
        }

        // Show the correct side-text for the selected eOffer, and set a function to do that when eOffer changes.
        // Use saveThis because "this" is not defined in the closure.
        this.setEOfferText( jFormDiv );
        let saveThis = this;
        jFormDiv.find('#msdSeedEdit_eOffer').change( function() { saveThis.setEOfferText( jFormDiv ); } );

        // disable all control buttons for all items, while the form is open
        $(".msdSeedEditButtonContainer button").attr("disabled","disabled");
        $(".msdSeedEditGlobalControls  button").attr("disabled","disabled");
    }

    setEOfferText( jFormDiv )
    {
        switch( jFormDiv.find("#msdSeedEdit_eOffer").val() ) {
            default:
            case 'member':
                jFormDiv.find('#msdSeedEdit_eOffer_instructions').html( "Only members of Seeds of Diversity will be able to request these seeds. Although the listing will be visible to the public, your contact information will only be available to members." );
                break;
            case 'grower-member':
                jFormDiv.find('#msdSeedEdit_eOffer_instructions').html( "Only members of Seeds of Diversity <b>who also list seeds in this directory</b> will be able to request these seeds. Although the listing will be visible to the public, your contact information will only be available to members." );
                break;
            case 'public':
                jFormDiv.find('#msdSeedEdit_eOffer_instructions').html( "Anyone who visits the online Seed Directory will be able to request these seeds, whether or not they are a member of Seeds of Diversity. <b>Your name and contact information will be visible to the public.</b> The printed Seed Directory is still only available to members." );
                break;
         }
    }

    FormSave_Action( kItem )
    {
        let p = "cmd=msdSeed--Update&kS="+kItem+"&"
              + (this.raConfig['overrideUidSeller'] ? ("config_OverrideUidSeller="+this.raConfig['overrideUidSeller']+"&") : "")
              + msdSeedContainerCurr.find('select, textarea, input').serialize();

        //SEEDJX_bDebug = true;
        //console.log(p);
        let oRet = SEEDJXSync( this.raConfig['qUrl'], p );
        //console.log(oRet);

        if( oRet['bOk'] ) {
            this.doAfterSuccess( msdSeedContainerCurr, oRet );

            this.FormClose( oRet['bOk'] );
        } else {
            // show the error and leave the form open
            this.doAfterError( msdSeedContainerCurr, oRet );
        }

        return( oRet['bOk'] );
    }

    FormClose_PostClose( jFormDiv )
    {
        // re-enable all control buttons for all items
        $(".msdSeedEditButtonContainer button").removeAttr("disabled");
        $(".msdSeedEditGlobalControls  button").removeAttr("disabled");
    }

    initButtons( jItem )
    /*******************
        Attach event listeners to the controls in an item. Show/hide the buttons based on eStatus.
     */
    {
        let saveThis = this;
        // on click of an msdSeedText or an Edit button, open the edit form
        jItem.find(".msdSeedText, .msdSeedEditButton_edit").click( function(e) { saveThis.FormOpen( jItem ); });

        // skip and delete buttons
        jItem.find(".msdSeedEditButton_skip").click( function(e)   { saveThis.doSkip(   jItem ); });
        jItem.find(".msdSeedEditButton_delete").click( function(e) { saveThis.doDelete( jItem ); });

        let kItem = this.GetItemId( jItem );
        if( kItem ) {
            let eStatus = this.raConfig['raSeeds'][kItem]['eStatus'];
            this.setButtonLabels( jItem, eStatus )
        }
    }


    setButtonLabels( jItem, eStatus )
    {
        switch( eStatus ) {
            default:
            case 'ACTIVE':
                jItem.find(".msdSeedEditButton_edit").show().html( "Edit" );
                jItem.find(".msdSeedEditButton_skip").show().html( "Skip" );
                jItem.find(".msdSeedEditButton_delete").show().html( "Delete" );
                break;
            case 'INACTIVE':
                jItem.find(".msdSeedEditButton_edit").show().html( "Edit" );
                jItem.find(".msdSeedEditButton_skip").show().html( "Un-skip" );
                jItem.find(".msdSeedEditButton_delete").show().html( "Delete" );
                break;
            case 'DELETED':
                jItem.find(".msdSeedEditButton_edit").hide();
                jItem.find(".msdSeedEditButton_skip").hide();
                jItem.find(".msdSeedEditButton_delete").show().html( "Un-delete" );
                break;
        }
    }


    doSkip( jItem )
    {
        let kItem = this.UseItem( jItem );
        if( !kItem ) return;

        this.SelectItem( jItem, false );    // make this the current container but don't open the form
        
        //SEEDJX_bDebug = true;
        let oRet = SEEDJXSync( this.raConfig['qUrl'], "cmd=msdSeed--ToggleSkip&kS="+kItem );
        if( oRet['bOk'] ) {
            this.doAfterSuccess( jItem, oRet );
        }
    }

    doDelete( jItem )
    {
        let kItem = this.UseItem( jItem );
        if( !kItem ) return;

        this.SelectItem( jItem, false );    // make this the current container but don't open the form

        //SEEDJX_bDebug = true;
        let oRet = SEEDJXSync( this.raConfig['qUrl'], "cmd=msdSeed--ToggleDelete&kS="+kItem );
        if( oRet['bOk'] ) {
            this.doAfterSuccess( jItem, oRet );
        }
    }

    doAfterSuccess( jItem, rQ )
    /**************************
        After a successful update, store the updated data and update the UI to match it
     */
    {
        let kItem = rQ['raOut']['_key'];    // for New items this will be novel information
    
        // raOut contains the validated seed data as stored in the database - save that here so it appears if you open the form again
        this.raConfig['raSeeds'][kItem]=rQ['raOut'];
    
        // sOut contains the revised msdSeedText
        jItem.find(".msdSeedText").html( rQ['sOut'] );
    
        // set data-kproduct for New items
        if( this.IsFormOpenAndNew() ) {
            jItem.attr( 'data-kproduct', kItem );
        }
    
        this.setButtonLabels( jItem, rQ['raOut']['eStatus'] );
    }

    doAfterError( jItem, rQ )
    {
        jItem.find(".seededit-form-msg").html(
            "<div class='alert alert-danger' style='font-size:10pt;margin-bottom:5px;padding:3px 10px;display:inline-block'>"
            +rQ['sErr']
            +"</div>" );
    }
}
</script>
basketScript;

        /* Set parameters for msdSeedEdit.
         *
         * raSeeds           : All the seed information is drawn to the UI but also stored here. This is how we get the info to
         *                     fill the edit form. When submitted, a fresh copy of the normalized data is returned and stored here.
         * qURL              : Url of our ajax handler
         * overrideUidSeller : This is the uid of the grower whose seeds are being edited. This is passed to msdq.
         *                     "-1" means an MSDOffice writer is editing a list by species (multiple growers) so msdSeed--update should not change
         *                     the uidSeller. In this mode new items cannot be added anyway (because we can't specify the uidSeller).
         *                     It is ignored if you don't have MSDOffice:W perms (the current user is uidSeller in that case, regardless of this).
         */
        $s .= "<script>
               var msdSEL = new MSDSeedEditList( { qUrl: '".Site_UrlQ('basketJX.php')."',
                                                   overrideUidSeller: ".($uidSeller ?: -1).",
                                                   raSeeds: ".json_encode($raSeeds)."
                                                 } );
               </script>";

        done:
        return( $s );
    }


    private function drawListOfMSDSeedContainers( $uidSeller, $kSp )
    {
        $sList = "";
        $raSeeds = array();

        $oMSDCore = new MSDCore( $this->oSB->oApp, array() );

        // Get the list of seed offers for the given seller/species
        $oMSDQ = new MSDQ( $this->oSB->oApp, ['config_bUTF8'=>true] );
        $rQ = $oMSDQ->Cmd( 'msdSeedList-GetData', ['kUidSeller'=>$uidSeller,'kSp'=>$kSp,'eStatus'=>"ALL"] );
        if( $rQ['bOk'] ) {
            $raSeeds = $rQ['raOut'];
        } else {
            goto done;
        }

        // Draw the list in a set of SeedEditContainers
        $category = "";
        foreach( $raSeeds as $kProduct => $raS ) {
            if( $category != $raS['category'] ) {
                $category = $raS['category'];
                $sList .= "<div><h2>".@$oMSDCore->GetCategories()[$category]['EN']."</h2></div>";
            }

            $sC = $this->sContainer;
            $sC = str_replace( '[[kP]]', $kProduct, $sC );

            $rQ = $oMSDQ->Cmd( 'msdSeed-Draw', array('kS'=>$kProduct, 'eDrawMode'=>MSDQ::SEEDDRAW_EDIT.' '.MSDQ::SEEDDRAW_VIEW_SHOWSPECIES) );
            $sP = $rQ['bOk'] ? $rQ['sOut'] : "Missing text for seed # $kProduct: {$rQ['sErr']}";
            $sC = str_replace( '[[sSeedText]]', $sP, $sC );
            $sList .= $sC;
        }

/*

        $oProdHandler = $this->oSB->GetProductHandler( "seeds" ) or die( "Seeds ProductHandler not defined" );

        $cond = ($uidSeller ? "uid_seller='$uidSeller'" : "1=1")
               .($kSp ? (" AND PE2.v='".addslashes($oMSDCore->GetKlugeSpeciesNameFromKey($kSp))."'") : "");

//        $kfrcP = $this->oC->oSB->oDB->GetKFRC( "PxPE3", "product_type='seeds' AND uid_seller='1' "
//                                                   ."AND PE1.k='category' "
//                                                   ."AND PE2.k='species' "
//                                                   ."AND PE3.k='variety' ",
//                                                   array('sSortCol'=>'PE1_v,PE2_v,PE3_v') );
        if( ($kfrcP = $oMSDCore->SeedCursorOpen($cond)) ) {
            $category = "";
            while( $oMSDCore->SeedCursorFetch( $kfrcP ) ) { // $kfrcP->CursorFetch() ) {
                $kP = $kfrcP->Key();
                if( $category != $kfrcP->Value('PE1_v') ) {
                    $category = $kfrcP->Value('PE1_v');
                    $sList .= "<div><h2>".@$oMSDCore->GetCategories()[$category]['EN']."</h2></div>";
                }

                $sC = $this->sContainer;
                $sC = str_replace( '[[kP]]', $kP, $sC );

                //$sP = $this->oSB->DrawProduct( $kfrcP, SEEDBasketProductHandler_Seeds::DETAIL_EDIT_WITH_SPECIES, ['bUTF8'=>true] );
                $rQ = $oMSDQ->Cmd( 'msdSeed-Draw', array('kS'=>$kfrcP->Key(), 'eDrawMode'=>MSDQ::SEEDDRAW_EDIT.' '.MSDQ::SEEDDRAW_VIEW_SHOWSPECIES) );
                $sP = $rQ['bOk'] ? $rQ['sOut'] : ("Missing text for seed #".$kfrP->Key().": {$rQ['sErr']}");
                $sC = str_replace( '[[sSeedText]]', $sP, $sC );
                $sList .= $sC;

                $raSeeds[$kP] = $oProdHandler->GetProductValues( $kfrcP, array('bUTF8'=>true) );
            }
        }
*/

        done:
        return( array( $sList, $raSeeds ) );
    }
}
