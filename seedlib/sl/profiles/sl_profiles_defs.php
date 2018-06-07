<?php

/* Crop Profiles definitions
 *
 * Copyright (c) 2009-2018 Seeds of Diversity Canada
 *
 * Descriptor definitions for the observations that are recorded in Crop Profiles.
 */

include_once( SEEDCOMMON."/sl/desc/apple_defs.php" );
include_once( SEEDCOMMON."/sl/desc/bean_defs.php" );
include_once( SEEDCOMMON."/sl/desc/garlic_defs.php" );
include_once( SEEDCOMMON."/sl/desc/lettuce_defs.php" );
include_once( SEEDCOMMON."/sl/desc/onion_defs.php" );
include_once( SEEDCOMMON."/sl/desc/pea_defs.php" );
include_once( SEEDCOMMON."/sl/desc/pepper_defs.php" );
include_once( SEEDCOMMON."/sl/desc/potato_defs.php" );
include_once( SEEDCOMMON."/sl/desc/squash_defs.php" );
include_once( SEEDCOMMON."/sl/desc/tomato_defs.php" );
include_once( SEEDCOMMON."/sl/desc/common_defs.php" );


class SLProfilesDefs
/*******************
 */
{
    private $oProfilesDB;
    private $raDefs = array();

    public $raSpecies = array( 'apple',
//                               'barley',
                               'bean',
                               'garlic',
                               'lettuce',
                               'onion',
                               'pea',
                               'pepper',
                               'potato',
                               'squash',
                               'tomato',
  //                             'wheat'
                              );


    function __construct( SLProfilesDB $oProfilesDB )
    {
        $this->oProfilesDB = $oProfilesDB;

//        $this->oDescDB_Cfg = new SLDescDB_Cfg( $oDescDB->kfdb, $oDescDB->uid );     // added this in a klugey way

        $this->raDefs['apple'] = SLDescDefsApple::$raDefsApple;
        $this->raDefs['bean'] = array_merge( SLDescDefsCommon::$raDefsCommon, SLDescDefsBean::$raDefsBean );
        $this->raDefs['garlic'] = SLDescDefsGarlic::$raDefsGarlic;
        $this->raDefs['lettuce'] = SLDescDefsLettuce::$raDefsLettuce;
        $this->raDefs['onion'] = SLDescDefsOnion::$raDefsOnion;
        $this->raDefs['pea'] = SLDescDefsPea::$raDefsPea;
        $this->raDefs['pepper'] = SLDescDefsPepper::$raDefsPepper;
        $this->raDefs['potato'] = SLDescDefsPotato::$raDefsPotato;
        $this->raDefs['squash'] = SLDescDefsSquash::$raDefsSquash;
        $this->raDefs['tomato'] = array_merge( SLDescDefsCommon::$raDefsCommon, SLDescDefsTomato::$raDefsTomato );

//        $this->raDefs['brassica'] = $this->getDefsFromDB( 'brassica' );
//        $this->raDefs['corn']     = $this->getDefsFromDB( 'corn' );
    }

    private function getDefsFromDB( $species )
    {
        $defs = array();
        if( ($kfrcTags = $this->oProfilesDB->GetKFRC( "CfgTags", "tag LIKE '$species%'" )) ) {
            while( $kfrcTags->CursorFetch() ) {
                $tag = $kfrcTags->value('tag');
                $defs[$tag] = array( 'l_EN'=>$kfrTags->Value('label_en'),
                                     'l_FR'=>$kfrTags->Value('label_fr'),
                                     'q_EN'=>$kfrTags->Value('q_en'),
                                     'q_FR'=>$kfrTags->Value('q_fr') );
                $raM = $this->oProfilesDB->GetList( 'CfgM', "tag='$tag'" );
                if( count($raM) ) {
                    foreach( $raM as $ra ) {
                        $defs[$tag]['m'][$ra['v']] = $ra['l_en'];
                    }
                }
            }
        }
        return( $defs );
    }

    function GetDefsRAFromCode( $code )
    /**********************************
     */
    {
        switch( substr( $code, 0, strpos($code,"_") ) ) {
            case "garlic":  return( $this->raDefs['garlic'] );
        }
        return( array() );
    }

    function GetDefsRAFromSP( $sp )
    /******************************
     */
    {
        $sp = strtolower($sp);
        return( @$this->raDefs[$sp] ?: array() );

    }
}

?>
