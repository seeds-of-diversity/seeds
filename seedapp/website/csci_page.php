<?php

/* csci_page
 *
 * Copyright 2010-2024 Seeds of Diversity Canada
 *
 * Show Canadian Seed Catalog Index on public site
 */

include_once(SEEDLIB."sl/QServerRosetta.php");
include_once(SEEDLIB."q/QServerSources.php");

class SLCSCI_Public
{
    private $oApp;
    private $oSLDB;

    function __construct(SEEDAppConsole $oApp)
    {
        $this->oApp = $oApp;
        $this->oSLDB = new SLDBSources($oApp);
    }

    function DrawCompaniesCultivars( int $kSp, string $lang, array $raParms = [] )
    /*****************************************************************************
        Returns utf-8 csci_companies_varieties page
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
