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

include_once(SEEDLIB."sl/profiles/sp/ground-cherry.php");


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
        $this->raDefs['ground-cherry'] = array_merge( SLProfilesDefs::$raSLDescDefsCommon, SLProfiles_GroundCherry::GetDefs() );

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
        'common_SoD_d__sowdate'         => ['l_EN' => "Sowing date",
                                            'q_EN' => "Date when you sowed the seeds?"],
        'common_SoD_d__germdate'        => ['l_EN' => "Seed germinated date",
                                            'q_EN' => "Date when the first seeds germinated?"],
        'common_SoD_d__transplantdate'  => ['l_EN' => "Transplant date",
                                            'q_EN' => "Date when you planted the seedlings in the garden/field?"],
        'common_SoD_d__flowerdate'  	=> array( 'l_EN' => "First flowering date",
                                             	  'q_EN' => "Date when the first flowers opened?" ),
        'common_SoD_d__poddate'     	=> ['l_EN' => "First edible pod date",
                                             	  'q_EN' => "(If an edible pod variety) Date when the first fresh pods were ready to eat?"],
        'common_SoD_d__seeddate'    	=> array( 'l_EN' => "First seed harvest date",
                                             	  'q_EN' => "Date when the first dry seeds were ready to harvest?" ),
        'common_SoD_d__lharvestdate' 	=> array( 'l_EN' => "First lettuce harvest date",
                                             	  'q_EN' => "Date when the lettuce was just ready to harvest for eating?" ),
        'common_SoD_d__fruitharvestdate'=> ['l_EN' => "First fruit harvest date",
                                            'q_EN' => "Date when the first fruit ripened?"],
        'common_SoD_d__seedharvestdate' => ['l_EN' => "First seed harvest date",
                                            'q_EN' => "Date when the first seeds were ready to harvest?"],
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
        'common_SoD_i__poptransplanted' => ['l_EN' => "Transplanted population size",
                                            'q_EN' => "How many seedlings did you plant in the garden/field?"],
        'common_SoD_i__plantsremoved'   => ['l_EN' => "Plants removed",
                                            'q_EN' => "How many plants did you remove because weak, diseased, or off types?"],
        'common_SoD_i__plantsdied'      => ['l_EN' => "Plants died",
                                            'q_EN' => "How many plants died of other causes?"],
        'common_SoD_i__poppollinating'  => ['l_EN' => "Pollinating population",
                                            'q_EN' => "How many plants remained at time of flowering?"],
        'common_SoD_i__popharvestseeds' => ['l_EN' => "Final population",
                                            'q_EN' => "How many plants did you harvest seeds from at the end of the season?"],

        'common_SoD_b__disease'         => ['l_EN' => "Signs of disease",
                                            'q_EN' => "Were there any signs of disease on any part of the plants? (please include description and photo if possible)"],


        'common_SoD_r__productivity'    => ['l_EN' => "Productivity",
                                            'q_EN' => "Productivity"],
        'common_SoD_r__flavour'         => ['l_EN' => "Flavour",
                                            'q_EN' => "Flavour"],
        'common_SoD_r__diseaseresistance' => ['l_EN' => "Disease resistance",
                                            'q_EN' => "Disease resistance"],
        'common_SoD_r__uniformity'      => ['l_EN' => "Uniformity",
                                            'q_EN' => "Uniformity (size, shape, growth habit, colour)"],
        'common_SoD_r__appeal'          => ['l_EN' => "General appeal",
                                            'q_EN' => "General appeal"],


        'common_SoD_b__wouldgrowagain'  => ['l_EN' => "Would grow again",
                                            'q_EN' => "Would you want to grow this variety again?"],
        'common_SoD_b__wouldrecommend'  => ['l_EN' => "Would recommend",
                                            'q_EN' => "Would you recommend this variety to a grower in your area if they were looking for one with its general characteristics (growth habit, size, etc)"],

        'common_SoD_s__notespros'       => ['l_EN' => "Pros",
                                            'rows' => 3,
                                            'q_EN' => "Pros (what you like about this variety)"],
        'common_SoD_s__notescons'       => ['l_EN' => "Cons",
                                            'rows' => 3,
                                            'q_EN' => "Cons (what you don't like about this variety)"],
        'common_SoD_s__notesgeneral'    => ['l_EN' => "Notes",
                                            'rows' => 5,
                                            'q_EN' => "Any other information you'd like to give"],

    ];

}
