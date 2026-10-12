<?php
namespace app\models;

use yii\base\Model;
use yii\web\UploadedFile;

class InstagramUploadForm extends Model
{
    /**
     * @var UploadedFile
     */
    public $imageFile;

    public function rules()
    {
        return [
            [['imageFile'], 'file', 'skipOnEmpty' => false, 'extensions' => 'png, jpg, jpeg', 'maxSize' => 1024 * 1024 * 8], // Max 8MB
        ];
    }
}