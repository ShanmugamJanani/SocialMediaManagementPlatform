<?php
namespace app\components\instagram;

use Yii;
use yii\base\Component;
use yii\web\UploadedFile;
use app\models\InstagramChatLog;
use app\models\SocialContact;
use yii\httpclient\Client;
use yii\helpers\Json;

class InstagramService extends Component
{ 

    public $instaId;
    public $instaAccId;
    public $instaAccessToken;
    public $version = 'v25.0';
    public $client;


    public function init()
    {
        parent::init();

        $this->client = new Client([
            'baseUrl' => "https://graph.facebook.com/{$this->version}",
        ]);

        // Only fallback to params if property wasn't set in components config
        if (empty($this->instaAccId) && isset(Yii::$app->params['instaAccountId'])) {
            $this->instaAccId = Yii::$app->params['instaAccountId'];
        }

        if (empty($this->instaAccessToken) && isset(Yii::$app->params['instaAccessToken'])) {
            $this->instaAccessToken = Yii::$app->params['instaAccessToken'];
        }
    }

    

    /**
     * Unified background outbound curl structural wrapper execution engine
     */
    private function executeCurl($url, $payload = [], $asJson = true, $method = 'POST')
    {
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

    
    public function processMessagesWebhook($message)
    {
        
        $senderId =  $message['sender']['id'] ?? null;
        $receiverId =  $message['recipient']['id'] ?? null;

        if (!$senderId || !$receiverId) {
            Yii::warning("Instagram message missing sender or receiver ID.", 'instagram_webhook');
            return;
        }
        
        $metaMessageId = $message['message']['mid'] ?? null;

        if ((string)$senderId === (string)$this->instaAccId) {
            
            if ($metaMessageId) {

                $existingLog = InstagramChatLog::find()
                    ->where(['meta_message_id' => $metaMessageId])
                    ->one();

                if ($existingLog) {

                    Yii::error(
                        "Duplicate outbound Instagram webhook ignored. " .
                        "Meta MID: {$metaMessageId}, Local ID: {$existingLog->id}",
                        'instagram_webhook'
                    );

                    return;
                }
            }

                if (isset($message['message']['text'])) {
                    $log = new InstagramChatLog(); 
                    $log->platform_uid = $receiverId; 
                    $log->message_body = $message['message']['text'];
                    $log->direction = 'outbound'; 
                $log->type = 'text';
                $log->meta_message_id = $message['message']['mid'] ?? null;
                $log->category = 'DM';
                $log->created_at = date('Y-m-d H:i:s');
                if(!$log->save()){
                    Yii::error("Failed to save outbound message: " . json_encode($log->getErrors()), 'instagram_webhook');
                    return;
                }
            }
            return; 
        }

        // Find or create the contact using your updated column names
        $contact = SocialContact::findOne([
            'platform_uid' => $senderId, 
            'platform' => 'INSTAGRAM'
        ]);
 

        // If contact in not there, create it.
        if (!$contact) {
            $profile = $this->getInstagramProfile($senderId);

            $contact = new SocialContact();
            $contact->platform_uid = $senderId;
            $contact->platform = 'INSTAGRAM';
            $contact->bot_state = 'initial_state';
            $contact->connected_with = Json::encode([(string)$this->instaAccId]);

            if ($profile) {
                $contact->username = $profile['username'] ?? null;
                $contact->profile_pic = $profile['profile_pic'] ?? null;
            }  
            
            if(!$contact->save()){
                Yii::error("Failed to save SocialContact: " . json_encode($contact->getErrors()), 'instagram_webhook');
                return;
            }
        }

        else{
            $profile = $this->getInstagramProfile($senderId);

            if ($profile) {
                $contact->username = $profile['username'] ?? null;
                $contact->profile_pic = $profile['profile_pic'] ?? null;
            } 

            if(!$contact->save()){
                Yii::error("Failed to save SocialContact: " . json_encode($contact->getErrors()), 'instagram_webhook');
                return;
            }
        }

        // Parse text and insert directly into the new table
        if (isset($message['message']['text'])) {
            
            $log = new InstagramChatLog(); 
            $log->platform_uid = $senderId;
            $log->message_body = $message['message']['text'];
            $log->direction = 'inbound';
            $log->type = 'text';
            $log->meta_message_id = $message['message']['mid'] ?? null;
            $log->category = 'DM';
            $log->created_at = date('Y-m-d H:i:s');

            if(!$log->save()){
                Yii::error("Failed to save inbound message: " . json_encode($log->getErrors()), 'instagram_webhook');
                return;
            }
        }

        return;
    }


        
//===============================================================================================================================================//
    public function processIncomingWebhook($payload)
    {
        Yii::error('processIncomingWebhook() STARTED', 'instagram_webhook');

        if (isset($payload['entry'][0]['messaging'][0])) {
            $messagingEvent = $payload['entry'][0]['messaging'][0];
            $senderId = $messagingEvent['sender']['id']; 

            // 1. Find or create the contact using your updated column names
            $contact = SocialContact::findOne([
                'platform_uid' => $senderId, 
                'platform' => 'INSTAGRAM'
            ]);
            
            if (!$contact) {

                $profile = $this->getInstagramProfile($senderId);

                $contact = new SocialContact();
                $contact->platform_uid = $senderId;
                $contact->platform = 'INSTAGRAM';
                $contact->bot_state = 'initial_state';                
                $contact->connected_with = Json::encode([(string)$this->instaAccId]);

                if ($profile) {
                    $contact->username = $profile['username'] ?? null;
                    $contact->profile_pic = $profile['profile_pic'] ?? null;
                }

                if (!$contact->save()) {
                    Yii::error("Failed to save SocialContact in processIncomingWebhook: " . json_encode($contact->getErrors()), 'instagram_webhook');
                }
            }

            // 2. Parse text and insert directly into the new table
            if (isset($messagingEvent['message']['text'])) {
                $text = $messagingEvent['message']['text'];
                $mid = $messagingEvent['message']['mid'];

                $log = new InstagramChatLog(); 
                $log->platform_uid = $senderId;
                $log->message_body = $text;
                $log->direction = 'inbound';
                $log->type = 'text';
                $log->meta_message_id = $mid;
                $log->category = 'DM';
                $log->created_at = date('Y-m-d H:i:s');
                if (!$log->save()) {
                    Yii::error("Failed to save Instagram chant: " . json_encode($log->getErrors()), 'instagram_webhook');
                }
            }
        }
    }

    /**
     * Save the incoming webhook comment to your local database
     */
    private function storeIncomingComment($data,$commenterId)
    {
        // Extract required attributes from Meta's comment webhook package
        $commentId = $data['id'] ?? null;
        $mediaId = $data['media']['id'] ?? null;
        $text = $data['text'] ?? '';
        $username = $data['from']['username'] ?? 'AnonymousUser';

        if (!$commentId || !$mediaId) return;

        // Check if comment already exists locally to avoid duplication
        $exists = InstagramChatLog::find()->where(['meta_message_id' => $commentId])->exists();
        if ($exists) return;

        // Map and save to your local database logging system
        $model = new InstagramChatLog();
        $model->meta_message_id = $commentId;
        $model->platform_uid = $commenterId;
        $model->commenter_username = $username;
        $model->meta_id_of_parent_msg = $mediaId;
        $model->message_body = $text;
        $model->direction = 'inbound';
        $model->category = 'COMMENT';
        $model->type = 'text';
        $model->created_at = date('Y-m-d H:i:s');
        
        $model->save();
    }


    
    /**
     * Send generic Free Text message pipeline (POST)
     */
    public function sendTextMessage($toPhoneNumber, $messageText, $replyMessageId = null, $isHumanAgent = false)
    {
      //  $url = "https://graph.facebook.com/{$this->version}/me/messages";
        $url = "https://graph.facebook.com/{$this->version}/me/messages";
        
        $payload = [
            'recipient' => ['id' => $toPhoneNumber],
            'message' => ['text' => $messageText]
        ];

        // Check if Human Agent tag is required (for messages past the 24-hour window up to 7 days)
        if ($isHumanAgent) {
            $payload['messaging_type'] = 'MESSAGE_TAG';
            $payload['tag']            = 'HUMAN_AGENT';
        }

        // If it's a quote reply, map it to Instagram's context configuration
        if ($replyMessageId) {
            $payload['message']['reply_to'] = ['mid' => $replyMessageId];
        }

        return $this->executeCurl($url, $payload);
    }

    /**
     * Uploads a raw posted local array document file directly to Meta, then dispatches the object identifier
     */
    public function uploadAndSendDocument($phone, array $fileArray)
    {
        // Meta Media Upload endpoint requires an uploaded file stream via multipart form data
        $url = "https://graph.facebook.com/{$this->version}/me/media";

        if (!file_exists($fileArray['tmp_name'])) {
            throw new \Exception("Source file does not exist for Meta upload.");
        }

        $payload = [
            'file' => new \CURLFile($fileArray['tmp_name'], $fileArray['type'], $fileArray['name']),
            'recipient' => json_encode(['id' => $phone]),
            'message' => json_encode([
                'attachment' => [
                    'type' => 'file',
                    'payload' => ['is_reusable' => true]
                ]
            ])
        ];

        // 1. Upload to Meta
        $uploadResult = $this->executeCurl($url, $payload, false);
        $mediaId = $uploadResult['attachment_id'] ?? null;

        if (!$mediaId) {
            throw new \Exception("Failed to retrieve attachment_id from Meta media upload pipeline.");
        }

        // 2. Dispatch the media using the returned attachment_id
        $sendUrl = "https://graph.facebook.com/{$this->version}/me/messages";
        $sendPayload = [
            'recipient' => ['id' => $phone],
            'message' => [
                'attachment' => [
                    'type' => 'file',
                    'payload' => ['attachment_id' => $mediaId]
                ]
            ]
        ];

        return $this->executeCurl($sendUrl, $sendPayload);
    }

    /**
     * Saves UploadedFile binary reference onto folder tree directory and dispatches links
     */
    public function uploadAndSendAudio($phone, UploadedFile $uploadedFile)
    {
        $uploadFolder = 'uploads/instagram/audio';
        $prefix = 'audio_' . time();
        $ext = $uploadedFile->extension;

        // 1. Persist the file locally first
        $localPath = $this->persistFileLocally($uploadedFile->tempName, $uploadFolder, $prefix, $ext);

        // 2. Send via Hosted URL string route payload (Instagram supports streaming audio attachments from accessible URLs)
        $url = "https://graph.facebook.com/{$this->version}/me/messages";
        $absoluteUrl = \yii\helpers\Url::to($localPath, true);

        $payload = [
            'recipient' => ['id' => $phone],
            'message' => [
                'attachment' => [
                    'type' => 'audio',
                    'payload' => ['url' => $absoluteUrl, 'is_reusable' => true]
                ]
            ]
        ];

        return $this->executeCurl($url, $payload);
    }

    /**
     * Local helper logic layer designed to safely push uploaded files records on to target folders paths
     */
    private function persistFileLocally($tmpPath, $folder, $prefix, $ext)
    {
        $baseDir = Yii::getAlias('@webroot/') . $folder;
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0775, true);
        }

        $filename = $prefix . '.' . $ext;
        $destination = $baseDir . '/' . $filename;

        if (move_uploaded_file($tmpPath, $destination)) {
            return $folder . '/' . $filename; // Returns path relative to webroot
        }

        throw new \Exception("Failed to write binary file downstream into path directory: " . $destination);
    }

    /**
     * Resolves an inbound resource tracking parameter code directly into downloadable direct URL routes pointers
     */
    private function fetchMediaEndpointUrl($mediaId)
    {
        // Resolves incoming attachment media IDs from webhooks back to hosted asset URLs
        $url = "https://graph.facebook.com/{$this->version}/{$mediaId}?fields=url";
        
        $ch = curl_init($url);
        curl_setopt($ch, curl_RETURNTRANSFER, true);
        curl_setopt($ch, curl_HTTPHEADER, [
            'Authorization: Bearer ' . $this->instaAccessToken
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        return $result['url'] ?? null;
    }

    /**
     * Downloads an asset via cURL stream blocks, allocating it cleanly down to structural disk configurations
     */
    private function handleIncomingMessagePayload($from, $text, $metaMessageId, $type, $mediaUrlId)
    {
        $localSavedPath = null;

        // If it's a media type message, download it locally
        if (in_array($type, ['image', 'video', 'audio', 'file']) && !empty($mediaUrlId)) {
            $remoteUrl = $this->fetchMediaEndpointUrl($mediaUrlId);
            
            if ($remoteUrl) {
                $ext = 'bin'; 
                if ($type === 'image') $ext = 'jpg';
                if ($type === 'video') $ext = 'mp4';
                if ($type === 'audio') $ext = 'ogg';

                $folder = 'uploads/instagram/' . $type;
                
                // Fetch the binary file data using basic GET curl call
                $ch = curl_init($remoteUrl);
                curl_setopt($ch, curl_RETURNTRANSFER, true);
                curl_setopt($ch, curl_FOLLOWLOCATION, true);
                $binaryData = curl_exec($ch);
                curl_close($ch);

                // Create a temporary path execution wrapper for persistFileLocally compatibility
                $tmpFile = tempnam(sys_get_temp_dir(), 'ig_media');
                file_put_contents($tmpFile, $binaryData);

                $localSavedPath = $this->persistFileLocally($tmpFile, $folder, 'received_' . time(), $ext);
                @unlink($tmpFile);
            }
        }

        // Save incoming metadata down into Chat log schemas
        $log = new InstagramChatLog();
        $log->platform_uid = $from;
        $log->message_body = $localSavedPath ?? $text;
        $log->direction = 'inbound';
        $log->type = $type;
        $log->category = 'DM';
        $log->meta_message_id = $metaMessageId;
        $log->created_at = date('Y-m-d H:i:s');
        return $log->save();
    }


    /**
     * Soft-deletes instagram Messenger Messages (DMs)
     */
    public function deletedMessageEvent($messagingEvent)
    {
        $messageId = $messagingEvent['message']['mid'] ?? $messagingEvent['unsend']['mid'] ?? null;
        if (!$messageId) return false;

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $log = InstagramChatLog::findOne(['meta_message_id' => $messageId, 'deleted_status' => 0]);

            if ($log) {
                $log->deleted_status = 1; 
                $log->deleted_at = date('Y-m-d H:i:s');
                $log->save(false); // save(false) bypasses validation rules for raw logging
            } 
            
            else {
                // Optional: If the unsend webhook arrived before the create webhook,
                // you can create an empty stub log here so it shows as deleted when the text arrives later.
                $log = new InstagramChatLog();
                $log->meta_message_id = $messageId;
                $log->deleted_status = 1;
                $log->deleted_at = date('Y-m-d H:i:s');
                $log->save(false);
            }

            $transaction->commit();
            return true;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error("Instagram message soft-delete failed: " . $e->getMessage(), 'instagram_webhook');
            return false;
        }
    }

   public function getInstagramProfile($igScopedUserId)
{
    // For Meta Graph API DM / IGSID profile fetching
    $url = "https://graph.facebook.com/{$this->version}/{$igScopedUserId}"
         . "?fields=name,username,profile_pic&access_token=" . urlencode($this->instaAccessToken);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $profile = json_decode($response, true);

    if ($httpCode === 200 && !empty($profile['username'])) {
        return $profile;
    }

    Yii::error("Failed to fetch Instagram profile for {$igScopedUserId}. HTTP {$httpCode}: " . json_encode($profile), 'instagram_webhook');
    return null;
}

/**
 * Synchronize all live Instagram Direct Message (DM) conversations and messages from Meta API into local DB.
 * 
 * @param int $limit Number of conversations to fetch per page
 * @return array Summary of synchronized messages and contacts
 */
public function syncLiveMessages($limit = 25)
{
    if (empty($this->instaAccId) || empty($this->instaAccessToken)) {
        throw new \Exception("Instagram Account ID or Page Access Token is not set in InstagramService.");
    }

    $syncedCount = 0;
    $contactsCreated = 0;

    // 1. Fetch conversations for the Instagram Business Account
    $endpoint = "https://graph.facebook.com/{$this->version}/me/conversations";
    
    $params = [
        'fields'       => 'id,updated_time,participants,messages{id,created_time,from,to,message,attachments}',
        'platform'     => 'instagram',
        'limit'        => $limit,
        'access_token' => $this->instaAccessToken,
    ];

    $response = $this->executeCurl($endpoint . '?' . http_build_query($params), [], true, 'GET');

    if (empty($response['data'])) {
        return ['synced_messages' => 0, 'synced_contacts' => 0];
    }

    // 2. Loop through each conversation thread
    foreach ($response['data'] as $conversation) {
        $participants = $conversation['participants']['data'] ?? [];
        $externalSender = null;

        // Extract external user ID (the person talking to the business)
        foreach ($participants as $participant) {
            if ((string)$participant['id'] !== (string)$this->instaAccId) {
                $externalSender = $participant;
                break;
            }
        }

        if (!$externalSender) {
            continue;
        }

        $senderId = $externalSender['id'];
        $username = $externalSender['username'] ?? ($externalSender['name'] ?? null);

        // 3. Ensure SocialContact exists for this user
        $contact = SocialContact::findOne([
            'platform_uid' => $senderId,
            'platform'     => 'INSTAGRAM',
        ]);

        if (!$contact) {
            $contact = new SocialContact();
            $contact->platform_uid   = $senderId;
            $contact->platform       = 'INSTAGRAM';
            $contact->bot_state      = 'initial_state';
            $contact->connected_with = Json::encode([(string)$this->instaAccId]);
            $contact->username       = $username;

            $contact->connected_with = Json::encode([(string)$this->instaAccId]);
            
            $profile = $this->getInstagramProfile($senderId);
            if ($profile) {
                $contact->profile_pic = $profile['profile_pic'] ?? null;
            }

            if ($contact->save()) {
                $contactsCreated++;
            } else {
                Yii::error("Failed to create contact during sync: " . json_encode($contact->getErrors()), 'instagram_sync');
            }
        }

        else{
            $connectedWith = Json::decode($contact->connected_with) ?? [];
            
            if (!is_array($connectedWith)) {
                $connectedWith = [];
            }
            
            $accIdStr = (string)$this->instaAccId;
            if (!in_array($accIdStr, $connectedWith, true)) {
                $connectedWith[] = $accIdStr;
                $contact->connected_with = Json::encode([(string)$this->instaAccId]);
                $contact->save(false);
            };
        }

        // 4. Process messages inside thread
        $messages = $conversation['messages']['data'] ?? [];

        foreach ($messages as $msg) {
            $metaMid = $msg['id'] ?? null;
            if (!$metaMid) continue;

            // Deduplication Check
            $exists = InstagramChatLog::find()->where(['meta_message_id' => $metaMid])->exists();
            if ($exists) {
                continue;
            }

            $messageFromId = $msg['from']['id'] ?? null;
            $direction     = ((string)$messageFromId === (string)$this->instaAccId) ? 'outbound' : 'inbound';
            $messageBody   = $msg['message'] ?? '';

            // Handle attachment URL if text is empty
            if (empty($messageBody) && !empty($msg['attachments']['data'][0]['image_data']['url'])) {
                $messageBody = $msg['attachments']['data'][0]['image_data']['url'];
            }

            $log = new InstagramChatLog();
            $log->platform_uid    = $senderId;
            $log->message_body    = $messageBody;
            $log->direction       = $direction;
            $log->connected_with  = Json::encode([(string)$this->instaAccId]);
            $log->type            = !empty($msg['attachments']) ? 'media' : 'text';
            $log->category        = 'DM';
            $log->meta_message_id = $metaMid;
            
            // Map created time from Meta ISO 8601 string
            if (!empty($msg['created_time'])) {
                $log->created_at = date('Y-m-d H:i:s', strtotime($msg['created_time']));
            } else {
                $log->created_at = date('Y-m-d H:i:s');
            }

            if ($log->save()) {
                $syncedCount++;
            } else {
                Yii::error("Failed to save synced message {$metaMid}: " . json_encode($log->getErrors()), 'instagram_sync');
            }
        }
    }

    return [
        'synced_messages' => $syncedCount,
        'synced_contacts' => $contactsCreated
    ];
}

}