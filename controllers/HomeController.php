<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\AccessControl;
use app\models\LogUser;
use app\models\SocialAccount;

class HomeController extends Controller
{
    public $layout = 'main';

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Main Social Media Management Dashboard
     */
    public function actionIndex()
    {
        $userId = Yii::$app->user->id;

        $accounts = SocialAccount::find()
        ->where([
            'active_status' => '1',
        ])
        ->indexBy('platform_type')
        ->all();


        /** @var LogUser|null $user */
        $user = LogUser::findOne([
            'user_id' => $userId
        ]);

        if (!$user) {
            Yii::$app->user->logout();

            return $this->redirect([
                '/authentication/login'
            ]);
        }

        return $this->render('index', [
            'user' => $user, 'accounts' => $accounts
        ]);
    }
}