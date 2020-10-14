<?php

/* Mailing List Generator
 *
 * Copyright 2013-2020 Seeds of Diversity Canada
 *
 * Generates mail and email lists for various categories of members and subscribers
 */


include_once( SEEDLIB."mbr/QServerMbr.php" );
include_once( SEEDCORE."SEEDXLSX.php" );

$oApp = SEEDConfig_NewAppConsole( ['db'=>'seeds2',
                                   'sessPermsRequired'=>['R MBR'],  // and R EBULLETIN
                                   'consoleConfig' => ['HEADER' => "Seeds of Diversity Mailing Lists"] ]
);

SEEDPRG();

$sLeft = $sRight = "";


$year = intval(date("Y"));

$bEbull    = SEEDInput_Int('gEbull');
$bSEDCurr  = SEEDInput_Int('gSEDCurr');
$bSEDCurr2 = SEEDInput_Int('gSEDCurrNotDone');


$bOverrideNoEmail  = SEEDInput_Int('override_noemail');


$oForm = new SEEDCoreForm('Plain');
$oForm->Load();

$p_lang = SEEDInput_Smart( 'eLang', ['', 'EN', 'FR'] );                     // also available as $oForm->Value('eLang')
$p_outFormat = SEEDInput_Smart( 'outFormat', ['email', 'mbrid','xls'] );
$p_mbrFilter = $oForm->Value( 'eMbrFilter' );
$p_locFilter = $oForm->Value( 'eLocFilter' );

$yMinus1 = $year-1;
$yMinus2 = $year-2;


/*************************************************
 * The form on the left side
 */

$sLeft .=
     "<style>
      .formsection {
          margin-bottom:10px;
          border:#888 solid 1px;
          border-radius:5px;
          padding:10px }
      .box1 {
          border:3px grey solid;
          background-color:#dddddd;
          padding:1em 2em;
      }
      </style>";

$sLeft .=
     "<div class='box1'>"
    ."<form method='post' action='".$oApp->PathToSelf()."'>"
    ."<div class='formsection'>"
        ."<p>Choose Members</p>"
        ."<div style='margin-bottom:10px'>"
        .$oForm->Select( 'yMbrExpires', ["-- No Members --" => 0,
                                         "Current Members ($year and greater)" => "$year+",
                                         "All members since $yMinus1 ($yMinus1 and greater)" => "$yMinus1+",
                                         "All members since $yMinus2 ($yMinus2 and greater)" => "$yMinus2+",
                                         "Non-current members expired in $yMinus2 or $yMinus1" => "$yMinus2,$yMinus1",
                                         "Non-current members expired in $yMinus1" => $yMinus1,
                                         "Non-current members expired in $yMinus2" => $yMinus2 ] )
        ."</div><div style='margin-bottom:10px'>"
        .$oForm->Select( 'eMbrFilter', ["-- No filter --" => 0,
                                        "Who receive the e-bulletin"             => 'getEbulletin',
                                        "Who receive donation appeals"           => 'getDonorAppeals',
                                        "Who receive the magazine"               => 'getMagazine',
                                        "Who receive the printed Seed Directory" => 'getPrintedMSD',
                                        "Who list seeds in the Seed Directory (active or skipped)" => 'msdGrowers',
                                        "Who list seeds in the Seed Directory (active or skipped) but are not Done this year" => 'msdGrowersNotDone'] )
        ."</div><div>"
        .$oForm->Select( 'eLocFilter', ["-- All locations --" => 0,
                                        "Ontario"             => 'locOntario',
                                        "Eastern Canada"      => 'locEasternCanada',
                                       ] )
        ."</div>"
    ."</div>"
    ."<div class='formsection'>"
        .$oForm->Checkbox( "chkEbulletin", "Emails for e-Bulletin subscribers who signed up on the web site" )
    ."</div>"
    ."<div class='formsection'>"
        ."<p>Language</p>"
        .$oForm->Select( 'eLang', ["-- All languages --" => 0,
                                   "English" => 'EN',
                                   "French"  => 'FR'] )
    ."</div>"
    ."<div class='formsection'>"
        ."<p>Output Format</p>"
        .$oForm->Select( 'outFormat', ["Email addresses" => 'email',
                                       "Member numbers" => 'mbrid',
                                       "Full spreadsheet" => 'xls'] )
    ."</div>"


    ."<div style='color:gray; border:thin solid grey;padding-left:1em;margin-bottom:10px'>"
    ."<p>Don't check this unless you really mean it</p>"
    ."<p style='margin-left:30px'>"
    // don't use SEEDForm because it's actually good if the check isn't sticky
    ."<input type='checkbox' name='override_noemail' value='1'> Include members who said they <U>don't want</U> email / e-Bulletin"
    ."</p>"
    ."</div>"

    ."<br/>"
    ."<input type='submit' value='List'/>"
    ."</form></div>";


/*************************************************
 * Compute the results for the right side
 */

$raEmail = []; // list of emails
$raMbrid = []; // list of mbr keys
$raMbr = [];   // list of full mbr records


/* Look up mbr_contacts
 */
if( ($yMbrExpires = $oForm->Value('yMbrExpires')) &&
    !SEEDCore_StartsWith($p_mbrFilter,'msdGrowers') )      // msdGrower filters are handled below
{
    $qParms = ['yMbrExpires' => $yMbrExpires];

    if( $p_lang )                $qParms['lang'] = $p_lang;
    if( $p_outFormat=='email' )  $qParms['bExistsEmail'] = true;

    switch( $p_mbrFilter ) {
        case 'getMagazine':                                                  break;  // all members get the magazine
        case 'getEbulletin':    $qParms['bGetEbulletin'] = !$bOverrideNoEmail;       // filter out members who don't want email, unless the override box is checked
                                $qParms['bExistsEmail'] = true;              break;
        case 'getPrintedMSD':   $qParms['bGetPrintedMSD'] = true;            break;
        case 'getDonorAppeals': $qParms['bGetDonorAppeals'] = true;          break;  // filter out members who don't want donor appeals
    }

    switch( $p_locFilter ) {
        case 'locOntario':       $qParms['provinceIn'] = "ON";                    break;
        case 'locEasternCanada': $qParms['provinceIn'] = "ON QC NB NS PE NF NL";  break;
        case 'locTorontoArea':   $qParms['postcodeIn'] = "M L N1 N2 N3 K9";       break;    // not implemented; translate to "(LEFT(postcode,1) IN ('M','L') OR LEFT(postcode,2) IN ('N1','N2','N3','K9'))"
        case 'locOntarioSouth':  $qParms['postcodeIn'] = "K L M N";               break;    // not implemented; translate to "(LEFT(postcode,1) IN ('K','L','M','N'))"
    }

    $oQ = new QServerMbr( $oApp, ['config_bUTF8'=>false] );
    $rQ = $oQ->Cmd( 'mbr-!-getListOffice', $qParms );
    $raMbr += $rQ['raOut'];

    $sRight .= "Members:<br/>{$rQ['sOut']}<br/>Found ".count($rQ['raOut'])." members<br/><br/>";
}


/* Look up bull_list
 * This does not implement spreadsheet output
 */
if( $oForm->Value('chkEbulletin') ) {
    $n = 0;
    switch( $p_lang ) {
        case 'EN': $sCond = "lang IN ('','B','E')";      break;     // '' in db is interpreted as E by default
        case 'FR': $sCond = "lang IN ('B','F')";         break;
        case '':
        default:   $sCond = "lang IN ('','B','E','F')";  break;     // '' in this form's ctrl is interpreted as All
    }

    if( ($dbc = $oApp->kfdb->CursorOpen( "SELECT email FROM seeds_1.bull_list WHERE status>0 AND $sCond" ) ) ) {
        while( $ra = $oApp->kfdb->CursorFetch( $dbc ) ) {
            $raEmail[] = $ra['email'];
            ++$n;
        }
    }

    $sRight .= "e-Bulletin:<br/>$sCond<br/>Found $n emails<br/><br/>";
}


/* Look up sed_grower_curr
 * This does not implement expiry dates nor spreadsheet output
 */
if( SEEDCore_StartsWith($p_mbrFilter,'msdGrowers') ) {
    include( SEEDLIB."msd/msdlib.php" );

    $raCond = ["NOT G.bDelete",
               "M.email IS NOT NULL AND M.email <> ''"];
    if( $p_lang == "EN" )  $raCond[] = "M.lang IN ('','B','E')";
    if( $p_lang == "FR" )  $raCond[] = "M.lang IN ('B','F')";
    if( $p_mbrFilter=='msdGrowersNotDone' )  $raCond[] = "(NOT G.bDone)";

    $sCond = "(".implode( " AND ", $raCond ).")";

    $n = 0;
    $oMSDLib = new MSDLib( $oApp );
    if( ($kfr = $oMSDLib->KFRelGxM()->CreateRecordCursor($sCond)) ) {
        while( $kfr->CursorFetch() ) {
            if( $p_outFormat == 'mbrid' ) {
                $raMbrid[] = $kfr->value('mbr_id');
            } else if( $p_outFormat == 'email' ){
                $raEmail[] = $kfr->value('M_email');
            }
            ++$n;
        }
    }

    $sRight .= "Seed Directory Growers:<br/>$sCond<br/>Found $n growers<br/><br/>";
}


switch( $p_outFormat ) {
    case 'email':
        // get the emails out of the raMbr array (N.B. the += and + operators overwrite elements by key, instead of appending)
        $raEmail = array_merge( $raEmail, array_map( function($ra){ return($ra['email']); }, $raMbr ) );
        break;
    case 'mbrid':
        // get the _keys out of the raMbr array (N.B. the += and + operators overwrite elements by key, instead of appending)
        $raMbrid = array_merge( $raMbrid, array_map( function($ra){ return($ra['_key']); }, $raMbr ) );
        break;
    case 'xls':
        // output the raMbr array to a spreadsheet
        $oXls = new SEEDXlsWrite( ['filename'=>'mailing-list.xlsx'] );
        $oXls->WriteHeader( 0, ['memberid', 'expiry',
                                'name', 'name2', 'address', 'city', 'province', 'postcode', 'country',
                                'email','phone'] );;

        $row = 2;
        foreach( $raMbr as $ra ) {
            $name1 = trim($ra['firstname'].' '.$ra['lastname']);
            $name2 = trim($ra['firstname2'].' '.$ra['lastname2']);
            if( $name1 ) {
                $name2 = $name2 ?: $ra['company'];
            } else {
                $name1 = $ra['company'];
            }

            $oXls->WriteRow( 0, $row++,
                             [$ra['_key'], $ra['expires'],
                             SEEDCore_utf8_encode($name1), SEEDCore_utf8_encode($name2), SEEDCore_utf8_encode($ra['address']), SEEDCore_utf8_encode($ra['city']),
                             $ra['province'], $ra['postcode'], $ra['country'],
                             $ra['email'], $ra['phone'] ] );
        }
        $oXls->OutputSpreadsheet();
        exit;
}





$n = count($raEmail);
$raEmail = array_unique( $raEmail );
$sRight .= "<p>Removed ".($n-count($raEmail))." duplicate emails.<br/>Listing ".count($raEmail)." addresses below.</p>";

$n = count($raMbrid);
$raMbrid = array_unique( $raMbrid );
$sRight .= "<p>Removed ".($n-count($raMbrid))." duplicate member ids.<br/>Listing ".count($raMbrid)." member ids below.</p>";

sort( $raEmail, SORT_STRING );
sort( $raMbrid, SORT_NUMERIC );

$sRight .= "<div style='border:solid thin gray;padding:1em;font-family:courier new,monospace;font-size:10pt;color:black'>"
          ."<textarea style='width:100%' rows='50'>"
          .SEEDCore_ArrayExpandSeries( $raEmail, "[[]]\n" )
          .SEEDCore_ArrayExpandSeries( $raMbrid, "[[]]\n" )
          ."</textarea>"
          ."</div>";


$s = "<div class='container-fluid'><div class='row'>"
    ."<div class='col-md-7'>$sLeft</div>"
    ."<div class='col-md-5' style='font-size:small;color:gray'>$sRight</div>"
    ."</div></div>";

echo Console02Static::HTMLPage( SEEDCore_utf8_encode($oApp->oC->DrawConsole($s)), "", 'EN', ['consoleSkin'=>'green'] );   // sCharset defaults to utf8
