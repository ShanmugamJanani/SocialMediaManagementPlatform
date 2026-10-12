<?php

namespace app\components\youtube;

use Yii;
use yii\base\Component;
use Google\Client;
use Google\Service\YouTube;

class YouTubeService extends component
{
    private $youtube;

    public function __construct()
    {
        $client = new Client();
        $client->setDeveloperKey(Yii::$app->params['key']);
        $this->youtube = new YouTube($client);
    }

    /**
     * Get Popular Videos (Home Feed)
     */
    public function getPopularVideos($pageToken = null, $maxResults = 12)
    {
        try {
            $params = [
                'chart' => 'mostPopular',
                'maxResults' => $maxResults,
                'regionCode' => 'IN', // Set an explicit region code (e.g., 'US', 'IN', 'GB')
                'hl' => 'ta',
                'videoCategoryId' => '28',
            ];
            
            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            // Request 'snippet' first to keep it simple and reliable
            $response = $this->youtube->videos->listVideos('snippet', $params);

            return [
                'items' => $response->getItems(),
                'nextPageToken' => $response->getNextPageToken(),
            ];
        } catch (\Exception $e) {
            // Log the actual error message to runtime logs
            Yii::error('YouTube API Error: ' . $e->getMessage(), __METHOD__);
            
            // TEMPORARY: Print exception message to browser to debug quickly
            // echo $e->getMessage(); die();

            return ['items' => [], 'nextPageToken' => null];
        }
    }

    /**
     * Search Videos
     */
    public function searchVideos($query, $pageToken = null, $maxResults = 12)
    {
        try {
            $params = [
                'q' => $query,
                'type' => 'video',
                'maxResults' => $maxResults,
            ];
            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $response = $this->youtube->search->listSearch('snippet', $params);

            return [
                'items' => $response->getItems(),
                'nextPageToken' => $response->getNextPageToken(),
            ];
        } catch (\Exception $e) {
            Yii::error('YouTube API Error: ' . $e->getMessage(), __METHOD__);
            return ['items' => [], 'nextPageToken' => null];
        }
    }
}