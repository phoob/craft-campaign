<?php

use craft\helpers\ArrayHelper;
use craft\services\Config;

$root = '/Users/ben/Sites/craft3';
$srcPath = $root.'/vendor/craftcms/cms/src';

$config = ArrayHelper::merge(
    [
        'components' => [
            'config' => [
                'class' => Config::class,
                'configDir' => __DIR__,
            ],
        ],
    ],
    require $srcPath.'/config/app.php',
    require $srcPath.'/config/app.web.php'
);

return ArrayHelper::merge($config, [
    'class' => craft\web\Application::class,
    'id'=>'craft-test',
    'basePath' => $srcPath
]);
