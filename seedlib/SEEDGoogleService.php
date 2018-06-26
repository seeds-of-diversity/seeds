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
    public  $client = null;

    private $raParms;
    private $sErrMsg = "";

    function __construct( $raParms, $bConstructClient = true )
    {
        $this->raParms = $raParms;
        if( $bConstructClient ) {
            // Probably the only time you wouldn't do this is when you're generating the credentials file (because getClient will fail without it)
            $this->client = $this->getClient( $raParms );
        }
    }

    function GetErrMsg()  { return( $this->sErrMsg ); }

    /**
     * Returns an authorized API client.
     * @return Google_Client the authorized client object
     */
    function GetClient()
    {
        if( !file_exists($this->raParms['client_secret_file']) ) {
            $this->sErrMsg = "SEEDGoogleService: you have to install the google-client-secret file to access Google API";
            goto done;
        }
        if( !file_exists($this->raParms['credentials_file']) ) {
            $this->sErrMsg = "Please generate credentials for this user's calendar using the Admin console";
            goto done;
        }

        $this->initClient();

        $accessToken = json_decode( file_get_contents($this->raParms['credentials_file']), true );

        $this->setAccess( $accessToken );

        done:
        return( $this->client );
    }

    function GetCredentials( $bCmdLine )
    /***********************************
        Credentials have not been created so request them from the user
     */
    {
        $this->initClient();

        // Request authorization from the user.
        $authUrl = $this->client->createAuthUrl();

        if( $bCmdLine ) {
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));
        } else {
            // get it another way
        }

        // Exchange authorization code for an access token.
        $accessToken = $this->client->fetchAccessTokenWithAuthCode( $authCode );

        // Store the credentials to disk.
//        if(!file_exists(dirname($credentialsPath))) {
//            mkdir(dirname($credentialsPath), 0700, true);
//        }
        file_put_contents( $this->raParms['credentials_file'], json_encode($accessToken) );
//        printf("Credentials saved to %s\n", $credentialsPath);

        $this->setAccess( $accessToken );
    }

    private function initClient()
    {
        if( ($this->client = new Google_Client()) ) {   // this will fail if you happen to be offline
            $this->client->setApplicationName( $this->raParms['application_name'] );
            $this->client->setScopes( $this->raParms['scopes'] );
            $this->client->setAuthConfig( $this->raParms['client_secret_file'] );   // provided by Google API to identify us for quota purposes
            $this->client->setAccessType('offline');
        }
    }

    private function setAccess( $accessToken )
    {
        $this->client->setAccessToken( $accessToken );

        // Refresh the token if it's expired.
        // This was designed for command line use - see getcreds - will not work via browser unless the directory and file have write permission for apache
        if( $this->client->isAccessTokenExpired() ) {
            $this->client->fetchAccessTokenWithRefreshToken( $this->client->getRefreshToken() );
            file_put_contents($this->raParms['credentials_file'], json_encode($this->client->getAccessToken()) );
        }
    }
}

?>