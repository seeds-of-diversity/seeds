<?php

include_once( "MbrContacts.php" );

class MbrEbulletin
{
    private $oApp;
    private $oDB;
    private $oMbr;

    // uploaded spreadsheets must have these columns
    private $uploadDef = [ 'headers-required' => ['email','language'],      // uploaded xls must have these columns
                           'headers-optional' => ['name','comments'],       // uploaded xls can have these columns
                           'charset-sheet' => 'cp1252' ];                   // convert uploaded data to Windows-1252
    function __construct( SEEDAppConsole $oApp )        // oApp->sess->GetUID() might be 0 because this is used for public sign-up
    {
        $this->oApp = $oApp;
        $this->oDB = new MbrEbulletinDB( $oApp );
        $this->oMbr = new Mbr_Contacts( $oApp );
    }

    const SUBSCRIBER_EBULLETIN = 1;     // subscriber via ebulletin list
    const SUBSCRIBER_CONTACTS  = 2;     // subscriber via contacts record
    const SUBSCRIBER_ANYSOURCE = 3;     // subscriber via either
    function IsSubscriber( string $email, int $eSource )
    {
        $bSubscriber = (($eSource | self::SUBSCRIBER_EBULLETIN) && $this->GetSubscriber($email)) ||
                       (($eSource | self::SUBSCRIBER_CONTACTS) && ($raM = $this->oMbr->GetAllValues($email)) && !$raM['bNoEBull']);
        return($bSubscriber);
    }

    function GetSubscriber( $email )
    {
        return( $this->oDB->GetRecordValsCond( 'B', "email='{$this->oApp->kfdb->EscapeString($email)}'") );
    }

    function GetSubscriberEmails( $lang )
    {
        switch( $lang ) {
            case 'EN': $sCond = "lang IN ('','B','E')";      break;     // '' in db is interpreted as E by default
            case 'FR': $sCond = "lang IN ('B','F')";         break;
            case '':
            default:   $sCond = "lang IN ('','B','E','F')";  break;     // '' in this form's ctrl is interpreted as All
        }

        return( $this->oDB->Get1List('B', 'email', $sCond ) );
    }

    function AddSubscriber( $email, $name, $lang, $comment )
    {
        $eRetBull = $eRetMbr = false;
        $sResult = "";

        $lang = $this->normalizeLanguage( $lang );

        if( !$email ) {
            $eRetBull = $eRetMbr = 'emailblank';
            $sResult = "no email address given";
            goto done;
        }

        /* Add to bull_list
         */
        if( ($kfr = $this->oDB->GetKFRCond('B', "email='".addslashes($email)."'")) ) {
            $eRetBull = "dup";
            $sResult .= "<span style='color:orange'>$email : already in subscriber list</span><br/>";
        } else {
            if( ($kfr = $this->oDB->Kfrel('B')->CreateRecord()) ) {
                $kfr->SetValue( 'email',    $email );
                $kfr->SetValue( 'name',     $name );
                $kfr->SetValue( 'lang',     $lang );
                $kfr->SetValue( 'comment',  $comment );
                $kfr->SetValue( 'status',   1 );
                $kfr->SetNull( 'ts0' );
                $kfr->SetNull( 'ts1' );
                $kfr->SetNull( 'ts2' );
                if( $kfr->PutDBRow() ) {
                    $eRetBull = 'ok';
                    $sResult .= "<span style='color:green'>$email : added to subscriber list</span><br/>";
                } else {
                    $this->oApp->oC->AddErrMsg( "<p>Sorry, unable to update email list.</p>".$this->oApp->kfdb->GetErrMsg() ); //
                }
            }
        }

        /* Set mbr_contacts.bNoEbull=0 if it's there
         */
        if( !($raMbr = $this->oMbr->GetAllValues( $email )) ) {
            $eRetMbr = 'notfound';
            $sResult .= "<span style='color:orange'>$email : not found in member database</span><br/>";
        } else if( !$raMbr['bNoEBull'] ) {
            $eRetMbr = 'alreadysubscribed';
            $sResult .= "<span style='color:orange'>$email : already subscribed in member database</span><br/>";
        } else if( $this->oMbr->EBullSubscribe( $raMbr['_key'], true ) ) {
            $eRetMbr = 'ok';
            $sResult .=  "<span style='color:green'>$email : subscribed in member database</span><br/>";
        } else {
            $this->oApp->oC->AddErrMsg( "<p>Sorry, unable to update member database.</p>" ); // $this->oApp->kfdb->GetErrMsg()
        }

        done:
        return( [$eRetBull,$eRetMbr,$sResult] );
    }

    function RemoveSubscriber( $email )
    {
        $eRetBull = $eRetMbr = false;
        $sResult = "";

        /* Delete from bull_list if it's there
         */
        if( !($kfr = $this->oDB->GetKFRCond('B', "email='".addslashes($email)."'")) ) {
            $eRetBull = 'notfound';
            $sResult .= "<span style='color:orange'>$email : not found in subscriber list</span><br/>";
        } else if( $kfr->DeleteRow() ) {
            $eRetBull = 'ok';
            $sResult .=  "<span style='color:green'>$email : removed from subscriber list</span><br/>";
        } else {
            $this->oApp->oC->AddErrMsg( "<p>Sorry, unable to update email list.</p>" ); // $this->oApp->kfdb->GetErrMsg()
        }

        /* Set mbr_contacts.bNoEbull if it's there
         */
        if( !($raMbr = $this->oMbr->GetAllValues( $email )) ) {
            $eRetMbr = 'notfound';
            $sResult .= "<span style='color:orange'>$email : not found in member database</span><br/>";
        } else if( $raMbr['bNoEBull'] ) {
            $eRetMbr = 'notsubscribed';
            $sResult .= "<span style='color:orange'>$email : already unsubscribed in member database</span><br/>";
        } else if( $this->oMbr->EBullSubscribe( $raMbr['_key'], false ) ) {
            $eRetMbr = 'ok';
            $sResult .=  "<span style='color:green'>$email : unsubscribed in member database</span><br/>";
        } else {
            $this->oApp->oC->AddErrMsg( "<p>Sorry, unable to update member database.</p>" ); // $this->oApp->kfdb->GetErrMsg()
        }

        return( [$eRetBull,$eRetMbr,$sResult] );
    }

    function ReadSubscribersFromFile( $file_upload_id )
    /**************************************************
        Read a list of (email,name,lang,comment) tuples from a spreadsheet file
     */
    {
        $ok = true;

        list($oSheets,$sErrMsg) = SEEDTableSheets_LoadFromUploadedFile( $file_upload_id, ['raSEEDTableSheetsLoadParms'=> $this->uploadDef] );
        if( !$oSheets ) {
            $this->oApp->oC->AddErrMsg( $sErrMsg );
            return;
        }
        $raRows = $oSheets->GetSheet($oSheets->GetSheetList()[0]);
        //var_dump($raRows); exit;

        // trim/fill all fields
        array_walk( $raRows, function (&$r){ foreach( ['name','email','language','comment'] as $p ) {
                                                 $r[$p] = isset($r[$p]) ? trim($r[$p]) : '';
                                             } } );

        // If name is given as two columns (first name,last name), join them
        array_walk( $raRows, function (&$r){ if( isset($r['first name']) && isset($r['last name']) )
                                                 $r['name'] = trim($r['first name']." ".$r['last name']);
                                           } );

        // Remove rows with no email and no name. (those with name but no email should be loaded and flagged later)
        $raRows = array_filter( $raRows, function ($r){ return( $r['name'] || $r['email'] ); } );

        // Convert errant language codes to expected format (E,F,B)   e.g. e -> E, EN -> E
        array_walk( $raRows, function (&$r){ $r['language'] = $this->normalizeLanguage( $r['language'] ); } );


        /* Warn about upload issues
         */
        if( !($nRows = count($raRows)) ) {
            $this->oApp->oC->AddErrMsg( "The file uploaded, but there didn't seem to be anything in it. Either it's an empty file, or there's something wrong with the columns." );
            $ok = false;
        }
        // PHP has a limit on the number of post-vars you can send at once.  If the file is too big, you won't be able to submit the form.
        $maxRows = ($miv = ini_get('max_input_vars')) ? ($miv - 20)/4 : 200;    // 20 is arbitrary for the blank rows that they UI might generate, 200 because the miv default is probably 1000
        if( $nRows > $maxRows ) {
            $this->oApp->oC->AddErrMsg( "Sorry, this program can only handle a maximum of $miv lines at a time. Please split this into smaller files." );
            $ok = false;
        }


        /* Warn about funny rows
         */
        $bErr = false;
        $bBadLang = false;
        foreach( $raRows as $r ) {
            if( !$r['email'] && $r['name'] ) {
                $this->oApp->oC->AddErrMsg( "Warning: {$r['name']} has a blank email.<br/>" );
                $ok = false;
            }

            if( $r['email'] ) {
                // a tricky way to check for non-allowed chars without using regex: convert those chars to one particular non-allowed char and check for it
                $raNonEmailChars = ["\r", "\n", "\t", ',', ';', ':', '"', "'", '(', ')', '<', '>', '[', ']', '|'];
                $e = str_replace( $raNonEmailChars, ' ', $r['email'] );
                if( strpos($r['email'], '@') === false || strpos($r['email'], ' ') !== false ) {
                    $this->oApp->oC->AddErrMsg( "Warning: {$r['email']} doesn't look like a valid email address.<br/>" );
                    $ok = false;
                }
            }
        }

        if( $nRows ) {
            $this->oApp->oC->AddUserMsg( "$nRows rows were loaded but not saved yet. Check the information below, fix it as needed, and click Add to save it." );
        }

        return( $raRows );
    }


    private function normalizeLanguage( $lang )
    {
        return( SEEDCore_SmartVal( strtoupper(substr($lang,0,1)), ['E','F','B']) );
    }
}


class MbrEbulletinDB extends Keyframe_NamedRelations
{
    private $oApp;

    function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;
        parent::__construct( $oApp->kfdb, $oApp->sess->GetUID(), $oApp->logdir );
    }

    function initKfrel( KeyframeDatabase $kfdb, $uid, $logdir )
    {
        $raKfrel = array();

        $def = ["Tables" => [
                    "B" => ["Table" => "{$this->oApp->GetDBName('seeds1')}.bull_list",
                            "Type"  => 'Base',
                            "Fields" => 'Auto'
               ]]];

        $parms = $logdir ? ['logfile'=>$logdir."bulletin.log"] : [];

        $raKfrel['B'] = new Keyframe_Relation( $kfdb, $def, $uid, $parms );

        return( $raKfrel );
    }
}
