<?php
class SLDescDefsPepper
{

static public $raDefsPepper = array(

//General
'pepper_GRIN_m__COMMCAT'       => array( 'l_EN' => "Pepper Variety",
									 	 'q_EN' => "What kind of pepper would you call this variety?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"bell",
												2=>"pimento",
												3=>"paprika",
												4=>"tabasco",
												5=>"chili",
												6=>"cayenne",
												7=>"ornamental",
												9=>"chiltepin",
												8=>"mixed or nondescript") ),
'pepper_GRIN_m__PUNGENCY2'     => array( 'l_EN' => "Spice",
									 	 'q_EN' => "Is this a sweet or hot pepper?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"sweet",
									 			5=>"moderately hot",
												9=>"very hot") ),

//Early-Season
'pepper_SoD_m__stemcolour'     => array( 'l_EN' => "Stem colour - young",
									 	 'q_EN' => "What colour is the stem of the young plant?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"green",
												2=>"green with purple stripes",
												3=>"purple",
												4=>"other") ),
'pepper_SoD_m__stemshape'      => array( 'l_EN' => "stem shape",
									 	 'q_EN' => "What shape is the stem?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"cylindrical",
												2=>"angled",
												3=>"flattened") ),
'pepper_SoD_m__pubescence'     => array( 'l_EN' => "Stem hairs",
									 	 'q_EN' => "Does the stem have small hairs?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"no hair",
												3=>"sparse hairs",
												5=>"intermediate",
												7=>"dense hairs") ),
'pepper_SoD_m__branchhabit'    => array( 'l_EN' => "Branch habits",
									 	 'q_EN' => "Does the plant tend to branch out, compared with other typical varieties?",
									 	 'm' => array(
									 			0=>"don't know",
									 			3=>"sparse branching",
												5=>"normal branching",
												7=>"dense branching") ),

//Leaves
'pepper_SoD_m__leafcolour'     => array( 'l_EN' => "Leaf colour",
									 	 'q_EN' => "What colour are the leaves?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"yellow",
												2=>"light green",
												3=>"green",
												4=>"dark green",
												5=>"light purple",
												6=>"purple",
												7=>"variegated (white patches or margins)",
												8=>"other") ),
'pepper_SoD_m__leafshape'      => array( 'l_EN' => "Leaf shape",
									 	 'q_EN' => "What shape are the leaves?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"deltoid",
												2=>"ovate",
												3=>"lanceolate"),
										 'img'=>array(
										 		1=>"pepper/pepper LEAFSHAPE_1.gif",
												2=>"pepper/pepper LEAFSHAPE_2.gif",
												3=>"pepper/pepper LEAFSHAPE_3.gif"),
										 'imgParms'=> array( "imgH" => 100 ) ),
'pepper_GRIN_m__LEAFTEXT'      => array( 'l_EN' => "Leaf texture",
									 	 'q_EN' => "Are the leaves smooth or wrinkled?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"smooth",
												9=>"wrinkled/curled") ),
'pepper_SoD_f__leaflength'     => array( 'l_EN' => "Leaf length (cm)",
									 	 'q_EN' => "How long are the mature leaves, on average, measured from the stem to the leaf tip (cm)?"),
'pepper_SoD_f__leafwidth'      => array( 'l_EN' => "Leaf width (cm)",
									 	 'q_EN' => "How wide are the mature leaves, on average, measured at the widest point (cm)?"),
//Flowers
'pepper_SoD_m__flowerposition' => array( 'l_EN' => "Flower direction",
									 	 'q_EN' => "Do the flowers droop downward or point upward?",
									 	 'm' => array(
									 			0=>"don't know",
									 			3=>"downward",
												5=>"intermediate",
												7=>"upward") ),
'pepper_SoD_m__flowercolour'   => array( 'l_EN' => "Flower colour",
									 	 'q_EN' => "What colour are the flowers?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"white",
												2=>"light yellow",
												3=>"yellow",
												4=>"yellow-green",
												5=>"purple with white base",
												6=>"white with purple base",
												7=>"white with purple margin",
												8=>"purple",
												9=>"other") ),
'pepper_SoD_m__anthercolour'   => array( 'l_EN' => "Anther colour",
									 	 'q_EN' => "What colour are the anthers (the pollen-carrying tips at the center of the flower)?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"white",
												2=>"yellow",
												3=>"pale blue",
												4=>"blue",
												5=>"purple",
												6=>"other") ),

//Fruit
'pepper_SoD_m__fruitcolourunripe'=> array( 'l_EN' => "Fruit colour - unripe",
									 	 'q_EN' => "What colour are the fruit when they are beginning to ripen, but not ready to harvest?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"white",
												2=>"yellow",
												3=>"green",
												4=>"orange",
												5=>"purple",
												6=>"deep purple",
												7=>"other") ),
'pepper_SoD_m__fruitcolourripe'=> array( 'l_EN' => "Fruit colour - ripe",
									 	 'q_EN' => "What colour are the fruit when they are ready to harvest?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"white",
												2=>"lemon-yellow",
												3=>"pale orange-yellow",
												4=>"orange-yellow",
												5=>"pale orange",
												6=>"orange",
												7=>"light red",
												8=>"red",
												9=>"dark red",
												10=>"purple",
												11=>"brown",
												12=>"black",
												13=>"other") ),
'pepper_GRIN_m__FRUITPOS'      => array( 'l_EN' => "Fruit position",
									 	 'q_EN' => "Do the fruit droop downward or point upward?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"downward",
												5=>"intermediate",
												9=>"upward") ),
'pepper_SoD_m__fruitshape'     => array( 'l_EN' => "Fruit shape - ripe",
									 	 'q_EN' => "What shape are the ripe fruit?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"elongated",
												2=>"almost round",
												3=>"triangular",
												4=>"campanulate",
												5=>"blocky",
												6=>"other"),
										 'img'=>array(
												1=>"pepper/pepper FRUITSHAPE_1.gif",
												2=>"pepper/pepper FRUITSHAPE_2.gif",
												3=>"pepper/pepper FRUITSHAPE_3.gif",
												4=>"pepper/pepper FRUITSHAPE_4.gif",
												5=>"pepper/pepper FRUITSHAPE_5.gif"),
										 'imgParms' => array( "imgH" => 80 ) ),
'pepper_GRIN_f__FRUITLNGTH'    => array( 'l_EN' => "Fruit length (cm)",
									 	 'q_EN' => "How long are the ripe fruit, on average (cm)?"),

//Late-Season
'pepper_SoD_f__plantheight'    => array( 'l_EN' => "Plant height (cm)",
									 	 'q_EN' => "How tall are the mature plants, on average (cm)?"),
'pepper_GRIN_m__STEMNUM'       => array( 'l_EN' => "Stems",
									 	 'q_EN' => "How many stems do the mature plants have? (look at the very bottom of the plant, not branches)",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"single stem",
												9=>"multiple stems") ),
'pepper_SoD_m__planthabit'     => array( 'l_EN' => "Plant habit",
									 	 'q_EN' => "Do the plants tend to spread out or stand upright?",
									 	 'm' => array(
									 			0=>"don't know",
									 			3=>"spreads sideways",
												5=>"intermediate",
												7=>"erect",
												9=>"other") ),

//Seeds
'pepper_SoD_m__seedcolour'     => array( 'l_EN' => "Seed colour",
									 	 'q_EN' => "What colour are the seeds?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"straw",
												2=>"brown",
												3=>"black",
												4=>"other") ),
'pepper_SoD_m__seedsurface'    => array( 'l_EN' => "Seed texture",
									 	 'q_EN' => "What is the texture of the seeds' surface?",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"smooth",
												2=>"rough",
												3=>"wrinkled") ),

);
}
?>