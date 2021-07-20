<?php

include_once( SEEDLIB."SEEDTemplate/masterTemplate.php" );

include_once( SITEROOT."drupalmod/lib/d8_seedbreeze.php" );


function seedsWPPlugin_Filter( $content )
{

    $oApp = SEEDConfig_NewAppConsole_LoginNotRequired( [] );

    $oTmpl = new Drupal8Template( $oApp, [] );

    $oMT = new SoDMasterTemplate( $oApp, [] );
    //$content = $oMT->GetTmpl()->ExpandStr( $content );
    $content = $oTmpl->ExpandStr( $content, [] );

//    $content = SEEDROOT." ".SEEDW." ".SEEDW_URL
//              ."<br/><br/>".$content;
    
    return( $content );
}

function seedsWPStart()
{
    add_filter( 'the_content', 'seedsWPPlugin_Filter' );
    remove_filter( 'the_content', 'wptexturize' );  // because WP turns '--' into &mdash; which breaks some image names in SEEDTags
}
