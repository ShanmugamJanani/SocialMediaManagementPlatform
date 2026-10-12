<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;


class SocialAccount extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'YOUR_DATABASE_NAME';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [

            // Required fields
            [
                [
                    'platform_type',
                    'platform_id',
                    'access_token'
                ],
                'required'
            ],

            // Platform
            [
                ['platform_type'],
                'string',
                'max' => 30
            ],

            // Platform account ID
            [
                ['platform_id'],
                'string',
            ],

            // Account name
            [
                ['account_name'],
                'string',
                'max' => 255
            ],

            // Access token
            [
                ['access_token'],
                'string'
            ],

            // Active status
            [
                ['active_status'],
                'in',
                'range' => ['0', '1']
            ],

            // Platform data
            [
                ['platform_data','connected_with'],
                'safe'
            ],

            // Timestamps
            [
                ['created_at', 'updated_at','user_access_token'],
                'safe'
            ],

            // Unique account combination
            [
                [
                    'platform_type',
                    'platform_id'
                ],
                'unique',
                'targetAttribute' => [
                    'platform_type',
                    'platform_id'
                ],
                'message' => 'This social media account is already connected.'
            ],
        ];
    }


    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'platform_type' => 'Platform',
            'platform_id' => 'Platform Account ID',
            'account_name' => 'Account Name',
            'access_token' => 'Access Token',
            'token_expires_at' => 'Token Expires At',
            'active_status' => 'Active Status',
            'platform_data' => 'Platform Data',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }


    /**
     * Find a specific platform account.
     */
    public static function findPlatformAccount(
        $platform,
        $platformAccountId
    ) {
        return static::find()
            ->where([
                
                'platform_type' => $platform,
                'platform_id' => $platformAccountId
            ])
            ->one();
    }


    /**
     * Check connected a platforms.
     */
    public static function isConnected(
        $platform
    ) {
        return static::find()
            ->where([
                'platform_type' => $platform,
                'active_status' => '1'
            ])
            ->exists();
    }


    /**
     * Get decoded platform-specific data.
     *
     * Yii/MySQL may return JSON as a string depending on
     * the DB driver/version, so decode it here.
     */
    public function getPlatformData()
    {
        if (empty($this->platform_data)) {
            return [];
        }

        if (is_array($this->platform_data)) {
            return $this->platform_data;
        }

        $data = json_decode(
            $this->platform_data,
            true
        );

        return is_array($data) ? $data : [];
    }


    /**
     * Store platform-specific data as JSON.
     */
    public function setPlatformData(array $data)
    {
        $this->platform_data = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }


    /**
     * Check whether the OAuth token has expired.
     *
     * Meta tokens may have NULL expiration depending
     * on your implementation.
     */
    public function isTokenExpired()
    {
        if (empty($this->token_expires_at)) {
            return false;
        }

        return strtotime($this->token_expires_at) <= time();
    }


    /**
     * Deactivate the social account.
     */
    public function deactivate()
    {
        $this->active_status = '0';

        return $this->save(false);
    }


    /**
     * Activate the social account.
     */
    public function activate()
    {
        $this->active_status = '1';

        return $this->save(false);
    }

    /**
     * Decodes the platform_data JSON field into an array.
     * 
     * @return array
     */
    public function getPlatformDataArray()
    {
        if (is_array($this->platform_data)) {
            return $this->platform_data;
        }

        return json_decode($this->platform_data, true) ?? [];
    }

    /**
     * Gets Facebook User ID from JSON data.
     */
    public function getFbUserId()
    {
        $data = $this->getPlatformDataArray();
        return $data['fb_user_id'] ?? null;
    }

    

    public function getDecryptedUserAccessToken()
    {
        $data = json_decode($this->platform_data, true);
        if (empty($data['user_access_token'])) {
            return null;
        }

        $secretKey = Yii::$app->request->cookieValidationKey;
        $decoded = base64_decode($data['user_access_token']);

        return Yii::$app->security->decryptByKey($decoded, $secretKey);
    }
}
?>
