<?php

/* mbrApp
 *
 * Copyright 2020-2022 Seeds of Diversity Canada
 *
 * Structures for apps that manage memberships and donations
 */

class MbrApp
{
    // These define permissions for apps. The arrays double for SEEDSessionAccount and TabSetPermissions
    static $raAppPerms = [
        // the app that manages contact info, logins, subscriptions
        'mbrContacts' =>
            [ 'contacts'    => ['W MBR'],
              'addcontacts' => ['W MBR'],
              'logins'      => ['W MBR'],
              'ebulletin'   => ['W BULL', 'A MBR', '|'],
              '|'  // allows screen-login even if some tabs are ghosted
            ],
        // the app that prints 3-up renewal and donation slips, and donation receipts
        'mbrPrint' =>
            [ 'renewalRequests'   => ['R MBR'],
              'donationRequests' => ['R MBRDonations', 'A MBR', '|'],
              'donationReceipts' => ['R MBRDonations', 'A MBR', '|'],
              'donationReceipts2' => ['R MBRDonations', 'A MBR', '|'],
              'donations'        => ['W MBRDonations', 'A MBR', '|'],
              'donationsSL'      => ['W MBRDonations', 'W SLAdopt', 'A MBR', 'A SL', '|'],
              '|'  // allows screen-login even if some tabs are ghosted
            ],
        // the app that connects MbrContacts with arbitrary google sheets
        'mbrGoogleSheets' =>
            [ 'dummy'   => ['W MBR'],
            ],
    ];
}