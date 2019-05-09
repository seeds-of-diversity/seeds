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

    function __construct( SEEDAppConsole $oApp )
    {
        global $consoleConfig;
        parent::__construct( $oApp->oC, $consoleConfig['TABSETS'] );

        $this->oApp = $oApp;
        $this->oSLDB = new SLDBRosetta( $this->oApp );
    }

    function TabSet_main_species_ControlDraw()
    {
        $raSrchParms['filters'] = array(
            array( 'label'=>'Species #',  'col'=>'S._key' ),
            array( 'label'=>'Name',       'col'=>'S.name_en' ),
            array( 'label'=>'Bot name',   'col'=>'S.name_bot'  ),
        );


        $oUI = new Rosetta_SEEDUI( $this->oApp, "Rosetta" );
        $kfrel = $this->oSLDB->GetKfrel('S');
        $cid = 'S';
        $oComp = new KeyframeUIComponent( $oUI, $kfrel, $cid );

        $oSrch = new SEEDUIWidget_SearchControl( $oComp, $raSrchParms );
        $sSrch = $oSrch->Draw();
        return( "<div style='padding:20px'>$sSrch</div>" );
    }

    function TabSet_main_species_ContentDraw()
    {
        $kfrel = $this->oSLDB->GetKfrel('S');
        $cid = 'S';
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


// the namespace functionality of this derived class should probably be provided in the base class instead
        $oUI = new Rosetta_SEEDUI( $this->oApp, "Rosetta" );
        $oComp = new KeyframeUIComponent( $oUI, $kfrel, $cid );
        $oComp->Update();

//$this->oApp->kfdb->SetDebug(2);
        $oList = new KeyframeUIWidget_List( $oComp );
        $oForm = new KeyframeUIWidget_Form( $oComp, array('sTemplate'=>$formTemplate) );

        $oComp->Start();    // call this after the widgets are registered

        list($oView,$raWindowRows) = $oComp->GetViewWindow();
        $sList = $oList->ListDrawInteractive( $raWindowRows, $raListParms );

        $sForm = $oForm->Draw();

$sInfo = "";
        // Have to do this after Start() because it can change things like kCurr
/*        switch( $mode ) {
            case 'Users':       $sInfo = $this->drawUsersInfo( $oComp );    break;
            case 'Groups':      $sInfo = $this->drawGroupsInfo( $oComp );   break;
            case 'Permissions': $sInfo = $this->drawPermsInfo( $oComp );    break;
        }
*/

        $s = $oList->Style()
            ."<div class='container-fluid'>"
                ."<div class='row'>"
                    ."<div class='col-md-6'>"
                        ."<div>".$sList."</div>"
                    ."</div>"
                    ."<div class='col-md-6'>"
                        ."<div style='width:90%;padding:20px;border:2px solid #999'>".$sForm."</div>"
                    ."</div>"
                ."</div>"
                .$sInfo
            ."</div>";


        return( "<div style='padding:20px'>$s</div>" );
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

