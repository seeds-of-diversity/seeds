<?php

/* MyProjects app
 *
 * Copyright (c) 2024-2026 Seeds of Diversity Canada
 */

class ProjectsTabOffice
{
    private $oCTS;
    private $oP;
    private $oMbr;
    private $oSLDB;

    function __construct( ProjectsCommon $oP, MyConsole02TabSet $oCTS )
    {
        $this->oCTS = $oCTS;
        $this->oP = $oP;
        $this->oMbr = new Mbr_Contacts($this->oP->oApp);
        $this->oSLDB = new SLDBProfile($this->oP->oApp);
    }

    function Init()
    {
    }

    function ControlDraw()
    {
        $s = "";

        return($s);
    }

    function ContentDraw()
    {
        $s = "";

        $oForm = new SEEDCoreFormSVA($this->oCTS->TabSetGetSVACurrentTab('main'), 'A',
                                     ['fields'=>['all'          =>['control'=>'checkbox'],
                                                 'ground-cherry'=>['control'=>'checkbox'],
                                                 'tomato'       =>['control'=>'checkbox'],
                                                 'bean'         =>['control'=>'checkbox'],
                                     ]]);
        $oForm->Update();
        // years from current..2024
        $raYears = []; for($y=date('Y'); $y >= 2024; --$y) $raYears["{$y}"] = $y;
        $s .= "<div style='display:inline-block;border:1px solid #aaa;border-radius:5px;padding:1em'><form>
               <p>".$oForm->Select('mode', ["CGO growers"=>'cgo_growers', "Core growers"=>'core_growers', "Profile Observations"=>'desc_obs'])."</p>
               <p>".$oForm->Select('year', $raYears)."</p>
               <p>".$oForm->Text('workflow','',['size'=>4])." min workflow</p>"

/*
               <p>".$oForm->Checkbox('all', "All")."</p>
               <p>".$oForm->Checkbox('ground-cherry', "Ground cherry")."</p>
               <p>".$oForm->Checkbox('tomato', "Tomato")."</p>
               <p>".$oForm->Checkbox('bean', "Bean")."</p>
*/
             ."<p><input type='submit' value='Show'/></p>
               </form></div>"

             /* Spreadsheet button
              */
             ."<div style='display:inline-block;vertical-align:top;padding-left:1em'>
                   <a href='?xlsx=1' target='_blank'><img src='https://seeds.ca/w/std/img/dr/xls.png' style='height:30px'/></a>
               </div>"

             /* google sheet controls
              */
             ."<div style='display:inline-block;vertical-align:top;padding-left:1em'border:1px solid #aaa;border-radius:5px'><form>
               {$oForm->Text('idSpreadsheet', "", ['size'=>50, 'placeholder'=>"spreadsheet id"])}<br/>
               {$oForm->Text('nameSheet',     "", ['size'=>30, 'placeholder'=>"sheet name"])}<br/>
               <input type='submit' name='cmd_g' value='Write to sheet'/> &nbsp;&nbsp;&nbsp; <input type='submit' name='cmd_g' value='Read workflow from sheet'/>
               </form></div>";

        switch($oForm->Value('mode')) {
            case 'cgo_growers':     $s .= $this->drawCGOGrowers($oForm);    break;
            case 'core_growers':    $s .= $this->drawCoreGrowers($oForm);   break;
            case 'desc_obs':        $s .= $this->drawDescObs($oForm);       break;
        }

        return( $s );
    }

    private function drawCGOGrowers( SEEDCoreForm $oForm )
    {
        $bShow = true;
        $raProj = [];
        $s = "";

        if( $oForm->Value('all') ) {
            $bShow = true;
        } else {
            foreach( ['ground-cherry','tomato','bean'] as $proj ) {
                if( $oForm->Value($proj) ) {
                    $bShow = true;
                    $raProj[] = $proj;
                }
            }
        }

        if( !$bShow )  goto done;

        /* Integrity tests
         */
        if( ($n = $this->oSLDB->GetCount('VI', "year='{$oForm->ValueDB('year')}' AND projcode<>'core' AND projcode NOT LIKE 'cgo_%'")) ) {
            $this->oP->oApp->oC->AddErrMsg("$n records have invalid projcode<br/>");
        }

        if( ($raTest = $this->oP->oApp->kfdb->QueryRowsRA(
                "SELECT V1.fk_mbr_contacts as kMbr , V1._key FROM {$this->oP->oApp->DBName('seeds1')}.sl_varinst V1, {$this->oP->oApp->DBName('seeds1')}.sl_varinst V2
                 WHERE V1._status=0 AND V2._status=0 AND
                       V1.year={$oForm->ValueDB('year')} AND V2.year={$oForm->ValueDB('year')} AND
                       V1.fk_mbr_contacts=V2.fk_mbr_contacts AND
                       V1.projcode='core' AND
                       V1.projcode <> V2.projcode")) )
        {
            $this->oP->oApp->oC->AddErrMsg(count($raTest)." core growers are in cgo projects: ".SEEDCore_ArrayExpandRows($raTest, "[[kMbr]] ")."<br/>");
        }

        $sCond = "year='{$oForm->ValueDB('year')}' AND projcode LIKE 'cgo_%'"
                .(($iWorkflow = $oForm->ValueInt('workflow')) ? " AND workflow >= $iWorkflow" : "")
                .($raProj ? (" AND psp in ('".implode("','", $raProj)."')") : "");

        $raMbr = [];        // one row per member with projects collapsed
        $raMbrProj = [];    // one row per project with member metadata
        foreach( $this->oSLDB->GetList('VI', $sCond) as $raVI ) {
            $kMbr = $raVI['fk_mbr_contacts'];

            if( !isset($raMbr[$kMbr]) ) {
                $ra = $this->oMbr->oDB->GetRecordVals('M', $kMbr);
                $raMbr[$kMbr] = ['member_name' => $ra ? $this->oMbr->GetContactNameFromMbrRA($ra) : "",
                                 'member_email'=> @$ra['email'],
                                 'ground-cherry' => '',
                                 'tomato' => '',
                                 'bean' => '',
                ];
            }

// use ComputeVarInstName - needs kfrVI instead of raVI
            $kfrLot = $raVI['fk_sl_inventory'] ? $this->oSLDB->GetKFR('IxAxP', $raVI['fk_sl_inventory']) : null;
            $psp = $kfrLot ? $kfrLot->Value('P_psp') : $raVI['psp'];

            switch( $psp ) {
                case 'ground-cherry':
                    $raMbr[$kMbr]['ground-cherry'] = 1;
                    break;
                case 'tomato':
                case 'bean':
                    if( $kfrLot ) {
                        $raMbr[$kMbr][$psp] .= "{$kfrLot->Value('P_name')} ({$kfrLot->Value('inv_number')}) ";
                    }
                    break;
            }

            /* Store this VI with the metadata computed for raMbr array
             */
            $raMbrProj[] = ['kVI'=>$raVI['_key'], 'kMbr'=>$kMbr, 'member_name'=>$raMbr[$kMbr]['member_name'],'member_email'=>$raMbr[$kMbr]['member_email'],
                            'psp'=>$psp,'cultivar'=>(@$raMbr[$kMbr][$psp] ?:""),'workflow'=>$raVI['workflow'] ];
        }

        if( SEEDInput_Int('xlsx') ) {
            // output as a spreadsheet
            include_once( SEEDCORE."SEEDXLSX.php" );

            $title = "Seeds of Diversity CGO Projects {$oForm->Value('year')}";
            $oXLSX = new SEEDXlsWrite( ['title'=> $title,
                                        'filename'=>$title.'.xlsx',
                                        'creator'=>$this->oP->oApp->sess->GetName(),
                                        'author'=>$this->oP->oApp->sess->GetName()] );

            $raKeys = ['member_name','member_email','ground-cherry','tomato','bean'];

            $oXLSX->WriteHeader( 0, array_merge(['member'],$raKeys));

            $iRow = 2;  // rows are origin-1 so this is the row below the header
            foreach( $raMbr as $kMbr => $ra ) {
                // reorder the $ra values to the same order as $raKeys
                $oXLSX->WriteRow( 0, $iRow++, SEEDCore_utf8_encode( array_merge([$kMbr],array_replace(array_fill_keys($raKeys,''), array_intersect_key($ra,array_fill_keys($raKeys,''))))) );
            }

            $oXLSX->OutputSpreadsheet();
            exit;
        }

        if( ($cmd_g = SEEDInput_Str('cmd_g')) ) {
            include_once(SEEDLIB."google/GoogleSheets.php");

            $idSpreadsheet = $oForm->Value('idSpreadsheet');
            $nameSheet = $oForm->Value('nameSheet');
            $oGoogleSheet = new SEEDGoogleSheets_NamedColumns(
                        ['appName' => 'My PHP App',
                         'authConfigFname' => SEEDCONFIG_DIR."sod-public-outreach-info-e36071bac3b1.json",
                         'idSpreadsheet' => $idSpreadsheet] );
            switch($cmd_g) {
                case 'Write to sheet':
                    $raG[] = ['kVI','member','member_name','member_email','psp','cultivar','workflow'];
                    $nBottom = count($raMbrProj)+1;
                    foreach($raMbrProj as $ra) {
                        $raG[] = [$ra['kVI'],$ra['kMbr'],$ra['member_name']??"",$ra['member_email']??"",$ra['psp'],$ra['cultivar'],$ra['workflow']];
                    }
                    $raG = SEEDCore_utf8_encode($raG);
                    $oGoogleSheet->WriteValues($nameSheet."!A1:G{$nBottom}", $raG);
                    $this->oP->oApp->oC->AddUserMsg("Wrote table to google sheet");
                    break;

                case 'Read workflow from sheet':
                    $raG = $oGoogleSheet->GetRowsWithNamedColumns($nameSheet);
                    foreach($raG as $ra) {
                        if( ($kfrVI = $this->oSLDB->GetKFR('VI',$ra['kVI'])) &&
                            $kfrVI->Value('workflow') != $ra['workflow'] )
                        {
                            $w = $kfrVI->Value('workflow');
                            $kfrVI->SetValue('workflow',$ra['workflow']);
                            $kfrVI->PutDBRow();
                            $this->oP->oApp->oC->AddUserMsg("Changed workflow from {$w} to {$ra['workflow']} for {$ra['member_name']} {$ra['psp']}<br/>");
                        }
                    }
                    break;
            }
        }

        $s .= "<style>.myproj_table td, .myproj_table th {padding:0 5px}</style>
               <table class='myproj_table' style=''><tr><th>Member</th><th>email</th><th>Ground cherry</th><th>Tomato</th><th>Bean</th></tr>";
        foreach( $raMbr as $kMbr => $ra ) {
            $s .= "<tr><td>{$ra['member_name']} ({$kMbr})</td><td>{$ra['member_email']}</td>
                       <td>{$ra['ground-cherry']}</td><td>{$ra['tomato']}</td><td>{$ra['bean']}</td></tr>";
        }
        $s .= "</table>";

        done:
        return($s);
    }

    private function drawCoreGrowers( SEEDCoreForm $oForm )
    {
        $s = "";

        /* We want to show all seeds being grown by Core growers, but those aren't necessarily all Core seeds. e.g. some will be cgo_gc
         * Get distinct kMbr of all the growers doing Core projects of the chosen workflow,
         * then get all their projects regardless of projcode.
         */
        $sCond = "year='{$oForm->ValueDB('year')}'"
                .(($iWorkflow = $oForm->ValueInt('workflow')) ? " AND workflow >= $iWorkflow" : "");

        // growers doing projects with the basic constraints where at least one is a Core project - this returns [kMbr1, kMbr2, kMbr2, ...]
        $raMbr = $this->oSLDB->Get1List('VI', 'fk_mbr_contacts', $sCond." AND projcode='core'", ['sGroupAliases'=>"fk_mbr_contacts"]);
        // all projects with the basic constraints, for those growers, regardless of projcode
        $raVIRows = $this->oSLDB->GetList('VI', $sCond." AND fk_mbr_contacts IN (".implode(',',$raMbr).")", ['sSortCol'=>"fk_mbr_contacts"]);

// make VIxM_IxAxP_P2
        $raOut = [];
        foreach( $raVIRows as $raVI ) {
            $kMbr = $raVI['fk_mbr_contacts'];
            $raM = $this->oMbr->oDB->GetRecordVals('M', $kMbr);

// use ComputeVarInstName - needs kfrVI instead of raVI
            $kfrLot = $raVI['fk_sl_inventory'] ? $this->oSLDB->GetKFR('IxAxP', $raVI['fk_sl_inventory']) : null;

            $raOut[] = ['member' => $kMbr,
                        'member_name'  => ($raM ? $this->oMbr->GetContactNameFromMbrRA($raM) : ""),
                        'member_email' => $raM['email'],
                        'projcode'     => $raVI['projcode'],
                        'psp'          => $kfrLot ? $kfrLot->Value('P_psp')  : ($raVI['psp'] ?: $raVI['osp']),
                        'pname'        => $kfrLot ? $kfrLot->Value('P_name') : ($raVI['pname'] ?: $raVI['oname']),
                        'lot'          => $kfrLot ? $kfrLot->Value('inv_number') : ""
                       ];
        }

        if( SEEDInput_Int('xlsx') ) {
            // output as a spreadsheet
            include_once( SEEDCORE."SEEDXLSX.php" );

            $title = "Seeds of Diversity Core Projects {$oForm->Value('year')}";
            $oXLSX = new SEEDXlsWrite( ['title'=> $title,
                                        'filename'=>$title.'.xlsx',
                                        'creator'=>$this->oP->oApp->sess->GetName(),
                                        'author'=>$this->oP->oApp->sess->GetName()] );

            $raKeys = ['member','member_name','member_email','projcode','psp','pname','lot'];

            $oXLSX->WriteHeader( 0, $raKeys);

            $iRow = 2;  // rows are origin-1 so this is the row below the header
            foreach( $raOut as $ra ) {
                // reorder the $ra values to the same order as $raKeys
                $oXLSX->WriteRow( 0, $iRow++, SEEDCore_utf8_encode( array_replace(array_fill_keys($raKeys,''), array_intersect_key($ra,array_fill_keys($raKeys,'')))) );
            }

            $oXLSX->OutputSpreadsheet();
            exit;
        }

        $s .= "<style>.myproj_table td, .myproj_table th {padding:0 5px}</style>
               <table class='myproj_table' style=''><tr><th>Member</th><th>email</th><th>project</th><th>psp</th><th>pname</th><th>Lot</th></tr>";
        foreach( $raOut as $ra ) {
            $s .= "<tr><td>{$ra['member_name']} ({$ra['member']})</td><td>{$ra['member_email']}</td>
                       <td>{$ra['projcode']}</td><td>{$ra['psp']}</td><td>{$ra['pname']}</td><td>{$ra['lot']}</td></tr>";
        }
        $s .= "</table>";

        done:
        return($s);
    }

    private function drawDescObs( SEEDCoreForm $oForm )
    {
        $bShow = false;
        $raProj = [];
        $s = "";

        // you can only select one of these
        $psp = '';
        if( $oForm->Value('bean') )   $psp = 'bean';
        if( $oForm->Value('tomato') ) $psp = 'tomato';
        if( $oForm->Value('ground-cherry') ) $psp = 'ground-cherry';

        if( !$psp) {
            $s .= "<b>Choose a species</b>";
            goto done;
        }

        $year = $oForm->ValueDB('year');
        $sCond = "year='{$year}' AND VI.psp='{$psp}'";

        $raMbr = [];
        $raVI = [];
        $raDescKeys = [];
        foreach( $this->oSLDB->GetList('VOxVI', $sCond) as $vo ) {
            $kVI = $vo['fk_sl_varinst'];
            if( !isset($raVI[$kVI]) ) {
                $raVI[$kVI]['VO'] = [];

                $raVI[$kVI]['psp'] = $psp;
                $raVI[$kVI]['year'] = $year;
                $raVI[$kVI]['kMbr'] = $vo['VI_fk_mbr_contacts'];

                if( ($raMbr = $this->oMbr->GetBasicValues($vo['VI_fk_mbr_contacts'])) ) {
                    $raVI[$kVI]['member_province'] = $raMbr['province'];
                    $raVI[$kVI]['member_email'] = $raMbr['email'];
                } else {
                    $raVI[$kVI]['member_province'] = "";
                    $raVI[$kVI]['member_email'] = "";
                }

                ($cv = $vo['VI_pname'])
                or
                ($cv = $vo['VI_oname'])
                or
                ($vo['VI_fk_sl_pcv'] && ($cv = $this->oSLDB->GetRecordVal1('P', $vo['VI_fk_sl_pcv'], 'pname')))
                or
                ($vo['VI_fk_sl_inventory'] && ($cv = $this->oSLDB->GetRecordVal1('IxAxP', $vo['VI_fk_sl_inventory'], 'P_pname')));

                $raVI[$kVI]['cv'] = $cv;
            }
            $raVI[$kVI]['VO-record'][$vo['k']] = $vo['v'];
            $raDescKeys[$vo['k']] = 1;
        }

        // $raVI[]['VO'] is an array of descObs_k => descObs_v for each varinst : an arbitrary set of those in arbitrary order
        // Using $raDescKeys which is a set of all descObs_k transform each ['VO'] to an identical format filling unknown values with ''
        $raDescKeys = array_keys($raDescKeys);
        foreach( $raVI as $kVI => $ra ) {
            $raVI[$kVI]['VO-expanded'] = array_replace(array_fill_keys($raDescKeys,''), array_intersect_key($ra['VO-record'],array_fill_keys($raDescKeys,'')));
        }

        if( SEEDInput_Int('xlsx') ) {
            // output as a spreadsheet
            include_once( SEEDCORE."SEEDXLSX.php" );

            $title = "Seeds of Diversity Projects {$oForm->Value('year')}";
            $oXLSX = new SEEDXlsWrite( ['title'=> $title,
                                        'filename'=>$title.'.xlsx',
                                        'creator'=>$this->oP->oApp->sess->GetName(),
                                        'author'=>$this->oP->oApp->sess->GetName()] );

            $raKeys = ['member_name','member_email','member_province','year','species','cultivar'];

            $oXLSX->WriteHeader( 0, array_merge(['member'],$raKeys, $raDescKeys));

            $iRow = 2;  // rows are origin-1 so this is the row below the header
            foreach( $raVI as $k => $ra ) {
                // reorder the $ra values to the same order as $raKeys
                $oXLSX->WriteRow( 0, $iRow++, SEEDCore_utf8_encode(
                    array_merge( [$ra['kMbr'], '', $ra['member_email'], $ra['member_province'], $ra['year'], $ra['psp'], $ra['cv']], $ra['VO-expanded'] )) );
            }

            $oXLSX->OutputSpreadsheet();
            exit;
        }

        $s .= "<style>.myproj_table td, .myproj_table th {padding:0 5px}</style>
               <table class='myproj_table' style=''><tr><th>Member</th><th>email</th><th>province</th><th>Species</th><th>Cultivar</th><th>Profile</th></tr>";
        foreach( $raVI as $kVI => $ra ) {
            $s .= "<tr><td>{$ra['kMbr']}</td><td>{$ra['member_email']}</td><td>{$ra['member_province']}</td><td>{$ra['psp']}</td><td>{$ra['cv']}</td>
                       <td>".SEEDCore_ArrayExpandSeries($ra['VO-record'], "[[k]]=[[v]], ")."</td></tr>";
        }
        $s .= "</table>";

        done:
        return($s);
    }
}
