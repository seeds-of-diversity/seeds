<?php

/* DocRepDB
 *
 * Copyright (c) 2006-2018 Seeds of Diversity Canada
 *
 * Manipulate DocRep documents at the database level.
 * DocRepDB methods guarantee that the integrity of the DocRepository is maintained. i.e. they clean up after errors.
 * Build your abstract DocRep manager on top of this.
 *
 * Internally-supported types:
 *
 *      TEXT         - (leaf or non-leaf) text that is meant to be viewed as a document
 *      IMAGE        - (leaf) file meant to be embedded in a document
 *      DOC          - (leaf) text or binary file meant to be viewed standalone (e.g. pdf)
 *      TEXTFRAGMENT - fragment of text meant to be combined with other fragments to compose a document
 *      FOLDER       - (non-leaf) placeholder that groups other docs as their parent
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

    function __construct( KeyframeDatabase $kfdb, $uid, $parms = array() )
    {
        parent::__construct( $kfdb, $uid, $parms );

        $this->parms['raPermsR'] = $this->raPermsR;     // old docrep accesses this via $this->raPermsR, new docrep accesses it via parms['raPermsR']
        $this->parms['raPermsW'] = $this->raPermsW;
        if( !@$this->parms['bPermclass_allaccess'] )   $this->parms['bPermclass_allaccess'] = false;
        if( !@$this->parms['bPermclass0_allaccess'] )  $this->parms['bPermclass0_allaccess'] = false;

        $this->oRel = new drRel( $kfdb, $uid, @$parms['sDB'], @$parms['logdir'] );
    }

    function GetRel()   { return( $this->oRel ); }
    function GetParms() { return( $this->parms ); }

/* Use GetDocRepDoc instead
    function GetDocFromName( $sName )
    [********************************
        Return kDoc of the document with the given name.  Plain integer names are converted to intval.
     *]
    {
        $kDoc = 0;

        if( empty($sName) ) goto done;

        if( is_numeric($sName) ) {
            $kfr = $this->oRel->GetKFR( 'Doc', intval($sName) );
        } else {
            $kfr = $this->oRel->GetKFRCond( 'Doc', "name='".addslashes($sName)."'" );
        }

        if( $kfr && $this->PermsR_Okay( $kfr->Key(), $kfr->Value('permclass') ) ) {
            $kDoc = $kfr->Key();
        }

        done:
        return( $kDoc );
    }
*/

// It's worth doing this even if you just want the kDoc of a name. The minimal code would do almost as much work, and there's a
// pretty good chance you're going to use the cached DocRepDoc after you get the kDoc.
    function GetDocRepDoc( $sDoc ) { return( $this->GetDoc( $sDoc ) ); }

    function GetDoc( $sDoc )
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
            if( ($kfr = $this->oRel->GetKFRCond( "Doc", "name='".addslashes($sDoc)."'" )) ) {
                $kDoc = $kfr->Key();
            }
        }

        if( $kDoc ) {
            if( isset($this->raDRDocsCache[$kDoc] ) ) {
                $oDRDoc = $this->raDRDocsCache[$kDoc];
            } else {
                $oDRDoc = new DocRepDoc2( $this, $kDoc );
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

    private   $raValues = array();    // array( flag1 => array(vals), flag2 => array(vals) )
    private   $raAncestors = null;
    private   $sFolderName = null;

    private   $bDebug = true;

    function __construct( DocRepDB2 $oDocRepDB, $kDoc )
    {
        $this->oDocRepDB = $oDocRepDB;
        $this->kDoc = $kDoc;
        $this->GetValues( "" );    // load the "" version to validate that the doc exists and is at least readable
        if( isset($this->raValues[""]['doc_key']) ) {
            $this->bValid = true;
        } else {
            $this->voidDoc();   // make the object unusable
        }
    }

    function IsValid()         { return( $this->bValid ); }
    function GetKey()          { return( $this->kDoc ); }
    function GetName()         { return( $this->GetValue( 'name', self::FLAG_INDEPENDENT ) ); }
    function GetTitle( $flag ) { return( $this->GetValue( 'title', $flag ) ); }
    function GetType()         { return( $this->GetValue( 'type', self::FLAG_INDEPENDENT ) ); }
    function GetPermclass()    { return( $this->GetValue( 'permclass', self::FLAG_INDEPENDENT ) ); }
    function GetParent()       { return( $this->GetValue( 'parent', self::FLAG_INDEPENDENT ) ); }
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
        return( $ra['type']=='DOC' && $ra['data_src'] == 'TEXT' ? $ra['data_text'] : "" );
    }

    function GetMetadataValue( $k, $flag )
    /*************************************
        Return the value of Data_metadata[$k] for the 'flag' version
     */
    {
        $ra = $this->GetValues($flag);
        return( @$ra['raMetadata'][$k] );
    }

    function GetValuesVer( $iVer )
    /*****************************
        Return an array of standardized values for the given numbered version.
        These values are not cached because this method is probably not used much except in Version UI.
     */
    {

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
        $raV['ver']            = $kfr->Value('Data_ver');
        $raV['dataspec']       = $kfr->Value('Data_dataspec');
        $raV['title']          = $kfr->Value('Data_title');
        $raV['mimetype']       = $kfr->Value('Data_mimetype');
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
     * The doc name can be split into a foldername (whether or not it is a FOLDER) and a basename.
     * Typically, this is used in UI where the user provides the base name and the foldername is derived from doc ancestors.
     *
     *  one                      name = one           foldername =             basename = one
     *      |
     *      - two                name = one/two       foldername = one         basename = two
     *      |
     *      - three              name = one/three     foldername = one         basename = three
     *      |
     *      - (noname)           name =               foldername = one         basename =
     *                |
     *                - four     name = one/four      foldername = one         basename = four
     *
     * The tricky part is that a doc's foldername is not necessarily the same as its parent's name. It is actually the name of
     * the closest named ancestor. Also, the foldername is not present within the doc name if the doc is unnamed. That seems
     * obvious when stated this way, but it's easy to forget while coding.
     *
     * Insertion rules:
     *      When inserting a named doc, the docname = foldername/name
     *      1) if foldername is blank, there is no '/'.  This would happen at parent=0 (example one),
     *         or if all ancestors were unnamed.
     *      2) if the parent has a name, then foldername=parent name
     *      3) if the parent doesn't have a name, then foldername=name of closest named ancestor.
     *      4) if inserting after a sibling, it's convenient to know that foldername of new doc = foldername of sibling
     *
     *  When inserting an unnamed doc, the docname is always blank.
     *  This is what creates the trickiness, because then the foldername is not stored in the doc, imposing an ancestor-search.
     *
     *  N.B. this scheme is used for all doc types, not just folders. Originally it was only used for folders because it was
     *  assumed that a doc inserted under a non-folder would not want the current doc's name e.g. foo.htm as part of the folder
     *  heirarchy.  In reality, the only time a non-folder name structure is useful is e.g. in a web site where the containing
     *  doc is naturally named _like_ a folder. e.g. one is a page, and one/two is a page. It would be strange to call these
     *  one.htm and one.htm/two.htm, and more confusing to put named images in a heirarchy e.g. "foo.jpg/bar.jpg" but you'd
     *  never do that.
     *  Therefore, the same name-structure conventions are used for all doctypes.
     *
     *  Can base names contain '/' ?
     *      No. So GetNewDocName converts '/' to '_'
     */

//    function GetDocName()   { return( $this->GetName() ); }  // same thing; we call it docname to differentiate from basename and foldername
    function GetBaseName()
    {
        $sName = $this->GetName();
        if( ($n = strrpos($sName,'/')) > 0 ) {    // 0-based position of rightmost '/' == # chars to left of that '/'
            $sName = substr( $sName, $n + 1 );
        }
        return( $sName );
    }

    function GetFolderName( $kRoot = 0 )
    /***********************************
        This is actually the closest named ancestor's name, whether or not it is a folder.
        If the doc is named, it is substr(name,0,strrpos('/'))
        If the doc is not named, we have to walk backward through the ancestor list to find a named ancestor.

        kRoot is the highest ancestor to evaluate (not sure why this is ever useful)
     */
    {
        if( $this->sFolderName === null ) {
            $this->sFolderName = "";

            if( ($name = $this->GetName()) ) {
                if( ($n = strrpos($name,'/')) > 0 ) {    // 0-based position of rightmost '/' == # chars to left of that '/'
                    $this->sFolderName = substr( $name, 0, $n );
                }
            } else {
                // Foldername = name of closest named ancestor
                $o = $this;
                while( $o = $o->GetParentObj() ) {
                    if( ($this->sFolderName = $o->GetName()) ) break;    // found a named ancestor
                    if( $o->GetKey() == $kRoot )  break;                 // reached the root ancestor
                }
            }
        }
        return( $this->sFolderName );
    }

    function GetNewDocName( $sName, $bInsertInto = false )
    /*****************************************************
        Generate the docname of a new doc that is being inserted relative to this doc.

        $sName is the (new) base name of a document, either being inserted, updated or renamed.
        $bInsertInto == (inserting a doc && posUnder the current doc)  -- this was contracted from $bInsert and $bPosUnder
        Return the full path name of the inserted/updated doc.

        If updating/renaming, the folder name doesn't change
        If inserting after the current doc, the folder name is the same as the current doc's folder name.
        If inserting under the current doc, the folder name is this doc
     */
    {
        if( !empty($sName) ) {
            $sName = str_replace( '/', '_', $sName );  // eliminate slashes so users can't insert weird name heirarchy

            if( $bInsertInto ) {
                // new name is this->docname/sName
                // tricky bit: if !this->docname then use foldername instead
                $sFolderName = $this->GetName();
                if( !$sFolderName ) {
                    $sFolderName = $this->GetFolderName();
                }
            } else {
                // new name is this->foldername/sName
                $sFolderName = $this->GetFolderName();
            }
            if( !empty($sFolderName) ) {
                $sName = $sFolderName.'/'.$sName;
            }

            /* If there is another doc with this name, add a suffix.
             */
// Have to check if this is an update of the same doc. Checking the return of GetDocFromName()==$this->GetKey() is not enough
// unless we know this is an update, not an insert.
// OR let the Insert function add the suffix
            if( false )     $sName = $this->makeUniqueName( $sName );
        }
        return( $sName );
    }

    function GetSFileExt()
    /*********************
        Get the file extension from an sfile name. This is pretty generic so you could also do this with any filename parser.
        You could use this for non-sfile FILE documents too, if you know they have extensions.

        N.B. the Data_data_file_ext field contains the file ext in the db anyway, but we're not sure we want to rely on it.
     */
    {
        $ext = "";

        $sBaseName = $this->GetBaseName();
        if( ($i = strrpos( $sBaseName, '.' )) !== false ) {
            $ext = substr( $sBaseName, $i + 1 );
        }

        return( $ext );
    }

    protected function makeUniqueName( $sName )
    /******************************************
        If the name already exists in the DocRep, add a suffix to make this name unique.
     */
    {
        if( $this->oDocRepDB->GetDocRepDoc( $sName ) ) {
            /* The name is already used. Figure out where to put a number to uniqueify it.
             * foo1.jpg is a lot better than foo.jpg1
             */
            if( ($iDot = strrpos( $sName, '.' )) === false ) {
                // no dot: put the suffix at the end of the name
                $iPos = strlen($sName);
            } else if( ($iSlash = strrpos( $sName, '/' )) === false ) {
                // no slash: put the suffix before the dot
                $iPos = $iDot;
            } else if( $iDot > $iSlash ) {
                // the last dot is after the last slash: put the suffix before the dot
                $iPos = $iDot;
            } else {
                // the last dot is before a slash: put the suffix at the end of the name
                $iPos = strlen($sName);
            }

            for( $iSuffix = 1; ; ++$iSuffix ) {
                $sNameNew = substr( $sName, 0, $iPos ).$iSuffix.substr( $sName, $iPos );
                if( !$this->oDocRepDB->GetDocRepDoc( $sNameNew ) ) {
                    $sName = $sNameNew;
                    break;
                }
            }
            $this->oDocRepDB->ErrMsg( "Duplicate name; calling this document <b>$sName</b> instead." );
        }
        return( $sName );
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


    protected function clearCache()
    /******************************
        Use this when data changes so the cache will be reloaded.
     */
    {
        $this->raValues = array();
    }

    protected function voidDoc()
    /***************************
        Use this to invalidate the doc object, e.g. when it is deleted from the db.
     */
    {
        unset($this->raValues);
        $this->bValid = false;
        $this->kDoc = 0;
    }

    private function getKfrDoc( $kDoc, $flagOrVer )
    /**********************************************
        Get kfr for doc X data with no permission check
     */
    {
        if( empty($flagOrVer) ) {
            /* Get Doc and Data for the maxVer
             */
            $kfrel = $this->oDocRepDB->GetRel()->GetKfrel('Doc X Data');   // doc x data
            $colnameKey     = $kfrel->GetDBColName( "docrep2_docs", "_key" );
            $colnameTopData = $kfrel->GetDBColName( "docrep2_docs", "kData_top" );
            $kfrDoc = $kfrel->GetRecordFromDB( "$colnameKey='$kDoc' AND $colnameTopData=Data._key" );
        } else if( is_numeric($flagOrVer) ) {
            /* Get Doc and Data for the given numbered version
             */
            $kfrel = $this->oDocRepDB->GetRel()->GetKFrel('Doc X Data');   // doc x data
            $iVer = intval($flagOrVer);
            $kfrDoc = $kfrel->GetRecordFromDB( "_key='$kDoc' AND Data.ver='$iVer'" );
        } else {
            /* Get Doc and Data for the flagged DXD
             */
            $kfrel = $this->oDocRepDB->GetRel()->GetKFrel('Doc X Dxd X Data');  // doc x dxd x data
            $colnameKey = $kfrel->GetDBColName( "docrep2_docs", "_key" );
            $kfrDoc = $kfrel->GetRecordFromDB( "$colnameKey='$kDoc' AND DXD.flag='$flagOrVer'" );
        }

        if( !$kfrDoc && $this->bDebug )  die( "Cannot find doc $kDoc:".(empty($flagOrVer) ? "maxVer" : $flagOrVer) );

        return( $kfrDoc );
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
        return( $this->insertDoc( 'TEXT', 'TEXT', $tmp_fname, $parms ) );
    }

    function InsertFile( $tmp_fname, $parms = array() )
    /**************************************************
        Create a FILE document
     */
    {
        return( $this->insertDoc( 'DOC', 'FILE', $tmp_fname, $parms ) );
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

        return( $this->insertDoc( 'DOC', 'SFILE', $tmp_fname, $parms ) );
    }

    function InsertLink( $kLinkDoc, $parms = array() )
    /*************************************************
     */
    {
        return( $this->insertDoc( 'LINK', '', '', $parms ) );
    }

    private function insertDoc( $docType, $eSrcType, $src, $parms )
    /**************************************************************
        Insert a new doc.
        $this must be a blank DocRepDoc with kDoc==0

        docType = TEXT | IMAGE | DOC | TEXTFRAGMENT | FOLDER | LINK | U_* (user type treated like DOC)
        eSrcType = TEXT | FILE | SFILE
        src     = eSrcType=TEXT: the content
                | eSrcType=FILE: the uploaded file name
                | eSrcType=SFILE: an optional uploaded file name
                | docType=LINK: dest kDoc


        For SFILE:
            $src is an optional uploaded file name - if blank create a doc pointing to dr_name and assume somebody put it there
            dr_name is the path rooted at sfile/
            dr_bReplaceCurrVersion is forced to true (for replace)
            dr_posUnder/posAfter are ignored, kDoc_parent is always DOCREP_PARENT_SFILE


        Basic parms:
        parms['dr_name'] = the name of the doc
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
            $parms['dr_name'] = $this->makeUniqueName( $parms['dr_name'] );
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
            $this->insert_ParentSiborder( $kfrDoc, intval(@$parms['dr_posUnder']), intval(@$parms['dr_posAfter']) );
        }

        /* Set the dr_parms into the records
         */
        $this->insertUpdate_Metadata( $kfrDoc, $kfrData, $parms );

        /* No guarantee that everything here will work (e.g. move_uploaded_file fails), but the doc records have
         * referential integrity at this point.
         */
        if( !$kfrDoc->Value('type') == 'FOLDER' ) {
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

        $this->clearCache();    // maybe this operation messes up some cached version data so reload from the db

        return( $this->kDoc );
    }

    private function insert_ParentSiborder( $kfrDoc, $posUnderParent, $posAfterSibling )
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

        } else if( $posAfterSibling ) {
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
    type                    VARCHAR(200) NOT NULL DEFAULT '',  # TEXT, IMAGE, DOC, TEXTFRAGMENT, FOLDER, etc. U_* are user-defined types
    docspec                 VARCHAR(200) DEFAULT '',           # user defined for searching, grouping, ordering, etc
    permclass               INTEGER NOT NULL,
    kData_top               INTEGER NOT NULL DEFAULT 0,        # docrep2_data._key for the latest version

-- (parent,siborder)==(0,0) is a set of uncontained docs
    kDoc_parent             INTEGER NOT NULL DEFAULT 0,        # docrep2_docs._key of this doc's parent (0 means this is at the top)
    siborder                INTEGER NOT NULL DEFAULT 0,

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
    src                 ENUM('TEXT','FILE','SFILE','LINK') NOT NULL,
    data_text           TEXT NULL,                      # src=TEXT ? the text is stored here
    data_file_ext       VARCHAR(20) NULL,               # src=FILE ? the file is stored as {_key}.{data_file_ext}
    data_sfile_name     VARCHAR(500) NULL,              # src=SFILE ? the filesystem name under the sfile root
    data_link_doc       INTEGER NULL,                   # src=LINK ? this doc's data is the same as link_doc's data (but metadata can be different)
    title               VARCHAR(200) DEFAULT '',        # metadata that is so commonly used it deserves to have its own field
    mimetype            VARCHAR(100) DEFAULT '',        # standalone docs should be served with this type in the http header
    dataspec            VARCHAR(200) DEFAULT '',        # user defined for searching, grouping, ordering, etc
    metadata            TEXT,                           # url-encoded

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
    $def = array( 'tables' => array(
        "docrep2_docs"     => array( 'create' => DOCREP2_DB_TABLE_DOCREP_DOCS,
                                     'insert' => array(
                                          "INSERT INTO docrep2_docs
                                                          (_key,_created,_updated, name,type,kData_top,permclass,kDoc_parent,siborder)
                                                   VALUES (1, NOW(), NOW(), 'folder1','FOLDER',1,1,0,1)",
                                          "INSERT INTO docrep2_docs
                                                          (_key,_created,_updated, name,type,kData_top,permclass,kDoc_parent,siborder)
                                                   VALUES (2, NOW(), NOW(), 'page1','DOC',2,1,1,1)",
                                          "INSERT INTO docrep2_docs
                                                          (_key,_created,_updated, name,type,kData_top,permclass,kDoc_parent,siborder)
                                                   VALUES (3, NOW(), NOW(), 'page2','DOC',3,1,1,2)",
                                     ) ),
        "docrep2_data"     => array( 'create' => DOCREP2_DB_TABLE_DOCREP_DATA,
                                     'insert' => array(
                                          "INSERT INTO docrep2_data (_key,_created,_updated,fk_docrep2_docs,ver,src)
                                                            VALUES (1, NOW(), NOW(), 1,1,'TEXT')",
                                          "INSERT INTO docrep2_data (_key,_created,_updated,fk_docrep2_docs,ver,src,data_text)
                                                            VALUES (2, NOW(), NOW(), 2,1,'TEXT','This is the first page')",
                                          "INSERT INTO docrep2_data (_key,_created,_updated,fk_docrep2_docs,ver,src,data_text)
                                                            VALUES (3, NOW(), NOW(), 3,1,'TEXT','This is the second page')"
                                     ) ),
        "docrep2_docxdata" => array( 'create' => DOCREP2_DB_TABLE_DOCREP_DOC_X_DATA,
                                     'inserts' => array() )
        ) );
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

?>