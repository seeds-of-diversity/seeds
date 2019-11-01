<?php

/* SEEDProblemSolver
 *
 * Copyright 2014-2019 Seeds of Diversity Canada
 *
 * Perform tests, and implement remedies
 */


class SEEDProblemSolver
/**********************
    Define tests to perform, and remedies

        title      = the title for the test
        testType   = rows0 | n0 | report-nofail
        testFn     = function to perform test
        testSql    = sql to perform test
        remedyType = '' |
        remedyFn   = function to perform a remedy
        remedySql  = sql to perform a remedy
        bNonFatal  = this test returns SPS_WARNING if it fails; else returns SPS_ERROR (which is the default if bNonFatal is not defined)

        rows0:         a test that returns failure rows (0 rows is success)
                           testFn returns array( array( k1=>v1,k2=>v2,... ) ) of rows that fail the test
                        OR testSql is a SQL string whose results are failure rows

        n0:            a test that returns the number of failures (0 is success)
                           testFn returns a number
                        OR testSql is a SQL string whose result is a number (e.g. count(*))

        report-nofail: a test that reports information with no failure
                           testFn returns a string containing the report
 */
{
    protected $raDefs;
    private $raParms;
    private $sOut = "";
    private $sErr = "";

    // Use $result == SOS_OK to move ahead only if no errors or warnings
    // Use $result != SPS_ERROR to move ahead despite warnings
    const SPS_ERROR   = 0;    // returned if a test fails and !bNonFatal (or undefined)
    const SPS_OK      = 1;
    const SPS_WARNING = 2;    // returned if a test fails and bNonFatal

    function __construct( $raDefs, $raParms )
    {
        $this->raDefs = $raDefs;
        $this->raParms = $raParms;
    }

    function GetOutput()  { return( $this->sOut ); }
    function GetErr()     { return( $this->sErr ); }
    function Clear()      { $this->sOut = $this->sErr = ""; }

    function TestAll()
    {
        $bAllPassed = true;

        foreach( $this->raDefs as $k => $raPS ) {
            if( !$this->Test( $k ) ) {
                $bAllPassed = false;
                break;
            }
        }
        return( $bAllPassed );
    }

    function Test2( $k )
    /******************
        Perform the given test, return an SPS_* code

        Return SPS_OK if the test passes
               SPS_WARNING if the test fails but it's bNonFatal
               SPS_ERROR if the test fails and it is not bNonFatal
     */
    {
        $bOk = true;
        $spsRet = self::SPS_OK;

        $def = $this->raDefs[$k]   or die( "ProblemSolver def $k not defined" );

        /* Perform the test using one of various methods.
         *     result = the result of the test, in a format known to 'testType'
         *     sOut   = regular output regarding the result of the test
         *     sErr   = error message if the test cannot be performed
         */
        if( isset($def['testFn']) ) {
            list($result,$sTestOut,$sTestErr) = call_user_func( $def['testFn'], $k, $this->raParms );
        } else if( isset($def['testSql']) ) {
            $kfdb = @$this->raParms['kfdb'] or die('ProblemSolver: kfdb parm not specified');
            switch( $def['testType'] ) {
                case 'rows0': $result = $kfdb->QueryRowsRA( $def['testSql'] ); break;    // form must be SELECT fields FROM... returning 0 or more rows
                case 'n0':    $result = $kfdb->Query1( $def['testSql'] ); break;         // form must be SELECT int1 FROM ... returning 1 row; e.g. SELECT count(*) FROM...
                default:      $result = null;
            }
        } else {
            die( "ProblemSolver: no test defined for $k" );
        }

        /* Interpret the $result, depending on the 'testType'
         */
        $outHeading = "";
        $outDetails = "";
        switch( $def['testType'] ) {
            case 'rows0':
                // result is an array of fail rows
                if( count($result) ) {
                    $bOk = false;
                    $outHeading = str_replace( '[[n]]', count($result), $def['failLabel'] );
                    if( @$def['failShowRow'] ) {
                        foreach( $result as $ra ) {
                            $outDetails .= SEEDCore_ArrayExpand( $ra, $def['failShowRow'] )."<BR/>";
                        }
                    } else if( @$def['failShowFn'] ) {
                        $outDetails .= call_user_func($def['failShowFn'], $result, $k, $this->raParms ); // argument is array( array of failed rows )
                    }
                }
                break;
            case 'n0':
                // result is the number of failures
                if( $result != 0 ) {
                    $bOk = false;
                    $outHeading = str_replace( '[[n]]', intval($result), $def['failLabel'] );
                }
                break;
            case 'report-nofail':
                // this "test" is not a test that can fail, but rather some kind of report
                $bOk = true;
                $outHeading = $def['title'];
                $outDetails = $sTestOut;
                break;
            default:
                die( "ProblemSolver $k has no test type" );
        }

        $spsRet = ($bOk ? self::SPS_OK : (@$def['bNonFatal'] ? self::SPS_WARNING : self::SPS_ERROR));


        $s = $this->DrawResult( $k, $spsRet, $outHeading, $outDetails );

        return( array($spsRet, $s) );
    }

    function Test( $k )
    {
        list($eSPS, $s) = $this->Test2( $k );

        $this->sOut .= $s;

        return( $eSPS );
    }


    function DrawResult( $k, $spsRet, $sHeading, $sDetails )
    /*******************************************************
        Draw the result of test $k where the spsRet is SP_* result, sHeading is a heading for the result report and sDetails contains any details
     */
    {
        $def = $this->raDefs[$k]   or die( "ProblemSolver def $k not defined" );

        switch( $spsRet ) {
            case self::SPS_OK:      $sColor = "green"; break;
            case self::SPS_WARNING: $sColor = "orange"; break;
            default:                $sColor = "red"; break;
        }

        $s = "<h4 style='margin-bottom:0px;color:$sColor'>".$def['title']."</h4>";

        if( @$def['testSql'] && @$this->raParms['bShowSql'] ) {
            $s .= "<div style='color:gray;font-size:7pt;margin-left:20px;margin-bottom:10px'>"
                 ."<i>".SEEDCore_HSC($def['testSql'])."</i></div>";
        }

        $s .= "<div style='margin-left:20px;'>"  // color:$sColor
             .($spsRet == self::SPS_OK ? ""/*"Okay"*/ : $sHeading)
             ."</div>";
        if( $spsRet != self::SPS_OK || $def['testType']=='report-nofail' ) {    // kluge: show details for report-nofail because it never fails
            if( $sDetails ) {
                $s .= "<div style='margin-left:40px'>$sDetails</div>";
            }
            if( @$def['remedyLabel'] ) {
                $s .= $this->DrawRemedyBlock( $k );
            }
        }

        $s = "<div style='margin-bottom:10px'>$s</div>";

        return( $s );
    }

    function DrawRemedyBlock( $k, $eMode = "" )
    /******************************************
        Draw a remedy suggestion and link

        eMode = ""           : just suggest the remedy with a link to confirm it
        eMode = "Confirm"    : user has confirmed the remedy; do it
     */
    {
        $def = $this->raDefs[$k]   or die( "ProblemSolver def $k not defined" );

        $s = "";

        if( $eMode != 'Confirm' ) {
            // Step 1: describe the remedy with a link to a confirmation
            $s .= "<div style='margin-left:20px;color:blue'>".$def['remedyLabel']."</div>"
                 ."<div style='margin-left:40px'>".$this->RemedyLink( $k, $eMode )."</div>";
        } else {
            // Step 2: describe the confirmation
            $s .= "<p>You have requested the following remedy. Confirm by clicking the link.</p>"
                 ."<div style='margin-left:20px;color:blue'>".$def['remedyLabel']."</div>"
                 ."<div style='margin-left:40px'>".$this->RemedyLink( $k, 'Confirm' )."</div>";
        }
        return( $s );
    }

    function RemedyLink( $k, $eMode = "" )
    {
        $def = $this->raDefs[$k]   or die( "ProblemSolver def $k not defined" );

        $s = "";

        if( @$def['remedySql'] || @$def['remedyFn'] ) {
            $s .= "<a href='?spsSolve".(($eMode=='Confirm') ? "Confirmed" : "")."=$k'>"
                 ."Solve this problem</a> : <i>".SEEDCore_HSC($def['remedySql'])."</i>";
            if( @$def['remedySql'] && @$this->raParms['bShowSql'] ) {
                $s .= "<div style='color:gray;font-size:7pt;margin-left:20px;margin-bottom:10px'>"
                     ."<i>".SEEDCore_HSC($def['remedySql'])."</i></div>";
            }
        }

        return( $s );
    }

//TODO move this to SEEDProblemSolverUI. Currently, some callers are using that, and this is just inherited.
    function DrawRemedyUI()
    /**********************
        Handle UI for requesting fixes

        1) request a remedy by clicking on a link with spsSolve=k; this draws a confirm link with spsSolveConfirmed=e
        2) confirm a remedy by clicking on the confirm link; this executes DoRemedy
     */
    {
        $sOut = $sErr = "";

        if(($k = SEEDInput_Str("spsSolve"))) {
            // User has clicked on a remedy link. Ask for confirmation
            $sOut = $this->DrawRemedyBlock( $k, "Confirm" );
        }
        if(($k = SEEDInput_Str("spsSolveConfirmed"))) {
            // User has clicked on a fix confirmation.  Do the fix.
            list($sOut,$sErr) = $this->DoRemedy( $k );
        }
        return( array($sOut,$sErr) );
    }

    function DoRemedy( $k )
    {
        $def = $this->raDefs[$k]   or die( "ProblemSolver def $k not defined" );

        $sOut = $sErr = "";

        if( isset($def['remedyFn']) ) {
            list($result,$sOut,$sErr) = call_user_func( $def['remedyFn'], $k, $this->raParms );
        } else if( isset($def['remedySql']) ) {
            $kfdb = @$this->raParms['kfdb'] or die('ProblemSolver: kfdb parm not specified');
            $sOut = "Remedy $k"
                   ."<div style='color:blue'>".SEEDCore_HSC($def['remedySql'])."</div>";
            if( $kfdb->Execute( $def['remedySql'] ) ) {
                $sOut .= "successful";
            } else {
                $sErr = "failed: ".$kfdb->GetErrMsg();
            }
        } else {
            die( "ProblemSolver: no remedy defined for $k" );
        }

        return( array($sOut,$sErr) );
    }

    function DoTests( $sPrefix = '', $bReturnSPSCode = false )
    /*********************************************************
        Perform all tests that start with sPrefix (or all of them if sPrefix=='')
        Stop if SPS_ERROR happens.
        $eSPS is the worst SPS_* code encountered.
        Return true if eSPS is okay or warning; false if error -- or if bReturnSPSCode just return eSPS for the client to interpret
     */
    {
        $eSPS = self::SPS_OK;

        foreach( $this->raDefs as $k => $def ) {
            if( !$sPrefix || strpos( $k, $sPrefix ) === 0 ) {
                $e = $this->Test( $k );
                if( $e == self::SPS_WARNING ) {
                    $eSPS = $e;    // it won't already be SPS_ERROR because that would have been fatal
                }
                if( $e == self::SPS_ERROR ) {
                    $eSPS = $e;
                    break;         // errors stop the testing, caused by !$def['bNonFatal']
                }
            }
        }

        return( $bReturnSPSCode ? $eSPS : ($eSPS != self::SPS_ERROR) );
    }
}


class SEEDProblemSolverUI extends SEEDProblemSolver
{
    private $kCurrTest = '';    // the test currently selected by the user

    function __construct( $raDef, $raParms )
    {
        parent::__construct( $raDef, $raParms );
        // kCurrTest is either $raParms['SPSTest'] or $_REQUEST['SPSTest'] or ''
        $this->kCurrTest = SEEDCore_ArraySmartVal1( $raParms, 'SPSTest', SEEDInput_Str('SPSTest') );
    }

    function DrawTests( $sPrefix )
    {
        $eSPSWorst = self::SPS_OK;
        $sTabs = "";
        $sCurrTest = "";

        foreach( $this->raDefs as $kTest => $def ) {
            if( !$sPrefix || strpos( $kTest, $sPrefix ) === 0 ) {
                list($eSPS,$sOut) = $this->Test2( $kTest );

                if( $kTest == $this->kCurrTest ) {
                    $sCurrTest = $sOut;
                }

                $sTabs .= $this->drawTestTab( $kTest, $eSPS );

                if( $eSPS == self::SPS_WARNING ) {
                    $eSPSWorst = $eSPS;    // it won't already be SPS_ERROR because that would have been fatal
                }
                if( $eSPS == self::SPS_ERROR ) {
                    $eSPSWorst = $eSPS;
                    break;         // errors stop the testing, caused by !$def['bNonFatal']
                }
            }
        }

        return( array($sTabs, $sCurrTest) );
    }

    function drawTestTab( $kTest, $eSPS )
    {
        $sClass = "alert ";
        $sAttrs = "";

        // Colour the alert green, yellow, or red based on the test result
        switch( $eSPS ) {
            case self::SPS_OK:
                $sClass .= " alert-success";
                $sBorder = "green";
                break;
            case self::SPS_WARNING:
                $sClass .= " alert-warning";
                $sBorder = "#faebcc";
                break;
            case self::SPS_ERROR:
            default:
                $sClass .= " alert-danger";
                $sBorder = "#f88";
                break;
        }

        // if this is the current section, make it stand out
        // else the div is a link to make it the current section
        if( $kTest == $this->kCurrTest ) {
            $sAttrs = "style='padding:3px;margin:3px;font-weight:bold;border:2px solid $sBorder;'";
        } else {
            $sClass .= " small";
            $sAttrs = "style='padding:3px;margin:3px;cursor:pointer' onclick='location.replace(\"?SPSTest=$kTest\")'";
        }

        $s =  "<div class='$sClass' $sAttrs>"
             ."<p>{$this->raDefs[$kTest]['title']}</p>"
             ."</div>";

        return( $s );
    }
}
