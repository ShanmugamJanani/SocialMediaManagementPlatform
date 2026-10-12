<?php
    namespace app\controllers;

    use Yii;
    use yii\web\Controller;
    use app\models\SocialAccount;
    use yii\helpers\ArrayHelper;

class ProfileController extends Controller
{
    public function actionIndex()
    {
        $userModel = Yii::$app->user->identity;

        // Retrieve connected social accounts
        $socialAccounts = SocialAccount::find()
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        return $this->render('index', [
            'userModel' => $userModel,
            'socialAccounts' => $socialAccounts,
        ]);
    }
}
?>