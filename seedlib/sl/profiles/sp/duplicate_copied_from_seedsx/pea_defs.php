<?php
class SLDescDefsPea
{

static public $raDefsPea = array(
//Seedling
'pea_GRIN_m__COTYLCOLOR'   => array( 'l_EN' => "Seed leaf colour",
									 'q_EN' => "What colour are the seed leaves (cotyledons)?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"green",
											4=>"yellow",
											2=>"mixed",
											3=>"other") ),

//Flower
'pea_GRIN_m__FLOWERCOL'    => array( 'l_EN' => "Flower Colour",
									 'q_EN' => "Are the flowers white or do they have some colour?",
									 'm' => array(
									 		0=>"don't know",
									 		3=>"white",
											2=>"colour",
											1=>"mixed") ),

'pea_GRIN_i__FLOWPEDUNC'   => array( 'l_EN' => "Max flowers in cluster",
									 'q_EN' => "What is the maximum number of flowers in a cluster?"),

//Harvest
'pea_SoD_f__LEAFLENGTH'    => array( 'l_EN' => "Leaf length",
									 'q_EN' => "How long are the leaves, on average (cm)?"),

'pea_SoD_f__LEAFWIDTH'     => array( 'l_EN' => "Leaf width",
									 'q_EN' => "How wide are the leaves, on average (cm)?"),

'pea_GRIN_f__HEIGHT'       => array( 'l_EN' => "Mature plant height",
									 'q_EN' => "How tall are the plants at maturity, on average, from ground to tip (cm)?"),

'pea_GRIN_f__INTERNODE'    => array( 'l_EN' => "Leaf node separation",
									 'q_EN' => "How far apart are the leaf nodes, on average (cm)?"),

'pea_GRIN_i__NODES'        => array( 'l_EN' => "Number of node with pods",
									 'q_EN' => "How many nodes on the main stem have pods?"),

'pea_GRIN_f__STEMDIAM'     => array( 'l_EN' => "Stem diameter",
									 'q_EN' => "What is the average diameter of the stem (mm)?"),


//Pod
'pea_GRIN_m__PODTYPE'      => array( 'l_EN' => "Pod type",
									 'q_EN' => "What type of pods does this pea have?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"edible (flat or round)",
											3=>"non-edible",
											2=>"mixed") ),

'pea_GRIN_m__PODCOLOR'     => array( 'l_EN' => "Pod Colour",
									 'q_EN' => "What colour are the pods?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"green",
											2=>"mixed",
											3=>"other") ),

'pea_GRIN_m__PODSHAPE'     => array( 'l_EN' => "Pod Shape",
									 'q_EN' => "What shape are the pods? (observe when they are still flat)",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"curved",
											4=>"straight",
											2=>"mixed",
											3=>"other") ),

'pea_GRIN_m__PODAPEX'  	   => array( 'l_EN' => "Pod tips",
									 'q_EN' => "Are the tips of the pods pointed? (observe when they are still flat)",
									 'm' => array(
									 		0=>"don't know",
									 		4=>"pointed",
											1=>"blunt",
											2=>"mixed",
											3=>"other") ),

'pea_GRIN_f__PODLENGTH'    => array( 'l_EN' => "Pod length",
									 'q_EN' => "How long are the pods, on average (cm)?"),

'pea_GRIN_f__PODWIDTH'     => array( 'l_EN' => "Pod width",
									 'q_EN' => "How wide are the pods, on average (cm)?"),

//Seed
'pea_GRIN_m__HILUMCOLOR'   => array( 'l_EN' => "Hilum colour",
									 'q_EN' => "Does the seed's hilum (the scar where the seed was attached to the pod) have a dark colour?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"no",
											3=>"yes",
											2=>"mixed") ),

'pea_GRIN_m__SDCOATCOL'    => array( 'l_EN' => "Seed colour",
									 'q_EN' => "What colour are these seeds?",
									 'm' => array(
									 		0=>"don't know",
									 		2=>"just uniform green/white",
											3=>"coloured or with markings",
											1=>"mixed") ),

'pea_GRIN_m__SDPATTERN'    => array( 'l_EN' => "Seed pattern",
									 'q_EN' => "If the seeds are coloured, what pattern is the colour?",
									 'm' => array(
									 		0=>"don't know",
									 		3=>"solid",
											2=>"mottled",
											4=>"spots",
											1=>"mixed") ),

'pea_GRIN_m__SDPATCOLOR'   => array( 'l_EN' => "Seed marking colour",
									 'q_EN' => "If the seeds have colour or markings, what colour?",
									 'm' => array(
									 		0=>"don't know",
									 		5=>"green",
											1=>"black",
											2=>"brown",
											3=>"blue",
											4=>"grey",
											7=>"purple",
											6=>"mixed") ),

'pea_GRIN_m__SEEDSURF'     => array( 'l_EN' => "Seed surface",
									 'q_EN' => "Are the seeds smooth or wrinkled?",
									 'm' => array(
									 		0=>"don't know",
									 		3=>"smooth",
											4=>"wrinkled",
											1=>"mixed",
											2=>"other") ),

'pea_GRIN_i__SEEDSPOD'     => array( 'l_EN' => "Seeds per pod",
									 'q_EN' => "How many seeds are in each pod, on average? (observe at least five pods from different plants)"),

'pea_GRIN_m__STEMFASC'     => array( 'l_EN' => "Stem",
									 'q_EN' => "Are the stems round or flattened?  (Plants with flattened (or \"fasciated\") stems tend to produce clusters of flowers only at the tops of the plants)",
									 'm' => array(
									 		0=>"don't know",
									 		2=>"round",
											3=>"flattened",
											1=>"mixed") ),

);
}
?>