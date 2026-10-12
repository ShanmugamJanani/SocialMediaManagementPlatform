<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\web\UploadedFile;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use app\models\SocialContact;
use app\models\WhatsappChatLog;

class SocialController extends Controller
{
    public $enableCsrfValidation = false;

    /**
     * AUTHCLIENT ROUTING HANDLERS
     */
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
        // Keeping Auth success fallback logic as is since it redirects app states directly
        $attributes = $client->getUserAttributes();
        $fbId = $attributes['id'] ?? null;
        $email = $attributes['email'] ?? null;
        $name = $attributes['name'] ?? null;

        $user = \app\models\LogUser::find()->where(['user_id' => $fbId])->one();

        if (!$user) {
            $user = new \app\models\LogUser();
            $user->name = $name;
            $user->email = $email;
            $user->user_id = $fbId;
            $user->auth_type = 'FB';
            $user->auth_key = Yii::$app->security->generateRandomString();
            
            if (!$user->save()) {
                Yii::error("Could not save FB user: " . json_encode($user->errors));
                Yii::$app->session->setFlash('error', 'Failed to save user data.');
                return $this->redirect(['/form/login']);
            }
        }

        if (Yii::$app->user->login($user, 3600 * 24 * 30)) {
            Yii::$app->session->setFlash('success', "Welcome, $name!");
            return $this->redirect(['/form/index']); 
        }

        return $this->redirect(['/form/login']);
    }

    /**
     * MALFORMED INPUT XSS FILTER
     */
    public function beforeAction($action)
    {
        if (strpos(Yii::$app->request->url, '<') !== false || strpos(Yii::$app->request->url, '[') !== false) {
            Yii::error("Blocked malformed request: " . Yii::$app->request->url, 'security');
            throw new BadRequestHttpException("Invalid request");
        }
        return parent::beforeAction($action);
    }

}