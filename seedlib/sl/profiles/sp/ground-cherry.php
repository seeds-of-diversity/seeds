<?php

/* Crop Profiles - ground cherry
 *
 * Copyright (c) 2024 Seeds of Diversity Canada
 */


class SLProfiles_GroundCherry
{
    static public function GetDefs()
    {
        $raDefs = [
            // Roguing
            'ground-cherry_SoD_i__uprightplants'        => ['l_EN' => "Upright plants",
                                                            'q_EN' => "Number of plants that grew upright?"],
            'ground-cherry_SoD_i__lowplantsremoved'     => ['l_EN' => "Low-growing plants removed",
                                                            'q_EN' => "Number of low-growing plants that were removed?"],
            'ground-cherry_SoD_b__floweringatremoval'   => ['l_EN' => "Flowering at roguing",
                                                            'q_EN' => "Were the plants already flowering when low-growing plants were removed?"],

            'ground-cherry_SoD_b__floweringatremoval'   => ['l_EN' => "Flowering at roguing",
                                                            'q_EN' => "Were the plants already flowering when low-growing plants were removed?"],

            // Tasting and saving seeds
            'ground-cherry_SoD_i__fruittasted'          => ['l_EN' => "Fruit tasted",
                                                            'q_EN' => "Number of fruit tasted?"],
            'ground-cherry_SoD_i__fruitharvestseeds'    => ['l_EN' => "Fruit for seeds",
                                                            'q_EN' => " Number of fruits chosen for seeds?"],
        ];

        return($raDefs);
    }

    static function GroundCherryForm( SLProfilesDefs $oSLProfilesDefs, SLProfilesDB $oDB, int $kVI, string $eForm )
    {
        $raFormCommon = [
            ['cmd'=>'head', 'head_EN'=>"ground-cherry"],

            ['cmd'=>'section', 'title_EN'=>"Dates", 'title_FR'=>"Les dates"],
            [   'cmd'=>'q', 'k'=>'common_SoD_d__sowdate'],
            [   'cmd'=>'q', 'k'=>'common_SoD_d__germdate'],
            [   'cmd'=>'q', 'k'=>'common_SoD_d__transplantdate'],
            [   'cmd'=>'q', 'k'=>'common_SoD_d__flowerdate'],
            [   'cmd'=>'q', 'k'=>'common_SoD_d__fruitharvestdate'],
            [   'cmd'=>'q', 'k'=>'common_SoD_d__seedharvestdate'],

            ['cmd'=>'section', 'title_EN'=>"Population Counts", 'title_FR'=>"Population Counts"],
            [   'cmd'=>'q', 'k'=>'common_SoD_i__popinitial'],
            [   'cmd'=>'q', 'k'=>'common_SoD_i__germpercent'],
            [   'cmd'=>'q', 'k'=>'common_SoD_i__poptransplanted'],
            [   'cmd'=>'q', 'k'=>'common_SoD_i__plantsremoved'],
            [   'cmd'=>'q', 'k'=>'common_SoD_i__plantsdied'],
            [   'cmd'=>'q', 'k'=>'common_SoD_i__poppollinating'],
            [   'cmd'=>'q', 'k'=>'common_SoD_i__popharvestseeds'],

        ];

        $raFormFull = [
        ];
        $raFormShort = [
        ];

        $raFormCGO = [
            // Tall-bearing project
            ['cmd'=>'section', 'title_EN'=>"Roguing out low-growing plants", 'title_FR'=>"Roguing out low-growing plants"],
            [   'cmd'=>'q', 'k'=>'ground-cherry_SoD_i__uprightplants'],
            [   'cmd'=>'q', 'k'=>'ground-cherry_SoD_i__lowplantsremoved'],
            [   'cmd'=>'q', 'k'=>'ground-cherry_SoD_b__floweringatremoval'],

            ['cmd'=>'section', 'title_EN'=>"Tasting berries and saving seeds", 'title_FR'=>"Tasting berries and saving seeds"],
            [   'cmd'=>'q', 'k'=>'ground-cherry_SoD_i__fruittasted'],
            [   'cmd'=>'q', 'k'=>'ground-cherry_SoD_i__fruitharvestseeds'],

            // General
            ['cmd'=>'section', 'title_EN'=>"Health/Disease", 'title_FR'=>"Health"],
            [   'cmd'=>'q', 'k'=>'common_SoD_b__disease'],

            ['cmd'=>'section', 'title_EN'=>"Ratings (5 for excellent, 1 for very poor)", 'title_FR'=>"Ratings"],
            [   'cmd'=>'q', 'k'=>'common_SoD_r__productivity'],
            [   'cmd'=>'q', 'k'=>'common_SoD_r__flavour'],
            [   'cmd'=>'q', 'k'=>'common_SoD_r__diseaseresistance'],
            [   'cmd'=>'q', 'k'=>'common_SoD_r__uniformity'],
            [   'cmd'=>'q', 'k'=>'common_SoD_r__appeal'],

            [   'cmd'=>'q', 'k'=>'common_SoD_b__wouldgrowagain'],
            [   'cmd'=>'q', 'k'=>'common_SoD_b__wouldrecommend'],

            ['cmd'=>'section', 'title_EN'=>"Notes", 'title_FR'=>"Notes"],
            [   'cmd'=>'inst', 'inst_EN'=>"Please note any pros and cons related to growing this variety."],
            [   'cmd'=>'q',    'k'=>'common_SoD_s__notespros'],
            [   'cmd'=>'q',    'k'=>'common_SoD_s__notescons'],
            [   'cmd'=>'inst', 'inst_EN'=>"Any other comments or things worth noting? How would you describe the variety overall? Anything stand out?"],
            [   'cmd'=>'q',    'k'=>'common_SoD_s__notesgeneral'],
        ];

        $oF = new SLProfilesForm( $oDB, $kVI );
        $oF->Update();
        $oF->SetDefs( $oSLProfilesDefs->GetDefsRAFromSP('ground-cherry') );

        $f = $oF->Style()
            .$oF->DrawForm($raFormCommon);        // draw the common parts of the forms
        switch($eForm) {
            default:
            case 'default':     // all forms are the same
            case 'long':        //$f .= $oF->DrawForm( $raFormFull );   break;
            case 'short':       //$f .= $oF->DrawForm( $raFormShort );  break;
            case 'cgo':         $f .= $oF->DrawForm( $raFormCGO );    break;
        }

        return ($f);
    }
}
