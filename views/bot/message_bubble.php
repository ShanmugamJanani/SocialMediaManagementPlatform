<?php
use yii\helpers\Html;
use yii\helpers\Url;

$isInbound = $msg->direction === 'inbound';
$body = trim($msg->message_body);

// Clean representation for text preview in the reply box
$previewText = ($msg->type === 'sticker') ? '[Sticker]' : 
              (($msg->type === 'image') ? '[Image]' : 
              (($msg->type === 'video') ? '[Video]' : 
              (($msg->type === 'audio') ? '[Voice Note]' : Html::encode($body))));
?>

<div class="msg-mobile-row" style="margin-bottom: 8px; display: flex; width: 100%; justify-content: <?= $isInbound ? 'flex-start' : 'flex-end' ?>; padding-left: 10px; padding-right: 10px;">
    
    <div class="msg-bubble-container" 
         onclick="handleMobileBubbleTap(event, this)"
         data-msg-id="<?= $msg->id ?>" 
         data-msg-meta_message_id = "<?= $msg->meta_message_id ?>" 
         data-msg-text="<?= htmlentities($msg->message_body) ?>"
         style="position: relative; max-width: 75%; min-width: 90px; padding: 8px 12px 20px 12px; border-radius: 8px; box-shadow: 0 1px 1px rgba(0,0,0,0.13); cursor: pointer; user-select: none;
                background-color: <?= $isInbound ? '#ffffff' : '#e7ffdb' ?>;
                border-top-<?= $isInbound ? 'left' : 'right' ?>-radius: 0;">

        <?php if ($msg->meta_id_of_parent_msg != 'New Message' || !empty($msg->reply_preview_text)): ?>
            <div style="
                border-left: 3px solid #00a884;
                padding-left: 8px;
                margin-bottom: 6px;
                background: rgba(0,0,0,0.03);
                border-radius: 4px;
                font-size: 12px;
                color: #555;
            ">
        <div style="font-weight: bold; font-size: 11px; color:#00a884;"> Replying to </div>
            <div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                <?= Html::encode($msg->reply_preview_text ?? '[message]') ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($msg->type === 'sticker' && !empty($body) && $body !== '[STICKER]'): ?>
            <img src="<?= Html::encode($body) ?>" style="max-width: 130px; border-radius: 4px; display: block;">

        <?php elseif (($msg->type === 'image' || substr($body, -5) === '.webp') && !empty($body) && $body !== '[IMAGE]'): ?>
            <img src="<?= Html::encode($body) ?>" style="max-width: 100%; border-radius: 6px; display: block; margin-bottom: 2px;">

        <?php elseif ($msg->type === 'video' && !empty($body) && $body !== '[VIDEO]'): ?>
            <video controls style="max-width: 100%; border-radius: 6px; display: block;">
                <source src="<?= Html::encode($body) ?>" type="video/mp4">
            </video>

        <?php elseif (($msg->type === 'audio' || substr($body, -4) === '.ogg') || $msg->type === 'voice_note'): ?>
            <div style="display: flex; align-items: center; gap: 8px; min-width: 220px; padding-top: 4px;">
                <span style="font-size: 18px;">🎙️</span>
                <audio controls style="width: 100%; height: 32px; display: block;">
                    <source src="<?= Html::encode($body) ?>" type="audio/ogg; codecs=opus">
                    <source src="<?= Html::encode(str_replace('.ogg', '.mp3', $body)) ?>" type="audio/mpeg">
                </audio>
            </div>

        <?php elseif (isset($msg->type) && $msg->type === 'document' && !empty($body)): ?>
            <?php 
                // Detect the true file extension from the local storage path string
                $extension = strtolower(pathinfo($body, PATHINFO_EXTENSION));
                
                // Set custom visual styles depending on file type
                $icon = '📄'; 
                $iconColor = '#6c757d'; 
                $fileTypeName = 'Document';

                if ($extension === 'pdf') {
                    $icon = '📕'; $iconColor = '#dc3545'; $fileTypeName = 'PDF Document';
                } elseif (in_array($extension, ['doc', 'docx'])) {
                    $icon = '📘'; $iconColor = '#0d6efd'; $fileTypeName = 'Word Document';
                } elseif (in_array($extension, ['xls', 'xlsx'])) {
                    $icon = '📗'; $iconColor = '#198754'; $fileTypeName = 'Excel Sheet';
                } elseif (in_array($extension, ['ppt', 'pptx'])) {
                    $icon = '📙'; $iconColor = '#fd7e14'; $fileTypeName = 'PowerPoint';
                }
            ?>
            
            <div style="display: flex; align-items: center; padding: 8px; min-width: 220px; background: rgba(0,0,0,0.03); border-radius: 6px; border-left: 4px solid <?= $iconColor ?>;">
                <span style="font-size: 26px; margin-right: 12px; margin-left: 4px;"><?= $icon ?></span>
                <div style="word-break: break-all; flex-grow: 1;">
                    <span style="font-size: 11px; text-transform: uppercase; color: #777; font-weight: bold; display: block; margin-bottom: 1px;">
                        <?= $fileTypeName ?>
                    </span>
                    <strong style="font-size: 13px; color: #222; display: block; max-width: 170px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 3px;">
                        <?= Html::encode(basename($body)) ?>
                    </strong>
                    <a href="<?= Url::to($body, true) ?>" download="<?= Html::encode(basename($body)) ?>" target="_blank" style="color: #007bff; text-decoration: none; font-size: 12px; font-weight: bold; display: flex; align-items: center; gap: 2px;">
                        📥 Download File
                    </a>
                </div>
            </div>
    

        <?php else: ?>
            <span style="font-size: 14.5px; line-height: 1.4; color: #111b21; word-break: break-word; display: block;">
                <?= Html::encode($body) ?>
            </span>
        <?php endif; ?>

        <small style="font-size: 10px; color: #667781; position: absolute; bottom: 3px; right: 7px; display: flex; align-items: center; gap: 3px;">
            <?= date('g:i A', strtotime($msg->created_at)) ?>
            <?php if (!$isInbound): ?>
                <span style="color: #53bdeb; font-size: 10px; font-weight: bold;">✓✓</span>
            <?php endif; ?>
        </small>
    </div>
</div>