<?php

/* MSDCore
 *
 * Copyright (c) 2018-2024 Seeds of Diversity
 *
 *  Basic Member Seed Directory support built on top of SEEDBasket.
 */

include_once( SEEDCORE."SEEDBasket.php" );
include_once( SEEDLIB."mbr/MbrContacts.php" );
include_once( SEEDAPP."basket/basketProductHandlers.php" ); // SEEDBasketProducts_SoD


class MSDCore
/************
    In general, this should only be used by seedlib-level code. App-level code should use MSDQ instead of this.
 */
{
    public  $oApp;
    private $raConfig;
    public  $oMSDSB;
    private $oSBDB;
    private $currYear;
    private $dbname1;
    private $dbname2;

    private $bShutdown = false;      // can't order seeds right now; also set mseClosed variable in msd.php

    function __construct( SEEDAppConsole $oApp, $raConfig = array() )
    /****************************************************************
        raConfig: sbdb     = config_KFDB name of db where the MSD's SEEDBasket lives. Defaults to 'seeds1'.
                             This cannot be taken from oApp because sometimes you authenticate on a different db.
                  currYear = the current year for entering MSD entries
     */
    {
        // shut down for everyone except Bob
        //$this->bShutdown = ($oApp->sess->GetUID() != 1499);

        $this->oApp = $oApp;
        $this->raConfig = $raConfig;

        $this->dbname1 = $this->oApp->GetDBName('seeds1');
        $this->dbname2 = $this->oApp->GetDBName('seeds2');

        $this->oMSDSB = new MSDBasketCore( $oApp );
        $this->oSBDB = new SEEDBasketDB( $oApp->kfdb, $oApp->sess->GetUID(), $oApp->logdir,
                                         // create these kfrels in oSBDB
                                         ['raCustomProductKfrelDefs' => ['PxPEMSD' => $this->GetSeedKeys('PRODEXTRA')],
                                          'fnInitKfrel' => [$this,'mseInitKfrel'],      // initKfrel calls this to define additional Named Relations
                                          'sbdb' => @$raConfig['sbdb'] ?: 'seeds1'
                                         ] );
        $this->currYear = @$raConfig['currYear'] ?: date("Y", time()+3600*24*150 );  // year of 150 days from now (so Aug-Dec,Jan-Jul is same year as Jan)
    }

    function mseInitKfrel()
    /**********************
        Return additional defs for Named Relations.
        e.g. PxCATEGORY_SPECIES joins PxPExPE such that PEcategory.v and PEspecies.v are the columns containing those values
                                                    and PEcategory_v and PEspecies_v are the column aliases
     */


    {
        $pdef = ['P' => ['Table' => "{$this->dbname1}.SEEDBasket_Products",
                         'Fields' => "Auto"] ];
        $gdef = ['G' => ['Table' => "{$this->dbname1}.sed_curr_growers",
                         'JoinOn' => "P.uid_seller=G.mbr_id",
                         'Fields' => "Auto"] ];

        // relation-name => kfdef
        $kdef = ['PxCATEGORY'                   => ['Tables' => $pdef + $this->_kdef('category') ],
                 'PxCATEGORYxSPECIES'           => ['Tables' => $pdef + $this->_kdef('category') + $this->_kdef('species') ],
                 'PxCATEGORYxSPECIESxVARIETY'   => ['Tables' => $pdef + $this->_kdef('category') + $this->_kdef('species') + $this->_kdef('variety') ],
                 'PxG'                          => ['Tables' => $pdef + $gdef ],
                 'PxGxCATEGORYxSPECIES'         => ['Tables' => $pdef + $gdef + $this->_kdef('category') + $this->_kdef('species') ],
                 'PxGxCATEGORYxSPECIESxVARIETY' => ['Tables' => $pdef + $gdef + $this->_kdef('category') + $this->_kdef('species') + $this->_kdef('variety') ],
            'PxGxCATEGORYxSPECIESxVARIETYxDESC' => ['Tables' => $pdef + $gdef + $this->_kdef('category') + $this->_kdef('species') + $this->_kdef('variety') + $this->_kdef('description') ],
        ];

        return( $kdef );
    }
    private function _kdef( $k )
    {
        // table-alias => table-def
        return( ["PE{$k}" => ['Table' => "{$this->dbname1}.SEEDBasket_ProdExtra",
                              'Type' => 'Join',
                              'JoinOn' => "PE{$k}.fk_SEEDBasket_Products=P._key AND PE{$k}.k='$k'",
                              'Fields' => "Auto"] ] );
    }

    /* Current year can be set at constructor, default is the year that would apply to Aug-Dec,Jan-Jun (which is the year of Jan in this range).
     * FirstDayForCurrYear is Aug 1 of the year prior to CurrYear.
     */
    function GetCurrYear()            { return( $this->currYear ); }
    function GetFirstDayForCurrYear() { return( (intval($this->currYear)-1)."-08-01" ); }

    /* Permissions are defined by MSD = what each member can do with their own listings
     *                            MSDOffice = what office volunteers and staff can do with the whole directory
     *
     *      MSD:R         you can read your own information in the msd (we don't use this because any member can)
     *      MSD:W         you can edit your own information (we probably don't use this because any member can)
     *      MSD:A         we don't use this because magical abilities are given by MSDOffice:A
     *      MSDOffice:R   you can look at anyone's non-public information (like their INACTIVE seeds)
     *      MSDOffice:W   you can edit anyone's seeds
     *      MSDOffice:A   you can do magical things with the Member Seed Directory
     */
    //function PermMSDR()
    //function PermMSDW()
    //function PermOfficeR()
    function PermOfficeW()  { return( $this->oApp->sess->CanWrite('MSDOffice') || $this->PermAdmin() ); }
    function PermAdmin()    { return( $this->oApp->sess->CanAdmin('MSDOffice') ); }

    /**
     * SQL condition on a PxGx* relation to return only seeds that are listable (i.e. to be shown to people viewing the list; might not be requestable)
     * @return string
     */
    function CondIsListable()
    {
                                      // this is CondIsGrowerListable('G')
        return( "eStatus='ACTIVE' AND NOT (G.bHold OR G.bSkip OR G.bDelete) AND {$this->CondIsGrowerDone('G')}" );
    }


    /*********************************************
        The grower Done checkbox records the date when it was checked. Return true if that happened during the CurrYear (Aug-Dec,Jan-Jul have CurrYear of Jan's year).
     */
    function IsGrowerDone( KeyframeRecord $kfrG )
    {
        return( $kfrG && $kfrG->Value('dDone') && $this->IsGrowerDoneFromDate($kfrG->Value('dDone')) );
    }
    function IsGrowerDoneFromDate( string $dDone )
    {
        return( $dDone && $dDone > $this->GetFirstDayForCurrYear() );
    }
    function CondIsGrowerDone( string $prefix = '' )
    /***********************************************
        sql cond for testing if Done status is set in a grower record
     */
    {
        if( $prefix )  $prefix = "{$prefix}.";
        return( "( {$prefix}dDone<>'' AND {$prefix}dDone > '{$this->GetFirstDayForCurrYear()}' )" );
    }
    function CondIsGrowerListable( string $prefix = '' )
    /***************************************************
        sql condition for testing if a grower should appear in a public Growers list
     */
    {
        if( $prefix )  $prefix = "{$prefix}.";
        return( "( NOT (G.bHold OR G.bSkip OR G.bDelete) AND G.nTotal<>0 AND {$this->CondIsGrowerDone('G')} )" );
    }


    // deprecate, use the indirection instead because this is a low-level (even oSBDB kind of thing)
    function GetSeedKeys( $set = "" ) { return( $this->oMSDSB->GetSeedKeys($set) ); }

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

    function GetSeedRAFromKfr( KeyframeRecord $kfrS, $raParms = array() )
    /********************************************************************
        kfrS is a SEEDBasket_Product
        Return an array of standard msd seed values. The kfr must have come from one of the methods above so it has prodextra information included in it.

        Values in the kfrS are always cp1252: use $raParms['bUTF8'] to get all fields in utf8
     */
    {
        $raOut = array();

        $bUTF8 = @$raParms['bUTF8'];

        foreach( $this->GetSeedKeys('ALL') as $k ) {
            $v = $kfrS->Value($k);
            $raOut[$k] = $bUTF8 ? SEEDCore_utf8_encode($v) : $v;
        }
        // the above does raOut['_key']=value('_key') which doesn't actually work, so overwrite it
        $raOut['_key'] = $kfrS->Key();

        return( $raOut );
    }

    function GetSeedKFRC( $sCond, $raKFRParms = array() )
    {
        $sCond .= ($sCond ? " AND " : "")."P.product_type='seeds'";
        return( $this->oSBDB->GetKFRC( 'PxPEMSD', $sCond, $raKFRParms ) );   // use custom SBDB kfrel
    }

    function GetSeedSql( $sCond, $raKFRParms = array() )
    {
        $sCond .= ($sCond ? " AND " : "")."P.product_type='seeds'";
        return( $this->oSBDB->GetKfrel('PxPEMSD')->GetSQL( $sCond, $raKFRParms ) );   // use custom SBDB kfrel
    }

    function PutSeedKfr( KeyframeRecord $kfrS )
    /******************************************
        kfrS is a SEEDBasket_Product
        Save a seed kfr to database. It must already be validated (or move validation code here?)

        Values in the kfrS are always cp1252 to convert them before SetValue before calling this
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

    const REQUESTABLE_YES = 'YES';
    const REQUESTABLE_NO_NOLOGIN = 'NO_NOLOGIN';
    const REQUESTABLE_NO_INACTIVE = 'NO_INACTIVE';
    const REQUESTABLE_NO_OUTOFSEASON = 'NO_OUTOFSEASON';
    const REQUESTABLE_NO_NONGROWER = 'NO_NONGROWER';

    function IsRequestableByUser( $kfrS )
    /************************************
        Return YES if the currently logged-in user is allowed to request this seed
     */
    {
        $eReq = self::REQUESTABLE_NO_INACTIVE;

        if( $this->bShutdown )  goto done;
        if( $kfrS->value('eStatus') != 'ACTIVE' )  goto done;

        // check whether this seed is within its requestable period
        // for now all seeds are out of season
        if( false
            // also code this into CondIsListableAndRequestable(kUserRequesting) to evaluate below plus if eOffer==grower-member that kUser's nTotal>0 and dDone>FirstDayForCurrentYear
            // $kfrS->Value('eDateRange')=='use_range' && date() between $kfrS->value('dDateRangeStart') and $kfrS->Value('dDateRangeEnd')
            ) {
            $eReq = self::REQUESTABLE_NO_OUTOFSEASON;
            goto done;
        }

        $eReq = self::REQUESTABLE_YES;

        // this should be obtained by the caller and used everywhere
        $kfrBetter = $this->GetSeedKfr($kfrS->Key());

        switch( $kfrBetter->value('eOffer') ) {
            default:
            case 'member':
                // I am a member
// use a different method to determine membership
//                $ok = $this->oApp->sess->CanRead( 'sed' );
                break;
            case 'grower-member':
                // I am a member offering seeds
// use a different method to determine membership
                if( // $this->oApp->sess->CanRead( 'sed' ) &&
                     !($this->oApp->kfdb->Query1( "SELECT count(*) FROM {$this->dbname1}.SEEDBasket_Products "
                                                 ."WHERE uid_seller='".$this->oApp->sess->GetUID()."' AND "
                                                       ."product_type='seeds' AND "
                                                       ."eStatus='ACTIVE' AND "
                                                       ."_status='0'" )) )
                {
                    $eReq = self::REQUESTABLE_NO_NONGROWER;
                }
                break;
            case 'public':
                // anyone can request these seeds
//                $ok = true;
                break;
        }

        done:
        return( $eReq );
    }

    function GetMSEStats( array $raOut )
    {
        $raSpList = $this->LookupSpeciesList( "", ['bListable'=>true] );

        $raOut['nSpecies'] = count($raSpList);
        foreach( $raSpList as $ra ) {
            if( $ra['category'] == 'flowers' )     ++$raOut['nFlowers'];
            if( $ra['category'] == 'vegetables' )  ++$raOut['nVeg'];
            if( $ra['category'] == 'fruit' )       ++$raOut['nFruit'];
            if( $ra['category'] == 'herbs' )       ++$raOut['nHerbs'];
            if( $ra['category'] == 'grain' )       ++$raOut['nGrain'];
            if( $ra['category'] == 'trees' )       ++$raOut['nTrees'];
            if( $ra['category'] == 'misc' )        ++$raOut['nMisc'];
        }

        $raOut['nVarieties'] = $this->oSBDB->GetCount( 'PxGxCATEGORYxSPECIESxVARIETY', $this->CondIsListable('G'),
                                                       ['sGroupAliases'=>'PEcategory_v,PEspecies_v,PEvariety_v'] );

        return( $raOut );
    }

    function LookupCategoryList( string $sCond = "" )
    {
        $raOut = [];

        if( ($ra = $this->oSBDB->GetList('PxCATEGORY', $sCond, ['sGroupAliases'=>'PEcategory_v'])) ) {
            $raOut = array_column($ra, 'PEcategory_v');
        }
        return( $raOut );
    }

    function LookupSpeciesList( string $sCond = "", array $raParms = [] )
    /********************************************************************
        Return [category, species, klugeKey2]... for all distinct category,species

        raParms: category   = limit to a category
                 bListable  = limit to species that have LISTABLE seeds

        N.B. klugeKey2 is totally different than the older kluge keys used in other methods
     */
    {
        $raOut = [];

        if( ($cat = @$raParms['category']) ) {
            $sCond .= ($sCond ? " AND " : "")."PEcategory.v='".addslashes($cat)."'";
        }

        if( @$raParms['bListable'] ) {
            $sCond .= ($sCond ? " AND " : "").$this->CondIsListable('G');
        }

        /* kluge 1: klugeKey is one random key of a Product that has a given category,species
         *          That means PxCATEGORYxSPECIES where P._key=$klugeKey will give you that category,species (until the database changes)
         *          and SELECT * FROM SEEDBasket_ProdExtra WHERE fk_SEEDBasketProducts=$klugeKey will give you that too (in a random product with those two values)
         *
         * kluge 2: For klugeKey we get MAX(P._key) as an arbitrary key that maps to category,species.
         *          The problem with novel aliases in KF is only cols known in the kfrel are copied into the kfr, so we don't yet have a way to get "MAX(P._key) as klugeKey".
         *          Some day maybe novel aliases will be added to the code that copies db values to the kfr.
         *          Until then, the code below uses the existing but unused v_i1 column. Note that this does not alter v_i1 in the db, just in the kfr.
         *          Don't re-write these records to the db.
         */
        $raSp = $this->oSBDB->GetList( 'PxGxCATEGORYxSPECIES', $sCond,
                                       ['sGroupAliases'=>'PEcategory_v,PEspecies_v',
                                        'raFieldsOverride'=>['PEcategory_v'=>'PEcategory.v','PEspecies_v'=>'PEspecies.v',
                                                             'v_i1'=>'MAX(P._key)']     // v_i1 is a kluge to retrieve a novel alias's value
                                       ] );
        foreach( $raSp as $ra ) {
            $raOut[] = ['category'=>$ra['PEcategory_v'], 'species'=>$ra['PEspecies_v'], 'klugeKey2'=>$ra['v_i1']];   // now throw away $ra containing v_i1 because it's a bad kluge
        }

        return( $raOut );
    }

    function GetSpeciesNameFromKlugeKey2( int $klugeKey2 )
    {
        // klugeKey2 is an arbitrary SEEDBasket_Products key that identifies a category,species (and nothing else)
        $kfr = $this->oSBDB->GetKfr('PxCATEGORYxSPECIES', $klugeKey2);
        return( [$kfr ? $kfr->Value('PEspecies_v') : "",
                 $kfr ? $kfr->Value('PEcategory_v') : ""] );
    }

    function SeedCursorOpen( $cond, $raParms = [] )
    {
        $sSortCol = @$raParms['sSortCol'] ?: "PEcategory_v,PEspecies_v,PEvariety_v";

        $kfrcP = $this->oSBDB->GetKFRC( "PxGxCATEGORYxSPECIESxVARIETY",
                                        "product_type='seeds' ".($cond ? "AND $cond " : "")
                                       ."AND PEcategory.k='category' "
                                       ."AND PEspecies.k='species' "
                                       ."AND PEvariety.k='variety' ",
                                       ['sSortCol'=>$sSortCol] );
        return( $kfrcP );
    }

    function SeedCursorOpen2( $rel, $cond, $raParms = [] )
    /*****************************************************
        Like SeedCursorOpen but you can specify the named relation. You probably have to add join constraints in the $cond too.
     */
    {
        $sSortCol = @$raParms['sSortCol'] ?: "PEcategory_v,PEspecies_v,PEvariety_v";

        $kfrcP = $this->oSBDB->GetKFRC( $rel,
                                        "product_type='seeds' ".($cond ? "AND $cond " : "")
                                       ."AND PEcategory.k='category' "
                                       ."AND PEspecies.k='species' "
                                       ."AND PEvariety.k='variety' ",
                                       ['sSortCol'=>$sSortCol] );
        return( $kfrcP );
    }

    function SeedCursorFetch( KeyframeRecord $kfrP )
    /***********************************************
        kfrP is a SEEDBasket_Product x sed_curr_growers x PRODEXTRA
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
        return(@$this->raCategories[$sCat][$this->oApp->lang] ?? "");
    }


    function TranslateSpecies( $sSpecies )
    {
        return( $this->oApp->lang == 'FR' && @$this->raSpecies[$sSpecies]['FR']
                    ? $this->raSpecies[$sSpecies]['FR']
                    : $sSpecies );
    }

    function TranslateSpecies2( $sSpecies )
    {
        return( @$this->raSpecies[$sSpecies]['FR'] );
    }

    function TranslateSpeciesList( $raSpList )
    /*****************************************
        Given a list of species names, translate to current language and sort

        Input: array( array('klugeKey2'=>kProd1, 'species'=>sSpecies, 'category'=>sCategory), ...)
        Output: array( array('kSpecies'=>kSp1, 'label'=>spname1_translated), array('kSpecies'=>kSp2, 'label'=>spname2_translated), ... )
     */
    {
        $raOut = array();

        foreach( $raSpList as $ra ) {
            $kKlugeSpeciesKey = $ra['klugeKey2'];

            if( $this->oApp->lang == 'FR' && isset($this->raSpecies[$ra['species']]['FR']) ) {
                // This would be great except for words like &Eacute;pinards (spinach) that start with a '&' which sorts to the top.
                // Something like $k = html_entity_decode( $this->raTypesCanon[$v]['FR'], ENT_COMPAT, 'ISO8859-1' );
                // would be great, to collapse the entity back to a latin-1 character except you have to set a French collation using setlocale
                // to make the sorting work, else the accented E sorts after z. And who knows how portable the setlocale will be - is fr_FR or fr_CA
                // language pack installed?
                // So the brute force method works best, though it will be a challenge if we want to get these names out of SEEDLocal if they have
                // accented letters at or near the first char.

                // use a non-accented version of the name for sorting, and accented version for display
                $kSort = @$this->raSpecies[$ra['species']]['FR_sort'] ?: $this->raSpecies[$ra['species']]['FR'];
                $label = $this->raSpecies[$ra['species']]['FR'];
            } else {
                $kSort = $ra['species'];
                $label = $ra['species'];
            }
            $raOut[$kSort] = array( 'label' => $label, 'kSpecies' => $kKlugeSpeciesKey );
        }
        ksort( $raOut );
        return( $raOut );
    }

    function GetKlugeSpeciesNameFromKey( $kSp )
    {
        // this is a cheater way to pass a "species" value as a number
        $k = $this->oApp->kfdb->Query1( "SELECT v FROM {$this->dbname1}.SEEDBasket_ProdExtra WHERE _key='$kSp'" );
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
            'BACHELOR BUTTONS'              => ['FR' => 'Bluet'],
            'CALENDULA' => array( 'FR' => 'Souci' ),
            'CASTOR OIL PLANT' => array( 'FR' => 'Ricin' ),
            'COLUMBINE' => array( 'FR' => 'Ancolie' ),
            'CORNFLOWER'                    => ['FR' => 'Bluet'],
            'COTTON' => array( 'FR' => 'Coton' ),
            "FOUR O'CLOCKS"                 => ['FR'=>"Belles de nuit"],
            'FOXGLOVE'                      => ['FR' => "Digitale"],
            'GAILLARDIA (BLANKETFLOWER)' => array( 'FR' => 'Gaillarde' ),
            'HOLLYHOCK'                     => ['FR' => 'Rose Tr&eacute;mi&egrave;re'],
            'LATHYRUS (SWEET PEA)' => array( 'FR' => 'Pois de senteur' ),
            'LAVATERA' => array( 'FR' => 'Lavat&egrave;re' ),
            'LINUM (FLAX)' => array( 'FR' => "Lin" ),
            'MALVA (MALLOW)' => ['FR'=>"Mauve"],
            'MARIGOLD' => array( 'FR' => "Oeillets d'Inde" ),
            'MORNING GLORY' => array( 'FR' => 'Belle-de-jour' ),
            'NASTURTIUM' => array( 'FR' => 'Capucine' ),
            'OENOTHERA' => array( 'FR' => 'Onagre' ),
            'PANSY'                       => ['FR' => 'Fleur de pens&eacute;e'],
            'POPPY' => array( 'FR' => "Pavot" ),
            'SEA HOLLY'                     => ['FR' => 'Panicaut'],
            'SNAPDRAGON'                    => ['FR' => 'Muflier'],
            'STRAWFLOWER'                   => ['FR' => 'Fleur de paille'],
            'SUNFLOWER' => array( 'FR' => 'Tournesol' ),


            'APPLE' => array( 'FR' => 'Pommes' ),
            'BLACK ELDERBERRY' => array( 'FR' => 'Baies de sureau' ),
            'BLACKBERRY' => array( 'FR' => 'M&ucirc;res' ),
            'CURRANT' => array( 'FR' => 'Groseilles' ),
            'ELDERBERRY' => array( 'FR' => 'Baies de sureau' ),
            'GRAPE' => array( 'FR' => 'Raisins' ),
            'GARDEN HUCKLEBERRY' => array( 'FR' => 'Airelles' ),
            'LITCHI TOMATO' => array( 'FR' => 'Morelle de balbis' ),
            'MEDLAR' => array( 'FR' => 'N&eacute;flier' ),
            'MELON' => array( 'FR' => 'Melons' ),
            'MELON/MUSKMELON' => array( 'FR' => 'Melons/Cantaloups' ),
            'PLUM' => array( 'FR' => 'Prunes' ),
            'RHUBARB' => array( 'FR' => 'Rhubarbe' ),
            'STRAWBERRY' => array( 'FR' => 'Fraises' ),
            'WATERMELON' => array( 'FR' => "Past&egrave;ques melon d'eau" ),


            'BARLEY' => array( 'FR' => 'Orge' ),
            'BUCKWHEAT'         => ['FR' => 'Sarrasin'],
            'OATS' => array( 'FR' => 'Avoine' ),
            'PEARL MILLET' => array( 'FR' => 'Mil &agrave; chandelle' ),
            'RICE'          => ['FR' => 'Riz'],
            'SORGHUM' => array( 'FR' => 'Sorgho' ),
            'WHEAT' => array( 'FR' => 'Bl&eacute;' ),


            'ANGELICA' => array( 'FR' => 'Ang&eacute;lique' ),
            'ANISE HYSSOP' => array( 'FR' => 'Agastache fenouil' ),
            'BASIL' => array( 'FR' => 'Basilic' ),
            'BERGAMOT'                      => ['FR' => 'Bergamote'],
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
            'HYSSOP'                => ['FR' => 'Hysope'],
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
            'BEAN/FAVA' => array( 'FR' => 'F&egrave;ves - Fava (Larges)' ),
            'BEAN/LIMA' => array( 'FR' => 'F&egrave;ves de Lima' ),
            'BEAN/MUNG' => array( 'FR' => 'F&egrave;ves mungo' ),
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
            'LENTIL'                    => ['FR' => 'Lentilles'],
            'LETTUCE/HEAD' => array( 'FR' => 'Laitues en pomme' ),
            'LETTUCE/LEAF' => array( 'FR' => 'Laitues en feuille' ),
            'LETTUCE/ROMAINE' => array( 'FR' => 'Laitues romaines' ),
            'MUSTARD/GREENS' => array( 'FR' => 'Moutarde en feuille' ),
            'OKRA' => array( 'FR' => 'Okra' ),
            'ONION' => array( 'FR' => 'Oignons' ),
            'ONION/GREEN' => array( 'FR' => 'Oignons verts' ),
            'ONION/MULTIPLIER/ROOT' => array( 'FR' => 'Oignons' ),
            'ONION/MULTIPLIER/TOP' => array( 'FR' => 'Oignons &eacute;gyptiens' ),
            'ONION/SHALLOT' => ['FR'=>'&Eacute;chalote', 'FR_sort' => 'Echalote'],
            'ONION/TOPSET' => ['FR'=>'Oignons &eacute;gyptiens'],
            'ONION/WELSH' => ['FR'=>'Ciboule'],
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
            'TOMATO/PINK TO PURPLE' => array( 'FR' => 'Tomates - Peaux roses &agrave; pourpres' ),
            'TOMATO/RED' => array( 'FR' => 'Tomates - Peaux rouges' ),
            'TOMATO/YELLOW TO ORANGE' => array( 'FR' => 'Tomates - Peaux jaunes &agrave; oranges' ),
            'TURNIP' => array( 'FR' => 'Navets' ),
            'TURNIP - RUTABAGA' => array( 'FR' => 'Rutabagas' ),
    );

    function GetGrowerName( $kGrower )
    /*********************************
        Return the firstname-lastname or company of the given mbr_contact
     */
    {
        $o = new Mbr_Contacts($this->oApp);
        return( $o->GetContactName($kGrower) );
    }

    function GetGrowerDetails( $kGrower )
    /************************************
        Return the basic fields of the given mbr_contact
     */
    {
        $o = new Mbr_Contacts($this->oApp);
        return( $o->GetBasicValues($kGrower) );
    }


    function GetLastUpdated( $cond, $raParms = [] )  { return( $this->oSBDB->ProductLastUpdated( $cond, $raParms ) ); }

    function KFRelG()
    {
        $defG = [ "Tables"=>[ 'G' => [ "Table" => "{$this->oApp->DBName('seeds1')}.sed_curr_growers",
                                       "Type" => "Base",
                                       "Fields" => "Auto" ] ]];
        return( new KeyFrame_Relation( $this->oApp->kfdb, $defG, $this->oApp->sess->GetUID(), ['logfile'=>$this->oApp->logdir."msd.log"] ) );
    }

    function KFRelGxM()
    {
        $defGxM =
            array( "Tables"=>array( 'G' => array( "Table" => "{$this->dbname1}.sed_curr_growers",
                                                  "Type" => "Base",
                                                  "Fields" => "Auto" ),
                                    'M' => array( "Table"=> "{$this->dbname2}.mbr_contacts",
                                                  "Type" => "Join",
                                                  "JoinOn" => "(G.mbr_id=M._key)",
                                                  "Fields" => array( array("col"=>"firstname",       "type"=>"S"),
                                                                     array("col"=>"lastname",        "type"=>"S"),
                                                                     array("col"=>"firstname2",      "type"=>"S"),
                                                                     array("col"=>"lastname2",       "type"=>"S"),
                                                                     array("col"=>"company",         "type"=>"S"),
                                                                     array("col"=>"dept",            "type"=>"S"),
                                                                     array("col"=>"address",         "type"=>"S"),
                                                                     array("col"=>"city",            "type"=>"S"),
                                                                     array("col"=>"province",        "type"=>"S"),
                                                                     array("col"=>"postcode",        "type"=>"S"),
                                                                     array("col"=>"country",         "type"=>"S"),
                                                                     array("col"=>"phone",           "type"=>"S"),
                                                                     array("col"=>"email",           "type"=>"S"),
                                                                     array("col"=>"lang",            "type"=>"S"),
                                                                     array("col"=>"expires",         "type"=>"S") ) ),
                                            ) );
        return( new KeyFrame_Relation( $this->oApp->kfdb, $defGxM, $this->oApp->sess->GetUID(), ['logfile'=>$this->oApp->logdir."msd.log"] ) );
    }

}

/* Derived class of SEEDBasketCore for MSD
 */
class MSDBasketCore extends SEEDBasketCore
/*****************************************
    Derived class of SEEDBasketCore for MSD.

    oDB knows the relation PxPEMSD which relates all MSD prodextra fields together

    Db tables live in 'seeds' by default, or $raConfig['sbdb']
 */
{
    public $bIsMbrLogin;

    function __construct( SEEDAppConsole $oApp, $raConfig = [] )
    {
        $this->bIsMbrLogin = $oApp->sess->CanRead("sed");   // only members get this perm; this implies IsLogin()

        parent::__construct( null, null, $oApp,
                             //SEEDBasketProducts_SoD::$raProductTypes );
                             array( 'seeds'=>SEEDBasketProducts_SoD::$raProductTypes['seeds'] ),

                             ['fn_sellerNameFromUid' => [$this,"cb_SellerNameFromUid"],
                              'logdir'=>SITE_LOG_ROOT,      // SEEDBasketCore should get this from oApp instead
                              'sbdb_config' =>
                                     ['raCustomProductKfrelDefs' => ['PxPEMSD' => $this->GetSeedKeys('PRODEXTRA')],
                                      'sbdb' => @$raConfig['sbdb'] ?: 'seeds1'
                                     ]
                             ]
                           );
    }

    function cb_SellerNameFromUid( $uidSeller )
    /******************************************
        SEEDBasketCore uses this to draw the name of a seller
     */
    {
        $ra = $this->oApp->kfdb->QueryRA( "SELECT * FROM {$this->oApp->GetDBName('seeds1')}.SEEDSession_Users WHERE _key='$uidSeller'" );
        if( !($sSeller = @$ra['realname']) ) {
            $o = new Mbr_Contacts($this->oApp);
            if( !($sSeller = $o->GetContactName($uidSeller)) ) {
                $sSeller = "Grower # $uidSeller";
            }
        }
        return( $sSeller );
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
}


/*  We never create these tables from scratch anymore, but it's good to have the definition

CREATE TABLE sed_growers (
        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    mbr_id          INTEGER NOT NULL,                   -- could be fk_mbr_contacts (no impact on kfrel that don't involve mbr_contacts)
    mbr_code        CHAR(10),                           -- keep this here instead of mbr_contacts so sed_seeds has a record of province
    frostfree       VARCHAR(200),
    soiltype        VARCHAR(200),
    organic         BOOL        DEFAULT 0,
    zone            VARCHAR(200),
    notes           TEXT,
    unlisted_phone  BOOL        DEFAULT 0,
    unlisted_email  BOOL        DEFAULT 0,
    cutoff          VARCHAR(200),
    pay_cash        BOOL        DEFAULT 0,
    pay_cheque      BOOL        DEFAULT 0,
    pay_stamps      BOOL        DEFAULT 0,
    pay_ct          BOOL        DEFAULT 0,
    pay_mo          BOOL        DEFAULT 0,              -- money order
    pay_etransfer   tinyint     not null default 0,
    pay_paypal      tinyint     not null default 0,
    pay_other       VARCHAR(200),

    nTotal          INTEGER     DEFAULT 0,
    nFlower         INTEGER     DEFAULT 0,
    nFruit          INTEGER     DEFAULT 0,
    nGrain          INTEGER     DEFAULT 0,
    nHerb           INTEGER     DEFAULT 0,
    nTree           INTEGER     DEFAULT 0,
    nVeg            INTEGER     DEFAULT 0,
    nMisc           INTEGER     DEFAULT 0,

    year            INTEGER,

    eReqClass       enum('mail_email','mail','email') not null default 'mail_email',

    eDateRange      enum('use_range','all_year') not null default 'use_range',
    dDateRangeStart date not null default '2021-01-01',     -- want these to be year-independent
    dDateRangeEnd   date not null default '2021-05-31',     -- want these to be year-independent


-- Uncomment for sed_curr_grower
--  bSkip           BOOL         DEFAULT 0,
--  bDelete         BOOL         DEFAULT 0,
--  bChanged        BOOL         DEFAULT 0,
-- // obsolete  bDone           BOOL         DEFAULT 0,
--  bDoneMbr        BOOL         DEFAULT 0,  -- the member clicked Done themselves
--  bDoneOffice     BOOL         DEFAULT 0,  -- we clicked Done in the office
-- // obsolete  _updated_by_mbr VARCHAR(100),

--  _updated_G_mbr  VARCHAR(100) NOT NULL DEFAULT '',   -- last time the grower updated their own sed_growers record
--  _updated_S_mbr  VARCHAR(100) NOT NULL DEFAULT '',   -- last time the grower updated their own seed-product records
--  _updated_S      VARCHAR(100) NOT NULL DEFAULT '',   -- last time anybody updated a seed-product record owned by this grower
--  _updated_S_by   INTEGER NOT NULL DEFAULT 0,         -- who made the most recent change to a seed-product record owned by this grower
--  dDone           VARCHAR(100) NOT NULL DEFAULT '',   -- date when the Done button was clicked (revert to '' if reversed)
--  dDone_by        INTEGER NOT NULL DEFAULT 0,         -- who clicked or unclicked the Done button
--  tsGLogin        TIMESTAMP NULL,                     -- last time the grower was in the mse-edit application (reading or writing)

    INDEX sed_growers_mbr_id   (mbr_id),
    INDEX sed_growers_mbr_code (mbr_code)
);

alter table sed_curr_growers rename column _updated_by_mbr to _updated_G_mbr;
alter table sed_curr_growers add _updated_S_mbr varchar(100);
alter table sed_curr_growers add _updated_S varchar(100);
alter table sed_curr_growers add _updated_S_by integer default 0;

update sed_curr_growers set _updated_G_mbr ='' WHERE _updated_G_mbr is null;
update sed_curr_growers set _updated_S_mbr ='' WHERE _updated_S_mbr is null;
update sed_curr_growers set _updated_S     ='' WHERE _updated_S is null;
alter table sed_curr_growers change _updated_G_mbr _updated_G_mbr  VARCHAR(100) NOT NULL DEFAULT '';
alter table sed_curr_growers change _updated_S_mbr _updated_S_mbr  VARCHAR(100) NOT NULL DEFAULT '';
alter table sed_curr_growers change _updated_S     _updated_S      VARCHAR(100) NOT NULL DEFAULT '';
alter table sed_curr_growers change _updated_S_by  _updated_S_by   INTEGER NOT NULL DEFAULT 0;


DROP TABLE IF EXISTS sed_seeds;
CREATE TABLE sed_seeds (
        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    mbr_id          INTEGER NOT NULL,                   -- not fk_sed_curr_growers because that won't work with sed_seeds
    category        VARCHAR(200) NOT NULL DEFAULT '',
    type            VARCHAR(200) NOT NULL DEFAULT '',
    variety         VARCHAR(200) NOT NULL DEFAULT '',
    bot_name        VARCHAR(200) NOT NULL DEFAULT '',
    days_maturity   VARCHAR(200) NOT NULL DEFAULT '',
    quantity        VARCHAR(200) NOT NULL DEFAULT '',
    origin          VARCHAR(200) NOT NULL DEFAULT '',
    year_1st_listed INTEGER NOT NULL DEFAULT 0,
    description     TEXT,
    year            INTEGER NOT NULL DEFAULT 0,         -- the year of this SED


-- Uncomment for sed_curr_seeds
--  OBSOLETE  bSkip           BOOL         DEFAULT 0,
--  OBSOLETE  bDelete         BOOL         DEFAULT 0,
--  OBSOLETE  bChanged        BOOL         DEFAULT 0,
--  OBSOLETE  _updated_by_mbr DATETIME,


    INDEX sed_seeds_mbr_id     (mbr_id),
    INDEX sed_seeds_catgy      (category(20)),
    INDEX sed_seeds_type       (type(20)),
    INDEX sed_seeds_variety    (variety(20)),
);

*/

