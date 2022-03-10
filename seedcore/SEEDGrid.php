<?php

/* Bootstrap Grid helper
 *
 * Copyright (c) 2018-2022 Seeds of Diversity Canada
 */

class SEEDGrid
/*************
    raConfig:
        'type'       =>  'bootstrap', 'table'
        'styleRow'   =>  css applied to every row element unless override
        'classRow'   =>  classes applied to every row element unless override
        'attrsRow'   =>  attrs applied to every row element unless override

        'styleCols   =>  ['css for col0', 'css for col1', ... ]
        'classCols   =>  ['classes for col0', 'classes for col1', ... ]
        'attrsCols   =>  ['attrs for col0', 'attrs for col1', ... ]
 */
{
    private $raConfig;

    function __construct( $raConfig = [] )
    {
        $this->SetConfig( $raConfig );
    }

    function SetConfig( $raConfig )
    {
        $this->raConfig = $raConfig;
    }

    function Row( $raValues, $raParms = [] )
    /***************************************
        raParms:
            styleRow, classRow, attrsRow     => override raConfig
            styleCols, classCols, attrsCols  => override raConfig
            styleColN                        => override all styleCols for column N (0-based)
            classColN                        => override all classCols for column N (0-based)
            attrsColN                        => override all attrsCols for column N (0-based)
     */
    {
        $styleRow = SEEDCore_ArraySmartVal1( $raParms, 'styleRow', @$this->raConfig['styleRow'], true );    // parms.styleRow overrides config.styleRow and can be empty
        $classRow = SEEDCore_ArraySmartVal1( $raParms, 'classRow', @$this->raConfig['classRow'], true );
        $attrsRow = SEEDCore_ArraySmartVal1( $raParms, 'attrsRow', @$this->raConfig['attrsRow'], true );

        if( $this->raConfig['type'] == 'bootstrap' ) {
            $s = "<div class='row $classRow' style='$styleRow' $attrsRow>";
            $n = 0;
            foreach( $raValues as $v ) {
                $styleCol = isset($raParms['styleCol'.$n]) ? $raParms['styleCol'.$n] :
                           (isset($raParms['styleCols'][$n]) ? $raParms['styleCols'][$n] : @$this->raConfig['styleCols'][$n]);
                $classCol = isset($raParms['classCol'.$n]) ? $raParms['classCol'.$n] :
                           (isset($raParms['classCols'][$n]) ? $raParms['classCols'][$n] : @$this->raConfig['classCols'][$n]);
                $attrsCol = isset($raParms['attrsCol'.$n]) ? $raParms['attrsCol'.$n] :
                           (isset($raParms['attrsCols'][$n]) ? $raParms['attrsCols'][$n] : @$this->raConfig['attrsCols'][$n]);

                $s .= "<div class='$classCol' style='$styleCol' $attrsCol>$v</div>";
                ++$n;
            }
            $s .= "</div>";
        }

        return( $s );
    }

}





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