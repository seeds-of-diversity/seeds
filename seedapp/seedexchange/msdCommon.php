<?php

/* msdCommon
 *
 * Copyright (c) 2011-2018 Seeds of Diversity Canada
 *
 * Member Seed Directory methods common to multiple applications
 */

include_once( SEEDCORE."SEEDBasket.php" );
include_once( SEEDAPP."basket/basketProductHandlers_seeds.php" );
include_once( SEEDAPP."basket/basketProductHandlers.php" );     // SEEDBasketProducts_SoD
include_once( SEEDLIB."msd/msdcore.php" );
include_once( SEEDLIB."msd/msdlib.php" );


class MSDCommonDraw
{
    public $oSB;
    private $oMSDCore;

    function __construct( SEEDBasketCore $oSB )     // actually this is MSDBasketCore
    {
        $this->oSB = $oSB;
        $this->oMSDCore = new MSDCore( $oSB->oApp );
    }

    function DrawMSDList()
    {
        $sMSDList = "";

        // Get all distinct categories (distinct prodextra-values for prodextra-key=='category' and product-type='seeds'),
        // and for each category get all distinct species (distinct prodextra-values for prodextra-key=='species').
        // The second query needs a double-join on prodextra, to fetch category and species together.
        $raCat = $this->oSB->oDB->GetList( "PxPE", "product_type='seeds' AND PE.k='category'",
                                           array('sGroupCol'=>'PE_v', 'sSortCol'=>'PE_v', 'raFieldsOverride'=>array('PE_v'=>'PE.v')) );
        foreach( $raCat as $ra ) {
            $raSpList = $this->oSB->oDB->GetList( "PxPE2", "product_type='seeds' AND PE1.k='category' "
                                                 ."AND PE1.v='".addslashes($ra['PE_v'])."' AND PE2.k='species'",
                                                  array('sGroupCol'=>'PE2_v', 'sSortCol'=>'PE2_v', 'raFieldsOverride'=>array('PE2_v'=>'PE2.v')) );

            $sCat = $this->oMSDCore->TranslateCategory( $ra['PE_v'] );
            // get all the species names and translate them
            $raSp = array();
            foreach( $raSpList as $ra2 ) {
                $raSp[] = $ra2['PE2_v'];
            }
            $raSp = $this->oMSDCore->TranslateSpeciesList( $raSp );

            $sMSDList .= "<div class='msd-list-category'>"
                            ."<div class='msd-list-category-title'>$sCat</div>"
                            ."<div class='msd-list-species-group'>"
                                // no bEnt because the &ent; characters below get escaped otherwise; escaping EN types below to prevent XSS
                                .SEEDCore_ArrayExpandRows( $raSp, "<div class='msd-list-species-title' kSpecies='[[kSpecies]]'>[[label]]</div>", false )
                            ."</div>"
                        ."</div>";
        }

        $sMSDList = "<div class='msd-list'>$sMSDList</div>";

        return( $sMSDList );
    }

private $eReportMode = 'VIEW-PUB';
private $bHideDetail = false;
private $kfrelG;
private $_lastCategory = "";
private $_lastType = "";
    function DrawVarietyFromKFR( KeyframeRecord $kfrS, $raParms = array() )
    {
        $sOut = "";

        $this->kfrelG = new Keyframe_Relation( $this->oSB->oDB->KFDB(), array('Tables'=>array('G'=>array("Table" => 'seeds.sed_curr_growers',"Fields" => "Auto"))), array() );

        $bNoSections = (@$raParms['bNoSections'] == true);  // true: you have to write the category/type headers yourself

        // mbrCode of the grower who offers this seed should only be displayed in interactive and layout modes, not public view mode
// it would make more sense for this to be available via a join
        $mbrCode = ($this->eReportMode != 'VIEW-PUB' &&
                    ($kfrG = $this->kfrelG->GetRecordFromDB( "mbr_id='".$kfrS->value('mbr_id')."'" )) )
                   ? $kfrG->value('mbr_code')
                   : "";

        if( !$bNoSections && $this->_lastCategory != $kfrS->value('category') ) {
            /* Start a new category
             */
            $sCat = $kfrS->value('category');
            if( $this->lang == 'FR' ) {
// should be a better accessor
                foreach( $this->oMSDCore->GetCategories() as $ra ) {
                    if( $ra['db'] == $kfrS->value('category') ) {
                        $sCat = $ra['FR'];
                        break;
                    }
                }
            }
            $sOut .= "<DIV class='sed_category'><H2>$sCat</H2></DIV>";
            $this->_lastCategory = $kfrS->value('category');
            // when Searching on a duplicated Type, it is possible to view more than one category with the same type, so this causes the second category to show the Type
            $this->_lastType = "";
        }
        if( !$bNoSections && $this->_lastType != $kfrS->value('type') ) {
            /* Start a new type
             */
            $sType = $kfrS->value('type');
            if( $this->eReportMode == 'LAYOUT' ) {
                if( ($sFR = @$this->raTypesCanon[$sType]['FR']) ) {
                    $sType .= " @T@ $sFR";
                }
            } else {
                if( $this->lang == 'FR' && isset($this->raTypesCanon[$sType]['FR']) ) {
                    $sType = $this->raTypesCanon[$sType]['FR'];
                }
            }
            $sOut .= "<DIV class='sed_type'><H3>$sType</H3></DIV>";
            $this->_lastType = $kfrS->value('type');
        }

        /* FloatRight contains everything that goes in the top-right corner
         */
        $sFloatRight = "";
        if( $this->eReportMode == 'EDIT' && !$kfrS->value('bSkip') && !$kfrS->value('bDelete') ) {
            switch( $kfrS->Value('eOffer') ) {
                default:
                case 'member':        $sFloatRight .= "<div class='sed_seed_offer sed_seed_offer_member'>Offered to All Members</div>";  break;
                case 'grower-member': $sFloatRight .= "<div class='sed_seed_offer sed_seed_offer_growermember'>Offered to Members who offer seeds in the Directory</div>";  break;
                case 'public':        $sFloatRight .= "<div class='sed_seed_offer sed_seed_offer_public'>Offered to the General Public</div>"; break;
            }
        }
        if( $this->eReportMode != 'LAYOUT' )  $sFloatRight .= "<div class='sed_seed_mc'>$mbrCode</div>";

        /* Buttons1 is the standard set of buttons : Edit, Skip, Delete
         * Buttons2 is Un-Skip and Un-Delete
         */
        $sButtons1 = "";//$this->getButtonsSeed1( $kfrS );
        $sButtons2 = "";//$this->getButtonsSeed2( $kfrS );

        /* Draw the seed listing
         */
        $s = "<b>".$kfrS->value('variety')."</b>"
            .( $this->eReportMode == 'LAYOUT'
               ? (" @M@ <b>$mbrCode</b>".$kfrS->ExpandIfNotEmpty( 'bot_name', "<br/><b><i>[[]]</i></b>" ))
               : ($kfrS->ExpandIfNotEmpty( 'bot_name', " <b><i>[[]]</i></b>" )))
            ;
        if( $this->eReportMode == "VIEW-MBR" ) {
            // Make the variety and mbr_code blue and clickable
            $s = "<span style='color:blue;cursor:pointer;' onclick='console01FormSubmit(\"ClickSeed\",".$kfrS->Key().");'>$s</span>";
        }

        $s .= $sButtons1;

        $s .= "<br/>";

        if( $this->eReportMode != "EDIT" || !$this->bHideDetail ) {
            $s .= $kfrS->ExpandIfNotEmpty( 'days_maturity', "[[]] dtm. " )
               // this doesn't have much value and it's readily mistaken for the year of harvest
               //  .($this->bReport ? "@Y@: " : "Y: ").$kfrS->value('year_1st_listed').". "
                 .$kfrS->value('description')." "
                 .$kfrS->ExpandIfNotEmpty( 'origin', (($this->eReportMode == "LAYOUT" ? "@O@" : "Origin").": [[]]. ") )
                 .$kfrS->ExpandIfNotEmpty( 'quantity', "<b><i>[[]]</i></b>" );

             if( ($price = $kfrS->Value('price')) != 0.00 ) {
                 $s .= " ".($this->lang=='FR' ? "Prix" : "Price")." $".$price;
             }
        }

        if( in_array($this->eReportMode, array("EDIT","REVIEW")) ) {
            // Show colour-coded backgrounds for Deletes, Skips, and Changes
            if( $kfrS->value('bDelete') ) {
                $s = "<div class='sed_seed_delete'><b><i>".($this->lang=='FR' ? "Supprim&eacute;" : "Deleted")."</i></b>"
                    .SEEDCore_NBSP("   ")
                    .$sButtons2
                    ."<br/>$s</div>";
            } else if( $kfrS->value('bSkip') ) {
                $sStyle = ($this->eReportMode == 'REVIEW') ? "style='background-color:#aaa'" : "";    // because this is used without <style>
                $s = "<div class='sed_seed_skip' $sStyle><b><i>".($this->lang=='FR' ? "Pass&eacute;" : "Skipped")."</i></b>"
                    .SEEDCore_NBSP("   ")
                    .$sButtons2
                    ."<br/>$s</div>";
            } else if( $kfrS->value('bChanged') ) {
                $s = "<div class='sed_seed_change'>$s</div>";
            }
        }

        // Put the FloatRight at the very top of the output block
        $s = "<div style='float:right'>$sFloatRight</div>".$s;

        if( in_array( $this->eReportMode, array('VIEW-MBR', 'VIEW-PUB', 'EDIT')) ) {
            // Wrap the seed listing with an id
            $sOut .= "<div class='sed_seed' id='Seed".$kfrS->Key()."'>$s</div>";
        } else {
            $sOut .= $s;
        }

        return( $sOut );
    }

// use the list in msdcore instead -- the method that uses this above is probably not used anymore
    private $raTypesCanon = array(
            'ALPINE COLUMBINE' => array( 'FR' => 'Ancolie des Alpes' ),
            'COLUMBINE' => array( 'FR' => 'Ancolie' ),
            'BACHELOR BUTTONS' => array( 'FR' => 'Bluet' ),
            'CALENDULA' => array( 'FR' => 'Souci' ),
            'CASTOR OIL PLANT' => array( 'FR' => 'Ricin' ),
            'COLUMBINE' => array( 'FR' => 'Ancolie' ),
            'COTTON' => array( 'FR' => 'Coton' ),
            'GAILLARDIA' => array( 'FR' => 'Gaillarde' ),
            'HOLLYHOCK'  => array( 'FR' => 'Tr&eacute;mi&egrave;re' ),
            'LATHYRUS (SWEET PEA)' => array( 'FR' => 'Pois de senteur' ),
            'LAVATERA' => array( 'FR' => 'Lavat&egrave;re' ),
            'LINUM (FLAX)' => array( 'FR' => "Lin" ),
            'MARIGOLD' => array( 'FR' => "Oeillets d'Inde" ),
            'MORNING GLORY' => array( 'FR' => 'Belle-de-jour' ),
            'NASTURTIUM' => array( 'FR' => 'Capucine' ),
            'OENOTHERA' => array( 'FR' => 'Onagre' ),
            'POPPY (PAPAVER)' => array( 'FR' => "Pavot" ),
            'SUNFLOWER' => array( 'FR' => 'Tournesol' ),


            'APPLE' => array( 'FR' => 'Pommes' ),
            'BLACK ELDERBERRY' => array( 'FR' => 'Baies de sureau' ),
            'BLACKBERRY' => array( 'FR' => 'M&ucirc;res' ),
            'CURRANT' => array( 'FR' => 'Groseilles' ),
            'GRAPE' => array( 'FR' => 'Raisins' ),
            'GARDEN HUCKLEBERRY' => array( 'FR' => 'Airelles' ),
            'LITCHI TOMATO' => array( 'FR' => 'Morelle de balbis' ),
            'MEDLAR' => array( 'FR' => 'N&eacute;flier' ),
            'MELON' => array( 'FR' => 'Melons' ),
            'MELON/MUSKMELON' => array( 'FR' => 'Melons/Cantaloups' ),
            'RHUBARB' => array( 'FR' => 'Rhubarbe' ),
            'STRAWBERRY' => array( 'FR' => 'Fraises' ),
            'WATERMELON' => array( 'FR' => "Past&egrave;ques melon d'eau" ),


            'BARLEY' => array( 'FR' => 'Orge' ),
            'OATS' => array( 'FR' => 'Avoine' ),
            'PEARL MILLET' => array( 'FR' => 'Mil &agrave; chandelle' ),
            'SORGHUM' => array( 'FR' => 'Sorgho' ),
            'WHEAT' => array( 'FR' => 'Bl&eacute;' ),


            'ANGELICA' => array( 'FR' => 'Ang&eacute;lique' ),
            'ANISE HYSSOP' => array( 'FR' => 'Agastache fenouil' ),
            'BASIL' => array( 'FR' => 'Basilic' ),
            'BLACK CUMIN' => array( 'FR' => 'Cumin' ),
            'BORAGE' => array( 'FR' => 'Bourrache' ),
            'CARAWAY' => array( 'FR' => 'Carvi' ),
            'CATNIP' => array( 'FR' => 'Cataire' ),
            'CELERY (SMALLAGE)' => array( 'FR' => 'C&eacute;l&eacute;ri' ),
            'CHAMOMILE' => array( 'FR' => 'Camomille' ),
            'CHERVIL' => array( 'FR' => 'Cerfeuil' ),
            'CHICORY' => array( 'FR' => 'Chicor&eacute;e' ),
            'CHIVES' => array( 'FR' => 'Ciboulette' ),
            'COMFREY' => array( 'FR' => 'Consoude' ),
            'CORIANDER/CILANTRO' => array( 'FR' => 'Coriandre persil arabe' ),
            'CRESS' => array( 'FR' => 'Cresson' ),
            'DILL' => array( 'FR' => 'Aneth' ),
            "DYER'S BROOM" => array( 'FR' => 'Gen&ecirc;t des teinturiers' ),
            'EDIBLE BURDOCK' => array( 'FR' => 'Bardane' ),
            'ELECAMPANE' => array( 'FR' => 'Aun&eacute;e' ),
            'EVENING PRIMROSE' => array( 'FR' => 'Oenoth&egrave;re onagre' ),
            'FENNEL' => array( 'FR' => 'Fenouil' ),
            'FENUGREEK' => array( 'FR' => 'Fenugrec' ),
            'FEVERFEW' => array( 'FR' => 'Grande camomille' ),
            'GARLIC CHIVES' => array( 'FR' => 'Ciboulette ail' ),
            'HOPS' => array( 'FR' => 'Houblon' ),
            'HOREHOUND' => array( 'FR' => 'Marrube' ),
            'HORSERADISH (ROOTS)' => array( 'FR' => 'Raifort' ),
            'HORSERADISH' => array( 'FR' => 'Raifort' ),
            "LAMB'S QUARTERS" => array( 'FR' => 'Ch&eacute;noposium' ),
            'LEMON BALM' => array( 'FR' => 'T&ecirc;te de dragon' ),
            'LOVAGE' => array( 'FR' => 'Liv&egrave;che' ),
            'MADDER' => array( 'FR' => 'Garance' ),
            'MALVA (MALLOW)' => array( 'FR' => 'Mauve' ),
            'MEXICAN TARRAGON' => array( 'FR' => 'Estragon Mexicain' ),
            'NETTLE' => array( 'FR' => 'Ortie' ),
            'OREGANO' => array( 'FR' => 'Origan' ),
            'PARSLEY' => array( 'FR' => 'Persil' ),
            'PEPPERGRASS' => array( 'FR' => 'Cresson al&eacute;nois' ),
            'POKEWEED' => array( 'FR' => "Raisin d'Am&eacute;rique" ),
            'POLYGONUM' => array( 'FR' => 'Renou&eacute;e des teinturiers' ),
            'SAGE' => array( 'FR' => 'Sauge' ),
            'SALAD BURNET' => array( 'FR' => 'Sanguisorba pimprenelle' ),
            'SORREL' => array( 'FR' => 'Oseille' ),
            "ST. JOHN'S WORT" => array( 'FR' => 'Millepertuis' ),
            'SUMMER SAVORY' => array( 'FR' => "Sarriette d'&eacute;t&eacute;" ),
            'SWEET CICELY' => array( 'FR' => 'Cerfeuil musqu&eacute;' ),
            'TANSY' => array( 'FR' => 'Tanasie' ),
            'TEASEL' => array( 'FR' => 'Card&egrave;re cultiv&eacute;e' ),
            'THYME' => array( 'FR' => 'Thym' ),
            'TOBACCO' => array( 'FR' => 'Tabac' ),
            'TULSI (HOLY BASIL)' => array( 'FR' => 'Basilic sacr&eacute;' ),
            'VALERIAN' => array( 'FR' => 'Val&eacute;riane' ),
            'WOAD' => array( 'FR' => 'Pastel' ),
            'WORMWOOD' => array( 'FR' => 'Absinthe' ),
/*
            '' => array( 'FR' => '' ),
*/
            'CHINESE ARTICHOKE' => array( 'FR' => 'Crosnes du Japon' ),
            'GROUND ALMOND' => array( 'FR' => 'Souchet' ),
            'ROBINIA' => array( 'FR' => 'Robinier' ),
            'SUMAC' => array( 'FR' => 'Vinaigrier' ),


            'AMARANTH' => array( 'FR' => 'Amarante' ),
            'ARUGULA' => array( 'FR' => 'Roquette' ),
            'ASPARAGUS' => array( 'FR' => 'Asperges' ),
            'BEAN/ADZUKI' => array( 'FR' => 'F&egrave;ves - Adzuki' ),
            'BEAN/BUSH' => array( 'FR' => 'F&egrave;ves - Plants nains' ),
            'BEAN/FAVA (BROAD)' => array( 'FR' => 'F&egrave;ves - Fava (Larges)' ),
            'BEAN/LIMA' => array( 'FR' => 'F&egrave;ves de Lima' ),
            'BEAN/OTHER' => array( 'FR' => 'F&egrave;ves et haricots - Divers' ),
            'BEAN/POLE' => array( 'FR' => 'F&egrave;ves - Plants grimpants' ),
            'BEAN/RUNNER' => array( 'FR' => "Haricots d'Espagne" ),
            'BEAN/SOY' => array( 'FR' => 'F&egrave;ves de soya' ),
            'BEAN/WAX/BUSH' => array( 'FR' => 'Haricots - Plants nains' ),
            'BEAN/WAX/POLE' => array( 'FR' => 'Haricots - Plants grimpants' ),
            'BEET' => array( 'FR' => 'Betteraves' ),
            'BEET/SUGAR' => array( 'FR' => 'Betteraves &agrave; sucre' ),
            'BROCCOLI' => array( 'FR' => 'Brocolis' ),
            'CABBAGE' => array( 'FR' => 'Choux' ),
            'CABBAGE/CHINESE' => array( 'FR' => 'Choux chinois' ),
            'CARROT' => array( 'FR' => 'Carottes' ),
            'CELERY' => array( 'FR' => 'C&eacute;leris' ),
            'CHICKPEA' => array( 'FR' => 'Pois chiches' ),
            'CORN SALAD' => array( 'FR' => 'M&acirc;che' ),
            'CORN/FLINT' => array( 'FR' => 'Ma&iuml;s corn&eacute;' ),
            'CORN/FLOUR' => array( 'FR' => 'Ma&iuml;s &agrave; farine' ),
            'CORN/POP' => array( 'FR' => 'Ma&iuml;s &agrave; souffler' ),
            'CORN/SWEET' => array( 'FR' => 'Ma&iuml;s sucr&eacute;' ),
            'COWPEA' => array( 'FR' => 'Doliques' ),
            'CUCUMBER/ANTILLES' => array( 'FR' => 'Concombre des Antilles' ),
            'CUCUMBER/KAYWA' => array( 'FR' => 'Concombre grimpant (Kaywa)' ),
            'CUCUMBER/MEXICAN SOUR GHERKIN' => array( 'FR' => 'Concombres &agrave; confire', 'EN'=> 'Cucumber - Mexican Sour Gherkin' ),
            'CUCUMBER/PICKLING' => array( 'FR' => 'Concombres &agrave; mariner', 'EN'=> 'Cucumber - Pickling' ),
            'CUCUMBER/SLICING' => array( 'FR' => 'Concombres frais', 'EN'=> 'Cucumber - Slicing' ),
            'EGGPLANT' => array( 'FR' => 'Aubergines' ),
            'GARLIC' => array( 'FR' => 'Ail' ),
            'GOURD/EDIBLE' => array( 'FR' => 'Gourdes comestibles' ),
            'GREENS' => array( 'FR' => 'Verdure' ),
            'GROUND ALMOND' => array( 'FR' => 'Souchet comestible amande de terre' ),
            'GROUND CHERRY' => array( 'FR' => 'Cerises de terre' ),
            'JERUSALEM ARTICHOKE' => array( 'FR' => 'Topinambour' ),
            'KALE' => array( 'FR' => 'Choux fris&eacute;s' ),
            'KOHLRABI' => array( 'FR' => 'Kohlrabi' ),
            'LEEK' => array( 'FR' => 'Poireaux' ),
            'LETTUCE/HEAD' => array( 'FR' => 'Laitues en pomme' ),
            'LETTUCE/LEAF' => array( 'FR' => 'Laitues en feuille' ),
            'LETTUCE/ROMAINE' => array( 'FR' => 'Laitues romaines' ),
            'MUSTARD/GREENS' => array( 'FR' => 'Moutarde en feuille' ),
            'OKRA' => array( 'FR' => 'Okra' ),
            'ONION' => array( 'FR' => 'Oignons' ),
            'ONION/GREEN' => array( 'FR' => 'Oignons verts' ),
            'ONION/MULTIPLIER/ROOT' => array( 'FR' => 'Oignons' ),
            'ONION/MULTIPLIER/TOP' => array( 'FR' => 'Oignons &eacute;gyptiens' ),
            'ORACH' => array( 'FR' => 'Arroche' ),
            'PARSNIP' => array( 'FR' => 'Panais' ),
            'PEA' => array( 'FR' => 'Pois' ),
            'PEA/EDIBLE PODDED' => array( 'FR' => 'Pois mange-tout' ),
            'PEPPER/HOT' => array( 'FR' => 'Piments forts' ),
            'PEPPER/OTHER' => array( 'FR' => 'Poivrons divers' ),
            'PEPPER/SWEET' => array( 'FR' => 'Poivrons doux' ),
            'POTATO' => array( 'FR' => 'Pommes de terre' ),
            'RADISH' => array( 'FR' => 'Radis' ),
            'SALSIFY/SCORZONERA' => array( 'FR' => 'Salsifis' ),
            'SKIRRET' => array( 'FR' => 'Chervis' ),
            'SPINACH' => array( 'FR' => '&Eacute;pinards', 'FR_sort' => 'Epinards' ),  // '&' initial is not good for sorting
            'SPINACH/MALABAR' => array( 'FR' => '&Eacute;pinards de Malabar', 'FR_sort' => 'Epinard de Malabar' ),  // '&' initial is not good for sorting
            'SPINACH/NEW ZEALAND' => array( 'FR' => '&Eacute;pinards T&eacute;tragone', 'FR_sort' => 'Epinard Tetragon' ),  // '&' initial is not good for sorting
            'SPINACH/STRAWBERRY' => array( 'FR' => '&Eacute;pinard-Fraise', 'FR_sort' => 'Epinard-Fraise' ),  // '&' initial is not good for sorting
            'SQUASH/MAXIMA' => array( 'FR' => 'Courges (Cucurbita maxima)' ),
            'SQUASH/MIXTA' => array( 'FR' => 'Courges (Cucurbita mixta)' ),
            'SQUASH/MOSCHATA' => array( 'FR' => 'Courges (Cucurbita moschata)' ),
            'SQUASH/PEPO' => array( 'FR' => 'Courges/Citrouilles (Cucurbita pepo)' ),
            'SWISS CHARD' => array( 'FR' => 'Bette &agrave; carde' ),
            'TOMATO/MISC OR MULTI-COLOUR' => array( 'FR' => 'Tomates - Couleurs diverses' ),
            'TOMATO/MISCELLANEOUS SPECIES' => array( 'FR' => 'Tomates - Esp&egrave;ces diverses' ),
            'TOMATO/PINK TO PURPLE SKIN' => array( 'FR' => 'Tomates - Peaux roses &agrave; pourpres' ),
            'TOMATO/RED SKIN' => array( 'FR' => 'Tomates - Peaux rouges' ),
            'TOMATO/YELLOW TO ORANGE SKIN' => array( 'FR' => 'Tomates - Peaux jaunes &agrave; oranges' ),
            'TURNIP' => array( 'FR' => 'Navets' ),
            'TURNIP - RUTABAGA' => array( 'FR' => 'Rutabagas' ),
    );


}

?>
