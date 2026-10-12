<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\AccessControl;
use yii\helpers\Json;
use app\models\SocialAccount;
use app\models\SocialContact;
use app\models\FacebookChatLog;

class AuthController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['auth'],
                        'allow' => true,
                        'roles' => ['?', '@'],
                    ],
                    [
                        'actions' => ['select-page', 'save-selected-page'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    public function actions()
    {
        return [
            'auth' => [
                'class' => 'yii\authclient\AuthAction',
                'successCallback' => [$this, 'onAuthSuccess'],
            ],
        ];
    }

    public function onAuthSuccess($client)
    {
        $clientId = $client->getId(); 
        
        if ($clientId === 'facebook') {
            return $this->connectFacebookAccount($client);
        }

        return $this->redirect(['home/index']);
    }

    protected function connectFacebookAccount($client)
    {
        if (Yii::$app->user->isGuest) {
            Yii::$app->session->setFlash('error', 'Please log in with your account credentials first.');
            return $this->redirect(['authentication/login']);
        }

        // 1. Get the AccessToken object from Yii AuthClient
        $tokenObject = $client->getAccessToken();

        // 2. Extract the raw User Access Token string
        $userAccessToken = $tokenObject->getToken();

        $attributes = $client->getUserAttributes();
        $fbUserId   = $attributes['id'] ?? null;
        $fbUserName = $attributes['name'] ?? null;
        $fbUserEmail = $attributes['email'] ?? null;
        $fbUserPic   = $attributes['picture']['data']['url'] ?? null;

        if (!$fbUserId) {
            Yii::$app->session->setFlash('error', 'Unable to retrieve Facebook user details.');
            return $this->redirect(['home/index']);
        }

        try {
            // Fetch Pages with extended fields including picture, followers, link, and linked Instagram Business account
            $response = $client->api('me/accounts', 'GET', [
                'fields' => 'id,name,access_token,category,picture.type(large),fan_count,followers_count,link,instagram_business_account',
            ]);

            $pages = $response['data'] ?? [];

            if (empty($pages)) {
                Yii::$app->session->setFlash('error', 'No Facebook Pages found under this account.');
                return $this->redirect(['home/index']);
            }

            // Save Facebook user info & detailed page list into session
            Yii::$app->session->set('fb_oauth_data', [
                'fb_user_id'    => $fbUserId,
                'fb_user_name'  => $fbUserName,
                'fb_user_email' => $fbUserEmail,
                'fb_user_pic'   => $fbUserPic,
                'pages'         => $pages,
            ]);

            return $this->redirect(['auth/select-page']);

        } catch (\Exception $e) {
            Yii::error('Facebook OAuth Error: ' . $e->getMessage(), __METHOD__);
            Yii::$app->session->setFlash('error', 'Failed to fetch Facebook Pages: ' . $e->getMessage());
            return $this->redirect(['home/index']);
        }
    }

    public function actionSelectPage()
    {
        $oauthData = Yii::$app->session->get('fb_oauth_data');

        if (empty($oauthData) || empty($oauthData['pages'])) {
            Yii::$app->session->setFlash('error', 'Facebook connection session expired. Please connect again.');
            return $this->redirect(['home/index']);
        }

        return $this->render('select-page', [
            'fbUserName' => $oauthData['fb_user_name'],
            'pages'      => $oauthData['pages'],
        ]);
    }

    public function actionSaveSelectedPage()
    {
        $request   = Yii::$app->request;
        $oauthData = Yii::$app->session->get('fb_oauth_data');

        if (!$request->isPost) {
            return $this->redirect(['home/index']);
        }

        $selectedPageId = $request->post('selected_page_id');

        if (empty($oauthData) || empty($selectedPageId)) {
            Yii::$app->session->setFlash('error', 'Please select a Facebook Page to continue.');
            return $this->redirect(['auth/select-page']);
        }

        $selectedPage = null;
        foreach ($oauthData['pages'] as $page) {
            if ($page['id'] === $selectedPageId) {
                $selectedPage = $page;
                break;
            }
        }

        if (!$selectedPage) {
            Yii::$app->session->setFlash('error', 'Invalid Facebook Page selection.');
            return $this->redirect(['auth/select-page']);
        }

        // Extract Page Profile Picture URL from Graph API response array
        $pagePicUrl = $selectedPage['picture']['data']['url'] ?? null;

        // =========================================================================
        // AUTOMATIC WEBHOOK SUBSCRIPTION TRIGGER FOR MULTI-TENANT SETUP
        // =========================================================================
        try {
            $pageId = $selectedPage['id'];
            $pageAccessToken = $selectedPage['access_token'];

            // Define the exact webhook fields your app listens to
            $subscribedFields = implode(',', [
                'messages',
                'messaging_postbacks',
                'messaging_optins',
                'message_deliveries',
                'message_reads',
                'feed'
            ]);

            $client = new \GuzzleHttp\Client();
            $response = $client->post("https://graph.facebook.com/v25.0/{$pageId}/subscribed_apps", [
                'query' => [
                    'subscribed_fields' => $subscribedFields,
                    'access_token'      => $pageAccessToken,
                ],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            
            if (!isset($result['success']) || $result['success'] !== true) {
                Yii::warning("Meta Webhook Subscription Warning for Page {$pageId}: " . json_encode($result), __METHOD__);
            } else {
                Yii::info("Successfully subscribed Page {$pageId} to fields: {$subscribedFields}", __METHOD__);
            }

        } catch (\Throwable $e) {
            // Log the failure without blocking account connection
            Yii::error('Failed to subscribe Facebook Page to webhooks: ' . $e->getMessage(), __METHOD__);
        }
        // =========================================================================

        // Build comprehensive JSON array for platform_data
        $platformData = [
            'page_id'                   => $selectedPage['id'],
            'page_name'                 => $selectedPage['name'],
            'page_access_token'         => $selectedPage['access_token'],
            'category'                  => $selectedPage['category'] ?? null,
            'profile_pic_url'           => $pagePicUrl,
            'fan_count'                 => $selectedPage['fan_count'] ?? 0,
            'followers_count'           => $selectedPage['followers_count'] ?? 0,
            'page_link'                 => $selectedPage['link'] ?? null,
            'instagram_business_id'     => $selectedPage['instagram_business_account']['id'] ?? null,
            'connected_by_user_id'      => $oauthData['fb_user_id'] ?? null,
            'connected_by_user_name'    => $oauthData['fb_user_name'] ?? null,
            'connected_by_user_email'   => $oauthData['fb_user_email'] ?? null,
            'connected_by_user_pic'     => $oauthData['fb_user_pic'] ?? null,
            'connected_at'              => date('Y-m-d H:i:s'),
        ];

        $userId = Yii::$app->user->id;

        // Fetch or create SocialAccount record bound to current app user and platform ID
        $account = SocialAccount::findOne([
            'platform_type' => 'facebook',
            'platform_id'   => $selectedPage['id'],
        ]) ?? new SocialAccount();

        // Standard core columns
        $account->platform_type       = 'facebook';
        $account->active_status       = '1';
        $account->platform_id         = $selectedPage['id'];
        $account->account_name        = $selectedPage['name'];
        $account->access_token        = $selectedPage['access_token'];

        // Encode enhanced JSON payload into platform_data
        $account->platform_data = json_encode($platformData);

        if ($account->save()) {
            Yii::$app->session->remove('fb_oauth_data');
            Yii::$app->session->setFlash('success', 'Connected Facebook Page: ' . $selectedPage['name']);
            try {
                $this->actionSyncFacebookHistory();
            } catch (\Throwable $e) {
                Yii::error("Initial Facebook DM Sync Failed: " . $e->getMessage(), __METHOD__);
            }
            return $this->redirect(['home/index']);
        }

        Yii::error('Failed saving SocialAccount: ' . json_encode($account->getErrors()), __METHOD__);
        Yii::$app->session->setFlash('error', 'Could not save the selected Facebook Page.');
        return $this->redirect(['auth/select-page']);
    }

      /**
 * Historical DM Sync for Facebook Messenger with Deduplication and Inactive Reset
 * Route: /webhook/sync-facebook-history
 */
public function actionSyncFacebookHistory()
{
    Yii::$app->response->format = Response::FORMAT_JSON;

    // Fetch account regardless of active state during reconnect sync trigger
    $account = SocialAccount::findOne(['platform_type' => 'facebook', 'active_status' => '1']);
    if (!$account || empty($account->access_token)) {
        return ['success' => false, 'error' => 'No active Facebook account found with an access token.'];
    }

    $pageId = (string) $account->platform_id;
    $accessToken = (string) $account->access_token;
    $client = new \GuzzleHttp\Client();

    $syncedMessagesCount = 0;
    $syncedContactsCount = 0;

    try {
        
        // Step 1: Query conversation threads directly
        $convUrl = "https://graph.facebook.com/v25.0/{$pageId}/conversations?fields=id,participants&access_token={$accessToken}";

        while ($convUrl) {
            $convResponse = $client->get($convUrl);
            $convData = json_decode($convResponse->getBody()->getContents(), true);
            $conversations = $convData['data'] ?? [];

            if (empty($conversations)) {
                Yii::error("No conversations returned from Graph API for Page {$pageId}", 'facebook_sync');
            }

            foreach ($conversations as $conversation) {
                $convId = $conversation['id'];
                $participants = $conversation['participants']['data'] ?? [];
                $contactUser = null;

                foreach ($participants as $participant) {
                    if ((string)$participant['id'] !== $pageId) {
                        $contactUser = $participant;
                        break;
                    }
                }

                if (!$contactUser) {
                    Yii::error("Could not resolve contact participant in conversation: " . $convId, 'facebook_sync');
                    continue; 
                }

                $contactPsid = (string) $contactUser['id'];
                $contactName = $contactUser['name'] ?? 'Facebook';
                
                // Ensure contact record exists locally
                $contact = $this->ensureContactSession($contactPsid, 'FACEBOOK', $contactName);
                
                // Decode & sanitize connected_with JSON array strictly
                $rawConnected = $contact->connected_with ?? '[]';
                $connected = Json::decode($rawConnected);
                if (!is_array($connected)) {
                    $connected = !empty($connected) ? [(string)$connected] : [];
                }

                if (!in_array($pageId, $connected, true)) {
                    $connected[] = $pageId;
                    $contact->connected_with = Json::encode(array_values(array_unique($connected)));
                    $contact->save(false);
                }

                // Step 2: Fetch messages for this specific conversation thread with pagination
                $msgUrl = "https://graph.facebook.com/v25.0/{$convId}/messages?fields=id,created_time,from,to,message&limit=100&access_token={$accessToken}";

                while ($msgUrl) {
                    $msgResponse = $client->get($msgUrl);
                    $msgData = json_decode($msgResponse->getBody()->getContents(), true);
                    $messages = $msgData['data'] ?? [];

                    foreach ($messages as $msg) {
                        $metaMsgId = $msg['id'] ?? null;
                        if (!$metaMsgId) continue;

                        $senderId = (string)($msg['from']['id'] ?? '');
                        $direction = ($senderId === $pageId) ? 'outbound' : 'inbound';
                        $body = $msg['message'] ?? '';
                        $createdAt = isset($msg['created_time']) 
                            ? date('Y-m-d H:i:s', strtotime($msg['created_time'])) 
                            : date('Y-m-d H:i:s');

                        $existingLog = FacebookChatLog::findOne(['meta_message_id' => $metaMsgId]);

                        if ($existingLog) {
                            $existingLog->deleted_status = 0;
                            $existingLog->deleted_at = null;
                            $existingLog->connected_with = $pageId;
                            if (!$existingLog->save(false)) {
                                Yii::error("Failed updating log ID {$metaMsgId}: " . print_r($existingLog->getErrors(), true), 'facebook_sync');
                            }
                        } else {
                            $log = new FacebookChatLog();
                            $log->platform_uid = $contactPsid;
                            $log->connected_with = $pageId;
                            $log->meta_message_id = $metaMsgId;
                            $log->message_body = $body;
                            $log->direction = $direction;
                            $log->category = 'DM';
                            $log->type = 'text';
                            $log->created_at = $createdAt;
                            $log->deleted_status = 0;

                            if ($log->save(false)) {
                                $syncedMessagesCount++;
                            } else {
                                Yii::error("Failed saving log ID {$metaMsgId}: " . print_r($log->getErrors(), true), 'facebook_sync');
                            }
                        }
                    }

                    // Paginate through messages within the current conversation thread
                    $msgUrl = $msgData['paging']['next'] ?? null;
                }

                $syncedContactsCount++;
            }

            // Paginate through conversations list
            $convUrl = $convData['paging']['next'] ?? null;
        }

        return [
            'success' => true,
            'message' => "Successfully synced {$syncedMessagesCount} messages across {$syncedContactsCount} contacts.",
        ];

    } catch (\GuzzleHttp\Exception\ClientException $e) {
        $errorBody = $e->getResponse()->getBody()->getContents();
        Yii::error("Facebook Graph API HTTP Error: " . $errorBody, 'facebook_sync');
        return ['success' => false, 'error' => $errorBody];
    } catch (\Throwable $e) {
        Yii::error("Facebook Sync Fatal Failure: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine(), 'facebook_sync');
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

private function ensureContactSession($phone, $platform, $username)
{
     Yii::error('auth controller STARTED', 'instagram_webhook');
    
    $contact = SocialContact::find()->where(['platform_uid' => $phone, 'platform' => $platform])->one();
    
    if (!$contact) {
        $contact = new SocialContact();
        $contact->platform_uid = $phone;
        $contact->bot_state = 'initial_state';
        $contact->platform = $platform;
        
        // Fetch active page ID
        $account = SocialAccount::findOne(['platform_type' => 'facebook', 'active_status' => '1']);
        $pageId = $account->platform_id ?? null;

        // FIX: Extract single string ID if $pageId happens to be an array
        if (is_array($pageId)) {
            $pageId = reset($pageId);
        }

        // Always encode as a JSON array string: '["1291729520684771"]'
        $contact->connected_with = Json::encode($pageId ? [(string)$pageId] : []);
        $contact->username = $username;
        
        if (!$contact->save(false)) {
            Yii::error("Failed saving new contact: " . print_r($contact->getErrors(), true), 'facebook_sync');
        }
    }
    
    return $contact;
}
}