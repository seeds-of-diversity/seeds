<?php

/* csci_page
 *
 * Copyright 2010-2024 Seeds of Diversity Canada
 *
 * Show Canadian Seed Catalog Index on public site
 */

include_once(SEEDLIB."sl/QServerRosetta.php");
include_once(SEEDLIB."q/QServerSources.php");
include_once(SEEDLIB."sl/sources/sl_sources_lib.php");

class SLCSCI_Public
{
    private $oApp;
    private $oSLDB;
    private $oTagParser;
    private $oSrcLib;

    function __construct(SEEDAppConsole $oApp)
    {
        $this->oApp = $oApp;
        $this->oSLDB = new SLDBSources($oApp);
        $this->oTagParser = new SEEDTagParser();
        $this->oSrcLib = new SLSourcesLib($oApp);
    }

    function DrawCompanies( $lang )
    /******************************
        Draw the csci companies page - utf-8
     */
    {
        $s = "<h3>Seed Companies in Canada</h3>";

        // $sSortCol = $lang=='EN' ? "S.country,S.name_en" : "S.country,S.name_fr";
        // except the name_fr are mostly blank
        if( ($kfr = $this->oSLDB->GetKFRC('SRC', "_key >= 3 AND country='Canada'", ['sSortCol'=>"name_en"] )) ) {
            while( $kfr->CursorFetch() ) {
                $s .= $this->oSrcLib->DrawCompanyBlock( $kfr, $lang );
            }
        }
        return( SEEDCore_utf8_encode($s) );
    }

    function DrawSpeciesList( string $lang, array $raParms = [] )
    /************************************************************
        Draw the csci species page - utf-8
     */
    {
        $s = "";

        $bIndex = intval(@$raParms['bIndex']);  // true: get Index names; false: get regular names

        $sTemplate = @$raParms['sTemplate'] ?:
                     "<div class='csci_species' style=''><a href='{$this->oApp->PathToSelf()}?psp=[[var:k]]'>[[var:name]] [[ifnot0:\$n|([[var:n]])]]</a></div>";

        $rQ = (new QServerSourceCV($this->oApp))->Cmd('srcSpecies', ['bAllComp'=>true, 'lang'=>$lang, 'outFmt'=>'KeyName', 'opt_spMap'=>'ESF', 'opt_bIndex'=>$bIndex]);
        foreach( $rQ['raOut'] as $k=>$spName ) {
            $this->oTagParser->SetVars( ['k'=>$k, 'name'=>$spName, 'n'=>0] );
            $s .= $this->oTagParser->ProcessTags($sTemplate);
        }

        return( $s );
    }

    function DrawCompaniesCultivars( int $kSp, string $lang, array $raParms = [] )
    /*****************************************************************************
        Draw the csci companies_varieties page - utf-8
     */
    {
        $s = "";
        $raOut = "";

        if( !($sTemplateBlock = @$raParms['sTemplateBlock']) ) {
            $sTemplateBlock = "<div class='slsrc_dcvblock_cv'>[[Var:cv]]</div>"
                             ."<div class='slsrc_dcvblock_companies'>[[Var:companies]]</div>";
        }

        if( $kSp ) {
            // get the given species name
            $rQ = (new QServerRosetta($this->oApp))->Cmd('rosetta-spname',['kSp'=>$kSp, 'lang'=>$lang]);
            $sSpecies = $rQ['sOut'];

            // get SRCCV records for this species
            $rQ = (new QServerSourceCV($this->oApp))->Cmd('srcSrcCvCultivarList', ['kSp'=>$kSp, 'bGetSrcList'=>true]);
            $raOut = $rQ['raOut'];
        }


        $s .= "<h3>$sSpecies - Varieties Sold in Canada</h3>";
        if( $raOut ) {
            $raSrcAll = $this->oSLDB->GetListKeyed('SRC', '_key', "");  // all SRC records to look up names and websites

            foreach( $raOut as $ra ) {
                $cv = $ra['P_name']
                     .($ra['raPY'] ? (" / ".implode( " / ", $ra['raPY'])) : "");

                $raCmp = [];
                foreach($ra['raSrc'] as $kSrc) {
                    $sCompany = SEEDCore_utf8_encode(@$raSrcAll[$kSrc]['name_en']);
                    $sCompany = "<nobr>{$sCompany}</nobr>";
                    $raCmp[$sCompany] = ($web = @$raSrcAll[$kSrc]['web']) ? "<a href='https://$web' target='sl_source_company'>$sCompany</a>" : $sCompany;
                }
                ksort($raCmp);    // key allows sorting by company name

                $s .= $this->dcv_block( $cv, $raCmp, $sTemplateBlock );
            }
        } else {
            $s .= "<p>No records</p>";
        }

        return( $s );
    }

    private function dcv_block( $cv, $raCompanies, $sTemplateBlock )
    /***************************************************************
     */
    {
        $oTagParser = new SEEDTagParser();
        $oTagParser->SetVars( ['cv'=>$cv,
                               // this puts two spaces between names, but allows line breaking to happen without inserting leading spaces
                               'companies' => implode(",&nbsp; ", $raCompanies)] );
        $s = $oTagParser->ProcessTags( $sTemplateBlock );
        return( $s );
    }
}
