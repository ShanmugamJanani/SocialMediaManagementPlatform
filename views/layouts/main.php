<?php

/** @var yii\web\View $this */
/** @var string $content */

use app\assets\AppAsset;
use app\widgets\Alert;
use yii\bootstrap5\Breadcrumbs;
use yii\bootstrap5\Html;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;

AppAsset::register($this);

$this->registerCssFile(
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css'
);

$this->registerCss("
    /* Main navbar menu */
    .navbar-nav .nav-link,
    .navbar-nav .dropdown-toggle {
        font-weight: 600;
        color: #ffffff !important;
        font-size: 15px;
    }

    /* Hover effect */
    .navbar-nav .nav-link:hover,
    .navbar-nav .dropdown-toggle:hover {
        color: #222 !important;
    }

    /* Dropdown background */
    .dropdown-menu {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        box-shadow: 0 4px 12px rgba(0,0,0,.1);
    }

    /* Dropdown items */
    .dropdown-item {
        color: #212529 !important;
        font-weight: 500;
        padding: 8px 15px;
    }

    .dropdown-item:hover {
        background-color: #e9ecef;
        color: #000 !important;
    }

    /* WhatsApp & Instagram icons */
    .dropdown-item i {
        width: 20px;
        text-align: center;
    }

    /* Brand name */
    .navbar-brand {
        font-weight: 700;
        color: #fff !important;
        font-size: 1.2rem;
    }
");

$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag([
    'name' => 'viewport',
    'content' => 'width=device-width, initial-scale=1, shrink-to-fit=no'
]);
$this->registerMetaTag([
    'name' => 'description',
    'content' => $this->params['meta_description'] ?? ''
]);
$this->registerMetaTag([
    'name' => 'keywords',
    'content' => $this->params['meta_keywords'] ?? ''
]);
$this->registerLinkTag([
    'rel' => 'icon',
    'type' => 'image/x-icon',
    'href' => Yii::getAlias('@web/favicon.ico')
]);
?>

<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100">
<head>
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
</head>

<body class="d-flex flex-column min-vh-100">
<?php $this->beginBody() ?>

<?php

$menuItems = [

    [
        'label' => 'Home',
        'url' => ['/home/index']
    ],

    [
        'label' => 'Social Media',
        'items' => [
            [
                'label' => '<i class="bi bi-whatsapp text-success me-2"></i>WhatsApp',
                'url' => ['/webhook/whatsapp-index'],
                'encode' => false,
            ],
            [
                'label' => '<i class="bi bi-instagram text-danger me-2"></i>Instagram',
                'url' => ['/webhook/instagram-feed-index'],
                'encode' => false,
            ], 
            [
                'label' => '<i class="bi bi-facebook text-primary me-2"></i>Facebook',
                'url' => ['/webhook/facebook-feed-index'],
                'encode' => false,
            ],
            [
                'label' => '<i class="bi bi-youtube text-danger me-2"></i>YouTube',
                'url' => ['/youtube/index'],
                'encode' => false,
            ],
        ]
    ],

    /*[
        'label' => 'Master Pages',
        'items' => [

            [
                'label' => 'Location',
                'items' => [
                    ['label' => 'Country', 'url' => ['/master/country']],
                    ['label' => 'State', 'url' => ['/master/state']],
                    ['label' => 'City', 'url' => ['/master/city']],
                ]
            ],

            [
                'label' => 'Education',
                'items' => [
                    ['label' => 'Higher Education', 'url' => ['/master/higher-edu']],
                    ['label' => 'Degree', 'url' => ['/master/degree']],
                    ['label' => 'Field of Study', 'url' => ['/master/field']],
                    ['label' => 'Institution', 'url' => ['/master/institute']],
                ]
            ],

            ['label' => 'Language', 'url' => ['/master/language']],
            ['label' => 'Soft Skills', 'url' => ['/master/skill']],
            ['label' => 'Technical Skills', 'url' => ['/master/technical']],

            [
                'label' => 'Extensions',
                'items' => [
                    ['label' => 'Editable', 'url' => ['/extension/editable']],
                    ['label' => 'Email Validator', 'url' => ['/extension/validator']],
                    ['label' => 'View PDF', 'url' => ['/extension/view-pdf']],
                ]
            ],
        ]
    ],*/
];

if (Yii::$app->user->isGuest) {

    $menuItems[] = [
        'label' => 'Login',
        'url' => ['/form/login']
    ];

} else {

    $menuItems[] = [
        'label' => Yii::$app->user->identity->name,
        'items' => [
            [
                'label' => 'Change Password',
                'url' => ['/form/change-password']
            ],
            [
                'label' => 'Logout',
                'url' => ['/authentication/logout'],
                'linkOptions' => [
                    'data-method' => 'post',
                    'class' => 'text-danger'
                ]
            ],
        ]
    ];
}

NavBar::begin([
    'brandLabel' => '<strong>My Company</strong>',
    'brandUrl' => Yii::$app->homeUrl,
    'options' => [
        'class' => 'navbar navbar-expand-lg shadow-sm',
        'style' => 'background:linear-gradient(90deg,#4f8dfd,#6a5cff);',
    ],
]);

echo Nav::widget([
    'options' => ['class' => 'navbar-nav ms-auto'],
    'encodeLabels' => false,
    'items' => $menuItems,
]);

NavBar::end();

?>

<main class="flex-grow-1">
    <div class="container-fluid mt-4">

        <?php if (!empty($this->params['breadcrumbs'])): ?>
            <?= Breadcrumbs::widget([
                'links' => $this->params['breadcrumbs']
            ]) ?>
        <?php endif ?>

        <?= Alert::widget() ?>

        <?= $content ?>

    </div>
</main>

<footer class="py-3 bg-light border-top mt-auto">
    <div class="container-fluid">
        <div class="row text-muted">

            <div class="col-md-6 text-center text-md-start">
                &copy; My Company <?= date('Y') ?>
            </div>

            <div class="col-md-6 text-center text-md-end">
                <?= Yii::powered() ?>
            </div>

        </div>
    </div>
</footer>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>