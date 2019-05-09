<?php

/* RosettaSEED app
 *
 * Copyright (c) 2014-2019 Seeds of Diversity Canada
 *
 */

include_once( SEEDCORE."console/console02.php" );
include_once( SEEDCORE."SEEDUI.php" );
include_once( SEEDROOT."Keyframe/KeyframeUI.php" );
include_once( SEEDLIB."sl/sldb.php" );

$consoleConfig = [
    'CONSOLE_NAME' => "rosetta",
    'HEADER' => "RosettaSEED",
//    'HEADER_LINKS' => array( array( 'href' => 'mbr_email.php',    'label' => "Email Lists",  'target' => '_blank' ),
//                             array( 'href' => 'mbr_mailsend.php', 'label' => "Send 'READY'", 'target' => '_blank' ) ),
    'TABSETS' => ['main'=> ['tabs' => [ 'species'  => ['label'=>'Species'],
                                        'cultivar' => ['label'=>'Cultivar'],
                                      ],
                            'perms' =>[ 'species'  => [],
                                        'cultivar' => [],
                                        'ghost'   => ['A notyou'],
                                        '|'  // allows screen-login even if some tabs are ghosted
                           ],
                  ],
    ],
    'urlLogin'=>'../login/',

    'consoleSkin' => 'green',
];


$oApp = new SEEDAppConsole( $config_KFDB['seeds1']
                            + array( 'sessPermsRequired' => ['W SL'],
                                     'sessUIConfig' => ['bTmpActivate'=>true, 'bLoginNotRequired'=>false, 'fTemplates'=>[SEEDAPP.'templates/seeds_sessionaccount.html'] ],
                                     'consoleConfig' => $consoleConfig,
                                     'logdir' => SITE_LOG_ROOT )
);
$oApp->kfdb->SetDebug(1);


class MyConsole02TabSet extends Console02TabSet
{
    private $oApp;
    private $oSLDB;

    private $oComp;

    function __construct( SEEDAppConsole $oApp )
    {
        global $consoleConfig;
        parent::__construct( $oApp->oC, $consoleConfig['TABSETS'] );

        $this->oApp = $oApp;
        $this->oSLDB = new SLDBRosetta( $this->oApp );
    }

    function TabSet_main_species_Init()
    {
// the namespace functionality of this derived class should probably be provided in the base class instead
        $oUI = new Rosetta_SEEDUI( $this->oApp, "Rosetta" );
        $kfrel = $this->oSLDB->GetKfrel('S');
        $cid = 'S';
        $this->oComp = new KeyframeUIComponent( $oUI, $kfrel, $cid );
    }

    function TabSet_main_species_ControlDraw()
    {
        $raSrchParms['filters'] = array(
            array( 'label'=>'Species #',  'col'=>'S._key' ),
            array( 'label'=>'Name',       'col'=>'S.name_en' ),
            array( 'label'=>'Bot name',   'col'=>'S.name_bot'  ),
        );

        $oSrch = new SEEDUIWidget_SearchControl( $this->oComp, $raSrchParms );
        $sSrch = $oSrch->Draw();
        return( "<div style='padding:15px'>$sSrch</div>" );
    }

    function TabSet_main_species_ContentDraw()
    {
        $formTemplate =
             "|||BOOTSTRAP_TABLE(class='col-md-6',class='col-md-6')\n"
            ."||| User #|| [[Text:_key | readonly]]\n"
            ."||| Name  || [[Text:name_en]]\n"
            ."||| <input type='submit'>"
            ;
        $raListParms['cols'] = array(
            array( 'label'=>'Species #',  'col'=>'_key' ),
            array( 'label'=>'Name',       'col'=>'name_en' ),
            array( 'label'=>'Bot name',   'col'=>'name_bot'  ),
        );
        //$raListParms['fnRowTranslate'] = array($this,"usersListRowTranslate");


        $this->oComp->Update();

//$this->oApp->kfdb->SetDebug(2);
        $oList = new KeyframeUIWidget_List( $this->oComp );
        $oForm = new KeyframeUIWidget_Form( $this->oComp, array('sTemplate'=>$formTemplate) );

        $this->oComp->Start();    // call this after the widgets are registered

        list($oView,$raWindowRows) = $this->oComp->GetViewWindow();
        $sList = $oList->ListDrawInteractive( $raWindowRows, $raListParms );

        $sForm = $oForm->Draw();

$sInfo = "";

        $s = $oList->Style()
            ."<div>".$sList."</div>"
            ."<div style='margin-top:20px;padding:20px;border:2px solid #999'>".$sForm."</div>"
            .$sInfo;

        return( "<div style='padding:15px'>$s</div>" );
    }

    function TabSet_main_cultivar_ControlDraw()
    {
        return( "<div style='padding:20px'>AAA</div>" );
    }

    function TabSet_main_cultivar_ContentDraw()
    {
        return( "<div style='padding:20px'>BBB</div>" );
    }
}

class Rosetta_SEEDUI extends SEEDUI
{
    private $oSVA;

    function __construct( SEEDAppSession $oApp, $sApplication )
    {
        parent::__construct();
        $this->oSVA = new SEEDSessionVarAccessor( $oApp->sess, $sApplication );
    }

    function GetUIParm( $cid, $name )      { return( $this->oSVA->VarGet( "$cid|$name" ) ); }
    function SetUIParm( $cid, $name, $v )  { $this->oSVA->VarSet( "$cid|$name", $v ); }
    function ExistsUIParm( $cid, $name )   { return( $this->oSVA->VarIsSet( "$cid|$name" ) ); }
}


$s = "[[TabSet:main]]";

$oCTS = new MyConsole02TabSet( $oApp );

$s = $oApp->oC->DrawConsole( $s, ['oTabSet'=>$oCTS] );


echo Console02Static::HTMLPage( utf8_encode($s), "", 'EN', array( 'consoleSkin'=>'green') );   // sCharset defaults to utf8

