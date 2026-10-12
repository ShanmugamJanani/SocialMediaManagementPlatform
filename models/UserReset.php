<?php

    namespace app\models;

    use yii\db\ActiveRecord;

    use yii\web\IdentityInterface;

    use Google\Service\YouTube;

   

    use Yii;    



    class LogUser extends ActiveRecord implements IdentityInterface{

               

        public static function tableName(){

            return 'YOUR_DATABASE_NAME';

        }



        public static function getDb()

        {

            return Yii::$app->dbAuth; // Points to the 'dbAuth' component

        }

       



        public static function primaryKey() {

            return ['user_id'];

        }

       

        /** * Validation rules */

        public function rules() {

            return [

                [['user_id'], 'integer'], [['dob'], 'safe'], [['active_status'], 'integer'],

                [['name'], 'string', 'max' => 100], [['mobile'], 'string', 'max' => 15],

                [['email'], 'string', 'max' => 512],

                ['password', 'string', 'min' => 8],

                ['password', 'match', 'pattern' => '/([a-z].*){2,}/', 'message' => 'Password must contain at least 2 lower case characters.'],

                ['password', 'match', 'pattern' => '/([A-Z].*){1,}/', 'message' => 'Password must contain at least 1 upper case characters.'],

                ['password', 'match', 'pattern' => '/([0-9].*){2,}/', 'message' => 'Password must contain at least 2 numeric/digit characters.'],

                ['password', 'match', 'pattern' => '/([^a-zA-Z0-9].*){2,}/', 'message' => 'Password must contain at least 2 special characters.'],

               

                [['auth_key'], 'string', 'max' => 255],

                [['email'], 'email'], [['email', 'password'], 'required', 'on' => 'login'],

            ]; }

           

        // Required by IdentityInterface: Finds a user by their ID (Primary Key)

        public static function findIdentity($id){

            return static::findOne($id);

        }





        public function isExpired()

        {

            if (!$this->yt_token_created_at || !$this->yt_token_expires_in) {

                return true;

            }



            $created = strtotime($this->yt_token_created_at);

            $expires = (int)$this->yt_token_expires_in;



            return time() >= ($created + $expires - 3600);

        }



        public function getId() {

            return $this->user_id;

        }



       



        // Required by IdentityInterface: Used for "Remember Me" cookies

        public function getAuthKey(){

            return $this->auth_key; // Ensure you have an 'auth_key' column in your table

        }



        public function validateAuthKey($authKey){

            return $this->getAuthKey() === $authKey;

        }



        // Required by IdentityInterface: Leave this as is for now

        public static function findIdentityByAccessToken($token, $type = null){

            return null;

        }



        /** * Validate password */

        public function validatePassword($password) {

            if (empty($this->password)) {

                return false;

            }

       

            return Yii::$app->security->validatePassword( $password, $this->password );

        }



    }

?>