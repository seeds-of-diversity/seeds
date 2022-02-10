<?php

/* DocRepTag
 *
 * Copyright 2017-2022 Seeds of Diversity Canada
 *
 * Handle SEEDTag tags for DocRep information
 */

class DocRep_TagHandler
/**********************
 */
{
    private $oApp;
    //private $raConfig;
    private $oDocRepDB;

    function __construct( SEEDAppSessionAccount $oApp, $raConfig )
    /*************************************************************
        raConfig: oDocRepDB  = the docrepdb object to use
     */
    {
        $this->oApp = $oApp;
        //$this->raConfig = $raConfig;
        $this->oDocRepDB = $raConfig['oDocRepDB'];
    }


    function ResolveTagDocRep( $raTag, SEEDTagParser $oTagParser, $raParms = [] )
    /****************************************************************************
        [[docrep-*:]]
     */
    {
        $s = "";
        $bHandled = true;

        switch( strtolower($raTag['tag']) ) {
            case 'docrep-include':
                if( ($oDoc = $this->oDocRepDB->GetDoc($raTag['target'])) ) {
                    $s = $oDoc->GetText('');
                    // vars for an included file are its inherited vars overridden by the including file's provided vars
                    $raVars = array_merge( $oDoc->GetDocMetadataRA_Inherited(),
                                           @$raParms['raVarsFromIncluder'] ?: [] );
                    $raMT = ['DocRepParms'=>['oDocRepDB'=>$this->oDocRepDB, 'raVarsFromIncluder'=>$raVars]];
                    $oTmpl = (new SoDMasterTemplate( $this->oApp, $raMT ))->GetTmpl();
                    $s = $oTmpl->ExpandStr($s, $raVars);
                }
                $bHandled = true;
                break;

            default:
                $bHandled = false;
        }

        done:
        return( [$bHandled,$s] );
    }
}
