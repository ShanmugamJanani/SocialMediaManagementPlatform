<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\UploadedFile;
use yii\helpers\Url;
use app\models\SocialAccount;
use app\models\YoutubeProfile;
use Google\Client;
use Google\Service\YouTube;

class YoutubeController extends Controller
{
    // 1. Helper to setup Google Client
    private function getGoogleClient()
    {
        $client = new Client();
        $client->setClientId('YOUR_CLIENID');
        $client->setClientSecret('YOUR_SECRET_CODE');
        $client->setRedirectUri('YOUR_REDIRECT_URI');
        $client->addScope(YouTube::YOUTUBE);
        
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return $client;
    }

    // 2. Gets a valid, unexpired Google Client for the active YouTube account
    private function getAuthenticatedClient()
    {
        $userId = Yii::$app->user->id;

        $account = SocialAccount::findOne([
            'platform_type' => 'youtube', 
            'active_status' => 1
        ]);

        if (!$account) {
            return null;
        }

        // Decode JSON platform_data into array
        $platformData = is_array($account->platform_data) 
            ? $account->platform_data 
            : json_decode($account->platform_data ?? '{}', true);

        if (!is_array($platformData)) {
            $platformData = [];
        }

        $client = $this->getGoogleClient();
        $client->setAccessToken($account->access_token);

        // Check if token expired (with a 60-second buffer)
        $createdAt = $platformData['token_created_at'] ?? 0;
        $expiresIn = $platformData['token_expires_in'] ?? 3600;
        $isExpired = (time() - $createdAt) >= ($expiresIn - 60);

        if ($isExpired) {
            $refreshToken = $platformData['refresh_token'] ?? null;

            if (!$refreshToken) {
                $account->active_status = 0;
                $account->save(false);
                return null;
            }

            $newToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);

            if (isset($newToken['error'])) {
                Yii::error('YouTube Token Refresh Error: ' . json_encode($newToken), 'youtube');
                $account->active_status = 0;
                $account->save(false);
                return null;
            }

            $account->access_token = $newToken['access_token'];
            $platformData['token_created_at'] = $newToken['created'] ?? time();
            $platformData['token_expires_in'] = $newToken['expires_in'] ?? 3600;

            $account->platform_data = json_encode($platformData);
            $account->save(false);

            $client->setAccessToken($newToken);
        }

        return $client;
    }

    // 3. Connect Action
    public function actionConnectYoutube()
    { 
        $client = $this->getGoogleClient();
        return $this->redirect($client->createAuthUrl());
    }

    // 4. Callback Action (Saves tokens to SocialAccount table)
    public function actionYoutubeCallback($code = null)
    {
        if (!$code) {
            Yii::$app->session->setFlash('error', 'Google authorization failed.');
            return $this->redirect(['home/index']);
        }

        $client = $this->getGoogleClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            Yii::$app->session->setFlash('error', 'Failed to retrieve access token.');
            return $this->redirect(['home/index']);
        }

        $client->setAccessToken($token);
        $youtube = new \Google\Service\YouTube($client);

        try {
            // Fetch extended channel details (snippet + statistics)
            $response = $youtube->channels->listChannels('id,snippet,statistics', ['mine' => true]);

            if (empty($response->getItems())) {
                Yii::$app->session->setFlash('error', 'No YouTube channel found for this Google account.');
                return $this->redirect(['home/index']);
            }

            $channel     = $response->getItems()[0];
            $youtubeId   = $channel->getId();
            $snippet     = $channel->getSnippet();
            $statistics  = $channel->getStatistics();
            $channelName = $snippet->getTitle();

            // Extract high-resolution channel profile picture
            $thumbnails = $snippet->getThumbnails();
            $avatarUrl  = null;
            if ($thumbnails) {
                $avatarUrl = $thumbnails->getHigh() ? $thumbnails->getHigh()->getUrl() : 
                            ($thumbnails->getMedium() ? $thumbnails->getMedium()->getUrl() : 
                            ($thumbnails->getDefault() ? $thumbnails->getDefault()->getUrl() : null));
            }

            // Calculate token expiry timestamp
            $expiresInSeconds = $token['expires_in'] ?? 3600;
            $tokenExpiresAt   = date('Y-m-d H:i:s', time() + $expiresInSeconds);

            $userId = Yii::$app->user->id;

            // Find existing channel account or create a new model instance
            $account = SocialAccount::findOne([
                'platform_id'   => $youtubeId,
                'platform_type' => 'youtube',
            ]) ?: new SocialAccount();

            // Standard core columns
            $account->platform_id   = $youtubeId;
            $account->platform_type = 'youtube';
            $account->account_name  = $channelName;
            $account->access_token  = $token['access_token'];
            $account->active_status = '1';

            // Decode existing platform_data if available
            $existingData = is_array($account->platform_data) 
                ? $account->platform_data 
                : json_decode($account->platform_data ?? '{}', true);

            if (!is_array($existingData)) {
                $existingData = [];
            }

            // Build comprehensive JSON payload for platform_data
            $platformData = array_merge($existingData, [
                'channel_id'       => $youtubeId,
                'channel_name'     => $channelName,
                'custom_url'       => $snippet->getCustomUrl() ?? null, // Channel @handle
                'description'      => $snippet->getDescription() ?? null,
                'profile_pic_url'  => $avatarUrl, // Standardized for frontend profile cards
                'avatar_url'       => $avatarUrl,
                'subscriber_count' => $statistics ? $statistics->getSubscriberCount() : 0,
                'view_count'       => $statistics ? $statistics->getViewCount() : 0,
                'video_count'      => $statistics ? $statistics->getVideoCount() : 0,
                'token_expires_at' => $tokenExpiresAt,
                'token_created_at' => time(),
                'connected_at'     => date('Y-m-d H:i:s'),
            ]);

            // Preserve Google Refresh Token if provided in callback
            if (isset($token['refresh_token'])) {
                $platformData['refresh_token'] = $token['refresh_token'];
            }

            $account->platform_data = json_encode($platformData);

            if (!$account->save()) {
                Yii::error('YouTube Account Save Errors: ' . json_encode($account->getErrors()), 'youtube');
                Yii::$app->session->setFlash('error', 'Failed to save YouTube account.');
            } else {
                Yii::$app->session->setFlash('success', 'Connected to YouTube Channel: ' . $channelName);
            }

        } catch (\Exception $e) {
            Yii::error('YouTube OAuth Exception: ' . $e->getMessage(), 'youtube');
            Yii::$app->session->setFlash('error', 'YouTube connection error: ' . $e->getMessage());
        }

        return $this->redirect(['home/index']);
    }

    // 5. YouTube Dashboard Action
    public function actionIndex()
    {
        $this->layout = 'main';
        $client = $this->getAuthenticatedClient();

        if (!$client) {
            return $this->render('connect');
        }

        try {
            $youtube = new YouTube($client);

            $channels = $youtube->channels->listChannels('snippet,statistics', ['mine' => true]);
            $channel = $channels->getItems()[0] ?? null;

            $videos = [];
            if ($channel) {
                $searchResponse = $youtube->search->listSearch('snippet', [
                    'forMine'     => true,
                    'type'        => 'video',
                    'order'       => 'date',
                    'maxResults'  => 6
                ]);
                $videos = $searchResponse->getItems();
            }

            return $this->render('dashboard', [
                'channel' => $channel,
                'videos'  => $videos,
            ]);

        } catch (\Exception $e) {
            Yii::error($e->getMessage(), 'youtube');
            return $this->render('connect');
        }
    }

    // 6. Disconnect Action
    public function actionDisconnectYoutube()
    {
        $account = SocialAccount::findOne([
            'platform_type' => 'youtube', 
            'active_status' => 1
        ]);

        if ($account) {
            try {
                if ($account->access_token) {
                    $client = $this->getGoogleClient();
                    $client->revokeToken($account->access_token);
                }
            } catch (\Exception $e) {
                Yii::error('Revoke token failed: ' . $e->getMessage(), 'youtube');
            }

            $account->active_status = 0;
            $account->save(false);
            
            Yii::$app->session->setFlash('success', 'Your YouTube channel has been disconnected.');
        }

        return $this->redirect(['/home/index']);
    }

    // 7. Channel Customization Action
    public function actionChannelCustomize()
    {
        $this->layout = 'main';
        $client = $this->getAuthenticatedClient();

        if (!$client) {
            return $this->redirect(['connect-youtube']);
        }

        $youtube = new YouTube($client);
        $model = new YoutubeProfile();

        try {
            $channels = $youtube->channels->listChannels('snippet,brandingSettings', ['mine' => true]);
            $channel = $channels->getItems()[0] ?? null;

            if (!$channel) {
                Yii::$app->session->setFlash('error', 'Channel not found.');
                return $this->redirect(['index']);
            }

            if (!Yii::$app->request->isPost) {
                $snippet = $channel->getSnippet();
                $branding = $channel->getBrandingSettings();
                $channelBranding = $branding ? $branding->getChannel() : null;

                $model->description = $channelBranding ? $channelBranding->getDescription() : ($snippet ? $snippet->getDescription() : '');
                $model->country = $channelBranding ? $channelBranding->getCountry() : '';
                $model->keywords = $channelBranding ? $channelBranding->getKeywords() : '';
                $model->profile_image = ($snippet && $snippet->getThumbnails() && $snippet->getThumbnails()->getDefault()) 
                    ? $snippet->getThumbnails()->getDefault()->getUrl() 
                    : null;
            }
        } catch (\Exception $e) {
            Yii::error($e->getMessage(), 'youtube');
            Yii::$app->session->setFlash('error', 'Failed to fetch channel details: ' . $e->getMessage());
            return $this->redirect(['index']);
        }

        if ($this->request->isPost) {
            $model->load(Yii::$app->request->post());
            $model->banner_image = UploadedFile::getInstance($model, 'banner_image');

            if ($model->validate()) {
                try {
                    $bannerExternalUrl = null;
                    if ($model->banner_image) {
                        $bannerResource = new \Google\Service\YouTube\ChannelBannerResource();
                        $insertedBanner = $youtube->channelBanners->insert($bannerResource, [
                            'data' => file_get_contents($model->banner_image->tempName),
                            'mimeType' => $model->banner_image->type,
                            'uploadType' => 'multipart',
                        ]);
                        $bannerExternalUrl = $insertedBanner->getUrl();
                    }

                    $brandingSettings = $channel->getBrandingSettings() ?: new \Google\Service\YouTube\ChannelBrandingSettings();
                    $channelSettings = $brandingSettings->getChannel() ?: new \Google\Service\YouTube\MetaData();

                    $channelSettings->setDescription($model->description);
                    $channelSettings->setKeywords($model->keywords);
                    $channelSettings->setCountry(strtoupper($model->country));
                    $brandingSettings->setChannel($channelSettings);

                    if ($bannerExternalUrl) {
                        $imageSettings = $brandingSettings->getImage() ?: new \Google\Service\YouTube\ImageSettings();
                        $imageSettings->setBannerExternalUrl($bannerExternalUrl);
                        $brandingSettings->setImage($imageSettings);
                    }

                    $channelUpdate = new \Google\Service\YouTube\Channel();
                    $channelUpdate->setId($channel->getId());
                    $channelUpdate->setBrandingSettings($brandingSettings);

                    $youtube->channels->update('brandingSettings', $channelUpdate);

                    Yii::$app->session->setFlash('success', 'Channel customization updated successfully!');
                    return $this->refresh();

                } catch (\Exception $e) {
                    Yii::error($e->getMessage(), 'youtube');
                    Yii::$app->session->setFlash('error', 'Update failed: ' . $e->getMessage());
                }
            }
        }

        return $this->render('customize', [
            'model'   => $model,
            'channel' => $channel,
        ]);
    }
}