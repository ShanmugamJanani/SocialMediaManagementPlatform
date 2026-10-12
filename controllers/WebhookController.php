<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\AccessControl;
use yii\web\Response;
use app\models\SocialContact;
use app\models\WhatsappChatLog;
use app\models\FacebookChatLog;
use app\models\InstagramChatLog;
use app\models\InstagramUploadForm;
use app\models\LogUser;
use app\models\SocialAccount;
use yii\web\UploadedFile;
use yii\helpers\Json;
use GuzzleHttp\Client;
use yii\helpers\Url;
use app\components\instagram\InstagramService;

class WebhookController extends Controller
{
    public $enableCsrfValidation = false;


    //************************************************//
    //-------------Main Webhook Reception-------------//
    //************************************************//
    
    //Verifies the verification webhook connection handshake initiated by Meta (GET)
    public function verifyWebhook($mode, $token)
    {
        $expectedToken = Yii::$app->params['metaWebhookAccessToken'];
        
        $return = ($mode === 'subscribe' && $token === $expectedToken);
        return ($mode === 'subscribe' && $token === $expectedToken);
    }

    public function actionWebhook()
    {
        $request = Yii::$app->request;
        $rawBody = Yii::$app->request->getRawBody();
        Yii::error('Inbound payload' .$rawBody , 'webhook');


        // 1. Handle GET requests (Meta's Webhook Verification Challenge)
        if ($request->isGet) {
            $isValid = $this->verifyWebhook(
                $request->get('hub_mode'),
                $request->get('hub_verify_token')
            );

            if ($isValid) {
                Yii::$app->response->format = Response::FORMAT_RAW;
                return $request->get('hub_challenge');
            }

            Yii::$app->response->statusCode = 403;
            return 'Forbidden';
        }

        // 2. Handle POST requests (Actual Inbound Messaging Payloads)
        if ($request->isPost) {
            // This is where Meta sends incoming WhatsApp or Instagram messages
            return $this->processWebhookPayload(); 
        }

        // Fallback for unexpected HTTP methods (PUT, DELETE, etc.)
        Yii::$app->response->statusCode = 405;
        return 'Method Not Allowed';
    }
    

    protected function processWebhookPayload()
    {
        $rawBody = Yii::$app->request->getRawBody();
        $payload = json_decode($rawBody, true);

        Yii::error('Inbound payload' .$rawBody , 'webhook');
        
        if (empty($payload)) {
            Yii::$app->response->statusCode = 400;
            return 'Empty Payload';
        }

        // Delegate handling based on origin ('whatsapp_business_account' or 'instagram')
        $objectType = $payload['object'] ?? '';

        //Meta always wraps events in an array because multiple updates can arrive in one webhook call. **--Entry => batch of updates (DM, COMMENT, POST, Like, etc) from one account/page --**
        $entries = $payload['entry'] ?? []; //multiple accounts could send simultaneously. so mata will send the batch of entriest 

        //Changes attribute carries the changes happened in our page (DM, COMMENT, POST, Like, etc)
        
        switch ($objectType) {
            case 'whatsapp_business_account':
                try {


                    foreach ($entries as $entry) {
                        foreach ($entry['changes'] ?? [] as $change) {

                            $value = $change['value'] ?? [];
                            $messages = $value['messages'] ?? [];

                            // CRITICAL STEP: If Meta sends a status tracking event (sent, delivered, read), exit immediately.
                            if (isset($value['statuses'])) { 
                                Yii::$app->whatsapp->processStatuses($value['statuses']);
                                continue;
                            }

                            foreach ($messages as $message){
                                $from = $message['from'] ?? null;
                                $username = $entry["changes"][0]["value"]["contacts"][0]["profile"]["name"];

                                if (!$from) continue;

                                $contact = $this->ensureContactSession($from, 'WHATSAPP', $username);

                                if ($contact->mode === 'BOT') {
                                    Yii::$app->bot->processIncomingWebhook($message);
                                } else {
                                    Yii::error('bot state'. $contact->mode);
                                    //Yii::$app->whatsapp->processIncomingWebhook($message);
                                }
                            }
                        }
                    }
                    
                } 
                
                catch (\Throwable $e) {
                    Yii::error("WhatsApp Processing Error: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'whatsapp_webhook');
                }
                break;
                
            case 'instagram':
                try {
                    foreach ($entries as $entry) {
                        $entryProcessed = false;

                        if (!empty($entry['messaging'])) {
                            $entryProcessed = true;
                            foreach ($entry['messaging'] as $message) {

                                $senderId = $message['sender']['id'] ?? null;
                                
                                // Intercept native Instagram deletion events early
                                // Check for both Instagram's 'is_deleted' flag AND Facebook's 'unsend' object
                                $isDeleted = isset($message['message']['is_deleted']) && $message['message']['is_deleted'] === true;
                                $isUnsend  = isset($message['unsend']);

                                if ($isDeleted || $isUnsend){
                                    Yii::$app->instagram->deletedMessageEvent($message);
                                    Yii::info("Instagram Webhook Sync: Handled message unsend", 'instagram_webhook');
                                    continue;
                                }
                                Yii::$app->instagram->processMessagesWebhook($message);      
                            }
                        }

                        if (!empty($entry['changes'])) {
                            $entryProcessed = true;

                            foreach ($entry['changes'] as $change) {
                                $field = $change['field'] ?? null;
                                $value = $change['value'] ?? [];

                                // Intercept native Instagram deletion events early
                                if (isset($value['verb']) && $value['verb'] === 'remove') {
                                    Yii::$app->instagramFeed->deletedChangeEvent($change);
                                    Yii::info(['field' => $field,'verb' => $value['verb'] ?? null,'comment_id' => $value['comment_id'] ?? null,'media_id' => $value['media_id'] ?? null,], 'instagram_webhook');
                                    continue;
                                } 

                                switch ($field) {
                                    case 'comments':
                                        // Fallback safely back to your standard creation workflow logic loop
                                        Yii::$app->instagramFeed->processCommentWebhook($value);
                                        break;

                                    case 'mentions':
                                        Yii::$app->instagramFeed->processMentionWebhook($value);
                                        break;

                                    default:
                                        Yii::warning("Unhandled Instagram field: {$field}", 'instagram_webhook');
                                }
                            }
                        }
                        
                        if (!$entryProcessed) {
                            Yii::error("Unhandled Instagram Entry structure:" . json_encode($entry) , 'instagram_webhook');
                        }
                    }
                } 
                
                catch (\Throwable $e) {
                    Yii::error("Instagram Processing Error: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'instagram_webhook');
                }
                break;

            case 'page':     
            case 'facebook': 
                try {
                    foreach ($entries as $entry) {
                        $pageId = $entry['id'] ?? null;

                        // 1. Messenger Webhook Events (Direct Messages)
                        if (!empty($entry['messaging'])) {
                            foreach ($entry['messaging'] as $messagingEvent) {
                                $senderId = $messagingEvent['sender']['id'] ?? null;
                                        
                                // Defensive validation: ensures session manager only fires with a real ID
                                if ($senderId) {
                                    $username = $this->getFacebookUserName($senderId);
                                    $this->ensureContactSession($senderId, 'FACEBOOK',$username);
                                }

                                // Intercept Facebook Message Unsend (Deletion)
                                if (isset($messagingEvent['unsend'])) {
                                    Yii::$app->facebook->deletedMessageEvent($messagingEvent);
                                    Yii::info("Facebook Webhook Sync: Handled message unsend", 'facebook_webhook');
                                    continue;
                                }

                                // Delegate to component for Graph API responses/business logic
                                Yii::$app->facebook->processMessageWebhook($messagingEvent);
                            }
                            continue;
                        }

                        // 2. Page Feed/Comment Event Modifications
                        foreach ($entry['changes'] ?? [] as $change) {
                            $field = $change['field'] ?? null;
                            $value = $change['value'] ?? [];

                            // Intercept deletions instantly
                            if (isset($value['verb']) && ($value['verb'] === 'remove' || ($value['verb'] === 'edited' && !isset($value['link'] )))) {
                                Yii::$app->facebook->deletedChangeEvent($change);
                                Yii::info("Webhook Sync: Purged native deleted FB item ", 'facebook_webhook');
                                continue;
                            }

                            switch ($field) {
                                case 'feed':
                                    // Handles new posts on the page
                                    Yii::$app->facebook->processFeedWebhook($value,$pageId);
                                    break;

                                case 'comments':
                                    Yii::$app->facebook->processCommentWebhook($value,$pageId);
                                    break;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    Yii::error("Facebook Core Webhook Error: " . $e->getMessage(), 'facebook_webhook');
                }
                break;

            default:
                Yii::warning("Unhandled Meta webhook object type: " . $objectType, 'meta_webhook');
                break;
        }

        // Meta expects a clear 200 OK acknowledgment response
        Yii::$app->response->statusCode = 200;
        return 'EVENT_RECEIVED';
    }

    private function ensureContactSession($phone, $platform, $username)
    {
        Yii::error('ensureContactSession() STARTED', 'instagram_webhook');
        $socialAccount = SocialAccount::findOne(['id' => $accountId, 'platform_type' => 'facebook']);                            
        // Find the contact by platform UID and platform type
        $contact = SocialContact::find()->where(['platform_uid' => $phone, 'platform' => $platform])->one();
        if (!$contact) {
            $contact = new SocialContact();
            $contact->platform_uid = $phone;
            $contact->bot_state = 'initial_state';
            $contact->platform = $platform;
            $contact->username = $username;
            $contact->connected_with = Json::encode([(string)$socialAccount->platform_id]);
            // Fetch active page ID
            $account = SocialAccount::findOne(['platform_type' => 'facebook', 'active_status' => '1']);
            $pageId = $account->platform_id ?? null;

            // FIX: Extract single string ID if $pageId happens to be an array
            if (is_array($pageId)) {
                $pageId = reset($pageId);
            }

            $initialAccounts = $pageId ? [(string)$pageId] : [];
            $contact->connected_with = Json::encode($initialAccounts);    
            $contact->save();
        
        }

        else {
            $account = SocialAccount::findOne(['platform_type' => 'facebook', 'active_status' => '1']);
            $pageId = $account->platform_id ?? null;
            // 1. Decode JSON string from MySQL LONGTEXT into a PHP variable
            $connectedWith = Json::decode($contact->connected_with) ?? [];

            // 2. If MySQL had NULL/empty text, ensure we convert it to an empty PHP array
            if (!is_array($connectedWith)) {
                $connectedWith = [];
            }

            $accountIdStr = (string)$pageId;

            // 3. Now it is guaranteed to be a PHP array, so in_array() works safely
            if (!in_array($accountIdStr, $connectedWith, true)) {
                $connectedWith[] = $accountIdStr; // Append new ID
                
                // 4. Convert the PHP array back into a JSON string for MySQL storage
                $contact->connected_with = Json::encode($connectedWith);  
                $contact->save(false);
            }
        }
        return $contact;
    }

    
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    // 1. Meta / Facebook incoming webhook endpoint (Public for guests & users)
                    [
                        'actions' => ['webhook', 'instagram-callback', 'connect-instagram', 'error'],
                        'allow' => true,
                        'roles' => ['?', '@'], 
                    ],

                    // 2. Global Error Page
                    [
                        'actions' => ['error'],
                        'allow' => true,
                        'roles' => ['?', '@'],
                    ],

                    // 3. ALL OTHER ACTIONS in WebhookController (like facebook-feed-info, whatsapp-index)
                    // Require the user to be logged in
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    public function actionDisconnect($id)
    {
        $userId = Yii::$app->user->id;

        // 1. Locate the social account belonging to the current user
        $account = SocialAccount::findOne([
            'id'      => $id,
        ]);

        if (!$account) {
            throw new NotFoundHttpException('The requested social account does not exist or access is denied.');
        }

        try {
            $platform = strtolower($account->platform_type);

            // 2. Revoke Meta Access Token via Graph API for Facebook, Instagram, or WhatsApp
            if (in_array($platform, ['facebook', 'instagram', 'whatsapp']) && !empty($account->platform_data['user_access_token'])) {
                $this->revokeMetaPermissions($account->access_token);
            }

            // 3. Mark account as inactive or delete from database
            // Option A: Set active_status = 0 (Preserves account connection history)
            $account->active_status = 0;
            $account->updated_at    = date('Y-m-d H:i:s');

            // Clean sensitive token data from platform_data on disconnect
            $platformData = is_array($account->platform_data) 
                ? $account->platform_data 
                : Json::decode($account->platform_data ?? '{}');
                
            $platformData['disconnected_at'] = date('Y-m-d H:i:s');
            $account->platform_data          = Json::encode($platformData);

            if ($account->save(false)) {
                Yii::$app->session->setFlash('success', ucfirst($platform) . ' account ("' . $account->account_name . '") disconnected successfully.');
            } else {
                Yii::$app->session->setFlash('error', 'Failed to update account status.');
            }

            // Option B: If you prefer soft/hard deletion instead, use $account->delete();

        } catch (\Throwable $e) {
            Yii::error('Disconnect Error: ' . $e->getMessage(), __METHOD__);
            Yii::$app->session->setFlash('error', 'An error occurred while disconnecting: ' . $e->getMessage());
        }

        return $this->redirect(['profile/index']); // Redirect back to profile page
    }

    /**
     * Helper function to revoke Facebook/Instagram/WhatsApp app permissions.
     *
     * @param string $accessToken
     */
    protected function revokeMetaPermissions($userAccessToken = null, $fbUserId = null)
{
    // Ensure you are using the User Access Token, NOT the Page Access Token
    if (empty($userAccessToken)) {
        Yii::warning('Cannot revoke Meta permissions: Missing User Access Token.', 'facebook_auth');
        return false;
    }

    try {
        $client = new \GuzzleHttp\Client();
        
        // Option A: If deleting user's app permissions completely via User Token
        $url = "https://graph.facebook.com/v25.0/me/permissions";
        
        // Option B: If targeting a specific user ID directly
        if ($fbUserId) {
            $url = "https://graph.facebook.com/v25.0/{$fbUserId}/permissions";
        }

        $response = $client->delete($url, [
            'query' => [
                'access_token' => $userAccessToken,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return isset($data['success']) && $data['success'] === true;

    } catch (\GuzzleHttp\Exception\ClientException $e) {
        $errorBody = $e->getResponse()->getBody()->getContents();
        Yii::warning("Meta API permission revocation returned HTTP Code 400: " . $errorBody, __METHOD__);
        return false;
    } catch (\Throwable $e) {
        Yii::error("Failed to revoke Meta permissions: " . $e->getMessage(), __METHOD__);
        return false;
    }
}

    
    //************************************************//
    //---------Whatasapp Controller Functions--------//
    //************************************************//                            

    public function actionWhatsappIndex($phone = null)
    {
        $this->layout = 'main';

        // 1. Fetch all unique contacts to populate the left sidebar
        $contacts = SocialContact::find()->where(['platform' => 'WHATSAPP'])->orderBy(['id' => SORT_DESC])->all();                                
        $messages = [];
        $activeContact = null;

        // 2. If a specific phone chat is selected, load its logs and state details
        if ($phone) {
            $activeContact = SocialContact::find()->where(['platform_uid' => $phone, 'platform' => 'WHATSAPP'])->one();
            
            $messages = WhatsappChatLog::find()
                ->where(['phone_number' => $phone])
                ->orderBy(['created_at' => SORT_ASC])
                ->all();
        }

        // 3. Render the views/webhook/index.php file we just updated
        return $this->render('index', [
            'contacts' => $contacts,
            'activePhone' => $phone,
            'messages' => $messages,
            'activeContact' => $activeContact,
        ]);
    } 

    /*
 * MANUAL TEXT DISPATCH HANDLER (AJAX POST)
 */
public function actionSend()
{
    Yii::$app->response->format = Response::FORMAT_JSON;
    $request = Yii::$app->request;

    // 1. Sanitize input
    $phone = trim((string)$request->post('phone_number'));
    $body = trim((string)$request->post('message_body'));

    if (empty($phone) || empty($body)) {
        return ['success' => false, 'error' => 'Message body or phone parameter input missing.'];
    }

    try {
        
        $apiResult = Yii::$app->bot->sendTextMessage($phone, $body);

        // 🚨 FIX 1: Check if Meta returned an API error
        if (isset($apiResult['error'])) {
            $metaErrorMessage = $apiResult['error']['message'] ?? 'Unknown Meta API Error';
            Yii::error("Manual Send Failed for {$phone}: " . json_encode($apiResult), 'bot_error');
            
            return [
                'success' => false, 
                'error' => "WhatsApp API Error: " . $metaErrorMessage
            ];
        }

        // Extract Meta Message ID upon success
        $metaMessageId = $apiResult['messages'][0]['id'] ?? null;

        // 3. Log outbound message to DB
        $log = new WhatsappChatLog();
        $log->phone_number = $phone;
        $log->message_body = $body;
        $log->direction = 'outbound';
        $log->type = 'text';
        $log->created_at = date('Y-m-d H:i:s');
        $log->meta_message_id = $metaMessageId;

        // 🚨 FIX 2: Validate DB Save
        if (!$log->save()) {
            Yii::error("Failed to save outbound log: " . json_encode($log->errors), 'bot_error');
            return ['success' => false, 'error' => 'Failed to save message log to database.'];
        }

        return [
            'success' => true,
            'timestamp' => date('h:i A'),
            'id' => $log->id,
            'meta_id' => $metaMessageId
        ];

    } catch (\Throwable $e) {
        Yii::error("Manual Send Exception: " . $e->getMessage(), 'bot_error');
        return ['success' => false, 'error' => 'System Exception: ' . $e->getMessage()];
    }
}


    /**
     * 1. Redirect user to Meta OAuth Authorization Page
     */
    public function actionWhatsappConnect()
    {
        $appId = Yii::$app->params['app_id'] ?? null;
        
        // Exact fixed callback URL in your application
        $redirectUri = Url::to(['webhook/whatsapp-callback'], true);

        // Required permissions for managing WhatsApp Business Account
        $scope = implode(',', [
            'whatsapp_business_management',
            'whatsapp_business_messaging'
        ]);

        $metaAuthUrl = "https://www.facebook.com/v25.0/dialog/oauth?" . http_build_query([
            'client_id'     => $appId,
            'redirect_uri'  => $redirectUri,
            'scope'         => $scope,
            'response_type' => 'code',
        ]);

        return $this->redirect($metaAuthUrl);
    }

    /**
     * 2. Handle Callback from Meta after User Authorizes
     */
    public function actionWhatsappCallback()
    {
        $code  = Yii::$app->request->get('code');
        $error = Yii::$app->request->get('error_message');

        if ($error || !$code) {
            Yii::$app->session->setFlash('error', $error ?? 'WhatsApp authorization was cancelled or failed.');
            return $this->redirect(['/home/index']);
        }

        $appId       = Yii::$app->params['app_id'] ?? null;
        $appSecret   = Yii::$app->params['app_secret'] ?? null;
        $redirectUri = Url::to(['webhook/whatsapp-callback'], true);

        $client = new \GuzzleHttp\Client();

        try {
            // 1. Exchange temporary code for Access Token
            $tokenResponse = $client->get("https://graph.facebook.com/v25.0/oauth/access_token", [
                'query' => [
                    'client_id'     => $appId,
                    'client_secret' => $appSecret,
                    'code'          => $code,
                    'redirect_uri'  => $redirectUri,
                ]
            ]);

            $tokenData   = json_decode($tokenResponse->getBody()->getContents(), true);
            $accessToken = $tokenData['access_token'] ?? null;

            if (!$accessToken) {
                Yii::$app->session->setFlash('error', 'Could not retrieve access token from Meta.');
                return $this->redirect(['/home/index']);
            }

            // 2. Inspect Access Token to extract granted WABA IDs
            $debugResponse = $client->get("https://graph.facebook.com/v25.0/debug_token", [
                'query' => [
                    'input_token'  => $accessToken,
                    'access_token' => "{$appId}|{$appSecret}",
                ]
            ]);
            $debugData = json_decode($debugResponse->getBody()->getContents(), true);

            $granularScopes = $debugData['data']['granular_scopes'] ?? [];

            // Extract all target WABA IDs granted
            $wabaIds = [];
            foreach ($granularScopes as $scopeItem) {
                if (in_array($scopeItem['scope'] ?? '', ['whatsapp_business_management', 'whatsapp_business_messaging'])) {
                    if (!empty($scopeItem['target_ids'])) {
                        $wabaIds = array_merge($wabaIds, $scopeItem['target_ids']);
                    }
                }
            }
            $wabaIds = array_unique($wabaIds);

            // 3. Loop through WABAs to find one with an active phone number
            $phoneRecord = null;
            $wabaId      = null;

            foreach ($wabaIds as $id) {
                try {
                    $phoneResponse = $client->get("https://graph.facebook.com/v25.0/{$id}/phone_numbers", [
                        'headers' => ['Authorization' => "Bearer {$accessToken}"],
                        'query'   => [
                            'fields' => 'id,display_phone_number,verified_name,quality_rating,code_verification_status,messaging_limit_tier'
                        ]
                    ]);
                    $phoneData = json_decode($phoneResponse->getBody()->getContents(), true);

                    if (!empty($phoneData['data'][0])) {
                        $phoneRecord = $phoneData['data'][0];
                        $wabaId      = $id; // Lock onto working WABA ID
                        break;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            // --- SAFEGUARD & VARIABLE EXTRACTION ---
            if (!$wabaId || !$phoneRecord) {
                Yii::$app->session->setFlash('error', 'No active WhatsApp phone numbers were found in the granted business accounts.');
                return $this->redirect(['/home/index']);
            }

            $phoneNumberId      = $phoneRecord['id'] ?? null;
            $displayPhoneNumber = $phoneRecord['display_phone_number'] ?? null;

            // 4. Fetch WhatsApp Business Profile (Avatar, Bio, Category, Email, Website)
            $profilePicUrl   = null;
            $businessProfile = [];

            try {
                $profileResponse = $client->get("https://graph.facebook.com/v25.0/{$phoneNumberId}/whatsapp_business_profile", [
                    'headers' => ['Authorization' => "Bearer {$accessToken}"],
                    'query'   => [
                        'fields' => 'profile_picture_url,about,address,description,email,websites,vertical'
                    ]
                ]);
                $profileData     = json_decode($profileResponse->getBody()->getContents(), true);
                $businessProfile = $profileData['data'][0] ?? [];
                $profilePicUrl   = $businessProfile['profile_picture_url'] ?? null;
            } catch (\Exception $e) {
                Yii::warning('Could not fetch WhatsApp Business Profile: ' . $e->getMessage(), __METHOD__);
            }

            // 5. Subscribe App to Tenant's WABA Webhooks with explicit fields
            try {
                $client->post("https://graph.facebook.com/v25.0/{$wabaId}/subscribed_apps", [
                    'headers' => ['Authorization' => "Bearer {$accessToken}"],
                    'query'   => [
                        'subscribed_fields' => 'messages,message_template_status_update'
                    ]
                ]);
            } catch (\Exception $e) {
                Yii::warning('Webhook subscription warning: ' . $e->getMessage(), __METHOD__);
            }

            // 6. Build Comprehensive JSON Array for platform_data
            $platformData = [
                'waba_id'                  => $wabaId,
                'phone_number_id'          => $phoneNumberId,
                'display_phone_number'     => $displayPhoneNumber,
                'verified_name'            => $phoneRecord['verified_name'] ?? 'WhatsApp Business',
                'profile_pic_url'          => $profilePicUrl,
                'quality_rating'           => $phoneRecord['quality_rating'] ?? 'UNKNOWN',
                'code_verification_status' => $phoneRecord['code_verification_status'] ?? 'VERIFIED',
                'messaging_limit_tier'     => $phoneRecord['messaging_limit_tier'] ?? 'TIER_NOT_SET',
                'about'                    => $businessProfile['about'] ?? null,
                'description'              => $businessProfile['description'] ?? null,
                'business_email'           => $businessProfile['email'] ?? null,
                'business_address'         => $businessProfile['address'] ?? null,
                'vertical'                 => $businessProfile['vertical'] ?? null,
                'websites'                 => $businessProfile['websites'] ?? [],
                'connected_at'             => date('Y-m-d H:i:s'),
            ];

            // 7. Save to Database
            $userId  = Yii::$app->user->id;
            $account = SocialAccount::findOne([
                'platform_type' => 'whatsapp',
                'platform_id'   => $phoneNumberId,
            ]) ?? new SocialAccount();

            $account->platform_type = 'whatsapp';
            $account->platform_id   = $phoneNumberId;
            $account->account_name  = $phoneRecord['verified_name'] ?? $displayPhoneNumber ?: 'WhatsApp Business';
            $account->access_token  = $accessToken;
            $account->platform_data = json_encode($platformData);
            $account->active_status = '1';

            if ($account->save()) {
                Yii::$app->session->setFlash('success', 'WhatsApp Business Account connected successfully!');
            } else {
                Yii::error('Failed saving WhatsApp SocialAccount: ' . json_encode($account->getErrors()), __METHOD__);
                Yii::$app->session->setFlash('error', 'Failed to save account to database.');
            }

        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            Yii::error("WhatsApp API Error: " . $responseBody, __METHOD__);
            Yii::$app->session->setFlash('error', 'Meta API Error: ' . $e->getMessage());
        } catch (\Exception $e) {
            Yii::error("WhatsApp Connect Error: " . $e->getMessage(), __METHOD__);
            Yii::$app->session->setFlash('error', 'Connection error: ' . $e->getMessage());
        }

        return $this->redirect(['/home/index']);
    }
    //************************************************//
    //----------Facebook Controller Functions---------//
    //************************************************//                                


    // Display Facebook Feed Management Index with Counts
    public function actionFacebookFeedIndex()
    {
        $this->layout = 'main';
        $account = SocialAccount::findOne([ 'platform_type'=> 'facebook', 'active_status'=> '1']);
        if($account){

            Yii::$app->facebook->fetchAndSyncLatestPosts($account->platform_id,$account->access_token);

            // Retrieve non-deleted top-level posts
            $posts = FacebookChatLog::find()
                ->where(['category' => 'POST', 'deleted_status' => 0, 'connected_with' => $account->platform_id])
                ->orderBy(['created_at' => SORT_DESC])
                ->all();

            $postMetrics = [];

            foreach ($posts as $post) {
                // Calculate local active non-deleted comments count
                $localCommentCount = FacebookChatLog::find()
                    ->where(['category' => 'COMMENT', 'deleted_status' => 0])
                    ->andWhere([
                        'or',
                        ['meta_id_of_parent_msg' => $post->meta_message_id],
                        ['in', 'meta_id_of_parent_msg', FacebookChatLog::find()
                            ->select('meta_message_id')
                            ->where(['meta_id_of_parent_msg' => $post->meta_message_id])
                        ]
                    ])
                    ->count();

                // Attempt live sync metrics from Meta API for accuracy
                $liveMetrics = Yii::$app->facebook->getPostSummary($post->meta_message_id);

                $postMetrics[$post->meta_message_id] = [
                    'likes' => $liveMetrics['like_count'] ?? 0,
                    'comments' => max((int)$localCommentCount, (int)($liveMetrics['comment_count'] ?? 0)),
                ];
            }

            return $this->render('facebook_feed', [
                'posts' => $posts,
                'postMetrics' => $postMetrics,
            ]);
        }

        else{
            return $this->redirect(['/home/index']);
        }
    }

    public function actionCreateFbPost()
{
    Yii::$app->response->format = Response::FORMAT_JSON;
    $request = Yii::$app->request;

    if (!$request->isPost) {
        return ['success' => false, 'error' => 'Invalid HTTP method.'];
    }

    $message = trim($request->post('message', ''));
    $uploadedFile = UploadedFile::getInstanceByName('image');
    $imagePath = null;

    if ($uploadedFile) {
        $uploadDir = Yii::getAlias('@webroot') . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileName = 'fb_post_' . date('Ymd_His') . '_' . Yii::$app->security->generateRandomString(8) . '.' . strtolower($uploadedFile->extension);
        $imagePath = $uploadDir . $fileName;

        if (!$uploadedFile->saveAs($imagePath)) {
            return ['success' => false, 'error' => 'Failed to save uploaded image locally.'];
        }
    }

    $postId = Yii::$app->facebook->createPagePostWithPhoto($message, $imagePath);

    if ($postId) {
        return ['success' => true, 'post_id' => $postId];
    }

    return ['success' => false, 'error' => 'Failed to publish post to Facebook.'];
}

    // AJAX Action: Delete Facebook Post
    public function actionDeleteFbPost()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $request = Yii::$app->request;

        if (!$request->isPost) {
            return ['success' => false, 'error' => 'Invalid HTTP method context.'];
        }

        $postId = $request->post('post_id');
        if (empty($postId)) {
            return ['success' => false, 'error' => 'Post ID parameter is missing.'];
        }

        $success = Yii::$app->facebook->deletePost($postId);

        if ($success) {
            return ['success' => true];
        }

        return ['success' => false, 'error' => 'Failed to delete post on Facebook or database.'];
    }

    // Renders the Unified Messaging Inbox UI
    public function actionFacebookIndex($activeContactId = null)
    {
        $this->layout = 'main';
        
        // 1. Fetch all contacts ordered by last activity
        $allContacts = SocialContact::find()->where(['platform' => 'FACEBOOK'])->all();

        $contacts = [];                                
        foreach ($allContacts as $contact) {
            // Safely decode JSON and enforce array type structure
            $connectedAcc = Json::decode($contact->connected_with ?? '[]') ?? [];
            if (!is_array($connectedAcc)) {
                $connectedAcc = (array)$connectedAcc;
            }

            if (in_array(Yii::$app->facebook->pageId, $connectedAcc)) {
                $contacts[] = $contact;
            }
        }

        $activeContact = null;
        $chatLogs = [];

        // Select requested contact ONLY if it belongs to current Facebook page
        if ($activeContactId) {
            foreach ($contacts as $contact) {
                if ((string)$contact->id === (string)$activeContactId) {
                    $activeContact = $contact;
                    break;
                }
            }
        }

        // If no valid active contact was selected, use first contact
        if (!$activeContact && !empty($contacts)) {
            $activeContact = $contacts[0];
        }

        // 3. Fetch chat history dynamically based on active contact's platform
        if ($activeContact) {
            $chatLogs = FacebookChatLog::find()
                ->where([
                    'platform_uid' => $activeContact->platform_uid, 
                    'connected_with' => Yii::$app->facebook->pageId,
                    'category' => 'DM'
                ])
                ->orderBy(['created_at' => SORT_ASC])
                ->all();
        }

        return $this->render('facebook', [
            'contacts' => $contacts,
            'activeContact' => $activeContact,
            'chatLogs' => $chatLogs,
        ]);
    }

    public function actionSendFacebookMessage()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $contactId       = Yii::$app->request->post('contact_id');
        $messageText     = trim(Yii::$app->request->post('message'));
        $parentMetaId    = Yii::$app->request->post('parent_meta_id'); // Captured for Replies

        if (empty($messageText) || empty($contactId)) {
            return ['status' => 'error', 'message' => 'Missing contact ID or message content.'];
        }

        $contact = SocialContact::find()
            ->where(['id' => $contactId, 'platform' => 'FACEBOOK', ])
            ->one();

        if (!$contact) {
            return ['status' => 'error', 'message' => 'Facebook contact not found.'];
        }

        $lastInbound = FacebookChatLog::find()
                ->where(['platform_uid' => $contact->platform_uid, 'direction' => 'inbound'])
                ->orderBy(['created_at' => SORT_DESC])
                ->one();

        $isHumanAgent = false;
        $outgoingPayload = null;

        if ($lastInbound && !empty($lastInbound->created_at)) {
            $lastInboundTime = strtotime($lastInbound->created_at);
            $currentTime     = time();
            
            // Calculate elapsed time in hours
            $hoursPassed = ($currentTime - $lastInboundTime) / 3600;

            if ($hoursPassed > 24 && $hoursPassed <= 168) {
                // Between 24 hours and 7 days (168 hours): apply HUMAN_AGENT tag
                $isHumanAgent = true;
            } elseif ($hoursPassed > 168) {
                // Over 7 days: standard & human agent messaging windows are closed
                return [
                    'success' => false,
                    'message' => 'The 7-day messaging window has expired. You can no longer reply to this conversation via API.'
                ];
            }
        }

        $success = Yii::$app->facebook->sendTextMessage(
            $contact->platform_uid, 
            $messageText, 
            $parentMetaId,
            $isHumanAgent,
            $outgoingPayload
        );

        if (!$success) {
            Yii::$app->session->setFlash('error', 'Cannot send message: The 24-hour messaging window has expired for this user.');
        }

        if ($success) {
            return [
                'status' => 'success',
                'message' => $messageText,
                'sent_at' => date('H:i'),
                'meta_payload' => $outgoingPayload,
            ];
        }

        return ['status' => 'error', 'message' => 'Failed to send message via Facebook API.'];
    }

        public function actionGetFbComments($parentId)
        {
            Yii::$app->response->format = Response::FORMAT_JSON; 

            // First get all top-level comments
            $topLevelCommentIds = FacebookChatLog::find()
                ->select('meta_message_id')
                ->where([
                    'meta_id_of_parent_msg' => $parentId,
                    'category' => 'COMMENT',
                    'deleted_status' => 0,
                    'is_hidden' => 0,
                ]);

            // Then get both:
            // - comments directly belonging to the post
            // - replies belonging to those comments
            $comments = FacebookChatLog::find()
                ->where([
                    'category' => 'COMMENT',
                    'deleted_status' => 0,
                    'is_hidden' => 0,
                ])
                ->andWhere([
                    'or',
                    ['meta_id_of_parent_msg' => $parentId],
                    ['in', 'meta_id_of_parent_msg', $topLevelCommentIds]
                ])
                ->orderBy(['created_at' => SORT_ASC])
                ->all();

            return [
                'status' => 'success',
                'comments' => $comments,
            ];
        }

    public function actionSendFbPostReply()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $targetMetaId = Yii::$app->request->post('target_meta_id');
        $message      = Yii::$app->request->post('message');

        if (empty($targetMetaId) || empty(trim($message))) {
            return ['status' => 'error', 'message' => 'Message and target ID are required.'];
        }

        // Call FacebookFeed component created in previous step
        $result = Yii::$app->facebook->sendReply($targetMetaId, $message);

        return $result;
    }

    public function actionToggleFbLike()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        // 1. Read post ID (supports post_id or target_meta_id)
        $postId = trim(Yii::$app->request->post('post_id') ?? Yii::$app->request->post('target_meta_id') ?? '');
        
        // 2. Read status sent from JS ('1'/1/'true' = liked, '0'/0/'false' = unliked)
        $currentStatus = Yii::$app->request->post('current_status') ?? Yii::$app->request->post('is_liked');
        
        // Properly evaluate as boolean regardless of whether JS sends 1, "1", 0, "0", true, or false
        $isLiked = filter_var($currentStatus, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) 
            ?? (in_array($currentStatus, [1, '1'], true));

        if (empty($postId)) {
            return ['status' => 'error', 'message' => 'Missing post ID'];
        }

        $accessToken = Yii::$app->facebook->pageAccessToken ?? Yii::$app->params['facebook_page_access_token'] ?? null;
        if (empty($accessToken)) {
            return ['status' => 'error', 'message' => 'Missing Facebook Page Access Token'];
        }

        // 3. Execute Graph API Request
        $ch = curl_init();

        if ($isLiked) {
            // --- UNLIKE ACTION (DELETE Request) ---
            $url = "https://graph.facebook.com/v25.0/{$postId}/likes?" . http_build_query([
                'access_token' => $accessToken
            ]);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        } else {
            // --- LIKE ACTION (POST Request) ---
            $url = "https://graph.facebook.com/v25.0/{$postId}/likes";
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'access_token' => $accessToken
            ]));
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $rawResponse = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($rawResponse, true);

        // 4. On API success, update local DB record (if post exists locally)
        if (isset($data['success']) && $data['success'] === true) {
            $newStatusInt = $isLiked ? 0 : 1; // If previously liked (1), new status is 0, and vice versa

            // Find and update local record if model exists
            $postModel = FacebookChatLog::findOne(['meta_message_id' => $postId]);
            if ($postModel) {
                $postModel->updateAttributes(['liked_by_me' => $newStatusInt]);
            }

            return [
                'status' => 'success',
                'new_status' => $newStatusInt, // 1 or 0 (used by frontend to switch UI)
                'message' => $isLiked ? 'Unliked successfully!' : 'Liked successfully!'
            ];
        }

        return [
            'status' => 'error',
            'message' => $data['error']['message'] ?? 'Failed to update post like status.',
            'debug_response' => $data
        ];
    }


    public function actionGetNewMessages($phone, $last_id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $platform = strtolower(Yii::$app->request->get('platform', 'whatsapp'));
        $category = strtoupper(Yii::$app->request->get('category', 'DM')); // Filter stream type

        if ($platform === 'instagram') {
            $query = InstagramChatLog::find()
                ->where(['platform_uid' => $phone])
                ->andWhere(['category' => $category, 'deleted_status'=>0]); // Now dynamically handles separation inside same table!
        } else {
            $query = WhatsappChatLog::find()->where(['phone_number' => $phone]);
        }

        $newMessages = $query->andWhere(['>', 'id', $last_id])
            ->orderBy(['created_at' => SORT_ASC])
            ->all();

        $html = '';
        $latestId = $last_id;

        foreach ($newMessages as $msg) {
            $latestId = $msg->id;
            if($platform === 'instagram'){
                $html .= $this->renderPartial('insta_message_bubble', ['msg' => $msg,'isInbound' => ($msg->direction === 'inbound')]);
            }
            elseif($platform === 'whatsapp'){
                $html .= $this->renderPartial('message_bubble', ['msg' => $msg,'isInbound' => ($msg->direction === 'inbound')]);
            }

            
        }
 
        return [
            'success' => true,
            'has_new' => !empty($newMessages),
            'html' => $html,
            'latest_id' => $latestId,
        ];
    }

    public function actionDeleteFbComment()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $commentId = Yii::$app->request->post('comment_id');

        if (!$commentId) {
            return ['success' => false, 'error' => 'Missing comment ID.'];
        }
        
        $deleted = Yii::$app->facebook->deleteComment($commentId);

        return ['success' => $deleted];
    }

    public function actionHideFbComment()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        // Read comment_id from POST
        $commentId = Yii::$app->request->post('comment_id');

        if (!$commentId) {
            return ['success' => false, 'error' => 'Missing comment ID.'];
        }

        // Safeguard: Do not allow hiding outbound comments (sent by yourself)
        $log = FacebookChatLog::findOne(['meta_message_id' => $commentId]);
        if ($log && $log->direction === 'outbound') {
            return ['success' => false, 'error' => 'You cannot hide your own outbound comments.'];
        }

        // Call FacebookService
        return Yii::$app->facebook->toggleHideComment($commentId);
    }

    public function actionReportFbComment()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $commentId = Yii::$app->request->post('comment_id');

        // Flag locally for admin moderation review
        $log = FacebookChatLog::findOne(['meta_message_id' => $commentId]);
        if ($log) {
            $log->is_reported = 1; // Ensure column exists or handle via local logs
            $log->save(false);
        }

        return ['success' => true, 'message' => 'Comment has been flagged for review.'];
    }
    /**
     * AJAX Action: Delete a Facebook Direct Message (DM)
     */
    public function actionDeleteFbMessage()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $request = Yii::$app->request;

        if (!$request->isPost) {
            return ['success' => false, 'error' => 'Invalid HTTP method context.'];
        }

        // 1. Retrieve parameters from AJAX request
        $messageId = $request->post('message_id'); // Local DB ID or Meta ID
        $metaMessageId = $request->post('meta_message_id');

        if (empty($messageId) && empty($metaMessageId)) {
            return ['success' => false, 'error' => 'Message identifier parameter is missing.'];
        }

        // 2. Locate the message in the local FacebookChatLog database
        $log = null;
        if ($metaMessageId) {
            $log = FacebookChatLog::findOne(['meta_message_id' => $metaMessageId]);
        }
        if (!$log && $messageId) {
            $log = FacebookChatLog::findOne($messageId);
        }

        if (!$log) {
            return ['success' => false, 'error' => 'Message record not found in local log.'];
        }

        try {
            $deletedOnMeta = false;

            // 3. Attempt to delete/unsend the message via Facebook Graph API component if meta_message_id exists
            if (!empty($log->meta_message_id)) {
                // If your Facebook component has a dedicated delete/unsend method:
                if (method_exists(Yii::$app->facebook, 'deleteMessage')) {
                    $deletedOnMeta = Yii::$app->facebook->deleteMessage($log->meta_message_id);
                } else {
                    // Fallback Direct Graph API request (DELETE /{message-id})
                    $accessToken = Yii::$app->facebook->pageAccessToken ?? Yii::$app->params['facebook_page_access_token'] ?? null;
                    if ($accessToken) {
                        $ch = curl_init("https://graph.facebook.com/v25.0/{$log->meta_message_id}?access_token=" . urlencode($accessToken));
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);

                        $deletedOnMeta = ($httpCode === 200);
                    }
                }
            }

            // 4. Update soft-delete status or remove local DB record
            $log->deleted_status = 1;
            $log->deleted_at = date('Y-m-d H:i:s');

            if ($log->save(false)) {
                return [
                    'success' => true,
                    'meta_deleted' => $deletedOnMeta,
                    'message' => 'Message deleted successfully.'
                ];
            }

            return ['success' => false, 'error' => 'Failed to update local database log record.'];

        } catch (\Throwable $e) {
            Yii::error("Facebook DM Delete Error: " . $e->getMessage(), 'facebook_webhook');
            return ['success' => false, 'error' => 'System exception: ' . $e->getMessage()];
        }
    }

    public function actionForwardFacebookMessage()
{
    Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

    try {
        $targetContactId = Yii::$app->request->post('target_contact_id');
        $messageBody     = Yii::$app->request->post('message_body');

        $contact = SocialContact::findOne($targetContactId);
        if (!$contact || empty($messageBody)) {
            return ['status' => 'error', 'message' => 'Invalid contact or message payload.'];
        }

        // Call Facebook Service
        $success = Yii::$app->facebook->sendTextMessage($contact->platform_uid, $messageBody);

        if ($success) {
            return ['status' => 'success', 'message' => 'Message forwarded successfully.'];
        }

        return ['status' => 'error', 'message' => 'Failed to forward message via Facebook API.'];
    } catch (\Exception $e) {
        Yii::error("Facebook Forward Error: " . $e->getMessage(), __METHOD__);
        return ['status' => 'error', 'message' => 'Internal server error: ' . $e->getMessage()];
    }
}

protected function getFacebookUserName(string $psid): string
{
    $accessToken = Yii::$app->facebook->pageAccessToken ?? Yii::$app->params['facebook_page_access_token'] ?? null;
    
    if (!$accessToken) {
        Yii::error("Facebook Page Access Token missing in params", 'facebook_webhook');
        return 'Facebook User';
    }

    $client = new \yii\httpclient\Client();
    
    try {
        $response = $client->createRequest()
            ->setMethod('GET')
            ->setUrl("https://graph.facebook.com/v25.0/{$psid}")
            ->setData([
                'fields' => 'name',
                'access_token' => $accessToken,
            ])
            ->send();

        if ($response->isOk && isset($response->data['name'])) {
            return $response->data['name'];
        }
    } catch (\Throwable $e) {
        Yii::error("Failed to fetch FB user name: " . $e->getMessage(), 'facebook_webhook');
    }

    return 'Facebook';
}
    
    //************************************************//
    //---------Instagram Controller Functions---------//
    //************************************************//    



    public function actionConnectInstagram()
    {
        $params = Yii::$app->params['instagramId'];

        // Required permissions for Instagram Graph API
        $scopes = [
            // Core & Authentication
            'public_profile',
            'business_management',
            'pages_show_list',
            'pages_read_engagement',
            'pages_manage_metadata',
            'pages_messaging',

            // Instagram Content & Publishing
            'instagram_basic',
            'instagram_content_publish',
            'instagram_manage_contents',

            // Instagram Interaction & Analytics
            'instagram_manage_comments',
            'instagram_manage_messages',
            'instagram_manage_engagement',
        ];

        // Generate CSRF state token
        $state = Yii::$app->security->generateRandomString(16);
        Yii::$app->session->set('instagram_oauth_state', $state);

        $graphVersion = 'v25.0'; 

        $authUrl = 'https://www.facebook.com/' . $graphVersion . '/dialog/oauth?' . http_build_query([
            'client_id'     => Yii::$app->params['app_id'],
            'redirect_uri'  => 'YOUR_REDIRECT_URI',
            'scope'         => implode(',', $scopes),
            'state'         => $state,
            'response_type' => 'code',
        ]);

        return $this->redirect($authUrl);
    }

    /**
     * Step 2: Meta OAuth Callback Handler
     * Route: /webhook/instagram-callback
     */
    public function actionInstagramCallback()
    {
        $request     = Yii::$app->request;
        $appParams   = Yii::$app->params;                          
        $code        = $request->get('code');
        $state       = $request->get('state');
        $storedState = Yii::$app->session->get('instagram_oauth_state');

        // 1. Verify CSRF state token
        if (!$state || $state !== $storedState) {
            Yii::$app->session->setFlash('error', 'Invalid security state token. Authentication aborted.');
            return $this->redirect(['/home/index']);
        }
        Yii::$app->session->remove('instagram_oauth_state');

        if (!$code) {
            Yii::$app->session->setFlash('error', 'Instagram authorization was denied or failed.');
            return $this->redirect(['/home/index']);
        }

        try {
            $clientId     = $appParams['app_id'] ?? '';
            $clientSecret = $appParams['app_secret'] ?? '';
            $graphVersion = 'v25.0';

            // A. Exchange code for Short-Lived Access Token
            $shortTokenRes = $this->fetchJson("https://graph.facebook.com/{$graphVersion}/oauth/access_token", [
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'code'          => $code,
                'redirect_uri'  => 'YOUR_REDIRECT_URI',
            ]);

            if (empty($shortTokenRes['access_token'])) {
                throw new \Exception('Failed to retrieve short-lived access token.');
            }

            // B. Exchange Short-Lived Token for Long-Lived Access Token (60-day validity)
            $longTokenRes = $this->fetchJson("https://graph.facebook.com/{$graphVersion}/oauth/access_token", [
                'grant_type'        => 'fb_exchange_token',
                'client_id'         => $clientId,
                'client_secret'     => $clientSecret,
                'fb_exchange_token' => $shortTokenRes['access_token'],
            ]);

            $longLivedToken   = $longTokenRes['access_token'] ?? $shortTokenRes['access_token'];
            $expiresInSeconds = $longTokenRes['expires_in'] ?? 5184000; // Default 60 days
            $tokenExpiresAt   = date('Y-m-d H:i:s', time() + $expiresInSeconds);

            // C. Fetch Pages & Comprehensive Instagram Business Profile Details
            $pagesRes = $this->fetchJson("https://graph.facebook.com/{$graphVersion}/me/accounts", [
                'fields'       => 'id,name,access_token,instagram_business_account{id,username,name,profile_picture_url,followers_count,follows_count,media_count,biography,website}',
                'access_token' => $longLivedToken,
            ]);

            $instagramAccount = null;
            $linkedPage       = null;

            // Clean single loop to extract page and instagram account details
            if (!empty($pagesRes['data'])) {
                foreach ($pagesRes['data'] as $page) {
                    if (!empty($page['instagram_business_account'])) {
                        $instagramAccount = $page['instagram_business_account'];
                        $linkedPage = [
                            'id'           => $page['id'],
                            'name'         => $page['name'],
                            'access_token' => $page['access_token'] ?? null,
                        ];
                        break;
                    }
                }
            }

            if (!$instagramAccount) {
                Yii::$app->session->setFlash('error', 'No linked Instagram Business/Creator Account found. Ensure your Instagram account is connected to a Facebook Page.');
                return $this->redirect(['/home/index']);
            }

            // D. Automatically Subscribe Linked Page to Webhooks
            if ($linkedPage && !empty($linkedPage['id'])) {
                try {
                    $pageId          = $linkedPage['id'];
                    $pageAccessToken = $linkedPage['access_token'];

                    $fields = implode(',', [
                        'feed',
                        'messages',
                        'mention'
                    ]);

                    $guzzleClient = new \GuzzleHttp\Client();
                    $guzzleClient->post("https://graph.facebook.com/v25.0/{$pageId}/subscribed_apps", [
                        'query' => [
                            'subscribed_fields' => $fields,
                            'access_token'      => $pageAccessToken
                        ]
                    ]);

                    Yii::info("Successfully subscribed Page ID {$pageId} to fields: {$fields}", 'webhook_sub');
                } catch (\Exception $e) {
                    // Log subscription failure without interrupting DB save workflow
                    Yii::error("Failed to subscribe Page webhooks: " . $e->getMessage(), 'webhook_sub');
                }
            }

            // E. Save / Update in SocialAccount Table
            $platformId = (string)$instagramAccount['id'];

            $model = SocialAccount::findOne([
                'platform_type' => 'instagram',
                'platform_id'   => $platformId,
            ]);

            if (!$model) {
                $model = new SocialAccount();
                $model->platform_type = 'instagram';
                $model->platform_id   = $platformId;
            }

            $model->account_name  = $instagramAccount['name'] ?? $instagramAccount['username'] ?? 'Instagram Business';
            $model->access_token  = $linkedPage['access_token'];
            $model->active_status = '1';

            // Detailed payload for platform_data JSON column
            $platformData = [
                'username'         => $instagramAccount['username'] ?? '',
                'name'             => $instagramAccount['name'] ?? '',
                'profile_pic_url'  => $instagramAccount['profile_picture_url'] ?? '',
                'avatar_url'       => $instagramAccount['profile_picture_url'] ?? '',
                'followers_count'  => $instagramAccount['followers_count'] ?? 0,
                'follows_count'    => $instagramAccount['follows_count'] ?? 0,
                'media_count'      => $instagramAccount['media_count'] ?? 0,
                'biography'        => $instagramAccount['biography'] ?? '',
                'website'          => $instagramAccount['website'] ?? '',
                'linked_page_id'   => $linkedPage['id'] ?? null,
                'linked_page_name' => $linkedPage['name'] ?? null,
                'token_expires_at' => $tokenExpiresAt,
                'connected_at'     => date('Y-m-d H:i:s'),
                'user_access_token' => $longLivedToken,
            ];

            $model->platform_data = Json::encode($platformData);
            $model->updated_at    = date('Y-m-d H:i:s');

            if ($model->isNewRecord) {
                $model->created_at = date('Y-m-d H:i:s');
            }

            if ($model->save()) {
                // Trigger Initial Message Sync
                try {

                Yii::$app->instagram->instaAccId = $platformId;
                Yii::$app->instagram->instaAccessToken = $linkedPage['access_token'];

                    // Sync up to 50 existing conversation threads immediately
                    $syncResult = Yii::$app->instagram->syncLiveMessages(25);

                    Yii::error(
                        "Initial Instagram message sync completed: " . json_encode($syncResult), 
                        'instagram_oauth'
                    );

                    Yii::$app->session->setFlash(
                        'success', 
                        'Instagram account connected!'
                    );

                } catch (\Throwable $syncError) {
                    // Log sync failure gracefully so it doesn't break the successful account setup
                    Yii::error(
                        'Initial Instagram DM Sync failed: ' . $syncError->getMessage(), 
                        'instagram_oauth'
                    );

                    Yii::$app->session->setFlash(
                        'success', 
                        'Instagram account connected successfully, but initial message import had an issue.'
                    );
                }

            } else {
                Yii::error('Failed saving Instagram account: ' . \yii\helpers\Json::encode($model->getErrors()), __METHOD__);
                Yii::$app->session->setFlash('error', 'Failed to save Instagram account: ' . implode(', ', $model->getFirstErrors()));
            }
                
        } catch (\Throwable $e) {
            Yii::error('Instagram OAuth Exception: ' . $e->getMessage(), __METHOD__);
            Yii::$app->session->setFlash('error', 'Connection Error: ' . $e->getMessage());
        }

        return $this->redirect(['/home/index']);
    }                           

    /**
     * Helper to perform HTTP GET requests using cURL
     */
    /**
     * Helper to perform HTTP GET requests using cURL
     */
    private function fetchJson($url, array $params = [])
    {
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("cURL Error: " . $error);
        }

        return json_decode($response, true);
    }

    public function actionInstagramIndex($phone = null)
    {
        $this->layout = 'main';

        $instaAccId = Yii::$app->instagram->instaAccId;

        // 1. Filter connected contacts directly in MySQL via JSON_CONTAINS
        $contacts = SocialContact::find()
            ->where(['platform' => 'INSTAGRAM'])
            ->andWhere('JSON_CONTAINS(connected_with, :accId)', [':accId' => json_encode((string)$instaAccId)])
            ->orderBy(['id' => SORT_DESC])
            ->all();
        
        $messages = [];
        $activeContact = null;

        // 2. If a specific Instagram thread is selected, load from your new log model
        if ($phone) {
            $activeContact = SocialContact::find()
                ->where(['platform_uid' => $phone, 'platform' => 'INSTAGRAM'])
                ->one();
            
            if ($activeContact) {
                // Fetch live profile picture URL from Instagram API
                $latestProfile = Yii::$app->instagram->getInstagramProfile($phone); 
                $latestProfilePic = $latestProfile['profile_pic'] ?? null; 
                $username = $latestProfile['username'];
                
                // Compare fetched URL with the database record and update if different
                if ($activeContact->profile_pic !== $latestProfilePic) {
                    $activeContact->profile_pic = $latestProfilePic;
                    $activeContact->username = $username;
                    $activeContact->save(false, ['profile_pic', 'username']); // Save only the fields
                }
            }
            // Querying exclusively from your separate Instagram log table
            $messages = InstagramChatLog::find()
                ->where(['platform_uid' => $phone, 'category' => 'DM', 'deleted_status' => 0, 'connected_with' => $instaAccId])
                ->orderBy(['created_at' => SORT_ASC])
                ->all();
        }

        // 3. Render your newly created views/webhook/instagram.php view file
        return $this->render('instagram', [
            'contacts' => $contacts,
            'activePhone' => $phone,
            'messages' => $messages,
            'activeContact' => $activeContact,
            'currentPlatform' => 'instagram'
        ]);
    }


    public function actionInstagramFeedIndex($activePostId = null)
    {
        $this->layout = 'main';

        Yii::$app->instagramFeed->syncLivePosts();

        $instaAccId = Yii::$app->instagram->instaAccId;
        $insta = SocialAccount::find()->where(['platform_id' => $instaAccId])->one();
        $data = json_decode($insta->platform_data,true);
        $profile = $data['profile_pic_url'];

        $posts = InstagramChatLog::find()
            ->where(['category' => 'POST', 'deleted_status' => 0])
            ->orderBy(['created_at' => SORT_DESC])
            ->all();
        


        if (!$activePostId && !empty($posts)) {
            $activePost = $posts[0];
        } else {
            $activePost = InstagramChatLog::findOne(['meta_message_id' => $activePostId]);
        }

        $activePost = null;
        $comments = [];
        $liveLikeCount = 0;
        $liveCommentsCount = 0;  
        $permalink = '';                          
        
        if ($activePostId) {
            $activePost = InstagramChatLog::findOne(['meta_message_id' => $activePostId]);
            
            if ($activePost) {
                // 1. Perform live sync for post comments AND nested replies
                Yii::$app->instagramFeed->syncLiveComments($activePost->meta_message_id);

                // 2. Get top-level parent comment IDs
                $parentCommentIds = InstagramChatLog::find()
                    ->select('meta_message_id')
                    ->where(['meta_id_of_parent_msg' => $activePostId, 'category' => 'COMMENT']);

                // 3. Retrieve non-deleted parent comments and their child replies
                $comments = InstagramChatLog::find()
                    ->where(['category' => 'COMMENT', 'deleted_status' => 0])
                    ->andWhere([
                        'or',
                        ['meta_id_of_parent_msg' => $activePostId],              // Parent Comments
                        ['in', 'meta_id_of_parent_msg', $parentCommentIds]       // Child Replies
                    ])
                    ->orderBy(['created_at' => SORT_ASC])
                    ->all();

                try {
                    $metaData = Yii::$app->instagramFeed->getMediaFields($activePost->meta_message_id, ['like_count', 'comments_count','is_comment_enabled', 'permalink']);
                    $liveLikeCount = (int)($metaData['like_count'] ?? 0);
                    $liveCommentsCount = (int)($metaData['comments_count'] ?? 0);
                    $permalink = $metaData['permalink'] ?? "Couldn't find the link ";

                    if (isset($metaData['is_comment_enabled'])) {
                        $isCommentEnabled = $metaData['is_comment_enabled'] ? 1 : 0;

                        // Only update DB if the state has actually changed from what you currently store
                        if ($activePost->enabled_comments !== $isCommentEnabled) {
                            $activePost->enabled_comments = $isCommentEnabled;
                            $activePost->save(false, ['enabled_comments']);
                        }
                    }
                } catch (\Exception $e) {
                    Yii::error("Meta API Like Count Fetch Failure: " . $e->getMessage());
                }
            }
        }

        return $this->render('instagram_feed', [
            'posts' => $posts,
            'activePost' => $activePost,
            'comments' => $comments,
            'liveLikeCount' => $liveLikeCount,
            'liveCommentCount' => $liveCommentsCount,
            'name' => $insta->account_name,
            'pic' => $profile,
            'permalink' => $permalink,
        ]);
    }

     public function actionLikePost()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $mediaId = Yii::$app->request->post('media_id');

        if (!$mediaId) {
            return ['success' => false, 'error' => 'Missing media reference identifier.'];
        }

        $success = Yii::$app->instagramFeed->likePost($mediaId);
        return ['success' => $success];
    }

    public function actionUnlikePost()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $mediaId = Yii::$app->request->post('media_id');

        if (!$mediaId) {
            return ['success' => false, 'error' => 'Missing media reference identifier.'];
        }

        $success = Yii::$app->instagramFeed->unlikePost($mediaId);
        return ['success' => $success];
    }

    //Route: /webhook/send-comment-reply. Handles dedicated public feed comment posting and replies.
    public function actionSendCommentReply()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $request = Yii::$app->request;

        if (!$request->isPost) {
            return ['success' => false, 'error' => 'Invalid access context.'];
        }

        $targetId = $request->post('target_id'); 
        $messageBody = $request->post('message_body');
        $isReply = ((int)$request->post('is_reply', 0) === 1);

        if (empty($targetId) || empty($messageBody)) {
            return ['success' => false, 'error' => 'Missing target reference or comment context.'];
        }
        
        try {
            // 1. Dispatch the data to the Meta Graph API via component
            if ($isReply) {
                $apiResult = Yii::$app->instagramFeed->replyToComment($targetId, $messageBody);
            } else {
                $apiResult = Yii::$app->instagramFeed->sendCommentToMedia($targetId, $messageBody);
            }

            // 2. Validate Meta API response
            // Note: Check components/instagram/InstagramFeedService.php to ensure 
            // replyToComment() returns an array containing ['id' => '...'] instead of a boolean.
            $metaCommentId = is_array($apiResult) ? ($apiResult['id'] ?? null) : null;

            if (!$metaCommentId) {
                return [
                    'success' => false, 
                    'error' => 'Meta API did not return a valid comment ID.'
                ];
            }

            // 3. Deduplication Check
            $existingLog = InstagramChatLog::find()
                ->where(['meta_message_id' => $metaCommentId])
                ->orWhere([
                    'message_body' => $messageBody,
                    'direction' => 'outbound',
                    'category' => 'COMMENT'
                ])
                ->one();

            if ($existingLog) {
                $existingLog->meta_id_of_parent_msg = $targetId;
                if (empty($existingLog->meta_message_id)) {
                    Yii::error('inside'.$existingLog->meta_message_id);
                    $existingLog->meta_message_id = $metaCommentId;
                    $existingLog->save(false);
                }
            } else {
                // 4. Persist the record inside DB
                $log = new InstagramChatLog();
                $log->meta_message_id = $metaCommentId;
                $log->platform_uid = Yii::$app->params['instaAppId'] ?? null;
                $log->commenter_username = 'You'; 
                $log->meta_id_of_parent_msg = $targetId; 
                $log->message_body = $messageBody;
                $log->direction = 'outbound';
                $log->category = 'COMMENT';
                $log->type = 'text';
                $log->created_at = date('Y-m-d H:i:s');

                if (!$log->save()) {
                    return [
                        'success' => false, 
                        'error' => 'Failed to save comment locally: ' . json_encode($log->getErrors())
                    ];
                }
            }

            // 5. Render view block
            $html = $this->renderPartial('insta_comment_bubble', [
                'msg' => $log ?? $existingLog,
                'isReply' => $isReply
            ]);

            return [
                'success' => true,
                'id' => ($log ?? $existingLog)->id,
                'meta_comment_id' => $metaCommentId,
                'html' => $html
            ];

        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    //delete-comment
    public function actionDeleteComment()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $request = Yii::$app->request;

        if (!$request->isPost) {
            return ['success' => false, 'error' => 'Invalid method context.'];
        }

        $targetCommentId = $request->post('comment_id');
        if (empty($targetCommentId)) {
            return ['success' => false, 'error' => 'Missing target comment reference context.'];
        }

        // Find the record using the Meta ID passed from the frontend data-comment-id
        $comment = InstagramChatLog::findOne(['meta_message_id' => $targetCommentId]);
        if (!$comment) {
            return ['success' => false, 'error' => 'Comment record not found in your database.'];
        }

        try {                  

            // Reusing your unified Instagram component action that runs executeCurl under the hood
            $metaDeleted = Yii::$app->instagramFeed->deleteComment($comment->meta_message_id);              

            if ($metaDeleted) {
                $comment->deleted_status = 1;
                $comment->deleted_at = date('Y-m-d H:i:s'); 

                if ($comment->save()) {
                    return ['success' => true];
                }
                return ['success' => false, 'error' => 'Deleted on Instagram, but local record removal failed.'];
            }

            return ['success' => false, 'error' => 'Meta API refused deletion execution workflow.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    //Create a brand new Post
    public function actionCreateFeedPost()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $request = Yii::$app->request;

        if (!$request->isPost) {
            return [
                'success' => false,
                'error' => 'Invalid HTTP request method.'
            ];
        }

        $message = trim($request->post('message', ''));

        $uploadedFile = UploadedFile::getInstanceByName('image');

        if (!$uploadedFile) {
            return [
                'success' => false,
                'error' => 'Please select an image.'
            ];
        }

        try {

            // ---------------------------------------------------------
            // 1. Prepare upload directory
            // ---------------------------------------------------------
            $uploadDir = Yii::getAlias('@webroot') . '/uploads/';

            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
                    throw new \Exception(
                        'Unable to create upload directory: ' . $uploadDir
                    );
                }
            }

            // ---------------------------------------------------------
            // 2. Generate unique filename
            // ---------------------------------------------------------
            $fileName = 'post_upload_' .
                date('Ymd_His') . '_' .
                Yii::$app->security->generateRandomString(8) .
                '.' .
                strtolower($uploadedFile->extension);

            $imagePath = $uploadDir . $fileName;

            // ---------------------------------------------------------
            // 3. Save image locally
            // ---------------------------------------------------------
            if (!$uploadedFile->saveAs($imagePath)) {

                Yii::error(
                    'Failed to save Instagram upload: ' . $imagePath,
                    'instagram_create_post'
                );

                return [
                    'success' => false,
                    'error' => 'Failed to save uploaded image locally.'
                ];
            }

            // Verify physical file really exists
            if (!file_exists($imagePath)) {

                Yii::error(
                    'Image was reported as saved but does not exist: ' . $imagePath,
                    'instagram_create_post'
                );

                return [
                    'success' => false,
                    'error' => 'Uploaded imimage was not found after saving.'
                ];
            }

            // ---------------------------------------------------------
            // 4. Build PUBLIC URL for Instagram
            // ---------------------------------------------------------
            $imageUrl = Yii::$app->request->hostInfo .
                Yii::$app->request->baseUrl .
                '/uploads/' .
                $fileName;

            Yii::info(
                'Instagram image saved locally: ' . $imagePath,
                'instagram_create_post'
            );

            Yii::info(
                'Instagram public image URL: ' . $imageUrl,
                'instagram_create_post'
            );

            // ---------------------------------------------------------
            // 5. Publish to Instagram
            // ---------------------------------------------------------
            $postId = Yii::$app->instagramFeed->createPost(
                $imageUrl,
                $message,
                '/uploads/' . $fileName
            );

            if (!$postId) {

                Yii::error(
                    'Instagram post publishing failed.',
                    'instagram_create_post'
                );

                return [
                    'success' => false,
                    'error' => 'Image was saved locally, but Instagram publishing failed.',
                    'image_url' => $imageUrl
                ];
            }

            // ---------------------------------------------------------
            // 6. Success
            // ---------------------------------------------------------
            return [
                'success' => true,
                'post_id' => $postId,
                'image_url' => '/uploads/' . $fileName,
                'message' => 'Instagram post published successfully.'
            ];

        } catch (\Throwable $e) {

            Yii::error(
                'Instagram Create Post Error: ' .
                $e->getMessage() .
                "\nFile: " . $e->getFile() .
                "\nLine: " . $e->getLine(),
                'instagram_create_post'
            );

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function actionSendInstaMessage()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $request = Yii::$app->request;

        $recipientId = $request->post('recipient_id');
        $messageText = $request->post('message');
        $replyToMid  = $request->post('reply_to_mid');
        $accountId   = $request->post('account_id');

        if (empty($recipientId) || empty($messageText)) {
            return [
                'success' => false,
                'message' => 'Recipient ID and message text are required.'
            ];
        }

        try {
            $socialAccount = SocialAccount::findOne(['id' => $accountId, 'platform_type' => 'instagram']);
            if (!$socialAccount) {
                $socialAccount = SocialAccount::findOne(['platform_type' => 'instagram', 'active_status' => '1']);
            }

            if (!$socialAccount || empty($socialAccount->access_token)) {
                return [
                    'success' => false,
                    'message' => 'No valid Instagram connected account found.'
                ];
            }

            // Fetch the latest INBOUND message received from this recipient
            $lastInbound = InstagramChatLog::find()
                ->where(['platform_uid' => $recipientId, 'direction' => 'inbound'])
                ->orderBy(['created_at' => SORT_DESC])
                ->one();

            $isHumanAgent = false;

            if ($lastInbound && !empty($lastInbound->created_at)) {
                $lastInboundTime = strtotime($lastInbound->created_at);
                $currentTime     = time();
                
                // Calculate elapsed time in hours
                $hoursPassed = ($currentTime - $lastInboundTime) / 3600;

                if ($hoursPassed > 24 && $hoursPassed <= 168) {
                    // Between 24 hours and 7 days (168 hours): apply HUMAN_AGENT tag
                    $isHumanAgent = true;
                } elseif ($hoursPassed > 168) {
                    // Over 7 days: standard & human agent messaging windows are closed
                    return [
                        'success' => false,
                        'message' => 'The 7-day messaging window has expired. You can no longer reply to this conversation via API.'
                    ];
                }
            }
            

            $instagramService = new InstagramService([
                'instaAccId'       => $socialAccount->platform_id,
                'instaAccessToken' => $socialAccount->access_token,
            ]);

            $response = $instagramService->sendTextMessage($recipientId, $messageText, $replyToMid, $isHumanAgent);
            $metaMessageId = $response['message_id'] ?? null;

            // Save outbound log with full metadata matching webhook structure
            $log = new InstagramChatLog();
            $log->platform_uid    = $recipientId;
            $log->message_body    = $messageText;
            $log->direction       = 'outbound';
            $log->type            = 'text';
            $log->category        = 'DM';
            $log->meta_message_id = $metaMessageId;
            $log->connected_with  = Json::encode([(string)$socialAccount->platform_id]);
            $log->created_at      = date('Y-m-d H:i:s');

            if (!$log->save()) {
                Yii::error("Failed to log outbound DM: " . json_encode($log->getErrors()), 'instagram');
            }

            $html = $this->renderPartial('insta_message_bubble', [
                'msg' => $log,
                'isInbound' => false
            ]);

            return [
                'success'    => true,
                'id'         => $log->id,        // Essential for advancing lastMessageId
                'message_id' => $metaMessageId,
                'html'       => $html,          // Rendered HTML bubble
                'data'       => $response
            ];

        } catch (\Exception $e) {
            Yii::error("Instagram Send Message Error: " . $e->getMessage(), 'instagram');
            return [
                'success' => false,
                'error'   => $e->getMessage()
            ];
        }
    }

    public function actionDeletePost()
    {
       Yii::$app->response->format = Response::FORMAT_JSON;
        $request = Yii::$app->request;

        if (!$request->isPost) {
            return ['success' => false, 'error' => 'Invalid HTTP method context.'];
        }

        $postId = $request->post('media_id');
        if (empty($postId)) {
            return ['success' => false, 'error' => 'Post ID parameter is missing.'];
        }

        $success = Yii::$app->instagramFeed->deletePost($postId);

        if ($success) {
            return ['success' => true];
        }

        return ['success' => false, 'error' => 'Failed to delete post on Facebook or database.'];
    }

    public function actionToggleComments()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $mediaId = Yii::$app->request->post('media_id');
        $post = InstagramChatLog::find()->where(['meta_message_id' => $mediaId])->one();
        
        // Optionally accept status from request ('true' or 'false')
        $enableComments = Yii::$app->request->post('enable', 1); 

        if (!$mediaId) {
            return ['success' => false, 'error' => 'Media ID is required.'];
        }

        $accessToken = Yii::$app->instagramFeed->instaAccessToken ?? '';
        $client = new \GuzzleHttp\Client();

        try {
            // Correct Endpoint: POST to /{ig-media-id} with comment_enabled
            $response = $client->post("https://graph.facebook.com/v25.0/{$mediaId}", [
                'form_params' => [
                    'comment_enabled' => ($enableComments === '0' || $enableComments === 0) ? 'true' : 'false',
                    'access_token'    => $accessToken
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['success']) && $data['success'] === true) {
                $enabled  = $enableComments == 1 ? 0 : 1;
                $post->enabled_comments = $enabled;
                $post->save(false);                       
                return ['success' => true, 'message' => 'Comment settings updated successfully.'];
            }
            
            return ['success' => true, 'message' => 'Updated successfully.', 'data' => $data];

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $errorResponse = [];
            if ($e->hasResponse()) {
                $errorResponse = json_decode($e->getResponse()->getBody()->getContents(), true);
            }
            
            return [
                'success' => false, 
                'error'   => $errorResponse['error']['message'] ?? $e->getMessage()
            ];
        }
    }

    
    //=================================================================================================================================
    public function actionToggleBotState()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $request = Yii::$app->request;

        if (!$request->isPost) {
            return ['success' => false, 'error' => 'Invalid request access context.'];
        }

        $phone = $request->post('phone_number');
        $action = $request->post('target_action');

        $contact = SocialContact::findOne(['platform_uid' => $phone]);
        if (!$contact) {
            return ['success' => false, 'error' => 'Contact tracking identity not found.'];
        }

        // Set bot_state flag based on choice
        if ($action === 'pause') {
            $contact->bot_state = 'turned_off';
        } else {
            $contact->bot_state = 'initial_state';
            $contact->last_parent_id = null;
        }

        if ($contact->save()) {
            return ['success' => true];
        }

        return ['success' => false, 'error' => 'Database persistence synchronization error occurred.'];
    } 

    /**
     * Error handler
     */
    public function actionError()
    {
        $exception = Yii::$app->errorHandler->exception;

        if ($exception !== null) {
            $statusCode = $exception instanceof \yii\web\HttpException
                ? $exception->statusCode
                : 500;

            return $this->render('error', [
                'name' => $exception->getName(),
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);
        }

        return $this->render('error', [
            'name' => 'Error',
            'message' => 'An unknown error occurred.',
            'exception' => null,
        ]);
    }


}