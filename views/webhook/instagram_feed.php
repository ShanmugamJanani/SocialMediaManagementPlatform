<?php
use yii\helpers\Url;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $posts app\models\InstagramChatLog[] */
/* @var $activePost app\models\InstagramChatLog */
/* @var $comments app\models\InstagramChatLog[] */
/* @var $liveLikeCount int */
/* @var $liveCommentCount int */

$this->title = 'Instagram Dashboard';
?>

<style>
.instagram-feed-dashboard {
    display: flex;
    height: calc(100vh - 90px);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    overflow: hidden;
    width: 100%;
}

.posts-sidebar {
    width: 25%;
    min-width: 260px;
    max-width: 340px;
    border-right: 1px solid #dbdbdb;
    background: #fff;
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
}

.feed-work-space {
    flex: 1;
    display: flex;
    background: #fafafa;
    overflow: hidden;
    min-width: 0; /* Prevents flex children from overflowing on window resize */
}

.post-preview-pane {
    flex: 1;
    padding: 20px;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    overflow-y: auto;
    height: 100%;
    box-sizing: border-box;
    min-width: 0;
}

.comments-management-pane {
    width: 30%;
    min-width: 280px;
    max-width: 380px;
    border-left: 1px solid #dbdbdb;
    background: #fff;
    display: flex;
    flex-direction: column;
    height: 100%;
    flex-shrink: 0;
}

/* Tablet & Smaller Screens Adjustment */
@media (max-width: 992px) {
    .posts-sidebar {
        width: 220px;
        min-width: 200px;
    }
    .comments-management-pane {
        width: 280px;
        min-width: 250px;
    }
}

/* Mobile Responsiveness Stack */
@media (max-width: 768px) {
    .instagram-feed-dashboard {
        flex-direction: column;
        height: auto;
        overflow: auto;
    }
    .posts-sidebar, 
    .comments-management-pane {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 100% !important;
        height: 300px;
        border: none;
        border-bottom: 1px solid #dbdbdb;
    }
    .feed-work-space {
        flex-direction: column;
        overflow: visible;
    }
    .post-preview-pane {
        height: auto;
    }
}
</style>

<div class="instagram-feed-dashboard">
    
    <!-- Sidebar Navigation Pane -->
    <div class="posts-sidebar">
        <div class="sidebar-header" style="padding: 15px; border-bottom: 1px solid #dbdbdb; display: flex; justify-content: center; align-items: center; text-align: center;">
            <button
                class="btn btn-primary btn-sm"
                onclick="window.location.href='<?= Url::to(['/webhook/instagram-index']) ?>'"
                style="background: #ffffff; border: 2px solid #000000; font-weight: 600; border-radius: 4px; color: #000000; width: 100%;">
                <i class="bi bi-send"></i> Messages
            </button>
        </div>

        <div class="sidebar-header" style="padding: 15px; border-bottom: 1px solid #dbdbdb; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 600;">Your Feed Posts</h3>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newPostModal" style="background: #0095f6; border: none; font-weight: 600; border-radius: 4px; color: #ffffff;">+ New Post</button>
        </div>
        
        <div class="posts-scroll-list" style="flex: 1; overflow-y: auto; padding: 10px;">
            <div class="row" style="margin: 0; display: flex; flex-wrap: wrap; gap: 8px;">
                <?php foreach ($posts as $post): ?>
                    <?php 
                        $activeMessageId = $activePost ? $activePost->meta_message_id : null;
                        $isActive = ($activeMessageId === $post->meta_message_id);
                    ?>
                    <a href="<?= Url::to(['instagram-feed-index', 'activePostId' => $post->meta_message_id]) ?>" 
                       class="post-grid-item" 
                       style="width: calc(33.33% - 6px); aspect-ratio: 1; position: relative; background: #eee; overflow: hidden; border: <?= $isActive ? '3px solid #0095f6' : '1px solid #dbdbdb' ?>; box-sizing: border-box;">
                        
                        <?php if (!empty($post->url)): ?>
                            <?php $normalizedImgUrl = Yii::$app->request->baseUrl . '/' . ltrim($post->url, '/'); ?>
                            <img src="<?= Html::encode($normalizedImgUrl) ?>" 
                                 alt="Instagram Post Thumbnail" 
                                 style="width: 100%; height: 100%; object-fit: cover; display: block;" />
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #fafafa; font-size: 11px; color: #8e8e8e; text-align: center; padding: 4px; box-sizing: border-box;">
                                <?= Html::encode(mb_strimwidth($post->message_body ?? '', 0, 25, "...")) ?>
                            </div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Workspace Viewport Area -->
    <div class="feed-work-space">
        <?php if ($activePost): ?>
            <!-- Center Pane: Native Post View Mockup -->
            <div class="post-preview-pane">
                <div class="instagram-card" style="width: 100%; max-width: 480px; background: #fff; border: 1px solid #dbdbdb; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden;">
                    
                    <!-- Native Post Header (Account Info + Options Menu Trigger) -->
                    <div class="card-owner" style="padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #efefef;">
                        <div style="display: flex; align-items: center;">
                            <div style="margin-right: 10px;">
                                <?php if (!empty($pic)): ?>
                                    <img src="<?= Html::encode($pic) ?>" alt="Instagram Profile" class="rounded-circle shadow-sm" style="width: 32px; height: 32px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle d-flex align-items-center justify-content-center text-primary fw-bold shadow-sm" style="width: 32px; height: 32px; font-size: 0.9rem; background: linear-gradient(135deg, #e7f1ff 0%, #d0e2ff 100%);">
                                        <?= strtoupper(substr($name ?: 'F', 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <span style="font-weight: 600; font-size: 14px; color: #262626; display: block; line-height: 1.2;"><?= Html::encode($name) ?></span>
                                <span style="font-size: 11px; color: #8e8e8e;"><?= date('M d, Y', strtotime($activePost->created_at)) ?></span>
                            </div>
                        </div>

                        <!-- Post Level Options Menu (Native Dropdown) -->
                        <div class="dropdown">
                            <button class="btn btn-link text-dark p-0" type="button" id="postOptionsMenu" data-bs-toggle="dropdown" aria-expanded="false" style="border: none; background: transparent; text-decoration: none;">
                                <i class="bi bi-three-dots" style="font-size: 20px; color: #262626;"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="postOptionsMenu" style="min-width: 200px; border-radius: 8px; border: 1px solid #dbdbdb; padding: 4px 0;">
                                <li>
                                    <a class="dropdown-item text-danger font-weight-bold" href="javascript:void(0)" onclick="deletePost('<?= $activePost->meta_message_id ?>')" style="padding: 10px 16px; font-size: 13px; font-weight: 600;">
                                        <i class="bi bi-trash me-2"></i>Delete
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider" style="margin: 2px 0;"></li>
                                <li>
                                    <a class="dropdown-item"
                                        href="javascript:void(0)"
                                        onclick="toggleCommenting('<?= $activePost->meta_message_id ?>', '<?= $activePost->enabled_comments ?>')"
                                        style="padding: 10px 16px; font-size: 13px; color: #262626;">

                                            <?php if ($activePost->enabled_comments): ?>
                                                <i class="bi bi-chat-slash me-2"></i>Turn off commenting
                                            <?php else: ?>
                                                <i class="bi bi-chat me-2"></i>Turn on commenting
                                            <?php endif; ?>

                                        </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="javascript:void(0)" onclick="copyPostLink('<?= \yii\helpers\Html::encode($permalink) ?>')" style="padding: 10px 16px; font-size: 13px; color: #262626;">
                                        <i class="bi bi-link-45deg me-2"></i>Copy link
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Post Media Container -->
                    <?php if (!empty($activePost->url)): ?>
                        <div class="card-media" style="width: 100%; max-height: 380px; background: #000; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                            <img src="<?= Html::encode(Yii::$app->request->baseUrl . '/' . ltrim($activePost->url, '/')) ?>" 
                                 style="width: 100%; height: 100%; max-height: 380px; object-fit: contain; display: block;" />
                        </div>
                    <?php endif; ?>

                    <!-- Action Bar (Likes & Comments) -->
                    <div class="card-actions" style="padding: 10px 16px; border-bottom: 1px solid #efefef; display: flex; align-items: center; gap: 16px;">
                        <button class="btn btn-link p-0 text-dark like-btn" onclick="togglePostLike('<?= $activePost->meta_message_id ?>', this)" style="border:none; background:none; cursor: pointer; line-height: 1;">
                            <i class="bi bi-heart" style="font-size: 22px;"></i>
                        </button>
                        <label for="commentText" style="cursor: pointer; margin: 0; line-height: 1;">
                            <i class="bi bi-chat" style="font-size: 22px; color: #262626;"></i>
                        </label>
                    </div>

                    <!-- Post Meta Info -->
                    <div class="card-meta" style="padding: 12px 16px;">
                        <div style="font-weight: 600; font-size: 14px; color: #262626;"><?= number_format($liveLikeCount) ?> likes</div>
                        <div style="margin-top: 6px; font-size: 13px; line-height: 1.4; color: #262626;">
                            <span style="font-weight: 600; margin-right: 6px;"><?= Html::encode($name) ?></span>
                            <?= Html::encode($activePost->message_body) ?>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Right Side: Comments Management Stream -->
            <div class="comments-management-pane">
                <div style="padding: 16px; border-bottom: 1px solid #dbdbdb; font-weight: 600; font-size: 16px; color: #262626;">
                    Comments (<?= number_format($liveCommentCount) ?>)
                </div>

                <div id="commentsContainer" style="flex: 1; overflow-y: auto; padding: 16px;">
                    <?php if (empty($comments)): ?>
                        <div style="text-align: center; color: #8e8e8e; font-size: 14px; margin-top: 40px;">No comments on this post yet.</div>
                    <?php else: ?>
                        <?php 
                        // 1. Build an associative lookup array indexed by meta_message_id
                        $commentTree = [];
                        $lookup = [];

                        foreach ($comments as $comment) {
                            $lookup[$comment->meta_message_id] = [
                                'model' => $comment,
                                'children' => []
                            ];
                        }

                        // 2. Link child nodes to their respective parent nodes using meta_id_of_parent_msg
                        foreach ($lookup as $id => &$node) {
                            $parentId = trim((string)$node['model']->meta_id_of_parent_msg);

                            if (isset($lookup[$parentId])) {
                                $lookup[$parentId]['children'][] = &$node;
                            } else {
                                $commentTree[] = &$node; // Top-level comment (parent is post ID)
                            }
                        }
                        unset($node);

                        // 3. Recursive inline function to render parent and nested child comments
                        function renderCommentTree($nodes, $activePost, $depth = 0) {
                            foreach ($nodes as $node) {
                                $comment = $node['model'];
                                $children = $node['children'];
                                $isReply = ($depth > 0);
                                $indentStyle = $isReply ? 'margin-left: ' . ($depth * 20) . 'px; border-left: 2px solid #efefef; padding-left: 8px;' : '';
                                ?>
                                <div class="comment-item" id="comment-<?= $comment->id ?>" style="margin-bottom: 14px; <?= $indentStyle ?>">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                        <div>
                                            <span style="font-weight: 600; font-size: 13px; color: #262626; margin-right: 6px;">
                                                <?= Html::encode($comment->commenter_username ?? 'User') ?>
                                            </span>
                                            <span style="font-size: 13px; color: #262626;"><?= Html::encode($comment->message_body) ?></span>
                                        </div>
                                        <button class="btn btn-sm btn-link text-danger p-0 ms-2" onclick="deleteComment('<?= $comment->meta_message_id ?>')" title="Delete Comment">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                    <div style="font-size: 11px; color: #8e8e8e; margin-top: 4px; display: flex; gap: 12px;">
                                        <span><?= date('M d, h:i A', strtotime($comment->created_at)) ?></span>
                                        <a href="javascript:void(0)" onclick="setReplyTarget('<?= $comment->meta_message_id ?>', '<?= Html::encode($comment->commenter_username) ?>')" style="color: #8e8e8e; text-decoration: none; font-weight: 600;">Reply</a>
                                    </div>
                                </div>
                                <?php
                                // Recursively render nested child replies
                                if (!empty($children)) {
                                    renderCommentTree($children, $activePost, $depth + 1);
                                }
                            }
                        }

                        // 4. Render the top-level parent comments with their nested children
                        renderCommentTree($commentTree, $activePost);
                        ?>
                    <?php endif; ?>
                </div>

                <!-- Comment Input Footer -->
                <div style="padding: 12px 16px; border-top: 1px solid #dbdbdb; background: #fff;">
                    <div id="replyIndicator" style="display: none; font-size: 12px; color: #0095f6; margin-bottom: 6px; justify-content: space-between; align-items: center;">
                        <span>Replying to <b id="replyUsername"></b></span>
                        <a href="javascript:void(0)" onclick="clearReplyTarget()" style="color: #ed4956; text-decoration: none;">Cancel</a>
                    </div>
                    <?php if($activePost->enabled_comments == 1): ?>
                    <form id="commentForm" onsubmit="submitComment(event)" style="display: flex; gap: 8px;">
                        <input type="hidden" id="targetId" value="<?= $activePost->meta_message_id ?>" />
                        <input type="hidden" id="isReply" value="0" />
                        <input type="text" id="commentText" class="form-control form-control-sm" placeholder="Add a comment..." required style="border-radius: 18px;" />
                        <button type="submit" class="btn btn-sm btn-primary" style="border-radius: 18px; background: #0095f6; border: none; font-weight: 600;">Post</button>
                    </form>
                    <?php endif ?>
                </div>
            </div>
        <?php else: ?>
            <div style="flex: 1; display: flex; align-items: center; justify-content: center; color: #8e8e8e;">
                Select a post from the sidebar to view details and manage comments.
            </div>
        <?php endif; ?>
        </div>
</div>

<!-- Modal: Create New Instagram Post -->
<div class="modal fade" id="newPostModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="font-size: 16px; font-weight: 600;">Create New Instagram Post</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createPostForm" onsubmit="submitNewPost(event)">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 13px; font-weight: 600;">Select Image</label>
                        <input type="file" id="postImage" name="image" class="form-control" accept="image/*" required />
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 13px; font-weight: 600;">Caption</label>
                        <textarea id="postCaption" name="message" class="form-control" rows="3" placeholder="Write a caption..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="publishBtn" style="background: #0095f6; border: none;">Publish Post</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let defaultPostId = '<?= $activePost ? $activePost->meta_message_id : '' ?>';

function deletePost(mediaId) {
    if (!confirm('Are you sure you want to delete this post from Instagram?')) return;

    const formData = new FormData();
    formData.append('media_id', mediaId);
    formData.append('<?= Yii::$app->request->csrfParam ?>', '<?= Yii::$app->request->csrfToken ?>');

    fetch('<?= Url::to(['/webhook/delete-post']) ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.href = '<?= Url::to(['/webhook/instagram-feed-index']) ?>';
        } else {
            alert('Error: ' + (data.error || 'Failed to delete post.'));
            location.reload();
        }
    });
}

function toggleCommenting(mediaId,enabled) {
    const formData = new FormData();
    formData.append('media_id', mediaId);
    formData.append('enable',enabled);

    fetch('<?= Url::to(['/webhook/toggle-comments']) ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to update commenting settings.'));
            location.reload();
        }
    });
}

function copyPostLink(permalink) {
    if (!permalink) {
        alert('Post link is not available.');
        return;
    }

    navigator.clipboard.writeText(permalink)
        .then(() => {
            alert('Post link copied to clipboard!');
        })
        .catch(err => {
            console.error('Failed to copy: ', err);
        });
}

function setReplyTarget(commentId, username) {
    document.getElementById('targetId').value = commentId;
    document.getElementById('isReply').value = '1';
    document.getElementById('replyUsername').innerText = '@' + username;
    document.getElementById('replyIndicator').style.display = 'flex';
    document.getElementById('commentText').focus();
}

function clearReplyTarget() {
    document.getElementById('targetId').value = defaultPostId;
    document.getElementById('isReply').value = '0';
    document.getElementById('replyIndicator').style.display = 'none';
}

function submitComment(e) {
    e.preventDefault();
    const targetId = document.getElementById('targetId').value;
    const isReply = document.getElementById('isReply').value;
    const messageBody = document.getElementById('commentText').value;

    const formData = new FormData();
    formData.append('target_id', targetId);
    formData.append('is_reply', isReply);
    formData.append('message_body', messageBody);
    formData.append('<?= Yii::$app->request->csrfParam ?>', '<?= Yii::$app->request->csrfToken ?>');

    fetch('<?= Url::to(['/webhook/send-comment-reply']) ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('commentText').value = '';
            clearReplyTarget();
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to post comment.'));
        }
    });
}

function deleteComment(commentId) {
    if (!confirm('Are you sure you want to delete this comment?')) return;

    const formData = new FormData();
    formData.append('comment_id', commentId);
    formData.append('<?= Yii::$app->request->csrfParam ?>', '<?= Yii::$app->request->csrfToken ?>');

    fetch('<?= Url::to(['/webhook/delete-comment']) ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to delete comment.'));
        }
    });
}

function togglePostLike(mediaId, btn) {
    const isLiked = btn.classList.contains('liked');
    const endpoint = isLiked ? '<?= Url::to(['/webhook/unlike-post']) ?>' : '<?= Url::to(['/webhook/like-post']) ?>';

    const formData = new FormData();
    formData.append('media_id', mediaId);
    formData.append('<?= Yii::$app->request->csrfParam ?>', '<?= Yii::$app->request->csrfToken ?>');

    fetch(endpoint, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const heartIcon = btn.querySelector('i');
            if (isLiked) {
                btn.classList.remove('liked');
                heartIcon.className = 'bi bi-heart';
                heartIcon.style.color = '';
            } else {
                btn.classList.add('liked');
                heartIcon.className = 'bi bi-heart-fill';
                heartIcon.style.color = '#ed4956';
            }
        }
    });
}

function submitNewPost(e) {
    e.preventDefault();
    const btn = document.getElementById('publishBtn');
    btn.disabled = true;
    btn.innerText = 'Publishing...';

    const formData = new FormData(document.getElementById('createPostForm'));
    formData.append('<?= Yii::$app->request->csrfParam ?>', '<?= Yii::$app->request->csrfToken ?>');

    fetch('<?= Url::to(['/webhook/create-feed-post']) ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Publishing Error: ' + (data.error || 'Unknown error occurred.'));
            btn.disabled = false;
            btn.innerText = 'Publish Post';
        }
    });
}
</script>