<?php

class SLDescDefsGarlic
{

static public $raDefsGarlic = array(
//Dates
'garlic_SoD_d__sowdate'    => array( 'l_EN' => "Date cloves planted",
                                     'q_EN' => "Date when you planted the cloves?" ),

'garlic_SoD_d__harvestdate'=> array( 'l_EN' => "Date bulbs harvested",
                                     'q_EN' => "Date when the bulbs were ready to harvest?" ),

//Planted
'garlic_SoD_f__sowdistance'=> array( 'l_EN' => "Cloves spacing",
                                     'q_EN' => "How far apart did you plant the cloves? (cm)"),

'garlic_SoD_b__mulch'      => array( 'l_EN' => "Mulch",
                                     'q_EN' => "Did you mulch over winter?"),

'garlic_SoD_f__mulchthickness' => array( 'l_EN' => "Mulch thickness",
                                         'q_EN' => "If you mulched, how thickly (cm)?"),

'garlic_SoD_s__mulchmaterial' => array( 'l_EN' => "Mulch Material",
                                        'q_EN' => "With what material?"),

//Cultivation
'garlic_SoD_b__irrigated'  => array( 'l_EN' => "Regular watering",
                                     'q_EN' => "Was the garlic watered regularly during spring and summer? (rain or irrigation)"),

'garlic_SoD_b__fertilized' => array( 'l_EN' => "Fertalized",
                                     'q_EN' => "Did you fertilize the garlic?"),

'garlic_SoD_s__fertilizerandamount' => array( 'l_EN' => "Type and amount of Fertilizer",
                                              'q_EN' => "If you fertilized, with what and about how much?"),

'garlic_SoD_m__weedcontrol'=> array( 'l_EN' => "Weed Control",
                                     'q_EN' => "Did you control weeds in the garlic bed?",
                                     'm' => array(
                                            0=>"don't know",
                                            1=>"no, it was weedy",
                                            2=>"mulched",
                                            3=>"hand weeded",
                                            4=>"other") ),

//Mid-season
'garlic_SoD_m__leafcolour' => array( 'l_EN' => "Leaf Colour",
                                     'q_EN' => "What colour are the leaves?",
                                     'm' => array(
                                            0=>"don't know",
                                            1=>"light green",
                                            2=>"yellow green",
                                            3=>"green",
                                            4=>"grey green",
                                            5=>"dark green",
                                            6=>"bluish green",
                                            7=>"purplish green") ),

'garlic_SoD_f__leaflength' => array( 'l_EN' => "Leaf Length",
                                     'q_EN' => "How long are the leaves(cm), on average? (measure five typical leaves from the stem to the tips)"),

'garlic_SoD_f__leafwidth'  => array( 'l_EN' => "Leaf Width",
                                     'q_EN' => "How wide are the leaves(cm), on average? (measure five typical leaves close to the stem)"),

'garlic_SoD_m__foliage'    => array( 'l_EN' => "Leaf Angle",
                                     'q_EN' => "At what angle do the leaves extend from the stem?",
                                     'm' => array(
                                            0=>"don't know",
                                            3=>"close to horizontal",
                                            5=>"close to 45 degrees",
                                            7=>"close to vertical") ),

'garlic_GRIN_m__PLANTVIGOR'=> array( 'l_EN' => "How vigorous",
                                     'q_EN' => "How vigorous is this garlic compared to other typical varieties?",
                                     'm' => array(
                                            0=>"don't know",
                                            1=>"very weak",
                                            3=>"weak",
                                            5=>"normal",
                                            7=>"strong",
                                            9=>"very strong") ),

//Scapes
'garlic_SoD_m__scapeproduced'=>array('l_EN' => "Scapes grew",
                                     'q_EN' => "Did this garlic produce scapes?",
                                     'm' => array(
                                            0=>"don't know",
                                            1=>"yes",
                                            2=>"no",
                                            3=>"sometimes") ),

'garlic_SoD_m__scaperemoved'=>array( 'l_EN' => "Scapes removed",
                                     'q_EN' => "Did you remove the scapes when they appeared?",
                                     'm' => array(
                                            0=>"don't know",
                                            1=>"yes",
                                            2=>"no",
                                            3=>"some") ),

'garlic_SoD_m__scapestemshape'=>array('l_EN'=>"Scape Stem",
                                      'q_EN'=>"What shape was the scape stem?",
                                      'm'=> array(
                                            0=>"don't know",
                                            1=>"coiled",
                                            2=>"curved",
                                            3=>"straight",
                                            4=>"mixed") ),

'garlic_SoD_m__scapebulbils'=>array( 'l_EN' => "Scape bulbils",
                                     'q_EN' => "How many bulbils were in each scape, on average?",
                                     'm' => array(
                                            0=>"don't know",
                                            1=>"none",
                                            2=>"1-9",
                                            3=>"10-19",
                                            4=>"20+") ),

//Harvest
'garlic_GRIN_f__PLANTHEIGHT'=>array( 'l_EN' => "Plant Height",
                                     'q_EN' => "How tall were the plants(cm), on average?  (measure five typical plants from the ground to the top leaf node)"),

'garlic_SoD_i__bulbharvest'=> array( 'l_EN' => "Number of Bulbs Harvested",
                                     'q_EN' => "How many bulbs did you harvest?"),

'garlic_GRIN_f__BULBDIAM'  => array( 'l_EN' => "Bulb Diameter",
                                     'q_EN' => "What is the average bulb diameter (cm)?  (measure five typical bulbs at their widest diameters)"),

'garlic_GRIN_m__BULBSHAPE' => array( 'l_EN' => "Bulb side view Shape",
                                     'q_EN' => "What shape is the bulb, viewed from the side?",
                                     'm' => array(
                                            0=>"don't know",
                                            2=>"flat bottom",
                                            4=>"flat indented bottom",
                                            6=>"round",
                                            10=>"teardrop",
                                            101=>"other"),
                                     'img'=>array(
                                            2=>"garlic/garlic BULBSHAPE_02.gif",
                                            4=>"garlic/garlic BULBSHAPE_04.gif",
                                            6=>"garlic/garlic BULBSHAPE_06.gif",
                                            10=>"garlic/garlic BULBSHAPE_10.gif"),
                                     'imgParms' => array("imgH" => 100 ) ),

'garlic_SoD_m__bulbskincolour'=>array('l_EN'=> "Fresh bulb skin colour",
                                      'q_EN'=> "What colour are the fresh bulb skins? (remove the first few to find a clean skin)",
                                      'm'=> array(
                                            0=>"don't know",
                                            1=>"white",
                                            2=>"cream",
                                            3=>"beige",
                                            // 4=white stripes,
                                            5=>"light violet",
                                            6=>"violet",
                                            // 7=dark violet,
                                            101=>"brown",
                                            102=>"striped brown",
                                            103=>"red",
                                            104=>"striped violet") ),

'garlic_SoD_m__cloveskincolour'=>array('l_EN'=>"Clove skin colour",
                                       'q_EN'=>"What colour are the clove skins?",
                                       'm'=>array(
                                            0=>"don't know",
                                            1=>"white",
                                            2=>"yellow / light brown",
                                            3=>"brown",
                                            4=>"red",
                                            5=>"violet") ),

'garlic_SoD_i__clovesperbulb'=>array('l_EN' => "Cloves in each bulb",
                                     'q_EN' => "How many cloves are in each bulb, on average?"),
'garlic_SoD_m__clovesperbulb'=>array('l_EN' => "Cloves in each bulb",
                                     'q_EN' => "How many cloves are in each bulb, on average?",
                                     'm' => array(
                                            0=>"don't know",
                                            1=>"1",
                                            2=>"2-4",
                                            3=>"5-10",
                                            4=>"11-15",
                                            5=>"16-20",
                                            6=>"20-50",
                                            7=>"more than 50") ),

'garlic_SoD_m__clovearrangement' => array( 'l_EN' => "Cloves arrangement",
                                           'q_EN' => "How are the cloves arranged in the bulb?",
                                           'm' => array(
                                            0=>"don't know",
                                            1=>"one circle of wedge-shaped cloves",
                                            2=>"two or more rings of cloves, uniform sizes, evenly spaced",
                                            3=>"cloves irregularly spaced, different sizes",
                                            4=>"other") ),

'garlic_SoD_b__bulbpeel'    => array( 'l_EN' => "Bulb wrappers peel",
                                     'q_EN' => "Are the bulb wrappers easy to peel when dry?"),

'garlic_SoD_b__clovepeel'    => array( 'l_EN' => "CLoves peel",
                                     'q_EN' => "Are the  cloves easy to peel when dry?"),

);

static public $raDefsCodesGarlic = array(
//Dates
'garlic_SoD_d__sowdate',
'garlic_SoD_d__harvestdate',
//Planted
'garlic_SoD_f__sowdistance',
'garlic_SoD_b__mulch',
'garlic_SoD_f__mulchthickness',
'garlic_SoD_s__mulchmaterial',
//Cultivation
'garlic_SoD_b__irrigated',
'garlic_SoD_b__fertilized',
'garlic_SoD_s__fertilizerandamount',
'garlic_SoD_m__weedcontrol',
//Mid-season
'garlic_SoD_m__leafcolour',

'garlic_SoD_f__leaflength',
'garlic_SoD_f__leafwidth',
'garlic_SoD_m__foliage',
'garlic_GRIN_m__PLANTVIGOR',
//Scapes
'garlic_SoD_m__scapeproduced',
'garlic_SoD_m__scaperemoved',
'garlic_SoD_m__scapestemshape',
'garlic_SoD_m__scapebulbils',
//Harvest
'garlic_GRIN_f__PLANTHEIGHT',
'garlic_SoD_i__bulbharvest',
'garlic_GRIN_f__BULBDIAM',
'garlic_GRIN_m__BULBSHAPE',
'garlic_SoD_m__bulbskincolour',
'garlic_SoD_m__cloveskincolour',
'garlic_SoD_i__clovesperbulb',
'garlic_SoD_m__clovesperbulb',
'garlic_SoD_m__clovearrangement',
'garlic_SoD_b__bulbpeel',
'garlic_SoD_b__clovepeel',
);

}

?>