<?php

/* mse-edit tabset for seeds tab
 *
 * Copyright (c) 2018-2023 Seeds of Diversity
 *
 */

/*
update seeds_1.SEEDBasket_ProdExtra set v='flowers' where v='FLOWERS AND WILDFLOWERS' and k='category';
update seeds_1.SEEDBasket_ProdExtra set v='vegetables' where v='VEGETABLES' and k='category';
update seeds_1.SEEDBasket_ProdExtra set v='fruit' where v='FRUIT' and k='category';
update seeds_1.SEEDBasket_ProdExtra set v='herbs' where v='HERBS AND MEDICINALS' and k='category';
update seeds_1.SEEDBasket_ProdExtra set v='grain' where v='GRAIN' and k='category';
update seeds_1.SEEDBasket_ProdExtra set v='trees' where v='TREES AND SHRUBS' and k='category';
update seeds_1.SEEDBasket_ProdExtra set v='misc' where v='MISC' and k='category';
 */


// for the most part, msd apps try to access seedlib/msd via MSDQ()
include_once( SEEDLIB."msd/msdq.php" );
include_once( SEEDAPP."seedexchange/msdCommon.php" );   // DrawMSDList() should be a seedlib thing
include_once( SEEDCORE."SEEDLocal.php" );

class MSEEditApp
/***************
    Shared by all tabs
 */
{
    public $oApp;
    public $oMSDLib;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oMSDLib = new MSDLib($oApp, ['sbdb'=>'seeds1']);
    }

    function NormalizeParms( int $kCurrGrower, string $eTab )
    /********************************************************
        Init() methods use this to normalize the current grower and office perm status
     */
    {
        $bOffice = $this->oMSDLib->PermOfficeW();
        if( !$bOffice || (!$kCurrGrower && $eTab=='grower') ) {     // kGrower==0 allowed for Seeds in office (see all seeds in current section)
            $kCurrGrower = $this->oApp->sess->GetUID();
        }
        return( [$bOffice, $kCurrGrower] );
    }

    function GetGrowerName( $kGrower )
    {
        $oMbr = new Mbr_Contacts($this->oApp);
        return( $oMbr->GetContactName($kGrower) );
    }

    function MakeSelectGrowerNames( int $kCurrGrower, bool $klugeEncodeUTF8 )
    {
//use GxM to make this more efficient
        $raG = $this->oApp->kfdb->QueryRowsRA( "SELECT mbr_id,bSkip,bDelete,bDone FROM {$this->oApp->GetDBName('seeds1')}.sed_curr_growers WHERE _status='0'" );
        $raG2 = array( '-- All Growers --' => 0 );
        foreach( $raG as $ra ) {
            $kMbr = $ra['mbr_id'];
            $bSkip = $ra['bSkip'];
            $bDelete = $ra['bDelete'];
            $bDone = $ra['bDone'];

            $name = $this->GetGrowerName( $kMbr )
                   ." ($kMbr)"
                   .($bDone ? " - Done" : "")
                   .($bSkip ? " - Skipped" : "")
                   .($bDelete ? " - Deleted" : "");
            if( $klugeEncodeUTF8 )  $name = SEEDCore_utf8_encode(trim($name));    // Seeds is utf8 but Growers isn't
            $raG2[$name] = $kMbr;
        }
        ksort($raG2);
        $oForm = new SEEDCoreForm( 'Plain' );
        return( "<form method='post'>".$oForm->Select( 'selectGrower', $raG2, "", ['selected'=>$kCurrGrower, 'attrs'=>"onChange='submit();'"] )."</form>" );
    }
}

