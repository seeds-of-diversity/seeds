<?php

/* MSDCore
 *
 * Copyright (c) 2018 Seeds of Diversity
 *
 *  Basic Member Seed Directory support built on top of SEEDBasket.
 */


class MSDCore
/************
    In general, this should only be used by seedlib-level code. App-level code should use MSDQ instead of this.
 */
{
    private $oApp;
    private $raConfig;
    private $oSBDB;

    function __construct( SEEDAppConsole $oApp, $raConfig = array() )
    {
        $this->oApp = $oApp;
        $this->raConfig = $raConfig;
        $this->oSBDB = new SEEDBasketDB( $oApp->kfdb, $oApp->sess->GetUID(), $oApp->logdir );
    }

    function GetSeedKeys( $set = "" )
    /********************************
        The official set of keys that make a seed product record that makes sense outside of SEEDBasket.
        i.e. not including things like product_type and quant_type which *define* the product.
     */
    {
        $kfrKeys = array( '_key', '_created', '_created_by', '_updated', '_updated_by' );   // not _status because we manage hidden/deleted using eStatus
        $prodKeys = array( 'uid_seller', 'eStatus', 'img', 'item_price' );
        $prodExtraKeys = array( 'category', 'species', 'variety', 'bot_name', 'days_maturity', 'days_maturity_seed',
                                'quantity', 'origin', 'description', 'eOffer', 'year_1st_listed' );

        switch( $set ) {
            default:
                return( array_merge($kfrKeys, $prodKeys, $prodExtraKeys ) );
            case 'PRODUCT':
                return( $prodKeys );
            case 'PRODEXTRA':
                return( $prodExtraKeys );
            case 'PRODUCT PRODEXTRA':
                return( array_merge( $prodKeys, $prodExtraKeys ) );
        }
    }

    function CreateSeedKfr()
    /***********************
     */
    {
        $kfr = $this->oSBDB->KFRel('P')->CreateRecord();

        // product defaults
        $kfr->SetValue( 'uid_seller', $this->oApp->sess->GetUID() );    // correct for single-user updater; multi-user editors will have to re-set this
        $kfr->SetValue( 'product_type', "seeds" );
        $kfr->SetValue( 'quant_type', "ITEM-1" );
        $kfr->SetValue( 'eStatus', 'ACTIVE' );
        $kfr->SetValue( 'item_price', '' );  // blank is the right default  '3.50' );

        // prodextra defaults
        $kfr->SetValue( 'eOffer', "member" );
        $kfr->SetValue( 'year_1st_listed', $this->currYear );

        return( $kfr );
    }

    function GetSeedKfr( $kProduct )
    /*******************************
        Get the kfr for this product and store prodextra values in the kfr too.
        Only store the standard msd prodextra keys so nobody can overwrite a crucial product field in the kfr by using a prodextra with that name
     */
    {
        $kfrP = null;

        if( $kProduct && ($kfrP = $this->oSBDB->GetKFR( 'P', $kProduct )) ) {
            $raPE = $this->oSBDB->GetProdExtraList( $kProduct );
            foreach( $this->GetSeedKeys('PRODEXTRA') as $k ) {
                $kfrP->SetValue( $k, @$raPE[$k] );
            }
        }
        return( $kfrP );
    }

    function GetSeedRAFromKfr( KeyframeRecord $kfrS )
    /************************************************
        kfrS is a SEEDBasket_Product
        Return an array of standard msd seed values. The kfr must have come from one of the methods above so it has prodextra information included in it.
     */
    {
        $raOut = array();

        foreach( $this->GetSeedKeys('ALL') as $k ) {
            $raOut[$k] = $kfrS->Value($k);
        }

        return( $raOut );
    }

    function PutSeedKfr( KeyframeRecord $kfrS )
    /******************************************
        kfrS is a SEEDBasket_Product
        Save a seed kfr to database. It must already be validated (or move validation code here?)
     */
    {
        // Save/update the product and get a product key if it's a new row.  Then save/update all the prodextra items.
        if( ($bOk = $kfrS->PutDBRow()) ) {
            foreach( $this->GetSeedKeys('PRODEXTRA') as $k ) {
                $this->oSBDB->SetProdExtra( $kfrS->Key(), $k, $kfrS->Value($k) );
            }
        }
        return( $bOk );
    }

    function SeedCursorOpen( $cond )
    {
// could just do $kfrcP = $this->oDB->GetKFRC( "P", "product_type='seeds' ".($cond ? "AND $cond " : "") if sorting is not required

// since the PE columns are just brought in for sorting, it makes sense for them to be left-joined so we don't lose products that are missing one of the PE
        $kfrcP = $this->oSBDB->GetKFRC( "PxPE3", "product_type='seeds' ".($cond ? "AND $cond " : "")
                                       ."AND PE1.k='category' "
                                       ."AND PE2.k='species' "
                                       ."AND PE3.k='variety' ",
                                       array('sSortCol'=>'PE1_v,PE2_v,PE3_v') );
        return( $kfrcP );
    }

    function SeedCursorFetch( KeyframeRecord &$kfrP )
    /************************************************
        kfrS is a SEEDBasket_Product
     */
    {
        if( ($ok = $kfrP->CursorFetch()) ) {
            $raPE = $this->oSBDB->GetProdExtraList( $kfrP->Key() );
            foreach( $this->GetSeedKeys('PRODEXTRA') as $k ) {
                $kfrP->SetValue( $k, @$raPE[$k] );
            }
        }
        return( $ok );
    }

    function GetCategories() { return( $this->raCategories ); }

    function TranslateCategory( $sCat )
    {
        return( @$this->raCategories[$sCat][$this->oApp->lang] );
    }


    function TranslateSpecies( $sSpecies )
    {
        return( $this->oApp->lang == 'FR' && @$this->raSpecies[$sSpecies]['FR']
                    ? $this->raSpecies[$sSpecies]['FR']
                    : $sSpecies );
    }

    function TranslateSpeciesList( $raSpecies )
    /******************************************
        Given a list of species names, translate to current language and sort

        Input: array( spname1, spname2, ... )
        Output: array( array('kSpecies'=>kSp1, 'label'=>spname1_translated), array('kSpecies'=>kSp2, 'label'=>spname2_translated), ... )
     */
    {
        $raOut = array();

        foreach( $raSpecies as $k ) {
            $kKlugeSpeciesKey = $this->getKlugeSpeciesKey( $k );

            if( $this->oApp->lang == 'FR' && isset($this->raSpecies[$k]['FR']) ) {
                // This would be great except for words like &Eacute;pinards (spinach) that start with a '&' which sorts to the top.
                // Something like $k = html_entity_decode( $this->raTypesCanon[$v]['FR'], ENT_COMPAT, 'ISO8859-1' );
                // would be great, to collapse the entity back to a latin-1 character except you have to set a French collation using setlocale
                // to make the sorting work, else the accented E sorts after z. And who knows how portable the setlocale will be - is fr_FR or fr_CA
                // language pack installed?
                // So the brute force method works best, though it will be a challenge if we want to get these names out of SEEDLocal if they have
                // accented letters at or near the first char.

                // use a non-accented version of the name for sorting, and accented version for display
                $kSort = @$this->raSpecies[$k]['FR_sort'] ?: $this->raSpecies[$k]['FR'];
                $label = $this->raSpecies[$k]['FR'];
            } else {
                $kSort = $k;
                $label = $k;
            }
            $raOut[$kSort] = array( 'label' => $label, 'kSpecies' => $kKlugeSpeciesKey );
        }
        ksort( $raOut );
        return( $raOut );
    }

    private function getKlugeSpeciesKey( $sp )
    {
        // this is a cheater way to pass a "species" value as a number
        $k = $this->oApp->kfdb->Query1( "SELECT _key FROM seeds.SEEDBasket_ProdExtra WHERE k='species' AND v='".addslashes($sp)."'" );
        return( $k );
    }


    function GetKlugeSpeciesNameFromKey( $kSp )
    {
        // this is a cheater way to pass a "species" value as a number
        $k = $this->oApp->kfdb->Query1( "SELECT v FROM seeds.SEEDBasket_ProdExtra WHERE _key='$kSp'" );
        return( $k );
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

    private $raSpecies = array(
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

