<?php
use yii\helpers\Html;

/** @var app\models\InstagramChatLog $msg */
/** @var bool $isReply */

$avatarSize = $isReply ? '24px' : '32px';
$fontSize = $isReply ? '13px' : '14px';
$username = Html::encode($msg->commenter_username);
$body = Html::encode($msg->message_body);
$time = date('H:i', strtotime($msg->created_at));
?>

<div class="insta-comment-row" style="display: flex; align-items: flex-start; margin-bottom: 12px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <!-- Avatar -->
    <div class="comment-avatar" style="width: <?= $avatarSize ?>; height: <?= $avatarSize ?>; border-radius: 50%; background-color: #dbdbdb; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #737373; font-weight: bold; margin-right: 12px; flex-shrink: 0; text-transform: uppercase;">
        <?= substr($username, 0, 1) ?>
    </div>
    
    <!-- Content -->
    <div class="comment-content" style="flex-grow: 1; font-size: <?= $fontSize ?>; line-height: 1.4;">
        <span class="comment-username" style="font-weight: 600; color: #262626; margin-right: 6px;">
            <?= $username ?>
        </span>
        <span class="comment-text" style="color: #262626; word-break: break-word;">
            <?= $body ?>
        </span>
        
        <!-- Actions -->
        <div class="comment-meta-actions" style="display: flex; align-items: center; gap: 15px; margin-top: 4px; font-size: 12px; color: #737373;">
            <span class="comment-time"><?= $time ?></span>
            
                <button type="button" class="btn-reply-trigger" 
                        data-comment-id="<?= Html::encode($msg->meta_message_id) ?>" 
                        data-username="<?= $username ?>"
                        style="background: none; border: none; padding: 0; font-weight: 600; color: #737373; cursor: pointer; font-size: 12px;">
                    Reply
                </button>

            <!-- Added Deletion Component Link Trigger -->
            <button type="button" class="btn-delete-comment" 
                    data-comment-id="<?= Html::encode($msg->meta_message_id) ?>" 
                    style="background: none; border: none; padding: 0; font-weight: 600; color: #a8a8a8; opacity: 0.4; cursor: pointer; font-size: 12px; transition: opacity 0.2s ease;">
                Delete
            </button>
        </div>
    </div>
</div>