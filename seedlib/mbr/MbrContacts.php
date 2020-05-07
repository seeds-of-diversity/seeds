<?php

class Mbr_Contacts
{
    public  $oDB;
    private $oApp;

    function __construct( SEEDAppSession $oApp )
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

        $ra = $this->oApp->kfdb->QueryRA( "SELECT firstname,lastname,firstname2,lastname2,company,city,province FROM seeds2.mbr_contacts WHERE _key='$k'" );

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

    function GetBasicValues( $k )
    {
        $raOut = [];

        if( ($raM = $this->oDB->GetRecordVals('M', $k)) ) {
            foreach( $this->raFldsBasic as $k=>$dummy ) { $raOut[$k] = $raM[$k]; }
        }

        return( $raOut );
    }

    function BuildDonorTable()
    {
        $s = "";

$this->oApp->kfdb->SetDebug(1);
        /* Find donations in mbr_contacts that are not in mbr_donations
         */
        $sql = "SELECT M._key as _key, M.donation_date as date, M.donation as amount, M.donation_receipt as receipt_num
                FROM seeds2.mbr_contacts M LEFT JOIN seeds2.mbr_donations D
                ON (M._key=D.fk_mbr_contacts AND M.donation_date=D.date_received)
                WHERE M.donation>0 AND D.fk_mbr_contacts IS NULL";
        if( ($raR = $this->oApp->kfdb->QueryRowsRA($sql)) ) {
            $s .= "<p>Copying ".count($raR)." rows</p>";

            foreach( $raR as $ra ) {
                $s .= "<p>mbr:{$ra['_key']}, received: {$ra['date']}, $ {$ra['amount']}, receipt # {$ra['receipt_num']}</p>";

                continue; // don't write changes, just show them for now

                if( !$ra['date'] ) {
                    $s .= "<p>Skipping blank date</p>";
                    continue;
                }

                $kfr = $this->oDB->KFRel('D')->CreateRecord();
                $kfr->SetValue( 'fk_mbr_contacts', $ra['_key'] );
                $kfr->SetValue( 'date_received', $ra['date'] );
                $kfr->SetValue( 'amount', $ra['amount'] );
                $kfr->SetValue( 'receipt_num', $ra['receipt_num'] );
                $kfr->SetNull( 'date_issued' );
                $kfr->PutDBRow();
            }
        }

        return( $s );
    }
}

class Mbr_ContactsDB extends Keyframe_NamedRelations
{
    private $oApp;

    function __construct( SEEDAppSession $oApp )
    {
        $this->oApp = $oApp;
        parent::__construct( $oApp->kfdb, $oApp->sess->GetUID(), $oApp->logdir );
    }

    protected function initKfrel( KeyframeDatabase $kfdb, $uid, $logdir )
    {
        $raKfrel = array();

        $defM =
            ["Tables" => [
                "M" => ["Table" => "seeds2.mbr_contacts",
                        "Type"  => 'Base',
                        "Fields" => 'Auto']
            ]];
        $defD =
            ["Tables" => [
                "D" => ["Table" => "seeds2.mbr_donations",
                        "Type"  => 'Base',
                        "Fields" => 'Auto'],
            ]];
        $defDxM =
            ["Tables" => [
                "D" => ["Table" => "seeds2.mbr_donations",
                        "Type"  => 'Base',
                        "Fields" => 'Auto'],
                "M" => ["Table" => "seeds2.mbr_contacts",
                        "Type"  => 'Join',
                        "Fields" => 'Auto']
            ]];


        $parms = $logdir ? ['logfile'=>$logdir."mbr_contacts.log"] : [];

        $raKfrel['M'] = new Keyframe_Relation( $kfdb, $defM, $uid, $parms );
        $raKfrel['D'] = new Keyframe_Relation( $kfdb, $defD, $uid, $parms );
        $raKfrel['DxM'] = new Keyframe_Relation( $kfdb, $defDxM, $uid, $parms );

        return( $raKfrel );
    }


    const SqlCreate_Donations = "
        CREATE TABLE IF NOT EXISTS seeds2.mbr_donations (

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
            notes                TEXT,

            INDEX (fk_mbr_contacts)
        );
    ";
}
