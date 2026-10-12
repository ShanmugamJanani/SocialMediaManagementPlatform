<?php

use yii\helpers\Html;
use Yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $user app\models\LogUser */
/* @var $accounts array */

$this->title = 'Social Media Dashboard';

/**
 * Helper to fetch connected platform account safely
 */
function getPlatformAccount($accounts, $platform)
{
    return $accounts[$platform] ?? null;
}

function getPlatformData($account)
{
    if (!$account || empty($account->platform_data)) {
        return [];
    }

    if (is_array($account->platform_data)) {
        return $account->platform_data;
    }

    $data = json_decode($account->platform_data, true);

    return is_array($data) ? $data : [];
}

function getProfileImage($account)
{
    $data = getPlatformData($account);

    return $data['profile_pic_url']
        ?? $data['profile_picture_url']
        ?? $data['profile_image']
        ?? $data['picture']
        ?? $data['avatar_url']
        ?? null;
}

$facebook  = getPlatformAccount($accounts, 'facebook');
$instagram = getPlatformAccount($accounts, 'instagram');
$whatsapp  = getPlatformAccount($accounts, 'whatsapp');
$youtube   = getPlatformAccount($accounts, 'youtube');
$linkedin  = getPlatformAccount($accounts, 'linkedin');

?>

<style>
/* Card Hover Elevation */
.social-platform-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 0.5rem 1.25rem rgba(0, 0, 0, 0.08) !important;
}

/* Secondary Button Hover State */
.social-platform-card .btn-quick-action:hover {
    background-color: #e9ecef !important;
    border-color: #ced4da !important;
}
</style>

<div class="container-fluid px-3 px-md-4 px-xl-5">

    <!-- PAGE HEADER -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h2 class="mb-1 fw-bold text-dark">Social Media Management</h2>
            <p class="text-muted mb-0">Manage your connected social media platforms from one place.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <?= Html::a(
                '<i class="fas fa-user me-1.5"></i> My Profile',
                ['profile/index'],
                ['class' => 'btn btn-outline-primary rounded-3 fw-medium']
            ) ?>
        </div>
    </div>

    <!-- USER PROFILE CARD -->
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10 mb-4">
            <div class="card border-0 shadow-sm rounded-4" style="border: 1px solid #eef0f3 !important;">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-md-2 text-center mb-3 mb-md-0">
                            <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center shadow-sm"
                                 style="width:70px; height:70px; font-size:28px;">
                                <?= strtoupper(substr($user->name ?: 'U', 0, 1)) ?>
                            </div>
                        </div>
                        <div class="col-md-7 text-center text-md-start">
                            <h4 class="mb-1 fw-bold text-dark"><?= Html::encode($user->name) ?></h4>
                            <p class="text-muted mb-1 small">
                                <i class="fas fa-envelope me-1.5 text-primary"></i> <?= Html::encode($user->email) ?>
                            </p>
                            <?php if (!empty($user->mobile)): ?>
                                <p class="text-muted mb-0 small">
                                    <i class="fas fa-phone me-1.5 text-primary"></i> <?= Html::encode($user->mobile) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SOCIAL MEDIA PLATFORMS GRID -->
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">
            <div class="row">

                <!-- =================================================
                     1. FACEBOOK CARD
                ================================================== -->
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 social-platform-card position-relative overflow-hidden" 
                         style="border: 1px solid #eef0f3 !important; transition: transform 0.2s ease, box-shadow 0.2s ease; background: #ffffff;">
                        
                        <div class="position-absolute top-0 start-0 end-0 bg-primary" style="height: 4px;"></div>

                        <div class="card-body p-4 d-flex flex-column pt-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-1.5 rounded-pill" style="font-size: 0.75rem;">
                                    <i class="fab fa-facebook me-1.5"></i> Facebook
                                </span>
                                
                                <?php if ($facebook): ?>
                                    <span class="d-inline-flex align-items-center badge bg-success-subtle text-success fw-medium rounded-pill px-2.5 py-1" style="font-size: 0.75rem; border: 1px solid rgba(25, 135, 84, 0.15);">
                                        <span class="bg-success rounded-circle me-1.5" style="width: 6px; height: 6px;"></span>
                                        Connected
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted fw-normal rounded-pill px-2.5 py-1 border" style="font-size: 0.75rem;">
                                        Not Connected
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if ($facebook): ?>
                                <div class="d-flex align-items-center my-2">
                                    <div class="position-relative me-3 flex-shrink-0">
                                        <?php if (!empty($facebook->avatar_url)): ?>
                                            <img src="<?= Html::encode($facebook->avatar_url) ?>" alt="Avatar" class="rounded-circle shadow-sm" style="width: 50px; height: 50px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded-circle d-flex align-items-center justify-content-center text-primary fw-bold shadow-sm" style="width: 50px; height: 50px; font-size: 1.1rem; background: linear-gradient(135deg, #e7f1ff 0%, #d0e2ff 100%);">
                                                <?= strtoupper(substr($facebook->account_name ?: 'F', 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <span class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center border border-2 border-white" style="width: 18px; height: 18px; font-size: 9px;">
                                            <i class="fab fa-facebook-f"></i>
                                        </span>
                                    </div>
                                    <div class="text-truncate">
                                        <h6 class="mb-0 fw-bold text-dark text-truncate" style="font-size: 0.95rem;">
                                            <?= Html::encode($facebook->account_name ?: 'My Business') ?>
                                        </h6>
                                        <span class="text-muted text-truncate d-block" style="font-size: 0.75rem; opacity: 0.8;">
                                            ID: <?= Html::encode($facebook->platform_id) ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="mb-3"></div>

                                <div class="mt-auto">
                                    <div class="row g-2 mb-2">
                                        <div class="col-6">
                                            <?= Html::a(
                                                '<i class="fas fa-comment-dots text-primary me-1.5"></i>Message', 
                                                ['/webhook/facebook-index'], 
                                                ['class' => 'btn btn-quick-action btn-sm w-100 fw-medium text-dark text-truncate py-2 rounded-3 text-start px-2.5', 'style' => 'background-color: #f4f6f8; border: 1px solid #e5e9f0; font-size: 0.78rem;']
                                            ) ?>
                                        </div>
                                        <div class="col-6">
                                            <?= Html::a(
                                                '<i class="fas fa-layer-group text-primary me-1.5"></i> Feed & Posts', 
                                                ['webhook/facebook-feed-index'], 
                                                ['class' => 'btn btn-quick-action btn-sm w-100 fw-medium text-dark text-truncate py-2 rounded-3 text-start px-2.5', 'style' => 'background-color: #f4f6f8; border: 1px solid #e5e9f0; font-size: 0.78rem;']
                                            ) ?>
                                        </div>
                                    </div>
                                    <?= Html::a('Manage Account <i class="fas fa-chevron-right ms-1" style="font-size:10px;"></i>', ['profile/index'], ['class' => 'btn btn-primary btn-sm w-100 fw-semibold py-2 rounded-3 shadow-sm', 'style' => 'font-size: 0.85rem;']) ?>
                                </div>
                            <?php else: ?>
                                <div class="py-3">
                                    <p class="text-muted mb-0" style="font-size: 0.82rem; line-height: 1.5;">Connect your Facebook page to manage wall posts, respond to direct messages, and monitor analytics.</p>
                                </div>
                                <div class="mt-auto">
                                    <?= Html::a('Connect Facebook', ['/auth/auth?authclient=facebook'], ['class' => 'btn btn-outline-primary btn-sm w-100 fw-semibold py-2 rounded-3', 'style' => 'font-size: 0.85rem;']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- =================================================
                     2. INSTAGRAM CARD
                ================================================== -->

                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 social-platform-card position-relative overflow-hidden" 
                        style="border: 1px solid #eef0f3 !important; transition: transform 0.2s ease, box-shadow 0.2s ease; background: #ffffff;">
                        
                        <div class="position-absolute top-0 start-0 end-0" style="height: 4px; background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);"></div>

                        <div class="card-body p-4 d-flex flex-column pt-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge bg-danger-subtle text-danger fw-semibold px-3 py-1.5 rounded-pill" style="font-size: 0.75rem;">
                                    <i class="fab fa-instagram me-1.5"></i> Instagram
                                </span>
                                
                                <?php if ($instagram): ?>
                                    <span class="d-inline-flex align-items-center badge bg-success-subtle text-success fw-medium rounded-pill px-2.5 py-1" style="font-size: 0.75rem; border: 1px solid rgba(25, 135, 84, 0.15);">
                                        <span class="bg-success rounded-circle me-1.5" style="width: 6px; height: 6px;"></span>
                                        Connected
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted fw-normal rounded-pill px-2.5 py-1 border" style="font-size: 0.75rem;">
                                        Not Connected
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if ($instagram): ?>

                                <?php
                                // Get Instagram profile picture from platform_data JSON column
                                $instagramPlatformData = $instagram->platform_data;

                                if (is_string($instagramPlatformData)) {
                                    $instagramPlatformData = json_decode($instagramPlatformData, true);
                                }

                                $instagramProfilePic = is_array($instagramPlatformData)
                                    ? ($instagramPlatformData['profile_pic_url'] ?? null)
                                    : null;
                                ?>

                                <div class="d-flex align-items-center my-2">
                                    <div class="position-relative me-3 flex-shrink-0">

                                        <?php if (!empty($instagramProfilePic)): ?>
                                            <img src="<?= Html::encode($instagramProfilePic) ?>"
                                                alt="Avatar"
                                                class="rounded-circle shadow-sm"
                                                style="width: 50px; height: 50px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded-circle d-flex align-items-center justify-content-center text-danger fw-bold shadow-sm" style="width: 50px; height: 50px; font-size: 1.1rem; background: linear-gradient(135deg, #ffeef1 0%, #ffd0d8 100%);">
                                                <?= strtoupper(substr($instagram->account_name ?: 'I', 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>

                                        <span class="position-absolute bottom-0 end-0 text-white rounded-circle d-flex align-items-center justify-content-center border border-2 border-white" style="width: 18px; height: 18px; font-size: 9px; background: #dc2743;">
                                            <i class="fab fa-instagram"></i>
                                        </span>
                                    </div>

                                    <div class="text-truncate">
                                        <h6 class="mb-0 fw-bold text-dark text-truncate" style="font-size: 0.95rem;">
                                            <?= Html::a('<i class="fab fa-instagram me-1.5"></i>' . Html::encode($instagram->account_name), ['/profile/index'], ['class' => 'badge bg-danger-subtle text-danger fw-semibold px-3 py-1.5 rounded-pill text-decoration-none', 'style' => 'font-size: 0.75rem;']) ?>
                                        </h6>
                                        <span class="text-muted text-truncate d-block" style="font-size: 0.75rem; opacity: 0.8;">
                                            @<?= Html::encode($instagram->username ?? $instagram->platform_id) ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="mb-3"></div>

                                <div class="mt-auto">
                                    <div class="row g-2 mb-2">
                                        <div class="col-6">
                                            <?= Html::a(
                                                '<i class="fas fa-paper-plane text-danger me-1.5"></i> Message', 
                                                ['webhook/instagram-index'], 
                                                ['class' => 'btn btn-quick-action btn-sm w-100 fw-medium text-dark text-truncate py-2 rounded-3 text-start px-2.5', 'style' => 'background-color: #f4f6f8; border: 1px solid #e5e9f0; font-size: 0.78rem;']
                                            ) ?>
                                        </div>
                                        <div class="col-6">
                                            <?= Html::a(
                                                '<i class="fas fa-th text-danger me-1.5"></i> Posts / Media', 
                                                ['webhook/instagram-feed-index'], 
                                                ['class' => 'btn btn-quick-action btn-sm w-100 fw-medium text-dark text-truncate py-2 rounded-3 text-start px-2.5', 'style' => 'background-color: #f4f6f8; border: 1px solid #e5e9f0; font-size: 0.78rem;']
                                            ) ?>
                                        </div>
                                    </div>

                                    <?= Html::a(
                                        'Manage Instagram <i class="fas fa-chevron-right ms-1" style="font-size:10px;"></i>',
                                        ['/webhook/instagram-feed-index'],
                                        ['class' => 'btn btn-danger btn-sm w-100 fw-semibold py-2 rounded-3 shadow-sm text-white', 'style' => 'font-size: 0.85rem; background: #dc2743; border-color: #dc2743;']
                                    ) ?>
                                </div>

                            <?php else: ?>

                                <div class="py-3">
                                    <p class="text-muted mb-0" style="font-size: 0.82rem; line-height: 1.5;">
                                        Connect Instagram to manage posts, manage direct messages, and profile engagement.
                                    </p>
                                </div>

                                <div class="mt-auto">
                                    <?= Html::a(
                                        'Connect Instagram',
                                        ['/webhook/connect-instagram'],
                                        ['class' => 'btn btn-outline-danger btn-sm w-100 fw-semibold py-2 rounded-3', 'style' => 'font-size: 0.85rem; border-color: #dc2743;']
                                    ) ?>
                                </div>

                            <?php endif; ?>

                        </div>
                    </div>
                </div>


                <!-- =================================================
                     3. WHATSAPP CARD
                ================================================== -->
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 social-platform-card position-relative overflow-hidden" 
                         style="border: 1px solid #eef0f3 !important; transition: transform 0.2s ease, box-shadow 0.2s ease; background: #ffffff;">
                        
                        <div class="position-absolute top-0 start-0 end-0 bg-success" style="height: 4px;"></div>

                        <div class="card-body p-4 d-flex flex-column pt-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge bg-success-subtle text-success fw-semibold px-3 py-1.5 rounded-pill" style="font-size: 0.75rem;">
                                    <i class="fab fa-whatsapp me-1.5"></i> WhatsApp
                                </span>
                                
                                <?php if ($whatsapp): ?>
                                    <span class="d-inline-flex align-items-center badge bg-success-subtle text-success fw-medium rounded-pill px-2.5 py-1" style="font-size: 0.75rem; border: 1px solid rgba(25, 135, 84, 0.15);">
                                        <span class="bg-success rounded-circle me-1.5" style="width: 6px; height: 6px;"></span>
                                        Connected
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted fw-normal rounded-pill px-2.5 py-1 border" style="font-size: 0.75rem;">
                                        Not Connected
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if ($whatsapp): ?>
                                <div class="d-flex align-items-center my-2">
                                    <div class="position-relative me-3 flex-shrink-0">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center text-success fw-bold shadow-sm" style="width: 50px; height: 50px; font-size: 1.1rem; background: linear-gradient(135deg, #e8f8f0 0%, #c3f0d8 100%);">
                                            <i class="fas fa-phone-alt"></i>
                                        </div>
                                        <span class="position-absolute bottom-0 end-0 bg-success text-white rounded-circle d-flex align-items-center justify-content-center border border-2 border-white" style="width: 18px; height: 18px; font-size: 9px;">
                                            <i class="fab fa-whatsapp"></i>
                                        </span>
                                    </div>
                                    <div class="text-truncate">
                                        <h6 class="mb-0 fw-bold text-dark text-truncate" style="font-size: 0.95rem;">
                                            <?= Html::encode($whatsapp->account_name ?: 'WhatsApp Business') ?>
                                        </h6>
                                        <span class="text-muted text-truncate d-block" style="font-size: 0.75rem; opacity: 0.8;">
                                            Phone ID: <?= Html::encode($whatsapp->platform_id) ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="mb-3"></div>

                                <div class="mt-auto">
                                    
                                    <?= Html::a('Message <i class="fas fa-chevron-right ms-1" style="font-size:10px;"></i>', ['/webhook/whatsapp-index'], ['class' => 'btn btn-success btn-sm w-100 fw-semibold py-2 rounded-3 shadow-sm text-white', 'style' => 'font-size: 0.85rem;']) ?>
                                </div>
                            <?php else: ?>
                                <div class="py-3">
                                    <p class="text-muted mb-0" style="font-size: 0.82rem; line-height: 1.5;">Connect WhatsApp Business API to handle incoming customer queries and automated notifications.</p>
                                </div>
                                <div class="mt-auto">
                                    <?= yii\helpers\Html::a(
                                        '<i class="fab fa-whatsapp me-1.5"></i> Connect WhatsApp', 
                                        ['/webhook/whatsapp-connect'], 
                                        [
                                            'class' => 'btn btn-outline-success btn-sm w-100 fw-semibold py-2 rounded-3 shadow-sm ', 
                                            'style' => 'font-size: 0.85rem;'
                                        ]
                                    ) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- =================================================
                     4. YOUTUBE CARD
                ================================================== -->
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 social-platform-card position-relative overflow-hidden" 
                         style="border: 1px solid #eef0f3 !important; transition: transform 0.2s ease, box-shadow 0.2s ease; background: #ffffff;">
                        
                        <div class="position-absolute top-0 start-0 end-0 bg-danger" style="height: 4px;"></div>

                        <div class="card-body p-4 d-flex flex-column pt-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge bg-danger-subtle text-danger fw-semibold px-3 py-1.5 rounded-pill" style="font-size: 0.75rem;">
                                    <i class="fab fa-youtube me-1.5"></i> YouTube
                                </span>
                                
                                <?php if ($youtube): ?>
                                    <span class="d-inline-flex align-items-center badge bg-success-subtle text-success fw-medium rounded-pill px-2.5 py-1" style="font-size: 0.75rem; border: 1px solid rgba(25, 135, 84, 0.15);">
                                        <span class="bg-success rounded-circle me-1.5" style="width: 6px; height: 6px;"></span>
                                        Connected
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted fw-normal rounded-pill px-2.5 py-1 border" style="font-size: 0.75rem;">
                                        Not Connected
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if ($youtube): ?>
                                <div class="d-flex align-items-center my-2">
                                    <div class="position-relative me-3 flex-shrink-0">
                                        <?php if (!empty($youtube->avatar_url)): ?>
                                            <img src="<?= Html::encode($youtube->avatar_url) ?>" alt="Avatar" class="rounded-circle shadow-sm" style="width: 50px; height: 50px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded-circle d-flex align-items-center justify-content-center text-danger fw-bold shadow-sm" style="width: 50px; height: 50px; font-size: 1.1rem; background: linear-gradient(135deg, #ffeef0 0%, #ffd0d5 100%);">
                                                <?= strtoupper(substr($youtube->account_name ?: 'Y', 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <span class="position-absolute bottom-0 end-0 bg-danger text-white rounded-circle d-flex align-items-center justify-content-center border border-2 border-white" style="width: 18px; height: 18px; font-size: 9px;">
                                            <i class="fab fa-youtube"></i>
                                        </span>
                                    </div>
                                    <div class="text-truncate">
                                        <h6 class="mb-0 fw-bold text-dark text-truncate" style="font-size: 0.95rem;">
                                            <?= Html::encode($youtube->account_name ?: 'YouTube Channel') ?>
                                        </h6>
                                        <span class="text-muted text-truncate d-block" style="font-size: 0.75rem; opacity: 0.8;">
                                            ID: <?= Html::encode($youtube->platform_id) ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="mb-3"></div>

                                <div class="mt-auto">
                                    
                                    <?= Html::a('Manage YouTube <i class="fas fa-chevron-right ms-1" style="font-size:10px;"></i>', ['/youtube/index'], ['class' => 'btn btn-danger btn-sm w-100 fw-semibold py-2 rounded-3 shadow-sm text-white', 'style' => 'font-size: 0.85rem;']) ?>
                                </div>
                            <?php else: ?>
                                <div class="py-3">
                                    <p class="text-muted mb-0" style="font-size: 0.82rem; line-height: 1.5;">Connect YouTube to monitor video comments, review channel analytics, and manage uploads.</p>
                                </div>
                                <div class="mt-auto">
                                    <?= Html::a('Connect YouTube', ['/youtube/connect-youtube'], ['class' => 'btn btn-outline-danger btn-sm w-100 fw-semibold py-2 rounded-3', 'style' => 'font-size: 0.85rem;']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- =================================================
                     5. LINKEDIN CARD
                ================================================== -->
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100 social-platform-card position-relative overflow-hidden" 
                         style="border: 1px solid #eef0f3 !important; transition: transform 0.2s ease, box-shadow 0.2s ease; background: #ffffff;">
                        
                        <div class="position-absolute top-0 start-0 end-0 bg-primary" style="height: 4px; background-color: #0a66c2 !important;"></div>

                        <div class="card-body p-4 d-flex flex-column pt-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge fw-semibold px-3 py-1.5 rounded-pill" style="font-size: 0.75rem; background-color: #e8f3ff; color: #0a66c2;">
                                    <i class="fab fa-linkedin me-1.5"></i> LinkedIn
                                </span>
                                
                                <?php if ($linkedin): ?>
                                    <span class="d-inline-flex align-items-center badge bg-success-subtle text-success fw-medium rounded-pill px-2.5 py-1" style="font-size: 0.75rem; border: 1px solid rgba(25, 135, 84, 0.15);">
                                        <span class="bg-success rounded-circle me-1.5" style="width: 6px; height: 6px;"></span>
                                        Connected
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted fw-normal rounded-pill px-2.5 py-1 border" style="font-size: 0.75rem;">
                                        Not Connected
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if ($linkedin): ?>
                                <div class="d-flex align-items-center my-2">
                                    <div class="position-relative me-3 flex-shrink-0">
                                        <?php if (!empty($linkedin->avatar_url)): ?>
                                            <img src="<?= Html::encode($linkedin->avatar_url) ?>" alt="Avatar" class="rounded-circle shadow-sm" style="width: 50px; height: 50px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 50px; height: 50px; font-size: 1.1rem; color: #0a66c2; background: linear-gradient(135deg, #e8f3ff 0%, #cce3ff 100%);">
                                                <?= strtoupper(substr($linkedin->account_name ?: 'L', 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <span class="position-absolute bottom-0 end-0 text-white rounded-circle d-flex align-items-center justify-content-center border border-2 border-white" style="width: 18px; height: 18px; font-size: 9px; background-color: #0a66c2;">
                                            <i class="fab fa-linkedin-in"></i>
                                        </span>
                                    </div>
                                    <div class="text-truncate">
                                        <h6 class="mb-0 fw-bold text-dark text-truncate" style="font-size: 0.95rem;">
                                            <?= Html::encode($linkedin->account_name ?: 'LinkedIn Page') ?>
                                        </h6>
                                        <span class="text-muted text-truncate d-block" style="font-size: 0.75rem; opacity: 0.8;">
                                            ID: <?= Html::encode($linkedin->platform_id) ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="mb-3"></div>

                                <div class="mt-auto">
                                    <div class="row g-2 mb-2">
                                        <div class="col-6">
                                            <?= Html::a(
                                                '<i class="fas fa-paper-plane me-1.5" style="color: #0a66c2;"></i> Messages', 
                                                ['/linkedin/messages'], 
                                                ['class' => 'btn btn-quick-action btn-sm w-100 fw-medium text-dark text-truncate py-2 rounded-3 text-start px-2.5', 'style' => 'background-color: #f4f6f8; border: 1px solid #e5e9f0; font-size: 0.78rem;']
                                            ) ?>
                                        </div>
                                        <div class="col-6">
                                            <?= Html::a(
                                                '<i class="fas fa-newspaper me-1.5" style="color: #0a66c2;"></i> Posts', 
                                                ['/linkedin/posts'], 
                                                ['class' => 'btn btn-quick-action btn-sm w-100 fw-medium text-dark text-truncate py-2 rounded-3 text-start px-2.5', 'style' => 'background-color: #f4f6f8; border: 1px solid #e5e9f0; font-size: 0.78rem;']
                                            ) ?>
                                        </div>
                                    </div>
                                    <?= Html::a('Manage LinkedIn <i class="fas fa-chevron-right ms-1" style="font-size:10px;"></i>', ['/linkedin/index'], ['class' => 'btn btn-sm w-100 fw-semibold py-2 rounded-3 shadow-sm text-white', 'style' => 'font-size: 0.85rem; background-color: #0a66c2; border-color: #0a66c2;']) ?>
                                </div>
                            <?php else: ?>
                                <div class="py-3">
                                    <p class="text-muted mb-0" style="font-size: 0.82rem; line-height: 1.5;">Connect LinkedIn to publish updates, view page engagement, and schedule corporate posts.</p>
                                </div>
                                <div class="mt-auto">
                                    <?= Html::a('Connect LinkedIn', ['/auth/auth?authclient=linkedin'], ['class' => 'btn btn-outline-primary btn-sm w-100 fw-semibold py-2 rounded-3', 'style' => 'font-size: 0.85rem; border-color: #0a66c2;']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>