<?php

/* Q http entry point.
 *
 * Include this from another file that sets the seedConfig
 */

if( !defined('SEEDROOT') ) { die( "set the seedConfig first" ); }

include_once( SEEDCORE."SEEDApp.php" );

$oApp = SEEDConfig_NewAppConsole( ['db'=>'seeds1',
                                   'sessPermsRequired' => ['PUBLIC'],
                                   'lang' => 'EN' ] );

$qcmd = SEEDInput_Str('qcmd');
$qfmt = SEEDInput_Smart( 'qfmt', ['json'] );

/*
$oQ = new Q( $oApp );

    // the charset returned by this query will always be utf8, unless this is reversed below
    $oQ->bUTF8 = true;
    $sCharset = "utf-8";
    $rQ = $oQ->Cmd( $cmd, $raQParms );

    if( !($name  = (@$raQParms['qname']))  && !($name  = (@$rQ['raMeta']['name'])) )  $name = $cmd;
    if( !($title = (@$raQParms['qtitle'])) && !($title = (@$rQ['raMeta']['title'])) ) $title = $cmd;
*/

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
            include_once( STDINC."SEEDTable.php" );
            $sCharset = 'utf-8';
            header( "Content-Type:text/plain; charset=$sCharset" );
            SEEDTable_OutputCSVFromRARows( $rQ['raOut'],
                               array( //'columns' => array_keys($rQ['raOut'][0]),  use default columns
                                      ) );
        }
        break;

    case 'xls':
        if( $rQ['bOk'] ) {
            include_once( STDINC."SEEDTable.php" );

            // PHPExcel sends the header( Content-Type )
            // N.B. the data has to be utf8 or PHPExcel will fail to write it
            SEEDTable_OutputXLSFromRARows( $rQ['raOut'],
                               array( 'columns' => array_keys($rQ['raOut'][0]),
                                      'filename'=>"$name.xls",
                                      'created_by'=>$sess->GetName(),
                                      'title'=>'$title'
                                      ) );
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

Site_Log( "q.log", date("Y-m-d H:i:s")."\t"
                  .$_SERVER['REMOTE_ADDR']."\t"
                  .intval(@$rQ['bOk'])."\t"
                  .$cmd."\t"
                  .(@$rQ['sLog'] ? : "") );
