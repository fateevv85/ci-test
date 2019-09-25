<?php
ini_set('error_reporting', E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/' . basename(__FILE__, '.php') . '_error_' . date("Y-m-d") . '.log');

require_once __DIR__ . '/../lib/autoload.php';

use \CiTest\BranchHandler as BH;

BH::logSetup(__DIR__ . '/../logs/consumer.log');
BH::log('---START---');

$consumer = new \CiTest\ConsumerWorker();
$consumer->listen();

BH::log('---END---');
