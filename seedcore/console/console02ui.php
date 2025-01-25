<?php

include_once( SEEDCORE."SEEDTableSheets.php" );

class Console02UI
{
    static function DownloadUpload( SEEDAppConsole $oApp, $raParms )
    /***************************************************************
        Draw a UI to download a spreadsheet and upload it again.
            Download needs a link that makes a spreadsheet, and appropriate filtering controls.
            Upload needs a SEEDTable definition, and a function to facilitate the upload process (like a Stepper).
     */
    {
        $s = "";

        $bEnableDownload = !@$raParms['download_disable'];
        $bEnableUpload   = !@$raParms['upload_disable'];

        if( $bEnableDownload ) {
            $s .= "<h4 class='DownloadBodyHeading'>Download a spreadsheet of {$raParms['label']}</h4>"
                 ."<div style='padding:1em'>"
                     ."<form method='post' action='{$raParms['downloadaction']}'>"
                     .$raParms['downloadctrl']
                     ."<input type='submit' value='Download'/>"
                     ."</form>"
                 ."</div>";
        }

        if( $bEnableDownload && $bEnableUpload ) {
            $s .= "<hr/>";
        }

        if( $bEnableUpload ) {
            $sCSVSelect = @$raParms['enable_csv_upload']
                            ? "<tr><td style='padding-top:5px'><select name='upfile-format'>
                                   <option value='xls'>Spreadsheet file</option>
                                   <option value='csv-utf8'>CSV in utf8</option>
                                   <option value='csv-win1252'>CSV in win1252</option>
                                   </td>
                               </tr>"
                            : "<input type='hidden' name='upfile-format' value='xls'/>";

            $s .= "<h4 class='DownloadBodyHeading'>Upload a spreadsheet of {$raParms['label']}</h4>"
                  ."<div style='border:1px solid #aaa;padding:10px;margin:20px;width:500px'>"
                     ."<style>"
                     .".console01_instructions table th {font-size:10pt}"
                     ."</style>"
                     ."<div class='console01_instructions' style='margin:0px 0px 15px 15px;'>"
                     ."<p>The first row of the spreadsheet must have these names (in any order).".SEEDTableSheets::SampleHead($raParms['seedTableDef'])."</p>"
                     ."</div>"
                     ."<form action='{$raParms['uploadaction']}' method='post' enctype='multipart/form-data'>"
                     ."<input type='hidden' name='MAX_FILE_SIZE' value='10000000' />"
                     .@$raParms['uploadctrl']
                     ."<table style='margin-left:20px' border='0'>"
                     .$sCSVSelect
                     ."<tr>"
                     ."<td><input style='display:inline' type='file' name='upfile'/></td>"
                     ."<td><input style='display:inline' type='submit' name='action' value='Upload' style='float:right'/></td>"
                     ."</tr></table>"
                     .@$raParms['uploadctrlbottom']
                     ."</form>"
                 ."</div>";
        }

        return( $s );
    }
}


/* Dropdown to choose an operation (or anything) that is persistent in a given SVA

   raOps = [label => opcode, ...]
 */
class Console02UI_OperationPicker
{
    private $opName;
    private $raOps;
    private $currVal;  // current value chosen from raOps

    function __construct( string $opName, SEEDSessionVarAccessor $oSVA, array $raOps )
    {
        $this->opName = $opName;
        $this->raOps = $raOps;
        $this->currVal = $oSVA->SmartGPC($opName, $raOps);    // default is the first value in raOps
    }

    function Value()    { return($this->currVal); }

    function DrawDropdown( string $cid = 'Plain' )
    {
        $oForm = new SEEDCoreForm($cid);
        $oForm->SetValue($this->opName, $this->currVal );
        $s = "<form>".$oForm->Select($this->opName, $this->raOps, "", ['attrs'=>"onchange='submit()'"] )."</form>";

        return( $s );
    }
}
