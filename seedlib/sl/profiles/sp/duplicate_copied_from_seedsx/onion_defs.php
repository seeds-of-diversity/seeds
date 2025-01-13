<?php
class SLDescDefsOnion
{

static public $raDefsOnion = array(
//Mid-Season
'onion_SoD_m__oniontype'   => array( 'l_EN' => "Onion Type",
									 'q_EN' => "What kind of onion would you call this?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"dry bulb onion",
											2=>"shallot",
											3=>"japanese bunching onion/welsh onion",
											101=>"other") ),

'onion_SoD_m__leafcolour'  => array( 'l_EN' => "Leaf Colour",
									 'q_EN' => "What colour are the leaves?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"light green",
											2=>"yellow green",
											3=>"green",
											4=>"grey green",
											5=>"dark green",
											6=>"bluish green",
											7=>"purplish-green") ),

'onion_SoD_m__leafattitude'=> array( 'l_EN' => "Leaf grow direction",
									 'q_EN' => "What direction do the leaves grow, in general?",
									 'm' => array(
									 		0=>"don't know",
									 		3=>"prostrate or spreading horizontally",
											5=>"intermediate",
											7=>"erect vertically") ),

'onion_GRIN_m__LEAFSTRUCT' => array( 'l_EN'=> "Inside leaf",
									 'q_EN' => "When you cut a leaf, what is inside?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"leaf is hollow",
											2=>"leaf is solid") ),

//Late Season
'onion_GRIN_f__PLANTHEIGHT'=> array( 'l_EN' => "Leaf length",
									 'q_EN' => "How long are the longest leaves, on average (cm)? (do not measure the flower stalk)"),

'onion_SoD_f__leafwidth'   => array( 'l_EN' => "Leaf width",
									 'q_EN' => "What is the average diameter of the leaves (cm)? (measure five typical leaves at their widest diameters)"),

//FLowers
'onion_SoD_b__flowerability'=>array( 'l_EN' => "Produces Flower",
									 'q_EN' => "Does this onion produce a flower?"),

'onion_SoD_m__flowercolour'=> array( 'l_EN' => "Flower Colour",
									 'q_EN' => "What colour is the flower?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"white",
											2=>"pink",
											3=>"violet",
											4=>"other") ),

'onion_GRIN_m__ANTHERCOL'  => array( 'l_EN' => "Anther Colour",
									 'q_EN' => "What colour are the anthers (pollen bearing structures that extend up from the open flowers)?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"blue",
											2=>"cream",
											3=>"green",
											5=>"orange",
											6=>"purple",
											7=>"white",
											8=>"yellow",
											4=>"mix") ),

//Bulbs
'onion_GRIN_f__BULBDIAM'   => array( 'l_EN' => "Mature bulbs diameter",
									 'q_EN' => "What is the average diameter of the mature bulbs (cm)? (measure five typical bulbs at their widest diameters)"),

'onion_GRIN_m__BULBSHAPE'  => array( 'l_EN' => "Mature bulb shape",
									 'q_EN' => "What shape are the mature bulbs?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"cylinder",
											2=>"flat bottom",
											3=>"flat globe",
											4=>"flat indented bottom",
											5=>"flat",
											6=>"globe",
											7=>"high globe",
											8=>"high top",
											//9=>"oval",
											9=>"spindle",
											10=>"teardrop",
											11=>"thickflat") ),

'onion_SoD_m__bulbskincolour'=>array('l_EN' => "Bulb skin colour",
									 'q_EN' => "What colour is the bulb skin?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"white",
											2=>"yellow",
											3=>"yellow and light brown",
											4=>"light brown",
											5=>"brown",
											6=>"dark brown",
											7=>"green",
											8=>"light violet",
											9=>"dark violet") ),

'onion_SoD_m__bulbfleshcolour'=>array('l_EN'=> "Bulb flesh colour",
									 'q_EN' => "What colour is the bulb flesh?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"white",
											2=>"cream",
											3=>"green and white",
											4=>"violet and white") ),

'onion_SoD_m__bulbhearts'  => array( 'l_EN' => "Number of Hearts",
									 'q_EN' => "How many hearts do these onions have?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"single heart",
											2=>"2-3 hearts",
											3=>"more than 3 hearts") ),

);

}
?>