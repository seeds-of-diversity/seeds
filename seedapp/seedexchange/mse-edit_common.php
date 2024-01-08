<?php

/* mse-edit tabset for seeds tab
 *
 * Copyright (c) 2018-2024 Seeds of Diversity
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

    function GetGrowerList( string $sSortCol, array $raChecked )
    {
        // sort list by sSortCol
        switch($sSortCol) {
            default:
            case 'firstname':   $sSortCol = 'M.firstname,M.lastname';   break;
            case 'lastname':    $sSortCol = 'M.lastname,M.firstname';   break;
            case 'mbrcode':     $sSortCol = 'mbr_code';                 break;
        }

        // filter list by raChecked
        $raCond = [];
        if(isset($raChecked['bDone']))     { $raCond[] = ($raChecked['bDone'] ? '' : 'NOT ')."({$this->oMSDLib->GetIsGrowerDoneCond()})"; }
        if(isset($raChecked['bSkip']))     { $raCond[] = ($raChecked['bSkip'] ? '' : 'NOT ')."bSkip"; }
        if(isset($raChecked['bDel']))      { $raCond[] = ($raChecked['bDel']  ? '' : 'NOT ')."bDelete"; }
        if(isset($raChecked['bExpired']))  { $raCond[] = ($raChecked['bExpired']  ? '' : 'NOT ')."(year(M.expires)<".(intval($this->oMSDLib->GetCurrYear())-2).")"; }    // e.g. for 2025 MSE the member's expiry is 2023 or less
        if(isset($raChecked['bNoChange'])) { $raCond[] = ($raChecked['bNoChange'] ? '' : 'NOT ')."((_updated_G_mbr='' OR _updated_G_mbr<'{$this->oMSDLib->GetFirstDayForCurrYear()}') AND
                                                                                                   (_updated_S_mbr='' OR _updated_S_mbr<'{$this->oMSDLib->GetFirstDayForCurrYear()}'))"; }
        if(isset($raChecked['bZeroSeeds'])) { $raCond[] = $raChecked['bZeroSeeds'] ? "nTotal=0" : "nTotal>0"; }

        $raG = $this->oMSDLib->KFRelGxM()->GetRecordSetRA(implode(' AND ',$raCond),['sSortCol'=>$sSortCol]);   // all growers with _status=0

        return( $raG );
    }

    function MakeGrowerNamesSelect( array $raGrowerList, int $kCurrGrower, bool $klugeEncodeUTF8 )   // get this array from GetGrowerList()
    {
        $oMbr = new Mbr_Contacts($this->oApp);

        $raG2 = array( '-- All Growers --' => 0 );
        foreach( $raGrowerList as $ra ) {
            $kMbr = $ra['mbr_id'];
            $bSkip = $ra['bSkip'];
            $bDelete = $ra['bDelete'];
            $bDone = $ra['bDone'];

            $name = $oMbr->GetContactNameFromMbrRA( $ra, ['fldPrefix'=>'M_'] )
                   ." ($kMbr {$ra['mbr_code']})"
                   .($bDone ? " - Done" : "")
                   .($bSkip ? " - Skipped" : "")
                   .($bDelete ? " - Deleted" : "");
            if( $klugeEncodeUTF8 )  $name = SEEDCore_utf8_encode(trim($name));    // Seeds is utf8 but Growers isn't
            $raG2[$name] = $kMbr;
        }
        //ksort($raG2);
        $oForm = new SEEDCoreForm( 'Plain' );
        return( "<form method='post'>".$oForm->Select( 'selectGrower', $raG2, "", ['selected'=>$kCurrGrower, 'attrs'=>"onChange='submit();'"] )."</form>" );
    }

    function MakeGrowerNamesTable( array $raGrowerList, int $kCurrGrower, bool $klugeEncodeUTF8 )   // get this array from GetGrowerList()
    {
        $s = "";

        $oMbr = new Mbr_Contacts($this->oApp);

        foreach( $raGrowerList as $ra ) {
            $kMbr = $ra['mbr_id'];
            $bSkip = $ra['bSkip'];
            $bDelete = $ra['bDelete'];
            $bDone = $this->oMSDLib->IsGrowerDoneFromDate($ra['dDone']);

            $name = $oMbr->GetContactNameFromMbrRA( $ra, ['fldPrefix'=>'M_'] )
                   ." ($kMbr {$ra['mbr_code']})"
                   .($bDone ? " - <span style='color:green'>Done</span>" : "")
                   .($bSkip ? " - <span style='color:orange'>Skipped</span>" : "")
                   .($bDelete ? " - <span style='color:red'>Deleted</span>" : "");

            if( $klugeEncodeUTF8 )  $name = SEEDCore_utf8_encode(trim($name));    // Seeds is utf8 but Growers isn't

            $cssName = "padding:3px;";
            if( $bDelete ) $cssName .= "background-color:#fdf;";
            else if( $bDone )   $cssName .= "background-color:#cdc;";
            $name = "<span style='$cssName'>$name</span>";

            if( $kMbr==$kCurrGrower ) $name = "<div style='font-weight:bold;border:1px solid #777;padding:3px'>$name</div>";
            $s .= "<form action='' method='post'><p onclick='this.parentElement.submit();' style='margin:0'>$name <input type='hidden' name='selectGrower' value='$kMbr'/></p></form>";
        }
        return( $s );
    }
}

