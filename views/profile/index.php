<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\Json;

/** @var yii\web\View $this */
/** @var app\models\User $userModel */
/** @var app\models\SocialAccount[] $socialAccounts */

$this->title = 'My Profile & Connected Accounts';

// Modern platform visual configurations with gradients and accents
$platformConfigs = [
    'facebook'  => [
        'icon'      => 'fab fa-facebook-f',
        'color'     => '#1877F2',
        'badge'     => 'bg-primary text-white',
        'gradient'  => 'linear-gradient(135deg, #1877F2 0%, #0052cc 100%)',
        'label'     => 'Facebook'
    ],
    'instagram' => [
        'icon'      => 'fab fa-instagram',
        'color'     => '#E4405F',
        'badge'     => 'text-white',
        'gradient'  => 'linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%)',
        'label'     => 'Instagram'
    ],
    'linkedin'  => [
        'icon'      => 'fab fa-linkedin-in',
        'color'     => '#0A66C2',
        'badge'     => 'bg-info text-white',
        'gradient'  => 'linear-gradient(135deg, #0A66C2 0%, #004182 100%)',
        'label'     => 'LinkedIn'
    ],
    'twitter'   => [
        'icon'      => 'fab fa-x-twitter',
        'color'     => '#000000',
        'badge'     => 'bg-dark text-white',
        'gradient'  => 'linear-gradient(135deg, #14171A 0%, #000000 100%)',
        'label'     => 'X (Twitter)'
    ],
    'youtube'   => [
        'icon'      => 'fab fa-youtube',
        'color'     => '#FF0000',
        'badge'     => 'bg-danger text-white',
        'gradient'  => 'linear-gradient(135deg, #FF0000 0%, #cc0000 100%)',
        'label'     => 'YouTube'
    ],
    'whatsapp'  => [
        'icon'      => 'fab fa-whatsapp',
        'color'     => '#25D366',
        'badge'     => 'bg-success text-white',
        'gradient'  => 'linear-gradient(135deg, #25D366 0%, #128C7E 100%)',
        'label'     => 'WhatsApp Business'
    ],
];
?>

<!-- FontAwesome Icons & Google Fonts -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background-color: #f8fafc;
    }
    
    .profile-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .platform-card {
        background: #ffffff;
        border: 1px solid var(--bs-border-color-translucent, #dee2e6) !important;
        border-radius: 1.25rem;
        transition: all 0.3s ease;
    }

    .platform-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 28px -4px rgba(15, 23, 42, 0.08);
        border-color: #cbd5e1;
    }

    .icon-badge {
        width: 48px;
        height: 48px;
        border-radius: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-size: 1.35rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
    }

    .avatar-ring {
        position: relative;
    }
    .avatar-ring img, .avatar-ring .avatar-placeholder {
        border: 3px solid #ffffff;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
    }

    .stat-pill {
        background: #f1f5f9;
        border-radius: 0.75rem;
        padding: 0.6rem 0.85rem;
    }

    .details-toggle-btn {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        transition: all 0.2s ease;
        font-size: 0.8rem;
    }
    .details-toggle-btn:hover {
        background: #f1f5f9;
        color: #0f172a !important;
    }
    .details-toggle-btn[aria-expanded="true"] .chevron-icon {
        transform: rotate(180deg);
    }
    .chevron-icon {
        transition: transform 0.2s ease;
    }

    .meta-detail-box {
        background-color: #f8fafc;
        border-left: 3px solid #3b82f6;
    }
</style>

<div class="container py-4">

    <!-- 1. USER PROFILE HEADER CARD -->
    <div class="card profile-card rounded-4 mb-4 border-0">
        <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-4">
                
                <!-- Avatar & Core Info -->
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-ring">
                        <?php if (!empty($userModel->profile_picture)): ?>
                            <?= Html::img($userModel->profile_picture, [
                                'alt' => Html::encode($userModel->username),
                                'class' => 'rounded-circle object-fit-cover',
                                'style' => 'width: 76px; height: 76px;'
                            ]) ?>
                        <?php else: ?>
                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold fs-2 avatar-placeholder" style="width: 76px; height: 76px;">
                                <?= strtoupper(substr($userModel->username ?? 'U', 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h4 class="fw-bold mb-0 text-dark"><?= Html::encode($userModel->username ?? 'User Profile') ?></h4>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 small fw-semibold">
                                <i class="fas fa-check-circle me-1"></i> Active
                            </span>
                        </div>
                        <p class="text-secondary mb-1 small mt-1">
                            <i class="far fa-envelope me-1 text-primary"></i> <?= Html::encode($userModel->email ?? 'No email set') ?>
                            <?php if (!empty($userModel->phone)): ?>
                                <span class="mx-2 text-muted">•</span>
                                <i class="fas fa-phone me-1 text-primary"></i> <?= Html::encode($userModel->phone) ?>
                            <?php endif; ?>
                        </p>
                        <div class="text-muted small">
                            <i class="far fa-calendar-alt me-1"></i> Joined <?= Yii::$app->formatter->asDate($userModel->created_at ?? time(), 'medium') ?>
                        </div>
                    </div>
                </div>

                <!-- Actions & Connected Counter -->
                <div class="d-flex align-items-center gap-4 ms-auto ms-sm-0">
                    <div class="text-end pe-4 border-end d-none d-sm-block">
                        <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">Linked Accounts</small>
                        <span class="fs-3 fw-extrabold text-dark"><?= count($socialAccounts) ?></span>
                    </div>
                    <div>
                        <?= Html::a('<i class="fas fa-plus me-1.5"></i> Add Integration', ['home/index'], [
                            'class' => 'btn btn-primary btn-md rounded-pill px-4 fw-semibold shadow-sm'
                        ]) ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- 2. SECTION TITLE -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h5 class="fw-bold text-dark mb-0">Connected Social Media Platforms</h5>
            <small class="text-muted">Manage your connected social networks and permissions</small>
        </div>
    </div>

    <!-- 3. PLATFORMS GRID -->
    <?php if (empty($socialAccounts)): ?>
        <div class="card profile-card border-1 rounded-4 text-center py-5">
            <div class="card-body">
                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px;">
                    <i class="fas fa-link-slash fs-2"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">No Integrations Connected</h5>
                <p class="text-muted small max-w-sm mx-auto mb-4">Connect your social channels to schedule posts, manage messages, and track analytics.</p>
                <?= Html::a('<i class="fas fa-plug me-1"></i> Connect First Account', ['home/index'], ['class' => 'btn btn-primary rounded-pill px-4 fw-medium']) ?>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($socialAccounts as $index => $account): ?>

                <?php
                    $platformKey = strtolower($account->platform_type ?? '');
                    $config = $platformConfigs[$platformKey] ?? [
                        'icon'      => 'fas fa-share-nodes',
                        'color'     => '#64748b',
                        'badge'     => 'bg-secondary text-white',
                        'gradient'  => 'linear-gradient(135deg, #64748b 0%, #475569 100%)',
                        'label'     => ucfirst($account->platform_type ?? 'Platform')
                    ];

                    $isActive   = ($account->active_status == '1');
                    $collapseId = "accountDetails_" . ($account->id ?? $index);


                    switch($account->platform_type){
                        case 'youtube':
                            $platformUrl = 'youtube/disconnect-youtube';
                            break;
                        
                        case 'instagram':
                        case 'facebook':
                        case 'whatsapp':
                            $platformUrl = 'webhook/disconnect';
                            break;

                        case 'linkedin':
                            $platformUrl = '#';
                        break;                                      
                    }

                    // Decode JSON platform details
                    $pData = [];
                    if (!empty($account->platform_data)) {
                        $pData = is_array($account->platform_data) ? $account->platform_data : Json::decode($account->platform_data);
                    }

                    $avatarUrl = $pData['profile_pic_url'] ?? $pData['avatar_url'] ?? null;
                ?>
                
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card platform-card h-100 border-0 p-3">
                        <div class="card-body p-2 d-flex flex-column justify-content-between">
                            
                            <div>
                                <!-- Top Bar: Platform Gradient Badge & Account Avatar -->
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="icon-badge" style="background: <?= $config['gradient'] ?>;">
                                            <i class="<?= $config['icon'] ?>"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold text-dark mb-0"><?= Html::encode($config['label']) ?></h6>
                                            <span class="badge <?= $isActive ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?> rounded-pill px-2 py-0.5" style="font-size: 0.68rem;">
                                                <i class="fas fa-circle me-1" style="font-size: 0.45rem;"></i>
                                                <?= $isActive ? 'Connected' : 'Disconnected' ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Platform Avatar -->
                                    <div class="avatar-ring">
                                        <?php if (!empty($avatarUrl)): ?>
                                            <?= Html::img($avatarUrl, [
                                                'alt'   => Html::encode($account->account_name),
                                                'class' => 'rounded-circle object-fit-cover',
                                                'style' => 'width: 46px; height: 46px;'
                                            ]) ?>
                                        <?php else: ?>
                                            <div class="rounded-circle bg-light border text-secondary d-flex align-items-center justify-content-center fw-bold" style="width: 46px; height: 46px; font-size: 0.9rem;">
                                                <?= strtoupper(substr($account->account_name ?? 'A', 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Account Name & Quick Stats Box -->
                                <div class="stat-pill mb-3">
                                    <div class="small text-muted fw-semibold" style="font-size: 0.72rem; uppercase;">Connected Profile</div>
                                    <div class="fw-bold text-dark text-truncate fs-6">
                                        <?= Html::encode($account->account_name ?: 'Connected Page/Channel') ?>
                                    </div>

                                    <!-- Context Metrics -->
                                    <div class="mt-2 pt-2 border-top border-secondary-subtle d-flex align-items-center justify-content-between text-muted" style="font-size: 0.78rem;">
                                        <?php if ($platformKey === 'youtube'): ?>
                                            <span class="fw-semibold text-dark"><i class="fab fa-youtube text-danger me-1"></i> <?= number_format($pData['subscriber_count'] ?? 0) ?> subs</span>
                                            <span class="text-muted"><?= Html::encode($pData['custom_url'] ?? '') ?></span>
                                        <?php elseif ($platformKey === 'instagram'): ?>
                                            <span class="fw-semibold text-dark"><i class="fab fa-instagram text-danger me-1"></i> <?= number_format($pData['followers_count'] ?? 0) ?> followers</span>
                                            <span class="text-muted">@<?= Html::encode($pData['username'] ?? '') ?></span>
                                        <?php elseif ($platformKey === 'facebook'): ?>
                                            <span class="fw-semibold text-dark"><i class="fas fa-thumbs-up text-primary me-1"></i> <?= number_format($pData['followers_count'] ?? $pData['fan_count'] ?? 0) ?> followers_coun</span>
                                            <span class="text-truncate ms-1"><?= Html::encode($pData['category'] ?? '') ?></span>
                                        <?php elseif ($platformKey === 'whatsapp'): ?>
                                            <span class="fw-semibold text-dark"><i class="fas fa-shield-halved text-success me-1"></i> <?= Html::encode($pData['quality_rating'] ?? 'OK') ?></span>
                                            <span class="text-muted"><?= Html::encode($pData['display_phone_number'] ?? '') ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Synced: <?= !empty($pData['connected_at']) ? Yii::$app->formatter->asDate($pData['connected_at'], 'short') : 'N/A' ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Collapsible Trigger Button -->
                                <div class="mb-2">
                                    <button class="btn details-toggle-btn w-100 text-muted fw-semibold d-flex align-items-center justify-content-between rounded-3 px-3 py-2" 
                                            type="button" 
                                            data-bs-toggle="collapse" 
                                            data-bs-target="#<?= $collapseId ?>" 
                                            aria-expanded="false" 
                                            aria-controls="<?= $collapseId ?>">
                                        <span><i class="fas fa-sliders-h me-1.5 text-primary"></i> View Detailed Specs</span>
                                        <i class="fas fa-chevron-down chevron-icon small"></i>
                                    </button>

                                    <!-- Collapsible Extended Details -->
                                    <div class="collapse mt-2" id="<?= $collapseId ?>">
                                        <div class="p-3 rounded-3 meta-detail-box text-muted" style="font-size: 0.78rem;">
                                            <div class="mb-2">
                                                <strong class="text-dark d-block">Platform Account ID:</strong>
                                                <span class="text-break font-monospace bg-white px-2 py-0.5 rounded border border-light"><?= Html::encode($account->platform_id ?? 'N/A') ?></span>
                                            </div>

                                            <?php if ($platformKey === 'facebook'): ?>
                                                <div class="mb-2">
                                                    <strong class="text-dark d-block">Authorized Admin:</strong>
                                                    <span><?= Html::encode($pData['connected_by_user_name'] ?? 'N/A') ?></span>
                                                </div>
                                                <?php if (!empty($pData['instagram_business_id'])): ?>
                                                    <div class="mb-2">
                                                        <strong class="text-dark d-block">Linked IG Business ID:</strong>
                                                        <span class="font-monospace"><?= Html::encode($pData['instagram_business_id']) ?></span>
                                                    </div>
                                                <?php endif; ?>

                                            <?php elseif ($platformKey === 'instagram'): ?>
                                                <div class="mb-2">
                                                    <strong class="text-dark d-block">Linked FB Page:</strong>
                                                    <span><?= Html::encode($pData['linked_page_name'] ?? 'N/A') ?></span>
                                                </div>
                                                <div class="mb-2">
                                                    <strong class="text-dark d-block">Total Published Posts:</strong>
                                                    <span><?= number_format($pData['media_count'] ?? 0) ?> Media items</span>
                                                </div>

                                            <?php elseif ($platformKey === 'whatsapp'): ?>
                                                <div class="mb-2">
                                                    <strong class="text-dark d-block">WABA Account ID:</strong>
                                                    <span class="font-monospace"><?= Html::encode($pData['waba_id'] ?? 'N/A') ?></span>
                                                </div>
                                                <div class="mb-2">
                                                    <strong class="text-dark d-block">Messaging Tier:</strong>
                                                    <span class="badge bg-light text-dark border"><?= Html::encode($pData['messaging_limit_tier'] ?? 'TIER_NOT_SET') ?></span>
                                                </div>

                                            <?php elseif ($platformKey === 'youtube'): ?>
                                                <div class="mb-2">
                                                    <strong class="text-dark d-block">Lifetime Performance:</strong>
                                                    <span><?= number_format($pData['view_count'] ?? 0) ?> views • <?= number_format($pData['video_count'] ?? 0) ?> uploads</span>
                                                </div>
                                            <?php endif; ?>

                                            <div class="mb-2">
                                                <strong class="text-dark d-block">Access Token Expiry:</strong>
                                                <span><?= !empty($pData['token_expires_at']) ? Yii::$app->formatter->asDatetime($pData['token_expires_at']) : 'Never Expires' ?></span>
                                            </div>
                                            <div>
                                                <strong class="text-dark d-block">Connection Established:</strong>
                                                <span><?= !empty($pData['connected_at']) ? Yii::$app->formatter->asDatetime($pData['connected_at']) : 'N/A' ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bottom Action Button -->
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top mt-2">
                                <small class="text-muted">Manage Access</small>
                                <div>
                                    <?php if ($isActive): ?>
                                        <?= Html::a('<i class="fas fa-plug-circle-xmark me-1"></i> Disconnect', [$platformUrl, 'id' => $account->id], [
                                            'class' => 'btn btn-link text-danger text-decoration-none p-0 border-0 fw-semibold',
                                            'style' => 'font-size: 0.82rem;',
                                            'data' => [
                                                'confirm' => 'Are you sure you want to disconnect this account?',
                                                'method' => 'post',
                                            ],
                                        ]) ?>
                                    <?php else: ?>
                                        <?= Html::a('<i class="fas fa-plug-circle-plus me-1"></i> Reconnect', ['home/index', 'id' => $account->id], [
                                            'class' => 'btn btn-link text-primary text-decoration-none p-0 border-0 fw-semibold',
                                            'style' => 'font-size: 0.82rem;',
                                            'data' => ['method' => 'post'],
                                        ]) ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>