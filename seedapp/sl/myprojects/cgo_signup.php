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
                           More <br/><span class='chevron chevron-down'></span>
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
}

class CGOSignup_GC extends CGOSignup
{
    function __construct(ProjectsCommon $oP)
    {
        parent::__construct($oP);
    }

    function Draw()
    {
        /* Sign up for CGO Ground Cherry
         */
        $s1 = "<b>Help Breed a Better Ground Cherry (Year 6)</b>
               <p>We're selecting upright-growing plants from an originally mixed population, aiming for a plant shape that is easier to harvest.</p>
               <p>Ground cherries (<i>Physalis pruinosa</i>) are small, sweet, golden-yellow berries that are relatives of tomatillos and tomatoes.
                  Each fruit is enclosed in a wrapper, and it falls from the plant when ripe, so you harvest by picking them off the ground.
                  Since low-growing branches make the berries hard to see, we're breeding for upright branches and good flavour.</p>
               <p>Your job will be to grow at least 15 plants, pull out any low-growing plants, and save seeds from the tall-growing plants so we can continue the process next year.</p>
               <div style='border:1px solid #aaa;background-color:#eee;padding:1em'>
                  <p><b>To participate in this project:</b></p>
                     <ul>
                     <li><b>You will need about 30 to 40 square feet of garden space.</b></li>
                     <li><b>You will need space indoors to start seedlings at 20-25 degrees C, for 8 weeks before transplanting.</b></li>
                     </ul>
                  <p style='color:red;margin-top:1em'><b>Reserve your seeds by March 1, 2025</b></p>
               </div>";
        $s2 = "<p><b>To participate in this project:</b></p>
                  <ul>
                  <li>You will germinate the seeds indoors, starting in March. Note that germination can be slow, and is speeded greatly by warmth.</li>
                  <li>Transplant as many seedlings as you can to your garden after frost. 15-20 would be a good range to aim for.</li>
                  <li>As the plants grow, uproot the plants that grow flat on the ground prior to flowering, to prevent them from crossing with the tall-bearing plants.</li>
                  <li>Taste the berries and keep seeds only from those with excellent flavour.</li>
                  <li>Send some of your saved seeds back to Seeds of Diversity so we can repeat the process next year.</li>
                  <li>Share some of the seeds in your community.</li>
                  </ul>";
        $s3 = "<br/><br/><br/>
                  <form id='cgosignup-form-gc' class='cgosignup-form'>
                  <p>Please confirm:<br/>
                  <input type='checkbox' id='cgosignup-form-gc1' value='1' onchange='CGOSignup_GroundCherry.doValidate()'/> I have at least 30 square feet of garden space for this project.<br/>
                  <input type='checkbox' id='cgosignup-form-gc2' value='1' onchange='CGOSignup_GroundCherry.doValidate()'/> I can germinate seeds at 20 - 25 degrees C, and grow seedlings indoors for 8 weeks.
                  </p>
                  <div id='cgosignup-form-btn-container'>
                    <button class='cgosignup-form-button' id='cgosignup-form-gcbutton' disabled onclick='CGOSignup_GroundCherry.doSubmit(event)'>Sign up for this project</button>
                  </div>
                  </form>";

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

    function Draw()
    {
        /* Sign up for CGO Tomato
         */
        list($sCvAvailable,$raCvOpts) = $this->tomatoVarieties();
        $sCvOpts = SEEDCore_ArrayExpandSeries($raCvOpts, "<option value='[[v]]'>[[k]]</option>");

        $s1 = "<b>Help Explore and Multiply Canadian Tomato Seeds</b>
               <p>Our members have shared an assortment of Canadian tomato seeds - varieties bred in Canada or with a long history of being well-adapted to our growing conditions.
                  Your job will be to grow 6 or more tomato plants, take observations through the season, and save seeds to help share those varieties next year.
                  We also hope that you'll share seeds with others in your community.</p>
               <div style='border:1px solid #aaa;background-color:#eee;padding:1em'>
                  <p><b>To participate in this project:</b></p>
                     <ul>
                     <li><b>You will need about 20 square feet of garden space.</b></li>
                     <li><b>You are able grow your tomatoes isolated at least 20 feet apart from any other tomato varieties.</b></li>
                     <li><b>You will need a warm, bright place indoors to start seedlings, for 6-8 weeks before transplanting.</b></li>
                     </ul>
                  <p style='color:red;margin-top:1em'><b>Reserve your seeds by March 1, 2025</b></p>
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
        $s3 = "<form id='cgosignup-form-tomato' class='cgosignup-form'>
                  <p>Please choose a variety below and confirm:<br/>
                      <input type='checkbox' id='cgosignup-form-tomato1' value='1' onchange='CGOSignup_Tomato.doValidate()'/> I have at least 20 square feet of garden space for this project.<br/>
                      <input type='checkbox' id='cgosignup-form-tomato2' value='1' onchange='CGOSignup_Tomato.doValidate()'/> I can isolate tomato plants at least 20 feet apart from any other tomato variety.<br/>
                      <input type='checkbox' id='cgosignup-form-tomato3' value='1' onchange='CGOSignup_Tomato.doValidate()'/> I can germinate seeds and grow seedlings indoors for 6-8 weeks.<br/>
                      <select id='cgosignup-form-tomatoselect' onchange='CGOSignup_Tomato.doValidate()'><option value='0'>--- Choose a variety ---</option>{$sCvOpts}</select>
                  </p>
                  <div class='cgosignup-form-btn-container'>
                      <button class='cgosignup-form-button' id='cgosignup-form-tomatobutton' disabled onclick='CGOSignup_Tomato.doSubmit(event, $(this))'>Sign up for this project</button>
                  </div>
                  </form>";

        $sImg = "https://seeds.ca/d?n=ebulletin/2024/08-tomato_Earlirouge_03_bob_r.jpg";

        return( $this->DrawSignupBox($s1, $s2, $s3, $sImg));
    }

    private function tomatoVarieties()
    {
        $raCv = [8819 => 8,
                 6957 => 3,
                 7955 => 54,
                 9120 => 1,
                 7816 => 1,
                 8273 => 16,
                 7361 => 14 + 49,    // plus
                 6981 => 5,
                 6983 => 52,
                 7215 => 2,
                 7658 => 3,
                 6994 => 52,
                 7085 => 7 + 48,     // plus
                 7211 => 3,
                 9231 => 3,
                 7612 => 7,
                 9239 => 1,
                 8468 => 2,
                 9221 => 2
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
            $s .= SEEDCore_ArrayExpand($ra, "<div><b>[[P_name]] [[kLot]]</b> {$sRemaining}<br/>[[P_packetLabel]]</div>");
        }
        $s .= "<h4 style='margin-top:2em'>Sorry no longer available</h4>";
        foreach($raN as $ra) {
            $sAssigned = $this->oP->CanReadOtherUsers() ? " ({$ra['nPackets']} assigned)" : "";
            $s .= SEEDCore_ArrayExpand($ra, "<div style='color:gray'><b>[[P_name]]</b> {$sAssigned}<br/>[[P_packetLabel]]</div>");
        }

$s .= "<p>$n packets available</p>";
        return([$s,$raOpts]);
    }
}

class CGOSignup_Bean extends CGOSignup
{
    function __construct(ProjectsCommon $oP)
    {
        parent::__construct($oP);
    }

    function Draw()
    {
        /* Sign up for CGO Beans
         */
        $s1 = "<b>Help Evaluate Beans in Your Climate Region</b>
               <p>With Richard Hebda of the University of Victoria, we've selected several bean varieties that we think hold promise to thrive in Canadian growing conditions.
                  We're looking for gardeners who can grow some beans, take detailed notes during the growing season, and save seeds so we can continue this program next year.</p>
               <div style='border:1px solid #aaa;background-color:#eee;padding:1em'>
                  <p><b>To participate in this project:</b></p>
                     <ul>
                     <li><b>You will need space to grow 15 to 20 feet of beans (you can choose bush or pole varieties).</b></li>
                     <li><b>You are able grow your beans isolated at least 20 feet apart from any other bean varieties.</b></li>
                     </ul>
                  <p style='color:red;margin-top:1em'><b>Reserve your space now, and choose your seeds in March.</b></p>
               </div>";
        $s2 = "<p><b>To participate in this project:</b></p>
                  <ul>
                  <li>You will sow your bean seeds in your garden after all danger of frost, <b>at least 20 feet apart</b> from any other bean variety.</li>
                  <li>As the plants grow, take observations and photos.</li>
                  <li>Harvest and enjoy the beans, and save seeds from at least a few pods from each plant.</li>
                  <li>Send some of your saved seeds back to Seeds of Diversity so we can repeat the process next year.</li>
                  <li>Share some of the seeds in your community.</li>
                  </ul>";
        $s3 = "<br/><br/><br/>
                  <form id='cgosignup-form-bean' class='cgosignup-form'>
                  <p>Please confirm:<br/>
                  <input type='checkbox' id='cgosignup-form-bean1' value='1' onchange='CGOSignup_Bean.doValidate()'/> I have at least 15 row-feet of garden space for this project.<br/>
                  <input type='checkbox' id='cgosignup-form-bean2' value='1' onchange='CGOSignup_Bean.doValidate()'/> I can isolate bean plants at least 20 feet apart from any other bean variety.<br/>
                  <br/>We'll follow up in March to let you choose your bean variety (bush / pole, hot / cool climate)
                  </p>
                  <div id='cgosignup-form-btn-container'>
                    <button class='cgosignup-form-button' id='cgosignup-form-beanbutton' disabled onclick='CGOSignup_Bean.doSubmit(event)'>Sign up for this project</button>
                  </div>
                  </form>";

        $sImg = "https://seeds.ca/d?n=ebulletin/2024/11-odawa-rotated.jpg";

        return( $this->DrawSignupBox($s1, $s2, $s3, $sImg));
    }
}
