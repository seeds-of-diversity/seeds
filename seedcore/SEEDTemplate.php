<?php

/* SEEDTemplate
 *
 * Copyright 2015-2019 Seeds of Diversity Canada
 *
 * A templating engine that loads named templates from arbitrary storage, and applies multiple template processors.
 *
 * SEEDTemplate::$raConfig
 *      'processors' => list of template processors, in order of execution
 *                      e.g. array( 'SEEDTag' => array( 'fnExpand', ... ),
 *                                  'Twig'    => array( 'fnExpand', ... ),
 *                                  'Wiki'    => array( 'fnExpand', ... ), ... )
 *
 *      'vars'       => variables referenced by template processors; the store is globally writable, not scoped by child templates
 *                      (if you want scoped local variables in templates, implement that in your processor)
 *
 *      'loader'     => how to get a named template. All processors use the same loader, because the templates can contain any or
 *                      all supported template languages. Our loader is therefore independent of any processor's built-in loader.
 *                      array( 'oLoader' => an object supporting LoadStr(), LoadRA(), Get()
 *                                          if not defined, factory_Loader() is called instead
 *
 *      'sTemplates' => a string (or an array of strings) containing one or more named templates, passed to oLoader->LoadStr().
 *      'fTemplates' => a filename (or an array of filenames) containing one or more named templates, read and passed to oLoader->LoadStr().
 *      'raTemplates'=> an array (NOT an array of arrays) "name1"=>"tmpl","name2"=>"tmpl2", passed to oLoader->LoadRA().
 *                      If neither defined, assume the provided loader already has the templates.
 *                      In all but trivial applications it probably makes sense to provide your own loader derived from SEEDTemplateLoader.
 */

include_once( "SEEDTag.php" );
include_once( SEEDROOT."vendor/autoload.php" );

class SEEDTemplate2
{
    protected $oDSVars = null;
    protected $oLoader = null;
    protected $raProcessors = array();

    function __construct( $raConfig = array(), SEEDDataStore $oDSVars = null )
    {
        $this->raProcessors = $raConfig['processors'];   // required

        // use a global shared datastore, or make a local one
        $this->oDSVars = $oDSVars ? $oDSVars : new SEEDDataStore();

        if( @$raConfig['vars'] ) $this->SetVars( $raConfig['vars'] );

        $this->oLoader = @$raConfig['loader']['oLoader'] ? $raConfig['loader']['oLoader']
                                                         : $this->factory_Loader();
    }

    function GetVarsRA()            { return( $this->oDSVars->GetValuesRA() ); }   // only implemented for plain array base class
    function SetVarsRA( $raVars )   { $this->oDSVars->SetValuesRA( $raVars ); }
    function GetVar( $k )           { return( $this->oDSVars->Value($k) ); }
    function SetVar( $k, $v )       { $this->oDSVars->SetValue( $k, $v ); }
    // deprecated for method name consistency
    function SetVars( $raVars )     { $this->SetVarsRA( $raVars ); }

    function ClearVars()            { $this->oDSVars->Clear(); }


    function Exists( $tmplname )
    {
        return( $this->oLoader->Exists( $tmplname ) );
    }

    function ExpandStr( $s, $raVars = array() )
    /******************************************
        Process the given string with the template processors.
        Vars are merged into the current datastore, scoped to this template.
     */
    {
        $raOldVars = $this->GetVarsRA();    // scope the given template vars to this template
// TODO: scoping means you can't set global vars that persist after an include. Create global vars in SEEDTemplate that are set by SetGlobalVar and checked after regular vars
        $this->SetVars( $raVars );
        foreach( $this->raProcessors as $k => $ra ) {
            $s = call_user_func( $ra['fnExpand'], $s, $this->oDSVars, $this->oLoader );
        }
        $this->ClearVars();
        $this->SetVars( $raOldVars );

        return( $s );
    }

    function ExpandTmpl( $tmplname, $raVars = array() )
    /**************************************************
        Process the given named template with the template processors.
        Vars are merged into the current datastore, and persist afterward.
     */
    {
        $sTmpl = $this->oLoader->Get( $tmplname );
        return( $sTmpl ? $this->ExpandStr( $sTmpl, $raVars ) : "" );
    }

    function factory_Loader()
    {
        // base class uses the base loader
        return( new SEEDTemplateLoader2() );
    }
}

class SEEDTemplateLoader2 {
    protected $raTmpl = array();

    function __construct( $raConfig )
    {
        if( @$raConfig['sTemplates'] ) {
            if( is_array( $raConfig['sTemplates'] ) ) {
                foreach( $raConfig['sTemplates'] as $sT )  $this->LoadStr( $sT );
            } else {
                $this->LoadStr( $raConfig['sTemplates'] );
            }
        }
        if( @$raConfig['raTemplates'] ) {
            // There is no easy way to distinguish an array of templates from an array of arrays of templates,
            // so just merge them into one array
            $this->LoadRA( $raConfig['raTemplates'] );
        }
        if( @$raConfig['fTemplates'] ) {
            if( is_array( $raConfig['fTemplates'] ) ) {
                foreach( $raConfig['fTemplates'] as $fT ) {
                    if( ($sTmpl = file_get_contents($fT)) ) {
                        $this->LoadStr( $sTmpl );
                    }
                }
            } else {
                if( ($sTmpl = file_get_contents($raConfig['fTemplates'])) ) {
                    $this->LoadStr( $sTmpl );
                }
            }
        }
    }

    function GetRATmpl()  { return( $this->raTmpl ); }

    function LoadStr( $sTmplGroup, $sMark = '%%' )
    /*********************************************
        Explode a group of templates from a string.
        This can be called multiple times if there are multiple template groups.
        Later additions overwrite earlier.

        Template format:
            # Comments above a template could be designated by hashes but actually anything before the first {sMark} is ignored

            {sMark} name-of-template
            template content
            # lines starting with hashes are removed (an arg could define the comment syntax if you don't like this)
            more template content

            {sMark} name-of-another-template
            template content
     */
    {
        $nOffset = 0;
        while( ($nStart = strpos($sTmplGroup, $sMark, $nOffset)) !== false ) {
            if( ($nEnd = strpos( $sTmplGroup, $sMark, $nStart+strlen($sMark) )) !== false ) {
                $chunk = substr( $sTmplGroup, $nStart, $nEnd - $nStart );
            } else {
                $chunk = substr( $sTmplGroup, $nStart );
            }
            $temp = explode("\n",$chunk,2);
            $tmplName = trim(substr($temp[0],strlen($sMark)));
            $tmplBody = $temp[1];

            // Comment lines start with #
            //if( $this->raConfig['bCommentsStartWithHash'] )  -- true by default
            $raBody = explode( "\n", $tmplBody );
            $tmplBody = "";
            foreach( $raBody as $l ) {
                if( substr( $l, 0, 1 ) != '#' )  $tmplBody .= $l."\n";
            }

            $this->raTmpl[$tmplName] = $tmplBody;

            if( $nEnd === false )  break;

            $nOffset = $nEnd;
        }
        return( true );
    }

    function LoadRA( $raTmpl )
    /*************************
        Add templates to the store, later additions overwriting earlier
     */
    {
        $this->raTmpl = array_merge( $this->raTmpl, $raTmpl );
    }

    function Exists( $tmplname )
    {
        return( isset( $this->raTmpl[$tmplname] ) );
    }

    function Get( $tmplname )
    {
        return( @$this->raTmpl[$tmplname] );
    }
}


class SEEDTemplateLoader_Twig extends SEEDTemplateLoader2
/****************************
    Twig wants a loader class that it can bind to and call read(), which has to be able to find the SEEDTagLoader
 */
{
    function __construct() { parent::__construct(); }

    function read( $tmplname )
    {
        // Twig takes this as a loader class, and calls read() when it needs a template. The return has to be a nodelist.
        // The fail case is that parse() reads "", so make sure it is a string (instead of null or 0)
        $sTmpl = $this->Get($tmplname);
        return( $this->runtime->parse( $sTmpl ? $sTmpl : "") );
    }

    function read_cache( $f )  { return $this->read($f); }
    function setOptions()      {}
}

class SEEDTemplate_Generator2
/***************************
    Makes a SEEDTemplate that processes Twig and/or SEEDTag
 */
{
    const USE_TWIG = 1;
    const USE_SEEDTAG = 2;

    protected $flags = 0;
    protected $oDSVars = null;
    protected $oSeedTag = null;
    protected $oTwig = null;
    protected $oLoader = null;

    protected $raConfig;

    function __construct( $raConfig = array() )
    {
        $this->raConfig = $raConfig;

        $this->oDSVars = new SEEDDataStore();    // used by factory_SEEDTag so put this before that is called
        if( @$raConfig['vars'] ) $this->oDSVars->SetValuesRA( $raConfig['vars'] );

        // by default, process Twig then SEEDTag for the reasons explained below
        $this->flags = isset($raConfig['use']) ? $raConfig['use']
                                               : (self::USE_SEEDTAG | self::USE_TWIG);

        // create the loader, which is shared by all processors
        $this->oLoader = $this->factory_Loader();

        // create the required processors
        if( $this->flags & self::USE_TWIG ) {
            // Twig should be included by vendor/autoload.php
            $this->oTwig = $this->factory_Twig();
        }
        if( $this->flags & self::USE_SEEDTAG ) {
            include_once( SEEDCORE."SEEDTag.php" );
            $this->oSeedTag = $this->factory_SEEDTag( @$this->raConfig['SEEDTagParms'] ?: array(),
                                                      $this->oDSVars );    // make SEEDTag use the same var datastore as SEEDTemplate and Twig
        }
    }

// There is no particular reason why some code is in the constructor and some is here. The object is not stateless so it doesn't matter
// where the code is
    function MakeSEEDTemplate()
    {
        $raST = array();

        /* Processing all Twig tags before SEEDTags
         * Con: [[SetVar:a|b]] does not work with {{a}}, but you can always use [[Var:a]] instead
         * Pro: otherwise all SEEDTags within false Twig conditionals {% if 0 %} [[tag:]] {%endif%} would be processed *with side effects*
         */
        if( $this->flags & self::USE_TWIG ) {
            $raST['processors']['Twig'] = array( 'fnExpand' => array($this,'ExpandTwig') );
        }
        if( $this->flags & self::USE_SEEDTAG ) {
            $raST['processors']['SEEDTag'] = array( 'fnExpand' => array($this,'ExpandSEEDTag') );
        }

        $raST['loader']['oLoader'] = $this->oLoader;

        $oTmpl = new SEEDTemplate2( $raST, $this->oDSVars );    // use global datastore for all template processors

// Kluge: to process [[include:]] the HandleTag needs SEEDTemplate
        if( $this->oSeedTag )  $this->oSeedTag->oTmpl = $oTmpl;

        return( $oTmpl );
    }

    function ExpandSEEDTag( $s, SEEDDataStore $oDSVars, SEEDTemplateLoader2 $oLoader )
    {
        // oDSVars from SEEDTemplate is the shared global datastore that's also used in this object, and oSeedTag.
        // That means if the template has a [[SetVar:]] everyone will see the new value.
        // It also means the argument can be ignored here.
        return( $this->oSeedTag->ProcessTags($s) );
    }

    function ExpandTwig( $s, SEEDDataStore $oDSVars, SEEDTemplateLoader2 $oLoader )
    {
        // The SEEDDataStore passed here by SEEDTemplate::ExpandStr is the same one used by ExpandSEEDTag.
        // However, Twig is processed first to avoid SEEDTag side-effects in false Twig conditional blocks,
        // so any [[SetVar:a|b]] will be processed after any {{a}}
        // If you want to set {{a}} you must use {{set:a}}.
        // If you want to set [[Var:a]] you must use [[SetVar:a]].
        // It is legal to do this:
        // {{set a=b}} [[SetVar:a|{{a}}]]  {{a}}  [[Var:a]]
        $raVars = $oDSVars->GetValuesRA();

        // See the code for createTemplate(). It puts the string in a Twig_Loader_Array() and uses Twig_Loader_Chain() to
        // provide access to the other templates. Could just do that here.
        $template = $this->oTwig->createTemplate( $s );
        return( $template->render( $raVars ) );
    }

    protected function factory_SEEDTag( $raParms, $oDSVars )    // typically oDSVars is the same var datastore as this class and H2o use
    {
        return( new SEEDTemplate_SEEDTagParser2( $raParms, $oDSVars ) );
    }

    protected function factory_Twig()
    /* Create the Twig object and its loader
     */
    {
        $o = function_exists('Twig_Environment') ? (new Twig_Environment($this->factory_TwigLoader()))    // twig 1.42 deprecate
                                                 : (new \Twig\Environment($this->factory_TwigLoader()));  // twig ^3.0
        if( isset($this->raConfig['charset']) )  $o->setCharset($this->raConfig['charset']);
$o->enableDebug();
        return( $o );
    }

    protected function factory_TwigLoader( $raParms = array() )
    {
        if( !($loader = @$this->raConfig['TwigLoader']) ) {
            $loader = function_exists('Twig_Loader_Array') ? (new Twig_Loader_Array($this->oLoader->GetRATmpl()))         // twig 1.42 deprecate
                                                           : (new \Twig\Loader\ArrayLoader($this->oLoader->GetRATmpl())); // twig ^3.0
        }
        return( $loader );
    }

    protected function factory_Loader()
    {
        return( new SEEDTemplateLoader2( $this->raConfig ) );
    }
}

class SEEDTemplate_SEEDTagParser2 extends SEEDTagParser
{
    public  $oTmpl;        // set by kluge to allow [[include:]]

    private $oBasicResolver = null;

    function __construct( $raConfig, $oDSVars )
    {
        if( !isset($raConfig['raResolvers']) )  $raConfig['raResolvers'] = array();

        if( isset($raConfig['EnableBasicResolver']) ) {
            // the value of EnableBasicResolver is the raConfig for SEEDTagBasicResolver
            $this->oBasicResolver = new SEEDTagBasicResolver( $raConfig['EnableBasicResolver'] );
            $raConfig['raResolvers'][] = array( 'fn'=>array($this->oBasicResolver,'ResolveTag'), 'raConfig'=>array() );
        }

        parent::__construct( $raConfig, $oDSVars );
    }

    function HandleTag( $raTag )
    {
        $bHandled = false;
        $s = "";

        // Template related tags are handled here. They could also be handled by a ResolveTag method added to raResolvers
        switch( strtolower($raTag['tag']) ) {
            case 'include':
                $s = $this->oTmpl->ExpandTmpl( $raTag['target'] );
                $bHandled = true;
                break;
        }
        if( $bHandled ) goto done;

        // the base SEEDTagParser will call each ResolveTag defined in the constructor, then employ its own base tags
        $s = parent::HandleTag( $raTag );

        done:
        return( $s );
    }
}
