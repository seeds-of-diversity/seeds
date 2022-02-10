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


// For debugging this, it's convenient to use a url like
// localhost/cats/jx.php?qcmd=dr-preview&bExpand=1&kDoc=3
// because it allows you to see var_dump

//var_dump($raTag);
//var_dump($raParms);
// do this to see what it contains
// [[docrep-whatever: A | B | C ]]
// $raTag['tag'] is docrep-whatever
// $raTag['target'] is A
// $raTag['raParms'][0] is the same as 'target' (A)
// $raTag['raParms'][1] B
// $raTag['raParms'][1] C


        switch( strtolower($raTag['tag']) ) {
            case 'docrep-include':
                /* Fetch and expand the document identified by 'target'.
                 * Vars for an included file are its inherited vars overridden by the including doc's vars.
                 * oDocReference is passed along so the "current" e.g. docrep-name can be found by included docs at all levels down
                 */
                if( ($oDoc = $this->oDocRepDB->GetDoc($raTag['target'])) ) {
                    $s = $oDoc->GetText('');
                    $raVars = array_merge( $oDoc->GetDocMetadataRA_Inherited(),
                                           @$raParms['raVarsFromIncluder'] ?: [] ); // including doc's vars override included doc's vars
                    $raMT = ['DocRepParms'=>['oDocRepDB'=>$this->oDocRepDB, 'oDocReference'=>$raParms['oDocReference'], 'raVarsFromIncluder'=>$raVars]];
                    $oTmpl = (new SoDMasterTemplate( $this->oApp, $raMT ))->GetTmpl();
                    $s = $oTmpl->ExpandStr($s, $raVars);
                }
                $bHandled = true;
                break;

            case 'docrep-name':
                /* Get the full name of a doc.
                 * [[docrep-name:]] name of the current document (oDocReference)
                 * [[docrep-name:kDoc]] name of the document with _key=kDoc
                 */
                if( ! $raTag['target'] ) { // if no target

                    if( ($oDoc = @$raParms['oDocReference']) ) { // use current oDoc
                        $s = $oDoc->GetNameFull();
                    }
                }
                else { // if target is provided
                    if( ($oDocTarget = $this->oDocRepDB->GetDoc($raTag['target'])) && is_numeric($raTag['target']) ) { // create new oDoc for target
                        $s = $oDocTarget->GetNameFull();
                    }
                }
                $bHandled = true;
                break;

            case 'docrep-title':
                /* Get the title of a doc.
                 * [[docrep-title:]] title of the current document (oDocReference)
                 * [[docrep-title:name-or-number]] title of the doc identified by full name or kDoc
                 */


                break;
            case 'docrep-parent':
                /* Get the numeric key of a doc's parent or 0 if this is the root.
                 * [[docrep-parent:]] parent kDoc of the current document (oDocReference)
                 * [[docrep-parent:name-or-number]] parent kDoc of the doc identified by full name or kDoc
                 */
                break;
            case 'docrep-ancestor':
                /* Get the numeric key of the nth ancestor or 0 if that's above the root.
                 * [[docrep-ancestor:n]] kDoc of nth ancestor of the current doc
                 * [[docrep-ancestor:n | name-or-number]] kDoc of nth ancestor of the doc identified by full name or kDoc
                 *
                 * Note:
                 * [[docrep-ancestor:1]] is the same as [[docrep-parent:]]
                 * [[docrep-ancestor:1|FOO]] is the same as [[docrep-parent:FOO]]
                 * [[docrep-ancestor:2]] is the same as [[docrep-parent: [[docrep-parent:]] ]]
                 */
// use GetAncestors() to implement this
                break;
            case 'docrep-sibling-prev':
                /* Get the numeric key of the previous sibling or 0 if this is the first sibling.
                 * [[docrep-sibling-prev:]] kDoc of the previous sibling of the current document
                 * [[docrep-sibling-prev:n]] kDoc of the nth previous sibling of the current document (n==1 is the same as blank)
                 * [[docrep-sibling-prev:n | name-or-number]] kDoc of the nth previous sibling of the doc identified
                 */
// implement DocRepDoc2::GetSiblingPrev() and use docrep2_docs.siborder field there
                break;
            case 'docrep-sibling-next':
                /* Get the numeric key of the next sibling or 0 if this is the last sibling.
                 * See docrep-sibling-prev for format.
                 */
                break;
            case 'docrep-sibling-first':
                /* Get the numeric key of the first sibling (could be this doc).
                 * [[docrep-sibling-first:]] kDoc of the first sibling of the current document
                 * [[docrep-sibling-first:name-or-number]] kDoc of the first sibling of the doc identified
                 */
                break;
            case 'docrep-sibling-last':
                /* Get the numeric key of the last sibling (could be this doc).
                 * See docrep-sibling-first for format.
                 */
                break;
            case 'docrep-child-first':
                /* Get the numeric key of the first child or 0 if there are no children.
                 * [[docrep-child-first:]] kDoc of the first child of the current document
                 * [[docrep-child-first:name-or-number]] kDoc of the first child of the doc identified
                 */
                break;
            case 'docrep-child-last':
                /* Get the numeric key of the last child (i.e. the child with the greatest siborder) or 0 if there are no children.
                 * See docrep-child-first for format.
                 */
                break;
            case 'docrep-child-count':
                /* Get the number of children
                 * See docrep-child-first for format.
                 */
                break;

            default:
                $bHandled = false;
        }

        done:
        return( [$bHandled,$s] );
    }
}
