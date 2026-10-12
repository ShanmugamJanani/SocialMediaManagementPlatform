<?php 

namespace app\components;

use Yii;
use yii\base\Component;
use yii\httpclient\Client;
use app\models\FacebookChatLog;

use app\models\SocialContact;

class FacebookService extends Component
{
    public string $pageId;
    public string $pageAccessToken;
    public string $version = 'v25.0';

    private string $baseUrl = 'https://graph.facebook.com';
    private ?Client $_httpClient = null;

    public function init()
    {
        parent::init();

        // Read directly from Yii::$app->params at runtime
        if (isset(Yii::$app->params['fbPageId'])) {
            $this->pageId = Yii::$app->params['fbPageId'];
        }

        if (isset(Yii::$app->params['AccessToken'])) {
            $this->pageAccessToken = Yii::$app->params['AccessToken'];
        }
    }

    protected function getClient(): Client
    {
        if ($this->_httpClient === null) {
            $this->_httpClient = new Client([
                'baseUrl' => "{$this->baseUrl}/{$this->version}",
            ]);
        }
        return $this->_httpClient;
    }

    // =========================================================================
    // SECTION 1: INBOUND WEBHOOK PROCESSORS (From WebhookController)
    // =========================================================================

    /**
     * Processes inbound Messenger Direct Messages (DMs)
     */
    public function processMessageWebhook(array $messagingEvent): bool
    {
        $senderId = $messagingEvent['sender']['id'] ?? null;
        $message  = $messagingEvent['message'] ?? null;

        if (!$senderId || !$message) {
            return false;
        }

        // Ignore echo messages (messages sent by your page itself via Messenger UI)
        if (!empty($message['is_echo'])) {
            return true;
        }

        $messageId   = $message['mid'] ?? null;
        $text        = $message['text'] ?? null;
        $attachments = $message['attachments'] ?? [];

        // Handle attachments (images, stickers, audio, video)
        if (!empty($attachments)) {
            $firstAttachment = $attachments[0];
            
            // Extract attachment URL
            $body = $firstAttachment['payload']['url'] ?? json_encode($attachments);
            
            // Extract the exact attachment type from Meta (image, sticker, video, audio, file)
            $type = $firstAttachment['type'] ?? 'media';

            // Check if message is a Facebook Messenger Sticker specifically
            if (!empty($message['sticker_id']) || $type === 'image' && !empty($firstAttachment['payload']['sticker_id'])) {
                $type = 'sticker';
            }
        }
        
        // Extract parent message ID if the customer quoted a specific message in Messenger
        $replyToId   = $message['reply_to']['mid'] ?? null;

        // If message is media/attachment, extract the primary URL instead of dumping raw JSON
        $body = $text;
        if (empty($body) && !empty($attachments)) {
            $body = $attachments[0]['payload']['url'] ?? json_encode($attachments);
        }

        return $this->saveChatLog([
            'platform_uid'          => $senderId,
            'meta_message_id'       => $messageId,
            'meta_id_of_parent_msg' => $replyToId, // FIXED: Now captures customer replies!
            'sender_name'           => 'Customer',
            'message_body'          => $body,
            'direction'             => 'inbound',
            'category'              => 'DM',
            'type'                  => !empty($attachments) ? 'media' : 'text',
        ]);
    }

    /**
     * Processes inbound Comments on Page Posts
     */
    public function processCommentWebhook(array $value): bool
    {
        $verb      = $value['verb'] ?? null;
        $commentId = $value['comment_id'] ?? null;
        $parentId  = $value['parent_id'] ?? $value['post_id'] ?? null;

        if ($verb === 'add' && $commentId) {
            return $this->saveChatLog([
                'platform_uid'          => $value['from']['id'] ?? null,
                'meta_message_id'       => $commentId,
                'meta_id_of_parent_msg' => $parentId,
                'sender_name'           => $value['from']['name'] ?? 'User',
                'message_body'          => $value['message'] ?? '',
                'direction'             => 'inbound',
                'category'              => 'COMMENT',
                'type'                  => 'comment',
            ]);
        }

        return false;
    }

    /**
     * Soft-deletes Facebook Messenger Messages (DMs)
     */
    public function deletedMessageEvent(array $messagingEvent): bool
    {
        $messageId = $messagingEvent['unsend']['mid'] ?? null;
        if (!$messageId) return false;

        return $this->markAsDeleted($messageId);
    }

    // =========================================================================
    // SECTION 2: OUTBOUND GRAPH API ACTIONS (Send DMs, Reply, Post)
    // =========================================================================

    /**
     * Send a direct message back to a user via Messenger API with optional parent reply link
     */
    public function sendTextMessage(string $recipientId, string $text, ?string $parentMetaId = null, $isHumanAgent = false, &$sentPayload = null)
    {
        // If replying to a parent message, fetch parent text to format for native Messenger
        if (!empty($parentMetaId)) {
            $parentLog = FacebookChatLog::findOne(['meta_message_id' => $parentMetaId]);
            if ($parentLog) {
                $quotedSnippet = mb_strimwidth($parentLog->message_body, 0, 40, "...");
                $text = "↩️ Replying to: \"{$quotedSnippet}\"\n--------------------\n" . $text;
            }
        }

        $payload = [
                'access_token' => $this->pageAccessToken,
                'recipient' => ['id' => $recipientId],
                'message' => ['text' => $text],                
            ];

        if ($isHumanAgent) {
            $payload['messaging_type'] = 'MESSAGE_TAG';
            $payload['tag']            = 'HUMAN_AGENT';
        }

        $sentPayload = $payload;

        $response = $this->getClient()->post('me/messages', $payload)->send();

        if ($response->isOk) {
            $responseData = $response->data;

            $this->saveChatLog([
                'platform_uid'          => $recipientId,
                'meta_message_id'       => $responseData['message_id'] ?? null,
                'meta_id_of_parent_msg' => $parentMetaId,
                'sender_name'           => 'You',
                'message_body'          => $text,
                'direction'             => 'outbound',
                'connected_with'        => $this->pageId,
                'category'              => 'DM',
                'type'                  => 'text',
            ]);
            return true;
        }

        Yii::error("FB Send DM Error: " . $response->content, 'facebook_api');
        return false;
    }
    
    /**
     * Reply to a specific user comment on a Post
     */
    public function replyToComment(string $commentId, string $text): bool
    {
        $response = $this->getClient()->post("{$commentId}/comments", [
            'message'      => $text,
            'access_token' => $this->pageAccessToken,
        ])->send();

        if ($response->isOk) {
            $this->saveChatLog([
                'platform_uid'          => $this->pageId,
                'meta_message_id'       => $response->data['id'] ?? null,
                'meta_id_of_parent_msg' => $commentId,
                'sender_name'           => 'You',
                'message_body'          => $text,
                'direction'             => 'outbound',
                'category'              => 'COMMENT',
                'type'                  => 'comment',
            ]);
            return true;
        }

        Yii::error("FB Comment Reply Error: " . $response->content, 'facebook_api');
        return false;
    }

    
    /**
     * Create a Page Post with optional Photo attachment
     */
    public function createPagePostWithPhoto(string $message, ?string $imageFilePath = null): ?string
    {
        // If image attachment is provided, post to /{page_id}/photos endpoint
        if (!empty($imageFilePath) && file_exists($imageFilePath)) {
            $url = "https://graph.facebook.com/{$this->version}/{$this->pageId}/photos";

            $postFields = [
                'caption'      => $message,
                'access_token' => $this->pageAccessToken,
                'source'       => new \CURLFile($imageFilePath),
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POSTFIELDS     => $postFields,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $data = json_decode($response, true);

            if ($httpCode === 200 && !empty($data['id'])) {
                // Post ID or Photo ID returned by Graph API
                $postId = $data['post_id'] ?? $data['id'];

                // Relative path for local storage
                $relativeUrl = '/uploads/' . basename($imageFilePath);

                $this->saveChatLog([
                    'platform_uid'          => $this->pageId,
                    'meta_message_id'       => $postId,
                    'meta_id_of_parent_msg' => null,
                    'sender_name'           => 'You',
                    'message_body'          => $message,
                    'direction'             => 'outbound',
                    'category'              => 'POST',
                    'type'                  => 'photo',
                    'url'                   => $relativeUrl,
                ]);

                return $postId;
            }
             Yii::error("FB pageId: " . $this->pageId, 'facebook_webhook');
            Yii::error("FB Photo Post Error: " . json_encode($data), 'facebook_api');
            return null;
        }

        // Otherwise fallback to standard text post via createPagePost()
        return $this->createPagePost($message);
    }

    public function createPagePost(string $message): ?string
{
    $response = $this->getClient()->post("{$this->pageId}/feed", [
        'message'      => $message,
        'access_token' => $this->pageAccessToken,
    ])->send();

    if ($response->isOk && !empty($response->data['id'])) {
        $postId = $response->data['id'];
        $this->saveChatLog([
            'platform_uid'    => $this->pageId,
            'meta_message_id' => $postId,
            'sender_name'     => 'You',
            'message_body'    => $message,
            'direction'       => 'outbound',
            'category'        => 'POST',
            'type'            => 'text',
        ]);
        return $postId;
    }

    Yii::error("FB Text Post Error: " . $response->content, 'facebook_api');
    return null;
}

    // =========================================================================
    // SECTION 3: PRIVATE DATABASE HELPERS
    // =========================================================================

    /**
     * Marks an item as soft-deleted in the database
     */
    private function markAsDeleted(string $metaMessageId): bool
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $log = FacebookChatLog::findOne(['meta_message_id' => $metaMessageId, 'deleted_status' => 0]);

            if ($log) {
                $log->deleted_status = 1;
                $log->deleted_at     = date('Y-m-d H:i:s');
                $log->updated_at     = date('Y-m-d H:i:s');
                $log->save(false);
            } else {
                // Pre-created stub entry if 'unsend' webhook arrives before the creation webhook
                $log = new FacebookChatLog();
                $log->meta_message_id = $metaMessageId;
                $log->platform_uid    = 'UNKNOWN';
                $log->message_body    = '[Unsent Message]';
                $log->direction       = 'inbound';
                $log->category        = 'DM';                
                $log->connected_with  = $this->pageId;
                $log->type            = 'text';
                $log->deleted_status  = 1;
                $log->deleted_at      = date('Y-m-d H:i:s');
                $log->created_at      = date('Y-m-d H:i:s');
                $log->updated_at      = date('Y-m-d H:i:s');
                $log->save(false);
            }

            $transaction->commit();
            return true;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error("FB Soft-Delete Exception: " . $e->getMessage(), 'facebook_webhook');
            return false;
        }
    }

    /**
     * Unified insert helper aligned with table schema
     */
    private function saveChatLog(array $data): bool
    {
        try {
            $now = date('Y-m-d H:i:s');

            $log = new FacebookChatLog();
            $log->platform_uid          = $data['platform_uid'] ?? '';
            $log->connected_with         = $data['platform_uid'] ?? '';
            $log->meta_message_id       = $data['meta_message_id'] ?? '';
            $log->meta_id_of_parent_msg = $data['meta_id_of_parent_msg'] ?? null;
            $log->sender_name           = $data['sender_name'] ?? 'You';
            $log->message_body          = $data['message_body'] ?? '';
            $log->direction             = $data['direction'] ?? 'inbound';                      
            $log->connected_with        = $this->pageId;
            $log->category              = $data['category'] ?? 'DM';
            $log->type                  = $data['type'] ?? 'text';
            $log->url                   = $data['url'] ?? null;
            $log->deleted_status        = 0;
            $log->created_at            = $now;
            $log->updated_at            = $now;

            return $log->save(false);
        } catch (\Throwable $e) {
            Yii::error("FB Save Chat Log Exception: " . $e->getMessage(), 'facebook_webhook');
            return false;
        }
    }

    public function processFeedWebhook(array $value, ?string $pageId = null)
    {
        $item = $value['item'] ?? '';
        $verb = $value['verb'] ?? '';

        // Only process additions/edits here; deletions are handled via deletedChangeEvent()
        if ($verb === 'remove') {
            return;
        }
        switch ($item) {
            case 'post':
            case 'status':
            case 'photo':
            case 'video':
                $this->handlePostEvent($value, $pageId);
                break;

            case 'comment':
                $this->handleCommentEvent($value, $pageId);
                break;

            case 'reaction':
                $this->handleReactionEvent($value, $pageId);
                break;
            default:
                Yii::error("Unhandled feed item type: {$item}", 'facebook_feed');
                break;
        }
    }

    private function saveFbMedia($mediaUrl, $postId, $extension = 'jpg')
    {
        $uploadDir = Yii::getAlias('@webroot') . '/uploads/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = 'fb_sync_' . $postId . '.' . $extension;
        $filePath = $uploadDir . $fileName;

        // Use cURL with User-Agent header for reliable fetching from Meta CDN
        $ch = curl_init($mediaUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($content)) {
            Yii::error("Unable to download Facebook media URL: " . $mediaUrl, 'facebook_feed');
            return null;
        }

        file_put_contents($filePath, $content);

        return '/uploads/' . $fileName;
    }

    /**
     * Handles incoming Page Posts
     */
    protected function handlePostEvent(array $value, ?string $pageId)
    {
        $postId     = $value['post_id'] ?? null;
        $senderId   = $value['from']['id'] ?? $value['sender_id'] ?? $pageId;
        $senderName = $value['from']['name'] ?? $value['sender_name'] ?? 'Facebook User';
        $message    = $value['message'] ?? $value['post'] ?? '';        
        
        if (!$postId) return;

        $this->ensureContactSession($senderId, 'FACEBOOK');

        $log = FacebookChatLog::findOne(['meta_message_id' => $postId]) ?? new FacebookChatLog();
        
        $isOutbound = ($senderId === $this->pageId || $senderId === $pageId);

        $log->platform_uid          = $senderId;
        $log->meta_message_id       = $postId;
        $log->meta_id_of_parent_msg = null;
        $log->sender_name           = $senderName;
        $log->message_body          = $message;
        $log->connected_with        = $this->pageId;
        $log->direction             = $isOutbound ? 'outbound' : 'inbound';
        $log->category              = 'POST';
        $log->type                  = $value['item'] ?? 'photo';
        $log->created_at            = date('Y-m-d H:i:s', $value['created_time'] ?? time());
        $log->deleted_status        = 0;

        $item = $value['item'] ?? '';
        // Capture link from payload (photo URL or post link)
        $mediaUrl = $value['link'] ?? $value['photos'][0] ?? null;

        if (!empty($mediaUrl) && ($item === 'photo' || $item === 'video' || !empty($value['photo_id']) || !empty($value['video_id']))) {
            $ext = ($item === 'video' || !empty($value['video_id'])) ? 'mp4' : 'jpg';
            
            // Try saving locally
            $localPath = $this->saveFbMedia($mediaUrl, $postId, $ext);
            
            // Fallback directly to $mediaUrl if local saving fails
            $log->url = $localPath ?? $mediaUrl;
        }
        
        if (!$log->save()) {
            Yii::error("Failed to save FB Post log: " . json_encode($log->errors), 'facebook_feed');
        }
    }

    /**
     * Handles incoming reaction (Like)
     */
    protected function handleReactionEvent(array $value, ?string $pageId)
    {
        $postId   = $value['post_id'] ?? null;
        $senderId   = $value['from']['id'] ?? $value['sender_id'] ?? null;
        $senderName = $value['from']['name'] ?? $value['sender_name'] ?? 'Facebook User';
        $message    = $value['message'] ?? '';

        if (!$postId) return;

        $contact = $this->ensureContactSession($senderId, 'FACEBOOK');
        $contact->username = $senderName;

        $log = FacebookChatLog::findOne(['meta_message_id' => $postId]);
        $log->liked_by_me = 1;

        if (!$log->save()) {
            Yii::error("Failed to save FB reaction log: " . json_encode($log->errors), 'facebook_feed');
        }
    }

    /**
     * Handles incoming Comments and Nested Replies
     */
    protected function handleCommentEvent(array $value, ?string $pageId)
    {
        $commentId = $value['comment_id'] ?? null;
        if (!$commentId) return;

        // 1. If this is a hide/unhide webhook update event, update DB and STOP execution
        if (($value['verb'] ?? '') === 'hide' || ($value['verb'] ?? '') === 'unhide') {
            $log = FacebookChatLog::findOne(['meta_message_id' => $commentId]);
            if ($log) {
                $log->is_hidden = ($value['verb'] === 'hide') ? 1 : 0;
                $log->save(false);
            }
            return; // CRITICAL: Stop execution so message_body isn't wiped out!
        }

        $parentId   = $value['parent_id'] ?? $value['post_id'] ?? null;
        $senderId   = $value['from']['id'] ?? $value['sender_id'] ?? null;
        $senderName = $value['from']['name'] ?? $value['sender_name'] ?? 'Facebook User';
        $message    = $value['message'] ?? '';

        if (!$commentId) return;

        $this->ensureContactSession($senderId, 'FACEBOOK');

        $log = FacebookChatLog::findOne(['meta_message_id' => $commentId]) ?? new FacebookChatLog();

        $isOutbound = ($senderId === $this->pageId || $senderId === $pageId);

        $log->platform_uid          = $senderId;
        $log->meta_message_id       = $commentId;
        $log->meta_id_of_parent_msg = $parentId;
        $log->sender_name           = $senderName;
        $log->message_body          = $message;
        $log->connected_with        = $this->pageId;
        $log->direction             = $isOutbound ? 'outbound' : 'inbound';
        $log->category              = 'COMMENT';
        $log->type                  = 'text';
        $log->created_at            = date('Y-m-d H:i:s', $value['created_time'] ?? time());
        $log->deleted_status        = 0;

        if (!$log->save()) {
            Yii::error("Failed to save FB Comment log: " . json_encode($log->errors), 'facebook_feed');
        }
    }



    /**
     * Handles deleted comments or posts
     */
    public function deletedChangeEvent(array $change)
    {
        $value     = $change['value'] ?? [];
        $targetId  = $value['comment_id'] ?? $value['post_id'] ?? null;

        $item = $value['item'] ?? '';

        if (!$targetId) return;

        $log = FacebookChatLog::findOne(['meta_message_id' => $targetId]);


        // Only process additions/edits here; deletions are handled via deletedChangeEvent()
        switch ($item) {
            case 'post':
            case 'status':
            case 'photo':
            case 'video':
            case 'comment':
                
                if ($log) {
                    $log->deleted_status = 1;
                    $log->deleted_at     = date('Y-m-d H:i:s');
                    if (!$log->save(false)) {
                        Yii::error("Failed to save FB reaction log: " . json_encode($log->errors), 'facebook_feed');
                    }
                }
                break;

            case 'reaction':
                $log->liked_by_me = 0;
                if (!$log->save(false)) {
                    Yii::error("Failed to save FB reaction log: " . json_encode($log->errors), 'facebook_feed');
                }
                break;

            default:
                Yii::info("Unhandled feed item type: {$item}", 'facebook_feed');
                break;
        }
    }

    /**
     * Reply to a Post or Comment via Graph API
     * POST /{object-id}/comments
     */
    public function sendReply(string $targetMetaId, string $message): array
    {
        $url = "https://graph.facebook.com/{$this->version}/{$targetMetaId}/comments";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'message'      => $message,
                'access_token' => $this->pageAccessToken,
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode === 200 && !empty($data['id'])) {
            // Persist local outbound record
            $log = new FacebookChatLog();
            $log->platform_uid          = $this->pageId;
            $log->meta_message_id       = $data['id'];
            $log->meta_id_of_parent_msg = $targetMetaId;
            $log->sender_name           = 'You';
            $log->message_body          = $message;
            $log->direction             = 'outbound';          
            $log->connected_with        = $this->pageId;
            $log->category              = 'COMMENT';
            $log->type                  = 'text';
            $log->created_at            = date('Y-m-d H:i:s');
            $log->deleted_status        = 0;
            $log->save(false);

            return ['status' => 'success', 'comment_id' => $data['id']];
        }

        return ['status' => 'error', 'message' => $data['error']['message'] ?? 'Failed to send reply'];
    }

    private function ensureContactSession($platformUid, $platform)
    {
        // Find the contact by platform UID and platform type
        $contact = SocialContact::find()->where(['platform_uid' => $platformUid, 'platform' => $platform])->one();
        if (!$contact) {
            $contact = new SocialContact();
            $contact->platform_uid = $platformUid;
            $contact->bot_state = 'initial_state';
            $contact->connected_with = $this->pageId;
            $contact->platform = $platform;
            $contact->save();
        }
        return $contact;
    }

        /**
     * Helper to fetch direct CDN image URL from Meta Graph API
     */
    private function fetchPostFullPicture(string $postId): ?string
    {
        try {
            $accessToken = Yii::$app->params['AccessToken'] ?? null;
            if (!$accessToken) return null;

            $url = "https://graph.facebook.com/v25.0/{$postId}?fields=full_picture&access_token={$this->pageAccessToken}";
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($response, true);
            return $data['full_picture'] ?? null;
        } catch (\Exception $e) {
            Yii::error("Error fetching full_picture for {$postId}: " . $e->getMessage(), 'facebook_feed');
            return null;
        }
    }

    /**
     * Delete a Post from Meta Graph API and mark local record as deleted
     */
    public function deletePost(string $postId): bool
    {
        // 1. Delete on Meta via Graph API (DELETE /{post_id})
        $url = "https://graph.facebook.com/{$this->version}/{$postId}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'access_token' => $this->pageAccessToken,
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
            $log = FacebookChatLog::findOne(['meta_message_id' => $postId]);
            if ($log) {
                $log->deleted_status = 1;
                $log->deleted_at = date('Y-m-d H:i:s');
                return $log->save();
            }
            return true;
        }

        Yii::error("Failed to delete FB Post {$postId}: " . json_encode($data), 'facebook_feed');
        return false;
    }

    /**
     * Fetch Live Post Metrics (Likes & Comments summary) from Graph API
     */
    public function getPostSummary(string $postId): array
    {
        $url = "https://graph.facebook.com/{$this->version}/{$postId}?fields=reactions.summary(total_count),comments.summary(total_count)&access_token={$this->pageAccessToken}";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        return [
            'like_count'    => $data['reactions']['summary']['total_count'] ?? 0,
            'comment_count' => $data['comments']['summary']['total_count'] ?? 0,
        ];
    }


    public function fetchAndSyncLatestPosts($pageId = null, $accessToken = null)
    {
        $pageId = $pageId ?? $this->pageId;
        $accessToken = $accessToken ?? $this->pageAccessToken;

        if (empty($pageId) || empty($accessToken)) {
            Yii::error("Facebook Sync Error: Missing pageId or accessToken", 'facebook_feed');
            return false;
        }

        // Request 20 posts with full picture, author information, and top 10 nested comments
        $endpoint = "https://graph.facebook.com/{$this->version}/{$pageId}/published_posts?" . http_build_query([
            'fields' => 'id,message,created_time,full_picture,from,comments.limit(10){id,message,created_time,from}',
            'limit' => 20,
            'access_token' => $accessToken,
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        if (empty($data['data'])) {
            Yii::warning("Facebook Feed Sync: No posts returned or API error. Response: " . json_encode($data), 'facebook_feed');
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            foreach ($data['data'] as $postData) {
                $postId = $postData['id'];

                // 1. Save or Update Post record
                $post = FacebookChatLog::findOne(['meta_message_id' => $postId]) 
                        ?? new FacebookChatLog();

                $post->platform_uid    = $postData['from']['id'] ?? $pageId;
                $post->meta_message_id = $postId;
                $post->category        = 'POST';
                $post->sender_name     = $postData['from']['name'] ?? 'My Business';
                $post->message_body    = $postData['message'] ?? '';
                $post->direction       = ($post->platform_uid === $pageId) ? 'outbound' : 'inbound';
                $post->type            = !empty($postData['full_picture']) ? 'photo' : 'text';
                $post->connected_with  = $this->pageId;
                
                // Save media image if present
                if (!empty($postData['full_picture'])) {
                    $localPath = $this->saveFbMedia($postData['full_picture'], $postId, 'jpg');
                    $post->url = $localPath ?? $postData['full_picture'];
                }

                $post->created_at     = date('Y-m-d H:i:s', strtotime($postData['created_time']));
                $post->deleted_status = 0;
                $post->save(false);

                // 2. Save or Update nested comments for this post
                if (!empty($postData['comments']['data'])) {
                    foreach ($postData['comments']['data'] as $commentData) {
                        $commentId = $commentData['id'];
                        $comment = FacebookChatLog::findOne(['meta_message_id' => $commentId]) 
                                ?? new FacebookChatLog();

                        $commenterId = $commentData['from']['id'] ?? null;

                        $comment->platform_uid          = $commenterId ?? 'UNKNOWN';
                        $comment->meta_message_id       = $commentId;
                        $comment->meta_id_of_parent_msg = $postId;
                        $comment->sender_name           = $commentData['from']['name'] ?? 'Facebook User';
                        $comment->category              = 'COMMENT';
                        $comment->message_body          = $commentData['message'] ?? '';
                        $comment->direction             = ($commenterId === $pageId) ? 'outbound' : 'inbound';
                        $comment->type                  = 'text';
                        $comment->created_at            = date('Y-m-d H:i:s', strtotime($commentData['created_time']));
                        $comment->deleted_status        = 0;
                        $comment->connected_with        = $this->pageId;
                        $comment->save(false);
                    }
                }
            }
            $transaction->commit();
            return true;
        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::error("Facebook Feed Sync Exception: " . $e->getMessage(), 'facebook_feed');
            return false;
        }
    }

    /**
    * Delete a Comment via Graph API (DELETE /{comment_id})
    */
    public function deleteComment(string $commentId): bool
    {
        $url = "https://graph.facebook.com/{$this->version}/{$commentId}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'access_token' => $this->pageAccessToken,
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        $success = ($httpCode === 200 && !empty($data['success']));

        if ($success || $httpCode === 404) {
            $log = FacebookChatLog::findOne(['meta_message_id' => $commentId]);
            if ($log) {
                $log->deleted_status = 1;
                $log->deleted_at = date('Y-m-d H:i:s');
                $log->save();
            }
            return true;
        }

        Yii::error("Failed to delete FB Comment {$commentId}: " . json_encode($data), 'facebook_feed');
        return false;
    }


    /**
     * Hide or Unhide a Comment on Facebook (POST /{comment_id}?is_hidden=true/false)
     */
    public function toggleHideComment(string $commentId): array
    {
        $log = FacebookChatLog::findOne(['meta_message_id' => $commentId]);
        
        // Determine target hide state: if currently 0/null -> set to 1 (true), else 0 (false)
        $newHideState = $log ? !((int)($log->is_hidden ?? 0)) : true;

        $url = "https://graph.facebook.com/{$this->version}/{$commentId}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'is_hidden'    => $newHideState ? 'true' : 'false',
                'access_token' => $this->pageAccessToken,
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        // Meta returns {"success": true} on successful updates
        if ($httpCode === 200 && !empty($data['success'])) {
            if ($log) {
                $log->is_hidden = $newHideState ? 1 : 0;
                $log->save(false);
            }
            return ['success' => true, 'is_hidden' => $newHideState];
        }

        // Log Graph API error details for debugging
        Yii::error("FB Hide Comment Error for {$commentId}: " . json_encode($data), 'facebook_api');

        return [
            'success' => false, 
            'error' => $data['error']['message'] ?? 'Failed to update hide status on Facebook.'
        ];
    }
}