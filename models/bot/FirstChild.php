<?php

namespace app\models\bot;

use yii\db\ActiveRecord;

class FirstChild extends ActiveRecord
{
    public static function tableName()
    {
        return 'YOUR_DATABASE_NAME';
    }

    public function rules()
    {
        return [
            [['parent_id', 'question'], 'required'],
            [['question'], 'string', 'max' => 1024],
        ];
    }

    public function getMainQuestion()
    {
        return $this->hasOne(MainQuestion::class, [
            'id' => 'parent_id'
        ]);
    }

    public function getSecondChildQuestions()
    {
        return $this->hasMany(GrandchildQuestion::class, [
            'parent_id' => 'id'
        ]);
    }
}