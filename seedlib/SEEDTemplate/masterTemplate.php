<?php
/*
 * MasterTemplate
 *
 * Copyright 2016-2024 Seeds of Diversity Canada
 *
 * Handle our advanced template functions.
 *
 * As much as possible, try to include code only when necessary and create objects only when necessary.
 */

include_once( SEEDCORE."SEEDTemplateMaker.php" );

class SoDMasterTemplate
{
    private $oApp;
    private $oTmpl;

    private $oDesc = null;
    private $bUTF8;

    function __construct( SEEDAppSessionAccount $oApp, $raConfig )
    /*************************************************************
        raConfig:   config_bUTF8 = the charset of the template and therefore the charset to substitute into the template
                                    Unfortunately for historical reasons this is false by default.
     */
    {
        $this->oApp = $oApp;

        $this->bUTF8 = @$raConfig['config_bUTF8'] ?: false;

        /* OLD COMMENT FROM seedsx
         *
         *
         *
         *  $raConfig['raSEEDTemplateMakerParms'] defines any resolvers etc that should precede the usual resolvers, as well as any
         * special parms for the SEEDTemplateMaker.
         *
         * EnableDocRep :
         *      site = 'public' | 'office'
         *      flag = the DocRep flag
         *      oDocRepDB = a DocRepDB object - the default uses the given kfdb and uid and is readonly, so you mostly won't need this
         *
         * EnableSEEDLocal :
         *      oLocal = a SEEDLocal object (if not defined, we create a minimal SEEDLocal so [[Lang:]] works)
         *      lang   = 'EN' | 'FR' (only needed if oLocal not defined)
         *
         * EnableSEEDForm  : define this in raSEEDTemplateMakerParms for now
         *
         * The BasicResolver is enabled by default.
         */
        $raTmplParms = SEEDCore_ArraySmartVal1( $raConfig, 'raSEEDTemplateMakerParms', [] );    // empty array is the default value

        $raTmplParms['fTemplates'][] = SEEDAPP."templates/seeds_sessionaccount.html";
        $raTmplParms['fTemplates'][] = SEEDAPP."templates/SEEDUI.html";

        $raTmplParms['raResolvers'] = [
            // handler for SEEDContent:
            ['fn' => [$this,'ResolveTagSEEDContent'],
             'raParms' => SEEDCore_ArraySmartVal1( $raConfig, 'raResolverParms', [] )   // empty array is the default value
            ],

            // handler for misc tags: msd, etc
            ['fn' => [$this,'ResolveTagMisc'],
             'raParms' => SEEDCore_ArraySmartVal1( $raConfig, 'raResolverParms', [] )   // empty array is the default value
            ],

        ];

        // DocRepTag handler
        if( @$raConfig['DocRepParms'] ) {
            if( !($oDocRepTag = @$raConfig['oDocRepTag']) ) {
                include_once( SEEDROOT."DocRep/DocRep_Tag.php" );
                $oDocRepTag = new DocRep_TagHandler( $this->oApp, ['oDocRepDB'=>$raConfig['DocRepParms']['oDocRepDB']] );
            }
            $raTmplParms['raResolvers'][] = ['fn'=>[$oDocRepTag,'ResolveTagDocRep'], 'raParms'=>$raConfig['DocRepParms'] ];
        }

        // SEEDSessionAccountTag handler - give information about the current user (or other users)
        if( !@$raConfig['DisableSEEDSession'] ) {
            // The default handler only reveals information about the current user, and not their password.
            // You can provide a permissive handler here to show info for other users and/or show passwords.
            if( !($oSessTag = @$raConfig['oSessionAccountTag']) ) {
                include_once( SEEDCORE."SEEDSessionAccountTag.php" );
                $oSessTag = new SEEDSessionAccountTagHandler( $this->oApp, [] );
            }
            $raTmplParms['raResolvers'][] = ['fn'=>[$oSessTag,'ResolveTagSessionAccount'], 'raParms'=>[] ];
        }



        /* Basic resolver is enabled by default.
         * EnableBasicResolver=>'DISABLE' disables it.
         * EnableBasicResolver=>[parms] overrides default parms
         */
        if( @$raConfig['EnableBasicResolver'] != "DISABLE" ) {
            $raTmplParms['bEnableBasicResolver'] = true;
            $raTmplParms['raBasicResolverParms'] = ['LinkBase'=>"https://seeds.ca/", "ImgBase"=>"https://seeds.ca/d?n="]
                                                    + (@$raConfig['EnableBasicResolver'] ?: array());        // these overwrite the first array
        }
        $this->oTmpl = SEEDTemplateMaker2( $raTmplParms );
    }

    function GetTmpl()  { return( $this->oTmpl ); }

    function ResolveTagSEEDContent( $raTag, SEEDTagParser $oTagDummy_same_as_this_oTmpl_oSeedTag, $raParms = [] )
    /************************************************************************************************************
        [[SEEDContent: tag]]

        Page content for our web site
     */
    {
        $s = "";
        $bHandled = false;

        if( $raTag['tag'] != 'SEEDContent' )  goto done;

        $contentName = strtolower($raTag['target']);
        $lang = ($l = @$raTag['raParms']['1']) ? (strtolower($l)=='fr' ? "FR" : "EN")   // only defined this way for some tags
                                               : $this->oApp->lang;

        switch( $contentName ) {
            case 'home-en':
                $s .= "Home English";
                $bHandled = true;
                break;

            case 'home-fr':
                $s .= "Home French";
                $bHandled = true;
                break;

            case 'home-edit':
                $s .= "<h3>Configure Home Page</h3>";
                $bHandled = true;
                break;

            case 'events-page':
                include_once(SEEDAPP."events/eventsApp.php");
                $s .= (new EventsApp($this->oApp, ['lang'=>$lang]))->DrawEventsPage();
                $bHandled = true;
                break;

            case 'csci_species':
            case 'csci_companies':
            case 'csci_companies_varieties':
                include_once( SEEDAPP."website/csci_page.php" );
                $oSLCSCI = new SLCSCI_Public($this->oApp);
                $lang = strtoupper(@$raTag['raParms']['1'] ?? "") ?: $this->oApp->lang;

                $sSp = ""; //because it can be short-circuited
                switch( $contentName ) {
                    case 'csci_species':
                        $s .= $oSLCSCI->DrawSpeciesList($lang, ['bCompaniesOnly'=>true, 'bCount'=>true, 'bIndex'=>true] );
                        break;
                    case 'csci_companies_varieties':
                        if( ($sSp = SEEDInput_Str('psp')) && SEEDCore_StartsWith($sSp, 'spk') && ($kSp = intval(substr($sSp,3))) ) {
                            $s .= "<p><a href='{$this->oApp->PathToSelf()}'>Back to Companies</a></p>
                                   <style>.slsrc_dcvblock_companies { padding-left:40px }
                                   </style>"
                                 .$oSLCSCI->DrawCompaniesCultivars($kSp, $lang);

                            include_once( SEEDCOMMON."siteutil.php" );
                            Site_Log( "csci_sp.log", date("Y-m-d H:i:s")." {$_SERVER['REMOTE_ADDR']} | $kSp $sSp" );
                        } else {
                            // if no parms show companies instead
                            $s .= $oSLCSCI->DrawCompanies( $lang );
                        }
                        break;
                    case 'csci_companies':
                        $s .= $oSLCSCI->DrawCompanies( $lang );
                        break;
                }
                $bHandled = true;
                break;

            case 'cd':
                // move crop profiles handler from siteTemplate
                $bHandled = true;
                break;

            default:
                $bHandled = false;
        }

        done:
        return( [$bHandled,$s] );
    }

    function ResolveTagMisc( $raTag, SEEDTagParser $oTagDummy_same_as_this_oTmpl_oSeedTag, $raParms = [] )
    /*****************************************************************************************************
        [[misc tags:]]
     */
    {
        $s = "";
        $bHandled = true;

        switch( strtolower($raTag['tag']) ) {
            case 'mse':
            case 'msd': // deprecate
                /* [[mse:seedlist|kMbr]]
                 *     Show all seeds offered by kMbr, including skipped and deleted.
                 *     MSDQ is configured to override read access on seeds so the bulk emailer can show each grower their skipped and deleted seeds.
                 */
                include_once( SEEDLIB."msd/msdq.php" );
                if( $raTag['target'] == 'seedlist' ) {
                    if( !($kMbr = intval($raTag['raParms'][1])) ) {
                        if( SEED_isLocal ) $s = "<div class='alert alert-danger'>**msd:seedlist** kMbr not defined</div>";
                        goto done;
                    }

                    if( !($sSeedListStyle = $this->oTmpl->GetVar('sSeedListStyle')) ) {
                        $sSeedListStyle="font-family:verdana,arial,helvetica,sans serif;margin-bottom:15px";
                    }
                    // could specify 'config_sbdb'=>'seeds1' here but that is MSDCore's default if blank
                    $o = new MSDQ( $this->oApp, ['config_bUTF8'=>$this->bUTF8, 'config_bAllowCanSeedRead'=>true] );
                    $rQ = $o->Cmd( 'msdSeedList-Draw', ['kUidSeller'=>$kMbr,
                                                        'eStatus'=>'ALL',
                                                        // for email, use <hr/> to separate because styles don't work, and keep lines short for transport
                                                        'sBetween' => "<hr/>\n" ]
                    );
                    $s =
                    "<style>.sed_seed_skip {background-color:#ccc} .sed_seed {margin:10px}</style>"
                    .$rQ['sOut'];
                    $bHandled = true;
                }
                break;

            case 'slprofiles':
                include_once(SEEDLIB."sl/profiles/sl_profiles_db.php");

                if( $raTag['target'] == 'varinstnamelist' ) {
                    /* [[slprofiles:varinstnames | kMbr | year]]
                     *     Show the species/cultivar names of the varinst records for kMbr and year.
                     */
                    $kMbr = intval(@$raTag['raParms']['1']) ?: $this->oApp->sess->GetUID();
                    $year = intval(@$raTag['raParms']['2']) ?: date('Y');
                    foreach( (new SLProfilesDB($this->oApp))->GetVarInstNames($kMbr, $year) as $ra ) {
                        $s .= "<p>{$ra['sp']} : {$ra['cv']}</p>";
                    }
                    $bHandled = true;
                }
                break;



            default:
                $bHandled = false;
        }

        done:
        return( [$bHandled,$s] );
    }
}
