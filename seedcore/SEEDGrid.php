<?php

/* Bootstrap Grid helper
 *
 * Copyright (c) 2018 Seeds of Diversity Canada
 */

class SEEDBootstrapGrid
{
    private $raParms;

    function __construct( $raParms = array() )
    {
        $this->SetGrid( $raParms );
    }

    function SetGrid( $raParms )
    {
        $this->raParms = $raParms;
    }

    function Row( $c1, $c2 = null, $c3 = null, $c4 = null, $c5 = null, $c6 = null, $c7 = null, $c8 = null, $c9 = null, $c10 = null, $c11 = null, $c12 = null )
    {
        $s = "<div class='row'>"
            ."<div class='{$this->raParms['classCol1']}'>$c1</div>"
            .($c2!== null ? "<div class='{$this->raParms['classCol2']}'>$c2</div>" : "")
            .($c3!== null ? "<div class='{$this->raParms['classCol3']}'>$c3</div>" : "")
            .($c4!== null ? "<div class='{$this->raParms['classCol4']}'>$c4</div>" : "")
            .($c5!== null ? "<div class='{$this->raParms['classCol5']}'>$c5</div>" : "")
            .($c6!== null ? "<div class='{$this->raParms['classCol6']}'>$c6</div>" : "")
            .($c7!== null ? "<div class='{$this->raParms['classCol7']}'>$c7</div>" : "")
            .($c8!== null ? "<div class='{$this->raParms['classCol8']}'>$c8</div>" : "")
            .($c9!== null ? "<div class='{$this->raParms['classCol9']}'>$c9</div>" : "")
            .($c10!==null ? "<div class='{$this->raParms['classCol10']}'>$c10</div>" : "")
            .($c11!==null ? "<div class='{$this->raParms['classCol11']}'>$c11</div>" : "")
            .($c12!==null ? "<div class='{$this->raParms['classCol12']}'>$c12</div>" : "")
            ."</div>";

        return( $s );
    }
}