<?php

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = $query ? "Search: " . Html::encode($query) : "YouTube Home Feed";
?>

<div class="container my-4">
    <!-- Header / Search Bar -->
    <div class="row justify-content-center mb-4">
        <div class="col-md-8">
            <?= Html::beginForm(['youtube/index'], 'get', ['class' => 'input-group input-group-lg']) ?>
                <?= Html::textInput('q', $query, [
                    'class' => 'form-control',
                    'placeholder' => 'Search YouTube...',
                    'autocomplete' => 'off'
                ]) ?>
                <button class="btn btn-danger" type="submit">
                    🔍 Search
                </button>
            <?= Html::endForm() ?>
        </div>
    </div>

    <h4 class="mb-3"><?= $query ? "Results for: " . Html::encode($query) : "🔥 Trending Videos" ?></h4>

    <!-- Video Feed Grid -->
    <div class="row" id="video-feed">
        <?php if (!empty($videos)): ?>
            <?php foreach ($videos as $video): ?>
                <?php 
                    $snippet = $video->getSnippet();
                    // Handle difference between video ID from listVideos vs searchList
                    $videoId = is_string($video->getId()) ? $video->getId() : $video->getId()->getVideoId();
                    $thumbnail = $snippet->getThumbnails()->getHigh()->getUrl();
                ?>
                <div class="col-12 col-sm-6 col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm border-0 rounded-3">
                        <div class="ratio ratio-16x9">
                            <iframe 
                                src="https://www.youtube.com/embed/<?= $videoId ?>" 
                                title="<?= Html::encode($snippet->getTitle()) ?>" 
                                allowfullscreen 
                                class="rounded-top">
                            </iframe>
                        </div>
                        <div class="card-body">
                            <h6 class="card-title text-truncate" title="<?= Html::encode($snippet->getTitle()) ?>">
                                <?= Html::encode($snippet->getTitle()) ?>
                            </h6>
                            <p class="card-text text-muted small mb-0">
                                📺 <?= Html::encode($snippet->getChannelTitle()) ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <p class="text-muted">No videos found.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Load More Pagination Button -->
    <?php if ($nextPageToken): ?>
        <div class="text-center my-4">
            <a href="<?= Url::to(['youtube/index', 'q' => $query, 'pageToken' => $nextPageToken]) ?>" class="btn btn-outline-danger btn-lg px-5">
                Load More Videos
            </a>
        </div>
    <?php endif; ?>
</div>