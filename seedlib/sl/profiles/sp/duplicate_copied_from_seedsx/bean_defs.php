<?php
class SLDescDefsBean
{

static public $raDefsBean = array(
'bean_NOR_m__PLAN_ANTHO'   => array( 'l_EN' => "Seedling stem colour",
                                     'q_EN' => "What colour is the seedling stem below the seed leaves (cotyledons)?",
                                     'm' => array(
                                            0=>"don't know",
                                            1=>"green",
                                            9=>"purple-green" ) ),

'bean_NOR_m__PLAN_GROWT'   => array( 'l_EN' => "Bush/Climbing",
                                     'q_EN' => "Is this a bush bean or climbing bean?",
                                     'm' => array(
                                            0=>"don't know",
                                            1=>"bush",
                                            9=>"climbing" ) ),

'bean_NOR_m__PLANT_TYPE'   => array( 'l_EN' => "Growth habit",
                                     'q_EN' => "What type of growth habit does the plant have?",
                                     'm' => array(
                                            0=>"don't know",
                                            1=>"short upright branches, no support needed",
                                            2=>"plant produces vines, spreading is limited, no support needed",
                                            3=>"vines spread a lot, support is helpful",
                                            4=>"climbing vines spread extensively" ) ),

'bean_NOR_m__PLAN_CLIMB'   => array( 'l_EN' => "Vining time",
                                     'q_EN' => "(Climbing beans only)  When do the vines start to climb?",
                                     'm' => array(
                                            0=>"don't know / not a climber",
                                            2=>"early",
                                            5=>"average",
                                            7=>"late" ) ),

// LEAVES

"bean_NOR_m__LEAF_COLOR"   => array( 'l_EN' => "Leaf colour",
                                     'q_EN' => "What colour are the leaves?",
                                     'm' => array(
                                   0=>"don't know",
                                   1=>"very light green",
                                   2=>"light green",
                                   5=>"medium green",
                                   7=>"dark green",
                                   9=>"very dark green",
                                    ) ),
"bean_NOR_m__LEAF_SIZE"    =>  array( 'l_EN' => "Leaf size",
                                      'q_EN' => "How big are the leaves compared to other varieties?",
                                      'm' => array(
                                   0=>"don't know",
                                   3=>"small",
                                   5=>"medium",
                                   7=>"large" ) ),
"bean_NOR_m__LEAF_RUGOS"   => array( 'l_EN' => "Leaf texture",
                                     'q_EN' => "How wrinkled or bumpy are the leaves?",
                                     'm' => array(
                                   0=>"don't know",
                                   3=>"mostly smooth, slightly wrinkled",
                                   5=>"moderately wrinkled",
                                   7=>"strongly wrinkled" ) ),
// FLOWERS

"bean_NOR_m__PLAN_FLOWE" => array( 'l_EN' => "Flowering time",
                                   'q_EN' => "When do the flowers appear?",
                                   'm' => array(
                                   0=>"don't know",
                                   1=>"very early",
                                   3=>"early",
                                   5=>"medium",
                                   7=>"late",
                                   9=>"very late" ) ),

"bean_NOR_m__FLOW_LOCAT" => array( 'l_EN' => "Flower location",
                                   'q_EN' => "(Bush beans only)  Where do the flower clusters grow on the plant?",
                                   'm' => array(
                                   0=>"don't know / not a bush bean",
                                   1=>"within the foliage",
                                   2=>"partly in the foliage",
                                   3=>"over the foliage" ) ),
"bean_NOR_m__FLOW_BRACT" => array( 'l_EN' => "Bract size",
                                   'q_EN' => "The leaf attached to a flower just below the petals is called a bract. How big are the bracts?",
                                   'm' => array(
                                   0=>"don't know",
                                   3=>"small",
                                   5=>"medium",
                                   7=>"large" ) ),
"bean_NOR_m__FLOW_STAND" => array( 'l_EN' => "Flower colour - standards",
                                   'q_EN' => "What colour are the standards?",
                                   'm' => array(
                                   0=>"don't know",
                                   1=>"white",
                                   2=>"pink",
                                   3=>"violet" ) ),
"bean_NOR_m__FLOW_WINGS" => array( 'l_EN' => "Flower colour - wings",
                                   'q_EN' => "What colour are the wings?",
                                   'm' => array(
                                   0=>"don't know",
                                   1=>"white",
                                   2=>"pink",
                                   3=>"violet" ) ),

// 2-3 weeks after flowering

"bean_NOR_f__PLANT_CM"     => array( 'l_EN' => "Plant height (cm)",
                                     'q_EN' => "(Bush beans only)  How tall are the plants, on average (cm)?" ),
"bean_NOR_m__TERMI_SHAPE"  => array( 'l_EN' => "Tip leaflet shape",
                                     'q_EN' => "(Bush beans only)  What shape is the leaflet at the tip of each branch?",
                                     'm' => array(
                                   0=>"don't know",
                                   1=>"triangular",
                                   2=>"diamond-shaped",
                                   3=>"rounded" ) ),
"bean_NOR_m__TERMI_SIZE"   => array( 'l_EN' => "Tip leaflet size",
                                     'q_EN' => "(Bush beans only)  How large is the leaflet at the tip of each branch, compared with typical varieties?",
                                     'm' => array(
                                   0=>"don't know",
                                   3=>"small",
                                   5=>"medium",
                                   7=>"large" ) ),
"bean_NOR_m__TERMI_APEX"   => array( 'l_EN' => "Tip leaflet taper",
                                     'q_EN' => "(Bush beans only)  Does the tip leaflet taper to a long or short point?",
                                     'm' => array(
                                   0=>"don't know",
                                   3=>"short",
                                   5=>"medium",
                                   7=>"long" ) ),

"bean_NOR_m__GRAI_COLOR"   => array( 'l_EN' => "Unripe seed colour",
                                     'q_EN' => "What colour are the unripe seeds inside the pods?",
                                     'm' => array(
                                            0=>"don't know",
                                            1=>"white",
                                            2=>"light green" ) ),

// PODS

"bean_SoD_f__podlength"    => array( 'l_EN' => "Pod length (cm)",
                                     'q_EN' => "How long are the pods, on average, at their best stage for fresh eating (cm)?" ),
"bean_NOR_m__POD_SECTIO"   => array( 'l_EN' => "Pod cross shape",
                                     'q_EN' => "What shape does the pod have when you cut it in cross-section?",
                                     'm' => array(
                                            0=>"don't know",
                                            1=>"narrow oval",
                                            2=>"oval",
                                            3=>"broad oval",
                                            4=>"heart shaped",
                                            5=>"circular",
                                            6=>"figure eight" ),
                                     'img' => array(
                                   1=>"bean/bean POD_SECTIO_1.gif",
                                   2=>"bean/bean POD_SECTIO_2.gif",
                                   3=>"bean/bean POD_SECTIO_3.gif",
                                   4=>"bean/bean POD_SECTIO_4.gif",
                                   5=>"bean/bean POD_SECTIO_5.gif",
                                   6=>"bean/bean POD_SECTIO_6.gif" ),

                                     'imgParms' => array( "imgH" => 60 ) ),
"bean_NOR_m__POD_GROUND"   => array( 'l_EN' => "Pod main colour",
                                     'q_EN' => "What is the main or background colour of the pods?",
                                     'm' => array(
                                            0=>"don't know",
                                            1=>"yellow",
                                            2=>"green",
                                            3=>"violet" ) ),
"bean_NOR_m__POD_INTENS"   => array( 'l_EN' => "Pod main colour intensity",
                                     'q_EN' => "How intense is the main or background colour?",
                                     'm' => array(
                                            0=>"don't know",
                                            3=>"light",
                                            5=>"medium",
                                            7=>"dark" ) ),
"bean_NOR_m__POD_PIGMEN"   => array( 'l_EN' => "Pod markings",
                                     'q_EN' => "Do the pods have additional markings, such as stripes or spots?",
                                     'm' => array(
                                            0=>"don't know",
                                            1=>"no",
                                            9=>"yes" ) ),
"bean_NOR_m__POD_PIGCOL"   => array( 'l_EN' => "Pod markings colour",
                                     'q_EN' => "If so, what colour are the markings? (it may be easier to decide after the pods have dried)",
                                     'm' => array(
                                            0=>"don't know",
                                            1=>"red",
                                            2=>"violet" ) ),
"bean_NOR_m__POD_STRING"   => array( 'l_EN' => "Pod string",
                                     'q_EN' => "Does the pod have a string (a fiber that peels along the seam when you break the pod in half)?",
                                     'm' => array(
                                            0=>"don't know",
                                            1=>"no",
                                            9=>"yes" ) ),
"bean_NOR_m__POD_CURVAT"   => array( 'l_EN' => "Pod curvature",
                                     'q_EN' => "Is the pod straight or curved?",
                                     'm' => array(
                                            0=>"don't know",
                                            1=>"straight or nearly straight",
                                            3=>"slightly curved",
                                            5=>"medium curve",
                                            7=>"strong curve",
                                            9=>"very strong curve" ) ),
"bean_NOR_m__POD_SHACUR"   => array( 'l_EN' => "Pod curve shape",
                                     'q_EN' => "If the pod is curved, what shape is the curve?",
                                     'm' => array(
                                            0=>"don't know / not curved",
                                            1=>"forwards",
                                            2=>"S shaped",
                                            3=>"backwards" ),
                                     'img' => array(
                                            0=>"bean/bean POD_SHACUR_0.gif",
                                            1=>"bean/bean POD_SHACUR_1.gif",
                                            2=>"bean/bean POD_SHACUR_2.gif",
                                            3=>"bean/bean POD_SHACUR_3.gif" ),
                                     'imgParms' => array( "imgH" => 100 ) ),
"bean_NOR_m__POD_SHATIP"   => array( 'l_EN' => "Pod tip shape",
                                     'q_EN' => "What shape is the tip of the pod?",
                                     'm' => array(
                                            0=>"don't know",
                                            1=>"pointed",
                                            2=>"blunt" ) ),
"bean_NOR_m__POD_LEBEAK"   => array( 'l_EN' => "Pod tip length",
                                     'q_EN' => "If pointed, how long is the tip of the pod?",
                                     'm' => array(
                                            0=>"don't know",
                                            3=>"short",
                                            5=>"medium",
                                            7=>"long" ) ),
"bean_NOR_m__POD_CURBEA"   => array( 'l_EN' => "Pod tip curvature",
                                     'q_EN' => "If pointed, is the tip of the pod straight or curved?",
                                     'm' => array(
                                            0=>"don't know",
                                            1=>"straight or nearly straight",
                                            3=>"slight curve",
                                            5=>"medium curve",
                                            7=>"strong curve",
                                            9=>"very strong curve" ),
                                     'img' => array(
                                            1=>"bean/bean POD_CURBEA_1.gif",
                                            3=>"bean/bean POD_CURBEA_3.gif",
                                            5=>"bean/bean POD_CURBEA_5.gif",
                                            7=>"bean/bean POD_CURBEA_7.gif",
                                            9=>"bean/bean POD_CURBEA_9.gif" ),
                                     'imgParms' => array( "imgH" => 60 ) ),
"bean_NOR_m__POD_PROMIN"   => array( 'l_EN' => "Pods have bulges",
                                     'q_EN' => "At the fresh eating stage, do the seeds create noticeable bulges in the pod?",
                                     'm' => array(
                                            0=>"don't know",
                                            3=>"not noticeable or very slight",
                                            5=>"medium",
                                            7=>"pronounced bulges" ) ),
"bean_NOR_m__POD_TEXTUR"   => array( 'l_EN' => "Pod texture",
                                     'q_EN' => "What is the texture of the pod surface?",
                                     'm' => array(
                                            0=>"don't know",
                                            3=>"smooth",
                                            5=>"medium",
                                            7=>"rough" ) ),

// DRY PODS

"bean_NOR_m__POD_PARDRY"   => array( 'l_EN' => "Papery layer inside dry pod",
                                     'q_EN' => "Do the dry pods have a thin papery layer inside, separating each seed to one side or the other?",
                                     'm' => array(
                                            0=>"don't know",
                                            1=>"no",
                                            3=>"small layer",
                                            5=>"medium",
                                            7=>"strong thick layer" ) ),
"bean_NOR_m__POD_CONSTR"   => array( 'l_EN' => "Dry pods tightened",
                                     'q_EN' => "Do the dry pods seem to have tightened around the seeds?",
                                     'm' => array(
                                            0=>"don't know",
                                            1=>"no",
                                            3=>"slightly",
                                            5=>"medium",
                                            7=>"tightly" ) ),

//chris
"bean_NOR_m__GRAIN_SIZE"   => array( 'l_EN' => "Size Compared to Typical Varieties", //edit this
									 'q_EN' => "How big are the seeds, compared with typical varieties?",
									 'm' => array(
										  	0=>"don't know",
										  	1=>"very small",
										  	3=>"small",
											5=>"medium",
											7=>"large",
											9=>"very large") ),

"bean_NOR_m__SEED_SHAPE"   => array( 'l_EN' => "Seed Shape", //edit this
									 'q_EN' => "What shape are the seeds?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"narrow oval",
											2=>"oval",
											3=>"broad oval",
											4=>"narrow egg-shaped",
											5=>"egg-shaped",
											6=>"broad egg-shaped",
											7=>"circular",
											8=>"narrow kidney shaped",
											9=>"kidney shaped",
											10=>"broad kidney shaped"),
									 'img'=>array(
									 		1=>"bean/bean SEEDSHAPE_1.gif",
											2=>"bean/bean SEEDSHAPE_2.gif",
											3=>"bean/bean SEEDSHAPE_3.gif",
											4=>"bean/bean SEEDSHAPE_4.gif",
											5=>"bean/bean SEEDSHAPE_5.gif",
											6=>"bean/bean SEEDSHAPE_6.gif",
											7=>"bean/bean SEEDSHAPE_7.gif",
											8=>"bean/bean SEEDSHAPE_8.gif",
											9=>"bean/bean SEEDSHAPE_9.gif",
											10=>"bean/bean SEEDSHAPE_10.gif"),
									 'imgParms' => array("imgH" => 60 ) ),

"bean_SoD_m__SEED_SHAPE2"  => array( 'l_EN' => "Seed Cross-section Shape", //edit this
									 'q_EN' => "What shape are the seeds in cross-section?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"flat",
											2=>"oval",
											3=>"circular") ),

"bean_SoD_m__seedcolours"  => array( 'l_EN' => "Distinct Colours", //edit this
									 'q_EN' => "How many distinct colours are on these seeds?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"single colour",
											2=>"two colours",
											3=>"more than two colours") ),

"bean_NOR_m__GRAIN_MAIN"   => array( 'l_EN' => "Main Background Colour", //edit this
									 'q_EN' => "What is the main background colour of the dry seeds?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"white or greenish",
											2=>"grey",
											3=>"yellow",
											4=>"buff coloured",
											5=>"brown",
											6=>"red",
											7=>"violet",
											8=>"black") ),

"bean_NOR_m__GRAI_MASEC"   => array( 'l_EN' => "Second most dominant colour", //edit this
									 'q_EN' => "If the seeds have two or more colours, what is the second most dominant seed colour?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"white or greenish",
											2=>"grey",
											3=>"yellow",
											4=>"buff coloured",
											5=>"brown",
											6=>"red",
											7=>"violet",
											8=>"black") ),

"bean_NOR_m__GRAI_DISTR"   => array( 'l_EN' => "Where the secondary colour is", //edit this
									 'q_EN' => "If the seeds have two or more colours, where do you see the secondary colour most?",
									 'm' => array(
									 		0=>"don't know",
								 			1=>"around hilum (the oval scar where the seed was attached to the pod)",
											2=>"streaked",
											3=>"on half of the seed",
											4=>"variegated") ),

"bean_NOR_m__SEED_VEINS"   => array( 'l_EN' => "Veins in Seed Coat", //edit this
									 'q_EN' => "Do the seeds have tiny veins visible in the seed coats?",
									 'm' => array(
									 		0=>"don't know",
									 		3=>"weakly visible",
											5=>"medium",
											7=>"strongly visible") ),

"bean_NOR_m__SEED_HILAR"   => array( 'l_EN' => "Hilum Colour", //edit this
									 'q_EN' => "What colour is the seed's hilum (the oval scar where the seed was attached to the pod)?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"same as seed colour",
											2=>"different from seed colour") ),

);
}
?>