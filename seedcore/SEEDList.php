<?php

use Google\Service\DisplayVideo\Pacing;

/* SEEDList
 *
 * Manage list-like data structures
 *
 * Copyright (c) 2024 Seeds of Diversity Canada
 */

class SEEDList_ArraySlices
/*************************
    Where there is an external ordered data set, use this to store one or more slices of that list.
    Slices are set into this object according to their positions in the actual data set,
    stored in a single array with nulls filling any gaps between slices.
 */
{
    private $raData;    // data from the first element of the first slice to the last element of the last slice, with nulls filling gaps
    private $iOffset;   // 0-based offset of raData within the actual data set

    function __construct()
    {
        $this->Clear();
    }

    function Clear()
    {
        $this->raData = [];
        $this->iOffset = 0;
    }

    function Stats()
    {
        return( "ArraySlices contains ".count($this->raData)." items starting at offset {$this->iOffset}" );
    }

    /**
     * Return true if indexed item is currently loaded
     * @param int $iPos - 0-based index in actual data set
     * @return boolean
     */
    function IsItemLoaded( int $iPos ) : bool
    {
        return( $this->isSliceLoaded($iPos, 1) );
    }

    /**
     * Return true if items in given slice are currently loaded
     * @param int $iSliceStart - 0-based index of slice in actual data set
     * @param int $nSliceSize
     * @return boolean
     */
    function IsSliceLoaded( int $iSliceStart, int $nSliceSize ) : bool
    {
        $bLoaded = false;

        if( $this->raData &&                                                            // initialized
            $nSliceSize > 0 &&                                                          // size makes sense
            $iSliceStart >= $this->iOffset &&                                           // slice is in the range loaded in raData
            $iSliceStart + $nSliceSize <= $this->iOffset + count($this->raData) )
        {
            // check for placeholder rows within the requested slice
            for( $i = $iSliceStart; $i < $iSliceStart + $nSliceSize; ++$i ) {
                if( $this->_getItem($i) === null )  goto done;
            }

            $bLoaded = true;
        }

        done:
        return( $bLoaded );
    }

    /**
     * Get the array item at the given index
     * @param int $iPos - 0-based index in actual data set
     * @return mixed the requested array element or null if not loaded
     */
    function GetItem( int $iPos )
    {
        return( $this->IsItemLoaded($iPos) ? $this->_getItem($iPos) : null );
    }

    private function _getItem( int $iPos )
    {
        // assumes IsItemLoaded has already been checked
        return( $this->raData[$iPos - $this->iOffset] );
    }

    /**
     * Get a subset of items from the store
     * @param int $iSliceStart - 0-based index of slice in actual data set
     * @param int $nSliceSize
     * @param bool $bCheckIsLoaded - check IsSliceLoaded() - default true
     */
    function GetSlice( int $iSliceStart, int $nSliceSize, bool $bCheckIsLoaded = true )
    {
        $raOut = [];

        if( $iSliceStart >= 0  &&
            !($bCheckIsLoaded && !$this->IsSliceLoaded($iSliceStart, $nSliceSize)) )
            /* N.B. use bCheckIsLoaded==false if requesting a slice that might exceed the actual view size
             */
        {
            $raOut = array_slice($this->raData, $iSliceStart - $this->iOffset, $nSliceSize);
        }

        return( $raOut );
    }

    /**
     * Insert an array slice into the storage
     * @param array $raSlice - array to insert into raData
     * @param int $iPos - 0-based offset of this slice within the actual data set
     */
    function AddSlice( array $raSlice, int $iSlicePos )
    {
//var_dump("Adding ".count($raSlice)." items at offset $iSlicePos");
        if( !count($raSlice) ) goto done;

        // if nothing loaded yet just set the slice
        if( !$this->raData ) {
            $this->raData = $raSlice;
            $this->iOffset = $iSlicePos;
            goto done;
        }

        // prepare to merge the new slice with the existing raViewRows
        $iNewStart = $iSlicePos;
        $iNewEnd   = $iSlicePos + count($raSlice) - 1;  // offset within data set of the last element of the new slice
        $iOldStart = $this->iOffset;
        $iOldEnd   = $this->iOffset + count($this->raData) - 1;
        $raDataOld = $this->raData;
//var_dump("old data $iOldStart $iOldEnd , new $iNewStart $iNewEnd");

        /* There are six ways the new slice can overlay the old data

            1) NNNN OOOO           new slice is before data (with gap of zero or more)
            2) NNNN                new slice overlaps first part of old
                 OOOO
            3) NNNN                new slice fully overlaps old
                OO
            4)  NN                 new slice fully within old range (including iNewEnd==iOldEnd)
               OOOO
            5)   NNNN              new slice overlaps end part of old
               OOOO
            6) OOOO NNNN           new slice is after old (with gap of zero or more)
         */
        if( $iNewStart <= $iOldStart ) {
            /* 1, 2, 3 : put the new rows, then append (part of) old rows (with possible gap)
             */
            if( $iNewEnd < $iOldStart ) {
                // 1) new rows are fully before old, gap of 0 or more to fill with nulls
                $this->raData = array_merge($raSlice, array_fill(0, $iOldStart - ($iNewEnd + 1), null), $raDataOld);
            } else {
                // 2) new rows are appended by the non-overlapped old rows
                // 3) same but there are no non-overlapped old rows so array_slice gives empty array
                $this->raData = array_merge($raSlice, array_slice($raDataOld, $iNewEnd + 1 - $iOldStart));
            }
            $this->iOffset = $iNewStart;

        } else if( $iOldEnd < $iNewEnd ) {
            /* 5, 6 : put (part of) old rows (with possible gap), then append the new rows
             */
            if( $iOldEnd < $iNewStart ) {
                // 6) new rows are fully after old, gap of 0 or more to fill with nulls
                $this->raData = array_merge($raDataOld, array_fill(0, $iNewStart - ($iOldEnd + 1), null), $raSlice);
            } else {
                // 5) old rows truncated and appended by new rows
                $this->raData = array_merge(array_slice($raDataOld, 0, $iNewStart - $iOldStart),     // the part of the old rows prior to the new slice
                                            $raSlice);
            }

        } else {
            /* 4) part of old rows, then the new rows, then part of old rows (or none if iNewEnd==iOldEnd)
             */
            $this->raData = array_merge( array_slice($raDataOld, 0, $iNewStart - $iOldStart),     // the part of the old rows prior to the new slice
                                         $raSlice,
                                         array_slice($raDataOld, $iNewEnd + 1 - $iOldStart) );    // the part of the old rows after the new slice
        }

        done:;
    }
}
