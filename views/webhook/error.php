<?php

use yii\helpers\Html;

$this->title = $name;
?>

<div class="container py-5">

    <div class="card shadow-sm border rounded-3">

        <div class="card-body text-center p-5">

            <h1 class="mb-3">
                <?= Html::encode($name) ?>
            </h1>

            <p class="text-muted mb-4">
                <?= Html::encode($message) ?>
            </p>

            <?= Html::a(
                'Back to Home',
                ['/home/index'],
                ['class' => 'btn btn-primary']
            ) ?>

        </div>

    </div>

</div>
