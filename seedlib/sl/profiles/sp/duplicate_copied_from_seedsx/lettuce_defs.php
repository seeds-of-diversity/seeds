<?php
class SLDescDefsLettuce
{

static public $raDefsLettuce = array(
//Harvest
'lettuce_GRIN_m__HEADTYPE' => array( 'l_EN' => "Lettuce Type",
									 'q_EN' => "What type of lettuce would you call this?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"butterhead",
											2=>"romaine",
											3=>"curled",
											4=>"crisphead",
											5=>"leafy",
											6=>"mixed",
											7=>"stem") ),

'lettuce_GRIN_m__LEAFCOLOR' => array( 'l_EN' => "Leaf Colour",
									  'q_EN' => "What colour are the leaves, generally?",
									  'm' => array(
									  		0=>"don't know",
									  		1=>"blue green",
											2=>"dark green",
											3=>"green",
											4=>"grey",
											5=>"mix",
											6=>"pale green",
											7=>"red",
											8=>"yellow green") ),

'lettuce_GRIN_m__ANTHOCYAN'=> array( 'l_EN' => "Red or Purple in leaves",
									 'q_EN' => "Is there any red or purple colour in the leaves?",
									 'm' => array(
									  		0=>"don't know",
									  		1=>"none",
									  		2=>"mix of spotting and leaf tips",
											3=>"spotting",
											4=>"tinge at leaf tips") ),

'lettuce_GRIN_f__HEADDEPTH'=> array( 'l_EN' => "Lettuce Height",
									 'q_EN' => "How tall is this lettuce on average (cm)?  (measure from the ground to the top of the head or uppermost leaves)"),

'lettuce_GRIN_f__HEADDIAM' => array( 'l_EN' => "Lettuce Diameter",
									 'q_EN' => "What is the average diameter of this lettuce (cm)?  (if a head type, measure only the part of the plant that you would eat; don't measure inedible spreading leaves)"),

'lettuce_GRIN_m__HEADSOLID'=> array( 'l_EN' => "Solid Head",
									 'q_EN' => "Does the lettuce have a solid or loose head?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"loose head or leafy",
											5=>"solid head") ),

'lettuce_GRIN_m__LEAFCRISP'=> array( 'l_EN' => "Crisp Leaves",
									 'q_EN' => "Are the leaves crisp?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"limp",
											5=>"crisp") ),

'lettuce_SoD_f__LEAFDIMEN_L'=>array( 'l_EN' => "Leaf lenght",
									 'q_EN' => "How long are the outermost (lowest) leaves (mm)?  (average five leaves)"),

'lettuce_SoD_f__LEAFDIMEN_W'=>array( 'l_EN' => "Leaf width",
									 'q_EN' => "How wide are the outermost (lowest) leaves (mm)?  (average five leaves)"),

'lettuce_GRIN_m__LEAFFOLD' => array( 'l_EN' => "Loose or Tight Leaves",
									 'q_EN' => "Are the leaves folded tightly or loosely?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"loosely",
											5=>"tightly") ),

'lettuce_GRIN_m__LEAFSHAPE'=> array( 'l_EN' => "Leaf Shape",
									 'q_EN' => "What shape are the leaves?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"linear",
											3=>"oblanceolate",
											4=>"obovate",
											5=>"spatulate",
											2=>"mix"),
									 'img'=>array(
									 		1=>"lettuce/lettuce LEAFSHAPE_1.gif",
											3=>"lettuce/lettuce LEAFSHAPE_3.gif",
											4=>"lettuce/lettuce LEAFSHAPE_4.gif",
											5=>"lettuce/lettuce LEAFSHAPE_5.gif"),
									 'imgParms'  => array( "imgH" => 60 )),

//Flowers
'lettuce_GRIN_m__FLOWERCOL'=> array( 'l_EN' => "Flower Colour",
									 'q_EN' => "What colour are the flowers?",
									 'm' => array(
									 		0=>"don't know",
									 		4=>"yellow",
											1=>"blue",
											3=>"purple",
											2=>"mix") ),

'lettuce_GRIN_f__FLOWERDIAM'=>array( 'l_EN' => "Flower Diameter",
									 'q_EN' => "What is the average diameter of the flowers when fully open (mm)?"),

//Seeds
'lettuce_GRIN_f__PLANTHGT' => array( 'l_EN' => "Plant height when seeds mature",
									 'q_EN' => "How tall are the plants, on average, when seeds are mature (cm)?"),

'lettuce_GRIN_m__SEEDCOLOR'=> array( 'l_EN' => "Seed colour",
									 'q_EN' => "What colour are the seeds?",
									 'm' => array(
									 		0=>"don't know",
									 		1=>"black/grey",
											2=>"black",
											3=>"brown",
											4=>"black/white mix",
											5=>"grey",
											7=>"white",
											6=>"mix") ),

);
}
?>