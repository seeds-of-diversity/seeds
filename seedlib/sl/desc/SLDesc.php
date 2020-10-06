<?php

/* SLDesc
 *
 * Copyright (c) 2017 Seeds of Diversity Canada
 *
 * Basic functions for Crop Description
 */


class SLDescReadOnly
{
    private $kfdb;
    private $lang;

    function __construct( KeyFrameDB $kfdb, $lang )
    {
        $this->kfdb = $kfdb;
        $this->lang = $lang;
    }

    function DrawQuestion( $sTag )
    {
        $s = "";

        $ra = $this->kfdb->QueryRA( "SELECT * FROM seeds.sl_desc_cfg_tags WHERE tag='".addslashes($sTag)."'" );
        if( !@$ra['tag'] ) {
            $s = "<span style='color:red'>CD tag '$sTag' not found</span>";
        } else if( !$ra['q_en'] ) {
            $s = "<span style='color:red'>CD tag '$sTag' has blank q_en</span>";
        } else {
            $s = @$ra['q_en'];
        }

        return( $s );
    }
}

?>
