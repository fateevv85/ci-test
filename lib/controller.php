<?php
ini_set('error_reporting', E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/' . basename(__FILE__, '.php') . '_error_' . date("Y-m-d") . '.log');

require_once __DIR__ . '/autoload.php';

use CiTest\BranchHandler as BH;

if (isset($_GET['to_queue'])) {
    BH::logSetup(__DIR__ . '/../logs/controller_queue.log');
    BH::log('---START---');
    BH::log('$_REQUEST:');
    BH::log($_REQUEST);
    BH::log('Отправляем запрос в очередь');

    try {
        $publisher = new \CiTest\PublisherWorker();
        $publisher->execute();

        $response = 'Запрос успешно отправлен';
    } catch (Exception $exception) {
        $response = 'Ошибка при отправке';

        BH::log($response);
        BH::log($exception->getCode());
        BH::log($exception->getMessage());
    }

    echo $response;
} elseif (isset($_GET['handle_branch']) && $_POST) {
    BH::logSetup(__DIR__ . '/../logs/controller_handle.log');
    BH::log('---START---');
    BH::log('$_REQUEST:');
    BH::log($_REQUEST);
    BH::log('Запрос на добавление ветки в релиз');

    ['branch_id' => $id, 'action' => $action] = $_POST;

    BH::log('branch_id : ' . $id);
    BH::log('action : ' . $action);

    if ($id !== false && $action) {
        try {
//            throw new Exception('error state');

            $handler = new BH($id, $action);
            $rcBranchName = $handler->handleBranch();
            $message = 'update is successful';
            $requestStatus = 1;
        } catch (Exception $e) {
            $message = $e->getMessage();
            $requestStatus = 0;

            BH::log('Ошибка при обработке запроса:');
            BH::log($e->getMessage());
        }
    } else {
        $message = 'not enough parameters';
        $requestStatus = 0;

        BH::log($message);
    }

    echo json_encode([
        'message' => $message,
        'status' => $requestStatus,
        'rc_branch_name' => $rcBranchName ?? false,
    ]);
} else {
    var_dump('not a post request');
}

BH::log('---END---');
