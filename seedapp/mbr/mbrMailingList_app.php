<?php

/* Mailing List Generator
 *
 * Copyright 2013-2020 Seeds of Diversity Canada
 *
 * Generates mail and email lists for various categories of members and subscribers
 */

include_once( SEEDLIB."mbr/QServerMbr.php" );
include_once( SEEDCORE."SEEDXLSX.php" );
include_once( SEEDLIB."mbr/MbrEbulletin.php" );

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
$p_mbrFilter1 = $oForm->Value( 'eMbrFilter1' );
$p_mbrFilter2 = $oForm->Value( 'eMbrFilter2' );
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
        .$oForm->Select( 'eMbrGroup',   ["-- OR (choose) --" => 0,
                                         "Members and donors of $yMinus2 and greater" => 'membersAndDonors2Years',
                                         "Members and donors of $yMinus2 and greater, no donation in past six months" => 'membersAndDonors2YearsNoDonationInSixMonths',
                                         "Ebulletin subscribers who are not members or donors in $yMinus2 and greater" => 'ebullNotMembersOrDonors2Years',
                                        ] )
        ."</div><div style='margin-bottom:10px'>"
        .$oForm->Select( 'eMbrFilter1', ["-- (No filter) --" => 0,
                                         "Who receive the e-bulletin"             => 'getEbulletin',
                                         "Who receive donation appeals"           => 'getDonorAppeals',
                                         "Who receive the magazine"               => 'getMagazine',
                                         "Who receive the printed Seed Directory" => 'getPrintedMSD',
                                         "Who list seeds in the Seed Directory (active or skipped)" => 'msdGrowers',
                                         "Who list seeds in the Seed Directory (active or skipped) but are not Done this year" => 'msdGrowersNotDone'] )
// Implemented within eMbrGroup-membersAndDonors2Years only
//        ."</div><div style='margin-bottom:10px'>"
//        .$oForm->Select( 'eMbrFilter2', ["-- AND (No filter) --" => 0,
//                                         "Who have not given a donation in the past six months" => 'noRecentDonation',
//                                        ] )
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

if( ($eGroup = $oForm->Value('eMbrGroup')) ) {
// Make these groups in MbrContactsList

    $dStart = "$yMinus2-01-01";                        // include members and donors from two years ago
    $dSixMonthsAgo = date("Y-m-d", strtotime("-6 months"));  // who haven't made donations during the past six months
    $sql = "";
    $oMbr = new Mbr_Contacts( $oApp );

    $condFilter = "1=1";
    $condFilter .= $p_lang ? ($p_lang=='FR'? " AND M.lang IN ('B','F')" : " AND M.lang IN ('','B','E')") : "";
// Duplicated below
    switch( $p_mbrFilter1 ) {
        case 'getMagazine':                                                  break;  // all members get the magazine
        case 'getEbulletin':    $condFilter .= " AND NOT M.bNoEBull";        break;
        case 'getPrintedMSD':   $condFilter .= " AND M.bPrintedMSD";         break;
        case 'getDonorAppeals': $condFilter .= " AND NOT M.bNoDonorAppeals"; break;
    }
    switch( $p_locFilter ) {
//        case 'locOntario':       $qParms['provinceIn'] = "ON";                    break;
//        case 'locEasternCanada': $qParms['provinceIn'] = "ON QC NB NS PE NF NL";  break;
//        case 'locTorontoArea':   $qParms['postcodeIn'] = "M L N1 N2 N3 K9";       break;    // not implemented; translate to "(LEFT(postcode,1) IN ('M','L') OR LEFT(postcode,2) IN ('N1','N2','N3','K9'))"
//        case 'locOntarioSouth':  $qParms['postcodeIn'] = "K L M N";               break;    // not implemented; translate to "(LEFT(postcode,1) IN ('K','L','M','N'))"
    }


    // get all members and/or donors since $dStart, optionally exclude those with donations within 6 months ago
    $mrdParms = ['condM_D' => $condFilter,
                 'bRequireEmail'=>true, 'bRequireAddress'=>false,
                 'dIncludeIfMbrAfter' => $dStart,
                 'dIncludeIfDonAfter' => $dStart
    ];
    if( $eGroup == 'membersAndDonors2YearsNoDonationInSixMonths' ) {
        $mrdParms['dExcludeIfDonAfter'] = $dSixMonthsAgo;
    }
    $raMD = $oMbr->oDB->GetContacts_MostRecentDonation( $mrdParms, $sql );

    if( $eGroup =='membersAndDonors2Years' ) {
        /* Contacts who have been members and/or donors within the past 2 years
         */
        // put keys and emails in $raMbr so they can be retrieved in the canonical way below
        foreach( $raMD as $ra )  $raMbr[] = ['_key'=>$ra['M__key'],'email'=>$ra['M_email']];

        $sRight .= "Members and Donors since $yMinus2:<br/>$sql<br/>Found ".count($raMD)." emails<br/><br/>";
    }
    if( $eGroup =='membersAndDonors2YearsNoDonationInSixMonths' ) {
        /* Contacts who have been members and/or donors within the past 2 years, but did not make a donation in the past six months
         */
        // put keys and emails in $raMbr so they can be retrieved in the canonical way below
        foreach( $raMD as $ra )  $raMbr[] = ['_key'=>$ra['M__key'],'email'=>$ra['M_email']];

        $sRight .= "Members and Donors since $yMinus2 who did not donate since $dSixMonthsAgo:<br/>$sql<br/>Found ".count($raMD)." emails<br/><br/>";
    }
    if( $eGroup =='ebullNotMembersOrDonors2Years' ) {
        /* Ebulletin subscribers who are not members and/or donors within the past 2 years.
         */
        $oBull = new MbrEbulletin( $oApp );
        $raE = $oBull->GetSubscriberEmails( $p_lang );
        $raE = array_flip($raE);    // values to keys so unset() removes emails

        $sRight .= "Ebulletin subscribers who are not members and/or donors since $yMinus2:<br/>".count($raE)." subscribers<br/>";
        foreach( $raMD as $ra ) {
            unset($raE[$ra['M_email']]);   // remove the member/donor email from raE if it exists
        }
        $sRight .= "Reduced to ".count($raE)." using Member/Donor list<br>";

        $raEmail = array_flip($raE);
    }
}
else
if( ($yMbrExpires = $oForm->Value('yMbrExpires')) &&
     !SEEDCore_StartsWith($p_mbrFilter1,'msdGrowers' ) )       // msdGrower filters are handled below
{
    /* Look up mbr_contacts
     */
    $qParms = ['yMbrExpires' => $yMbrExpires];

    if( $p_lang )                $qParms['lang'] = $p_lang;
    if( $p_outFormat=='email' )  $qParms['bExistsEmail'] = true;

// Duplicated above
    switch( $p_mbrFilter1 ) {
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
// use MbrEbulletin::GetSubscriberEmails()
    $n = 0;
    switch( $p_lang ) {
        case 'EN': $sCond = "lang IN ('','B','E')";      break;     // '' in db is interpreted as E by default
        case 'FR': $sCond = "lang IN ('B','F')";         break;
        case '':
        default:   $sCond = "lang IN ('','B','E','F')";  break;     // '' in this form's ctrl is interpreted as All
    }

    if( ($dbc = $oApp->kfdb->CursorOpen( "SELECT email FROM {$oApp->DBName('seeds1')}.bull_list WHERE status>0 AND $sCond" ) ) ) {
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
if( SEEDCore_StartsWith($p_mbrFilter1,'msdGrowers') ) {
    include( SEEDLIB."msd/msdlib.php" );

    $raCond = ["NOT G.bDelete",
               "M.email IS NOT NULL AND M.email <> ''"];
    if( $p_lang == "EN" )  $raCond[] = "M.lang IN ('','B','E')";
    if( $p_lang == "FR" )  $raCond[] = "M.lang IN ('B','F')";
    if( $p_mbrFilter1=='msdGrowersNotDone' )  $raCond[] = "(NOT G.bDone)";

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
            if( ($name1 = Mbr_Contacts::FirstnameLastname( $ra, '' )) ) {
                $name2 = $ra['company'];
            } else {
                $name1 = $ra['company'];
                $name2 = '';
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
