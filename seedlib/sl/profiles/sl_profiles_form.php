<?php

/* Crop Profiles form generator
 *
 * Copyright (c) 2009-2018 Seeds of Diversity Canada
 */

include_once( SEEDCORE."SEEDCoreForm.php" );
include_once( SEEDCORE."SEEDTag.php" );
//include_once( STDINC."KeyFrame/KFUIForm.php" );



$raSLDescDefsCommon = array(
'common_SoD_d__sowdate'     	=> array( 'l_EN' => "Sowing date",
                                     	  'q_EN' => "Date when you sowed the seeds?" ),
'common_SoD_d__flowerdate'  	=> array( 'l_EN' => "First flowering date",
                                     	  'q_EN' => "Date when the first flowers opened?" ),
'common_SoD_d__poddate'     	=> array( 'l_EN' => "First edible pod date",
                                     	  'q_EN' => "(If an edible pod variety) Date when the first pod was ready to eat?" ),
'common_SoD_d__seeddate'    	=> array( 'l_EN' => "First seed harvest date",
                                     	  'q_EN' => "Date when the first dry seeds were ready to harvest?" ),
'common_SoD_d__lharvestdate' 	=> array( 'l_EN' => "First lettuce harvest date",
                                     	  'q_EN' => "Date when the lettuce was just ready to harvest?" ),
'common_SoD_d__harvestdate' 	=> array( 'l_EN' => "First Fruit harvest date",
                                     	  'q_EN' => "Date when the first fruit ripened?" ),
'common_SoD_d__leafdate'    	=> array( 'l_EN' => "First leaf date",
                                     	  'q_EN' => "Date of first leaf appearance?" ),
'common_SoD_d__boltdate'    	=> array( 'l_EN' => "First bolt date",
                                     	  'q_EN' => "Date when the plant began to bolt (elongated, became bitter, no longer fit to eat)?" ),
'common_SoD_d__diestartdate'	=> array( 'l_EN' => "Dying Start date",
                                     	  'q_EN' => "Date when the leaves/vines began to die down?" ),
'common_SoD_d__dieenddate'      => array( 'l_EN' => "Dying End date",
                                     	  'q_EN' => "Date when the leaves/vines had completely died back?" ),
'common_SoD_d__flowerdatemale'  => array( 'l_EN' => "Male flower date",
                                     	  'q_EN' => "Date when the first male flower opened?" ),
'common_SoD_d__flowerdatefemale'=> array( 'l_EN' => "Female flower date",
                                     	  'q_EN' => "Date when the first female flower opened?" ),

);




class SLProfilesForm
{
    private $oProfilesDB;
    private $oDescDB_Cfg;
    private $kVI = 0;
    private $lang;
    private $raDefs = array();
    private $raValues = array();

    private $oForm;
    private $oTagParser;    // used by DrawFormExpandTags

    function __construct( SLProfilesDB $oProfilesDB, $kVI, $lang = "EN" )
    {
        //$this->oDescDB_Cfg = new SLDescDB_Cfg( $oDescDB->kfdb, $oDescDB->uid );     // added this in a klugey way

        $this->oProfilesDB = $oProfilesDB;
        $this->kVI = $kVI;
        $this->lang = $lang;

        $kfrel = $this->oProfilesDB->GetKfrel( "Obs" );
        // descriptor codes encoded here with this SEEDForm
        $this->oForm = new KeyframeForm( $kfrel, 'A',
                                         array( "DSParms" => array('fn_DSPreStore'=>array($this,'myDSPreStore') ) ) );

        if( $kVI ) {
            $raD = $this->oProfilesDB->GetList( "Obs", "fk_sl_varinst='$kVI'" );
            foreach( $raD as $ra ) {
                $this->SetValue( $ra['k'], $ra['v'] );
            }
        }
    }

    function Update()
    /****************
        Scan $_REQUEST for seedform parms 'A' and update rows in sl_desc_obs accordingly
     */
    {
        $this->oForm->Update();
    }

    function myDSPreStore()
    {
        if( !$this->oForm->GetKey() && !$this->oForm->Value('v') ) {
            // This desccode is not stored in the db for this varinst, and its submitted value is still blank.  Don't bother storing 0 or empty.
            return( false );
        } else {
            // This desccode is in the db OR it has a non-empty value. Insert or Update it.
            // If the user entered a value, and is now changing it to zero or blank, all we do is write the zero or blank.
            // This does the right thing, but it means there can be default/blank values in the db (not really a problem, just so you know).
            return( true );
        }
    }

    function SetDefs( $raDefs )
    {
        $this->raDefs = array_merge( $this->raDefs, $raDefs );
    }

    function LoadDefs( $sPrefix = "" )
    /*********************************
        Load desc defs from the tags and multi tables
     */

    {
        $defs = array();

        $sCond = $sPrefix ? "tag LIKE '$sPrefix%'" : "";

        if( ($kfrTags = $this->oDescDB_Cfg->GetKfrelCfgTags()->CreateRecordCursor( $sCond )) ) {
            while( $kfrTags->CursorFetch() ) {
                $tag = $kfrTags->value('tag');
                $defs[$tag] = array( 'l_EN'=>$kfrTags->Value('label_en'),
                                     'l_FR'=>$kfrTags->Value('label_fr'),
                                     'q_EN'=>$kfrTags->Value('q_en'),
                                     'q_FR'=>$kfrTags->Value('q_fr'),
                );
                $raM = $this->oDescDB_Cfg->GetKfrelCfgM()->GetRecordSet( "tag='$tag'" );
                if( count($raM) ) {
                    foreach( $raM as $kfrM ) {
                        $defs[$tag]['m'][$kfrM->Value('v')] = $kfrM->Value('l_en');
                    }
                }
            }
        }

        $this->SetDefs( $defs );
    }

    function SetValue( $k, $v )
    {
        $this->raValues[$k] = $v;
    }

	function Style()
    {
        return( "<STYLE>"
               .".sld_secthdr { font-family: verdana,helvetica,arial,sans-serif; }"
               .".sld_inst    { font-family: verdana,helvetica,arial,sans-serif; font-size:10pt; background-color:#D8ECD4; margin-bottom: 1em; font-style: italic; }"
               .".sld_q       { font-family: verdana,helvetica,arial,sans-serif; font-size:10pt; background-color:#fff;    margin-bottom: 1em; width:100% }"
               .".sld_a       { font-family: verdana,helvetica,arial,sans-serif; font-size:10pt;                           margin-bottom: 1em; }"
               .".sld_q br    { clear:both; }"  // clear the float
               ."</STYLE>" );
    }

    function Section( $title )
    {
        return( "<HR/><H4 class='sld_secthdr'>$title</H4>" );
    }

    function Instruction( $sInst )
    {
        return( "<DIV class='sld_inst'>$sInst</DIV>" );
    }

    function TopHeader($head)
    {
        $s = "<div class='sld_inst'>If you cannot answer any of these questions, just leave them blank or select the \"don't know\" choice.</div>"
            .$this->Q_I( "common_SoD_i__samplesize" );

        return( $s );
    }


    function Q_D( $k ) { return( $this->Q_S( $k ) ); }

    function Q_S( $k )
    {
        $q = $this->q_( $k );

        return( "<div class='sld_q'>"
               ."<span style='float:right'>"
                   .$this->prepForm( $k )
                   .$this->oForm->Text( 'v', '', array('size'=>30) )
               ."</span>"
               .$q     //<LABEL for='$k'>$q</LABEL><BR/>"
               ."</div>" );
    }

    function Q_F( $k ) { return( $this->Q_I( $k ) ); }

    function Q_I( $k )
    {
        $q = $this->q_( $k );
        return( "<div class='sld_q'>"
               ."<div style='float:right'>"
                   .$this->prepForm( $k )
                   .$this->oForm->Text( 'v', '', array('size'=>10) )
               ."</div>"
               .$q    // <LABEL for='$k'></LABEL><BR/>
               ."</div>" );
    }

	function Q_B( $k )
	{
        $q = $this->q_( $k );
//	    $sOptions = $this->lang == 'EN' ?
//                    ("<OPTION value='0'>don't know</OPTION>"
//                    ."<OPTION value='1'>Yes</OPTION>"
//                    ."<OPTION value='2'>No</OPTION>")
//                    :
//                    ("<OPTION value='0'>sais pas</OPTION>"
//                    ."<OPTION value='1'>Oui</OPTION>"
//                    ."<OPTION value='2'>Non</OPTION>");

        $raOptions = $this->lang == 'EN'
                       ? array( "don't know" => 0, "Yes" => 1, "No"  => 2 )
                       : array( "sais pas"   => 0, "Oui" => 1, "Non" => 2 );

        $s = "<div class='sld_q'>"
            ."<div style='float:right'>"
                .$this->prepForm( $k )
                .$this->oForm->Select( 'v', $raOptions )
            ."</div>"
            .$q    // <LABEL for='$k'></LABEL><BR/>
            ."</div>";

        return( $s );
	}

    function Q_M( $k )
    /*****************
       Implement multiple choice with a SELECT
     */
    {
        $q = $this->q_( $k );
        $raOptions = array();
        foreach( $this->raDefs[$k]['m'] as $v => $label ) {
            if( $this->lang == 'FR' && isset($this->raValXlat[$label]) ) {
                $label = $this->raValXlat[$label];
            }
            $raOptions[$label] = $v;
        }
        $s = "<div class='sld_q'>"
            ."<div style='float:right'>"
                .$this->prepForm( $k )
                .$this->oForm->Select( 'v', $raOptions )
            ."</div>"
            .$q    // <LABEL for='$k'></LABEL><BR/>
            ."</div>";

        return( $s );
    }

    function Q_M_Table( $k )
    /***********************
        Implement multiple choice with a table of RADIO
        Images can be defined too, but they're optional
     */
    {
        $ra = $this->raDefs[$k];
        $q = $this->q_( $k );

        // work out the img height/width, if applicable
        $imgAttrs = "";
        if( isset($ra['img']) ) {
            if(      !empty($ra['imgParms']['imgW']) )  $imgAttrs = "width='".$ra['imgParms']['imgW']."'";
            else if( !empty($ra['imgParms']['imgH']) )  $imgAttrs = "height='".$ra['imgParms']['imgH']."'";
            else                                        $imgAttrs = "height='80'";
        }

        $s = "<DIV class='sld_q'><LABEL for='$k'>$q</LABEL>"
            ."<TABLE class='sld_q_m_img' border='0' cellpadding='5'>"
            .$this->prepForm( $k );

        $bZero = false;
        $i = 0;
        foreach( $ra['m'] as $v => $label ) {
            if( $v == 0 ) {
                $bZero = true;
                continue;
            }
            $s .= $this->_q_m_table_td( $i, $k, $ra, $v, $label, $imgAttrs );
        }
        if( $bZero ) {
            $s .= $this->_q_m_table_td( $i, $k, $ra, 0, $ra['m'][0], $imgAttrs );
        }
        $s .= "</TR></TABLE>"
             ."</DIV>";
        return( $s );
    }

    function _q_m_table_td( &$i, $k, $ra, $v, $label, $imgAttrs )
    {
        $s = "";

        if( $this->lang == 'FR' && isset($this->raValXlat[$label]) ) {
            $label = $this->raValXlat[$label];
        }
        if( ($i % 5 == 0) ) {
            if( $i )  $s .= "</TR>";
            $s .= "<TR>";
        }
        $s .= "<TD valign=top>"
             .$this->oForm->Radio( 'v', $v, "" )
             .$label;
        if( !empty($ra['img'][$v]) ) {
            $s .= "<BR/><DIV align=center><IMG src='".W_ROOT."seedcommon/sl/descimg/".$ra['img'][$v]."' $imgAttrs></DIV>";
        }
        $s .= "</TD>";
        ++$i;

        return( $s );
    }

    private function q_( $k )
    {
        if( !($q = @$this->raDefs[$k]["q_".$this->lang]) ) {
            $q = $k." - Question undefined";
        }
        return( $q );
    }

    private function prepForm( $k )
    /******************************
        Set up the oForm to draw a control for the given desccode in the current varinst.
        All you have to do after this is draw the control using oForm.
     */
    {
        $kfr = $this->oProfilesDB->GetKFRCond( "Obs", "fk_sl_varinst='".$this->kVI."' AND k='".addslashes($k)."'" );
        if( !$kfr ) $kfr = $this->oForm->Kfrel()->CreateRecord();

        $this->oForm->SetKFR( $kfr );
        $this->oForm->IncRowNum();

        return( $this->oForm->HiddenKey()
               .$this->oForm->Hidden( 'fk_sl_varinst', array('value'=>$this->kVI) )
               .$this->oForm->Hidden( 'k', array('value'=>$k ) ) );
    }


    function DrawForm( $raForm )
    {
        $s = "";
        foreach( $raForm as $raFormItem ) {
            $s .= $this->DrawFormItem( $raFormItem );
        }
        return( $s );
    }

    function DrawFormItem( $raFormItem )
    {
        $s = "";

        $ra = $raFormItem;//rename the array below
        switch( $ra['cmd'] ) {
            case 'head':
                $s .= $this->TopHeader( $ra["head_{$this->lang}"] );
                break;
            case 'section':
                $s .= $this->Section( $ra["title_{$this->lang}"] );
                break;
            case 'inst':
                $s .= $this->Instruction( $ra["inst_{$this->lang}"] );
                break;
            case 'q_d':
                $s .= $this->Q_D( $ra['k'] );
                break;
            case 'q_s':
                $s .= $this->Q_S( $ra['k'] );
                break;
            case 'q_f':
                $s .= $this->Q_F( $ra['k'] );
                break;
            case 'q_m':
                $s .= $this->Q_M( $ra['k'] );
                break;
            case 'q_i':
                $s .= $this->Q_I( $ra['k'] );
                break;
            case 'q_b':
                $s .= $this->Q_B( $ra['k'] );
                break;
            case 'q_m_t':
                $s .= $this->Q_M_Table( $ra['k'] );
                break;
               default:
                break;
        }
        return( $s );
    }

    function DrawFormItemTag( $tag, $bMultiAsTable = false )
    /*******************************************************
        Given $tag == 'crop_blart_X__Foo' draw the form item
     */
    {
// This is all redundant because it can be derived from the type code and the tag (for multi-table).
// It's just so we can call DrawFormItem
        $cmd = 'q_s';
        if( strpos( $tag, '_m__' ) !== false )  $cmd = $bMultiAsTable ? 'q_m_t' : 'q_m';
        if( strpos( $tag, '_i__' ) !== false )  $cmd = 'q_i';
        if( strpos( $tag, '_b__' ) !== false )  $cmd = 'q_b';
        if( strpos( $tag, '_f__' ) !== false )  $cmd = 'q_f';
        if( strpos( $tag, '_d__' ) !== false )  $cmd = 'q_d';

        return( $this->DrawFormItem( array( 'cmd'=>$cmd, 'k' => $tag ) ) );
    }

    function DrawDefaultForm( $psp )
    /*******************************
        Make a form based on all the loaded defs that match $psp
     */
    {
        $s = "";

        foreach( $this->raDefs as $tag => $ra ) {
            if( substr( $tag, 0, strlen($psp) ) == $psp )  $s .= $this->DrawFormItemTag( $tag );
        }

        return( $s );
    }

    function DrawFormExpandTags( $sTemplate )
    {
        $s = "";

        $this->oTagParser = new SEEDTagParser( array( 'fnHandleTag' => array($this,'_drawFormExpandTags_HandleTag') ) );
        $s = $this->oTagParser->ProcessTags( $sTemplate );

        return( $s );
    }

    function _drawFormExpandTags_HandleTag( $raTag )
    {
        $s = "";
        switch( $raTag['tag'] ) {
            case 'cd':
                if( ($k = $raTag['target']) ) {
                    $s = $this->DrawFormItemTag( $k );
                }
                break;
            case 'cd-t':
                // draw multiple choice as a table
                if( ($k = $raTag['target']) ) {
                    $s = $this->DrawFormItemTag( $k, true );
                }
                break;
            default:
                $s = $this->oTagParser->HandleTag( $raTag );
        }
        return( $s );
    }
}

?>
