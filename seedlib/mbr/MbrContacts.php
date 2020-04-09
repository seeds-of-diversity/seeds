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

    function GetContactName( $k )
    {
        $ra = $this->oApp->kfdb->QueryRA( "SELECT firstname,lastname,company FROM seeds2.mbr_contacts WHERE _key='$k'" );
        if( !($name = trim($ra['firstname'].' '.$ra['lastname'])) ) {
            $name = $ra['company'];
        }
        return( $name );
    }
    
    function GetBasicValues( $k )
    {
        $raOut = [];
        
        if( ($raM = $this->oDB->GetRecordVals('M', $k)) ) {
            foreach( $this->raFldsBasic as $k=>$dummy ) { $raOut[$k] = $raM[$k]; }
        }
        
        return( $raOut );
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

        $def = ["Tables" => [
                    "M" => ["Table" => "seeds2.mbr_contacts",
                            "Type"  => 'Base',
                            "Fields" => 'Auto'
               ]]];

        $parms = $logdir ? ['logfile'=>$logdir."mbr_contacts.log"] : [];

        $raKfrel['M'] = new Keyframe_Relation( $kfdb, $def, $uid, $parms );

        return( $raKfrel );
    }
}
