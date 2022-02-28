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
        $oDoc = null;


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
                if( @$raTag['target'] === "0" || @$raTag['target'] === 0 ) { // if target is root
                }
                else if( ! $raTag['target'] ) { // if no target
                    if( ($oDoc = @$raParms['oDocReference']) ) { // use current oDoc
                        $s = $oDoc->GetNameFull();
                    }
                }
                else { // if target is provided
                    if( ctype_digit($raTag['target']) && $raTag['target'] > 0 && ($oDocTarget = @$this->oDocRepDB->GetDoc($raTag['target'])) ) { // create new oDoc for target
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
                if( @$raTag['target'] === "0" || @$raTag['target'] === 0 ) { // if target is root
                }
                else if( ! $raTag['target'] ) {
                    $oDoc = @$raParms['oDocReference']; // use current doc
                }
                else {
                    $oDoc = @$this->oDocRepDB->GetDoc($raTag['target']); // use target doc
                }
                if( $oDoc ) {
                    $s = $oDoc->GetTitle("");
                }
                $bHandled = true;
                break;

            case 'docrep-parent':

                /* Get the numeric key of a doc's parent or 0 if this is the root.
                 * [[docrep-parent:]] parent kDoc of the current document (oDocReference)
                 * [[docrep-parent:name-or-number]] parent kDoc of the doc identified by full name or kDoc
                 */
                if( @$raTag['target'] === "0" || @$raTag['target'] === 0 ){ // if target is root, anything above root is 0
                    $s = "0";
                }
                else if( @$raTag['target'] === "" || ! @$raTag['target']) {
                    $oDoc = @$raParms['oDocReference']; // use current doc
                }
                else {
                    $oDoc = @$this->oDocRepDB->GetDoc($raTag['target']); // use target doc
                }
                if( $oDoc ) {
                    $s = $oDoc->GetParent();
                }
                $bHandled = true;
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
                $raAncestors = [];

                if( @$raTag['raParms'][1] === "0" || @$raTag['raParms'][1] === 0 ) { // if target is root, anything above root is 0
                    $s = "0";
                }
                else if( ! @$raTag['raParms'][1] ) {
                    $oDoc = @$raParms['oDocReference']; // use current doc
                }
                else {
                    $oDoc = @$this->oDocRepDB->GetDoc($raTag['raParms'][1]); // use new doc
                }

                if( $oDoc && (ctype_digit($raTag['raParms'][0])) && ($raTag['raParms'][0] > 0) ) {
                    $raAncestors = $oDoc->GetAncestors();
                    if( ($raTag['raParms'][0]) > (count($raAncestors)-1) ){ // if ancestor is above root
                        $s = 0;
                    }
                    else {
                        $s = $raAncestors[$raTag['target']];
                    }
                }
                $bHandled = true;
                break;

            case 'docrep-sibling-prev':
                /* Get the numeric key of the previous sibling or 0 if this is the first sibling.
                 * [[docrep-sibling-prev:]] kDoc of the previous sibling of the current document
                 * [[docrep-sibling-prev:n]] kDoc of the nth previous sibling of the current document (n==1 is the same as blank)
                 * [[docrep-sibling-prev:n | name-or-number]] kDoc of the nth previous sibling of the doc identified
                 */

                $n = 1;
                if( @$raTag['raParms'][1] === "0" || @$raTag['raParms'][1] === 0 ) { // if target is root
                    $s = "0";
                }
                else if( ! @$raTag['raParms'][1] ) {
                    $oDoc = @$raParms['oDocReference']; // use current doc
                }
                else {
                    $oDoc = @$this->oDocRepDB->GetDoc($raTag['raParms'][1]); // use new doc
                }

                if( $oDoc ) {
                    if( (@ctype_digit($raTag['raParms'][0])) && ($raTag['raParms'][0] > 0) ) { // if n is provided
                        $n = $raTag['raParms'][0];
                    }
                    $s = $oDoc->GetSiblingPrev($n);
                }
                $bHandled = true;
                break;

            case 'docrep-sibling-next':
                /* Get the numeric key of the next sibling or 0 if this is the last sibling.
                 * See docrep-sibling-prev for format.
                 */
                $n = 1;
                if( @$raTag['raParms'][1] === "0" || @$raTag['raParms'][1] === 0 ) { // if target is root
                    $s = "0";
                }
                else if( ! @$raTag['raParms'][1] ) {
                    $oDoc = @$raParms['oDocReference']; // use current doc
                }
                else {
                    $oDoc = @$this->oDocRepDB->GetDoc($raTag['raParms'][1]); // use new doc
                }

                if( $oDoc ) {
                    if( (@ctype_digit($raTag['raParms'][0])) && $raTag['raParms'][0] > 0 ) { // if n is provided
                        $n = $raTag['raParms'][0];
                    }
                    $s = $oDoc->GetSiblingNext($n);

                }
                $bHandled = true;
                break;

            case 'docrep-sibling-first':
                /* Get the numeric key of the first sibling (could be this doc).
                 * [[docrep-sibling-first:]] kDoc of the first sibling of the current document
                 * [[docrep-sibling-first:name-or-number]] kDoc of the first sibling of the doc identified
                 */
                if( @$raTag['target'] === "0" || @$raTag['target'] === 0 ) { // if target is root
                    $s = "0";
                }
                else if( ! $raTag['target'] ) {
                    $oDoc = @$raParms['oDocReference']; // use current doc
                }
                else {
                    $oDoc = @$this->oDocRepDB->GetDoc($raTag['target']); // use target doc
                }
                while( $oDoc && $sib = @$oDoc->GetSiblingPrev() ) { // loop until first sibling is found
                    $oDoc = $this->oDocRepDB->GetDoc($sib);
                    $s = $oDoc->GetKey();
                }
                $bHandled = true;
                break;

            case 'docrep-sibling-last':
                /* Get the numeric key of the last sibling (could be this doc).
                 * See docrep-sibling-first for format.
                 */
                if( @$raTag['target'] === "0" || @$raTag['target'] === 0 ) { // if target is root
                    $s = "0";
                }
                else if( ! $raTag['target'] ) {
                    $oDoc = @$raParms['oDocReference']; // use current doc
                }
                else {
                    $oDoc = @$this->oDocRepDB->GetDoc($raTag['target']); // use target doc
                }
                while( $oDoc && $sib = @$oDoc->GetSiblingNext() ) { // loop until last sibling is found
                    $oDoc = $this->oDocRepDB->GetDoc($sib);
                    $s = $oDoc->GetKey();
                }
                $bHandled = true;
                break;

            case 'docrep-child-first':
                /* Get the numeric key of the first child or 0 if there are no children.
                 * [[docrep-child-first:]] kDoc of the first child of the current document
                 * [[docrep-child-first:name-or-number]] kDoc of the first child of the doc identified
                 */
                if( @$raTag['target'] === "0" || @$raTag['target'] === 0 ) { // if target is root
                    $s = "0";
                }
                else if( ! $raTag['target'] ) {
                    $oDoc = @$raParms['oDocReference']; // use current doc
                }
                else {
                    $oDoc = @$this->oDocRepDB->GetDoc($raTag['target']); // use target doc
                }
                if( $oDoc ){
                    $raChildren = $oDoc->GetChildren();
                    $s = $raChildren[0]['_key']; // first child's key
                }
                $bHandled = true;
                break;

            case 'docrep-child-last':
                /* Get the numeric key of the last child (i.e. the child with the greatest siborder) or 0 if there are no children.
                 * See docrep-child-first for format.
                 */
                if( @$raTag['target'] === "0" || @$raTag['target'] === 0 ) { // if target is root
                $s = "0";
                }
                if( ! $raTag['target'] ) {
                    $oDoc = @$raParms['oDocReference']; // use current doc
                }
                else {
                    $oDoc = @$this->oDocRepDB->GetDoc($raTag['target']); // use target doc
                }
                if( $oDoc ){
                    $raChildren = $oDoc->GetChildren();
                    $s = end($raChildren)['_key']; // first child's key
                }
                $bHandled = true;
                break;

            case 'docrep-child-count':
                /* Get the number of children
                 * See docrep-child-first for format.
                 */
                if( @$raTag['target'] === "0" || @$raTag['target'] === 0 ) { // if target is root
                    $oDoc = @$this->oDocRepDB->GetDoc($raTag['target']); // use target doc
                }
                if( ! $raTag['target'] ) {
                    $oDoc = @$raParms['oDocReference']; // use current doc
                }
                else {
                    $oDoc = @$this->oDocRepDB->GetDoc($raTag['target']); // use target doc
                }
                if( $oDoc ){
                    $raChildren = $oDoc->GetChildren();
                    $s = count($raChildren);
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
