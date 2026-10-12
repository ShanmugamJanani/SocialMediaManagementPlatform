<?php
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Connect Facebook Account';
?>

<div class="connect-facebook-container text-center my-5">
    <h2>Connect Your Facebook Account</h2>
    <p class="text-muted">You need to link your Facebook account to view posts and manage feeds.</p>

    <div class="mt-4">
        <?= Html::a(
            '<i class="fab fa-facebook"></i> Connect with Facebook',
            Url::to(['auth/auth', 'authclient' => 'facebook']), 
            ['class' => 'btn btn-primary btn-lg']
        ) ?>
    </div>
</div>