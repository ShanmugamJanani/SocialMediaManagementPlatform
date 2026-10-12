<?php

namespace app\models;

use yii\db\ActiveRecord;
use yii\web\UploadedFile;

class YoutubeProfile extends ActiveRecord
{
    public static function tableName(){
        return 'YOUR_DATABASE_NAME';
    }

    public function rules()
    {
        return [
            [['description', 'keywords', 'country','title'], 'string'],
            [['country'], 'string', 'max' => 2], // 2-letter ISO country code (e.g., US, IN)
            [['updated_at'],'date'],
            [['banner_image'], 'image', 'skipOnEmpty' => true, 'extensions' => 'png, jpg, jpeg', 'maxSize' => 6 * 1024 * 1024, 'minWidth' => 2048, 'minHeight' => 1152, 'tooSmall' => 'The banner must be at least 2048x1152 pixels.', 'tooBig' => 'The file size cannot exceed 6MB.'
        ],
        ];
    }

    public function attributeLabels()
    {
        return [
            'title' => 'Title',
            'description' => 'Channel Description',
            'keywords' => 'Channel Keywords (comma separated)',
            'country' => 'Country Code (e.g., US, IN, GB)',
            'banner_image' => 'Channel Banner (Recommended: 2560x1440 px, max 6MB)',
            'updated_at' => 'Updated at'
        ];
    }
}

?>