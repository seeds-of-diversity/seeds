<?php

/* SEEDElo
 *
 * Copyright 2014 Seeds of Diversity Canada
 *
 * The Elo algorithm used for chess ranking (and Facemash) establishes a rating for each item/player and
 * computes an expectation that one item will win over another based on the difference between ratings.
 *
 * Ra, Rb : ratings of a and b
 * Ea, Eb : expectations of a or b winning against each other
 * S      : a scaling factor that matches the exponential curve to the typical scale of R
 *          The question is where extreme Ra-Rb would blow up 10^(Ra-Rb)
 *          e.g. if R tends to be between 100 to 2000, S=1/400 works well (this is used for chess rankings)
 *               if R tends to be between -5 to 5, S=1 works well
 * W      : the actual result of the win, weighted as desired, between 0..1
 *          e.g. 1 is a full win, 0 is a full loss, 0.5 could be a draw, 0.25 could be a handicapped loss
 *               It is common to use the average of several results here, e.g. after a chess tournament
 * K      : the maximum change in the rating.  If (W-E) is 1, this is how much the rating will jump.
 *          This might be different for a and b, if they are playing at different levels, if they have different
 *          numbers of previously accumulated scores, or if they are mismatched in some way, but this is typically a constant
 *          for all players.
 *
 * Since Ea and Eb are probabilities,
 * Ea + Eb = 1
 *
 * The expectation formulae are:
 *   Ea = 1 / ( 1 + 10^(S(Rb-Ra)) )
 *   Eb = 1 / ( 1 + 10^(S(Ra-Rb)) )
 *
 * Which incidentally is the same as
 *   Ea = Qa / (Qa + Qb)
 *   Eb = Qb / (Qa + Qb)
 * where
 *   Qa = 10^SRa
 *   Qb = 10^SRb
 * so just for fun, you can also say
 *   Ea/Eb = Qa/Qb = 10^SRa/10^SRb = 10^S(Ra-Rb)
 *
 * Every time results are obtained, or an average of a set of results, update R
 * Ra' = Ra + K( W - Ea )
 */

class SEEDElo
{
    private $Scale;     // S = 1/Scale (1/400 is typical in chess)
    private $K;         // maximum change

    function __construct( $Scale = 400.0, $K = 24.0 )   // typical values for chess
    {
        $this->SetConstants( $Scale, $K );
    }

    function SetConstants( $Scale, $K )
    {
        $this->Scale = $Scale;
        $this->K     = $K;
    }

    function Expectation( $Ra, $Rb )
    /*******************************
        Based on two player rankings, return the expected probability that the first one (a) will win
     */
    {
        return( 1.0 / ( 1.0 + pow(10.0, (1.0/$this->Scale) * ($Rb-$Ra)) ) );
    }

    function AdjustedRankings( $Ra, $Rb, $W )
    /****************************************
        Based on two player rankings and (a)'s outcome in a contest, return the new rankings

        W is between 0 and 1, indicating success for (a); 0 for a loss, 1 for a win, 0.5 for a tie, or some other weighted value.
        To ensure proper accounting, (1-W) is used as the corresponding value for player (b).
     */
    {
        $Ea = $this->Expectation( $Ra, $Rb );
        $Eb = $this->Expectation( $Rb, $Ra );
        $Ra2 = $Ra + $this->K * ($W - $Ea);
        $Rb2 = $Rb + $this->K * ((1.0 - $W) - $Eb);

        return( array( $Ra2, $Rb2 ) );
    }
}

?>
