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
                    // oDocReference is the original doc in the include chain, so e.g. docrep-name can be found
                    $raMT = ['DocRepParms'=>['oDocRepDB'=>$this->oDocRepDB, 'oDocReference'=>$raParms['oDocReference'], 'raVarsFromIncluder'=>$raVars]];
                    $oTmpl = (new SoDMasterTemplate( $this->oApp, $raMT ))->GetTmpl();
                    $s = $oTmpl->ExpandStr($s, $raVars);
                }
                $bHandled = true;
                break;

// For debugging this, it's convenient to use a url like
// localhost/cats/jx.php?qcmd=dr-preview&bExpand=1&kDoc=3
// because it allows you to see var_dump

            case 'docrep-name':
                if( ($oDoc = @$raParms['oDocReference']) ) {
//TODO: this only gets the base name, not the full name
//      implement a DocRepDoc2::GetNameFull() function that uses GetAncestors() to create the full name, and use GetNamFull() below
                    $s = $oDoc->GetName();
                }
//TODO: amend the above so that's what happens if $ra['target'] is blank.
//      [[docrep-name:]] gives the name of oDocReference
//      [[docrep-name:FOO]] gives the name of the doc where fullname=FOO or _key=FOO -- just use GetDoc(FOO) either way
                break;
            case 'docrep-title':
                // get the title of the document
// [[docrep-title:]] is the title of oDocReference
// [[docrep-title:FOO]] is the title of the named or numbered doc FOO
                break;
            case 'docrep-parent':
                // get the numeric key of the parent
// use the same $ra['target'] format as docrep-name and docrep-title
                break;
            case 'docrep-ancestor':
                // get the numeric key of the nth ancestor
// [[docrep-ancestor:1]] is the same as [[docrep-parent:]]
// [[docrep-ancestor:1|FOO]] is the same as [[docrep-parent:FOO]]
// [[docrep-ancestor:2]] is the same as [[docrep-parent: [[docrep-parent:]] ]]
// use GetAncestors() to implement this
                break;


            default:
                $bHandled = false;
        }

        done:
        return( [$bHandled,$s] );
    }
}
