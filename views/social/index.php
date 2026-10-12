<?php
use yii\helpers\Url;
use yii\helpers\Html;
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script type="module" src="https://cdn.jsdelivr.net/npm/emoji-picker-element@^1/index.js"></script>

<?php $this->title = 'WhatsApp Portal Dashboard'; ?>

<div class="whatsapp-chat-portal" style="margin-top: 10px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">

    <div class="row">
        <div class="col-md-4" style="border-right: 1px solid #e1e9eb; height: 85vh; overflow-y: auto; background: #fff; padding-right: 0;">
            <div style="padding: 15px; border-bottom: 1px solid #f0f2f5; background: #f0f2f5;">
                <h4 style="margin: 0;">Chats</h4>
            </div>

            <div class="list-group list-group-flush">
                <?php foreach ($contacts as $contact): ?>
                    <a href="<?= Url::to(['social/index', 'phone' => $contact->phone_number]) ?>"
                       class="list-group-item list-group-item-action">
                        +<?= Html::encode($contact->phone_number) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-md-8" style="height: 85vh; display:flex; flex-direction:column; background:#efeae2;">

            <?php if ($activePhone): ?>

                <div style="padding:10px; background:#f0f2f5;">
                    <h4>+<?= Html::encode($activePhone) ?></h4>
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

                        <span id="emojiBtn" style="cursor:pointer; font-size: 20px; padding: 0 5px;">😀</span>
                        <div id="emojiPickerWrapper" style="display:none; position:absolute; bottom:60px; left:10px; z-index:999;">
                            <emoji-picker id="emojiPicker"></emoji-picker>
                        </div>

                        <input
                            type="text"
                            id="chat-reply-input"
                            style="flex:1; border:none; outline:none; padding:10px;"
                            placeholder="Type a message"
                            autocomplete="off">
                            
                        <button
                            type="button"
                            id="attach-btn"
                            title="Attach File"
                            style="background:none; border:none; font-size:22px; cursor:pointer; color:#54656f;">
                            <i class="bi bi-paperclip"></i>
                        </button>

                        <input
                            type="file"
                            id="chat-file-input"
                            style="display:none;"
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx">
                            
                        <button
                            id="voiceNoteBtn"
                            type="button"
                            style="background:none; border:none; font-size:22px; cursor:pointer; color:#54656f;">
                            <i class="bi bi-mic"></i>
                        </button>
                        
                        <button
                            id="send-chat-btn"
                            type="button"
                            style="background:none; border:none; font-size:22px; cursor:pointer; color:#00a884;">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div style="margin:auto;">Select a chat</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="forwardModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10000; align-items:center; justify-content:center;">
    <div style="background:#fff; width:400px; border-radius:8px; padding:20px; box-shadow:0 4px 12px rgba(0,0,0,0.15); max-height:80vh; display:flex; flex-direction:column;">
        <div style="display:flex; justify-content:between; align-items:center; margin-bottom:15px; border-bottom:1px solid #f0f2f5; padding-bottom:10px;">
            <h5 style="margin:0;">Forward Message To:</h5>
            <span id="closeForwardModal" style="cursor:pointer; font-size:20px; font-weight:bold; color:#54656f;">✕</span>
        </div>
        
        <div style="flex:1; overflow-y:auto; margin-bottom:15px;" id="forward-contact-list">
            <div class="list-group list-group-flush">
                <?php foreach ($contacts as $contact): ?>
                    <button type="button" 
                            class="list-group-item list-group-item-action forward-target-btn" 
                            data-phone="<?= Html::encode($contact->phone_number) ?>"
                            style="text-align:left; border:none; padding:12px 15px;">
                        <i class="bi bi-person-fill" style="color:#00a884; margin-right:10px;"></i>
                        +<?= Html::encode($contact->phone_number) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>



<?php
$sendUrl = Url::to(["social/send"]);
$deleteUrl = Url::to(["social/delete-message"]);
$sendDocUrl = Url::to(["social/send-document"]);
$sendAudioUrl = Url::to(["social/send-audio"]);
$forwardUrl = Url::to(["social/forward"]);
$cleanPhone = Html::encode($activePhone);

$this->registerJs("
    window.chatConfig = {
        sendUrl: '{$sendUrl}',
        deleteUrl: '{$deleteUrl}',
        sendDocUrl: '{$sendDocUrl}',
        sendAudioUrl: '{$sendAudioUrl}',
        forwardUrl: '{$forwardUrl}',
        activePhone: '{$cleanPhone}'
    };
", \yii\web\View::POS_HEAD);

$script = <<<'JS'
let mediaRecorder;
let audioChunks = [];
let isRecording = false;

function scrollBottom() {
    let box = document.getElementById('chat-stream-box');
    if (box) box.scrollTop = box.scrollHeight;
}

// Helper function to safely escape strings in Javascript to avoid XSS injections
function escapeHtml(string) {
    return String(string).replace(/[&<>"'`=\/]/g, function (s) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
            '/': '&#x2F;',
            '`': '&#x60;',
            '=': '&#x3D;'
        }[s];
    });
}

// ================= OUTBOUND TEXT DISPATCH =================
$('#send-chat-btn').on('click', executeChatOutbound);

$('#chat-reply-input').on('keypress', function(e){
    if (e.which === 13) executeChatOutbound();
});

function executeChatOutbound() {
    let textPayload = $('#chat-reply-input').val();
    if (!textPayload.trim()) return;

    let quotedTextForUI = replyMessageText;
    
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

$('#reply-cancel').on('click', function() {
    clearReply();
});

function showReplyPreview(text) {
    $('#reply-preview').show();
    $('#reply-text').text(text);
}

function hideReplyPreview() {
    $('#reply-preview').hide();
    $('#reply-text').text('');
}

function addMessageToUI(text, time) {
    // FIXED: Escaping the raw text string to render safely as text text rather than raw executable HTML
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

// ================= ATTACH FILE TRIGGER =================
$('#attach-btn').on('click', function() {
    $('#chat-file-input').click();
});

// ================= MULTIPART FILE UPLOAD DISPATCH =================
$('#chat-file-input').on('change', function() {
    let fileInput = this;
    if (fileInput.files.length === 0) return;

    let fileToUpload = fileInput.files[0];
    let formData = new FormData();

    formData.append('whatsapp_doc', fileToUpload);
    formData.append('phone_number', window.chatConfig.activePhone);

    let sendBtnIcon = $('#attach-btn i');
    sendBtnIcon.removeClass('bi-paperclip').addClass('bi-hourglass-split');

    $.ajax({
        url: window.chatConfig.sendDocUrl,
        type: 'POST',
        data: formData,
        processData: false, 
        contentType: false, 
        success: function(res) {
            if (res.success) {
                $(fileInput).val('');
                let docHtml = `
                    <div style="display:flex; justify-content:flex-end; margin:8px 0;">
                        <div style="background:#e7ffdb; padding:10px; border-radius:8px; max-width: 70%;">
                            <div style="font-weight:bold; color:#00a884; margin-bottom:4px;">
                                <i class="bi bi-file-earmark-arrow-up-fill"></i> Document Sent
                            </div>
                            <small style="word-break: break-all;">
                                <a href="${res.file_url}" target="_blank" style="color:#025fd4; text-decoration:underline;">
                                    ${escapeHtml(fileToUpload.name)}
                                </a>
                            </small>
                        </div>
                    </div>
                `;
                $('#chat-stream-box').append(docHtml);
                scrollBottom();
            } else {
                alert(res.error || "File asset upload rejected by server rules.");
            }
        },
        error: function() {
            alert("Network connection error handling attachment data transfer.");
        },
        complete: function() {
            sendBtnIcon.removeClass('bi-hourglass-split').addClass('bi-paperclip');
        }
    });
});

// ================= MESSAGE OPTIONS ACTION MENUS =================
let replyMessageId = null;
let replyMessageText = null;

$('#chat-stream-box').on('click', '.msg-bubble-container', function(e){
    e.stopPropagation();

    let msgId = $(this).attr('data-msg-id');
    let metaId = $(this).attr('data-msg-meta_message_id'); 
    let msgText = $(this).attr('data-msg-text');

    openMessageActions($(this), metaId, msgText);
});

function openMessageActions(el, id, msgText) {
    $('.msg-action-menu').remove();

    let menu = $(`
        <div class="msg-action-menu" style="position:absolute; background:#fff; border:1px solid #ddd; padding:8px; z-index:9999;">
            <div id="reply-cancel" class="msg-action-item" style="cursor:pointer;"> ✕ </div>
            <div style="cursor:pointer" class="msg-action-item" data-action="reply" data-id="${id}" data-text="${msgText}">Reply</div>
            <div style="cursor:pointer" class="msg-action-item" data-action="forward" data-id="${id}">Forward</div>
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

    if (action === 'reply') {
        replyMessage(id, text);
    }

    if (action === 'forward') forwardMessage(id);
});

function clearReply() {
    replyMessageId = null;
    replyMessageText = null;
    hideReplyPreview();
}

function replyMessage(id, msgText) {
    replyMessageId = id;
    replyMessageText = msgText;

    showReplyPreview(msgText);
}

let forwardMessageId = null;
function forwardMessage(id) {
   forwardMessageId = id;
    // Show the modal backdrop layout cleanly
    $('#forwardModal').css('display', 'flex');
}

// Modal closing rules
$('#closeForwardModal').on('click', function() {
    $('#forwardModal').hide();
    forwardMessageId = null;
});

// Close modal if user clicks outside the modal box container
$(window).on('click', function(e) {
    if ($(e.target).is('#forwardModal')) {
        $('#forwardModal').hide();
        forwardMessageId = null;
    }
});

// Handle choosing a contact to forward the message to
$(document).on('click', '.forward-target-btn', function() {
    let targetPhone = $(this).data('phone');
    if (!forwardMessageId) return;

    let btn = $(this);
    btn.prop('disabled', true).css('opacity', '0.6');

    $.ajax({
        url: window.chatConfig.forwardUrl,
        type: 'POST',
        data: {
            message_id: forwardMessageId, // The Meta or local database message ID string
            target_phone: targetPhone
        },
        success: function(res) {
            if (res.success) {
                $('#forwardModal').hide();
            } else {
                alert(res.error || "Forward execution failed.");
            }
        },
        error: function() {
            alert("Network connectivity error executing forward transaction.");
        },
        complete: function() {
            btn.prop('disabled', false).css('opacity', '1');
            forwardMessageId = null;
        }
    });
});

// ================= EMOJI INJECTIONS =================
$('#emojiBtn').on('click', function(e){
    e.stopPropagation();
    $('#emojiPickerWrapper').toggle();
});
document.querySelector('#emojiPicker').addEventListener('emoji-click', event => {
    $('#chat-reply-input').val($('#chat-reply-input').val() + event.detail.unicode);
});
$(document).on('click', function(){
    $('#emojiPickerWrapper').hide();
});

// ================= AUDIO RECORDING RUNTIMES =================
let activeRecordingStream = null;

$('#voiceNoteBtn').on('click', async function () {
    if (!isRecording) startRecording();
    else stopRecording();
});

async function startRecording() {
    try {
        activeRecordingStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        
        let options = {};
        // 1. Check if native AAC recording is available (Safari/iOS)
        if (MediaRecorder.isTypeSupported('audio/aac')) {
            options = { mimeType: 'audio/aac' };
        } else if (MediaRecorder.isTypeSupported('audio/mp4')) {
            // 2. Fallback to MP4 audio container (Widely supported across Chromium/Windows)
            options = { mimeType: 'audio/mp4;codecs=mp4a.40.2' };
        } else {
            // 3. Fallback to default browser webm container if absolutely forced
            options = { mimeType: 'audio/webm;codecs=opus' };
        }

        mediaRecorder = new MediaRecorder(activeRecordingStream, options);
        audioChunks = [];

        mediaRecorder.ondataavailable = e => {
            if (e.data.size > 0) audioChunks.push(e.data);
        };

        mediaRecorder.onstop = function () {
            // Build the blob using the definitive hardware mimeType
            const blob = new Blob(audioChunks, { type: mediaRecorder.mimeType });
            const localUrl = URL.createObjectURL(blob);
            
            let formData = new FormData();
            
            // Map structural files dynamically to expected extensions
            let fileExt = 'mp4';
            if (mediaRecorder.mimeType.includes('aac')) fileExt = 'aac';
            if (mediaRecorder.mimeType.includes('webm')) fileExt = 'webm';
            
            formData.append('whatsapp_audio', blob, 'voicenote.' + fileExt);
            formData.append('phone_number', window.chatConfig.activePhone);

            let micIcon = $('#voiceNoteBtn i');
            micIcon.removeClass('bi-mic').addClass('bi-hourglass-split');

            $.ajax({
                url: window.chatConfig.sendAudioUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    if (res.success) {
                        let html = `
                            <div style="display:flex; justify-content:flex-end; margin:8px 0;">
                                <div style="background:#e7ffdb; padding:10px; border-radius:8px; max-width:70%;">
                                    <audio controls src="${res.file_url || localUrl}" style="max-width:100%;"></audio>
                                    <div style="font-size:10px; text-align:right; color:#747474; margin-top:4px;">${escapeHtml(res.timestamp || '')}</div>
                                </div>
                            </div>
                        `;
                        $('#chat-stream-box').append(html);
                        scrollBottom();
                    } else {
                        alert("Meta Sync Failure: " + (res.error || "Verification blocked."));
                        console.error("Meta structural dump details:", res.details);
                    }
                },
                error: function() {
                    alert("System layout processing transmission timeout error.");
                },
                complete: function() {
                    micIcon.removeClass('bi-hourglass-split').addClass('bi-mic');
                }
            });
        };

        mediaRecorder.start();
        isRecording = true;
        $('#voiceNoteBtn i').removeClass('bi-mic').addClass('bi-stop-fill').css('color', '#ea0038');
    } catch (err) {
        alert("Microphone capture permissions rejected.");
    }
}

function stopRecording() {
    if (mediaRecorder && isRecording) {
        mediaRecorder.stop();
        isRecording = false;
        $('#voiceNoteBtn i').removeClass('bi-stop-fill').addClass('bi-mic').css('color', '#54656f');
        
        if (activeRecordingStream) {
            activeRecordingStream.getTracks().forEach(track => track.stop());
            activeRecordingStream = null;
        }
    }
}
JS;

$this->registerJs($script);
?>