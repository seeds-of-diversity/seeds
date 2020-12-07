<?php

/* mbrApp
 *
 * Copyright 2020 Seeds of Diversity Canada
 *
 * Structures for apps that manage memberships and donations
 */

class MbrApp
{
    // These define permissions for apps. The arrays double for SEEDSessionAccount and TabSetPermissions
    static $raAppPerms = [
        // the app that prints 3-up renewal and donation slips, and donation receipts
        'mbrPrint' =>
            [ 'renewalRequests'   => ['R MBR'],
               'donationRequests' => ['R MBRDonations', 'A MBR', '|'],
               'donationReceipts' => ['R MBRDonations', 'A MBR', '|'],
               'donations'        => ['W MBRDonations', 'A MBR', '|'],
               '|'  // allows screen-login even if some tabs are ghosted
            ],
    ];

}