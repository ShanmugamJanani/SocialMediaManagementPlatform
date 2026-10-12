<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class PrivacyController extends Controller
{
    // Crucial: Disable CSRF validation for this specific action 
    // because Facebook will not have your app's CSRF token.
    public $enableCsrfValidation = false;

    public function actionFacebookDelete()
    {
        // Force the output response to be JSON
        Yii::$app->response->format = Response::FORMAT_JSON;

        $request = Yii::$app->request;
        
        // 1. Grab the signed_request from the POST body
        $signedRequest = $request->post('signed_request');
        
        if (!$signedRequest) {
            throw new BadRequestHttpException('Missing signed_request parameter.');
        }

        // 2. Separate the signature and payload
        list($encodedSig, $payload) = explode('.', $signedRequest, 2);

        // 3. Decode the payload data
        $data = json_decode($this->base64UrlDecode($payload), true);
        
        if (!$data || !isset($data['user_id'])) {
            throw new BadRequestHttpException('Invalid payload data.');
        }

        $facebookUserId = $data['user_id'];

        // 4. YOUR CODE HERE: Wipe the user from your database
        
            $loginUser = LogUser::findOne(['user_id' => $facebookUserId]);
            //$emp = Employee::findOne(['email' => $loginUser->email]);

            if($loginUser !== null){
                $loginUser -> active_status = 0;
                $loginUser -> save();
            }
        

        // 5. Generate a tracking confirmation code
        $confirmationCode = 'DEL-' . $facebookUserId . '-' . time();
        
        // Ensure you create this static view page on your site to show status!
        $statusUrl = \yii\helpers\Url::to(['privacy/deletion-status', 'code' => $confirmationCode], true);

        // 6. Return the JSON payload Facebook requires
        return [
            'url' => $statusUrl,
            'confirmation_code' => $confirmationCode,
        ];
    }

    /**
     * Helper method to decode Base64 URL strings sent by Facebook
     */
    private function base64UrlDecode($input)
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $input));
    }

    public function actionDeletionStatus($code = null)
    {
        if (empty($code)) {
            throw new BadRequestHttpException('Missing status tracking code.');
        }

        // Sanitizing the code input just to be safe
        $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

        // Render the view file and pass the confirmation code to it
        return $this->render('deletion-status', [
            'confirmationCode' => $safeCode
        ]);
    }
}