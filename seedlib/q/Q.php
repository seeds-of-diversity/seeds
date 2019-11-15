<?php

/* Q.php
 *
 * Copyright 2017-2019 Seeds of Diversity Canada
 *
 * Main API point for Q commands
 */

class Q
{
    public $oApp;
    public $raConfig;
    public $bUTF8 = false;

    function __construct( SEEDAppSessionAccount $oApp, $raConfig = array() )
    {
        $this->oApp = $oApp;
        $this->raConfig = $raConfig;
        $this->bUTF8 = intval(@$raConfig['bUTF8']);
    }

    function Cmd( $cmd, $parms )
    {
        $rQ = self::GetEmptyRQ();

        if( $cmd == 'test' ) {
            $rQ['bOk'] = true;
            $rQ['sOut'] = "Test is successful";
            $rQ['raOut'] = [ [ 'first name' => "Fred", 'last name' => "Flintstone" ],
                             [ 'first name' => "Barney", 'last name' => "Rubble"   ] ];
            $rQ['raMeta']['title'] = "Test";
            $rQ['raMeta']['name'] = "qtest";
        }
        else

        if( SEEDCore_StartsWith( $cmd, 'desc' ) ) {
//            include_once( "_QServerDesc.php" );
//            $o = new QServerDesc( $this );
//            $rQ = $o->Cmd( $cmd, $parms );
        }
        else

        if( SEEDCore_StartsWith( $cmd, 'rosetta' ) ) {
//            include_once( "_QServerPCV.php" );
//            $o = new QServerPCV( $this );
//            $rQ = $o->Cmd( $cmd, $parms );
        }
        else

        if( SEEDCore_StartsWith( $cmd, 'src' ) ) {
            include_once( "QServerSources.php" );
            $o = new QServerSourceCV( $this, ['bUTF8' => true] );
            $rQ = $o->Cmd( $cmd, $parms );
        }
        else

        if( SEEDCore_StartsWith( $cmd, 'collection' ) ) {
//            include_once( "_QServerCollection.php" );
//            $o = new QServerCollection( $this, [] );
//            $rQ = $o->Cmd( $cmd, $parms );
        }
        else

        if( SEEDCore_StartsWith( $cmd, 'collreport' ) ) {
//            include_once( "_QServerCollectionReport.php" );
//            $o = new QServerCollectionReport( $this, [] );
//            $rQ = $o->Cmd( $cmd, $parms );
        }
        else

        if( SEEDCore_StartsWith( $cmd, 'mbr' ) ) {
//            include_once( SEEDLIB."mbr/QServerMbr.php" );
//            $o = new QServerMbr( $this->oApp, array() );
//            $rQ = $o->Cmd( $cmd, $parms );
        }
        else

        if( SEEDCore_StartsWith( $cmd, 'basket' ) ) {
            include_once( "QServerBasket.php" );
            $o = new QServerBasket( $this, [] );
            $rQ = $o->Cmd( $cmd, $parms );
        }
        else {
            $rQ['sErr'] = "Unknown cmd";
        }

        return( $rQ );
    }

    function QCharset( $s )
    /**********************
        If the input is cp1252, the output will be the charset defined by $this->bUTF8
     */
    {
        return( $this->bUTF8 ? utf8_encode($s) : $s );
    }

    static function GetEmptyRQ()
    /***************************
     */
    {
        return( [ 'bOk'=>false, 'sOut'=>"", 'sErr'=>"", 'sLog'=>"", 'raOut'=>[], 'raMeta'=>[] ] );
    }

    function CheckPerms( $cmd, $ePerm, $sPermLabel )
    /***********************************************
        cmds containing --- require admin access
        cmds containing --  require write access
        cmds containing -   require read access

        Note that any command might check further permissions to allow or deny access
     */
    {
        $bAccess = false;
        $sErr = "";

        if( strpos( $cmd, "---" ) !== false ) {
            if( !($bAccess = $this->oApp->sess->TestPerm( $ePerm, 'A' )) ) {
                $sErr = "Command requires $sPermLabel admin permission";
            }
        } else
        if( strpos( $cmd, "--" ) !== false ) {
            if( !($bAccess = $this->oApp->sess->TestPerm( $ePerm, 'W' )) ) {
                $sErr = "Command requires $sPermLabel write permission";
            }
        } else
        if( strpos( $cmd, "-" ) !== false ) {
            if( !($bAccess = $this->oApp->sess->TestPerm( $ePerm, 'R' )) ) {
                $sErr = "Command requires $sPermLabel read permission";
            }
        }

        return( [$bAccess, $sErr] );
    }
}


class QCursor
{
    private $kfrc;
    private $fnGetNextRow;    // function to translate kfrc->values to the GetNextRow values
    private $raParms;

    function __construct( KeyframeRecord $kfrc, $fnGetNextRow, $raParms )
    {
        $this->kfrc = $kfrc;
        $this->fnGetNextRow = $fnGetNextRow;
        $this->raParms = $raParms;
    }

    function KFRC()  { return( $this->kfrc ); }

    function GetNextRow()
    {
        $raOut = null;
        if( $this->kfrc->CursorFetch() ) {
            if( $this->fnGetNextRow ) {
                $raOut = call_user_func( $this->fnGetNextRow, $this, $this->raParms );
            } else {
                $raOut = $this->kfrc->ValuesRA();
            }
        }
        return( $raOut );
    }
}
