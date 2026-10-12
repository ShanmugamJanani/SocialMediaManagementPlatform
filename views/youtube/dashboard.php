<?php

use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $channel Google\Service\YouTube\Channel */
/* @var $videos array Google\Service\YouTube\SearchResult[] */

$this->title = 'YouTube Dashboard';

// Safely pull channel details
$snippet = $channel ? $channel->getSnippet() : null;
$statistics = $channel ? $channel->getStatistics() : null;

$channelTitle = $snippet ? $snippet->getTitle() : 'My Channel';
$channelThumbnail = $snippet ? $snippet->getThumbnails()->getHigh()->getUrl() : '';
$subscriberCount = $statistics ? number_format($statistics->getSubscriberCount()) : 0;
$videoCount = $statistics ? number_format($statistics->getVideoCount()) : 0;
$viewCount = $statistics ? number_format($statistics->getViewCount()) : 0;
?>

<div class="youtube-dashboard">

    <!-- Header Section: Channel Info -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body d-flex align-items-center justify-content-between flex-wrap">
            <div class="d-flex align-items-center">
                <?php if ($channelThumbnail): ?>
                    <img src="<?= Html::encode($channelThumbnail) ?>" 
                         alt="Channel Avatar" 
                         class="rounded-circle me-3" 
                         style="width: 72px; height: 72px; object-fit: cover;">
                <?php endif; ?>
                <div>
                    <h3 class="mb-1"><?= Html::encode($channelTitle) ?></h3>
                    <span class="badge bg-success text-white">Connected</span>
                </div>
            </div>
            
            <div class="mt-3 mt-md-0">
                <?= Html::a(
                    '<i class="glyphicon glyphicon-trash"></i> Disconnect Channel', 
                    Url::to(['disconnect-youtube']), 
                    [
                        'class' => 'btn btn-outline-danger btn-sm',
                        'data' => [
                            'confirm' => 'Are you sure you want to disconnect your YouTube account?',
                            'method' => 'post',
                        ],
                    ]
                ) ?>
            </div>
        </div>
    </div>

    <!-- Statistics Cards Grid -->
    <div class="row mb-4">
        <!-- Subscribers -->
        <div class="col-md-4 mb-3">
            <div class="card border-0 bg-light text-center p-3 shadow-sm">
                <small class="text-muted text-uppercase font-weight-bold">Subscribers</small>
                <h2 class="text-danger mt-2 mb-0"><?= $subscriberCount ?></h2>
            </div>
        </div>

        <!-- Total Views -->
        <div class="col-md-4 mb-3">
            <div class="card border-0 bg-light text-center p-3 shadow-sm">
                <small class="text-muted text-uppercase font-weight-bold">Total Views</small>
                <h2 class="text-danger mt-2 mb-0"><?= $viewCount ?></h2>
            </div>
        </div>

        <!-- Uploaded Videos -->
        <div class="col-md-4 mb-3">
            <div class="card border-0 bg-light text-center p-3 shadow-sm">
                <small class="text-muted text-uppercase font-weight-bold">Uploaded Videos</small>
                <h2 class="text-danger mt-2 mb-0"><?= $videoCount ?></h2>
            </div>
        </div>
    </div>

    <!-- Recent Videos Section -->
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0">Recent Uploads</h5>
            <a href="https://studio.youtube.com" target="_blank" class="btn btn-sm btn-primary">
                Go to YouTube Studio &rarr;
            </a>
        </div>
        
        <div class="card-body">
            <?php if (!empty($videos)): ?>
                <div class="row">
                    <?php foreach ($videos as $video): ?>
                        <?php 
                            $vSnippet = $video->getSnippet();
                            $videoId = $video->getId()->getVideoId();
                            $title = $vSnippet->getTitle();
                            $thumb = $vSnippet->getThumbnails()->getMedium()->getUrl();
                            $publishedAt = date('M d, Y', strtotime($vSnippet->getPublishedAt()));
                        ?>
                        <div class="col-md-4 col-sm-6 mb-4">
                            <div class="card h-100 border">
                                <a href="https://www.youtube.com/watch?v=<?= $videoId ?>" target="_blank">
                                    <img src="<?= Html::encode($thumb) ?>" class="card-img-top" alt="Thumbnail">
                                </a>
                                <div class="card-body d-flex flex-column">
                                    <h6 class="card-title text-truncate" title="<?= Html::encode($title) ?>">
                                        <?= Html::encode($title) ?>
                                    </h6>
                                    <small class="text-muted mt-auto">Published: <?= $publishedAt ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted my-3 text-center">No recent videos found for this channel.</p>
            <?php endif; ?>
        </div>
    </div>

</div>