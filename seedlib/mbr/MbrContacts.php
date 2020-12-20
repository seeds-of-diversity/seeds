<?php

class Mbr_Contacts
{
    public  $oDB;
    private $oApp;

    function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;
        $this->oDB = new Mbr_ContactsDB( $oApp );
    }

    function GetBasicFlds()   { return( $this->raFldsBasic ); }
    function GetOfficeFlds()  { return( $this->raFldsBasic + $this->raFldsOffice ); }
    function GetAllFlds()     { return( $this->raFldsBasic + $this->raFldsOffice + $this->raFldsSensitive ); }

    private $raFldsBasic = [
        'firstname'  => ['l_en'=>'First name'],
        'lastname'   => ['l_en'=>'Last name'],
        'firstname2' => ['l_en'=>'First name 2'],
        'lastname2'  => ['l_en'=>'Last name 2'],
        'company'    => ['l_en'=>'Company'],
        'dept'       => ['l_en'=>'Dept'],
        'address'    => ['l_en'=>'Address'],
        'city'       => ['l_en'=>'City'],
        'province'   => ['l_en'=>'Province'],
        'postcode'   => ['l_en'=>'Postal code'],
        'country'    => ['l_en'=>'Country'],
        'email'      => ['l_en'=>'Email'],
        'phone'      => ['l_en'=>'Phone'],
        'lang'       => ['l_en'=>'Language'],
    ];

    private $raFldsOffice = [
        'comment'    => ['l_en'=>'Comment'],
        'referral'   => ['l_en'=>'Referral'],
        'bNoEBull'   => ['l_en'=>'No E-bulletin'],
        'bNoDonorAppeals' => ['l_en'=>'No Donor Appeals'],
        'expires'    => ['l_en'=>'Expires'],
        'lastrenew'  => ['l_en'=>'Last Renewal'],
        'startdate'  => ['l_en'=>'Start Date'],
//      'bNoSED'     => ['l_en'=>'Online MSD'],     obsolete and no longer updated
        'bPrintedMSD' => ['l_en'=>'Printed MSD'],
    ];

    private $raFldsSensitive = [
        // add donation fields etc here
    ];

    function GetContactName( $k, $raParms = [] )
    /*******************************************
        Default is to show [firstname] [lastname] { & [firstname2] [lastname2] }
                        or [company] if names are blank.

        SHOW_ONE_NAME_ONLY     = inhibits firstname2/lastname2
        SHOW_COMPANY_WITH_NAME = shows company even if names not blank
        SHOW_CITY              = appends "in [city]"
        SHOW_PROVINCE          = appends "in [province]"
        SHOW_CITY_PROVINCE     = appends "in [city], [province]" or SHOW_PROVINCE if city is blank
     */
    {
        $s = "";

        $bShowOneNameOnly       = @$raParms['SHOW_ONE_NAME_ONLY'];
        $bShowCompanyWithName   = @$raParms['SHOW_COMPANY_WITH_NAME'];
        $bShowCity              = @$raParms['SHOW_CITY'];
        $bShowProvince          = @$raParms['SHOW_PROVINCE'];
        $bShowCityProvince      = @$raParms['SHOW_CITY_PROVINCE'];

        $ra = $this->oApp->kfdb->QueryRA( "SELECT firstname,lastname,firstname2,lastname2,company,city,province "
                                         ."FROM {$this->oApp->GetDBName('seeds2')}.mbr_contacts WHERE _key='$k'" );

        // firstname(s)/lastname(s)
        $f1 = $ra['firstname']; $f2 = $ra['firstname2'];
        $l1 = $ra['lastname'];  $l2 = $ra['lastname2'];

        if( !$f2 && !$l2 ) {                // name1 only (which is blank if all are empty)
            $s = trim("$f1 $l1");
        } else if( !$f1 && !$l1 ) {         // name2 only
            $s = trim("$f2 $l2");
        } else if( $l1 == $l2 ) {           // both names, lastname is the same
            $s = trim("$f1 & $f2 $l2");
        } else {                            // both names, lastnames are different
            $s = trim("$f1 $l1 & $f2 $l2");
        }

        // company
        if( !$s || $bShowCompanyWithName ) {
            $s = ($s ? ", " : "") . $ra['company'];
        }

        // city, province
        if( !$ra['city'] ) {
            // if city not defined, reduce to just province parms
            $bShowProvince = $bShowProvince || $bShowCityProvince;
            $bShowCity = false;
            $bShowCityProvince = false;
        }
        if( !$ra['province'] ) {
            // if province not defined, reduce to just city parms
            $bShowCity = $bShowCity || $bShowCityProvince;
            $bShowProvince = false;
            $bShowCityProvince = false;
        }
        if( $bShowCity )          $s .= " in {$ra['city']}";
        if( $bShowProvince )      $s .= " in {$ra['province']}";
        if( $bShowCityProvince )  $s .= " in {$ra['city']}, {$ra['province']}";

        return( $s );
    }

    function DrawAddressBlock( $kMbr, $format = 'html', $prefix = '' )
    {
        // get record where _key=$kMbr
        // $ra = ValuesRA()
        //return( $this->DrawAddressBlockFromRA( $ra, $format, $prefix ) );
    }

    static function DrawAddressBlockFromRA( $raMbr, $fmt = 'HTML', $prefix = '' )
    /****************************************************************************
        Draw a contact's address block in the given format (HTML or PDF).
        $prefix is an optional prefix on the $raMbr keys e.g. "M_"
     */
    {
        if( $fmt == 'HTML' ) {
            // The container should use style='white-space: nowrap' to prevent breaking in weird places e.g the middle of a postal code
            //                      and style='margin:...' to pad around the address block (no margin is set here)
            $topMargin = "";
            $leftMargin = "";
            $lnbreak = "<br/>";
        } else if( $fmt == 'PDF' ) {
            // PDF_Label gives no margin: leading \n is for top margin, spaces for left margin
            //
            // Maybe some complex formatting is possible using FPDF::GetStringWidth() e.g. breaking after a very long city+prov to put postcode on next line
            $topMargin = "\n";
            $leftMargin = "  ";
            $lnbreak = "\n";
        } else {
            return( "" );
        }

        // firstname(s)/lastname(s)
        $f1 = $raMbr[$prefix.'firstname']; $f2 = $raMbr[$prefix.'firstname2'];
        $l1 = $raMbr[$prefix.'lastname'];  $l2 = $raMbr[$prefix.'lastname2'];

        if( !$f2 && !$l2 ) {                // name1 only (which is blank if all are empty)
            $name = trim("$f1 $l1");
        } else if( !$f1 && !$l1 ) {         // name2 only
            $name = trim("$f2 $l2");
        } else if( $l1 == $l2 ) {           // both names, lastname is the same
            $name = trim("$f1 & $f2 $l2");
        } else {                            // both names, lastnames are different
            $name = trim("$f1 $l1 & $f2 $l2");
        }

        if( ($company = $raMbr[$prefix.'company']) ) {
            if( $name ) $name .= $lnbreak.$leftMargin;
            $name .= $company;
        }
        if( ($dept = $raMbr[$prefix.'dept']) ) {
            if( $name ) $name .= $lnbreak.$leftMargin;
            $name .= $dept;
        }

        $text = $topMargin.$leftMargin.$name.$lnbreak
                          .$leftMargin.$raMbr[$prefix.'address'].$lnbreak
                          .$leftMargin.$raMbr[$prefix.'city']." ".$raMbr[$prefix.'province']." ".$raMbr[$prefix.'postcode'];
        if( !in_array( ($country = $raMbr[$prefix.'country']), ['','Canada','CANADA'] ) ) {
            $text .= $lnbreak.$leftMargin.$country;
        }
        return( $text );
    }

    function GetAllValues( $mbrid )   // mbrid can be _key or email
    {
        return( is_numeric($mbrid) ? $this->oDB->GetRecordVals( 'M', $mbrid )
                                   : $this->oDB->GetRecordValsCond( 'M', "email='".addslashes($mbrid)."'" ) );
    }

    function GetBasicValues( $mbrid )   // mbrid can be _key or email
    {
        $raOut = [];

        if( ($raM = $this->GetAllValues($mbrid)) ) {
            foreach( $this->raFldsBasic as $k=>$dummy ) { $raOut[$k] = $raM[$k]; }
        }

        return( $raOut );
    }

    private function getMbrKfr( $mbrid )
    {
        return( is_numeric($mbrid) ? $this->oDB->GetKFR( 'M', $mbrid )
                                   : $this->oDB->GetKFRCond( 'M', "email='".addslashes($mbrid)."'", [] ) );
    }

    function EBullSubscribe( $mbrid, $bSubscribe )
    /*********************************************
        Subscribe/unsubscribe the ebulletin
     */
    {
        $ok = false;

        if( ($kfr = $this->getMbrKfr( $mbrid )) ) {
            $kfr->SetValue( 'bNoEBull', !$bSubscribe );     // bNoEBull==0 is the default, which means bSubscribe
            $ok = $kfr->PutDBRow();
        }
        return( $ok );
    }


    function AddMbrDonation( $ra )
    {
        $kDonation = 0;

        if( ($kfr = $this->oDB->KFRel('D')->CreateRecord()) ) {
            $kfr->SetValue( 'fk_mbr_contacts', $ra['kMbr'] );
            $kfr->SetValue( 'date_received',   $ra['date_received'] );
            $kfr->SetValue( 'amount',          $ra['amount'] );
            $kfr->SetValue( 'receipt_num',     $ra['receipt_num'] );
            $kfr->SetNull( 'date_issued' );
            if( $kfr->PutDBRow() ) {
                $kDonation = $kfr->Key();
            }
        }
        return( $kDonation );
    }

    function BuildDonorTable()
    {
        include_once( "MbrIntegrity.php" );

        $s = "";

        $oInteg = new MbrIntegrity( $this->oApp );
        $s = $oInteg->ReportDonations();

        return( $s );
    }
}

class Mbr_ContactsDB extends Keyframe_NamedRelations
{
    private $oApp;

    function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;
        parent::__construct( $oApp->kfdb, $oApp->sess->GetUID(), $oApp->logdir );
    }

    protected function initKfrel( KeyframeDatabase $kfdb, $uid, $logdir )
    {
        $raKfrel = array();

        $dbname1 = $this->oApp->GetDBName('seeds1');
        $dbname2 = $this->oApp->GetDBName('seeds2');
        $defM =
            ["Tables" => [
                "M" => ["Table" => "{$dbname2}.mbr_contacts",
                        "Type"  => 'Base',
                        "Fields" => 'Auto']
            ]];
        $defD =
            ["Tables" => [
                "D" => ["Table" => "{$dbname2}.mbr_donations",
                        "Type"  => 'Base',
                        "Fields" => 'Auto'],
            ]];
        $defA =
            ["Tables" => [
                "A" => ["Table" => "{$dbname1}.sl_adoptions",
                        "Type"  => 'Base',
                        "Fields" => 'Auto'],
            ]];
        $defDxM =
            ["Tables" => [
                "D" => ["Table" => "{$dbname2}.mbr_donations",
                        "Type"  => 'Base',
                        "Fields" => 'Auto'],
                "M" => ["Table" => "{$dbname2}.mbr_contacts",
                        "Type"  => 'Join',
                        "Fields" => 'Auto']
            ]];
        $defM_D =
            ["Tables" => [
                "M" => ["Table" => "{$dbname2}.mbr_contacts",
                        "Type"  => 'Base',
                        "Fields" => 'Auto'],
                "D" => ["Table" => "{$dbname2}.mbr_donations",
                        "Type"  => "LeftJoin",
                        "JoinOn" => "D.fk_mbr_contacts=M._key",     // actually this is automatic and the Type makes it a left join
                        "Fields" => 'Auto']
            ]];

        // Adoption x MbrContact _ should have fk_mbr_donations _ can have fk_sl_pcv
        // Make it AxMxD_P if mbr_donations exist for all adoptions
        $defAxM_D_P =
            ["Tables" => [
                "A" => ["Table" => "{$dbname1}.sl_adoption",
                        "Type"  => 'Base',
                        "Fields" => 'Auto'],
                "M" => ["Table" => "{$dbname2}.mbr_contacts",
                        "Type"  => 'Join',
                        "Fields" => 'Auto'],
                "D" => ["Table" => "{$dbname2}.mbr_donations",
                        "Type"  => "LeftJoin",
                        "JoinOn" => "A.kDonation=D._key",
                        "Fields" => 'Auto'],
                "P" => ["Table" => "{$dbname1}.sl_pcv",
                        "Type"  => "LeftJoin",
                        "JoinOn" => "A.fk_sl_pcv=P._key",         // actually this is automatic and the Type makes it a left join
                        "Fields" => 'Auto']
            ]];


        $parms = $logdir ? ['logfile'=>$logdir."mbr_contacts.log"] : [];

        $raKfrel['M'] = new Keyframe_Relation( $kfdb, $defM, $uid, $parms );
        $raKfrel['D'] = new Keyframe_Relation( $kfdb, $defD, $uid, $parms );
        $raKfrel['DxM'] = new Keyframe_Relation( $kfdb, $defDxM, $uid, $parms );
        $raKfrel['M_D'] = new Keyframe_Relation( $kfdb, $defM_D, $uid, $parms );
        $raKfrel['AxM_D_P'] = new Keyframe_Relation( $kfdb, $defAxM_D_P, $uid, $parms );

        return( $raKfrel );
    }


    function GetContacts_MostRecentDonation( $raParms = [], &$retSql = null )
    /************************************************************************
        Return array of M_D where D is the most recent donation for M

            raParms:
                condM_D = condition on mbr_contacts LEFT JOIN mbr_donations with cols prefixed "M." and "D." e.g. M.email <> '' AND year(D.date_received)>'2019'
                condD2  = (not used because _D is always just the most recent donation and you can filter on that with M_D ?)
                          condition on mbr_donations with cols prefixed "D2." e.g. year(D2.date_received) > '2019'

            output:
                M_*             = mbr_contacts fields
                D_date_received = date of most recent donation that satisfies cond
                D_amount        = amount of most recent donation that satisfies cond
                D_receipt_num   = receipt number of most recent donation that satisfies cond
                D_amountTotal   = sum of amounts that satisfies cond
     */
    {
        if( !SEED_isLocal ) { echo "MySQL 5 does not support 'partition by'<br/>"; return([]); }

        $condM_D = "M.country='Canada' AND "
                  ."M.address IS NOT NULL AND M.address<>'' AND "   // address is blanked out if mail comes back RTS
                  ."NOT M.bNoDonorAppeals"
                  .(@$raParms['condM_D'] ? " AND ({$raParms['condM_D']}) " : "");
        $condD2  = @$raParms['condD2'] ? " AND ({$raParms['condD2']}) " : "";

        $dbname = $this->oApp->GetDBName('seeds2');

        /* Get each mbr_contact with a left-joined row of mbr_donations that is that contact's most recent donation.
         *
         * Table D2 is mbr_donations. Filtering of the donations rows should be done using D2.* conditions.
         * Table D1 is the result of filtering D2 with condD, partitioning that by fk_mbr_contacts
         * (partitioning happens after WHERE), sorting each partition by date_received desc, and applying row_number().
         * Therefore every row of D1 with row_num==1 is the newest donation per fk_mbr_contacts.
         * Table D is D1 filtered with row_num=1 to get just the newest donations.
         * Then M is LEFT JOINED with D and filtered with condM to get every qualified mbr_contact and their
         * most recent donation that matches condD.
         */
        $sql = "SELECT M._key as M__key,
                       M.firstname as M_firstname,M.lastname as M_lastname,
                       M.firstname2 as M_firstname2,M.lastname2 as M_lastname2,
                       M.company as M_company,M.dept as M_dept,
                       M.address as M_address,M.city as M_city,M.postcode as M_postcode,M.province as M_province,M.country as M_country,
                       M.email as M_email,M.phone as M_phone,
                       D._key as D__key,D.date_received as D_date_received,D.receipt_num as D_receipt_num,
                       D.amount as D_amount,D.amountTotal as D_amountTotal
                    FROM {$dbname}.mbr_contacts M LEFT JOIN
                    (SELECT * FROM
                        (SELECT *,
                                row_number() over (partition by fk_mbr_contacts order by date_received desc) as row_num,
                                sum(amount) over (partition by fk_mbr_contacts) as amountTotal
                         FROM {$dbname}.mbr_donations D2 WHERE D2._status=0 $condD2) as D1
                     WHERE D1.row_num=1) as D
                ON M._key=D.fk_mbr_contacts WHERE M._status=0 AND $condM_D "
              ."ORDER BY cast(D.amountTotal as decimal) desc,M.lastname,M.firstname";
        if( $retSql !== null ) $retSql = $sql;

        return( $this->oApp->kfdb->QueryRowsRA($sql) );
    }


    const SqlCreate_Donations = "
        CREATE TABLE IF NOT EXISTS seeds_2.mbr_donations (

                _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
                _created    DATETIME,
                _created_by INTEGER,
                _updated    DATETIME,
                _updated_by INTEGER,
                _status     INTEGER DEFAULT 0,

            fk_mbr_contacts      INTEGER NOT NULL,
            date_received        DATE NOT NULL,                   # set when the donation is recorded
            amount               DECIMAL(7,2),
            receipt_num          INTEGER NOT NULL DEFAULT 0,      # set any time; 0=not set yet, -1=non-receiptable, -2=below threshold
            date_issued          DATE NULL,                       # set when the receipt is actually sent
            category             VARCHAR(200) NOT NULL DEFAULT '',# e.g. SLAdoption, SFG
            notes                TEXT,

            INDEX (fk_mbr_contacts)
        );
    ";
}


class MbrContactsList
/********************
    Fetch mailing lists that match preset criteria
 */
{
    private $oMbr;
    private $oApp;

    private $yCurrent;
    private $raGroups = ['donor'];

    function __construct( SEEDAppSessionAccount $oApp, $raConfig = [] )
    {
        $this->oApp = $oApp;
        $this->oMbr = new Mbr_Contacts($oApp);

        $this->yCurrent = @$raConfig['yCurrent'] ?: date('Y');
        $this->initGroups();
    }

    function GetGroup( $sGroup )
    {
        $ret = null;

        if( isset($this->raGroups[$sGroup]) ) {
            // fetch the mailing list if not already loaded
            if( !$this->raGroups[$sGroup]['raList'] ) {
                $this->raGroups[$sGroup]['raList'] = $this->oMbr->oDB->GetContacts_MostRecentDonation(
                        ['condM_D'=>$this->raGroups[$sGroup]['cond']], $this->raGroups[$sGroup]['sql'] );
                foreach( $this->raGroups[$sGroup]['raList'] as &$ra ) {
                    $ra['SEEDPrint:addressblock'] = Mbr_Contacts::DrawAddressBlockFromRA( $ra, 'HTML', 'M_' );
                }
            }
            $ret = $this->raGroups[$sGroup];
        }

        return( $ret );
    }

    function GetGroupCount( $sGroup ) : int
    {
        return( ($raG = $this->GetGroup($sGroup)) ? intval(@count($raG['raList'])) : 0 );
    }

    private function initGroups()
    {
        $dStart    = ($this->yCurrent - 2)."-01-01";    // include members and donors from two years ago
// End could be current date minus six months
        $dDonorEnd = "{$this->yCurrent}-07-01";         // and donors who haven't made donations since before July
        $lEN = "M.lang<>'F'";
        $lFR = "M.lang='F'";
// parameterize
        $condLarge = "D.amountTotal>=150";

        // condD2 filters mbr_donations (just get the most recent donation, which is the default)
        $condD2 = "";
        /* condM_D filters the results of the final join M_D
         * 1) Donor is anyone eligible for a donation request who made a donation between dDonorStart and dDonorEnd
         * 2) NonDonorMember is anyone eligible for a donation request who did not make a donation since dDonorStart
         *    but has been a member since dMemberStart
         * 3) This excludes anyone who neither was a member nor made a donation since dStart
         * 4) Also excludes anyone who made a donation since $dDonorEnd
         */
        $condDonor = "D.date_received IS NOT NULL AND D.date_received BETWEEN '$dStart' AND '$dDonorEnd'";
        $condNonDonorMember = "(D.date_received IS NULL OR D.date_received<'$dStart') AND M.expires>='$dStart'";

        foreach(['donorEN'    => ['title'=>'Donors English',       'cond'=>[$condDonor,          $lEN],       ],// 'order'=>"cast(donation as decimal) desc,lastname,firstname"],
                 'donorFR'    => ['title'=>'Donors French',        'cond'=>[$condDonor,          $lFR],       ],// 'order'=>"cast(donation as decimal) desc,lastname,firstname"],
                 'donor100EN' => ['title'=>'Donors English $100+', 'cond'=>[$condDonor,          $lEN, $condLarge],],// 'order'=>"cast(donation as decimal) desc,lastname,firstname"],
                 'donor100FR' => ['title'=>'Donors French $100+',  'cond'=>[$condDonor,          $lFR, $condLarge],],// 'order'=>"cast(donation as decimal) desc,lastname,firstname"],
                 'donor99EN'  => ['title'=>'Donors English $99-',  'cond'=>[$condDonor,          $lEN, "NOT $condLarge"], ],// 'order'=>"cast(donation as decimal) desc,lastname,firstname"],
                 'donor99FR'  => ['title'=>'Donors French $99-',   'cond'=>[$condDonor,          $lFR, "NOT $condLarge"], ],// 'order'=>"cast(donation as decimal) desc,lastname,firstname"],
                 'nonDonorEN' => ['title'=>'Non-donors English',   'cond'=>[$condNonDonorMember, $lEN],       ],// 'order'=>"lastname,firstname"],
                 'nonDonorFR' => ['title'=>'Non-donors French',    'cond'=>[$condNonDonorMember, $lFR],       ],// 'order'=>"lastname,firstname"]
                ] as $k => $ra )
        {
            $this->raGroups[$k] = ['title'=>$ra['title'], 'raList'=>null, 'cond'=>implode(' AND ',$ra['cond']), 'sql'=>""];
        }
    }
}
