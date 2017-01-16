<?php

/* msdCommon
 *
 * Copyright (c) 2017 Seeds of Diversity Canada
 *
 * Member Seed Directory methods common to multiple applications
 */

include_once( SEEDCORE."SEEDBasket.php" );
include_once( SEEDAPP."basket/basketProductHandlers_seeds.php" );
include_once( SEEDAPP."basket/basketProductHandlers.php" );     // SEEDBasketProducts_SoD

class MSDCommonDraw
{
    public $oW;
    public $oSB;

    function __construct( $oW )
    {
        $this->oW = $oW;

// make this take $oW
        $this->oSB = new SEEDBasketCore( $oW->kfdb, $oW->sess, //SEEDBasketProducts_SoD::$raProductTypes );
                                                               array( 'seeds'=>SEEDBasketProducts_SoD::$raProductTypes['seeds'] ) );
    }

    function DrawMSDList()
    {
        $sMSDList = "";

        $raCat = $this->oSB->oDB->GetList( "PxPE", "product_type='seeds' AND PE.k='category'",
                                           array('sGroupCol'=>'PE_v', 'sSortCol'=>'PE_v', 'raFieldsOverride'=>array('PE_v'=>'v')) );
        foreach( $raCat as $ra ) {
            $raSp = $this->oSB->oDB->GetList( "PxPE2", "product_type='seeds' AND PE1.k='category' "
                                             ."AND PE1.v='".addslashes($ra['PE_v'])."' AND PE2.k='species'",
                                              array('sGroupCol'=>'PE2_v', 'sSortCol'=>'PE2_v', 'raFieldsOverride'=>array('PE2_v'=>'v')) );

            $sMSDList .= "<div class='msd-list-category'>"
                            ."<div class='msd-list-category-title'>{$ra['PE_v']}</div>"
                            ."<div class='msd-list-species-group'>"
                                .SEEDCore_ArrayExpandRows( $raSp, "<div class='msd-list-species-title'>[[PE2_v]]</div>" )
                            ."</div>"
                        ."</div>";
        }

        $sMSDList = "<div class='msd-list'>$sMSDList</div>";

        return( $sMSDList );
    }
}

?>