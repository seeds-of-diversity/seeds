<?php

/* mse-edit tabset for edit tab
 *
 * Copyright (c) 2018-2023 Seeds of Diversity
 *
 */

class MSEEditAppTabOffice
{
    private $oMSDLib;

    function __construct( MSDLib $oMSDLib )
    {
        $this->oMSDLib = $oMSDLib;
    }

    function DrawControl()
    {
        return( "" );
    }

    function DrawContent()
    {
        $s = "<p>Don't use these tools unless you know what you're doing, AND you've backed up the seed exchange database first!</p>";
        $sSpRenameResult = "";

        $oForm = new SEEDCoreForm("A");
        $oForm->Load();


        /* Bulk Species Rename
         */
        if( ($kSpFrom = $oForm->ValueInt('spRenameFrom')) && ($kSpTo = $oForm->ValueInt('spRenameTo')) ) {
            list($ok,$sMsg) = $this->oMSDLib->BulkRenameSpecies( $kSpFrom, $kSpTo );
            $sSpRenameResult = "<div class='alert ".($ok ? 'alert-success' : 'alert-danger')."'>$sMsg</div>";
        }
        $oForm->Clear();
        $raOptsSp = ['-- Species --'=>0] + $this->oMSDLib->GetSpeciesSelectOpts();
        $s .= "<div style='border:1px solid #aaa;background-color:#e0e0e0;padding:15px'>"
            ."<h3>Bulk Species Rename</h3>"
            .$sSpRenameResult
            ."<form method='post'>"
            ."<p>Rename all {$oForm->Select('spRenameFrom', $raOptsSp)} to {$oForm->Select('spRenameTo', $raOptsSp)} <input type='submit' value='Rename'/></p>"
            ."</form></div>";


        return( $s );
    }
}

