<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Employees', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="employee-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Update', ['update', 'emp_id' => $model->emp_id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Delete', ['delete', 'emp_id' => $model->emp_id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Are you sure you want to delete this item?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'emp_id',
            'name',
            'dob',
            'mobile',
            'email:email',
            [
                'label' => 'Gender',
                'value' => $model->gender->gender ?? '(not set)',
            ],
            [
                'label' => 'Country',
                'value' => $model->country->country ?? '(not set)',
            ],
            [
                'label' => 'City',
                'value' => $model->city->city ?? '(not set)',
            ],
            'salary',
            'advance',
        ],
    ]) ?>

</div>