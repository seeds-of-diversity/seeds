<?php

/* SEEDDateCalendar.php
 *
 * Based on calendarclass by Manuel Lemos
 *
 * @(#) $Id: calendarclass.class,v 1.3 2000/12/03 22:10:48 mlemos Exp $
 *
 */

include_once( SEEDCORE."SEEDDate.php" );


class SEEDDateCalendar  // calendar_class
{
    private $year = 2000;
    private $month = 1;    // 1..12
    private $day = 0;      // 1..31

    private $daysInMonth = 0;
    private $dayOfWeek = 0;    // 0..6  is Sun..Sat
    private $calendar_rows = 0;

    private $week_day_names = ["Sun","Mon","Tue","Wed","Thu","Fri","Sat"];

    function __construct() {}

    function GetYear()      { return( $this->year ); }
    function GetMonth()     { return( $this->month ); }
    function GetDay()       { return( $this->day ); }

    function SetYear( $y )  { $this->year = $y; }
    function SetMonth( $m ) { $this->month = $m; }

    function GetMonthName( $m )
    /**************************
        $m is a number 1..12
     */
    {
        return( ($m >= 1 && $m <= 12) ? SEEDDate::$raMonths[$m]['en'] : "MONTH $m" );
    }

    function OutputCalendar( $raParms = [] )
    {
        $this->daysInMonth = SEEDDate::DaysInMonth( $this->month, $this->year );

        $this->dayOfWeek = SEEDDate::DayOfWeek( $this->year, $this->month, 1 );
        $this->calendar_rows = intval( ($this->dayOfWeek + $this->daysInMonth + 6) / 7 ) + 1;

        return( $this->outputtable( $raParms ) );
    }

    function DayContent()       // override to show the content of the current day
    {
        return("");
    }


    private function outputtable( $raParms )
    {
        $center = @$raParms['table_center'] ?: true;
        $class  = @$raParms['table_class'];
        $style  = @$raParms['table_style'];
        $width  = @$raParms['table_width'];
        $border = @$raParms['table_border'];

        return ($center ? '<center>' : '')
              ."<table class='{$class}' style='{$style}' width='{$width}' border='{$border}'>"
              .$this->outputrows()
              ."</table>"
              .($center ? '</center>' : '');
    }

    private function outputrows()
    {
        $s = "";
        for( $row = 0; $row < $this->calendar_rows; ++$row ) {
            $s .= "<tr>".$this->outputcolumns( $row )."</tr>";
        }
        return( $s );
    }

    private function outputcolumns( $row )
    {
        $s = "";
        $columndata = [];
        for( $col = 0; $col < 7; ++$col ) {
            $columndata['data']='';
            $columndata['header']=0;
            $columndata['backgroundcolor']='';
            $columndata['width']='75';
            $columndata['class']='';
            $columndata['style']='text-align:center';
            $columndata['verticalalign']='top';
            $this->fetchcolumn($columndata, $row, $col);

            $s .= $columndata['header']
                      ? "<th class='{$columndata['class']}' style='{$columndata['style']}' width='{$columndata['width']}' valign='{$columndata['verticalalign']}'>{$columndata['data']}</th>"
                      : "<td class='{$columndata['class']}' style='{$columndata['style']}' width='{$columndata['width']}' valign='{$columndata['verticalalign']}'>{$columndata['data']}</td>";
        }
        return( $s );
    }

    private function fetchcolumn( &$columndata, $row, $column )
    {
        if( $row == 0 ) {
            // header with day-of-week labels
            $this->day = 0;
            $columndata["data"] = $this->week_day_names[$column];
            $columndata["header"] = true;
        } else {
            $this->day = ($row-1)*7 + $column+1 - $this->dayOfWeek;
            if( $this->day > 0 && $this->day <= $this->daysInMonth ) {
                // valid day in the calendar
                $columndata["data"] = strval($this->day).$this->DayContent();
            } else {
                // blank space in first or last week of the calendar
                $this->day = 0;
            }
        }
    }
}
