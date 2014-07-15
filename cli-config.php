<?php

define('APPLICATION_PATH', realpath(__DIR__));
/* @var $LOADER \Composer\Autoload\ClassLoader */
$LOADER = require_once('vendor/autoload.php');
$CONFIG = require_once('config/config.php');

$app = new Filemon\Application();
return $app->runCli();
