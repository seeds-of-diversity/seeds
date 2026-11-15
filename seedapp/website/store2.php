<?php

/* store2.php
 *
 * Copyright 2026 Seeds of Diversity Canada
 *
 * Second generation Seeds of Diversity store app
 */

include_once(SEEDLIB."basket/store2_basket.php");
include_once(SEEDLIB."sl/sldb.php");
include_once(SEEDCORE."SEEDCoreFormSession.php");
include_once(SEEDCORE."SEEDLocal.php");

class Store2App
{
    private $oApp, $raConfig;
    private $p = [];    // dynamically filled because contains $this
    private $oForm, $oL, $oBasket;
    private $formAction;    // action='' for forms, necessary when embedding in Wordpress

    function __construct( SEEDAppConsole $oApp, array $raConfig )
    {
        $this->oApp = $oApp;
        $this->raConfig = $raConfig;
        $this->p = [
            'donation' => ['fn'=>[$this,'page0_donation'], 'button'=>"Give Now", 'title'=>"Give a Charitable Donation", 'content'=>"<p>Please support our horticultural preservation and educational projects by making a tax-creditable donation.</p>"],
            'sladopt'  => ['fn'=>[$this,'page0_SLAdopt'],  'button'=>"Give Now", 'title'=>"Adopt a Variety into the Canadian Seed Library", 'content'=>"<p>You can adopt a heritage seed variety into our Seed Library collection forever, with a donation!</p>"],
//            'se'       => ['fn'=>[$this,'page0_SE'],       'button'=>"Join Now", 'title'=>"Join the Seed Exchange"],
//            'cgo'      => ['fn'=>[$this,'page0_CGO'],      'button'=>"Join Now", 'title'=>"Join our Seed Grow-out Projects"],
//            'garlic'   => ['fn'=>[$this,'page0_garlic'],   'button'=>"Buy Now",  'title'=>"Purchase Garlic Bulbils for Planting"],
//            'book'     => ['fn'=>[$this,'page0_book'],     'button'=>"Buy Now",  'title'=>"Buy a Book"],
//            'member'   => ['fn'=>[$this,'page0_member'],   'button'=>"Join Now", 'title'=>"Join our Membership"],
//            'other'    => ['fn'=>[$this,'page0_other'],    'button'=>"More",     'title'=>"Special Orders"],
        ];
        $this->oForm = new SEEDCoreFormSession($oApp->sess, 'store_basket', 'A');
        $this->oBasket = new Store2_Basket($oApp, $this->oForm);

        $this->oL = new SEED_Local( $this->sLocalStrs(),
                                    @$raConfig['lang'] ?: $this->oApp->lang,     // specify lang or use oApp's lang
                                    'store' );

         $this->formAction = SEED_isLocal ? "" : "https://seeds.ca/store2";
    }

    function DrawApp()
    {
        $s = $this->oBasket->Style().$this->style();

        $this->oBasket->UpdateBasket(); // updates the form from http, and normalizes basket contents ; this should not be done on every page though

        // don't allow going back
        if( $this->oForm->Value('kOrder') && !in_array($this->oForm->Value('page'), ['0-clear','2-payment','3-final']) )  $this->oForm->SetValue('page','2-payment');

        // kluge to allow going back from 1-confirm
        if( $this->oForm->Value('page')=='2-payment' && SEEDInput_Str('klugeBack') )  $this->oForm->SetValue('page','0-back');

        switch($this->oForm->Value('page')) {
            case '1-confirm': $s .= $this->page1_confirm(); break;
            case '2-payment': $s .= $this->page2_payment(); break;
            case '3-final':   $s .= $this->page3_final();   break;
            case '0-clear':   $this->oForm->Clear();
                              $s .= $this->page0_start();   break;
            default:          $s .= $this->page0_start();   break;
        }

        return($s);
    }

    private function page0_start()
    {
        $s = "";

        $sSection = SEEDInput_Str('p_section');
        if( isset($this->p[$sSection]) ) {
            /* Show a specific section sub-page
             */
            $s .= "<div class='store2_sect_major'>
                       <div class='store2_sect_major_header'>{$this->p[$sSection]['title']}</div>
                       <div class='store2_sect_major_body'>
                           <form action='{$this->formAction}' method='post'>
                           {$this->p[$sSection]['fn']($sSection)}
                           <div style='padding-top:2em'><input type='submit' name='p_add' value='Continue'/></div>
                           <input type='hidden' name='p_add_section' value='$sSection'/>
                           </form>
                       </div>
                   </div>";
        } else {
            /* Show the main page
             */
            if( ($sAddSection = SEEDInput_Str('p_add_section')) ) {
                //$this->oBasket->UpdateBasket();
//                $s .= "<div class='alert alert-success'>Evaluating $sAddSection</div>";
            }
            $s .= $this->page0_main();
        }

        return($s);
    }

    private function page0_main()
    {
        $s = "";

        if( $this->oBasket->HasContents() ) {
            $s .= "<form action='{$this->formAction}' method='post'>
                   <div class='container-fluid'><div class='row'>
                   <div class='col-md-6'>
                       {$this->oBasket->DrawBasket(['bNotesEditable'=>true])}
                   </div>
                   <div class='col-md-6'>
                       <input type='submit' value='Confirm your order >>'/>
                   </div>
                   </div></div>
                   {$this->oForm->Hidden('page',['value'=>"1-confirm"])}
                   </form>";
        }

        $s .= "<div class='container-fluid'><div class='row'>";
        foreach($this->p as $k=>$ra) {
            $s .= "<div class='col-md-3'>
                   <div class='store2_sect_minor'>
                       <div class='store2_sect_minor_header'>{$ra['title']}</div>
                       <div class='store2_sect_minor_body'>
                           {$ra['content']}
                           <p><form action='{$this->formAction}' method='post'><button>{$ra['button']}</button><input type='hidden' name='p_section' value='{$k}'/></form>
                       </div>
                   </div></div>";
        }
        $s .= "</div></div>";

        return($s);
    }

    private function page0_donation()
    {
        $s = "";

        $fDonation = floatval($this->oForm->Value('donation')); // make a ValueFloat() method
        $raDon = [35, 75, 100];
        $bDonX = !in_array($fDonation, $raDon);     // using the type-your-own-number box
        $s .= "<div class='mbro_help'>{$this->oL->S('donation_desc')}</div>
               <div class='container-fluid'><div class='row'>
               <div class='col-md-8'>
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
               </div>
               <div class='col-md-4'>
                   <!-- display:inline-block makes the div width no larger than the content width -->
                   <div style='border:1px solid #777;margin-top:1em;padding:1em;display:inline-block;'>
                       <p>We also encourage donations through CanadaHelps, a secure and trusted donation platform.<br/>Please use CanadaHelps for instant donations, gifts of securities, and monthly donations.</p>
                       <p style='font-size:1.2em'><a target='_blank' href='https://www.canadahelps.org/en/charities/seeds-of-diversity-canada-programme-semencier-du-patrimoin/'>
                           <img style='height:3em' src='https://seeds.ca/d?n=www/home/ch-logo.png' border='0' alt='Donate Now Through CanadaHelps.org!'/> Go to CanadaHelps</a>
                       </p>
                   </div>
               </div>
               </div></div>";

// kluge : after storing checkbox state in the controls, reset in the sess so back->uncheck->next will do the right thing
        $oSVar = new SEEDSessionVarAccessor( $this->oApp->sess, "mbrocdata" );
        foreach( ['donpref_sl','donpref_yfs','donpref_ss','donpref_any'] as $k ) {
            $oSVar->VarSet($k,0);
        }

        return($s);
    }

    private function myNumber($n) { return($n ? $n : ""); }

    private function page0_SLAdopt()
    {
        $s = "";

        $sPriorityList = "<h4>Our Current Priority Varieties</h4>";
        $raPriorityOpts = ['-- Choose a Priority Variety --'=>0];
        foreach( $this->page0_SLAdopt_priorities() as $raP ) {
            $sPriorityList .= "<p style='font-weight:bold'>{$raP['name']}</p><p style='padding-left:2em'>{$raP['desc']}</p>";
            $raPriorityOpts[$raP['name']] = $raP['kPcv'];
        }


        $fAmount = floatval($this->oForm->Value('slAdopt_amount'));
        $raAdopt = array( 50, 100, 150, 200, 250 );
        $bAdoptX = !in_array($fAmount, $raAdopt);     // using the type-your-own-number box

        $s =  $this->oL->S("You can adopt etc")
             ."<div class='container-fluid'><div class='row'>
               <div class='col-md-6'>

               <table class='mbro_ctrl'>
               <tr><td colspan='2'><hr/></td></tr>
               <tr>
               <td style='vertical-align: top'>{$this->oL->S('Choose a variety to adopt')}</td>
               <td style='padding-left:15px'>
                   {$this->oForm->Radio('slcvchoose','as_needed','',['checked'=>true])} {$this->oL->S('as needed')}<br/>
                   {$this->oForm->Radio('slcvchoose','priority','')} {$this->oForm->Select('slAdopt_kPcv', $raPriorityOpts)}<br/>
                   {$this->oForm->Radio('slcvchoose','','')} {$this->oForm->Text('slAdopt_cv',"",array("size"=>15))}  Enter your favourite variety - <a href='https://seeds.ca/diversity/seed-library/list' target='_blank'>See All Our Varieties Here</a>
               </td></tr>

               <tr><td colspan='2'><hr/></td></tr>

               <tr>
               <td style='vertical-align: top;padding-bottom:1em'>{$this->oL->S("Full adoption")}</td>
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

             ."<tr><td style='vertical-align: top'>{$this->oL->S("Partial adoption")}</td>
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
               <td style='vertical-align: top'>{$this->oL->S('Adopt in the name of')}</td>
               <td style='padding-left:15px'>"
             .$this->oForm->Radio('slnamechoose',0,'',['checked'=>true])." ".$this->oL->S('my name')."<br/>"
             .$this->oForm->Radio('slnamechoose',1,'')." ".$this->oL->S('anonymous')."<br/>"
             .$this->oForm->Radio('slnamechoose',2,'')." ".$this->oL->S('as a gift to')." ".$this->oForm->Text('slAdopt_giftname',"",array("size"=>25))
             //."<div class='mbro_infobox'>Gift donors will be sent a card in the recipient's name, describing the adopted variety. Please make your donation by Dec 14 for holiday delivery.</div>"
             ."</td></tr></table>
             <p>&nbsp;</p>
             </div>

             <div class='col-md-6'>
                 <div style='border:1px solid #aaa;border-radius:5px;padding:1em'>
                 {$sPriorityList}
                 </div>
             </div>
             </div></div>";

        return($s);
    }

    private function page0_SLAdopt_priorities()
    {
        $raPriorities = [];

        $raP = [
             376,   // Amish Salad
             238,   // Burgess Stuffing
            3369,   // Dwarf Japanese
            3046,   // Federle
             743,   // Gallo Plum
            3400,   // German Yellow Cherry
            1034,   // Gregori
            3486,   // Hilda
            3256,   // Honey Drop
            2281,   // Jitomate Bulito
            1645,   // Manyel
             788,   // Milka's Red Bulgarian
            1600,   // Puck
            2271,   // Punta Banda
             347,   // Red Ruffles
            2357,   // Rumi Banjan
             263,   // Santa Cruz Kada
            1094,   // Summer pea
             375,   // Thai Pink Egg
            1806,   // Tumbling Tom
        ];

        $oSLDB = new SLDBRosetta($this->oApp);
        foreach( $raP as $kPcv ) {
            // kluge if a priority variety is already in an order, don't show it
            if( $this->oApp->kfdb->Query1("SELECT count(*) FROM {$this->oApp->DBName('seeds1')}.mbr_order_pending WHERE _status=0 AND sExtra LIKE '%slAdopt_kPcv={$kPcv}&%'") ) {
                continue;
            }

            if( ($kfr = $oSLDB->GetKFR('PxS', $kPcv)) ) {
                $raPriorities[] = ['kPcv'=>$kPcv, 'name'=>"{$kfr->Value('P_name')} {$kfr->Value('S_name_en')}", 'desc'=>$kfr->Value('packetLabel')];
            }
        }

        return($raPriorities);
    }


    private function page0_SE()
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

    private function page0_CGO()
    {
        $s = "";

        return($s);
    }

    private function page0_garlic( string $sSection )
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

    private function page0_book( string $sSection )
    {
        $s = "";

        $s = "<p align='center'>".$this->oL->S('see_descriptions_here')."</p>"
             ."<table class='mbro_ctrl' cellspacing='0' cellpadding='0' border='0'><tr valign='top'>"
             ."<td width='60'>&nbsp;</td>"
             ."<td width='500'><b>".$this->oL->S('title')."</b></td>"
             ."<td width='200'><b>".$this->oL->S('price')."</b></td>"
             ."<td width='60'><b>".$this->oL->S('quantity')."</b></td>"
             ."</tr>";

        $s .= $this->page0_book_item( "ssh_en6_20", 20 );
        $s .= $this->page0_book_item( "ssh_fr6_20", 20 );

        $s .= "</table>"
             ."<p>U.S. orders: please add $5 for shipping in the Miscellaneous Payment box below</p>"
             ."<p>".$this->oL->S('contact_for_bulk_rates')."</p>";


        return($s);
    }

    private function page0_book_item( string $type, int $price )
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

    private function page0_member( string $sSection )
    {
        $s = "";


        return($s);
    }

    private function page0_other( string $sSection )
    {
        $s = "";

        $s = "<p>".$this->oL->S('Misc_payment_instructions').$this->oForm->Text('fMisc',"",["size"=>5])."</p>";

        return($s);
    }

    private function page1_confirm()
    {
        $s = "";

        $sContactForm =
              $this->page1_confirm_mail_line("mail_email")
             .$this->page1_confirm_mail_line("mail_firstname")
             .$this->page1_confirm_mail_line("mail_lastname")
             .$this->page1_confirm_mail_line("mail_company")
             .$this->page1_confirm_mail_line("mail_addr")
             .$this->page1_confirm_mail_line("mail_city")
             .$this->page1_confirm_mail_line("mail_prov")
//             .$mL->S('mail_prov')."<br/>";
//        $sfProv = $this->oKForm->oFormParms->sfParmField("mail_prov");
//        $s .= SelectProvinceOrState( "mbrocForm1", $sfProv, $this->lang, $this->oKForm->oDS->Value("mail_prov") ); //(!$kfr->IsEmpty('mail_prov') ? ($kfr->value('mail_prov').($kfr->value('mail_country')=='Canada' ? 1 : 2)) : "" ) );
             .$this->page1_confirm_mail_line("mail_postcode")
             .$this->page1_confirm_mail_line("mail_phone");

        $s .= "<form action='{$this->formAction}' method='post'>
               <div class='container-fluid'><div class='row'>
               <div class='col-md-6'>
                   <div>{$this->oBasket->DrawBasket(['bNotesEditable'=>false])}</div>
               </div>
               <div class='col-md-6'>
                   <div style='background-color:#eee;width:32em;padding:1em 1em 3em 1em;border-radius:5px'>
                       $sContactForm
                       <div style='width:30em;margin-top:1em'><div style='display:inline-block;float:left'><input type='submit' name='klugeBack' value='<< Back'/></div> <div style='display:inline-block;float:right'><input type='submit' value='Payment >>'/></div></div
                   </div>
               </div>
               </div></div>
               {$this->oForm->Hidden('page',['value'=>"2-payment"])}
               </form>";

        return($s);
    }

    private function page1_confirm_mail_line( string $name, $size = 30 )
    {
        //return( $o->oKForm->TextTD($name, $o->oL->S($name), array("size"=>$size) ) );
        return( $this->oL->S($name)."<br/>".$this->oForm->Text($name, "", ['width'=>'30em'])."<br/>" );
    }


    private function page2_payment()
    {
        $s = "";

include_once(SEEDAPP."basket/sodBasketFulfil.php");
        $oOrder = new SodOrder($this->oApp);

        if( ($kOrder = $this->oForm->ValueInt('kOrder')) ) {
            $kfrOrder = $oOrder->KfrelOrder()->GetRecordFromDBKey($kOrder);
        } else {
            $kfrOrder = $oOrder->KfrelOrder()->CreateRecord();
            $kfrOrder->SetValue('eStatus', "NEW");
            $kfrOrder->SetValue('country', "Canada");
            foreach( ["mail_firstname", "mail_lastname",
                      "mail_company",   "mail_addr",
                      "mail_city",      "mail_postcode",
                      "mail_phone",     "mail_email",
                      "mail_prov",
                      "notes"] as $k )
            {
                $kfrOrder->SetValue($k, $this->oForm->Value($k));
            }

            $fTotal = 0.0;

            /* Donation
             */
            if(($p = floatval($this->oForm->Value('donation')))) {
                $kfrOrder->SetValue('donation',$p);
                $fTotal += $p;
            }

            /* SLAdopt
             */
            if(($slAdopt_amount = floatval($this->oForm->Value('slAdopt_amount')))) {
                $fTotal += $slAdopt_amount;

                $kfrOrder->UrlParmSet( "sExtra", 'slAdopt_amount', $slAdopt_amount );
                // kluge: put this one early so LIKE '%slAdopt_kPcv=NNNN&%' works because there's always an & after the number
                if(($p = $this->oForm->Value('slAdopt_kPcv'))) {
                    $kfrOrder->UrlParmSet( "sExtra", 'slAdopt_kPcv', $p);
                }
                $kfrOrder->UrlParmSet( "sExtra", 'slAdopt_cv',     $this->oForm->Value('slAdopt_cv'));

                switch( $this->oForm->Value("slnamechoose") ) {
                    case 1:     $name = "anonymous";                                       break;
                    case 2:     $name = "gift to: {$this->oForm->Value('slAdopt_name')}";  break;
                    case 0:
                    default:    $name = "";                                                break;
                }
                $kfrOrder->UrlParmSet( "sExtra", 'slAdopt_name', $name );
            }
            $kfrOrder->SetValue('pay_total', $fTotal);
            $kfrOrder->PutDBRow();
            $kOrder = $kfrOrder->Key();
            $this->oForm->SetValue('kOrder', $kOrder);
            $this->oForm->SetValue('fTotal', $fTotal);

            $oSoDOrder = new SoDOrder_MbrOrder($this->oApp);
            $oSoDOrder->CreateFromMbrOrder($kOrder);
        }

        $s .= "<div class='container-fluid'><div class='row'>
               <div class='col-md-6'>
                   <h4 style='text-align:center'>Order #{$kOrder}</h4>
                   <div>{$this->oBasket->DrawBasket(['bNotesEditable'=>false])}</div>

                   <div><form action='{$this->formAction}' method='post'><input type='submit' value='Start a New Order'/>{$this->oForm->Hidden('page',['value'=>'0-clear'])}</form></div>
               </div>
               <div class='col-md-6'>
                   <div style='background-color:#eee;border:1px solid #aaa;border-radius:10px;padding:1em 2em;margin:2em'>
                       <h3>{$this->oL->S('Pay by e-transfer')}</h3>
                       <div class='store_payment_expand'>{$this->oL->S('etransfer_instructions',['kOrder'=>$kOrder])}</div>
                   </div>
                   <div style='background-color:#eee;border:1px solid #aaa;border-radius:10px;padding:1em 2em;margin:2em'>
                       <h3>{$this->oL->S('Pay by credit card / Paypal')}</h3>
                       <div class='store_payment_expand'>
                           <div style='display:inline-block'>{$this->page2_paypalButton()}</div>
                           <!-- also submit the paypal form if clicked on the instructions -->
                           <div style='display:inline-block; cursor:pointer;margin-left:3em' onclick='document.getElementById(\"store2_paypal_button_form\").submit()'>{$this->oL->S('paypal_instructions')}</div>
                       </div>
                   </div>
                   <div style='background-color:#eee;border:1px solid #aaa;border-radius:10px;padding:1em 2em;margin:2em'>
                       <h3>{$this->oL->S('Pay by cheque')}</h3>
                       <div class='store_payment_expand'>{$this->oL->S('cheque_instructions')}</div>
                   </div>
               </div>
               </div></div>";

        return($s);
    }

//https://www.paypal.com/cgi-bin/webscr?cmd=p/pdn/howto_checkout-outside
    private function page2_paypalButton( $raVars = array() )
    {
		$raPP = array();

        /* SYSTEM
         */
		$raPP['cmd']                   = '_xclick';                  // Buy Now button  - see value='_donations' for donation button
		$raPP['business']              = 'mail@seeds.ca';
        $raPP['quantity']              = 1;                          // multiplies the 'amount' to make a bulk payment!  Must be 1.
        $raPP['no_shipping']           = 1;                          // the buyer is not prompted for a shipping address (in addition to billing address)
        $raPP['no_note']               = 1;                          // the buyer is not prompted to enter a note
//TODO: explicitly set charset to iso, instead of relying on their default
        //$raPP['charset']               = 'utf-8';                  // PayPal "sends data to you" in the charset specified here. It also seems to expect data to be sent to it in this charset.
                                                                     // We used to set this to UTF-8, but accented characters in our posted parms (like Adh�sion in item_name) caused immediate PayPal fatal error about character encoding.
        /* TRANSACTION
         */
        $raPP['item_name']             = "Donation"; //$this->oMbrOrder->tinySummary();
        $raPP['amount']                = $this->oForm->Value('fTotal');  // $this->oMbrOrder->kfr->value('pay_total');
        $raPP['currency_code']         = "CAD"; // ($this->oMbrOrder->kfr->value('mail_country')=='Canada'? "CAD" : "USD");
        $raPP['item_number']           = $this->oForm->ValueInt('kOrder');  // Passthrough variables: PayPal doesn't use these, but transmits them to IPN.
        $raPP['invoice']               = $this->oForm->ValueInt('kOrder');  // Though PayPal docs say that they are neither used nor recorded by PayPal, we've seen PayPal complain when an invoice number duplicates an earlier one.
     // $raPP['custom']                = merchant_custom_value

        /* PAGES - these can also be set as an option in the PayPal account, but we want to propagate parms and use different checkoutApp urls
         */
        $raPP['notify_url']            = 'https://www.seeds.ca/l/mbr/mbr_PPipn.php'; // Instant Payment Notification
// TODO: assemble the full url for PHP_SELF with the PHPSESSID propagated.  cancel_return should somehow cause mbrocst=MBROC_ST_CANCEL
        $raPP['return']                = $this->oL->GetLang() == 'EN' ? "https://www.seeds.ca/store" : "https://www.semences.ca/boutique";
        $raPP['cancel_return']         = $this->oL->GetLang() == 'EN' ? "https://www.seeds.ca/store" : "https://www.semences.ca/boutique";
        $raPP['rm']                    = 2;                          // Return Method: how parms sent to return page: 1:GET, 2:POST
        $raPP['cbt']                   = $this->oL->S('Return to SoD button'); // The text on the Continue button on completion

        /* DECORATION
         */
     // $raPP['image_url']             = 'http://www.seeds.ca/img/logo/logo02_EN_150.png';  // logo image max 150x50
     // $raPP['cpp_header_image']      = 'http://www.seeds.ca/img/logo/logo02_EN_750.png';  // header image max 750x90
     // $raPP['cpp_headerback_color']  = 'FFFFFF';                                          // header background colour
     // $raPP['cpp_headerborder_color'] = '555555';                                         // header border colour
     // $raPP['cpp_payflow_color']     = 'AAAAAA';                                          // page background colour below header

        /* PREPOPULATE
         */
        $raPP['first_name']            = $this->oForm->Value('mail_firstname');
        $raPP['last_name']             = $this->oForm->Value('mail_lastname');
        $raPP['address1']              = $this->oForm->Value('mail_addr');
        $raPP['city']                  = $this->oForm->Value('mail_city');
        $raPP['state']                 = $this->oForm->Value('mail_prov');
        $raPP['zip']                   = $this->oForm->Value('mail_postcode');
        $raPP['country']               = ($this->oForm->Value('mail_country')=='Canada' ? "CA" : "US");

        $raPP['lc'] = $raPP['country'] == 'US' ? 'en_US'
                                               : ($this->oL->GetLang()=='FR' ? 'fr_CA' : 'en_CA');


/*
    <input type="hidden" name="tax" value="0">
    <input type="hidden" name="shipping" value="5.00">
    <input type="hidden" name="bn" value="PP-DonationsBF">
    <input type="image" src="https://www.paypal.com/en_US/i/btn/x-click-but04.gif" border="0" name="submit" alt="Make payments with PayPal - it's fast, free and secure!">
    <img alt="" border="0" src="https://www.paypal.com/de_DE/i/scr/pixel.gif" width="1" height="1">
*/

        foreach( $raVars as $k => $v ) {
            $raPP[$k] = $v;
        }
        $s = "\n<FORM id='store2_paypal_button_form' action='https://www.paypal.com/cgi-bin/webscr' method='post'>"
            ."\n<INPUT type='image' src='https://www.paypal.com/en_US/i/btn/x-click-but6.gif' name='submit' alt='".$this->oL->S('secure_payment_paypal')."'>";
        foreach( $raPP as $k => $v ) {
            $s .= "\n<INPUT type='hidden' name='$k' value='".SEEDCore_HSC($v)."'/>";
        }
        $s .= "\n</FORM>";

        return( $s );
    }

    private function page3_final()
    {
        $s = "";

        $s = "Final";

        return($s);
    }

    private function style()
    {
        return(
            "<style>
             .store2_sect_minor        { margin:1.5em 0; background-color:#eee; }
             .store2_sect_minor_header { background-color:#aaa; color:white; text-align:center; padding:4px; font-weight:bold; font-size:11pt; }
             .store2_sect_minor_body   { padding:15px; font-size:10pt }
             .store2_sect_major        { margin-bottom:20px; }
             .store2_sect_major_header { background-color:#aaa; color:white; text-align:center; padding:4px; font-weight:bold; font-size:12pt; }
             .store2_sect_major_body   { padding:1em }
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

            "mail_firstname"
                => array( "EN" => "First Name",
                          "FR" => "Pr&eacute;nom" ),

            "mail_lastname"
                => array( "EN" => "Last Name",
                          "FR" => "Nom" ),

            "mail_company"
                => array( "EN" => "Organization or company",   // (if applicable)"
                          "FR" => "Organisme ou compagnie" ),  // (si applicable)"

            "mail_addr"
                => array( "EN" => "Address",
                          "FR" => "Adresse" ),

            "mail_city"
                => array( "EN" => "City or Town",
                          "FR" => "Ville" ),

            "mail_prov"
                => array( "EN" => "Province/State",
                          "FR" => "Province/&Eacute;tat" ),

            "mail_postcode"
                => array( "EN" => "Postal Code / Zip",
                          "FR" => "Code postal" ),

            "mail_phone"
                => array( "EN" => "Telephone (with area code)",
                          "FR" => "T&eacute;l&eacute;phone" ),

            "mail_email"
                => array( "EN" => "Email",
                          "FR" => "Courriel" ),

            "Pay by e-transfer"           => ['EN' => "[[]]", "FR" => "Payer par Virement Interac"],
            "Pay by credit card / Paypal" => ['EN' => "[[]]", "FR" => "Payer par carte de cr&eacute;dit / Paypal"],
            "Pay by cheque"               => ['EN' => "[[]]", "FR" => "Payer par ch&egrave;que"],

            "etransfer_instructions"
                => ["EN" => "<p style='margin-left:1em'>Send your e-transfer to mail@seeds.ca and please mention the order number <strong>[[kOrder]]</strong>. No security question is needed.</p>",
                    "FR" => "<p style='margin-left:1em'>L'envoyez &agrave; mail@seeds.ca et notez le nombre de l'ordre <strong>[[kOrder]]</strong>. Aucune question de s&eacute;curit&eacute; n'est n&eacute;cessaire.</p>"],

            "cheque_instructions"
                => ["EN" => "<p style='margin-left:1em'>Please print this summary page and mail it with a cheque or money order payable to <b>Seeds of Diversity Canada</b>.</p>
                             <div style='margin-left:2em;font-weight:bold'>Seeds of Diversity Canada<br/>#1-12 Dupont St West<br/>Waterloo ON N2L 2X6</div>",
                    "FR" => "<p style='margin-left:1em'>Veuillez faire imprimer ce page et exp&eacute;dier avec un ch&egrave;que ou mandat postal.
                                                        Libellez votre ch&egrave;que au nom de <b>Semences du patrimoine Canada</b>.</p>
                             <div style='margin:1em;font-weight:bold'>Semences du patrimoine<BR>#1-12 Dupont St West<br/>Waterloo ON N2L 2X6</div>"],

            "paypal_instructions"
                => ["EN" => "<p><strong>Click here to pay by credit card</strong><br/>You don't need a PayPal account to pay for your order, just a credit card.</p>",
/* TODO */          "FR" => "<P>Cliquez ici pour employer notre page s&ucirc;re de PayPal pour le paiement de carte de cr&eacute;dit.</P>"],

            "Return to SoD button"
                => array( "EN" => "Return to Seeds of Diversity",
                          "FR" => "Retour au Semences du patrmoine"),
            "secure_payment_paypal"
                => array( "EN" => "Secure payment through PayPal",
                          "FR" => "Paiement s&ucirc;r via PayPal" ),


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
                => ['EN' => "<p>Seeds of Diversity's Canadian Seed Library is a collection of more than 3000 varieties of rare and endangered food plants,
                                backing up the work of our seed savers and Canadian heritage seed suppliers.</p>
                             <p>You can adopt a heritage seed variety into our Seed Library collection forever, with a donation!</p>
                             <p>A full adoption of $250 will preserve a seed variety for all time. You can also make a partial adoption of any amount. Donations of $50 or more
                                will be permanently recognized in the Seed Library, and the full amount of every adoption is a tax-receiptable charitable donation.</p>",
/* TODO */                "FR" => "<P>Vous pouvez adopter une vari&eacute;t&eacute; dans notre Biblioth&egrave;que des semences, en permanence.</P>"
                                 ."<P>Chaque don de 250$ nous permet de rendre une vari&eacute;t&eacute; disponible pour les jardiniers pour toujours. "
                                 ."Vous pouvez aussi partager une adoption partielle. Tous les dons au montant de 50$ ou de plus seront identifi&eacute;es "
                                 ."pour toujours dans la Biblioth&egrave;que des semences, et le montant total de chaque adoption est un don charitable "
                                 ."pour lequel le donneur recevra un re&ccedil;u.</P>"],
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
