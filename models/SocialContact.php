<?php
    namespace app\models;

use Yii;
use yii\db\ActiveRecord;

class SocialContact extends ActiveRecord
{
    /**
     * Link this model to our independent social_contact table
     */
    public static function tableName()
    {
        return 'YOUR_DATABASE_NAME';
    }

    /**
     * Validation rules for saving contacts safely
     */
    public function rules()
    {
        return [
            [['platform_uid'], 'required'],
            [['platform'], 'string'],
            [['created_at'], 'safe'],
            [['platform_uid'], 'string', 'max' => 100],
            [['username'], 'string', 'max' => 100],
        ];
    }
}
?>