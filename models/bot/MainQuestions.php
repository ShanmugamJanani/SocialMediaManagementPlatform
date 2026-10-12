<?php

namespace app\models\bot;

use yii\db\ActiveRecord;

class MainQuestions extends ActiveRecord
{
    public static function tableName()
    {
        return 'YOUR_DATABASE_NAME';
    }

    public function rules()
    {
        return [
            [['question'], 'required'],
            [['question'], 'string', 'max' => 1024],
        ];
    }

    public function getChildQuestions()
    {
        return $this->hasMany(FirstChild::class, [
            'parent_id' => 'id'
        ]);
    }
}