<?php

/*
 * Seed Directory member seed exchange editing interface
 *
 * Copyright 2011-2024 Seeds of Diversity Canada
 *
 * Gives the current user an interface to their own listings in the Member Seed Exchange
 */


// The report that we use to send printouts to people would be really nice as an email, per grower. It looks good in an email.

// Grower edit screen should have some clickable boxes:
//    I'm still thinking about which seeds I want to offer.
//    I'm done making changes, please list what I've said here.
//    I want my seed list to be exactly the same as last year.
// the problem is with people who logged in but didn't change anything - are they thinking, or do they want the list to remain the same?


include_once( SEEDCORE."SEEDBasket.php" );
include_once( SEEDAPP."basket/basketProductHandlers_seeds.php" );
include_once( SEEDAPP."seedexchange/mse-edit_common.php" );
include_once( SEEDAPP."seedexchange/mse-edit_ts_growers.php");
include_once( SEEDAPP."seedexchange/mse-edit_ts_seeds.php");
include_once( SEEDAPP."seedexchange/mse-edit_ts_edit.php");
include_once( SEEDAPP."seedexchange/mse-edit_ts_admin.php");
include_once( SEEDLIB."msd/msdlib.php" );


$oApp = SEEDConfig_NewAppConsole( ['db'=>'seeds1',
                                   'sessPermsRequired' => ["|", "W MSD", "W MSDOffice",
                                                                "W sed" ],                  // deprecate in favour of MSE or MSD
                                   'lang' => site_define_lang() ] );

SEEDPRG();

//var_dump($_SESSION); echo "<BR/><BR/>"; var_dump($_REQUEST);

$oMSDLib = new MSDLib( $oApp );

/* Output reports in this window with no console.
 * MSDLibReport sets header(charset) based on the format of report
 */
if( $oMSDLib->PermOfficeW() && SEEDInput_Str('doReport') ) {
    include_once( SEEDLIB."msd/msdlibReport.php" );
    echo (new MSDLibReport($oMSDLib))->Report();
    exit;
}


class MyConsole02TabSet extends Console02TabSet
{
    private $oApp;
    private $oMSDLib;
    private $oW = null;
    private $kCurrGrower = 0;
    private $kCurrSpecies = 0;

    function __construct(SEEDAppConsole $oApp, MSDLib $oMSDLib)
    {
        global $consoleConfig;
        parent::__construct( $oApp->oC, $consoleConfig['TABSETS'] );

        $this->oApp = $oApp;
        $this->oMSDLib = $oMSDLib;

        if( $this->oMSDLib->PermOfficeW() ) {
            $this->kCurrGrower = $this->oApp->oC->oSVA->SmartGPC( 'selectGrower', [0] );
        } else {
            $this->kCurrGrower = $this->oApp->sess->GetUID();
        }
        $this->kCurrSpecies = $this->oApp->oC->oSVA->SmartGPC( 'selectSpecies', [0] );    // normally an int, but can be tomatoAC, tomatoDH, etc
    }

    function TabSetPermission( $tsid, $tabname )
    {
        switch( $tabname ) {
            case 'growers':
            case 'seeds':
                return( Console02TabSet::PERM_SHOW );
            case 'edit':
            case 'office':
            case 'archive':
                return( $this->oMSDLib->PermOfficeW() ? Console02TabSet::PERM_SHOW : Console02TabSet::PERM_HIDE );
        }
        return( Console02TabSet::PERM_HIDE );
    }

    function TabSet_main_growers_Init()           { $this->oW = new MSEEditAppTabGrower($this->oApp); $this->oW->Init_Grower($this->kCurrGrower); }
    function TabSet_main_growers_ControlDraw()    { return( $this->oW->ControlDraw_Grower() ); }
    function TabSet_main_growers_ContentDraw()    { return( $this->oW->ContentDraw_Grower() ); }

    function TabSet_main_seeds_Init()             { $this->oW = new MSEEditAppTabSeeds($this->oApp); $this->oW->Init_Seeds($this->kCurrGrower, $this->kCurrSpecies); }
    function TabSet_main_seeds_ControlDraw()      { return( $this->oW->ControlDraw_Seeds() ); }
    function TabSet_main_seeds_ContentDraw()      { return( $this->oW->ContentDraw_Seeds() ); }

    function TabSet_main_edit_Init()              { $this->oW = new MSEEditAppTabOffice( $this->oMSDLib ); }
    function TabSet_main_edit_ControlDraw()       { return( $this->oMSDLib->PermOfficeW() ? $this->oW->DrawControl() : "" ); }         // MSDOfficeEditTab or MSDAdminTab
    function TabSet_main_edit_ContentDraw()       { return( $this->oMSDLib->PermOfficeW() ? $this->oW->DrawContent() : "" ); }

    function TabSet_main_office_Init()            { $this->oW = new MSEEditAppAdminTab( $this->oMSDLib ); }
    function TabSet_main_office_ControlDraw()     { return( $this->oMSDLib->PermOfficeW() ? $this->oW->DrawControl() : "" ); }         // MSDOfficeEditTab or MSDAdminTab
    function TabSet_main_office_ContentDraw()     { return( $this->oMSDLib->PermOfficeW() ? $this->oW->DrawContent() : "" ); }

    function TabSet_main_archive_Init()           {
//select A.year,nGrowers,nSeeds, nSeeds/nGrowers
//  from (select year,count(*) as nGrowers from sed_growers group by 1) as A,
//       (select year,count(*) as nSeeds from sed_seeds group by 1) as B
//  where A.year=B.year order by 1;
    }
}

$sTitle = $oApp->lang=='EN' ? "Seeds of Diversity - Your Member Seed Exchange" : "Semences du patrimoine - Votre catalogue de semences";

$consoleConfig = [
    'CONSOLE_NAME' => "msd-edit",
    'HEADER' =>  $sTitle,
    'TABSETS' => ['main'=> ['tabs' => [ 'growers' => ['label' => $oMSDLib->oTmpl->ExpandTmpl("MSEEdit_tablabel_G")],
                                        'seeds'   => ['label' => $oMSDLib->oTmpl->ExpandTmpl("MSEEdit_tablabel_S")],
                                        'edit'    => ['label' => "Edit"],
                                        'office'  => ['label' => "Office"],
                                        'archive' => ['label' => "Archive"],
                                      ],

                            'perms' => ['growers' => Console02TabSet::PERM_SHOW,
                                        'seeds'   => Console02TabSet::PERM_SHOW,
                                        'edit'    => $oMSDLib->PermOfficeW() ? Console02TabSet::PERM_SHOW : Console02TabSet::PERM_HIDE,
                                        'office'  => $oMSDLib->PermOfficeW() ? Console02TabSet::PERM_SHOW : Console02TabSet::PERM_HIDE,
                                        'archive' => $oMSDLib->PermOfficeW() ? Console02TabSet::PERM_SHOW : Console02TabSet::PERM_HIDE,
                                       ]
                           ],
                 ],
    'urlLogin'=>'../login/',

    'consoleSkin' => 'green',

];
$oApp->oC->SetConfig($consoleConfig);

$oCTS = new MyConsole02TabSet( $oApp, $oMSDLib );
// kluge to set sCharset based on current tab
// Growers and Office are cp1252, but make sure '' is too. Growers was being rendered in utf-8 on initialization, which led some members to enter notes in that charset.
$sCharset = $oCTS->TabSetGetCurrentTab('main') == 'seeds' ? 'utf-8' : 'cp1252';
$oApp->oC->SetConfig(['sCharset' => $sCharset]);
//var_dump($oCTS->TabSetGetCurrentTab('main') == 'seeds' ? 'utf-8' : 'cp1252');

$sBody = $oApp->oC->DrawConsole( "[[TabSet:main]]", ['oTabSet'=>$oCTS] );

$sHead = $oMSDLib->oTmpl->ExpandTmpl('MSEEdit_css')
        .$oMSDLib->oTmpl->ExpandTmpl('MSEEdit_js_popovers');

echo Console02Static::HTMLPage( $sBody, $sHead,
                                $oApp->lang,
                                ['consoleSkin'=>'green',
                                 'sTitle'=>$sTitle,
                                 'sCharset' => $sCharset,
                                 'raScriptFiles'=>[ W_ROOT."std/js/SEEDStd.js", W_CORE."js/SEEDCore.js", W_CORE."js/console02.js",W_CORE."js/SEEDPopover.js" ]
                                ] );
