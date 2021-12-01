<?php

/* msdedit
 *
 * Copyright (c) 2018-2021 Seeds of Diversity
 *
 * App to edit Member Seed Directory, built on top of SEEDBasket
 */

/*
update seeds_1.SEEDBasket_ProdExtra set v='flowers' where v='FLOWERS AND WILDFLOWERS' and k='category';
update seeds_1.SEEDBasket_ProdExtra set v='vegetables' where v='VEGETABLES' and k='category';
update seeds_1.SEEDBasket_ProdExtra set v='fruit' where v='FRUIT' and k='category';
update seeds_1.SEEDBasket_ProdExtra set v='herbs' where v='HERBS AND MEDICINALS' and k='category';
update seeds_1.SEEDBasket_ProdExtra set v='grain' where v='GRAIN' and k='category';
update seeds_1.SEEDBasket_ProdExtra set v='trees' where v='TREES AND SHRUBS' and k='category';
update seeds_1.SEEDBasket_ProdExtra set v='misc' where v='MISC' and k='category';
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
// this can just be oApp if MSDCommonDraw takes oApp instead too
    function __construct( SEEDBasketCore $oSB )
    {
        $this->oSB = $oSB;
    }

    private $sItemTemplate =
        "<div class='well seededit-item seededit-item-msd' data-kitem='[[kP]]' style='margin:5px'>"
           ."<div class='msdSeedEditButtonContainer' style='float:right'>"
               ."<button class='seededit-ctrledit' style='display:none'>Edit</button><br/>"
               ."<button class='seededit-ctrlskip' style='display:none'>Skip</button><br/>"
               ."<button class='seededit-ctrldelete' style='display:none'>Delete</button></div>"
           ."<div class='seededit-form-msg'></div>"
           ."<div class='seededit-text' style='padding:0px'>[[sSeedText]]</div>"
           ."<div class='seededit-form' style='display:none'></div>"
       ."</div>";

    function Draw( $uidSeller, $kSp )
    {
        $s = "";
        $sForm = $sList = "";
        $raSeeds = array();

// Do this via MSDLib because MSDApp isn't supposed to know about MSDCore.
// Also, it's necessary to specify the db where SEEDBasket lives because sometimes (mbr_basket) this oApp is seeds2.
// BTW, oSB->oSBDB knows this so why can't MSDCore/MSDLib just take oSB
        $oMSDCore = new MSDCore( $this->oSB->oApp, ['sbdb'=>'seeds1'] );
// this whole method should be in msdlib anyway
        $oMSDLib = new MSDLib( $this->oSB->oApp, ['sbdb'=>'seeds1'] );
        if( $oMSDLib->PermOfficeW() ) {
            if( !$uidSeller && !$kSp ) {
                $sList = "<h3>Please choose a Grower above and/or a Species at the left</h3>";
                goto drawScreen;
            }
        } else {
            if( !$uidSeller )  $uidSeller = $this->oSB->oApp->sess->GetUID();
        }
        if( $uidSeller ) {
            $sList .= "<h3>".$this->oSB->oApp->kfdb->Query1( "SELECT mbr_code FROM {$this->oSB->oApp->GetDBName('seeds1')}.sed_curr_growers WHERE mbr_id='$uidSeller'" )." : "
                     .SEEDCore_utf8_encode($oMSDCore->GetGrowerName($uidSeller))."</h3><hr/>";
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
                    ."<button class='seededit-ctrlnew'>Add New Seed</button>"
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

        $oDraw = new MSDCommonDraw( $this->oSB, ['sbdb'=>'seeds1'] );
        $msdList = $oDraw->DrawMSDList();

        $s = $oTmpl->ExpandTmpl( 'msdSpeciesListScript', array() )
            .$oTmpl->ExpandTmpl( 'msdEditStyle', array() )
            ."<div class='container-fluid'><div class='row'>"
                ."<div class='col-sm-2 msd-list-col'>$msdList</div>"
                ."<div class='col-sm-8'><div class='seededit-list'>$sList</div></div>"
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
        <td><div class='msdSeedEdit_instruction'><b>Price</b>: We recommend $3.50 for seeds and $18.00 for roots and tubers. That is the default if you leave this field blank. Members who offer seeds (like you!) get an automatic discount of $1 per item.</div></td>
    </tr></table>
msdSeedEditForm;
$msdSeedEditForm = str_replace("\n","",$msdSeedEditForm);   // jquery doesn't like linefeeds in its selectors

$msdSeedEditItemTemplate = $this->sItemTemplate;
$msdSeedEditItemTemplate = str_replace( '[[kP]]', "", $msdSeedEditItemTemplate );
$msdSeedEditItemTemplate = str_replace( '[[sSeedText]]', "<h3>New Seed</h3>", $msdSeedEditItemTemplate );


$s .= <<<basketStyle
<style>
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
/* UI for an editable list of SEEDBasket products
 */
class SEEDBasket_EditList extends ConsoleEditList
{
    constructor( raConfig )
    {
        super( raConfig );

        let saveThis = this;
        $(".seededit-item-msd").each( function() { saveThis.Item_Init( $(this) ); });
    }

    Item_Init( jItem )
    /*****************
        Attach event listeners to the controls in an item. Show/hide controls based on eStatus.
     */
    {
        // the base class hooks certain click events to FormOpen
        super.Item_Init( jItem );

        let saveThis = this;

        // skip and delete buttons
        jItem.find(".seededit-ctrlskip").click( function(e)   { saveThis.doSkip(   jItem ); });
        jItem.find(".seededit-ctrldelete").click( function(e) { saveThis.doDelete( jItem ); });

        let kItem = this.GetItemId( jItem );
        if( kItem ) {
            let eStatus = this.raConfig['raSeeds'][kItem]['eStatus'];
            this.setControlLabels( jItem, eStatus )
        }
    }

    FormOpen_InitForm( jFormDiv, kItem )
    {
        super.FormOpen_InitForm( jFormDiv, kItem );     // disable new and edit buttons

        // disable all control buttons for all items, while the form is open
        $(".msdSeedEditButtonContainer button").attr("disabled","disabled");
        $(".msdSeedEditGlobalControls  button").attr("disabled","disabled");
    }

    FormClose_PostClose( jItem )
    /***************************
        This is called after the form is removed from the dom. The item should still be valid though.

        N.B. If a New form is Cancelled, jItem will be null at this point.
     */
    {
        super.FormClose_PostClose( jItem );             // re-enable new and edit buttons

        // re-enable all control buttons for all items
        $(".msdSeedEditButtonContainer button").removeAttr("disabled");
        $(".msdSeedEditGlobalControls  button").removeAttr("disabled");
    }


    setControlLabels( jItem, eStatus )
    {
        switch( eStatus ) {
            default:
            case 'ACTIVE':
                jItem.find(".seededit-ctrledit").show().html( "Edit" );
                jItem.find(".seededit-ctrlskip").show().html( "Skip" );
                jItem.find(".seededit-ctrldelete").show().html( "Delete" );
                break;
            case 'INACTIVE':
                jItem.find(".seededit-ctrledit").show().html( "Edit" );
                jItem.find(".seededit-ctrlskip").show().html( "Un-skip" );
                jItem.find(".seededit-ctrldelete").show().html( "Delete" );
                break;
            case 'DELETED':
                jItem.find(".seededit-ctrledit").hide();
                jItem.find(".seededit-ctrlskip").hide();
                jItem.find(".seededit-ctrldelete").show().html( "Un-delete" );
                break;
        }
    }


    doSkip( jItem )
    {
        let kItem = this.SelectItem( jItem, false );   // make this the current item but don't open the form
        if( kItem == -1 ) return;

        //SEEDJX_bDebug = true;
        let oRet = SEEDJXSync( this.raConfig['qUrl'], "cmd=msdSeed--ToggleSkip&kS="+kItem );
        if( oRet['bOk'] ) {
            this.doAfterSuccess( jItem, oRet );
        }
    }

    doDelete( jItem )
    {
        let kItem = this.SelectItem( jItem, false );   // make this the current item but don't open the form
        if( kItem == -1 ) return;

        //SEEDJX_bDebug = true;
        let oRet = SEEDJXSync( this.raConfig['qUrl'], "cmd=msdSeed--ToggleDelete&kS="+kItem );
        if( oRet['bOk'] ) {
            this.doAfterSuccess( jItem, oRet );
        }
    }

}


class MSDSeedEditList extends SEEDBasket_EditList
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
        super.FormOpen_InitForm( jFormDiv, kItem );

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
              + this.jItemCurr.find('select, textarea, input').serialize();

        //SEEDJX_bDebug = true;
        //console.log(p);
        let oRet = SEEDJXSync( this.raConfig['qUrl'], p );
        //console.log(oRet);

        if( oRet['bOk'] ) {
            this.doAfterSuccess( this.jItemCurr, oRet );
            this.FormClose( oRet['bOk'] );
        } else {
            // show the error and leave the form open
            this.doAfterError( this.jItemCurr, oRet );
        }

        return( oRet['bOk'] );
    }

    doAfterSuccess( jItem, rQ )
    /**************************
        After a successful update, store the updated data and update the UI to match it
     */
    {
        let kItem = rQ['raOut']['_key'];    // for New items this will be novel information

        // raOut contains the validated seed data as stored in the database - save that here so it appears if you open the form again
        this.raConfig['raSeeds'][kItem]=rQ['raOut'];

        // sOut contains the revised seededit-text
        jItem.find(".seededit-text").html( rQ['sOut'] );

        // set data-kitem for New items
        if( this.IsFormNew() ) {
            this.SetItemId( jItem, kItem );
        }

        this.setControlLabels( jItem, rQ['raOut']['eStatus'] );
    }

    doAfterError( jItem, rQ )
    {
        jItem.find(".seededit-form-msg").html( "<div class='alert alert-danger'>"+rQ['sErr']+"</div>" );
    }
}



$(document).ready( function() {

    var msdSEL = new MSDSeedEditList( msdSELConfig );

});

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
               var msdSELConfig = {
                        // base
                        itemtype:          'msd',
                        itemhtml:          \"$msdSeedEditItemTemplate\",
                        formhtml:          \"$msdSeedEditForm\",

                        // derived
                        qUrl:              '".Site_UrlQ('basketJX.php')."',
                        overrideUidSeller: ".($uidSeller ?: -1).",
                        raSeeds:           ".json_encode($raSeeds)."
               };
               </script>";

        done:
        return( $s );
    }


    private function drawListOfMSDSeedContainers( $uidSeller, $kSp )
    {
        $sList = "";
        $raSeeds = array();

        $oMSDCore = new MSDCore( $this->oSB->oApp, ['sbdb'=>'seeds1'] );     // oApp can be seeds2 e.g. mbr_basket

        // Get the list of seed offers for the given seller/species
        $oMSDQ = new MSDQ( $this->oSB->oApp, ['config_bUTF8'=>true, 'config_sbdb'=>'seeds1'] );
        $rQ = $oMSDQ->Cmd( 'msdSeedList-GetData', ['kUidSeller'=>$uidSeller,'kSp'=>$kSp,'eStatus'=>"ALL"] );
        if( $rQ['bOk'] ) {
            $raSeeds = $rQ['raOut'];
        } else {
            goto done;
        }

        // Draw the list in a set of SeedEditList items
        $category = "";
        foreach( $raSeeds as $kProduct => $raS ) {
            if( $category != $raS['category'] ) {
                $category = $raS['category'];
                $sList .= "<div><h2>".@$oMSDCore->GetCategories()[$category]['EN']."</h2></div>";
            }

            $sC = $this->sItemTemplate;
            $sC = str_replace( '[[kP]]', $kProduct, $sC );
            $rQ = $oMSDQ->Cmd( 'msdSeed-Draw', array('kS'=>$kProduct, 'eDrawMode'=>MSDQ::SEEDDRAW_EDIT.' '.MSDQ::SEEDDRAW_VIEW_SHOWSPECIES) );
            $sP = $rQ['bOk'] ? $rQ['sOut'] : "Missing text for seed # $kProduct: {$rQ['sErr']}";
            $sC = str_replace( '[[sSeedText]]', $sP, $sC );
            $sList .= $sC;
        }

        done:
        return( array( $sList, $raSeeds ) );
    }
}


class MSDAppGrowerForm extends KeyframeForm
{
    private $oMSDLib;
    private $bOffice;
    private $yCurrent;

    function __construct( MSDLib $oMSDLib, int $yCurrent, bool $bOffice )
    {
        $this->oMSDLib = $oMSDLib;
        $this->bOffice = $bOffice;
        $this->yCurrent = $yCurrent;

        // do the right thing when these checkboxes are unchecked (http parms are absent, stored value is 1, so change stored value to 0)
        $fields = ['unlisted_phone' => ['control'=>'checkbox'],
                   'unlisted_email' => ['control'=>'checkbox'],
                   'organic'        => ['control'=>'checkbox'],
                   'pay_cash'       => ['control'=>'checkbox'],
                   'pay_cheque'     => ['control'=>'checkbox'],
                   'pay_stamps'     => ['control'=>'checkbox'],
                   'pay_ct'         => ['control'=>'checkbox'],
                   'pay_mo'         => ['control'=>'checkbox'],
                   'pay_etransfer'  => ['control'=>'checkbox'],
                   'pay_paypal'     => ['control'=>'checkbox'],
                   //'bDone'          => ['control'=>'checkbox']
                  ];

        // KFForm is created with KFRelG for purpose of form updates, but SetKGrower() can put a kfrGxM in the form for convenience
        parent::__construct( $this->oMSDLib->KFRelG(), null, ['fields'=>$fields, 'DSParms'=> ['fn_DSPreStore'=>[$this,'growerForm_DSPreStore']]] );
    }

    function growerForm_DSPreStore( $oDS )
    /*************************************
        Fix up the grower record before writing to db. Return true to proceed with the db write.
    */
    {
        if( !$this->bOffice ) {
            // regular users can only update their own listings
            if( $oDS->Value('mbr_id') != $this->oMSDLib->oApp->sess->GetUID() ) {
                die( "Cannot update grower information - mismatched grower code" );
            }

// *** Do this for seeds too
            // record when the member saved the record because office changes overwrite _updated
            $oDS->SetValue( '_updated_by_mbr', date("y-m-d") );  // this really should be the new _updated but that's hard to get
        }

        if( !$oDS->Value('year') )  $oDS->SetValue( 'year', $this->yCurrent );

        $oDS->SetValue( 'bChanged', 1 );

        return( true );
    }

    function SetKGrower( $kGrower )
    /******************************
        Replace the oDS->kfr with kfrGxM of this grower
        The KFForm is created with KFRelG for purpose of form updates, but SetKGrower() can put a kfrGxM in the form for convenience
     */
    {
        // first try to get GxM
        if( !($kfr = $this->oMSDLib->KFRelGxM()->GetRecordFromDB( "mbr_id='$kGrower'" )) ) {
            // create empty form because Grower row doesn't exist (M_* will be blank)
            $kfr = $this->oMSDLib->KFRelG()->CreateRecord();
            $kfr->SetValue( 'mbr_id', $kGrower );
        }
        $this->SetKFR( $kfr );
    }

    function DrawGrowerForm()
    {
        $s = "
<style>
.msd_grower_edit_form       { padding:0px 1em; font-size:9pt; }
.msd_grower_edit_form td    { font-size:9pt; }
.msd_grower_edit_form input { font-size:8pt;}
.msd_grower_edit_form h3    { font-size:12pt; }
.msd_grower_edit_form input[type='submit'] { background-color:#07f;color:white;font-size:9pt;font-weight:bold; }
.msd_grower_edit_form .help { padding:0 10px;font-weight:bold; font-size:10pt; color:#07f; }
</style>
";

/*
    alter table sed_curr_growers add eReqClass enum ('mail_email','mail','email') not null default 'mail_email';

    alter table sed_curr_growers add pay_etransfer tinyint not null default 0;
    alter table sed_curr_growers add pay_paypal    tinyint not null default 0;

    alter table sed_curr_growers add eDateRange enum ('use_range','all_year') not null default 'use_range';
    alter table sed_curr_growers add dDateRangeStart date not null default '2022-01-01';
    alter table sed_curr_growers add dDateRangeEnd   date not null default '2022-05-31';

    alter table sed_growers add eReqClass       text;
    alter table sed_growers add eDateRange      text;
    alter table sed_growers add dDateRangeStart text;
    alter table sed_growers add dDateRangeEnd   text;

 */

        $bNew = !$this->Value('mbr_id');  // only bOffice can instantiate this form with kGrower==0


//        $oForm = new KeyframeForm( $kfrGxM->KFRel(), "A" );
//        $oForm->SetKFR($kfrGxM);
        $oFE = new SEEDFormExpand( $this );

        $s .= "<div class='msd_grower_edit_form'>"
             ."<h3>".($bNew ? "Add a New Grower"
                            : $this->GetKFR()->Expand( "Edit Grower [[mbr_code]] : [[M_firstname]] [[M_lastname]] [[M_company]]" ))."</h3>"

             .(!$bNew ? ("<div style='background-color:#ddd; margin-bottom:1em; padding:1em; font-size:9pt;'>"
                        ."If your name, address, phone number, or email have changed, please notify our office"
                        ."</div>") : "")

             ."<form method='post'>
               <div class='container-fluid'>
                   <div class='row'>
                       <div class='col-md-6'>"
                         .$oFE->ExpandForm(
                             "|||BOOTSTRAP_TABLE(class='col-md-4' | class='col-md-8')
                              ||| <input type='submit' value='Save'/><br/><br/> || [[HiddenKey:]]
                              ||| *Member&nbsp;#*        || ".($this->bOffice && $bNew ? "[[mbr_id]]" : "[[mbr_id | readonly]]" )."
                              ||| *Member&nbsp;Code*     || ".($this->bOffice ? "[[mbr_code]]" : "[[mbr_code | readonly]]")."<span class='help SEEDPopover SPop_mbr_code'>?</span>
                              ||| *Email&nbsp;unlisted*  || [[Checkbox:unlisted_email]]&nbsp;&nbsp; do not publish <span class='help SEEDPopover SPop_unlisted'>?</span>
                              ||| *Phone&nbsp;unlisted*  || [[Checkbox:unlisted_phone]]&nbsp;&nbsp; do not publish
                              ||| *Frost&nbsp;free*      || [[frostfree | size:5]]&nbsp;&nbsp; days <span class='help SEEDPopover SPop_frost_free'>?</span>
                              ||| *Organic*              || [[Checkbox: organic]]&nbsp;&nbsp; are your seeds organically grown?  <span class='help SEEDPopover SPop_organic'>?</span>
                              ||| *Notes*                || &nbsp;
                              ||| {replaceWith class='col-md-12'} [[TextArea: notes | width:100% rows:10]]
                             " )
                     ."<div style='margin-top:10px;border:1px solid #aaa; padding:10px'>
                         <p><strong>I accept seed requests:</strong></p>
                         <p>".$this->Radio('eDateRange', 'use_range')."&nbsp;&nbsp;Between these dates</p>
                         <p style='margin-left:20px'>Members will not be able to make online requests outside of this period. Our default is January 1 to May 31.</p>
                         <p style='margin-left:20px'>".$this->Date('dDateRangeStart')."</p>
                         <p style='margin-left:20px'>".$this->Date('dDateRangeEnd')."</p>
                         <p>&nbsp;</p>
                         <p>".$this->Radio('eDateRange', 'all_year')."&nbsp;&nbsp;All year round</p>
                         <p style='margin-left:20px'>Members will be able to request your seeds at any time of year.</p>
                       </div>
                       </div>
                       <div class='col-md-6'>
                         <div style='border:1px solid #aaa; padding:10px'>
                         <p><strong>I accept seed requests and payment:</strong></p>

                         <p>".$this->Radio('eReqClass', 'mail_email')."&nbsp;&nbsp;By mail or email</p>
                         <ul>
                         <li>Members will see your mailing address and email address.</li>
                         <li>You will receive seed requests in the mail and by email.</li>
                         <li>Members will be prompted to send payment as you specify below.</li>
                         </ul>

                         <p>".$this->Radio('eReqClass', 'mail')."&nbsp;By mail only</p>
                         <ul>
                         <li>Members will see your mailing address.</li>
                         <li>You will receive seed requests my mail only.</li>
                         <li>Members will be prompted to send payment as you specify below.</li>
                         </ul>

                         <p>".$this->Radio('eReqClass', 'email')."&nbsp;By email only</p>
                         <ul>
                         <li>Members will not see your mailing address.</li>
                         <li>You will receive seed requests my email only.</li>
                         <li>Members will be prompted to send payment as you specify below (e-transfer and/or Paypal only).</li>
                         </ul>

                         <p><strong>Payment Types Accepted</strong></p>
                         <p>".$this->Checkbox( 'pay_cash',      "Cash" ).SEEDCore_NBSP("",4)
                             .$this->Checkbox( 'pay_cheque',    "Cheque" ).SEEDCore_NBSP("",4)
                             .$this->Checkbox( 'pay_stamps',    "Stamps" )."<br/>"
                             .$this->Checkbox( 'pay_ct',        "Canadian Tire money" ).SEEDCore_NBSP("",4)
                             .$this->Checkbox( 'pay_mo',        "Money Order" )."<br/>"
                             .$this->Checkbox( 'pay_etransfer', "e-transfer" ).SEEDCore_NBSP("",4)
                             .$this->Checkbox( 'pay_paypal',    "Paypal" )."<br/>"
                             .$this->Text( 'pay_other', "Other ", ['size'=> 30] )
                       ."</p>
                         </div>
                       </div>
                   </div>
               </div></form>
               </div>";

/*
        $s .= "<TABLE border='0'>";
        $nSize = 30;
        $raTxtParms = array('size'=>$nSize);
        if( $bNew ) {
            $s .= $bOffice ? ("<TR>".$oKForm->TextTD( 'mbr_id', "Member #", $raTxtParms  )."</TR>")
                           : ("<TR><td>Member #</td><td>".$oKForm->Value('mbr_id')."</td></tr>" );
        }
        //if( $this->sess->CanAdmin('sed') ) {  // Only administrators can change a grower's code
        if( $this->bOffice ) {  // Only the office application can change a grower's code
            $s .= "<TR>".$oKForm->TextTD( 'mbr_code', "Member Code", $raTxtParms )."</TR>";
        }
        $s .= "<TR>".$oKForm->CheckboxTD( 'unlisted_phone', "Phone", array('sRightTail'=>" do not publish" ) )."</TR>"
             ."<TR>".$oKForm->CheckboxTD( 'unlisted_email', "Email", array('sRightTail'=>" do not publish" ) )."</TR>"
             ."<TR>".$oKForm->TextTD( 'frostfree', "Frost free", $raTxtParms )."<TD></TD></TR>"
             ."<TR>".$oKForm->TextTD( 'soiltype', "Soil type", $raTxtParms )."<TD></TD></TR>"
             ."<TR>".$oKForm->CheckboxTD( 'organic', "Organic" )."</TR>"
             ."<TR>".$oKForm->TextTD( 'zone', "Zone", $raTxtParms )."</TR>"
             ."<TR>".$oKForm->TextTD( 'cutoff', "Cutoff", $raTxtParms )."</TR>"

             ."</TD></TR>"
             ."<TR>".$oKForm->TextAreaTD( 'notes', "Notes", 35, 8, array( 'attrs'=>"wrap='soft'"))."</TD></TR>"
             //."<TR>".$oKForm->CheckboxTD( 'bDone', "This Grower is Done:" )."</TR>"
             ."</TABLE>"
             ."<BR><INPUT type=submit value='Save' />"
             ;

*/

        return( $s );
    }

}
