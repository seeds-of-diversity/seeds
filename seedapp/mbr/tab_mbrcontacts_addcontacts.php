<?php

class MbrContactsTabAddContacts // implements Console02TabSet_Worker
{
    private $oApp;

    // these are the fields that are copied from spreadsheet to db
    private $fldsAdd = ['firstname','lastname','company','address','city','province','postcode','email'];

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oMbrContacts = new Mbr_Contacts($oApp);
    }

    function Init()
    {

    }

    function ControlDraw()
    {
        $s = "";

        return( $s );
    }

    function ContentDraw()
    {
        $s = "<h4>Add Contacts from Spreadsheet</h4>";

        if( SEEDInput_Str('cmd') == 'uploadtest' ) { $s .= $this->upload( true )."<br/><hr style='border-color:#aaa'/>"; }
        if( SEEDInput_Str('cmd') == 'upload' )     { $s .= $this->upload( false )."<br/><hr style='border-color:#aaa'/>"; }

        $s .= "<div class='ctrl'>"
        ."<p>Upload a spreadsheet containing basic contact columns e.g. firstname, lastname, email, address, email, etc</p>"
        ."<div class='row'>"
            ."<div class='col-sm-4'>".$this->uploadForm(true)."</div>"
            ."<div class='col-sm-4'>".$this->uploadForm(false)."</div>"
        ."</div>"
        ."</div>";

        return( $s );
    }

    private function uploadForm( $bTest )
    {
        return(
            "<form action='".$this->oApp->PathToSelf()."' method='post' enctype='multipart/form-data'>"
            ."<input type='hidden' name='MAX_FILE_SIZE' value='10000000' />"
            ."<input type='file' name='uploadfile'/>"
            ."<input type='hidden' name='cmd' value='".($bTest ? 'uploadtest' : 'upload')."'/>"
            ."<input type='submit' value='".($bTest ? 'Upload and Test' : 'Upload and Commit')."'/>"
            ."</form>" );
    }

    private function upload( $bTest )
    {
        include_once( SEEDCORE."SEEDTableSheets.php" );

        $s = "";

        $parms = [ // parms for SEEDTableSheetsFile constructor
                   'raSEEDTableSheetsFileParms' => [],
                   // parms for LoadFromFile()
                   'raSEEDTableSheetsLoadParms' => ['fmt' => 'xls',
                                                    'charset-file' => 'utf-8',
                                                    'charset-sheet' => 'Windows-1252',
                                                    //'sheets' => [1]                 // just the first sheet
                                                   ]
                 ];

        list($oSheets,$sErr) = SEEDTableSheets_LoadFromUploadedFile( 'uploadfile', $parms );
        if( !$oSheets ) {
            $s .= $sErr;
            goto done;
        }

        $sheetname = $oSheets->GetSheetList()[0];
        $raRows = $oSheets->GetSheet($sheetname);


        /* Test the upload
         */
        list($bFatal,$sTest) = $this->testUpload( $raRows );
        $s .= $sTest;

        if( $bTest || $bFatal ) goto done;


        /* Commit the upload to the mbr_contacts table
         */
        $s .= "<br/><hr style='border-color:#aaa'/>"
             ."<h4>Committing the Upload</h4>".$this->commitUpload( $raRows );

        done:
        return( $s );
    }

    private function testUpload( $raRows )
    {
        $bFatal = false;
        $sTest = "";
        $keys = [];

        if( !count($raRows) ) {
            $sTest = "<div class='alert alert-danger'>Empty spreadsheet</div>";
            $bFatal = true;
            goto done;
        }

        $keys = array_keys($raRows[0]);

        $sTest = "<div class='alert'>".count($raRows)." rows found with keys: <strong>".implode(', ', $keys)."</strong><br/><br/>"
                ."Supported: <strong>".implode(", ", $this->fldsAdd)."</strong></div>";

        if( !in_array('firstname',$keys) || !in_array('lastname',$keys) || !in_array('email',$keys) ) {
            $sTest .= "<div class='alert alert-danger'>Columns must include <strong>firstname, lastname, email</strong> even if they're blank</div>";
            $bFatal = true;
            goto done;
        }
        $sDupEmails = "";
        $sSumTable = "<tr>".SEEDCore_ArrayExpandSeries($this->fldsAdd,"<th>[[]]</th>")."</tr>";
        foreach( $raRows as $ra ) {
            if( $ra['email'] && ($m = $this->oMbrContacts->GetAllValues($ra['email']) ) ) {
                $sDupEmails .= $ra['email']." ";
            } else {
                $sSumTable .= "<tr>";
                foreach( $this->fldsAdd as $fld ) {
                    $sSumTable .= "<td>".@$ra[$fld]."</td>";
                }
                $sSumTable .= "</tr>";
            }
        }
        if( $sDupEmails ) {
            $sTest .= "<div class='alert alert-warning'>The following emails already exist. These rows will be discarded. <strong>$sDupEmails</strong></div>";
        }
        $sTest .= "<div class='alert alert-success'>These will be added <table border='1' style='margin-top:10px'>$sSumTable</table></div>";

        $kMbrNext = $this->oApp->kfdb->Query1("SELECT MAX(_key) FROM {$this->oApp->GetDBName('seeds2')}.mbr_contacts WHERE _key<1000000" ) + 1;
        $sTest .= "<div class='alert'>The next contact key will be $kMbrNext</div>";

        done:
        return( [$bFatal,$sTest] );
    }

    private function commitUpload( $raRows )
    {
        $s = "";

        $keys = array_keys($raRows[0]);

        $sYes = $sNo = "";
        $nAdded = 0;
        foreach( $raRows as $ra ) {
            if( $ra['email'] && ($m = $this->oMbrContacts->GetAllValues($ra['email']) ) ) {
                $sNo .= "Skipping {$ra['email']}<br/>";
                continue;
            }

            $kfr = $this->oMbrContacts->oDB->KFRel('M')->CreateRecord();
            foreach( $this->fldsAdd as $fld ) {
                $kfr->SetValue( $fld, @$ra[$fld] );
            }
            if( $kfr->PutDBRow() ) {
                $sYes .= "Added {$kfr->Key()} {$ra['firstname']} {$ra['lastname']} {$ra['email']} <br/>";
                ++$nAdded;
            } else {
                $sNo .= "Failed to add {$ra['email']}<br/>";
            }
        }

        $s .= "<div class='alert alert-warning'>$sNo</div>"
             ."<div class='alert alert-success'>Added $nAdded contacts<br/><br/>$sYes</div>";

        return( $s );
    }

}
