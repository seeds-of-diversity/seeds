<?php

/* SEEDGoogleService.php
 *
 * Copyright (c) 2018 Seeds of Diversity
 *
 * Help access Google services
 */

require_once( W_ROOT."os/google-api-php-client-2.2.1/vendor/autoload.php" );

class SEEDGoogleService
{
    public  $client;

    private $raParms;
    private $bCmdLine;

    function __construct( $raParms, $bCmdLine = false )
    {
        $this->raParms = $raParms;
        $this->bCmdLine = $bCmdLine;
        $this->client = $this->getClient( $raParms );
    }

    /**
     * Returns an authorized API client.
     * @return Google_Client the authorized client object
     *
     * $bCmdLine : true = designed to be run on command line; false = designed to be run via browser
     */
    private function getClient( $raParms )
    {
        $client = new Google_Client();
        $client->setApplicationName( $raParms['application_name'] );
        $client->setScopes( $raParms['scopes'] );
        $client->setAuthConfig( $raParms['client_secret_file'] );
        $client->setAccessType('offline');

        $credentialsPath = $raParms['credentials_file'];

        // Load previously authorized credentials from a file.
        if (file_exists($credentialsPath)) {
            $accessToken = json_decode(file_get_contents($credentialsPath), true);
        } else {
            // Credentials have not been created so request them from the user (command line tool only)
            if( $this->bCmdLine ) {
                // Request authorization from the user.
                $authUrl = $client->createAuthUrl();
                printf("Open the following link in your browser:\n%s\n", $authUrl);
                print 'Enter verification code: ';
                $authCode = trim(fgets(STDIN));

                // Exchange authorization code for an access token.
                $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

                // Store the credentials to disk.
                if(!file_exists(dirname($credentialsPath))) {
                    mkdir(dirname($credentialsPath), 0700, true);
                }
                file_put_contents($credentialsPath, json_encode($accessToken));
                printf("Credentials saved to %s\n", $credentialsPath);
            } else {
                die( "Run getcreds.php to create the credentials file" );
            }
        }

        $client->setAccessToken($accessToken);

        // Refresh the token if it's expired.
        // This was designed for command line use - see getcreds - will not work via browser unless the directory and file have write permission for apache
        if ($client->isAccessTokenExpired()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            file_put_contents($credentialsPath, json_encode($client->getAccessToken()));
        }

        return $client;
    }

}

?>