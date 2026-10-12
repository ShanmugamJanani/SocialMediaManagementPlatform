<?php
use yii\helpers\Html;
use yii\widgets\ActiveForm;

$this->title = 'Channel Customisation';

// Branding / Banner URL
$brandingSettings = $channel->getBrandingSettings();
$image = $brandingSettings ? $brandingSettings->getImage() : null;
$currentBanner = $image ? $image->getBannerExternalUrl() : null;

// SVG Fallback Placeholders
$defaultBanner = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='900' height='250' viewBox='0 0 900 250'><rect width='100%' height='100%' fill='%23e9ecef'/><text x='50%' y='50%' font-family='sans-serif' font-size='24' fill='%236c757d' dominant-baseline='middle' text-anchor='middle'>No Banner Uploaded</text></svg>";
?>

<!-- Page Container -->
<div class="container-fluid bg-light min-vh-100 py-4 px-3 px-md-5">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                <i class="bi bi-sliders text-primary"></i> <?= Html::encode($this->title) ?>
            </h2>
            <p class="text-secondary mb-0">Manage your YouTube channel branding, description, keywords, and regional settings.</p>
        </div>
        <div>
            <a href="https://studio.youtube.com" target="_blank" class="btn btn-outline-dark rounded-pill px-3 shadow-sm border-2 fw-semibold">
                <i class="bi bi-box-arrow-up-right me-1"></i> Open Studio
            </a>
        </div>
    </div>

    <?php $form = ActiveForm::begin([
        'id' => 'customize-form',
        'options' => ['enctype' => 'multipart/form-data']
    ]); ?>

    <!-- Two-Column Layout (65% / 35%) -->
    <div class="row g-4 mb-5">
        
        <!-- LEFT COLUMN (65%) -> Basic Information Card -->
        <div class="col-lg-8">
            <div class="card border-1 border-light-subtle shadow-sm rounded-4 p-4 bg-white h-100">
                
                <!-- Card Title -->
                <div class="d-flex align-items-center gap-2 mb-4 pb-3 border-bottom">
                    <span class="bg-primary-subtle text-primary p-2 rounded-3 d-inline-flex">
                        <i class="bi bi-file-text-fill fs-5"></i>
                    </span>
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Basic Information</h5>
                        <small class="text-muted">Set up your channel metadata to help viewers find your content.</small>
                    </div>
                </div>

                <!-- 1. Channel Description -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label fw-semibold text-dark mb-0 d-flex align-items-center gap-1">
                            <i class="bi bi-text-paragraph text-muted"></i> Channel Description
                        </label>
                        <span class="badge bg-light text-secondary border fw-normal" id="char-count">0 / 1000</span>
                    </div>

                    <?= $form->field($model, 'description', ['template' => '{input}{error}'])->textarea([
                        'rows' => 5,
                        'id' => 'channel-description-field',
                        'class' => 'form-control rounded-3 p-3 bg-light-subtle border',
                        'placeholder' => 'Tell viewers what your channel is about, what content you publish, and when you upload...',
                        'maxlength' => 1000
                    ]) ?>
                </div>

                <!-- 2. Channel Keywords -->
                <div class="mb-4">
                    <div class="d-flex align-items-center gap-1 mb-2">
                        <label class="form-label fw-semibold text-dark mb-0">
                            <i class="bi bi-tags-fill text-muted"></i> Channel Keywords
                        </label>
                        <i class="bi bi-info-circle text-muted" data-bs-toggle="tooltip" title="Type a keyword and press Enter or comma to add a tag."></i>
                    </div>

                    <div class="form-control rounded-3 p-2 bg-light-subtle border d-flex flex-wrap gap-2 align-items-center" id="tags-container">
                        <input type="text" id="tag-input" class="border-0 bg-transparent flex-grow-1 shadow-none" placeholder="Add a tag and press Enter..." style="outline: none;">
                    </div>

                    <?= $form->field($model, 'keywords', ['template' => '{input}{error}'])->hiddenInput([
                        'id' => 'real-keywords-field'
                    ]) ?>
                    
                    <div class="form-text text-muted small mt-1">Separate keywords with commas or press Enter.</div>
                </div>

                <!-- 3. Country Selector -->
                <div class="mb-2">
                    <div class="d-flex align-items-center gap-1 mb-2">
                        <label class="form-label fw-semibold text-dark mb-0">
                            <i class="bi bi-globe2 text-muted"></i> Country / Region
                        </label>
                        <i class="bi bi-info-circle text-muted" data-bs-toggle="tooltip" title="Select the primary location where your channel operates."></i>
                    </div>

                    <?= $form->field($model, 'country', ['template' => '{input}{error}'])->textInput([
                        'class' => 'form-control rounded-3 p-3 bg-light-subtle border text-uppercase',
                        'placeholder' => 'e.g. US, IN, GB, CA',
                        'maxlength' => 2
                    ]) ?>
                    <div class="form-text text-muted small mt-1">Enter your 2-letter ISO country code.</div>
                </div>

            </div>
        </div>

        <!-- RIGHT COLUMN (35%) -> Branding Card -->
        <div class="col-lg-4">
            <div class="card border-1 border-light-subtle shadow-sm rounded-4 p-4 bg-white h-100">
                
                <!-- Card Title -->
                <div class="d-flex align-items-center gap-2 mb-4 pb-3 border-bottom">
                    <span class="bg-primary-subtle text-primary p-2 rounded-3 d-inline-flex">
                        <i class="bi bi-image-fill fs-5"></i>
                    </span>
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Branding</h5>
                        <small class="text-muted">Channel visual identity</small>
                    </div>
                </div>

                <!-- Banner Image Area -->
                <div class="mb-3">
                    <label class="form-label fw-semibold text-dark mb-2">Banner Image</label>
                    
                    <div id="banner-dropzone" class="border border-2 border-dashed rounded-4 p-3 text-center bg-light-subtle position-relative overflow-hidden">
                        <div class="position-relative mb-3">
                            <img id="banner-preview" src="<?= Html::encode($currentBanner ?: $defaultBanner) ?>" class="img-fluid rounded-3 w-100 shadow-sm border object-fit-cover" style="max-height: 140px;" alt="Banner Preview">
                        </div>

                        <div class="mb-3">
                            <i class="bi bi-cloud-arrow-up-fill text-primary fs-1 mb-2 d-block"></i>
                            <h6 class="fw-semibold text-dark mb-1">Drag & Drop Banner Here</h6>
                            <p class="text-muted small mb-0">or click below to browse files</p>
                        </div>

                        <label for="banner-upload-input" class="btn btn-outline-primary btn-sm rounded-pill px-4 fw-semibold border-2">
                            <i class="bi bi-folder2-open me-1"></i> Browse Banner
                        </label>

                        <?= $form->field($model, 'banner_image', ['template' => '{input}{error}', 'options' => ['class' => 'd-none']])
                            ->fileInput([
                                'id' => 'banner-upload-input',
                                'accept' => 'image/png, image/jpeg',
                                'onchange' => 'handleFilePreview(this.files, "banner-preview")'
                            ]) ?>
                    </div>
                </div>

                <!-- Upload Guidance Notes -->
                <div class="bg-light rounded-3 p-3 border mt-auto">
                    <h6 class="fw-bold text-dark small mb-2 d-flex align-items-center gap-1">
                        <i class="bi bi-check-circle-fill text-success"></i> Image Specifications
                    </h6>
                    <ul class="list-unstyled text-muted small mb-0 d-flex flex-column gap-1">
                        <li><i class="bi bi-dot"></i> Banner (Minimum size): <strong>2048 × 1152 px</strong></li>
                        <li><i class="bi bi-dot"></i> Max file size: <strong>6 MB </strong></li>
                        <li><i class="bi bi-dot"></i> Formats supported: <strong>PNG, JPG</strong></li>
                    </ul>
                </div>

            </div>
        </div>

    </div>

    <!-- BOTTOM ACTION BAR -->
    <div class="fixed-bottom bg-white border-top shadow-lg py-3 px-4 px-md-5">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <span class="text-muted small d-none d-sm-inline-block">
                <i class="bi bi-shield-check text-success me-1"></i> All changes are securely synchronized with Google APIs.
            </span>
            <div class="d-flex gap-2 ms-auto">
                <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-outline-secondary rounded-pill px-4 fw-semibold border-2']) ?>
                <?= Html::submitButton('<i class="bi bi-check2-circle me-1"></i> Save Changes', [
                    'class' => 'btn btn-primary bg-gradient rounded-pill px-4 fw-bold shadow'
                ]) ?>
            </div>
        </div>
    </div>

    <?php ActiveForm::end(); ?>
</div>

<!-- JavaScript Interactivity -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    
    // 1. Tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // 2. Character Counter
    const descField = document.getElementById('channel-description-field');
    const charCount = document.getElementById('char-count');

    if (descField && charCount) {
        function updateCharCount() {
            const currentLength = descField.value.length;
            charCount.textContent = `${currentLength} / 1000`;
            if (currentLength >= 900) {
                charCount.className = 'badge bg-danger-subtle text-danger border fw-normal';
            } else {
                charCount.className = 'badge bg-light text-secondary border fw-normal';
            }
        }
        descField.addEventListener('input', updateCharCount);
        updateCharCount();
    }

    // 3. Keywords Input
    const tagInput = document.getElementById('tag-input');
    const tagsContainer = document.getElementById('tags-container');
    const realKeywordsField = document.getElementById('real-keywords-field');
    let tags = [];

    if (realKeywordsField && realKeywordsField.value) {
        tags = realKeywordsField.value.split(',').map(t => t.trim()).filter(t => t.length > 0);
        renderTags();
    }

    function renderTags() {
        document.querySelectorAll('.tag-badge').forEach(el => el.remove());
        tags.forEach((tag, index) => {
            const badge = document.createElement('span');
            badge.className = 'tag-badge badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2 d-inline-flex align-items-center gap-2';
            badge.innerHTML = `${tag} <i class="bi bi-x-circle-fill text-primary cursor-pointer ms-1" onclick="removeTag(${index})"></i>`;
            tagsContainer.insertBefore(badge, tagInput);
        });
        realKeywordsField.value = tags.join(', ');
    }

    window.removeTag = function(index) {
        tags.splice(index, 1);
        renderTags();
    };

    if (tagInput) {
        tagInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                const value = tagInput.value.replace(',', '').trim();
                if (value && !tags.includes(value)) {
                    tags.push(value);
                    renderTags();
                    tagInput.value = '';
                }
            }
        });
    }

    // 4. Setup Drag and Drop for Banner
    setupDropzone('banner-dropzone', 'banner-upload-input', 'banner-preview');
});

function setupDropzone(dropzoneId, inputId, previewId) {
    const dropzone = document.getElementById(dropzoneId);
    const input = document.getElementById(inputId);

    if (dropzone && input) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('bg-primary-subtle', 'border-primary');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('bg-primary-subtle', 'border-primary');
            }, false);
        });

        dropzone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files.length > 0) {
                input.files = files;
                handleFilePreview(files, previewId);
            }
        });
    }
}

// 5. Dynamic Image Preview Helper
function handleFilePreview(files, previewImgId) {
    if (!files || !files[0]) return;

    const file = files[0];
    const bannerInput = document.getElementById('banner-upload-input');

    // 1. Check File Size (Max 6MB)
    const maxSizeInBytes = 6 * 1024 * 1024;
    if (file.size > maxSizeInBytes) {
        alert('File size exceeds 6 MB. Please select a smaller image.');
        bannerInput.value = ''; // Reset input
        return;
    }

    // 2. Check File Dimensions
    const reader = new FileReader();
    reader.onload = function (e) {
        const img = new Image();
        img.src = e.target.result;

        img.onload = function () {
            const minWidth = 2048;
            const minHeight = 1152;

            if (this.width < minWidth || this.height < minHeight) {
                alert(`Image resolution is too small (${this.width}x${this.height} px).\nYouTube banner must be at least 2048x1152 px.`);
                bannerInput.value = ''; // Reset input
                return;
            }

            // Image passed validation -> Update Preview
            const previewImg = document.getElementById(previewImgId);
            if (previewImg) {
                previewImg.src = e.target.result;
            }
        };
    };
    reader.readAsDataURL(file);
}
</script>