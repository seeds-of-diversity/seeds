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

        if( !($kfrVI = $this->oProfilesDB->GetKFR( 'VI', $kVI )) ) goto done;
        list($sp,$cv) = $this->oProfilesDB->ComputeVarInstName( $kfrVI->ValuesRA() );

        $raDO = $this->oProfilesDB->GetList( 'Obs', "fk_sl_varinst='$kVI'" );
        $defsRA = $this->oProfilesDefs->GetDefsRAFromSP( $sp );
//var_dump($raVI);
//var_dump($raDO);

        $s = "<table class='sldesc_VIRecord_table' border='0' cellspacing='5' cellpadding='5'>";
        if( $bBasic ) {
            $s .= "<tr><td width='250'><b>Species:</b></td><td width='200'>".ucwords($sp)."</td></tr>"
                 ."<tr><td><b>Variety:</b></td><td> ".ucwords($cv)."</td></tr>"
                 ."<tr><td><b>Year:</b></td><td> ".$kfrVI->Value('year')."</td></tr>"
                 ."<tr><td><b>Location:</b></td><td> ".$kfrVI->Value('Site_province')."</td></tr>";
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
            include_once( SEEDCOMMON."sl/desc/".$sp.".php" );

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
            include_once( SEEDCOMMON."sl/desc/_sl_desc.php" );
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

?>
