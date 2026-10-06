<?php

if (!defined('PHPUNIT_RUN')) {
    // phpcs:disable PSR1.Files.SideEffects
    define('PHPUNIT_RUN', 1);
    // phpcs:enable
}

require_once __DIR__ . '/../../../lib/base.php';

OC_App::loadApp('solid');

OC_Hook::clear();
