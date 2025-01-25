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
        $oQ = new Q( $this->oApp, ['config_bUTF8'=>false] );   // change this to true and make downstream code utf8 compliant
        $rQ = $oQ->Cmd( 'srcSrcCv', ['kSrc'=>$kSrc] );  // 'sSortCol'=>'osp,ocv' by default

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

    function DrawCompanyBlock( KeyframeRecord $kfrSrc, string $lang='EN', $raParms=[] )
    /**********************************************************************************
        Draw a sl_sources kfr as a publicly viewable block
            bEdit : also show edit controls
     */
    {
        $s = "";

        if( $lang == 'FR' ) {
            $lang = "fr";  $langother = "en";
        } else {
            $lang = "en";  $langother = "fr";
        }
        $bEdit = SEEDCore_ArraySmartBool( $raParms, 'bEdit', false );

        $name = $kfrSrc->valueEnt( !$kfrSrc->IsEmpty('name_'.$lang) ? ('name_'.$lang) : ('name_'.$langother) );
        $addr = $kfrSrc->valueEnt( !$kfrSrc->IsEmpty('addr_'.$lang) ? ('addr_'.$lang) : ('addr_'.$langother) );
        $desc = $kfrSrc->valueEnt( !$kfrSrc->IsEmpty('desc_'.$lang) ? ('desc_'.$lang) : ('desc_'.$langother) );

        // Caller might wrap the name with a link or something
        // e.g. subst_name = "<a href=foo>[[name]]</a>   -- [[name]] is substituted with $name (which could be EN or FR as decided above)
        if( isset($raParms['subst_name']) ) {
// easier to use str_replace('[[name]]')
            $name = SEEDCore_ArrayExpand( array('name'=>$name), $raParms['subst_name'], false );  // bEnt=false because entities already expanded
        }

        $s .= "<span style='font-size:11pt;font-weight:bold'>$name</span><br/>"
        ."<font size='2'>"
        .($addr ? ("<nobr>$addr</nobr><BR/>"
                   .$kfrSrc->Expand( "<nobr>[[city]] [[prov]] [[postcode]]</nobr><BR/>"))
                : "")
        .$kfrSrc->ExpandIfNotEmpty('phone', "Phone: [[]]<br/>")
                      // stopPropagation() is a kluge to prevent the onclick of the containing div (which selects the company)
        .$kfrSrc->ExpandIfNotEmpty('web', "Web: <a href='https://[[web]]' target='_blank' onclick='event.stopPropagation();'>[[web]]</a><br/>")
        .$kfrSrc->ExpandIfNotEmpty('email', "Email: <a href='mailto:[[email]]'>[[email]]</a><br/>")
        .$kfrSrc->ExpandIfNotEmpty('year_est', "Established: [[]]</br>" )
        //.($kfr->value('bSupporter') ? "*<BR/>" : "")
        ."<div style=''>$desc</div>"
        ."</font><br/>";

        if( $bEdit ) {
            $sNeeded = "";
            if( $kfrSrc->value('bNeedXlat') )    $sNeeded .= "Translation ";
            if( $kfrSrc->value('bNeedVerify') )  $sNeeded .= "Verification ";
            //if( $kfr->value('bNeedProof') )   $sNeeded .= "Proofreading ";
            if( $sNeeded )  $s .= "<BR/><FONT color='red' size='2'>Needs: $sNeeded</FONT>";
            $s .= $kfrSrc->ExpandIfNotEmpty( 'comments', "<BR/><FONT size='2' color='blue'>Private comments: [[]]</FONT>" );
        }

        return( $s );
    }
}
