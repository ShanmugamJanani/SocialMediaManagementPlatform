<?php 
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Facebook Feed Management';
?>

<!-- Native Facebook UI Styles -->
<style>
    body {
        background-color: #f0f2f5;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }
    .fb-feed-container {
        max-width: 1100px;
        margin: 0 auto;
    }
    .fb-card {
        background: #ffffff;
        border-radius: 8px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        border: 1px solid #ced0d4;
        margin-bottom: 16px;
        overflow: hidden;
    }
    .fb-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
    }
    .fb-user-name {
        font-weight: 600;
        color: #050505;
        font-size: 0.9375rem;
        text-decoration: none;
    }
    .fb-user-name:hover {
        text-decoration: underline;
    }
    .fb-post-text {
        font-size: 0.9375rem;
        color: #050505;
        line-height: 1.3333;
        white-space: pre-line;
        cursor: pointer;
    }
    .fb-media-clickable {
        cursor: pointer;
    }
    .fb-media-img {
        width: 100%;
        max-height: 500px;
        object-fit: cover;
    }
    .fb-action-btn {
        color: #65676b;
        font-weight: 600;
        font-size: 0.875rem;
        padding: 8px 12px;
        border-radius: 4px;
        border: none;
        background: transparent;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .fb-action-btn:hover {
        background-color: #f2f2f2;
    }
    .fb-comment-bubble {
        background-color: #f0f2f5;
        border-radius: 18px;
        padding: 8px 12px;
        display: inline-block;
        max-width: 85%;
    }
    .fb-outbound-bubble {
        background-color: #e7f3ff;
        border-radius: 18px;
        padding: 8px 12px;
        display: inline-block;
        max-width: 85%;
    }
    .post-metrics-bar {
        padding: 8px 16px;
        font-size: 0.875rem;
        color: #65676b;
        border-bottom: 1px solid #e5e5e5;
    }
    .create-post-box {
        background: #ffffff;
        border-radius: 8px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        border: 1px solid #ced0d4;
        padding: 12px 16px;
        margin-bottom: 16px;
        cursor: pointer;
    }
    .create-post-input-stub {
        background-color: #f0f2f5;
        border-radius: 20px;
        padding: 8px 16px;
        color: #65676b;
        font-size: 0.9375rem;
    }
</style>

<div class="container-fluid py-4 fb-feed-container">
    <div class="row">
        <!-- Main Feed Timeline -->
        <div class="col-lg-7">
            <!-- Header Bar with Title and Create Post Button -->
            <div class="d-flex align-items-center justify-content-between mb-3">
                <a href="<?= Url::to(['webhook/facebook-index']) ?>" 
                    class="btn btn-sm fw-bold px-3 rounded-pill message-dashboard-btn">
                    <i class="fas fa-comments me-1"></i> Messages
                </a>
                <button type="button" class="btn btn-primary btn-sm fw-bold px-3 rounded-pill" data-bs-toggle="modal" data-bs-target="#createPostModal">
                    + Create New Post
                </button>
            </div>

            <!-- Facebook-Style "What's on your mind?" Input Card -->
            <div class="create-post-box d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#createPostModal">
                <img src="https://ui-avatars.com/api/?name=My+Business&background=1877f2&color=fff" class="fb-avatar" alt="Avatar">
                <div class="create-post-input-stub flex-grow-1">
                    What's on your mind?
                </div>
            </div>

            <?php if (empty($posts)): ?>
                <div class="fb-card p-4 text-center text-muted">
                    <p class="mb-0">No posts found in the feed.</p>
                </div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <?php 
                        $fbPostUrl = "https://facebook.com/" . Html::encode($post->meta_message_id);

                        // 1. Get likes & comments from metrics array (or fall back to 0)
                        $likes = $postMetrics[$post->meta_message_id]['likes'] ?? 0;
                        $commentsCount = $postMetrics[$post->meta_message_id]['comments'] ?? 0;

                        // 2. Read 'liked_by_me' directly from your database column
                        $isLikedByMe = (bool) $post->liked_by_me;
                    ?>

                    <div class="fb-card" id="post-card-<?= Html::encode($post->meta_message_id) ?>">
                        
                        <!-- Header (Avatar + Name + Dropdown Options Menu) -->
                        <div class="p-3 d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <img src="https://ui-avatars.com/api/?name=<?= urlencode($post->sender_name ?? 'MB') ?>&background=1877f2&color=fff" 
                                     class="fb-avatar me-2" alt="Avatar">
                                <div>
                                    <a href="<?= $fbPostUrl ?>" target="_blank" class="fb-user-name d-block">
                                        <?= Html::encode($post->sender_name ?? 'My Business') ?>
                                    </a>
                                    <small class="text-muted">
                                        <?= Yii::$app->formatter->asRelativeTime($post->created_at) ?> · <i class="fas fa-globe-americas"></i>
                                    </small>
                                </div>
                            </div>

                            <!-- Post Deletion -->
                            <div class="dropdown">
                                <button class="btn btn-outline-danger btn-sm delete-post-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-post-id="<?= Html::encode($post->meta_message_id) ?>">
                                    <span class="fas fa-ellipsis-h fs-5">Delete Post</span>
                                </button>
                            </div>
                        </div>

                        <!-- Post Body Text (Clickable to load comments) -->
                        <?php if (!empty($post->message_body)): ?>
                            <div class="px-3 pb-2 fb-post-text load-comments-btn" 
                                 data-post-id="<?= Html::encode($post->meta_message_id) ?>" 
                                 data-sender-name="<?= Html::encode($post->sender_name) ?>">
                                <?= Html::encode($post->message_body) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Post Media Frame (Clickable to load comments) -->
                        <?php if (!empty($post->url)): ?>
                            <?php $mediaPath = Yii::$app->request->baseUrl . '/' . ltrim($post->url, '/'); ?>
                            <div class="w-100 bg-dark text-center fb-media-clickable load-comments-btn" 
                                 data-post-id="<?= Html::encode($post->meta_message_id) ?>" 
                                 data-sender-name="<?= Html::encode($post->sender_name) ?>">
                                <?php if (($post->type ?? '') === 'video'): ?>
                                    <video controls class="w-100" style="max-height: 500px;">
                                        <source src="<?= Html::encode($mediaPath) ?>" type="video/mp4">
                                    </video>
                                <?php else: ?>
                                    <img src="<?= Html::encode($mediaPath) ?>" class="fb-media-img" alt="Facebook Attachment">    
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Total Likes & Comments Count Bar -->
                        <div class="post-metrics-bar d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-thumbs-up text-primary me-1"></i> 
                                <span class="fw-bold"><?= number_format($likes) ?></span> likes
                            </div>
                            <div>
                                <a href="javascript:void(0)" class="text-secondary text-decoration-none load-comments-btn" 
                                   data-post-id="<?= Html::encode($post->meta_message_id) ?>" 
                                   data-sender-name="<?= Html::encode($post->sender_name) ?>">
                                    <span class="fw-bold"><?= number_format($commentsCount) ?></span> comments
                                </a>
                            </div>
                        </div>
        
                        <!-- Action Bar (Button section) -->
                        <div class="px-2 py-1 d-flex justify-content-between align-items-center">
                            <button class="fb-action-btn toggle-like-btn" 
                                    data-post-id="<?= Html::encode($post->meta_message_id) ?>" 
                                    data-liked="<?= $isLikedByMe ? '1' : '0' ?>">
                                <i class="<?= $isLikedByMe ? 'fas text-primary' : 'far' ?> fa-thumbs-up like-icon"></i> 
                                <span class="like-text <?= $isLikedByMe ? 'text-primary' : '' ?>"><?= $isLikedByMe ? 'Liked' : 'Like' ?></span>
                            </button>
                            
                            <button class="fb-action-btn load-comments-btn" 
                                    data-post-id="<?= Html::encode($post->meta_message_id) ?>" 
                                    data-sender-name="<?= Html::encode($post->sender_name) ?>">
                                <i class="far fa-comment"></i> Comment
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Threaded Comment Side Drawer -->
        <div class="col-lg-5">
            <div class="fb-card sticky-top" style="top: 20px;">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-light">
                    <h6 class="fw-bold mb-0" id="comment-pane-title">Select a post to view comments</h6>
                </div>
                <div class="p-3" id="comments-container" style="max-height: 500px; overflow-y: auto;">
                    <p class="text-muted text-center py-4">Click any post image, caption, or comment button to view discussion threads.</p>
                </div>

                <!-- Reply Form -->
                <div class="p-3 border-top bg-white d-none" id="reply-form-wrapper">
                    <form id="reply-form">
                        <input type="hidden" id="target_meta_id" name="target_meta_id">
                        <div class="input-group">
                            <input type="text" id="reply-message" class="form-control rounded-pill me-2" placeholder="Write a public comment reply..." required>
                            <button type="submit" class="btn btn-primary rounded-circle" id="send-reply-btn">
                                <span class="fas fa-paper-plane">Send</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CREATE NEW POST MODAL -->
<div class="modal fade" id="createPostModal" tabindex="-1" aria-labelledby="createPostModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="createPostModalLabel">Create Facebook Post</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="create-post-form" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <textarea id="create-post-message" name="message" class="form-control border-0" rows="4" placeholder="What's on your mind?" style="resize: none; font-size: 1.1rem;"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="create-post-image" class="form-label text-muted small fw-bold">Attach Photo (Optional):</label>
                        <input type="file" id="create-post-image" name="image" class="form-control" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4" id="submit-post-btn">
                        Post to Facebook
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$getCommentsUrl = Url::to(['webhook/get-fb-comments']);
$sendReplyUrl   = Url::to(['webhook/send-fb-post-reply']);
$deletePostUrl  = Url::to(['webhook/delete-fb-post']);
$createPostUrl  = Url::to(['webhook/create-fb-post']);
$toggleLikeUrl = Url::to(['webhook/toggle-fb-like']);

$hideCommentUrl   = Url::to(['webhook/hide-fb-comment']);
$deleteCommentUrl = Url::to(['webhook/delete-fb-comment']);

$js = <<<JS
let activePostId = null;

// Create New Post Submission Handler
$('#create-post-form').on('submit', function(e) {
    e.preventDefault();

    let formData = new FormData(this);
    let submitBtn = $('#submit-post-btn');

    submitBtn.prop('disabled', true).text('Publishing...');

    $.ajax({
        url: '{$createPostUrl}',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            submitBtn.prop('disabled', false).text('Post to Facebook');

            if (response.success) {
                $('#createPostModal').modal('hide');
                $('#create-post-form')[0].reset();
                location.reload();
            } else {
                alert('Error publishing post: ' + (response.error || 'Unknown error occurred.'));
            }
        },
        error: function() {
            submitBtn.prop('disabled', false).text('Post to Facebook');
            alert('A server error occurred while publishing the post.');
        }
    });
});

// Delete Post Action
$(document).on('click', '.delete-post-btn', function(e) {
    e.preventDefault();
    let postId = $(this).data('post-id');

    if (!confirm('Are you sure you want to delete this post from Facebook? This cannot be undone.')) {
        return;
    }

    let card = $('#post-card-' + postId);
    card.css('opacity', '0.5');

    $.post('{$deletePostUrl}', { post_id: postId }, function(response) {
        if (response.success) {
            card.slideUp(300, function() {
                $(this).remove();
            });
            if (activePostId === postId) {
                $('#comments-container').html('<p class="text-muted text-center py-4">Select a post to view comments</p>');
                $('#reply-form-wrapper').addClass('d-none');
            }
        } else {
            card.css('opacity', '1');
            alert('Error deleting post: ' + (response.error || 'Unable to complete deletion request.'));
        }
    });
});

// Load Comments Thread (Fires when Image, Text, Metrics Bar, or Comment Button is clicked)
$(document).on('click', '.load-comments-btn', function(e) {
    // If a link or inner button inside media/text was clicked, ignore bubble propagation
    if ($(e.target).is('a, button, input') && !$(e.target).hasClass('load-comments-btn')) {
        return;
    }

    let postId = $(this).data('post-id');
    let senderName = $(this).data('sender-name');
    
    activePostId = postId;
    $('#target_meta_id').val(postId);
    $('#comment-pane-title').text('Comments on Post');
    $('#reply-form-wrapper').removeClass('d-none');
    $('#comments-container').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>');

    fetchComments(postId);
});

function fetchComments(parentId) {
    $.getJSON('{$getCommentsUrl}', { parentId: parentId }, function(response) {
        if (response.status === 'success') {
            let html = '';
            
            if (!response.comments || response.comments.length === 0) {
                html = '<p class="text-muted text-center py-4">No comments on this post yet.</p>';
            } else {
                // Separate top-level comments and replies
                let topLevelComments = [];
                let repliesMap = {};

                $.each(response.comments, function(index, comment) {
                    let parent = comment.meta_id_of_parent_msg;
                    
                    if (parent && parent !== activePostId) {
                        if (!repliesMap[parent]) {
                            repliesMap[parent] = [];
                        }
                        repliesMap[parent].push(comment);
                    } else {
                        topLevelComments.push(comment);
                    }
                });

                // Helper to render a single comment bubble HTML
                // Helper to render a single comment bubble HTML
                function renderCommentNode(comment, isReply = false) {
                    let isOutbound = comment.direction === 'outbound';
                    let bubbleClass = isOutbound ? 'fb-outbound-bubble' : 'fb-comment-bubble';
                    
                    return `
                        <div class="mb-2 position-relative \${isReply ? 'ms-4 mt-2 border-start ps-2' : ''}">
                            <div class="d-flex align-items-start justify-content-between">
                                <div class="d-flex align-items-start flex-grow-1 me-2" style="min-width: 0;">
                                    <img src="https://ui-avatars.com/api/?name=\${encodeURIComponent(comment.sender_name)}&background=\${isOutbound ? '198754' : '6c757d'}&color=fff" 
                                        class="fb-avatar me-2 flex-shrink-0" style="width:\${isReply ? '24px' : '32px'}; height:\${isReply ? '24px' : '32px'};" alt="Avatar">
                                    
                                    <div class="flex-grow-1" style="min-width: 0;">
                                        <div class="\${bubbleClass}">
                                            <strong>\${comment.sender_name}</strong>
                                            <p class="mb-0 text-break" style="font-size: 0.85rem;">\${comment.message_body}</p>
                                        </div>

                                        <div class="mt-1 px-1">
                                            <small class="text-muted" style="font-size: 0.725rem;">
                                                \${comment.created_at}
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Context Menu Dropdown -->
                                <div class="dropdown flex-shrink-0 align-self-start">
                                    <button class="btn btn-sm btn-light border-0 rounded-circle p-0 d-flex align-items-center justify-content-center" 
                                            type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 24px; height: 24px;">
                                        &#8942;
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                        <li>
                                            <a class="dropdown-item reply-to-comment-btn" href="javascript:void(0)" 
                                            data-comment-id="\${comment.meta_message_id}" 
                                            data-sender-name="\${comment.sender_name}">
                                                <i class="fas fa-reply me-2 text-primary"></i>Reply
                                            </a>
                                        </li>
                                        \${!isOutbound ? `
                                        <li>
                                            <a class="dropdown-item hide-comment-btn" href="javascript:void(0)" 
                                            data-comment-id="\${comment.meta_message_id}">
                                                <i class="fas fa-eye-slash me-2 text-warning"></i>Hide
                                            </a>
                                        </li>
                                        ` : ''}
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item text-danger delete-comment-btn" href="javascript:void(0)" 
                                            data-comment-id="\${comment.meta_message_id}">
                                                <i class="fas fa-trash-alt me-2"></i>Delete
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    `;
                }

                // Loop through top-level comments and append matching sub-replies
                $.each(topLevelComments, function(i, parentComment) {
                    html += renderCommentNode(parentComment, false);
                    
                    let replies = repliesMap[parentComment.meta_message_id] || repliesMap[parentComment.id] || [];
                    if (replies.length > 0) {
                        $.each(replies, function(j, replyComment) {
                            html += renderCommentNode(replyComment, true);
                        });
                    }
                });
            }
            
            $('#comments-container').html(html);
        }
    });
}

// Switch Reply Target
$(document).on('click', '.reply-to-comment-btn', function() {
    let commentId = $(this).data('comment-id');
    let senderName = $(this).data('sender-name');
    
    $('#target_meta_id').val(commentId);
    $('#reply-message').attr('placeholder', 'Replying to ' + senderName + '...').focus();
});

// Hide Comment Action Handler
$(document).on('click', '.hide-comment-btn', function(e) {
    e.preventDefault();
    let commentId = $(this).data('comment-id');

    if (!confirm('Are you sure you want to hide this comment?')) {
        return;
    }

    $.post('{$hideCommentUrl}', { comment_id: commentId }, function(response) {
        if (response.success) {
            // Re-fetch comments to update UI
            fetchComments(activePostId);
        } else {
            alert('Error hiding comment: ' + (response.error || response.message || 'Failed to hide comment on Facebook.'));
        }
    }).fail(function(xhr) {
        alert('Server error occurred while trying to hide the comment.');
    });
});

// Delete Comment Action Handler
$(document).on('click', '.delete-comment-btn', function(e) {
    e.preventDefault();
    let commentId = $(this).data('comment-id');

    if (!confirm('Are you sure you want to delete this comment? This action cannot be undone.')) {
        return;
    }

    $.post('{$deleteCommentUrl}', { comment_id: commentId }, function(response) {
        if (response.status === 'success' || response.success) {
            // Re-fetch comments to update UI
            fetchComments(activePostId);
        } else {
            alert('Error deleting comment: ' + (response.message || response.error || 'Failed to delete.'));
        }
    }).fail(function() {
        alert('Server error occurred while trying to delete the comment.');
    });
});

// Toggle Facebook Like State
$(document).on('click', '.toggle-like-btn', function() {
    let btn = $(this);
    let postId = $.trim(btn.data('post-id'));
    let isLiked = btn.data('liked'); // boolean

    btn.prop('disabled', true);

    $.post('{$toggleLikeUrl}', {
        post_id: postId,
        is_liked: isLiked
    }, function(response) {
        btn.prop('disabled', false);

        if (response.status === 'success') {
            let newState = response.new_state;
            
            btn.data('liked', newState);
            btn.attr('data-liked', newState);

            if (newState) {
                // Liked State (Blue icon & text)
                btn.find('.like-icon').removeClass('far').addClass('fas text-primary');
                btn.find('.like-text').addClass('text-primary').text('Liked');
            } else {
                // Unliked State
                btn.find('.like-icon').removeClass('fas text-primary').addClass('far');
                btn.find('.like-text').removeClass('text-primary').text('Like');
            }
        } else {
            alert('Error toggling like: ' + response.message);
        }
    });
});


// Submit Outbound Reply
$('#reply-form').on('submit', function(e) {
    e.preventDefault();
    
    let targetMetaId = $('#target_meta_id').val();
    let message = $('#reply-message').val();
    let submitBtn = $('#send-reply-btn');

    if (!message.trim()) return;

    submitBtn.prop('disabled', true);

    $.post('{$sendReplyUrl}', {
        target_meta_id: targetMetaId,
        message: message
    }, function(response) {
        submitBtn.prop('disabled', false);

        if (response.status === 'success') {
            $('#reply-message').val('').attr('placeholder', 'Write a public comment reply...');
            $('#target_meta_id').val(activePostId);
            fetchComments(activePostId);
        } else {
            alert('Error sending reply: ' + (response.message || 'Unknown error'));
        }
    });
});
JS;
$this->registerJs($js);
?>