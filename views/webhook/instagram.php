<?php
/** @var yii\web\View $this */
/** @var app\models\SocialContact[] $contacts */
/** @var app\models\InstagramChatLog[] $messages */
/** @var app\models\SocialContact|null $activeContact */
/** @var string $activePhone */
/** @var string $currentPlatform */

use yii\helpers\Url;
use yii\helpers\Html;

$this->title = 'Instagram Dashboard';
$lastMessageId = 0;
?>

<div class="instagram-messaging-wrapper" style="display: flex; height: 100vh; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #000000;">
    
    <div class="instagram-sidebar" style="width: 350px; background: #121212; border-right: 1px solid #262626; display: flex; flex-direction: column;">
        <div class="sidebar-header" style="padding: 20px; background: #1c1c1e; color: #fff; font-size: 18px; font-weight: bold; border-bottom: 1px solid #262626; display: flex; align-items: center; gap: 10px;">
            <div style="width: 12px; height: 12px; background: #e1306c; border-radius: 50%;"></div>
            Instagram Direct
        </div>
        
        <div class="contacts-list" style="flex: 1; overflow-y: auto;">
            <?php if (empty($contacts)): ?>
                <div style="color: #737373; text-align: center; margin-top: 40px; padding: 0 20px; font-size: 14px;">
                    No active Instagram threads found.
                </div>
            <?php else: ?>
                <?php foreach ($contacts as $contact): ?>
                    <?php $isActive = ($contact->platform_uid === $activePhone); ?>
                    <a href="<?= Url::to(['webhook/instagram-index', 'phone' => $contact->platform_uid, 'platform' => 'INSTAGRAM']) ?>" 
                       class="contact-item" style="display: block; text-decoration: none; padding: 15px 20px; border-bottom: 1px solid #262626; background: <?= $isActive ? '#262626' : 'transparent' ?>; transition: background 0.2s;">
                        <div class="d-flex align-items-center gap-2 fw-semibold text-light fs-6 text-break">
    <?php if (!empty($contact->profile_pic)): ?>
        <img src="<?= Html::encode($contact->profile_pic) ?>" 
             alt="Profile" 
             class="rounded-circle border border-secondary flex-shrink-0" 
             style="width: 48px; height: 48px; object-fit: cover;" />
    <?php else: ?>
        <?php 
            $firstLetter = strtoupper(substr($contact->name ?? $contact->username ?? 'U', 0, 1));
        ?>
        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center border border-secondary flex-shrink-0 fw-bold fs-5" 
             style="width: 48px; height: 48px;">
            <?= Html::encode($firstLetter) ?>
        </div>
    <?php endif; ?>

    <span>@<?= Html::encode($contact->username) ?></span>
</div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="chat-area" style="flex: 1; background: #000000; display: flex; flex-direction: column;">
        <?php if ($activeContact): ?>
            <div class="chat-header p-3 px-4 bg-dark d-flex justify-content-between align-items-center border-bottom border-secondary" style="background: #332f2f;">
                <div class="d-flex align-items-center gap-2 text-light fw-bold fs-6">
                    <span>Conversation with</span>

                    <?php
                        $username = trim((string)$contact->username);
                        $avatarLetter = $username !== '' ? strtoupper(mb_substr($username, 0, 1)) : '?';

                        if (!empty($contact->profile_pic)): ?>
                            <img src="<?= Html::encode($contact->profile_pic) ?>"
                                alt="Profile"
                                class="rounded-circle border border-secondary flex-shrink-0"
                                style="width: 48px; height: 48px; object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-secondary text-light d-flex align-items-center justify-content-center fw-bold flex-shrink-0 border border-secondary"
                                style="width: 48px; height: 48px; font-size: 20px;">
                                <?= Html::encode($avatarLetter) ?>
                            </div>
                        <?php endif; ?>

                    <span style="color: #e1306c;">@<?= Html::encode($contact->username) ?></span>
                </div>

                <!-- Right side controls (if needed) -->
                <!-- <div>
                    <button id="btn-toggle-bot" class="btn text-white rounded-pill fw-bold btn-sm" style="background: #e1306c;">
                        Automation: <?= $activeContact->bot_state === 'turned_off' ? 'PAUSED' : 'ACTIVE' ?>
                    </button>
                </div> -->
            </div>

            <div id="chat-stream-box" style="flex: 1; overflow-y: auto; padding: 30px; display: flex; flex-direction: column; gap: 12px;">
                <?php 
                $lastMessageId = 0;
                foreach ($messages as $msg) {
                    $lastMessageId = $msg->id;
                    echo $this->render('insta_message_bubble', [
                        'msg' => $msg,
                        'isInbound' => ($msg->direction === 'inbound')
                    ]);
                }
                ?>
            </div>

            <div class="chat-footer" style="padding: 20px; background: #121212; display: flex; gap: 15px; align-items: center; border-top: 1px solid #262626;">
                <input type="text" id="input-message-body" placeholder="Message..." 
                       style="flex: 1; padding: 12px 20px; border-radius: 24px; border: 1px solid #262626; background: #000000; color: #f5f5f5; font-size: 14px; outline: none;">
                <button id="btn-send-message" style="background: transparent; color: #e1306c; border: none; padding: 0 10px; cursor: pointer; font-weight: bold; font-size: 15px;">
                    Send
                </button>
            </div>

        <?php else: ?>
            <div style="flex: 1; display: flex; justify-content: center; align-items: center; flex-direction: column; color: #737373;">
                <div style="font-size: 40px; margin-bottom: 10px;">✉️</div>
                <div style="font-size: 18px; color: #f5f5f5; font-weight: 400; margin-bottom: 5px;">Your Instagram Messages</div>
                <div style="font-size: 14px;">Select a profile from the directory to start talking.</div>
            </div>
        <?php endif; ?>
    </div>
</div>




<?php

$sendUrl   = Url::to(['webhook/send-insta-message']);
$toggleUrl = Url::to(['webhook/toggle-bot-state']);

$script = <<<JS
    var currentUid = "{$activePhone}";
    var lastMessageId = "{$lastMessageId}";

    function scrollToBottom() {
        var box = document.getElementById('chat-stream-box');
        if (box) { box.scrollTop = box.scrollHeight; }
    }
    scrollToBottom();

// 1. Global Scope Helper Function
window.appendMessageSafely = function(htmlString, messageId) {
    if (!messageId) return;
    
    var existingElement = document.querySelector('#chat-stream-box [data-message-id="' + messageId + '"]');
    if (!existingElement) {
        var chatBox = document.getElementById('chat-stream-box');
        if (chatBox) {
            chatBox.insertAdjacentHTML('beforeend', htmlString);
            if (typeof scrollToBottom === 'function') {
                scrollToBottom();
            }
        }
    }
};

// 2. Keypress Handler for Enter Key
$('#input-message-body').off('keypress').on('keypress', function(e) {
    if (e.which === 13 && !e.shiftKey) { 
        e.preventDefault();
        $('#btn-send-message').click(); 
    }
});

// 3. Outbound Message Dispatcher
$('#btn-send-message').off('click').on('click', function() {
    var bodyText = $('#input-message-body').val().trim();
    if (!bodyText) return;

    $('#input-message-body').val('');

    $.ajax({
        url: '{$sendUrl}',
        type: 'POST',
        data: {
            recipient_id: currentUid, 
            message: bodyText,  
            platform: 'instagram' 
        },
        success: function(res) {
            if (res.success) {
                if (res.id && parseInt(res.id) > parseInt(lastMessageId)) {
                    lastMessageId = res.id;
                }
                if (res.html && res.id) {
                    window.appendMessageSafely(res.html, res.id);
                }
            } else {
                alert('Instagram Dispatch Failure: ' + (res.error || res.message));
            }
        }
    });
});


JS;

$this->registerJs($script);
?>