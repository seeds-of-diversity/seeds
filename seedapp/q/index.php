<?php

/* Q http entry point.
 *
 * Include this from another file that sets the seedConfig
 */

if( !defined('SEEDROOT') ) { die( "set the seedConfig first" ); }

include_once( SEEDCORE."SEEDApp.php" );
include_once( SEEDLIB."q/Q.php" );

if( !defined("Q_DB") )  define("Q_DB", "seeds1");   // caller can initialize Q to any other database

$oApp = SEEDConfig_NewAppConsole_LoginNotRequired(
            ['db'=>Q_DB,
             'lang' => 'EN' ] );

$qcmd = SEEDInput_Str('qcmd');
$qfmt = SEEDInput_Smart( 'qfmt', ['json'] );

$oQ = new Q( $oApp, ['bUTF8'=>true] );  // return utf8 data unless this is reversed below
$sCharset = "utf-8";
$rQ = $oQ->Cmd( $qcmd, $_REQUEST );

($name  = (@$raQParms['qname']))  || ($name  = (@$rQ['raMeta']['name']))  || ($name = $qcmd);
($title = (@$raQParms['qtitle'])) || ($title = (@$rQ['raMeta']['title'])) || ($title = $qcmd);

switch( $qfmt ) {
    case 'plain':    echo $rQ['sOut'];         break;
    case 'plainRA':  var_dump( $rQ['raOut'] ); break;
    case 'json':
        // Allow any domain to make ajax requests - see CORS
        // Note that this is even necessary for http://www.seeds.ca to access https://www.seeds.ca/.../q because the
        // CORS access control policy is per (scheme|domain|port)
        header( "Access-Control-Allow-Origin: *" );
        echo json_encode( $rQ );
        break;
    /* not tested but used for cross-site ajax
    case 'jsonp':
        $raOut['name'] = "response";
        echo $_GET['callback']."(".json_encode($raOut).");";
        break;
    */
    case 'csv':
        if( $rQ['bOk'] ) {
//            include_once( STDINC."SEEDTable.php" );
//            $sCharset = 'utf-8';
//            header( "Content-Type:text/plain; charset=$sCharset" );
//            SEEDTable_OutputCSVFromRARows( $rQ['raOut'],
//                               array( //'columns' => array_keys($rQ['raOut'][0]),  use default columns
//                                      ) );
        }
        break;

    case 'xls':
        if( $rQ['bOk'] ) {
            include_once( SEEDCORE."SEEDXLSX.php" );

            $oXLSX = new SEEDXlsWrite();

            $iRow = 0;
            foreach( $rQ['raOut'] as $ra ) {
                $oXLSX->WriteRow( 0, $iRow++, $ra );
            }

            $oXLSX->OutputSpreadsheet();
            exit;

//            include_once( STDINC."SEEDTable.php" );

            // PHPExcel sends the header( Content-Type )
            // N.B. the data has to be utf8 or PHPExcel will fail to write it
//            SEEDTable_OutputXLSFromRARows( $rQ['raOut'],
//                               array( 'columns' => array_keys($rQ['raOut'][0]),
//                                      'filename'=>"$name.xls",
//                                      'created_by'=>$sess->GetName(),
//                                      'title'=>'$title'
//                                      ) );
        }
        break;

    case 'xml':
        if( $rQ['bOk'] ) {
            $sCharset = 'utf-8';
            header( "Content-Type:text/xml; charset=$sCharset" );

            $s = "<q name='$name'>";
            foreach( $rQ['raOut'] as $row ) {
                $s .= "<qrow>";
                foreach( $row as $k => $v ) {
                    $k = str_replace( ' ', '-', $k );
                    $s .= "<$k>$v</$k>";
                }
                $s .= "</qrow>";
            }
            $s .= "</q>";

            echo $s;
        }
        break;

    default:
        break;
}

$oApp->Log( "q.log", $_SERVER['REMOTE_ADDR']."\t"
                    .intval(@$rQ['bOk'])."\t"
                    .$qcmd."\t"
                    .(@$rQ['sLog'] ? : "") );
