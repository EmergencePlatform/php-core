<?php

require(__DIR__ . '/../vendor/autoload.php');

// load core
Site::initialize($_SERVER['SITE_ROOT'], $_SERVER['HTTP_HOST']);

// dispatch request
Site::handleRequest();