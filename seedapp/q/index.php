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
$qfmt = SEEDInput_Str('qfmt') ?: 'json';

$oQ = new Q( $oApp, ['bUTF8'=>true] );  // return utf8 data unless this is reversed below
$sCharset = "utf-8";
$rQ = $oQ->Cmd( $qcmd, $_REQUEST );

/* Write cmd and sLog to log file. Then unset it so we don't send our log notes to the user.
 */
$oApp->Log( "q.log", $_SERVER['REMOTE_ADDR']."\t"
                    .intval(@$rQ['bOk'])."\t"
                    .$qcmd."\t"
                    .(@$rQ['sLog'] ? : "") );
unset($rQ['sLog']);


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
            include_once( SEEDCORE."SEEDXLSX.php" );
            $sCharset = 'utf-8';
            header( "Content-Type:text/plain; charset=$sCharset" );     // should this be text/csv ?

            SEEDXlsx_WriteFileCSV( [], $rQ['raOut'],
                                   ['sCharsetRows'=>$sCharset,
                                    'sCharsetFile'=>$sCharset ] );
        }
        break;

    case 'xls':
        if( $rQ['bOk'] ) {
            include_once( SEEDCORE."SEEDXLSX.php" );

            $oXLSX = new SEEDXlsWrite( ['title'=>$title,
                                        'filename'=>$name.'.xlsx',
                                        'creator'=>$oApp->sess->GetName(),
                                        'author'=>$oApp->sess->GetName()] );

            $oXLSX->WriteHeader( 0, array_keys($rQ['raOut'][0]) );

            $iRow = 2;  // rows are origin-1 so this is the row below the header
            foreach( $rQ['raOut'] as $ra ) {
                $oXLSX->WriteRow( 0, $iRow++, $ra );
            }

            $oXLSX->OutputSpreadsheet();
            exit;
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
