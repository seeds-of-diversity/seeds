<?php

/* search app
 *
 * Copyright 2024 Seeds of Diversity Canada
 *
 * Search for a variety.
 * Report everything we know about a variety.
 */

include_once( SEEDLIB."sl/QServerRosetta.php" );

class SLSearchApp
{
    private $oApp;
    private $oFormSearch;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oFormSearch = new SEEDCoreForm('S');
        $this->oFormSearch->Update();
    }

    function Draw( int $kPcv = 0, string $sSrch = '' )
    {
        if( $kPcv )   $this->oFormSearch->SetValue('cv', $kPcv);
        if( $sSrch )  $this->oFormSearch->SetValue('search', $sSrch);

        $s = $this->Style();

        $sTitle = "";

        // kPcv: you can get a specific cultivar by setting cv=N or search=N
        // sSrch: you can get a search results list by setting search=S, where S is non-numeric
        $kPcv = $this->oFormSearch->ValueInt('cv') ?: $this->oFormSearch->ValueInt('search');
        $sSrch = $this->oFormSearch->Value('search');

        if($kPcv) {
            list($sTitle,$sBody) = $this->drawCultivar($kPcv);
        } else if($sSrch) {
            list($sTitle,$sBody) = $this->drawSearch($sSrch);
        } else {
            $sTitle = "<h2>What Do We Know About Seeds?</h2>";
            $sBody  = "<div style='border:1px solid #ddd;border-radius:5px; padding:15px'>"
                     .$this->defaultBody(false)
                     ."</div>";
        }

        $sTitle .= "<div class='sl_srch_heading_srchctrl'>{$this->drawSearchControl()}</div>";

//        $s .= "<div class='container-fluid'><div class='row'>
//                <div class='col-md-10'><div class='sl_srch_heading'>{$sTitle}</div>{$sBody}</div>
//                <div class='col-md-2'><p>&nbsp;</p>{$this->drawSearchControl()}</div>
//                </div></div>";

        $s .= "<div style='position:relative'>
               <div class='sl_srch_heading'>{$sTitle}</div>
               <div class='sl_srch_body'>{$sBody}</div>
               </div>";

        done:
        return( $s );
    }

    private function drawSearchControl()
    {
        $s = "<form method='get'><div>
              <div style='display:inline-block'>{$this->oFormSearch->Text('search')}</div>
              <button style='display:inline-block' onclick='submit()'>Find</button>
              </div></form>";

        return( $s );
    }

    function Style()
    {
        return( "
            <style>
            .sl_srch_heading          { background-color:#777; font-weight:bold; padding:5px 15px; color:white; }
            .sl_srch_heading h2       { font-size:18pt }
            .sl_srch_heading h3       { font-size:14pt }
            .sl_srch_header_cvname    { font-size:22pt;font-weight:bold }
            .sl_srch_heading_srchctrl { position:absolute; top:10px; right:30px; color:black; }
            .sl_srch_body             { margin: 20px }
            .sl_srch_roundbox         { border:1px solid #888; border-radius:5px; padding:10px; margin-bottom:10px; max-width:30em; }
            </style>
            ");
    }

    function drawSearch( string $sSrch )
    {
        $sTitle = $sBody = "";

        /* Keyword search - find all pcv that match the keyword
         */
        $o = new QServerRosetta( $this->oApp );
        $rQ = $o->Cmd( 'rosetta-cultivarSearch', ['sSrch'=>$sSrch] );
        if( count($rQ['raOut']) ) {
            $sTitle = "<h2>Here are some seeds that match <span class='sl_srch_header_cvname'>\"".SEEDCore_HSC($sSrch)."\"</span></h3>";
            $sBody = "<h4 style='margin-bottom:30px;'><span style='background-color:#ddd;padding:10px;border-radius:10px'>Click each name for more information</span></h4>";
            foreach( $rQ['raOut'] as $k => $ra ) {
                $sBody .= "<div><h4><a href='?sfSp_cv={$ra['kPcv']}'>{$ra['sSpecies']}, {$ra['sCultivar']}</a></h4>
                               <p style='margin-left:30px'>{$ra['about_cultivar']}</p>
                           </div>";
            }
        } else {
            $sTitle = "<h2>Looks like we don't have any matches for <span class='sl_srch_header_cvname'>\"".SEEDCore_HSC($sSrch)."\"</span></h3>";
            $sBody = $this->defaultBody(true);
        }

        return( [$sTitle,$sBody] );
    }

    private function defaultBody( bool $bAfterFailedSearch )
    {
        $s = $bAfterFailedSearch ? "<h4>Try searching for something else, or click through the links below to find out everything we know about 20,000 varieties of vegetable and fruit seeds in Canada</h4>"
                                 : "<h4>Click through the links below to find out everything we know about 20,000 varieties of vegetable and fruit seeds grown in Canada.<br/><br/>
                                    <h4>Or use the search bar to find what you're looking for.</h4>";
        return( $s );
    }

    private function drawCultivar( int $kPcv )
    {
        $sTitle = $sBody = "";
        $raOut = [];

        $o = new QServerRosetta( $this->oApp );
        $rQ = $o->Cmd( 'rosetta-cultivaroverview', ['kPcv'=>$kPcv] );
        if( !$rQ['bOk'] ) {
            $sBody .= "<div class='alert alert_warning'>Sorry, apparently we don't know much about that variety.</div>";
            goto done;
        }

        $oSLDB = new SLDBRosetta( $this->oApp );

        /* Main Heading
         */
        $sNameSpecies = @$rQ['raOut']['PxS']['S_name_en'] ?: "Unknown species";
        $sNameCultivar = @$rQ['raOut']['PxS']['P_name']   ?: "Unknown cultivar";
        $sTitle .= "<h2><span class='sl_srch_header_cvname'>{$sNameCultivar} {$sNameSpecies}</span>".SEEDCore_NBSP('',10)."Here's everything we know about it</h2>";

        /* Packet label and Synonyms - these are written even if empty
         */
        $raOut['packetlabel'] = ($p = @$rQ['raOut']['PxS']['P_packetLabel']) ? "<div class='sl_srch_roundbox'>$p</div>" : "";
        $raOut['synonyms'] = ($raSyn = $oSLDB->GetList('PY', "fk_sl_pcv='$kPcv'"))
                                ? ("Also known as:<div style='margin:0px 20px'>".SEEDCore_ArrayExpandRows($raSyn,"[[name]]<br/>")."</div>") : "";

        /* Crop Profile
         */
        if( $rQ['raOut']['raProfile'] ) {
            $raOut['profile'] = "<h3 class='sl_srch_heading'>What our seed savers told us about it</h3>
                                 <p>Show first 200px with expand</p>";
        }

        /* Sources
         */
        $cSrc = count($rQ['raOut']['raSrc']);
        $raOut['csci'] = "<h3 class='sl_srch_heading'>Where you can buy it in Canada".($cSrc ? (" ($cSrc companies)") : "")."</h3>";
        if( $cSrc ) {
            $raOut['csci'] .= "<div style='border:1px solid white;max-height:200px;overflow-y:auto;width:50%'>"
                     .SEEDCore_ArrayExpandRows( $rQ['raOut']['raSrc'],
                                                "<div><a href='http://[[SRC_web]]' target='_blank'>[[SRC_name_en]] in [[SRC_prov]]</a></div>" )
                     ."</div>";
        } else {
            $raOut['csci'] .= "<p>We don't know of any Canadian seed companies that sell this. If you do, please tell us.</p>";
        }

        /* Seed Exchange
         */
        if( $rQ['raOut']['raMSE'] ) {
            $raOut['mse'] =
                "<h3 class='sl_srch_heading'>Listed in our Member Seed Exchange</h3>
                 <p>Seeds of Diversity's seed savers collect over 3000 varieties of heritage vegetables, fruits, grains, flowers, and herbs,
                    and offer them through a national seed exchange. <a href='https://seeds.ca/seedexchange'>Find out how you can exchange seeds with our seed savers too!</a></p>";
            foreach($rQ['raOut']['raMSE'] as $ra) {
                $raOut['mse'] .= "<div style='margin:15px;padding:3px;background-color:#e0e0e0;border-color:#aaa'>{$ra['description']}</div>";
            }
        }

        /* Seed Library Collection
         */
        if( count($rQ['raOut']['raIxA']) ) {
            $raOut['sl_collection'] = "<h3 class='sl_srch_heading'>Preserved in our Seed Library Collection</h3>
                       <p>Seeds of Diversity backs up rare and endangered heritage seeds in a national collection,
                          engages volunteers to multiply them, and redistributes seeds to community groups and seed swaps all across Canada.
                          Find out how you can help!</p>
                       <p>We have these samples of $sNameCultivar $sNameSpecies in storage:</p>";
            foreach( $rQ['raOut']['raIxA']  as $ra ) {
                $y = ($y = $ra['year_harvested'] ?: $ra['year_received']) ? " from $y" : "";
                $raOut['sl_collection'] .= "<div style='margin-left:50px'>Lot #{$ra['inv_number']}: {$ra['g_weight']} grams $y</div>";
            }
        }

        /* Historic Seed Catalogues
         */
        //$s .= "<h3 class='sl_srch_heading'>Historic Canadian seed catalogues</h3>";

        $sBody .= "<div class='container-fluid'>
                   <div class='row'>
                       <div class='col-md-6'>{$raOut['packetlabel']}</div><div class='col-md-6'>{$raOut['synonyms']}</div>
                   </div>
                   <div class='row'>";
        $i = 0;
        foreach( ['profile','csci','mse','sl_collection'] as $k ) {
            if( ($v = @$raOut[$k]) ) { $sBody .= "<div class='col-md-6'>{$v}</div>"; ++$i; }
            if( $i == 2 ) { $sBody .= "</div><div class='row'>"; $i = 0; }
        }
        $sBody .= "</div></div>";


        done:
        return( [$sTitle,$sBody] );
    }

}
