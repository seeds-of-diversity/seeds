<?php

/* msdCommon
 *
 * Copyright (c) 2017 Seeds of Diversity Canada
 *
 * Member Seed Directory methods common to multiple applications
 */

include_once( SEEDCORE."SEEDBasket.php" );
include_once( SEEDAPP."basket/basketProductHandlers_seeds.php" );
include_once( SEEDAPP."basket/basketProductHandlers.php" );     // SEEDBasketProducts_SoD


class MSDCommonDraw
{
    public $oSB;

    function __construct( SEEDBasketCore $oSB )     // actually this is MSDBasketCore
    {
        $this->oSB = $oSB;
    }

    function DrawMSDList()
    {
        $sMSDList = "";

        // Get all distinct categories (distinct prodextra-values for prodextra-key=='category' and product-type='seeds'),
        // and for each category get all distinct species (distinct prodextra-values for prodextra-key=='species').
        // The second query needs a double-join on prodextra, to fetch category and species together.
        $raCat = $this->oSB->oDB->GetList( "PxPE", "product_type='seeds' AND PE.k='category'",
                                           array('sGroupCol'=>'PE_v', 'sSortCol'=>'PE_v', 'raFieldsOverride'=>array('PE_v'=>'v')) );
        foreach( $raCat as $ra ) {
            $raSp = $this->oSB->oDB->GetList( "PxPE2", "product_type='seeds' AND PE1.k='category' "
                                             ."AND PE1.v='".addslashes($ra['PE_v'])."' AND PE2.k='species'",
                                              array('sGroupCol'=>'PE2_v', 'sSortCol'=>'PE2_v', 'raFieldsOverride'=>array('PE2_v'=>'v')) );

            $sCat = $this->translateCategory( $ra['PE_v'] );
            $raSp = $this->translateTypes( $raSp );

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

    function translateCategory( $sCatEN )
    {
        if( $this->oSB->oW->lang == 'EN' )  return( $sCatEN );

        foreach( $this->raCategories as $k => $ra ) {
            if( $ra['db'] == $sCatEN )  return( $ra['FR'] );
        }
        return( $sCatEN );
    }

    function translateTypes( $raTypes )
    {
        $raOut = array();

        foreach( $raTypes as $ra ) {
            $k = $ra['PE2_v'];

            $kKlugeTypeKey = $this->oSB->GetKlugeTypeKeyFromName( $k );

            if( $this->oSB->oW->lang == 'FR' && isset($this->raTypesCanon[$k]['FR']) ) {
                // This would be great except for words like &Eacute;pinards (spinach) that start with a '&' which sorts to the top.
                // Something like $k = html_entity_decode( $this->raTypesCanon[$v]['FR'], ENT_COMPAT, 'ISO8859-1' );
                // would be great, to collapse the entity back to a latin-1 character except you have to set a French collation using setlocale
                // to make the sorting work, else the accented E sorts after z. And who knows how portable the setlocale will be - is fr_FR or fr_CA
                // language pack installed?
                // So the brute force method works best, though it will be a challenge if we want to get these names out of SEEDLocal if they have
                // accented letters at or near the first char.
                if( isset($this->raTypesCanon[$k]['FR_sort']) ) {
                    $kSort = $this->raTypesCanon[$k]['FR_sort'];   // use a non-accented version of the name for sorting, and accented version for display
                } else {
                    $kSort = $this->raTypesCanon[$k]['FR'];
                }
                $raOut[$kSort] = array( 'label' => $this->raTypesCanon[$k]['FR'], 'kSpecies' => $kKlugeTypeKey );
            } else {
                $raOut[$k] = array( 'label' => SEEDCore_HSC($k), 'kSpecies' => $kKlugeTypeKey );    // HSC because !bEnt is used by the caller
            }
        }
        ksort( $raOut );
        return( $raOut );
    }

    private $raCategories = array(
            'flowers'    => array( 'db' => "FLOWERS AND WILDFLOWERS", 'EN' => "Flowers and Wildflowers", 'FR' => "Fleurs et gramin&eacute;es sauvages et ornementales" ),
            'vegetables' => array( 'db' => "VEGETABLES",              'EN' => "Vegetables",              'FR' => "L&eacute;gumes" ),
            'fruit'      => array( 'db' => "FRUIT",                   'EN' => "Fruits",                  'FR' => "Fruits" ),
            'herbs'      => array( 'db' => "HERBS AND MEDICINALS",    'EN' => "Herbs and Medicinals",    'FR' => "Fines herbes et plantes m&eacute;dicinales" ),
            'grain'      => array( 'db' => "GRAIN",                   'EN' => "Grains",                  'FR' => "C&eacute;r&eacute;ales" ),
            'trees'      => array( 'db' => "TREES AND SHRUBS",        'EN' => "Trees and Shrubs",        'FR' => "Arbres et arbustes" ),
            'misc'       => array( 'db' => "MISC",                    'EN' => "Miscellaneous",           'FR' => "Divers" ),
        );

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
            'MARIGOLD' => array( 'FR' => "Oeillets d'Inde" ),
            'MORNING GLORY' => array( 'FR' => 'Belle-de-jour' ),
            'NASTURTIUM' => array( 'FR' => 'Capucine' ),
            'OENOTHERA' => array( 'FR' => 'Onagre' ),
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
            'ANISE HYSSOP' => array( 'FR' => 'Agastache' ),
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
            'SUMMER SAVORY' => array( 'FR' => 'Sarriette' ),
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
            'SPINACH/MALABAR' => array( 'FR' => '&Eacute;pinard de Malabar', 'FR_sort' => 'Epinard de Malabar' ),  // '&' initial is not good for sorting
            'SPINACH/STRAWBERRY' => array( 'FR' => '&Eacute;pinard-Fraise', 'FR_sort' => 'Epinard-Fraise' ),  // '&' initial is not good for sorting
            'SQUASH/MAXIMA' => array( 'FR' => 'Courges (Cucurbita maxima)' ),
            'SQUASH/MIXTA' => array( 'FR' => 'Courges (Cucurbita mixta)' ),
            'SQUASH/MOSCHATA' => array( 'FR' => 'Courges (Cucurbita moschata)' ),
            'SQUASH/PEPO' => array( 'FR' => 'Courges/Citrouilles (Cucurbita pepo)' ),
            'SWISS CHARD' => array( 'FR' => 'Bette &agrave; carde' ),
            'TOMATO/MISC OR MULTI-COLOUR' => array( 'FR' => 'Tomates - Couleurs diverses' ),
            'TOMATO/MISC SPECIES' => array( 'FR' => 'Tomates - Esp&egrave;ces diverses' ),
            'TOMATO/PINK TO PURPLE SKIN' => array( 'FR' => 'Tomates - Peaux roses &agrave; pourpres' ),
            'TOMATO/RED SKIN' => array( 'FR' => 'Tomates - Peaux rouges' ),
            'TOMATO/YELLOW TO ORANGE SKIN' => array( 'FR' => 'Tomates - Peaux jaunes &agrave; oranges' ),
            'TURNIP' => array( 'FR' => 'Navets' ),
            'TURNIP/RUTABAGA' => array( 'FR' => 'Rutabagas' ),
    );


}

?>