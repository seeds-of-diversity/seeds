<?php

include_once( SEEDCORE."SEEDTableSheets.php" );

class Console02UI
{
    static function DownloadUpload( SEEDAppConsole $oApp, $raParms )
    /***************************************************************
        Draw a UI to download a spreadsheet and upload it again.
            Download needs a link that makes a spreadsheet, and appropriate filtering controls.
            Upload needs a SEEDTable definition, and a function to facilitate the upload process (like a Stepper).

        label                : the name of the thing that's in each row

        download_disable     : don't show the download section
        download_action      : <form action=' this '>
        download_ctrl        : ctrl string to put within form
        download_buttonlabel : label for Download button
        download_noform      : don't render <form> element - the caller will do that

        upload_disable       : don't show the upload section
        upload_action        : <form action=' this '>
        upload_ctrl          : ctrl string to put within form
        upload_buttonlabel   : label for Upload button
        //upload_noform        : don't render <form> element - the caller will do that
        upload_nobutton      : don't render the [Upload] button - the caller will do that

        enable_csv_upload    : render options for csv charset
     */
    {
        $s = "";

        $bEnableDownload = !@$raParms['download_disable'];
        $bEnableUpload   = !@$raParms['upload_disable'];

        $sDownloadButtonLabel = @$raParms['download_buttonlabel'] ?: "Download";
        $sUploadButtonLabel   = @$raParms['upload_buttonlabel']   ?: "Upload";

        if( $bEnableDownload ) {
            $sInner = @$raParms['downloadctrl']."<input type='submit' value='$sDownloadButtonLabel'/>";
            if( !@$raParms['download_noform'] )  $sInner = "<form method='post' action='{$raParms['downloadaction']}'>$sInner</form>";

            $s = "<h4 class='DownloadBodyHeading'>Download a spreadsheet of {$raParms['label']}</h4>
                   <div style='padding:1em'>$sInner</div>";
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

            $s .= "<h4 class='DownloadBodyHeading'>Upload a spreadsheet of {$raParms['label']}</h4>
                   <style>
                   .console02_instructions table th {font-size:10pt}
                   </style>
                   <form action='{$raParms['uploadaction']}' method='post' enctype='multipart/form-data'>
                   <div style='border:1px solid #aaa;padding:10px;margin:20px;width:500px'>
                       <div class='console02_instructions' style='margin:0px 0px 15px 15px;'>
                           <p>The first row of the spreadsheet must have these names (in any order).".SEEDTableSheets::SampleHead($raParms['seedTableDef'])."</p>
                       </div>
                       <input type='hidden' name='MAX_FILE_SIZE' value='10000000' />"
                     .@$raParms['uploadctrl']
                     ."<table style='margin-left:20px' border='0'>
                       {$sCSVSelect}
                       <tr>
                       <td><input style='display:inline' type='file' name='upfile'/></td>"
                     .(!@$raParms['upload_nobutton'] ? "<td><input style='display:inline' type='submit' name='action' value='$sUploadButtonLabel' style='float:right'/></td>" : "")
                     ."</tr></table>"
                     .@$raParms['uploadctrlbottom']
                 ."</div>"
                 .@$raParms['uploadctrlbelow']
                 ."</form>";
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
