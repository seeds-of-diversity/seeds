<?php

/* QServerCollection
 *
 * Copyright 2016-2025 Seeds of Diversity Canada
 *
 * Serve queries about sl_collection, sl_accession, sl_inventory
 */

include_once(SEEDLIB."sl/sldb.php");

class QServerCollection2 extends SEEDQ
{
    private $oSLDB;

    function __construct( SEEDAppSessionAccount $oApp, $raConfig = [] )
    {
        parent::__construct( $oApp, $raConfig );
        $this->oSLDB = new SLDBCollection($oApp, []);
    }

    function Cmd( $cmd, $parms )
    {
        $rQ = $this->GetEmptyRQ();

        list($bAccess,$rQ['sErr']) = $this->TestPerm($cmd, 'collection2', 'SLCollection');
        if( !$bAccess ) goto done;

        $rQ['bHandled'] = true;

        switch( strtolower($cmd) ) {
// move QServerCollection::getLot here
//            case 'collection-getlot':
//                list($rQ['bOk'],$rQ['raOut'],$rQ['sErr']) = $this->getLot($parms);
//                break;
            // add a new accession with one or more lots
            case 'collection2--addaccession':
                list($rQ['bOk'],$rQ['raOut'],$rQ['sErr']) = $this->addAccession($parms);
                break;
            // add/update lots for an existing accession
//            case 'collection--updatelots':
//                break;
            default:
                break;
        }

        done:
        return( $rQ );
    }


    private function addAccession( array $parms )
    /********************************************
        Add a new Accession and at least one Inventory record

        Required input:
            kPCV       : create Accession for this pcv
            or
            iParentLot : create Accession as a child of this Lot

            kColl
            g0+loc0, g1+loc1 : at least one of these Lot quantities/locations (can be 0/blank in which case it is ignored)

        Optional input
            gtotal     : original total for Accession
            g100       : weight of 100 seeds
            oname      :
            bPurchased : true=we bought the seeds, so year indicates purchase year
            sSupplier  : grower, source, company, site grown
            ySupplier  : bPurchased=year purchased, !bPurchased=year harvested

        Output
            raOut['raILots'] : [iLot0, iLot1]

     */
    {
        $ok = false;
        $raOut = ['raILots'=>[0,0]];  // inv_numbers of the created lots
        $sErr = "";

        /* Check existence and write access to Collection
         */
        if( !($kColl = intval(@$parms['kColl'])) ) {
            $sErr = "kColl not specified";
            goto done;
        }
        if( !($kfrC = $this->oSLDB->GetKFR('C',$kColl)) ) {
            $sErr = "Collection $kColl not found";
            goto done;
        }
// TODO: test write access on collection
$bCanWrite = true;
        if( !$bCanWrite ) {
            $sErr = "Collection $kColl does not allow write access";
            goto done;
        }

        /* Get kPCV or iParentLot basis for the Accession
         */
        $kPCV = intval(@$parms['kPCV']);
        $iParentLot = intval(@$parms['iParentLot']);
        $kfrLotParent = null;
        if($iParentLot) {
            if( !($kfrLotParent = $this->oSLDB->GetKFR_LotFromNumber($kColl, $iParentLot)) ) {
                $sErr = "Parent lot $iParentLot not found";
                goto done;
            }
            $kPCV = $kfrLotParent->Value('A_fk_sl_pcv');
        }
        else if($kPCV) {
            if( !($kfrP = $this->oSLDB->GetKFR('P',$kPCV)) ) {
                $sErr = "Cultivar $kPCV not found";
                goto done;
            }
        }

        /* Create New Lot(s)
         */
        $kfrA = null;
        $raLotParms = [ ['g'=>floatval(@$parms['g0']), 'loc'=>@$parms['loc0']],
                        ['g'=>floatval(@$parms['g1']), 'loc'=>@$parms['loc1']] ];
        foreach( [0,1] as $k ) {
            if( ($g = $raLotParms[$k]['g']) && ($loc = $raLotParms[$k]['loc']) ) {
                // Create a new Accession record iff there is at least one valid Lot to connect to it
                if( !$kfrA )  $kfrA = $this->addAccession_createAcc($kPCV, $kfrLotParent, $parms);

                $kfrI = $this->oSLDB->GetKFRel('I')->CreateRecord();
                $kfrI->SetValue('fk_sl_collection', $kColl);
                $kfrI->SetValue('fk_sl_accession', $kfrA->Key());
                $kfrI->SetValue('g_weight', $g);
                $kfrI->SetValue('location', $loc);
                $kfrI->SetValue('dCreation', date('Y-m-d'));     // this is the same as _created modulo timezone

                // sl_collection keeps the next inv_number. Increment and reload kfrC for the loop
                $kfrI->SetValue('inv_number', $kfrC->Value('inv_counter'));
                $this->oApp->kfdb->Execute("UPDATE sl_collection SET inv_counter=inv_counter+1 WHERE _key='$kColl'");
                $kfrC = $this->oSLDB->GetKFR('C', $kColl);

                if( $kfrI->PutDBRow() ) {
                    $raOut['raILots'][$k] = $kfrI->Value('inv_number');
                }
            }
        }

        $ok = $raOut['raILots'][0] || $raOut['raILots'][1];     // success if either lot was created (0/blank input is ignored so not successful)

        done:
        return( [$ok,$raOut,$sErr] );
    }

    private function addAccession_createAcc( int $kPCV, ?KeyframeRecord $kfrLotParent, array $parms )
    {
        /* Create new Accession
         */
        $kfrA = $this->oSLDB->GetKFRel('A')->CreateRecord();
        $kfrA->SetValue('fk_sl_pcv',   $kPCV);
        $kfrA->SetValue('kLotParent',  $kfrLotParent ? $kfrLotParent->Key() : 0 );
        $kfrA->SetValue('g_original',  intval(@$parms['gtotal']));
        $kfrA->SetValue('g_100',       intval(@$parms['g100']));
        $kfrA->SetValue('oname',       @$parms['oname']);
        $kfrA->SetValue('x_member',    @$parms['sSupplier']);
        $kfrA->SetValue('x_d_harvest', @$parms['ySupplier']);
        $kfrA->SetValue('notes',       @$parms['sNotesAcc']);
        $kfrA->PutDBRow();

        return( $kfrA );
    }
}
