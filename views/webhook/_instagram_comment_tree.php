<?php
use yii\helpers\Html;

/**
 * @var $comment app\models\InstagramChatLog
 * @var $children array
 */

?>

<div id="comment-node-<?= Html::encode($comment->meta_message_id) ?>" 
     style="margin-bottom:15px;">

    <?= $this->render('insta_comment_bubble', [
        'msg' => $comment,
        'isReply' => !empty($children)
    ]) ?>


    <?php if (!empty($children)): ?>

        <div style="
            margin-left:40px;
            border-left:2px solid #efefef;
            padding-left:12px;
            margin-top:8px;
        ">

            <?php foreach($children as $child): ?>

                <?= $this->render('_instagram_comment_tree',[
                    'comment'=>$child['model'],
                    'children'=>$child['children']
                ]) ?>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>