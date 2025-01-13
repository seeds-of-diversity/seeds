<?php
class SLDescDefsApple
{

static public $raDefsApple = array(
//General
'apple_SoD_i__age'         => array( 'l_EN' => "Age of tree",
									 'q_EN' => "On average, approximately how many years old are the tree(s)?"),
'apple_SoD_m__condition'   => array( 'l_EN' => "Tree Condition",
									 'q_EN' => "What is the general condition of the tree(s) (please use a separate form for groups of trees of different condition)?",
									 'm' => array(
									 		0 => "don't know",
									 		2 => "dying",
									 		3 => "old, declining",
									 		4 => "mature, diseased",
									 		5 => "mature, non-vigorous",
									 		6 => "mature, vigorous",
									 		7 => "non-bearing",
									 		8 => "healthy, cropping poorly",
									 		9 => "healthy, cropping well") ),
//Spring
'apple_SoD_m__hardy'	   => array( 'l_EN' => "Cold-Hardiness",
									 'q_EN' => "How cold-hardy does this variety seem?",
									 'm' => array(
									 		0 => "don't know",
									 		1 => "extremely hardy",
									 		3 => "hardy",
											5 => "intermediate",
											7 => "tender",
											9 => "extremely tender") ),

'apple_SoD_m__habit'	   => array( 'l_EN' => "Main Growth Habit",
									 'q_EN' => "What is the main growth habit (shape) of this variety?",
									 'm' => array(
									 		0 =>"don't know",
									 		1 =>"extremely upright",
											3 =>"upright",
											4 =>"spreading",
											7 =>"drooping",
											9 =>"weeping") ),

'apple_SoD_m__vigour'      => array( 'l_EN' => "How Vigorous Variety Seems",
									 'q_EN' => "How vigorous does this variety seem?",
									 'm' => array(
									 		0 => "don't know",
									 		1 => "extremely weak",
									 		3 => "weak",
									 		5 => "intermediate",
									 		7 => "vigorous",
									 		9 => "extremely vigorous") ),
//flowers
'apple_SoD_m__flowerseason'=> array( 'l_EN' => "Flower timing",
									 'q_EN' => "When did the tree(s) flower, compared to other typical varieties?",
									 'm' => array(
									 		0 => "don't know",
									 		1 =>"extremely early",
											2 =>"very early",
											3 =>"early",
											5 =>"intermediate",
											7 =>"late",
											8 =>"very late",
											9 =>"extremely late") ),

'apple_SoD_i__flowerduration'=> array('l_EN'=> "Time stayed in Flower",
									  'q_EN'=> "Approximately how many days did the tree(s) remain in flower? (answer only if older than 4 years)"),

'apple_SoD_m__flowerregularity'=>array('l_EN'=>"Flower Regularity",
									   'q_EN'=>"Do the tree(s) flower every year, or irregularly?",
									   'm'=>array(
									   		0 => "don't know",
									   		1 => "extremly irregularly",
									   		3 => "irregularly, some years but no pattern",
									   		5 => "regularly every other year (biennial)",
									   		7 => "almost every year",
									   		9 => "every year") ),

'apple_SoD_m__flowersecondary'=>array('l_EN'=> "Second Flower Period",
									  'q_EN'=> "Does this variety have a definite second period of flowering in the same season?",
									  'm'=> array(
									  		0 => "don't know",
									  		1 => "very rarely",
									  		3 => "rarely",
									  		5 => "sometimes",
									  		7 => "frequently",
									  		9 => "nearly always") ),

'apple_SoD_m__selfcompatible'=> array('l_EN'=> "Pollinates itself",
									  'q_EN'=> "Is the variety self-compatible, i.e. can it pollinate itself?",
									  'm'=> array(
									  		0 => "don't know",
									  		1 => "incompatible",
									  		2 => "very poor",
									  		3 => "poor",
									  		5 => "intermediate",
									  		7 => "good",
									  		8 => "very good",
									  		9 => "extremely") ),

'apple_GRIN_m__ANTHERCOL'  => array( 'l_EN' => "Anther Colour",
									 'q_EN' => "What colour is the anther before pollen is released?",
									 'm' => array(
									 		0 => "don't know",
									 		1 => "white",
											2 => "white-yellow",
											3 => "white-pink",
											4 => "yellow",
											5 => "yellow-purple",
											6 => "orange-yellow",
											7 => "orange",
											8 => "pink",
											9 => "pink-red",
											10 => "red",
											11 => "purple") ),

'apple_GRIN_m__CARPELARR'  => array( 'l_EN' => "Cut Base Cross-section shape",
									 'q_EN' => "Cut the base of a flower in cross-section. What shape do you see inside?",
									 'm' => array(
									 		0 => "don't know",
									 		1 => "closed star shape",
											2 => "open star shape, not joined",
											3 => "both open and closed") ),

'apple_GRIN_m__FLWRCOLOR'  => array( 'l_EN' => "Flower Colour",
									 'q_EN' => "What colour are the flowers at full bloom?",
									 'm' => array(
									 		0 =>"don't know",
									 		1 =>"white",
											2 =>"white-pink",
											3 =>"white-green",
											4 =>"white-pink and red",
											5 =>"white-pink and purple",
											6 =>"pink",
											7 =>"pink-red",
											8 =>"pink-purple",
											9 =>"purple-white",
											10 =>"red-purple",
											11 =>"black-purple",
											12 =>"green") ),

'apple_GRIN_f__FLOWERSIZE' => array( 'l_EN' => "Flower Diameter",
									 'q_EN' => "What is the average diameter of the flowers, at ful bloom (mm)? (measure five typical flowers on vigorous branches)"),

'apple_GRIN_i__FLWRINFLOR' => array( 'l_EN' => "Flowers per bud",
									 'q_EN' => "How many flowers emerge from each flower bud, on average?"),

//Leaves
'apple_GRIN_m__LFHAIRSURF' => array( 'l_EN' => "Fuzzy Leaves",
									 'q_EN' => "How fuzzy are the leaves, especially the underside?",
									 'm' => array(
									 		0 => "don't know",
									 		1 =>"smooth, no hairs visible",
											2 =>"slightly fuzzy",
											3 =>"fuzzy") ),

'apple_GRIN_f__LEAFLENGTH' => array( 'l_EN' => "Leaf Length",
									 'q_EN' => "How long are the leaves, on average (mm)?  (measure from the base of the leaf to the tip)"),
									//was a picture right after this but not included with it

'apple_GRIN_m__LEAFSHAPE'  => array( 'l_EN' => "Leaf Shape",
									 'q_EN' => "What shape are the leaves?  (look at leaves in the middle section of a shoot, not at the tip)",
									 'm' => array(
									 		0 =>"don't know",
									 		1 =>"round",
											2 =>"ellipse",
											3 =>"wide ellipse",
											4 =>"narrow ellipse",
											5 =>"long ellipse",
											6 =>"egg-shaped",
											7 =>"wide egg-shaped",
											8 =>"long egg-shaped",
											9 =>"wider at tip"),
									 'img' => array(
                                   			1=>"apple/apple LEAFSHAPE_1.gif",
											2=>"apple/apple LEAFSHAPE_2.gif",
											3=>"apple/apple LEAFSHAPE_3.gif",
											4=>"apple/apple LEAFSHAPE_4.gif",
											5=>"apple/apple LEAFSHAPE_5.gif",
											6=>"apple/apple LEAFSHAPE_6.gif",
											7=>"apple/apple LEAFSHAPE_7.gif",
											8=>"apple/apple LEAFSHAPE_8.gif",
											9=>"apple/apple LEAFSHAPE_9.gif"),

                                     'imgParms' => array( "imgH" => 60 ) ),

'apple_GRIN_m__LEAFLOBING' => array( 'l_EN' => "Leaf Lobes",
									 'q_EN' => "Do the leaves have lobes (a wavy shape on the edges)?",
									 'm' => array(
									 		0 =>"don't know",
									 		1 =>"not lobed",
											2 =>"partly lobed",
											3 =>"fully lobed") ),
//Fruit
'apple_SoD_m__fruitshape'  => array( 'l_EN' => "Apple Shape",
									 'q_EN' => "What shape are the apples?",
									 'm' => array(
									 		0 =>"don't know",
									 		1=>"globose-conical",
											2=>"short-globose-conical",
											3=>"flat",
											4=>"flat-globose (oblate)",
											5=>"conical",
											6=>"intermediate-conical",
											7=>"ellipsoid",
											8=>"ellipsoid-conical (ovate)",
											9=>"oblong",
											10=>"oblong-conical",
											11=>"oblong-waisted"),
									 'img' => array(
									 		1=>"apple/apple fruitshape_01.gif",
											2=>"apple/apple fruitshape_02.gif",
											3=>"apple/apple fruitshape_03.gif",
											4=>"apple/apple fruitshape_04.gif",
											5=>"apple/apple fruitshape_05.gif",
											6=>"apple/apple fruitshape_06.gif",
											7=>"apple/apple fruitshape_07.gif",
											8=>"apple/apple fruitshape_08.gif",
											9=>"apple/apple fruitshape_09.gif",
											10=>"apple/apple fruitshape_10.gif",
											11=>"apple/apple fruitshape_11.gif"),
									 'imgParms' => array( "imgH" => 60) ),

'apple_GRIN_m__TOPFRTSHAPE'=> array( 'l_EN' => "Top View Shape",
									 'q_EN' => "Viewed from the top, are the apples round or do they have angular bumps?",
									 'm' => array(
									 		0 => "don't know",
									 		1 => "with angles",
											2 => "round"),
									 'img' => array(
									 		1=>"apple/apple TOPFRTSHAPE_1.gif",
											2=>"apple/apple TOPFRTSHAPE_2.gif"),
									 'imgParms' => array( "imgH" => 80) ),

'apple_GRIN_m__FRUITTENAC' => array( 'l_EN' => "Fruit drop tendencies",
									 'q_EN' => "Do the fruit tend to drop by themselves or stay on the tree?",
									 'm' => array(
									 		0 => "don't know",
									 		1 => "drops before mature",
											2 => "holds until mature, then drops",
											3 => "holds on the tree well into winter") ),

'apple_SoD_m__fruitearliness'=>array('l_EN' => "Ripen time",
									 'q_EN' => "How early or late do the apples ripen, compared with other typical varieties?",
									 'm' => array(
									 		0 => "don't know",
									 		2 => "very early",
											3 => "early",
											5 => "mid-season",
											7 => "late",
											8 => "very late") ),

'apple_SoD_m__fruitsize'   => array( 'l_EN' => "Fruit Size",
									 'q_EN' => "How large are the fruit compared with typical commercial apples?",
									 'm' => array(
									 		0 => "don't know",
									 		2 => "very small",
											3 => "small",
											5 => "medium",
											7 => "large",
											8 => "very large") ),

'apple_SoD_m__skingroundcolour'=>array('l_EN'=>"Skin ground colour",
									   'q_EN'=>"What is the ground colour of the apple skin when fully ripe?",
									   'm'=>array(
									   		0=>"don't know",
									   		1=>"red",
											2=>"orange",
											3=>"cream-white",
											4=>"yellow",
											5=>"green-yellow",
											6=>"green") ),

'apple_SoD_m__skinovercolour'=>array('l_EN' => "Skin over colour",
									 'q_EN' => "What is the over colour of the apple skin when fully ripe?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"orange",
											2=>"pink",
											3=>"red",
											4=>"dark red",
											5=>"purple",
											6=>"brown") ),

'apple_SoD_m__skinoverpattern'=>array('l_EN'=> "Skin over colour pattern",
									  'q_EN'=> "What is the pattern of the over colour when fully ripe?",
									  'm'=> array(
									  		0=>"don't know",
									  		1=>"striped",
											2=>"streaked",
											3=>"mottled",
											4=>"splashed",
											5=>"slightly blushed",
											6=>"washed-out (faded)",
											7=>"complete coverage") ),

'apple_GRIN_m__FRUITBLOOM' => array( 'l_EN' => "Amount of Wax (Bloom)",
									 'q_EN' => "Many apples have a natural wax (called bloom) on the skin of the mature fruit.  Rate the amount of wax on this variety.",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"none",
											2=>"slightly waxy",
											3=>"moderately waxy",
											4=>"very waxy") ),

'apple_SoD_m__bruising'    => array( 'l_EN' => "Bruise Tendencies",
									 'q_EN' => "Do the apples bruise easily when fully ripe?",
									 'm' => array(
									 		0=>"don't know",
									 		2=>"bruise very easily",
											3=>"bruise easily",
											5=>"intermediate",
											7=>"resistant to bruising",
											8=>"very resistant to bruising") ),

'apple_GRIN_i__CARPELNUM'  => array( 'l_EN' => "Star Sections in cross-section",
									 'q_EN' => "Cut a ripe apple in cross-section.  In most common varieties you would see a five-sectioned star shape in the apple core.  How many sections does this variety have?"),

'apple_GRIN_m__FRTFLSHCOL' => array( 'l_EN' => "Interior flesh colour",
									 'q_EN' => "What colour is the apple's interior flesh when fully ripe?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"green",
											2=>"white",
											//3=>"green",   NB: 1 and 3 are duplicates - check GRIN
											4=>"pink",
											5=>"red",
											6=>"yellow",
											7=>"orange") ),

'apple_GRIN_m__FRTFLSHFRM' => array( 'l_EN' => "Interior Firmness",
									 'q_EN' => "Rate the firmness of the apple's interior flesh whne fully ripe.",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"soft",
											2=>"semifirm",
											3=>"firm",
											4=>"hard") ),

'apple_SoD_m__texture'     => array( 'l_EN' => "Interior Texture",
									 'q_EN' => "Rate the texture of the apple's interior flesh when fully ripe.",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"extremely coarse",
											2=>"coarse",
											5=>"intermediate",
											7=>"fine",
											9=>"extremely fine") ),

'apple_GRIN_m__FRTFLSHFLA' => array( 'l_EN' => "Flavour",
									 'q_EN' => "What flavour best descibes this variety when fully ripe?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"aromatic",
											2=>"sweet",
											3=>"subacid (mildly tart)",
											4=>"acid (tart)",
											5=>"astringent (very sour)") ),

'apple_GRIN_m__SEEDCOLOR'  => array( 'l_EN' => "Fully ripe seed colour",
									 'q_EN' => "What colour are the seeds when the apple is fully ripe?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"brown",
											2=>"light brown",
											3=>"dark brown",
											4=>"black brown",
											5=>"red brown",
											6=>"gray brown") ),

'apple_GRIN_m__SEEDSHAPE'  => array( 'l_EN' => "Fully ripe seed shape",
									 'q_EN' => "What general shape are the seeds when they the apple is fully ripe?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"ovate",
											2=>"ovate and oblong",
											3=>"wide ovate",
											4=>"narrow ovate",
											5=>"nearly round") ),

);
}
?>