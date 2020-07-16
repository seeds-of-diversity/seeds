<?php

include("../../seedcore/SEEDCore.php");

$commercial_population = array(
	"arugula" => 80,
	"asparagus" => 100,
	"barley" => 80,
	"basil" => 80,
	"bean-adzuki" => 40,
	"bean-asparagus" => 40,
	"bean-bush" => 40,
	"bean-fava" => 40,
	"bean-hyacinth" => 40,
	"bean-lima" => 40,
	"bean-mung" => 40,
	"bean-pole" => 40,
	"bean-runner" => 40,
	"bean-soy" => 40,
	"bean-tepary" => 40,
	"beet-sugar" => 80,
	"broccoli" => 80,
	"broccoli-rabe" => 80,
	"broccoli-chinese" => 80,
	"brussel sprouts" => 80,
	"buckwheat" => 80,
	"cabbage" => 80,
	"cabbage-chinese" => 80,
	"canola" => 80,
	"carrot" => 200,
	"cauliflower" => 80,
	"celeriac" => 80,
	"celery" => 80,
	"chickpea" => 80,
	"chicory-curled" => 80,
	"chicory-radicchio" => 80,
	"chives" => 120,
	"chives-chinese" => 120,
	"chives-garlic" => 120,
	"collards" => 80,
	"coriander" => 0,
	"corn" => 200,
	"corn-salad" => 20,
	"cowpea" => 40,
	"cucumber" => 20,
	"dill" => 80,
	"eggplant" => 80,
	"endive" => 0,
	"fennel" => 0,
	"flax" => 80,
	"gourd" => 20,
	"ground cherry" => 0,
	"kale" => 80,
	"kohlrabi" => 80,
	"leek" => 80,
	"lentil" => 80,
	"lettuce" => 20,
	"melon-muskmelon" => 20,
	"mustard" => 80,
	"oat" => 80,
	"okra" => 40,
	"onion" => 200,
	"orach" => 0,
	"parsley" => 80,
	"parsnip" => 80,
	"pea" => 0,
	"peanut" => 0,
	"pepper-sweet" => 20,
	"pepper-hot" => 40,
	"radish" => 80,
	"rhubarb" => 0,
	"rye" => 80,
	"safflower" => 0,
	"salsify-scorzonera" => 0,
	"sorghum" => 80,
	"sorrel" => 0,
	"spinach" => 80,
	"spinach-new-zealand" => 80,
	"squash" => 20,
	"sunflower" => 0,
	"swiss chard" => 80,
	"tomatillo" => 0,
	"tomato" => 20,
	"tomato-ancient" => 40,
	"triticale" => 80,
	"turnip" => 80,
	"turnip-rutabaga" => 80,
	"watermelon-citron" => 20
);

$germination = SEEDInput_Str("germ") / 100;
$species = SEEDInput_Str("sp");
$weight100 = SEEDInput_Str("weight");

$have_weight = true;
if ($weight100 == "") {
    $have_weight = false;
}

$working_lot = 10 * ($commercial_population[$species] / $germination) * 1.15;
$backup_lot = 20 * ($commercial_population[$species] / $germination) * 1.15;

$working_weight = 0;
$backup_weight = 0;
if ($have_weight) {
    $working_weight = round($working_lot * 100 / $weight100, 2);
    $backup_weight = round($backup_lot * 100 / $weight100, 2);
}

$working_string = "working lot: " . $working_lot . " seeds";
$backup_string = "backup lot: " . $backup_lot . " seeds";

if ($have_weight) {
    $working_string .= " (" . $working_weight . "g)";
    $backup_string .= " (" . $backup_weight . "g)";
}

echo($working_string . "<br>");
echo($backup_string . "<br>");
?>
