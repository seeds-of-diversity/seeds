<?php

/* mbr_ts_ebulletin
 *
 * Copyright 2017-2020 Seeds of Diversity Canada
 *
 * Ebulletin tabset UI
 */
include_once( SEEDLIB."mbr/MbrEbulletin.php" );
include_once( SEEDCORE."SEEDTableSheets.php" );


class MbrUIEbulletin
{
    private $oApp;
    private $oEbullLib;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oEbullLib = new MbrEbulletin( $oApp );
    }

    function DoAction( $action )
    {
        $s = "";
        $raEmails = null;

        switch( $action ) {
            case 'Add':    $s = $this->doAdd();            break;
            case 'Delete': $s = $this->doDelete();         break;
            case 'Upload': $raEmails = $this->doUpload();  break;
            default:                                       break;
        }

        return( [$s,$raEmails] );
    }

    private function marshalFormInput()
    /**********************************
        Elements in the form are organized as: (e1,n1,l1,c1),(e2,n2,l1,c1),...
        The eN elements are essentially keys for the tuples since they are the only values that must exist, and they must be unique.
     */
    {
        $raRows = [];

        foreach( $_REQUEST as $k => $v ) {
            if( SEEDCore_StartsWith($k,'e') && ($i = intval(substr($k,1))) ) {
                // $v is the email value and it must not be blank
                if( !$v ) continue;

                $raRows[] = [ 'email'=>$v, 'name'=>SEEDInput_Str("n$i"), 'lang'=>SEEDInput_Str("l$i"), 'comment'=>SEEDInput_Str("c$i") ];
            }
        }
        return( $raRows );
    }

    private function doAdd()
    {
        $s = "<h3>Adding email addresses</h3>";

        foreach( $this->marshalFormInput() as $ra ) {
            list($eRetBull,$eRetMbr,$sResult) = $this->oEbullLib->AddSubscriber( $ra['email'], $ra['name'], $ra['lang'], $ra['comment'] );
            $s .= $sResult;
        }

        return( $s );
    }

    private function doDelete()
    {
        $s = "<h3>Unsubscribing email addresses</h3>";

        foreach( $this->marshalFormInput() as $ra ) {
            list($eRetBull,$eRetMbr,$sResult) = $this->oEbullLib->RemoveSubscriber( $ra['email'] );
            $s .= $sResult;
        }

        return( $s );
    }

    private function doUpload()
    /**************************
        Upload a spreadsheet file and put the contents into ContentDraw's form
     */
    {
        $raRows = $this->oEbullLib->ReadSubscribersFromFile( 'upfile' );
        return( $raRows );
    }

}