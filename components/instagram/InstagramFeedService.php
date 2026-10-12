<?php 
namespace app\components\instagram;

use yii\base\Component;
use yii\httpclient\Client;
use Yii;
use app\models\InstagramChatLog; 

class InstagramFeedService extends Component
{
    public $instaId;
    public $instaAccId;
    public $instaAccessToken;
    public $version = 'v25.0';
    private $client;


    public function init()
    {
        parent::init();

        $this->client = new Client([
            'baseUrl' => "https://graph.facebook.com/{$this->version}",
        ]);

        // Read directly from Yii::$app->params at runtime
        if (isset(Yii::$app->params['instaAccountId'])) {
            $this->instaAccId = Yii::$app->params['instaAccountId'];
        }

        if (isset(Yii::$app->params['instaAccessToken'])) {
            $this->instaAccessToken = Yii::$app->params['instaAccessToken'];
        }
    }

    /**
     * Feature 1: Publish an Image Post to your Instagram Feed & log it
     */
    public function createPost(string $imageUrl, string $caption, string $filePath)
    {
        // Step 1: Create Media Container
        $containerResponse = $this->client->post("/{$this->instaAccId}/media", [
            'image_url' => $imageUrl,
            'caption' => $caption,
            'access_token' => $this->instaAccessToken
        ])->send();

        if (!$containerResponse->isOk) {
            Yii::error("IG Container Creation Failed: " . json_encode($containerResponse->data), 'instagram feed service');
            return false;
        }

        $containerId = $containerResponse->data['id'] ?? null;

        // Step 2: Publish Container
        $publishResponse = $this->client->post("/{$this->instaAccId}/media_publish", [
            'creation_id' => $containerId,
            'access_token' => $this->instaAccessToken
        ])->send();

        if ($publishResponse->isOk) {
            $postId = $publishResponse->data['id'] ?? null;

            // Log the newly created post into your unified table
            $this->logActivity(
                $this->instaAccId, 
                $caption, 
                $filePath,
                'outbound', 
                $postId, 
                'POST'
            );

            return $postId;
        }

        return false;
    }


    function adjustImageForInstagram($sourcePath, $outputPath) {
        // 1. Get current dimensions
        list($width, $height, $type) = getimagesize($sourcePath);
        if (!$width || !$height) return false;

        $currentRatio = $width / $height;
        
        // Instagram boundaries
        $minRatio = 0.8;  // 4:5 Portrait
        $maxRatio = 1.91; // 1.91:1 Landscape

        $newWidth = $width;
        $newHeight = $height;

        // 2. Determine if padding is needed
        if ($currentRatio > $maxRatio) {
            // Image is too wide (like your 1942x809 image). Increase height.
            $newHeight = round($width / $maxRatio);
        } elseif ($currentRatio < $minRatio) {
            // Image is too tall. Increase width.
            $newWidth = round($height * $minRatio);
        } else {
            // Image is already perfect! Just copy or move it.
            return copy($sourcePath, $outputPath);
        }

        // 3. Load the source image based on file type
        switch ($type) {
            case IMAGETYPE_JPEG: $sourceImg = imagecreatefromjpeg($sourcePath); break;
            case IMAGETYPE_PNG:  $sourceImg = imagecreatefrompng($sourcePath); break;
            case IMAGETYPE_WEBP: $sourceImg = imagecreatefromwebp($sourcePath); break;
            default: return false; // Unsupported type
        }

        // 4. Create a new blank canvas with the target dimensions
        $canvas = imagecreatetruecolor($newWidth, $newHeight);

        // 5. Define a padding color (e.g., White or Black)
        // You can change 255, 255, 255 to 0, 0, 0 for black bars
        $backgroundColor = imagecolorallocate($canvas, 255, 255, 255); 
        imagefill($canvas, 0, 0, $backgroundColor);

        // 6. Calculate centering offsets
        $dstX = round(($newWidth - $width) / 2);
        $dstY = round(($newHeight - $height) / 2);

        // 7. Copy original image onto the padded canvas
        imagecopy($canvas, $sourceImg, $dstX, $dstY, 0, 0, $width, $height);

        // 8. Save the padded image back to disk
        switch ($type) {
            case IMAGETYPE_JPEG: imagejpeg($canvas, $outputPath, 90); break; // 90 = quality
            case IMAGETYPE_PNG:  imagepng($canvas, $outputPath); break;
            case IMAGETYPE_WEBP: imagewebp($canvas, $outputPath, 90); break;
        }

        // Free up memory
        imagedestroy($sourceImg);
        imagedestroy($canvas);

        return true;
    }


    /**
     * Programmatically Like an Instagram Media object (Post, Carousel, Reel)
     */
    public function likePost($mediaId)
    {
        $url = "https://graph.facebook.com/{$this->version}/{$mediaId}/likes";
        
        // We pass an empty array payload because the endpoint just needs a bare POST trigger
        $result = $this->executeCurl($url, [], true, 'POST');
        
        return $result['success'] ?? false;
    }

    public function unlikePost($mediaId)
    {
        $url = "https://graph.facebook.com/{$this->version}/{$mediaId}/likes";
        
        $result = $this->executeCurl($url, [], true, 'DELETE');
        
        return $result['success'] ?? false;
    }

    /**
     * Programmatically Delete an Instagram Comment
     */
    public function deleteComment($commentId)
    {
        
        $url = "https://graph.facebook.com/{$this->version}/{$commentId}";
        
        // Why: Explicitly declaring 'DELETE' switches the custom curl request verb
        $result = $this->executeCurl($url, [], true, 'DELETE');
        
        return $result['success'] ?? false;
    }

    /**
     * Feature 2: Reply directly to a user's comment & log it
     */
    public function replyToComment(string $commentId, string $message)
    {
        $response = $this->client->post("/{$commentId}/replies", [
            'message' => $message,
            'access_token' => $this->instaAccessToken
        ])->send();

        if (!$response->isOk) {
            Yii::error("IG Comment Reply Failed: " . json_encode($response->data), 'instagram feed service');
            return false;
        }

        $replyCommentId = $response->data['id'];

        // Log your outgoing reply comment into your unified table
        $this->logActivity(
            $this->instaAccId, 
            $message, 
            '',
            'outbound', 
            $replyCommentId, 
            'COMMENT'
        );

        return $response->data;
    }

    /**
     * Feature 3: Process real-time inbound Comments/Feeds from Webhook
     */
    public function processFeedWebhook(array $payload)
    {
        $entries = $payload['entry'] ?? [];
        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                
                if (($change['field'] ?? '') === 'instagram_comments') {
                    $commentData = $change['value'] ?? [];
                    
                    $commentId   = $commentData['id'] ?? null;
                    $messageBody = $commentData['text'] ?? '';
                    $username    = $commentData['from']['username'] ?? null;
                    $platformUid = $commentData['from']['id'] ?? null; 

                    if (!$commentId || !$platformUid) {
                        continue;
                    }

                    // Save customer contact identity session
                    $this->ensureContactSession($platformUid, 'INSTAGRAM_FEED');

                    // Save incoming comment into unified table
                    $this->logActivity(
                        $platformUid, 
                        $messageBody, 
                        '',
                        'inbound', 
                        $commentId, 
                        'COMMENT'
                    );
                }
            }
        }
    }

    /**
     * Helper to insert records cleanly into your unified table structure
     */
    private function logActivity($platformUid, $body, $url=null, $direction, $metaId, $category)
    {
        $log = InstagramChatLog::find()->where(['platform_uid' => $metaId]) ?? null;
        if($log == null)
        {   $log = new InstagramChatLog(); 
            $log->platform_uid = $platformUid;
            $log->message_body = $body;
            $log->url = $url;
            $log->direction = $direction;
            $log->type = 'text';
            $log->meta_message_id = $metaId;
            $log->category = $category; // DM, COMMENT, or POST
            $log->created_at = date('Y-m-d H:i:s');
            $log->save();
        }
    }

    private function ensureContactSession($platformUid, $platformType)
    {
        $contact = \app\models\SocialContact::find()
            ->where(['platform_uid' => $platformUid, 'platform' => $platformType])
            ->one();

        if (!$contact) {
            $contact = new \app\models\SocialContact();
            $contact->platform_uid = $platformUid;
            $contact->platform = $platformType;
            $contact->bot_state = 'initial_state';
            $contact->save();
        }
    }

        
    private function saveInstagramMedia($mediaUrl, $postId, $extension = 'jpg')
    {
        $uploadDir = Yii::getAlias('@webroot') . '/uploads/';

        // Ensure directory exists with writable permissions
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                Yii::error("Failed to create upload directory: " . $uploadDir, 'instagram_sync');
                return null;
            }
        }

        $fileName = 'ig_sync_' . $postId . '.' . $extension;
        $filePath = $uploadDir . $fileName;

        // Use cURL instead of file_get_contents to handle HTTPS & User-Agent requirement
        $ch = curl_init($mediaUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_TIMEOUT        => 30,
        ]);

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || empty($content)) {
            Yii::error("Unable to download Instagram media (HTTP {$httpCode}). Error: {$curlError} | URL: {$mediaUrl}", 'instagram_sync');
            return null;
        }

        // Save file to disk
        if (file_put_contents($filePath, $content) === false) {
            Yii::error("Failed to write image file to disk at: " . $filePath, 'instagram_sync');
            return null;
        }

        return '/uploads/' . $fileName;
    }

    /**
     * Soft-deletes instagram Feed Posts or Comments
     */
    public function deletedChangeEvent($change)
    {
        $value = $change['value'] ?? [];
        $commentId = $value['comment_id'] ?? null;
        $postId = $value['post_id'] ?? null;

        if (!$commentId && !$postId) return false;

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $log = null;
            if ($commentId) {
                $log = InstagramChatLog::findOne(['meta_message_id' => $commentId, 'deleted_status' => 0]);
            } elseif ($postId) {
                $log = InstagramChatLog::findOne(['meta_message_id' => $postId, 'deleted_status' => 0]);
            }

            if ($log) {
                $log->deleted_status = 1;
                $log->deleted_at = date('Y-m-d H:i:s');
                $log->save(false);
            } else {
                // Create a stub log so the delete status is preserved
                $log = new InstagramChatLog();
                if($commentId){
                    $log->meta_message_id = $commentId;
                }
                elseif($postId){
                    $log->meta_message_id = $postId;
                }
                
                $log->deleted_status = 1;
                $log->deleted_at = date('Y-m-d H:i:s');
                $log->save(false);
            }

            $transaction->commit();
            return true;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error("Instagram feed soft-delete failed: " . $e->getMessage(), 'instagram_webhook');
            return false;
        }
    }

    public function getCommentsForMedia($mediaId)
    {
        $accessToken = $this->instaAccessToken; 

        $this->syncLiveComments($mediaId);

        return InstagramChatLog::find()
            ->where(['meta_id_of_parent_msg' => $mediaId, 'deleted_status' => 0])
            ->orderBy(['created_at' => SORT_ASC])
            ->asArray()
            ->all();
    }

    /**
     * Unified background outbound curl structural wrapper execution engine
     */
    private function executeCurl($url, $payload = [], $asJson = true, $method = 'POST')
    {
        // Append access token to URL if not present
        if (strpos($url, 'access_token=') === false && !empty($this->instaAccessToken)) {
            $delimiter = (strpos($url, '?') !== false) ? '&' : '?';
            $url .= $delimiter . 'access_token=' . urlencode($this->instaAccessToken);
        }

        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        //Dynamically set the request verb instead of hardcoding POST
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        // Handle payload if present (DELETE and GET requests don't typically send bodies)
        if (!empty($payload)) {
            if ($asJson) {
                $jsonData = json_encode($payload);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->instaAccessToken
                ]);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $this->instaAccessToken
                ]);
            }
        } else {
            // Safe fallback headers for empty payload calls (Like & Delete requests)
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->instaAccessToken
            ]);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMsg = $result['error']['message'] ?? 'Unknown Meta API Error';
            throw new \Exception("Meta Graph API Error (HTTP $httpCode): " . $errorMsg);
        }

        return $result;
    }

    public function processCommentWebhook($data)
    {       
        // Extract required attributes from Meta's comment webhook package
        $commentId = $data['id'] ?? null;
        $mediaId = $data['media']['id'] ?? null;
        $text = $data['text'] ?? '';
        $commenterId = $data['from']['id'] ?? null;
        $username = $data['from']['username'] ?? 'AnonymousUser';

        if (!$commentId || !$mediaId || !$commenterId) {
            Yii::warning("Instagram comment missing required metadata (sender, comment, or media ID)." . json_encode($data), 'instagram_webhook');
            return;
        }

        // Check if comment already exists locally to avoid duplication
        $exists = InstagramChatLog::find()->where(['meta_message_id' => $commentId])->exists();
        if ($exists){
            $model = InstagramChatLog::find()->where(['meta_message_id' => $commentId])->one();
            $model->meta_id_of_parent_msg = $data['parent_id'] ?? $data['media']['id'];
            $model->commenter_username = ($commenterId == $this->instaAccId) ? 'You' : $username;
            if(!$model->save()){
                Yii::error("Failed to update comment reply: " . json_encode($model->getErrors()), 'instagram_webhook');
                return;
            }
            return;
        }

        // Map and save to your local database logging system
        $model = new InstagramChatLog();
        $model->meta_message_id = $commentId;
        $model->platform_uid = $commenterId;
        $model->commenter_username = ($commenterId == $this->instaAccId) ? 'You' : $username;
        $model->meta_id_of_parent_msg = $data['parent_id'] ?? $mediaId;
        $model->message_body = $text;
        $model->direction = ($commenterId == $this->instaAccId) ? 'outbound' : 'inbound';
        $model->category = 'COMMENT';
        $model->type = 'text';
        $model->created_at = date('Y-m-d H:i:s');
        if(!$model->save()){
            Yii::error("Failed to save inbound comment: " . json_encode($model->getErrors()), 'instagram_webhook');
            return;
        }
    }

    public function sendCommentToMedia($mediaId, $message)
    {
        $url = "https://graph.facebook.com/{$this->version}/{$mediaId}/comments";
        
        $payload = [
            'message' => $message
        ];

        return $this->executeCurl($url, $payload, true, 'POST');
    }
    
    

    /**
     * Full-state sync for Instagram Posts/Feed items.
     * Fetches media from Meta Graph API, adds new posts, updates changed captions, 
     * and soft-deletes posts removed directly on Instagram.
     */
    public function syncLivePosts()
    {
        $stats = [
            'added'   => 0,
            'updated' => 0,
            'deleted' => 0
        ];

        $livePostIds = [];

        try {
            // Query Meta Graph API for media published on this Instagram account
            // Fields retrieved: id, caption, media_type, media_url, permalink, timestamp
            $url = "https://graph.facebook.com/{$this->version}/{$this->instaAccId}/media?fields=id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,children{media_url,thumbnail_url}&limit=50";
                        
            while ($url) {
                $apiData = $this->executeCurl($url, [], true, 'GET');
                $posts = $apiData['data'] ?? [];

                foreach ($posts as $remote) {
                    $postId = $remote['id'] ?? null;
                    if (!$postId) continue;

                    $livePostIds[] = $postId;
                    $caption = $remote['caption'] ?? '';
                    $createdAt = isset($remote['timestamp']) 
                        ? date('Y-m-d H:i:s', strtotime($remote['timestamp'])) 
                        : date('Y-m-d H:i:s');

                    // Extract Media URL: Videos use thumbnail_url for preview cards
                    $mediaUrl = $remote['media_url'] ?? $remote['thumbnail_url'] ?? null;
                    
                    $local = InstagramChatLog::findOne([
                        'meta_message_id' => $postId, 
                        'category' => 'POST'
                    ]);

                    if (!$local) {
                        // ----------------------------------------------------
                        // 1. ADD NEW POST DISCOVERED ON INSTAGRAM
                        // ----------------------------------------------------
                        $model = new InstagramChatLog();
                        $model->meta_message_id = $postId;
                        $model->platform_uid = $this->instaAccId;
                        $model->message_body = $caption;

                        if (!empty($mediaUrl)) {
                            $localSavedUrl = $this->saveInstagramMedia($mediaUrl, $postId);
                            $model->url = $localSavedUrl ?? $mediaUrl; // Fallback if download fails
                        } else {
                            $model->url = null;
                        }

                        $model->direction = 'outbound';
                        $model->category = 'POST';
                        $model->type = strtolower($remote['media_type'] ?? 'image');
                        $model->deleted_status = 0;
                        $model->created_at = $createdAt;

                        if ($model->save(false)) {
                            $stats['added']++;
                        }
                    } else {
                        // ----------------------------------------------------
                        // 2. CHECK FOR CAPTION EDITS OR RESTORATIONS
                        // ----------------------------------------------------
                        $needsSave = false;

                        if ($local->message_body !== $caption) {
                            $local->message_body = $caption;
                            $local->updated_at = date('Y-m-d H:i:s');
                            $needsSave = true;
                        }

                        if ($local->deleted_status == 1) {
                            $local->deleted_status = 0;
                            $local->deleted_at = null;
                            $needsSave = true;
                        }

                        if ($needsSave && $local->save(false)) {
                            $stats['updated']++;
                        }
                    }
                }

                // Paginate through account media if needed
                $url = $apiData['paging']['next'] ?? null;
            }

            // ----------------------------------------------------
            // 3. MARK DELETED POSTS
            // ----------------------------------------------------
            $localActivePosts = InstagramChatLog::find()
                ->where(['category' => 'POST', 'deleted_status' => 0])
                ->all();

            foreach ($localActivePosts as $localPost) {
                if (!in_array($localPost->meta_message_id, $livePostIds)) {
                    $localPost->deleted_status = 1;
                    $localPost->deleted_at = date('Y-m-d H:i:s');
                    $localPost->save(false);
                    $stats['deleted']++;
                }
            }

        } catch (\Throwable $e) {
            Yii::error("Live Posts Sync Error: " . $e->getMessage(), 'instagram_sync');
        }

        return $stats;
    }

    /**
     * Full-state sync for Instagram Comments AND Nested Replies under a Media ID.
     * Handles Insertion, Deletion marking, and Text Modifications across all depth levels.
     */
    public function syncLiveComments($mediaId)
    {
        $stats = [
            'added'   => 0,
            'updated' => 0,
            'deleted' => 0
        ];

        $allLiveCommentIds = [];

        try {
            // 1. Query Meta Graph API for all top-level comments on the post
            $url = "https://graph.facebook.com/{$this->version}/{$mediaId}/comments?fields=id,text,from,created_time&limit=100";

            while ($url) {
                $apiData = $this->executeCurl($url, [], true, 'GET');
                $topComments = $apiData['data'] ?? [];

                foreach ($topComments as $remote) {
                    $topCommentId = $remote['id'] ?? null;
                    if (!$topCommentId) continue;

                    // Process top-level comment
                    $allLiveCommentIds[] = $topCommentId;
                    $this->processSingleCommentSync($remote, $mediaId, $stats);

                    // 2. Fetch and sync nested replies under this top-level parent comment
                    $replyIds = $this->syncCommentReplies($topCommentId, $stats);
                    $allLiveCommentIds = array_merge($allLiveCommentIds, $replyIds);
                }

                // Handle API Pagination for posts with large numbers of top-level comments
                $url = $apiData['paging']['next'] ?? null;
            }

            // 3. Mark deleted records for BOTH parent comments and replies under this Media
            // Fetch parent comments + child replies targeting this media context
            $localCommentIdsQuery = InstagramChatLog::find()
                ->select('meta_message_id')
                ->where(['category' => 'COMMENT', 'meta_id_of_parent_msg' => $mediaId]);

            $localActiveComments = InstagramChatLog::find()
                ->where(['category' => 'COMMENT', 'deleted_status' => 0])
                ->andWhere([
                    'or',
                    ['meta_id_of_parent_msg' => $mediaId], // Top-level comments
                    ['in', 'meta_id_of_parent_msg', $localCommentIdsQuery] // Replies attached to top-level comments
                ])
                ->all();

            foreach ($localActiveComments as $localComment) {
                if (!in_array($localComment->meta_message_id, $allLiveCommentIds)) {
                    $localComment->deleted_status = 1;
                    $localComment->deleted_at = date('Y-m-d H:i:s');
                    $localComment->save(false);
                    $stats['deleted']++;
                }
            }

        } catch (\Throwable $e) {
            Yii::error("Live Comment & Reply Sync Error [Media ID: {$mediaId}]: " . $e->getMessage(), 'instagram_sync');
        }

        return $stats;
    }

    /**
     * Helper: Syncs nested replies belonging to a parent comment node.
     */
    private function syncCommentReplies($parentCommentId, &$stats)
    {
        $liveReplyIds = [];
        $replyUrl = "https://graph.facebook.com/{$this->version}/{$parentCommentId}/replies?fields=id,text,from,created_time&limit=100";

        while ($replyUrl) {
            $replyData = $this->executeCurl($replyUrl, [], true, 'GET');
            $replies = $replyData['data'] ?? [];

            foreach ($replies as $remoteReply) {
                $replyId = $remoteReply['id'] ?? null;
                if (!$replyId) continue;

                $liveReplyIds[] = $replyId;
                // Target parent ID is set to $parentCommentId for nested replies
                $this->processSingleCommentSync($remoteReply, $parentCommentId, $stats);
            }

            $replyUrl = $replyData['paging']['next'] ?? null;
        }

        return $liveReplyIds;
    }

    /**
     * Helper: Core logic for Adding, Updating, or Restoring a single comment/reply record.
     */
    private function processSingleCommentSync($remoteData, $parentTargetId, &$stats)
    {
        $commentId = $remoteData['id'];
        $remoteText = $remoteData['text'] ?? '';
        $commenterId = $remoteData['from']['id'] ?? null;
        $username = $remoteData['from']['username'] ?? 'AnonymousUser';

        $local = InstagramChatLog::findOne(['meta_message_id' => $commentId]);

        if (!$local) {
            // ----------------------------------------------------
            // 1. NEW COMMENT OR REPLY DISCOVERED
            // ----------------------------------------------------
            $model = new InstagramChatLog();
            $model->meta_message_id = $commentId;
            $model->platform_uid = $commenterId;
            $model->commenter_username = ($commenterId == $this->instaAccId) ? 'You' : $username;
            $model->meta_id_of_parent_msg = $parentTargetId; // Can be Media ID OR Parent Comment ID
            $model->message_body = $remoteText;
            $model->direction = ($commenterId == $this->instaAccId) ? 'outbound' : 'inbound';
            $model->category = 'COMMENT';
            $model->type = 'text';
            $model->deleted_status = 0;
            $model->created_at = isset($remoteData['created_time']) 
                ? date('Y-m-d H:i:s', strtotime($remoteData['created_time'])) 
                : date('Y-m-d H:i:s');
            
            if ($model->save(false)) {
                $stats['added']++;
            }
        } else {
            // ----------------------------------------------------
            // 2. CHECK FOR TEXT EDITS OR RESTORATION
            // ----------------------------------------------------
            $needsSave = false;

            if ($local->message_body !== $remoteText) {
                $local->message_body = $remoteText;
                $local->updated_at = date('Y-m-d H:i:s');
                $needsSave = true;
            }

            // If previously marked as deleted but re-appeared on Meta
            if ($local->deleted_status == 1) {
                $local->deleted_status = 0;
                $local->deleted_at = null;
                $needsSave = true;
            }

            if ($needsSave && $local->save(false)) {
                $stats['updated']++;
            }
        }
    }

    public function getMediaFields($mediaId, $fields = ['like_count', 'comments_count', 'is_comment_enabled', 'permalink'])
    {
        $fieldsString = implode(',', $fields);

        $url = "https://graph.facebook.com/{$this->version}/{$mediaId}?fields={$fieldsString}";

        return $this->executeCurl($url, [], true, 'GET');
    }

    public function deletePost($postId)
    {
        // 1. Delete on Meta via Graph API (DELETE /{post_id})
        $url = "https://graph.facebook.com/{$this->version}/{$postId}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'access_token' => $this->instaAccessToken,
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        // Meta returns {"success": true} upon deletion
        $metaDeleted = ($httpCode === 200 && !empty($data['success']));

        if ($metaDeleted || $httpCode === 404) { 
            // 2. Soft-delete locally in DB
            $log = InstagramChatLog::findOne(['meta_message_id' => $postId]);
            if ($log) {
                $log->deleted_status = 1;
                $log->deleted_at = date('Y-m-d H:i:s');
                return $log->save();
            }
            return true;
        }

        Yii::error("Failed to delete Instagram Post {$postId}: " . json_encode($data), 'instagram_feed');
        return false;
    }
}