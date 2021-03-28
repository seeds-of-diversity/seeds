<?php

/* SEEDIcon
 *
 * Help draw little pictures
 *
 * Copyright (c) 2021 Seeds of Diversity Canada
 */


class SEEDIcon
{
    function __construct() {}

    /* Draw triangles that point left, right, up, down.
     *      Parms: fill          (default blue)
     *             border        (default blue)
     *             border-width  (default 1)
     *             width         (default 10)
     *             height        (default 10)
     */
    static function TriangleLeft( $raParms = [] )  { return( self::triangle("4,0 16,10 4,20", $raParms) ); }
    static function TriangleRight( $raParms = [] ) { return( self::triangle("16,0 4,10 16,20", $raParms) ); }
    static function TriangleUp( $raParms = [] )    { return( self::triangle("10,4 0,16 20,16", $raParms) ); }
    static function TriangleDown( $raParms = [] )  { return( self::triangle("10,16 0,4 20,4", $raParms) ); }

/* Obsolete: this uses an image that contains lots of triangles. SVG seems more versatile.

        return( "<div style='width:14px;height:10px;position:relative;display:inline-block'>"
               ."<img src='".SEEDW_URL."img/ctrl/triangle_blue.png' "
                     .( "style='position:absolute; top:-17px; left:-19px; clip: rect( 18px, auto, auto, 20px'" )
               ."/></div>" );
*/

    private static function triangle( $points, $raParms )
    {
        $fill        = @$raParms['fill'] ?: 'blue';
        $border      = @$raParms['border'] ?: 'blue';
        $borderWidth = @$raParms['border-width'] ?: '1';
        // N.B. you have to define both width & height if you define one of them
        $w           = @$raParms['width'] ?: '10';
        $h           = @$raParms['height'] ?: '10';

        return(
             "<div style='position:relative;display:inline-block;margin:0 3px'>
                  <svg width='$w' height='$h' viewbox='0 0 20 20'>
                      <polygon points='$points'
                               style='fill:$fill;stroke:$border;stroke-width:$borderWidth' />
                      Sorry, your browser does not support inline SVG.
                  </svg>
              </div>" );
    }
}