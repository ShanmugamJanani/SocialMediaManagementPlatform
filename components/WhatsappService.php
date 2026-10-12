<?php
namespace app\components;

use Yii;
use yii\base\Component;
use yii\web\UploadedFile;
use app\models\WhatsappChatLog;

class WhatsAppService extends Component
{
    public $phoneNumberId;
    public $accessToken;
    public $whatsappBusinessAccountId;
    public $webhookToken;
    public $version = 'v25.0';


    public function processIncomingWebhook(array $data)
    {
        $message = $data;

        if (!empty($message)) {
            $from = $message['from'] ?? null;
            $messageId = $message['id'] ?? null;
            $type = $message['type'] ?? 'text';

            $mediaUrlId = null;
            $text = $this->extractMessageText($message, $mediaUrlId);

            $this->handleIncomingMessagePayload($from, $text, $messageId, $type, $mediaUrlId);
        }

    }

    /**
     * Send generic Free Text message pipeline (POST)
     */
    public function sendTextMessage($toPhoneNumber, $messageText, $replyMessageId = null)
    {
        $url = "https://graph.facebook.com/{$this->version}/{$this->phoneNumberId}/messages";
        
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toPhoneNumber,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $messageText
            ]
        ];

        if ($replyMessageId) {
            $payload['context'] = ['message_id' => $replyMessageId];
        }

        return $this->executeCurl($url, $payload);
    }

    /**
     * Uploads a raw posted local array document file directly to Meta, then dispatches the object identifier
     */
    public function uploadAndSendDocument($phone, array $fileArray)
{
    $tmpFilePath = $fileArray['tmp_name'];
    $mimeType = $fileArray['type'] ?: 'application/octet-stream';
    $originalName = $fileArray['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) ?: 'pdf';

    // 1. Upload file binary directly onto cloud servers
    $mediaUploadUrl = "https://graph.facebook.com/{$this->version}/{$this->phoneNumberId}/media";
    
    // Ensure the physical file mapping is structured cleanly
    $cFile = new \CURLFile($tmpFilePath, $mimeType, $originalName);

    $uploadPayload = [
        'file' => $cFile,
        'messaging_product' => 'whatsapp' // Meta strictly requires this text field alongside the file binary
    ];

    // Note: executeCurl needs to process this as a standard multi-part post array, NOT as a raw JSON string
    $uploadResponse = $this->executeCurl($mediaUploadUrl, $uploadPayload, false); 

    if (!isset($uploadResponse['id'])) {
        $errorMsg = $uploadResponse['error']['message'] ?? 'Unknown Cloud Media Upload Failure';
        // Detailed array logging can be tracked in your Yii Yii::error() logs or via the debug bar panel
        throw new \Exception("Meta Upload Failed: " . $errorMsg);
    }

    $metaMediaId = $uploadResponse['id'];

    // 2. Dispatch Document asset node payload object to endpoint user target
    $sendMessageUrl = "https://graph.facebook.com/{$this->version}/{$this->phoneNumberId}/messages";
    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $phone,
        'type' => 'document',
        'document' => [
            'id' => $metaMediaId,
            'filename' => $originalName
        ]
    ];

    $msgResponse = $this->executeCurl($sendMessageUrl, $payload, true); // Setting true to pass as application/json

    if (!isset($msgResponse['messages'][0]['id'])) {
        $errorMsg = $msgResponse['error']['message'] ?? 'Unknown Meta Dispatch failure.';
        throw new \Exception("Meta Delivery Rejected: " . $errorMsg);
    }

    // 3. Move asset internally and save local database mapping entries records
    $localPathUrl = $this->persistFileLocally($tmpFilePath, 'uploads/whatsapp/', 'media_out_', $extension);
    WhatsappChatLog::logMessage($phone, $localPathUrl, 'outbound', 'document', $msgResponse['messages'][0]['id']);

    return $localPathUrl;
}

    /**
     * Saves UploadedFile binary reference onto folder tree directory and dispatches links
     */
    public function uploadAndSendAudio($phone, UploadedFile $uploadedFile)
    {
        $dir = Yii::getAlias('@webroot/uploads/audio/');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $ext = $uploadedFile->extension ?? 'mp4';
        $fileName = time() . '_voicenote.' . $ext;
        $filePath = $dir . $fileName;

        if (!$uploadedFile->saveAs($filePath)) {
            throw new \Exception('Failed writing audio track to local storage partitions.');
        }

        $publicAudioUrl = rtrim(Yii::$app->request->hostInfo, '/') . Yii::$app->request->baseUrl . '/uploads/audio/' . $fileName;

        if ($ext === 'webm' || $ext === 'mp4') {
            $payload = [
                "messaging_product" => "whatsapp",
                "to" => $phone,
                "type" => "document",
                "document" => [
                    "link" => $publicAudioUrl, 
                    "filename" => "VoiceNote." . $ext
                ]
            ];
        } else {
            $payload = [
                "messaging_product" => "whatsapp",
                "to" => $phone,
                "type" => "audio",
                "audio" => ["link" => $publicAudioUrl]
            ];
        }

        $sendMessageUrl = "https://graph.facebook.com/{$this->version}/{$this->phoneNumberId}/messages";
        $metaResult = $this->executeCurl($sendMessageUrl, $payload);

        if (isset($metaResult['messages'][0]['id'])) {
            $metaMessageId = $metaResult['messages'][0]['id'];
            $relativeUrlPath = Yii::$app->request->baseUrl . '/uploads/audio/' . $fileName;
            
            WhatsappChatLog::logMessage($phone, $relativeUrlPath, 'outbound', 'audio', $metaMessageId);
            return $publicAudioUrl;
        }

        $errorMsg = $metaResult['error']['message'] ?? 'Meta audio pipeline rejected transmission layouts.';
        throw new \Exception("Meta Delivery Failure: " . $errorMsg);
    }

    /**
     * Normalizes payload properties to isolate plaintext text blocks out of inbound objects maps arrays
     */
    private function extractMessageText(array $msg, &$mediaUrlId)
    {
        $type = $msg['type'] ?? 'text';
        $mediaTypes = ['audio', 'image', 'video', 'sticker', 'document'];

        if (in_array($type, $mediaTypes)) {
            $mediaUrlId = $msg[$type]['id'] ?? null;
        }
        
        switch ($type) {
            case 'text':
                return $msg['text']['body'] ?? '';
            case 'audio':
                return '[VOICE NOTE ENQUEUED]';
            case 'image':
                return $msg['image']['caption'] ?? '[IMAGE]';
            case 'video':
                return $msg['video']['caption'] ?? '[VIDEO]';
            case 'sticker':
                return '[STICKER]';
            case 'document':
                return $msg['document']['filename'] ?? '[DOCUMENT]';
            default:
                return '[UNSUPPORTED ENCRYPTED MEDIA ASSET]';
        }
    }

    /**
     * Resolves an inbound resource tracking parameter code directly into downloadable direct URL routes pointers
     */
    private function fetchMediaEndpointUrl($mediaId)
    {
        if (!$mediaId) return null;

        $url = "https://graph.facebook.com/{$this->version}/{$mediaId}";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$this->accessToken}"]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        return $data['url'] ?? null;
    }

    /**
     * Downloads an asset via cURL stream blocks, allocating it cleanly down to structural disk configurations
     */
    private function handleIncomingMessagePayload($from, $text, $metaMessageId, $type, $mediaUrlId)
    {
        if (!$from) return;

        $localPathUrl = null;
        $mediaTypes = ['sticker', 'image', 'video', 'audio', 'document'];

        if (in_array($type, $mediaTypes) && !empty($mediaUrlId)) {
            $downloadUrl = $this->fetchMediaEndpointUrl($mediaUrlId);

            if ($downloadUrl) {
                $extension = match ($type) {
                    'sticker' => 'webp',
                    'image' => 'jpg',
                    'video' => 'mp4',
                    'audio' => 'ogg',
                    'document' => strtolower(pathinfo($text, PATHINFO_EXTENSION)) ?: 'pdf',
                    default => 'bin'
                };

                $relativeFolder = 'uploads/whatsapp/';
                $absoluteFolder = Yii::getAlias('@webroot/') . $relativeFolder;

                if (!is_dir($absoluteFolder)) {
                    mkdir($absoluteFolder, 0755, true);
                }

                $fileName = 'media_out_' . uniqid() . '_' . time() . '.' . $extension;
                $targetLocalFilePath = $absoluteFolder . $fileName;

                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $downloadUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTPHEADER => ["Authorization: Bearer {$this->accessToken}"],
                ]);

                $rawBinaryData = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200 && $rawBinaryData) {
                    file_put_contents($targetLocalFilePath, $rawBinaryData);
                    $localPathUrl = Yii::$app->request->baseUrl . '/' . $relativeFolder . $fileName;
                }
            }
        }

        $finalBody = !empty($localPathUrl) ? $localPathUrl : $text;
        WhatsappChatLog::logMessage($from, $finalBody, 'inbound', $type, $metaMessageId);
    }

    /**
     * Local helper logic layer designed to safely push uploaded files records on to target folders paths
     */
    private function persistFileLocally($tmpPath, $folder, $prefix, $ext)
    {
        $absoluteFolder = Yii::getAlias('@webroot/') . $folder;
        if (!is_dir($absoluteFolder)) {
            mkdir($absoluteFolder, 0755, true);
        }

        $fileName = $prefix . uniqid() . '_' . time() . '.' . $ext;
        $targetLocalFilePath = $absoluteFolder . $fileName;

        if (move_uploaded_file($tmpPath, $targetLocalFilePath)) {
            return Yii::$app->request->baseUrl . '/' . $folder . $fileName;
        }
        return "[Uploaded to Meta Storage Portfolio Successfully]";
    }

    /**
     * Unified background outbound curl structural wrapper execution engine
     */
    private function executeCurl($url, $payload, $asJson = true)
    {
        $ch = curl_init($url);
        $headers = ["Authorization: Bearer {$this->accessToken}"];

        // Initialize base cURL options
        $options = [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true
        ];

        if ($asJson) {
            $headers[] = "Content-Type: application/json";
            $options[CURLOPT_POSTFIELDS] = json_encode($payload);
        } else {
            // CRITICAL: When sending a CURLFile, do NOT set a Content-Type header.
            // Letting PHP/cURL bind the raw array into CURLOPT_POSTFIELDS automatically 
            // forces the correct 'multipart/form-data' headers and content boundaries.
            $options[CURLOPT_POSTFIELDS] = $payload;
        }

        // Set the headers array into options
        $options[CURLOPT_HTTPHEADER] = $headers;

        // Apply everything safely in one unified block
        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
}