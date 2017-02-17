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

    function DrawQuestion( $sCode )
    {
        $s = "";

        $ra = $this->kfdb->QueryRA( "SELECT * FROM seeds.sl_desc_cfg_tags WHERE tag='".addslashes($sCode)."'" );
        $s = @$ra['q_en'];

        return( $s );
    }
}

?>
