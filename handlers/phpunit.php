<?php

// bootstrap emergence
require(__DIR__ . '/../vendor/autoload.php');
Site::$debug = true;
Site::initialize($_SERVER['SITE_ROOT'], $_SERVER['HTTP_HOST']);
