<?php

/* MbrProfile
 *
 * Copyright 2021 Seeds of Diversity Canada
 *
 * Manage member profile data
 */

class MbrProfile
{
    private $oApp;
    private $lang;

    private $raChoices = [
        'mbrWho'    => ['who_gardener'    => ['EN'=>"Gardener",                         'FR'=>"Jardinier"],
                        'who_farmer'      => ['EN'=>"Farmer",                           'FR'=>""],
                        'who_seedsaver'   => ['EN'=>"Seed saver",                       'FR'=>""],
                        'who_seedvendor'  => ['EN'=>"Commercial seed vendor/producer",  'FR'=>""],
                        'who_educator'    => ['EN'=>"Educator",                         'FR'=>""],
                        'who_nfp'         => ['EN'=>"Not-for-profit organization",      'FR'=>""],
                        'who_heritage'    => ['EN'=>"Heritage site",                    'FR'=>""],
                        'who_commgard'    => ['EN'=>"Community garden",                 'FR'=>""],
                       ],
        'mbrHow'    => ['how_exchange'    => ['EN'=>"Exchanging saved seeds with other gardeners",
                                              'FR'=>""],
                        'how_slgrow'      => ['EN'=>"Growing out rare seeds from Seeds of Diversity's collection",
                                              'FR'=>""],
                        'how_promote'     => ['EN'=>"Promoting Seeds of Diversity in my community",
                                              'FR'=>""],
                        'how_teach'       => ['EN'=>"Teaching local gardeners about seed saving, pollinators, food biodiversity and history",
                                              'FR'=>""],
                       ],
        'mbrLearn'  => ['learn_seeds'     => ['EN'=>"Seed saving methods",                  'FR'=>""],
                        'learn_poll'      => ['EN'=>"Pollinators and pollination",          'FR'=>""],
                        'learn_gardening' => ['EN'=>"General gardening techniques",         'FR'=>""],
                        'learn_youth'     => ['EN'=>"Youth in gardening and food systems",  'FR'=>""],
                        'learn_heritage'  => ['EN'=>"Food and garden history",              'FR'=>""],
                        'learn_seedysat'  => ['EN'=>"Seedy Saturdays/Sundays",              'FR'=>""],
                        'learn_policy'    => ['EN'=>"Policy and regulations about seeds",   'FR'=>""],
                        'learn_lgscale'   => ['EN'=>"Scaling up seed production for vendors and wholesale growers", 'FR'=>""],
                       ]
    ];

    private $raOthers = ['mbrWhoOther'=>'who_other', 'mbrHowOther'=>'how_other', 'mbrLearnOther'=>'learn_other'];

    private $raMT = [ 'mbrWho_codes' => "", 'mbrHow_codes' => "", 'mbrLearn_codes' => "",   // who_gardener,who_farmer,...
                      'mbrWho_EN'    => "", 'mbrHow_EN'    => "", 'mbrLearn_EN'    => "",   // Gardener, Farmer, ...
                      'mbrWho_FR'    => "", 'mbrHow_FR'    => "", 'mbrLearn_FR'    => "",   // Jardinier, Fermier, ...
                      'mbrWhoOther'  => "", 'mbrHowOther'  => "", 'mbrLearnOther'  => "",   // whatever they entered
    ];

    function __construct( SEEDAppSessionAccount $oApp, string $lang )
    {
        $this->oApp = $oApp;
        $this->lang = SEEDCore_SmartVal( $lang, ['EN','FR'] );
    }

    function GetProfileData()
    {
        $raOut = [];

        return( $raOut );
    }

    function InputFormData( array $raSrc = null )
    {
        $raOut = $this->raMT;

        if( $raSrc === null ) $raSrc = $_REQUEST;

        foreach( $this->raChoices as $kX => $raP ) {
            $ra = [];
            foreach( $raP as $p => $dummy ) { if( intval(@$raSrc["p_$p"]) )  $ra[] = $p; }  // raSrc will have e.g. p_who_gardener => 1
            if( $ra )  $raOut[$kX.'_codes'] = implode(',', $ra );
        }
        foreach( $this->raOthers as $kX => $p ) {
            $raOut[$kX] = @$raSrc["p_$p"];
        }

        return( $raOut );
    }

    function KlugeString( ?string $sCodes )
    {
        $s = "";

        $raCodes = explode( ',', $sCodes );

        // convert a string of codes into a readable string -- this is probably a private function once $this knows about storage of profile data
        foreach( $this->raChoices as $kXDummy => $raP ) {
            foreach( $raP as $p => $raLabel ) {
                if( in_array( $p, $raCodes ) )  $s .= ($s ? ", " : "").$raLabel[$this->lang];
            }
        }

        return( $s );
    }

    function DrawProfileForm()
    {
        $s = "<div style='border:1px solid #aaa;border-radius:5px;margin:20px;padding:15px'>"
             ."<form method='post'>"
             ."<h3>Welcome as a member of Seeds of Diversity!</h3>"
             ."<p>Please take a few moments to tell us more about yourself and your interests.</p>"

             ."<p><b>How would you describe yourself? (check all that apply)</b></p>"
             ."<p>".SEEDCore_ArrayExpandSeries( $this->raChoices['mbrWho'], "<input type='checkbox' name='p_[[k]]' value='1'/>&nbsp;[[v|{$this->lang}]]<br/>" )
             ."Other: <input type='text' name='p_who_other' size='20'/>"
             ."</p>"

             ."<p><b>How would you like to get involved in Seeds of Diversity? (check all that apply)</b></p>"
             ."<p>".SEEDCore_ArrayExpandSeries( $this->raChoices['mbrHow'], "<input type='checkbox' name='p_[[k]]' value='1'/>&nbsp;[[v|{$this->lang}]]<br/>" )
             ."Other: <input type='text' name='p_how_other' size='20'/>"
             ."</p>"

             ."<p><b>What would you like to read about in our newsletters? (check all that apply)</b></p>"
             ."<p>".SEEDCore_ArrayExpandSeries( $this->raChoices['mbrLearn'], "<input type='checkbox' name='p_[[k]]' value='1'/>&nbsp;[[v|{$this->lang}]]<br/>" )
             ."Other: <input type='text' name='p_learn_other' size='20'/>"
             ."</p>"

             ."<p><b>You'll be able to edit these answers, and add more information too, when you login to your Seeds of Diversity member account.</b></p>"

             //."<input type='hidden' name='mbrocst' value='".MBROC_ST_CONFIRM."'/>"     // once the order is confirmed the kOrder is in the session and eStatus col is NEW so this is unnecessary
             ."<input type='hidden' name='p_submitted' value='1'/>"
             ."<input type='submit' value='Save'/>"
             ."</form>"
             ."</div>";

        return( $s );
    }
}
