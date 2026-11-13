<?php

/* store2.php
 *
 * Copyright 2026 Seeds of Diversity Canada
 *
 * Second generation Seeds of Diversity store app
 */

include_once(SEEDCORE."SEEDCoreFormSession.php");
include_once(SEEDLIB."basket/store2_basket.php");

class Store2App
{
    private $oApp, $raConfig;
    private $p = [];    // dynamically filled because contains $this
    private $oForm, $oL, $oBasket;

    function __construct( SEEDAppConsole $oApp, array $raConfig )
    {
        $this->oApp = $oApp;
        $this->raConfig = $raConfig;
        $this->p = [
            'donation' => ['fn'=>[$this,'sectionDonation'], 'button'=>"Give Now", 'title'=>"Give a Charitable Donation"],
            'sladopt'  => ['fn'=>[$this,'sectionSLAdopt'],  'button'=>"Give Now", 'title'=>"Adopt a Variety into the Canadian Seed Library"],
            'se'       => ['fn'=>[$this,'sectionSE'],       'button'=>"Join Now", 'title'=>"Join the Seed Exchange"],
            'cgo'      => ['fn'=>[$this,'sectionCGO'],      'button'=>"Join Now", 'title'=>"Join our Seed Grow-out Projects"],
            'garlic'   => ['fn'=>[$this,'sectionGarlic'],   'button'=>"Buy Now",  'title'=>"Purchase Garlic Bulbils for Planting"],
            'book'     => ['fn'=>[$this,'sectionBook'],     'button'=>"Buy Now",  'title'=>"Buy a Book"],
            'member'   => ['fn'=>[$this,'sectionMember'],   'button'=>"Join Now", 'title'=>"Join our Membership"],
            'other'    => ['fn'=>[$this,'sectionOther'],    'button'=>"More",     'title'=>"Special Orders"],
        ];
        $this->oForm = new SEEDCoreFormSession($oApp->sess, 'store_basket', 'A');
        $this->oBasket = new Store2_Basket($oApp, $this->oForm);

        $this->oL = new SEED_Local( $this->sLocalStrs(),
                                    @$raConfig['lang'] ?: $this->oApp->lang,     // specify lang or use oApp's lang
                                    'store' );
    }

    function DrawApp()
    {
        $s = $this->oBasket->Style().$this->style();

        $sSection = SEEDInput_Str('p_section');
        if( isset($this->p[$sSection]) ) {
            /* Show a specific section sub-page
             */
            $s .= "<div class='store2_sect_major'>
                       <div class='store2_sect_major_header'>{$this->p[$sSection]['title']}</div>
                       <form method='post'>
                       {$this->p[$sSection]['fn']($sSection)}
                       <div><input type='submit' name='p_add' value='Continue'/></div>
                       <input type='hidden' name='p_add_section' value='$sSection'/>
                       </form>
                   </div>";
        } else {
            /* Show the main page
             */
            if( ($sAddSection = SEEDInput_Str('p_add_section')) ) {
                $this->oBasket->UpdateBasket();
//                $s .= "<div class='alert alert-success'>Evaluating $sAddSection</div>";
            }
            $s .= $this->sectionMain();
        }

        return($s);
    }

    private function sectionMain()
    {
        $s = "";

        if( $this->oBasket->HasContents() ) {
            $s .= "<form method='post'>
                   <div class='container-fluid'><div class='row'>
                   <div class='col-md-6'>
                       {$this->oBasket->DrawBasket(['bNotesEditable'=>true])}
                   </div>
                   <div class='col-md-6'>
                       <input type='submit' value='Confirm your order >>'/>
                   </div>
                   </div></div>
                   <input type='hidden' name='storepage' value='confirm'/>
                   </form>";
        }

        $s .= "<div class='container-fluid'><div class='row'>";
        foreach($this->p as $k=>$ra) {
            $s .= "<div class='col-md-3'>
                   <div class='store2_sect_minor'>
                       <div class='store2_sect_minor_header'>{$ra['title']}</div>
                       <div class='store2_sect_minor_body'>
                           <p>Foo</p>
                           <p><form method='post'><button>{$ra['button']}</button><input type='hidden' name='p_section' value='{$k}'/></form>
                       </div>
                   </div></div>";
        }
        $s .= "</div></div>";

        return($s);
    }

    private function sectionDonation()
    {
        $s = "";

        $fDonation = floatval($this->oForm->Value('donation')); // make a ValueFloat() method
        $raDon = [35, 75, 100];
        $bDonX = !in_array($fDonation, $raDon);     // using the type-your-own-number box
        $s .= "<div class='mbro_help'>{$this->oL->S('donation_desc')}</div>
               <div class='mbro_ctrl' style='margin:1em 0 0 2em'>"
                 .SEEDCore_ArrayExpandSeries( $raDon,
                                              function($k,$d) { return("{$this->oForm->Radio('donation',$d,'')}&nbsp;{$this->oL->Dollar($d)}<br/>" ); }
                                            )
// check the x radio button when someone starts typing in donationX; clear donationX when someone clicks another button
                 ."<input type='radio' style='display:inline' name='{$this->oForm->Name('donation')}' value=''".($bDonX ? " checked" : "")."/>&nbsp;
                   <input type='text' style='width:6em;display:inline' name='{$this->oForm->Name('donationX')}'
                                      value='".($bDonX ? $this->myNumber($fDonation) : "")."'/>
               </div>
               <div class='mbro_ctrl' style='margin-top:2em'>
                   <p>Use my donation for (choose as many as you want):</p>
                   <div style='margin-left:2em'>
                       {$this->oForm->Checkbox('donpref_sl',  "Seed conservation: rescuing and distributing rare seed varieties")}<br/>
                       {$this->oForm->Checkbox('donpref_yfs', "Youth in Food Systems: inspiring youth to become the sustainable food leaders of tomorrow")}<br/>
                       {$this->oForm->Checkbox('donpref_ss',  "Seedy Saturdays/Sundays: supporting local community seed events")}<br/>
                       {$this->oForm->Checkbox('donpref_any', "Where it's needed the most", ['checked'=>1])}
                   </div>
               </div>
               <!-- display:inline-block makes the div width no larger than the content width -->
               <div style='border:1px solid #777;margin-top:1em;padding:1em;display:inline-block;'>
                   <p>We also encourage donations through CanadaHelps, a secure and trusted donation platform.<br/>Please use CanadaHelps for instant donations, gifts of securities, and monthly donations.</p>
                   <p style='font-size:1.2em'><a target='_blank' href='https://www.canadahelps.org/en/charities/seeds-of-diversity-canada-programme-semencier-du-patrimoin/'>
                       <img style='height:3em' src='https://seeds.ca/d?n=www/home/ch-logo.png' border='0' alt='Donate Now Through CanadaHelps.org!'/> Go to CanadaHelps</a>
                   </p>
               </div>";

// kluge : after storing checkbox state in the controls, reset in the sess so back->uncheck->next will do the right thing
        $oSVar = new SEEDSessionVarAccessor( $this->oApp->sess, "mbrocdata" );
        foreach( ['donpref_sl','donpref_yfs','donpref_ss','donpref_any'] as $k ) {
            $oSVar->VarSet($k,0);
        }

        return($s);
    }

    private function myNumber($n) { return($n ? $n : ""); }

    private function sectionSLAdopt()
    {
        $s = "";

        $fAmount = floatval($this->oForm->Value('slAdopt_amount'));
        $raAdopt = array( 50, 100, 150, 200, 250 );
        $bAdoptX = !in_array($fAmount, $raAdopt);     // using the type-your-own-number box

        $s =  $this->oL->S("You can adopt etc")
             ."<table class='mbro_ctrl'><tr valign='top'>
               <td valign='top'>{$this->oL->S('Choose a variety to adopt')}</td>
               <td style='padding-left:15px'>{$this->oForm->Radio('slcvchoose','as_needed','')} {$this->oL->S('as needed')}<br/>
                                     {$this->oForm->Radio('slcvchoose','','')} {$this->oForm->Text('slAdopt_cv',"",array("size"=>15))}&nbsp;&nbsp;&nbsp;<A href='https://seeds.ca/diversity/seed-library/list' target='_blank'>See Varieties Here</A>
               </td></tr>

               <tr><td colspan='2'><hr/></td></tr>

               <tr>
               <td valign='top' style='padding-bottom:1em'>{$this->oL->S("Full adoption")}</td>
               <td style='padding-left:15px'>{$this->oForm->Radio("slAdopt_amount", 250, '')} {$this->oL->Dollar(250)}</td>
               </tr>"
/*
               <tr>
               <td>{$this->oL->S("Partial adoption")}</td><td>";

        $s .= "<table border='0' cellspacing='0' cellpadding='10'><tr>";
        foreach( $raAdopt as $d ) {
            if( $d == 250 ) continue;
            $s .= "<td style='padding-left:15px;'>".$this->oForm->Radio("slAdopt_amount",$d,'').$this->oL->Dollar($d)."</td>";
        }
        $s .= "<td style='padding-left:15px'><input type='radio' name='".$this->oForm->Name('slAdopt_amount')."' value='X'".($bAdoptX ? " checked" : "")."/>"
             ."<INPUT type='text' size='8' name='".$this->oForm->Name('slAdopt_amountX')."' value='".($bAdoptX ? $this->myNumber($this->oForm->ValueEnt('slAdopt_amountX')) : "")."'/></td>"
             ."</tr></table>"
             ."</td></tr>
*/

             ."<tr><td valign='top'>{$this->oL->S("Partial adoption")}</td>
                   <td style='padding-left:15px'>"
                   .SEEDCore_ArrayExpandSeries( $raAdopt,
                                                function($k,$d) { return($d<>250 ? "{$this->oForm->Radio('slAdopt_amount',$d,'')}&nbsp;{$this->oL->Dollar($d)}<br/>" : "" ); }
                                              )
."
               <input type='radio' name='{$this->oForm->Name('slAdopt_amount')}' value=''".($bAdoptX ? " checked" : "")."/>&nbsp;
               <input type='text' size='8' name='{$this->oForm->Name('slAdopt_amountX')}' value='".($bAdoptX ? $this->myNumber($fAmount) : "")."'/>
               </td></tr>

               <tr><td colspan='2'><hr/></td></tr>

               <tr>
               <td valign='top'>{$this->oL->S('Adopt in the name of')}</td>
               <td style='padding-left:15px'>"
             .$this->oForm->Radio('slnamechoose',0,'')." ".$this->oL->S('my name')."<br/>"
             .$this->oForm->Radio('slnamechoose',1,'')." ".$this->oL->S('anonymous')."<br/>"
             .$this->oForm->Radio('slnamechoose',2,'')." ".$this->oL->S('as a gift to')." ".$this->oForm->Text('slAdopt_name',"",array("size"=>25))
             //."<div class='mbro_infobox'>Gift donors will be sent a card in the recipient's name, describing the adopted variety. Please make your donation by Dec 14 for holiday delivery.</div>"
             ."</td></tr></table>";

        return($s);
    }

    private function sectionSE()
    {
        $s = "";
        $s = "<div style='font-weight:bold;margin-bottom:5px'>{$this->oL->S('membership')}</div>"
                             ."<div class='mbro_help'>{$this->oL->S('membership_desc2022')}</div>"
                             ."<div class='mbro_ctrl'>"
                                 //."<p>".$this->oKForm->Checkbox( 'mbrJoin', "&nbsp;Join or renew your membership. We'll ask you to fill in a short survey about your interests on the next page." )."</p>"  //$this->oL->S("Please send samples of garlic bulbils for $15") )."</p>")
                                 .$this->oForm->Radio('mbr_type','mbr1_0','')."&nbsp;&nbsp;&nbsp;".$this->oL->S('One Year Membership form line - online SED')."<br/>"
                                 .$this->oForm->Radio('mbr_type','mbr1_15sed','')."&nbsp;&nbsp;&nbsp;".$this->oL->S('One Year Membership form line - printed SED')."<br/>"
                                 .$this->oForm->Radio('mbr_type','','', array('bNoBlankMatches'=>1))."&nbsp;&nbsp;&nbsp;".$this->oL->S('se_none')
                             ."</div>";

        return($s);
    }

    private function sectionCGO()
    {
        $s = "";

        return($s);
    }

    private function sectionGarlic( string $sSection )
    {
        $s = "";

        $bGarlicAdvertised  = defined("STORE_GARLIC_ADVERTISED") && STORE_GARLIC_ADVERTISED;  // true: show the garlic box
        $bGarlicAvailable   = defined("STORE_GARLIC_AVAILABLE")  && STORE_GARLIC_AVAILABLE;   // true: enable sales, false: explain not available

        $s = ($bGarlicAvailable
                      ? ($this->oL->S("Garlic-bulbils-instr")
                         ."<p>".$this->oForm->Checkbox( 'bBulbils', $this->oL->S("Please send samples of garlic bulbils for $15") )."</p>")
                      : ("<p style='border:1px solid #822;color:#822;padding:10px'>Sorry! Our garlic bulbils are sold out. Please check back in late summer and fall. Thank you for your interest!</p>")
                     );

        return($s);
    }

    private function sectionBook( string $sSection )
    {
        $s = "";

        $s = "<p align='center'>".$this->oL->S('see_descriptions_here')."</p>"
             ."<table class='mbro_ctrl' cellspacing='0' cellpadding='0' border='0'><tr valign='top'>"
             ."<td width='60'>&nbsp;</td>"
             ."<td width='500'><b>".$this->oL->S('title')."</b></td>"
             ."<td width='200'><b>".$this->oL->S('price')."</b></td>"
             ."<td width='60'><b>".$this->oL->S('quantity')."</b></td>"
             ."</tr>";

        $s .= $this->mbr_pub( "ssh_en6_20", 20 );
        $s .= $this->mbr_pub( "ssh_fr6_20", 20 );

        $s .= "</table>"
             ."<p>U.S. orders: please add $5 for shipping in the Miscellaneous Payment box below</p>"
             ."<p>".$this->oL->S('contact_for_bulk_rates')."</p>";


        return($s);
    }

    private function mbr_pub( string $type, int $price )
    {
        $s = "<TR><TD>".$this->oL->S('vend_'.$type)."</TD>"
            ."<TD>".$this->oL->S('pub_'.$type)."</TD>"
            //."<TD>".$this->oL->Dollar( $this->oMbrOrder->raPubs[$type]['price'] )."</TD>"
            ."<TD>".$this->oL->Dollar($price)."</TD>"
            ."<TD><INPUT type=text name='".$this->oForm->Name('pub_'.$type)."' "
            ."value='".$this->myNumber($this->oForm->ValueEnt('pub_'.$type))."' size='3''></TD></TR>"
            ."<TR><TD colspan='4'><HR></TD></TR>";

        return( $s );
    }

    private function sectionMember( string $sSection )
    {
        $s = "";


        return($s);
    }

    private function sectionOther( string $sSection )
    {
        $s = "";

        $s = "<p>".$this->oL->S('Misc_payment_instructions').$this->oForm->Text('fMisc',"",["size"=>5])."</p>";

        return($s);
    }

    private function style()
    {
        return(
            "<style>
             .store2_sect_minor        { margin-bottom:20px; }
             .store2_sect_minor_header { background-color:#aaa; color:white; text-align:center; padding:4px; font-weight:bold; font-size:11pt; }
             .store2_sect_minor_body   { background-color:#eee; padding:15px; font-size:10pt }
             .store2_sect_major        { margin-bottom:20px; }
             .store2_sect_major_header { background-color:#aaa; color:white; text-align:center; padding:4px; font-weight:bold; font-size:12pt; }
             .store2_sect_major_body   { }
             </style>"
        );
    }

    private function sLocalStrs()
    {
        $raStrs = [ ['ns'=>'store', 'strs'=> [
            'Join Our Community Seed Growouts'
                => ['EN'=>"[[]]",
                    'FR'=>"Rejoignez nos projets de semences communautaires"],
            // More with chevron
            'More'
                => ['EN'=>"[[]]",
                    'FR'=>"Voir plus"],
            'Sorry no longer available'
                => ['EN'=>"[[]]",
                    'FR'=>"D&eacute;sol&eacute;, n'est plus disponible"],
            'Choose a variety'
                => ['EN'=>"[[]]",
                    'FR'=>"Choisissez une vari&eacute;t&eacute;"],
        ]] ];

        $sSiteImg = /* $this->bDrupal ? ("https://seeds.ca/i/img/") : */ SITEIMG;
        $sPubPageEN = "//seeds.ca/books";     //$this->bDrupal ? (SITEROOT."publications") : (SITEROOT."vend/forsale.php");
        $sPubPageFR = "//semences.ca/livres"; //$this->bDrupal ? (SITEROOT."publications_fr") : (SITEROOT."vend/vendre.php");

$sGarlicVarieties =
"
<div class='container-fluid' style='border: 1px solid #aaa;font-size:9pt;background-color:white'>
<p><strong>Varieties available / Les vari&eacute;t&eacute;s disponibles</strong></p>
<div class='row'>
<div class='col-md-2'>
    Alison's<br/><br/>
    Armenian Porcelain<br/><br/>
    Baba Franchuk's</br><br/>
    Belarus<br/><br/>
    Belatic Croatian<br/><br/>
    Brown Rose<br/><br/>
    Brown Tempest<br/><br/>
    California Late<br/><br/>
    Cedar Creek<br/><br/>
    Central Siberian<br/><br/>
    Chesnuk Red<br/><br/>
</div>
<div class='col-md-2'>
    Chet's Italian<br/><br/>
    Chiloe<br/><br/>
    Chinese 1<br/><br/>
    Chinese 2<br/><br/>
    Czech Broadleaf<br/><br/>
    Fauquier<br/><br/>
    French Red<br/><br/>
    Gaia's Joy<br/><br/>
    Georgian Crystal<br/><br/>
    German Red<br/><br/>
    German Stiffneck<br/><br/>
</div>
<div class='col-md-2'>
    Italian Purple<br/><br/>
    Jesuit<br/><br/>
    Khabar<br/><br/>
    Kiev<br/><br/>
    Killarney<br/><br/>
    Korean Purple<br/><br/>
    Lavigna<br/><br/>
    Legacy<br/><br/>
    Leningrad<br/><br/>
    Lukak<br/><br/>
    Malpasse<br/><br/>
</div>
<div class='col-md-2'>
    Mediterranean<br/><br/>
    Mennonite<br/><br/>
    Metechi<br/><br/>
    Music<br/><br/>
    Persian Star<br/><br/>
    Portugal Azores 1<br/><br/>
    Portugeorge<br/><br/>
    Purple Glazer<br/><br/>
    Racey<br/><br/>
    Railway Creek<br/><br/>
    Red Janice<br/><br/>
</div>
<div class='col-md-2'>
    Red Rezan<br/><br/>
    Rose de Lautrec<br/><br/>
    Russian Giant<br/><br/>
    Shatilli<br/><br/>
    Siberian<br/><br/>
    Sicilian<br/><br/>
    Slovak<br/><br/>
    Vevak Czech<br/><br/>
    Wild Buff<br/><br/>
    Yugoslavian<br/><br/>
</div>
</div></div>

<p>Sorry, we are not able to ship garlic to the U.S.</p>";


        $sL = array(
            "Annual_Membership_and_Donation"
                => array( "EN" => "Membership and Donation",
                          "FR" => "Adh&eacute;sion et dons" ),
/*
            "One Year Membership form line - no SED"
                => array( "EN" => "$35&nbsp;&nbsp;&nbsp;Membership for one year with on-line Seed Directory",
                          "FR" => "35$&nbsp;&nbsp;&nbsp;Adh&eacute;sion pour un an avec acc&egraves au Catalogue de semences en ligne" ),
            "One Year Membership form line"
                => array( "EN" => "$45&nbsp;&nbsp;&nbsp;Membership for one year with printed and on-line Seed Directory",
                          "FR" => "45$&nbsp;&nbsp;&nbsp;Adh&eacute;sion pour un an avec une version imprim&eacute;e du Catalogue de semences (acc&egrave;s &agrave; la version Web inclus)" ),
*/
            "One Year Membership form line - online SED"
                => array( "EN" => "<i>Free</i>&nbsp;&nbsp;&nbsp;Membership for one year with on-line Seed Directory",
                          "FR" => "<i>Gratuite</i>&nbsp;&nbsp;&nbsp;Adh&eacute;sion pour un an avec acc&egraves au Catalogue de semences en ligne" ),
            "One Year Membership form line - printed SED"
                => array( "EN" => "$15&nbsp;&nbsp;&nbsp;Membership for one year with printed and on-line Seed Directory",
                          "FR" => "15$&nbsp;&nbsp;&nbsp;Adh&eacute;sion pour un an avec une version imprim&eacute;e du Catalogue de semences (acc&egrave;s &agrave; la version Web inclus)" ),
            "Give a Charitable Donation"
                => array( "EN" => "Give a Charitable Donation",
                          "FR" => "Faire un don charitable" ),
            "membership"
                => array( "EN" => "Membership (new or renewal)",
                          "FR" => "Devenir membre ou renouveler votre adh&eacute;sion" ),
            "se_none"
                => ["EN" => "No thanks. Maybe another time.",
                    "FR" => "Non merci. Pas pour le moment."],

            "membership_desc"
                => array( "EN" => "<P>Membership includes:</P>"
                                 ."<UL compact='compact'>"
                                 ."<LI>a full year subscription to <I>Seeds of Diversity</I> magazine</LI>"
                                 ."<li>our monthly e-bulletin</li>"
                                 ."<LI>our annual Member Seed Directory, which lists "
                                 ."the seeds offered from member-to-member in our annual seed exchange (over 2400 varieties!)</LI></UL>",
                          "FR" => "<P>Votre adh&eacute;sion comprend:</P>"
                                 ."<UL compact='compact'>"
                                 ."<LI>l'abonnement &agrave; notre revue <I>Semences du patrimoine</I></LI>"
                                 ."<LI>le Catalogue de semences, soit la liste de toutes les semences offertes par les membres (environ 2400 vari&eacute;t&eacute;s!)</LI></UL>" ),
            'membership_desc2022'
                => ['EN'=> "<p>Seeds of Diversity members can participate in our seed exchange and seed grow-out programs, and support many projects in their communities.</p>"
                          ."<p><i>Membership is free!</i></p>",
                    'FR'=> "<p><i>L'adh&eacute;sion est gratuite!</i></p>"],

            "donation_desc"
                => array( "EN" => "Seeds of Diversity Canada is a registered Canadian charity (89650 8157 RR0001). "
                                 ."We depend on donations to do our work. Please support our horticultural preservation and educational projects by making a tax-creditable donation.",
                          "FR" => "Les Semences du patrimoine Canada (Le Programme semencier du patrimoine Canada) est un organisme "
                                 ."de bienfaisance enregistr&eacute; (89650 8157 RR0001).  Vos dons sont appr&eacute;ci&eacute;s puisqu'ils nous soutiennent dans nos projets "
                                 ."de pr&eacute;servation et de sensibilisation." ),
            "mbr_calendar_year"
                => array( "EN" => "Membership is for the calendar year, January through December. If it is late in the year, "
                                 ."your membership will begin in the next new year, unless you request otherwise.",
                          "FR" => "Toutes les adh&eacute;sions d&eacute;butent le 1er janvier et se terminent le 31 d&eacute;cembre. "
                                 ."Si votre inscription se fait tard au cours de l'ann&eacute;e, votre adh&eacute;sion d&eacute;butera d&eacute;s le "
                                 ."d&eacute;but de l'ann&eacute;e suivante, &agrave; moins que vous ne le sp&eacute;cifiez autrement." ),

            /*** Publications ***/

            "see_descriptions_here"
                => array( "EN" => "<A HREF='$sPubPageEN' target='_blank'>See descriptions here</A>",
                          "FR" => "<A HREF='$sPubPageFR'  target='_blank'>Voir les descriptions en cliquant ici</A>" ),

            "title"
                => array( "EN" => "Title",
                          "FR" => "Titre" ),
            "price"
                => array( "EN" => "Price",
                          "FR" => "Prix" ),
            "quantity"
                => array( "EN" => "Quantity",
                          "FR" => "Quantit&eacute;" ),
            "contact_for_bulk_rates"
                => array( "EN" => "Contact our office for discount rates on bulk orders of 10 or more.",
/*****/                   "FR" => "Plus de 10?  Contactez nous pour un bon prix!" ),
            "pub_ssh_en"
                => array( "EN" => "How to Save Your Own Seeds, 5th edition",
                          "FR" => "How to Save Your Own Seeds (Anglais)" ),
            "pub_ssh_en6_20"
                => array( "EN" => "How to Save Your Own Seeds, 6th edition",
                          "FR" => "How to Save Your Own Seeds (Anglais)" ),
            "pub_ssh_fr6_20"
                => array( "EN" => "La conservation des semences (French)",
                          "FR" => "La conservation des semences, une nouvelle &eacute;dition" ),

"pub_ssh_fr"
=> array( "EN" => "La conservation des semences du patrimoine",
          "FR" => "La conservation des semences du patrimoine" ),

"pub_nmd"
=> array( "EN" => "Niche Market Development and Business Planning",
          "FR" => "Niche Market Development and Business Planning (anglais seulement)" ),

"pub_shc"
=> array( "EN" => "Selling Heritage Crops",
          "FR" => "Selling Heritage Crops (anglais seulement)" ),

            "pub_suechan2012"
                => array( "EN" => "Conserving Native Pollinators in Ontario, by Sue Chan",
                          "FR" => "Conserving Native Pollinators in Ontario, by Sue Chan (anglais seulement)" ),
            "pub_kent2012"
                => array( "EN" => "How to Make a Pollinator Garden, by Clement Kent",
                          "FR" => "How to Make a Pollinator Garden, by Clement Kent (anglais seulement)" ),

            "vend_ssh_en"
                => array( "EN" => "<A HREF='{$sPubPageEN}#ssh_e' target='_blank'><IMG src='{$sSiteImg}vend/ssh_cv.gif' height='50'></A>",
                          "FR" => "<A HREF='{$sPubPageFR}#ssh_e' target='_blank'><IMG src='{$sSiteImg}vend/ssh_cv.gif' height='50'></A>" ),
            "vend_ssh_en6_20"
                => array( "EN" => "<A HREF='{$sPubPageEN}#ssh_e' target='_blank'><IMG src='{$sSiteImg}vend/ssh6en150.jpg' height='50'></A>",
                          "FR" => "<A HREF='{$sPubPageFR}#ssh_e' target='_blank'><IMG src='{$sSiteImg}vend/ssh6en150.jpg' height='50'></A>" ),
            "vend_ssh_fr6_20"
                => array( "EN" => "<A HREF='{$sPubPageEN}#ssh_f' target='_blank'><IMG src='{$sSiteImg}vend/ssh6fr150.jpg' height='50'></A>",
                          "FR" => "<A HREF='{$sPubPageFR}#ssh_f' target='_blank'><IMG src='{$sSiteImg}vend/ssh6fr150.jpg' height='50'></A>" ),
            "vend_ssh_fr"
                => array( "EN" => "<A HREF='{$sPubPageEN}#ssh_f' target='_blank'><IMG src='{$sSiteImg}vend/ssh_f_cv.gif' height='50'></A>",
                          "FR" => "<A HREF='{$sPubPageFR}#ssh_f' target='_blank'><IMG src='{$sSiteImg}vend/ssh_f_cv.gif' height='50'></A>" ),
            "vend_nmd"
                => array( "EN" => "<A HREF='{$sPubPageEN}#niche1' target='_blank'><IMG src='{$sSiteImg}vend/niche1_cv.gif' height='50'></A>",
                          "FR" => "<A HREF='{$sPubPageFR}#niche1' target='_blank'><IMG src='{$sSiteImg}vend/niche1_cv.gif' height='50'></A>" ),
            "vend_shc"
                => array( "EN" => "<A HREF='{$sPubPageEN}#niche2' target='_blank'><IMG src='{$sSiteImg}vend/niche2_cv.gif' height='50'></A>",
                          "FR" => "<A HREF='{$sPubPageFR}#niche2' target='_blank'><IMG src='{$sSiteImg}vend/niche2_cv.gif' height='50'></A>" ),
            "vend_everyseed"
                => array( "EN" => "<A HREF='{$sPubPageEN}#every_seed' target='_blank'><IMG src='{$sSiteImg}vend/EverySeed150.png' height='50'></A>",
                          "FR" => "<A HREF='{$sPubPageFR}#every_seed' target='_blank'><IMG src='{$sSiteImg}vend/EverySeed150.png' height='50'></A>" ),

            "vend_suechan2012"
                => array( "EN" => "<A HREF='{$sPubPageEN}#suechan2012' target='_blank'><IMG src='//www.seeds.ca/d?n=pubs/cover-conserving-native-pollinators--600.jpg' height='50'></A>",
                          "FR" => "<A HREF='{$sPubPageEN}#suechan2012' target='_blank'><IMG src='//www.seeds.ca/d?n=pubs/cover-conserving-native-pollinators--600.jpg' height='50'></A>" ),
            "vend_kent2012"
                => array( "EN" => "<A HREF='{$sPubPageEN}#kent2012' target='_blank'><IMG src='//www.seeds.ca/d?n=pubs/cover-how-to-make-a-pollinator-garden--420.jpg' height='50'></A>",
                          "FR" => "<A HREF='{$sPubPageEN}#kent2012' target='_blank'><IMG src='//www.seeds.ca/d?n=pubs/cover-how-to-make-a-pollinator-garden--420.jpg' height='50'></A>" ),

            "Registration"
                => array( "EN" => "Register for an Event",
    /* ?? */                      "FR" => "Conf&eacute;rences, &Eacute;v&eacute;nements" ),

            "Garlic bulbils available for planting"
                => array( "EN" => "Garlic bulbils available for planting",
                          "FR" => "Bulbilles d'ails disponibles" ),

            "Garlic-bulbils-instr"
                => array( "EN" => "<p>Garlic bulbils are available for autumn planting. These are not garlic bulbs! See our article "
                                 ."<a href='http://www.seeds.ca/d?n=web/ebulletin/2016-08-en/articles/garlic' target='_blank'>"
                                 ."How to Grow Garlic from Bulbils</a> if you're not sure what bulbils are or what to do with them.</p>"
                                 ."<p>We will send 5 varieties for $15, with at least 10 bulbils of each variety. We cannot ensure "
                                 ."special requests but if you have favourites, please mention them in the Notes section below, and "
                                 ."we will try to include them.</p>"
                                 .$sGarlicVarieties,

                          "FR" => "<p>Nous offrons 48 vari&eacute;t&eacute;s de bulbilles d'ail pour la plantation d&egrave;s cet automne! "
                                 ."Attention, ce sont des bulbilles et non pas de gros ca&iuml;eu auquel vous &ecirc;tes habitu&eacute;, "
                                 ."mais le tout petit bulbe qui pousse dans la fleur &agrave; la cime des plantes.</p>"
                                 ."<p>Consultez notre article "
                                 ."<a href='http://www.semences.ca/d?n=web/ebulletin/2016-08-fr/articles/ail' target='_blank'>"
                                 ."La culture des bulbilles d'ail</a></p>"
                                 ."<p>Nous enverrons au moins 10 de bulbilles de 5 vari&eacute;t&eacute;s diff&eacute;rentes pour 15$ frais de poste inclus. "
                                 ."Nous ne serons peut-&ecirc;tre pas en mesure d'offrir des vari&eacute;t&eacute;s sur demande, mais si vous connaissez "
                                 ."votre vari&eacute;t&eacute; pr&eacute;f&eacute;r&eacute;e, n'h&eacute;sitez pas &agrave; la demander et nous ferons "
                                 ."tout notre possible pour vous la procurer.</p>"
                                 .$sGarlicVarieties,
                        ),
            "Please send samples of garlic bulbils for $15"
                => array( "EN" => "Please send samples of garlic bulbils for $15",
                          "FR" => "$15 &nbsp;&nbsp;Envoyez 5 &eacute;chantillons diff&eacute;rentes des bulbilles d'ails" ),



            "Adopt a Variety into the Canadian Seed Library"
                => array( "EN" => "Adopt a Variety into the Canadian Seed Library",
                          "FR" => "Adoptez une vari&eacute;t&eacute; &agrave; la Biblioth&egrave;que canadienne des semences" ),
            "You can adopt etc"
                => array( "EN" => "<P>You can adopt a heritage seed variety into our Seed Library collection forever, with a donation!</P>"
                                 ."<P>A full adoption of $250 will preserve a seed variety for all time. You can also make a partial adoption of any amount. "
                                 ."Donations of $50 or more will be permanently recognized in the Seed Library, and the "
                                 ."full amount of every adoption is a tax-receiptable charitable donation.</P>",
/* TODO */                "FR" => "<P>Vous pouvez adopter une vari&eacute;t&eacute; dans notre Biblioth&egrave;que des semences, en permanence.</P>"
                                 ."<P>Chaque don de 250$ nous permet de rendre une vari&eacute;t&eacute; disponible pour les jardiniers pour toujours. "
                                 ."Vous pouvez aussi partager une adoption partielle. Tous les dons au montant de 50$ ou de plus seront identifi&eacute;es "
                                 ."pour toujours dans la Biblioth&egrave;que des semences, et le montant total de chaque adoption est un don charitable "
                                 ."pour lequel le donneur recevra un re&ccedil;u.</P>" ),
            "Full adoption"
                => array( "EN" => "Full adoption",
                          "FR" => "Adoption compl&egrave;ve" ),
            "Partial adoption"
                => array( "EN" => "Partial adoption",
                          "FR" => "Adoption partielle" ),
            "as needed"
                => array( "EN" => "as needed",
                          "FR" => "selon les besoins" ),
            "my name"
                => array( "EN" => "my name",
                          "FR" => "en mon nom" ),
            "anonymous"
                => array( "EN" => "anonymous",
                          "FR" => "de fa&ccedil;on anonyme" ),
            "as a gift to"
                => array( "EN" => "as a gift to",
                          "FR" => "en cadeau &agrave;" ),
            "Adopt in the name of"
                => array( "EN" => "Adopt in the name of",
                          "FR" => "Adoptez" ),
            "Choose a variety to adopt"
                => array( "EN" => "Choose a variety to adopt",
                          "FR" => "Choisissez une vari&eacute;t&eacute; pour adopter" ),
            "Please note what mbr fee covers"
                => array( "EN" => "Please note that your membership fee only covers the cost of publications that you receive. "
                         ."If you would like to contribute to Seeds of Diversity's projects and administrative expenses, "
                         ."please consider adding a charitable donation.",
                         "FR" => "Veuillez prendre note que le montant de l'adh&eacute;sion ne couvre que les frais reli&eacute;s aux publications que vous recevez. Si vous souhaitez contribuer financi&egrave;rement &agrave; nos projets et &agrave; nos frais d'administration, nous vous remercions de penser &agrave; faire un don." ),


            "reg # tickets"
                => array( "EN" => "Registration : # of tickets",
                          "FR" => "Registration : combien de billets" ),

        );
        $raStrs[] = ['ns'=>'store', 'strs'=>$sL];

        return($raStrs);
    }
}
