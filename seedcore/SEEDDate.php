<?php

/* SEEDDate
 *
 * Copyright (c) 2010-2020 Seeds of Diversity Canada
 *
 * Manipulate dates and times in English and French
 */

class SEEDDate
{
    static public $raMonths = [                                     // wanted to put number of days here but leap years make that hard
         1 => [ 'en' => "January",   'fr' => "Janvier" ],
         2 => [ 'en' => "February",  'fr' => "F&eacute;vrier" ],
         3 => [ 'en' => "March",     'fr' => "Mars" ],
         4 => [ 'en' => "April",     'fr' => "Avril" ],
         5 => [ 'en' => "May",       'fr' => "Mai" ],
         6 => [ 'en' => "June",      'fr' => "Juin" ],
         7 => [ 'en' => "July",      'fr' => "Juillet" ],
         8 => [ 'en' => "August",    'fr' => "A&ouml;ut" ],
         9 => [ 'en' => "September", 'fr' => "Septembre" ],
        10 => [ 'en' => "October",   'fr' => "Octobre" ],
        11 => [ 'en' => "November",  'fr' => "Novembre" ],
        12 => [ 'en' => "December",  'fr' => "Decembre" ],
    ];

    static public $raDaysOfWeek = [
        0 => [ 'en' => 'Sunday',    'en3' => "Sun", 'fr' => "Dimanche", 'fr3' => 'Dim' ],
        1 => [ 'en' => 'Monday',    'en3' => "Mon", 'fr' => "Lundi",    'fr3' => 'Lun' ],
        2 => [ 'en' => 'Tuesday',   'en3' => "Tue", 'fr' => "Mardi",    'fr3' => 'Mar' ],
        3 => [ 'en' => 'Wednesday', 'en3' => "Wed", 'fr' => "Mercredi", 'fr3' => 'Mer' ],
        4 => [ 'en' => 'Thursday',  'en3' => "Thu", 'fr' => "Jeudi",    'fr3' => 'Jeu' ],
        5 => [ 'en' => 'Friday',    'en3' => "Fri", 'fr' => "Vendredi", 'fr3' => 'Ven' ],
        6 => [ 'en' => 'Saturday',  'en3' => "Sat", 'fr' => "Samedi",   'fr3' => 'Sam' ],
    ];

    static function IsLeapYear( $y )
    {
        return( intval($y % 4)==0 && (intval($y % 100)!=0 || intval($y % 400)==0) );
    }

    static function DaysInMonth( $month, $year )    // year is required because of leap years
    {
        $days = 0;

        if( $month < 1 || $month > 12 )  return( 0 );

        if( in_array( $month, [4,6,9,11] ) ) {
            $days = 30;
        } else if( $month == 2 ) {
            $days = self::IsLeapYear($year) ? 29 : 28;
        } else {
            $days = 31;
        }

        return( $days );
    }

    static function DayOfWeek( $year, $month, $day )
    /***********************************************
        Return a number between 0..6 representing Sun..Sat
     */
    {
        $leap_years = intval( ($month < 3 ? ($year-1) : $year) / 4 );

        switch( $month ) {
            default:
            case 1:    $month_year_day=0;    break;
            case 2:    $month_year_day=31;   break;
            case 3:    $month_year_day=59;   break;
            case 4:    $month_year_day=90;   break;
            case 5:    $month_year_day=120;  break;
            case 6:    $month_year_day=151;  break;
            case 7:    $month_year_day=181;  break;
            case 8:    $month_year_day=212;  break;
            case 9:    $month_year_day=243;  break;
            case 10:   $month_year_day=273;  break;
            case 11:   $month_year_day=304;  break;
            case 12:   $month_year_day=334;  break;
        }
        $w = (-473
              +365*($year-1970)
              +$leap_years
              -intval($leap_years/25)
              +((intval($leap_years % 25)<0) ? 1 : 0)
              +intval((intval($leap_years/25))/4)
              +$month_year_day
              +$day
              -1);
        return( intval( (intval($w %7) +7) %7) );    // don't know why
    }

}
