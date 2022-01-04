<?php

/* SEEDLocal
 *
 * Copyright 2006-2021 Seeds of Diversity Canada
 *
 * Localize strings to facilitate multi-language applications.
 *
 * Strings are stored as [ [ 'ns'=>ns1, 'strs'=> [ key => [ lang1 => "String in lang1", lang2 => "String in lang2", ... ] ... ]
 *                         [ 'ns'=>ns2, 'strs'=> [ key => [...]
 *
 * Any number of string sets can be added.
 * ns and keys don't have to be unique. Arrays are searched in reverse order so later keys override earlier keys.
 * "[[]]" expands to the string key to simplify verbatim cases
 *
 * e.g. [ 'ns'=>"lang_name",  'strs' => [ 'English'      => ["EN" => "English", "FR" => "Anglais"] ]],
 *                                        'French'       => [...] ],
 *      [ 'ns'=>"lang_name",  'strs' => [ 'my_lang_name' => ["EN" => "English", "FR" => "Francais" ] ]],
 *      [ 'ns'=>"lang_name",  'strs' => [ 'English'      => ["EN" => "[[]]", ... ] ]]
 */

include_once(SEEDCORE."SEEDTag.php");    // SEEDTagParser

class SEED_Local {
    const CODE_FOUND = 1;
    const CODE_NOT_FOUND = 2;
    const CODE_BAD_HOST = 3;

    private   $lang;
    private   $nsDefault = "";
    private   $raStrSets = [];
    protected $raParms = [];
    private   $bDebug = false;

    protected $oTagParser;    // a SEEDLocal_TagParser for processing [[..]] tags

    function __construct( ?array $raStrs, string $lang, string $nsDefault = "", array $raParms = [] )
    {
        $this->raStrSets = [];
        $this->lang = $lang;
        $this->nsDefault = $nsDefault;
        $this->raParms = $raParms;
        if( $raStrs ) $this->AddStrs( $raStrs );

        $this->bDebug = (SEED_isLocal || @$this->raParms['bDebug']);

        $this->oTagParser = new SEED_Local_TagParser( $this );
    }

    function AddStrs( $raStrs )
    /**************************
        $raStrs can be ['ns'=>ns, 'strs'=>[strs]]
                    or [ ['ns'=>ns, 'strs'=>[strs]], ['ns'=>ns, 'strs'=>[strs]] ]
     */
    {
        if( isset($raStrs['ns']) ) {
            // raStrs is a simple array with 'ns and 'strs'
            $this->raStrSets[] = $raStrs;
        } else {
            // raStrs is an array of [ns and strs]
            $this->raStrSets = array_merge( $this->raStrSets, $raStrs );
        }
    }

    function GetLang()           { return( $this->lang ); }

    function S( $key, $raSubst = [], $ns = null )
    /********************************************
        Retrieve the string that corresponds to the key.
        Substitute [[]] with $key to simplify verbatim definitions.
        Substitute $raSubst elements using SEEDCore_ArrayExpand syntax e.g. "[[left]] = [[right]]"
     */
    {
        $s = "";

        if( $ns === null ) { $ns = $this->nsDefault; }

        list($code,$raStrs) = $this->_SLookup( $ns, $key );   // Derived classes override this method to retrieve string sets from various places

        switch( $code ) {
            case self::CODE_FOUND:
                if( !($s = @$raStrs[$this->lang]) ) {
                    /* The string set was found, but it doesn't contain the language we need.
                     * In dev or testing mode, show an error message. In prod mode, show the other language.
                     */
                    $s = $this->bDebug ? "<span style='color:red'>__TRANSLATE_{$key}_</span>"
                                         : @$raStrs[ $this->lang == 'EN' ? 'FR' : 'EN' ];
                }
                // replace verbatim key
                if( $s == '[[]]' ) {
                    $s = $key;
                }
                // replace subst values
                if( $raSubst ) {
                    $s = SEEDCore_ArrayExpand( $raSubst, $s );
                }
                break;
            case self::CODE_NOT_FOUND:
                $s = $this->bDebug ? "<span style='color:red'>__NOT_FOUND_{$key}_</span>" : "";
                break;
            case self::CODE_BAD_HOST:
                $s = $this->bDebug ? "<span style='color:red'>__HOST_{$key}_</span>" : "";
                break;
        }
        return( $s );
    }

/*
    function SExpand( $sTemplate, $raVars = array() )    // deprecate, use S2 instead
    [************************************************
        String contains tags of the form [[ns:key | subst1 | subst2]]
        ns: and substN are optional

        sTemplate can contain tags of the forms:
            [[code]]
            [[code whose text contains %1% and %2% | subst to 1 | subst to 2]]
            [[code whose text contains "[[code2]]" ]]   -- recursively expand [[code2]] with same raVars
            [[Var:X]]                                   -- subst with $raVars['X']
            [[If:X | str1 | str2]]                      -- if($raVars['X']) process str1 else process str2

     *]
    {
        for(;;) {
            $s1 = strpos( $sTemplate, "[[" );
            $s2 = strpos( $sTemplate, "]]" );
            if( $s1 === false || $s2 === false )  break;

            $tag = substr( $sTemplate, $s1 + 2, $s2 - $s1 - 2 );
            $raTag = explode( '|', $tag );
            $first = array_shift( $raTag );  // shifts the items down so $raTag contains subst values, returns the first item
            if( empty($first) ) break;

            $ra1 = explode( ':', $first );
            if( count($ra1) == 1 ) {
                $ns = $this->nsDefault;
                $k = trim($ra1[0]);
            } else {
                $ns = trim($ra1[0]);
                $k = trim($ra1[1]);
            }

            $sTemplate = substr( $sTemplate, 0, $s1 )
                        .$this->S( $k, $raTag, $ns )
                        .substr( $sTemplate, $s2 + 2 );
        }
        return( $sTemplate );
    }
*/

    function S2( string $sTemplate, $raVars = [] )
    {
        $this->oTagParser->SetVars( $raVars );
        return( $this->oTagParser->ProcessTags( $sTemplate ) );
    }

    protected function _SLookup( $ns, $key )
    {
        $code = self::CODE_NOT_FOUND;
        $raRet = ['EN'=>"", 'FR'=>""];

        /* Search from the last set to the first so later additions override.
         */
        foreach( array_reverse($this->raStrSets) as $ra ) {
            if( $ra['ns'] == $ns  &&
                ($raStr = @$ra['strs'][$key]) )
            {
                $raRet = $raStr;
                $code = self::CODE_FOUND;
                break;
            }
        }
        return( [$code,$raRet] );
    }

    function Dollar( $d )
    {
        return( SEEDCore_Dollar( $d, $this->GetLang() ) );
    }


    function ResolveTag( $raTag, SEEDTagParser $oTagDummy, $raParms )
    /****************************************************************
        Call here from SEEDTagParser::HandleTag to resolve tags having to do with SEEDLocal codes

        bRequireLocalPrefix: [[FR_De:]] etc have to be [[LocalFR_De:]]
                             Use this in template contexts where multiple resolvers could compete.
                             Most importantly, [[foo]] is not a synonym for [[Local:foo] ; you have to use the namespace
     */
    {
        $s = "";
        $bHandled = true;

        $bRequirePrefix = SEEDCore_ArraySmartBool( $raParms, 'bRequireLocalPrefix', false );

        if( $bRequirePrefix && substr(strtolower($raTag['tag']), 0, 5) != 'local' ) {
            $bHandled = false;
            goto done;
        }

        $t = $raTag['target'];

        switch( $raTag['tag'] ) {
            case 'LocalFR_de':
            case 'FR_de':
                // if target starts with a vowel, prepend "d'" else "de "
                if( $t ) {
                    $s = in_array( strtolower(substr($t,0,1)), ['a','e','i','o','u'] )
                           ? "d'$t" : "de $t";
                }
                break;

            case 'LocalFR_De':
            case 'FR_De':
                // if target starts with a vowel, prepend "D'" else "De "
                if( $t ) {
                    $s = in_array( strtolower(substr($t,0,1)), ['a','e','i','o','u'] )
                           ? "D'$t" : "De $t";
                }
                break;

/*
// make this a basic tag [[plural_s:target | n]]
            case 'LocalPlural_s':
            case 'Plural_s':
                // if p1 == 0 or p1 > 1 append an 's' to target.   e.g. 0 results, 1 result, 2 results, ...
                $s = $t.(intval($raTag['raParms'][1]) == 1 ? "" : "s");
                break;

// make this a basic tag
            case 'LocalPlural_es':
            case 'Plural_es':
                // if p1 == 0 or p1 > 1 append an 'es' to target.  e.g. 0 matches, 1 match, 2 matches, ...
                $s = $t.(intval($raTag['raParms'][1]) == 1 ? "" : "es");
                break;

// make this a basic tag
            case 'LocalPlural_y':
            case 'Plural_y':
                // if p1 == 1 append a 'y' to target, else 'ies'.   e.g. 0 companies, 1 company, 2 companies, ...
                $s = $t.(intval($raTag['raParms'][1]) == 1 ? "y" : "ies");
                break;
*/

            case 'LocalLang':
            case 'Lang':
                $s = $this->GetLang() == 'EN' ? @$raTag['raParms'][0] : @$raTag['raParms'][1];
                break;

            case 'Local':
            case '': // no tag: means the target is a S-code
                $s = $this->S($t);
                break;

            default:
                $bHandled = false;
                break;
        }

        done:
        return( [$bHandled,$s] );
    }
}

/* transition from STD to here

class SEEDLocalDB extends SEEDLocal
{
    protected $kfdb;

    function __construct( KeyFrameDB $kfdb, $lang, $nsDefault = "", $raParms = array() )
    {
        $this->kfdb = $kfdb;
        $dummyStrs = array(); // SEEDLocal needs this, but we have no strings in an array
        parent::__construct( $dummyStrs, $lang, $nsDefault, $raParms );
    //    if( !empty($nsDefault) )  $this->AddStrsDB( $nsDefault );
    }

    protected function _SLookup( $ns, $key )
    [***************************************
        You don't call this to get strings.  You call S(), which calls this.
     *]
    {
        $lookup = parent::_SLookup( $ns, $key );

        if( $lookup['code'] != SEEDLocal::CODE_FOUND && $ns ) {
            $ra = $this->kfdb->QueryRA( "SELECT * FROM SEEDLocal WHERE _status='0' AND ns='".addslashes($ns)."' AND k='".addslashes($key)."'" );
            if( @$ra['_key'] ) {
                $lookup['code'] = SEEDLocal::CODE_FOUND;
                $lookup['EN'] = trim($ra['en']);
                $lookup['FR'] = trim($ra['fr']);
                $lookup['content_type'] = $ra['content_type'];
            }
        }
        return( $lookup );
    }

    function AddStrsDB( $ns )
    {
        $raStrs = array();
        if( ($dbc = $this->kfdb->CursorOpen("SELECT * FROM SEEDLocal WHERE _status=0 AND ns='".addslashes($ns)."'") )) {
            while( $ra = $this->kfdb->CursorFetch($dbc) ) {
                $raStrs[$ra['k']] = array( 'EN' => $ra['en'], 'FR' => $ra['fr'] );
            }
        }
        $this->AddStrs( $raStrs, $ns );
    }

    public function SLookupDB( $ns, $key )
    [*************************************
        Special accessor to a SEEDLocal DB row, e.g. used by translation servers.
     *]
    {
        return( $this->_SLookup( $ns, $key ) );
    }
}


class SEEDLocalDBServer extends SEEDLocalDB
{
    [* If this machine is a production server, get the localized strings from the database.
     * If this machine is a development server, call the production server to get the strings.
     *]
    private $sServer;

    function __construct( KeyFrameDB $kfdb, $lang, $sServer = "", $nsDefault = "", $raParms = array() )
    {
        $this->sServer = $sServer;
        $dummyStrs = array(); // SEEDLocal needs this, but we have no strings in an array
        parent::__construct( $kfdb, $lang, $nsDefault, $raParms );
    }

    protected function _SLookup( $ns, $key )
    [***************************************
        You don't call this to get strings.  You call S(), which calls this.
     *]
    {
        // production server: always reading from local database
        // development server: try the local database, then call the production server
        $lookup = parent::_SLookup( $ns, $key );

        if( STD_isLocal && $lookup['code'] != SEEDLocal::CODE_FOUND ) {
            $url = "http://{$this->sServer}/app/traductions.php?mode=REST&ns=".urlencode($ns)."&k=".urlencode($key);

// this was returning 404, no idea why, all looked good
//            list( $ok, $sResponseHeader, $sResponseContent )
//                = SEEDStd_HttpRequest( $this->sServer, '/int/traductions.php',
//                                       array( 'mode'=>'REST', 'ns'=>$ns, 'k'=>$key, 'lang'=>$this->GetLang() ) );
//            $s = trim($sResponseHeader);

            $s = file_get_contents( $url );  // default context is GET and HTTP/1.0, but other context can be set here

            if( substr( $s, 0, 10 ) == "<SEEDLocal" ) {
                if( strpos( $s, '<SEEDLocal:error' ) === false ) {
                    $raEN = SEEDStd_BookEnds( $s, "<SEEDLocal:en>", "</SEEDLocal:en>" );
                    $raFR = SEEDStd_BookEnds( $s, "<SEEDLocal:fr>", "</SEEDLocal:fr>" );
                    $raCT = SEEDStd_BookEnds( $s, "<SEEDLocal:ct>", "</SEEDLocal:ct>" );

                    $lookup['code'] = SEEDLocal::CODE_FOUND;
                    $lookup['EN'] = @$raEN[0];
                    $lookup['FR'] = @$raFR[0];
                    $lookup['content_type'] = @$raCT[0];

// this only happens on dev installations, kind of nice to see when it happens
echo "<P>Downloaded translation for: $ns:$key</P>";
//var_dump($lookup);

                    // cache the string in the local development database
                    $this->kfdb->Execute( "INSERT INTO SEEDLocal (ns,k,en,fr,content_type) "
                                         ."VALUES ('".addslashes($ns)."','".addslashes($key)."',"
                                         ."'".addslashes($lookup['EN'])."',"
                                         ."'".addslashes($lookup['FR'])."',"
                                         ."'".addslashes($lookup['content_type'])."')" );
                } else {
                    $lookup['code'] = SEEDLocal::CODE_NOT_FOUND;  // or this could relay the error
                }
            } else {
                $lookup['code'] = SEEDLocal::CODE_BAD_HOST;
            }
        }

        return( $lookup );
    }
}

*/


class SEED_Local_TagParser extends SEEDTagParser
{
    private $oL;

    function __construct( $oL )
    {
        $this->oL = $oL;
        parent::__construct();
    }

    function HandleTag( $raTag )
    {
        $s = "";
        //var_dump( $raTag );

//TODO: this should just call SEEDLocal::ResolveTag
/*
        switch( $raTag['tag'] ) {
            case 'FR_de':
                // if target starts with a vowel, prepend "d'" else "de "
                if( ($t = @$raTag['target']) ) {
                    $s = in_array( strtolower(substr($t,0,1)), array('a','e','i','o','u') )
                           ? ("d'".$t) : ("de ".$t);
                }
                return( $s );

            case 'FR_De':
                // if target starts with a vowel, prepend "D'" else "De "
                if( ($t = @$raTag['target']) ) {
                    $s = in_array( strtolower(substr($t,0,1)), array('a','e','i','o','u') )
                           ? ("D'".$t) : ("De ".$t);
                }
                return( $s );

            case 'Plural_s':
                // if p1 == 0 or p1 > 1 append an 's' to target.   e.g. 0 results, 1 result, 2 results, ...
                return( $raTag['target'].(intval($raTag['raParms'][1]) == 1 ? "" : "s") );

            case 'Plural_es':
                // if p1 == 0 or p1 > 1 append an 'es' to target.  e.g. 0 matches, 1 match, 2 matches, ...
                return( $raTag['target'].(intval($raTag['raParms'][1]) == 1 ? "" : "es") );

            case 'Plural_y':
                // if p1 == 1 append a 'y' to target, else 'ies'.   e.g. 0 companies, 1 company, 2 companies, ...
                return( $raTag['target'].(intval($raTag['raParms'][1]) == 1 ? "y" : "ies") );

            case '':
                // no tag: means the target is a S-code
                return( $this->ProcessTags( $this->oL->S($raTag['target']) ) );
            default:
                return( parent::HandleTag( $raTag ) );
        }
*/
    }
}


define("SEEDLOCAL_DB_TABLE_SEEDLOCAL",
"
CREATE TABLE IF NOT EXISTS SEEDLocal (

        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    ns VARCHAR(200) NOT NULL DEFAULT '',   # namespace for this translation
    k  VARCHAR(200) NOT NULL,              # key for this translation
    en TEXT NOT NULL,
    fr TEXT NOT NULL,
    content_type ENUM ('PLAIN','HTML') NOT NULL DEFAULT 'PLAIN',
    comment TEXT,

    INDEX (ns(20)),
    INDEX (ns(20),k(20))
);
"
);


function SEEDLocal_Setup( $oSetup, &$sReport, $bCreate = false )
/**************************************************************
    Test whether the tables exist.
    bCreate: create the tables and insert initial data if they don't exist.

    Return true if exists (or create is successful if bCreate); return a text report in sReport

    N.B. $oSetup is a SEEDSetup.  This file doesn't include SEEDSetup.php because the setup code is very rarely used.
         Instead, the code that calls this function knows about SEEDSetup.
 */
{
    return( $oSetup->SetupTable( "SEEDLocal", SEEDLOCAL_DB_TABLE_SEEDLOCAL, $bCreate, $sReport ) );
}
