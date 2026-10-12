<?php
use yii\helpers\Html;

/* @var $msg app\models\InstagramChatLog */
/* @var $isInbound bool */

$isInbound = $msg->direction === 'inbound';
$body = trim($msg->message_body);
?>
<div class="message-bubble <?= $isInbound ? 'inbound' : 'outbound' ?>" data-message-id="<?= $msg->id ?>">
    <div class="msg-mobile-row" style="margin-bottom: 10px; display: flex; width: 100%; justify-content: <?= $isInbound ? 'flex-start' : 'flex-end' ?>; padding-left: 14px; padding-right: 14px; box-sizing: border-box;">
        
        <div class="msg-bubble-container" 
            data-msg-id="<?= $msg->id ?>" 
            data-msg-text="<?= Html::encode($body) ?>"
            style="position: relative; max-width: 70%; min-width: 80px; padding: 10px 14px 22px 14px; border-radius: 18px; user-select: none; box-sizing: border-box;
                    /* Dark mode friendly bubble colors */
                    background-color: <?= $isInbound ? '#262626' : '#0095f6' ?>; 
                    color: #ffffff;
                    border: 1px solid <?= $isInbound ? '#363636' : '#007ccf' ?>;
                    box-shadow: 0 1px 2px rgba(0,0,0,0.2);">

            <!-- Text Body Container -->
            <span style="font-size: 14px; line-height: 1.4; word-break: break-word; display: block;">
                <?= Html::encode($body) ?>
            </span>

            <!-- Floating Muted Timestamp Footer (No blue checkmarks) -->
            <small style="font-size: 9px; color: <?= $isInbound ? '#a8a8a8' : '#e0f2fe' ?>; position: absolute; bottom: 5px; right: 12px; display: block; font-weight: 500;">
                <?= date('M j, Y g:i A', strtotime($msg->created_at)) ?>
            </small>
        </div>
    </div>
</div>