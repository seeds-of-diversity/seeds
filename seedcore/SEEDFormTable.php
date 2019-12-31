<?php

/* SEEDFormTable.php
 *
 * Copyright (c) 2016-2019 Seeds of Diversity Canada
 *
 * Simplify drawing tables of form rows.
 *
 *  Form definition
 *  ---------------
 *  $raFormDef defines structure and details about a form table row.
 *
 *  array( field1 => array(...), field2 => array(...), [{op} => array(...) )  where field1, field2, ... are the non-sf-encoded form field names
 *
 *              field => array( 'label' => ...                              : label for the table header, if applicable
 *                              'type'  => 'text' | 'checkbox' | 'select'   : control type, default=text
 *                              'size'  => ...                              : size for text control
 *                              'readonly'  => 1                            : no control - just show the current value
 *                              'selOptions' => array( val => label,...)    : options for select control
 *
 *              {op} are reserved row-scope controls:
 *                  _sf_op_d => array( 'label' => ... )    : a checkbox delete control for the row
 *                  _sf_op_h => array( 'label' => ... )    : a checkbox hide control for the row
 *                  _sf_op_r => array( 'label' => ... )    : a checkbox to reset (undelete/unhide) the row
 */

include_once( "SEEDCoreForm.php" );

class SEEDFormTable
{
    protected $oForm;
    private $raFormDef;

    function __construct( SEEDCoreForm $oForm )
    {
        $this->oForm = $oForm;
    }

    function Start( $raFormDef )
    {
//$this->oForm->raParms['formdef'] = $raFormDef;
        $this->raFormDef = $raFormDef;
        $this->oForm->SetRowNum( 0 );
        $s = "<table border='1' cellpadding='5' cellspacing='0'>";

        return( $s );
    }

    function Header()
    {
        $s = "<tr>";
        foreach( $this->raFormDef as $fld => $def ) {
            // use the same filter logic as Row()
            if( !in_array( @$def['type'], array('hidden', 'hidden_key') ) ) {
                $label = @$def['label'] ?: $fld;
                $s .= "<th valign='top'>$label</th>";
            }
        }
        $s .= "</tr>";

        return( $s );
    }

    function Row()
    {
        $s = "<tr>";

        $bKey = false;
        foreach( $this->raFormDef as $fld => $def ) {
            $s1 = $this->oForm->DrawElement_FormDef( $this->raFormDef, $fld, ['bDrawLabel'=>false] );
            // use the same filter logic as Header()
            if( !in_array( @$def['type'], array('hidden', 'hidden_key') ) ) {
                $s1 = "<td valign='top'>$s1</td>";
            }
            $s .= $s1;
            if( $fld == "_key" ) $bKey = true;
        }
        if( !$bKey ) {
            // the _key was not in the formdef (which is the usual case), so write it here
            $s .= $this->oForm->HiddenKey();
        }

        $s .= "</tr>";

        $this->oForm->IncRowNum();

        return( $s );
    }

    function End()
    {
        $s = "</table>";

        return( $s );
    }
}
