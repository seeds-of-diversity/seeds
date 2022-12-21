<?php

/* location.php
 *
 * Copyright (c) 2022 Seeds of Diversity Canada
 *
 * Utility functions for locations
 */

class SEEDLocation
{
    const raProvinces = ['AB' => ['EN'=>"Alberta",                   'FR'=>"Alberta"],
                         'BC' => ['EN'=>"British Columbia",          'FR'=>"Colombie-Britannique"],
                         'MB' => ['EN'=>"Manitoba",                  'FR'=>"Manitoba"],
                         'NB' => ['EN'=>"New Brunswick",             'FR'=>"Nouveau-Brunswick"],
                         'NL' => ['EN'=>"Newfoundland and Labrador", 'FR'=>"Terre-Neuve-et-Labrador"],
                         'NS' => ['EN'=>"Nova Scotia",               'FR'=>"Nouvelle-&Eacute;cosse"],
                         'ON' => ['EN'=>"Ontario",                   'FR'=>"Ontario"],
                         'PE' => ['EN'=>"Prince Edward Island",      'FR'=>"&Icirc;le du Prince-&Eacute;douard "],
                         'QC' => ['EN'=>"Quebec",                    'FR'=>"Qu&eacute;bec"],
                         'SK' => ['EN'=>"Saskatchewan",              'FR'=>"Saskatchewan"],
                         'YK' => ['EN'=>"Yukon",                     'FR'=>"Yukon"],
                         'NT' => ['EN'=>"Northwest Territories",     'FR'=>"Territoires du Nord-Ouest"],
                         'NU' => ['EN'=>"Nunavut",                   'FR'=>"Nunavut"]
                        ];

    static function ProvinceName( string $k, string $lang = 'EN' )
    {
        return( self::raProvinces[$k][$lang] );
    }

    static function SelectProvince( string $fld, string $value, array $raParms = [] )
    /********************************************************************************
        Create a <select> control for choosing a province

        fld is the verbatim 'name' attribute
     */
    {
        $sAttrs = @$raParms['sAttrs'];
        $lang = (@$raParms['lang']=='FR' ? 'FR' : 'EN');
        $bFullnames = @$raParms['bFullnames'];              // by default show province codes

        $s = "<select name='$fld' id='$fld' $sAttrs>";
        if( @$raParms['bAll'] ) {
            $s .= "<option value=''".($value=='' ? " SELECTED" : "").">-- ".($lang=='EN' ? "All" : "Tout")."--</option>";
        }

        $s .= SEEDCore_ArrayExpandSeries( self::raProvinces,
                                          function ($k,$v,$parms) use ($value,$lang,$bFullnames)
                                          {
                                              return( "<option value='$k'".($value==$k ? " SELECTED" : "").">"
                                                     .($bFullnames ? ($lang=='FR' ? $v['FR'] : $v['EN']) : $k)
                                                     ."</option>" );
                                          },
                                          [] )
            ."</select>";

        return( $s );
    }

    static function SelectProvinceWithSEEDForm( SEEDCoreForm $oForm, string $fld, $raParms = [] )
    /********************************************************************************************
        Create a <select> control for choosing a province, using SEEDForm

        fld is the basic name for the field not including sfA_ prefix
     */
    {
        return( self::SelectProvince( $oForm->Name($fld), $oForm->Value($fld) ?: "", $raParms ) );
    }
}