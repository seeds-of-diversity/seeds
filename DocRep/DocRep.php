<?php

/* DocRep
 *
 * Copyright (c) 2006-2021 Seeds of Diversity Canada
 *
 * Document repository support
 */

include_once( SEEDCORE."SEEDSessionPerms.php" );
include_once( "DocRepDB.php" );

class DocRepUtil
{
    const DocRep_SEEDPerms_Appname = 'DocRep';

    static function New_DocRepDB_WithMyPerms( SEEDAppSessionAccount $oApp, $raParms = [] )
    /*************************************************************************************
        Construct a DocRepDB with the current user's SEEDPerms installed.
        This is probably how you will always make a DocRepDB, instead of creating it directly.
     */
    {
        $bReadonly = @$raParms['bReadonly'];

        $oPerms = New_SEEDPermsFromUID( $oApp, $oApp->sess->GetUID(), self::DocRep_SEEDPerms_Appname );

        $parms = [ 'db' => @$raParms['db'],     // logical db (if blank, DocRep uses the $oApp connection)
                   'raPermClassesR' => $oPerms->GetClassesAllowed( "R", false )
                 ];
        if( !$bReadonly ) {
            $parms['raPermClassesW'] = $oPerms->GetClassesAllowed( "W", false );
        }

        return( new DocRepDB2( $oApp, $parms ) );
    }
}
