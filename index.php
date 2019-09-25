<?php
ini_set('error_reporting', E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/' . basename(__FILE__, '.php') . '_error_' . date("Y-m-d") . '.log');

use CiTest\BranchHandler;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/autoload.php';
include_once __DIR__ . '/lib/bootstrap.php';

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$twig = new \Twig\Environment($loader);
$template = $twig->load('table.twig');

echo $template->render([
    'table_name' => 'Branches',
    'class_name' => 'branches',
    'branch_data' => BranchHandler::getBranches(),
]);

$lastIncrement = BranchHandler::getLastIncrement();

echo $template->render([
    'table_name' => 'Release candidate',
    'class_name' => 'release_candidate',
    'branch_data' => BranchHandler::getReleaseCandidates(),
    'rc_table' => 1,
    'rc_branch_name' => ($lastIncrement) ? "rc_$lastIncrement" : '',
]);
