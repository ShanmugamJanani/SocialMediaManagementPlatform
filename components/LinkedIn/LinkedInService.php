<?php
    namespace app\components;

    use Yii;
    use yii\base\Component;
    use yii\httpclient\Client;

    class LinkedInService extends Component
    {
        private $client;
        private $orgUrn;
        private $accessToken;

        public function init()
        {
            parent::init();
            $params = Yii::$app->params['linkedin'];
            $this->orgUrn = $params['organizationUrn'];
            $this->accessToken = $params['accessToken'];
            $this->client = Yii::$app->linkedinApi;
        }
        /**
     * Master Sync Function: Pushes local scheduled posts AND pulls remote updates from official LinkedIn app.
     *
     * @return array Summary of sync actions
     */
    public static function synchronize(): array
    {
        $linkedInService = Yii::createObject('app\components\LinkedInService');

        // -------------------------------------------------------------
        // Step A: Push local scheduled/pending posts to LinkedIn
        // -------------------------------------------------------------
        $pendingPosts = PendingPost::find()
            ->where(['status' => 'pending'])
            ->andWhere(['<=', 'scheduled_at', date('Y-m-d H:i:s')])
            ->all();

        $pushedCount = 0;
        foreach ($pendingPosts as $post) {
            $urn = $linkedInService->createCompanyPost($post->content, $post->link_url, $post->title);

            if ($urn) {
                $post->status = 'published';
                $post->linkedin_urn = $urn;
                $post->published_at = date('Y-m-d H:i:s');
                $post->save();
                $pushedCount++;
            } else {
                $post->status = 'failed';
                $post->save();
            }
        }

        // -------------------------------------------------------------
        // Step B: Pull posts/comments created via Official LinkedIn App
        // -------------------------------------------------------------
        $remotePosts = $linkedInService->getCompanyPosts(20);
        $importedCount = 0;

        foreach ($remotePosts as $remote) {
            $urn = $remote['id'] ?? null;
            if (!$urn) continue;

            // Check if post already exists locally
            $exists = LocalPost::find()->where(['linkedin_urn' => $urn])->exists();

            if (!$exists) {
                // Post was created on Official LinkedIn App -> Import into local database
                $localPost = new LocalPost();
                $localPost->linkedin_urn = $urn;
                $localPost->content = $remote['commentary'] ?? '';
                $localPost->status = 'published';
                $localPost->source = 'official_linkedin_app';
                $localPost->published_at = date('Y-m-d H:i:s', ($remote['createdAt'] ?? time() * 1000) / 1000);
                $localPost->save();

                $importedCount++;
            }
        }

        return [
            'pushed' => $pushedCount,
            'imported' => $importedCount,
        ];
    }

        /**
         * Create a text/link post on the LinkedIn Company Page
         */
        public function createCompanyPost(string $text, ?string $linkUrl = null, ?string $linkTitle = null)
        {
            $payload = [
                'author' => $this->orgUrn,
                'commentary' => $text,
                'visibility' => 'PUBLIC',
                'distribution' => [
                    'feedDistribution' => 'MAIN_FEED',
                    'targetEntities' => [],
                    'thirdPartyDistributionChannels' => [],
                ],
                'lifecycleState' => 'PUBLISHED',
                'isReshareDisabledByAuthor' => false,
            ];

            if ($linkUrl) {
                $payload['content'] = [
                    'article' => [
                        'source' => $linkUrl,
                        'title' => $linkTitle ?? $linkUrl,
                    ],
                ];
            }

            $response = $this->client->createRequest()
                ->setMethod('POST')
                ->setUrl('posts')
                ->addHeaders(['Authorization' => 'Bearer ' . $this->accessToken])
                ->setData($payload)
                ->send();

            if ($response->isOk) {
                return $response->headers->get('x-restli-id'); // Returns the Post URN
            }

            Yii::error('LinkedIn Post Creation Failed: ' . json_encode($response->data), __METHOD__);
            return false;
        }

        /**
         * Fetch recent posts from the LinkedIn Company Page for synchronization
         */
        public function getCompanyPosts(int $count = 10)
        {
            $response = $this->client->createRequest()
                ->setMethod('GET')
                ->setUrl('posts')
                ->addHeaders(['Authorization' => 'Bearer ' . $this->accessToken])
                ->setData([
                    'author' => $this->orgUrn,
                    'q' => 'author',
                    'count' => $count,
                ])
                ->send();

            if ($response->isOk) {
                return $response->data['elements'] ?? [];
            }

            Yii::error('LinkedIn Fetch Posts Failed: ' . json_encode($response->data), __METHOD__);
            return [];
        }
    }
?>