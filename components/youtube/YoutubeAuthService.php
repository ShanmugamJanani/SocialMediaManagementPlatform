<?php

namespace app\components\youtube;

use Yii;
use Google\Client;
use Google\Service\YouTube;

class YouTubeOAuthService extends component
{
    private $client;

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setClientId($this->clientId);
        $this->client->setClientSecret($this->clientSecret);
        $this->client->setRedirectUri('YOUR_REDIRECT_URI');
        
        // Add scopes required for full channel management
        $this->client->addScope(YouTube::YOUTUBE); // Full access: upload, edit, delete
        $this->client->setAccessType('offline');   // Requests refresh token so you don't have to log in repeatedly
        $this->client->setPrompt('consent');
    }

    // 1. Get Google Login URL
    public function getAuthUrl()
    {
        return $this->client->createAuthUrl();
    }

    // 2. Exchange auth code for tokens after redirect
    public function authenticate($code)
    {
        $accessToken = $this->client->fetchAccessTokenWithAuthCode($code);
        Yii::$app->session->set('yt_access_token', $accessToken);
        return $accessToken;
    }

    // 3. Get YouTube Client authenticated with saved session token
    public function getYouTubeService()
    {
        $token = Yii::$app->session->get('yt_access_token');
        if ($token) {
            $this->client->setAccessToken($token);
            if ($this->client->isAccessTokenExpired()) {
                if ($this->client->getRefreshToken()) {
                    $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                }
            }
            return new YouTube($this->client);
        }
        return null;
    }
}