<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

class LogUser extends ActiveRecord implements IdentityInterface
{
    /**
     * Database table
     */
    public static function tableName()
    {
        return 'YOUR_DATABASE_NAME';
    }


    /**
     * Authentication database connection
     */
    public static function getDb()
    {
        return Yii::$app->dbAuth;
    }


    /**
     * Primary key
     */
    public static function primaryKey()
    {
        return ['user_id'];
    }


    /**
     * Validation rules
     */
    public function rules()
    {
        return [

            // Basic fields
            [
                ['user_id', 'active_status'],
                'integer'
            ],

            [
                ['dob'],
                'safe'
            ],

            [
                ['name'],
                'string',
                'max' => 100
            ],

            [
                ['mobile'],
                'string',
                'max' => 15
            ],

            [
                ['email'],
                'string',
                'max' => 512
            ],

            [
                ['email'],
                'email'
            ],

            // Password
            [
                ['password'],
                'string',
                'min' => 8
            ],

            /*
             * Password complexity rules
             *
             * These rules apply when password is being
             * entered/changed. They are NOT needed for
             * validating an existing password hash.
             */
            [
                ['password'],
                'match',
                'pattern' => '/([a-z].*){2,}/',
                'message' => 'Password must contain at least 2 lower case characters.'
            ],

            [
                ['password'],
                'match',
                'pattern' => '/([A-Z].*){1,}/',
                'message' => 'Password must contain at least 1 upper case character.'
            ],

            [
                ['password'],
                'match',
                'pattern' => '/([0-9].*){2,}/',
                'message' => 'Password must contain at least 2 numeric/digit characters.'
            ],

            [
                ['password'],
                'match',
                'pattern' => '/([^a-zA-Z0-9].*){2,}/',
                'message' => 'Password must contain at least 2 special characters.'
            ],

            /*
             * Authentication key
             */
            [
                ['auth_key'],
                'string',
                'max' => 255
            ],

            /*
             * Login scenario
             */
            [
                ['email', 'password'],
                'required',
                'on' => 'login'
            ],
        ];
    }


    /*
     * ==========================================================
     * IdentityInterface
     * ==========================================================
     */

    /**
     * Find identity by ID
     */
    public static function findIdentity($id)
    {
        return static::findOne([
            'user_id' => $id
        ]);
    }


    /**
     * Find identity by access token
     *
     * Not used for normal session-based login.
     */
    public static function findIdentityByAccessToken(
        $token,
        $type = null
    ) {
        return null;
    }


    /**
     * Return user ID
     */
    public function getId()
    {
        return $this->user_id;
    }


    /**
     * Return authentication key
     */
    public function getAuthKey()
    {
        return $this->auth_key;
    }


    /**
     * Validate authentication key
     */
    public function validateAuthKey($authKey)
    {
        return !empty($this->auth_key)
            && hash_equals(
                $this->auth_key,
                $authKey
            );
    }


    /*
     * ==========================================================
     * Password
     * ==========================================================
     */

    /**
     * Set password hash
     */
    public function setPassword($password)
    {
        $this->password =
            Yii::$app->security->generatePasswordHash(
                $password
            );
    }


    /**
     * Validate plain-text password against stored hash
     */
    public function validatePassword($password)
    {
        /*
         * Prevent Yii from attempting to validate
         * against an empty/null hash.
         */
        if (
            empty($password) ||
            empty($this->password)
        ) {
            return false;
        }

        try {

            return Yii::$app->security->validatePassword(
                $password,
                $this->password
            );

        } catch (\Exception $e) {

            Yii::error(
                'Password validation failed: ' .
                $e->getMessage(),
                __METHOD__
            );

            return false;
        }
    }


    /*
     * ==========================================================
     * Authentication Key
     * ==========================================================
     */

    /**
     * Generate authentication key
     */
    public function generateAuthKey()
    {
        $this->auth_key =
            Yii::$app->security->generateRandomString();
    }


    /*
     * ==========================================================
     * YouTube token expiration
     * ==========================================================
     */

    /**
     * Check whether YouTube token is expired
     */
    public function isExpired()
    {
        if (
            empty($this->yt_token_created_at) ||
            empty($this->yt_token_expires_in)
        ) {
            return true;
        }

        $created = strtotime(
            $this->yt_token_created_at
        );

        $expires = (int) $this->yt_token_expires_in;

        return time() >= (
            $created +
            $expires -
            3600
        );
    }


    /*
     * ==========================================================
     * Before Save
     * ==========================================================
     */

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        /*
         * Generate authentication key for new users
         */
        if (
            $insert &&
            empty($this->auth_key)
        ) {
            $this->generateAuthKey();
        }

        return true;
    }
}
