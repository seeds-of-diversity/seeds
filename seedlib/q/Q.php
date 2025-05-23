<?php

/* Q.php
 *
 * Copyright 2017-2025 Seeds of Diversity Canada
 *
 * Main API point for Q commands
 */

include_once( SEEDCORE."SEEDApp.php" );

class Q
{
    public $oApp;
    public $raConfig;
    public $bUTF8 = false;     // deprecated: SEEDQ handles this (the opposite way)

    function __construct( SEEDAppSessionAccount $oApp, $raConfig = array() )
    {
        $this->oApp = $oApp;
        $this->raConfig = $raConfig;
        $this->bUTF8 = intval(@$raConfig['bUTF8']);     // deprecated: SEEDQ handles this (the opposite way)
    }

    function Cmd( $cmd, $parms )
    {
        $rQ = SEEDQ::GetEmptyRQ();

        // cmds containing ! are insecure for ajax access: use them via your own instance of a QServer* object
        if( strpos($cmd,'!') !== false ) {
            $rQ['sErr'] = "cmd $cmd not available at this access point";
            goto done;
        }

        if( $cmd == 'test' ) {
            $rQ['bHandled'] = true;
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
//            $o = new QServerDesc( $this->oApp, $this->raConfig );
//            $rQ = $o->Cmd( $cmd, $parms );
        }
        else

        if( SEEDCore_StartsWith( $cmd, 'ev' ) ) {
            include_once( SEEDLIB."events/QServerEvents.php" );
            $o = new QServerEvents( $this->oApp, $this->raConfig );
            $rQ = $o->Cmd( $cmd, $parms );
        }
        else

        if( SEEDCore_StartsWith( $cmd, 'rosetta' ) ) {
            include_once( SEEDLIB."sl/QServerRosetta.php" );
            $rQ = (new QServerRosetta($this->oApp, $this->raConfig))->Cmd( $cmd, $parms );
        }
        else

        if( SEEDCore_StartsWith( $cmd, 'src' ) ) {
            include_once( "QServerSources.php" );
            $o = new QServerSourceCV( $this->oApp, $this->raConfig );
            $rQ = $o->Cmd( $cmd, $parms );
        }
        else

        if( SEEDCore_StartsWith( $cmd, 'slprofile' ) ) {
            include_once( SEEDLIB."sl/QServerSLProfile.php" );
            $rQ = (new QServerSLProfile($this->oApp, $this->raConfig))->Cmd( $cmd, $parms );
        }
        else

        if( SEEDCore_StartsWith( $cmd, 'collection' ) ) {
//            include_once( "_QServerCollection.php" );
//            $o = new QServerCollection( $this->oApp, $this->raConfig );
//            $rQ = $o->Cmd( $cmd, $parms );
        }
        else

        if( SEEDCore_StartsWith( $cmd, 'collreport' ) ) {
            include_once( SEEDLIB."sl/QServerSLCollectionReports.php" );
            $o = new QServerSLCollectionReports( $this->oApp, $this->raConfig );
            $rQ = $o->Cmd( $cmd, $parms );
        }
        else

        if( SEEDCore_StartsWith( $cmd, 'mbr' ) ) {
            include_once( SEEDLIB."mbr/QServerMbr.php" );
            $o = new QServerMbr( $this->oApp, $this->raConfig );
            $rQ = $o->Cmd( $cmd, $parms );
        }
        else

        if( SEEDCore_StartsWith( $cmd, 'msd' ) ) {
            include_once( SEEDLIB."msd/msdq.php" );
            $o = new MSDQ( $this->oApp, $this->raConfig );
            $rQ = $o->Cmd( $cmd, $parms );
        }
        else

        if( SEEDCore_StartsWith( $cmd, 'basket' ) ) {
            include_once( "QServerBasket.php" );
            $o = new QServerBasket( $this->oApp, $this->raConfig );
            $rQ = $o->Cmd( $cmd, $parms );
        }
        else {
            $rQ['sErr'] = "Unknown cmd";
        }

        done:
        return( $rQ );
    }

/* obsolete - using SEEDQ::QCharset
    function QCharset( $s )
    [**********************
        If the input is cp1252, the output will be the charset defined by $this->bUTF8
     *]
    {
        return( $this->bUTF8 ? SEEDCore_utf8_encode($s) : $s );
    }
*/

/* obsolete - using SEEDQ::GetEmptyRQ
    static function GetEmptyRQ()
    ]***************************
     *]
    {
        return( [ 'bOk'=>false, 'sOut'=>"", 'sErr'=>"", 'sLog'=>"", 'raOut'=>[], 'raMeta'=>[] ] );
    }
*/

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


/**
 * Execute a qcmd with the given oApp, and return the result to stdout.
 * This helps apps to receive their own qcmds and redirect them here, which is convenient because they
 * can just use pathToSelf() for the q server.
 *
 * Usage:  if( ($qcmd = SEEDInput_Str('qcmd')) ) DoQCmd($oApp, $qcmd);     // this never returns

 * @param SEEDAppBase $oApp
 * @param string $qcmd
 * @param array $raParms
 */
function DoQCmd( SEEDAppBase $oApp, string $qcmd, array $raParmsNotUsed = [] )
{
    $rQ = SEEDQ::GetEmptyRQ();

    if( $qcmd ) {
        $rQ = (new Q($oApp, ['bUTF8'=>true]))->Cmd( $qcmd, $_REQUEST ); // raParms could be merged with this

        /* Write cmd and sLog to log file. Then unset it so we don't send our log notes to the user.
         */
        $oApp->Log( "q.log", $_SERVER['REMOTE_ADDR']."\t"
                            .intval(@$rQ['bOk'])."\t"
                            .$qcmd."\t"
                            .(@$rQ['sLog'] ? : "") );
        unset($rQ['sLog']);
    }

    // Allow any domain to make ajax requests - see CORS
    // Note that this is even necessary for http://www.seeds.ca to access https://www.seeds.ca/.../q because the
    // CORS access control policy is per (scheme|domain|port)
    header( "Access-Control-Allow-Origin: *" );
    echo json_encode( $rQ );

    exit;
}

/* obsolete: using SEEDQCursor instead
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
*/
