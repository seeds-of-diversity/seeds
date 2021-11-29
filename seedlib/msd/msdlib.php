<?php

/* msdlib
 *
 * Copyright (c) 2009-2021 Seeds of Diversity
 *
 * Support for MSD app-level code that shouldn't know about MSDCore but can't get what it needs from MSDQ.
 *
 * Office-Admin functions.
 */

require_once "msdcore.php";
require_once SEEDCORE."SEEDProblemSolver.php";


class MSDLib
{
    public  $oApp;
    private $oMSDCore;
    private $dbname1;

    function __construct( SEEDAppConsole $oApp, $raConfig = [] )
    {
        $this->oApp = $oApp;
        $this->oMSDCore = new MSDCore( $oApp, ['sbdb' => @$raConfig['sbdb']] );
        $this->dbname1 = $this->oApp->GetDBName('seeds1');
    }

    function PermOfficeW()  { return( $this->oMSDCore->PermOfficeW() ); }
    function PermAdmin()    { return( $this->oMSDCore->PermAdmin() ); }

    function GetCurrYear()  { return( $this->oMSDCore->GetCurrYear() ); }

    function GetSpeciesNameFromKey( $kSp ) { return( $this->oMSDCore->GetKlugeSpeciesNameFromKey( $kSp ) ); }

    function TranslateCategory( $sCat ) { return( $this->oMSDCore->TranslateCategory( $sCat ) ); }
    function TranslateSpecies( $sSp )   { return( $this->oMSDCore->TranslateSpecies( $sSp ) ); }
    function TranslateSpecies2( $sSp )  { return( $this->oMSDCore->TranslateSpecies2( $sSp ) ); }

    function KFRelGxM() { return( $this->oMSDCore->KfrelGxM() ); }

    function AdminNormalizeStuff()
    {
        $s = "";

        if( !$this->PermAdmin() ) goto done;

        $this->oApp->kfdb->Execute( "UPDATE {$this->dbname1}.SEEDBasket_Products P,{$this->dbname1}.SEEDBasket_ProdExtra PE "
                                   ."SET PE.v=UPPER(TRIM(v)) "
                                   ."WHERE P.product_type='seeds' AND P._key=PE.fk_SEEDBasket_Products AND PE.k='species'" );

        /* Update offer counts in grower table (should happen after every edit)
         */
        $i = 0;
        if( ($dbc = $this->oMSDCore->oApp->kfdb->CursorOpen( "SELECT mbr_id FROM {$this->dbname1}.sed_curr_growers" )) ) {
            while( $ra = $this->oMSDCore->oApp->kfdb->CursorFetch($dbc) ) {
                $sCond = "mbr_id='{$ra['mbr_id']}' AND _status='0' AND NOT bSkip AND NOT bDelete";

                $sql = "SELECT count(*) FROM {$this->dbname1}.SEEDBasket_Products P,{$this->dbname1}.SEEDBasket_ProdExtra PE "
                      ."WHERE P._key=PE.fk_SEEDBasket_Products AND P._status='0' AND P.product_type='seeds' AND "
                            ."P.uid_seller='{$ra['mbr_id']}' AND P.eStatus='ACTIVE' AND PE.k='category' ";

                $nTotal  = $this->oMSDCore->oApp->kfdb->Query1( $sql );
                $nFlower = $this->oMSDCore->oApp->kfdb->Query1( $sql."  AND v='flowers'" );
                $nFruit  = $this->oMSDCore->oApp->kfdb->Query1( $sql."  AND v='fruit'" );
                $nGrain  = $this->oMSDCore->oApp->kfdb->Query1( $sql."  AND v='grain'" );
                $nHerb   = $this->oMSDCore->oApp->kfdb->Query1( $sql."  AND v='herbs'" );
                $nTree   = $this->oMSDCore->oApp->kfdb->Query1( $sql."  AND v='trees'" );
                $nVeg    = $this->oMSDCore->oApp->kfdb->Query1( $sql."  AND v='vegetables'" );
                $nMisc   = $this->oMSDCore->oApp->kfdb->Query1( $sql."  AND v='misc'" );

                $this->oMSDCore->oApp->kfdb->Execute(
                        "UPDATE {$this->dbname1}.sed_curr_growers "
                       ."SET nTotal='$nTotal',nFlower='$nFlower',nFruit='$nFruit',"
                       ."nGrain='$nGrain',nHerb='$nHerb',nTree='$nTree',nVeg='$nVeg',nMisc='$nMisc' "
                       ."WHERE mbr_id='{$ra['mbr_id']}'");
                ++$i;
            }
            $this->oMSDCore->oApp->kfdb->CursorClose($dbc);
        }

        $s = "<p>Removed NULLs, trimmed and upper-cased strings. Updated offer counts for $i growers.</p>";

        done:
        return( $s );
    }

    function AdminCopyToArchive( $year )
    /***********************************
        Delete all archive records for $year.
        Copy current active growers and seeds to archive and give them $year.
     */
    {
        $ok = true;
        $s = "";

        $this->oApp->kfdb->Execute( "DELETE FROM {$this->dbname1}.sed_growers WHERE year='$year'" );
        $this->oApp->kfdb->Execute( "DELETE FROM {$this->dbname1}.sed_seeds WHERE year='$year'" );

        /* Archive growers
         */
        $fields = "mbr_id,mbr_code,frostfree,soiltype,organic,zone,cutoff,eDateRange,dDateRangeStart,dDateRangeEnd,eReqClass,notes, _created,_created_by,_updated,_updated_by";
        $sql = "INSERT INTO {$this->dbname1}.sed_growers (_key,_status, year, $fields )"
              ."SELECT NULL,0, '$year', $fields "
              ."FROM {$this->dbname1}.sed_curr_growers WHERE _status=0 AND NOT bSkip AND NOT bDelete";
        if( $this->oApp->kfdb->Execute($sql) ) {
            $s .= "<h4 style='color:green'>Growers Successfully Archived</h4>"
                 ."<p style='margin-left:30px'><pre>$sql</pre></p>";
        } else {
            $s .= "<h4 style='color:red'>Archiving Growers Failed</h4>"
                 ."<p style='margin-left:30px'><pre>$sql</pre></p>"
                 ."<p style='margin-left:30px'><pre>".$this->oApp->kfdb->GetErrMsg()."</pre></p>";
            $ok = false;
        }

        /* Archive seeds
         *
         * Copy active seeds to the archive using INSERT...SELECT...
         * Use custom kfrel to fetch all ACTIVE seeds and their prodExtra fields, one per row per seed.
         * Override the fields in the SELECT so they match the fields in the INSERT.  All that matters is that they're in the same order.
         */
        $raSelectFields = [
             // create new _key in archive with the same create/update information as the seeds
             // (note this does not capture _updated/by from the PE fields so timestamps of latest changes to descriptions etc will not be reflected in the archive)
             'VERBATIM_newkey' => 'NULL',
             '_created' => 'P._created',
             '_created_by' => 'P._created_by',
             '_updated' => 'P._updated',
             '_updated_by' => 'P._updated_by',

             'mbr_id'=>'P.uid_seller',
             'category' => 'PE_category.v',
             'species' => 'PE_species.v',
             'variety' => 'PE_variety.v',
             'bot_name' => 'PE_bot_name.v',
             'days_maturity' => 'PE_days_maturity.v',
             'days_maturity_seed' => 'PE_days_maturity_seed.v',
             'quantity' => 'PE_quantity.v',
             'origin' => 'PE_origin.v',
             'eOffer' => 'PE_eOffer.v',
             'year_1st_listed' => 'PE_year_1st_listed.v',
             'description' => 'PE_description.v',
             'VERBATIM_year' => "'$year'"
        ];
        $sSelectSql = $this->oMSDCore->GetSeedSql( "eStatus='ACTIVE'", ['raFieldsOverride'=> $raSelectFields] );

        $sInsertFields = "mbr_id,category,type,variety,bot_name,days_maturity,days_maturity_seed,quantity,origin,eOffer,year_1st_listed,description,year";


        /* Archive seeds
         */
        $sql = "INSERT INTO {$this->dbname1}.sed_seeds (_key,_created,_created_by,_updated,_updated_by, $sInsertFields ) $sSelectSql";

        if( $this->oApp->kfdb->Execute($sql) ) {
            $s .= "<h4 style='color:green'>Seeds Successfully Archived</h3>"
                 ."<p style='margin-left:30px'><pre>$sql</pre></p>";
        } else {
            $s .= "<h4 style='color:red'>Archiving Seeds Failed</h4>"
                 ."<p style='margin-left:30px'><pre>$sql</pre></p>"
                 ."<p style='margin-left:30px'><pre>".$this->oApp->kfdb->GetErrMsg()."</pre></p>";
            $ok = false;
        }

        return( array( $ok, $s ) );
    }

    function DrawGrowerBlock( KeyframeRecord $kfrGxM, $bFull = true )
    {
        $s = $kfrGxM->Expand( "<b>[[mbr_code]]: [[M_firstname]] [[M_lastname]] ([[mbr_id]]) " )
             .($kfrGxM->value('organic') ? $this->S('Organic') : "")."</b>"
             ."<br/>";

        if( $bFull ) {
            $s .= $kfrGxM->ExpandIfNotEmpty( 'company', "<strong>[[]]</strong>><br/>" );

            if( $kfrGxM->Value('eReqClass')!='email' ) {
                // don't show mailing address if requests are accepted by email only
                $s .= $kfrGxM->Expand( "[[M_address]], [[M_city]] [[M_province]] [[M_postcode]]<br/>" );
            }

            $s1 = "";
            if( !$kfrGxM->value('unlisted_email') )  $s1 .= $kfrGxM->ExpandIfNotEmpty( 'M_email', "<i>[[]]</i>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" );
            if( !$kfrGxM->value('unlisted_phone') )  $s1 .= $kfrGxM->ExpandIfNotEmpty( 'M_phone', "[[]]" );
            if( $s1 )  $s .= $s1."<br/>";

            $s .= $kfrGxM->ExpandIfNotEmpty( 'cutoff', "No requests after: [[]]<br/>" );

            $s1 = $kfrGxM->ExpandIfNotEmpty( 'frostfree', "[[]] frost free days. " );
                 //.$kfrGxM->ExpandIfNotEmpty( 'soiltype',  "Soil: [[]]. " )
                 //.$kfrGxM->ExpandIfNotEmpty( 'zone',      "Zone: [[]]. " );
            if( $s1 )  $s .= $s1."<br/>";

            $s .= "Requests accepted "
                 .($kfrGxM->Value('eDateRange')=='all_year'
                     ? "all year round"
                     : ("from ".date_format(date_create($kfrGxM->Value('dDateRangeStart')), "M d")." to ".date_format(date_create($kfrGxM->Value('dDateRangeEnd')), "M d"))
                  )
                 .($kfrGxM->Value('eReqClass')=='mail' ? " by mail only" : ($kfrGxM->Value('eReqClass')=='email' ? " by e-payment only" : ""))
                 .".<br/>";

            $ra = array();
            foreach( array('nFlower' => array('flower','flowers'),
                           'nFruit'  => array('fruit','fruits'),
                           'nGrain'  => array('grain','grains'),
                           'nHerb'   => array('herb','herbs'),
                           'nTree'   => array('tree/shrub','trees/shrubs'),
                           'nVeg'    => array('vegetable','vegetables'),
                           'nMisc'   => array('misc','misc')
                           ) as $k => $raV ) {
                if( $kfrGxM->value($k) == 1 )  $ra[] = "1 ".$raV[0];
                if( $kfrGxM->value($k) >  1 )  $ra[] = $kfrGxM->value($k)." ".$raV[1];
            }
            $s .= $kfrGxM->value('nTotal')." listings: ".implode( ", ", $ra ).".<br/>";

            if( ($sPM = $this->drawPaymentMethod($kfrGxM)) ) {
                $s .= "<i>Payment method: $sPM</i><br/>";
            }

            $s .= $kfrGxM->ExpandIfNotEmpty( 'notes', "Notes: [[]]<br/>" );
        }

        return( $s );
    }

    private $ePaymentMethods = [
        'pay_cash'      => ['epay'=>0, 'en' => "Cash",                'fr' => "" ],
        'pay_cheque'    => ['epay'=>0, 'en' => "Cheque",              'fr' => "" ],
        'pay_stamps'    => ['epay'=>0, 'en' => "Stamps",              'fr' => "" ],
        'pay_ct'        => ['epay'=>0, 'en' => "Canadian Tire money", 'fr' => "" ],
        'pay_mo'        => ['epay'=>0, 'en' => "Money order",         'fr' => "" ],
        'pay_etransfer' => ['epay'=>1, 'en' => "E-transfer",          'fr' => "" ],
        'pay_paypal'    => ['epay'=>1, 'en' => "Paypal",              'fr' => "" ] ];

    private function drawPaymentMethod( $kfrGxM )
    {
        $raPay = [];

        foreach( $this->ePaymentMethods as $k => $ra ) {
            if( $kfrGxM->value('eReqClass')=='email' && !$ra['epay'] ) continue;    // exclude non-epay methods if grower only accepts epay
            $raPay[] = $ra['en'];
        }
        if( $kfrGxM->value('pay_other') )  $raPay[] = $kfrGxM->value('pay_other');

        return( implode( ", ", $raPay ) );
    }

    private function S( $sTranslate )
    {
        if( $sTranslate == 'Organic' ) return( $this->oApp->lang=='EN' ? $sTranslate : "Biologique" );

        return( $sTranslate );
    }

    function DrawGrowerForm( KeyframeRecord $kfrGxM, $bOffice = false )
    {
        $s = "";

$s .= "
<style>
.msd_grower_edit_form       { padding:0px 1em; font-size:9pt; }
.msd_grower_edit_form td    { font-size:9pt; }
.msd_grower_edit_form input { font-size:8pt;}
.msd_grower_edit_form h3    { font-size:12pt; }
</style>
";

/*
    alter table sed_curr_growers add eReqClass enum ('mail_email','mail','email') not null default 'mail_email';
    alter table sed_growers add eReqClass      enum ('mail_email','mail','email');

    alter table sed_curr_growers add pay_etransfer tinyint not null default 0;
    alter table sed_curr_growers add pay_paypal    tinyint not null default 0;

    alter table sed_curr_growers add eDateRange enum ('use_range','all_year') not null default 'use_range';
    alter table sed_curr_growers add dDateRangeStart date not null default '2022-02-01';
    alter table sed_curr_growers add dDateRangeEnd   date not null default '2022-05-31';

    alter table sed_growers add eReqClass       text;
    alter table sed_growers add eDateRange      text;
    alter table sed_growers add dDateRangeStart text;
    alter table sed_growers add dDateRangeEnd   text;

 */


        $bNew = !$kfrGxM->Key();

        $s .= "<div class='msd_grower_edit_form'>"
             ."<h3>".($bNew ? "Add a New Grower"
                            : $kfrGxM->Expand( "Edit Grower [[mbr_code]] : [[M_firstname]] [[M_lastname]] [[M_company]]" ))."</h3>";

        if( !$bNew ) {
            $s .= "<div style='background-color:#ddd; margin-bottom:1em; padding:1em; font-size:9pt;'>"
                 ."If your name, address, phone number, or email have changed, please notify our office"
                 ."</div>";
        }

        $oForm = new KeyframeForm( $kfrGxM->KFRel(), "A" );
        $oForm->SetKFR($kfrGxM);
        $oFE = new SEEDFormExpand( $oForm );
        $s .= "<form method='post'>
               <div class='container-fluid'>
                   <div class='row'>
                       <div class='col-md-6'>"
                         .$oFE->ExpandForm(
                             "|||BOOTSTRAP_TABLE(class='col-md-4' | class='col-md-8')
                              ||| <input type='submit' value='Save'/><br/><br/> || [[HiddenKey:]]
                              ||| *Member&nbsp;#*        || ".($bOffice && $bNew ? "[[mbr_id]]" : "[[mbr_id | readonly]]" )
                            ."||| *Member&nbsp;Code*     || ".($bOffice ? "[[mbr_code]]" : "[[mbr_code | readonly]]")
                            ."||| *Email&nbsp;unlisted*  || [[Checkbox:unlisted_email]]&nbsp;&nbsp; do not publish
                              ||| *Phone&nbsp;unlisted*  || [[Checkbox:unlisted_phone]]&nbsp;&nbsp; do not publish
                              ||| *Frost&nbsp;free*      || [[frostfree | size:5]]&nbsp;&nbsp; days
                              ||| *Organic*              || [[Checkbox: organic]]&nbsp;&nbsp; are your seeds organically grown?
                              ||| *Notes*                || &nbsp;
                              ||| {replaceWith class='col-md-12'} [[TextArea: notes | width:100% rows:10]]
                             " )
                     ."<div style='margin-top:10px;border:1px solid #aaa; padding:10px'>
                         <p><strong>I accept seed requests:</strong></p>
                         <p>".$oForm->Radio('eDateRange', 'use_range')."&nbsp;&nbsp;Between these dates</p>
                         <p style='margin-left:20px'>Members will not be able to make online requests outside of this period. Our default is January 1 to May 31.</p>
                         <p style='margin-left:20px'>".$oForm->Date('dDateRangeStart')."</p>
                         <p style='margin-left:20px'>".$oForm->Date('dDateRangeEnd')."</p>
                         <p>&nbsp;</p>
                         <p>".$oForm->Radio('eDateRange', 'all_year')."&nbsp;&nbsp;All year round</p>
                         <p style='margin-left:20px'>Members will be able to request your seeds at any time of year.</p>
                       </div>
                       </div>
                       <div class='col-md-6'>
                         <div style='border:1px solid #aaa; padding:10px'>
                         <p><strong>I accept seed requests and payment:</strong></p>

                         <p>".$oForm->Radio('eReqClass', 'mail_email')."&nbsp;&nbsp;By mail or email</p>
                         <ul>
                         <li>Members will see your mailing address and email address.</li>
                         <li>You will receive seed requests in the mail and by email.</li>
                         <li>Members will be prompted to send payment as you specify below.</li>
                         </ul>

                         <p>".$oForm->Radio('eReqClass', 'mail')."&nbsp;By mail only</p>
                         <ul>
                         <li>Members will see your mailing address.</li>
                         <li>You will receive seed requests my mail only.</li>
                         <li>Members will be prompted to send payment as you specify below.</li>
                         </ul>

                         <p>".$oForm->Radio('eReqClass', 'email')."&nbsp;By email only</p>
                         <ul>
                         <li>Members will not see your mailing address.</li>
                         <li>You will receive seed requests my email only.</li>
                         <li>Members will be prompted to send payment as you specify below (e-transfer and/or Paypal only).</li>
                         </ul>

                         <p><strong>Payment Types Accepted</strong></p>
                         <p>".$oForm->Checkbox( 'pay_cash',      "Cash" ).SEEDCore_NBSP("",4)
                             .$oForm->Checkbox( 'pay_cheque',    "Cheque" ).SEEDCore_NBSP("",4)
                             .$oForm->Checkbox( 'pay_stamps',    "Stamps" )."<br/>"
                             .$oForm->Checkbox( 'pay_ct',        "Canadian Tire money" ).SEEDCore_NBSP("",4)
                             .$oForm->Checkbox( 'pay_mo',        "Money Order" )."<br/>"
                             .$oForm->Checkbox( 'pay_etransfer', "e-transfer" ).SEEDCore_NBSP("",4)
                             .$oForm->Checkbox( 'pay_paypal',    "Paypal" )."<br/>"
                             .$oForm->Text( 'pay_other', "Other ", ['size'=> 30] )
                       ."</p>
                         </div>
                       </div>
                   </div>
               </div></form>";

goto done;
        $s .= "<TABLE border='0'>";
        $nSize = 30;
        $raTxtParms = array('size'=>$nSize);
        if( $bNew ) {
            $s .= $bOffice ? ("<TR>".$oKForm->TextTD( 'mbr_id', "Member #", $raTxtParms  )."</TR>")
                           : ("<TR><td>Member #</td><td>".$oKForm->Value('mbr_id')."</td></tr>" );
        }
        //if( $this->sess->CanAdmin('sed') ) {  // Only administrators can change a grower's code
        if( $this->bOffice ) {  // Only the office application can change a grower's code
            $s .= "<TR>".$oKForm->TextTD( 'mbr_code', "Member Code", $raTxtParms )."</TR>";
        }
        $s .= "<TR>".$oKForm->CheckboxTD( 'unlisted_phone', "Phone", array('sRightTail'=>" do not publish" ) )."</TR>"
             ."<TR>".$oKForm->CheckboxTD( 'unlisted_email', "Email", array('sRightTail'=>" do not publish" ) )."</TR>"
             ."<TR>".$oKForm->TextTD( 'frostfree', "Frost free", $raTxtParms )."<TD></TD></TR>"
             ."<TR>".$oKForm->TextTD( 'soiltype', "Soil type", $raTxtParms )."<TD></TD></TR>"
             ."<TR>".$oKForm->CheckboxTD( 'organic', "Organic" )."</TR>"
             ."<TR>".$oKForm->TextTD( 'zone', "Zone", $raTxtParms )."</TR>"
             ."<TR>".$oKForm->TextTD( 'cutoff', "Cutoff", $raTxtParms )."</TR>"

             ."</TD></TR>"
             ."<TR>".$oKForm->TextAreaTD( 'notes', "Notes", 35, 8, array( 'attrs'=>"wrap='soft'"))."</TD></TR>"
             //."<TR>".$oKForm->CheckboxTD( 'bDone', "This Grower is Done:" )."</TR>"
             ."</TABLE>"
             ."<BR><INPUT type=submit value='Save' />"
             ;

done:
$s .= "</div>";

        return( $s );
    }
}
