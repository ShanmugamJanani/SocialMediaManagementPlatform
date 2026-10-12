<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use app\models\Employee;

/**
 * UserSearch represents the model behind the search form of `app\models\Employee`.
 */
class UserSearch extends Employee
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['emp_id', 'gender_id', 'salary', 'advance', 'country_id', 'state_id', 'city_id', 'food_preference_id', 'minimum_salary', 'maximum_salary'], 'integer'],
            [['name', 'dob', 'email', 'mobile', 'fav_color', 'password'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     * @param string|null $formName Form name to be used into `->load()` method.
     *
     * @return ActiveDataProvider
     */
    public function search($params, $formName = null)
    {
        $query = Employee::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params, $formName);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'emp_id' => $this->emp_id,
            'dob' => $this->dob,
            'gender_id' => $this->gender_id,
            'salary' => $this->salary,
            'advance' => $this->advance,
            'country_id' => $this->country_id,
            'state_id' => $this->state_id,
            'city_id' => $this->city_id,
            'food_preference_id' => $this->food_preference_id,
            'minimum_salary' => $this->minimum_salary,
            'maximum_salary' => $this->maximum_salary,
        ]);

        $query->andFilterWhere(['like', 'name', $this->name])
            ->andFilterWhere(['like', 'email', $this->email])
            ->andFilterWhere(['like', 'mobile', $this->mobile])
            ->andFilterWhere(['like', 'fav_color', $this->fav_color])
            ->andFilterWhere(['like', 'password', $this->password]);

        return $dataProvider;
    }
}
