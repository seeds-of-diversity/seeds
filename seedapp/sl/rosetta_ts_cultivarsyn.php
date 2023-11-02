<?php

include_once( SEEDCORE."SEEDCoreFormSession.php" );

class RosettaCultivarSynonyms
{
    private $oApp;
    private $oSVA;      // this tab's session variables
    private $oSLDB;

    function __construct( SEEDAppConsole $oApp, SEEDSessionVarAccessor $oSVA )
    {
        $this->oApp = $oApp;
        $this->oSVA = $oSVA;
        $this->oSLDB = new SLDBRosetta( $oApp );


    }

    function Init()
    {
        $this->oFormSpecies = new SEEDCoreFormSVA( $this->oSVA, 'S' );
        $this->oFormSpecies->Update();

        $this->DoJX();
    }

    function ControlDraw()
    {
        $s = "";

        $raSp = $this->oSLDB->GetList( 'S', "category in ('VEG','GRAIN')", ['sSortCol'=>'name_en'] );
        $raOpts = ['-- Choose Species --'=>0];
        foreach( $raSp as $ra ) {
            $raOpts[$ra['name_en']] = $ra['_key'];
        }

        $s .= "<div style='float:left'><form>"
             .$this->oFormSpecies->Select( 'currSpecies', $raOpts, "", ['attrs'=>"onChange='submit()'"] )." Showing vegetables and grains"
             ."</form></div>";

        $s .= "<div style='float:right'><form>
               <input type='hidden' name='cmd' value='reindex'/>
               <input type='submit' value='Reindex All Cultivar Names'/>
               </form></div>";

        $s .= "<div style='clear:both;font-size:1pt;'>&nbsp;</div>";

        return( $s );
    }

    function ContentDraw()
    {
        $s = "";

        if( !($kSpecies = $this->oFormSpecies->ValueInt('currSpecies')) ) {
            goto done;
        }

        $sLeft = $this->drawIndexedNamesTable( $kSpecies );
        $sRight = $this->drawNonIndexedNamesTable( $kSpecies );

        $s = "<style>
              .grid-striped .row:nth-of-type(odd) { background-color: rgba(0,0,0,.1); }
              .cvName { display:inline-block; background-color:#eff;border:1px solid #aaa; padding:3px; margin: 2px 0; }
              </style>
              <div class='container-fluid'><div class='row'>
                   <div class='col-md-6 grid-striped'>$sLeft</div>
                   <div class='col-md-6'><div id='fixed_scrolling_nonindex_list'>$sRight</div></div>
               </div></div>"

             ."<button id='buttonMakeThisPrimary' style='position:fixed;top:0;right:0;padding:10px;border:2px solid green;display:none'>Make This a Primary Cultivar</button>"

             .$this->script();

        done:
        return( $s );
    }

    private function drawIndexedNamesTable( $kSpecies )
    /**************************************************
        Show primary cultivar names in a column, with their synonyms in a second column
     */
    {
        $s = "<div class='row'>
                  <div class='col-md-5 text-center'><h4>Primary Cultivar Names</h4></div>
                  <div class='col-md-7 text-center'><h4>Synonyms</h4></div>
              </div>";
        $raCVList = $this->oSLDB->GetList( 'P', "fk_sl_species='$kSpecies'", ['sSortCol'=>'name'] );
        foreach( $raCVList as $raCV ) {
            $s .= "<div class='row rowPcv' data-kPcv='{$raCV['_key']}'>"
                 .$this->drawRowPcv( $raCV )
                 ."</div>";
        }
        return( $s );
    }

    private function drawRowPcv( $raCV )
    /***********************************
        Contents of the <div class='row'></div> for the given kPCV and its synonyms
     */
    {
        $raSynList = $this->oSLDB->GetList( 'PY', "fk_sl_pcv='{$raCV['_key']}'", ['sSortCol'=>'name'] );
        $sSynonyms = SEEDCore_ArrayExpandRows( $raSynList, "<div class='cvName itemPcvSyn' data-kSyn='[[_key]]'>[[name]]</div>" );
        $s = "<div class='col-md-4'>
                  <div class='cvName itemPcv' data-kPcv='{$raCV['_key']}'>{$raCV['name']}</div>
              </div>
              <div class='col-md-1' style='border-right:1px solid #aaa'>&nbsp;</div>
              <div class='col-md-7'>$sSynonyms</div>";
        return( $s );
    }

    private function drawNonIndexedNamesTable( $kSpecies )
    /*****************************************************
        Show the cultivar names in this species from sl_cv_sources that are not in sl_pcv or sl_pcv_syn
     */
    {
        $s = "<div class='row'>
                  <div class='col-md-12 text-center'><h4>Non-indexed Cultivar Names</h4></div>
              </div>";

        // Get all non-indexed ocv names of this species.
        // To make the query group and also return an ANY_VALUE(_key), GROUP_CONCAT all the _keys together and parse out the first with strtok
        $raNamesSrccv = $this->oApp->kfdb->QueryRowsRA(
                            "SELECT ocv,GROUP_CONCAT(_key,',') as k FROM {$this->oApp->DBName('seeds1')}.sl_cv_sources
                             WHERE fk_sl_species='$kSpecies' AND _status='0'
                                   AND ocv<>'' AND fk_sl_pcv='0' AND fk_sl_sources>='3'
                             GROUP BY 1
                             ORDER BY 1" );
        array_walk( $raNamesSrccv, function (&$r){ $r['k'] = strtok($r['k'],','); } );

        $s .= SEEDCore_ArrayExpandRows( $raNamesSrccv, "<div class='cvName itemNonindex' data-kSrccv='[[k]]'>[[ocv]]</div>" );

        return( $s );
    }

    private function script()
    {
        return(
<<<SCRIPT
      <script>
      var jCurrNonindex = null;

      $(document).ready( function() {
            $(".itemNonindex").click( function() {
                if( !jCurrNonindex ) {
                    jCurrNonindex = $(this); // $(this).attr('data-key');
                    jCurrNonindex.css( {'border': '2px solid green', 'margin':'5px'} );
                    
                    let buttonTop = $(this).offset().top - $(window).scrollTop() + 35;
                    let buttonLeft = $(this).offset().left; 
                    $("#buttonMakeThisPrimary").css( {'top' : buttonTop, 'left' : buttonLeft } );
                    $("#buttonMakeThisPrimary").show();
                } else {
                    clearNonindexCtrl();
                }
            });

            $(".rowPcv").click( function() {
                if( jCurrNonindex ) {
                    makeSynonym( $(this) );
                }
            });

            $("#buttonMakeThisPrimary").click( function() {
                if( jCurrNonindex ) {
                    alert( "Make "+jCurrNonindex.html()+" a primary cultivar" );
                }
            });

            let h = $(window).height() - $('#fixed_scrolling_nonindex_list').offset().top;
            $('#fixed_scrolling_nonindex_list').css( {'position':'fixed','height': h,'overflow-y':'scroll'} );
        });

        function clearNonindexCtrl()
        /***************************
            Call this when you're done with a selected nonindex item
         */
        {
            if( jCurrNonindex ) {
                jCurrNonindex.css( {'border': '1px solid #aaa', 'margin':'3px'} );
                jCurrNonindex = null;
            }
            $("#buttonMakeThisPrimary").hide();
        }

        function makeSynonym( jRowPcv )
        /******************************
            Make the ocv related to kSrccv a synonym of kPcv
         */
        {
            if( !jCurrNonindex ) return;

            let kPcv = jRowPcv.attr('data-kPcv');
            let kSrccv = jCurrNonindex.attr('data-kSrccv');

            //alert( "Make "+jCurrNonindex.html()+"("+kSrccv+") a synonym of "+jRowPcv.find(".itemPcv").html()+" ("+kPcv+")" );

            let jxData = { jx   : 'makeSynonym',
                           kSrccv : kSrccv,
                           kPcv   : kPcv,
                         };
    
            SEEDJXAsync2( "rosetta.php", jxData, function(o) {
                    if( o['bOk'] ) {
                        jRowPcv.html(o['sOut']);      // re-render the pcv row with synonyms
                        jCurrNonindex.html("");       // remove the non-index name item
                        clearNonindexCtrl();
                    }
                });
        }
      </script>
SCRIPT
);
    }


    function DoJX()
    {
        if( ($jx = SEEDInput_Str('jx')) ) {
            $rQ = ['bOk'=>false, 'sOut'=>"", 'sErr'=>""];

            /* Readonly commands
             */
            switch( $jx ) {
                case 'makeSynonym':
                    $kSrccv = SEEDInput_Int('kSrccv');
                    $kPcv = SEEDInput_Int('kPcv');

                    $raCV = $this->oApp->kfdb->QueryRA( "SELECT * from {$this->oApp->DBName('seeds1')}.sl_pcv WHERE _key='$kPcv'" );
                    $raSrccv = $this->oApp->kfdb->QueryRA( "SELECT * from {$this->oApp->DBName('seeds1')}.sl_cv_sources WHERE _key='$kSrccv'" );
                    $sSynName = addslashes($raSrccv['ocv']);
                    $this->oApp->kfdb->Execute( "INSERT INTO {$this->oApp->DBName('seeds1')}.sl_pcv_syn (fk_sl_pcv,name,notes) VALUES ($kPcv,'$sSynName','')" );

                    $rQ['sOut'] = $this->drawRowPCV( $raCV );
                    $rQ['bOk'] = true;
                    break;
            }

//            if( !$oUI->bCanWrite )  goto jxDone;

            /* Write commands
             */
            switch( $jx ) {
                case "changeStatusToPaid":
                    break;
            }

            jxDone:
            echo json_encode($rQ);
            exit;
        }
    }
}