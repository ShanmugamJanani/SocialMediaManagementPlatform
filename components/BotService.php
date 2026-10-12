<?php
namespace app\components;

use Yii;
use yii\base\Component;
use yii\web\Response;
use app\models\SocialContact;
use app\models\bot\MainQuestions;
use app\models\bot\FirstChild;
use app\models\bot\SecondChild;
use app\models\WhatsappChatLog;

class BotService extends Component
{
    public $phoneNumberId;
    public $accessToken;
    public $accessTokenId; 
    public $webhookToken;
    public $version = 'v25.0';

    public function init()
    {
        parent::init();

        if (empty($this->phoneNumberId)) {
            $this->phoneNumberId = '';
        }

        if (empty($this->accessToken)) {
            $this->accessToken = '';
        }

        if (empty($this->accessTokenId)) {
            $this->accessTokenId = '';
        }
    }
    
    public function processIncomingWebhook(array $message)
    {
        
        $messageData = $message;

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
                $interactive = $messageData['interactive'] ?? [];

                if (isset($interactive['list_reply'])) {
                    $incomingText = $interactive['list_reply']['id'] ?? null;
                } 
                
                elseif (isset($interactive['button_reply'])) {
                    $incomingText = $interactive['button_reply']['id'] ?? null;
                }
            }


            if ($senderPhone && $incomingText !== null) {
                try {
                    // Forward directly to the BotService encapsulation layer
                    $this->handleIncomingMessage($senderPhone, $incomingText, $metaId);
                } catch (\Throwable $e) {
                    Yii::error("Bot Execution Failure: " . $e->getMessage(), 'bot_error');
                    Yii::error($e->getTraceAsString(), 'bot_error');
                }
            }
        }

        Yii::$app->response->statusCode = 200;
        return 'EVENT_RECEIVED';
    }
    
    /**
     * Entry route to process multi-level state automation
     */
    public function handleIncomingMessage($senderPhone, $incomingText, $metaId)
    {
        $message = strtolower(trim($incomingText));
        
        // 1. Log inbound message locally directly into your database
        $this->saveChatLog($senderPhone, $incomingText, 'inbound', 'text', $metaId);

        // 2. Fetch or initialize contact session tracking states
        $contact = $this->ensureContactSession($senderPhone);

        $replyText = "";

        // 3. State Machine Engine
        switch ($contact->bot_state) {
            case 'initial_state':
                $replyText = $this->processInitialState($contact, $message);
                break;

            case 'main_menu_received':
                $replyText = $this->processMainMenuReceived($contact, $message);
                break;

            case 'first_child_menu_received':
                $replyText = $this->processFirstChildReceived($contact, $message);
                break;

            case 'second_child_menu_received':
                $replyText = $this->processSecondChildReceived($contact, $message);
                break;

            default:
                $replyText = "System encountered an unmanaged session branch. Type 'Hi' to restart.";
                $this->resetSessionState($contact);
                break;
        }


        if (!empty($replyText)) {
            // 4. Send outbound generation data to terminal endpoint via Meta Cloud API
            $apiResult = $this->sendTextMessage($senderPhone, $replyText);
            
            // 5. Track outbound message logs locally if Meta returns successfully
            $messageId = $apiResult['messages'][0]['id'] ?? null;
            $this->saveChatLog($senderPhone, $replyText, 'outbound', 'text', $messageId);
        }

        else{
            Yii::error("no reply text");
        }
        
        return true;
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
     * Helper logic to directly persist communications data streams
     */
    private function saveChatLog($phone, $body, $direction, $type = 'text', $metaId )
    {
        try {
            $log = new WhatsappChatLog();
            $log->phone_number = $phone;
            $log->message_body = $body;
            $log->direction = $direction; // 'inbound' or 'outbound'
            $log->type = $type;
            $log->meta_message_id = $metaId;
            $log->created_at = date('Y-m-d H:i:s');
            $log->save();
        } catch (\Exception $e) {
            Yii::error("Failed saving message log details: " . $e->getMessage(), 'bot_error');
        }
    }

    /**
     * Initial greeting and core baseline menu parsing 
     */
    private function processInitialState(SocialContact $contact, $message)
    { 
        if (strpos($message, 'hi') !== false || strpos($message, 'hello') !== false) {

            $mainQuestions = MainQuestions::find()->all();

            $rows = [];

            foreach ($mainQuestions as $index => $row) {
                $rows[] = [
                    'id' => $row->id,
                    'title' => $row->question
                ];
            }
            $x=$this->sendListMessage(
                $contact->platform_uid,
                "Hello! Welcome to our automated system. Please select an option:",
                $rows
            );

                Yii::error("Meta API Response for List Message: " . json_encode($x), 'bot_error');

            $contact->bot_state = 'main_menu_received';  
            $contact->save();
            return null;
        }

        else{
            return "Please say 'Hi' or 'Hello' to see our menu options.";
        }
        
    }

    /**
     * Processing input for Level 1 questions
     */
    private function processMainMenuReceived(SocialContact $contact, $message)
    {
        if (!is_numeric($message)) {
            return "Invalid choice. Please reply with a valid option number.";
        }

        $userSelection = (int)$message;

        $selectedQuestion = MainQuestions::findOne($userSelection);
        if (!$selectedQuestion) {
            return "⚠️ Invalid selection. Please select an option from the menu list.";
        }

        $childQuestions = FirstChild::find()->where(['parent_id' => $userSelection])->all();

        if (empty($childQuestions)) {
            $this->resetSessionState($contact);
            return "No sub-options available for this choice. Please try another menu.";
        }

        $rows = [];

        foreach ($childQuestions as $index => $row) {
            $rows[] = [
                'id' => $row->id,
                'title' => $row->question
            ];
        }

        $this->sendListMessage(
            $contact->platform_uid,
            "Please select an option:",
            $rows
        );

        $contact->bot_state = 'first_child_menu_received';
        $contact->last_parent_id = $userSelection;
        $contact->save();

        return null;
    }

    /**
     * Processing input for Level 2 child branching lists
     */
    private function processFirstChildReceived(SocialContact $contact, $message)
    {
        if (!is_numeric($message)) {
            return "Invalid choice. Please reply with a valid sub-option number.";
        }

        $userSelection = (int)$message;
        $mainMenuOption = $contact->last_parent_id;

        $selectedQuestion = FirstChild::findOne(['id' => $userSelection, 'parent_id' => $mainMenuOption]);
        if (!$selectedQuestion) {
            return "⚠️ Invalid selection. Please select an option from the menu list.";
        }

        $secondChildQuestions = SecondChild::find()->where(['parent_id' => $userSelection])->all();

        if (empty($secondChildQuestions)) {
            $this->resetSessionState($contact);
            return "No sub-options available for this choice. Please try another menu.";
        }

        $rows = [];

        foreach ($secondChildQuestions as $index => $row) {
            $rows[] = [
                'id' => $row->id,
                'title' => $row->question
            ];
        }

        $this->sendListMessage(
            $contact->platform_uid,
            "Please select an option:",
            $rows
        );

        $contact->bot_state = 'second_child_menu_received';
        $contact->last_parent_id = $userSelection;
        $contact->save();

        return null;
    }

    /**
     * Terminal point resolver summarizing leaf answers mapping properties records
     */
    private function processSecondChildReceived(SocialContact $contact, $message)
    {
        if (!is_numeric($message)) {
            return "Invalid choice. Please reply with a number from the menu options list.";
        }

        $userSelection = (int)$message;
        $firstChildOption = $contact->last_parent_id;

        $selectedQuestion = SecondChild::findOne(['id' => $userSelection, 'parent_id' => $firstChildOption]);
        if (!$selectedQuestion) {
            return "⚠️ Invalid selection. Please select an option from the menu list.";
        }

        $replyText = "Thank you for utilizing our service! Our agents will reach out if further updates occur.";

        $this->resetSessionState($contact);
        return $replyText;
    }

    /**
     * Session Initialization Engine Layer
     */
    private function ensureContactSession($phone)
    {
        $contact = SocialContact::findOne(['platform_uid' => $phone]);
        if (!$contact) {
            $contact = new SocialContact();
            $contact->platform_uid = $phone;
            $contact->bot_state = 'initial_state';
            $contact->save();
        }
        return $contact;
    }

    /**
     * Resets session records back onto zero targets properties mapping states
     */
    private function resetSessionState(SocialContact $contact)
    {
        $contact->bot_state = 'initial_state';
        $contact->last_parent_id = 0;
        $contact->save();
    }

    /**
     * Unified background outbound curl structural wrapper execution engine
     */
    private function executeCurl($url, $payload, $asJson = true)
    {
        $ch = curl_init($url);
        
        $headers = ["Authorization: Bearer {$this->accessToken}"];

        $options = [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true
        ];

        if ($asJson) {
            $headers[] = "Content-Type: application/json";
            $options[CURLOPT_POSTFIELDS] = json_encode($payload);
        } else {
            $options[CURLOPT_POSTFIELDS] = $payload;
        }

        $options[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            Yii::error("cURL API Dispatch Error: " . curl_error($ch), 'bot_error');
        }
        
        curl_close($ch);
        return json_decode($response, true);
    }

    public function sendListMessage($to, $bodyText, $rows)
    {
        $payload = [
            "messaging_product" => "whatsapp",
            "to" => $to,
            "type" => "interactive",
            "interactive" => [
                "type" => "list",
                "body" => [
                    "text" => $bodyText
                ],
                "action" => [
                    "button" => "Select Option",
                    "sections" => [
                        [
                            "title" => "Main Menu",
                            "rows" => $rows
                        ]
                    ]
                ]
            ]
        ];

        return $this->executeCurl("https://graph.facebook.com/{$this->version}/{$this->phoneNumberId}/messages",$payload );
    }
}