<?php

/* MbrProfile
 *
 * Copyright 2021-2024 Seeds of Diversity Canada
 *
 * Manage member profile data
 */

include_once( SEEDCORE."SEEDLocal.php" );


class MbrProfile
{
    private $oApp;
    private $oL;

    private $raChoices = [
        'mbrWho'    => ['who_gardener'    => ['EN'=>"Gardener",                         'FR'=>"Jardinier"],
                        'who_farmer'      => ['EN'=>"Farmer",                           'FR'=>"Fermier"],
                        'who_seedsaver'   => ['EN'=>"Seed saver",                       'FR'=>"Conservateur de semences"],
                        'who_seedvendor'  => ['EN'=>"Commercial seed vendor/producer",  'FR'=>"Fournisseur/producteur de semences"],
                        'who_educator'    => ['EN'=>"Educator",                         'FR'=>"&Eacute;ducateur"],
                        'who_nfp'         => ['EN'=>"Not-for-profit organization",      'FR'=>"Organisme sans but lucratif"],
                        'who_heritage'    => ['EN'=>"Heritage site",                    'FR'=>"Site du patrimoine"],
                        'who_commgard'    => ['EN'=>"Community garden",                 'FR'=>"Jardin communautaire"],
                       ],
        'mbrHow'    => ['how_exchange'    => ['EN'=>"Exchanging saved seeds with other gardeners",
                                              'FR'=>"&Eacute;changer des semences entre jardiniers"],
                        'how_slgrow'      => ['EN'=>"Growing out rare seeds from Seeds of Diversity's collection",
                                              'FR'=>"Cultiver des semences rares de la collection de Semences du patrimoine"],
                        'how_promote'     => ['EN'=>"Promoting Seeds of Diversity in my community",
                                              'FR'=>"Faire conna&icirc;tre Semences du patrimoine autour de moi"],
                        'how_teach'       => ['EN'=>"Teaching local gardeners about seed saving, pollinators, food biodiversity and history",
                                              'FR'=>"Transmettre des connaissances sur la conservation des semences, la pollinisation,
                                                     la biodiversit&eacute; alimentaire et l'histoire &agrave; des jardiniers locaux"],
                       ],
        'mbrLearn'  => ['learn_seeds'     => ['EN'=>"Seed saving methods",                  'FR'=>"M&eacute;thodes de conservation des semences"],
                        'learn_poll'      => ['EN'=>"Pollinators and pollination",          'FR'=>"Pollinisateurs et pollinisation"],
                        'learn_gardening' => ['EN'=>"General gardening techniques",         'FR'=>"Techniques g&eacute;n&eacute;rales de jardinage"],
                        'learn_youth'     => ['EN'=>"Youth in gardening and food systems",  'FR'=>"Jeunes, jardinage et syst&egrave;mes alimentaires"],
                        'learn_heritage'  => ['EN'=>"Food and garden history",              'FR'=>"Histoire du jardinage et de l'alimentation"],
                        'learn_seedysat'  => ['EN'=>"Seedy Saturdays/Sundays",              'FR'=>"F&ecirc;tes des semences"],
                        'learn_policy'    => ['EN'=>"Policy and regulations about seeds",   'FR'=>"Politiques et r&eacute;glementation des semences"],
                        'learn_lgscale'   => ['EN'=>"Scaling up seed production for vendors and wholesale growers",
                                              'FR'=>"Mise &agrave; l'&eacute;chelle de la production de semences pour les commer&ccedil;ants et producteurs grossistes"],
                       ]
    ];

    private $raOthers = ['mbrWhoOther'=>'who_other', 'mbrHowOther'=>'how_other', 'mbrLearnOther'=>'learn_other'];

    private $raMT = [ 'mbrWho_codes' => "", 'mbrHow_codes' => "", 'mbrLearn_codes' => "",   // who_gardener,who_farmer,...
                      'mbrWho_EN'    => "", 'mbrHow_EN'    => "", 'mbrLearn_EN'    => "",   // Gardener, Farmer, ...
                      'mbrWho_FR'    => "", 'mbrHow_FR'    => "", 'mbrLearn_FR'    => "",   // Jardinier, Fermier, ...
                      'mbrWhoOther'  => "", 'mbrHowOther'  => "", 'mbrLearnOther'  => "",   // whatever they entered
    ];

    function __construct( SEEDAppSessionAccount $oApp ) //, string $lang )
    {
        $this->oApp = $oApp;
        //$this->lang = SEEDCore_SmartVal( $lang, ['EN','FR'] );

        $this->oL = new SEED_Local( $this->seedlocalStrs(), $this->oApp->lang, 'mbrProfile' );
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
                if( in_array( $p, $raCodes ) )  $s .= ($s ? ", " : "").$raLabel[$this->oApp->lang];
            }
        }

        return( $s );
    }

    function DrawProfileForm()
    {
        $s = "<div style='border:1px solid #aaa;border-radius:5px;margin:20px;padding:15px'>"
             ."<form method='post'>"
             ."{$this->oL->S('form_header')}"

             ."<p><b>{$this->oL->S('question_who')}</b></p>"
             ."<p>".SEEDCore_ArrayExpandSeries( $this->raChoices['mbrWho'], "<input type='checkbox' name='p_[[k]]' value='1'/>&nbsp;[[v|{$this->oApp->lang}]]<br/>", false ) // don't escape entities in the translated labels
             ."{$this->oL->S('Other')}: <input type='text' name='p_who_other' size='20'/>"
             ."</p>"

             ."<p><b>{$this->oL->S('question_how')}</b></p>"
             ."<p>".SEEDCore_ArrayExpandSeries( $this->raChoices['mbrHow'], "<input type='checkbox' name='p_[[k]]' value='1'/>&nbsp;[[v|{$this->oApp->lang}]]<br/>", false )
             ."{$this->oL->S('Other')}: <input type='text' name='p_how_other' size='20'/>"
             ."</p>"

             ."<p><b>{$this->oL->S('question_learn')}</b></p>"
             ."<p>".SEEDCore_ArrayExpandSeries( $this->raChoices['mbrLearn'], "<input type='checkbox' name='p_[[k]]' value='1'/>&nbsp;[[v|{$this->oApp->lang}]]<br/>", false )
             ."{$this->oL->S('Other')}: <input type='text' name='p_learn_other' size='20'/>"
             ."</p>"

             ."<p><b>{$this->oL->S('form_footer')}</b></p>"

             //."<input type='hidden' name='mbrocst' value='".MBROC_ST_CONFIRM."'/>"     // once the order is confirmed the kOrder is in the session and eStatus col is NEW so this is unnecessary
             ."<input type='hidden' name='p_submitted' value='1'/>"
             ."<input type='submit' value='{$this->oL->S('Save')}'/>"
             ."</form>"
             ."</div>";

        return( $s );
    }

    private function seedLocalStrs()
    {
        $raStrs = [ 'ns'=>'mbrProfile', 'strs'=> [
            'form_header'       => ['EN'=>"<h3>Welcome as a member of Seeds of Diversity!</h3>
                                           <p>Please take a few moments to tell us more about yourself and your interests.</p>",
                                    'FR'=>"<h3>Bienvenue en tant que membre de Semences du patrimoine!</h3>
                                           <p>Veuillez prendre un moment pour parlez-nous un peu de vous et de ce qui vous int&eacute;resse.</p>"],

            'question_who'      => ['EN'=>"How would you describe yourself? (check all that apply)",
                                    'FR'=>"Qu'est-ce qui vous d&eacute;crit? (cocher tout ce qui s'applique)"],
            'question_how'      => ['EN'=>"How would you like to get involved in Seeds of Diversity? (check all that apply)",
                                    'FR'=>"Comment voulez-vous participer &agrave; Semences du patrimoine? (cocher tout ce qui s'applique)"],
            'question_learn'    => ['EN'=>"What would you like to read about in our newsletters? (check all that apply)",
                                    'FR'=>"Sur quoi voulez-vous en apprendre davantage? (cocher tout ce qui s'applique)"],

            'form_footer'       => ['EN'=>"You'll be able to edit these answers, and add more information too, when you login to your Seeds of Diversity member account.",
                                    'FR'=>"Vous pourrez modifier ces r&eacute;ponses et ajouter plus d'informations lorsque vous vous connecterez &agrave;
                                           votre compte de membre Semences du patrimoine."],

            'Other'             => ['EN'=>"[[]]", 'FR'=>"Autre"],
            'Save'              => ['EN'=>"[[]]", 'FR'=>"Enregistrer"],
        ]];

        return( $raStrs );
    }
}
