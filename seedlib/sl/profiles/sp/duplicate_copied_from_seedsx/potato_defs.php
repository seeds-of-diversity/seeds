<?php
class SLDescDefsPotato
{

static public $raDefsPotato = array(
//Mid-Season
'potato_SoD_m__foliagematurity'=> array( 'l_EN' => "Leaf maturity",
									 	 'q_EN' => "Does this variety sprout and grow mature leaves early or late, compared to other typical varieties?",
									 	 'm' => array(
									 			0=>"don't know",
									 			9=>"very early",
												7=>"early",
												5=>"intermediate",
												3=>"late",
												1=>"very late") ),

'potato_SoD_m__planthabit'     => array( 'l_EN' => "Plant habit",
									 	 'q_EN' => "Do the plants spread on the ground or stand erect?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"extremely erect",
												3=>"erect",
												5=>"normal",
												7=>"spreading") ),

'potato_SoD_m__flowerfrequency'=> array( 'l_EN' => "Has flowers",
										 'q_EN' => "Does this variety produce flowers in your location?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"extremely rarely",
												3=>"rarely",
												5=>"occasionally",
												7=>"frequently",
												9=>"always / nearly always") ),

'potato_SoD_s__flowercolour'   => array( 'l_EN' => "Flower colour",
									 	 'q_EN' => "What colour are the flowers (if any)?"),

'potato_SoD_m__berrynumber'    => array( 'l_EN' => "Berries Produced",
									 	 'q_EN' => "If the plants flower, do they also produce berries?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"extremely rarely",
												3=>"rarely",
												5=>"occasionally",
												7=>"frequently",
												9=>"always / nearly always") ),

'potato_GRIN_m__VIGOR'         => array( 'l_EN' => "Vigorous vines",
									 	 'q_EN' => "How vigorous are the vines, compared with other typical varieties?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"very weak",
												2=>"weaker than normal",
												3=>"normal",
												4=>"stronger than normal",
												5=>"very strong") ),

'potato_SoD_s__leafcolour'     => array( 'l_EN' => "Leaf colour",
									 	 'q_EN' => "What colour are the leaves?"),

//Tubers
'potato_SoD_m__tubergreening'  => array( 'l_EN' => "Partialy green while in the ground",
									 	 'q_EN' => "Do the potatoes tend to turn partially green when they're still in the ground?",
									 	 'm' => array(
									 			0=>"don't know",
									 			9=>"very rarely / never",
												7=>"rarely",
												5=>"medium",
												3=>"often",
												1=>"very often") ),

'potato_SoD_s__tubershape'     => array( 'l_EN' => "Potato shape",
									 	 'q_EN' => "What shape are the potatoes?"),

'potato_SoD_s__tuberskincolour'=> array( 'l_EN' => "Skin colour",
									 	 'q_EN' => "What colour is the skin?"),

'potato_SoD_m__skintexture'    => array( 'l_EN' => "Skin texture",
									 	 'q_EN' => "What is the texture of the skin?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"very rough",
												3=>"rough",
												5=>"intermediate",
												7=>"smooth",
												9=>"very smooth") ),

'potato_SoD_s__tuberfleshcolour'=>array( 'l_EN' => "Flesh colour",
									 	 'q_EN' => "What colour is the flesh?"),

'potato_SoD_s__tubereyecolour' => array( 'l_EN' => "Eyes Colour",
									 	 'q_EN' => "What colour are the eyes when they begin to sprout?"),

'potato_SoD_m__eyedepth'       => array( 'l_EN' => "Depression depth",
									 	 'q_EN' => "The eyes sit in small depressions on the potato's surface.  How deep are these depressions?",
						 			 	 'm' => array(
									 			0=>"don't know",
									 			1=>"very deep",
												3=>"deep",
												5=>"medium",
												7=>"shallow",
												9=>"very shallow") ),

'potato_SoD_m__tubernumber'    => array( 'l_EN' => "Number of Potatoes",
									 	 'q_EN' => "How many potatoes are produced by each plant, on average, compared with other typical varieties?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"very few",
												3=>"few",
												5=>"medium",
												7=>"many",
												9=>"very many") ),

'potato_SoD_m__tubersize'      => array( 'l_EN' => "Potato size",
									 	 'q_EN' => "How large are the potatoes, compared with typical commercial sizes?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"very small",
												3=>"small",
												5=>"medium",
												7=>"large",
												9=>"very large") ),

'potato_SoD_m__hollowheart'    => array( 'l_EN' => "Hollow potatoes",
									 	 'q_EN' => "When you cut the potatoes open, are they ever hollow?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"very often",
												3=>"often",
												5=>"medium",
												7=>"rarely",
												9=>"very rarely / never") ),

'potato_SoD_m__resistdamageexternal'=> array( 'l_EN' => "External damage",
									 	 'q_EN' => "Do you find that this variety withstands external damage, such as cuts and scuffs?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"any damage causes it to spoil very easily",
												3=>"does not withstand much damage",
												5=>"normal",
												7=>"fairly resistant to damage",
												9=>"very tough, resists most damage") ),

'potato_SoD_m__resistdamageinternal'=> array( 'l_EN' => "Internal damage",
									 	 'q_EN' => "Do you find that this variety tends to bruise easily when the potatoes are handled roughly?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"bruises very easily",
												3=>"bruises easily",
												5=>"normal",
												7=>"bruises rarely",
												9=>"very tough, nearly never bruises") ),

'potato_SoD_m__storageability' => array( 'l_EN' => "Storageability",
									 	 'q_EN' => "Does this variety store well?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"very poor",
												3=>"poor",
												5=>"normal",
												7=>"good",
												9=>"very good") ),

'potato_SoD_m__drought'        => array( 'l_EN' => "Drought tolerance",
									 	 'q_EN' => "Does this variety tolerate drought?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"very poor",
												3=>"poor",
												5=>"normal",
												7=>"good",
												9=>"very good") ),

'potato_SoD_m__frost'          => array( 'l_EN' => "Frost tolerance",
									 	 'q_EN' => "Does this variety tolerate frost? (answer if you've experience spring frost after planting)",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"very poor",
												3=>"poor",
												5=>"normal",
												7=>"good",
												9=>"very good") ),


);
}
?>