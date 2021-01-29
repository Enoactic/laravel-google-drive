<?php

namespace LaravelGoogleDrive;

use Exception;
use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;

class Drive
{
    public $client;

    public function __construct($applicationName, $redirectUri, $credentialPath, $tokenPath)
    {
        $client = new Google_Client();

        $client->setApplicationName($applicationName);
        $client->setRedirectUri($redirectUri);

        $client->setScopes(Google_Service_Drive::DRIVE);
        $client->setAuthConfig($credentialPath);
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');

        // Load previously authorized token from a file, if it exists.
        // The file token.json stores the user's access and refresh tokens, and is
        // created automatically when the authorization flow completes for the first
        // time.
        if (file_exists($tokenPath)) {
            $accessToken = json_decode(file_get_contents($tokenPath), true);
            $client->setAccessToken($accessToken);
        }

        // If there is no previous token or it's expired.
        if ($client->isAccessTokenExpired()) {
            // Refresh the token if possible, else fetch a new one.
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            } else {
                // Request authorization from the user.
                $authUrl = $client->createAuthUrl();
                printf("Open the following link in your browser:\n%s\n", $authUrl);
                print 'Enter verification code: ';
                $authCode = trim(fgets(STDIN));

                // Exchange authorization code for an access token.
                $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
                $client->setAccessToken($accessToken);

                // Check to see if there was an error.
                if (array_key_exists('error', $accessToken)) {
                    throw new Exception(join(', ', $accessToken));
                }
            }
            // Save the token to a file.
            if (!file_exists(dirname($tokenPath))) {
                mkdir(dirname($tokenPath), 0700, true);
            }
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
        }
        $this->client = $client;
    }

    public function getItems($query = null)
    {
        $service = new Google_Service_Drive($this->client);

        $parameters = array(
            'q' => $query,
            'fields' => 'id, name, webViewLink, webContentLink',
        );

        return $service->files->listFiles($parameters);
    }

    public function createFolder($folderName, $parentFolderId = null)
    {
        $duplicateFolder = $this->getItems("mimeType='application/vnd.google-apps.folder'
            ".(($parentFolderId) ? " and '$parentFolderId' in parents ": "")."
            and name='$folderName' 
            and trashed=false
        ");

        if(count($duplicateFolder) == 0){
            $service = new Google_Service_Drive($this->client);
            $folder = new Google_Service_Drive_DriveFile();

            $folder->setName($folderName);
            $folder->setMimeType('application/vnd.google-apps.folder');
            if(!empty($parentFolderId)){
                $folder->setParents([$parentFolderId]);
            }
            $item = $service->files->create($folder);
            $itemId = null;
            if(isset($item['id']) && !empty($item['id'])){
                $itemId = $item['id'];
            }
            return $itemId;
        }

        return $duplicateFolder[0]['id'];
    }

    public function createdFile( $filePath, $fileName, $parentFolderId = null)
    {
        $service = new Google_Service_Drive($this->client);
        $file = new Google_Service_Drive_DriveFile();

        $file->setName($fileName);

        if(!empty($parentFolderId)){
            $file->setParents([$parentFolderId]);
        }

        $item = $service->files->create(
            $file,
            array(
                'data' => file_get_contents($filePath),
                'mimeType' => 'application/octet-stream',
            )
        );

        $itemId = null;
        if(isset($item['id']) && !empty($item['id'])){
            $itemId = $item['id'];
        }
        return $itemId;
    }
}
