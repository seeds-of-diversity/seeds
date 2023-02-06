<?php

/* msdadmin
 *
 * Copyright (c) 2018-2023 Seeds of Diversity
 *
 * Office admin of Member Seed Directory
 */


class MSDOfficeEditTab
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

class MSDAdminTab
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
        $s = "<style>"
            ."h4         { font-weight: bold }"
            ."p          { margin-left:30px }"
            ."div.indent { margin-left:60px }"
            ."</style>";

        if( !$this->oMSDLib->PermOfficeW() )  goto done;

        $Y = date('Y');     // typically better than oMSDLib->GetCurrYear() unless you want a forward-looking date in late fall

        /* Show statistics box
         */
        $oMSDQ = new MSDQ($this->oMSDLib->oApp, [] );
        $raQStats = $oMSDQ->Cmd( 'msd-getStats', [] );
        $s .= "<div style='float:right;margin:10px;padding:10px;border:1px solid #aaa'>"
             ."Active growers: {$raQStats['raOut']['nGrowersActive']}<br/>"
             ."Skipped growers: {$raQStats['raOut']['nGrowersSkipped']}<br/>"
             ."Deleted growers: {$raQStats['raOut']['nGrowersDeleted']}<br/>"
             ."<br/>"
             ."Active seed offers: {$raQStats['raOut']['nSeedsActive']}<br/>"
             ."Skipped seed offers: {$raQStats['raOut']['nSeedsSkipped']}<br/>"
             ."Deleted seed offers: {$raQStats['raOut']['nSeedsDeleted']}<br/>"
             ."<br/>"
             ."Species: {$raQStats['raOut']['nSpecies']}<br/>"
             ."Varieties: {$raQStats['raOut']['nVarieties']}<br/>"
             ."</div>";


        $s .= "<h4>Printed Directory</h4>"
             ."<p><a href='?doReport=JanGrowers' target='_blank'>Grower list</a></p>"
             ."<p><a href='?doReport=JanSeeds'            target='_blank'>Seeds list - everything except tomatoes</a></p>"
             ."<p><a href='?doReport=JanSeeds&doTomato=1' target='_blank'>Seeds list - all tomatoes sorted by variety</a></p>";

        $s .= "<h4>Packages to Send to Growers</strong></h4>"
             ."<p><a href='?doReport=SeptGrowers' target='_blank'>Grower info sheets - all growers</a></p>"
             ."<p><a href='?doReport=SeptGrowers&noemail=1' target='_blank'>Grower info sheets - those without email addresses</a></p>"
             ."<p><a href='?doReport=SeptSeeds' target='_blank'>Seeds lists per grower - all growers</a></p>"
             ."<p><a href='?doReport=SeptSeeds&noemail=1' target='_blank'>Seeds lists per grower - those without email addresses</a></p>";

        $s .= "<hr/>";

        if( $this->oMSDLib->PermAdmin() ) {
            $s .= "<h4>Admin</h4>";

            $s .= $this->oMSDLib->AdminNormalizeStuff();

            /* Integrity tests
             */
            include_once( SEEDLIB."msd/msdlibIntegrity.php" );
            $oIntegrity = new MSDLibIntegrity( $this->oMSDLib );

            $s .= "<p><a href='?doIntegrityTests=1'>Do integrity tests</a></p>";

            // Draw the Solve This Problem UI if a SEEDProblemSolver link has been clicked
            $s .= $oIntegrity->DrawMSDTestUI();

            // Perform integrity tests and show results
            if( SEEDInput_Int('doIntegrityTests') ) {
                $sTest = "<h4><strong>Integrity Tests</strong></h4>"
                        .$oIntegrity->AdminIntegrityTests()
                        ."<h4><strong>Workflow Tests</strong></h4>"
                        .$oIntegrity->AdminWorkflowTests()
                        ."<h4><strong>Data Tests</strong></h4>"
                        .$oIntegrity->AdminDataTests();

                $sTest .="<h4><strong>Content Tests</strong></h4>"
                        .$oIntegrity->AdminContentTests();

                $s .= "<div class='well'>$sTest</div>";
            }

            $s .= "<p><a href='?archiveCurrentMSD=1'>Archive $Y: replace archive with current msd</a></p>";
            if( SEEDInput_Int('archiveCurrentMSD') ) {
                // delete archive records for $Y, copy current active growers and seeds there and give them year $Y
                list($ok,$s1) = $this->oMSDLib->AdminCopyToArchive( $Y );
                $s .= "<div class='indent'>$s1</div>";
            }

            $s .= "<p><a href='?prepareForDataEntry=1'>Show steps to prepare for data entry in fall</a></p>";
            if( SEEDInput_Int('prepareForDataEntry') ) {
                $s .= "<p><pre style='margin-left:30px'>"
                     ."Check sed_curr_growers._updated for changes within the last few months"
                     ."<br/>SELECT * FROM sed_curr_growers WHERE _updated>'$Y-08-01'"
                     ."<br/>"
                     ."<br/>Check SEEDBasket_Products._updated for changes within the last few months"
                     ."<br/>SELECT * FROM SEEDBasket_Products WHERE prod_type='seeds' AND _updated>'$Y-08-01'"
                     ."<br/>"
                     ."<br/>Make sure last year's MSD is archived"
                     ."<br/>"
                     ."<br/>Clear the flags in the grower table, but manually replace any that were set recently (per above). Seeds uses _updated to detect changes."
                     ."<br/>UPDATE sed_curr_growers SET bDone=0,bDoneMbr=0,bDoneOffice=0,bChanged=0"
                     ."<br/>  todo: bChanged is unnecessary if you use _updated the way seeds do"
                     ."</pre></p>";
            }
        }

        done:
        return( $s );
    }
}