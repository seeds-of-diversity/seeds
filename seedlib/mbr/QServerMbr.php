<?php

/* QServerMbr
 *
 * Copyright 2019 Seeds of Diversity Canada
 *
 * Contacts Q layer
 */

class QServerMbr extends SEEDQ
{
    function __construct( SEEDAppConsole $oApp, $raConfig )
    /******************************************************
     */
    {
        parent::__construct( $oApp, $raConfig );
    }

    function Cmd( $cmd, $raParms = array() )
    {
        $rQ = $this->GetEmptyRQ();

        if( SEEDCore_StartsWith( $cmd, 'mbr--' ) ) {
            $rQ['bHandled'] = true;

            if( !$this->oApp->sess->CanWrite('MBR') ) {
                $rQ['sErr'] = "<p>You do not have permission to change mbr information.</p>";
                goto done;
            }

            goto done;  // not implemented yet

        } else
        if( SEEDCore_StartsWith( $cmd, 'mbr-' ) ) {
            $rQ['bHandled'] = true;

            if( !$this->oApp->sess->CanRead('MBR') ) {
                $rQ['sErr'] = "<p>You do not have permission to read mbr information.</p>";
                goto done;
            }

        }

        switch( $cmd ) {
            case 'mbr-search':
                list($rQ['bOk'],$rQ['raOut'],$rQ['sErr']) = $this->mbrSearch( $raParms );
                break;
        }

        if( !$rQ['bHandled'] )  $rQ = parent::Cmd( $cmd, $raParms );

        done:
        return( $rQ );
    }


    private function mbrSearch( $raParms )
    /*************************************
        Find a row in mbr_contacts by searching various fields
            sSearch   : search string
            nMinChars : don't search if sSearch is fewer than this many chars (default 3)
     */
    {
        $bOk = false;
        $raOut = array();
        $sErr = "";

        // impose a lower limit to the number of chars in the search string to prevent unwieldy results
        if( !($nMinChars = intval(@$raParms['nMinChars'])) )  $nMinChars = 3;
        if( strlen( ($sSearch = @$raParms['sSearch']) ) < $nMinChars )  goto done;

        $raM = $this->oApp->kfdb->QueryRowsRA( "SELECT * FROM seeds2.mbr_contacts WHERE _status='0' AND "
                                                ."(_key='$sSearch' OR "
                                                 ."firstname LIKE '%$sSearch%' OR "
                                                 ."lastname  LIKE '%$sSearch%' OR "
                                                 ."company   LIKE '%$sSearch%' OR "
                                                 ."email     LIKE '%$sSearch%' OR "
                                                 ."address   LIKE '%$sSearch%' OR "
                                                 ."city      LIKE '%$sSearch%')" );

        if( $raM && count($raM) ) {
            foreach( $raM as $ra ) {
                $raR = array();
                $raR['_key']      = $ra['_key'];
                $raR['firstname'] = $this->QCharSet($ra['firstname']);
                $raR['lastname']  = $this->QCharSet($ra['lastname']);
                $raR['company']   = $this->QCharSet($ra['company']);
                $raR['email']     = $this->QCharSet($ra['email']);
                $raR['phone']     = $this->QCharSet($ra['phone']);
                $raR['address']   = $this->QCharSet($ra['address']);
                $raR['city']      = $this->QCharSet($ra['city']);
                $raR['province']  = $this->QCharSet($ra['province']);
                $raR['postcode']  = $this->QCharSet($ra['postcode']);

                $raOut[] = $raR;
            }

            $bOk = true;
        }

        done:
        return( array($bOk, $raOut, $sErr) );
    }
}

