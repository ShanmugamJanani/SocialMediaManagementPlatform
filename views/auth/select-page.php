<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $fbUserName string */
/* @var $pages array */

$this->title = 'Select Facebook Page';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="fab fa-facebook me-2"></i> Select Facebook Page
                    </h5>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted mb-4">
                        Logged in as <strong><?= Html::encode($fbUserName) ?></strong>. Please select the Facebook Page you want to connect to this portal:
                    </p>

                    <?php $form = ActiveForm::begin([
                        'action' => ['auth/save-selected-page'],
                        'method' => 'post',
                    ]); ?>

                    <div class="list-group mb-4">
                        <?php foreach ($pages as $index => $page): ?>
                            <label class="list-group-item list-group-item-action d-flex align-items-center p-3 cursor-pointer">
                                <input 
                                    class="form-check-input me-3" 
                                    type="radio" 
                                    name="selected_page_id" 
                                    value="<?= Html::encode($page['id']) ?>" 
                                    <?= $index === 0 ? 'checked' : '' ?>
                                    required
                                >
                                <div>
                                    <h6 class="mb-1 fw-bold"><?= Html::encode($page['name']) ?></h6>
                                    <small class="text-muted">
                                        Page ID: <?= Html::encode($page['id']) ?> 
                                        <?= isset($page['category']) ? ' • ' . Html::encode($page['category']) : '' ?>
                                    </small>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <?= Html::a('Cancel', ['home/index'], ['class' => 'btn btn-outline-secondary']) ?>
                        <?= Html::submitButton('Connect Page', ['class' => 'btn btn-primary px-4']) ?>
                    </div>

                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
    </div>
</div>