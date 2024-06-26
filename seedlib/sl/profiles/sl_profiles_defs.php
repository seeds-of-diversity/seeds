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
        $this->raDefs['bean'] = array_merge( SLProfilesDefs::$raSLDescDefsCommon, SLDescDefsBean::$raDefsBean );
        $this->raDefs['garlic'] = SLDescDefsGarlic::$raDefsGarlic;
        $this->raDefs['lettuce'] = SLDescDefsLettuce::$raDefsLettuce;
        $this->raDefs['onion'] = SLDescDefsOnion::$raDefsOnion;
        $this->raDefs['pea'] = SLDescDefsPea::$raDefsPea;
        $this->raDefs['pepper'] = SLDescDefsPepper::$raDefsPepper;
        $this->raDefs['potato'] = SLDescDefsPotato::$raDefsPotato;
        $this->raDefs['squash'] = SLDescDefsSquash::$raDefsSquash;
        $this->raDefs['tomato'] = array_merge( SLProfilesDefs::$raSLDescDefsCommon, SLDescDefsTomato::$raDefsTomato );

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
        return( @$this->raDefs[$sp] ?: [] );
    }

    static $raSLDescDefsCommon = [
        'common_SoD_d__sowdate'     	=> array( 'l_EN' => "Sowing date",
                                             	  'q_EN' => "Date when you sowed the seeds?" ),
        'common_SoD_d__flowerdate'  	=> array( 'l_EN' => "First flowering date",
                                             	  'q_EN' => "Date when the first flowers opened?" ),
        'common_SoD_d__poddate'     	=> array( 'l_EN' => "First edible pod date",
                                             	  'q_EN' => "(If an edible pod variety) Date when the first pod was ready to eat?" ),
        'common_SoD_d__seeddate'    	=> array( 'l_EN' => "First seed harvest date",
                                             	  'q_EN' => "Date when the first dry seeds were ready to harvest?" ),
        'common_SoD_d__lharvestdate' 	=> array( 'l_EN' => "First lettuce harvest date",
                                             	  'q_EN' => "Date when the lettuce was just ready to harvest?" ),
        'common_SoD_d__harvestdate' 	=> array( 'l_EN' => "First fruit harvest date",
                                             	  'q_EN' => "Date when the first fruit ripened?" ),
        'common_SoD_d__leafdate'    	=> array( 'l_EN' => "First leaf date",
                                             	  'q_EN' => "Date of first leaf appearance?" ),
        'common_SoD_d__boltdate'    	=> array( 'l_EN' => "First bolt date",
                                             	  'q_EN' => "Date when the plant began to bolt (elongated, became bitter, no longer fit to eat)?" ),
        'common_SoD_d__diestartdate'	=> array( 'l_EN' => "Dying Start date",
                                             	  'q_EN' => "Date when the leaves/vines began to die down?" ),
        'common_SoD_d__dieenddate'      => array( 'l_EN' => "Dying End date",
                                             	  'q_EN' => "Date when the leaves/vines had completely died back?" ),
        'common_SoD_d__flowerdatemale'  => array( 'l_EN' => "Male flower date",
                                             	  'q_EN' => "Date when the first male flower opened?" ),
        'common_SoD_d__flowerdatefemale'=> array( 'l_EN' => "Female flower date",
                                             	  'q_EN' => "Date when the first female flower opened?" ),

        'common_SoD_i__samplesize'      => array( 'l_EN' => "Sample size",
                                                  'q_EN' => "Approximately how many plants"./*($dw_sp=='apple' ? "trees" : "plants").*/" are you observing for the purposes of this form?" ),

        'common_SoD_i__popinitial'      => ['l_EN' => "Initial population size",
                                            'q_EN' => "How many seeds did you sow in total?"],
        'common_SoD_i__germpercent'     => ['l_EN' => "Germination rate",
                                            'q_EN' => "What percentage of the seeds germinated?"],
        'common_SoD_i__plantsremoved'   => ['l_EN' => "Plants removed",
                                            'q_EN' => "How many plants did you remove because weak, diseased, or off type?"],
        'common_SoD_i__plantsdied'      => ['l_EN' => "Plants died",
                                            'q_EN' => "How many plants died of other causes?"],
        'common_SoD_i__poppollinating'  => ['l_EN' => "Pollinating population",
                                            'q_EN' => "How many plants remained at time of flowering?"],
        'common_SoD_i__popharvestseeds' => ['l_EN' => "Final population",
                                            'q_EN' => "How many plants did you harvest seeds from at the end of the season?"],
    ];

}
