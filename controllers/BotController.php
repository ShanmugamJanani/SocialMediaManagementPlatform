<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\web\BadRequestHttpException;
use app\models\SocialContact;
use app\models\WhatsappChatLog;

class BotController extends Controller
{
    public $enableCsrfValidation = false;
  

    /**
     * WEBHOOK ENTRY POINT (GET Validation & POST Automation Orchestration)
     */
    /**
     * WEBHOOK ENTRY POINT (GET Validation & POST Automation Orchestration)
     */
    public function actionWebhook()
    {
        $request = Yii::$app->request;
        
        // 1. ALWAYS FETCH RAW BODY AT THE VERY TOP
        $rawBody = $request->getRawBody();
        
        // Log the raw incoming text data payload for debugging
        Yii::error($rawBody, 'bot_webhook_debug');
        
        if (!$request->isPost && !$request->isGet) {
            throw new BadRequestHttpException('Unsupported structural integration method request.');
        }
        
        // 2. HANDSHAKE VERIFICATION PORT (GET)
        if ($request->isGet) {
            $isValid = Yii::$app->whatsapp->verifyWebhook(
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

        // 3. DISPATCH PROCESSING LOGIC (POST)
        if ($request->isPost) {
            $payload = json_decode($rawBody, true);
            $value = $payload['entry'][0]['changes'][0]['value'] ?? null;

            // CRITICAL STEP: If Meta sends a status tracking event (sent, delivered, read), exit immediately.
            if (isset($value['statuses'])) {
                Yii::$app->response->statusCode = 200;
                return 'STATUS_ACKNOWLEDGED';
            }

            // Safely parse out the incoming message block from the decoded structure
            $messageData = $value['messages'][0] ?? null;

            if ($messageData && isset($messageData['type'])) {
                $senderPhone = $messageData['from'] ?? null;
                $incomingText = null;
                $metaId =  $messageData['id'];

                // Capture standard text responses
                if ($messageData['type'] === 'text') {
                    $incomingText = $messageData['text']['body'] ?? null;
                } 
                // Capture interactive quick-reply button clicks if you use them later
                elseif ($messageData['type'] === 'interactive') {
                    $incomingText = $messageData['interactive']['list_reply']['id'];
                }

                if ($senderPhone && $incomingText !== null) {
                    try {
                        // Forward directly to the BotService encapsulation layer
                        Yii::$app->botService->handleIncomingMessage($senderPhone, $incomingText, $metaId);
                    } catch (\Throwable $e) {
                        Yii::error("Bot Execution Failure: " . $e->getMessage(), 'bot_error');
                        Yii::error($e->getTraceAsString(), 'bot_error');
                    }
                }
            }

            Yii::$app->response->statusCode = 200;
            return 'EVENT_RECEIVED';
        }
    }


  
    public function actionIndex($phone = null)
    {
        $this->layout = 'login';

        // 1. Fetch all unique contacts to populate the left sidebar
        $contacts = SocialContact::find()->orderBy(['id' => SORT_DESC])->all();

        $messages = [];
        $activeContact = null;

        // 2. If a specific phone chat is selected, load its logs and state details
        if ($phone) {
            $activeContact = SocialContact::findOne(['phone_number' => $phone]);
            
            $messages = \app\models\WhatsappChatLog::find()
                ->where(['phone_number' => $phone])
                ->orderBy(['id' => SORT_ASC])
                ->all();
        }

        // 3. Render the views/bot/index.php file we just updated
        return $this->render('index', [
            'contacts' => $contacts,
            'activePhone' => $phone,
            'messages' => $messages,
            'activeContact' => $activeContact,
        ]);
    }

    /**
     * MANUAL TEXT DISPATCH HANDLER (AJAX POST)
     */
    public function actionSend()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $request = Yii::$app->request;

        $phone = $request->post('phone_number');
        $body = $request->post('message_body');

        if (empty($phone) || empty($body)) {
            return ['success' => false, 'error' => 'Message body or phone parameter input missing.'];
        }

        try {
            // Send out using your existing Whatsapp Meta SDK configuration component layer
            $apiResult = Yii::$app->botService->sendTextMessage($phone, $body);
            $metaMessageId = $apiResult['messages'][0]['id'] ?? null;

            // Log directly into database 
            $log = new WhatsappChatLog();
            $log->phone_number = $phone;
            $log->message_body = $body;
            $log->direction = 'outbound';
            $log->type = 'text';
            $log->created_at = date('Y-m-d H:i:s');
            $log->meta_message_id = $metaMessageId;
            $log->save();

            return [
                'success' => true,
                'timestamp' => date('h:i A'),
                'id' => $log->id
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * POLL REFRESH NEW INBOUND MESSAGES (AJAX)
     */
    public function actionGetNewMessages($phone, $last_id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $newMessages = WhatsappChatLog::find()
            ->where(['phone_number' => $phone])
            ->andWhere(['>', 'id', $last_id])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        $html = '';
        $latestId = $last_id;

        foreach ($newMessages as $msg) {
            $latestId = $msg->id;
            $html .= $this->renderPartial('message_bubble', [
                'msg' => $msg,
                'isInbound' => ($msg->direction === 'inbound')
            ]);
        }

        return [
            'success' => true,
            'has_new' => !empty($newMessages),
            'html' => $html,
            'latest_id' => $latestId,
        ];
    }

    /**
     * TOOGLE AUTOMATION PIPELINE (AJAX POST Endpoint)
     */
    public function actionToggleBotState()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $request = Yii::$app->request;

        if (!$request->isPost) {
            return ['success' => false, 'error' => 'Invalid request access context.'];
        }

        $phone = $request->post('phone_number');
        $action = $request->post('target_action');

        $contact = SocialContact::findOne(['phone_number' => $phone]);
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
}