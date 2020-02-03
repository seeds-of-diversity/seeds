<?php

/* Seed Sources app
 *
 * Copyright (c) 2012-2019 Seeds of Diversity Canada
 *
 */

include_once( SEEDCORE."console/console02.php" );
include_once( SEEDCORE."SEEDUI.php" );
include_once( SEEDROOT."Keyframe/KeyframeUI.php" );
include_once( SEEDLIB."sl/sldb.php" );
include_once( SEEDLIB."sl/sources/sl_sources_lib.php" );
include_once( "_sources_edit.php" );
include_once( "_sources_download.php" );

$consoleConfig = [
    'CONSOLE_NAME' => "sources",
    'HEADER' => "Seed Sources",
//    'HEADER_LINKS' => array( array( 'href' => 'mbr_email.php',    'label' => "Email Lists",  'target' => '_blank' ),
//                             array( 'href' => 'mbr_mailsend.php', 'label' => "Send 'READY'", 'target' => '_blank' ) ),
    'TABSETS' => ['main'=> ['tabs' => [ 'sources'         => ['label'=>'Sources'],
                                        'edit'            => ['label'=>'Edit'],
                                        'downloadupload'  => ['label'=>'Download/Upload'],
                                      ],
                            'perms' =>[ 'sources'         => [ "W SLSources", "A SL", "|" ],  // SLSources-W OR SL-A],
                                        'edit'            => [ "W SLSources", "A SL", "|" ],
                                        'downloadupload'  => [ "W SLSources", "A SL", "|" ],
                                        '|'  // allows screen-login even if some tabs are ghosted
                           ],
                  ],
    ],
    'pathToSite' => '../../',

    'consoleSkin' => 'green',
];


$oApp = new SEEDAppConsole( $config_KFDB['seeds1']
                            + array( 'sessPermsRequired' => $consoleConfig['TABSETS']['main']['perms'],
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
    private $oW;    // object that does the work for the chosen tab

    function __construct( SEEDAppConsole $oApp )
    {
        global $consoleConfig;
        parent::__construct( $oApp->oC, $consoleConfig['TABSETS'] );

        $this->oApp = $oApp;
        $this->oSLDB = new SLDBRosetta( $this->oApp );
    }

    function TabSet_main_sources_Init()
    {
// the namespace functionality of this derived class should probably be provided in the base class instead -- this is duplicated in the new Rosetta
        $oUI = new Rosetta_SEEDUI( $this->oApp, "Sources" );
        $kfrel = $this->oSLDB->GetKfrel('S');
        $cid = 'S';
        $this->oComp = new KeyframeUIComponent( $oUI, $kfrel, $cid );
    }

    function TabSet_main_sources_ControlDraw()
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

    function TabSet_main_sources_ContentDraw()
    {
        $formTemplate =
             "|||BOOTSTRAP_TABLE(class='col-md-6' | class='col-md-6')\n"
            ."||| User #|| [[Text:_key | readonly]]\n"
            ."||| Name  || [[Text:name_en]]\n"
            ."||| <input type='submit'>"
            ;

        $raListConfig = [ 'bUse_key' => true ];  // constant things for the __construct that might be needed for state computation
        $raListParms = array();                  // variables that might be computed or altered during state computation

        $raListConfig['cols'] = array(
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
        );
        //$raListConfig['fnRowTranslate'] = array($this,"usersListRowTranslate");


        $this->oComp->Update();

//$this->oApp->kfdb->SetDebug(2);
        $oList = new KeyframeUIWidget_List( $this->oComp, $raListConfig );
        $oForm = new KeyframeUIWidget_Form( $this->oComp, ['fnExpandTemplate'=>array($this,'foo')] );
        //$oForm = new KeyframeUIWidget_Form( $this->oComp, ['sExpandTemplate'=>$formTemplate] );

        $this->oComp->Start();    // call this after the widgets are registered

        // GetViewWindow() uses Get_iWindowOffset() to get a ViewSlice starting at the window offset.
        // ListDrawInteractive() is smart enough to  use that slice but only if you set iViewOffset and nViewSize
        // to tell it the context of the slice. There's probably a better way to encapsulate a ViewSlice using what oComp already knows.
        list($oView,$raWindowRows) = $this->oComp->GetViewWindow();
        $oViewWindow = new SEEDUIComponent_ViewWindow( $this->oComp, ['bEnableKeys'=>true] );
        $oViewWindow->SetViewSlice( $raWindowRows, ['iViewSliceOffset' => $this->oComp->Get_iWindowOffset(),
                                                    'nViewSize' => $oView->GetNumRows()] );
        $sList = $oList->ListDrawInteractive( $oViewWindow, $raListParms );

        $sForm = $oForm->Draw();

$sInfo = "";

        $s = $oList->Style()
            ."<div>".$sList."</div>"
            ."<div style='margin-top:20px;padding:20px;border:2px solid #999'>".$sForm."</div>"
            .$sInfo;

        return( "<div style='padding:15px'>$s</div>" );
    }

    function TabSet_main_edit_Init()
    {
        $this->oW = new SLSourcesAppEdit( $this->oApp, $this->TabSetGetSVA('main','edit') );
    }

    function TabSet_main_edit_ControlDraw()
    {
        $s = "<style>.console02-tabset-controlarea { padding:15px; }</style>"
            ."AAA";

        return( $s );
    }

    function TabSet_main_edit_ContentDraw()
    {
        $s = "<style>.console02-tabset-contentarea { padding:15px; }</style>"
            .$this->oW->Draw();

        return( $s );
    }

    function TabSet_main_downloadupload_Init()
    {
        $this->oW = new SLSourcesAppDownload( $this->oApp, $this->TabSetGetSVA('main','downloadupload') );
    }

    function TabSet_main_downloadupload_ControlDraw()
    {
        $s = "<style>.console02-tabset-controlarea { padding:15px; }</style>"
            ."AAA";

        return( $s );
    }

    function TabSet_main_downloadupload_ContentDraw()
    {
        $s = "<style>.console02-tabset-contentarea { padding:15px; }</style>"
            .$this->oW->Draw();

        return( $s );
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

