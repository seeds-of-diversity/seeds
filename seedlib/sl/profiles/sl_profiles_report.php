<?php

/* Crop Profiles reporting
 *
 * Copyright (c) 2009-2024 Seeds of Diversity Canada
 */

//include_once("sl_profiles_defs.php");

class SLProfilesReport
{
    private $oProfilesDB;
    private $oProfilesDefs;
    private $oApp;

    function __construct( SLProfilesDB $oProfilesDB, SLProfilesDefs $oProfilesDefs, SEEDAppBase $oApp, $raParms = array() )
    {
        $this->oProfilesDB   = $oProfilesDB;
        $this->oProfilesDefs = $oProfilesDefs;
        $this->oApp = $oApp;
    }

    function DrawVIRecord( $kVI, $bBasic = true )
    /********************************************
        Show the record for a variety/site/year
     */
    {
        $s = "";

        if( !($kfrVI = $this->oProfilesDB->GetKFR( 'VI', $kVI )) ) goto done;
        list($psp,$sp,$cv) = $this->oProfilesDB->ComputeVarInstName($kfrVI);

        $raDO = $this->oProfilesDB->GetList( 'Obs', "fk_sl_varinst='$kVI'" );
        $defsRA = $this->oProfilesDefs->GetDefsRAFromSP( $psp );
//var_dump($raVI);
//var_dump($raDO);

        $s = "<table class='sldesc_VIRecord_table' border='0' cellspacing='5' cellpadding='5'>";
        if( $bBasic ) {
            $mbrName = (new Mbr_Contacts($this->oApp))->GetContactName($kfrVI->Value('fk_mbr_contacts'));
            //$uid = $kfrVI->Value('Site_uid');
            //$oDB = new SEEDSessionAccountDBRead( $this->oProfilesDB->GetKFDB(), "seeds" );
            //list($k,$raUserInfo) = $oDB->GetUserInfo( $uid, false, false );
            //if( @$raUserInfo['realname'] ) {
            //    $uid = $raUserInfo['realname']." ($uid)";
            //}

            $s .= "<tr><td width='250'><b>Observer:</b></td><td width='200'>".SEEDCore_HSC($mbrName)."</td></tr>"
                 ."<tr><td width='250'><b>Species:</b></td><td width='200'>{$sp}</td></tr>"
                 ."<tr><td><b>Variety:</b></td><td>{$cv}</td></tr>"
                 ."<tr><td><b>Year:</b></td><td>{$kfrVI->Value('year')}</td></tr>"
                 ."<tr><td><b>Location:</b></td><td>{$kfrVI->Value('Site_province')}</td></tr>"
	         ."<tr><td><hr/></td><td>&nbsp;</td></tr>";
        }
        foreach( $raDO as $obs ) {
            if( $obs['k'] == 'common_SoD_i__samplesize' ) {
                if( ($def = @$defsRA[ $obs['k'] ]) ) {
                    $v = @$obs['v'];
                    $s .= $this->drawObsSummaryRow( $obs['k'], $v, $def );
                }
                break;
            }
        }
        foreach( $raDO as $obs ) {
            if( !($def = @$defsRA[ $obs['k'] ]) ) continue;
            if( $obs['k'] == 'common_SoD_i__samplesize' )  continue;

            $v = @$obs['v'];
            $s .= $this->drawObsSummaryRow( $obs['k'], $v, $def );
        }
        $s .= "</table>";

        done:
        return( $s );
    }

    private function drawObsSummaryRow( $k, $v, $def )
    {
        $s = "";

        $l = @$def['l_EN'];

        if( ($vl = @$def['m'][$v]) ) {  // the multi-choice text value corresponding to the numerical value
            $vl = ucwords( $vl );
            if( ($vimg = @$def['img'][$v]) ) {
                $s = "<tr><td><b>$l:</b></td><td>$vl</td><td><img src='".W_ROOT."seedcommon/sl/descimg/$vimg' height='75'/></td></tr>";
            } else {
                $s = "<tr><td><b>$l:</b></td><td>$vl</td></tr>";
            }
        } else {
            $s = "<tr><td><b>$l:</b></td><td>$v</td></tr>";
        }
        return( $s );
    }

    function DrawVIForm( $kVI, SEEDUIComponent $oUIComp, string $eForm )
    {
        $s = "";

        if( !$eForm )  $eForm = 'default';

        if( !($kfrVI = $this->oProfilesDB->GetKFR( 'VI', $kVI )) ) goto done;
        //list($psp,$sp,$cv) = $this->oProfilesDB->ComputeVarInstName($kfrVI);

//        $raForms = $this->getFormsForSp( $sp );

        /* Let the user select a form, unless there is only one
         */
/*
        if( count($raForms) == 1 ) {
            // first element of associative array
            reset($raForms);
            $eForm = key($raForms);
        } else {
            if( !($eForm = SEEDSafeGPC_GetStrPlain( 'showVIForm' )) ) {
                // first element of associative array
                reset($raForms);
                $eForm = key($raForms);
            }

            $s .= "<div class='panel panel-default'><div class='panel-body'>"
                 ."<form action='".Site_path_self()."' method='post'>"
                 .SEEDForm_Hidden( 'editVI', $this->kVI )    // propagate to top
                 .SEEDForm_Hidden( 'showObsForm', 1 )    // propagate to top
                 ."Choose a form: "
                 .SEEDForm_Select( 'showVIForm', $raForms, $eForm, array('selectAttrs'=>"onchange='submit()'") )
                 //."<input type='submit' value='Show Form'/>"
                 ."</form>"
                 ."</div></div>";
        }
*/

        $s .= $this->drawObservationForm( $kfrVI, $eForm, $oUIComp );

        done:
        return( $s );
    }

    private function drawObservationForm( $kfrVI, $eForm, SEEDUIComponent $oUIComp )
    {
        $kVI = $kfrVI->Key();
        list($psp,$sp,$cv) = $this->oProfilesDB->ComputeVarInstName($kfrVI);

        $s = "";

        /* eForm is either:
               default    = make a form from all the soft-coded tags for the current species
               {psp}      = use a hard-coded form (should be one of the hard-coded species names)
               {number}   = the _key of a sl_desc_cfg_form
         */
        if( true ) { //@$this->raSpecies[$eForm]['hardform'] ) {
            //include_once( SEEDCOMMON."sl/desc/".$sp.".php" );

            switch( $psp ) {
                case "apple"  : $s .=   appleForm( $this->oProfilesDB, $kVI); break;
                case "bean"   : $s .=    beanForm( $this->oProfilesDefs, $this->oProfilesDB, $kVI, $eForm); break;
                case "garlic" : $s .=  garlicForm( $this->oProfilesDB, $kVI); break;
                case "lettuce": $s .= lettuceForm( $this->oProfilesDB, $kVI); break;
                case "onion"  : $s .=   onionForm( $this->oProfilesDB, $kVI); break;
                case "pea"    : $s .=     peaForm( $this->oProfilesDB, $kVI); break;
                case "pepper" : $s .=  pepperForm( $this->oProfilesDB, $kVI); break;
                case "potato" : $s .=  potatoForm( $this->oProfilesDB, $kVI); break;
                case "squash" : $s .=  squashForm( $this->oProfilesDB, $kVI); break;
                case "tomato" : $s .=  tomatoForm( $this->oProfilesDefs, $this->oProfilesDB, $kVI, $eForm); break;
            }
        } else {
            $oF = new SLDescForm( $this->oSLDescDB, $this->kVI );
            $oF->Update();

            $oF->LoadDefs( $psp );

            $s .= $oF->Style();

            if( $eForm == 'default' ) {
                $s .= $oF->DrawDefaultForm( $psp );

            } else if( ($kForm = intval($eForm)) ) {
                $ra = $this->kfdb->QueryRA( "SELECT * from sl_desc_cfg_forms WHERE _key='$kForm'" );
                if( $ra['species'] == $psp ) {
                    $s .= $oF->DrawFormExpandTags( $ra['form'] );
                }
            }
        }


// Use SEEDUI to format the form
        $s = "<form method='post' action='{$this->oApp->PathToSelf()}'>"
            ."<input type='hidden' name='vi' value='{$kVI}'/>"                          // this is just for the UI (use profileUpdate)
            ."<br/><input type='submit' value='Save' class='slUserFormButton' />"
            ."<div style='border:1px solid #eee;padding:10px'>"
            .SEEDForm_Hidden( 'action', 'profileUpdate' )
            //.$oUIComp->HiddenFormUIParms( array('kCurr','sortup','sortdown') )
            .$s
            ."<br/><input type='submit' value='Save' class='slUserFormButton' />"
            ."</div></form>";

        return( $s );
    }
}



function appleForm( SLProfilesDB $oDB, $kVI ){
$oF = new SLProfilesForm( $oDB, $kVI );
$oF->Update();

$f = $oF->Style();

$raAppleForm = array(
    array( 'cmd'=>'section', 'title_EN'=>"General", 'title_FR'=>"General" ),
    array(     'cmd'=>'q_f', 'k'=>'apple_SoD_i__age' ),
    array(     'cmd'=>'q_m', 'k'=>'apple_SoD_m__condition' ),

    array( 'cmd'=>'section', 'title_EN'=>"Spring", 'title_FR'=>"Spring"),
    array(     'cmd'=>'q_m', 'k'=>'apple_SoD_m__hardy' ),
    array(     'cmd'=>'q_m', 'k'=>'apple_SoD_m__habit' ),
    array(     'cmd'=>'q_m', 'k'=>'apple_SoD_m__vigour' ),

    array( 'cmd'=>'section', 'title_EN'=>"Flowers (late spring, early summer)", 'title_FR'=>"Flowers (late spring, early summer)"),
	array(     'cmd'=>'q_m', 'k'=>'apple_SoD_m__flowerseason' ),
	array(     'cmd'=>'q_i', 'k'=>'apple_SoD_i__flowerduration' ),
	array(     'cmd'=>'q_m', 'k'=>'apple_SoD_m__flowerregularity' ),
	array(     'cmd'=>'q_m', 'k'=>'apple_SoD_m__flowersecondary' ),
	array(     'cmd'=>'q_m', 'k'=>'apple_SoD_m__selfcompatible' ),
	array(     'cmd'=>'q_m', 'k'=>'apple_GRIN_m__ANTHERCOL' ),
	array(     'cmd'=>'q_m', 'k'=>'apple_GRIN_m__CARPELARR' ),
	array(     'cmd'=>'q_m', 'k'=>'apple_GRIN_m__FLWRCOLOR' ),
	array(     'cmd'=>'q_f', 'k'=>'apple_GRIN_f__FLOWERSIZE' ),
	array(     'cmd'=>'q_i', 'k'=>'apple_GRIN_i__FLWRINFLOR' ),


	array( 'cmd'=>'section', 'title_EN'=>"Leaves (Mid summer)", 'title_FR'=>"Leaves (Mid summer)"),
	array(     'cmd'=>'q_m', 'k'=>'apple_GRIN_m__LFHAIRSURF' ),
	array(     'cmd'=>'q_f', 'k'=>'apple_GRIN_f__LEAFLENGTH' ),
	//img echo "<DIV class='d_a' align=center><IMG src='../img/apple/apple LEAFLENGTH.gif' height=120></DIV>";
	array(     'cmd'=>'q_m_t', 'k'=>'apple_GRIN_m__LEAFSHAPE' ),
	array(     'cmd'=>'q_m', 'k'=>'apple_GRIN_m__LEAFLOBING' ),


	array( 'cmd'=>'section', 'title_EN'=>"Fruit (Harvest season)", 'title_FR'=>"Fruit (Harvest season)"),
	array(     'cmd'=>'q_m_t', 'k'=>'apple_SoD_m__fruitshape' ),
	array(     'cmd'=>'q_m_t', 'k'=>'apple_GRIN_m__TOPFRTSHAPE' ),
	array(     'cmd'=>'q_m', 'k'=>'apple_GRIN_m__FRUITTENAC' ),
	array(     'cmd'=>'q_m', 'k'=>'apple_SoD_m__fruitearliness' ),
	array(     'cmd'=>'q_m', 'k'=>'apple_SoD_m__fruitsize' ),
	array(     'cmd'=>'inst', 'inst_EN'=>"Apple skin colour is often made up of two layers: an underlying \"ground colour\" that appears first, and an \"over colour\" that usually appears when the fruit ripens. ".
										 "<BR/>e.g. Golden Delicious has an even yellow ground colour, sometimes with a blush of pink over colour. ".
										 "<BR/>e.g. Gala has an underlying yellow-orange ground colour, often with stripes of red over colour. ".
										 "<BR/>e.g. McIntosh has an underlying green ground colour with a large splash of red over colour."),
    array(     'cmd'=>'q_m', 'k'=>'apple_SoD_m__skingroundcolour' ),
	array(     'cmd'=>'q_m', 'k'=>'apple_SoD_m__skinovercolour' ),
	array(     'cmd'=>'q_m', 'k'=>'apple_SoD_m__skinoverpattern' ),
	array(     'cmd'=>'q_m', 'k'=>'apple_GRIN_m__FRUITBLOOM' ),
	array(     'cmd'=>'q_m', 'k'=>'apple_SoD_m__bruising' ),
	array(     'cmd'=>'q_i', 'k'=>'apple_GRIN_i__CARPELNUM' ),
	array(     'cmd'=>'q_m', 'k'=>'apple_GRIN_m__FRTFLSHCOL' ),
	array(     'cmd'=>'q_m', 'k'=>'apple_GRIN_m__FRTFLSHFRM' ),
	array(     'cmd'=>'q_m', 'k'=>'apple_SoD_m__texture' ),
	array(     'cmd'=>'q_m', 'k'=>'apple_GRIN_m__FRTFLSHFLA' ),
	array(     'cmd'=>'q_m', 'k'=>'apple_GRIN_m__SEEDCOLOR' ),
	array(     'cmd'=>'q_m', 'k'=>'apple_GRIN_m__SEEDSHAPE' ),


);

$oF->SetDefs( SLDescDefsApple::$raDefsApple );  // this tells SLDescForm how to interpret the 'garlic' descriptors

$f .= $oF->DrawForm( $raAppleForm );  // this tells SLDescForm to draw a form using those garlic descriptors, as organized in the array above

   //dw_sect( "Dates" );
return ($f);
}



function beanForm( SLProfilesDefs $oSLProfilesDefs, SLProfilesDB $oDB, int $kVI, string $eForm )
{
    $raBeanFormCommon = [
    	['cmd'=>'head', 'head_EN'=>"bean"],

        ['cmd'=>'section', 'title_EN'=>"Dates", 'title_FR'=>"Les dates"],
        [   'cmd'=>'q_d', 'k'=>'common_SoD_d__sowdate'],
        [   'cmd'=>'q_d', 'k'=>'common_SoD_d__flowerdate'],
        [   'cmd'=>'q_d', 'k'=>'common_SoD_d__poddate'],
        [   'cmd'=>'q_d', 'k'=>'common_SoD_d__seeddate'],

        ['cmd'=>'section', 'title_EN'=>"Population Counts", 'title_FR'=>"Population Counts"],
        [   'cmd'=>'q_i', 'k'=>'common_SoD_i__popinitial'],
        [   'cmd'=>'q_i', 'k'=>'common_SoD_i__germpercent'],
        [   'cmd'=>'q_i', 'k'=>'common_SoD_i__plantsremoved'],
        [   'cmd'=>'q_i', 'k'=>'common_SoD_i__plantsdied'],
        [   'cmd'=>'q_i', 'k'=>'common_SoD_i__poppollinating'],
        [   'cmd'=>'q_i', 'k'=>'common_SoD_i__popharvestseeds'],
    ];

    //TODO: TERMI_SHAPE needs a picture
    //TODO: GRAI_COLOR should be in the next section (snap harvest)

    $raBeanFormFull = array(
        array( 'cmd'=>'section', 'title_EN'=>"Seedling", 'title_FR'=>"Seedling" ),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__PLAN_ANTHO' ),

        array( 'cmd'=>'section', 'title_EN'=>"Mid summer", 'title_FR'=>"Mid summer" ),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__PLAN_GROWT' ),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__PLANT_TYPE' ),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__PLAN_CLIMB' ),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__LEAF_COLOR' ),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__LEAF_SIZE' ),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__LEAF_RUGOS' ),

        array( 'cmd'=>'section', 'title_EN'=>"Flowers", 'title_FR'=>"Les fleurs" ),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__PLAN_FLOWE' ),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__FLOW_LOCAT' ),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__FLOW_BRACT' ),
        array(     'cmd'=>'inst', 'inst_EN'=>"Bean flowers have two kinds of petals:  standards curled at the bottom and wings spread at the top."),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__FLOW_STAND' ),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__FLOW_WINGS' ),

        array( 'cmd'=>'section', 'title_EN'=>"2-3 weeks after flowering", 'title_FR'=>"2-3 weeks after flowering" ),
        array(     'cmd'=>'q_f', 'k'=>'bean_NOR_f__PLANT_CM' ),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__TERMI_SHAPE' ),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__TERMI_SIZE' ),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__TERMI_APEX' ),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__GRAI_COLOR' ),

        array( 'cmd'=>'section', 'title_EN'=>"Snap beans, harvest", 'title_FR'=>"Snap beans, harvest" ),
        array(     'cmd'=>'inst', 'inst_EN'=>"Please answer the following questions for ripe pods of varieties that are suitable for fresh (snap) use."),
        array(     'cmd'=>'q_f', 'k'=>'bean_SoD_f__podlength' ),
        array(     'cmd'=>'q_m_t', 'k'=>'bean_NOR_m__POD_SECTIO' ),
        array(     'cmd'=>'inst', 'inst_EN'=>"Most bean pods are one uniform colour; some have a main background colour with extra markings such as stripes or spots."),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__POD_GROUND' ),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__POD_INTENS' ),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__POD_PIGMEN' ),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__POD_PIGCOL' ),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__POD_STRING' ),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__POD_CURVAT' ),
        array(     'cmd'=>'q_m_t', 'k'=>'bean_NOR_m__POD_SHACUR' ),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__POD_SHATIP' ),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__POD_LEBEAK' ),
        array(     'cmd'=>'q_m_t', 'k'=>'bean_NOR_m__POD_CURBEA' ),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__POD_PROMIN' ),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__POD_TEXTUR' ),

        array( 'cmd'=>'section', 'title_EN'=>"Dry pods", 'title_FR'=>"Dry pods" ),
        array(     'cmd'=>'inst', 'inst_EN'=>"Please answer these questions after the pods have dried naturally on the plants."),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__POD_PARDRY' ),
        array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__POD_CONSTR' ),

    	array( 'cmd'=>'section', 'title_EN'=>"Seeds", 'title_FR'=>"Seeds"),
    	array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__GRAIN_SIZE' ),
    	array(     'cmd'=>'q_m_t', 'k'=>'bean_NOR_m__SEED_SHAPE' ),
    	array(     'cmd'=>'q_m', 'k'=>'bean_SoD_m__SEED_SHAPE2' ),
    	array(     'cmd'=>'inst', 'inst_EN'=>"Most bean seeds are one uniform colour, but some are multicoloured."),
        array(     'cmd'=>'q_m', 'k'=>'bean_SoD_m__seedcolours' ),
    	array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__GRAIN_MAIN' ),
    	array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__GRAI_MASEC' ),
    	array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__GRAI_DISTR' ),
    	array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__SEED_VEINS' ),
    	array(     'cmd'=>'q_m', 'k'=>'bean_NOR_m__SEED_HILAR' ),
    );

    $raBeanFormShort = [
    ];

    $raBeanFormCGO = [
        ['cmd'=>'section', 'title_EN'=>"Observations", 'title_FR'=>"Observations"],
        [   'cmd'=>'q_b', 'k'=>'common_SoD_b__disease'],

        ['cmd'=>'section', 'title_EN'=>"Ratings", 'title_FR'=>"Ratings"],
        [   'cmd'=>'q_r', 'k'=>'common_SoD_r__productivity'],
        [   'cmd'=>'q_r', 'k'=>'common_SoD_r__flavour'],
        [   'cmd'=>'q_r', 'k'=>'common_SoD_r__diseaseresistance'],
        [   'cmd'=>'q_r', 'k'=>'common_SoD_r__uniformity'],
        [   'cmd'=>'q_r', 'k'=>'common_SoD_r__appeal'],

        [   'cmd'=>'q_b', 'k'=>'common_SoD_b__wouldyougrowagain'],
        [   'cmd'=>'q_b', 'k'=>'common_SoD_b__wouldyourecommend'],

        ['cmd'=>'section', 'title_EN'=>"Notes", 'title_FR'=>"Notes"],
        [   'cmd'=>'inst', 'inst_EN'=>"Please note any pros and cons related to growing this variety."],
        [   'cmd'=>'q_s',  'k'=>'common_SoD_s__notespros'],
        [   'cmd'=>'q_s',  'k'=>'common_SoD_s__notescons'],
        [   'cmd'=>'inst', 'inst_EN'=>"Any other comments or things worth noting? How would you describe the variety overall? Anything stand out? Is it good as a fresh eating bean? Good as a soup bean? Both? (etc)."],
        [   'cmd'=>'q_s',  'k'=>'common_SoD_s__notesgeneral'],
    ];

    $oF = new SLProfilesForm( $oDB, $kVI );
    $oF->Update();
    $oF->SetDefs( $oSLProfilesDefs->GetDefsRAFromSP('bean') );

    $f = $oF->Style()
        .$oF->DrawForm($raBeanFormCommon);        // draw the common parts of the forms
    switch($eForm) {
        default:
        case 'default':
        case 'long':        $f .= $oF->DrawForm( $raBeanFormFull );   break;
        case 'short':       $f .= $oF->DrawForm( $raBeanFormShort );  break;
        case 'cgo':         $f .= $oF->DrawForm( $raBeanFormCGO );    break;
    }

    return ($f);
}


function garlicForm( SLProfilesDB $oDB, $kVI ){
$oF = new SLProfilesForm( $oDB, $kVI );
$oF->Update();

$f = $oF->Style();


$raGarlicForm = array(
	array( 'cmd'=>'head', 'head_EN'=>"garlic"),

    array( 'cmd'=>'section', 'title_EN'=>"Dates", 'title_FR'=>"Dates" ),
    array(     'cmd'=>'q_d', 'k'=>'garlic_SoD_d__sowdate' ),
    array(     'cmd'=>'q_d', 'k'=>'garlic_SoD_d__harvestdate' ),

    array( 'cmd'=>'section', 'title_EN'=>"When you planted the cloves", 'title_FR'=>"When you planted the cloves" ),
	array(     'cmd'=>'q_f', 'k'=>'garlic_SoD_f__sowdistance' ),
	array(     'cmd'=>'q_b', 'k'=>'garlic_SoD_b__mulch' ),
    array(     'cmd'=>'q_f', 'k'=>'garlic_SoD_f__mulchthickness' ),
    array(     'cmd'=>'q_s', 'k'=>'garlic_SoD_s__mulchmaterial' ),

    array( 'cmd'=>'section', 'title_EN'=>"Cultivation", 'title_FR'=>"Cultivation" ),
    array(     'cmd'=>'q_b', 'k'=>'garlic_SoD_b__irrigated' ),
    array(     'cmd'=>'q_b', 'k'=>'garlic_SoD_b__fertilized' ),
    array(     'cmd'=>'q_s', 'k'=>'garlic_SoD_s__fertilizerandamount' ),
    array(     'cmd'=>'q_m', 'k'=>'garlic_SoD_m__weedcontrol' ),

    array( 'cmd'=>'section', 'title_EN'=>"Mid-Season", 'title_FR'=>"Mid-Season" ),
    array(     'cmd'=>'q_m', 'k'=>'garlic_SoD_m__leafcolour' ),
    array(     'cmd'=>'q_f', 'k'=>'garlic_SoD_f__leaflength' ),
    array(     'cmd'=>'q_f', 'k'=>'garlic_SoD_f__leafwidth' ),
    array(     'cmd'=>'q_m', 'k'=>'garlic_SoD_m__foliage' ),
    array(     'cmd'=>'q_m', 'k'=>'garlic_GRIN_m__PLANTVIGOR' ),

    array( 'cmd'=>'section', 'title_EN'=>"Scapes", 'title_FR'=>"Scapes" ),
    array(     'cmd'=>'q_m', 'k'=>'garlic_SoD_m__scapeproduced' ),
    array(     'cmd'=>'q_m', 'k'=>'garlic_SoD_m__scaperemoved' ),
    array(     'cmd'=>'q_m', 'k'=>'garlic_SoD_m__scapestemshape' ),
    array(     'cmd'=>'q_m', 'k'=>'garlic_SoD_m__scapebulbils' ),

    array( 'cmd'=>'section', 'title_EN'=>"Harvest", 'title_FR'=>"Harvest" ),
    array(     'cmd'=>'q_f', 'k'=>'garlic_GRIN_f__PLANTHEIGHT' ),
    array(     'cmd'=>'q_i', 'k'=>'garlic_SoD_i__bulbharvest' ),
    array(     'cmd'=>'q_f', 'k'=>'garlic_GRIN_f__BULBDIAM' ),
    array(     'cmd'=>'q_m_t', 'k'=>'garlic_GRIN_m__BULBSHAPE' ),
    array(     'cmd'=>'q_m', 'k'=>'garlic_SoD_m__bulbskincolour' ),
    array(     'cmd'=>'q_m', 'k'=>'garlic_SoD_m__cloveskincolour' ),
    array(     'cmd'=>'q_m', 'k'=>'garlic_SoD_m__clovesperbulb' ),
    array(     'cmd'=>'q_i', 'k'=>'garlic_SoD_i__clovesperbulb' ),
    array(     'cmd'=>'q_m', 'k'=>'garlic_SoD_m__clovearrangement' ),
    array(     'cmd'=>'q_b', 'k'=>'garlic_SoD_b__bulbpeel' ),
    array(     'cmd'=>'q_b', 'k'=>'garlic_SoD_b__clovepeel' ),
);

$oF->SetDefs( SLDescDefsGarlic::$raDefsGarlic );  // this tells SLDescForm how to interpret the 'garlic' descriptors

$f .= $oF->DrawForm( $raGarlicForm );  // this tells SLDescForm to draw a form using those garlic descriptors, as organized in the array above

   //dw_sect( "Dates" );
return ($f);
}


function tomatoForm( SLProfilesDefs $oSLProfilesDefs, SLProfilesDB $oDB, int $kVI, string $eForm )
{
    $raTomatoFormCommon = [
    	['cmd'=>'head', 'head_EN'=>"tomato"],

        ['cmd'=>'section', 'title_EN'=>"Dates", 'title_FR'=>"Les dates"],
        [   'cmd'=>'q_d', 'k'=>'common_SoD_d__sowdate'],
        [   'cmd'=>'q_d', 'k'=>'common_SoD_d__transplantdate'],
        [   'cmd'=>'q_d', 'k'=>'common_SoD_d__flowerdate'],
        [   'cmd'=>'q_d', 'k'=>'common_SoD_d__fruitharvestdate'],
        [   'cmd'=>'q_d', 'k'=>'common_SoD_d__seedharvestdate'],

        ['cmd'=>'section', 'title_EN'=>"Population Counts", 'title_FR'=>"Population Counts"],
        [   'cmd'=>'q_i', 'k'=>'common_SoD_i__popinitial'],
        [   'cmd'=>'q_i', 'k'=>'common_SoD_i__germpercent'],
        [   'cmd'=>'q_i', 'k'=>'common_SoD_i__poptransplanted'],
        [   'cmd'=>'q_i', 'k'=>'common_SoD_i__plantsremoved'],
        [   'cmd'=>'q_i', 'k'=>'common_SoD_i__plantsdied'],
        [   'cmd'=>'q_i', 'k'=>'common_SoD_i__poppollinating'],
        [   'cmd'=>'q_i', 'k'=>'common_SoD_i__popharvestseeds'],
    ];

    $raTomatoFormFull = [
        ['cmd'=>'section', 'title_EN'=>"Mid-Season", 'title_FR'=>"Mid-Season"],
        [   'cmd'=>'inst', 'inst_EN'=>
                "For the next question: <br/> determinate (about 2-3 feet tall, produces one main crop of fruit then mostly stops growing, little if any side growth, usually don't need staking)"
               ."<br/> semi-determinate (about 3-5 feet tall, some slow side growth, grow well on short stakes)"
               ."<br/> indeterminate (continuously grows long vines with new flower clusters until frost, widely-spaced branches and lots of side shoots, needs staking)"],
        [   'cmd'=>'q_m', 'k'=>'tomato_SoD_m__planthabit'],
        [   'cmd'=>'q_m', 'k'=>'tomato_SoD_m__stempubescence'],
        [   'cmd'=>'q_m', 'k'=>'tomato_SoD_m__foliagedensity'],
        [   'cmd'=>'q_m', 'k'=>'tomato_SoD_m__leafattitude'],
        [   'cmd'=>'q_m_t', 'k'=>'tomato_SoD_m__leaftype'],
        [   'cmd'=>'q_m', 'k'=>'tomato_SoD_m__flowercolour'],
        [   'cmd'=>'q_m', 'k'=>'tomato_SoD_m__fruitcolourunripe'],

        ['cmd'=>'section', 'title_EN'=>"Late-Season", 'title_FR'=>"Late-Season"],
        [   'cmd'=>'q_f', 'k'=>'tomato_SoD_f__vinelength'],
        [   'cmd'=>'q_f', 'k'=>'tomato_SoD_f__internodelength'],

        ['cmd'=>'section', 'title_EN'=>"Fruit", 'title_FR'=>"Fruit"],
        [   'cmd'=>'inst', 'inst_EN'=>"Answer these questions when the fruit is fully ripe.  Please observe several typical fruit and average your observations."],
        [   'cmd'=>'q_m_t', 'k'=>'tomato_SoD_m__fruitshape'],
        [   'cmd'=>'q_m_t', 'k'=>'tomato_SoD_m__fruitshapecrosssection'],
        [   'cmd'=>'q_m', 'k'=>'tomato_SoD_m__fruitsize'],
        [   'cmd'=>'q_m', 'k'=>'tomato_SoD_m__fruitdetachment'],
        [   'cmd'=>'q_m', 'k'=>'tomato_SoD_m__fruitcolourexterior'],
        [   'cmd'=>'q_m', 'k'=>'tomato_SoD_m__fruitcolourinterior'],
        [   'cmd'=>'q_m', 'k'=>'tomato_GRIN_m__GELCOLOR'],
        [   'cmd'=>'q_m', 'k'=>'tomato_SoD_m__fruitfirmness'],
        [   'cmd'=>'q_m', 'k'=>'tomato_SoD_m__fruitpubescence'],
    ];

    $raTomatoFormShort = [
    ];

    $raTomatoFormCGO = [
        ['cmd'=>'section', 'title_EN'=>"Observations", 'title_FR'=>"Observations"],
        [   'cmd'=>'inst', 'inst_EN'=>"At each stage, remove any plants that are distinctly different (ie off types) than the majority"],
        [   'cmd'=>'q_m', 'k'=>'tomato_SoD_m__planthabit'],
        [   'cmd'=>'q_s', 'k'=>'tomato_SoD_s__aggressive'],
        [   'cmd'=>'q_m_t', 'k'=>'tomato_SoD_m__leaftype'],
        [   'cmd'=>'q_m', 'k'=>'tomato_SoD_m__flowercolour'],

        ['cmd'=>'section', 'title_EN'=>"Fruit", 'title_FR'=>"Fruit"],
        [   'cmd'=>'q_m_t', 'k'=>'tomato_SoD_m__fruitshape'],
        [   'cmd'=>'q_m_t', 'k'=>'tomato_SoD_m__fruitshapecrosssection'],
        [   'cmd'=>'q_m', 'k'=>'tomato_SoD_m__fruitsize'],
        [   'cmd'=>'q_m', 'k'=>'tomato_SoD_m__fruitdetachment'],
        [   'cmd'=>'q_m', 'k'=>'tomato_SoD_m__fruitcolourexterior'],
        [   'cmd'=>'q_m', 'k'=>'tomato_SoD_m__fruitcolourinterior'],
        [   'cmd'=>'q_m', 'k'=>'tomato_SoD_m__fruitfirmness'],
        [   'cmd'=>'q_m', 'k'=>'tomato_SoD_m__fruitsizeuniformity'],
        [   'cmd'=>'q_m', 'k'=>'tomato_SoD_m__fruitcategory'],

        ['cmd'=>'section', 'title_EN'=>"Health", 'title_FR'=>"Health"],
        [   'cmd'=>'q_b', 'k'=>'common_SoD_b__disease'],
        [   'cmd'=>'inst', 'inst_EN'=>"If yes, please describe and include photo if possible"],

        ['cmd'=>'section', 'title_EN'=>"Ratings", 'title_FR'=>"Ratings"],
        [   'cmd'=>'q_r', 'k'=>'common_SoD_r__productivity'],
        [   'cmd'=>'q_r', 'k'=>'common_SoD_r__flavour'],
        [   'cmd'=>'q_r', 'k'=>'common_SoD_r__diseaseresistance'],
        [   'cmd'=>'q_r', 'k'=>'common_SoD_r__uniformity'],
        [   'cmd'=>'q_r', 'k'=>'common_SoD_r__appeal'],

        [   'cmd'=>'q_b', 'k'=>'common_SoD_b__wouldyougrowagain'],
        [   'cmd'=>'q_b', 'k'=>'common_SoD_b__wouldyourecommend'],

        ['cmd'=>'section', 'title_EN'=>"Notes", 'title_FR'=>"Notes"],
        [   'cmd'=>'inst', 'inst_EN'=>"Please note any pros and cons related to growing this variety."],
        [   'cmd'=>'q_s',  'k'=>'common_SoD_s__notespros'],
        [   'cmd'=>'q_s',  'k'=>'common_SoD_s__notescons'],
        [   'cmd'=>'inst', 'inst_EN'=>"Any other comments or things worth noting? How would you describe the variety overall? Anything stand out? Is it good as a fresh eating bean? Good as a soup bean? Both? (etc)."],
        [   'cmd'=>'q_s',  'k'=>'common_SoD_s__notesgeneral'],
    ];

    $oF = new SLProfilesForm( $oDB, $kVI );
    $oF->Update();
    $oF->SetDefs( $oSLProfilesDefs->GetDefsRAFromSP('tomato') );

    $f = $oF->Style()
        .$oF->DrawForm($raTomatoFormCommon);        // draw the common parts of the forms
    switch($eForm) {
        default:
        case 'default':
        case 'long':        $f .= $oF->DrawForm( $raTomatoFormFull );   break;
        case 'short':       $f .= $oF->DrawForm( $raTomatoFormShort );  break;
        case 'cgo':         $f .= $oF->DrawForm( $raTomatoFormCGO );    break;
    }

    return ($f);
}


function pepperForm( SLProfilesDB $oDB, $kVI ){
$oF = new SLProfilesForm( $oDB, $kVI );
$oF->Update();

$f = $oF->Style();

$raPepperFormCommon = array(
    array( 'cmd'=>'head', 'head_EN'=>"pepper"),

    array( 'cmd'=>'section', 'title_EN'=>"Dates", 'title_FR'=>"Les dates" ),
    array(     'cmd'=>'q_d', 'k'=>'common_SoD_d__sowdate' ),
    array(     'cmd'=>'q_d', 'k'=>'common_SoD_d__flowerdate' ),
    array(     'cmd'=>'q_d', 'k'=>'common_SoD_d__harvestdate' ),

);

$oF->SetDefs( SLDescDefsCommon::$raDefsCommon );      // this tells SLDescForm how to interpret the 'common' descriptors
$f .= $oF->DrawForm( $raPepperFormCommon );  // this tells SLDescForm to draw a form using those common descriptors, as organized in the array above

$raPepperForm = array(

	array( 'cmd'=>'section', 'title_EN'=>"General", 'title_FR'=>"Genral" ),
	array(     'cmd'=>'q_m', 'k'=>'pepper_GRIN_m__COMMCAT' ),
	array(     'cmd'=>'q_m', 'k'=>'pepper_GRIN_m__PUNGENCY2' ),

	array( 'cmd'=>'section', 'title_EN'=>"Early-Season", 'title_FR'=>"Early-Season" ),
	array(     'cmd'=>'q_m', 'k'=>'pepper_SoD_m__stemcolour' ),
	array(     'cmd'=>'q_m', 'k'=>'pepper_SoD_m__stemshape' ),
	array(     'cmd'=>'q_m', 'k'=>'pepper_SoD_m__pubescence' ),
	array(     'cmd'=>'q_m', 'k'=>'pepper_SoD_m__branchhabit' ),

	array( 'cmd'=>'section', 'title_EN'=>"Leaves", 'title_FR'=>"Leaves" ),
	array(     'cmd'=>'q_m', 'k'=>'pepper_SoD_m__leafcolour' ),
	array(     'cmd'=>'q_m_t', 'k'=>'pepper_SoD_m__leafshape' ),
	array(     'cmd'=>'q_m', 'k'=>'pepper_GRIN_m__LEAFTEXT' ),
	array(     'cmd'=>'q_f', 'k'=>'pepper_SoD_f__leaflength' ),
	array(     'cmd'=>'q_f', 'k'=>'pepper_SoD_f__leafwidth' ),

	array( 'cmd'=>'section', 'title_EN'=>"Flowers", 'title_FR'=>"Flowers" ),
	array(     'cmd'=>'q_m', 'k'=>'pepper_SoD_m__flowerposition' ),
	array(     'cmd'=>'q_m', 'k'=>'pepper_SoD_m__flowercolour' ),
	array(     'cmd'=>'q_m', 'k'=>'pepper_SoD_m__anthercolour' ),

	array( 'cmd'=>'section', 'title_EN'=>"Fruit", 'title_FR'=>"Fruit" ),
	array(     'cmd'=>'q_m', 'k'=>'pepper_SoD_m__fruitcolourunripe' ),
	array(     'cmd'=>'q_m', 'k'=>'pepper_SoD_m__fruitcolourripe' ),
	array(     'cmd'=>'q_m', 'k'=>'pepper_GRIN_m__FRUITPOS' ),
	array(     'cmd'=>'q_m_t', 'k'=>'pepper_SoD_m__fruitshape' ),
	array(     'cmd'=>'q_f', 'k'=>'pepper_GRIN_f__FRUITLNGTH' ),

	array( 'cmd'=>'section', 'title_EN'=>"Late-Season", 'title_FR'=>"Late-Season" ),
	array(     'cmd'=>'q_f', 'k'=>'pepper_SoD_f__plantheight' ),
	array(     'cmd'=>'q_m', 'k'=>'pepper_GRIN_m__STEMNUM' ),
	array(     'cmd'=>'q_m', 'k'=>'pepper_SoD_m__planthabit' ),

	array( 'cmd'=>'section', 'title_EN'=>"Seeds", 'title_FR'=>"Seeds" ),
	array(     'cmd'=>'q_m', 'k'=>'pepper_SoD_m__seedcolour' ),
	array(     'cmd'=>'q_m', 'k'=>'pepper_SoD_m__seedsurface' ),

);

$oF->SetDefs( SLDescDefsPepper::$raDefsPepper );  // this tells SLDescForm how to interpret the 'garlic' descriptors

$f .= $oF->DrawForm( $raPepperForm );  // this tells SLDescForm to draw a form using those garlic descriptors, as organized in the array above

   //dw_sect( "Dates" );
return ($f);
}



function lettuceForm( SLProfilesDB $oDB, $kVI ){
$oF = new SLProfilesForm( $oDB, $kVI );
$oF->Update();

$f = $oF->Style();

$raLettuceFormCommon = array(
	array( 'cmd'=>'head', 'head_EN'=>"lettuce"),

    array( 'cmd'=>'section', 'title_EN'=>"Dates", 'title_FR'=>"Les dates" ),
    array(     'cmd'=>'q_d', 'k'=>'common_SoD_d__sowdate' ),
    array(     'cmd'=>'q_d', 'k'=>'common_SoD_d__lharvestdate' ),
    array(     'cmd'=>'q_d', 'k'=>'common_SoD_d__boltdate' ),
    array(     'cmd'=>'q_d', 'k'=>'common_SoD_d__flowerdate' ),
    array(     'cmd'=>'q_d', 'k'=>'common_SoD_d__seeddate' ),

);

$oF->SetDefs( SLDescDefsCommon::$raDefsCommon );      // this tells SLDescForm how to interpret the 'common' descriptors
$f .= $oF->DrawForm( $raLettuceFormCommon );  // this tells SLDescForm to draw a form using those common descriptors, as organized in the array above

$raLettuceForm = array(

	array( 'cmd'=>'section', 'title_EN'=>"Harvest", 'title_FR'=>"Harvest" ),
	array(     'cmd'=>'inst', 'inst_EN'=>"Please answer these questions when the lettuce is ready to harvest."),
    array(     'cmd'=>'q_m', 'k'=>'lettuce_GRIN_m__HEADTYPE' ),
    array(     'cmd'=>'q_m', 'k'=>'lettuce_GRIN_m__LEAFCOLOR' ),
	array(     'cmd'=>'q_m', 'k'=>'lettuce_GRIN_m__ANTHOCYAN' ),
	array(     'cmd'=>'q_f', 'k'=>'lettuce_GRIN_f__HEADDEPTH' ),
	array(     'cmd'=>'q_f', 'k'=>'lettuce_GRIN_f__HEADDIAM' ),
	array(     'cmd'=>'q_m', 'k'=>'lettuce_GRIN_m__HEADSOLID' ),
	array(     'cmd'=>'q_m', 'k'=>'lettuce_GRIN_m__LEAFCRISP' ),
	array(     'cmd'=>'q_f', 'k'=>'lettuce_SoD_f__LEAFDIMEN_L' ),
	array(     'cmd'=>'q_f', 'k'=>'lettuce_SoD_f__LEAFDIMEN_W' ),
	array(     'cmd'=>'q_m', 'k'=>'lettuce_GRIN_m__LEAFFOLD' ),
	array(     'cmd'=>'q_m_t', 'k'=>'lettuce_GRIN_m__LEAFSHAPE' ),

	array( 'cmd'=>'section', 'title_EN'=>"Flowers", 'title_FR'=>"Flowers" ),
	array(     'cmd'=>'inst', 'inst_EN'=>"Please answer these question if you allowed your lettuce to bolt and produce flowers."),
    array(     'cmd'=>'q_m', 'k'=>'lettuce_GRIN_m__FLOWERCOL' ),
	array(     'cmd'=>'q_f', 'k'=>'lettuce_GRIN_f__FLOWERDIAM' ),

	array( 'cmd'=>'section', 'title_EN'=>"Seeds", 'title_FR'=>"Seeds" ),
	array(     'cmd'=>'inst', 'inst_EN'=>"Please answer these question if you allowed your lettuce to mature and produce ripe seeds."),
    array(     'cmd'=>'q_f', 'k'=>'lettuce_GRIN_f__PLANTHGT' ),
	array(     'cmd'=>'q_m', 'k'=>'lettuce_GRIN_m__SEEDCOLOR' ),

);
$oF->SetDefs( SLDescDefsLettuce::$raDefsLettuce );  // this tells SLDescForm how to interpret the 'garlic' descriptors

$f .= $oF->DrawForm( $raLettuceForm );  // this tells SLDescForm to draw a form using those garlic descriptors, as organized in the array above

   //dw_sect( "Dates" );
return ($f);
}


function onionForm( SLProfilesDB $oDB, $kVI ){
$oF = new SLProfilesForm( $oDB, $kVI );
$oF->Update();

$f = $oF->Style();

$raOnionFormCommon = array(
	array( 'cmd'=>'head', 'head_EN'=>"onion"),

    array( 'cmd'=>'section', 'title_EN'=>"Dates", 'title_FR'=>"Les dates" ),
    array(     'cmd'=>'q_d', 'k'=>'common_SoD_d__sowdate' ),
    array(     'cmd'=>'q_d', 'k'=>'common_SoD_d__diestartdate' ),
    array(     'cmd'=>'q_d', 'k'=>'common_SoD_d__harvestdate' ),


);

$oF->SetDefs( SLDescDefsCommon::$raDefsCommon );      // this tells SLDescForm how to interpret the 'common' descriptors
$f .= $oF->DrawForm( $raOnionFormCommon );  // this tells SLDescForm to draw a form using those common descriptors, as organized in the array above

$raOnionForm = array(

	array( 'cmd'=>'section', 'title_EN'=>"Mid-Season", 'title_FR'=>"Mid-Season" ),
	array(     'cmd'=>'q_m', 'k'=>'onion_SoD_m__oniontype' ),
	array(     'cmd'=>'q_m', 'k'=>'onion_SoD_m__leafcolour' ),
	array(     'cmd'=>'q_m', 'k'=>'onion_SoD_m__leafattitude' ),
	array(     'cmd'=>'q_m', 'k'=>'onion_GRIN_m__LEAFSTRUCT' ),

	array( 'cmd'=>'section', 'title_EN'=>"Late-Season", 'title_FR'=>"Late-Season" ),
	array(     'cmd'=>'q_f', 'k'=>'onion_GRIN_f__PLANTHEIGHT' ),
	array(     'cmd'=>'q_f', 'k'=>'onion_SoD_f__leafwidth' ),

	array( 'cmd'=>'section', 'title_EN'=>"Flowers", 'title_FR'=>"Flowers" ),
	array(     'cmd'=>'inst', 'inst_EN'=>"This section is for onions that produce true flowers.  Note that some onions are biennial, producing flowers only in their second year.  Also, some produce topsets which are not flowers - make sure that you know the difference between a flower and a topset." ),
	array(     'cmd'=>'q_b', 'k'=>'onion_SoD_b__flowerability' ),
	array(     'cmd'=>'q_m', 'k'=>'onion_SoD_m__flowercolour' ),
	array(     'cmd'=>'q_m', 'k'=>'onion_GRIN_m__ANTHERCOL' ),

	array( 'cmd'=>'section', 'title_EN'=>"Bulbs", 'title_FR'=>"Bulbs" ),
	array(     'cmd'=>'q_f', 'k'=>'onion_GRIN_f__BULBDIAM' ),
	array(     'cmd'=>'q_m', 'k'=>'onion_GRIN_m__BULBSHAPE' ),
	array(     'cmd'=>'q_m', 'k'=>'onion_SoD_m__bulbskincolour' ),
	array(     'cmd'=>'q_m', 'k'=>'onion_SoD_m__bulbfleshcolour' ),
	array(     'cmd'=>'q_m', 'k'=>'onion_SoD_m__bulbhearts' ),

);
$oF->SetDefs( SLDescDefsOnion::$raDefsOnion );  // this tells SLDescForm how to interpret the 'garlic' descriptors

$f .= $oF->DrawForm( $raOnionForm );  // this tells SLDescForm to draw a form using those garlic descriptors, as organized in the array above

   //dw_sect( "Dates" );
return ($f);
}


function peaForm( SLProfilesDB $oDB, $kVI ){
$oF = new SLProfilesForm( $oDB, $kVI );
$oF->Update();

$f = $oF->Style();

$raPeaFormCommon = array(
    array( 'cmd'=>'head', 'head_EN'=>"pea"),

    array( 'cmd'=>'section', 'title_EN'=>"Dates", 'title_FR'=>"Les dates" ),
    array(     'cmd'=>'q_d', 'k'=>'common_SoD_d__sowdate' ),
    array(     'cmd'=>'q_d', 'k'=>'common_SoD_d__flowerdate' ),
    array(     'cmd'=>'q_d', 'k'=>'common_SoD_d__poddate' ),
    array(     'cmd'=>'q_d', 'k'=>'common_SoD_d__seeddate' ),


);

$oF->SetDefs( SLDescDefsCommon::$raDefsCommon );      // this tells SLDescForm how to interpret the 'common' descriptors
$f .= $oF->DrawForm( $raPeaFormCommon );  // this tells SLDescForm to draw a form using those common descriptors, as organized in the array above

$raPeaForm = array(
	/*
	array( 'cmd'=>'section', 'title_EN'=>"Sample", 'title_FR'=>"Sample" ),
	array(     'cmd'=>'inst', 'inst_EN'=>"Sample" ),
	array(     'cmd'=>'q_', 'k'=>'' ),
	*/
	array( 'cmd'=>'section', 'title_EN'=>"Seedling", 'title_FR'=>"Seedling" ),
	array(     'cmd'=>'q_m', 'k'=>'pea_GRIN_m__COTYLCOLOR' ),

	array( 'cmd'=>'section', 'title_EN'=>"Flower", 'title_FR'=>"Flower" ),
	array(     'cmd'=>'q_m', 'k'=>'pea_GRIN_m__FLOWERCOL' ),
	array(     'cmd'=>'q_i', 'k'=>'pea_GRIN_i__FLOWPEDUNC' ),

	array( 'cmd'=>'section', 'title_EN'=>"Harvest", 'title_FR'=>"Harvest" ),
	array(     'cmd'=>'q_f', 'k'=>'pea_SoD_f__LEAFLENGTH' ),
	array(     'cmd'=>'q_f', 'k'=>'pea_SoD_f__LEAFWIDTH' ),
	array(     'cmd'=>'q_f', 'k'=>'pea_GRIN_f__HEIGHT' ),
	array(     'cmd'=>'q_f', 'k'=>'pea_GRIN_f__INTERNODE' ),
	array(     'cmd'=>'q_i', 'k'=>'pea_GRIN_i__NODES' ),
	array(     'cmd'=>'q_f', 'k'=>'pea_GRIN_f__STEMDIAM' ),

	array( 'cmd'=>'section', 'title_EN'=>"Pods", 'title_FR'=>"Pods" ),
	array(     'cmd'=>'q_m', 'k'=>'pea_GRIN_m__PODTYPE' ),
	array(     'cmd'=>'q_m', 'k'=>'pea_GRIN_m__PODCOLOR' ),
	array(     'cmd'=>'q_m', 'k'=>'pea_GRIN_m__PODSHAPE' ),
	array(     'cmd'=>'q_m', 'k'=>'pea_GRIN_m__PODAPEX' ),
	array(     'cmd'=>'q_f', 'k'=>'pea_GRIN_f__PODLENGTH' ),
	array(     'cmd'=>'q_f', 'k'=>'pea_GRIN_f__PODWIDTH' ),

	array( 'cmd'=>'section', 'title_EN'=>"Seeds", 'title_FR'=>"Seeds" ),
	array(     'cmd'=>'q_m', 'k'=>'pea_GRIN_m__HILUMCOLOR' ),
	array(     'cmd'=>'inst', 'inst_EN'=>"Most pea seeds are uniform green/white in colour, but some have other colours or markings." ),
	array(     'cmd'=>'q_m', 'k'=>'pea_GRIN_m__SDCOATCOL' ),
	array(     'cmd'=>'q_m', 'k'=>'pea_GRIN_m__SDPATTERN' ),
	array(     'cmd'=>'q_m', 'k'=>'pea_GRIN_m__SDPATCOLOR' ),
	array(     'cmd'=>'q_m', 'k'=>'pea_GRIN_m__SEEDSURF' ),
	array(     'cmd'=>'q_i', 'k'=>'pea_GRIN_i__SEEDSPOD' ),
	array(     'cmd'=>'q_m', 'k'=>'pea_GRIN_m__STEMFASC' ),

);
$oF->SetDefs( SLDescDefsPea::$raDefsPea );  // this tells SLDescForm how to interpret the 'garlic' descriptors

$f .= $oF->DrawForm( $raPeaForm );  // this tells SLDescForm to draw a form using those garlic descriptors, as organized in the array above

   //dw_sect( "Dates" );
return ($f);
}


function potatoForm( SLProfilesDB $oDB, $kVI ){
$oF = new SLProfilesForm( $oDB, $kVI );
$oF->Update();

$f = $oF->Style();


$raPotatoFormCommon = array(
	array( 'cmd'=>'head', 'head_EN'=>"potato"),

    array( 'cmd'=>'section', 'title_EN'=>"Dates", 'title_FR'=>"Les dates" ),
    array(     'cmd'=>'q_d', 'k'=>'common_SoD_d__sowdate' ),
    array(     'cmd'=>'q_d', 'k'=>'common_SoD_d__diestartdate' ),
    array(     'cmd'=>'q_d', 'k'=>'common_SoD_d__dieenddate' ),


);

$oF->SetDefs( SLDescDefsCommon::$raDefsCommon );      // this tells SLDescForm how to interpret the 'common' descriptors
$f .= $oF->DrawForm( $raPotatoFormCommon );  // this tells SLDescForm to draw a form using those common descriptors, as organized in the array above

$raPotatoForm = array(

	array( 'cmd'=>'section', 'title_EN'=>"Mid-Season", 'title_FR'=>"Mid-Season" ),
	array(     'cmd'=>'q_m', 'k'=>'potato_SoD_m__foliagematurity' ),
	array(     'cmd'=>'q_m', 'k'=>'potato_SoD_m__planthabit' ),
	array(     'cmd'=>'q_m', 'k'=>'potato_SoD_m__flowerfrequency' ),
	array(     'cmd'=>'q_s', 'k'=>'potato_SoD_s__flowercolour' ),
	array(     'cmd'=>'q_m', 'k'=>'potato_SoD_m__berrynumber' ),
	array(     'cmd'=>'q_m', 'k'=>'potato_GRIN_m__VIGOR' ),
	array(     'cmd'=>'q_s', 'k'=>'potato_SoD_s__leafcolour' ),

	array( 'cmd'=>'section', 'title_EN'=>"Tubers", 'title_FR'=>"Tubers" ),
	array(     'cmd'=>'q_m', 'k'=>'potato_SoD_m__tubergreening' ),
	array(     'cmd'=>'q_s', 'k'=>'potato_SoD_s__tubershape' ),
	array(     'cmd'=>'q_s', 'k'=>'potato_SoD_s__tuberskincolour' ),
	array(     'cmd'=>'q_m', 'k'=>'potato_SoD_m__skintexture' ),
	array(     'cmd'=>'q_s', 'k'=>'potato_SoD_s__tuberfleshcolour' ),
	array(     'cmd'=>'q_s', 'k'=>'potato_SoD_s__tubereyecolour' ),
	array(     'cmd'=>'q_m', 'k'=>'potato_SoD_m__eyedepth' ),
	array(     'cmd'=>'q_m', 'k'=>'potato_SoD_m__tubernumber' ),
	array(     'cmd'=>'q_m', 'k'=>'potato_SoD_m__tubersize' ),
	array(     'cmd'=>'q_m', 'k'=>'potato_SoD_m__hollowheart' ),
	array(     'cmd'=>'q_m', 'k'=>'potato_SoD_m__resistdamageexternal' ),
	array(     'cmd'=>'q_m', 'k'=>'potato_SoD_m__resistdamageinternal' ),
	array(     'cmd'=>'q_m', 'k'=>'potato_SoD_m__storageability' ),
	array(     'cmd'=>'q_m', 'k'=>'potato_SoD_m__drought' ),
	array(     'cmd'=>'q_m', 'k'=>'potato_SoD_m__frost' ),

);

$oF->SetDefs( SLDescDefsPotato::$raDefsPotato );  // this tells SLDescForm how to interpret the 'garlic' descriptors

$f .= $oF->DrawForm( $raPotatoForm );  // this tells SLDescForm to draw a form using those garlic descriptors, as organized in the array above

   //dw_sect( "Dates" );
return ($f);
}


function squashForm( SLProfilesDB $oDB, $kVI ){
$oF = new SLProfilesForm( $oDB, $kVI );
$oF->Update();

$f = $oF->Style();

$raSquashFormCommon = array(
	array( 'cmd'=>'head', 'head_EN'=>"squash"),
    array( 'cmd'=>'section', 'title_EN'=>"Dates", 'title_FR'=>"Les dates" ),
    array(     'cmd'=>'q_d', 'k'=>'common_SoD_d__sowdate' ),
    array(     'cmd'=>'q_d', 'k'=>'common_SoD_d__flowerdatemale' ),
    array(     'cmd'=>'q_d', 'k'=>'common_SoD_d__flowerdatefemale' ),
    array(     'cmd'=>'q_d', 'k'=>'common_SoD_d__harvestdate' ),


);

$oF->SetDefs( SLDescDefsCommon::$raDefsCommon );      // this tells SLDescForm how to interpret the 'common' descriptors
$f .= $oF->DrawForm( $raSquashFormCommon );  // this tells SLDescForm to draw a form using those common descriptors, as organized in the array above

$raSquashForm = array(
	array( 'cmd'=>'section', 'title_EN'=>"Mid-Season", 'title_FR'=>"Mid-Season" ),
	array(     'cmd'=>'q_m', 'k'=>'squash_GRIN_m__PLANTHABIT' ),
	array(     'cmd'=>'q_m', 'k'=>'squash_GRIN_m__VIGOR' ),

	array( 'cmd'=>'section', 'title_EN'=>"Leaves", 'title_FR'=>"Leaves" ),
	array(     'cmd'=>'q_f', 'k'=>'squash_SoD_f__leaflength' ),
	array(     'cmd'=>'q_f', 'k'=>'squash_SoD_f__leafwidth' ),
	array(     'cmd'=>'q_s', 'k'=>'squash_SoD_s__leafshape' ),

	array( 'cmd'=>'section', 'title_EN'=>"Flowers", 'title_FR'=>"Flowers" ),
	array(     'cmd'=>'q_s', 'k'=>'squash_SoD_s__flowercolour' ),
	array(     'cmd'=>'q_f', 'k'=>'squash_SoD_f__flowerlength' ),
	array(     'cmd'=>'q_f', 'k'=>'squash_SoD_f__flowerwidth' ),
	array(     'cmd'=>'q_s', 'k'=>'squash_SoD_s__anthercolour' ),

	array( 'cmd'=>'section', 'title_EN'=>"Fruit", 'title_FR'=>"Fruit" ),
	array(     'cmd'=>'q_b', 'k'=>'squash_GRIN_b__UNIFORMITY' ),
	array(     'cmd'=>'q_m', 'k'=>'squash_GRIN_m__FRUITCOLOR' ),
	array(     'cmd'=>'q_m', 'k'=>'squash_GRIN_m__FRUITSPOT' ),
	array(     'cmd'=>'q_m', 'k'=>'squash_GRIN_m__FLESHCOLOR' ),
	array(     'cmd'=>'q_f', 'k'=>'squash_GRIN_f__FLESHDEPTH' ),
	array(     'cmd'=>'q_f', 'k'=>'squash_GRIN_f__FRUITLEN' ),
	array(     'cmd'=>'q_f', 'k'=>'squash_GRIN_f__FRUITDIAM' ),
	array(     'cmd'=>'q_m', 'k'=>'squash_GRIN_m__FRUITRIB' ),
	array(     'cmd'=>'q_m', 'k'=>'squash_GRIN_m__FRUITSET' ),

	array( 'cmd'=>'section', 'title_EN'=>"Seeds", 'title_FR'=>"Seeds" ),
	array(     'cmd'=>'q_s', 'k'=>'squash_SoD_s__seedcolour' ),
	array(     'cmd'=>'q_m', 'k'=>'squash_SoD_m__seednumber' ),
	array(     'cmd'=>'q_f', 'k'=>'squash_SoD_f__seedlength' ),

);

$oF->SetDefs( SLDescDefsSquash::$raDefsSquash );  // this tells SLDescForm how to interpret the 'garlic' descriptors

$f .= $oF->DrawForm( $raSquashForm );  // this tells SLDescForm to draw a form using those garlic descriptors, as organized in the array above

   //dw_sect( "Dates" );
return ($f);
}


?>
