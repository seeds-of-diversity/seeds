<?php

/* search app
 *
 * Copyright 2020 Seeds of Diversity Canada
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
        if( $kPcv )   $this->oFormSearch->SetValue( 'kPcv', $kPcv );
        if( $sSrch )  $this->oFormSearch->SetValue( 'sSrch', $sSrch );

        $sSrch = $this->oFormSearch->Value('sSrch');
        $kPcv = $this->oFormSearch->ValueInt('kPcv');

        $s = $this->Style();

        $s .= $this->drawSearchControl();

        if( $sSrch ) {
            /* Keyword search - find all pcv that match the keyword
             */
            $o = new QServerRosetta( $this->oApp );
            $rQ = $o->Cmd( 'rosetta-cultivarSearch', ['sSrch'=>$sSrch] );
            if( count($rQ['raOut']) ) {
                $s .= "<h3 class='sl_srch_heading'>Here are some seeds that match the keyword \"".SEEDCore_HSC($sSrch)."\""
                     ."&nbsp;&nbsp;&nbsp;<span style='font-size:x-small'>Click each name for more information</p></h3>";
                foreach( $rQ['raOut'] as $k => $ra ) {
                    list($sp,$cv) = explode( '|', $k, 2 );

                    $s .= "<div><h4><a href='?sfSp_kPcv={$ra['kPcv']}'>{$ra['sSpecies']}, $cv</a></h4>
                                <p style='margin-left:30px'>{$ra['about_cultivar']}</p>
                           </div>";
                }
            }
        } else if( $kPcv ) {
            $o = new QServerRosetta( $this->oApp );
            $rQ = $o->Cmd( 'rosetta-cultivaroverview', ['kPcv'=>$kPcv] );
            if( !$rQ['bOk'] ) {
                $s .= "<div class='alert alert_warning'>Sorry, apparently we don't know much about that variety.</div>";
                goto done;
            }

            $oSLDB = new SLDBRosetta( $this->oApp );

            /* Main Heading
             */
            $sNameSpecies = $rQ['raOut']['PxS']['S_name_en'];
            $sNameCultivar = $rQ['raOut']['PxS']['P_name'];
            $s .= "<h2 class='sl_srch_heading'>Here's everything we know about \"{$sNameCultivar}\" {$sNameSpecies}</h2>";

            /* Packet label and/or Synonyms
             */
            $sPacketLabel = $rQ['raOut']['PxS']['P_packetLabel'];
            $raSyn = $oSLDB->GetList( 'PY', "fk_sl_pcv='$kPcv'" );
            if( $sPacketLabel || $raSyn ) {
                $sPacketLabel = ($sPacketLabel ? "<div class='sl_srch_roundbox'>$sPacketLabel</div>" : "");

                $sSyn = $raSyn ? ("Also known as:<div style='margin:0px 20px'>".SEEDCore_ArrayExpandRows($raSyn,"[[name]]<br/>")."</div>") : "";

                $s .= "<div class='container-fluid'><div class='row'>"
                         ."<div class='col-sm-6'>".($sPacketLabel ? $sPacketLabel : $sSyn)."&nbsp;</div>"
                         ."<div class='col-sm-6'>".($sPacketLabel ? $sSyn : "")." &nbsp;</div>"
                     ."</div></div>";

            }

            /* Crop Profile
             */
            if( $rQ['raOut']['raProfile'] ) {
                $s .= "<h3 class='sl_srch_heading'>What our seed savers told us about it</h3>";
                $s .= "<p>Show first 200px with expand</p>";
            }

            /* Sources
             */
            $cSrc = count($rQ['raOut']['raSrc']);
            $s .= "<h3 class='sl_srch_heading'>Where you can buy it in Canada".($cSrc ? (" ($cSrc companies)") : "")."</h3>";
            if( $cSrc ) {
                $s .= "<div style='border:1px solid white;max-height:200px;overflow-y:auto;width:50%'>"
                     .SEEDCore_ArrayExpandRows( $rQ['raOut']['raSrc'],
                                                "<div><a href='http://[[SRC_web]]' target='_blank'>[[SRC_name_en]] in [[SRC_prov]]</a></div>" )
                     ."</div>";
            } else {
                $s .= "<p>Hmm, we don't know of any Canadian seed companies that sell this. If you do, please tell us.</p>";
            }

            /* Seed Exchange
             */
            if( $rQ['raOut']['raMSD'] ) {
                $s .= "<h3 class='sl_srch_heading'>Listed in our Seed Exchange</h3>";
                $s .= "<p>Seeds of Diversity's seed savers collect over 3000 varieties of heritage vegetables, fruits, grains, flowers, and herbs,
                          and offer them through a national seed exchange. Find out how you can exchange seeds with our seed savers too!</p>";
            }

            /* Seed Library Collection
             */
            if( count($rQ['raOut']['raIxA']) ) {
                $s .= "<h3 class='sl_srch_heading'>Preserved in our Seed Library Collection</h3>"
                     ."<p>Seeds of Diversity backs up rare and endangered heritage seeds in a national collection,
                          engages volunteers to multiply them, and redistributes seeds to community groups and seed swaps all across Canada.
                          Find out how you can help!</p>"
                     ."<p>We have these samples of $sNameCultivar $sNameSpecies in storage:</p>";
                foreach( $rQ['raOut']['raIxA']  as $ra ) {
                    $y = ($y = $ra['year_harvested'] ?: $ra['year_received']) ? " from $y" : "";
                    $s .= "<div style='margin-left:50px'>Lot #{$ra['inv_number']}: {$ra['g_weight']} grams $y</div>";
                }
            }

            /* Historic Seed Catalogues
             */
            //$s .= "<h3 class='sl_srch_heading'>Historic Canadian seed catalogues</h3>";

        }

        done:
        return( $s );
    }

    private function drawSearchControl()
    {
        $s = "<form>"
            ."<div>".$this->oFormSearch->Text('sSrch')."</div>"
            ."<div>".$this->oFormSearch->Text('kPcv')."</div>"
            ."<input type='submit' value='Search' /></form>";

        return( $s );
    }

    function Style()
    {
        return( "
            <style>
            .sl_srch_heading { background-color:#777; font-weight:bold; padding:3px; color:white; }
            h2.sl_srch_heading { font-size:18pt }
            h3.sl_srch_heading { font-size:14pt }
            .sl_srch_roundbox { border:1px solid #888; border-radius:5px; padding:10px; margin-bottom:10px; max-width:30em; }
            </style>
            ");
    }
}