<?php

class CGOSignup
{
    function __construct() {}

    function DrawSignupBox( string $s1, string $s2, string $s3, string $sImg )
    {
        $s = "<div class='cgosignup_box container-fluid'><div class='row'>
                   <div class='col-md-2'><img src='$sImg' style='width:100%'/></div>
                   <div class='col-md-8'>$s1</div>
                   <div class='col-md-2 cgosignup_opener' style='text-align:center'>V</div>
               </div><div class='row' style='display:none'>
                   <div class='col-md-2'>&nbsp;</div>
                   <div class='col-md-6'><hr style='border-color:#888'/>$s2</div>
                   <div class='col-md-4'>$s3</div>
               </div></div>";

        return($s);
    }
}

class CGOSignup_GC extends CGOSignup
{
    function __construct()
    {
        parent::__construct();
    }

    function Draw()
    {
        /* Sign up for CGO
         */
        $s1 = "<b>Help Breed a Better Ground Cherry (Year 6)</b>
               <p>We're selecting upright-growing plants from an originally mixed population, aiming for a plant shape that is easier to harvest.</p>
               <p>Ground cherries (<i>Physalis pruinosa</i>) are small, sweet, golden-yellow berries that are relatives of tomatillos and tomatoes.
                  Each fruit is enclosed in a wrapper, and it falls from the plant when ripe, so you harvest by picking them off the ground.
                  Since low-growing branches make the berries hard to see, we're breeding for upright branches and good flavour.</p>";
        $s2 = "<p><b>To participate in this project:</b></p>
                  <ul>
                  <li><b>You will need about 30 to 40 square feet of garden space</b></li>
                  <li><b>You will need space indoors to start seedlings at 20-25 degrees C, for 8 weeks before transplanting.</b></li>
                  <br/>
                  <li>You will germinate the seeds indoors, starting in March.</li>
                  <li>Transplant as many seedlings as you can to your garden after frost. 15-20 would be a good range to aim for.</li>
                  <li>As the plants grow, uproot the plants that grow flat on the ground prior to flowering, to prevent them from crossing with the tall-bearing plants.</li>
                  <li>Taste the berries and keep seeds only from those with excellent flavour.</li>
                  <li>Send some of your saved seeds back to Seeds of Diversity so we can repeat the process next year.</li>
                  <li>Share some of the seeds in your community.</li>
                  </ul>";
        $s3 = "<br/><br/><br/>
                  <form id='cgosignup_form_gc'>
                  <p>Please confirm:<br/>
                  <input type='checkbox' id='cgosignup_form_gc1' value='1' onchange='CGOSignup_GroundCherry.doRadio1()'/> I have at least 30 square feet of garden space for this project<br/>
                  <input type='checkbox' id='cgosignup_form_gc2' value='1' onchange='CGOSignup_GroundCherry.doRadio2()'/> I can germinate seeds at 20 - 25 degrees C, and grow seedlings indoors for 8 weeks
                  </p>
                  <button id='cgosignup_form_gc_join' disabled onclick='CGOSignup_GroundCherry.doSubmit(event)'>Sign up for this project</button>
                  </form>";

        $sImg = "";

        return( $this->DrawSignupBox($s1, $s2, $s3, $sImg));
    }
}

class CGOSignup_Tomato extends CGOSignup
{
    function __construct()
    {
        parent::__construct();
    }

    function Draw()
    {
        /* Sign up for CGO
         */
        $s1 = "<b>Help Explore and Multiply Canadian Tomato Seeds</b>
               <p>We've selected about a dozen Canadian tomatoes - varieties bred in Canada or with a long history of being well-adapted to our growing conditions.
                  Your job will be to grow 6 tomato plants, take observations through the season and save seeds. We hope you’ll send some seed back to the Seed Library
                  to refresh our supply, and share it far & wide.</p>";
        $s2 = "<p><b>To participate in this project:</b></p>
                  <ul>
                  <li><b>You will need about 20 square feet of garden space</b></li>
                  <li><b>You will need a warm, bright place indoors to start seedlings, for 6-8 weeks before transplanting.</b></li>
                  <br/>
                  <li>You will germinate the seeds indoors, starting in March.</li>
                  <li>Transplant at least 6 seedlings to your garden after frost.</li>
                  <li>As the plants grow, take observations and photos.</li>
                  <li>Harvest and enjoy the tomatoes, and save seeds from at least a few fruit from each plant.</li>
                  <li>Send some of your saved seeds back to Seeds of Diversity so we can repeat the process next year.</li>
                  <li>Share some of the seeds in your community.</li>
                  </ul>";
        $s3 = "<br/><br/><br/>
                  <form id='cgosignup_form_gc'>
                  <p>Please confirm:<br/>
                  <input type='checkbox' id='cgosignup_form_tomato1' value='1' onchange='CGOSignup_Tomato.doRadio1()'/> I have at least 20 square feet of garden space for this project<br/>
                  <input type='checkbox' id='cgosignup_form_tomato2' value='1' onchange='CGOSignup_Tomato.doRadio2()'/> I can germinate seeds and grow seedlings indoors for 6-8 weeks
                  <select name='cgosignup_form_tomatochoice'><option value='0'>--- Choose a variety ---</option></select>
                  </p>
                  <button id='cgosignup_form_gc_join' disabled onclick='CGOSignup_Tomato.doSubmit(event)'>Sign up for this project</button>
                  </form>";

        $sImg = "";

        return( $this->DrawSignupBox($s1, $s2, $s3, $sImg));
    }
}

