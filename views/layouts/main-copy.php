<?php

/** @var yii\web\View $this */
/** @var string $content */

use app\assets\AppAsset;
use app\widgets\Alert;
use yii\bootstrap5\Breadcrumbs;
use yii\bootstrap5\Html;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;
use kartik\bs5dropdown\Dropdown;
use kartik\tabs\TabsX;
use kartik\widgets\SideNav;


AppAsset::register($this);

$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1, shrink-to-fit=no']);
$this->registerMetaTag(['name' => 'description', 'content' => $this->params['meta_description'] ?? '']);
$this->registerMetaTag(['name' => 'keywords', 'content' => $this->params['meta_keywords'] ?? '']);
$this->registerLinkTag(['rel' => 'icon', 'type' => 'image/x-icon', 'href' => Yii::getAlias('@web/favicon.ico')]);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100">
<head>
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
</head>
<body class="d-flex flex-column h-100">
<?php $this->beginBody() ?>

<div class="container-fluid h-100">
    <div class="row h-100">
        
        <header id="header" class="col-md-3 col-lg-2 bg-light border-end p-0 vh-100 sticky-top d-flex flex-column">
            <div class="p-3 bg-primary text-white mb-0">
                <h4 class="m-0">Menu</h4>
            </div>
            
            <?php
            $currentRoute = Yii::$app->controller->getRoute();

            $menuItems = [];
            
            if (Yii::$app->user->isGuest) {
                $menuItems[] = ['label' => 'Login', 'url' => ['/form/login']];
            } else {
                $menuItems[] = [
                    'label' => 'Logout (' . Yii::$app->user->identity->fb_username . ')',
                    'url' => ['/form/logout'],
                    'linkOptions' => [
                        'data-method' => 'post', // Required for Yii2 logout
                        'class' => 'text-danger'  // Optional: make it look distinct
                    ]
                ];
            }
            $menuItems[] = ['label' => 'Employee List', 'url' => ['/form/index']];
            $menuItems[] = ['label' => 'Create New Employee', 'url' => ['/form/create']];
            $menuItems[] = ['label' => 'whatsapp', 'url' => ['/webhook/whatsapp-index']];
            $menuItems[] = ['label' => 'Instagram', 'url' => ['/webhook/instagram-index']];
            $menuItems[] = ['label' => 'Change Password', 'url' => ['/form/change-password']];
            /*$menuItems[] = [
                    'label' => 'Master Pages',
                    'items' => [
                        [
                            'label' => 'Location', 
                            'url' => '#',
                            'items' => [
                                ['label' => 'Country', 'url' => ['/master/country']],
                                ['label' => 'State', 'url' => ['/master/state']],
                                ['label' => 'City', 'url' => ['/master/city']],
                            ],
                        ],   
                        //'<div class="dropdown-divider"></div>',
                        [
                            'label' => 'Education', 
                            'url' => '#',
                            'items' => [
                                ['label' => 'Higher Education', 'url' => ['/master/higher-edu']],
                                ['label' => 'Degree', 'url' => ['/master/degree']],
                                ['label' => 'Field of study', 'url' => ['/master/field']],
                                ['label' => 'Institution', 'url' => ['/master/institute']],
                            ],
                        ],
                        //'<div class="dropdown-divider"></div>',
                        ['label' => 'Language', 'url' => ['/master/language']],
                        //'<div class="dropdown-divider"></div>',
                        ['label' => 'Soft Skills', 'url' => ['/master/skill']],
                        //'<div class="dropdown-divider"></div>',
                        ['label' => 'Technical', 'url' => ['/master/technical']],
                        //'<div class="dropdown-divider"></div>',
                        ['label' => 'Editable', 'url' => ['/extension/editable']],
                        //'<div class="dropdown-divider"></div>',
                        [
                            'label' => 'Extension', 
                            'url' => '#',
                            'items' => [
                                ['label' => 'Email', 'url' => ['/extension/validator']],
                                ['label' => 'View Pdf Extension', 'url' => ['/extension/view-pdf']],
                            ],
                        ],
                    ],
            ];*/
            ?>
                <div class="flex-grow-1">
                    <?php 
                        echo SideNav::widget([
                            'type' => SideNav::TYPE_DEFAULT,
                            'encodeLabels' => false,
                            'containerOptions' => ['class' => 'border-0'], 
                            'items' => $menuItems, 
                        ]);
                    ?>

                </div>
            </header>

                

        <div class="col-md-9 col-lg-10 d-flex flex-column p-0">
            <main id="main" class="flex-shrink-0 mb-4" role="main">
                <div class="container-fluid pt-4">
                    <?php if (!empty($this->params['breadcrumbs'])): ?>
                        <?= Breadcrumbs::widget(['links' => $this->params['breadcrumbs']]) ?>
                    <?php endif ?>
                    <?= Alert::widget() ?>
                    <?= $content ?>
                </div>
            </main>

            <footer id="footer" class="mt-auto py-3 bg-light border-top">
                <div class="container-fluid">
                    <div class="row text-muted">
                        <div class="col-md-6 text-center text-md-start">&copy; My Company <?= date('Y') ?></div>
                        <div class="col-md-6 text-center text-md-end"><?= Yii::powered() ?></div>
                    </div>
                </div>
            </footer>
        </div>
    </div>
</div>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
