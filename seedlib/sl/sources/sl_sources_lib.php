<?php

class SLSourcesLib
{
    private $oApp;
    public  $oSrcDB;

    function __construct( SEEDAppSession $oApp )
    {
        $this->oApp = $oApp;
        $this->oSrcDB = new SLDBSources( $oApp );
    }

    function GetSrcCVListFromSource( $kSrc )
    {
        $oQ = new Q( $this->oApp );
        $rQ = $oQ->Cmd( 'srcSrcCv', ['kSrc'=>$kSrc, 'kfrcParms'=>array('sSortCol'=>'osp,ocv')] );

        return( $rQ['raOut'] );
    }

    function AddSrcCV( $raParms )
    {
        $bOk = false;

        if( !@$raParms['fk_sl_sources'] || (!@$raParms['fk_sl_species'] && !@$raParms['osp']) ) goto done;

        if( ($kfr = $this->oSrcDB->GetKFRel('SRCCV')->CreateRecord()) ) {
            foreach( ['fk_sl_sources', 'osp', 'ocv']  as $k ) {
                $kfr->SetValue( $k, @$raParms[$k] );
// this could do a lot of validation, other combinations of valid inputs e.g. (fk_sl_sources,fk_sl_species,ocv), etc
            }
            $bOk = $kfr->PutDBRow();
        }

        done:
        return( $bOk ? $kfr->Key() : 0 );
    }

    static function DrawSRCCVRow( $ra, $prefix = '', $raParms = array() )
    /********************************************************************
        raParms:  subst_key = string to show instead of _key
                  no_key    = omit the key column
     */
    {
        $raTmp = $ra;

        // specify a prefix to customize the fields. e.g. prefix 'A_' will use A_year,A_osp,A_ocv,etc
        if( $prefix ) {
            foreach( ['_key','year','osp','ocv','bOrganic','notes'] as $f ) { $raTmp[$f] = $ra[$prefix.$f]; }   // 'bulk'
        }

        // do this outside of ArrayExpand because the subst_key typically has html that would be html-escaped
        $key = @$raParms['subst_key'] ?: @$raTmp['_key'];

        $s = SEEDCore_ArrayExpand( $raTmp,
                "<tr>"
                   .(@$raParms['no_key'] ? ""
                       : "<td style='vertical-align:top;padding-right:5px'>$key</td>")
                   ."<td style='vertical-align:top;padding-right:5px'><em>[[SRC_name_en]]</em></td>"
                   ."<td style='vertical-align:top;padding-right:5px'>[[year]]</td>"
                   ."<td style='vertical-align:top;padding-right:5px'><strong>[[osp]] : [[ocv]]</strong> "
                       .($raTmp['bOrganic']?"<span style='color:green;font-size:small'>organic</span>":"")."</td>"
                   ."<td style='vertical-align:top;padding-right:5px'>[[notes]]</td>"
               ."</tr>" );
        return( $s );
    }

    function DrawCompanySelector( SEEDCoreForm $oForm, $fld = 'kCompany' )
    /*********************************************************************
        Return <select> for the current list of seed companies, with the current company selected.
        Also return the name of the selected company for convenience.
     */
    {
        $kCompanyCurr = $oForm->Value($fld);
        $sCompanyName = "";

        $raSrc = $this->oSrcDB->GetList( 'SRC', '_key>=3', ['sSortCol'=>'name_en'] );
        $raOpts = [ " -- Choose a Company -- " => 0 ];
        foreach( $raSrc as $ra ) {
            if( $kCompanyCurr && $kCompanyCurr == $ra['_key'] ) {
                $sCompanyName = $ra['name_en'];
            }
            $raOpts[$ra['name_en']] = $ra['_key'];
        }
        $s = "<div style='padding:1em'><form method='post' action=''>"
            .$oForm->Select( $fld, $raOpts )
            ."<input type='submit' value='Choose'/>"
            ."</form></div>";

        return( array($s,$sCompanyName) );
    }
}
