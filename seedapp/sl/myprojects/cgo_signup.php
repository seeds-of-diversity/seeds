<?php

include_once(SEEDLIB."sl/sldb.php");

class CGOSignup
{
    protected $oP;

    function __construct(ProjectsCommon $oP)
    {
        $this->oP = $oP;
    }

    function DrawSignupBox( string $s1, string $s2, string $s3, string $sImg )
    {
        $s = "<div class='cgosignup-box container-fluid'>
                <div class='row'>
                   <div class='col-md-2'><img src='$sImg' style='width:100%'/></div>
                   <div class='col-md-8'>
                       $s1
                       <div class='cgosignup-opener' style='padding:1em 0;text-align:center;font-size:150%;font-weight:bold;color:gray;width:60%'>
                           <span style='color:green'>Click to Join this Project</span> <br/><span class='chevron chevron-down'></span>
                       </div>
                   </div>
                   <div class='col-md-2' style='text-align:center'></div>
                </div><div class='row cgosignup-closer' style='display:none'>
                   <div class='col-md-2'>&nbsp;</div>
                   <div class='col-md-6'>$s2</div>
                   <div class='col-md-4'>$s3</div>
                </div>
              </div>";
$s .= "<style>
        .chevron {
            display: inline-block;
            border-right:
                4px solid gray;
            border-bottom:
                4px solid gray;
            width: 1em;
            height: 1em;
        }
        .chevron-down { transform:rotate(45deg); }
</style>";
        return($s);
    }

    protected function drawButton(string $id, bool $bRegistered)
    {
        if( $this->oP->oL->GetLang() == 'EN' ) {
            $s = "
                <div class='cgosignup-form-btn-container-notregistered' style='".($bRegistered ? 'display:none' : "")."'>
                    <button class='cgosignup-form-button' id='$id' disabled onclick='CGOSignup.doRegister($(this))'>Sign up for this project</button>
                </div>
                <div class='cgosignup-form-btn-container-registered' style='".($bRegistered ? "" : 'display:none')."'>
                    <div class='alert alert-success'>Thanks! You're registered in this project.<br/>You'll hear from us soon.
                        <hr/>
                        Oh no, I don't want to be in this project &nbsp;&nbsp;<button onclick='CGOSignup.doUnregister($(this))'>Unregister me please</button>
                    </div>
                </div>";
        } else {
            $s = "
                <div class='cgosignup-form-btn-container-notregistered' style='".($bRegistered ? 'display:none' : "")."'>
                    <button class='cgosignup-form-button' id='$id' disabled onclick='CGOSignup.doRegister($(this))'>Rejoindre ce projet</button>
                </div>
                <div class='cgosignup-form-btn-container-registered' style='".($bRegistered ? "" : 'display:none')."'>
                    <div class='alert alert-success'>Merci! Vous &ecirc;tes inscrit &agrave; ce projet.<br/>Vous aurez bient&ocirc;t de nos nouvelles.
                        <hr/>
                        Oh non, je ne veux pas participer &agrave; ce projet &nbsp;&nbsp;<button onclick='CGOSignup.doUnregister($(this))'>Me d&eacute;sinscrire SVP</button>
                    </div>
                </div>";
        }
        return( $s );
    }

    protected $sReserve =
            ['EN' => "<p style='color:red;margin-top:1em'><b>Reserve your seeds by March 7, 2026</b></p>", // <p style='color:red;margin-top:1em'><b>Reserve your seeds now</b></p>
             'FR' => "<p style='color:red;margin-top:1em'><b>R&eacute;servez vos semences avant le 7 mars 2026</b></p>", // <p style='color:red;margin-top:1em'><b>R&eacute;servez vos semences maintenant</b></p>
            ];
}

class CGOSignup_GC extends CGOSignup
{
    function __construct(ProjectsCommon $oP)
    {
        parent::__construct($oP);
    }

    function Draw( bool $bRegistered )
    {
        /* Sign up for CGO Ground Cherry
         */
        if( $this->oP->oL->GetLang() == 'EN' ) {
            $s1 = "<b>Help Breed a Better Ground Cherry (Year 7)</b>
                   <p>We're selecting upright-growing plants from an originally mixed population, aiming for a plant shape that is easier to harvest.</p>
                   <p>Ground cherries (<i>Physalis pruinosa</i>) are small, sweet, golden-yellow berries that are relatives of tomatillos and tomatoes.
                      Each fruit is enclosed in a wrapper, and it falls from the plant when ripe, so you harvest by picking them off the ground.
                      Since low-growing branches make the berries hard to see, we're breeding for upright branches and good flavour.</p>
                   <p>Your project will be to grow at least 15 plants, pull out any low-growing plants, and save seeds from the tall-growing plants so we can continue the process next year.</p>
                   <div style='border:1px solid #aaa;background-color:#eee;padding:1em'>
                      <p><b>To participate in this project:</b></p>
                         <ul>
                         <li><b>You will need about 30 to 40 square feet of garden space.</b></li>
                         <li><b>You will need space indoors to start seedlings at 20-25 degrees C, for 8 weeks before transplanting.</b></li>
                         </ul>
                      {$this->sReserve['EN']}
                   </div>";
            $s2 = "<p><b>To participate in this project:</b></p>
                      <ul>
                      <li>You will germinate the seeds indoors, starting in March. Note that germination can be slow, and is speeded greatly by warmth.</li>
                      <li>Transplant as many seedlings as you can to your garden after frost. 15-20 would be a good number to aim for.</li>
                      <li>As the plants grow, uproot the plants that grow flat on the ground prior to flowering, to prevent them from crossing with the tall-bearing plants.</li>
                      <li>Taste the berries and keep seeds only from those with excellent flavour.</li>
                      <li>Send some of your saved seeds back to Seeds of Diversity so we can repeat the process next year.</li>
                      <li>Share some of the seeds in your community.</li>
                      </ul>";
            $s3 = "<br/><br/><br/>
                    <div class='cgosignup-form' data-project='cgo2026gc'>
                        <p>Please confirm:<br/>
                        <input type='checkbox' id='cgosignup-form-gc1' value='1' onchange='CGOSignup_GroundCherry.doValidate()'/> I have at least 30 square feet of garden space for this project.<br/>
                        <input type='checkbox' id='cgosignup-form-gc2' value='1' onchange='CGOSignup_GroundCherry.doValidate()'/> I can germinate seeds at 20 - 25 degrees C, and grow seedlings indoors for 8 weeks.
                        </p>
                        {$this->drawButton('cgosignup-form-gcbutton', $bRegistered)}
                    </div>";
        } else {
            $s1 = "<b>Aidez &agrave; produire une meilleure cerise de terre (ann&eacute;e 6)</b>
                   <p>Nous s&eacute;lectionnons des plantes &agrave; croissance verticale parmi une population initialement mixte, en visant une forme de plante plus facile &agrave; r&eacute;colter.</p>
                   <p>Les cerises de terre (<i>Physalis pruinosa</i>) sont de petites baies sucr&eacute;es de couleur jaune dor&eacute; qui sont apparent&eacute;es aux tomatilles et aux tomates.
                      Chaque fruit est enferm&eacute; dans un emballage et tombe de la plante &agrave; maturit&eacute;, vous les r&eacute;coltez donc en les ramassant sur le sol.
                      Puisque les branches basses rendent les baies difficiles &agrave; voir, nous s&eacute;lectionnons pour des branches dress&eacute;es et une bonne saveur.</p>
                   <p>Votre projet sera de faire pousser au moins 15 plantes, d'en arracher toutes les courts plantes, et conserver
                      les semences des plantes &agrave; croissance &eacute;lev&eacute;e afin que nous puissions continuer le processus l'ann&eacute;e prochaine.</p>
                   <div style='border:1px solid #aaa;background-color:#eee;padding:1em'>
                      <p><b>Pour participer &agrave ce projet:</b></p>
                         <ul>
                         <li><b>Vous aurez besoin d'environ 30 &agrave; 40 pieds carr&eacute;s d'espace de jardin.</b></li>
                         <li><b>Vous aurez besoin d'espace &agrave; l'int&eacute;rieur pour faire pousser des plants &agrave; 20-25 degr&eacute;s C pendant 8 semaines avant de les planter dans le jardin.</b></li>
                         </ul>
                      {$this->sReserve['FR']}
                   </div>";
            $s2 = "<p><b>Pour participer &agrave ce projet:</b></p>
                      <ul>
                      <li>Vous ferez germer les semences &agrave; l'int&eacute;rieur, en mars. Notez que la germination peut &ecirc;tre lente et est grandement acc&eacute;l&eacute;r&eacute;e par la chaleur.</li>
                      <li>Transplantez autant de plants que possible dans votre jardin apr&egrave;s le gel. 15-20 serait un bon chiffre &agrave; viser.</li>
                      <li>Au d&eacute;but de l'&eacute;t&eacute;, retirez les plantes qui poussent &agrave; plat sur le sol avant la floraison, pour &eacute;viter qu'elles ne se croisent avec les plantes &eacute;lev&eacute;.</li>
                      <li>Go&ucirc;tez les baies et conservez les semences uniquement de celles qui ont une excellente saveur.</li>
                      <li>Renvoyez une partie de vos semences &agrave; Semences du patrimoine afin que nous puissions r&eacute;p&eacute;ter le processus l'ann&eacute;e prochaine.</li>
                      <li>Partagez des semences dans votre communaut&eacute;.</li>
                      </ul>";
            $s3 = "<br/><br/><br/>
                    <div class='cgosignup-form' data-project='cgo2026gc'>
                        <p>Veuillez confirmer:<br/>
                        <input type='checkbox' id='cgosignup-form-gc1' value='1' onchange='CGOSignup_GroundCherry.doValidate()'/> J'ai au moins 30 pieds carr&eacute;s d'espace de jardin pour ce projet.<br/>
                        <input type='checkbox' id='cgosignup-form-gc2' value='1' onchange='CGOSignup_GroundCherry.doValidate()'/> Je peux faire germer des semences &agrave; 20 - 25 degr&eacute;s C et faire pousser des plants &agrave; l'int&eacute;rieur pendant 8 semaines.
                        </p>
                        {$this->drawButton('cgosignup-form-gcbutton', $bRegistered)}
                    </div>";
        }

        $sImg = "https://seeds.ca/d?n=ebulletin/2023/10-groundcherry-1.jpg";

        return( $this->DrawSignupBox($s1, $s2, $s3, $sImg));
    }
}

class CGOSignup_Tomato extends CGOSignup
{
    function __construct(ProjectsCommon $oP)
    {
        parent::__construct($oP);
    }

    function Draw( bool $bRegistered )
    {
        /* Sign up for CGO Tomato
         */
        list($sCvAvailable,$raCvOpts) = $this->tomatoVarieties();
        $sCvOpts = SEEDCore_ArrayExpandSeries($raCvOpts, "<option value='[[v]]'>[[k]]</option>");

        if( $this->oP->oL->GetLang() == 'EN' ) {
                /* <b>Help Explore and Multiply Canadian Tomato Seeds</b>
                   <p>Our members have shared an assortment of Canadian tomato seeds - varieties bred in Canada or with a long history of being well-adapted to our growing conditions.
                      Your project will be to grow 6 or more tomato plants...</p>
                 */
            $s1 = "<b>Help Explore and Multiply Dwarf Tomato Seeds</b>
                   <p>We've chosen an assortment of dwarf tomato varieties, ranging from ultra-compact to ~3ft tall. Some are cherry varieties, and some are good-sized slicers,
                      but they all grow on short, compact plants that are perfect for small gardens or containers.</p>
                   <p>None of them should require staking. All of them are relatively rare.</p>
                   <p>Your project will be to grow 6 or more tomato plants, take observations through the season, and save seeds to help share those varieties next year.
                      We also hope that you'll share seeds with others in your community.</p>
                   <p>Learn more about dwarf tomato varieties at the <a href='https://www.dwarftomatoproject.net'>Dwarf Tomato Project</a></p>
                   <div style='border:1px solid #aaa;background-color:#eee;padding:1em'>
                      <p><b>To participate in this project:</b></p>
                         <ul>
                         <li><b>You will need at least 20 square feet of garden space to grow 6 or more tomato plants 1.5 feet apart.</b></li>
                         <li><b>You are able grow your tomatoes isolated at least 20 feet apart from any other tomato varieties.</b></li>
                         <li><b>You will need a warm, bright place indoors to start seedlings, for 6-8 weeks before transplanting.</b></li>
                         </ul>
                      {$this->sReserve['EN']}
                    </div>";
            $s2 = "<p><b>To participate in this project:</b></p>
                      <ul>
                      <li>You will germinate the seeds indoors, starting in March.</li>
                      <li>Transplant at least 6 seedlings to your garden after all danger of frost, <b>at least 20 feet apart</b> from any other tomato variety.</li>
                      <li>As the plants grow, take observations and photos.</li>
                      <li>Harvest and enjoy the tomatoes, and save seeds from at least a few fruit from each plant.</li>
                      <li>Send some of your saved seeds back to Seeds of Diversity so we can repeat the process next year.</li>
                      <li>Share some of the seeds in your community.</li>
                      </ul>
                   <hr style='border-color:#888'/>
                   <p style='font-weight:bold;font-size:150%'>Varieties Available</p>"
                  .$sCvAvailable;
            $sDisabled = $bRegistered ? 'disabled' : '';
            $s3 =  "<div class='cgosignup-form' data-project='cgo2026tomato'>
                        <p>Please choose a variety below and confirm:<br/>
                            <input type='checkbox' id='cgosignup-form-tomato1' value='1' onchange='CGOSignup_Tomato.doValidate()'/> I have at least 20 square feet of garden space for this project.<br/>
                            <input type='checkbox' id='cgosignup-form-tomato2' value='1' onchange='CGOSignup_Tomato.doValidate()'/> I can isolate tomato plants at least 20 feet apart from any other tomato variety.<br/>
                            <input type='checkbox' id='cgosignup-form-tomato3' value='1' onchange='CGOSignup_Tomato.doValidate()'/> I can germinate seeds and grow seedlings indoors for 6-8 weeks.<br/>
                            <select id='cgosignup-form-tomatoselect' {$sDisabled} onchange='CGOSignup_Tomato.doValidate()'><option value='0'>--- Choose a variety ---</option>{$sCvOpts}</select>
                        </p>
                        {$this->drawButton('cgosignup-form-tomatobutton', $bRegistered)}
                    </div>";
        } else {
                /* <b>Explorez et multipliez les semences de tomates canadiennes</b>
                   <p>Nos membres ont partag&eacute; un assortiment de semences de tomates canadiennes - des vari&eacute;t&eacute;s cultiv&eacute;es au Canada ou ayant une longue histoire d'&ecirc;tre bien adapt&eacute;es &agrave; nos conditions de culture.
                      Votre projet consistera &agrave; cultiver 6 plants de tomates ou plus, ...</p>
                 */
            $s1 = "<b>Explorez et multipliez les semences de tomates naines</b>
                   <p>Nous avons s&eacute;lectionn&eacute; un assortiment de vari&eacute;t&eacute;s de tomates naines, allant des ultra-compactes &agrave;
                      celles atteignant environ 90 cm de hauteur. Certaines sont des tomates cerises, d'autres sont de belles tomates &agrave; trancher,
                      mais toutes poussent sur des plants courts et compacts, parfaits pour les petits jardins ou la culture en pot.</p>
                   <p>Aucun tuteur n'est n&eacute;cessaire. Toutes sont relativement rares.</p>
                   <p>Votre projet consistera &agrave; cultiver 6 plants de tomates ou plus, &agrave; faire des observations tout au long de la saison et &agrave; conserver des graines pour aider &agrave; partager ces vari&eacute;t&eacute;s l'ann&eacute;e prochaine.
                      Nous esp&eacute;rons &eacute;galement que vous partagerez des graines avec d'autres membres de votre communaut&eacute;.</p>
                   <p>Pour en savoir plus sur les vari&eacute;t&eacute;s de tomates naines, consultez <a href='https://www.dwarftomatoproject.net'>Dwarf Tomato Project</a></p>
                   <div style='border:1px solid #aaa;background-color:#eee;padding:1em'>
                      <p><b>Pour participer &agrave ce projet:</b></p>
                         <ul>
                         <li><b>Vous aurez besoin d'au moins 20 pieds carr&eacute;s d'espace de jardin pour faire pousser 6 plants de tomates ou plus espac&eacute;s de 1,5 pied.</b></li>
                         <li><b>Vous pouvez cultiver vos tomates isol&eacute;es &agrave; au moins 20 pieds de toute autre vari&eacute;t&eacute; de tomates.</b></li>
                         <li><b>Vous aurez besoin d'un endroit chaud et lumineux &agrave; l'int&eacute;rieur pour d&eacute;marrer les semis, pendant 6 &agrave; 8 semaines avant de les planter dans le jardin.</b></li>
                         </ul>
                      {$this->sReserve['FR']}
                    </div>";
            $s2 = "<p><b>Pour participer &agrave ce projet:</b></p>
                      <ul>
                      <li>Vous ferez germer les semences &agrave; l'int&eacute;rieur, en mars-avril.</li>
                      <li>Transplantez au moins 6 plants dans votre jardin apr&egrave;s tout risque de gel, <b>&agrave; au moins 20 pieds de distance</b> de toute autre vari&eacute;t&eacute; de tomates.</li>
                      <li>En &eacute;t&eacute;, faites des observations et des photos.</li>
                      <li>R&eacute;coltez et d&eacute;gustez les tomates, et conservez les semences d'au moins quelques fruits de chaque plante.</li>
                      <li>Renvoyez une partie de vos semences &agrave; Semences du patrimoine afin que nous puissions r&eacute;p&eacute;ter le processus l'ann&eacute;e prochaine.</li>
                      <li>Partagez des semences dans votre communaut&eacute;.</li>
                      </ul>
                   <hr style='border-color:#888'/>
                   <p style='font-weight:bold;font-size:150%'>Vari&eacute;t&eacute;s disponibles</p>"
                  .$sCvAvailable;
            $sDisabled = $bRegistered ? 'disabled' : '';
            $s3 =  "<div class='cgosignup-form' data-project='cgo2026tomato'>
                        <p>Veuillez choisir une vari&eacute;t&eacute; ci-dessous et confirmer:<br/>
                            <input type='checkbox' id='cgosignup-form-tomato1' value='1' onchange='CGOSignup_Tomato.doValidate()'/> J'ai au moins 20 pieds carr&eacute;s d'espace de jardin pour ce projet.<br/>
                            <input type='checkbox' id='cgosignup-form-tomato2' value='1' onchange='CGOSignup_Tomato.doValidate()'/> Je peux isoler les plants de tomates &agrave; au moins 20 pieds de toute autre vari&eacute;t&eacute; de tomates.<br/>
                            <input type='checkbox' id='cgosignup-form-tomato3' value='1' onchange='CGOSignup_Tomato.doValidate()'/> Je peux faire germer des semences et faire pousser des plants &agrave; l'int&eacute;rieur pendant 6 &agrave; 8 semaines.<br/>
                            <select id='cgosignup-form-tomatoselect' {$sDisabled} onchange='CGOSignup_Tomato.doValidate()'><option value='0'>--- {$this->oP->oL->S('Choose a variety')} ---</option>{$sCvOpts}</select>
                        </p>
                        {$this->drawButton('cgosignup-form-tomatobutton', $bRegistered)}
                    </div>";
        }

        $sImg = "https://seeds.ca/d?n=ebulletin/2024/08-tomato_Earlirouge_03_bob_r.jpg";

        return( $this->DrawSignupBox($s1, $s2, $s3, $sImg));
    }

    private function tomatoVarieties()
    {                                                    // new seed
        $raCv = [//9576 => 130,       // Andy's Buckflats     13g  per
                 //8819 => 8,         // Adelin
                 //6957 => 3 + 100,   // Beaverlodge          10g             -- not accessioned
                 //7955 => 54 + 80,   // Betty's               8g  9417
                 //9120 => 1,         // Centennial Red
                 //7816 => 1,         // Charlie's Red Staker
                 9602 => 20,          // Coastal Pride Red     8g  per
                 //8273 => 16,        // Cobourg
                 //7361 => 14 + 49,   // Doucet's QEM         bob  9467
                 //6981 => 5,         // Earlibright
                 //6983 => 52,        // Early Lethbridge
                 //7215 => 2 + 90,    // Mac Pink              9g  9564
                 //7658 => 3 + 40,    // Manitoba              4g             -- not accessioned
                 //6994 => 52,        // Mennonite Orange
                 //9501 => 20,        // Montreal Tasty        2g  per
                 9465 => 20,          // Petitbec
                 //7211 => 3,         // Quebec 5
                 //9231 => 3,         // Quinte
                 9586 => 20,          // Scotia                3g             -- not accessioned
                 9221 => 20,          // Sub-arctic Cherry
                 //8468 => 2,         // Sub-arctic Maxi
                 //9239 => 1 + 20,    // Superbec              2g 9239        -- guessing 9239 from 2023

// g = 500 seeds = 10 pkts but really 20
        ];

        $s = "";
        $raOpts = [];

        $raY = $raN = [];
$n = 0;
        $oSLDB = new SLDBCollection($this->oP->oApp);
        foreach($raCv as $kLot => $nPackets) {
            if( ($kfrLot = $oSLDB->GetKFRCond('IxAxP', "fk_sl_collection=1 AND inv_number=$kLot")) ) {
                $nAssigned = $this->oP->oProfilesDB->GetCount('VI', "fk_sl_inventory='{$kfrLot->Key()}'");
                if( $nAssigned < $nPackets ) {
                    $raY[] = ['P_name'=>$kfrLot->Value('P_name'), 'P_packetLabel'=>$kfrLot->Value('P_packetLabel'),
                              'kLot'=>$kLot, 'nRemaining'=>($nPackets-$nAssigned), 'nPackets'=>$nPackets];
                    $raOpts[$kfrLot->Value('P_name')] = $kLot;
                } else {
                    $raN[] = ['P_name'=>$kfrLot->Value('P_name'), 'P_packetLabel'=>$kfrLot->Value('P_packetLabel'),
                              'kLot'=>$kLot, 'nPackets'=>$nPackets];
                }
            }
$n+=$nPackets;
        }

        foreach($raY as $ra) {
            $sRemaining = $this->oP->CanReadOtherUsers() ? " ({$ra['nRemaining']} / {$ra['nPackets']} left)" : "";
            $s .= SEEDCore_ArrayExpand($ra, "<div><b>[[P_name]]         </b> {$sRemaining}<br/>[[P_packetLabel]]</div>");
//          $s .= SEEDCore_ArrayExpand($ra, "<div><b>[[P_name]] [[kLot]]</b> {$sRemaining}<br/>[[P_packetLabel]]</div>");
        }
        $s .= "<h4 style='margin-top:2em'>{$this->oP->oL->S('Sorry no longer available')}</h4>";
        foreach($raN as $ra) {
            $sAssigned = $this->oP->CanReadOtherUsers() ? " ({$ra['nPackets']} assigned)" : "";
            $s .= SEEDCore_ArrayExpand($ra, "<div style='color:gray'><b>[[P_name]]</b> {$sAssigned}<br/>[[P_packetLabel]]</div>");
        }

if($this->oP->CanReadOtherUsers())  $s .= "<p>$n packets available</p>";
        return([$s,$raOpts]);
    }
}

class CGOSignup_Bean extends CGOSignup
{
    function __construct(ProjectsCommon $oP)
    {
        parent::__construct($oP);
    }

    function Draw( bool $bRegistered )
    /*********************************
        For signing up but not choosing seeds
     */
    {
        /* Sign up for CGO Beans
         */
        list($sCvAvailable,$raCvOpts) = $this->beanVarieties();
        $sCvOpts = SEEDCore_ArrayExpandSeries($raCvOpts, "<option value='[[v]]'>[[k]]</option>");

        if( $this->oP->oL->GetLang() == 'EN' ) {
            $s1 = "<b>Help Evaluate Beans for Canadian Climates</b>
                   <p>With Dr. Richard Hebda of the University of Victoria, we've chosen several heritage bean varieties that we think hold promise to thrive in Canadian growing conditions.
                      We're looking for gardeners who can grow some beans, take detailed notes during the growing season, and save seeds so we can continue this program next year.</p>
                   <div style='border:1px solid #aaa;background-color:#eee;padding:1em'>
                      <p><b>To participate in this project:</b></p>
                         <ul>
                         <li><b>You will need space to grow 15 to 20 feet of bean plants (you can choose bush or pole varieties).</b></li>
                         <li><b>You are able grow your beans isolated at least 20 feet apart from any other bean varieties.</b></li>
                         </ul>
                      {$this->sReserve['EN']}
                   </div>";
            $s2 = "<p><b>To participate in this project:</b></p>
                      <ul>
                      <li>You will sow your bean seeds in your garden after all danger of frost, <b>at least 20 feet apart</b> from any other bean variety.</li>
                      <li>As the plants grow, take observations and photos.</li>
                      <li>Harvest and enjoy the beans, and save seeds from at least a few pods from each plant.</li>
                      <li>Send some of your saved seeds back to Seeds of Diversity so we can repeat the process next year.</li>
                      <li>Share some of the seeds in your community.</li>
                      </ul>
                   <hr style='border-color:#888'/>
                   <p style='font-weight:bold;font-size:150%'>Varieties Available</p>"
                  .$sCvAvailable;
            $sDisabled = $bRegistered ? 'disabled' : '';
            $s3 = "<div class='cgosignup-form' data-project='cgo2026bean'>
                        <p>Please choose a variety below and confirm:<br/>
                            <input type='checkbox' id='cgosignup-form-bean1' value='1' onchange='CGOSignup_Bean.doValidate()'/> I have at least 15 row-feet of garden space for this project.<br/>
                            <input type='checkbox' id='cgosignup-form-bean2' value='1' onchange='CGOSignup_Bean.doValidate()'/> I can isolate bean plants at least 20 feet apart from any other bean variety.<br/>
                            <select id='cgosignup-form-beanselect' {$sDisabled}        onchange='CGOSignup_Bean.doValidate()'><option value='0'>--- {$this->oP->oL->S('Choose a variety')} ---</option>{$sCvOpts}</select>
                        </p>
                        {$this->drawButton('cgosignup-form-beanbutton', $bRegistered)}
                    </div>";
        } else {
            $s1 = "<b>Testez les haricots pour les climats canadiens</b>
                   <p>Avec Dr. Richard Hebda de l'Universit&eacute; de Victoria, nous avons choisi plusieurs vari&eacute;t&eacute;s de haricots patrimoniales qui, selon nous, sont prometteuses pour prosp&eacute;rer dans les conditions de culture canadiennes.
                      Nous recherchons des jardiniers capables de cultiver des haricots, de prendre des notes d&eacute;taill&eacute;es pendant la saison de croissance et de conserver les semences afin que nous puissions poursuivre ce programme l'ann&eacute;e prochaine.</p>
                   <div style='border:1px solid #aaa;background-color:#eee;padding:1em'>
                      <p><b>Pour participer &agrave ce projet:</b></p>
                         <ul>
                         <li><b>Vous aurez besoin d'espace pour cultiver 15 &agrave; 20 pieds de haricots (vous pouvez choisir des vari&eacute;t&eacute;s nains ou grimpants).</b></li>
                         <li><b>Vous pouvez cultiver vos haricots isol&eacute;es &agrave; au moins 20 pieds de toute autre vari&eacute;t&eacute; de haricots.</b></li>
                         </ul>
                      {$this->sReserve['FR']}
                   </div>";
            $s2 = "<p><b>Pour participer &agrave ce projet:</b></p>
                      <ul>
                      <li>Vous semerez vos semences de haricot dans votre jardin apr&egrave;s tout risque de gel, <b>&agrave; au moins 20 pieds de distance</b> de toute autre vari&eacute;t&eacute; de haricot.</li>
                      <li>En &eacute;t&eacute;, faites des observations et des photos.</li>
                      <li>R&eacute;coltez et savourez les haricots, et conservez les semences d'au moins quelques gousses de chaque plante.</li>
                      <li>Renvoyez une partie de vos semences &agrave; Semences du patrimoine afin que nous puissions r&eacute;p&eacute;ter le processus l'ann&eacute;e prochaine.</li>
                      <li>Partagez des semences dans votre communaut&eacute;.</li>
                      </ul>";
            $s3 = "<br/><br/><br/>
                    <div class='cgosignup-form' data-project='cgo2026bean'>
                        <p>Veuillez confirmer:<br/>
                            <input type='checkbox' id='cgosignup-form-bean1' value='1' onchange='CGOSignup_Bean.doValidate()'/> J'ai au moins 15 pieds rang&eacute;es d'espace de jardin pour ce projet.<br/>
                            <input type='checkbox' id='cgosignup-form-bean2' value='1' onchange='CGOSignup_Bean.doValidate()'/> Je peux isoler les plants de haricots &agrave; au moins 20 pieds de toute autre vari&eacute;t&eacute; de haricots.<br/>
                            <select id='cgosignup-form-beanselect' {$sDisabled}        onchange='CGOSignup_Bean.doValidate()'><option value='0'>--- {$this->oP->oL->S('Choose a variety')} ---</option>{$sCvOpts}</select>
                            <br/>Nous vous contacterons courant mars pour vous laisser choisir votre vari&eacute;t&eacute; de haricot (buisson/poteau, climat chaud/frais)
                        </p>
                        {$this->drawButton('cgosignup-form-beanbutton', $bRegistered)}
                    </div>";
        }

        $sImg = "https://seeds.ca/d?n=ebulletin/2024/11-odawa-rotated.jpg";

        return( $this->DrawSignupBox($s1, $s2, $s3, $sImg));
    }

    function Draw2()
    /***************
        For choosing seeds after signing up
     */
    {
/*
        list($sCvAvailable,$raCvOpts) = $this->beanVarieties();
        $sCvOpts = SEEDCore_ArrayExpandSeries($raCvOpts, "<option value='[[v]]'>[[k]]</option>");

        [* Choose CGO Beans
         *]
        if( $this->oP->oL->GetLang() == 'EN' ) {
            $s1 = "<h4>You've registered for our Beans for Canadian Climates project - It's time to choose your seeds!</h4>

                   <b>Help Evaluate Beans for Canadian Climates</b>
                   <p>With Dr. Richard Hebda of the University of Victoria, we've chosen several heritage bean varieties that we think hold promise to thrive in Canadian growing conditions.
                      We're looking for gardeners who can grow some beans, take detailed notes during the growing season, and save seeds so we can continue this program next year.</p>
                   <div style='border:1px solid #aaa;background-color:#eee;padding:1em'>
                      <p><b>To participate in this project:</b></p>
                         <ul>
                         <li><b>You will need space to grow 15 to 20 feet of bean plants (you can choose bush or pole varieties).</b></li>
                         <li><b>You are able grow your beans isolated at least 20 feet apart from any other bean varieties.</b></li>
                         </ul>
<!--                      <p style='color:red;margin-top:1em'><b>Reserve your space now, and choose your seeds in March.</b></p>   -->
                   </div>";
            $s2 = "<p><b>To participate in this project:</b></p>
                      <ul>
                      <li>You will sow your bean seeds in your garden after all danger of frost, <b>at least 20 feet apart</b> from any other bean variety.</li>
                      <li>As the plants grow, take observations and photos.</li>
                      <li>Harvest and enjoy the beans, and save seeds from at least a few pods from each plant.</li>
                      <li>Send some of your saved seeds back to Seeds of Diversity so we can repeat the process next year.</li>
                      <li>Share some of the seeds in your community.</li>
                      </ul>
                   <hr style='border-color:#888'/>
                   <p style='font-weight:bold;font-size:150%'>Varieties Available</p>"
                  .$sCvAvailable;
            $s3 = "<br/><br/><br/>
                    <div class='cgosignup-form' data-project='cgo2025bean'>
                        <p>Please choose your bean variety:<br/>
                            <select id='cgosignup-form-beanselect' ><option value='0'>--- {$this->oP->oL->S('Choose a variety')} ---</option>{$sCvOpts}</select>
                            <button onclick='CGOSignup.doChooseBean($(this))'>Choose this bean</button>
                        </p>
                    </div>";
        } else {
            $s1 = "<h4>You've registered for our Beans for Canadian Climates project - It's time to choose your seeds!</h4>

                   <b>Testez les haricots pour les climats canadiens</b>
                   <p>Avec Dr. Richard Hebda de l'Universit&eacute; de Victoria, nous avons choisi plusieurs vari&eacute;t&eacute;s de haricots patrimoniales qui, selon nous, sont prometteuses pour prosp&eacute;rer dans les conditions de culture canadiennes.
                      Nous recherchons des jardiniers capables de cultiver des haricots, de prendre des notes d&eacute;taill&eacute;es pendant la saison de croissance et de conserver les semences afin que nous puissions poursuivre ce programme l'ann&eacute;e prochaine.</p>
                   <div style='border:1px solid #aaa;background-color:#eee;padding:1em'>
                      <p><b>Pour participer &agrave ce projet:</b></p>
                         <ul>
                         <li><b>Vous aurez besoin d'espace pour cultiver 15 &agrave; 20 pieds de haricots (vous pouvez choisir des vari&eacute;t&eacute;s nains ou grimpants).</b></li>
                         <li><b>Vous pouvez cultiver vos haricots isol&eacute;es &agrave; au moins 20 pieds de toute autre vari&eacute;t&eacute; de haricots.</b></li>
                         </ul>
<!--                      <p style='color:red;margin-top:1em'><b>R&eacute;servez votre projet maintenant, et choisissez vos semences en mars.</b></p>   -->
                   </div>";
            $s2 = "<p><b>Pour participer &agrave ce projet:</b></p>
                      <ul>
                      <li>Vous semerez vos semences de haricot dans votre jardin apr&egrave;s tout risque de gel, <b>&agrave; au moins 20 pieds de distance</b> de toute autre vari&eacute;t&eacute; de haricot.</li>
                      <li>En &eacute;t&eacute;, faites des observations et des photos.</li>
                      <li>R&eacute;coltez et savourez les haricots, et conservez les semences d'au moins quelques gousses de chaque plante.</li>
                      <li>Renvoyez une partie de vos semences &agrave; Semences du patrimoine afin que nous puissions r&eacute;p&eacute;ter le processus l'ann&eacute;e prochaine.</li>
                      <li>Partagez des semences dans votre communaut&eacute;.</li>
                      </ul>                   <hr style='border-color:#888'/>
                   <p style='font-weight:bold;font-size:150%'>Vari&eacute;t&eacute;s disponibles</p>"
                  .$sCvAvailable;
            $s3 = "<br/><br/><br/>
                    <div class='cgosignup-form' data-project='cgo2025bean'>
                        <p>Veuillez choisir une vari&eacute;t&eacute;:<br/>
                            <select id='cgosignup-form-beanselect' ><option value='0'>--- {$this->oP->oL->S('Choose a variety')} ---</option>{$sCvOpts}</select>
                            <button onclick='CGOSignup.doChooseBean($(this))'>Choose this bean</button>
                        </p>
                    </div>";
        }

        $sImg = "https://seeds.ca/d?n=ebulletin/2024/11-odawa-rotated.jpg";

        return( $this->DrawSignupBox($s1, $s2, $s3, $sImg));
*/
    }

    private function beanVarieties()
    {
        $raBeans = [
            ['cat'  => "Bush varieties, Cool climate",
             'raCV' => [
                 9676 => intval(287.0 / 30.0 * 100 / 25),  // Blue Jay
                 9682 => 100,                              // Doukhobor  (amount is a guess)
                 9678 => intval(206.0 / 42.0 * 100 / 25),  // Drew's Dandy
             ]],
            ['cat'  => "Bush varieties, Hot climate",
             'raCV' => [
                 9680 => intval(502.0 / 37.0 * 100 / 25),  // Mayocoba
                 9394 => 75,                               // Rojo de Seda
                 9173 => intval(614.0 / 23.0 * 100 / 25),  // Xico
             ]],

            ['cat'  => "Pole varieties, Cool climate",
             'raCV' => [
                 9677 => intval(261.0 / 56.0 * 100 / 25),  // Pezel's Giant
//                 9679 => intval(169.0 / 47.0 * 100 / 25),  // Polish White
             ]],
            ['cat'  => "Pole varieties, Hot climate",
             'raCV' => [
                 9681 => intval(773.0 / 51.0 * 100 / 25),  // Good Mother Stallard
             ]]
        ];

        $s = "";
        $raOpts = [];

        $raY = $raN = [];
$n = 0;
        $oSLDB = new SLDBCollection($this->oP->oApp);
        foreach($raBeans as $raBeanCat ) {
            if(!isset($raY[$raBeanCat['cat']])) $raY[$raBeanCat['cat']] = [];
            if(!isset($raN[$raBeanCat['cat']])) $raN[$raBeanCat['cat']] = [];
            foreach($raBeanCat['raCV'] as $kLot => $nPackets) {
                if( ($kfrLot = $oSLDB->GetKFRCond('IxAxP', "fk_sl_collection=1 AND inv_number=$kLot")) ) {
                    $nAssigned = $this->oP->oProfilesDB->GetCount('VI', "fk_sl_inventory='{$kfrLot->Key()}'");
                    if( $nAssigned < $nPackets ) {
                        $raY[$raBeanCat['cat']][] =
                                 ['P_name'=>$kfrLot->Value('P_name'), 'P_packetLabel'=>$kfrLot->Value('P_packetLabel'),
                                  'kLot'=>$kLot, 'nRemaining'=>($nPackets-$nAssigned), 'nPackets'=>$nPackets];
                        $raOpts[$kfrLot->Value('P_name')] = $kLot;
                    } else {
                        $raN[$raBeanCat['cat']][] =
                                 ['P_name'=>$kfrLot->Value('P_name'), 'P_packetLabel'=>$kfrLot->Value('P_packetLabel'),
                                  'kLot'=>$kLot, 'nPackets'=>$nPackets];
                    }
                }
    $n+=$nPackets;
            }
        }

        foreach($raY as $catLabel => $raY2) {
            $s .= "<p><b>{$catLabel}</b></p>";
            foreach($raY2 as $ra) {
                $sRemaining = $this->oP->CanReadOtherUsers() ? " ({$ra['nRemaining']} / {$ra['nPackets']} left)" : "";
                $s .= SEEDCore_ArrayExpand($ra, "<div style='margin-left:3em'><b>[[P_name]]         </b> {$sRemaining}<br/>[[P_packetLabel]]</div>");
            }
        }
        $s .= "<h4 style='margin-top:2em'>{$this->oP->oL->S('Sorry no longer available')}</h4>";
        foreach($raN as $catLabel => $raN2) {
            $s .= "<p style='color:gray'><b>{$catLabel}</b></p>";
            foreach($raN2 as $ra) {
                $sAssigned = $this->oP->CanReadOtherUsers() ? " ({$ra['nPackets']} assigned)" : "";
                $s .= SEEDCore_ArrayExpand($ra, "<div style='color:gray;margin-left:3em'><b>[[P_name]]</b> {$sAssigned}<br/>[[P_packetLabel]]</div>");
            }
        }

if($this->oP->CanReadOtherUsers())  $s .= "<p>$n packets available</p>";
        return([$s,$raOpts]);
    }

}
