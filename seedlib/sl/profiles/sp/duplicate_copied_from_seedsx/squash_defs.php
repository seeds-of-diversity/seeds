<?php
class SLDescDefsSquash
{

static public $raDefsSquash = array(
//Mid-Season
'squash_GRIN_m__PLANTHABIT'=> array( 'l_EN' => "Plant vine habits",
									 'q_EN' => "Is this a \"bush\" variety (compact, short vines) or a \"vine\" variety (long spreading vines)?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"bush",
											2=>"vine") ),

'squash_GRIN_m__VIGOR'     => array( 'l_EN' => "Plant vigorous",
									 'q_EN' => "How vigorous is this variety?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"very weak",
											3=>"weak",
											5=>"normal",
											7=>"strong",
											9=>"excellent") ),

//Leaves
'squash_SoD_f__leaflength' => array( 'l_EN' => "Leaf length",
									 'q_EN' => "How long are the leaves, on average (cm)? (measure from the base of the leaf to the tip)"),

'squash_SoD_f__leafwidth'  => array( 'l_EN' => "Leaf width",
									 'q_EN' => "How wide are the leaves, measured from the widest point (cm)?"),

'squash_SoD_s__leafshape'  => array( 'l_EN' => "Leaf shape",
									 'q_EN' => "What shape are the leaves?"),

//Flowers
'squash_SoD_s__flowercolour'=>array( 'l_EN' => "Flower colour",
									 'q_EN' => "What is the main colour of the flowers?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"white",
											2=>"pale yellow",
											3=>"lemon yellow",
											4=>"deep yellow or orange-yellow",
											5=>"other") ),

'squash_SoD_f__flowerlength'=>array( 'l_EN' => "Flower length",
									 'q_EN' => "How long are the flowers, on average (cm)? (measure from the base to the tip of five closed flowers that are ready to open, or that have just closed)"),

'squash_SoD_f__flowerwidth'=> array( 'l_EN' => "Flower width",
									 'q_EN' => "How wide are the flowers, on average (cm)? (measure the outermost diameter of five fully-opened flowers)"),

'squash_SoD_s__anthercolour'=>array( 'l_EN' => "Anther colour",
									 'q_EN' => "What colour are the anthers (pollen-bearing structures inside the male flower)?  (observe flowers that are just opening or ready to open)"),


//Fruit
'squash_GRIN_b__UNIFORMITY'=> array( 'l_EN' => "Uniform plants",
									 'q_EN' => "Do the plants and fruit appear to be uniform?"),

'squash_GRIN_m__FRUITCOLOR'=> array( 'l_EN' => "Harvest main colour",
									 'q_EN' => "What is the squash's main exterior colour at harvest? (if more than one colour, indicate the main colour)",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"gray",
											2=>"white",
											3=>"orange",
											4=>"tan",
											5=>"green") ),

'squash_GRIN_m__FRUITSPOT' => array( 'l_EN' => "Spots on fruit",
									 'q_EN' => "Does the squash have spots of colour on it?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"not spotted or slightly spotted",
											9=>"many spots") ),

'squash_GRIN_m__FLESHCOLOR'=> array( 'l_EN' => "Inside flesh colour",
									 'q_EN' => "What colour is the flesh inside the squash?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"yellow",
											2=>"yellow-orange",
											3=>"pale orange",
											4=>"medium orange",
											5=>"dark orange",
											6=>"light green") ),

'squash_GRIN_f__FLESHDEPTH'=> array( 'l_EN' => "Flesh depth",
									 'q_EN' => "How thick is the flesh (cm)?  (measure the average thickness from the skin to the seed cavity)"),

'squash_GRIN_f__FRUITLEN'  => array( 'l_EN' => "Fruit length",
									 'q_EN' => "How long is the squash, on average (cm)?"),

'squash_GRIN_f__FRUITDIAM' => array( 'l_EN' => "Fruit diameter",
									 'q_EN' => "How wide is the squash, on average (cm)?  (measure the diameter at the widest point of the fruit)"),

'squash_GRIN_m__FRUITRIB'  => array( 'l_EN' => "Fruit cross-section",
									 'q_EN' => "Is the fruit ribbed or round in cross-section?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"not ribbed or very slightly ribbed",
											5=>"somewhat ribbed",
											9=>"pronounced ribs") ),

'squash_GRIN_m__FRUITSET'  => array( 'l_EN' => "Plant set amount",
									 'q_EN' => "Did this plant set many fruit, compared with other typical varieties?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"poor",
											5=>"normal",
											9=>"excellent") ),

//Seeds
'squash_SoD_s__seedcolour' => array( 'l_EN' => "Seed colour",
									 'q_EN' => "What colour are the seeds?"),

'squash_SoD_m__seednumber' => array( 'l_EN' => "Seed amount",
									 'q_EN' => "Did this squash have many seeds, compared with other typical varieties?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"very few",
											3=>"less than normal",
											5=>"normal",
											7=>"more than normal",
											9=>"extremely seedy") ),

'squash_SoD_f__seedlength' => array( 'l_EN' => "Seed length",
									 'q_EN' => "How long are the seeds, on average (mm)?"),

);
}
?>