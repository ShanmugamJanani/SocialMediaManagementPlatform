<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use app\models\LogUser;

class AuthenticationController extends Controller
{
    /**
     * Login
     */
    public function actionLogin()
    {
        $this->layout = 'login';

        // Already logged in
        if (!Yii::$app->user->isGuest) {
            return $this->redirect([
                '/home/index'
            ]);
        }

        $model = new LogUser();
        $model->scenario = 'login';

        if ($model->load(Yii::$app->request->post())) {

            // Clean email
            $model->email = trim($model->email);

            // Find user
            $user = LogUser::findOne([
                'email' => $model->email
            ]);

            // User does not exist
            if ($user === null) {

                $model->addError(
                    'password',
                    'Incorrect email or password.'
                );

                return $this->render('login', [
                    'model' => $model
                ]);
            }

            // Account inactive
            if ((int) $user->active_status !== 1) {

                $model->addError(
                    'password',
                    'Access denied. Your account is inactive.'
                );

                return $this->render('login', [
                    'model' => $model
                ]);
            }

            // Validate password
            if (!$user->validatePassword($model->password)) {

                $model->addError(
                    'password',
                    'Incorrect email or password.'
                );

                return $this->render('login', [
                    'model' => $model
                ]);
            }

            /*
            * --------------------------------------------------
            * Create Yii login session
            * --------------------------------------------------
            */
            if (Yii::$app->user->login($user, 3600 * 24 * 30)) {

                // Check default DOB password
                if (!empty($user->dob)) {
                    $defaultPassword = Yii::$app->formatter->asDate($user->dob, 'php:dmY');

                    if ($user->validatePassword($defaultPassword)) {
                        Yii::$app->session->setFlash('warning', 'Please change your default password.');
                        return $this->redirect(['/authentication/change-password']);
                    }
                }

                Yii::$app->session->setFlash('success', 'Logged-in successfully!');

                // Redirect to intended page before login, or fallback to /home/index
                return $this->goBack(['/home/index']);
            }

            // If login() returns false
            Yii::error('Yii login() failed for user_id: ' . $user->user_id, __METHOD__);
            $model->addError('password', 'Unable to create login session.');

            return $this->render('login', ['model' => $model]);

            /*
             * --------------------------------------------------
             * Confirm session was created
             * --------------------------------------------------
             */
            if (Yii::$app->user->isGuest) {

                Yii::error(
                    'Login returned TRUE but user is still guest. ' .
                    'user_id: ' . $user->user_id,
                    __METHOD__
                );

                $model->addError(
                    'password',
                    'Login session could not be established.'
                );

                return $this->render('login', [
                    'model' => $model
                ]);
            }

            /*
             * --------------------------------------------------
             * Check default DOB passworD
             * --------------------------------------------------
             */
            if (!empty($user->dob)) {

                $defaultPassword = Yii::$app->formatter->asDate(
                    $user->dob,
                    'php:dmY'
                );

                if ($user->validatePassword($defaultPassword)) {

                    Yii::$app->session->setFlash(
                        'warning',
                        'Please change your default password.'
                    );

                    return $this->redirect([
                        '/authentication/change-password'
                    ]);
                }
            }

            /*
             * --------------------------------------------------
             * Successful login
             * --------------------------------------------------
             */
            Yii::$app->session->setFlash(
                'success',
                'Logged-in successfully!'
            );

            /*
             * Main application dashboard
             */
            return $this->redirect([
                '/home/index'
            ]);
        }

        /*
         * Initial login page
         */
        return $this->render('login', [
            'model' => $model
        ]);
    }


    /**
     * Logout
     */
    public function actionLogout()
    {
        // Completely remove authentication
        Yii::$app->user->logout(true);

        // Destroy PHP/Yii session
        Yii::$app->session->destroy();

        return $this->redirect([
            '/authentication/login'
        ]);
    }
}
?>
