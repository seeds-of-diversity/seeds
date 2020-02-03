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
    'TABSETS' => ['main'=> ['tabs' => [ 'cultivar' => ['label'=>'Cultivar'],
                                        'species'  => ['label'=>'Species'],
                                      ],
                            'perms' =>[ 'cultivar' => [],
                                        'species'  => [],
                                        'ghost'   => ['A notyou'],
                                        '|'  // allows screen-login even if some tabs are ghosted
                           ],
                  ],
    ],
    'pathToSite' => '../../',

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
    private $oSrch;
    private $oList;
    private $oForm;

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

        $this->oComp->Update();

        $raSrchConfig = [
            'filters' => [
                ['label'=>'Species #',  'col'=>'S._key'],
                ['label'=>'Name',       'col'=>'S.name_en'],
                ['label'=>'Bot name',   'col'=>'S.name_bot'],
            ]
        ];
        $this->oSrch = new SEEDUIWidget_SearchControl( $this->oComp, $raSrchConfig );

        $raListConfig = [           // constant things for the __construct that might be needed for state computation
            'bUse_key' => true,
            'cols' => [
                [ "label"=>"Sp #",      "col"=>"_key",      "w"=>30 ],
                [ "label"=>"psp",       "col"=>"psp",       "w"=>80 ],
                [ "label"=>"Name EN",   "col"=>"name_en",   "w"=>120 ],
                [ "label"=>"Index EN",  "col"=>"iname_en",  "w"=>120 ],
                [ "label"=>"Name FR",   "col"=>"name_fr",   "w"=>120 ], //, "colsel" => array("filter"=>"")),
                [ "label"=>"Index FR",  "col"=>"iname_fr",  "w"=>120 ],
                [ "label"=>"Botanical", "col"=>"name_bot",  "w"=>120 ],
                [ "label"=>"Family EN", "col"=>"family_en", "w"=>120 ],
                [ "label"=>"Family FR", "col"=>"family_fr", "w"=>120 ],
                [ "label"=>"Category",  "col"=>"category",  "w"=>60, "colsel" => array("filter"=>"") ],
            ]
        ];
        //$raListConfig['fnRowTranslate'] = array($this,"usersListRowTranslate");
//$this->oApp->kfdb->SetDebug(2);
        $this->oList = new KeyframeUIWidget_List( $this->oComp, $raListConfig );
        $this->oForm = new KeyframeUIWidget_Form( $this->oComp, ['fnExpandTemplate'=>array($this,'foo')] );

        $this->oComp->Start();    // call this after the widgets are registered
    }

    function TabSet_main_species_ControlDraw()
    {

        $sSrch = $this->oSrch->Draw();
        return( "<div style='padding:15px'>$sSrch</div>" );
    }

    function foo( $oForm )
    {
        $sStats = "";
        $s = "|||TABLE( || class='slAdminForm' width='100%' border='0')"
            ."||| *psp*       || [[text:psp|size=30]]      || *Name EN*  || [[text:name_en|size=30]]  || *Name FR*  || [[text:name_fr|size=30]]"
            ."||| *Botanical* || [[text:name_bot|size=30]] || *Index EN* || [[text:iname_en|size=30]] || *Index FR* || [[text:iname_fr|size=30]]"
            ."||| *Category*  || [[text:category]] || *Family EN*|| [[text:family_en|size=30]]|| *Family FR*|| [[text:family_fr|size=30]]"
            ."||| *Notes*     || {colspan='3'} ".$oForm->TextArea( "notes", array('width'=>'100%') )
            ."<td colspan='2'>foo".$sStats."&nbsp;</td>"
            ."|||ENDTABLE"
            ."<INPUT type='submit' value='Save'>";
            return( $s );
    }

    function TabSet_main_species_ContentDraw()
    {

        // GetViewWindow() uses Get_iWindowOffset() to get a ViewSlice starting at the window offset.
        // ListDrawInteractive() is smart enough to  use that slice but only if you set iViewOffset and nViewSize
        // to tell it the context of the slice. There's probably a better way to encapsulate a ViewSlice using what oComp already knows.
        list($oView,$raWindowRows) = $this->oComp->GetViewWindow();
        $raListParms = [          // variables that might be computed or altered during state computation
            'iViewOffset' => $this->oComp->Get_iWindowOffset(),
            'nViewSize' => $oView->GetNumRows()
        ];

        $oViewWindow = new SEEDUIComponent_ViewWindow( $this->oComp, ['bEnableKeys'=>true] );
        $oViewWindow->SetViewSlice( $raWindowRows, ['iViewSliceOffset' => $this->oComp->Get_iWindowOffset(),
                                                    'nViewSize' => $oView->GetNumRows()] );
        $sList = $this->oList->ListDrawInteractive( $raWindowRows, $oViewWindow, $raListParms );

        $sForm = $this->oForm->Draw();

$sInfo = "";

        $s = $this->oList->Style()
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

