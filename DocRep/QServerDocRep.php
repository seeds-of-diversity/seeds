<?php

/* QServerDocRep
 *
 * Copyright 2021 Seeds of Diversity Canada
 *
 * Serve queries for Document Repositories
 */

include_once( "DocRep.php" );

class QServerDocRep extends SEEDQ
{
    private $oDocRepDB;

    function __construct( SEEDAppSessionAccount $oApp, $raConfig = [] )
    {
        parent::__construct( $oApp, $raConfig );
        $this->oDocRepDB = DocRepUtil::New_DocRepDB_WithMyPerms( $oApp );
    }

    function Cmd( $cmd, $parms )
    {
        $rQ = $this->GetEmptyRQ();

        if( !SEEDCore_StartsWith( $cmd, 'dr-' ) ) goto done;

        // check permissions


        $kDoc = SEEDInput_Int('kDoc');

        switch( $cmd ) {
            case 'dr-preview':
                $rQ['bHandled'] = true;
                list($rQ['bOk'],$rQ['sOut']) = $this->doPreview($kDoc);
                break;
        }

        done:
        return( $rQ );
    }

    private function doPreview( $kDoc )
    {
        $s = "";
        $bOk = false;

        if( $kDoc && ($oDoc = $this->oDocRepDB->GetDocRepDoc( $kDoc )) ) {
            switch( $oDoc->GetType() ) {
                case 'FOLDER':
                    $s = "FOLDER";
                    break;
                case 'LINK':
                    $s = "Link to another doc";
                    break;
                case 'TEXT':
                    $s = $oDoc->GetText('');
                    break;
                case 'BIN':
                    if( SEEDCore_StartsWith( $oDoc->GetValue( 'mimetype', ''), 'image/' ) ) {
                        $s = "This should be an <img/>";
                    } else {
                        $s = "This should be a <a>link to download the file</a>";
                    }
                    break;
            }

            $bOk = true;
        }

        done:
        return( [$bOk,$s] );
    }
}