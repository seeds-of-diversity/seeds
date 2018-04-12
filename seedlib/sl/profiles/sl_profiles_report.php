<?php

/* Crop Profiles reporting
 *
 * Copyright (c) 2009-2018 Seeds of Diversity Canada
 *
 */


class SLProfilesReport
{
    private $oProfilesDB;
    private $oProfilesDefs;
    private $lang;

    function __construct( SLProfilesDB $oProfilesDB, SLProfilesDefs $oProfilesDefs, $lang, $raParms = array() )
    {
        $this->oProfilesDB   = $oProfilesDB;
        $this->oProfilesDefs = $oProfilesDefs;
        $this->lang = $lang;

    }

    function DrawVIRecord( $kVI, $bBasic = true )
    /********************************************
        Show the record for a variety/site/year
     */
    {
        $s = "";

        if( !($kfrVI = $this->oProfilesDB->GetKFR( 'VISite', $kVI )) ) goto done;
        list($sp,$cv) = $this->oProfilesDB->ComputeVarInstName( $kfrVI->ValuesRA() );

        $raDO = $this->oProfilesDB->GetList( 'Obs', "fk_sl_varinst='$kVI'" );
        $defsRA = $this->oProfilesDefs->GetDefsRAFromSP( $sp );
//var_dump($raVI);
//var_dump($raDO);

        $s = "<table class='sldesc_VIRecord_table' border='0' cellspacing='5' cellpadding='5'>";
        if( $bBasic ) {
            $s .= "<tr><td width='250'><b>Observer:</b></td><td width='200'>".$kfrVI->Value('Site_uid')."</td></tr>"
                 ."<tr><td width='250'><b>Species:</b></td><td width='200'>".ucwords($sp)."</td></tr>"
                 ."<tr><td><b>Variety:</b></td><td> ".ucwords($cv)."</td></tr>"
                 ."<tr><td><b>Year:</b></td><td> ".$kfrVI->Value('year')."</td></tr>"
                 ."<tr><td><b>Location:</b></td><td> ".$kfrVI->Value('Site_province')."</td></tr>"
	         ."<tr><td><hr/></td><td>&nbsp;</td></tr>";
        }
        foreach( $raDO as $obs ) {
            if( !($def = @$defsRA[ $obs['k'] ]) ) continue;

            $v = @$obs['v'];
            $l = @$def['l_EN'];

            if( ($vl = @$def['m'][$v]) ) {  // the multi-choice text value corresponding to the numerical value
                $vl = ucwords( $vl );
                if( ($vimg = @$def['img'][$v]) ) {
                    $s .= "<tr><td><b>$l:</b></td><td>$vl</td><td><img src='".W_ROOT."seedcommon/sl/descimg/$vimg' height='75'/></td></tr>";
                } else {
                    $s .= "<tr><td><b>$l:</b></td><td>$vl</td></tr>";
                }
            } else {
                $s .= "<tr><td><b>$l:</b></td><td>".$obs['v']."</td></tr>";
            }
        }
        $s .= "</table>";

        done:
        return( $s );
    }

    function DrawVIForm( $kVI )
    {
        $s = "";

        if( !($kfrVI = $this->oProfilesDB->GetKFR( 'VI', $kVI )) ) goto done;
        list($sp,$cv) = $this->oProfilesDB->ComputeVarInstName( $kfrVI->ValuesRA() );

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

        $s .= $this->drawObservationForm( $kfrVI, 'default' );

        done:
        return( $s );
    }

    function drawObservationForm( $kfrVI, $eForm )
    {
        $kVI = $kfrVI->Key();
        list($sp,$cv) = $this->oProfilesDB->ComputeVarInstName( $kfrVI->ValuesRA() );

//        $oKForm = $this->_getKFUFormVI( $kfr );

        $s = "<FORM method='post' action='${_SERVER['PHP_SELF']}'>"
            ."<DIV style='border:1px solid #eee;padding:10px'>";
//            ."<FIELDSET class='slUserForm-NO-NOT-THIS-IF-THE-DESCRIPTOR-FORMS-ARE-DRAWN-HERE'>"
//            ."<LEGEND style='font-weight:bold'>Edit this Variety Record</LEGEND>";

        /* eForm is either:
               default    = make a form from all the soft-coded tags for the current species
               {psp}      = use a hard-coded form (should be one of the hard-coded species names)
               {number}   = the _key of a sl_desc_cfg_form
         */
        if( true ) { //@$this->raSpecies[$eForm]['hardform'] ) {
            //include_once( SEEDCOMMON."sl/desc/".$sp.".php" );

            switch( $sp ) {
                //case "apple"  : $s .=   appleForm( $this->oSLDescDB, $this->kVI); break;
                case "bean"   : $s .=    beanForm( $this->oProfilesDB, $kVI); break;
                //case "garlic" : $s .=  garlicForm( $this->oSLDescDB, $this->kVI); break;
                //case "lettuce": $s .= lettuceForm( $this->oSLDescDB, $this->kVI); break;
                //case "onion"  : $s .=   onionForm( $this->oSLDescDB, $this->kVI); break;
                //case "pea"    : $s .=     peaForm( $this->oSLDescDB, $this->kVI); break;
                //case "pepper" : $s .=  pepperForm( $this->oSLDescDB, $this->kVI); break;
                //case "potato" : $s .=  potatoForm( $this->oSLDescDB, $this->kVI); break;
                //case "squash" : $s .=  squashForm( $this->oSLDescDB, $this->kVI); break;
                //case "tomato" : $s .=  tomatoForm( $this->oSLDescDB, $this->kVI); break;
            }
        } else {
            $oF = new SLDescForm( $this->oSLDescDB, $this->kVI );
            $oF->Update();

            $oF->LoadDefs( $osp );

            $s .= $oF->Style();

            if( $eForm == 'default' ) {
                $s .= $oF->DrawDefaultForm( $osp );

            } else if( ($kForm = intval($eForm)) ) {
                $ra = $this->kfdb->QueryRA( "SELECT * from sl_desc_cfg_forms WHERE _key='$kForm'" );
                if( $ra['species'] == $osp ) {
                    $s .= $oF->DrawFormExpandTags( $ra['form'] );
                }
            }
        }


        $s .= SEEDForm_Hidden( 'action', 'profileUpdate' )
             .SEEDForm_Hidden( 'kVi', $kVI )
             //.SEEDForm_Hidden( 'kVI', $this->kVI )
            ."<BR/><LABEL>&nbsp;</LABEL><INPUT type='submit' value='Save' class='slUserFormButton' />"
//            ."</FIELDSET>"
            ."</DIV></FORM>";

        return( $s );
    }
}



function beanForm( SLProfilesDB $oDB, $kVI ){
$oF = new SLProfilesForm( $oDB, $kVI );
$oF->Update();

$f = $oF->Style();
//TODO: TERMI_SHAPE needs a picture
//TODO: GRAI_COLOR should be in the next section (snap harvest)

$raBeanFormCommon = array(
	array( 'cmd'=>'head', 'head_EN'=>"bean"),

    array( 'cmd'=>'section', 'title_EN'=>"Dates", 'title_FR'=>"Les dates" ),
    array(     'cmd'=>'q_d', 'k'=>'common_SoD_d__sowdate' ),
    array(     'cmd'=>'q_d', 'k'=>'common_SoD_d__flowerdate' ),
    array(     'cmd'=>'q_d', 'k'=>'common_SoD_d__poddate' ),
    array(     'cmd'=>'q_d', 'k'=>'common_SoD_d__seeddate' ),
);
$oF->SetDefs( SLDescDefsCommon::$raDefsCommon );      // this tells SLDescForm how to interpret the 'common' descriptors
$f .= $oF->DrawForm( $raBeanFormCommon );  // this tells SLDescForm to draw a form using those common descriptors, as organized in the array above

$raBeanForm = array(
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

$oF->SetDefs( SLDescDefsBean::$raDefsBean );  // this tells SLDescForm how to interpret the 'garlic' descriptors

$f .= $oF->DrawForm( $raBeanForm );  // this tells SLDescForm to draw a form using those garlic descriptors, as organized in the array above

   //dw_sect( "Dates" );
return ($f);
}


?>
