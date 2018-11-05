<?php

/* SEEDSessionPerms
 *
 * Copyright 2015-2018 Seeds of Diversity Canada
 *
 * SEEDSessionAccount and SEEDPerms both use userids and usergroups, but SEEDPerms is supposed to be general and unaware of SEEDSessionAccount.
 * The main disconnect is SEEDPerms doesn't know which userids are in the usergroups.
 * Here are functions that connect them.
 */

include_once( "SEEDPerms.php" );
include_once( "SEEDSessionAccountDB.php" );


function New_SEEDPermsFromUID( SEEDAppDB $oApp, $uid, $appname )
/***************************************************************
    Get a SEEDPermsTest for the given user, taking into account their SEEDSession_Groups
 */
{
// kluge: $uid should never be 0 but if it is we undoubtedly mean the anonymous user
if( !$uid )  $uid = -1;

    $o = new SEEDSessionAccountDBRead( $oApp->kfdb );
    $raGroups = $o->GetGroupsFromUser( $uid );
    return( new SEEDPermsTest( $oApp, $appname, array($uid), $raGroups ) );
}


function SEEDSessionPerms_GetUseridsFromPermClass( SEEDAppDB $oApp, $permclass, $mode, $bDetail = false )
/********************************************************************************************************
    Get the userids that have access to the given permclass in the given mode.
    SEEDPerms can give you the userids and usergroups, but only SEEDSession can convert the usergroups to userids
 */
{
    $oSEEDPerms = new SEEDPermsRead( $oApp );
    list($raUserids,$raGroups) = $oSEEDPerms->GetUsersFromPermClass( $permclass, $mode );
    if( count($raGroups) ) {
        $o = new SEEDSessionAccountDBRead( $oApp->kfdb );
        foreach( $raGroups as $g ) {
            $raU = $o->GetUsersFromGroup( $g, array('bDetail'=>false) );    // just get the userids
            $raUserids = array_merge( $raUserids, $raU );
        }
        $raUserids = array_unique( $raUserids );
    }

    // raUserids is an array of SEEDSession_Users._key

    if( $bDetail && $raUserids ) {
        // Transform to an array of _key => user info -- there should be a more efficient way to do this (in SEEDSessionAccountDB?) using SEEDCore_MakeRangeStrDB
        $o = new SEEDSessionAccountDBRead( $oApp->kfdb );
        $raU2 = array();
        foreach( $raUserids as $uid ) {
            list($k,$raUser,$raMetadata) = $o->GetUserInfo( $uid, false );
            if( $k ) {
                $raU2[$uid] = $raUser;
            }
        }
        $raUserids = $raU2;
    }

    return( $raUserids );
}

?>