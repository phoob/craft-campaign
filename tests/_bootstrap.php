<?php

define('YII_ENV', 'test');
define('YII_DEBUG', true);

// Project root path
$root = '/Users/ben/Sites/craft3';

// Composer autoloader
require_once $root.'/vendor/autoload.php';

// dotenv?
if (file_exists($root.'/.env')) {
    $dotenv = new Dotenv\Dotenv($root);
    $dotenv->load();
}

// Craft
define('CRAFT_BASE_PATH', $root);
require $root.'/vendor/craftcms/cms/bootstrap/console.php';