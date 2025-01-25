<?php
class SLDescDefsTomato
{

static public $raDefsTomato = array(
//Mid-Season

'tomato_SoD_s__vigour'         => ['l_EN' => "Plant vigour",
                                   'q_EN' => "Any comments on how vigorously it grew and suckered?"],

'tomato_SoD_m__planthabit'     => array( 'l_EN' => "Plant growth habit",
									 	 'q_EN' => "What type of plant is this tomato?
                                                    <ul><li>dwarf: very short</li>
                                                        <li>determinate : about 2-3 feet tall, produces one main crop of fruit then mostly stops growing,
                                                            little if any side growth, usually don't need much support</li>
                                                        <li>semi-determinate : about 3-5 feet tall, some slow side growth, grow well on short stakes</li>
                                                        <li>indeterminate : continuously grows long vines with new flower clusters until frost,
                                                                            widely-spaced branches and lots of side shoots, needs staking</li>
                                                    </ul>",
									 	 'm' => array(
									 			0=>"don't know",
									 			1=>"dwarf (very short)",
												2=>"determinate",
												3=>"semi-determinate",
												4=>"indeterminate") ),

'tomato_SoD_m__stempubescence' => array( 'l_EN' => "Stem hair",
									 	 'q_EN' => "How hairy are the stems of this variety?",
									 	 'm' => array(
									 			0=>"don't know",
									 			3=>"sparse hairs",
												5=>"intermediate",
												7=>"dense hairs") ),

'tomato_SoD_m__foliagedensity' => array( 'l_EN' => "Foliage density",
									 	 'q_EN' => "How dense is the foliage, compared with other typical varieties?",
									  	 'm' => array(
									 			0=>"don't know",
									 			3=>"sparse leaves",
												5=>"normal",
												7=>"dense leaves") ),

'tomato_SoD_m__leafattitude'   => array( 'l_EN' => "Leaf angle",
									 	 'q_EN' => "Do the leaves tend to droop downward or point upright?",
									  	 'm' => array(
									 			0=>"don't know",
									 			7=>"drooping downward",
												5=>"horizontal",
												3=>"standing above horizontal") ),

'tomato_SoD_m__leaftype'       => array( 'l_EN' => "Leaf type",
									 	 'q_EN' => "What type of leaves does this variety have?",
									  	 'm' => array(
									 			0=>"don't know",
									 			101=>"standard", // [this includes 3=standard, 4=peruvianum, 6=hirsutum]"
												1=>"dwarf",
												2=>"potato leaf type",
												5=>"currant type (pimpinellifolium)",
												7=>"other"),
										 'img'=>array(
										 		1=>"tomato/tomato LEAFTYPE_1.gif",
  												2=>"tomato/tomato LEAFTYPE_2.gif",
  												//4=>"tomato/tomato LEAFTYPE_4.gif",
  												5=>"tomato/tomato LEAFTYPE_5.gif",
  												//6=>"tomato/tomato LEAFTYPE_6.gif",
												101=>"tomato/tomato LEAFTYPE_101.gif"),
										 'imgParms'=>array( "imgH" => 100 ) ),

'tomato_SoD_m__flowercolour'   => array( 'l_EN' => "Flower colour",
									 	 'q_EN' => "What colour are the flowers?",
									  	 'm' => array(
									 			0=>"don't know",
									 			1=>"white",
												2=>"yellow",
												3=>"orange",
												4=>"other") ),

'tomato_SoD_m__fruitcolourunripe'=>array('l_EN' => "Fruit colour - unripe",
									 	 'q_EN' => "What colour are the fruit before they ripen?",
									  	 'm' => array(
									 			0=>"don't know",
									 			1=>"greenish white",
												2=>"light green",
												5=>"green",
												7=>"dark green",
												9=>"very dark green") ),

//Late-Season
'tomato_SoD_f__vinelength'     => array( 'l_EN' => "Plant height (cm)",
									 	 'q_EN' => "How tall/long are the plants, on average, at the end of their season (cm)?  (measure from the ground to the tip of the longest vine)"),

'tomato_SoD_f__internodelength'=> array( 'l_EN' => "Leaf node distance (cm)",
									 	 'q_EN' => "How far apart are the leaf nodes on the main stem, on average (cm)?"),

//Fruit
'tomato_SoD_m__fruitshape'     => array( 'l_EN' => "Fruit shape - side",
									 	 'q_EN' => "What shape is the fruit, viewed from the side?",
									  	 'm' => array(
									 			0=>"don't know",
									 			1=>"flattened",
												2=>"slightly flattened",
												3=>"round",
												4=>"high rounded",
												5=>"heart-shaped",
												6=>"cylindrical",
												7=>"pear-shaped",
												8=>"plum-shaped (oval)",
												9=>"other"),
										 'img'=>array(
										 		1=>"tomato/tomato FRUITSHAPE_1.gif",
												2=>"tomato/tomato FRUITSHAPE_2.gif",
												3=>"tomato/tomato FRUITSHAPE_3.gif",
												4=>"tomato/tomato FRUITSHAPE_4.gif",
												5=>"tomato/tomato FRUITSHAPE_5.gif",
												6=>"tomato/tomato FRUITSHAPE_6.gif",
												7=>"tomato/tomato FRUITSHAPE_7.gif",
												8=>"tomato/tomato FRUITSHAPE_8.gif"),
										 'imgParms' => array( "imgH" => 60 ) ),

'tomato_SoD_m__fruitshapecrosssection'=> array( 'l_EN' => "Fruit shape - cross",
									 	 'q_EN' => "What shape is the fruit, sliced in cross-section?",
									  	 'm' => array(
									 			0=>"don't know",
									 			1=>"round",
												2=>"angular",
												3=>"irregular"),
										 'img'=>array(
										 		1=>"tomato/tomato FRUITCROSSSECTION_1.gif",
												2=>"tomato/tomato FRUITCROSSSECTION_2.gif",
												3=>"tomato/tomato FRUITCROSSSECTION_3.gif"),
										 'imgParms' => array( "imgH" => 60 ) ),

'tomato_SoD_m__fruitsize'      => array( 'l_EN' => "Fruit diameter",
									 	 'q_EN' => "How large is the fruit (diameter)?",
									  	 'm' => array(
									 			0=>"don't know",
									 			1=>"very small (less than 3cm)",
												2=>"small (3-5cm)",
												3=>"intermediate (5.1-8cm)",
												4=>"large (8.1-10cm)",
												5=>"very large (10cm+)") ),

'tomato_SoD_m__fruitdetachment'=> array( 'l_EN' => "Easy to pick",
									 	 'q_EN' => "How easy is it to pick the fruit?",
									  	 'm' => array(
									 			0=>"don't know",
									 			3=>"fruit detaches easily, tends to fall",
												5=>"fruit detaches with a gentle pull or twist, tends to hang until picked",
												7=>"fruit is difficult to detach when pulled, better to cut the stems") ),

'tomato_SoD_m__fruitcolourexterior'=> array( 'l_EN' => "Fruit colour - ripe exterior",
									 	 'q_EN' => "What is the exterior colour of the fruit?",
									  	 'm' => array(
									 			0=>"don't know",
									 			1=>"green",
												2=>"yellow",
												3=>"orange",
												4=>"pink",
												5=>"red",
												6=>"other") ),

'tomato_SoD_m__fruitcolourinterior'=> array( 'l_EN' => "Fruit colour - ripe interior",
									 	 'q_EN' => "What colour is the interior flesh?",
									  	 'm' => array(
									 			0=>"don't know",
									 			1=>"green",
												2=>"yellow",
												3=>"orange",
												4=>"pink",
												5=>"red",
												6=>"other") ),

'tomato_GRIN_m__GELCOLOR'      => array( 'l_EN' => "Gel colour",
									 	 'q_EN' => "What colour is the gel that surrounds the seeds?",
									  	 'm' => array(
									 			0=>"don't know",
									 			1=>"yellow",
												2=>"green",
												3=>"red") ),

'tomato_SoD_m__fruitfirmness'  => array( 'l_EN' => "Fruit firmness",
									 	 'q_EN' => "How firm is the interior flesh?",
									  	 'm' => array(
									 			0=>"don't know",
									 			3=>"soft",
												5=>"intermediate",
												7=>"firm") ),

'tomato_SoD_m__fruitpubescence'=> array( 'l_EN' => "Fruit hair",
									 	 'q_EN' => "Does the skin have any noticeable hair or fuzziness (like a peach)?",
									  	 'm' => array(
									 			0=>"don't know",
									 			1=>"none",
												3=>"sparse hairs",
												5=>"somewhat fuzzy",
												7=>"densely fuzzy") ),

'tomato_SoD_m__fruitsizeuniformity'  => ['l_EN' => "Fruit size uniformity",
                                         'q_EN' => "How uniform is fruit size within a plant?",
                                         'm' => [ 0=>"don't know",
                                                  1=>"Very uniform",
                                                  3=>"Slightly different sizes",
                                                  7=>"Various fruit sizes"]],

'tomato_SoD_m__fruitcategory'        => ['l_EN' => "Fruit category",
                                         'q_EN' => "Which category best describes the variety?",
                                         'm' => [ 0=>"don't know",
                                                  1=>"slicer",
                                                  2=>"paste/canning",
                                                  3=>"saladette",
                                                  4=>"cherry",
                                                  5=>"grape",
                                                  6=>"cluster"]],

);
}
