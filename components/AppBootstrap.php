<?php

namespace app\components;

use yii\base\BootstrapInterface;

class AppBootstrap implements BootstrapInterface
{
    public function bootstrap($app)
    {
        // 1. Load dynamic params from DB
        $dynamicParams = SocialAccountConfig::load();

        // 2. Overwrite Yii::$app->params
        $app->params = array_merge($app->params, $dynamicParams);
    }
}