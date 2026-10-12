<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use app\components\LinkedInSyncHelper;

class LinkedInController extends Controller
{
    public $enableCsrfValidation = true;

    public function beforeAction($action)
    {
        // Disable CSRF validation specifically for external incoming LinkedIn webhooks
        if ($action->id === 'webhook') {
            $this->enableCsrfValidation = false;
        }
        return parent::beforeAction($action);
    }

    /**
     * Webhook Endpoint for real-time updates from LinkedIn
     * Endpoint: https://yourdomain.com/linked-in/webhook
     */
    public function actionWebhook()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $request = Yii::$app->request;

        // Step A: Respond to LinkedIn Webhook verification challenge
        $challenge = $request->get('challenge');
        if ($challenge) {
            return ['challenge' => $challenge];
        }

        // Step B: Handle incoming real-time payload
        $rawPayload = $request->getRawBody();
        $eventData = json_decode($rawPayload, true);

        if (!empty($eventData)) {
            LinkedInSyncHelper::processIncomingWebhookEvent($eventData);
            return ['status' => 'success'];
        }

        return ['status' => 'ignored'];
    }

    /**
     * Triggered manually from the Admin Web Interface
     * Endpoint: https://yourdomain.com/linked-in/manual-sync
     */
    public function actionManualSync()
    {
        // 1. Push pending local posts to LinkedIn
        $pushResult = LinkedInSyncHelper::syncPendingPosts();

        // 2. Pull recent posts/comments created on official LinkedIn app
        $pullResult = LinkedInSyncHelper::pullAndMergeRemoteActivity();

        Yii::$app->session->setFlash(
            'success', 
            "Sync Completed! Outbound: {$pushResult['success']} published | Inbound: {$pullResult['imported']} imported from official app."
        );

        return $this->redirect(Yii::$app->request->referrer ?: ['/admin/index']);
    }
}