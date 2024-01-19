<?php

if (!defined('PHPUNIT_RUN')) {
	// phpcs:disable PSR1.Files.SideEffects
	define('PHPUNIT_RUN', 1);
	// phpcs:enable
}

require_once __DIR__ . '/../../../lib/base.php';

// Fix for "Autoload path not allowed: .../tests/lib/testcase.php"
\OC::$loader->addValidRoot(OC::$SERVERROOT . '/tests');

// Fix for "Autoload path not allowed: .../solid/tests/testcase.php"
\OC_App::loadApp('solid');

if (!class_exists('PHPUnit_Framework_TestCase')) {
	require_once('PHPUnit/Autoload.php');
}

OC_Hook::clear();
