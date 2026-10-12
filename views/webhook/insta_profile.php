<?php
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var array $connectedAccount */

$this->title = 'Connected Social Accounts';
$this->params['breadcrumbs'][] = ['label' => 'Settings', 'url' => ['/settings']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="settings-social-accounts container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1">Instagram Profile</h3>
            <p class="text-muted mb-0">Manage connected Instagram</p>
        </div>
        <?= Html::a('<i class="bi bi-facebook me-1"></i> Connect New Channel', ['/auth/meta-login'], [
            'class' => 'btn btn-primary'
        ]) ?>
    </div>

    <div class="row g-3">
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body p-4">
                    
                    <!-- Account Header -->
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="position-relative">
                            <img src="<?= Html::encode($account['profile_picture_url']) ?>" 
                                    alt="<?= Html::encode($account['username']) ?>" 
                                    class="rounded-circle border" 
                                    style="width: 60px; height: 60px; object-fit: cover;">
                            
                            <!-- Platform Badge Icon -->
                            <span class="position-absolute bottom-0 end-0 bg-danger text-white rounded-circle p-1 small d-flex align-items-center justify-content-center" style="width: 20px; height: 20px;">
                                <i class="bi bi-instagram" style="font-size: 10px;"></i>
                            </span>
                        </div>

                        <div class="overflow-hidden">
                            <h5 class="mb-0 text-truncate">@<?= Html::encode($account['username']) ?></h5>
                            <small class="text-muted d-block text-truncate"><?= Html::encode($account['name'] ?? 'Instagram Professional Account') ?></small>
                            <small class="badge bg-light text-dark border mt-1">ID: <?= Html::encode($account['instagram_account_id']) ?></small>
                        </div>
                    </div>

                    <!-- Status & Asset Meta Details -->
                    <div class="p-2 bg-light rounded mb-3 small">
                        <div class="d-flex justify-content-between text-muted mb-1">
                            <span>Status:</span>
                            <span class="text-success fw-bold">Active</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted">
                            <span>Linked Page:</span>
                            <span><?= Html::encode($account['facebook_page_name'] ?? 'Main FB Page') ?></span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex gap-2">
                        <?= Html::a('Manage Account', ['/settings/manage-account', 'id' => $account['id']], [
                            'class' => 'btn btn-outline-primary btn-sm w-100'
                        ]) ?>
                        
                        <?= Html::a('Disconnect', ['/settings/disconnect-account', 'id' => $account['id']], [
                            'class' => 'btn btn-outline-danger btn-sm w-100',
                            'data' => [
                                'confirm' => 'Are you sure you want to disconnect this Meta asset?',
                                'method' => 'post',
                            ],
                        ]) ?>
                    </div>

                </div>
            </div>
        </div>
    </div>

</div>