<?php

/* DocRepDB
 *
 * Copyright (c) 2006-2022 Seeds of Diversity Canada
 *
 * Manipulate DocRep documents at the database level.
 * DocRepDB methods guarantee that the integrity of the DocRepository is maintained. i.e. they clean up after errors.
 * Build your abstract DocRep manager on top of this.
 *
 * Document types (doc.type):
 *
 *      FOLDER       - (non-leaf) placeholder that groups other docs as their parent
 *      TEXT         - (leaf or non-leaf) text that is meant to be viewed as a document
 *      TEXTFRAGMENT - (leaf or non-leaf) fragment of text meant to be combined with other fragments to compose a document
 *      IMAGE        - (leaf) file meant to be embedded in a document
 *      BIN          - (leaf) text or binary file meant to be viewed standalone (e.g. pdf,docx)
 *      U_*          - (leaf) user defined type treated identically to BIN
 *
 * Storage types (data.src):
 *      TEXT         - data is stored in data.data_text
 *      FILE         - data is in a file in virtual filesystem keyed by data._key
 *      SFILE        - data is in a file in static filesystem
 *      LINK         - references another doc - allows transclusion into alternate tree structures when trees of links
 *                          reference TEXTFRAGMENTs. See "transclusion" in Wikipedia for an excellent explanation.
 *
 *
 * Upload directory defined by DOCREP_UPLOAD_DIR : normally permissions 703 (rwx----wx)
 *
 *
 * Rules for SFILE:
 *     At DocRepDB level, name can be different than sfile_name.  Apps might want them to be the same.
 *     parent is always DOCREP_PARENT_SFILE, siborder is ignored.
 *     Replace always re-uses the docdata record; there is only one version of each doc.
 *     Rename allows change of title, name, and sfile_name.  A change of sfile_name is a filesystem move.
 *     Move (change parent,siborder) doesn't mean anything, and is not supported.
 *     There is no doc tree. Generally you use sfile_name or possibly the filesystem to walk through the sfile tree.
 *     If name were independent of sfile_name, you could have a special name tree, but the app should support that, not DocRepDB.
 */

require_once( SEEDROOT."Keyframe/KeyframeRelation.php" );

class DocRep_DB
{
    const DOCREP_PARENT_DOCS = 0;     // docs with this parent are the roots of the document forest
    const DOCREP_PARENT_SFILE = -1;   // docs with this parent are in the sfile set

    public $kfdb;    // lots of code that uses oDocRepDB also wants to use this
    public $uid;
    protected $parms = array();

    protected $raPermsR;
    protected $raPermsW;

    public $sErrMsg = "";
    public $bDebug = false;  // set this to true to make errors die with an explanation (public because DocRepDoc uses it too)

    function __construct( KeyframeDatabase $kfdb, $uid, $parms = array() )
    /*********************************************************************
        $parms: bPermclass_allaccess  = boolean (default false)  - true: user has access to all documents
                bPermclass0_allaccess = boolean (default false)  - true: user has access to permclass zero
                raPermClassesR        = array(int,int,...) of permclasses that this user can read
                raPermClassesW        = array(int,int,...) of permclasses that this user can insert into / update / delete

        There are probably a lot more documents than permclasses, so the most efficient way to filter by permclass
        is for the caller to enumerate the allowable permclasses, and for DocRepDB to use (IN (permclass1,permclass2,...).
        The caller can either tell us all of the permclasses allowed for the user, or just the permclasses that are
        allowed for the user in the current app, whichever is faster/easier.
        This paradigm also has the advantage of isolating DocRep from any knowledge of where permclasses are stored
        or what they mean.
     */
    {
        $this->kfdb = $kfdb;
        $this->uid = $uid;
        $this->parms = $parms;
        $this->raPermsR = @$this->parms['raPermClassesR'] ?: array();
        $this->raPermsW = @$this->parms['raPermClassesW'] ?: array();
    }

    function ErrMsg( $sMsg )
    {
        $this->sErrMsg .= ($this->sErrMsg ? "<br/>" : "") . $sMsg;
    }

}

class DocRepDB2 extends DocRep_DB
{
    private $oRel;
    private $raDRDocsCache = array();

    function __construct( SEEDAppSessionAccount $oApp, $parms = array() )
    {
        $logdir = $parms['logdir'] = (@$parms['logdir'] ?: $oApp->logdir);
        $dbname = @$parms['db'] ? $oApp->GetDBName($parms['db']) : "";      // if blank DocRepDB uses the $oApp default connection

        parent::__construct( $oApp->kfdb, $oApp->sess->GetUID(), $parms );

        $this->parms['raPermsR'] = $this->raPermsR;     // old docrep accesses this via $this->raPermsR, new docrep accesses it via parms['raPermsR']
        $this->parms['raPermsW'] = $this->raPermsW;
        if( !@$this->parms['bPermclass_allaccess'] )   $this->parms['bPermclass_allaccess'] = false;
        if( !@$this->parms['bPermclass0_allaccess'] )  $this->parms['bPermclass0_allaccess'] = false;

        $this->oRel = new drRel( $oApp->kfdb, $oApp->sess->GetUID(), $dbname, $logdir );
    }

    function GetRel()   { return( $this->oRel ); }
    function GetParms() { return( $this->parms ); }


// It's worth doing this even if you just want the kDoc of a name. The minimal code would do almost as much work, and there's a
// pretty good chance you're going to use the cached DocRepDoc after you get the kDoc.
    function GetDocRepDoc( $sDoc ): ?DocRepDoc2 { return( $this->GetDoc( $sDoc ) ); }

    function GetDoc( $sDoc ): ?DocRepDoc2
    /***********************
        Get a DocRepDoc by kDoc or name
     */
    {
        $oDRDoc = null;

        if( !$sDoc ) goto done;

        $kDoc = 0;

        if( is_numeric($sDoc) ) {
            $kDoc = intval($sDoc);
        } else {
            if( ($kfr = $this->GetRel()->GetKFRCond('Doc', "name='".addslashes($sDoc)."'")) ) {
                $kDoc = $kfr->Key();
            }
        }
//var_dump($sDoc,$kDoc);
        if( $kDoc ) {
            if( isset($this->raDRDocsCache[$kDoc] ) ) {
                $oDRDoc = $this->raDRDocsCache[$kDoc];
            } else {
                $oDRDoc = new DocRepDoc2( $this, $kDoc );
//var_dump($oDRDoc->IsValid());
                if( $oDRDoc->IsValid() ) {
                    $this->raDRDocsCache[$kDoc] = $oDRDoc;
                } else {
                    $oDRDoc = null;
                }
            }
        }
        done:
        return( $oDRDoc );
    }

    function GetSubtree( $kParent, $depth = -1, $raParms = array() )
    /***************************************************************
        Return an array tree of descendants of $kParent
            array( childFolder1 => array( 'visible' => true,
                                          'children' => array( grandchildFolder1 => array( 'visible' => false,
                                                                                           'children' => array( ggchildDoc1 => array( 'visible' => true,
                                                                                                                                      'children' => array() ) ) ),
                                                               grandchildDoc2 => array( 'visible' => true,
                                                                                        'children' => array() ) ),
                   childDoc2 => array( 'visible' => true,
                                       'children' => array() ) )
            where every key is an integer kDoc

        If a non-visible FOLDER doc contains a visible descendant, then the folder is returned but marked invisible.
        The 'visible' flag is stored for convenience to the caller because it's difficult to compute, but in practice it should be ignored since
        all returned nodes are "visible" according to the normal rules of DocRep trees.
        All other information can be obtained by getting a DocRepDoc for each node.

        depth==x:  descend x levels (e.g. 1 gets the children only)
        depth==-1: no limit to depth
        depth==0:  used internally to indicate that recursion has gone below depth to evaluate folder visibility

        *** The structure relies on the fact that PHP adds keys in the order you define them.
            So adding to the array folder 13, then folder 2, will give array(13 => array(children), 2 => array(children))
     */
    {
        $raRet = array();

        $bIncludeDeleted = SEEDCore_ArraySmartVal( $raParms, 'bIncludeDeleted', array(false,true) );

        /* Get all the children of kParent
         */
        $raChildren = array();
// use iStatus to implement bIncludeDeleted
        if( ($kfr = $this->oRel->GetKFRC( "Doc", "kDoc_parent='$kParent'", array('sSortCol'=>'siborder') )) ) {
            while( $kfr->CursorFetch() ) {
                $raChildren[] = $kfr->Key();
            }
            $kfr->CursorClose();
        }

        /* For each child of kParent, expand visible subtrees and non-visible folders for visible children
         */
        foreach( $raChildren as $kDoc ) {
            if( !($oDoc = $this->GetDocRepDoc( $kDoc ) ) )  continue;   // continues if kDoc is non-visible

            // oDoc will be successful for non-visible folders that contain visible children.
            // The arg on PermsR_Okay prevents that check, so bVisible is only true if the doc itself is visible
            $bVisible = $oDoc->PermsR_Okay( false );

            if( $bVisible ) {
                // This is a visible doc or folder.
                // If depth > 1 or -1 recurse normally.
                // If depth == 1 make all nodes at this level look like leaves (no children).
                // If depth == 0 we're looking below depth for a visible descendant of an invisible folder. Found it, so return 'visible' and no children.
                $raRet[$kDoc]['children'] = ( $depth > 1 || $depth == -1 )
                                            ? $this->GetSubTree( $kDoc, ($depth == -1 ? -1 : $depth - 1), $raParms )
                                            : array();
                $raRet[$kDoc]['visible'] = true;

            } else if( $oDoc->GetType() == 'FOLDER' ) {
// Redundancy:
// The code below is never part of the determination about whether the invisible folder has visible descendants.
// All it does is collect the information about the visible children of an invisible folder, after the folder is determined to be visible.
// That is entirely done by PermsR_Okay which is called by GetDocRepDoc above.
// PermsR_Okay does GetSubtree for the children of the folder, and if it is not an empty array it says the invisible folder is visible.
// Then GetDocRepDoc returns above, and here we do the descent again to store the information about the children.

                // This is an invisible folder. Recurse to find a visible descendant, and go as deep as necessary to find one.
                // If the search goes below depth, only store the descendants of the required depth.
                //
                // If depth == 0 we're looking below depth for a visible descendant. Keep looking to the next level.
                //     If a child is returned (visible or invisible), it means some visible descendant was found. Return this folder, with no children,
                //     marked invisible so the parent recursion gets the same message.
                //     If no child is returned, it means no visible descendant was found. Do not return this folder, so the parent recursion gets the
                //     same message (unless there are visible siblings here).
                // If depth == 1 this is a leaf folder that will be either visible or invisible depending on the visibility of descendants. Check for
                //     visible descendants at depth=0.
                //     If a child is returned (visible or invisible), return this folder, with no children, marked invisible .  Else do not return this folder.
                // If depth > 1 or depth == -1 we're recursing normally. Get the folder's subtree at depth-1.
                //     If a child is returned (visible or invisible), return this folder, with children, marked invisible.
                //     If no child is returned, do not return the folder.
                //
                // In summary: if no child is returned, no visible descendant was found, so ignore this folder completely.
                //             if a child is returned, at depth > 1 or depth == -1 return this folder with children; at depth 0 or 1 return it with no children.
                //             always mark this folder as invisible.
                $raChildren = $this->GetSubTree( $kDoc, (($depth == -1 || $depth == 0) ? $depth : $depth - 1), $raParms );
                if( count($raChildren) ) {
                    $raRet[$kDoc]['children'] = ($depth == 0 || $depth == 1) ? array() : $raChildren;
                    $raRet[$kDoc]['visible'] = false;
                }
            }
        }
        return( $raRet );
    }

    function DocCacheClear( $kDoc )
    /******************************
        When DocRepDoc operations change info about other docs, they use this to clear old data from the cache
        e.g. when Rename() changes the names of descendants, we have to reload those descendant DocRepDocs.
        There might be many of them so we don't do it via a DocRepDoc tree traversal, so they're probably not loaded, but they could be.
     */
    {
        if( isset($this->raDRDocsCache[$kDoc] ) ) {     // this is the cache of DocRepDoc objects
            $this->raDRDocsCache[$kDoc]->ClearCache();  // this is the doc info inside the object
        }
    }

    function ExportXML( $kDoc )
    /**
     * convert subtree under $kDoc into a xml string
     * return xml string
     * will only export latest version of doc
     */
    {
        $xmlString = $this->buildXML($kDoc); // recursively create xml string

        $dom = new DOMDocument;
        $dom->loadXML($xmlString);
        $dom->preserveWhiteSpace = true; // formats string to have indents
        $dom->formatOutput = true;
        $xmlString = $dom->saveXML();
        $xmlString = htmlentities($xmlString); // escape html characters

        return $xmlString;
    }

    private function buildXML( $kDoc )
    /**
     * recursively add onto xml string
     */
    {
        $s = "";

        $oDoc = $this->GetDocRepDoc($kDoc);

        // docrep2_docs fields
        $sType = SEEDCore_HSC($oDoc->GetType());
        if( !in_array($sType, ['FOLDER','TEXT']) )  goto done;  // skip other types, not implemented
        $sName = SEEDCore_HSC($oDoc->GetName());
        $sDocMetadata = SEEDCore_HSC($oDoc->GetValue('docMetadata', ''));
        $sDocspec = SEEDCore_HSC($oDoc->GetValue('docspec', ''));

        // can't assume that the permclass number will match on other installations so import will use the insertion point's permclass
        //$iPermclass = SEEDCore_HSC($oDoc->GetPermclass());
        // assuming that this is a text type (currently don't support import of FILE,TEXTFRAGMENT,etc
        //$sType = SEEDCore_HSC($oDoc->GetType());

        // docrep2_data fields of top version
        $sText = SEEDCore_HSC($oDoc->GetText(''));
        $sTitle = SEEDCore_HSC($oDoc->GetTitle(''));
        $sDataspec = SEEDCore_HSC($oDoc->GetValue('dataspec', ''));
        $sMetadata = SEEDCore_HSC($oDoc->GetValue('metadata', ''));

        // assuming this is TEXT
        //$sSrc = htmlentities($oDoc->GetValue('data_src', ''));
        //$sFileExt = htmlentities($oDoc->GetSFileExt()); // only getter exist for this, no way to set value yet
        //$sMimetype = htmlentities($oDoc->GetValue('mimetype', ''));

        $s = "<$sType name='$sName' title='$sTitle'
                      docspec='$sDocspec' docMetadata='$sDocMetadata'
                      dataspec='$sDataspec' metadata='$sMetadata'
                      data_text='$sText'>";

        $raChildren = $this->GetSubtree($kDoc); // find all children
        foreach($raChildren as $k=>$v ){
            $s .= $this->buildXML($k); // recursively call on children
        }
        $s .= "</$sType>";

        done:
        return $s;
    }

    function ImportXML( $kDoc, String $sXML )
    /**
     * takes a xml string and converts it into a DOMDocument object
     * get root of DOMDocument object
     * puts new entries beside kDoc
     */
    {
        $oXML = new DOMDocument();
        $oXML->preserveWhiteSpace = FALSE;
        $oXML->loadXML($sXML); // convert xml string to DOMDocument object
        $oXML->formatOutput = TRUE;

        $oRootNode = $oXML->documentElement; // root element

        $this->breakXML($kDoc, $oRootNode, false); // recursively break xml starting form root
    }

    private function breakXML( $kParent, $oXML, $bInsertInto = true )
    /**
     * takes in a DOMNode object
     * recursively deconstruct xml and convert it into array of parameters
     * calls insert to add to database
     */
    {
        $parms = [];

        $parms['type'] = $oXML->nodeName;
        if( !in_array($parms['type'], ['FOLDER','TEXT']) )  goto done;  // skip other types, not implemented

        $parms['dr_name'] = $oXML->getAttribute('name'); // get all attributes from xml, array key should match $parms for insertDoc()
        $parms['dr_title'] = $oXML->getAttribute('title');
        $parms['dr_docspec'] = $oXML->getAttribute('docspec');
        $parms['dr_docMetadata'] = parse_url($oXML->getAttribute('docMetadata')); // metadata should be array, convert to array first
        $parms['dr_dataspec'] = $oXML->getAttribute('dataspec');
        $parms['dr_metadata'] = parse_url($oXML->getAttribute('metadata')); // metadata should be array, convert to array first
        //$parms['dr_mimetype'] = $oXML->getAttribute('mimetype');

        $parms['dr_data_text'] = $oXML->getAttribute('data_text');
        $parms['dr_flag'] = '';
        $parms['kDoc'] = $kParent;

        if( $bInsertInto ) { // insert all other under parent
            $parms['dr_posUnderLastChild'] = $kParent;
        }
        else { // insert first doc beside parent
            $parms['dr_posAfter'] = $kParent;
        }

        // set permclass the same as the insertion point's permclass
        $oDocParent = $this->GetDocRepDoc($kParent);
        $parms['dr_permclass'] = $oDocParent->GetPermclass();

        // add current to database
        $oDoc = new DocRepDoc2_Insert( $this );

        switch( $parms['type'] ) {
            case 'TEXT':
                $oDoc->InsertText( $parms['dr_data_text'], $parms );
                break;
            case 'FILE':
                $oDoc->InsertFile( "", $parms ); //TODO: what's the first parameter for insertfile()?
                break;
            case 'FOLDER':
                $oDoc->InsertFolder($parms);
                break;
        }

        $key = $oDoc->GetKey();
        foreach ($oXML->childNodes as $child){ // find all children and recursively call on children
            $this->breakXML($key, $child);
        }

        done:;
    }
}

class DocRepDoc2_ReadOnly
/************************
    Methods that let you get documents from the Document Repository
 */
{
    const     FLAG_INDEPENDENT = '';   // use this as a flag for GetValues which are flag-independent (values stored in docrep2_docs)

    public    $oDocRepDB;
    protected $kDoc;
    private   $bValid = false;    // to test if the constructor worked

    // cached data
    private   $raValues = [];       // array( flag1 => array(vals), flag2 => array(vals) )
    private   $raAncestors = null;
    private   $sFolderName = null;

    private   $bDebug = false;

    function __construct( DocRepDB2 $oDocRepDB, $kDoc )
    {
        $this->oDocRepDB = $oDocRepDB;
        $this->kDoc = $kDoc;

        if($kDoc != 0){
            $this->GetValues( "" );    // load the "" version to validate that the doc exists and is at least readable
            if( isset($this->raValues[""]['doc_key']) ) {
                $this->bValid = true;
            } else {
                $this->voidDoc();   // make the object unusable
            }
        }
        else{
// kDoc==0 : this object is used during Insert -- is it more correct to use voidDoc() at this point?
            $this->bValid = true;
        }
    }

    function IsValid()         { return( $this->bValid ); }
    function GetKey()          { return( $this->kDoc ); }
    function GetName()         { return( $this->GetValue( 'name', self::FLAG_INDEPENDENT ) ); }
    function GetTitle( $flag ) { return( $this->GetValue( 'title', $flag ) ); }
    function GetType()         { return( $this->GetValue( 'type', self::FLAG_INDEPENDENT ) ); }
    function GetPermclass()    { return( $this->GetValue( 'permclass', self::FLAG_INDEPENDENT ) ); }
    function GetParent()       { return( $this->GetValue( 'parent', self::FLAG_INDEPENDENT ) ); }
    function GetSibOrder()     { return( $this->GetValue( 'siborder', self::FLAG_INDEPENDENT ) ); }
    //function GetVerspec($flag) { return( $this->GetValue( 'dataspec', $flag ) ); }

    function GetValue( $k, $flag )   // return a doc property value; force caller to specify flag for safety
    {
        $ra = $this->GetValues($flag);
        return( is_array($ra) && isset($ra[$k]) ? $ra[$k] : NULL );
    }

    function GetText( $flag )
    /************************
        Return the data_text of this doc (returns "" if the data type is not text)
     */
    {
        $ra = $this->GetValues($flag);
// type can be FOLDER, TEXT, TEXTFRAGMENT, IMAGE, BIN, LINK
// src just says where it's stored
         return( ($ra['type']=='TEXT' || $ra['type']=='TEXTFRAGMENT') && $ra['data_src'] == 'TEXT' ? $ra['data_text'] : "" );
        //return( $ra['type']=='TEXT' && $ra['data_src'] == 'TEXT'
        //    ? $ra['data_text'] : "" );
    }

    function GetDocMetadataRA()
    /**************************
        Return the whole docMetadata array
     */
    {
        return( $this->GetValue('raDocMetadata', self::FLAG_INDEPENDENT) );
    }

    function GetDocMetadataValue( $k )
    /*********************************
        Return the value of docMetadata[$k] (doc metadata is unversioned so there is no $flag to select a version)
     */
    {
        return( @$this->GetDocMetadataRA()[$k] );
    }

    function GetDocMetadataRA_Inherited()
    /************************************
        Return the superposition of all docMetadata from the current doc's ancestors.
        Doc's metadata overrides parent's, which overrides grandparent's.
     */
    {
        $raMD = [];
        foreach( array_reverse($this->GetAncestors()) as $kDoc ) {  // [0] is root, [last] is current doc
            if( ($oDoc = $this->oDocRepDB->GetDoc($kDoc)) ) {
                $raMD = array_merge($raMD, $oDoc->GetDocMetadataRA());
            }
        }
        return( $raMD );
    }

    function GetMetadataValue( $k, $flag )
    /*************************************
        Return the value of Data_metadata[$k] for the 'flag' version
     */
    {
        $ra = $this->GetValue('raMetadata', $flag);
        return( @$ra[$k] );
    }

    function GetValuesVer( $iVer )
    /*****************************
        Return an array of standardized values for the given numbered version.
        These values are not cached because this method is probably not used much except in Version UI.
     */
    {
        $ra = [];

        if( ($kfrData = $this->oDocRepDB->GetRel()->GetKFRCond('Data', "fk_docrep2_docs='{$this->kDoc}' AND ver='".intval($iVer)."'")) )
        {
            $ra = $this->GetValues( self::FLAG_INDEPENDENT );       // get all the version-independent information
            $ra['ver']            = $kfrData->Value('ver');
            $ra['dataspec']       = $kfrData->Value('dataspec');
            $ra['title']          = $kfrData->Value('title');
            $ra['mimetype']       = $kfrData->Value('mimetype');
            $ra['metadata']       = $kfrData->Value('metadata');
            $ra['raMetadata']     = SEEDCore_ParmsURL2RA( $kfrData->Value('metadata') );
            $ra['data_key']       = $kfrData->Value('_key');
            $ra['data_src']       = $kfrData->Value('src');
            $ra['data_text']      = $kfrData->Value('data_text');
        }

        return( $ra );
    }

    function GetAllVersions()
    /************************
        Return an array of all information about all versions, sorted by reverse version-number
            [ verN => [ array from GetValuesVer ],
              ...
              2   =>  [ array from GetValuesVer ],
              1   =>  [ array from GetValuesVer ]
            ]
     */
    {
        $ra = [];

        if( ($kfr = $this->oDocRepDB->GetRel()->GetKFRC('Data', "fk_docrep2_docs='{$this->kDoc}'", ['sSortCol'=>'ver','bSortDown'=>true])) ) {
            while( $kfr->CursorFetch() ) {
                $ra[$kfr->Value('ver')] = $this->GetValuesVer( $kfr->Value('ver') );
            }
        }

        return( $ra );
    }

    function GetValues( $flag )
    /**************************
        Return a complete array of standardized values for the given version flag
     */
    {
        $raV = null;

        if( isset($this->raValues[$flag]) ) {
            $raV = $this->raValues[$flag];
            goto done;
        }

        // The constructor tries to load the top version. If that fails here, it sets IsValid==false and no data is stored.
        if( !($kfr = $this->getKfrDoc( $this->kDoc, $flag )) )  { goto done; }
        if( !$this->_permsR_okay( $kfr->value('permclass'), $kfr->value('type'), true ) ) { goto done; }

        // map kfr values to standardized keys (add any standardized keys you wish)
        $raV = array();
        $raV['name']           = $kfr->Value('name');
        $raV['type']           = $kfr->Value('type');
        $raV['docspec']        = $kfr->Value('docspec');
        $raV['kData_top']      = $kfr->Value('kData_top');
        $raV['permclass']      = $kfr->Value('permclass');
        $raV['parent']         = $kfr->Value('kDoc_parent');
        $raV['siborder']       = $kfr->Value('siborder');
        $raV['ver']            = $kfr->Value('Data_ver');
        $raV['dataspec']       = $kfr->Value('Data_dataspec');
        $raV['title']          = $kfr->Value('Data_title');
        $raV['mimetype']       = $kfr->Value('Data_mimetype');
        $raV['docMetadata']    = $kfr->Value('docMetadata');
        $raV['metadata']       = $kfr->Value('metadata');
        $raV['raDocMetadata']  = SEEDCore_ParmsURL2RA( $kfr->Value('docMetadata') );
        $raV['raMetadata']     = SEEDCore_ParmsURL2RA( $kfr->Value('Data_metadata') );

        // make this a client-defined variable in Data_metadata
//        $raV['desc']           = @$ra['Data_meta_desc'];

        $raV['doc_key']        = $this->GetKey();
        $raV['doc_created']    = $kfr->Value('_created');
        $raV['doc_created_by'] = $kfr->Value('_created_by');
        $raV['doc_updated']    = $kfr->Value('_updated');
        $raV['doc_updated_by'] = $kfr->Value('_updated_by');
        $raV['data_key']       = $kfr->Value('Data__key');
        $raV['data_src']       = $kfr->Value('Data_src');
        $raV['data_text']      = $kfr->Value('Data_data_text');

        $this->raValues[$flag] = $raV;

        done:
        return( $raV );
    }

    function GetSiblingPrev( $n = 1 )
    /**
     * return kDoc of nth previous sibling
     * reutrn false if sibling does not exist
     */
    {
        $sibOrder = $this->GetSibOrder();
        $parent = $this->GetParent();
        $sibOrder -= $n;

        if ( $kfr = $this->oDocRepDB->GetRel()->GetKFRCond('Doc', "kDoc_parent=$parent AND siborder=$sibOrder") ) {
            return $kfr->Key();
        }
        return false;
    }

    function GetSiblingNext( $n = 1 )
    {
        return $this->GetSiblingPrev(-$n);
    }

    function GetChildren()
    /**
     * return array of children
     */
    {
        $kDoc = $this->GetKey();
        $kfr = $this->oDocRepDB->GetRel()->GetList('Doc', "kDoc_parent=$kDoc");
        return $kfr;
    }

    function GetAncestors()
    /**********************
        Return a list of all ancestors of this doc, including kDoc but not 0.
        kDoc is the first element, the tree root is the last element.
     */
    {
        if( $this->raAncestors === null ) {
            $this->raAncestors = array();
            $kDoc = $this->kDoc;

            while( $kDoc ) {
                $oDoc = $this->oDocRepDB->GetDoc( $kDoc );
// is perms necessary?
// TODO: allow invisible folder if contains visible item
                //if( $this->_permsR_Okay( $permclass ) ) {
                    $this->raAncestors[] = $kDoc;
                //}

                $kDoc = $oDoc->GetParent();
            }
        }
        return( $this->raAncestors );
    }

    function GetParentObj()
    /**********************
        Return a DocRepDoc of the parent of this doc
     */
    {
        // use GetDocRepDoc because it can return a cached object
        return( ($kParent = $this->GetParent()) ? $this->oDocRepDB->GetDocRepDoc( $kParent ) : null );
    }

    /* Document Names
     *
     * The document name is stored as a full path name from the root, but the name tree does not necessarily match the
     * doc tree because docs can be unnamed. Unnamed structures subdivide the name heirarchy, which is handy, but imposes
     * tricky rules for name generation.
     *
     * If a doc has a name, it can always be decomposed into foldername/basename, where foldername is a unique doc name which is an ancestor (not necessarily parent), and
     * basename is not blank. Also, basenames cannot contain '/' so locating the last '/' is a good way to decompose the name.
     * If a named doc has no named ancestor then there is no '/', so decomposition must obtain the basename in that case.
     * If a doc has no basename, then it also has no doc name at all (i.e. we don't refer to it using the folder name, because that's the name of one of its ancestors).
     *
     * The doc name can be split into a foldername (whether or not it is a FOLDER) and a basename.
     *
     *  one                      name = one           foldername =             basename = one
     *      |
     *      - two                name = one/two       foldername = one         basename = two
     *      |
     *      - three              name = one/three     foldername = one         basename = three
     *      |
     *      - (noname)           name =               foldername = one         basename =           ** N.B. this doc has no name
     *                |
     *                - four     name = one/four      foldername = one         basename = four
     *
     * The tricky part is that a doc's foldername is not necessarily the same as its parent's name. It is actually the name of
     * the closest named ancestor. Also, the foldername is not present within the doc name if the doc is unnamed. That seems
     * obvious when stated this way, but it's easy to forget while coding.
     *
     * Insertion/renaming rules:
     *      When inserting/renaming a doc, the basename and tree location are provided
     *      1) if the basename is blank, the doc name is set to empty.
     *      else
     *      2) foldername = name of closest named ancestor. This could be the parent, but not necessarily.
     *      3) if foldername is blank, i.e. parent==0 or all ancestors are unnamed, there is no '/' before the basename.
     *      4) if inserting after a sibling, it's convenient that foldername of new doc = foldername of sibling
     *
     *  N.B. this scheme is used for all doc types, not just folders. Originally it was only used for folders because it was
     *  assumed that a doc inserted under a non-folder would not want the current doc's name e.g. foo.htm as part of the folder
     *  heirarchy.  In reality, the only time a non-folder name structure is useful is e.g. in a web site where the containing
     *  doc is naturally named _like_ a folder. e.g. one is a page, and one/two is a page. It would be strange to call these
     *  one.htm and one.htm/two.htm, and more confusing to put named images in a heirarchy e.g. "foo.jpg/bar.jpg" but you'd
     *  never do that.
     *  Therefore, the same name-structure conventions are used for all doctypes.
     */

//obsolete, names are always full path names
     function GetNameFull() { return( $this->GetName() ); }
    /*
    {
        $sNameFull = "";
        $raAncestors = $this->GetAncestors();

        foreach( $raAncestors as $k ) { // add name of ancestors to path
            $oDoc = new DocRepDoc2_ReadOnly($this->oDocRepDB, $k);
            $name = $oDoc->GetName();
            $sNameFull = $name . "/" .$sNameFull;
        }
        $sNameFull = substr($sNameFull, 0, -1); // remove last /

        return $sNameFull;
    }
*/

    function GetBaseName()
    /*********************
        foldername/basename     return the basename
        foldername/(unnamed)    return "" (easy because doc.name is blank)
        basename                return the basename (there is no named ancestor)
     */
    {
        if( ($name = $this->GetName()) ) {           // full path name
            if( ($n = strrpos($name,'/')) > 0 ) {    // 0-based position of rightmost '/' == # chars to left of that '/'
                // end of foldername found: get the basename
                $name = substr( $name, $n+1 );
            } else {
                // no folder name: the whole thing is the basename (i.e. this is the topmost named doc in the tree)
            }
        }
        return( $name );
    }

    function GetFolderName( $kRoot = 0 )
    /***********************************
        This is actually the closest named ancestor's name, whether or not it is a folder.

        If the doc is named, it is substr(name,0,strrpos('/'))
            name=foldername/basename     return foldername
            name=basename                return ""    (there is no named ancestor)

        If the doc is not named, we have to walk backward through the ancestor list to find a named ancestor.

        kRoot is the parent of the highest ancestor to evaluate (not sure why this is ever useful)
     */
    {
        if( $this->sFolderName === null ) {
            $this->sFolderName = "";

            if( ($name = $this->GetName()) ) {
                if( ($n = strrpos($name,'/')) > 0 ) {    // 0-based position of rightmost '/' == # chars to left of that '/'
                    // end of foldername found; get it
                    $this->sFolderName = substr( $name, 0, $n );
                } else {
                    // return "": docname has a basename but no foldername; it is the topmost named doc in the tree
                }
            } else {
                // unnamed doc, so foldername is the name of closest named ancestor
                $o = $this;
                while( $o && $o->GetKey() != $kRoot ) {
                    if( ($this->sFolderName = $o->GetName()) ) break;    // found a named ancestor
                    $o = $o->GetParentObj();
                }
            }
        }
        return( $this->sFolderName );
    }

    const NEW_DOC_NAME_OP_RENAME = "rename";    // use this for Update too, because an updated name is just a rename
    const NEW_DOC_NAME_OP_INSERT = "insert";

    function GetNewDocName( $sNewBasename, $eOp = self::NEW_DOC_NAME_OP_RENAME, $bInsertPosUnder = false )
    /*****************************************************************************************************
        Generate the doc.name for a basename that is either a rename for this doc, or being inserted relative to this doc.

        Rename:                          new basename replaces the old basename, with the same foldername
        Insert under (into) current doc: if current doc has a name, then that is the new doc's folder name
                                         if current doc is unnamed, then its foldername is the new doc's folder name
        Insert after the current doc:    the new doc's foldername is the same as the current doc's foldername
     */
    {
        $sNewFullname = "";

        /* if basename is blank, we store the doc.name as ""
         */
        if( !$sNewBasename )  goto done;

        // eliminate slashes so users can't insert weird name heirarchy
        $sNewBasename = str_replace( '/', '_', $sNewBasename );
        if( $eOp == self::NEW_DOC_NAME_OP_INSERT && $bInsertPosUnder && ($n = $this->GetName()) ) {
            $sNewFoldername = $n;
        } else if( ($n = $this->GetFoldername()) ) {
            $sNewFoldername = $n;
        } else {
            // current doc has blank foldername, so the new doc.name is just basename with no '/'
            $sNewFoldername = "";
        }

        $sNewFullname = $sNewFoldername.($sNewFoldername ? "/" : "").$sNewBasename;

        /* Prevent duplicate names by adding a unique number. Ignore collision if it's the same doc being renamed to the same name.
         */
        if( ($oDoc = $this->oDocRepDB->GetDoc( $sNewFullname )) &&
            !($eOp == self::NEW_DOC_NAME_OP_RENAME && $oDoc->GetKey() == $this->GetKey()) )     // same doc being renamed to the same name is not a collision
        {
            // put the suffix before the last dot, or at the end of the name; foo.jpg becomes foo1.jpg and foobar becomes foobar1
            if( ($iSuffix = strrpos( $sNewBasename, '.' )) === false ) {
                $iSuffix = strlen($sNewBasename);
            }

            for( $i = 1; ; ++$i ) {
                $sNewFullname = $sNewFoldername.($sNewFoldername ? "/" : "").substr($sNewBasename, 0, $iSuffix).$i.substr($sNewBasename,$iSuffix);
                if( !$this->oDocRepDB->GetDoc( $sNewFullname ) ) {
                    // this name does not exist, so keep it
                    break;
                }
            }
        }

        done:
        return( $sNewFullname );
    }

    function GetSFileExt()
    /*********************
        Get the file extension from an sfile name. This is pretty generic so you could also do this with any filename parser.
        You could use this for non-sfile FILE documents too, if you know they have extensions.

        N.B. the Data_data_file_ext field contains the file ext in the db anyway, but we're not sure we want to rely on it.
     */
    {
        $ext = "";

        $sBaseName = $this->GetName();
        if( ($i = strrpos( $sBaseName, '.' )) !== false ) {
            $ext = substr( $sBaseName, $i + 1 );
        }

        return( $ext );
    }

    function GetFlagOfCurrVer()
    /**************************
        Get the dxd flag corresponding to the current version of this doc.
     */
    {
        // use doc x dxd x data, constrain to the kData_top, and return a dxd.flag if there is one
        $kfr = $this->oDocRepDB->GetRel()->GetKFRCond( 'Doc X Dxd X Data', "Doc._key='{$this->kDoc}' AND Doc.kData_top=Data._key" );
        return( $kfr ? $kfr->Value('Dxd.flag') : "" );
    }

    function PermsR_Okay( $bDescendInvisibleFolders = true )
    /*******************************************************
        Return true if the given permclass can be read by the current user.

        $bDescendInvisibleFolders causes a check for visible descendants of invisible folders. This is turned off
        when performing that descent to prevent weird redundancies.

        GetValues() checks read permission before it stores the values, so allow the private method to work independently
        of GetPermclass() and GetType()
     */
    {
        return( $this->_permsR_Okay( $this->GetPermclass(),  $this->GetType(), $bDescendInvisibleFolders ) );
    }

    private function _permsR_Okay( $permclass, $doctype, $bDescendInvisibleFolders )
    /*******************************************************************************
     */
    {
        $drParms = $this->oDocRepDB->GetParms();
        $bOk = $drParms['bPermclass_allaccess'] ||
               ($drParms['bPermclass0_allaccess'] && $permclass == 0 ) ||
               in_array( $permclass, $drParms['raPermsR'] );

        if( !$bOk ) {
            // This doc is not normally visible but GetSubtree will succeed if it is a FOLDER containing a visible descendant.
            // In that case, we treat the folder as readable.
            // We only have to look at immediate descendants (depth==1) because if any of those are non-visible folders, GetSubtree
            // will recurse to look for visible descendants.
            if( $doctype == 'FOLDER' && $bDescendInvisibleFolders && $this->oDocRepDB->GetSubtree( $this->kDoc, 1 ) ) {
                $bOk = true;
            } else {
                // doc is not visible/readable
                if( $this->oDocRepDB->bDebug )  die( "Doc $kDoc exists but does not have R perms" );
            }
        }
        return( $bOk );
    }

    function PermsW_Okay()
    {
        return( $this->oDocRepDB->PermsW_Okay( $this->GetPermclass() ) );
    }


    function ClearCache()
    /********************
        Use this when data changes so it will be reloaded.
     */
    {
        $this->raValues = [];
        $this->raAncestors = null;
        $this->sFolderName = null;
    }

    protected function voidDoc()
    /***************************
        Use this to invalidate the doc object, e.g. when it is deleted from the db.
     */
    {
        $this->ClearCache();
        $this->bValid = false;
        $this->kDoc = 0;
    }

    protected function getKfrDoc( $kDoc, $flagOrVer )
    /************************************************
        Get kfr for Doc X Data with no permission check
     */
    {
        $kfrel        = $this->oDocRepDB->GetRel()->GetKfrel('Doc X Data');
        $kfrelWithDxd = $this->oDocRepDB->GetRel()->GetKFrel('Doc X Dxd X Data');
        return( $this->getKfrDoc_Data( $kDoc, $flagOrVer, $kfrel, $kfrelWithDxd ) );
    }

    protected function getKfrData( $kDoc, $flagOrVer )
    /************************************************
        Get kfr for Data X Doc with no permission check
     */
    {
        $kfrel        = $this->oDocRepDB->GetRel()->GetKfrel('Data X Doc');
        $kfrelWithDxd = $this->oDocRepDB->GetRel()->GetKFrel('Data X Dxd X Doc');
        return( $this->getKfrDoc_Data( $kDoc, $flagOrVer, $kfrel, $kfrelWithDxd ) );
    }

    private function getKfrDoc_Data( $kDoc, $flagOrVer, $kfrel, $kfrelWithDxd )
    {
//$this->oDocRepDB->kfdb->SetDebug(2);
        if( empty($flagOrVer) ) {
            /* Get Doc and Data for the maxVer
             */
            // Tried to do it this way, but it's hard to tell whether the first arg should be seeds.docrep2_docs, seeds2.docrep_docs or docrep2_docs
            //$colnameKey     = $kfrel->GetDBColName( "docrep2_docs", "_key" );
            //$colnameTopData = $kfrel->GetDBColName( "docrep2_docs", "kData_top" );
            $colnameKey     = 'Doc._key';
            $colnameTopData = 'Doc.kData_top';
            $kfr = $kfrel->GetRecordFromDB( "$colnameKey='$kDoc' AND $colnameTopData=Data._key" );
        } else if( is_numeric($flagOrVer) ) {
            /* Get Doc and Data for the given numbered version
             */
            $iVer = intval($flagOrVer);
            $kfr = $kfrel->GetRecordFromDB( "Doc._key='$kDoc' AND Data.ver='$iVer'" );
        } else {
            /* Get Doc and Data for the flagged DXD
             */
            //$colnameKey = $kfrel->GetDBColName( "docrep2_docs", "_key" );
            $colnameKey = 'Doc._key';
            $kfr = $kfrelWithDxd->GetRecordFromDB( "$colnameKey='$kDoc' AND Dxd.flag='$flagOrVer'" );
        }

        if( !$kfr && $this->bDebug )  var_dump( "Cannot find doc $kDoc:".(empty($flagOrVer) ? "maxVer" : $flagOrVer) );

        return( $kfr );
    }


    // much of DocRepDoc will go here




}

class DocRepDoc2 extends DocRepDoc2_ReadOnly
/***************
    Methods that let you manipulate documents in the Document Repository
 */
{
    function __construct( DocRepDB2 $oDocRepDB, $kDoc )
    {
        parent::__construct( $oDocRepDB, $kDoc );
    }

    function SetDocMetadataValue( $k, $v )
    /*************************************
        Change the value of docMetadata[$k] (doc metadata is unversioned so there is no $flag to select a version)
     */
    {
        $ra = $this->GetDocMetadataRA();
        $ra[$k] = $v;
        $this->SetDocMetadataRA( $ra );
    }

    function SetDocMetadataRA( $raDocMetadata )
    /******************************************
        Replace all docMetadata with the given array
     */
    {
        if( ($kfr = $this->getKfrDoc($this->kDoc, '')) ) {
            $kfr->SetValue( 'docMetadata', SEEDCore_ParmsRA2URL($raDocMetadata) );
            $kfr->PutDBRow();
        }
        $this->ClearCache();    // force a data refresh
    }

    function Update( $parms )
    /************************
        Update the content and/or metadata of a document
     */
    {
        $ok = true;

        if( isset($parms['src']) ) {
            // Updating content
            switch( $parms['src'] ) {
                case 'TEXT':
                    $kfrDoc = $this->getKfrDoc( $this->kDoc, '' );
                    $kfrData = $this->getKfrData( $this->kDoc, '' );
                    if( @$parms['bNewVersion'] ) {
                        // create a new version
                        $kfrNew = $kfrData->Copy();
                        $kfrNew->SetKey(0);                 // will force a new db record to be inserted
                        $kfrNew->SetValue( 'ver', $kfrNew->Value('ver') + 1 );
                        $kfrNew->SetValue( 'data_text', $parms['data_text'] );
                        $kfrNew->SetValue( 'src', $parms['src'] );             // should be idempotent but just in case
                        $kfrNew->PutDBRow();

                        // now that the new data record is saved, update the doc record to point to it
                        $kfrDoc->SetValue( 'kData_top', $kfrNew->Key() );
                        $kfrDoc->PutDBRow();
                    } else {
                        // just update the data in the current version
                        $kfrData->SetValue( 'data_text', $parms['data_text'] );
                        $kfrData->SetValue( 'src', $parms['src'] );             // should be idempotent but just in case
                        $kfrData->PutDBRow();
                    }
                    break;
            }
        }
        $this->ClearCache();    // force a data refresh

        return( $ok );
    }

    function Rename( $parms )
    /************************
        Change the name or title of a document. Changed names also rename descendants.

        parms: name     = new base name
               title    = new title
     */
    {
// make an UpdateMetadata() method and call it from here
        $ok = true;

// TODO: there is no way to change a name to ""
        if( @$parms['name'] ) {
            $newName = $this->GetNewDocName($parms['name'], self::NEW_DOC_NAME_OP_RENAME);  // convert to full path name
            $oldName = $this->GetName();

            if( $newName != $oldName ) {
                // Rename the doc
                $kfrDoc = $this->getKfrDoc( $this->kDoc, '' );
                $kfrDoc->SetValue( 'name', $newName );
                $ok = $kfrDoc->PutDBRow();

                // Rename all descendants. e.g. if this doc is renamed from a/b to a/b1, then a/b/c becomes a/b1/c
                if( $ok ) {
                    $dbNewName = addslashes($newName);
                    $dbOldName = addslashes($oldName);
                    $lenOldName = strlen($oldName);
                    if( ($kfrc = $this->oDocRepDB->GetRel()->GetKFRC('Doc', "name LIKE '${dbOldName}/%'")) ) {
                        while( $kfrc->CursorFetch() ) {
                            // rename descendant doc
                            $kfr = $this->getKfrDoc( $kfrc->Key(), '' );
                            $kfr->SetValue( 'name', $newName.substr($kfr->Value('name'),strlen($oldName)) );
                            $ok = $kfr->PutDBRow();
                            // uncache any DocRepDoc obj for this descendant
                            $this->oDocRepDB->DocCacheClear($kfrc->Key());
                        }
                        /*
                        $this->oDocRepDB->kfdb->Execute( "UPDATE {$this->oDocRepDB->GetRel()->DBName()}.docrep2_docs
                                                          SET name=CONCAT('$dbNewName',SUBSTR(name,$lenOldName))
                                                          WHERE name LIKE '{$dbNewName}/%'" );
                        */
                    }
                }
            }
        }

// TODO: there is no way to change a title to "". The solution is to only update parms that are isset()
        if( $ok && @$parms['title'] ) {
            $kfrData = $this->getKfrData( $this->kDoc, '' );
            $kfrData->SetValue( 'title', $parms['title'] );
            $ok = $kfrData->PutDBRow();
        }

        $this->ClearCache();    // force a data refresh

        return( $ok );
    }

    function UpdatePermClass( $parms )
    /**
     * update permclass
     */
    {
// make an UpdateMetadata() method and call it from here
        $ok = false;

        if( @$parms['permclass'] ) {
            $kfrDoc = $this->getKfrDoc( $this->kDoc, '' );
            $kfrDoc->SetValue( 'permclass', $parms['permclass'] );
            $ok = $kfrDoc->PutDBRow();
        }
        return( $ok );
    }

    function UpdateSchedule( $parms )
    /**
     * update schedule in docMetadata
     */
    {
        $ok = true;

        if( $this->GetType() != 'DOC' && $this->GetType() != 'TEXT' ){ // schedule can only be added to DOC and TEXT
            return false;
        }
        if( @$parms['schedule'] ){
            $this->setDocMetadataValue( 'schedule', $parms['schedule'] ); // update schedule
        }
        else{
            return false;
        }
        return( $ok );
    }

    function DeleteVersion( $iVer )
    /**
     * delete a version of the doc
     * set status to -1
     */
    {

        if( ($kfrData = $this->oDocRepDB->GetRel()->GetKFRCond('Data', "fk_docrep2_docs='{$this->kDoc}' AND ver='".intval($iVer)."'")) )
        {
            $kfrData->SetValue( '_status', -1 );
            $ok = $kfrData->PutDBRow();
        }
        return( $ok );
    }

    function RestoreVersion( $iVer )
    /**
     * restore a version of the doc
     * set status to 0
     */
    {

        if( ($kfrData = $this->oDocRepDB->GetRel()->GetKFRCond('Data', "fk_docrep2_docs='{$this->kDoc}' AND ver='".intval($iVer)."'")) )
        {
            $kfrData->SetValue( '_status', 0 );
            $ok = $kfrData->PutDBRow();
        }
        return( $ok );
    }

    function MoveUp( $steps = 1 ){

        if( $steps <= 0 ){ // not moving
            return false;
        }
        $sibOrder = $this->GetSibOrder(); // siborder of current

        while( $sibOrder > 1 && $steps > 0 ){ // swap current and previous
            $this->oDocRepDB->kfdb->Execute("UPDATE docrep2_docs SET siborder=siborder+1 WHERE kDoc_parent =" .$this->GetParent() ." AND siborder=" .($sibOrder-1)); // move sibling down
            $this->oDocRepDB->kfdb->Execute("UPDATE docrep2_docs SET siborder=siborder-1 WHERE _key=" .$this->GetKey()); // move current up
            $steps -= 1;
            $sibOrder -= 1;
        }
    }

    function MoveDown( $steps = 1 ){
        if( $steps <= 0 ){ // not moving
            return false;
        }
        $sibOrder = $this->GetSibOrder(); // siborder of current
        $maxSibOrder = $this->oDocRepDB->kfdb->Query1( "SELECT MAX(siborder) FROM docrep2_docs WHERE kDoc_parent=" .$this->GetParent() );

        while( $sibOrder < $maxSibOrder && $steps > 0 ){ // swap current and next
            $this->oDocRepDB->kfdb->Execute("UPDATE docrep2_docs SET siborder=siborder-1 WHERE kDoc_parent =" .$this->GetParent() ." AND siborder=" .($sibOrder+1)); // move sibling up
            $this->oDocRepDB->kfdb->Execute("UPDATE docrep2_docs SET siborder=siborder+1 WHERE _key=" .$this->GetKey()); // move current up
            $steps -= 1;
            $sibOrder += 1;
        }
    }
}

class DocRepDoc2_Insert extends DocRepDoc2
/**********************
    Methods that let you insert documents into the Document Repository.
    DocRepDoc is instantiated with a kDoc, but this object only takes a DocRepDB
 */
{
    function __construct( DocRepDB2 $oDocRepDB )
    {
        parent::__construct( $oDocRepDB, 0 );
    }

    function InsertFolder( $parms = array() )
    /****************************************
     */
    {
        return( $this->insertDoc( 'FOLDER', '', '', $parms ) );
    }

    function InsertText( $sText, $parms = array() )
    /**********************************************
        Create a TEXT document
     */
    {
        return( $this->insertDoc( 'TEXT', 'TEXT', $sText, $parms ) );
    }

    function InsertFile( $tmp_fname, $parms = array() )
    /**************************************************
        Create a FILE document
     */
    {
        return( $this->insertDoc( 'BIN', 'FILE', $tmp_fname, $parms ) );
    }

    function InsertSFile( $name, $tmp_fname = "", $parms = array() )
    /***************************************************************
        Create an SFILE document with the given name.
            name      = the full path name in sfile space (required)
            tmp_fname = optional file to copy (typically is_uploaded_file) - if not specified, assume somebody put the file in sfile
            parms     : permclass, comment, metadata

        $this must be a blank DocRepDoc
     */
    {
        $parms['dr_name'] = $name;

        return( $this->insertDoc( 'BIN', 'SFILE', $tmp_fname, $parms ) );
    }

    function InsertLink( $kLinkDoc, $parms = array() )
    /*************************************************
     */
    {
        return( $this->insertDoc( '', 'LINK', '', $parms ) );   // should the docType be LINK or a copy of the target's docType?
    }

    private function insertDoc( $docType, $eSrcType, $src, $parms )
    /**************************************************************
        Insert a new doc.
        $this must be a blank DocRepDoc with kDoc==0

        docType = FOLDER | TEXT | TEXTFRAGMENT | IMAGE | BIN | U_* (user type treated like BIN)
        eSrcType = TEXT | FILE | SFILE | LINK
        src     = eSrcType==TEXT:  the content
                          | FILE:  the uploaded file name
                          | SFILE: an optional uploaded file name
                          | LINK:  dest kDoc

        For SFILE:
            $src is an optional uploaded file name - if blank create a doc pointing to dr_name and assume somebody put it there
            dr_name is the path rooted at sfile/
            dr_bReplaceCurrVersion is forced to true (for replace)
            dr_posUnder/posAfter are ignored, kDoc_parent is always DOCREP_PARENT_SFILE


        Basic parms:
        parms['dr_name'] = the name of the doc (basename relative to dr_posUnder/dr_posAfter doc)
        parms['dr_title'] = the title of the doc
        parms['dr_docspec']  = user string for searching, grouping, etc - for the document (applies to all versions)
        parms['dr_dataspec'] = user string for searching, grouping, etc - for the version
        parms['dr_flag'] = the flag associated with the new version
        parms['dr_permclass'] = integer permclass
        parms['dr_mimetype'] = the mime type
        parms['dr_fileext'] = the file extension

        Control parms:
        parms['dr_bEraseOldVersion']            mainly for use with FILE to delete old files to save disk space (new data goes in a new data record)
        parms['dr_bReplaceCurrVersion']         mainly for use with TEXT for minor updates that don't preserve current version (new data overwrites current data record)
        parms['dr_posUnder'] = kDoc of parent (make this doc the first child)
        parms['dr_posAfter'] = kDoc of sibling (make this doc the next sibling)

        User Metadata:
        parms['dr_metadata'][]  // not implemented, undefined whether these override or totally replace existing metadata
     */
    {
        if( $this->GetKey() )  return( false );                        // $this must be a blank DocRepDoc

        if( @$parms['dr_name'] ) {
            if( @$parms['dr_posUnder']) {
                $parms['dr_name'] = $this->oDocRepDB->GetDoc($parms['dr_posUnder'])->GetNewDocName( $parms['dr_name'], self::NEW_DOC_NAME_OP_INSERT, true );
            }
            else if ( @$parms['dr_posUnderLastChild'] ) {
                $parms['dr_name'] = $this->oDocRepDB->GetDoc($parms['dr_posUnderLastChild'])->GetNewDocName( $parms['dr_name'], self::NEW_DOC_NAME_OP_INSERT, true );
            }
            else if( @$parms['dr_posAfter'] ) {
                $parms['dr_name'] = $this->oDocRepDB->GetDoc($parms['dr_posAfter'])->GetNewDocName( $parms['dr_name'], self::NEW_DOC_NAME_OP_INSERT, false );
            }
        }

        $kfrDoc = $this->oDocRepDB->GetRel()->GetKFRel('Doc')->CreateRecord() or die( "Can't create blank kfrDoc" );
        $kfrData = $this->oDocRepDB->GetRel()->GetKFRel('Data')->CreateRecord() or die( "Can't create blank kfrDocData" );

        if( $docType == "FOLDER" ) {
            $eSrcType = "TEXT";     // there is no storage for this type, so normalize to this value to be tidy
        }
        if( !in_array( $eSrcType, array('TEXT', 'FILE', 'SFILE', 'LINK') ) ) {
            die( "Invalid insertion srcType $eSrcType" );
        }

        $kfrDoc->SetValue( "type", $docType );
        $kfrDoc->PutDBRow() or die( "Cannot create doc record" );    // get the doc key for docdata.fk_docrep2_docs
        if( !($this->kDoc = $kfrDoc->Key()) )  return( null );

        $kfrData->SetValue( "src", $eSrcType );
        $kfrData->SetValue( "ver", 1 );
        $kfrData->SetValue( "fk_docrep2_docs", $kfrDoc->Key() );
        $kfrData->PutDBRow() or die( "Cannot create docdata record" );  // store the linked record now for integrity, in case of failures below

        $kfrDoc->SetValue( "kData_top", $kfrData->Key() );  // now we have the docrep2_data key

        /* Set the parent/sib
         */
        if( $eSrcType == 'SFILE' ) {
            // all docs in the sfile set have this parent, to isolate them from the regular doc forest (rooted at DOCREP_PARENT_DOCS)
            $kfrDoc->SetValue( 'kDoc_parent', DocRepDB2::DOCREP_PARENT_SFILE );
            $kfrDoc->SetValue( 'siborder', 0 );
        } else {
            $this->insert_ParentSiborder( $kfrDoc, intval(@$parms['dr_posUnder']), intval(@$parms['dr_posUnderLastChild']), intval(@$parms['dr_posAfter']) );
        }

        /* Set the dr_parms into the records
         */
        $this->insertUpdate_Metadata( $kfrDoc, $kfrData, $parms );

        /* No guarantee that everything here will work (e.g. move_uploaded_file fails), but the doc records have
         * referential integrity at this point.
         */
        if( $kfrDoc->Value('type') != 'FOLDER' ) {
            switch( $kfrData->Value('src') ) {
                case "TEXT":  $kfrData->SetValue( "data_text", $src );                      break;
                case "FILE":
                case "SFILE": $this->insertUpdate_File( $kfrDoc, $kfrData, $src, $parms );  break;
                case "LINK":  $kfrData->SetValue( "data_link_doc", intval($src) );          break;
            }
        }

        // rewrite the records to store the data/metadata
        $kfrData->PutDBRow() or die( "Cannot rewrite docdata row" );
        $kfrDoc->PutDBRow()  or die( "Cannot rewrite doc row" );
        if( ($flag = @$parms['dr_flag']) ) {
            // Make a DXD record (this doesn't change doc or data records)
            $this->oDocRepDB->VersionSetDXDFlag( $kfrDoc->Key(), $kfrData->Key(), $flag );
        }

        $this->ClearCache();    // maybe this operation messes up some cached version data so reload from the db

        return( $this->kDoc );
    }

    private function insert_ParentSiborder( $kfrDoc, $posUnderParent, $posUnderParentLast, $posAfterSibling )
    /***********************************************************************************
     */
    {

// There is no way to insert at (0,1) - the first root position
// Haven't decided whether to allow uncontained documents (0,0)
        if( $posUnderParent ) {
            /* Insert the new document as the first sibling of the given parent
             */
            $i = intval( $this->oDocRepDB->kfdb->Query1( "SELECT MIN(siborder) FROM docrep2_docs WHERE kDoc_parent='$posUnderParent'" ) );
            if( $i == 1 ) {
                $this->oDocRepDB->kfdb->Execute( "UPDATE docrep2_docs SET siborder=siborder+1 WHERE kDoc_parent='$posUnderParent'" );
            }
            $kfrDoc->SetValue( "kDoc_parent", $posUnderParent );
            $kfrDoc->SetValue( "siborder", 1 );

        }
        else if( $posUnderParentLast ){
            // insert doc as last child of parent
            $i = intval( $this->oDocRepDB->kfdb->Query1( "SELECT MAX(siborder) FROM docrep2_docs WHERE kDoc_parent='$posUnderParentLast'" ) );

            if( $i ) { // if there are other siblings
                $kfrDoc->SetValue( "kDoc_parent", $posUnderParentLast );
                $kfrDoc->SetValue( "siborder", $i+1 );
            }
            else { // if no other siblings
                $kfrDoc->SetValue( "kDoc_parent", $posUnderParentLast );
                $kfrDoc->SetValue( "siborder", 1 );
            }
        }
        else if( $posAfterSibling ) {
            /* Insert the new document as the next sibling after the given sibling
             */
            $ra = $this->oDocRepDB->kfdb->QueryRA( "SELECT kDoc_parent as parent,siborder FROM docrep2_docs WHERE _key='$posAfterSibling'" );
            $parent = $ra['parent'];
            $siborder = $ra['siborder'];
            if( $parent || $siborder ) {
                $this->oDocRepDB->kfdb->Execute( "UPDATE docrep2_docs SET siborder=siborder+1 WHERE kDoc_parent='$parent' and siborder > '$siborder'" );
                $kfrDoc->SetValue( "kDoc_parent", $parent );
                $kfrDoc->SetValue( "siborder", $siborder + 1 );
            } else {
                // ???  This condition should probably not happen, so not sure what to do.
                $kfrDoc->SetValue( "kDoc_parent", 0 );
                $kfrDoc->SetValue( "siborder", 0 );
            }
        } else {
            // ??? unspecified position - put it at 0,0 = uncontained documents
            $kfrDoc->SetValue( "kDoc_parent", 0 );
            $kfrDoc->SetValue( "siborder", 0 );
        }
    }

    private function insertUpdate_Metadata( $kfrDoc, $kfrData, $parms )
    /******************************************************************
        Use input parms to set kfrDoc and kfrData values for insertDoc and updateDoc

        dr_posUnder and dr_posAfter are not processed here: insertDoc() handles them
     */
    {
        foreach( $parms as $k => $v ) {
            switch( $k ) {
                case "dr_flag":
                case "dr_posUnder":    // insertDoc() handles these
                case "dr_posAfter":    // insertDoc() handles these
                    break;

                case "dr_name":     $kfrDoc->SetValue( "name", $v );          break;
                case "dr_docspec":  $kfrDoc->SetValue( "docspec", $v );       break;
                case "dr_permclass":$kfrDoc->SetValue( "permclass", $v );     break;
                case "dr_title":    $kfrData->SetValue( "title", $v );        break;
                case "dr_mimetype": $kfrData->SetValue( "mimetype", $v );     break;
                case "dr_dataspec": $kfrData->SetValue( "dataspec", $v );     break;
                case "dr_metadata": $kfrData->SetValue( "metadata", SEEDCore_ParmsRA2URL( $v ) ); break;
            }
        }
    }

    private function insertUpdate_File( $kfrDoc, $kfrData, $fname, $parms )
    /**********************************************************************
        "FILE":  fname is the tmp uploaded file.  Calculate its file extension and mimetype, and move it to the DOCREP_UPLOAD_DIR
        "SFILE": fname is the tmp uploaded file or "" if a file equal to the name has already been placed in DOCREP_UPLOAD_DIR."sfile/".
                 Find its file ext and mimetype.
     */
    {
        global $fileExt2Mimetype;

        $fExt = @$parms['dr_fileext'];
        if( empty($fExt) && $kfrData->Value('src') == "SFILE" )  $fExt = substr( strrchr( $fname, '.' ), 1 );
        if( empty($fExt) )  $fExt = substr( strrchr( $kfrDoc->value('name'), '.' ), 1 );
        if( !empty($fExt) ) $kfrData->SetValue( "data_fileext", $fExt );

        if( $kfrData->IsEmpty("mimetype") ) {
            $mimetype = @$fileExt2Mimetype[strtolower($kfrData->Value("data_fileext"))];
            if( empty($mimetype) ) {
                $mimetype = "application/octet-stream";
            }
            $kfrData->SetValue( "mimetype", $mimetype );
        }

        if( $kfrData->Value('src') == "SFILE" ) {
// there could be another parm for sfile_name, which would allow name to be different from sfile_name
            $kfrData->SetValue( "sfile_name", $kfrDoc->value('name') );

            if( $fname ) {
                // a temp file was uploaded
                if( !is_uploaded_file( $fname ) ) {
                    die( "SFILE was not uploaded" );
                }
                $fnameDest = $this->oDocRepDB->GetDataFilename($kfrData, true);

                SEEDCore_MkDirForFile( $fnameDest );
                if( !move_uploaded_file( $fname, $fnameDest ) ) {
                    die( "Cannot move SFILE" );
                }
            } else {
                // assume somebody put a file in the same place as the name
            }
        } else {
            // FILE
            if( !is_uploaded_file( $fname ) ) {
                die( "File was not uploaded" );
            }
            if( !move_uploaded_file( $fname, $this->oDocRepDB->GetDataFilename($kfrData) ) ) {
                die( "Cannot move file" );
            }
        }

    }
}

class drRel extends Keyframe_NamedRelations
{
    private $sDB = "";

    function __construct( KeyframeDatabase $kfdb, $uid, $sDB, $logdir )
    {
        if( $sDB ) $this->sDB = $sDB.".";
        parent::__construct( $kfdb, $uid, $logdir );
    }

    function DBName()  { return( $this->sDB ); }

    protected function initKfrel( KeyFrameDatabase $kfdb, $uid, $logdir )
    {
        $kdefDoc = array( "Tables" =>
            array( "Doc" =>  array( "Table" => "{$this->sDB}docrep2_docs",      "Fields" => "Auto" ) ) );
        $kdefData = array( "Tables" =>
            array( "Data" => array( "Table" => "{$this->sDB}docrep2_data",      "Fields" => "Auto" ) ) );
        $kdefDxd = array( "Tables" =>
            array( "Dxd" =>  array( "Table" => "{$this->sDB}docrep2_docxdata",  "Fields" => "Auto" ) ) );

// TODO: are these only used for the cases where doc.kData_top==data._key? If so, add that JoinOn here instead of making it a condition elsewhere.
        $kdefDocData = array( "Tables" =>
            array( "Doc" =>  array( "Table" => "{$this->sDB}docrep2_docs",      "Fields" => "Auto" ),
                   "Data" => array( "Table" => "{$this->sDB}docrep2_data",      "Fields" => "Auto" ) ) );
        $kdefDataDoc = array( "Tables" =>
            array( "Data" => array( "Table" => "{$this->sDB}docrep2_data",      "Fields" => "Auto" ),
                   "Doc" =>  array( "Table" => "{$this->sDB}docrep2_docs",      "Fields" => "Auto" ) ) );

        $kdefDocXData = array( "Tables" =>
            array( "Doc" =>  array( "Table" => "{$this->sDB}docrep2_docs",      "Fields" => "Auto" ),
                   "Dxd" =>  array( "Table" => "{$this->sDB}docrep2_docxdata",  "Fields" => "Auto" ),
                   "Data" => array( "Table" => "{$this->sDB}docrep2_data",      "Fields" => "Auto" ) ) );
        $kdefDataXDoc = array( "Tables" =>
            array( "Data" => array( "Table" => "{$this->sDB}docrep2_data",      "Fields" => "Auto" ),
                   "Dxd" =>  array( "Table" => "{$this->sDB}docrep2_docxdata",  "Fields" => "Auto" ),
                   "Doc" =>  array( "Table" => "{$this->sDB}docrep2_docs",      "Fields" => "Auto" ) ) );


        $raParms = defined('SITE_LOG_ROOT') ? array( 'logfile' => $logdir."docrep2.log" ) : array();
        $raKfrel = array();
        $raKfrel['Doc']              = new KeyFrame_Relation( $kfdb, $kdefDoc,      $uid, $raParms );
        $raKfrel['Data']             = new KeyFrame_Relation( $kfdb, $kdefData,     $uid, $raParms );
        $raKfrel['Dxd']              = new KeyFrame_Relation( $kfdb, $kdefDxd,      $uid, $raParms );
        $raKfrel['Doc X Data']       = new KeyFrame_Relation( $kfdb, $kdefDocData,  $uid, $raParms );
        $raKfrel['Data X Doc']       = new KeyFrame_Relation( $kfdb, $kdefDataDoc,  $uid, $raParms );
        $raKfrel['Doc X Dxd X Data'] = new KeyFrame_Relation( $kfdb, $kdefDocXData, $uid, $raParms );
        $raKfrel['Data X Dxd X Doc'] = new KeyFrame_Relation( $kfdb, $kdefDataXDoc, $uid, $raParms );

        return( $raKfrel );
    }
}


define("DOCREP2_DB_TABLE_DOCREP_DOCS",
"
CREATE TABLE docrep2_docs (
    # Each row is a logical representation of a doc in the system. Contains non-versioned metadata and a reference to the current version.

        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    name                    VARCHAR(200) NOT NULL DEFAULT '',
    type                    VARCHAR(200) NOT NULL DEFAULT '',  # FOLDER - the doc is a folder with metadata but no data
                                                               # TEXT, TEXTFRAGMENT - the doc is mime type text/*
                                                               # BIN - the doc is binary; use mimetype to define handling
                                                               # IMAGE - like BIN but can be directly viewed using <img>
                                                               # U_* are user-defined types

    docspec                 VARCHAR(200) DEFAULT '',           # user defined for searching, grouping, ordering, etc
    permclass               INTEGER NOT NULL,
    kData_top               INTEGER NOT NULL DEFAULT 0,        # docrep2_data._key for the latest version

-- (parent,siborder)==(0,0) is a set of uncontained docs
    kDoc_parent             INTEGER NOT NULL DEFAULT 0,        # docrep2_docs._key of this doc's parent (0 means this is at the top)
    siborder                INTEGER NOT NULL DEFAULT 0,

    docMetadata             TEXT,                              # url-encoded  N.B. docrep2_data.metadata is versioned; this is not

    INDEX (name(20)),
    INDEX (kDoc_parent)
);
"
);


define("DOCREP2_DB_TABLE_DOCREP_DOC_X_DATA",
"
CREATE TABLE docrep2_docxdata (
    # Join docs and docdata through a screen of flags. This allows particular versions of a doc to be flagged for workflow, or other purposes.
    # Extensible: any number of flags can be attached to any version, and moved between versions atomically.
    # Efficient indexing of flagged versions: since the number of flags is probably much less than the number of versions,
    # this allows highly efficient lookup of docrep2_data for desired versions.

        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    fk_docrep2_docs     INTEGER NOT NULL,
    fk_docrep2_data     INTEGER NOT NULL,
    flag                VARCHAR(200) NOT NULL,

    INDEX (fk_docrep2_docs),
    INDEX (fk_docrep2_data)
);
"
);


define("DOCREP2_DB_TABLE_DOCREP_DATA",
"
CREATE TABLE docrep2_data (
    # Each row is a version of a document's data and metadata.

        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    fk_docrep2_docs     INTEGER NOT NULL,               # the document of which this is a version
    ver                 INTEGER NOT NULL DEFAULT 1,     # strictly order the versions chronologically
    src                 ENUM('TEXT',                    # data is stored in data_text (regardless of doc.type)
                             'FILE',                    # data is stored in a file named {_key}.{data_file_ext}
                             'SFILE',                   # data is stored in a file named by data_sfile_name under the sfile root
                             'LINK') NOT NULL,          # data is the current version of data_link_doc, but metadata is defined by this record
    data_text           TEXT NULL,                      # src=TEXT ? the text is stored here
    data_file_ext       VARCHAR(20) NULL,               # src=FILE ? the file is stored as {_key}.{data_file_ext}
    data_sfile_name     VARCHAR(500) NULL,              # src=SFILE ? the filesystem name under the sfile root
    data_link_doc       INTEGER NULL,                   # src=LINK ? current version of another doc's data (but metadata can be different)
    title               VARCHAR(200) DEFAULT '',        # metadata that is so commonly used it deserves to have its own field
    mimetype            VARCHAR(100) DEFAULT '',        # standalone docs should be served with this type in the http header
    dataspec            VARCHAR(200) DEFAULT '',        # user defined for searching, grouping, ordering, etc
    metadata            TEXT,                           # url-encoded  N.B. this is versioned; docrep2_docs.docMetadata is not

    INDEX (fk_docrep2_docs)
);
"
);



function DocRep_Setup( $oSetup, $bCreate = false )
/*************************************************
    Test whether the tables exist.
    bCreate: create the tables and insert initial data if they don't exist.

    Return true if exists (or create is successful if bCreate); return a text report in sReport

    N.B. $oSetup is a SEEDSetup.  This file doesn't include SEEDSetup.php because the setup code is very rarely used.
         Instead, the code that calls this function knows about SEEDSetup.
 */
{
    $def = ['tables' => [
        "docrep2_docs"     => [ 'create' => DOCREP2_DB_TABLE_DOCREP_DOCS,
                                'insert' => [
                                      "INSERT INTO docrep2_docs
                                              (_key,_created,_updated, name,type,kData_top,permclass,kDoc_parent,siborder)
                                       VALUES (1, NOW(), NOW(), 'folder1','FOLDER',           1,1,0,1),    -- root folder with A and B
                                              (2, NOW(), NOW(), 'folder1/pageA','DOC',        2,1,1,1),
                                              (3, NOW(), NOW(), 'folder1/pageB','DOC',        3,1,1,2),
                                              (4, NOW(), NOW(), 'folder2','FOLDER',           4,1,0,2),    -- root folder with C and D
                                              (5, NOW(), NOW(), 'folder2/pageC','DOC',        5,1,4,1),
                                              (6, NOW(), NOW(), 'folder2/pageD','DOC',        6,1,4,2),
                                              (7, NOW(), NOW(), 'folder1/folder3','FOLDER',   7,1,1,3),    -- subfolder of 1 with E and F
                                              (8, NOW(), NOW(), 'folder1/folder3/pageE','DOC',8,1,7,1),
                                              (9, NOW(), NOW(), 'folder1/folder3/pageF','DOC',9,1,7,2)"
                                     ] ],
        "docrep2_data"     => [ 'create' => DOCREP2_DB_TABLE_DOCREP_DATA,
                                'insert' => [
                                      "INSERT INTO docrep2_data (_key,_created,_updated,fk_docrep2_docs,ver,src,data_text)
                                       VALUES (1, NOW(), NOW(), 1,1,'TEXT',''),
                                              (2, NOW(), NOW(), 2,1,'TEXT','This is page A'),
                                              (3, NOW(), NOW(), 3,1,'TEXT','This is page B'),
                                              (4, NOW(), NOW(), 4,1,'TEXT',''),
                                              (5, NOW(), NOW(), 5,1,'TEXT','This is page C'),
                                              (6, NOW(), NOW(), 6,1,'TEXT','This is page D'),
                                              (7, NOW(), NOW(), 7,1,'TEXT',''),
                                              (8, NOW(), NOW(), 8,1,'TEXT','This is page E'),
                                              (9, NOW(), NOW(), 9,1,'TEXT','This is page F')"
                                     ] ],
        "docrep2_docxdata" => [ 'create' => DOCREP2_DB_TABLE_DOCREP_DOC_X_DATA,
                                'inserts' => [] ]
        ] ];
    $ok = $oSetup->SetupDBTables( $def, $bCreate ? SEEDSetup2::ACTION_CREATETABLES_INSERT : SEEDSetup2::ACTION_TESTEXIST );

    return( $ok );
}


function DRSetup( $kfdb )
{
    include_once( SEEDCORE."SEEDSetup.php" );
    $o = new SEEDSetup2( $kfdb );
    DocRep_Setup( $o, true );
    return( $o->GetReport() );
}
