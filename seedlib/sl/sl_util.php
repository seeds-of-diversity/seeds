<?php

class SLUtil
{
    /**
     * @param float $g
     * @param array $raParms g_100 => weight of 100 seeds ; or psp to use standard amount
     * @return int number of seeds
     */
    static function SeedsFromGrams( float $g, array $raParms ) : int
    {
        $nSeeds = 0;
        
        if( @$raParms['g_100'] ) {
            // weight of 100 seeds is given
            $nSeeds = intval($g * 100 / $raParms['g_100']);
        } else 
        if( ($seedsPerGram = self::GetSeedsPerGram(@$raParms['psp'])) > 0 ) {
            // use standard seeds/gram
            $nSeeds = intval($g * $seedsPerGram); 
        }
        return($nSeeds);
    }

    /**
     * @param int $nSeeds number of seeds
     * @param array $raParms popsize => population size ; psp to use standard population size
     * @return float
     */
    static function PopsFromSeeds( int $nSeeds, array $raParms ) : float
    {
        return( $nSeeds / (@$raParms['popsize'] ?: self::GetPopulationCommercial($raParms['psp'])) );
    }
    
    
    static function GetPopulationCommercial( string $psp )
    {
        $psp = self::normalizePSP($psp);
        return(@self::commercial_population[$psp] ?: 40);
    }

    static function normalizePSP( string $psp )
    {
        $psp = strtolower( $psp );
        if( substr($psp,0,8) == 'amaranth' )   $psp = 'amaranth';
        if( substr($psp,0,6) == 'tomato' )   $psp = 'tomato';
        if( substr($psp,0,4) == 'bean' )     $psp = 'bean';
        if( substr($psp,0,7) == 'lettuce' )  $psp = 'lettuce';
        if( substr($psp,0,6) == 'squash' )   $psp = 'squash';
        if( substr($psp,0,6) == 'turnip' )   $psp = 'turnip';
// use in_array to avoid things like peafeather
        if( substr($psp,0,3) == 'pea' )      $psp = 'pea';
// avoid peppergrass?
        if( substr($psp,0,6) == 'pepper' )      $psp = 'pepper';
        if( substr($psp,0,8) == 'cucumber' )      $psp = 'cucumber';
// avoid cornsalad?
        if( substr($psp,0,4) == 'corn' )      $psp = 'corn';

        return( $psp );
    }

    static function GetSeedsPerGram( string $psp )
    {
        $psp = self::normalizePSP($psp);

        $raSeedsPerGram = [
            // from sustainableseedco.com - these numbers reveal approximations when you convert to ounces or pounds
            'amaranth' => 1235,
            'artichoke'     => 22,
            'asparagus'     => 34,
            'barley'        => 18,
            'basil'         => 564,
            'bean'          => 3,  // range of 2-4 for bush, lima, pole, kidney, pinto, etc
            'bean-soy'      => 6,
            'beet'          => 53,
            'broccoli'      => 317,
            'brussel sprouts' => 282,
            'buckwheat' => 33,
            'cabbage'      => 229,
            'cabbage-chinese' => 388,
            'carrot'        => 705,
            'cauliflower'    => 317,
            'celery'        => 2293,
            'chard'         => 53,
            'collards'     => 300,
            'corn'          => 5,   //'corn-dent'    => 5, 'corn-sweet'    => 7,
            'cucumber'     => 34,
            'eggplant'        => 229,
            'gourd'    => 30,

            'kale' => 282,
            'kohlrabi' => 247,
            'leek' => 388,
            'lentil'        => 21,
            'lettuce' => 882,
            'melon'        => 389,
            'millet'    => 176,
            'mustard' => 529,
            'oat' => 34,
            'okra' => 18,
            'onion' => 317,
            'parsnip' => 176,
            'pea'          => 4,
            'pepper' => 176,
            'quinoa' => 353,
            'radish' => 94,
            'rye' => 18,
            'spinach' => 74,
            'sorghum' => 35,
            'squash' => 6,  // winter=6, summer=9, pumpkin=8
            'sunflower' => 14,
            'tatsoi' => 423,
            'tomato' => 265,
            'tomato-cherry' => 353,
            'turnip' => 335,
            'watermelon' => 14,
            
            'emmer' => 18,  // should be wheat-emmer in rosetta
            'einkorn' => 18,  // should be wheat-einkorn in rosetta
            
            'wheat' => 18,
        ];

        return( @$raSeedsPerGram[$psp] ?: -1 );
    }

    const commercial_population = [
    	"arugula" => 80,
    	"asparagus" => 100,
    	"barley" => 80,
    	"basil" => 80,
    	"bean-adzuki" => 40,
    	"bean-asparagus" => 40,
    	"bean" => 40,
    	"bean-fava" => 40,
    	"bean-hyacinth" => 40,
    	"bean-lima" => 40,
    	"bean-mung" => 40,
    	"bean-runner" => 40,
    	"bean-soy" => 40,
    	"bean-tepary" => 40,
    	"beet" => 80,
        "beet-sugar" => 80,
    	"broccoli" => 80,
    	"broccoli-rabe" => 80,
    	"broccoli-chinese" => 80,
    	"brussel sprouts" => 80,
    	"buckwheat" => 80,
    	"cabbage" => 80,
    	"cabbage-chinese" => 80,
    	"canola" => 80,
    	"carrot" => 80,            // our book says 200 but for purposes of collection population size let's use this
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
    	"melon" => 20,
    	"mustard" => 80,
    	"oat" => 80,
    	"okra" => 40,
    	"onion" => 200,
    	"orach" => 0,
    	"parsley" => 80,
    	"parsnip" => 80,
    	"pea" => 40,
    	"peanut" => 40,
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
    	"watermelon-citron" => 20,
        
        'emmer' => 80,  // should be wheat-emmer in rosetta
        'einkorn' => 80,  // should be wheat-einkorn in rosetta
        
        'wheat'        => 80,
        'wheat, durum' => 80,
    ];
}