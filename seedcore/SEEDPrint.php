<?php

/* SEEDPrint
 *
 * Copyright (c) 2018 Seeds of Diversity Canada
 *
 * Format printable output.
 */

class SEEDPrintHTML
/******************
    Formats HTML pages for printing
 */
{
    protected $sStyle = "
.SPaddrblock { position:absolute; width:3in; height:1in; left:0.75in; top:1.825in; border:1px dashed #aaa; } /* change #fff to something else to see where the address will go */
.SPaddrblockpad { padding:0.125in; }
";
    protected $sBody = "";

    function __construct() {}

    public function GetHead() { return( "<style>".$this->sStyle."</style>" ); }
    public function GetBody() { return( $this->sBody ); }

    public function AddressBlock( $sAddress )
    /****************************************
        Return the html for an address block. Put this anywhere in a container, and it will be positioned absolutely
        to be visible in a window envelope, if the top of the container is the top of the printed piece in the envelope.

        $sAddress should have <br/> formatting or equivalent html
     */
    {
        return( "<div class='SPaddrblock'><div class='SPaddrblockpad'>$sAddress</div></div>" );
    }
}

class SEEDPrint3UpHTML extends SEEDPrintHTML
/*********************
    Formats a template 3 times per sheet of paper, evenly spaced vertically.
    This generates html with page breaks.

    N.B. Print the html at 100% size with no margins.
 */
{
    function __construct()
    {
        parent::__construct();

        $this->sStyle .=
    /* Tried making these absolute on the page with different tops, but then sections on other pages want to go on the first page too.
     * Tried putting an explicit page-break-after:always on the third section, but it made a blank page because it's already forcing a break.
     */
"
.s1 { position:relative; width:8.5in; height:3.67in; left:0; top:0; border-bottom:1px dashed #eee;}
.s2 { position:relative; width:8.5in; height:3.67in; left:0;        border-bottom:1px dashed #eee;}
.s3 { position:relative; width:8.5in; height:3.67in; left:0;        border:none; }

.spad { padding:0.325in; }

.s_title   { font-family:Times New Roman; font-size:15pt; font-weight:bold; }
.s_form    { font-family:Times New Roman; font-size:12pt; }
.s_right   { font-family:Times New Roman; font-size:10pt; }
.s_form td { padding-right:0.125in; }
.s_note    { font-family:Times New Roman; font-size:8pt; }
";
    }

    function Do3Up( $raRows, $sTmpl )
    /********************************
        Draw as many 3-up pages as necessary for the given rows
     */
    {
        for( $i = 0; $i < count($raRows); $i += 3 ) {
            $ra1 = @$raRows[$i];
            $ra2 = @$raRows[$i+1];
            $ra3 = @$raRows[$i+2];

            $this->AddPage( $sTmpl, $ra1, $ra2, $ra3 );
        }
    }

    function AddPage( $sTmpl, $ra1, $ra2, $ra3 )
    {
        $this->sBody .= "<div style='page-break-before:always;font-size:1pt'>.</div>";
        if( $ra1 )  $this->sBody .= $this->tmplExpand( $sTmpl, $ra1, 1 );
        if( $ra2 )  $this->sBody .= $this->tmplExpand( $sTmpl, $ra2, 2 );
        if( $ra3 )  $this->sBody .= $this->tmplExpand( $sTmpl, $ra3, 3 );
    }

    private function tmplExpand( $sTmpl, $ra, $n )
    {
        // If addressblock is defined, add a placeholder for it and replace it below because it contains html formatting which
        // will be escaped by SEEDCore_ArrayExpand.
        // The addressblock can be appended to the bottom of the template because it is positioned absolutely in the container.
        $s = "<div class='s$n'><div class='spad'>$sTmpl"
                .(isset($ra['SEEDPrint:addressblock'])
                    ? $this->AddressBlock( "[[SEEDPrint:deferAddressblock]]" ) : "")
             ."</div></div>";
        $s = SEEDCore_ArrayExpand( $ra, $s );

        if( isset($ra['SEEDPrint:addressblock']) ) {
            // replace this separately because it contains html which will be escaped by SEEDCore_ArrayExpand
            $s = str_replace( "[[SEEDPrint:deferAddressblock]]", $ra['SEEDPrint:addressblock'], $s );
        }

        return( $s );
    }

}

?>
