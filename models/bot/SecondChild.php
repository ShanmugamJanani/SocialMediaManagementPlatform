<?php

namespace app\models\bot;

use yii\db\ActiveRecord;

class SecondChild extends ActiveRecord
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

    public function getFirstChildQuestion()
    {
        return $this->hasOne(ChildQuestion::class, [
            'id' => 'parent_id'
        ]);
    }
}