<?php

/* QServerDocRep
 *
 * Copyright 2021-2022 Seeds of Diversity Canada
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
                
            case 'dr--add':
                $rQ['bHandled'] = true;
                list($rQ['bOk'],$rQ['sOut']) = $this->doAdd($kDoc, $parms);
                break;

            case 'dr--update':
                $rQ['bHandled'] = true;
                list($rQ['bOk'],$rQ['sOut']) = $this->doUpdate($kDoc);
                break;

            case 'dr--rename':
                $rQ['bHandled'] = true;
                list($rQ['bOk'],$rQ['sOut']) = $this->doRename($kDoc, $parms);
                break;
        }

        done:
        return( $rQ );
    }

    private function doPreview( $kDoc )
    {
        $s = "Preview";
        $bOk = false;

        if( $kDoc && ($oDoc = $this->oDocRepDB->GetDocRepDoc( $kDoc )) ) {
            switch( $oDoc->GetType() ) {
                case 'FOLDER':
                    $s = "FOLDER";
                    break;
                //case 'LINK':          not a type; it's a storage method
                //    $s = "Link to another doc";
                //    break;
                case 'TEXT':
                case 'TEXTFRAGMENT':
                    $s = $oDoc->GetText('');
                    break;
                case 'BIN':
                case 'IMAGE':
                    if( SEEDCore_StartsWith( $oDoc->GetValue( 'mimetype', ''), 'image/' ) ) {
                        $s = "This should be an <img/>";
                    } else {
                        $s = "This should be a <a>link to download the file</a>";
                    }
                    break;
            }

            $bOk = true;
        }

        return( [$bOk,$s] );
    }
    
    private function doAdd ( $kDoc, $parms ){
        $s = "";
        $bOk = false;
        $oDoc = new DocRepDoc2_Insert( $this->oDocRepDB );
        
        if( $parms['type'] == 'file' ) {
            $bOk = $oDoc->InsertFile( "", $parms );
        }
        else if( $parms['type'] == 'folder' ) {
            $bOk = $oDoc->InsertFolder($parms);
        }
        return( [$bOk,$s] );
    }

    private function doUpdate( $kDoc )
    {
        $s = "";
        $bOk = false;

        if( $kDoc && ($oDoc = $this->oDocRepDB->GetDocRepDoc( $kDoc )) ) {
            if( ($p_text = SEEDInput_Str('p_text')) ) {
                $bOk = $oDoc->Update( ['src'=>'TEXT', 'data_text'=>$p_text] );
            }
        }

        return( [$bOk,$s] );
    }

    private function doRename( $kDoc, $parms )
    {
        $s = "";
        $bOk = false;

        if( $kDoc && ($oDoc = $this->oDocRepDB->GetDocRepDoc( $kDoc )) ) {
            $bOk = $oDoc->Rename( $parms );
        }

        return( [$bOk,$s] );
    }
}
