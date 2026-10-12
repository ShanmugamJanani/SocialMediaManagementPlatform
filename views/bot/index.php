<?php
use yii\helpers\Url;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\SocialContact[] $contacts */
/** @var string|null $activePhone */
/** @var app\models\WhatsappChatLog[] $messages */
/** @var app\models\SocialContact|null $activeContact */

$this->title = 'WhatsApp Portal Dashboard';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<div class="whatsapp-chat-portal" style="margin-top: 10px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
    <div class="row">
        <div class="col-md-4" style="border-right: 1px solid #e1e9eb; height: 85vh; overflow-y: auto; background: #fff; padding-right: 0;">
            <div style="padding: 15px; border-bottom: 1px solid #f0f2f5; background: #f0f2f5;">
                <h4 style="margin: 0;">Chats</h4>
            </div>

            <div class="list-group list-group-flush">
                <?php foreach ($contacts as $contact): ?>
                    <?php 
                    $isBotOff = ($contact->bot_state === 'turned_off');
                    $activeClass = ($activePhone === $contact->phone_number) ? 'active' : '';
                    ?>
                    <a href="<?= Url::to(['bot/index', 'phone' => $contact->phone_number]) ?>"
                       class="list-group-item list-group-item-action <?= $activeClass ?>" 
                       style="display: flex; justify-content: space-between; align-items: center;">
                        <span>+<?= Html::encode($contact->phone_number) ?></span>
                        <span class="badge" style="background: <?= $isBotOff ? '#747474' : '#00a884' ?>; font-size: 10px; color: #fff; border-radius: 10px;">
                            <?= $isBotOff ? 'Agent' : 'Bot Active' ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-md-8" style="height: 85vh; display:flex; flex-direction:column; background:#efeae2;">
            <?php if ($activePhone && $activeContact): ?>
                
                <div style="padding:10px 20px; background:#f0f2f5; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e1e9eb;">
                    <div>
                        <h4 style="margin: 0;">+<?= Html::encode($activePhone) ?></h4>
                        <small style="color: #667781;">
                            Status: <strong><?= ($activeContact->bot_state === 'turned_off') ? '👨‍💻 Human Intervened' : '🤖 Automated Bot Flow (' . Html::encode($activeContact->bot_state) . ')' ?></strong>
                        </small>
                    </div>
                    <div>
                        <?php if ($activeContact->bot_state === 'turned_off'): ?>
                            <button id="btn-toggle-bot" class="btn btn-sm" style="background: #00a884; color: #fff; border-radius: 20px;" data-action="resume">
                                <i class="bi bi-robot"></i> Restart Bot Engine
                            </button>
                        <?php else: ?>
                            <button id="btn-toggle-bot" class="btn btn-sm" style="background: #ea0038; color: #fff; border-radius: 20px;" data-action="pause">
                                <i class="bi bi-person-fill-dash"></i> Pause Bot & Take Over
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="chat-stream-box" style="flex:1; overflow-y:auto; padding:12px;">
                    <?php foreach ($messages as $msg): ?>
                        <?= $this->render('message_bubble', ['msg' => $msg]); ?>
                    <?php endforeach; ?>
                </div>

                <div style="padding:10px; background:#f0f2f5;">
                    <div id="reply-preview" style="display:none; padding:6px 10px; background:#f0f2f5; border-left:4px solid #00a884; margin-bottom:6px;">
                        <div id="reply-text" style="font-size:12px;"></div>
                        <span id="reply-cancel" style="cursor:pointer; float:right;">✕</span>
                    </div>

                    <div style="display:flex; align-items:center; background:#fff; padding:8px; border-radius:24px; position: relative;">
                        <input
                            type="text"
                            id="chat-reply-input"
                            style="flex:1; border:none; outline:none; padding:10px;"
                            placeholder="Type a message manually..."
                            autocomplete="off">
                        
                        <button id="send-chat-btn" type="button" style="background:none; border:none; font-size:22px; cursor:pointer; color:#00a884;">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </div>
                </div>

            <?php else: ?>
                <div style="margin:auto; text-align: center; color: #667781;">
                    <i class="bi bi-whatsapp" style="font-size: 48px; color: #cbd5e1;"></i>
                    <div style="margin-top: 10px;">Select a chat to inspect bot state pipelines</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="forwardModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10000; align-items:center; justify-content:center;">
    <div style="background:#fff; width:400px; border-radius:8px; padding:20px; box-shadow:0 4px 12px rgba(0,0,0,0.15); max-height:80vh; display:flex; flex-direction:column;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid #f0f2f5; padding-bottom:10px;">
            <h5 style="margin:0;">Forward Message To:</h5>
            <span id="closeForwardModal" style="cursor:pointer; font-size:20px; font-weight:bold; color:#54656f;">✕</span>
        </div>
        
        <div style="flex:1; overflow-y:auto; margin-bottom:15px;" id="forward-contact-list">
            <div class="list-group list-group-flush">
                <?php foreach ($contacts as $cItem): ?>
                    <button type="button" class="list-group-item list-group-item-action forward-target-btn" data-phone="<?= Html::encode($cItem->phone_number) ?>" style="text-align:left; border:none; padding:12px 15px;">
                        <i class="bi bi-person-fill" style="color:#00a884; margin-right:10px;"></i>
                        +<?= Html::encode($cItem->phone_number) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Config mappings targets
$sendUrl = Url::to(["bot/send"]);
$getNewMessagesUrl = Url::to(["bot/get-new-messages"]);
$toggleBotUrl = Url::to(["bot/toggle-bot-state"]);
$forwardUrl = Url::to(["bot/forward-message"]);

$lastMessageId = !empty($messages) ? (int)end($messages)->id : 0;
$cleanPhone = Html::encode($activePhone);

$this->registerJs("
    window.chatConfig = {
        sendUrl: '{$sendUrl}',
        getNewMessagesUrl: '{$getNewMessagesUrl}',
        toggleBotUrl: '{$toggleBotUrl}',
        forwardUrl: '{$forwardUrl}',
        activePhone: '{$cleanPhone}',
        lastMessageId: {$lastMessageId}
    };
", \yii\web\View::POS_HEAD);

$script = <<<'JS'
let replyMessageId = null;
let replyMessageText = null;

function scrollBottom() {
    let box = document.getElementById('chat-stream-box');
    if (box) box.scrollTop = box.scrollHeight;
}
scrollBottom();

function escapeHtml(string) {
    return String(string).replace(/[&<>"'`=\/]/g, function (s) {
        return {
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;', '/': '&#x2F;', '`': '&#x60;', '=': '&#x3D;'
        }[s];
    });
}

// ================= LIVE AUTO-POLLING REGISTRATION =================
function pollBotChatLogs() {
    if (!window.chatConfig.activePhone) return;

    $.ajax({
        url: window.chatConfig.getNewMessagesUrl,
        type: 'GET',
        data: {
            phone: window.chatConfig.activePhone,
            last_id: window.chatConfig.lastMessageId
        },
        success: function(res) {
            if (res.success && res.has_new) {
                $('#chat-stream-box').append(res.html);
                window.chatConfig.lastMessageId = res.latest_id;
                scrollBottom();
            }
        }
    });
}
setInterval(pollBotChatLogs, 3000);

// ================= TOGGLE BOT STATE (PAUSE/RESUME) =================
$(document).on('click', '#btn-toggle-bot', function() {
    let action = $(this).data('action');
    let confirmPrompt = (action === 'pause') 
        ? 'Are you sure you want to stop the automated bot for this customer?' 
        : 'Restart bot automatic reply processing?';

    if (confirm(confirmPrompt)) {
        $.ajax({
            url: window.chatConfig.toggleBotUrl,
            type: 'POST',
            data: {
                phone_number: window.chatConfig.activePhone,
                target_action: action
            },
            success: function(res) {
                if (res.success) {
                    location.reload();
                } else {
                    alert(res.error || "Failed to switch execution modes.");
                }
            }
        });
    }
});

// ================= OUTBOUND TEXT DISPATCH =================
$('#send-chat-btn').on('click', executeChatOutbound);
$('#chat-reply-input').on('keypress', function(e){
    if (e.which === 13) executeChatOutbound();
});

function executeChatOutbound() {
    let textPayload = $('#chat-reply-input').val();
    if (!textPayload.trim()) return;
    
    $.ajax({
        url: window.chatConfig.sendUrl,
        type: 'POST',
        data: {
            phone_number: window.chatConfig.activePhone,
            message_body: textPayload,
            reply_message_id: replyMessageId
        },
        success: function(res) {
            if (res.success) {
                addMessageToUI(textPayload, res.timestamp);
                clearReply(); 
            } else {
                alert(res.error || "Send failed");
            }
        }
    });
}

$('#reply-cancel').on('click', function() { clearReply(); });
function showReplyPreview(text) { $('#reply-preview').show(); $('#reply-text').text(text); }
function hideReplyPreview() { $('#reply-preview').hide(); $('#reply-text').text(''); }
function clearReply() { replyMessageId = null; replyMessageText = null; hideReplyPreview(); }

function replyMessage(id, msgText) { replyMessageId = id; replyMessageText = msgText; showReplyPreview(msgText); }

function addMessageToUI(text, time) {
    let safeText = escapeHtml(text);
    let safeTime = escapeHtml(time);
    let html = `
        <div style="display:flex; justify-content:flex-end; margin:8px 0;">
            <div style="background:#e7ffdb; padding:10px; border-radius:8px; max-width: 70%;">
                <div>${safeText}</div>
                <div style="font-size:10px; text-align:right; color: #747474; margin-top: 4px;">${safeTime}</div>
            </div>
        </div>
    `;
    $('#chat-stream-box').append(html);
    $('#chat-reply-input').val('');
    scrollBottom();
}

// ================= CONTEXT POPUP ACTION MENUS =================
$('#chat-stream-box').on('click', '.msg-bubble-container', function(e){
    e.stopPropagation();
    openMessageActions($(this), $(this).attr('data-msg-meta_message_id'), $(this).attr('data-msg-text'));
});

function openMessageActions(el, id, msgText) {
    $('.msg-action-menu').remove();
    let menu = $(`
        <div class="msg-action-menu" style="position:absolute; background:#fff; border:1px solid #ddd; padding:8px; z-index:9999; border-radius:4px; box-shadow:0 2px 5px rgba(0,0,0,0.1);">
            <div style="cursor:pointer; padding:2px 8px;" class="msg-action-item" data-action="reply" data-id="${id}" data-text="${msgText}">Reply</div>
            <div style="cursor:pointer; padding:2px 8px; border-top:1px solid #eee;" class="msg-action-item" data-action="forward" data-id="${id}">Forward</div>
        </div>
    `);
    $('body').append(menu);
    let offset = el.offset();
    menu.css({ top: offset.top - 40, left: offset.left });
}

$(document).on('click', '.msg-action-item', function () {
    let action = $(this).attr('data-action');
    let id = $(this).attr('data-id');
    let text = $(this).attr('data-text'); 
    $('.msg-action-menu').remove();

    if (action === 'reply') replyMessage(id, text);
    if (action === 'forward') { forwardMessageId = id; $('#forwardModal').css('display', 'flex'); }
});

$('#closeForwardModal').on('click', function() { $('#forwardModal').hide(); forwardMessageId = null; });

$(document).on('click', '.forward-target-btn', function() {
    let targetPhone = $(this).data('phone');
    if (!forwardMessageId) return;
    let btn = $(this);
    btn.prop('disabled', true).css('opacity', '0.6');

    $.ajax({
        url: window.chatConfig.forwardUrl,
        type: 'POST',
        data: { message_id: forwardMessageId, target_phone: targetPhone },
        success: function(res) {
            if (res.success) $('#forwardModal').hide();
            else alert(res.error || "Forward failed.");
        },
        complete: function() { btn.prop('disabled', false).css('opacity', '1'); forwardMessageId = null; }
    });
});

$(document).on('click', function(){ 
    $('.msg-action-menu').remove(); 
});
JS;
$this->registerJs($script);
?>