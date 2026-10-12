<?php
use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var app\models\SocialContact[] $contacts */
/** @var app\models\FacebookChatLog[] $chatLogs */
/** @var app\models\SocialContact|null $activeContact */

$this->title = 'Facebook Messenger Management';

// Index chat logs by meta_message_id for quick parent message lookups
$logsByMetaId = [];
if (!empty($chatLogs)) {
    foreach ($chatLogs as $l) {
        if (!empty($l->meta_message_id)) {
            $logsByMetaId[$l->meta_message_id] = $l;
        }
    }
}
?>

<style>
/* Mobile App Style Context Actions Bar */
.msg-container { position: relative; display: flex; align-items: center; gap: 6px; }
.msg-container .msg-action-pill {
    display: none;
    background: #ffffff;
    border: 1px solid #e0e0e0;
    border-radius: 20px;
    padding: 3px 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.12);
    z-index: 10;
}
.msg-container:hover .msg-action-pill { display: flex; align-items: center; gap: 4px; }

.action-icon-btn {
    border: none;
    background: transparent;
    border-radius: 50%;
    width: 26px;
    height: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 13px;
    color: #555;
    transition: background 0.15s ease;
}
.action-icon-btn:hover { background: #f0f2f5; color: #000; }

/* Quoted Reply Box inside bubble */
.reply-quote-box {
    background: rgba(0, 0, 0, 0.06);
    border-left: 3px solid #0d6efd;
    padding: 6px 10px;
    border-radius: 4px;
    margin-bottom: 6px;
    font-size: 0.82rem;
}
.bg-primary .reply-quote-box {
    background: rgba(255, 255, 255, 0.2);
    border-left-color: #ffffff;
    color: #ffffff;
}

/* Reply Banner active preview above input */
#reply-preview-bar {
    display: none;
    background: #e7f1ff;
    border-left: 4px solid #0d6efd;
}
</style>

<div class="container-fluid mt-3">
    <div class="row" style="height: 84vh;">
        
        <!-- LEFT PANEL: Contacts -->
        <div class="col-md-4 col-lg-3 border-end p-0 bg-white d-flex flex-column" style="height: 100%;">
            <div class="p-3 border-bottom bg-primary text-white">
                <h5 class="m-0 fw-bold"><i class="bi bi-facebook me-2"></i> Facebook Messenger</h5>
            </div>
            <div class="list-group list-group-flush overflow-auto flex-grow-1">
                <?php if (empty($contacts)): ?>
                    <div class="p-3 text-muted text-center">No active Facebook conversations.</div>
                <?php else: ?>
                    <?php foreach ($contacts as $c): ?>
                        <?php $isActive = ($activeContact && $activeContact->id === $c->id); ?>
                        <a href="<?= Url::to(['webhook/facebook-index', 'activeContactId' => $c->id]) ?>" 
                           class="list-group-item list-group-item-action p-3 <?= $isActive ? 'active' : '' ?>">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold <?= $isActive ? 'text-white' : 'text-dark' ?>">
                                    <?= Html::encode($c->username ?? 'Facebook User') ?>
                                </span>
                                <span class="badge bg-primary">FB</span>
                            </div>
                            <small class="<?= $isActive ? 'text-light' : 'text-muted' ?>">
                                PSID: <?= Html::encode($c->platform_uid) ?>
                            </small>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT PANEL: Chat Area -->
        <div class="col-md-8 col-lg-9 d-flex flex-column bg-light p-0" style="height: 100%;">
            <?php if ($activeContact): ?>
                
                <!-- Chat Header -->
                <div class="p-3 border-bottom bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="m-0 fw-bold"><?= Html::encode($activeContact->username ?? 'Facebook User') ?></h6>
                        <small class="text-muted">PSID: <strong><?= Html::encode($activeContact->platform_uid) ?></strong></small>
                    </div>
                    <div>
                        <span class="badge bg-secondary">Mode: <?= Html::encode($activeContact->bot_state ?? 'HUMAN') ?></span>
                    </div>
                </div>

                <!-- Chat Log Stream -->
                <div id="chat-messages-container" class="p-3 overflow-auto flex-grow-1" style="background: #f0f2f5;">
                    <?php if (empty($chatLogs)): ?>
                        <div class="text-center text-muted my-5">No message history for this conversation.</div>
                    <?php else: ?>
                        <?php foreach ($chatLogs as $log): ?>
                            <?php 
                                if (!empty($log->deleted_status)) continue;
                                $isOutbound  = (strtolower($log->direction ?? '') === 'outbound' || strtolower($log->sender_name ?? '') === 'you');
                                $messageBody = $log->message_body ?? $log->message ?? '';
                                $senderLabel = $isOutbound ? 'You' : Html::encode($activeContact->username ?? 'User');
                                $isMedia     = in_array($log->type, ['sticker', 'image', 'media']);

                                // Parent reply check
                                $parentLog = (!empty($log->meta_id_of_parent_msg) && isset($logsByMetaId[$log->meta_id_of_parent_msg])) 
                                    ? $logsByMetaId[$log->meta_id_of_parent_msg] 
                                    : null;
                            ?>
                            
                            <div class="d-flex mb-3 <?= $isOutbound ? 'justify-content-end' : 'justify-content-start' ?>" id="msg-row-<?= $log->id ?>">
                                <div class="msg-container <?= $isOutbound ? 'flex-row-reverse' : 'flex-row' ?>">
                                    
                                    <!-- Message Bubble -->
                                    <div class="<?= $log->type === 'sticker' ? 'p-1' : 'p-3 rounded-3 shadow-sm' ?> <?= $isOutbound ? 'bg-primary text-white' : 'bg-white text-dark' ?>" style="max-width: 380px;">
                                        
                                        <!-- Quoted Parent Message if Reply -->
                                        <?php if ($parentLog): ?>
                                            <div class="reply-quote-box">
                                                <div class="fw-bold" style="font-size: 0.75rem;">
                                                    Replying to <?= (strtolower($parentLog->direction) === 'outbound') ? 'Yourself' : Html::encode($parentLog->sender_name) ?>
                                                </div>
                                                <div class="text-truncate"><?= Html::encode($parentLog->message_body) ?></div>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Message Content (Text vs Sticker/Image) -->
                                        <?php if ($isMedia): ?>
                                            <div class="my-1">
                                                <img src="<?= Html::encode($messageBody) ?>" alt="Sticker/Image" style="max-width: 220px; height: auto; border-radius: 8px; display: block;" />
                                            </div>
                                        <?php else: ?>
                                            <p class="m-0 text-break"><?= nl2br(Html::encode($messageBody)) ?></p>
                                        <?php endif; ?>

                                        <!-- Timestamp -->
                                        <div class="text-end mt-1">
                                            <small class="<?= $isOutbound ? 'text-white-50' : 'text-muted' ?>" style="font-size: 0.72rem;">
                                                <?= Html::encode($log->created_at ?? '') ?>
                                            </small>
                                        </div>
                                    </div>

                                    <!-- Mobile-Style Floating Context Action Pill -->
                                    <div class="msg-action-pill">
                                        <button class="action-icon-btn" title="Reply" onclick="setReplyState('<?= $log->meta_message_id ?>', '<?= addslashes(Html::encode($messageBody)) ?>', '<?= addslashes($senderLabel) ?>')">↩️</button>
                                        <button class="action-icon-btn" title="Forward" onclick="openForwardModal('<?= addslashes(Html::encode($messageBody)) ?>')">↪️</button>
                                    </div>

                                </div>
                            </div>

                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Input Footer with Reply Banner -->
                <div class="bg-white border-top">
                    
                    <!-- Reply Banner Preview -->
                    <div id="reply-preview-bar" class="p-2 px-3 align-items-center justify-content-between">
                        <div class="text-truncate me-2" style="font-size: 0.85rem;">
                            <span class="fw-bold text-primary" id="reply-target-sender">Replying...</span>: 
                            <span id="reply-target-body" class="text-muted"></span>
                        </div>
                        <button type="button" class="btn-close btn-sm" onclick="cancelReply()"></button>
                    </div>

                    <!-- Main Reply Form -->
                    <form id="reply-form" class="p-3 d-flex gap-2">
                        <input type="hidden" name="contact_id" value="<?= $activeContact->id ?>">
                        <input type="hidden" id="parent-meta-id" name="parent_meta_id" value="">
                        
                        <input type="text" id="message-input" name="message" class="form-control" placeholder="Type a response..." required autocomplete="off">
                        <button type="submit" class="btn btn-primary px-4">Send</button>
                    </form>
                </div>

            <?php else: ?>
                <div class="d-flex h-100 align-items-center justify-content-center text-muted">
                    <h5>Select a Facebook conversation on the left to start messaging.</h5>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Forward Modal -->
<div class="modal fade" id="forwardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Forward Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="forward-preview-text" class="alert alert-secondary text-break"></p>
                <div class="mb-3">
                    <label class="form-label fw-bold">Select Target Contact:</label>
                    <select id="forward-target-select" class="form-select">
                        <?php foreach ($contacts as $c): ?>
                            <option value="<?= $c->id ?>"><?= Html::encode($c->username ?? 'Facebook User') ?> (PSID: <?= Html::encode($c->platform_uid) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitForward()">Forward Now</button>
            </div>
        </div>
    </div>
</div>

<script>
let selectedForwardText = '';

document.addEventListener('DOMContentLoaded', function() {
    const chatContainer = document.getElementById('chat-messages-container');
    if (chatContainer) chatContainer.scrollTop = chatContainer.scrollHeight;

    // Dispatch message handler
    const replyForm = document.getElementById('reply-form');
    if (replyForm) {
        replyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const input = document.getElementById('message-input');
            const message = input.value.trim();
            const contactId = replyForm.querySelector('input[name="contact_id"]').value;
            const parentMetaId = document.getElementById('parent-meta-id').value;

            if (!message) return;

            const formData = new FormData();
            formData.append('contact_id', contactId);
            formData.append('message', message);
            if (parentMetaId) {
                formData.append('parent_meta_id', parentMetaId);
            }
            formData.append('<?= Yii::$app->request->csrfParam ?>', '<?= Yii::$app->request->csrfToken ?>');

            fetch('<?= Url::to(['webhook/send-facebook-message']) ?>', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        });
    }
});

// Reply Action Handler
function setReplyState(metaId, body, sender) {
    document.getElementById('parent-meta-id').value = metaId;
    document.getElementById('reply-target-sender').innerText = 'Replying to ' + sender;
    document.getElementById('reply-target-body').innerText = body;
    document.getElementById('reply-preview-bar').style.display = 'flex';
    document.getElementById('message-input').focus();
}

function cancelReply() {
    document.getElementById('parent-meta-id').value = '';
    document.getElementById('reply-preview-bar').style.display = 'none';
}



// Report Action Handler
function reportContact(psid) {
    const reason = prompt('Enter reason for reporting user:');
    if (!reason) return;

    const formData = new FormData();
    formData.append('psid', psid);
    formData.append('reason', reason);
    formData.append('<?= Yii::$app->request->csrfParam ?>', '<?= Yii::$app->request->csrfToken ?>');

    fetch('<?= Url::to(['webhook/report-facebook-contact']) ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message);
        if (data.status === 'success') location.reload();
    });
}

// Forward Modal Handler
function openForwardModal(text) {
    selectedForwardText = text;
    document.getElementById('forward-preview-text').innerText = text;
    const modal = new bootstrap.Modal(document.getElementById('forwardModal'));
    modal.show();
}

function submitForward() {
    const targetContactId = document.getElementById('forward-target-select').value;
    if (!targetContactId || !selectedForwardText) return;

    const formData = new FormData();
    formData.append('target_contact_id', targetContactId);
    formData.append('message_body', selectedForwardText);
    formData.append('<?= Yii::$app->request->csrfParam ?>', '<?= Yii::$app->request->csrfToken ?>');

    fetch('<?= Url::to(['webhook/forward-facebook-message']) ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            bootstrap.Modal.getInstance(document.getElementById('forwardModal')).hide();
        }
    });
}
</script>